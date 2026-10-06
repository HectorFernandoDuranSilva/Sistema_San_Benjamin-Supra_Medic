# Actualizacion de requisitos del cliente - 10 de agosto de 2026

Este documento interpreta la conversacion recibida con el cliente y acomoda cada punto dentro del sprint correspondiente. No reemplaza la plantilla madre del Excel ni autoriza cambios visuales en comprobantes.

## Resumen interpretado

El cliente confirmo nuevos criterios para diseno visual, manejo de nombres comerciales, tipo de cambio, calculo de precios y fechas de presentacion.

Puntos principales:

- El login debe usar fondo azul con estilo glass claro.
- El color visual solicitado es `#67bed9` Lagoon Glass.
- En el login deben aparecer los logos de San Benjamin y Supra Medic, cada uno en una esquina.
- El dashboard debe permitir cambiar el nombre comercial activo entre San Benjamin y Supra Medic.
- Al cambiar el nombre comercial debe mostrarse una advertencia antes de confirmar.
- Al cambiar el nombre comercial tambien debe cambiar el NIT correspondiente.
- El tipo de cambio debe estar visible o disponible en el login y tambien en el dashboard.
- Los productos se manejaran con precio de ingreso referencial y precio de venta calculado.
- El calculo debe partir de valores en dolares y convertir a bolivianos con el tipo de cambio del dia.
- Para calcular el precio final de venta, el operador debe seleccionar manualmente un porcentaje dentro del rango de 25% a 27% sobre el costo o precio de ingreso del producto.
- Fechas solicitadas: entrega de Sprint 0 y Sprint 1 para el 14 de agosto de 2026; retroalimentacion para el 21 de agosto de 2026.

## Regla aclarada de porcentajes para venta

### Margen manual entre 25% y 27%

El cliente aclaro que cuando la empresa compra un insumo o producto, al momento de venderlo el operador debe seleccionar un porcentaje dentro del rango de 25% a 27% para calcular el precio final de venta.

Regla confirmada:

- El porcentaje no debe ser totalmente automatico.
- El operador elige el porcentaje de venta.
- El porcentaje permitido debe estar entre 25% y 27%.
- El calculo parte del costo o precio de ingreso del producto.
- El resultado es el precio sugerido/final de venta, segun validacion del operador.

Formula funcional sugerida:

`precio_venta = costo_ingreso + (costo_ingreso * porcentaje_operador / 100)`

Pendiente menor de validacion:

- Confirmar si el operador puede elegir decimales dentro del rango, por ejemplo 25.5%, 26.3% o 27%.
- Confirmar si el precio calculado queda bloqueado o si el operador puede ajustarlo manualmente antes de guardar.
- Confirmar si el porcentaje mencionado como 3% corresponde a otra regla aparte o queda descartado para el calculo de precio de venta.

## Distribucion por sprint

### Sprint 0 - Analisis y definiciones

Incluir en Sprint 0:

- Documentar el nuevo requerimiento visual: estilo glass claro.
- Registrar color principal solicitado: `#67bed9`.
- Documentar que el login debe tener fondo azul y logos de ambos nombres comerciales.
- Documentar que el sistema debe manejar tipo de cambio diario.
- Documentar que los precios se trabajaran en dolares y bolivianos.
- Documentar que el cambio de nombre comercial debe cambiar el NIT.
- Documentar que el cambio de nombre comercial requiere advertencia de confirmacion.
- Documentar la regla confirmada: el precio final de venta se calcula con un porcentaje elegido por el operador entre 25% y 27% sobre el costo o precio de ingreso.
- Registrar fechas objetivo:
  - 14 de agosto de 2026: entrega Sprint 0 y Sprint 1.
  - 21 de agosto de 2026: retroalimentacion del cliente.

### Sprint 1 - Base visual, productos y configuracion inicial

Incluir en Sprint 1:

- Aplicar estilo visual glass claro al login y dashboard.
- Usar `#67bed9` como color principal de referencia visual.
- Colocar logo de San Benjamin en una esquina del login.
- Colocar logo de Supra Medic en la otra esquina del login.
- Agregar campo o visualizacion de tipo de cambio en login.
- Agregar tipo de cambio en dashboard.
- Crear o preparar configuracion de tipo de cambio diario.
- Preparar datos de nombres comerciales con NIT correspondiente.
- Permitir que productos tengan:
  - precio de ingreso referencial en dolares,
  - tipo de cambio usado,
  - precio convertido a bolivianos,
  - porcentaje de venta elegido por operador entre 25% y 27%,
  - precio de venta calculado.
- Preparar validaciones para que el operador revise los valores antes de guardar.

### Sprint 2 - Ventas, compras y aplicacion de calculos

Incluir en Sprint 2:

- Aplicar el precio de venta calculado al registrar ventas.
- Permitir que el operador confirme o ajuste el precio calculado antes de vender.
- Guardar el tipo de cambio usado en cada venta.
- Guardar el nombre comercial usado en cada venta.
- Cambiar NIT y datos del comprobante segun el nombre comercial seleccionado.
- Mostrar advertencia cuando el operador cambie de nombre comercial antes de emitir o registrar.
- Aplicar calculos de porcentaje sobre productos ingresados usando el rango permitido de 25% a 27%.
- Mantener registro historico de:
  - precio de ingreso,
  - porcentaje aplicado entre 25% y 27%,
  - tipo de cambio,
  - precio final de venta.

### Sprint 3 - Reportes y movimiento mensual

Incluir en Sprint 3:

- Reporte mensual con compras y ventas.
- Reporte mensual sin aplicar el porcentaje de 25% a 27%, porque ese porcentaje corresponde a cada producto al calcular su precio de venta.
- Reporte consolidado de ambos nombres comerciales.
- Detalle separado por nombre comercial.
- Grafico unico con comparacion de resultados por nombre comercial.
- Mostrar conversiones relevantes entre dolares y bolivianos si corresponde.

## Criterio visual confirmado

El cliente envio una referencia de color:

- HEX: `#67bed9`
- Nombre visual: Lagoon Glass
- RGB: `103, 190, 217`

Uso recomendado:

- Fondo principal o acento del login.
- Botones principales.
- Elementos glass claro.
- Detalles de dashboard.

No usar este color para modificar comprobantes existentes sin autorizacion, porque los comprobantes deben respetar la plantilla madre del Excel.

## Fechas

- 14 de agosto de 2026: entrega esperada de Sprint 0 y Sprint 1.
- 21 de agosto de 2026: fecha esperada para retroalimentacion del cliente.
