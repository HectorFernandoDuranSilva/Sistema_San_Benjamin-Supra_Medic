@extends('layouts.app')

@section('content')
<section class="dash-shell">
    @include('partials.navigation')

    <main class="content">
        <div class="dash-top">
            <div>
                <h1>Dashboard</h1>
            </div>
            <div class="dash-actions">
                <div class="commercial-select">
                    <span>Nombre comercial</span>
                    <img src="{{ asset($commercial['logo'] ?? 'assets/logos/san-benjamin.png') }}" alt="{{ $commercial['name'] ?? 'Nombre comercial' }}">
                    <strong>{{ $commercial['name'] ?? 'Nombre comercial' }}</strong>
                    <span>NIT: {{ $commercial['nit'] ?? '-' }}</span>
                </div>
                <div class="commercial-select">
                    <span>Tipo de cambio del dia</span>
                    <strong>Bs {{ number_format($tipoCambioValor, 2) }}</strong>
                </div>
                <div class="commercial-select theme-dashboard-switch">
                    <span>Modo oscuro</span>
                    <label class="theme-toggle" aria-label="Cambiar modo oscuro">
                        <input type="checkbox" data-theme-toggle>
                        <span class="theme-toggle-track"><span class="theme-toggle-knob"></span></span>
                    </label>
                </div>
                <a class="secondary" href="{{ route('commercial.select') }}">Cambiar</a>
                @if(auth()->user()->hasPermission('ventas'))<a href="{{ route('ventas.create') }}">Nueva venta</a>@endif
                @if(auth()->user()->hasPermission('compras'))<a href="{{ route('compras.create') }}">Nueva compra</a>@endif
            </div>
        </div>

        <div class="cards">
            @if($dashboardOpciones['ventas_hoy'])<div class="metric"><span>Ventas del dia</span><strong>{{ $ventasHoy }}</strong></div>@endif
            @if($dashboardOpciones['total_vendido'])<div class="metric"><span>Total vendido</span><strong>Bs {{ number_format($totalHoy, 2) }}</strong></div>@endif
            @if($dashboardOpciones['stock_bajo'])<div class="metric"><span>Stock bajo</span><strong>{{ $productosBajoStock->count() }}</strong></div>@endif
            @if($dashboardOpciones['stock_pendiente'])<div class="metric"><span>Faltantes por regularizar</span><strong>{{ $productosStockNegativo->count() }}</strong></div>@endif
            @if($dashboardOpciones['movimientos'])<div class="metric"><span>Movimientos</span><strong>{{ $ultimosMovimientos->count() }}</strong></div>@endif
            @if($dashboardOpciones['cuentas_cobrar'])<div class="metric"><span>Cuentas por cobrar</span><strong>Bs {{ number_format($cuentasPorCobrar['importe'], 2) }}</strong></div>@endif
        </div>

        <div class="dashboard-grid">
            @if($dashboardOpciones['movimiento_mensual'])
            @php
                $movimientoBase = $movimientoMensual['ventas'] + $movimientoMensual['compras'];
                $ventasProporcion = $movimientoBase > 0 ? round(($movimientoMensual['ventas'] / $movimientoBase) * 100, 1) : 0;
                $comprasProporcion = $movimientoBase > 0 ? round(($movimientoMensual['compras'] / $movimientoBase) * 100, 1) : 0;
                $resultadoEsPositivo = $movimientoMensual['resultado'] >= 0;
            @endphp
            <section class="panel monthly-summary">
                <div class="monthly-summary-head">
                    <div>
                        <span class="eyebrow">Resumen financiero del mes</span>
                        <h2>Movimiento mensual</h2>
                        <p class="muted">Comparación entre ventas y compras registradas en el período actual.</p>
                    </div>
                    <span class="monthly-status {{ $resultadoEsPositivo ? 'is-positive' : 'is-negative' }}">
                        <strong>{{ $resultadoEsPositivo ? 'Ganancia' : 'Pérdida' }}</strong>
                        <small>{{ $resultadoEsPositivo ? 'Las ventas superan a las compras' : 'Las compras superan a las ventas' }}</small>
                    </span>
                </div>
                <div class="monthly-kpis">
                    <div class="monthly-kpi sales">
                        <span>Ventas</span>
                        <strong>Bs {{ number_format($movimientoMensual['ventas'], 2) }}</strong>
                        <small>{{ number_format($ventasProporcion, 1) }}% del movimiento</small>
                    </div>
                    <div class="monthly-kpi purchases">
                        <span>Compras</span>
                        <strong>Bs {{ number_format($movimientoMensual['compras'], 2) }}</strong>
                        <small>{{ number_format($comprasProporcion, 1) }}% del movimiento</small>
                    </div>
                    <div class="monthly-kpi result {{ $resultadoEsPositivo ? 'is-positive' : 'is-negative' }}">
                        <span>Resultado comercial</span>
                        <strong>{{ $resultadoEsPositivo ? '+' : '-' }}Bs {{ number_format(abs($movimientoMensual['resultado']), 2) }}</strong>
                        <small>Ventas - compras</small>
                    </div>
                </div>
                <div class="monthly-comparison" aria-label="Comparación entre ventas y compras">
                    <div class="monthly-comparison-labels"><span>Ventas</span><span>Compras</span></div>
                    <div class="monthly-bar">
                        <span class="monthly-bar-sales" style="width: {{ $ventasProporcion }}%"></span>
                        <span class="monthly-bar-purchases" style="width: {{ $comprasProporcion }}%"></span>
                    </div>
                    <p class="monthly-explanation">
                        {{ $resultadoEsPositivo ? 'Las ventas superan a las compras' : 'Las compras superan a las ventas' }}
                        por <strong>Bs {{ number_format(abs($movimientoMensual['resultado']), 2) }}</strong>.
                    </p>
                </div>
            </section>
            @endif
            @if($dashboardOpciones['movimientos'])<section class="panel">
                <h2>Movimientos de inventario</h2>
                <div class="timeline">
                    @forelse($ultimosMovimientos->take(4) as $movimiento)
                        <div class="timeline-row">
                            <span>{{ $movimiento->fecha_movimiento->format('H:i') }}</span>
                            <div>
                                <b>{{ ucfirst(str_replace('_', ' ', $movimiento->tipo_movimiento)) }}</b>
                                <br><span class="muted">{{ $movimiento->producto?->nombre ?? 'Producto sin nombre' }} - {{ number_format($movimiento->cantidad, 2) }}</span>
                            </div>
                            <span class="pill">{{ number_format($movimiento->stock_nuevo, 2) }}</span>
                        </div>
                    @empty
                        <div class="timeline-row"><span>-</span><div><b>Sin movimientos recientes</b><br><span class="muted">Las entradas, salidas y ajustes apareceran aqui.</span></div><span class="pill">Stock</span></div>
                    @endforelse
                </div>
            </section>
            @endif
        </div>

        <div class="dashboard-bottom">
            @if($dashboardOpciones['stock_bajo'])<section class="panel">
                <h2>Stock bajo</h2>
                <div class="compact-list">
                    @forelse($productosBajoStock->take(5) as $producto)
                        <div class="compact-item">
                            <div>
                                <b>{{ $producto->codigo }} - {{ $producto->nombre }}</b>
                                <small>{{ $producto->categoria?->nombre ?? 'Sin categoria' }} - Minimo {{ number_format($producto->stock_minimo, 2) }}</small>
                            </div>
                            <span class="pill">{{ number_format($producto->stock_actual, 2) }}</span>
                        </div>
                    @empty
                        <div class="compact-item">
                            <div><b>Sin productos en alerta</b><small>El inventario está por encima del mínimo registrado.</small></div>
                            <span class="pill">OK</span>
                        </div>
                    @endforelse
                </div>
            </section>@endif

            @if($dashboardOpciones['stock_pendiente'])<section class="panel">
                <div class="top">
                    <h2>Faltantes de stock</h2>
                    @if(auth()->user()->hasPermission('productos'))
                        <a class="btn secondary" href="{{ route('productos.index') }}">Regularizar faltantes</a>
                    @endif
                </div>
                <div class="compact-list">
                    @forelse($productosStockNegativo->take(5) as $producto)
                        @if(auth()->user()->hasPermission('productos'))
                            <a class="alert-item" href="{{ route('productos.show', $producto) }}">
                                <strong><span>{{ $producto->codigo }} - {{ $producto->nombre }}</span><span>{{ number_format(abs((float) $producto->stock_actual), 2) }}</span></strong>
                                <small>{{ $producto->categoria?->nombre ?? 'Sin categoría' }} · unidades faltantes · Abrir regularización</small>
                            </a>
                        @else
                            <div class="alert-item">
                                <strong><span>{{ $producto->codigo }} - {{ $producto->nombre }}</span><span>{{ number_format(abs((float) $producto->stock_actual), 2) }}</span></strong>
                                <small>{{ $producto->categoria?->nombre ?? 'Sin categoría' }} · unidades faltantes</small>
                            </div>
                        @endif
                    @empty
                        <div class="compact-item">
                            <div><b>Sin faltantes pendientes</b><small>No existen productos con stock negativo.</small></div>
                            <span class="pill">OK</span>
                        </div>
                    @endforelse
                </div>
            </section>@endif

            @if($dashboardOpciones['ultimas_operaciones'])<section class="panel">
                <h2>Ultimas operaciones</h2>
                <div class="compact-list">
                    @foreach($ultimasOperaciones->take(5) as $operacion)
                        <div class="compact-item">
                            <div>
                                <b>{{ $operacion['tipo'] }} {{ $operacion['numero'] }}</b>
                                <small>{{ $operacion['fecha']->format('d/m/Y') }} - {{ $operacion['nombre_comercial'] ?? 'Sin nombre comercial' }} - {{ $operacion['tercero'] }}</small>
                            </div>
                            <span class="pill">Bs {{ number_format($operacion['total'], 2) }}</span>
                        </div>
                    @endforeach
                    @if($ultimasOperaciones->isEmpty())
                        <div class="compact-item">
                            <div><b>Sin operaciones recientes</b><small>Las ventas y compras registradas apareceran aqui.</small></div>
                            <span class="pill">Nuevo</span>
                        </div>
                    @endif
                </div>
            </section>@endif

            @if($dashboardOpciones['alertas_datos'])<section class="panel">
                <h2>Alertas de datos</h2>
                <div class="compact-list">
                    @foreach($alertasDatos->take(5) as $alerta)
                        @if(auth()->user()->hasPermission($alerta['permiso']))
                            <a class="alert-item" href="{{ $alerta['ruta'] }}">
                                <strong><span>{{ $alerta['titulo'] }}</span><span>{{ $alerta['total'] }}</span></strong>
                                <small>{{ $alerta['detalle'] }}</small>
                            </a>
                        @else
                        <div class="alert-item">
                            <strong><span>{{ $alerta['titulo'] }}</span><span>{{ $alerta['total'] }}</span></strong>
                            <small>{{ $alerta['detalle'] }}</small>
                        </div>
                        @endif
                    @endforeach
                </div>
            </section>@endif

            @if($dashboardOpciones['vencimientos'])<section class="panel">
                <h2>Vencimientos</h2>
                <div class="compact-list">
                    @forelse($lotesPorVencer as $lote)
                        @if(auth()->user()->hasPermission('inventario'))
                            <a class="alert-item" href="{{ route('inventario.lotes.index', ['producto_id' => $lote->producto_id, 'estado' => 'activo']) }}">
                                <strong><span>{{ $lote->producto?->codigo }} - {{ $lote->producto?->nombre }}</span><span>{{ number_format($lote->cantidad_disponible, 2) }}</span></strong>
                                <small>Vence {{ $lote->fecha_vencimiento->format('d/m/Y') }} - alerta {{ $diasAlertaVencimiento }} días</small>
                            </a>
                        @else
                            <div class="alert-item">
                                <strong><span>{{ $lote->producto?->codigo }} - {{ $lote->producto?->nombre }}</span><span>{{ number_format($lote->cantidad_disponible, 2) }}</span></strong>
                                <small>Vence {{ $lote->fecha_vencimiento->format('d/m/Y') }} - alerta {{ $diasAlertaVencimiento }} días</small>
                            </div>
                        @endif
                    @empty
                        <div class="compact-item">
                            <div><b>Sin vencimientos cercanos</b><small>No hay lotes por vencer dentro del rango configurado.</small></div>
                            <span class="pill">{{ $diasAlertaVencimiento }} días</span>
                        </div>
                    @endforelse
                </div>
            </section>@endif

            @if($dashboardOpciones['cuotas'])<section class="panel">
                <div class="top"><h2>Cuentas y cuotas por cobrar</h2>@if(auth()->user()->hasPermission('notas_facturas'))<a class="btn secondary" href="{{ route('notas-facturas.cobros') }}">Ver cartera</a>@endif</div>
                <div class="compact-list">
                    <div class="compact-item">
                        <div><b>{{ $cuentasPorCobrar['cantidad'] }} documentos por cobrar</b><small>Incluye facturas, notas y cuotas con saldo pendiente.</small></div>
                        <span class="pill">Bs {{ number_format($cuentasPorCobrar['importe'], 2) }}</span>
                    </div>
                    @forelse($cuotasConAtraso as $cuota)
                        <a class="alert-item" href="{{ route($cuota->venta->tipo_documento === 'nota_entrega' ? 'notas.show' : 'ventas.show', $cuota->venta) }}#cobros">
                            <strong><span>{{ $cuota->venta->numero_venta }} · cuota {{ $cuota->numero }}</span><span>Bs {{ number_format($cuota->saldo_pendiente, 2) }}</span></strong>
                            <small>{{ $cuota->venta->cliente?->nombre_mostrable ?? 'Sin cliente' }} · venció {{ $cuota->fecha_vencimiento->format('d/m/Y') }}</small>
                        </a>
                    @empty
                        <div class="compact-item"><div><b>Sin cuotas con atraso</b><small>La alerta aparece después de {{ $diasAtrasoCuotas }} días desde el vencimiento.</small></div><span class="pill">OK</span></div>
                    @endforelse
                </div>
            </section>@endif
        </div>
    </main>
