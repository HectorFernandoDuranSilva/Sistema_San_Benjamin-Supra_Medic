# Registro de requisitos - 25 de septiembre de 2026

Este documento conserva las solicitudes recibidas por mensajes y transcripciones de audio. Es una lista de planificación: no confirma que alguna función esté implementada.

## 1. Cobros y cuotas de clientes

- Al emitir una factura debe poder indicarse que el cliente pagará en cuotas.
- Al registrar cada cobro se debe relacionar con la cuota correspondiente, registrar fecha, importe y saldo pendiente.
- El sistema debe mostrar las cuentas por cobrar en el dashboard.
- Debe alertar cuando una cuota o factura lleve 10 días de impago.

**Forma recomendada:** registrar un plan de cuotas con importe, vencimiento y estado por cuota. Los pagos parciales se aplican a la cuota más antigua pendiente. Esto evita que una factura aparezca como pagada cuando aún conserva saldo.

**Decisión confirmada:** los 10 días de impago se cuentan desde la fecha de vencimiento de cada cuota.

## 2. Resumen mensual de IVA e IT

- Trabajar únicamente con facturas registradas del mes; las notas de entrega quedan fuera del cálculo.
- Mostrar el detalle de números e importes de facturas de venta y de compra para cada empresa.
- Sumar las ventas facturadas y las compras facturadas por separado.
- Calcular la diferencia como **compras menos ventas**.
- Calcular el IVA como el 13% de esa diferencia.
- Calcular el IT como el 3% del total de ventas facturadas.
- Mantener IVA e IT separados y mostrar además el resultado combinado: **IVA más IT**.
- Cuando el resultado combinado sea negativo, mostrarlo en rojo como **Por pagar**, según la hoja de referencia aprobada.
- Repetir el cálculo por mes y por cada nombre comercial: San Benjamín y Supra Medic.

### Investigación y criterio recomendado

Para el Régimen General boliviano, el IVA se trabaja por diferencia entre dos componentes del mismo período:

1. **IVA débito fiscal:** 13% de las ventas facturadas gravadas.
2. **IVA crédito fiscal:** 13% de las compras facturadas que estén vinculadas a la actividad gravada y sean computables.
3. **Saldo IVA:** IVA débito menos IVA crédito. Si el resultado es positivo, representa un saldo referencial a pagar; si es negativo, representa saldo a favor que no debe ocultarse.
4. **IT:** 3% de la base de ventas alcanzadas. Se declara por separado del IVA.

La fórmula verbal “ventas menos compras y al resultado sacar 13%” llega al mismo importe que débito menos crédito cuando todas las ventas y compras son facturas gravadas, sin descuentos, devoluciones, exenciones ni compras no computables. La separación en débito y crédito es más segura: permite explicar cada cifra, incorporar ajustes futuros y detectar documentos que no deben dar crédito fiscal.

**Criterio operativo confirmado por la hoja de referencia:** para el reporte interno solicitado se mostrará: `Diferencia = compras facturadas - ventas facturadas`; `IVA = diferencia x 13%`; `IT = ventas facturadas x 3%`; y `Por pagar = IVA + IT`. En el ejemplo de San Benjamín de agosto de 2026: ventas Bs 146.458,00; compras Bs 81.831,00; diferencia Bs -64.627,00; IVA Bs -8.401,51; IT Bs 4.393,74; por pagar Bs -4.007,77. El resultado negativo se presenta en rojo como “Por pagar”.

El sistema debe continuar identificando este resultado como **referencial para control interno**, no como declaración jurada ni importe definitivo ante el SIN. La declaración se presenta mediante Formulario 200 para IVA y Formulario 400 para IT, con el Registro de Compras y Ventas previo.

Fuentes consultadas el 4 de octubre de 2026:

