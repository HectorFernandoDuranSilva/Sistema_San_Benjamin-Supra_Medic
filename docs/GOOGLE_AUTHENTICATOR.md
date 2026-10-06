# Google Authenticator para acciones sensibles

## Qué protege

Un administrador debe ingresar un código de Google Authenticator para:

- Eliminar o desactivar productos, categorías, clientes y usuarios.
- Eliminar definitivamente un producto o retirar un lote.
- Anular ventas, cobros y pagos a proveedores.
- Cambiar la cabecera de una venta o compra ya registrada.
- Eliminar un reporte guardado.

La contraseña de inicio de sesión no se solicita para estas acciones.

## Activación inicial

1. Ingrese como administrador.
2. Abra `Administración > Seguridad`.
3. Seleccione `Generar código QR`.
4. Escanee el QR con Google Authenticator, Microsoft Authenticator, Authy u otra aplicación compatible.
5. Escriba el código de seis dígitos para confirmar.
6. Guarde los diez códigos de recuperación que se muestran una sola vez.

## Uso diario

1. Abra la acción sensible, por ejemplo `Eliminar`, `Anular` o `Editar cabecera`.
2. Escriba el código actual de seis dígitos de la aplicación.
3. Confirme la operación.

Un código cambia aproximadamente cada 30 segundos. Si no hay teléfono disponible, se puede usar uno de los códigos de recuperación; cada uno se invalida luego de usarse.

## Protección ante intentos incorrectos

- Cinco códigos incorrectos bloquean temporalmente las acciones protegidas durante 15 minutos.
- Los rechazos se registran en auditoría sin guardar el código ingresado.
- Una vez vencido el bloqueo, el contador vuelve a empezar.

## Cambio de teléfono

1. Ingrese como administrador y abra `Administración > Seguridad`.
2. Elija `Cambiar autenticador`.
3. Ingrese un código válido de la aplicación anterior o un código de recuperación.
4. Escanee el nuevo QR y confirme el código del nuevo teléfono.
5. Guarde los nuevos códigos de recuperación.

El responsable debe verificar que cada administrador active su propio autenticador antes de utilizar acciones protegidas.
