# San Benjamin-SupraMedic para Linux

Esta copia ejecuta el backend NestJS y el frontend Vue sin Docker.

## Requisitos

- Ubuntu/Debian o distribución compatible.
- Node.js 20 o superior y npm.
- PostgreSQL 14 o superior.

## Inicio rápido

1. En una terminal, entre en esta carpeta.
2. Ejecute `chmod +x instalar-linux.sh iniciar-linux.sh`.
3. Ejecute `./instalar-linux.sh`.
4. Edite `backend_sanbenjamin/.env` con la contraseña local de PostgreSQL.
5. Cree la base de datos: `createdb -U postgres sis257_sanbenjamin`.
6. Ejecute `./iniciar-linux.sh`.
7. Abra `http://127.0.0.1:5173`.

El script instala dependencias, genera Prisma, sincroniza el esquema y carga los datos de demostración. El frontend queda accesible desde la red local en el puerto 5173 y la API usa el puerto 3000.

Usuario de demostración: `hector`
Contraseña: `hector123`
