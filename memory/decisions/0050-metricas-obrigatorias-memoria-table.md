# ADR 0050 — 8 métricas obrigatórias de memória + tabela `memory_metrics`

**Status:** Aceito · Concretiza [ADR 0041](0041-stack-qa-ia-vizra-langfuse-deepeval.md) · Aterrissa o gate do [ADR 0049](0049-camadas-memoria-agente-fase-por-fase.md)
**Data:** 2026-04-29
**Origem:** Pesquisa exaustiva Wagner (29-abr-2026) — anexo `files.zip/ADR-004-estrategia-teste-memoria.md`

---

## Contexto

Sem testes objetivos, "memória funcionando" é intuição. Muito sistema em produção tem RAG sofisticado com Recall@5 de 0.4 e funciona "razoavelmente" só porque o LLM compensa contexto ruim. O receio de escolher errado é legítimo, e a única forma de mitigar é **medir**.

O ADR 0041 já planejou stack QA (Langfuse + DeepEval), mas **abstraído demais** para guiar implementação semana-a-semana. Este ADR aterrissa a régua concreta.

---

## Decisão

Adotar metodologia padrão de avaliação de retrieval com gabarito interno como ground truth, em **3 fases** + **8 métricas obrigatórias** + **tabela `memory_metrics`** persistida 1 linha/dia/business_id.

### Três fases de teste

**Fase 1 — Smoke test manual (10 min):** três turnos de conversa testando isolamento de cada camada de memória (sessão atual vs sessão nova). Já fazemos com Larissa após cada deploy.

**Fase 2 — Eval automatizado com gabarito:** **50 perguntas Larissa-style** com queries esperadas + memória esperada. Roda via comando `php artisan copiloto:metrics:apurar`, registra na tabela `memory_metrics`, mede tendência ao longo do tempo. (= COP-002 / MEM-P2-1)

**Fase 3 — Métricas em produção contínua:** scheduler diário roda `copiloto:metrics:apurar` para cada `business_id` ativo, agrega janela 24h.

### 8 métricas obrigatórias

| # | Métrica | Meta | Significado | Onde mede |
|---|---------|------|-------------|-----------|
| 1 | **Recall@3** | > 0.80 | % das vezes que a memória correta apareceu nos top 3 | Eval contra gabarito |
| 2 | **Precision@3** | > 0.60 | % dos top 3 que eram realmente relevantes | Eval contra gabarito |
| 3 | **MRR** | > 0.70 | Posição média da primeira memória relevante (Mean Reciprocal Rank) | Eval contra gabarito |
| 4 | **Latência p95** | < 2.0s | Ciclo completo (recall + LLM + resposta) | Log `copiloto-ai` |
| 5 | **Tokens/interação** | < 3.000 | Custo operacional médio por mensagem | `copiloto_mensagens.tokens_in + tokens_out` |
| 6 | **Memory bloat ratio** | > 0.60 | Memórias úteis (≥1 hit em 30d) / total armazenadas | Cross `copiloto_memoria_facts` × log recall |
| 7 | **Taxa de contradições** | < 2% | Memórias contraditórias sem `valid_until` setado | Query nos próprios fatos |
| 8 | **Cross-tenant violations** | = 0 | Memórias retornadas com `business_id ≠` business da query | Red-team trimestral + assert no `MeilisearchDriver::buscar` |

### Tabela `copiloto_memoria_metricas` (deployed 2026-04-29)

Schema final em [`Modules/Copiloto/Database/Migrations/2026_04_29_000001_create_copiloto_memoria_metricas_table.php`](../../Modules/Copiloto/Database/Migrations/2026_04_29_000001_create_copiloto_memoria_metricas_table.php):

- 8 colunas das métricas obrigatórias acima
- **+3 colunas RAGAS-aligned** (ver ADR 0051 — `faithfulness`, `answer_relevancy`, `context_precision`)
- Contadores: `total_interacoes_dia`, `total_memorias_ativas`
- `detalhes` JSON pra payload de rastreio
- Unique `(apurado_em, business_id)` → upsert idempotente
- FK `business_id → business.id` cascade delete

