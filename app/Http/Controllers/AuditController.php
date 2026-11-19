<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $buscar = trim((string) $request->input('buscar', ''));
        $eventos = AuditEvent::with('usuario')
            ->when($request->filled('accion'), fn ($query) => $query->where('accion', $request->input('accion')))
            ->when($request->filled('modulo'), fn ($query) => $query->where('modulo', $request->input('modulo')))
            ->when($request->filled('usuario_id'), fn ($query) => $query->where('user_id', $request->integer('usuario_id')))
            ->when($request->filled('desde'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('desde')))
            ->when($request->filled('hasta'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('hasta')))
            ->when($buscar !== '', function ($query) use ($buscar) {
                $term = '%' . $buscar . '%';
                $query->where(function ($query) use ($term) {
                    $query->where('descripcion', 'like', $term)
                        ->orWhere('auditable_type', 'like', $term)
                        ->orWhere('auditable_id', 'like', $term)
                        ->orWhereHas('usuario', fn ($usuario) => $usuario
                            ->where('name', 'like', $term)
                            ->orWhere('username', 'like', $term));
                });
            })
            ->latest('created_at')
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        return view('seguridad.auditoria', [
            'eventos' => $eventos,
            'usuarios' => User::orderBy('name')->get(['id', 'name', 'username']),
            'acciones' => AuditEvent::query()->select('accion')->whereNotNull('accion')->distinct()->orderBy('accion')->pluck('accion'),
            'modulos' => AuditEvent::query()->select('modulo')->whereNotNull('modulo')->distinct()->orderBy('modulo')->pluck('modulo'),
        ]);
    }
}
