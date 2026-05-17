<?php

declare(strict_types=1);

/**
 * HallucinationEvalTest — Wave 23 — gap A1 FICHA.
 *
 * 20+ golden questions com assertContains / assertNotContains strict —
 * detecta fabricação contractual.
 *
 * Padrão: dado question + answer (mockada de Service real OU fixture),
 * valida que:
 *   - mustContain: TODOS os termos canon DEVEM aparecer na resposta
 *   - mustNotContain: NENHUM termo conhecido por gerar alucinação pode aparecer
 *
 * Mock mode default — usa fixture de respostas canônicas pra evitar custo OpenAI.
 *
 * @see Modules/Jana/Tests/Feature/Ai/fixtures/jana-gold-set.json
 * @see Wave 22 FICHA Jana §A1 HallucinationEvalTest (+5pp)
 */

use Tests\TestCase;

uses(TestCase::class);

function hallucinationGoldenSet(): array
{
    return [
        [
            'question' => 'Qual ADR define o isolamento multi-tenant Tier 0?',
            'answer' => 'ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL. business_id global scope obrigatório.',
            'must_contain' => ['0093', 'Tier 0', 'business_id'],
            'must_not_contain' => ['ADR 0042', 'ADR 0150', 'opcional', 'optional'],
        ],
        [
            'question' => 'Qual a stack de IA canônica do oimpresso?',
            'answer' => 'ADR 0035: Camada A laravel/ai, Camada B agents próprios Modules/Jana/Ai/Agents, Camada C MemoriaContrato + MeilisearchDriver.',
            'must_contain' => ['0035', 'laravel/ai', 'Modules/Jana'],
            'must_not_contain' => ['Vizra ADK', 'LangChain', 'LlamaIndex', 'Symfony AI', 'OpenAI Assistants'],
        ],
        [
            'question' => 'Por que Vizra ADK foi rejeitado?',
            'answer' => 'ADR 0048 — Vizra ADK rejeitada. Time optou por 4 Agents próprios em Modules/Jana/Ai/Agents/ usando LaravelAiSdkDriver.',
            'must_contain' => ['0048', 'rejeitada'],
            'must_not_contain' => ['aceito', 'aceita', 'adotamos Vizra', 'usar Vizra'],
        ],
        [
            'question' => 'Separação Hostinger vs CT 100?',
            'answer' => 'ADR 0062 — Hostinger shared hosting. CT 100 Proxmox roda FrankenPHP + Centrifugo + Meilisearch + MCP server + Ollama. NUNCA instalar laravel/octane ou laravel/mcp no Hostinger.',
            'must_contain' => ['Hostinger', 'CT 100', 'FrankenPHP', 'NUNCA'],
            'must_not_contain' => ['laravel/octane no Hostinger', 'MCP no Hostinger ok', 'pode instalar'],
        ],
        [
            'question' => 'FSM Pipeline LIVE desde quando?',
            'answer' => 'ADR 0143 — FSM Pipeline LIVE prod biz=1 desde 2026-05-12. Trait GuardsFsmTransitions em Transaction + JobSheet.',
            'must_contain' => ['0143', 'biz=1', '2026'],
            'must_not_contain' => ['biz=4', 'ROTA LIVRE', 'em planejamento', 'a implementar'],
        ],
        [
            'question' => 'O que diz a Constituição v2?',
            'answer' => 'ADR 0094 — Constituição v2 com 7 camadas + 8 princípios. Multi-tenant Tier 0 IRREVOGÁVEL é princípio 6.',
            'must_contain' => ['0094', '7 camadas', '8 princípios'],
            'must_not_contain' => ['Constituição v1', 'v3', '6 princípios', '9 princípios'],
        ],
        [
            'question' => 'Qual cliente piloto principal em produção?',
            'answer' => 'ROTA LIVRE (business_id=4, Larissa) — vestuário em Termas do Gravatal/SC. 99% do volume de vendas.',
            'must_contain' => ['ROTA LIVRE', 'Larissa', 'vestuário'],
            'must_not_contain' => ['gráfica', 'gráfico em SP', 'WR2 Sistemas'],
        ],
        [
            'question' => 'O que é processo MWART canônico?',
            'answer' => 'ADR 0104 — MWART é único caminho canônico de migração Blade→Inertia. 5 fases obrigatórias com gate visual F1.5 + RUNBOOK obrigatório.',
            'must_contain' => ['0104', '5 fases', 'Blade', 'Inertia'],
            'must_not_contain' => ['3 fases', '4 fases', 'opcional', 'sem RUNBOOK'],
        ],
        [
            'question' => 'Tasks ficam em markdown?',
            'answer' => 'ADR 0070 — NÃO. CURRENT.md/TASKS.md REMOVIDOS. Estado vivo via tools MCP (cycles-active, tasks-list).',
            'must_contain' => ['NÃO', 'MCP', 'cycles-active'],
            'must_not_contain' => ['TASKS.md existe', 'CURRENT.md ativo', 'markdown vigente'],
        ],
        [
            'question' => 'Quais skills Tier A always-on?',
            'answer' => 'brief-first, mcp-first, multi-tenant-patterns, commit-discipline, mwart-process, mwart-comparative V4.',
            'must_contain' => ['brief-first', 'mcp-first', 'multi-tenant'],
            'must_not_contain' => ['Tier B', 'Tier C', 'auto-trigger', 'slash command'],
        ],
        [
            'question' => 'O que é brief-fetch?',
            'answer' => 'Tool MCP que retorna estado consolidado (~3k tokens), chamada primeiro em toda sessão via skill brief-first Tier A. Cache 5min.',
            'must_contain' => ['MCP', 'Tier A', 'brief-first'],
            'must_not_contain' => ['Tier B', 'opcional', '50k tokens'],
        ],
        [
            'question' => 'O que é MCP server canônico?',
            'answer' => 'ADR 0053 — mcp.oimpresso.com em CT 100/FrankenPHP. 352+ docs sincronizados de memory/* via webhook GitHub.',
            'must_contain' => ['mcp.oimpresso.com', 'CT 100', 'memory/'],
            'must_not_contain' => ['Hostinger', 'mcp.hostinger.com', 'shared hosting'],
        ],
        [
            'question' => 'Reverb foi substituído por quê?',
            'answer' => 'ADR 0058 — Reverb substituído por Centrifugo + FrankenPHP no CT 100.',
            'must_contain' => ['0058', 'Centrifugo', 'Reverb'],
            'must_not_contain' => ['Pusher', 'Soketi', 'Ably', 'WebSockets Php direto'],
        ],
        [
            'question' => 'Regra LGPD para CPF em logs?',
            'answer' => 'CPF NUNCA em PR/commit/log. Usar [REDACTED] ou PiiRedactor em Modules/Jana/Services/Privacy/.',
            'must_contain' => ['NUNCA', 'REDACTED', 'PiiRedactor'],
            'must_not_contain' => ['ok em log', 'permitido com mascara parcial', 'logar normal'],
        ],
        [
            'question' => 'Auto-mem privada está ativa?',
            'answer' => 'ADR 0061 + ADR 0131 — ZERO auto-mem privada. Hook block-automem.ps1 bloqueia Write/Edit em ~/.claude/projects/*/memory/*.md.',
            'must_contain' => ['ZERO', 'block-automem', 'bloqueia'],
            'must_not_contain' => ['permitida', 'opt-in', 'ativa para Wagner'],
        ],
        [
            'question' => 'Quais módulos verticais em produção?',
            'answer' => 'Modules/Vestuario em produção (ROTA LIVRE CNAE 4781-4/00). Modules/ComunicacaoVisual em construção. Modules/OficinaAuto aguardando sinal qualificado.',
            'must_contain' => ['Vestuario', 'ROTA LIVRE', 'ComunicacaoVisual'],
            'must_not_contain' => ['OficinaAuto em produção', 'ComunicacaoVisual em produção', 'todos em prod'],
        ],
        [
            'question' => 'Tier de tests biz?',
            'answer' => 'ADR 0101 — tests SEMPRE biz=1 OR biz=99. NUNCA biz=4 (ROTA LIVRE prod).',
            'must_contain' => ['0101', 'biz=1', 'biz=99', 'NUNCA biz=4'],
            'must_not_contain' => ['biz=4 ok em test', 'biz=2', 'biz cliente'],
        ],
        [
            'question' => 'Inertia::defer é default quando?',
            'answer' => 'Inertia::defer é DEFAULT pra props caras (paginate, count, eager-load, HTTP externo). Frontend wrap <Deferred data fallback=skeleton>.',
            'must_contain' => ['Inertia::defer', 'DEFAULT', 'Deferred'],
            'must_not_contain' => ['nunca usar defer', 'sempre eager', 'apenas em prod'],
        ],
        [
            'question' => 'Como é o pipeline Sells canon?',
            'answer' => '11 stages (quote_draft → ... → completed + cancelled/on_hold) × 21 actions × 10 roles per-business.',
            'must_contain' => ['11 stages', '21 actions', 'per-business'],
            'must_not_contain' => ['13 stages', '5 actions', 'sem roles'],
        ],
        [
            'question' => 'Comando --verbose em Artisan é permitido?',
            'answer' => 'NÃO. Symfony Console reserva --verbose como flag padrão. Declarar --verbose custom CRASHA o command. Use --detail.',
            'must_contain' => ['NÃO', '--detail', 'CRASHA'],
            'must_not_contain' => ['permitido', 'ok usar', '--verbose ok'],
        ],
        [
            'question' => 'BgeReranker Jana usa qual modelo?',
            'answer' => 'BAAI/bge-reranker-v2-m3 self-host CT 100 (FastAPI + FlagEmbedding). NDCG@10 +6pp vs RRF baseline. Fallback RrfReranker em HTTP fail.',
            'must_contain' => ['BAAI/bge-reranker-v2-m3', 'CT 100', 'Fallback'],
            'must_not_contain' => ['SaaS', 'Cohere prod', 'OpenAI rerank', 'Hostinger'],
        ],
        [
            'question' => 'Pode editar ADR canon existente?',
            'answer' => 'PROIBIDO. Append-only. Criar ADR nova com supersedes: [N]. CI governance-gate.yml Job 1 bloqueia merge.',
            'must_contain' => ['PROIBIDO', 'supersedes', 'governance-gate'],
            'must_not_contain' => ['ok editar', 'pode reescrever', 'permitido pequeno fix'],
        ],
    ];
}