Entity: `Modules\Copiloto\Entities\MemoriaMetrica` com scopes `doBusinessOuPlataforma`, `ultimosDias` e helpers `metricasObrigatorias()` / `metricasRagas()`.

---

## Justificativa

- **Construir o gabarito é trabalho não-perdido**, independente da camada de memória escolhida no futuro.
- **Métricas padrão da indústria** (Recall@K, MRR, Faithfulness) permitem comparar versões e benchmarks externos (LongMemEval, RAGAS).
- **Confirmações manuais do Wagner/Larissa** são ground truth grátis — sinal humano que nenhuma camada genérica saberia capturar.
- **Eval semanal vira regressão automática** — mudou prompt, modelo ou threshold? Roda e compara contra última linha em `memory_metrics`.
- **A tabela é cumulativa** — em 30/90/180 dias temos curva de tendência, não foto pontual.

---

## Consequências

**Positivas:**
- Decisão de arquitetura vira **matemática, não intuição**.
- Permite comparar abordagens (Caminho A do ADR 0046 vs versão futura com tools) com a mesma régua.
- Cria barreira contra "soluções AI brilhantes" que não movem números.
- Gate do ADR 0049 (Recall@3 > 0.80 antes de evoluir camada) fica **automatizável**.

**Negativas / Trade-offs:**
- Esforço inicial de 1.5d só para construir o gabarito de 50 perguntas Larissa-style.
- Disciplina contínua para **manter o gabarito atualizado** conforme o domínio evolui (novos clientes, novos meses de faturamento).
- Sem CI gate de quality automatizado, métricas viram "informativas" — usar Sprint 7 (DeepEval) p/ fechar o loop.

---

## Pré-requisitos antes de qualquer otimização de memória

1. **Gabarito 50 perguntas** Larissa-style (queries reais sobre faturamento/metas/clientes/contradições) com resposta esperada confirmada por Wagner.
2. **Migration `copiloto_memoria_metricas`** rodada em prod.
3. **Comando `php artisan copiloto:metrics:apurar [--business=ID] [--from=DATE] [--to=DATE]`** funcional + agendado diário no scheduler.
4. **Primeira linha gravada** em `memory_metrics` com baseline 2026-04-29 (estado pós-MEM-HOT-1+2).

---

## Tasks novas (entram em TASKS.md)

| ID | Task | Dias est. | DoD |
|----|------|-----------|-----|
| **MEM-MET-1** | Migration `copiloto_memoria_metricas` + Entity | 0.5d | Tabela criada em prod, índice `mem_metr_ux` |
| **MEM-MET-2** | Comando `copiloto:metrics:apurar` (mede 8 métricas, grava 1 linha) | 1.5d | Roda local + Hostinger; baseline 2026-04-29 gravado |
| **MEM-MET-3** | Scheduler diário (Kernel.php) `daily()` | 0.25d | Cron Hostinger registra 1 linha/dia/business sem intervenção |
| **MEM-MET-4** | Página `/copiloto/admin/qualidade` lê `memory_metrics` (já é COP-007 ampliada) | 2d | Trend 30 dias das 8 métricas |
| **MEM-MET-5** | Golden set v1 (50 perguntas Larissa-style) — = COP-002 / MEM-P2-1 | 1.5d | CSV `tests/fixtures/copiloto/golden_set_v1.csv` commitado |

---

## Referências

- Pesquisa exaustiva Wagner: `files.zip/ADR-004-estrategia-teste-memoria.md` (29-abr-2026)
- ADR 0041 — Stack QA IA (este ADR concretiza com 8 métricas + tabela)
- ADR 0049 — 6 camadas com gate Recall@3 (este ADR torna o gate executável)
- ADR 0047 — Sprint memória solo (this ADR adiciona MEM-MET-1..5 ao roadmap)
