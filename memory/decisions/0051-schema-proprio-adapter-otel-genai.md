# ADR 0051 — Schema próprio + adapter pattern + emissão OpenTelemetry GenAI

**Status:** Aceito
**Data:** 2026-04-29
**Decidido por:** Wagner (após pesquisa de tendências 2026-04-29)
**Contexto:** Decisão estratégica sobre como evoluir o schema de persistência/eval do Copiloto à medida que `laravel/ai` amadurece.

---

## Contexto

`laravel/ai` 1.0 foi lançado em **17-mar-2026** com Laravel 13. Verificações em 29-abr-2026:

- **Sem eval framework nativo** (zero issues abertas no repo `github.com/laravel/ai` mencionando recall/precision/MRR/eval).
- **Sem multi-tenancy nativa** (só conhece `user_id`; nem RFC nem PR sinalizando `tenant_id`/`business_id`).
- **Sem retention/audit** built-in pra LGPD.

A questão não é "schema próprio vs nativo". É: **qual estratégia minimiza dívida técnica e maximiza opcionalidade futura?**

Pesquisa de tendências (29-abr-2026, sumário em `memory/sessions/2026-04-29-pesquisa-tendencias-laravel-ai.md`) confirma que o padrão emergente em SDKs de IA modernos (LangChain, CrewAI, Vercel AI SDK):

1. **Schema próprio no banco** — controle total, multi-tenant, retention, queries SQL pra trend.
2. **Emissão OpenTelemetry GenAI** (`gen_ai.*` semantic conventions) — interop com Datadog, Langfuse, Arize sem reescrever schema.
3. **Adapter sobre interfaces do framework** quando elas existem (ex: `Laravel\Ai\Contracts\ConversationStore`) — fica "compliant" sem ser "nativo".

Comunidade Laravel multi-tenant (Spatie, Stancl) faz o mesmo: adapter pattern sobre o SDK, não fork.

---

## Decisão

Adotar **estratégia híbrida pragmática** com 4 pilares:

### Pilar 1 — Schema próprio NO BANCO

Manter como verdade canônica:
- `copiloto_conversas` + `copiloto_mensagens` (já em prod)
- `copiloto_memoria_facts` (já em prod)
- `copiloto_memoria_metricas` (criada em 29-abr — ADR 0050)

Justificativa: `business_id` é requisito duro (UltimatePOS multi-tenant) e LGPD soft-delete são exigências de domínio que `laravel/ai` não cobre nem sinaliza cobrir.

### Pilar 2 — Adapter sobre `ConversationStore` contract

Quando precisar trocar storage subjacente (futuro), implementar `Laravel\Ai\Contracts\ConversationStore` em uma classe `CopilotoConversationStoreAdapter` que delega para nossas tabelas. Custo de troca = 1 dia, sem migrar dados.

Não fazer agora — só quando precisar (YAGNI).

### Pilar 3 — Métricas RAGAS-aligned

`copiloto_memoria_metricas` tem **8 colunas obrigatórias (ADR 0050)** + **3 colunas RAGAS-aligned**:

| Coluna | Origem | Significado |
|--------|--------|-------------|
| `faithfulness` | RAGAS | Resposta vs contexto (sem alucinação); meta > 0.85 |
| `answer_relevancy` | RAGAS | Resposta vs pergunta (relevância semântica); meta > 0.80 |
| `context_precision` | RAGAS | Chunks recuperados ranqueados por relevância; meta > 0.70 |

**Por que isso importa:** quando plugarmos Langfuse/RAGAS no futuro, as métricas que já capturamos são **reconhecidas nativamente** pela ferramenta — zero rename de coluna, zero migration de dados históricos.

### Pilar 4 — Emissão OpenTelemetry GenAI (task MEM-OTEL-1)

Adicionar emissão de spans/eventos com atributos `gen_ai.*` nas operações do `LaravelAiSdkDriver::responderChat`:

```
gen_ai.system          = "openai"
gen_ai.request.model   = "gpt-4o-mini"
gen_ai.usage.input_tokens  = N
gen_ai.usage.output_tokens = M
gen_ai.business_id     = 4               # extension custom (LGPD audit)
gen_ai.conversation.id = "{$conv->id}"
```

Implementação inicial: log channel `otel-gen-ai` (rotacionado diário, 30d). Quando ligarmos OTel SDK PHP de verdade, o mapping é direto.

**Beneficio futuro**: Datadog suporta `gen_ai.*` nativamente. Langfuse e Arize mapeiam direto. Plugar em qualquer dashboard externo vira 1 dia de trabalho.

---

## Justificativa

- **Schema próprio é o padrão 2026 em SDKs de IA** — não estamos sendo anti-padrão.
- **OpenTelemetry GenAI é o "norte" emergente** (status experimental mar/2026, Datadog já implementa, vai virar default multi-vendor).
- **Adapter sobre `ConversationStore` blinda contra mudanças** sem custo prematuro.
- **Métricas RAGAS-aligned destravam ferramentas externas** sem coupling forte.
- **Custo de migrar pra schema nativo no futuro permanece baixo** — nada precisa ser reescrito hoje.

---

## Consequências

**Positivas:**
- Zero dívida técnica adicional.
- `gen_ai.*` traces ficam disponíveis pra qualquer ferramenta de eval/observability futura sem retrabalho.
- Dashboard `/copiloto/admin/qualidade` consome `copiloto_memoria_metricas` direto (queries SQL nativas, sem adapter).
- Multi-tenant respeitado em 100% das tabelas.

**Negativas / Trade-offs:**
- 3 colunas RAGAS extras na tabela (~12 bytes/linha — irrelevante).
- Emissão OTel adiciona ~50-100 linhas no `LaravelAiSdkDriver` (task MEM-OTEL-1, 0.5d).
- Sem dashboard nativo de métrica até MEM-MET-4 (=COP-007 ampliada) ficar pronto.

---

## Triggers para reavaliar (revisão trimestral)

Disparar nova ADR superseder esta se ≥1 trigger ativar:

1. **`laravel/ai` lançar feature `tenant_id` / multi-tenancy nativa** com migration tool.
2. **`laravel/ai` lançar eval framework** (issue/PR/RFC) que cubra ≥6 das nossas 8 métricas obrigatórias.
3. **OpenTelemetry GenAI sair de experimental** (status "stable") com SDK PHP first-class.
4. **Custo de manutenção do schema próprio** ultrapassar 2 sprints/ano.

Sem triggers, **estratégia atual continua por tempo indeterminado**.

---

## Tasks que dependem deste ADR

- ✅ MEM-MET-1 — migration `copiloto_memoria_metricas` com 8 + 3 colunas (29-abr)
- ⏳ **MEM-OTEL-1** — emissão `gen_ai.*` no LaravelAiSdkDriver (Pilar 4) — 0.5d
- ⏳ MEM-MET-2 — comando `copiloto:metrics:apurar` apurar 8 + 3 métricas
- ⏳ MEM-MET-4 (=COP-007) — page `/copiloto/admin/qualidade` consome schema próprio

---

## Referências

- Pesquisa tendências 2026-04-29 (sumário no commit referenciado em CURRENT.md)
- ADR 0050 — 8 métricas + tabela memory_metrics
- ADR 0049 — 6 camadas + gate Recall@3>0.80
- `laravel/ai` repo: github.com/laravel/ai
- OpenTelemetry GenAI semantic conventions: opentelemetry.io/docs/specs/semconv/gen-ai/
- RAGAS: docs.ragas.io
- DeepEval: docs.confident-ai.com
- Datadog GenAI semconv support: datadoghq.com/blog/llm-otel-semantic-convention/
