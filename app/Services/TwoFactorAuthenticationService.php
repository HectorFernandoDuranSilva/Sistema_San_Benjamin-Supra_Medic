<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthenticationService
{
    private const MAX_ATTEMPTS = 5;

    private const LOCK_MINUTES = 15;

    public function __construct(private readonly Google2FA $google2fa, private readonly AuditLogService $auditLog)
    {
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function qrCodeDataUri(User $user, string $secret): string
    {
        $issuer = config('app.name', 'San Benjamin');
        $label = rawurlencode($issuer.':'.$user->username);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);

        return (new SvgWriter())
            ->write(QrCode::create("otpauth://totp/{$label}?{$query}")->setSize(240)->setMargin(8))
            ->getDataUri();
    }

    /** @return array<int, string>|null */
    public function confirmEnrollment(User $user, string $secret, string $code): ?array
    {
        if (! $this->isTotpValid($secret, $code)) {
            return null;
        }

        $codes = $this->makeRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(fn (string $recoveryCode): string => $this->recoveryHash($recoveryCode), $codes),
            'two_factor_confirmed_at' => now(),
            'two_factor_failed_attempts' => 0,
            'two_factor_locked_until' => null,
        ])->save();

        return $codes;
    }

    public function authorize(Request $request, string $module, ?Model $subject = null, string $description = 'Acción protegida'): ?string
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);

        $result = $this->verify($user, (string) $request->input('totp_code'));
        if ($result['valid']) {
            return null;
        }

        $this->auditLog->record($request, 'rechazo_autenticador', $module, $subject, null, null, $description.': '.$result['message']);

        return $result['message'];
    }

    /** @return array{valid: bool, message: string, recovery: bool} */
    public function verify(User $user, string $submittedCode): array
    {
        if (! $user->two_factor_confirmed_at || ! $user->two_factor_secret) {
            return ['valid' => false, 'message' => 'Primero configure Google Authenticator desde Administración > Seguridad.', 'recovery' => false];
        }

        $now = CarbonImmutable::now();
        if ($user->two_factor_locked_until && $user->two_factor_locked_until->isFuture()) {
            return ['valid' => false, 'message' => 'El autenticador está bloqueado hasta '.$user->two_factor_locked_until->format('d/m/Y H:i').'.', 'recovery' => false];
        }

        if ($user->two_factor_locked_until) {
            $user->forceFill(['two_factor_failed_attempts' => 0, 'two_factor_locked_until' => null])->save();
        }

        $code = $this->normalizeCode($submittedCode);
        $validTotp = preg_match('/^\d{6}$/', $code) === 1 && $this->isTotpValid($user->two_factor_secret, $code);
        $recoveryCodes = $user->two_factor_recovery_codes ?? [];
        $recoveryHash = $this->recoveryHash($code);
        $validRecovery = ! $validTotp && in_array($recoveryHash, $recoveryCodes, true);

        if ($validTotp || $validRecovery) {
            $updates = ['two_factor_failed_attempts' => 0, 'two_factor_locked_until' => null];
            if ($validRecovery) {
                $updates['two_factor_recovery_codes'] = array_values(array_filter(
                    $recoveryCodes,
                    fn (string $storedHash): bool => ! hash_equals($storedHash, $recoveryHash),
                ));
            }
            $user->forceFill($updates)->save();

            return ['valid' => true, 'message' => '', 'recovery' => $validRecovery];
        }

        $attempts = min(self::MAX_ATTEMPTS, (int) $user->two_factor_failed_attempts + 1);
        $updates = ['two_factor_failed_attempts' => $attempts];
        $message = 'El código de Google Authenticator no es válido.';
        if ($attempts >= self::MAX_ATTEMPTS) {
            $updates['two_factor_locked_until'] = $now->addMinutes(self::LOCK_MINUTES);
            $message = 'Se alcanzó el límite de intentos. El autenticador quedó bloqueado por '.self::LOCK_MINUTES.' minutos.';
        }
        $user->forceFill($updates)->save();

        return ['valid' => false, 'message' => $message, 'recovery' => false];
    }

    private function isTotpValid(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code, 1);
    }

    /** @return array<int, string> */
    private function makeRecoveryCodes(): array
    {
        return collect(range(1, 10))->map(function (): string {
            $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $value = '';
            for ($index = 0; $index < 12; $index++) {
                $value .= $characters[random_int(0, strlen($characters) - 1)];
            }

            return implode('-', str_split($value, 4));
        })->all();
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[\s-]+/', '', trim($code)) ?? '');
    }

    private function recoveryHash(string $code): string
    {
        return hash('sha256', $this->normalizeCode($code));
    }
}
