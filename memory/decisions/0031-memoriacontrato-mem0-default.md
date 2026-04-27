# ADR 0031 — `MemoriaContrato` interface PHP + driver default `Mem0RestDriver`

**Status:** ✅ Aceita — **VERDADE CANÔNICA** (consolidada em ADR 0035) — ⚠️ **driver default revisado por ADR 0036**: `MeilisearchDriver` agora é default, `Mem0RestDriver` virou condicional (Sprint 8+, com triggers documentados)
**Data decisão:** 2026-04-26
**Autor:** Wagner (dono/operador) — *"melhor ROI"* (declaração canônica em ADR 0035)
**Registrado por:** Claude (sessão `dazzling-lichterman-e59b61`)
**Relacionado:**
- [ADR 0026 — Posicionamento "ERP gráfico com IA"](0026-posicionamento-erp-grafico-com-ia.md)
- [ADR 0027 — Gestão de memória do projeto](0027-gestao-memoria-roles-claros.md)
- [ADR 0032 — Vizra ADK + Prism PHP](0032-vizra-adk-prism-php-orquestracao.md)

---

## Contexto

Em 2026-04-26 foi feito comparativo Capterra do Copiloto runtime memory vs estado-da-arte (Mem0, LangGraph+LangMem, Letta, Zep, OMEGA). Achados centrais:

