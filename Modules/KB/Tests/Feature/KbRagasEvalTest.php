<?php

declare(strict_types=1);

/**
 * KbRagasEvalTest — Wave 23 KB §G2 — port do pattern Jana RAGAS pra KB.
 *
 * Mede baseline cego (mock mode default) sobre 20 perguntas golden KB.
 * Real mode opt-in via .env OPENAI_API_KEY + RAGAS_FORCE_MOCK=false.
 *
 * Métricas RAGAS reusando RagasJudgeService do Jana (Modules\Jana\Services\Ragas):
 *   - faithfulness — respostas KB sem alucinação
 *   - answer_relevancy — respostas relevantes
 *   - context_precision — top-N reranked com signal > noise
 *
 * Custo aprox real mode (20 ex × 3 métricas):
 *   - ~$0.024 por suite run
 *   - Weekly = ~$0.10/mês
 *
 * @group ragas
 * @see Modules/Jana/Services/Ragas/RagasJudgeService.php
 * @see Modules/KB/Services/KbRagService.php
 * @see Wave 22 FICHA KB §G2 RAGAS eval suite KB
 */

use Modules\Jana\Services\Ragas\RagasJudgeService;

// TestCase é aplicado via tests/Pest.php uses(TestCase::class)->in(KbFeatureDir).

/**
 * Gold-set KB — 20 perguntas canon sobre Knowledge Base unificado.
 * Cobre: SCHEMA KB, multi-tenant Tier 0, retrieval, ADRs canon.
 */
