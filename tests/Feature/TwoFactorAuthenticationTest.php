<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_enroll_and_use_a_time_based_code(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $service = app(TwoFactorAuthenticationService::class);
        $secret = $service->generateSecret();
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $recoveryCodes = $service->confirmEnrollment($user, $secret, $code);

        $this->assertCount(10, $recoveryCodes);
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->assertTrue($service->verify($user->fresh(), $code)['valid']);
        $this->assertNotEmpty($service->qrCodeDataUri($user->fresh(), $secret));
    }

    public function test_five_failed_codes_lock_the_authenticator_and_recovery_code_is_consumed(): void
    {
        $user = User::factory()->create(['role' => 'administrador', 'activo' => true]);
        $service = app(TwoFactorAuthenticationService::class);
        $secret = $service->generateSecret();
        $codes = $service->confirmEnrollment($user, $secret, app(Google2FA::class)->getCurrentOtp($secret));

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $result = $service->verify($user->fresh(), '000000');
        }

        $this->assertFalse($result['valid']);
        $this->assertNotNull($user->fresh()->two_factor_locked_until);

        $user->forceFill(['two_factor_locked_until' => now()->subMinute()])->save();
        $this->assertTrue($service->verify($user->fresh(), $codes[0])['valid']);
        $this->assertFalse($service->verify($user->fresh(), $codes[0])['valid']);
    }

    public function test_dashboard_hides_an_individual_quick_item_when_user_preference_disables_it(): void
    {
        $user = User::factory()->create([
            'role' => 'administrador',
            'activo' => true,
            'dashboard_preferences' => ['ventas_hoy' => false],
        ]);

        $this->actingAs($user)
            ->withSession(['commercial_context' => ['name' => 'San Benjamin']])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Ventas del dia');
    }
}