- **Copiloto runtime hoje é Tier 1 de ~10.** Tem `Conversa` + `Mensagem` flat + `ContextSnapshotService` (cache 10min). Zero embeddings, vector store, knowledge graph, summarization, forget, multi-tier.
- **Estado-da-arte (OMEGA 95.4%, Letta 83.2%, Zep 71.2%, Mem0 ~67%) usa hybrid (vector+graph+key-value), 3+ tiers e temporal validity.**
- **Mem0 lidera adoção** (21 frameworks integrados, **-91% latência** vs full-context, [arXiv 2504.19413](https://arxiv.org/abs/2504.19413)). Tem REST API utilizável de PHP. Trata agent-generated facts como first-class.
- Letta é melhor em LongMemEval mas exige Python self-hosted. Zep é melhor em temporal mas exige Graphiti+Neo4j.
- **Zero deles tem SDK PHP oficial** — a única forma viável é REST API.
- Construir camada de memória do zero em PHP nativo levaria 8-12 sprints e chegaria em Tier 3-4 só. Reinventaria roda.

Comparativos completos:
- [memory/comparativos/copiloto_runtime_memory_vs_mem0_langgraph_letta_zep_capterra_2026_04_26.md](../comparativos/copiloto_runtime_memory_vs_mem0_langgraph_letta_zep_capterra_2026_04_26.md) — Camada C (memória)
- [memory/comparativos/stack_agente_php_vizra_prism_mem0_capterra_2026_04_26.md](../comparativos/stack_agente_php_vizra_prism_mem0_capterra_2026_04_26.md) — stack completa (A+B+C)

## Decisão

**Adotamos `Modules\Copiloto\Contracts\MemoriaContrato` como interface PHP da camada de memória** com `Mem0RestDriver` como implementação default.

### Interface

```php
namespace Modules\Copiloto\Contracts;

interface MemoriaContrato
{
    /**
     * Persiste um fato/memória sobre o user no scope especificado.
     */
    public function lembrar(int $businessId, int $userId, string $fato, array $metadata = []): MemoriaPersistida;

    /**
     * Busca top-K memórias relevantes pra query (semantic search).
     */
    public function buscar(int $businessId, int $userId, string $query, int $topK = 5): array;

    /**
     * Atualiza fato existente. Pra drivers temporais (Zep), supersedes o antigo.
     */
    public function atualizar(string $memoriaId, string $novoFato, array $metadata = []): void;

    /**
     * Marca como esquecido (LGPD compliant).
     */
    public function esquecer(string $memoriaId): void;

    /**
     * Lista todas memórias de um user (pra tela "O Copiloto lembra de você").
     */
    public function listar(int $businessId, int $userId): array;
}
```

### Drivers planejados

| Driver | Quando usar | Status |
|---|---|---|
| `Mem0RestDriver` | **Default em produção.** Mem0 managed via REST. -91% latência, dedup automático, 21 frameworks integrados | A implementar (sprints 4-5 do caminho A) |
| `NullMemoriaDriver` | Dev/dry_run. Devolve fixtures, não chama rede | A implementar (sprint 1) |
| `ZepRestDriver` | Quando temporal validity virar requisito (US-COPI-MEM-009) | Futuro — só se Wagner aprovar |
| `LettaRestDriver` | Se Mem0 não escalar pra long-running agents | Futuro |

### Configuração

```php
// config/copiloto.php
'memoria' => [
    'driver' => env('COPILOTO_MEMORIA_DRIVER', 'mem0_rest'),
    'mem0' => [
        'api_key' => env('MEM0_API_KEY'),
        'base_url' => env('MEM0_BASE_URL', 'https://api.mem0.ai/v1'),
        'org_id' => env('MEM0_ORG_ID'),
        'top_k_default' => 5,
    ],
    'null' => [
        'fixtures_path' => 'tests/fixtures/memoria',
    ],
],
```

### Multi-tenant scope

Toda chamada usa `businessId + userId` como scope composto. Mem0 trata via `user_id="biz{id}_user{id}"` custom (workaround documentado na [docs Mem0](https://docs.mem0.ai/)).

## Justificativa

- **Comparativo Capterra deu B (REST adapter pra Mem0) como caminho recomendado** — 5-7 sprints, custo recorrente $25-300/mês, salto Tier 1 → 6-7.
- **Interface trocável** garante que se Mem0 sumir/encarecer/limitar, troca-se driver sem reescrever app.
- **Driver default `mem0_rest` em vez de hardcoded** abre caminho pra `null` em dev (LGPD-friendly em testes) e drivers futuros (Zep/Letta) sem refactor.
- **PHP native + REST do Mem0** evita container Python self-hosted (deal-breaker em Hostinger compartilhado).

## Consequências

✅ Copiloto pode sair de fixtures e responder com memória semântica real.
✅ Tier 6-7 LongMemEval atingível em 5-7 sprints.
✅ LGPD compliant via `esquecer()` + tela `/copiloto/memoria` (US-COPI-MEM-012).
✅ `null` driver pra testes.
⚠️ Custo recorrente $25-300/mês Mem0 — orçar antes de subscrever cliente.
⚠️ Multi-tenant via `user_id="biz{id}_user{id}"` é workaround — se Mem0 lançar tenancy nativa em 12-18m, vale migrar.
⚠️ Stack PHP precisa de internet pra Mem0 — em offline, cair pra `null` driver com warning.
⚠️ Dependência de upstream (Mem0 managed) — risco moderado, mitigado pela interface trocável.

## Alternativas consideradas

- **Construir camada PHP nativa do zero** (caminho A do comparativo Camada C): rejeitado — 8-12 sprints, Tier 3-4 só, reinventa roda.
- **Letta REST adapter** (LongMemEval 83.2%): rejeitado pra v1 — Letta tem foco em long-running agents (overshoot pro Copiloto chatbot). Reavaliar quando Copiloto virar agente persistente "always-on".
- **Zep REST adapter** (LongMemEval 71.2%, temporal validity): rejeitado pra v1 — temporal é overhead pra Copiloto v1 (que não tem fatos com validity windows ainda). Adicionar como `ZepRestDriver` quando US-COPI-MEM-009 virar requisito.
- **Driver hardcoded sem interface**: rejeitado — viola ADR 0027 (substituível) e ADR 0032 (camada B trocável).
- **OMEGA managed (LongMemEval 95.4%)**: rejeitado — premium-only, sem self-hosted, sem tier free pra dev. Reavaliar quando Copiloto tiver receita pra justificar.

## Refs externas

- [Mem0: Production-Ready AI Agents (arXiv 2504.19413)](https://arxiv.org/abs/2504.19413)
- [Mem0 GitHub](https://github.com/mem0ai/mem0)
- [State of AI Agent Memory 2026](https://mem0.ai/blog/state-of-ai-agent-memory-2026)

## Roadmap concreto

**Sprints 4-5 do caminho A (após sprints 1-3 do ADR 0032):**

- Sprint 4: implementar `MemoriaContrato` + `NullMemoriaDriver` + `Mem0RestDriver` + bind no service provider + 14 testes Pest
- Sprint 5: integrar no `ChatController@send` — buscar antes de chamar LLM, escrever fatos extraídos depois (job assíncrono `ExtrairFatosDaConversaJob`)
- Sprint 6 (opt): tela `/copiloto/memoria` (US-COPI-MEM-012) + LGPD opt-out

US relacionadas (do Anexo A do comparativo Camada C): **US-COPI-MEM-001 a 008** (Tiers 2-4).