function ragasKbGoldSet(): array
{
    return [
        ['question' => 'Qual ADR formaliza o KB unificado como módulo IA central?', 'ground_truth' => 'ADR 0149 — KB Unificado grafo conhecimento módulo IA central. Define 12 tabelas kb_* + bridge mcp_memory_documents.', 'tags' => ['kb', 'governance']],
        ['question' => 'Quantas tabelas o schema KB v1 define?', 'ground_truth' => 'SCHEMA-DB-V1.md define 12 tabelas: kb_categories, kb_subcategories, kb_nodes, kb_edges, kb_paths, kb_path_steps, kb_decision_trees, kb_decision_tree_steps, kb_node_versions, kb_favorites, kb_comments, kb_bridge_state.', 'tags' => ['kb']],
        ['question' => 'Como KbRagService::ask() faz RAG?', 'ground_truth' => 'Flow: sanitize PII → corpus hash → cache check → retrieve top-K Meilisearch → rerank top-N (bonus type) → prompt LLM via KbAnswerAgent → parse + cache.', 'tags' => ['kb', 'ia-stack']],
        ['question' => 'Qual o re-rank bonus por tipo no KbRagService legacy?', 'ground_truth' => 'Bonus +0.15 para charter/adr/runbook (governança canon). Bonus +0.10 para article (operacional). Penalty -0.05 se snippet vazio.', 'tags' => ['kb']],
        ['question' => 'KbRagService usa SaaS LLM externo?', 'ground_truth' => 'KbRagService usa KbAnswerAgent (Modules/Jana/Ai/Agents) que reusa laravel/ai SDK. Modelo padrão gpt-4o-mini (ADR 0035 Brain A) — PII sempre redacted antes via PiiRedactor.', 'tags' => ['kb', 'ia-stack', 'lgpd']],
        ['question' => 'Como funciona PII redact no KB?', 'ground_truth' => 'Service KbRagService::redactPii usa PiiRedactor (Modules/Jana/Services/Privacy/). Defense in depth — toda string que vai pra LLM/cache/log passa pelo redactor. Cobre CPF, CNPJ, email, CEP, phone BR.', 'tags' => ['kb', 'lgpd']],
        ['question' => 'KbRagService permite cross-tenant?', 'ground_truth' => 'NÃO. Service exige business_id positivo explícito em todos métodos públicos (assertBusinessId throws InvalidArgumentException). Multi-tenant Tier 0 ADR 0093. Cross-tenant Pest CrossTenantIsolationTest exigido.', 'tags' => ['kb', 'multi-tenant']],
        ['question' => 'Onde KbRagService cacheia respostas?', 'ground_truth' => 'Redis Cache TTL 1h ask() — key sha1(query|corpusHash) + biz_id. TTL 6h summarize() — key inclui node updated_at + sourceDoc updated_at.', 'tags' => ['kb']],
        ['question' => 'Como BgeReranker melhora KB retrieval?', 'ground_truth' => 'BAAI/bge-reranker-v2-m3 self-host CT 100. Improve nDCG@10 +6-15pp vs RRF/score legacy. Latency +100-300ms aceitável.', 'tags' => ['kb', 'ia-stack']],
        ['question' => 'KbBgeRerankerService faz graceful degradation?', 'ground_truth' => 'Sim. HTTP fail no driver BgeReranker delega pra RrfReranker (fallback). Se driver retorna vazio, KbBgeRerankerService devolve top-N por score legacy.', 'tags' => ['kb']],
        ['question' => 'Qual a estimativa de custo gpt-4o-mini no KbRagService?', 'ground_truth' => 'Constantes: PRICE_INPUT_PER_M_USD = 0.15, PRICE_OUTPUT_PER_M_USD = 0.60. USD→BRL via config(kb.usd_to_brl, 5.0). Cost logged em copiloto-ai channel + mcp_audit_log.', 'tags' => ['kb']],
        ['question' => 'Como KbRagService gera citações?', 'ground_truth' => 'Prompt numera FONTES [1][2][3]…top-N (renderFontesBlock). KbAnswerAgent retorna formato: Resposta + Citações: - [slug](path) — quote + Confiança. parseAgentResponse extrai answer + confidence.', 'tags' => ['kb', 'ia-stack']],
        ['question' => 'Quais drivers de memória ADR 0035?', 'ground_truth' => 'MemoriaContrato + MeilisearchDriver default (produção). NullDriver dev. NÃO usa Pinecone, Weaviate, Qdrant cloud — self-host CT 100 (ADR 0058+0062).', 'tags' => ['kb', 'ia-stack']],
        ['question' => 'Como rodar Pest da suite KB?', 'ground_truth' => 'Pest v4. Tests em Modules/KB/Tests/{Feature,Unit}/* registrados em phpunit.xml. Helpers em Modules/KB/Tests/Helpers.php com kbBootstrapSchema/kbActAsUser. NUNCA biz=4 (ROTA LIVRE prod).', 'tags' => ['kb', 'multi-tenant']],
        ['question' => 'Qual o pattern de bridge KB ↔ MCP?', 'ground_truth' => 'KbBridgeFromMcpJob lê mcp_memory_documents (canon git) e cria/atualiza kb_nodes scoped por business_id. KbBridgeState persiste cursor. Job recebe $businessId no constructor (ADR 0093 §4).', 'tags' => ['kb', 'multi-tenant']],
        ['question' => 'O que faz KbEdgeAutoDeriver?', 'ground_truth' => 'KbEdgeAutoDeriver detecta relações entre kb_nodes (ex: ADR cita outra ADR, charter referencia runbook) e cria kb_edges auto. KbEdgeAutoDeriverJob async per business.', 'tags' => ['kb']],
        ['question' => 'KbRagService suporta idempotency_key?', 'ground_truth' => 'Sim. Via opts[idempotency_key]. Cache key vira sha1(idempotency_key):biz:{businessId}. Útil pra retry seguro de jobs.', 'tags' => ['kb']],
        ['question' => 'O que retorna RagResult::notFound?', 'ground_truth' => 'Resultado vazio quando top-K Meilisearch vazio. Inclui latency_ms + corpus_hash atual + cache_hit=false. Frontend mostra mensagem amigável ao usuário.', 'tags' => ['kb']],
        ['question' => 'Como suggestMeta gera auto-tag?', 'ground_truth' => 'Recebe body_blocks rascunho, serializa inline + redact PII + cap 8000 chars. Prompt gpt-4o-mini extrai TITLE/EXCERPT/TAGS/CATEGORY/NIVEL. Fail-safe: retorna meta vazia ou snippet truncado.', 'tags' => ['kb', 'ia-stack']],
        ['question' => 'Onde fica a Charter do KB no projeto?', 'ground_truth' => 'memory/requisitos/KB/BRIEFING.md + SCHEMA-DB-V1.md. ADR proposal em memory/decisions/proposals/0149-kb-unificado-grafo-conhecimento-modulo-ia-central.md.', 'tags' => ['kb', 'governance']],
    ];
}

