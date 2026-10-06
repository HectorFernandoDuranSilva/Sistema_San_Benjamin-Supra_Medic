# Sprint 0 - Alcance funcional confirmado

Este documento cierra el analisis funcional inicial antes de avanzar con diseno visual de login/dashboard o desarrollo de Sprints 1, 2 y 3.

## Estado del Sprint 0

Sprint 0 queda enfocado en:

- revision del Excel real,
- identificacion de hojas y plantillas,
- mapeo inicial Excel a base de datos,
- instalacion Laravel,
- autenticacion base,
- estructura tecnica inicial,
- reglas funcionales confirmadas por el usuario,
- estrategia de despliegue en VPS.

No se debe considerar cerrado el diseno visual de login/dashboard. Ese estilo sera definido despues por el usuario.

## Nombres comerciales

San Benjamin y Supra Medic pertenecen a la misma persona y a la misma operacion general. No se deben tratar como dos empresas legales separadas.

Para el sistema se manejaran como **nombres comerciales** o **marcas operativas** dentro de una misma empresa.

Nombres comerciales iniciales:

- San Benjamin
- Supra Medic

Uso esperado:

- Algunas ventas se registraran como San Benjamin.
- Algunas ventas se registraran como Supra Medic.
- Algunas compras podran asociarse a San Benjamin o Supra Medic si corresponde.
- Algunos clientes o productos pueden distinguirse por el nombre comercial usado.
- Los reportes mensuales deben poder sumar ambos nombres comerciales como una sola operacion, pero tambien mostrar el detalle de cada uno.

## Comprobantes

Cada nombre comercial debe tener su propio grupo de comprobantes.

Comprobantes requeridos:

- Venta San Benjamin.
- Compra San Benjamin.
- Venta Supra Medic.
- Compra Supra Medic.

Regla critica:

La plantilla madre esta en Excel y debe respetarse exactamente. No se puede cambiar texto, orden, posiciones, formato, columnas, tamanos relativos, encabezados ni estructura sin autorizacion expresa del usuario.

Si una plantilla no esta identificada con claridad en el Excel, debe quedar como pendiente de validacion. No se debe inventar una plantilla.

## Ventas

Cada venta debe guardar:

- nombre comercial usado: San Benjamin o Supra Medic,
- usuario operador,
- cliente,
- productos,
- cantidades,
- precios,
- descuentos ingresados manualmente,
- comprobante usado.

La venta debe descontar inventario y generar movimiento de salida.

## Compras

Cada compra debe guardar:

- nombre comercial usado si aplica,
- proveedor,
- numero o valor de factura si corresponde,
- productos,
- cantidades,
- costos,
- descuentos ingresados manualmente,
- usuario operador,
- comprobante usado.

La compra debe aumentar inventario y generar movimiento de entrada.

## Descuentos y porcentajes

El sistema no debe imponer automaticamente 20%, 25%, 30% ni ningun porcentaje fijo.

Regla confirmada:

El operador siempre escribe el descuento o porcentaje.

Esto aplica a:

- producto dentro de una venta,
- producto dentro de una compra,
- facturacion,
- calculo de precio de venta por producto.

El sistema solo debe calcular usando el valor que el operador escribio.

## Movimiento mensual

El movimiento mensual debe funcionar como reporte y resumen de compras, ventas y resultados. No debe aplicar el porcentaje de 25% a 27%, porque ese porcentaje corresponde al calculo de precio de venta de cada producto.

El movimiento mensual debe considerar:

- compras del mes,
- ventas del mes,
- resultado base,
- usuario que hizo el calculo,
- fecha y hora.

El reporte mensual debe mostrar en un solo grafico:

- San Benjamin,
- Supra Medic,
- total general.

Tambien debe detallar:

- compras por nombre comercial,
- ventas por nombre comercial,
- ganancia o resultado por nombre comercial,
- total consolidado.