</section>

@if($dashboardOpciones['vencimientos'] && $lotesPorVencer->isNotEmpty())
    <div class="glass-modal-backdrop is-open" role="dialog" aria-modal="true" data-dashboard-alert>
        <div class="glass-modal">
            <h2>Vencimientos cercanos</h2>
            <p>Estos lotes están dentro del rango de alerta configurado de {{ $diasAlertaVencimiento }} días.</p>
            <div class="compact-list" style="margin-top:14px;">
                @foreach($lotesPorVencer as $lote)
                    <div class="alert-item">
                        <strong><span>{{ $lote->producto?->codigo }} - {{ $lote->producto?->nombre }}</span><span>{{ number_format($lote->cantidad_disponible, 2) }}</span></strong>
                        <small>Lote {{ $lote->codigo_lote ?: 'sin lote' }} - vence {{ $lote->fecha_vencimiento->format('d/m/Y') }}</small>
                    </div>
                @endforeach
            </div>
            <div class="glass-modal-actions">
                @if(auth()->user()->hasPermission('inventario'))
                    <a class="btn secondary" href="{{ route('inventario.lotes.index', ['estado' => 'activo']) }}">Ver lotes</a>
                @endif
                <button type="button" class="btn" data-glass-close>Entendido</button>
            </div>
        </div>
    </div>
