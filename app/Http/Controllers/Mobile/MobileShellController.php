<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Base do app das lojas (/m) — decisão [W] 2026-10-01: o app abre o protótipo Mobile, não o
 * site do ERP emulado. Contrato: memory/requisitos/AppMobile/RUNBOOK-shell-mobile.md.
 *
 * Este controller só serve as telas que PROVAM o shell (Início e Mais mínimos) e o
 * marcador das abas cujas telas ainda não existem. As telas de negócio (Tarefas, Pedidos,
 * Produção, Pessoas, Ponto) nascem em controllers próprios das sessões de tela.
 *
 * Tier 0 (ADR 0093): todo dado vem do usuário autenticado e do business DELE — nenhum
 * parâmetro de rota escolhe tenant.
 */
class MobileShellController extends Controller
{
    /** Abas ainda sem tela: rota existe para a tab bar nunca levar a 404. */
    private const EM_CONSTRUCAO = [
        'tarefas' => 'Tarefas',
        'pedidos' => 'Pedidos',
        'producao' => 'Produção',
    ];

    public function inicio(Request $request): Response
    {
        return Inertia::render('Mobile/Inicio', $this->identidade($request) + [
            'hoje' => now()->locale('pt_BR')->translatedFormat('j M'),
        ]);
    }

    public function mais(Request $request): Response
    {
        return Inertia::render('Mobile/Mais', $this->identidade($request));
    }

    public function emConstrucao(Request $request, string $aba): Response
    {
        abort_unless(isset(self::EM_CONSTRUCAO[$aba]), 404);

        return Inertia::render('Mobile/EmConstrucao', [
            'aba' => $aba,
            'titulo' => self::EM_CONSTRUCAO[$aba],
        ]);
    }

    /** @return array{usuario: array{nome: string}, empresa: array{nome: string}} */
    private function identidade(Request $request): array
    {
        /** @var \App\User $user */
        $user = $request->user();

        return [
            'usuario' => ['nome' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: (string) $user->username],
            'empresa' => ['nome' => (string) \App\Business::query()->whereKey($user->business_id)->value('name')],
        ];
    }
}
