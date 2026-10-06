# Guia rapida de usuario

## Ingresar

Abre `/login`, escribe usuario y clave. El administrador inicial se define en `.env`.

## Crear productos

Entra a `Productos`, pulsa `Crear producto`, registra codigo, nombre, categoria, precio, costo y stock minimo. El stock actual no se escribe manualmente: se actualiza con compras, ventas, ajustes o importacion.

## Importar desde Excel

Entra a `Importar Excel`, carga el archivo `.xlsm`, revisa el reporte y confirma. Si aparecen codigos vacios, duplicados, productos sin precio o stock negativo, corrige el Excel o valida el caso antes de usarlo en produccion.

## Registrar ventas

Entra a `Ventas`, pulsa `Nueva venta`, selecciona cliente y productos. El sistema valida stock antes de guardar. Al registrar la venta se descuenta inventario y se genera el movimiento de salida.

## Imprimir recibos

Desde el detalle de venta o compra usa `Vista recibo` para imprimir o `PDF` para descargar. El formato replica las hojas `PrintVentas` y `PrintCompras` y debe validarse contra el comprobante Excel antes de produccion.

## Registrar compras

Entra a `Compras`, pulsa `Nueva compra`, selecciona proveedor y productos. Al guardar, el stock aumenta automaticamente y se registra el movimiento de entrada.

## Consultar stock

Entra a `Inventario`. Veras stock actual, minimo y alerta de stock bajo.

## Reportes

Usa `Reportes` para ventas por fecha/estado, compras por fecha y movimientos de inventario filtrados por fecha o producto.
