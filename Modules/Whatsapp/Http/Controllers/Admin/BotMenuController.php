<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Whatsapp\Entities\Channel;

/**
 * Menu do bot de atendimento por canal — leitura e gravação (US-WA-327).
 *
 * O motor é o `MenuDeAtendimento` (US-WA-324); aqui a empresa configura saudação, opções
 * (tecla → rótulo → fila → resposta) e se pergunta o nome. Um menu por canal (chave única).
 * Permissão: a mesma da tela do canal (`whatsapp.settings.manage`, na rota).
 *
 * Tier 0 (ADR 0093): canal e filas sempre da empresa da sessão; canal de outra empresa → 404;
 * fila de outra empresa não passa na validação.
 */
class BotMenuController extends Controller
{
    public const MAX_OPCOES = 9;

    /** @return array{ativo: bool, saudacao: string, opcoes: list<array<string, mixed>>, pergunta_nome: bool}|null */
    public static function doCanal(int $businessId, int $channelId): ?array
    {
        $m = DB::table('whatsapp_bot_menus')->where('business_id', $businessId)->where('channel_id', $channelId)->first();

        return $m === null ? null : [
            'ativo' => (bool) $m->ativo,
            'saudacao' => (string) $m->saudacao,
            'opcoes' => array_values(json_decode((string) $m->opcoes, true) ?: []),
            'pergunta_nome' => (bool) $m->pergunta_nome,
        ];
    }

    /** @return list<array{slug: string, label: string}> */
    public static function filas(int $businessId): array
    {
        return DB::table('whatsapp_queues')->where('business_id', $businessId)
            ->orderBy('sort_order')->orderBy('label')->get(['slug', 'label'])
            ->map(fn ($f) => ['slug' => (string) $f->slug, 'label' => (string) $f->label])->values()->all();
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $canal = Channel::query()->where('business_id', $businessId)->findOrFail($id);

        $dados = $request->validate([
            'ativo' => ['required', 'boolean'],
            'saudacao' => ['required', 'string', 'max:1000'],
            'pergunta_nome' => ['required', 'boolean'],
            'opcoes' => ['required', 'array', 'min:1', 'max:'.self::MAX_OPCOES],
            'opcoes.*.tecla' => ['required', 'string', 'max:3', 'distinct'],
            'opcoes.*.rotulo' => ['required', 'string', 'max:60'],
            'opcoes.*.fila' => ['nullable', 'string', Rule::exists('whatsapp_queues', 'slug')->where('business_id', $businessId)],
            'opcoes.*.resposta' => ['nullable', 'string', 'max:500'],
        ]);

        $opcoes = array_map(fn (array $o) => [
            'tecla' => trim((string) $o['tecla']),
            'rotulo' => trim((string) $o['rotulo']),
            'fila' => ($o['fila'] ?? null) ?: null,
            'resposta' => trim((string) ($o['resposta'] ?? '')) ?: null,
        ], $dados['opcoes']);

        DB::table('whatsapp_bot_menus')->upsert(
            [[
                'business_id' => $businessId,
                'channel_id' => (int) $canal->id,
                'ativo' => (bool) $dados['ativo'],
                'saudacao' => trim($dados['saudacao']),
                'opcoes' => json_encode($opcoes, JSON_UNESCAPED_UNICODE),
                'pergunta_nome' => (bool) $dados['pergunta_nome'],
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['business_id', 'channel_id'],
            ['ativo', 'saudacao', 'opcoes', 'pergunta_nome', 'updated_at'],
        );

        return back()->with('success', $dados['ativo'] ? 'Menu do bot salvo e ligado.' : 'Menu do bot salvo (desligado).');
    }
}
