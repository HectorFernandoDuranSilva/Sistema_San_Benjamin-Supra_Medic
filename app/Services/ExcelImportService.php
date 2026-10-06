<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\ImportacionExcel;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\TipoCambio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelImportService
{
    public function preview(UploadedFile|string $file): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        $productos = $this->readProductos($spreadsheet);
        $clientes = $this->readClientes($spreadsheet);
        $proveedores = $this->readProveedores($spreadsheet);
        $errores = $this->validateProductos($productos);

        return [
            'productos' => $productos,
            'clientes' => $clientes,
            'proveedores' => $proveedores,
            'errores' => $errores,
            'resumen' => [
                'productos' => count($productos),
                'clientes' => count($clientes),
                'proveedores' => count($proveedores),
                'errores' => count($errores),
            ],
        ];
    }

    public function confirm(UploadedFile $file, int $usuarioId, InventoryService $inventory): ImportacionExcel
    {
        $preview = $this->preview($file);

        return DB::transaction(function () use ($preview, $file, $usuarioId, $inventory) {
            $importacion = ImportacionExcel::create([
                'archivo_original' => $file->getClientOriginalName(),
                'resumen' => $preview['resumen'],
                'errores' => $preview['errores'],
                'usuario_id' => $usuarioId,
            ]);

            foreach ($preview['clientes'] as $row) {
                Cliente::updateOrCreate(
                    ['documento' => $row['documento'] ?: null, 'nombre' => $row['nombre']],
                    $row + ['activo' => true]
                );
            }

            foreach ($preview['proveedores'] as $row) {
                Proveedor::updateOrCreate(
                    ['documento' => $row['documento'] ?: null, 'nombre' => $row['nombre']],
                    $row + ['activo' => true]
                );
            }

            foreach ($preview['productos'] as $row) {
                if ($row['codigo'] === '') {
                    continue;
                }

                $categoria = $row['categoria']
                    ? Categoria::firstOrCreate(['nombre' => $row['categoria']], ['activo' => true])
                    : null;

                $producto = Producto::updateOrCreate(
                    ['codigo' => $row['codigo']],
                    [
                        'nombre' => $row['nombre'],
                        'categoria_id' => $categoria?->id,
                        'medida' => $row['medida'],
                        'ubicacion' => $row['ubicacion'],
                        'precio_ingreso_usd' => $row['precio_ingreso_usd'],
                        'tipo_cambio' => $row['tipo_cambio'],
                        'porcentaje_utilidad' => $row['porcentaje_utilidad'],
                        'precio_venta' => $row['precio_venta'],
                        'costo_referencial' => $row['costo_referencial'],
                        'stock_minimo' => $row['stock_minimo'],
                        'activo' => true,
                    ]
                );

                $hasInitial = $producto->movimientos()
                    ->where('referencia_tipo', 'importacion_excel')
                    ->exists();

                if (! $hasInitial && (float) $row['stock_actual'] > 0) {
                    $inventory->registrarMovimiento(
                        $producto,
                        'entrada',
                        (float) $row['stock_actual'],
                        'importacion_excel',
                        $importacion->id,
                        $usuarioId,
                        'Stock inicial importado desde Excel'
                    );
                }
            }

            return $importacion;
        });
    }

    private function readProductos($spreadsheet): array
    {
        $productos = [];
        $sheet = $spreadsheet->getSheetByName('Productos');
        $prices = $this->readPriceIndex($spreadsheet);

        if (! $sheet) {
            return $productos;
        }

        for ($row = 5; $row <= $sheet->getHighestRow(); $row++) {
            $codigo = trim((string) $sheet->getCell("A{$row}")->getCalculatedValue());
            $nombre = trim((string) $sheet->getCell("B{$row}")->getCalculatedValue());

            if ($codigo === '' && $nombre === '') {
                continue;
            }

            $price = $prices[$codigo] ?? [];
            $tipoCambio = TipoCambio::ultimoValor();
            $porcentaje = 27.00;
            $precioIngresoUsd = $this->number($price['precio_compra'] ?? 0);
            $costoReferencial = $precioIngresoUsd > 0
                ? round($precioIngresoUsd * $tipoCambio, 2)
                : $this->number($sheet->getCell("H{$row}")->getCalculatedValue());
            $precioVenta = $this->number($price['precio_venta'] ?? 0);

            if ($precioVenta <= 0 && $costoReferencial > 0) {
                $precioVenta = round($costoReferencial * (1 + ($porcentaje / 100)), 2);
            }

            $productos[] = [
                'fila' => $row,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'categoria' => trim((string) $sheet->getCell("C{$row}")->getCalculatedValue()),
                'medida' => trim((string) $sheet->getCell("D{$row}")->getCalculatedValue()),
                'ubicacion' => trim((string) $sheet->getCell("E{$row}")->getCalculatedValue()),
                'stock_minimo' => $this->number($sheet->getCell("F{$row}")->getCalculatedValue()),
                'stock_actual' => $this->number($sheet->getCell("G{$row}")->getCalculatedValue()),
                'precio_ingreso_usd' => $precioIngresoUsd,
                'tipo_cambio' => $tipoCambio,
                'porcentaje_utilidad' => $porcentaje,
                'costo_referencial' => $costoReferencial,
                'precio_venta' => $precioVenta,
            ];
        }

        return $productos;
    }

    private function readPriceIndex($spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('listProductos');
        $prices = [];

        if (! $sheet) {
            return $prices;
        }

        for ($row = 5; $row <= $sheet->getHighestRow(); $row++) {
            $codigo = trim((string) $sheet->getCell("A{$row}")->getCalculatedValue());

            if ($codigo === '') {
                continue;
            }

            $prices[$codigo] = [
                'precio_venta' => $this->number($sheet->getCell("G{$row}")->getCalculatedValue()),
                'precio_sugerido' => $this->number($sheet->getCell("H{$row}")->getCalculatedValue()),
                'precio_sin_factura' => $this->number($sheet->getCell("I{$row}")->getCalculatedValue()),
                'precio_compra' => $this->number($sheet->getCell("J{$row}")->getCalculatedValue()),
            ];
        }

        return $prices;
    }

    private function readClientes($spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('Clientes');
        $rows = [];

        if (! $sheet) {
            return $rows;
        }

        for ($row = 5; $row <= $sheet->getHighestRow(); $row++) {
            $nombre = trim((string) $sheet->getCell("B{$row}")->getCalculatedValue());
            if ($nombre === '') {
                continue;
            }
            $rows[] = [
                'nombre' => $nombre,
                'razon_social' => trim((string) $sheet->getCell("C{$row}")->getCalculatedValue()),
                'documento' => trim((string) $sheet->getCell("D{$row}")->getCalculatedValue()),
                'telefono' => trim((string) $sheet->getCell("F{$row}")->getCalculatedValue()),
                'direccion' => trim((string) $sheet->getCell("G{$row}")->getCalculatedValue()),
            ];
        }

        return $rows;
    }

    private function readProveedores($spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('Proveedor');
        $rows = [];

        if (! $sheet) {
            return $rows;
        }

        for ($row = 5; $row <= $sheet->getHighestRow(); $row++) {
            $nombre = trim((string) $sheet->getCell("B{$row}")->getCalculatedValue());
            if ($nombre === '') {
                continue;
            }
            $rows[] = [
                'nombre' => $nombre,
                'documento' => trim((string) $sheet->getCell("C{$row}")->getCalculatedValue()),
                'telefono' => null,
                'direccion' => trim((string) $sheet->getCell("D{$row}")->getCalculatedValue()),
            ];
        }

        return $rows;
    }

    private function validateProductos(array $productos): array
    {
        $errores = [];
        $seen = [];

        foreach ($productos as $producto) {
            if ($producto['codigo'] === '') {
                $errores[] = "Fila {$producto['fila']}: codigo vacio.";
            }
            if ($producto['nombre'] === '') {
                $errores[] = "Fila {$producto['fila']}: producto sin nombre.";
            }
            if ($producto['codigo'] !== '' && isset($seen[$producto['codigo']])) {
                $errores[] = "Codigo duplicado {$producto['codigo']} en filas {$seen[$producto['codigo']]} y {$producto['fila']}.";
            }
            if ((float) $producto['precio_venta'] <= 0) {
                $errores[] = "Fila {$producto['fila']}: producto sin precio de venta.";
            }
            if ((float) $producto['stock_actual'] < 0) {
                $errores[] = "Fila {$producto['fila']}: stock negativo.";
            }
            $seen[$producto['codigo']] = $producto['fila'];
        }

        return $errores;
    }

    private function number(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = str_replace(',', '.', trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }
}