## Propuesta de modelo futuro

Para Sprints posteriores se recomienda agregar:

- `nombres_comerciales`
  - San Benjamin
  - Supra Medic

- En `ventas`
  - `nombre_comercial_id`
  - `descuento_porcentaje`
  - `descuento_monto`
  - `plantilla_comprobante_id`

- En `detalle_ventas`
  - `descuento_porcentaje`
  - `descuento_monto`

- En `compras`
  - `nombre_comercial_id`
  - `numero_factura`
  - `valor_factura`
  - `descuento_porcentaje`
  - `descuento_monto`
  - `plantilla_comprobante_id`

- En `detalle_compras`
  - `descuento_porcentaje`
  - `descuento_monto`

- `plantillas_comprobantes`
  - nombre comercial,
  - tipo: compra o venta,
  - hoja/rango origen Excel,
  - estado: pendiente de validacion o validada.

- `movimientos_mensuales`
  - mes,
  - anio,
  - resultado_base,
  - usuario_id.

## Distribucion por sprint

### Sprint 0

- Analizar y documentar reglas.
- Confirmar que San Benjamin y Supra Medic son nombres comerciales de una misma operacion.
- Documentar que los porcentajes y descuentos siempre los ingresa el operador.
- Documentar que las plantillas Excel no se modifican.
- Preparar el diseno de base de datos para soportarlo despues.

### Sprint 1

- Agregar nombres comerciales a la base.
- Asociar productos/clientes a un nombre comercial cuando corresponda.
- Preparar descuentos manuales por producto.
- Preparar estructura de plantillas de comprobantes.

### Sprint 2

- Aplicar nombres comerciales en ventas y compras.
- Permitir descuento manual por item y por factura.
- Seleccionar plantilla de comprobante segun nombre comercial y tipo.
- Generar comprobantes sin alterar la plantilla madre Excel.

### Sprint 3

- Movimiento mensual como reporte/resumen, sin aplicar el porcentaje de 25% a 27%.
- Reporte consolidado San Benjamin + Supra Medic.
- Grafico unico con detalle por nombre comercial.
- Reportes de compras, ventas y ganancias separadas y consolidadas.

## Pendientes antes de programar Sprints 1-3

- El usuario definira el estilo visual de login y dashboard.
- Validar visualmente las plantillas exactas de San Benjamin y Supra Medic en el Excel.
- Confirmar si compras siempre debe elegir nombre comercial o solo en algunos casos.
- Confirmar si productos/clientes tendran nombre comercial predeterminado o se elegira solo al vender/comprar.

## Actualizacion del cliente - 10 de agosto de 2026

Se agregan los siguientes requisitos derivados de la conversacion con el cliente:

- Estilo visual solicitado: glass claro.
- Color principal de referencia: `#67bed9` Lagoon Glass.
- Login con fondo azul.
- Logo de San Benjamin en una esquina del login.
- Logo de Supra Medic en la otra esquina del login.
- Tipo de cambio visible o disponible en login y dashboard.
- Dashboard con opcion para cambiar nombre comercial entre San Benjamin y Supra Medic.
- Al cambiar el nombre comercial debe mostrarse una advertencia de confirmacion.
- El cambio de nombre comercial debe cambiar tambien el NIT correspondiente.
- Productos con precio de ingreso referencial, calculo de precio de venta y conversion de dolares a bolivianos por tipo de cambio del dia.
- Regla aclarada de precio de venta: cuando se compra un insumo o producto, al venderlo el operador selecciona un porcentaje dentro del rango de 25% a 27% sobre el costo o precio de ingreso para calcular el precio final de venta.
- Fechas objetivo:
  - 14 de agosto de 2026: entrega Sprint 0 y Sprint 1.
  - 21 de agosto de 2026: retroalimentacion del cliente.

Documento detallado de distribucion por sprint:

- `docs/actualizacion-cliente-2026-08-10-sprints.md`