@endif

@if($dashboardOpciones['faltantes_stock'] && $productosStockNegativo->isNotEmpty())
    <div class="glass-modal-backdrop is-open" role="dialog" aria-modal="true" data-dashboard-alert>
        <div class="glass-modal is-error">
            <h2>Faltantes de stock por regularizar</h2>
            <p>Estos productos tienen existencias negativas. Registra la regularización desde la ficha de cada producto.</p>
            <div class="compact-list" style="margin-top:14px;">
                @foreach($productosStockNegativo->take(8) as $producto)
                    @if(auth()->user()->hasPermission('productos'))
                        <a class="alert-item" href="{{ route('productos.show', $producto) }}">
                            <strong><span>{{ $producto->codigo }} - {{ $producto->nombre }}</span><span>{{ number_format($producto->stock_actual, 2) }}</span></strong>
                            <small>Faltan {{ number_format(abs((float) $producto->stock_actual), 2) }} unidades · Abrir regularización</small>
                        </a>
                    @else
                        <div class="alert-item">
                            <strong><span>{{ $producto->codigo }} - {{ $producto->nombre }}</span><span>{{ number_format($producto->stock_actual, 2) }}</span></strong>
                            <small>Faltan {{ number_format(abs((float) $producto->stock_actual), 2) }} unidades</small>
                        </div>
                    @endif
                @endforeach
            </div>
            <div class="glass-modal-actions">
                @if(auth()->user()->hasPermission('productos'))
                    <a class="btn secondary" href="{{ route('productos.index') }}">Ver productos</a>
                @endif
                <button type="button" class="btn" data-glass-close>Entendido</button>
            </div>
        </div>
    </div>
