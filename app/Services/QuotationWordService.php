<?php

namespace App\Services;

use App\Models\Cotizacion;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class QuotationWordService
{
    public function create(Cotizacion $cotizacion): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('No se puede crear el archivo Word porque ZipArchive no está disponible.');
        }

        $data = app(QuotationPdfService::class)->data($cotizacion);
        $directory = storage_path('app/quotation-word');
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $path = $directory . DIRECTORY_SEPARATOR . Str::uuid() . '.docx';
        $archive = new ZipArchive();
        if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo preparar el archivo Word.');
        }

        $archive->addFromString('[Content_Types].xml', $this->contentTypes());
        $archive->addFromString('_rels/.rels', $this->rootRelations());
        $archive->addFromString('word/_rels/document.xml.rels', $this->documentRelations());
        $archive->addFromString('word/document.xml', $this->document($cotizacion, $data));
        $archive->addFile($data['logo'], 'word/media/logo.jpeg');
        $archive->addFile($data['signature'], 'word/media/signature.jpeg');
        $archive->close();

        return $path;
    }

    private function document(Cotizacion $cotizacion, array $data): string
    {
        $brand = $data['isSupra'] ? '187CAA' : '090660';
        $rows = [];
        foreach ($cotizacion->detalles as $index => $detail) {
            $rows[] = $this->tableRow([
                $index + 1, $detail->nombre_producto, $detail->medida ?? $detail->producto?->medida ?: '-',
                $this->quantity($detail->cantidad), $detail->marca ?: '-', $detail->procedencia ?: '-',
                $detail->fecha_vencimiento?->format('d/m/Y') ?: '-', number_format($detail->precio_unitario, 2),
                number_format($detail->total, 2),
            ], false);
        }
        $contact = implode('<w:br/>', array_map(fn ($line) => $this->run($line, true, $brand), [
            $data['header']['business'], $data['header']['name'], $data['header']['address'],
            'TEL.: ' . $data['header']['phone'], $data['header']['city'],
        ]));
        $terms = '';
        foreach ([
            'Empresa:' => $data['footer']['empresa'], 'Dirección:' => $data['footer']['direccion'],
            'Tiempo de validez de la oferta:' => $data['footer']['validez'], 'Tiempo de entrega:' => $data['footer']['entrega'],
            'Condiciones de pago:' => $data['footer']['condiciones_pago'], 'Depósito a cuenta:' => $data['footer']['deposito_cuenta'],
        ] as $label => $value) {
            $terms .= $this->paragraph($this->run($label . ' ', true) . $this->run($value), false);
        }
        $date = 'Sucre, ' . $cotizacion->fecha->locale('es')->translatedFormat('d \\d\\e F \\d\\e Y');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><w:body>'
            . $this->tableRaw('<w:tr><w:tc>' . $this->paragraph($contact, false) . '</w:tc><w:tc>' . $this->paragraph($this->image('rId1', 220, 104, 'Logo'), false) . '</w:tc></w:tr>', [3600, 5760])
            . $this->paragraph($date, true, 'left', 120)
            . $this->paragraph($this->run('SEÑORES:', true) . '<w:br/>' . $this->run($cotizacion->cliente?->nombre_mostrable ?? 'Sin cliente', true) . ($cotizacion->nit_cliente ? '<w:br/>' . $this->run('NIT: ' . $cotizacion->nit_cliente) : '') . '<w:br/>' . $this->run('PRESENTE.-', true, null, true), false, 'left', 180)
            . $this->paragraph($this->run('COTIZACIÓN', true, null, true), false, 'center', 60, 26)
            . $this->paragraph('N.º ' . $cotizacion->numero_cotizacion, true, 'center', 150)
            . $this->table(array_merge([$this->tableRow(['N.º', 'DETALLE', 'PRESENTACIÓN', 'CANT.', 'MARCA', 'PROCEDENCIA', 'VENCIMIENTO', 'PRECIO UN.', 'PRECIO TOTAL'], true)], $rows), [420, 1540, 940, 510, 750, 1020, 940, 800, 860])
            . $this->paragraph($this->run('TOTAL (Bs) ', true) . $this->run(number_format($cotizacion->total, 2), true), false, 'right', 180)
            . $terms
            . $this->paragraph($this->image('rId2', 150, 80, 'Firma'), false, 'right', 0)
            . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720"/></w:sectPr></w:body></w:document>';
    }

    private function table(array $rows, array $widths): string
    {
        return $this->tableRaw(implode('', $rows), $widths);
    }

    private function tableRaw(string $rows, array $widths): string
    {
        $grid = implode('', array_map(fn ($width) => '<w:gridCol w:w="' . $width . '"/>', $widths));
        return '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders><w:top w:val="single" w:sz="4"/><w:left w:val="single" w:sz="4"/><w:bottom w:val="single" w:sz="4"/><w:right w:val="single" w:sz="4"/><w:insideH w:val="single" w:sz="4"/><w:insideV w:val="single" w:sz="4"/></w:tblBorders></w:tblPr><w:tblGrid>' . $grid . '</w:tblGrid>' . $rows . '</w:tbl>';
    }

    private function tableRow(array $cells, bool $header): string
    {
        $content = '';
        foreach ($cells as $value) {
            $content .= '<w:tc><w:tcPr><w:tcMar><w:top w:w="60" w:type="dxa"/><w:left w:w="60" w:type="dxa"/><w:bottom w:w="60" w:type="dxa"/><w:right w:w="60" w:type="dxa"/></w:tcMar>' . ($header ? '<w:shd w:fill="E8EEF5"/>' : '') . '</w:tcPr>' . $this->paragraph($this->run((string) $value, $header), false, is_numeric($value) ? 'right' : 'left', 0, 16) . '</w:tc>';
        }
        return '<w:tr>' . $content . '</w:tr>';
    }

    private function paragraph(string $content, bool $escape = true, string $alignment = 'left', int $after = 0, int $size = 20): string
    {
        $content = $escape ? $this->run($content, false, null, false, $size) : $content;
        return '<w:p><w:pPr><w:jc w:val="' . $alignment . '"/><w:spacing w:after="' . $after . '"/></w:pPr>' . $content . '</w:p>';
    }

    private function run(string $text, bool $bold = false, ?string $color = null, bool $underline = false, int $size = 20): string
    {
        $properties = '<w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/>' . ($bold ? '<w:b/>' : '') . ($color ? '<w:color w:val="' . $color . '"/>' : '') . ($underline ? '<w:u w:val="single"/>' : '');
        return '<w:r><w:rPr>' . $properties . '</w:rPr><w:t xml:space="preserve">' . htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</w:t></w:r>';
    }

    private function image(string $relation, int $width, int $height, string $name): string
    {
        $cx = $width * 9525; $cy = $height * 9525;
        return '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="' . $cx . '" cy="' . $cy . '"/><wp:docPr id="' . ($relation === 'rId1' ? '1' : '2') . '" name="' . $name . '"/><a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic><pic:nvPicPr><pic:cNvPr id="0" name="' . $name . '.jpeg"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip r:embed="' . $relation . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill><pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';
    }

    private function quantity(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="jpeg" ContentType="image/jpeg"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>';
    }

    private function rootRelations(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>';
    }

    private function documentRelations(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/logo.jpeg"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/signature.jpeg"/></Relationships>';
    }
}
