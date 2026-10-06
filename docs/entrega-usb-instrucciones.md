# Instrucciones para entrega USB

La carpeta `ENTREGA_USB` esta preparada para copiarse a una memoria USB y ejecutarse en un equipo Windows.

## Contenido

- `proyecto/`: copia portable del proyecto Laravel.
- `runtime/php/`: PHP 8.4 portable extraido para ejecutar Laravel sin instalar PHP globalmente.
- `instaladores/`: instaladores o paquetes necesarios.
- `INICIAR_SAN_BENJAMIN.bat`: inicia el servidor local.
- `PREPARAR_PRIMER_USO.bat`: ejecuta configuracion inicial si hace falta.
- `README_USB.md`: guia rapida para el usuario que recibira el USB.

## Uso rapido

1. Copiar la carpeta `ENTREGA_USB` completa a la computadora destino.
2. Ejecutar `PREPARAR_PRIMER_USO.bat`.
3. Ejecutar `INICIAR_SAN_BENJAMIN.bat`.
4. Abrir `http://127.0.0.1:8000/login`.

## Acceso inicial

- Usuario: `admin@sanbenjamin.local`
- Contrasena: `Cambiar123!`

Estas credenciales son para demostracion local y deben cambiarse antes de un uso real.

## Nota

Esta entrega portable usa SQLite para facilitar la ejecucion local. Para produccion se recomienda VPS con MySQL o PostgreSQL, HTTPS y configuracion segura.