@endif

@if($dashboardOpciones['facturacion_mensual'] && $facturacionMensualAlerta)
    <div class="glass-modal-backdrop is-open" role="dialog" aria-modal="true" data-dashboard-alert>
        <div class="glass-modal">
            <h2>{{ $facturacionMensualAlerta['titulo'] }}</h2>
            <p>Facturación registrada del {{ $facturacionMensualAlerta['desde']->format('d/m/Y') }} al {{ $facturacionMensualAlerta['hasta']->format('d/m/Y') }}. Las notas de entrega no se mezclan en este corte.</p>
            <div class="compact-list" style="margin-top:14px;">
                @foreach($facturacionMensualAlerta['items'] as $item)
                    <div class="alert-item">
                        <strong><span>{{ $item['nombre_comercial'] }}</span><span>Bs {{ number_format($item['total'], 2) }}</span></strong>
                        <small>{{ $item['cantidad'] }} facturas registradas</small>
                    </div>
                @endforeach
                <div class="alert-item">
                    <strong><span>Total facturado</span><span>Bs {{ number_format($facturacionMensualAlerta['total'], 2) }}</span></strong>
                    <small>Corte generado automáticamente desde ventas facturadas.</small>
                </div>
            </div>
            <div class="glass-modal-actions">
                @if(auth()->user()->hasPermission('reportes'))
                    <a class="btn secondary" href="{{ $facturacionMensualAlerta['ruta'] }}">Ver reporte</a>
                @endif
                <button type="button" class="btn" data-glass-close>Entendido</button>
            </div>
        </div>
    </div>
@endif

@if($dashboardOpciones['cuotas'] && $cuotasConAtraso->isNotEmpty())
    <div class="glass-modal-backdrop is-open" role="dialog" aria-modal="true" data-dashboard-alert>
        <div class="glass-modal is-error">
            <h2>Cuotas con atraso</h2>
            <p>Estas cuotas continúan pendientes {{ $diasAtrasoCuotas }} días después de su vencimiento.</p>
            <div class="compact-list" style="margin-top:14px;">
                @foreach($cuotasConAtraso as $cuota)
                    <div class="alert-item">
                        <strong><span>{{ $cuota->venta->numero_venta }} · cuota {{ $cuota->numero }}</span><span>Bs {{ number_format($cuota->saldo_pendiente, 2) }}</span></strong>
                        <small>{{ $cuota->venta->cliente?->nombre_mostrable ?? 'Sin cliente' }} · venció {{ $cuota->fecha_vencimiento->format('d/m/Y') }}</small>
                    </div>
                @endforeach
            </div>
            <div class="glass-modal-actions"><button type="button" class="btn" data-glass-close>Entendido</button></div>
        </div>
    </div>
@endif
@endsection
