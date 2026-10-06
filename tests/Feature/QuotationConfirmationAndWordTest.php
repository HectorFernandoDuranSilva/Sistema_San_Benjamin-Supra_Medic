<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Producto;
use App\Models\User;
use App\Services\QuotationService;
use App\Services\QuotationWordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ZipArchive;
use Tests\TestCase;

class QuotationConfirmationAndWordTest extends TestCase
{
    use RefreshDatabase;

    public function test_exports_require_confirmation_and_word_contains_the_quotation_data(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $quote = $this->quote($user);

        $this->actingAs($user)
            ->get(route('cotizaciones.pdf', $quote))
            ->assertRedirect(route('cotizaciones.show', $quote))
            ->assertSessionHasErrors('cotizacion');
        $this->actingAs($user)
            ->get(route('cotizaciones.word', $quote))
            ->assertRedirect(route('cotizaciones.show', $quote))
            ->assertSessionHasErrors('cotizacion');

        $this->actingAs($user)
            ->post(route('cotizaciones.confirm', $quote), ['confirmacion' => 1])
            ->assertRedirect(route('cotizaciones.show', $quote))
            ->assertSessionHasNoErrors();

        $quote->refresh();
        $this->assertNotNull($quote->confirmada_at);
        $this->assertSame($user->id, $quote->confirmada_por_id);
        $this->assertDatabaseHas('auditoria_eventos', [
            'auditable_type' => Cotizacion::class,
            'auditable_id' => $quote->id,
            'accion' => 'confirmo',
        ]);

        $this->actingAs($user)
            ->get(route('cotizaciones.pdf', $quote))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->actingAs($user)
            ->get(route('cotizaciones.word', $quote))
            ->assertOk()
            ->assertDownload('cotizacion-CT-CONFIRMADA.docx');

        $path = app(QuotationWordService::class)->create($quote->load('cliente', 'detalles.producto'));
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $this->assertNotFalse($zip->locateName('[Content_Types].xml'));
        $this->assertNotFalse($zip->locateName('word/document.xml'));
        $this->assertNotFalse($zip->locateName('word/media/logo.jpeg'));
        $this->assertNotFalse($zip->locateName('word/media/signature.jpeg'));
        $document = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        if ($qaDirectory = getenv('QUOTATION_WORD_QA')) {
            if (! is_dir($qaDirectory)) {
                mkdir($qaDirectory, 0775, true);
            }
            copy($path, $qaDirectory . DIRECTORY_SEPARATOR . 'cotizacion-confirmada.docx');
        }
        @unlink($path);

        foreach (['COTIZACIÓN', 'Producto de prueba Word', 'Marca de prueba', 'Brasil', '31/12/2028', 'PRESENTE.-'] as $text) {
            $this->assertStringContainsString($text, $document);
        }
    }

    public function test_editing_a_confirmed_quotation_requires_a_new_confirmation(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $quote = $this->quote($user);
        $quote->update(['confirmada_at' => now(), 'confirmada_por_id' => $user->id]);

        $data = [
            'numero_cotizacion' => 'CT-CONFIRMADA',
            'nombre_comercial' => 'San Benjamin',
            'cliente_id' => $quote->cliente_id,
            'fecha' => '2026-10-04',
            'valida_hasta' => '2026-10-14',
            'nit_cliente' => '44556677',
            'descuento_porcentaje' => 0,
            'revision' => $quote->revision,
            'items' => [[
                'id' => $quote->detalles->first()->id,
                'producto_id' => $quote->detalles->first()->producto_id,
                'cantidad' => 3,
                'precio_unitario' => 125,
                'porcentaje_utilidad' => 25,
                'descuento' => 0,
                'impuesto' => 0,
                'marca' => 'Marca de prueba',
                'procedencia' => 'Brasil',
                'fecha_vencimiento' => '2028-12-31',
            ]],
        ];

        $this->actingAs($user)
            ->put(route('cotizaciones.update', $quote), $data)
            ->assertRedirect(route('cotizaciones.show', $quote));

        $this->assertNull($quote->fresh()->confirmada_at);
        $this->actingAs($user)
            ->get(route('cotizaciones.pdf', $quote))
            ->assertRedirect(route('cotizaciones.show', $quote))
            ->assertSessionHasErrors('cotizacion');
    }

    private function quote(User $user): Cotizacion
    {
        $client = Cliente::create([
            'nombre' => 'Cliente de prueba Word',
            'documento' => '44556677',
            'activo' => true,
        ]);
        $product = Producto::create([
            'codigo' => 'WORD-001',
            'nombre' => 'Producto de prueba Word',
            'medida' => 'Caja x 10',
            'marca' => 'Marca de prueba',
            'procedencia' => 'Brasil',
            'precio_venta' => 125,
            'costo_referencial' => 100,
            'porcentaje_utilidad' => 25,
            'stock_minimo' => 0,
            'activo' => true,
        ]);

        return app(QuotationService::class)->registrar([
            'numero_cotizacion' => 'CT-CONFIRMADA',
            'nombre_comercial' => 'San Benjamin',
            'cliente_id' => $client->id,
            'fecha' => '2026-10-04',
            'valida_hasta' => '2026-10-14',
            'nit_cliente' => '44556677',
            'descuento_porcentaje' => 0,
            'items' => [[
                'producto_id' => $product->id,
                'cantidad' => 2,
                'precio_unitario' => 125,
                'porcentaje_utilidad' => 25,
                'descuento' => 0,
                'impuesto' => 0,
                'marca' => 'Marca de prueba',
                'procedencia' => 'Brasil',
                'fecha_vencimiento' => '2028-12-31',
            ]],
        ], $user->id);
    }
}
