# San Benjamin Inventarios

Aplicacion Laravel para migrar el sistema actual de inventario, compras, ventas y recibos desde el archivo Excel con macros `Sistemas de inventarios Compras Ventas.xlsm`.

## Requisitos

- PHP 8.3 o superior compatible con Laravel 13.
- Extensiones PHP: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pdo`, `pdo_mysql` o `pdo_pgsql`, `session`, `tokenizer`, `xml`, `zip`, `gd`.
- Composer 2.
- MySQL 8 o PostgreSQL 15, configurable en `.env`.
- Nginx o Apache en servidor Linux.
- Certificado SSL para HTTPS.

## Instalacion local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Antes de ejecutar los seeders en producción, defina un administrador único en `.env`:

```env
ADMIN_NAME=Administrador
ADMIN_EMAIL=admin@tudominio.com
ADMIN_USERNAME=usuario-administrador
ADMIN_PASSWORD=clave-segura
```

## Base de datos

La aplicacion soporta MySQL o PostgreSQL mediante `.env`.

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sanbenjamin
DB_USERNAME=sanbenjamin
DB_PASSWORD=clave-segura
```

Para PostgreSQL usa `DB_CONNECTION=pgsql` y puerto `5432`.

## Importar Excel

Ruta: `/importar/excel`.

El importador lee las hojas reales detectadas:

- `Productos`: codigo, nombre, categoria, medida, ubicacion, stock minimo, existencia y costo.
- `listProductos`: precio de venta y precios auxiliares.
- `Clientes`: datos maestros de clientes.
- `Proveedor`: datos maestros de proveedores.

Antes de confirmar genera revision de:

- codigos vacios,
- productos duplicados,
- productos sin precio,
- stock negativo,
- filas sin datos.

La importacion usa `updateOrCreate` por codigo/documento para no duplicar datos maestros si se ejecuta mas de una vez. El stock inicial confirmado se registra como movimiento de inventario tipo `entrada` con referencia `importacion_excel`.

## Recibos y PDF

Rutas:

- Venta: `/ventas/{venta}/recibo` y `/ventas/{venta}/pdf`.
- Compra: `/compras/{compra}/recibo` y `/compras/{compra}/pdf`.

Las vistas Blade estan en:

- `resources/views/recibos/venta.blade.php`
- `resources/views/recibos/compra.blade.php`

La primera replica se baso en las hojas `PrintVentas` y `PrintCompras`. Queda marcado como pendiente validar visualmente pixel a pixel contra el Excel antes de cerrar produccion, porque el archivo usa celdas combinadas, formatos y posibles dibujos de Excel.

## Alcance Sprint 0

El cierre funcional del Sprint 0 esta documentado en `docs/sprint-0-alcance-funcional.md`.

Reglas confirmadas:

- San Benjamin y Supra Medic son nombres comerciales de una misma operacion, no dos empresas legales separadas.
- Cada venta debe guardar con que nombre comercial fue hecha.
- Los reportes mensuales deben consolidar ambos nombres, pero detallar ganancias por cada uno.
- Los descuentos y porcentajes siempre los ingresa el operador.
- Cada nombre comercial debe tener sus propias plantillas de comprobantes de compra y venta.
- Las plantillas madre del Excel no deben modificarse sin autorizacion expresa.

## Produccion en VPS

1. Crear VPS Ubuntu Server.
2. Instalar Nginx, PHP, extensiones, Composer y MySQL/PostgreSQL.
3. Crear base de datos y usuario.
4. Subir o clonar el proyecto.
5. Crear `.env` con `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://tudominio.com`.
6. Ejecutar:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

7. Permisos:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache
```

8. Nginx ejemplo:

```nginx
server {
    listen 80;
    server_name tudominio.com;
    root /var/www/sanbenjamin/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

9. Activar SSL con Certbot:

```bash
sudo certbot --nginx -d tudominio.com
```

10. Probar login, productos, importacion, ventas, compras, PDF y reportes.

## Backups

MySQL:

```bash
mysqldump -u usuario -p sanbenjamin > backup_$(date +%F).sql
tar -czf storage_$(date +%F).tar.gz storage/app
```

PostgreSQL:

```bash
pg_dump -U usuario sanbenjamin > backup_$(date +%F).sql
tar -czf storage_$(date +%F).tar.gz storage/app
```

Programa estos comandos con `cron` y copia los archivos a un almacenamiento externo.