- Ley N.º 843: el débito fiscal se determina sobre ventas y el crédito fiscal sobre compras vinculadas a operaciones gravadas; también contempla saldo a favor del fisco o del contribuyente. [Texto de la Ley 843 - SIN](https://www.impuestos.gob.bo/wp-content/uploads/2025/10/4.-ley843-06-25.pdf)
- El SIAT describe el cálculo del crédito fiscal como base computable por 13% y el detalle de la declaración IVA. [Guía IVA Formulario 200 - SIAT](https://siatanexo.impuestos.gob.bo/index.php/declaraciones-juradas-en-formato-electronico/impuesto-al-valor-agregado/200-impuesto-al-valor-agregado)
- El SIAT calcula el IT con una alícuota del 3% de la base correspondiente. [Guía IT Formulario 400 - SIAT](https://siatinfo.impuestos.gob.bo/index.php/declaraciones-juradas-en-formato-electronico/impuesto-a-las-transacciones/400-impuesto-a-las-transacciones)

### Cambios concretos en Notas/Facturas

1. Mantener cuatro bloques separados: facturas de venta, notas de entrega, facturas de compra y notas de compra.
2. En la sección “Referencia tributaria”, incluir solo facturas activas dentro del período elegido. Las notas se muestran para control comercial, pero no se suman al IVA ni al IT.
3. Mostrar por cada nombre comercial el detalle de números e importes de facturas de venta y compra, junto con sus totales.
4. Mostrar en este orden: diferencia (compras menos ventas), IVA 13%, IT 3% sobre ventas y Por pagar (IVA más IT).
5. Resaltar en rojo el importe negativo de Por pagar, como en la hoja de referencia.
6. Mantener los resultados de San Benjamín y Supra Medic separados por mes. Las notas de entrega permanecen visibles para control comercial, pero no se incluyen en la fórmula.

### Cambios concretos en Balance mensual

1. Mantener el selector de mes y añadir el selector de nombre comercial: San Benjamín, Supra Medic o ambos.
2. Mostrar ventas y compras facturadas por separado, con el detalle de sus números de factura e importes.
3. Calcular diferencia como compras menos ventas y calcular el IVA al 13% sobre esa diferencia.
4. Mostrar el IT al 3% de las ventas facturadas de forma separada.
5. Mostrar el total combinado “Por pagar” como IVA más IT y resaltarlo en rojo cuando sea negativo.
6. Desglosar San Benjamín y Supra Medic de manera independiente en cada mes.
7. Repetir exactamente la misma lógica, cifras y colores en pantalla, PDF y Excel para evitar resultados diferentes según el formato descargado.
8. Añadir al pie: “Reporte interno referencial. Validar la declaración con contabilidad y SIAT antes de pagar.”

### Validación previa con contabilidad

Antes de activar el cálculo como referencia principal, el contador debe confirmar:

1. Que el formato interno de la hoja, especialmente la interpretación de un Por pagar negativo, será usado solo como control y no sustituye la declaración oficial.
2. Qué compras son computables como crédito fiscal y cuáles no.
3. Si los totales guardados en el sistema incluyen o no componentes que deban excluirse de la base gravada.
4. Cómo se registrarán devoluciones, descuentos y notas de crédito/débito futuras.
5. Si corresponde considerar compensaciones de IT con IUE; esa situación no debe asumirse sin criterio contable.

## 3. Información obligatoria del producto

- Agregar **marca** y **procedencia** a todos los productos.
- Exigir ambos datos al crear o editar un producto; no se debe permitir guardar si faltan.
- Completar los productos existentes antes de bloquear el formulario, para que el catálogo no quede con datos incompletos.

**Cuidado confirmado:** al incorporar estos campos y la nueva información del Excel, se deberá usar una copia de seguridad, revisión previa y pruebas completas para evitar que otros módulos dejen de funcionar, como ocurrió anteriormente.

## 4. Cotizaciones y su PDF

- Agregar marca, procedencia y vencimiento en las columnas de la cotización.
- Rellenar esos datos automáticamente desde producto y lote cuando estén disponibles, permitiendo modificarlos manualmente para esa cotización.
- Registrar y mostrar correctamente el vencimiento en el PDF.
- Usar tamaño de hoja carta.
- Mostrar “Sucre, [fecha y año]” en el PDF.
- Ubicar “PRESENTE” debajo del NIT del cliente.
- Mantener el resto de la plantilla comercial ya aprobada: datos de empresa, colores, logo, firma y textos finales.

**Regla propuesta:** el vencimiento automático debe provenir de un lote seleccionado. Si se cotiza sin stock, se permite escribirlo manualmente o dejarlo vacío, sin crear ni descontar inventario.

## 5. Dashboard y alertas

- Mostrar cuentas por cobrar: cantidad de documentos con saldo y monto total pendiente.
- Mostrar alertas por cuotas/facturas con 10 días de impago.
- Revisar la alerta del total facturado, además de los lotes vencidos o próximos a vencer, porque se indicó que no siempre aparece.

**Decisión confirmada:** el aviso de facturación se mantiene para el día 20 y el cierre de cada mes. No se requiere un aviso diario.

## 6. Búsqueda, inventario y facilidad de uso

- Agregar filtros de stock por rango, por ejemplo de 0 a 10, para listar los productos dentro de ese intervalo.
- Mejorar la búsqueda de productos en ventas y cotizaciones: buscar coincidencias dentro del nombre, no solo coincidencias exactas o al inicio. Ejemplos: “HCG”, “capilares” y “tubos capilares”.
- Confirmar que los clientes aparezcan por su nombre completo en todas las búsquedas; se reportó “Hospital del Niño” como “Hospital”.
- Permitir cotizar un producto que aún no tiene stock, sin obligar a crear códigos innecesarios. Debe quedar claramente separado de un producto inventariable para no alterar el stock.
- Desactivar los productos de la categoría “Convenio”, conservando ventas, compras y movimientos históricos.
- Ajustar la ubicación del botón “Agregar producto” en la pantalla que el cliente enviará como referencia. La solicitud es elevarlo para evitar desplazarse antes de agregar una línea.

**Por confirmar:** la imagen prometida para la ubicación del botón no fue incluida en este lote de mensajes.

## 7. Exportaciones

- Incorporar descarga de cotizaciones en PDF y Word.

**Decisión confirmada:** no se crearán nuevas exportaciones Word/PDF para inventario u otros listados; las exportaciones existentes se mantienen como están.

## Orden recomendado de trabajo

### Protección de datos: nueva base del Excel

El archivo recibido, `Sistemas de inventarios Compras Ventas nuevo (Recuperado automáticamente).xlsm`, contiene productos, clientes, proveedores, entradas y salidas históricas. No debe sustituir directamente la base activa.

La base activa al momento de la revisión contiene 464 productos, 239 clientes, 57 proveedores, 382 compras, 910 ventas y 3.723 movimientos de inventario. El conteo de registros del Excel, excluyendo encabezados y filas vacías, muestra 418 productos de catálogo, 241 clientes, 60 proveedores, 1.235 líneas de entrada y 2.501 líneas de salida con fecha e importe válidos. Hay además una salida inválida en la fila 97. Los tamaños de hoja informados inicialmente no representaban cantidades de registros. La fuente requiere conciliación antes de incorporar información.

Pasos obligatorios antes de cualquier carga:

1. Crear una copia fechada de la base activa y verificar que pueda abrirse.
2. Leer el Excel sin modificarlo y generar un reporte de diferencias por código, cliente, proveedor, compra, venta y movimiento.
3. Identificar registros nuevos, ya existentes, duplicados y aquellos con datos insuficientes.
4. Cargar primero todo en una copia de prueba de la base, nunca en la base de trabajo.
5. Ejecutar pruebas de Productos, Inventario, Compras, Ventas, Cotizaciones, Kardex, Notas/Facturas y Balance mensual sobre esa copia.
6. Comparar existencias y totales antes/después de la carga.
7. Aplicar la carga definitiva solo si la comparación y las pruebas son correctas.
8. Conservar el Excel original y la copia de seguridad hasta recibir aceptación final.

El importador existente solo contempla catálogo y un ajuste inicial de stock; antes de usar este Excel debe ampliarse para procesar entradas y salidas con trazabilidad, sin repetir movimientos ya registrados.

### Etapa 1. Preparar datos y filtros diarios

1. Revisar los nombres completos de clientes y la búsqueda en ventas, cotizaciones, historial y reportes.
2. Ajustar la búsqueda de productos para que encuentre palabras contenidas en el nombre y no solo el comienzo del texto.
3. Incorporar filtro de stock mínimo y máximo, con ejemplos rápidos como 0 a 10.
4. Revisar los productos de categoría “Convenio”, listar sus ventas/compras/movimientos y desactivarlos sin borrar el histórico.
5. Recibir la imagen pendiente para decidir la nueva ubicación del botón “Agregar producto”.

**Resultado esperado:** el operador encuentra clientes y productos correctamente, revisa faltantes por rango y no se eliminan datos históricos por error.

### Etapa 2. Completar el catálogo de productos

1. Agregar los campos marca y procedencia al producto.
2. Cargar la información conocida de los productos existentes desde una fuente aprobada.
3. Señalar los productos sin dato para que el administrador los complete, sin inventar información.
4. Activar la obligatoriedad al crear y editar, una vez que los históricos estén revisados.
5. Mostrar marca y procedencia donde sean útiles: producto, inventario, compra, venta y cotización.

**Resultado esperado:** ningún producto nuevo queda sin marca ni procedencia y el catálogo histórico conserva datos confiables.

### Etapa 3. Mejorar cotizaciones

1. Añadir marca, procedencia y vencimiento a cada línea de cotización.
2. Autocompletar marca/procedencia desde el producto y vencimiento desde el lote elegido.
3. Permitir editar esos tres valores solo en la cotización, sin cambiar el catálogo por accidente.
4. Permitir cotizar artículos sin stock como “ítem no inventariable” o “artículo por pedido”, sin descontar existencia ni crear un lote artificial.
5. Ajustar el PDF: papel carta, “Sucre, fecha y año”, “PRESENTE” debajo del NIT y columnas visibles aun cuando el dato sea manual.
6. Verificar en vista previa, PDF y descarga que la información coincida.

**Resultado esperado:** una cotización se puede preparar con productos disponibles o por pedido, y el documento comercial queda completo sin afectar inventario.

### Etapa 4. Implementar cuotas y cuentas por cobrar

1. Al emitir una factura, elegir pago al contado o crédito/cuotas.
2. Si se eligen cuotas, indicar cantidad, fechas de vencimiento e importes; validar que la suma sea igual al total de la factura.
3. Registrar cada cobro contra la cuota pendiente más antigua, permitiendo pagos parciales.
4. Mostrar por factura: total, cobrado, saldo, próxima cuota y estado.
5. Mostrar en dashboard: total de cuentas por cobrar, cuotas vencidas y cuotas con 10 días de atraso.
6. Permitir abrir desde la alerta la factura y registrar el pago.
7. Probar contado, una cuota, varias cuotas, pago parcial, pago total, anulación de pago y factura anulada.

**Resultado esperado:** el saldo pendiente se calcula desde los pagos reales y las alertas llevan directamente al cobro que requiere atención.

### Etapa 5. Ajustar IVA, IT y reportes mensuales

1. Aplicar el criterio operativo confirmado: compras menos ventas, IVA 13%, IT 3% sobre ventas y Por pagar como IVA más IT.
2. Actualizar Notas/Facturas con detalle de facturas e importes por empresa, manteniendo las notas de entrega fuera del cálculo.
3. Actualizar Balance mensual con el mismo desglose por mes y por empresa, incluido Por pagar negativo en rojo.
4. Aplicar las mismas cifras, etiquetas y colores a pantalla, PDF y Excel.
5. Comparar un mes real contra el Registro de Compras y Ventas/SIAT junto con contabilidad.
6. Dejar el texto “referencial” hasta obtener la validación del responsable contable.

**Resultado esperado:** los reportes sirven para anticipar el cierre mensual, sin sustituir el cálculo o la presentación oficial del contador.

### Etapa 6. Exportaciones y aceptación

1. Crear la descarga Word de cotización manteniendo el diseño y la información comercial del PDF aprobado.
2. Probar el PDF y Word con cotizaciones cortas, extensas, con lote y por pedido.
4. Realizar una demostración final: producto, compra, lote, venta, cuota, cobro, cotización, alerta y reporte mensual.

## Decisiones necesarias antes de programar

1. Criterio contable definitivo para IVA/IT.
2. Datos de marca y procedencia para productos ya existentes.

## Aclaraciones confirmadas y avance del 4 de octubre de 2026

- La decisión inicial de conservar IVA e IT sin un resultado combinado queda reemplazada el 5 de octubre de 2026 por el criterio de la hoja de referencia: diferencia de compras menos ventas, IVA 13%, IT 3% sobre ventas y Por pagar como suma de IVA e IT.
- Incorporar una acción explícita de revisión y confirmación de datos antes de exportar cotizaciones. Word se añade únicamente a cotizaciones; las exportaciones existentes de otros reportes continúan.
- Integrar pagos en cuotas con Ventas y contar el atraso desde el vencimiento de cada cuota.
- Crear un módulo administrativo para configurar todas las alertas e indicadores rápidos del dashboard: visibilidad, orden, plazos y umbrales. Los importes deben seguir calculándose desde operaciones reales, no editarse libremente como cifras.
- Desactivar Convenio conservando historial y relaciones.
- Mantener la prueba integral de aceptación para el final. Ejecutar pruebas específicas durante cada cambio.
- No crear entregables hasta que el usuario lo solicite.

Avance verificado:

1. Inventario filtra existencias por rango y conserva la exclusión de productos inactivos al buscar por nombre.
2. Ventas y Cotizaciones reconstruyen las opciones de sus buscadores de productos/clientes. Admiten palabras en distinto orden y búsquedas sin tildes; muestran ausencia de coincidencias sin elegir un producto anterior.
3. La prueba de exportación consulta un período explícito y ya no depende del mes del equipo.
4. Suite: 101 pruebas y 911 aserciones correctas. Buscador: seis comprobaciones correctas en Chrome.
5. Comparación de solo lectura guardada en `docs/excel-analysis/revision-recuperado-2026-10-04.json`. Integridad SQLite correcta y sin errores de relaciones.

La comparación inicial detecta 24 códigos de catálogo ausentes, 7 controles de compra ausentes y 30 controles de venta ausentes. No equivale todavía a una lista autorizada para insertar: hay que revisar controles, productos previamente retirados, fecha, empresa, detalle y pagos asociados. Se detectan 19 compras y 27 ventas con totales distintos; la igualdad de total tampoco garantiza igualdad de todas las líneas. Los clientes tienen 11 coincidencias ambiguas por documento.

Próximo trabajo: resolver diferencias y la fila inválida del Excel, generar respaldo consistente y probar una sincronización incremental en una copia. Luego completar catálogo, cotizaciones, cuotas, configuración del dashboard y reportes conforme a las etapas anteriores.

### Continuación verificada del 4 de octubre

- Se desactivaron 54 productos de Convenio sin borrar sus relaciones ni operaciones. Respaldo: `storage/app/backups/antes-convenio-20261004-140852-d0a263.sqlite`.
- De los 24 códigos ausentes del catálogo, 23 corresponden a productos retirados anteriormente y no deben volver a crearse. El producto realmente nuevo es 641, GETEIN- CA 125.
- De los controles ausentes, se identificaron 3 compras y 26 ventas nuevas para revisar/importar. Otros 4 controles de compra y 4 de venta contienen exclusivamente productos retirados: se excluyen.
- Las líneas de 382 compras y 909 ventas existentes coinciden al excluir los códigos retirados. Esto no sustituye la revisión pendiente de identidad del cliente/proveedor, empresa y documento.
- La venta EXCEL-V-840 tiene 3.050 unidades: el Excel cambia el precio de Bs 16 a Bs 14,70, de Bs 48.800 a Bs 44.835. No tiene cobros registrados. El cambio está identificado, todavía no aplicado.
- La fila 97 de Salidas tiene total vacío; subtotal y descuento son ambos Bs 28,50, con impuestos y otros cargos cero. El análisis recupera total cero sin modificar el Excel.
- La sincronización de movimientos NO se ha ejecutado en la base activa. Quedan por resolver coincidencias ambiguas de clientes/proveedores y comprobar existencias/lotes en una copia antes de aplicarla.
- Marca y procedencia se añadieron al catálogo. Son obligatorias al crear o editar desde el formulario, se muestran en la ficha y se incluyen en el historial de cambios. Los 464 productos anteriores conservan ambos campos pendientes, sin valores inventados.
- El cambio de estructura fue aplicado después de un respaldo consistente: `storage/app/backups/antes-marca-procedencia-20261004-101610.sqlite`.
- Pruebas posteriores: 103 correctas, 931 aserciones. Se verificó que las columnas y filas anteriores permanecen iguales después del cambio de estructura, con integridad y relaciones correctas.

Siguiente orden: sincronización incremental ensayada en copia; completar marca/procedencia/vencimiento por línea de cotización y artículos por pedido; cuotas con alertas desde su vencimiento; configuración del dashboard; revisión de reportes conservando el criterio actual; confirmación y Word de cotizaciones; aceptación integral. No se ha creado ningún entregable.

### Sincronización aplicada y cotizaciones verificadas

El usuario confirmó que el nuevo Excel es correcto después de informar las 33 diferencias de stock. Se tomó el saldo de Kardex como referencia y se registraron ajustes explícitos, sin borrar movimientos anteriores.

- Ensayo aislado y posterior aplicación incremental completados. Se añadieron 3 compras (13 líneas), 26 ventas (64 líneas), el producto 641 y una categoría. Se actualizaron datos de 115 productos existentes según el catálogo y la valoración del Excel.
- Se incorporaron 6 identidades de clientes y 7 de proveedores ausentes por combinación exacta de nombre/documento. Se conservaron las identidades históricas: no se fusionaron instituciones por compartir NIT.
- La venta EXCEL-V-840 quedó en Bs 44.835, con precio unitario Bs 14,70, conservando su cantidad y sus identificadores.
- Se añadieron 77 movimientos de las nuevas operaciones y 33 ajustes de conciliación. Los saldos de los lotes de cierre importados se conciliaron conservando identificadores y fechas existentes. No se inventaron asignaciones de lotes físicos a ventas históricas.
- Se conservaron íntegros cobros, pagos de compras, cotizaciones y asignaciones históricas de lotes. No se reactivaron productos de Convenio ni se reinsertaron los 23 códigos retirados.
- Las compras nuevas conservan la convención anterior de San Benjamin: Entradas no contiene una columna de empresa. Debe tenerse presente al revisar el reporte por nombre comercial.
- Respaldo anterior a la aplicación: `database/database.before-recovered-20261004-153445.sqlite`. Resultado detallado: `docs/excel-analysis/aplicacion-recuperado-2026-10-04.json`.
- Comparación posterior: 385 compras y 936 ventas con detalles coincidentes, cero diferencias de stock contra Kardex para los códigos presentes, integridad correcta. Los controles que solo contienen códigos retirados continúan excluidos.

Cotizaciones implementadas en esta continuación:

- Marca y procedencia se cargan desde el producto; elegir un lote carga su vencimiento. Los tres datos se pueden introducir o modificar en la cotización.
- Los valores se conservan en cada línea, aunque posteriormente cambie el catálogo. Editar la cotización no modifica el producto ni el lote.
- Se rechazan lotes que pertenezcan a otro producto. Cotizar no descuenta existencias ni reserva lotes.
- El PDF muestra esos datos, usa papel carta, incluye Sucre y fecha completa, y coloca PRESENTE debajo del NIT. Se conservaron nombres comerciales, logos, firmas, condiciones y ausencia de desglose de utilidad/impuestos.
- El botón Agregar producto se movió junto a los campos del producto, antes de las condiciones del PDF. Enter dentro de esos campos agrega la línea sin registrar accidentalmente toda la cotización.
- Cambio de estructura aplicado con respaldo `storage/app/backups/antes-cotizaciones-identidad-20261004-152121.sqlite`.

Verificaciones: 105 pruebas y 956 aserciones correctas; comprobación adicional de pantallas/exportaciones contra la copia actualizada (38 aserciones) sin modificarla; tres pruebas de aplicación protegida; ensayo repetido sin duplicar documentos; prueba en navegador de datos manuales, comillas, búsqueda sin resultados y Enter en escritorio/móvil; revisión visual de PDFs de ambas empresas y generación multipágina.

Pendientes: artículos por pedido sin crear producto; confirmación previa y exportación Word de cotizaciones; cuotas integradas con cobros y alertas a diez días desde el vencimiento; módulo de configuración de alertas e indicadores del dashboard; comprobación final de reportes con el criterio tributario actual; aceptación integral. No se ha preparado un entregable.

### Artículos por pedido completados

- Cotizaciones permite elegir Del catálogo o Por pedido. Por pedido admite descripción, presentación, cantidad, precio, marca, procedencia y vencimiento sin crear un producto ni modificar existencias.
- Las líneas manuales se conservan al editar y aparecen en el PDF con sus datos completos. Pueden combinarse con productos del catálogo.
- Cada línea por pedido tiene una selección para vincularla posteriormente a un producto registrado. La conversión a venta se bloquea mientras haya líneas sin vincular; después utiliza el flujo normal de stock de Ventas.
- Se mantienen las restricciones de revisión simultánea y de cotizaciones ya convertidas. No se permite sustituir silenciosamente el producto de una línea histórica del catálogo por un artículo manual.
- Migración aplicada con respaldo `storage/app/backups/antes-articulos-pedido-20261004-162110.sqlite`; todos los registros previos se conservaron, integridad y relaciones correctas.
- Verificación: 108 pruebas / 985 aserciones; comprobación adicional de pantallas/exportaciones con copia de la base actual (38 aserciones); navegador en escritorio y móvil para alta de artículo por pedido, datos conservados y selección del producto para vincular.

Pendientes actuales: confirmación previa y Word de cotizaciones; cuotas y sus alertas; configuración del dashboard; revisión final de reportes y aceptación integral. No se ha preparado un entregable.

### Confirmación y Word de cotizaciones completados

- Una cotización debe ser revisada y confirmada por una persona con acceso antes de habilitar sus descargas. La confirmación guarda fecha, hora y responsable.
- La pantalla muestra claramente si está pendiente de revisión o confirmada. Al modificar una cotización, la confirmación anterior se anula para obligar a revisar los datos nuevamente.
- PDF y Word solo están disponibles después de confirmar. El Word conserva la información comercial de cada empresa, logo, firma, Sucre con fecha, destinatario, PRESENTE, productos, marca, procedencia, vencimiento, total y condiciones de la cotización.
- Se verificó visualmente el Word en una hoja carta con una cotización de prueba de San Benjamín. La exportación se mantiene restringida únicamente a cotizaciones.
- Cambio de estructura aplicado con respaldo `storage/app/backups/antes-confirmacion-word-cotizaciones-20261004-214300.sqlite`. La integridad SQLite es correcta y no hubo cambios en la cantidad de registros existentes.
- Verificación posterior: 110 pruebas y 1.022 aserciones correctas, incluyendo bloqueo previo, confirmación registrada, exportaciones PDF/Word y nueva revisión al editar.

Pendientes actuales: cuotas y alertas desde su vencimiento; configuración del dashboard; revisión final de reportes con el criterio tributario actual; aceptación integral. No se ha preparado un entregable.

### Cuotas, alertas y revisión de reportes

- Las ventas pueden registrarse con pago único o con un calendario editable de cuotas. La suma debe coincidir con el total de la venta.
- Cada cobro de una venta con cuotas se asigna a una cuota concreta y no puede superar su saldo. Los cobros históricos continúan disponibles sin requerir asignación.
- El dashboard muestra el total de cuentas por cobrar y avisa sobre cuotas que siguen pendientes después del plazo configurado desde su vencimiento. El plazo inicial es de diez días y se puede cambiar en Administración.
- Administración permite mostrar u ocultar los indicadores y alertas existentes del dashboard. Los importes continúan siendo calculados desde operaciones reales.
- Respaldos previos: `storage/app/backups/antes-cuotas-ventas-20261004-221207.sqlite` y `storage/app/backups/antes-configuracion-dashboard-20261004-222155.sqlite`.
- Notas/Facturas y Balance mensual aplican ahora el criterio de la hoja aprobada: diferencia = compras facturadas menos ventas facturadas; IVA = diferencia x 13%; IT = ventas facturadas x 3%; Por pagar = IVA más IT. Las notas de entrega quedan fuera del cálculo. Un Por pagar negativo se muestra en rojo.
- Balance mensual permite filtrar por mes y nombre comercial. Los tres formatos —pantalla, PDF y Excel— muestran el desglose por empresa. Los Excel conservan sus cuatro hojas de detalle editables y resumen con fórmulas equivalentes; al corregir una copia, se recalculan diferencia, IVA, IT y Por pagar al abrirla. La copia advierte que esos cambios no modifican los documentos guardados en el sistema.
- La configuración del dashboard ahora permite definir una vista distinta para Administradores y Operadores. Por cada rol se puede ocultar o mostrar indicadores, bloques operativos y alertas, incluyendo movimiento mensual, últimas operaciones y el aviso de facturación. Las preferencias generales se mantienen como base para evitar alterar la vista actual hasta que se configure cada rol.
- Marca y procedencia se muestran al elegir un producto y en cada línea consultada de Compras y Ventas. También aparecen en Inventario, Movimientos y Lotes para identificar el artículo sin abrir su ficha.
- El aviso de facturación queda confirmado como regla operativa para el día 20 y el cierre de cada mes.
- Respaldo previo: `storage/app/backups/antes-dashboard-por-rol-20261005-004216.sqlite`. La base activa quedó con integridad SQLite correcta y sin relaciones incompletas.
- Verificación: 113 pruebas y 1.072 aserciones correctas. No se ha preparado un entregable.

Pendiente actual: aceptación integral con datos reales y revisión operativa final. No se ha preparado un entregable.

### Aceptación técnica final

- Todas las migraciones se encuentran aplicadas en la base activa.
- Integridad SQLite y relaciones entre tablas: correctas, sin referencias rotas.
- Recorrido aislado sobre una copia de la base: compra, movimiento de entrada, venta y validación de stock final completados correctamente. La base operativa no fue modificada por esta prueba.
- Datos activos al momento de la revisión: 465 productos, 245 clientes, 64 proveedores, 385 compras, 936 ventas, 5 cotizaciones y 3.833 movimientos de inventario.
- Pendientes operativos conservados sin inventar información: 207 productos activos sin precio de venta, 411 productos activos sin marca o procedencia y 146 lotes activos con stock sin fecha de vencimiento.

La aceptación técnica está completada. La aceptación operativa requiere que el responsable del negocio revise los pendientes de datos antes de declarar el sistema cerrado. No se ha preparado un entregable.

### Seguimiento de datos pendientes

- El dashboard ahora muestra alertas con acceso directo para productos activos sin marca o procedencia, y para lotes activos con stock sin fecha de vencimiento.
- Las alertas se suman a las ya existentes de precio, categoría y stock, sin modificar datos ni crear valores supuestos.
- Verificación posterior: 112 pruebas y 1.034 aserciones correctas.
