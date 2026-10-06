<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuotationRequest;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Producto;
use App\Models\TipoCambio;
use App\Services\AuditLogService;
use App\Services\QuotationService;
use App\Services\QuotationWordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuotationController extends Controller
{
    public function index()
    {
        return view('cotizaciones.index', [
            'cotizaciones' => Cotizacion::with('cliente', 'usuario', 'venta', 'confirmadaPor')->latest()->paginate(20),
        ]);
    }

    public function create()
    {
        return $this->formData();
    }

    public function edit(Cotizacion $cotizacion)
    {
        if ($cotizacion->venta_id) {
            return redirect()->route('cotizaciones.show', $cotizacion)->withErrors(['cotizacion' => 'La cotización ya fue convertida y no se puede editar.']);
        }
        return $this->formData($cotizacion->load('detalles.producto'));
    }

    private function formData(?Cotizacion $cotizacion = null)
    {
        $productos = Producto::with(['lotes' => fn ($q) => $q->where('estado', 'activo')->where('cantidad_disponible', '>', 0)->orderBy('fecha_vencimiento')])->where('activo', true)->orWhereIn('id', $cotizacion?->detalles->pluck('producto_id') ?? [])->orderBy('nombre')->get();
        $tipoCambioActual = TipoCambio::where('estado', 'vigente')->latest('fecha')->latest('id')->first();
        $tipoCambioValor = $tipoCambioActual ? (float) $tipoCambioActual->valor : TipoCambio::ultimoValor();

        return view('cotizaciones.form', [
            'cotizacion' => $cotizacion,
            'itemsIniciales' => $cotizacion?->detalles->map(fn ($d) => $d->only(['id', 'producto_id', 'nombre_producto', 'medida', 'cantidad', 'precio_unitario', 'porcentaje_utilidad', 'descuento', 'impuesto', 'costo_referencial', 'marca', 'procedencia', 'inventario_lote_id']) + ['por_pedido' => $d->producto_id === null, 'fecha_vencimiento' => $d->fecha_vencimiento?->toDateString()])->all() ?? [],
            'clientes' => Cliente::where('activo', true)->orWhere('id', $cotizacion?->cliente_id)->orderBy('nombre')->get(),
            'productos' => $productos,
            'productosCotizacion' => $productos->map(fn ($p) => [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'medida' => $p->medida,
                'marca' => $p->marca,
                'procedencia' => $p->procedencia,
                'lotes' => $p->lotes->map(fn ($l) => ['id' => $l->id, 'codigo' => $l->codigo_lote, 'vencimiento' => $l->fecha_vencimiento?->toDateString()])->values(),
                'stock' => $p->stock_actual,
                'price' => $p->precio_venta,
                'cost' => $p->costo_promedio ?? $p->costo_referencial,
                'suggested' => app(\App\Services\ProductPricingService::class)->suggestedReplacementPrice($p, $tipoCambioValor),
                'margin' => $p->porcentaje_utilidad,
            ])->values(),
            'commercial' => session('commercial_context'),
            'siguienteNumero' => 'CT-' . str_pad((string) (((int) Cotizacion::max('id')) + 1), 6, '0', STR_PAD_LEFT),
            'tipoCambioActual' => $tipoCambioActual,
            'tipoCambioValor' => $tipoCambioValor,
            'footerDefaults' => $this->footerDefaults($cotizacion?->nombre_comercial ?? session('commercial_context.name', 'San Benjamin')),
            'footerCompanies' => [
                'San Benjamin' => config('quotation_pdf.san-benjamin.footer_company'),
                'Supra Medic' => config('quotation_pdf.supra-medic.footer_company'),
            ],
        ]);
    }

    private function footerDefaults(string $commercialName): array
    {
        $slug = str_contains(strtolower($commercialName), 'supra') ? 'supra-medic' : 'san-benjamin';
        $defaults = config('quotation_pdf.footer_defaults');
        $company = config("quotation_pdf.{$slug}.footer_company");

        return [
            'empresa' => $company,
            'direccion' => $defaults['direccion'],
            'validez' => $defaults['validez'],
            'entrega' => $defaults['entrega'],
            'condiciones_pago' => $defaults['condiciones_pago'],
            'deposito_cuenta' => str_replace('{empresa}', $company, $defaults['deposito_cuenta']),
        ];
    }

    public function store(QuotationRequest $request, QuotationService $service)
    {
        try {
            $cotizacion = $service->registrar($request->validated(), $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cotizacion' => $e->getMessage()])->withInput();
        }

        return redirect()->route('cotizaciones.show', $cotizacion)->with('ok', 'Cotización registrada.');
    }

    public function show(Cotizacion $cotizacion)
    {
        return view('cotizaciones.show', [
            'cotizacion' => $cotizacion->load('cliente', 'detalles.producto', 'usuario', 'venta', 'confirmadaPor'),
        ]);
    }

    public function update(QuotationRequest $request, Cotizacion $cotizacion, QuotationService $service, AuditLogService $audit)
    {
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $cotizacion, $service, $audit) {
                $before = $cotizacion->load('detalles')->toArray();
                $updated = $service->actualizar($cotizacion, $request->validated(), $request->user()->id);
                $audit->record($request, 'actualizo', 'cotizaciones', $updated, $before, $updated->toArray(), 'Edición de cotización y sus ítems');
            });
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cotizacion' => $e->getMessage()])->withInput();
        }
        return redirect()->route('cotizaciones.show', $cotizacion)->with('ok', 'Cotización actualizada.');
    }

    public function convertir(Request $request, Cotizacion $cotizacion, QuotationService $service, AuditLogService $audit)
    {
        $validated = $request->validate([
            'tipo_documento' => ['nullable', 'in:factura,nota_entrega'],
            'fecha' => ['nullable', 'date'],
            'factura' => ['nullable', 'string', 'max:60'],
        ]);
        $before = $cotizacion->toArray();

        try {
            $venta = $service->convertirAVenta($cotizacion, $request->user()->id, $validated);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cotizacion' => $e->getMessage()]);
        }

        $audit->record(
            $request,
            'convirtio',
            'cotizaciones',
            $cotizacion->fresh(),
            $before,
            ['venta_id' => $venta->id, 'tipo_documento' => $venta->tipo_documento],
            'Cotización convertida en venta'
        );

        return redirect()->route('ventas.show', $venta)->with('ok', 'Cotización convertida en venta.');
    }

    public function confirm(Request $request, Cotizacion $cotizacion, AuditLogService $audit)
    {
        $request->validate([
            'confirmacion' => ['accepted'],
        ], [
            'confirmacion.accepted' => 'Marque la casilla para confirmar que revisó los datos de la cotización.',
        ]);

        DB::transaction(function () use ($request, $cotizacion, $audit) {
            $quotation = Cotizacion::lockForUpdate()->findOrFail($cotizacion->id);
            $before = $quotation->only(['confirmada_at', 'confirmada_por_id']);
            $quotation->update([
                'confirmada_at' => now(),
                'confirmada_por_id' => $request->user()->id,
            ]);
            $audit->record(
                $request,
                'confirmo',
                'cotizaciones',
                $quotation,
                $before,
                $quotation->only(['confirmada_at', 'confirmada_por_id']),
                'Cotización revisada y confirmada para exportación'
            );
        });

        return redirect()->route('cotizaciones.show', $cotizacion)->with('ok', 'Cotización confirmada. Ya puede exportarla a PDF o Word.');
    }

    public function pdf(Cotizacion $cotizacion)
    {
        if (! $cotizacion->confirmada_at) {
            return redirect()->route('cotizaciones.show', $cotizacion)
                ->withErrors(['cotizacion' => 'Revise y confirme la cotización antes de exportarla.']);
        }

        return app(\App\Services\QuotationPdfService::class)
            ->render($cotizacion->load('cliente', 'detalles.producto', 'usuario', 'confirmadaPor'))
            ->download("cotizacion-{$cotizacion->numero_cotizacion}.pdf");
    }

    public function word(Cotizacion $cotizacion, QuotationWordService $word)
    {
        if (! $cotizacion->confirmada_at) {
            return redirect()->route('cotizaciones.show', $cotizacion)
                ->withErrors(['cotizacion' => 'Revise y confirme la cotización antes de exportarla.']);
        }

        $path = $word->create($cotizacion->load('cliente', 'detalles.producto', 'usuario', 'confirmadaPor'));

        return response()
            ->download($path, "cotizacion-{$cotizacion->numero_cotizacion}.docx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])
            ->deleteFileAfterSend(true);
    }
}
