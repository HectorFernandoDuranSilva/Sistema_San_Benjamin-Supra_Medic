# Guia del entregable - Sprint 1

## Contenido del paquete

El entregable contiene una copia funcional del proyecto Laravel en la carpeta `proyecto` y documentacion de apoyo en la carpeta `documentacion`.

No se incluye el archivo `.env` real, logs, base de datos local de prueba ni archivos temporales. Esos archivos deben conservarse desde la instalacion anterior del cliente o configurarse nuevamente segun el servidor donde se instale.

## Credenciales de prueba

- Usuario: el definido por el administrador durante la instalacion.
- Contrasena: la definida por el administrador durante la instalacion.

## Actualizacion sobre una version anterior

Si el cliente ya tiene una version instalada, no se debe reemplazar todo sin respaldo.

Pasos recomendados:

1. Hacer copia de seguridad de la carpeta actual del sistema.
2. Hacer respaldo de la base de datos.
3. Conservar el archivo `.env` de la instalacion anterior.
4. Reemplazar los archivos del proyecto por los archivos nuevos de la carpeta `proyecto`.
5. Volver a colocar el `.env` original si fue necesario.
6. Ejecutar:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Tipo de cambio

El sistema consulta el tipo de cambio desde la fuente oficial configurada.

En esta version se dejo activo el refresco automatico desde la aplicacion web. Al abrir login, seleccion de nombre comercial o dashboard, el sistema revisa si debe actualizar el valor. El intervalo por defecto es cada 4 horas.

Variables recomendadas en `.env`:

```env
BCB_AUTO_UPDATE_ON_WEB=true
BCB_AUTO_UPDATE_INTERVAL_HOURS=4
```

En servidor de produccion tambien se recomienda configurar el programador de Laravel para ejecutar tareas automaticas:

```bash
* * * * * cd /ruta/del/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

## Modulos incluidos

- Login con usuario y contrasena.
- Seleccion de nombre comercial despues del login.
- Dashboard operativo.
- Modo claro y modo oscuro.
- Productos.
- Categorias.
- Clientes con NIT.
- Compras.
- Ventas.
- Movimientos de inventario.
- Comprobantes de compra y venta.
- Consulta y actualizacion de tipo de cambio.
- Recalculo automatico de precios cuando cambia el tipo de cambio.
- Eliminacion controlada de productos desde productos e inventario.
- Eliminacion definitiva protegida con contrasena de administrador.

## Verificacion recomendada despues de instalar

1. Entrar al sistema con el usuario de prueba.
2. Seleccionar un nombre comercial.
3. Verificar que el dashboard cargue correctamente.
4. Crear una categoria.
5. Crear o revisar un cliente con NIT.
6. Crear o revisar un producto.
7. Registrar una compra de prueba.
8. Verificar que aumente el inventario.
9. Registrar una venta de prueba.
10. Verificar que descuente inventario.
11. Revisar comprobante de compra y venta.
12. Confirmar que el tipo de cambio se muestre con dos decimales.
13. Crear un producto de prueba, eliminarlo desde Productos y verificar que desaparezca de la lista activa.
14. Crear otro producto de prueba, eliminarlo desde Inventario y verificar que desaparezca de la lista activa.

## Pruebas automaticas

Antes de cerrar el entregable se ejecuto la bateria automatica del sistema:

- 28 pruebas ejecutadas.
- 188 verificaciones realizadas.
- Resultado correcto.
