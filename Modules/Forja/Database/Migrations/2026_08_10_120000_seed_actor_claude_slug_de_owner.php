<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registra o ator `claude` no Identity Mesh (ADR 0081) — o slug que as tasks
 * de fato usam em `mcp_tasks.owner`.
 *
 * POR QUÊ (medido em produção, 2026-08-10, smoke do Quadro da Forja):
 *
 *   agents servidos ao front : ["claude-code-wagner-laptop"]
 *   owners reais das tasks   : wagner · [w] · claude · eliana · felipe ·
 *                              luiz · maiara · maira · [f]
 *
 * NENHUM casa. O `<ActorSeal>` marca "agente" só quando o owner está na lista
 * de `ai_agent`, e como `claude` nunca esteve, as 8 tasks dele apareciam como
 * HUMANO — em `/forja/trabalho` E em `/team-mcp/tasks` (383 selos, 100%
 * `human`). O selo existia desde que nasceu e nunca distinguiu nada.
 *
 * O QUE ISTO **NÃO** É: heurística de nome. Não se decide "é agente?" por
 * `startsWith('claude')` — isso erra e faz o selo mentir com confiança (está
 * proibido no charter de `Forja/Trabalho`). Aqui se registra um FATO: existe um
 * agente chamado `claude` que possui tasks, e o Identity Mesh é o registro de
 * quem age no sistema. O selo passa a ler dado, não palpite.
 *
 * POR QUE ATOR NOVO E NÃO RENOMEAR AS TASKS: mudar `owner` de 8 linhas pra
 * `claude-code-wagner-laptop` poria um slug de TOKEN na UI (o card mostra o
 * owner literal). São conceitos diferentes — token identifica a sessão que
 * escreve; `owner` identifica quem responde pela task. O Mesh comporta os dois.
 *
 * SEGURANÇA (Tier 0): este ator nasce SEM TOKEN e com ZERO capability —
 * `modules_write: []`, `trust_level: L4` (o menor da escala L0..L4), tudo em
 * `actions_blocked`. Ele NÃO ganha permissão de nada; serve só como registro de
 * identidade pro selo. Quem escreve continua sendo o ator-com-token
 * `claude-code-wagner-laptop`, intocado.
 *
 * IDEMPOTENTE: `INSERT` só se o slug não existir — mesma disciplina do seed
 * inicial (`2026_05_05_240002_seed_initial_actors`).
 *
 * IMPACTO: 1 linha nova em `mcp_actors`. 8 tasks passam a exibir selo de
 * AGENTE em vez de humano. Nenhuma permissão criada, nenhum dado de task
 * alterado, nenhuma tabela de negócio tocada.
 */
return new class extends Migration
{
    private const SLUG = 'claude';

    public function up(): void
    {
        if (DB::table('mcp_actors')->where('slug', self::SLUG)->exists()) {
            return;   // idempotente — re-run não duplica
        }

        // parent = wagner (root), como o `claude-code-wagner-laptop` já faz.
        $wagner = DB::table('mcp_actors')->where('slug', 'wagner')->value('id');

        $now = now();

        DB::table('mcp_actors')->insert([
            'slug'            => self::SLUG,
            'type'            => 'ai_agent',
            'trust_level'     => 'L4',              // menor privilégio da escala
            'parent_actor_id' => $wagner,
            'modules_write'   => json_encode([]),   // ZERO escrita
            'modules_read'    => json_encode([]),   // ZERO leitura
            'modules_blocked' => json_encode(['*']),
            'skills_required' => json_encode([]),
            'actions_blocked' => json_encode(['*']),
            'audit_required'  => 1,
            'user_id'         => null,
            'display_name'    => 'Claude (agente)',
            'notes'           => 'Slug de EXIBIÇÃO usado em mcp_tasks.owner. Sem token e sem '
                                .'capability: existe para o <ActorSeal> distinguir agente de '
                                .'humano na UI. Quem escreve é claude-code-wagner-laptop.',
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }

    public function down(): void
    {
        // Só remove se continuar sem token vinculado — se alguém tiver promovido
        // este ator a portador de token, derrubá-lo quebraria autenticação.
        $id = DB::table('mcp_actors')->where('slug', self::SLUG)->value('id');

        if (! $id) {
            return;
        }

        $temToken = DB::table('mcp_tokens')->where('actor_id', $id)->exists();

        if (! $temToken) {
            DB::table('mcp_actors')->where('id', $id)->delete();
        }
    }
};
