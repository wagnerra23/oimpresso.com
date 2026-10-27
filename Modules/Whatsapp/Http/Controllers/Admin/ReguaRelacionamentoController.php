<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Configuração da régua de relacionamento por empresa (US-WA-328) — aniversário e recompra.
 *
 * O motor é o `ReguaDeRelacionamento` (US-WA-326). Uma linha por empresa e tipo; permissão na rota
 * (`whatsapp.settings.manage`). Tier 0: sempre a empresa da sessão.
 */
class ReguaRelacionamentoController extends Controller
{
    public const TIPOS = ['aniversario', 'recompra'];

    /** @return array<string, array{ativo: bool, texto: string, dias_sem_compra: int|null}> */
    public static function daEmpresa(int $businessId): array
    {
        $linhas = DB::table('whatsapp_reguas_relacionamento')->where('business_id', $businessId)->get()->keyBy('tipo');
        $padrao = [
            'aniversario' => ['ativo' => false, 'texto' => 'Parabéns, {nome}! Desejamos um ótimo aniversário.', 'dias_sem_compra' => null],
            'recompra' => ['ativo' => false, 'texto' => 'Oi, {nome}! Faz um tempo desde a sua última compra. Podemos ajudar com algo?', 'dias_sem_compra' => 30],
        ];

        return collect($padrao)->map(fn ($p, $tipo) => ($l = $linhas->get($tipo)) === null ? $p : [
            'ativo' => (bool) $l->ativo,
            'texto' => (string) $l->texto,
            'dias_sem_compra' => $l->dias_sem_compra !== null ? (int) $l->dias_sem_compra : null,
        ])->all();
    }

    public function update(Request $request): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $dados = $request->validate([
            'tipo' => ['required', Rule::in(self::TIPOS)],
            'ativo' => ['required', 'boolean'],
            'texto' => ['required', 'string', 'max:500'],
            'dias_sem_compra' => ['required_if:tipo,recompra', 'nullable', 'integer', 'min:7', 'max:365'],
        ]);

        DB::table('whatsapp_reguas_relacionamento')->upsert(
            [[
                'business_id' => $businessId,
                'tipo' => $dados['tipo'],
                'ativo' => (bool) $dados['ativo'],
                'texto' => trim($dados['texto']),
                'dias_sem_compra' => $dados['tipo'] === 'recompra' ? (int) $dados['dias_sem_compra'] : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['business_id', 'tipo'],
            ['ativo', 'texto', 'dias_sem_compra', 'updated_at'],
        );

        return back()->with('success', 'Régua de relacionamento salva.');
    }
}
