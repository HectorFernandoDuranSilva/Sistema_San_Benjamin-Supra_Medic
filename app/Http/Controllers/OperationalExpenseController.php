<?php

namespace App\Http\Controllers;

use App\Models\CompraGasto;
use App\Models\ConfiguracionEmpresa;
use App\Models\GastoOperativo;
use App\Services\AuditLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OperationalExpenseController extends Controller
{
    public function index(Request $request)
    {
        $month = $this->normalizedMonth($request->input('mes'));
        $nombreComercial = $this->validCommercialName($request->input('nombre_comercial'));
        $nombreComercialFormulario = $nombreComercial
            ?? $this->validCommercialName(session('commercial_context.name'))
            ?? 'San Benjamin';

        $periodo = Carbon::createFromFormat('Y-m', $month);
        $gastosQuery = GastoOperativo::query()
            ->whereBetween('fecha', [$periodo->copy()->startOfMonth(), $periodo->copy()->endOfMonth()])
            ->when($nombreComercial, fn ($query) => $query->where('nombre_comercial', $nombreComercial));

        $totalGastos = (float) (clone $gastosQuery)->sum('importe');
        $gastos = $gastosQuery
            ->with('usuario')
            ->latest('fecha')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $historicos = CompraGasto::with(['compra.proveedor'])
            ->whereBetween('fecha', [$periodo->copy()->startOfMonth(), $periodo->copy()->endOfMonth()])
            ->when($nombreComercial, fn ($query) => $query->whereHas('compra', fn ($compra) => $compra->where('nombre_comercial', $nombreComercial)))
            ->latest('fecha')
            ->latest('id')
            ->get();

        return view('reportes.gastos-operativos', [
            'mes' => $month,
            'nombreComercial' => $nombreComercial,
            'nombreComercialFormulario' => $nombreComercialFormulario,
            'gastos' => $gastos,
            'historicos' => $historicos,
            'totalGastos' => $totalGastos,
        ]);
    }

    public function store(Request $request, AuditLogService $audit)
    {
        $data = $request->validate([
            'nombre_comercial' => ['required', 'in:San Benjamin,Supra Medic'],
            'tipo' => ['required', 'in:combustible,envio,transporte,recojo,otro'],
            'modalidad' => ['required', 'in:envio,recojo,otro'],
            'documento_tipo' => ['required', 'in:factura,nota,guia,sin_documento'],
            'documento_numero' => ['nullable', 'string', 'max:255'],
            'fecha' => ['required', 'date'],
            'importe' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $gasto = GastoOperativo::create($data + ['usuario_id' => $request->user()->id]);
        $audit->record($request, 'registro', 'gastos_operativos', $gasto, null, $gasto->toArray(), 'Gasto operativo registrado fuera de compras.');

        return redirect()->route('reportes.gastos-operativos', ['mes' => substr($data['fecha'], 0, 7), 'nombre_comercial' => $data['nombre_comercial']])
            ->with('ok', 'Gasto operativo registrado. No generó stock ni numeración de compra.');
    }

    public function pdf(GastoOperativo $gasto)
    {
        $gasto->load('usuario');

        return Pdf::loadView('recibos.gasto-operativo', [
            'gasto' => $gasto,
            'empresa' => ConfiguracionEmpresa::firstOrCreate([]),
            'generadoEn' => now(),
            'isPdf' => true,
        ])->setPaper('letter')->download("gasto-operativo-{$gasto->id}.pdf");
    }

    private function normalizedMonth(mixed $month): string
    {
        $month = (string) $month;

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : now()->format('Y-m');
    }

    private function validCommercialName(mixed $nombreComercial): ?string
    {
        return in_array($nombreComercial, ['San Benjamin', 'Supra Medic'], true) ? $nombreComercial : null;
    }
}
