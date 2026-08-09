<?php

declare(strict_types=1);

namespace Modules\Forja\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Carbon;
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
