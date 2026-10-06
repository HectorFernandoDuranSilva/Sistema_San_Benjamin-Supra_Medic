# Mapeo inicial Excel a base de datos

Fuente analizada: `C:\Users\Pc\Desktop\Sistemas de inventarios Compras Ventas.xlsm`.

## Hojas relevantes

- `Productos`: base de productos e inventario calculado. Encabezado detectado en fila 4: `CODIGO`, `DESCRIPCION DEL PRODUCTO`, `CATEGORIA`, `MEDIDA`, `UBICACION`, `STOCK MINIMO`, `Existencia`, `Costo Unit`, `Costo Total`.
- `listProductos`: productos con precios. Encabezado fila 4: `CODIGO`, `DESCRIPCION DEL PRODUCTO`, `CATEGORIA`, `MEDIDA`, `UBICACION`, `STOCK MINIMO`, `Precio de Venta`, `Precio Sugerido`, `Precio S/F`, `Precio de Compra`.
- `Entradas`: compras/ingresos. Tabla `tblEntradas` en `A4:S845`.
- `Salidas`: ventas/salidas. Tabla `tblSalidas` en `A4:Z1590`.
- `Setting`: ajustes. Tabla `tblAjustes` en `A4:M85`.
- `Clientes`: clientes, encabezado fila 4.
- `Proveedor`: proveedores, encabezado fila 4.
- `PrintVentas`: plantilla de recibo de venta. Rango usado `B3:AA77`.
- `PrintCompras`: plantilla de comprobante de compra. Tabla `tblPrintCompras` en `A9:I14`.

## Correspondencias

| Excel | Campo Excel | Tabla Laravel | Campo Laravel |
| --- | --- | --- | --- |
| `Productos` | `CODIGO` | `productos` | `codigo` |
| `Productos` | `DESCRIPCION DEL PRODUCTO` | `productos` | `nombre` |
| `Productos` | `CATEGORIA` | `categorias` / `productos` | `categorias.nombre` / `categoria_id` |
| `Productos` | `MEDIDA` | `productos` | `medida` |
| `Productos` | `UBICACION` | `productos` | `ubicacion` |
| `Productos` | `STOCK MINIMO` | `productos` | `stock_minimo` |
| `Productos` | `Existencia` | `movimientos_inventario` | stock inicial via movimiento |
| `Productos` | `Costo Unit` | `productos` | `costo_referencial` |
| `listProductos` | `Precio de Venta` | `productos` | `precio_venta` |
| `Clientes` | `CLIENTE` | `clientes` | `nombre` |
| `Clientes` | `NIT` | `clientes` | `documento` |
| `Clientes` | `TELEFONO` | `clientes` | `telefono` |
| `Clientes` | `DIRECCION` | `clientes` | `direccion` |
| `Proveedor` | `Proveedor` | `proveedores` | `nombre` |
| `Proveedor` | `NIT` | `proveedores` | `documento` |
| `Proveedor` | `Direccion` | `proveedores` | `direccion` |
| `Entradas` | `No CONTROL`, `FECHA`, `PROVEEDOR`, `CODIGO`, `CANTIDAD`, `PRECIO`, `TOTAL` | `compras`, `detalle_compras`, `movimientos_inventario` | compra, detalle y entrada |
| `Salidas` | `No CONTROL`, `FECHA`, `CLIENTE`, `CODIGO`, `CANTIDAD`, `PRECIO`, `TOTAL` | `ventas`, `detalle_ventas`, `movimientos_inventario` | venta, detalle y salida |
| `Setting` | `ENTRADA` / `SALIDA` | `movimientos_inventario` | ajustes positivos/negativos |

## Logica migrada

El Excel calcula la existencia con entradas menos salidas mas ajustes:

`tblEntradas[CANTIDAD] - tblSalidas[CANTIDAD] + tblAjustes[ENTRADA] - tblAjustes[SALIDA]`

En Laravel se conserva esta regla como historial de `movimientos_inventario`. La existencia visible se obtiene del ultimo `stock_nuevo`.

## Pendientes

- Validar visualmente la plantilla `PrintVentas` contra PDF generado.
- Identificar y validar las plantillas exactas para San Benjamin y Supra Medic, tanto compra como venta.
- Confirmar si se debe migrar historial completo de `Entradas` y `Salidas` o solo maestros y stock inicial.
- Revisar macros VBA si contienen reglas no visibles en formulas.

## Regla funcional agregada en Sprint 0

San Benjamin y Supra Medic se manejaran como nombres comerciales de una misma operacion. Cada venta, compra y comprobante debera poder registrar que nombre comercial se uso. Los reportes mensuales deben consolidar ambos nombres comerciales y tambien detallar el resultado de cada uno.

Los descuentos y porcentajes no son fijos ni automaticos. El operador debe escribir el porcentaje o descuento aplicable en ventas, compras, facturacion, productos dentro del formulario y movimiento mensual.
