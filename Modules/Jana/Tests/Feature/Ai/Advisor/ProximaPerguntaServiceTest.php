<?php

declare(strict_types=1);

use Laravel\Ai\Ai;
use Modules\Jana\Ai\Agents\ProximaPerguntaAgent;
use Modules\Jana\Services\Advisor\ProximaPerguntaService;

uses(Tests\TestCase::class);

/**
 * R-JANA-ADVISOR-B — GUARD tests da próxima-melhor-pergunta proativa (Metade B, ADR 0245).
 *
 *  001. flag OFF → no-op (sem LLM, sem bloco)
 *  002. snapshot vazio → null (honestidade: nada a pautar)
 *  003. happy path → bloco markdown com label da persona + pergunta + resposta
 *  004. honestidade — todas tem_pergunta=false → null
 *  005. max_por_persona corta o excesso
 *  006. fail-open — agente lança → null (brief sai sem o bloco)
 */
$snapshotCheio = [
    'sources' => [
        'vendas' => [
            'mes_corrente' => ['total' => 47150.0, 'ticket_medio' => 1890.0],
            'projecao_fechamento_mes' => 60000.0,
            'delta_projetado_pct' => -68.0,
        ],
        'inadimplencia' => [
            'total_devido_atrasado' => 4535636.0,
            'clientes_inadimplentes_count' => 4255,
            'top_5_devedores' => [['name' => 'VARGAS LEANDRO', 'devido' => 385195.0]],
        ],
        'tickets' => ['total_unread_business' => 7, 'top_5' => []],
        'nfe' => ['rejeitadas_30d' => 2, 'taxa_rejeicao_pct' => 5.0],
        'oportunidades' => [
            'reativacao_candidatos' => [['contact_name' => 'EXTREMA SOLDAS', 'ltv' => 71000.0, 'dias_sem_comprar' => 98]],
            'combo_candidatos' => [],
        ],
    ],
];

$snapshotVazio = [
    'sources' => [
        'vendas' => ['mes_corrente' => ['total' => 0.0]],
        'inadimplencia' => ['total_devido_atrasado' => 0.0, 'clientes_inadimplentes_count' => 0, 'top_5_devedores' => []],
        'tickets' => ['total_unread_business' => 0, 'top_5' => []],
        'nfe' => ['rejeitadas_30d' => 0],
        'oportunidades' => ['reativacao_candidatos' => [], 'combo_candidatos' => []],
    ],
];

/** Subclasse que injeta um snapshot fixo (sem montar o schema do ERP). */
function svcComSnapshot(array $snap): ProximaPerguntaService
{
    return new class($snap) extends ProximaPerguntaService {
        /** @param array<string,mixed> $snap */
        public function __construct(private array $snap) {}
        protected function snapshot(int $businessId): array { return $this->snap; }
    };
}

beforeEach(function () {
    config([
        'copiloto.advisor_questions.enabled' => true,
        'copiloto.advisor_questions.max_por_persona' => 2,
        'copiloto.advisor_questions.personas' => [
            ['key' => 'larissa', 'label' => 'Balcão / velocidade de venda', 'foco' => 'vendas'],
            ['key' => 'eliana',  'label' => 'Fiscal / financeiro',          'foco' => 'inadimplência'],
        ],
    ]);
});

it('R-JANA-ADVISOR-B-001 — flag OFF é no-op (sem LLM)', function () use ($snapshotCheio) {
    config(['copiloto.advisor_questions.enabled' => false]);
    Ai::fakeAgent(ProximaPerguntaAgent::class, [['blocos' => [
        ['persona' => 'larissa', 'tem_pergunta' => true, 'perguntas' => [['pergunta' => 'X?', 'porque' => 'y', 'resposta_curta' => 'z']]],
    ]]]);

    expect(svcComSnapshot($snapshotCheio)->gerarBloco(1, 'ACME'))->toBeNull();
});

it('R-JANA-ADVISOR-B-002 — snapshot vazio → null (honestidade)', function () use ($snapshotVazio) {
    Ai::fakeAgent(ProximaPerguntaAgent::class, [['blocos' => [
        ['persona' => 'larissa', 'tem_pergunta' => true, 'perguntas' => [['pergunta' => 'NUNCA', 'porque' => 'y', 'resposta_curta' => 'z']]],
    ]]]);

    // snapshot vazio curto-circuita ANTES do LLM.
    expect(svcComSnapshot($snapshotVazio)->gerarBloco(1, 'ACME'))->toBeNull();
});

it('R-JANA-ADVISOR-B-003 — happy path gera bloco por persona', function () use ($snapshotCheio) {
    Ai::fakeAgent(ProximaPerguntaAgent::class, [['blocos' => [
        ['persona' => 'eliana', 'tem_pergunta' => true, 'perguntas' => [
            ['pergunta' => 'Quanto dos R$ 4,5M vencidos está nos top 3 devedores?', 'porque' => 'concentração', 'resposta_curta' => 'VARGAS sozinho concentra R$ 385k.'],
        ]],
        ['persona' => 'larissa', 'tem_pergunta' => false, 'perguntas' => []],
    ]]]);

    $bloco = svcComSnapshot($snapshotCheio)->gerarBloco(1, 'ACME');

    expect($bloco)->toContain('Perguntas que você deveria fazer agora')
        ->and($bloco)->toContain('Fiscal / financeiro')
        ->and($bloco)->toContain('🔮')
        ->and($bloco)->toContain('VARGAS')
        // larissa (tem_pergunta=false) NÃO aparece — honestidade
        ->and($bloco)->not->toContain('Balcão / velocidade');
});

it('R-JANA-ADVISOR-B-004 — todas sem pergunta → null', function () use ($snapshotCheio) {
    Ai::fakeAgent(ProximaPerguntaAgent::class, [['blocos' => [
        ['persona' => 'larissa', 'tem_pergunta' => false, 'perguntas' => []],
        ['persona' => 'eliana', 'tem_pergunta' => false, 'perguntas' => []],
    ]]]);

    expect(svcComSnapshot($snapshotCheio)->gerarBloco(1, 'ACME'))->toBeNull();
});

it('R-JANA-ADVISOR-B-005 — max_por_persona corta excesso', function () use ($snapshotCheio) {
    config(['copiloto.advisor_questions.max_por_persona' => 1]);
    Ai::fakeAgent(ProximaPerguntaAgent::class, [['blocos' => [
        ['persona' => 'eliana', 'tem_pergunta' => true, 'perguntas' => [
            ['pergunta' => 'Pergunta 1?', 'porque' => 'a', 'resposta_curta' => 'r1'],
            ['pergunta' => 'Pergunta 2?', 'porque' => 'b', 'resposta_curta' => 'r2'],
            ['pergunta' => 'Pergunta 3?', 'porque' => 'c', 'resposta_curta' => 'r3'],
        ]],
    ]]]);

    $bloco = svcComSnapshot($snapshotCheio)->gerarBloco(1, 'ACME');

    expect($bloco)->toContain('Pergunta 1?')
        ->and($bloco)->not->toContain('Pergunta 2?')
        ->and($bloco)->not->toContain('Pergunta 3?');
});

it('R-JANA-ADVISOR-B-006 — fail-open: agente lança → null', function () use ($snapshotCheio) {
    Ai::fakeAgent(ProximaPerguntaAgent::class, function () {
        throw new RuntimeException('provider down');
    });

    expect(svcComSnapshot($snapshotCheio)->gerarBloco(1, 'ACME'))->toBeNull();
});