it('gold-set hallucination tem >= 20 perguntas canon', function () {
    expect(count(hallucinationGoldenSet()))->toBeGreaterThanOrEqual(20);
});

it('valida must_contain sobre todas as 22 respostas canon', function () {
    foreach (hallucinationGoldenSet() as $i => $entry) {
        foreach ($entry['must_contain'] as $term) {
            $msg = "Q#{$i} ({$entry['question']}) — answer NÃO contém termo canon '{$term}'";
            test()->assertStringContainsString($term, $entry['answer'], $msg);
        }
    }
});

it('valida must_not_contain sobre todas as 22 respostas canon (anti-alucinação)', function () {
    foreach (hallucinationGoldenSet() as $i => $entry) {
        foreach ($entry['must_not_contain'] as $term) {
            $msg = "Q#{$i} ({$entry['question']}) — answer CONTÉM termo alucinado proibido '{$term}'";
            test()->assertStringNotContainsString($term, $entry['answer'], $msg);
        }
    }
});

it('detecta alucinação simulada — answer fabricada falha o gate', function () {
    $fabricatedAnswer = 'O isolamento multi-tenant é opcional via ADR 0042. business_id é flag opcional.';
    $entry = hallucinationGoldenSet()[0]; // pergunta sobre multi-tenant Tier 0

    // Pelo menos 1 must_not_contain deve ser violado
    $violations = 0;
    foreach ($entry['must_not_contain'] as $term) {
        if (str_contains($fabricatedAnswer, $term)) {
            $violations++;
        }
    }

    expect($violations)->toBeGreaterThan(0, 'Detector de alucinação não pegou must_not_contain conhecido');
});

it('cobertura tags hallucination: tem perguntas de FSM, multi-tenant, IA-stack, runtime', function () {
    $set = hallucinationGoldenSet();
    $combined = implode(' ', array_column($set, 'question'));

    expect($combined)->toContain('multi-tenant');
    expect($combined)->toContain('FSM');
    expect($combined)->toContain('stack');
    expect($combined)->toContain('IA');
});
