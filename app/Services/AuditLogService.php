<?php

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AuditLogService
{
    public function record(
        Request $request,
        string $accion,
        string $modulo,
        ?Model $model = null,
        ?array $before = null,
        ?array $after = null,
        ?string $descripcion = null
    ): AuditEvent {
        return AuditEvent::create([
            'user_id' => $request->user()?->id,
            'accion' => $accion,
            'modulo' => $modulo,
            'auditable_type' => $model ? $model::class : null,
            'auditable_id' => $model?->getKey(),
            'descripcion' => $descripcion,
            'valores_anteriores' => $this->clean($before),
            'valores_nuevos' => $this->clean($after),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function clean(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $values = Arr::except($values, ['password', 'remember_token', 'force_password', 'totp_code', 'two_factor_secret', 'two_factor_recovery_codes']);

        return collect($values)
            ->reject(fn ($value, $key) => Str::contains((string) $key, ['password', 'token', 'totp', 'two_factor', 'recovery']))
            ->all();
    }
}
