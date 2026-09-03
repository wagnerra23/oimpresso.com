<?php

declare(strict_types=1);

namespace Modules\Forja\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Forja\Entities\CoworkHandoff;
use Modules\Forja\Entities\McpActor;
use Modules\Jana\Entities\Mcp\McpCcSession;
use Modules\Jana\Entities\Mcp\McpTask;

/**
 * ForjaAprovacoesService — a fila da Mesa de Aprovações: o que espera por uma
 * DECISÃO HUMANA, em ordem de espera.
 *
 * Superfície da {@see \Modules\Jana\Entities\Mcp\McpTask::AWAITING_HUMAN} (ADR 0368).
 * A ADR aceitou a POLÍTICA e deixou escrito que "o código vai em PR próprio"; o
 * estado (`pending_approval`), o FSM e a trava de recusa-sem-motivo já chegaram
 * (#5283/#5288). O que faltava — e é só isto aqui — é a TELA: hoje a fila existe
 * no banco e não existe em lugar nenhum que o [W] consiga olhar.
 *
 * Read-only por design: quem MUTA é {@see \Modules\Jana\Services\TaskRegistry\TaskCrudService},
 * o mesmo chokepoint da tool MCP `tasks-update`. Não há segundo caminho de escrita —
 * é a trava que faz a recusa-sem-motivo e o FSM valerem também pra web (se a Mesa
 * escrevesse direto no Eloquent, as duas regras ficariam de fora).
 *
 * Tier 0 (ADR 0093): `mcp_tasks` é REPO-WIDE (governança da plataforma, não de
 * tenant) — sem `business_id` por design, igual TriageController/ForjaMcpService.
 *
 * Observability (ADR 0155 D9.a): leitura dentro de `OtelHelper::span` (zero-cost
 * com OTel off), igual {@see ForjaMcpService}.
 *
 * ⚠️ O que este service NÃO faz, e é deliberado: não inventa os 4 "tipos de
 * submissão" (Plano/Modificação/Design/Proposta) como taxonomia própria. Só
 * `Proposta` tem estado canônico hoje (`pending_approval`); os outros vivem em
 * `cowork_handoffs` e já têm dono ({@see ForjaMcpService::handoffs()}). Fundir as
 * duas fontes numa fila só é decisão [W] — e enquanto não for, duas listas
 * honestas valem mais que uma taxonomia inventada.
 */
class ForjaAprovacoesService
{
    /** Teto da fila. Espera por humano é baixo-volume por natureza — se estourar, o problema é outro. */
    private const LIMIT = 200;

    /** Espera a partir da qual o item fica em atenção (âmbar). */
    private const SLA_ATENCAO_MIN = 30;

    /** Espera a partir da qual o item fica urgente (vermelho). */
    private const SLA_URGENTE_MIN = 120;

    /** Silencio a partir do qual um papel aparece "sem sinal" no placar. */
    private const SEM_SINAL_HORAS = 24;

    /** Janela do placar: "entregas" e "retrabalho" sao dos ultimos N dias. */
    private const PLACAR_DIAS = 7;

    /** Piso do critique que o gate F1.5 exige (mesmo numero do HandoffAckTool). */
    private const CRITIQUE_PISO = 80;

