<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    private function commercialOptions(): array
    {
        return [
            'san-benjamin' => [
                'key' => 'san-benjamin',
                'name' => 'San Benjamin',
                'nit' => '411168020',
                'logo' => 'assets/logos/san-benjamin.png',
            ],
            'supra-medic' => [
                'key' => 'supra-medic',
                'name' => 'Supra Medic',
                'nit' => '10313888011',
                'logo' => 'assets/logos/supra-medic.png',
            ],
        ];
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            if (! Auth::user()->activo) {
                Auth::logout();

                return back()->withErrors(['username' => 'Usuario inactivo.']);
            }

            $request->session()->forget('commercial_context');

            return redirect()->route('commercial.select');
        }

        return back()->withErrors(['username' => 'Credenciales inválidas.'])->onlyInput('username');
    }

    public function showCommercialSelection()
    {
        return view('auth.select-commercial', [
            'commercialOptions' => $this->commercialOptions(),
        ]);
    }

    public function selectCommercial(Request $request)
    {
        $validated = $request->validate([
            'commercial_name' => ['required', 'in:san-benjamin,supra-medic'],
        ]);

        $options = $this->commercialOptions();
        $request->session()->put('commercial_context', $options[$validated['commercial_name']]);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
