# Informe de avance - Sprint 0

**Proyecto:** Modernizacion del sistema de inventario, compras y ventas  
**Etapa:** Sprint 0 - Revision inicial y preparacion tecnica  
**Fecha:** 27 de julio de 2026  
**Archivo revisado:** `Sistemas de inventarios Compras Ventas.xlsm`

## Resumen general

Durante esta primera etapa se reviso el archivo Excel que actualmente sostiene la operacion del negocio. El objetivo fue entender como esta organizado, que informacion contiene, que partes son importantes para conservar y como puede trasladarse gradualmente a una aplicacion web mas estable y facil de mantener.

Tambien se preparo una base inicial en Laravel para que el proyecto ya tenga una estructura tecnica desde donde continuar el desarrollo. Esta base no representa aun el sistema final, pero si deja ordenado el punto de partida para los siguientes sprints.

## Trabajo realizado

Se hizo una revision inicial del Excel entregado, identificando hojas relacionadas con productos, clientes, proveedores, entradas, salidas, ajustes, reportes y comprobantes.

Ademas, se preparo la estructura inicial del sistema web:

- proyecto Laravel creado y configurado,
- login basico con usuario y contrasena,
- estructura inicial de base de datos,
- modelos principales del sistema,
- controladores base,
- servicios iniciales para manejar inventario, compras y ventas,
- vista inicial del dashboard,
- preparacion preliminar para importar datos desde Excel,
- preparacion preliminar para generar comprobantes en PDF,
- documentacion tecnica y funcional del avance.

## Revision del Excel

El archivo Excel contiene varias hojas ocultas y de trabajo interno. Entre las mas importantes se identificaron hojas para:

- productos,
- precios,
- compras o entradas,
- ventas o salidas,
- ajustes de inventario,
- clientes,
- proveedores,
- comprobantes,
- reportes.

Tambien se detecto que el archivo no maneja el inventario como un numero aislado, sino que lo calcula a partir de entradas, salidas y ajustes. Esta es una logica importante y fue tomada como base para el diseno del sistema web.

## Criterio de inventario

Uno de los puntos principales de la revision fue conservar la trazabilidad del stock. Por eso, la propuesta tecnica no se basa en editar manualmente una existencia final, sino en registrar movimientos.

La idea para los siguientes sprints es que:

- cada compra genere una entrada,
- cada venta genere una salida,
- cada ajuste quede registrado,
- cada movimiento guarde usuario, fecha y referencia,
- el stock pueda consultarse desde el historial.

Este enfoque reduce errores y permite auditar mejor lo que ocurre con cada producto.

## Comprobantes

Se identificaron hojas del Excel que funcionan como base para comprobantes de compra y venta.

Queda establecido que estas plantillas deben respetarse tal como estan. No se deben cambiar textos, ubicaciones, columnas, orden, formatos ni estructura visual sin una indicacion expresa.

En esta etapa se dejo preparada una primera base tecnica para generar comprobantes desde la aplicacion, pero queda pendiente validar visualmente las plantillas exactas antes de cerrar esa parte como definitiva.

## Definiciones funcionales

Durante el analisis se confirmo que el sistema debe permitir trabajar con mas de un nombre comercial dentro de una misma operacion. En terminos practicos, el sistema debe permitir distinguir ciertos registros, comprobantes y reportes segun el nombre comercial usado, sin dividir la administracion general del negocio.

Tambien se confirmo que los descuentos y porcentajes no deben aplicarse automaticamente con valores fijos. El operador debe poder ingresar manualmente el valor que corresponda en cada proceso donde aplique.

Estas reglas quedaron documentadas para tomarse en cuenta en los siguientes sprints.

## Login

Se dejo listo un login basico y funcional. La pantalla de ingreso contiene solamente:

- correo electronico,
- contrasena,
- boton de ingreso.

La seleccion de nombre comercial o de modulos no forma parte del login. Eso corresponde al flujo interno del sistema despues de iniciar sesion.

## Base tecnica preparada

La aplicacion quedo preparada con una estructura inicial que permite seguir creciendo el sistema de forma ordenada.

La base generada incluye:

- usuarios con rol y estado,
- productos,
- categorias,
- clientes,
- proveedores,
- compras,
- ventas,
- detalles de compra y venta,
- movimientos de inventario,
- configuracion general,
- registro preliminar de importaciones,
- servicios de negocio,
- vistas iniciales.

## Entrega portable

Tambien se preparo una carpeta para trasladar el proyecto en una memoria USB y ejecutarlo en otro equipo Windows.

Esta carpeta incluye:

- una copia del proyecto,
- PHP portable,
- instaladores necesarios,
- scripts para preparar el primer uso,
- script para iniciar el servidor local,
- instrucciones de ejecucion.

Esto permite mostrar o probar el avance sin depender de una instalacion completa en servidor.

## Validaciones realizadas

Se realizaron pruebas tecnicas basicas para confirmar que la base preparada funciona correctamente:

- migraciones ejecutadas,
- datos iniciales cargados,
- login disponible,
- rutas principales verificadas,
- vistas compiladas correctamente,
- pruebas automatizadas iniciales aprobadas,
- prueba de ejecucion desde la carpeta portable.

Tambien se hizo una lectura preliminar del Excel desde el importador. Esa lectura genero observaciones de datos que deberan revisarse antes de realizar una importacion definitiva.

## Pendientes para la siguiente etapa

Para continuar con el desarrollo se recomienda:

- definir el estilo visual definitivo,
- validar las plantillas de comprobantes,
- completar la estructura para nombres comerciales,
- definir si productos o clientes tendran un nombre comercial predeterminado,
- implementar descuentos manuales en compras y ventas,
- completar la importacion de datos desde Excel,
- avanzar con reportes operativos,
- probar con datos reales revisados por el cliente.

## Conclusion

El Sprint 0 queda completado como una etapa de revision, ordenamiento y preparacion. Se entendio la estructura principal del Excel, se identificaron las reglas que deben respetarse y se dejo creada una base Laravel funcional para continuar el desarrollo sin perder la logica que el negocio ya utiliza.

El siguiente paso recomendado es elegir el estilo visual definitivo y avanzar con los modulos funcionales priorizados, manteniendo siempre la logica util del Excel y respetando las plantillas originales de comprobantes.