    /**
     * A fila: tudo em `pending_approval`, MAIS ANTIGO PRIMEIRO.
     *
     * A ordem é por espera crescente de propósito — numa mesa de decisão, o que
     * espera há mais tempo é o que custa mais caro. Prioridade NÃO desempata:
     * deixar um p3 de três dias atrás de um p0 de cinco minutos é como a fila
     * envelhece sem ninguém ver.
     *
     * @return list<array<string,mixed>>
     */
    public function fila(): array
    {
        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_tasks é repo-wide (ADR 0070/0093), sem tenant por design

        return OtelHelper::span('forja.aprovacoes.fila', [], function (): array {
            return McpTask::query()
                ->where('status', McpTask::AWAITING_HUMAN)
                ->orderBy('created_at')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn (McpTask $t): array => $this->serialize($t))
                ->values()
                ->all();
        });
    }

    /** Quantos esperam por decisão — o badge do segmento. */
    public function contagem(): int
    {
        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_tasks é repo-wide (ADR 0070/0093), sem tenant por design

        return OtelHelper::span('forja.aprovacoes.contagem', [], function (): int {
            return McpTask::query()->where('status', McpTask::AWAITING_HUMAN)->count();
        });
    }

    /**
     * As decisões possíveis, DERIVADAS DO FSM — nunca hardcoded.
     *
     * Se alguém alterar `McpTask::TRANSITIONS['pending_approval']`, a Mesa
     * acompanha sozinha. Hardcodar aqui criaria um segundo lugar onde o fluxo é
     * declarado, e os dois iam divergir na primeira mudança.
     *
     * O rótulo é o vocabulário da ADR 0368 §6 (eixo "decisão humana"):
     * admitida · admitida-parqueada · recusada — nunca "aprovado", que no
     * `CAPTERRA-INVENTARIO.md` já significa outra coisa ("a capacidade existe").
     *
     * @return list<array{status:string,verbo:string,descricao:string,exige_motivo:bool,atalho:string}>
     */
    public function decisoesPossiveis(): array
    {
        $rotulos = [
            'todo' => [
                'verbo'        => 'Admitir',
                'descricao'    => 'Entra no fluxo normal de trabalho.',
                'exige_motivo' => false,
                'atalho'       => 'a',
            ],
            'backlog' => [
                'verbo'        => 'Parquear',
                'descricao'    => 'Admitida, mas sem entrar na fila de trabalho agora.',
                'exige_motivo' => false,
                'atalho'       => 'd',
            ],
            'cancelled' => [
                'verbo'        => 'Recusar',
                // ADR 0368 §5: sem motivo, a mesma capacidade volta em três meses
                // e consome a decisão de novo. Quem enforça é o TaskCrudService.
                'descricao'    => 'Motivo obrigatório — vai pro inventário.',
                'exige_motivo' => true,
                'atalho'       => 'x',
            ],
        ];

        // Itera os RÓTULOS filtrando pelo FSM (e não o contrário). O efeito é o
        // mesmo — só sai o que o FSM permite —, mas sem `?? []` nem `isset()` sobre
        // offset que o PHPStan prova existir (os dois viravam código morto, e ele
        // reprova no ratchet, com razão).
        //
        // A proteção contra "destino novo no FSM sem rótulo aqui" NÃO se perdeu:
        // ela deixou de ser um `continue` silencioso e passou a ser o UC-APROV-05,
        // que exige o conjunto oferecido IGUAL a `TRANSITIONS`. Se alguém acrescentar
        // uma saída no FSM e esquecer o rótulo, o teste falha e diz o que falta —
        // em vez da UI simplesmente não mostrar o botão e ninguém perceber.
        $permitidos = McpTask::TRANSITIONS[McpTask::AWAITING_HUMAN];

        $out = [];
        foreach ($rotulos as $destino => $meta) {
            if (! in_array($destino, $permitidos, true)) {
                continue;
            }
            $out[] = ['status' => $destino] + $meta;
        }

        return $out;
    }

    /**
     * "Ao vivo no MCP" — quem da equipe está trabalhando agora, e em quê.
     *
     * A faixa do protótipo (`AoVivo`, `forja-aprova.jsx:24`) responde "quem está
     * na sala": ela existe pra que a mesa não pareça uma caixa de entrada morta.
     *
     * FONTES REAIS, nenhuma inventada:
     *   · quem é       → `mcp_actors` (slug · display_name · type · trust_level)
     *   · o que faz    → `mcp_cc_sessions` (sessão aberta: projeto + branch)
     *   · desde quando → `mcp_cc_sessions.started_at` e `mcp_audit_log.ts`
     *   · custo hoje   → `mcp_audit_log.custo_brl` do dia, por `user_id`
     *
     * ⚠️ O eixo `nivel` do protótipo (sênior/júnior/artista/agente) NÃO existe
     * neste schema e não é derivável: `mcp_actors` declara `type`
     * (human/ai_agent/service) e `trust_level` (L0..L4), que é outra coisa.
     * Mapear um no outro seria inventar semântica que ninguém decidiu, então o
     * selo mostra o que É declarado. Diferença de categoria, não bug de paridade
     * (ADR 0385).
     *
     * Multi-tenant: `mcp_cc_sessions` TEM `business_id` com global scope
     * (`HasBusinessScope`) e ele fica LIGADO de propósito — sessão de Claude Code
     * é dado de tenant, ao contrário de `mcp_tasks`. Sem `withoutGlobalScopes`.
     *
     * @return list<array<string,mixed>>
     */
    public function aoVivo(): array
    {
        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_actors/mcp_audit_log são repo-wide (ADR 0070/0093); mcp_cc_sessions mantém o scope global LIGADO

        return OtelHelper::span('forja.aprovacoes.ao_vivo', [], function (): array {
            $hoje = Carbon::today();

            $atores = McpActor::query()
                ->whereNull('revoked_at')
                ->whereNotNull('user_id')
                ->orderBy('id')
                ->get();

            if ($atores->isEmpty()) {
                return [];
            }

            $userIds = $atores->pluck('user_id')->filter()->map(fn ($id): int => (int) $id)->all();

            // Custo do dia + última batida, por usuário. Uma query, não N.
            $uso = DB::table('mcp_audit_log')
                ->whereIn('user_id', $userIds)
                ->whereDate('ts', $hoje)
                ->groupBy('user_id')
                ->selectRaw('user_id, SUM(custo_brl) AS custo, MAX(ts) AS ultima')
                ->get()
                ->keyBy('user_id');

            // Sessão ABERTA (sem ended_at) = "executando". Scope de business ligado.
            $abertas = McpCcSession::query()
                ->whereIn('user_id', $userIds)
                ->whereNull('ended_at')
                ->orderByDesc('started_at')
                ->get()
                ->keyBy('user_id');

            // Quem tem item esperando decisão = "espera você". `owner` casa com o slug do ator.
            $esperando = McpTask::query()
                ->where('status', McpTask::AWAITING_HUMAN)
                ->whereNotNull('owner')
                ->get()
                ->groupBy('owner');

            return $atores
                ->map(function (McpActor $a) use ($uso, $abertas, $esperando): array {
                    $uid = (int) $a->user_id;
                    $sessao = $abertas->get($uid);
                    $fila = $esperando->get((string) $a->slug);
                    $linha = $uso->get($uid);

                    if ($fila !== null && $fila->isNotEmpty()) {
                        $status = 'aguardando';
                        $fazendo = (string) $fila->first()->title;
                    } elseif ($sessao !== null) {
                        $status = 'executando';
                        $fazendo = $this->descreveSessao($sessao);
                    } else {
                        $status = 'offline';
                        $fazendo = '—';
                    }

                    $ultima = $linha !== null && $linha->ultima !== null
                        ? Carbon::parse((string) $linha->ultima)
                        : ($sessao?->started_at instanceof Carbon ? $sessao->started_at : null);

                    return [
                        'slug'       => (string) $a->slug,
                        'pessoa'     => (string) ($a->display_name ?: $a->slug),
                        'tipo'       => (string) $a->type,          // human · ai_agent · service
                        'confianca'  => (string) $a->trust_level,   // L0..L4 — o que o schema declara
                        'status'     => $status,
                        'fazendo'    => $fazendo,
                        'custo_hoje' => (float) ($linha->custo ?? 0),
                        'ha'         => $ultima?->diffForHumans(),
                    ];
                })
                ->values()
                ->all();
        });
    }

    /**
     * Placar por PAPEL do loop de design — a tabela "Equipe de agentes" do
     * protótipo (`forja-aprova.jsx:210`), pedida pelo [W] em 2026-08-08 e que
     * estava no `[BACKLOG]` do `casos.md` esperando exatamente este backend.
     *
     * A linha é o `created_by` de `cowork_handoffs` (CC · CD · CL · CA · AN),
     * porque é o único lugar onde o papel do loop existe como dado.
     *
     * O QUE É MEDIDO (e de onde):
     *   · sinal      → `MAX(created_at)` dos handoffs do papel
     *   · critique   → média de `gate_status->critique_score` (o mesmo número que o
     *                  `HandoffAckTool` exige >= 80 pra deixar virar `applied`)
     *   · retrabalho → handoffs `rejected` na janela (o "devolvido ao autor")
     *   · entregas   → handoffs criados na janela
     *
     * ⚠️ O QUE NÃO É MEDIDO, E POR QUÊ — "sessões hoje" e "custo/quota" do
     * protótipo são POR USUÁRIO (`mcp_cc_sessions`, `mcp_audit_log` e
     * `mcp_quotas` são todos `user_id`), e NÃO existe no schema ligação
     * papel→usuário: os atores semeados são `wagner`/`felipe`/`maira`/`luiz`/
     * `eliana`/`claude-code-wagner-laptop`, nunca `CC`/`CD`/`CL`. Preencher essas
     * colunas exigiria inventar o vínculo — dado fantasma. Elas vão `null`, e a
     * tela mostra "—" dizendo o motivo. Criar o vínculo é decisão [W].
     *
     * @return list<array<string,mixed>>
     */
    public function placar(): array
    {
        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — cowork_handoffs é repo-wide (ADR 0093/0283), sem tenant por design

        return OtelHelper::span('forja.aprovacoes.placar', [], function (): array {
            $desde = Carbon::now()->subDays(self::PLACAR_DIAS);

            $handoffs = CoworkHandoff::query()
                ->where('status', '!=', 'superseded')
                ->where('created_at', '>=', $desde)
                ->get();

            if ($handoffs->isEmpty()) {
                return [];
            }

            return $handoffs
                ->groupBy(fn (CoworkHandoff $h): string => (string) ($h->created_by ?: '—'))
                ->map(function ($doPapel, string $papel): array {
                    $scores = $doPapel
                        ->map(fn (CoworkHandoff $h): ?int => $this->critiqueScore($h))
                        ->filter(fn (?int $n): bool => $n !== null)
                        ->values();

                    $ultimo = $doPapel
                        ->map(fn (CoworkHandoff $h) => $h->created_at)
                        ->filter(fn ($d): bool => $d instanceof Carbon)
                        ->sortDesc()
                        ->first();

                    $entregas = $doPapel->count();
                    $retrabalho = $doPapel->where('status', 'rejected')->count();
                    $media = $scores->isEmpty() ? null : (int) round((float) $scores->avg());

                    return [
                        'papel'          => $papel,
                        'sinal'          => $ultimo instanceof Carbon ? $ultimo->diffForHumans() : null,
                        // "sem sinal" é sobre o SILÊNCIO do papel, não sobre falha:
                        // sem handoff na janela de N horas, o alarme acende.
                        'sinal_ok'       => $ultimo instanceof Carbon
                            && $ultimo->gt(Carbon::now()->subHours(self::SEM_SINAL_HORAS)),
                        'critique'       => $media,
                        'critique_serie' => $scores->all(),
                        'critique_baixo' => $media !== null && $media < self::CRITIQUE_PISO,
                        'entregas'       => $entregas,
                        'retrabalho'     => $retrabalho,
                        'retrabalho_pct' => $entregas > 0 ? (int) round($retrabalho / $entregas * 100) : 0,
                        // Sem fonte por PAPEL — ver o docblock. `null` é a resposta honesta.
                        'sessoes_hoje'   => null,
                        'custo_hoje'     => null,
                        'quota_dia'      => null,
                    ];
                })
                ->sortByDesc('entregas')
                ->values()
                ->all();
        });
    }

    /**
     * Quantos handoffs estão com problema — o botão de alerta do cabeçalho
     * (`ap-handoff-alert`), que leva pra /forja/handoffs.
     *
     * "Problema" = o mesmo par que o protótipo usa (`estado stale || gateConflito`)
     * e que o {@see ForjaMcpService} já deriva: parado além do teto, ou ack
     * dizendo verde com required check vermelho no PR (Gap 2 · ADR 0283).
     *
     * Delega ao dono do tema em vez de reimplementar a derivação aqui — as duas
     * cópias divergiriam na primeira mudança da regra.
     */
    public function handoffsComProblema(): int
    {
        return OtelHelper::span('forja.aprovacoes.handoffs_problema', [], function (): int {
            $handoffs = app(ForjaMcpService::class)->handoffs();

            return count(array_filter(
                $handoffs,
                fn (array $h): bool => ($h['status'] ?? null) === 'stale' || ($h['gate'] ?? null) === 'conflito',
            ));
        });
    }

    /** Descreve o que uma sessão aberta está fazendo, com o que a tabela guarda. */
    private function descreveSessao(McpCcSession $s): string
    {
        $resumo = trim((string) ($s->summary_auto ?? ''));
        if ($resumo !== '') {
            return $resumo;
        }

        $projeto = trim((string) ($s->project_path ?? ''));
        $branch = trim((string) ($s->git_branch ?? ''));

        if ($projeto !== '' && $branch !== '') {
            return basename($projeto) . ' · ' . $branch;
        }

        if ($projeto !== '') {
            return basename($projeto);
        }

        return $branch !== '' ? $branch : 'sessão aberta';
    }

    /** O `critique_score` gravado no ack (A3), ou null quando o handoff não tem gate. */
    private function critiqueScore(CoworkHandoff $h): ?int
    {
        $g = $h->gate_status;
        if (! is_array($g) || ! isset($g['critique_score'])) {
            return null;
        }

        return (int) $g['critique_score'];
    }

    /**
     * Serializa 1 item da fila pro front.
     *
     * @return array<string,mixed>
     */
    private function serialize(McpTask $t): array
    {
        $criadoEm = $t->created_at instanceof Carbon ? $t->created_at : null;
        $esperaMin = $criadoEm !== null ? (int) $criadoEm->diffInMinutes(now()) : 0;

        return [
            'task_id'          => $t->task_id,
            'identifier'       => $t->identifier,
            'title'            => $t->title,
            'module'           => $t->module,
            'type'             => $t->type,
            'priority'         => $t->priority,
            'owner'            => $t->owner,
            'created_at'       => optional($criadoEm)->toIso8601String(),
            'created_at_human' => optional($criadoEm)->diffForHumans(),
            'espera_min'       => $esperaMin,
            'sla'              => $this->sla($esperaMin),
        ];
    }

    /**
     * Faixa de espera. Só descreve o tempo — não decide nada e não bloqueia.
     */
    private function sla(int $esperaMin): string
    {
        if ($esperaMin >= self::SLA_URGENTE_MIN) {
            return 'urgente';
        }

        if ($esperaMin >= self::SLA_ATENCAO_MIN) {
            return 'atencao';
        }

        return 'ok';
    }
}
