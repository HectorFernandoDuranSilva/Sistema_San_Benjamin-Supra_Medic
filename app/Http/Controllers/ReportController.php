<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Cliente;
use App\Models\DetalleCompra;
use App\Models\DetalleVenta;
use App\Models\GastoOperativo;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\ReporteGuardado;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use App\Services\AuditLogService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function ventas(Request $request)
    {
        $ventas = Venta::with('cliente', 'usuario')
            ->where(fn ($query) => $query->whereNull('tipo_documento')->orWhere('tipo_documento', '!=', 'nota_entrega'))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha', '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha', '<=', $request->hasta))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->latest('fecha')
            ->paginate(30)
            ->withQueryString();

        return view('reportes.ventas', ['ventas' => $ventas, 'total' => $ventas->sum('total')]);
    }

    public function inventario(Request $request)
    {
        $movimientos = MovimientoInventario::with('producto', 'usuario')
            ->when($request->filled('producto_id'), fn ($q) => $q->where('producto_id', $request->producto_id))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_movimiento', '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_movimiento', '<=', $request->hasta))
            ->latest('fecha_movimiento')
            ->paginate(30)
            ->withQueryString();

        return view('reportes.inventario', [
            'movimientos' => $movimientos,
            'productos' => Producto::orderBy('nombre')->get(),
        ]);
    }

    public function stockBajoPdf()
    {
        $productos = Producto::with('categoria', 'movimientos')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->filter(fn ($producto) => $producto->stock_actual <= (float) $producto->stock_minimo)
            ->values();

        return Pdf::loadView('reportes.stock-bajo-pdf', [
            'productos' => $productos,
            'generadoEn' => now(),
        ])->setPaper('letter')->download('reporte-stock-bajo.pdf');
    }

    public function kardex(Request $request)
    {
        $movimientos = $this->kardexQuery($request)
            ->paginate(40)
            ->withQueryString();

        $movimientos->setCollection($this->attachKardexReferenceData($movimientos->getCollection()));

        return view('reportes.kardex', [
            'movimientos' => $movimientos,
            'productos' => Producto::where('activo', true)->orderBy('nombre')->get(),
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')->get(),
            'clienteSeleccionado' => $request->filled('cliente_id')
                ? Cliente::find($request->integer('cliente_id'))
                : null,
        ]);
    }

    public function kardexPdf(Request $request)
    {
        $pdfLimit = 120;
        $movimientos = $this->kardexQuery($request)->limit($pdfLimit + 1)->get();
        $hayMasMovimientos = $movimientos->count() > $pdfLimit;
        $movimientos = $movimientos->take($pdfLimit);
        $movimientos = $this->attachKardexReferenceData($movimientos);

        return Pdf::loadView('reportes.kardex-pdf', [
            'movimientos' => $movimientos,
            'generadoEn' => now(),
            'hayMasMovimientos' => $hayMasMovimientos,
            'pdfLimit' => $pdfLimit,
            'clienteSeleccionado' => $request->filled('cliente_id')
                ? Cliente::find($request->integer('cliente_id'))
                : null,
        ])->setPaper('letter', 'landscape')->download('kardex-inventario.pdf');
    }

    public function compras(Request $request)
    {
        $compras = Compra::with('proveedor', 'usuario')
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha', '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha', '<=', $request->hasta))
            ->latest('fecha')
            ->paginate(30)
            ->withQueryString();

        return view('reportes.compras', compact('compras'));
    }

    public function documentos(Request $request)
    {
        $data = $this->documentReportData($request, true);

        return view('reportes.documentos', $data);
    }

    public function documentosPdf(Request $request)
    {
        $data = $this->documentReportData($request, false);

        return Pdf::loadView('reportes.documentos-pdf', $data + [
            'generadoEn' => now(),
        ])->setPaper('letter', 'landscape')->download('reporte-notas-facturas.pdf');
    }

    public function notasEntregaPdf(Request $request)
    {
        $data = $this->deliveryNotesReportData($request);

        return Pdf::loadView('reportes.notas-entrega-pdf', $data + [
            'generadoEn' => now(),
        ])->setPaper('letter', 'landscape')->download('notas-entrega-' . $data['mes'] . '.pdf');
    }

    public function documentosExcel(Request $request)
    {
        $data = $this->documentReportData($request, false);
        $spreadsheet = new Spreadsheet();
        $resumen = $spreadsheet->getActiveSheet();
        $resumen->setTitle('Resumen');
        $resumen->fromArray([
            ['Reporte de notas y facturas'],
            ['Período', $data['filtros']['desde'] . ' al ' . $data['filtros']['hasta']],
            ['Nota', 'Edite esta copia solo para revisión contable. Los cambios no modifican los documentos del sistema.'],
            [],
            ['Concepto', 'Total Bs'],
            ['Ventas facturadas'],
            ['Notas de entrega (informativo, no ingresa al balance)'],
            ['Compras facturadas'],
            ['Notas de compra'],
            ['Diferencia (compras - ventas)'],
            ['IVA 13%'],
            ['IT 3% sobre ventas'],
            ['Por pagar (IVA + IT)'],
        ]);
        $resumen->getStyle('A1:B1')->getFont()->setBold(true);
        $resumen->getStyle('A5:B5')->getFont()->setBold(true);
        $resumen->getColumnDimension('A')->setWidth(32);
        $resumen->getColumnDimension('B')->setWidth(92);

        $this->addDocumentSheet($spreadsheet, 'Facturas venta', $data['ventas'], 'cliente');
        $this->addDocumentSheet($spreadsheet, 'Notas venta', $data['notas'], 'cliente');
        $this->addDocumentSheet($spreadsheet, 'Facturas compra', $data['compras'], 'proveedor');
        $this->addDocumentSheet($spreadsheet, 'Notas compra', $data['notasCompras'], 'proveedor');

        $resumen->setCellValue('B6', $this->documentTotalFormula('Facturas venta', $data['ventas']));
        $resumen->setCellValue('B7', $this->documentTotalFormula('Notas venta', $data['notas']));
        $resumen->setCellValue('B8', $this->documentTotalFormula('Facturas compra', $data['compras']));
        $resumen->setCellValue('B9', $this->documentTotalFormula('Notas compra', $data['notasCompras']));
        $resumen->setCellValue('B10', '=B8-B6');
        $resumen->setCellValue('B11', '=B10*13%');
        $resumen->setCellValue('B12', '=B6*3%');
        $resumen->setCellValue('B13', '=B11+B12');
        $resumen->getStyle('B6:B13')->getNumberFormat()->setFormatCode('#,##0.00');
        $resumen->getStyle('B13')->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false)->setForceFullCalc(true)->save('php://output');
        }, 'reporte-notas-facturas.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function balanceMensual(Request $request)
    {
        return view('reportes.balance-mensual', $this->monthlyBalanceData($request));
    }

    public function balanceMensualPdf(Request $request)
    {
        return Pdf::loadView('reportes.balance-mensual-pdf', $this->monthlyBalanceData($request) + [
            'generadoEn' => now(),
        ])->setPaper('letter', 'landscape')->download('balance-mensual.pdf');
    }

    public function balanceMensualExcel(Request $request)
    {
        $data = $this->monthlyBalanceData($request);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumen mensual');
        $sheet->fromArray([
            ['Balance mensual de compras y ventas'],
            ['Período', $data['desde'] . ' al ' . $data['hasta']],
            ['Nota', 'Edite esta copia solo para revisión contable. Los cambios no modifican los documentos del sistema.'],
            [],
            ['Empresa', 'Facturas venta', 'Facturas compra', 'Gastos operativos', 'Diferencia', 'IVA 13%', 'IT 3%', 'Por pagar', 'Resultado comercial'],
        ]);

        $ventasFacturas = $data['ventas']->filter(fn (Venta $venta) => $venta->tipo_documento !== 'nota_entrega')->values();
        $comprasFacturas = $data['compras']->filter(fn (Compra $compra) => $compra->tipo_documento !== 'nota_entrega')->values();
        $this->addDocumentSheet($spreadsheet, 'Facturas venta', $ventasFacturas, 'cliente');
        $this->addDocumentSheet($spreadsheet, 'Facturas compra', $comprasFacturas, 'proveedor');

        foreach ($data['porNombre'] as $item) {
            $row = $sheet->getHighestRow() + 1;
            $sheet->setCellValue('A' . $row, $item['nombre_comercial']);
            $sheet->setCellValue('B' . $row, $this->documentTotalByCompanyFormula('Facturas venta', $ventasFacturas, $row));
            $sheet->setCellValue('C' . $row, $this->documentTotalByCompanyFormula('Facturas compra', $comprasFacturas, $row));
            $sheet->setCellValue('D' . $row, (float) $data['gastosOperativos']->where('nombre_comercial', $item['nombre_comercial'])->sum('importe'));
            $sheet->setCellValue('E' . $row, '=C' . $row . '-B' . $row);
            $sheet->setCellValue('F' . $row, '=E' . $row . '*13%');
            $sheet->setCellValue('G' . $row, '=B' . $row . '*3%');
            $sheet->setCellValue('H' . $row, '=F' . $row . '+G' . $row);
            $sheet->setCellValue('I' . $row, '=B' . $row . '-C' . $row . '-D' . $row);
        }

        $firstDataRow = 6;
        $totalRow = $sheet->getHighestRow() + 1;
        $sheet->setCellValue('A' . $totalRow, 'Total');
        foreach (range('B', 'I') as $column) {
            $sheet->setCellValue($column . $totalRow, '=SUM(' . $column . $firstDataRow . ':' . $column . ($totalRow - 1) . ')');
        }
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->getStyle('A5:I5')->getFont()->setBold(true);
        $sheet->getStyle('A' . $sheet->getHighestRow() . ':I' . $sheet->getHighestRow())->getFont()->setBold(true);
        $sheet->getStyle('B6:I' . $sheet->getHighestRow())->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('H6:H' . $sheet->getHighestRow())->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false)->setForceFullCalc(true)->save('php://output');
        }, 'balance-mensual.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function guardados()
    {
        return view('reportes.guardados', [
            'reportes' => ReporteGuardado::with('usuario')->where('activo', true)->latest()->paginate(20),
        ]);
    }

    public function guardarActual(Request $request)
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:ventas,compras,inventario,kardex,stock_bajo,documentos,balance_mensual'],
            'ruta' => ['required', 'string', 'max:2048'],
            'observacion' => ['nullable', 'string'],
        ]);

        $data['ruta'] = $this->safeReportPath($data['ruta']);
        $data['usuario_id'] = $request->user()->id;

        ReporteGuardado::create($data);

        return back()->with('ok', 'Reporte guardado.');
    }

    public function actualizarGuardado(Request $request, ReporteGuardado $reporte)
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:ventas,compras,inventario,kardex,stock_bajo,documentos,balance_mensual'],
            'ruta' => ['required', 'string', 'max:2048'],
            'observacion' => ['nullable', 'string'],
        ]);

        $before = $reporte->only(['titulo', 'tipo', 'ruta', 'observacion']);
        $data['ruta'] = $this->safeReportPath($data['ruta']);
        $reporte->update($data);
        app(AuditLogService::class)->record(
            $request,
            'actualizo',
            'reportes',
            $reporte,
            $before,
            $reporte->only(array_keys($before)),
            'Reporte guardado actualizado'
        );

        return back()->with('ok', 'Reporte actualizado.');
    }

    public function eliminarGuardado(Request $request, ReporteGuardado $reporte)
    {
        $request->validate(['totp_code' => ['required', 'string', 'max:32']]);
        if ($error = app(\App\Services\TwoFactorAuthenticationService::class)->authorize($request, 'reportes', $reporte, 'Intento de eliminar reporte guardado')) {
            return back()->withErrors(['totp_code' => $error]);
        }

        $before = $reporte->only(['titulo', 'tipo', 'ruta', 'observacion', 'activo']);
        $reporte->update(['activo' => false]);
        app(AuditLogService::class)->record(
            $request,
            'elimino',
            'reportes',
            $reporte,
            $before,
            $reporte->only(array_keys($before)),
            'Reporte guardado desactivado'
        );

        return back()->with('ok', 'Reporte eliminado.');
    }

    private function kardexQuery(Request $request)
    {
        $buscar = trim((string) $request->input('buscar', ''));

        return MovimientoInventario::with('producto', 'usuario')
            ->when($buscar !== '', function ($query) use ($buscar) {
                $term = '%' . $buscar . '%';
                $query->whereHas('producto', fn ($producto) => $producto
                    ->where('codigo', 'like', $term)
                    ->orWhere('nombre', 'like', $term)
                    ->orWhere('marca', 'like', $term)
                    ->orWhere('procedencia', 'like', $term)
                    ->orWhere('medida', 'like', $term));
            })
            ->when($request->filled('producto_id'), fn ($q) => $q->where('producto_id', $request->producto_id))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_movimiento', '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_movimiento', '<=', $request->hasta))
            ->when($request->filled('tipo_movimiento'), fn ($q) => $q->where('tipo_movimiento', $request->tipo_movimiento))
            ->when($request->filled('cliente_id'), fn ($q) => $q
                ->where('referencia_tipo', 'venta')
                ->whereIn(
                    'referencia_id',
                    Venta::where('cliente_id', $request->integer('cliente_id'))->select('id')
                ))
            // The ledger is a chronological journal. Ordering by product first
            // made movements from different products appear out of sequence.
            ->orderByDesc('fecha_movimiento')
            ->orderByDesc('id');
    }

    private function attachKardexReferenceData($movimientos)
    {
        $comprasIds = $movimientos
            ->where('referencia_tipo', 'compra')
            ->pluck('referencia_id')
            ->filter()
            ->unique()
            ->values();
        $ventasIds = $movimientos
            ->whereIn('referencia_tipo', ['venta'])
            ->pluck('referencia_id')
            ->filter()
            ->unique()
            ->values();

        $compras = Compra::with('proveedor')->whereIn('id', $comprasIds)->get()->keyBy('id');
        $ventas = Venta::with('cliente')->whereIn('id', $ventasIds)->get()->keyBy('id');
        $detallesCompra = DetalleCompra::with('lote')
            ->whereIn('compra_id', $comprasIds)
            ->get()
            ->groupBy(fn ($detalle) => $detalle->compra_id . '|' . $detalle->producto_id);
        $detallesVenta = DetalleVenta::with('lotes.lote')
            ->whereIn('venta_id', $ventasIds)
            ->get()
            ->groupBy(fn ($detalle) => $detalle->venta_id . '|' . $detalle->producto_id);

        return $movimientos->map(function ($movimiento) use ($compras, $ventas, $detallesCompra, $detallesVenta) {
            $key = $movimiento->referencia_id . '|' . $movimiento->producto_id;
            $precioUnitario = null;
            $totalLinea = null;
            $lote = null;
            $documento = trim((string) $movimiento->referencia_tipo . ' ' . (string) $movimiento->referencia_id);
            $tercero = null;

            if ($movimiento->referencia_tipo === 'compra') {
                $compra = $compras->get($movimiento->referencia_id);
                $detalle = $detallesCompra->get($key)?->first();
                $precioUnitario = $detalle?->costo_unitario;
                $totalLinea = $detalle?->total;
                $lote = $detalle?->lote?->codigo_lote ?: $detalle?->codigo_lote;
                $documento = $compra?->numero_compra ?: $documento;
                $tercero = $compra?->proveedor?->nombre;
            }

            if (in_array($movimiento->referencia_tipo, ['venta'], true)) {
                $venta = $ventas->get($movimiento->referencia_id);
                $detalle = $detallesVenta->get($key)?->first();
                $precioUnitario = $detalle?->precio_unitario;
                $totalLinea = $detalle?->total;
                $lote = $detalle?->lotes
                    ?->map(fn ($consumo) => $consumo->lote?->codigo_lote ?: 'sin lote')
                    ->unique()
                    ->join(', ');
                $documento = $venta?->numero_venta ?: $documento;
                $tercero = $venta?->cliente?->nombre_mostrable;
            }

            if ($movimiento->referencia_tipo === 'lote') {
                $documento = 'Lote #' . ($movimiento->referencia_id ?: '-');
            } elseif ($movimiento->referencia_tipo === 'ajuste_manual') {
                $documento = 'Ajuste manual';
            } elseif ($movimiento->referencia_tipo === 'regularizacion_faltante') {
                $documento = 'Regularización de faltante';
            } elseif ($movimiento->referencia_tipo === 'importacion_excel') {
                $documento = 'Importación Excel';
            } elseif ($movimiento->referencia_tipo === 'inventario_inicial') {
                $documento = 'Inventario inicial';
            }

            $movimiento->setAttribute('kardex_documento', $documento ?: '-');
            $movimiento->setAttribute('kardex_tercero', $tercero ?: '-');
            $movimiento->setAttribute('kardex_precio_unitario', $precioUnitario);
            $movimiento->setAttribute('kardex_total_linea', $totalLinea);
            $movimiento->setAttribute('kardex_lote', $lote ?: '-');

            $movimiento->setAttribute('kardex_observacion', $movimiento->observacion ?: '-');

            return $movimiento;
        });
    }

    private function safeReportPath(string $path): string
    {
        $path = trim($path);

        if (str_starts_with($path, url('/'))) {
            $path = substr($path, strlen(url('/')));
        }

        if (! str_starts_with($path, '/reportes')) {
            return '/reportes/ventas';
        }

        return $path;
    }

    private function documentReportData(Request $request, bool $paginate): array
    {
        $desde = $request->input('desde', now()->startOfMonth()->toDateString());
        $hasta = $request->input('hasta', now()->endOfMonth()->toDateString());
        $tipoDocumento = $request->input('tipo_documento');
        $nombreComercial = $request->input('nombre_comercial');
        $buscar = trim((string) $request->input('buscar'));

        $ventasQuery = Venta::with('cliente', 'usuario', 'cobros')
            ->where('estado', 'registrada')
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->when($tipoDocumento, fn ($query) => $query->where('tipo_documento', $tipoDocumento))
            ->when($nombreComercial, fn ($query) => $query->where('nombre_comercial', $nombreComercial))
            ->when($buscar !== '', function ($query) use ($buscar) {
                $term = '%' . $buscar . '%';
                $query->where(function ($query) use ($term, $buscar) {
                    $query->where('numero_venta', 'like', $term)
                        ->orWhere('factura', 'like', $term)
                        ->orWhere('nit_cliente', 'like', $term)
                        ->orWhereHas('cliente', fn ($cliente) => $cliente->coincideConBusqueda($buscar));
                });
            })
            ->latest('fecha')
            ->latest('id');

        $comprasQuery = Compra::with('proveedor', 'usuario', 'pagos')
            ->where('estado', 'registrada')
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->when($tipoDocumento, fn ($query) => $query->where('tipo_documento', $tipoDocumento))
            ->when($nombreComercial, fn ($query) => $query->where('nombre_comercial', $nombreComercial))
            ->when($buscar !== '', function ($query) use ($buscar) {
                $term = '%' . $buscar . '%';
                $query->where(function ($query) use ($term) {
                    $query->where('numero_compra', 'like', $term)
                        ->orWhere('factura', 'like', $term)
                        ->orWhere('nit_proveedor', 'like', $term)
                        ->orWhereHas('proveedor', fn ($proveedor) => $proveedor->where('nombre', 'like', $term));
                });
            })
            ->latest('fecha')
            ->latest('id');

        $ventasFacturasQuery = (clone $ventasQuery)->where(fn ($query) => $query->whereNull('tipo_documento')->orWhere('tipo_documento', '!=', 'nota_entrega'));
        $notasVentasQuery = (clone $ventasQuery)->where('tipo_documento', 'nota_entrega');
        $comprasFacturasQuery = (clone $comprasQuery)->where(fn ($query) => $query->whereNull('tipo_documento')->orWhere('tipo_documento', '!=', 'nota_entrega'));
        $notasComprasQuery = (clone $comprasQuery)->where('tipo_documento', 'nota_entrega');
        $ventasResumen = $this->summaryByCommercialAndDocument((clone $ventasQuery)->get());
        $comprasResumen = $this->summaryByCommercialAndDocument((clone $comprasQuery)->get());
        $ventasFacturadas = (clone $ventasFacturasQuery)->get();
        $comprasFacturadas = (clone $comprasFacturasQuery)->get();
        $totales = $this->totalesTributarios($ventasFacturadas->sum('total'), $comprasFacturadas->sum('total'));

        return [
            'notas' => $paginate ? $notasVentasQuery->paginate(20, ['*'], 'notas_page')->withQueryString() : $notasVentasQuery->limit(500)->get(),
            'ventas' => $paginate ? $ventasFacturasQuery->paginate(20, ['*'], 'ventas_page')->withQueryString() : $ventasFacturasQuery->limit(500)->get(),
            'compras' => $paginate ? $comprasFacturasQuery->paginate(20, ['*'], 'compras_page')->withQueryString() : $comprasFacturasQuery->limit(500)->get(),
            'notasCompras' => $paginate ? $notasComprasQuery->paginate(20, ['*'], 'notas_compras_page')->withQueryString() : $notasComprasQuery->limit(500)->get(),
            'ventasResumen' => $ventasResumen,
            'comprasResumen' => $comprasResumen,
            'tributarioPorNombre' => $this->tributarioPorNombre($ventasFacturadas, $comprasFacturadas, $nombreComercial),
            'totales' => $totales + [
                'notas_entrega' => $notasVentasQuery->sum('total'),
                'ventas' => $ventasFacturadas->sum('total'),
                'compras' => $comprasFacturadas->sum('total'),
            ],
            'filtros' => [
                'desde' => $desde,
                'hasta' => $hasta,
                'tipo_documento' => $tipoDocumento,
                'nombre_comercial' => $nombreComercial,
                'buscar' => $buscar,
            ],
        ];
    }

    private function addDocumentSheet(Spreadsheet $spreadsheet, string $title, $documents, string $counterparty): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);
        $sheet->fromArray([['Fecha', 'Número', 'Empresa', 'Factura o nota', 'NIT', ucfirst($counterparty), 'Total Bs']]);
        foreach ($documents as $document) {
            $sheet->fromArray([[
                $document->fecha->format('d/m/Y'),
                $document instanceof Venta ? $document->numero_venta : $document->numero_compra,
                $document->nombre_comercial,
                $document->factura,
                $document instanceof Venta ? $document->nit_cliente : $document->nit_proveedor,
                $document->{$counterparty}?->nombre_mostrable ?? 'Sin ' . $counterparty,
                (float) $document->total,
            ]], null, 'A' . ($sheet->getHighestRow() + 1));
        }
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:G' . max(2, $sheet->getHighestRow()));
        $sheet->getStyle('G2:G' . max(2, $sheet->getHighestRow()))->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function documentTotalFormula(string $sheet, $documents): string
    {
        $lastRow = max(2, $documents->count() + 1);

        return "=SUM('{$sheet}'!\$G\$2:\$G\$" . $lastRow . ')';
    }

    private function documentTotalByCompanyFormula(string $sheet, $documents, int $summaryRow): string
    {
        $lastRow = max(2, $documents->count() + 1);

        return "=SUMIF('{$sheet}'!\$C\$2:\$C\$" . $lastRow . ',$A' . $summaryRow . ",'{$sheet}'!\$G\$2:\$G\$" . $lastRow . ')';
    }

    private function monthlyBalanceData(Request $request): array
    {
        $month = $this->normalizedMonth($request->input('mes'));
        $nombreComercial = $this->validCommercialName($request->input('nombre_comercial'));
        $period = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $desde = $period->copy()->startOfMonth()->toDateString();
        $hasta = $period->copy()->endOfMonth()->toDateString();

        $ventas = Venta::with('cliente')->where('estado', 'registrada')
            ->where(fn ($query) => $query->whereNull('tipo_documento')->orWhere('tipo_documento', '!=', 'nota_entrega'))
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->when($nombreComercial, fn ($query) => $query->where('nombre_comercial', $nombreComercial))
            ->get();
        $compras = Compra::with('proveedor')->where('estado', 'registrada')
            ->where(fn ($query) => $query->whereNull('tipo_documento')->orWhere('tipo_documento', '!=', 'nota_entrega'))
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->when($nombreComercial, fn ($query) => $query->where('nombre_comercial', $nombreComercial))
            ->get();
        $notasEntrega = Venta::with('cliente')->where('estado', 'registrada')
            ->where('tipo_documento', 'nota_entrega')
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->when($nombreComercial, fn ($query) => $query->where('nombre_comercial', $nombreComercial))
            ->get();
        $gastosOperativos = GastoOperativo::query()
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->when($nombreComercial, fn ($query) => $query->where('nombre_comercial', $nombreComercial))
            ->get();

        $nombres = collect($nombreComercial ? [$nombreComercial] : ['San Benjamin', 'Supra Medic'])
            ->merge($ventas->pluck('nombre_comercial'))
            ->merge($compras->pluck('nombre_comercial'))
            ->merge($gastosOperativos->pluck('nombre_comercial'))
            ->filter()
            ->unique()
            ->values();

        $porNombre = $nombres->map(function (string $nombre) use ($ventas, $compras, $gastosOperativos) {
            $ventasNombre = $ventas->where('nombre_comercial', $nombre);
            $comprasNombre = $compras->where('nombre_comercial', $nombre);
            $ventasFacturas = $ventasNombre->filter(fn ($venta) => $venta->tipo_documento !== 'nota_entrega')->sum('total');
            $comprasFacturas = $comprasNombre->filter(fn ($compra) => $compra->tipo_documento !== 'nota_entrega')->sum('total');
            $gastosNombre = $gastosOperativos->where('nombre_comercial', $nombre)->sum('importe');
            $tributario = $this->totalesTributarios($ventasFacturas, $comprasFacturas);

            return [
                'nombre_comercial' => $nombre,
                'ventas_facturas' => $ventasFacturas,
                'compras_facturas' => $comprasFacturas,
                'compras_deducibles' => $comprasFacturas,
                'ventas_total' => $ventasFacturas,
                'compras_total' => $comprasFacturas,
                'gastos_operativos' => $gastosNombre,
                'resultado' => $ventasFacturas - $comprasFacturas - $gastosNombre,
                ...$tributario,
            ];
        });

        $totales = $this->totalesTributarios(
            $porNombre->sum('ventas_facturas'),
            $porNombre->sum('compras_facturas'),
        ) + [
            'ventas_facturas' => $porNombre->sum('ventas_facturas'),
            'compras_facturas' => $porNombre->sum('compras_facturas'),
            'compras_deducibles' => $porNombre->sum('compras_deducibles'),
            'ventas_total' => $porNombre->sum('ventas_total'),
            'compras_total' => $porNombre->sum('compras_total'),
            'gastos_operativos' => $porNombre->sum('gastos_operativos'),
            'resultado' => $porNombre->sum('resultado'),
            'notas_entrega' => $notasEntrega->sum('total'),
        ];

        $maxGrafico = max(1, $porNombre->max(fn ($item) => max($item['ventas_total'], $item['compras_total'])));

        return [
            'mes' => $month,
            'nombreComercial' => $nombreComercial,
            'periodo' => $period,
            'desde' => $desde,
            'hasta' => $hasta,
            'porNombre' => $porNombre,
            'totales' => $totales,
            'maxGrafico' => $maxGrafico,
            'ventas' => $ventas,
            'compras' => $compras,
            'notasEntrega' => $notasEntrega,
            'gastosOperativos' => $gastosOperativos,
        ];
    }

    private function deliveryNotesReportData(Request $request): array
    {
        $month = $this->normalizedMonth($request->input('mes'));
        $period = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $desde = $this->normalizedDate($request->input('desde'), $period->copy()->startOfMonth()->toDateString());
        $hasta = $this->normalizedDate($request->input('hasta'), $period->copy()->endOfMonth()->toDateString());
        $nombreComercial = $this->validCommercialName($request->input('nombre_comercial'));

        $notas = Venta::with('cliente')
            ->where('estado', 'registrada')
            ->where('tipo_documento', 'nota_entrega')
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->when($nombreComercial, fn ($query) => $query->where('nombre_comercial', $nombreComercial))
            ->latest('fecha')
            ->latest('id')
            ->get();

        return [
            'mes' => $period->format('Y-m'),
            'desde' => $desde,
            'hasta' => $hasta,
            'nombreComercial' => $nombreComercial,
            'notas' => $notas,
            'totalNotas' => $notas->sum('total'),
            'porNombre' => $notas->groupBy(fn (Venta $nota) => $nota->nombre_comercial ?: 'Sin nombre comercial')
                ->map(fn ($items, $nombre) => ['nombre_comercial' => $nombre, 'cantidad' => $items->count(), 'total' => $items->sum('total')])
                ->values(),
        ];
    }

    private function normalizedMonth(mixed $month): string
    {
        $month = (string) $month;

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
            ? $month
            : now()->format('Y-m');
    }

    private function normalizedDate(mixed $date, string $fallback): string
    {
        $date = (string) $date;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $fallback;
        }

        $parsed = Carbon::createFromFormat('!Y-m-d', $date);

        return $parsed->format('Y-m-d') === $date ? $date : $fallback;
    }

    private function validCommercialName(mixed $nombreComercial): ?string
    {
        return in_array($nombreComercial, ['San Benjamin', 'Supra Medic'], true)
            ? $nombreComercial
            : null;
    }

    private function totalesTributarios(float $ventasFacturadas, float $comprasFacturadas): array
    {
        $diferencia = round($comprasFacturadas - $ventasFacturadas, 2);
        $iva = round($diferencia * 0.13, 2);
        $it = round($ventasFacturadas * 0.03, 2);

        return [
            'ventas_facturadas' => $ventasFacturadas,
            'compras_facturadas' => $comprasFacturadas,
            'diferencia_tributaria' => $diferencia,
            'iva_estimado' => $iva,
            'it_estimado' => $it,
            'por_pagar' => round($iva + $it, 2),
        ];
    }

    private function tributarioPorNombre($ventas, $compras, ?string $nombreComercial): \Illuminate\Support\Collection
    {
        $nombres = collect($nombreComercial ? [$nombreComercial] : ['San Benjamin', 'Supra Medic'])
            ->merge($ventas->pluck('nombre_comercial'))
            ->merge($compras->pluck('nombre_comercial'))
            ->filter()
            ->unique()
            ->values();

        return $nombres->map(fn (string $nombre) => [
            'nombre_comercial' => $nombre,
            ...$this->totalesTributarios(
                $ventas->where('nombre_comercial', $nombre)->sum('total'),
                $compras->where('nombre_comercial', $nombre)->sum('total'),
            ),
        ]);
    }

    private function summaryByCommercialAndDocument($items)
    {
        return $items
            ->groupBy(fn ($item) => ($item->nombre_comercial ?: 'Sin nombre comercial') . '|' . ($item->tipo_documento ?: 'factura'))
            ->map(function ($group, $key) {
                [$nombreComercial, $tipoDocumento] = explode('|', $key);

                return [
                    'nombre_comercial' => $nombreComercial,
                    'tipo_documento' => $tipoDocumento,
                    'cantidad' => $group->count(),
                    'total' => $group->sum('total'),
                ];
            })
            ->values();
    }
}
