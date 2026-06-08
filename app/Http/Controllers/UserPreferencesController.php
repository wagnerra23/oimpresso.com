<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Endpoints para preferências de UI do usuário autenticado.
 *
 * Segurança: só atualiza colunas controladas (`ui_theme`, `ui_sidebar_collapsed`).
 * Nunca aceita `user_id` do cliente — sempre usa `$request->user()->id`.
 */
class UserPreferencesController extends Controller
{
    public function updateTheme(Request $request)
    {
        $validated = $request->validate([
            'theme' => ['nullable', Rule::in(['light', 'dark'])],
        ]);

        $user = $request->user();
        abort_unless($user, 401);

        // null = "seguir sistema"; 'light'|'dark' = override explícito
        $user->ui_theme = $validated['theme'] ?? null;
        $user->save();

        // Inertia reload parcial só dessas props — sem trocar de página
        return back();
    }

    public function updateSidebarCollapsed(Request $request)
    {
        $validated = $request->validate([
            'collapsed' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        abort_unless($user, 401);

        $user->ui_sidebar_collapsed = (bool) $validated['collapsed'];
        $user->save();

        return back();
    }
}