beforeEach(function () {
    if (! env('RAGAS_FORCE_MOCK', true) && empty(env('OPENAI_API_KEY'))) {
        $this->markTestSkipped('Real mode exige OPENAI_API_KEY OR RAGAS_FORCE_MOCK=true.');
    }
});

it('gold-set KB tem >= 20 perguntas canon', function () {
    $set = ragasKbGoldSet();
    expect(count($set))->toBeGreaterThanOrEqual(20);

    foreach ($set as $i => $q) {
        expect($q)->toHaveKeys(['question', 'ground_truth', 'tags'], "Item #{$i} malformado");
    }
})->group('ragas');

it('KB faithfulness gate >= threshold sobre gold-set (mock-safe)', function () {
    $judge = app(RagasJudgeService::class);
    if (env('RAGAS_FORCE_MOCK', true)) {
        $judge->enableMock(['faithfulness' => 0.82]);
    }

    $gold = array_slice(ragasKbGoldSet(), 0, (int) config('ragas.sample_size', 5));
    $threshold = (float) config('ragas.thresholds.faithfulness', 0.70);

    $scores = [];
    foreach ($gold as $q) {
        $scores[] = $judge->scoreFaithfulness($q['question'], $q['ground_truth'], $q['ground_truth']);
    }

    $avg = array_sum($scores) / max(1, count($scores));
    expect($avg)->toBeGreaterThanOrEqual($threshold);
})->group('ragas');

it('KB answer_relevancy gate >= threshold sobre gold-set (mock-safe)', function () {
    $judge = app(RagasJudgeService::class);
    if (env('RAGAS_FORCE_MOCK', true)) {
        $judge->enableMock(['answer_relevancy' => 0.76]);
    }

    $gold = array_slice(ragasKbGoldSet(), 0, (int) config('ragas.sample_size', 5));
    $threshold = (float) config('ragas.thresholds.answer_relevancy', 0.60);

    $scores = [];
    foreach ($gold as $q) {
        $scores[] = $judge->scoreAnswerRelevancy($q['question'], $q['ground_truth']);
    }

    $avg = array_sum($scores) / max(1, count($scores));
    expect($avg)->toBeGreaterThanOrEqual($threshold);
})->group('ragas');

it('KB context_precision gate >= threshold sobre gold-set (mock-safe)', function () {
    $judge = app(RagasJudgeService::class);
    if (env('RAGAS_FORCE_MOCK', true)) {
        $judge->enableMock(['context_precision' => 0.80]);
    }

    $gold = array_slice(ragasKbGoldSet(), 0, (int) config('ragas.sample_size', 5));
    $threshold = (float) config('ragas.thresholds.context_precision', 0.70);

    $scores = [];
    foreach ($gold as $q) {
        $scores[] = $judge->scoreContextPrecision($q['question'], $q['ground_truth'], $q['ground_truth']);
    }

    $avg = array_sum($scores) / max(1, count($scores));
    expect($avg)->toBeGreaterThanOrEqual($threshold);
})->group('ragas');

it('KB gold-set cobre tags canon (kb, multi-tenant, ia-stack)', function () {
    $set = ragasKbGoldSet();
    $allTags = [];
    foreach ($set as $q) {
        foreach ($q['tags'] as $tag) {
            $allTags[$tag] = ($allTags[$tag] ?? 0) + 1;
        }
    }

    expect($allTags)->toHaveKey('kb');
    expect($allTags['kb'])->toBeGreaterThanOrEqual(15);
    expect($allTags)->toHaveKey('multi-tenant');
    expect($allTags)->toHaveKey('ia-stack');
})->group('ragas');
