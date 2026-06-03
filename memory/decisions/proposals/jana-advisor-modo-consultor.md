# Jana "Modo Consultor" (Advisor) — Metade A: clarify reativo — 2026-06-02

> **Status:** ✅ PROMOVIDO → **[ADR 0245](../0245-jana-advisor-modo-consultor-clarify.md)** (aceito por [W] em 2026-06-02, pós-merge PR #2134). Este proposal vira registro histórico da origem §10.4; a decisão canônica vive na ADR 0245.
> **Tipo:** evolução de produto (Modules/Jana — chat/raciocínio) · **Tier 0** (produto + custo) → PR aberto, **espera [W]**.
> **Autor:** [CL] Claude Code · **Origem:** peer-review (L-17) do pedido [CC] `PROMPT_PARA_CODE_JANA-ADVISOR-MODE.md` (sessão 2026-06-02). Insight [W]: *"as melhores respostas vêm quando eu pergunto que pergunta eu deveria fazer, porque nem eu sei o que é melhor."*
> **Natureza:** peer-review, não ordem — [CL] avaliou se procede e onde mora melhor.

---

## 1. Veredito do peer-review: **procede** (andaime de raciocínio, não troca de modelo)

O achado bate com o estado-da-arte 2025 e é checável contra `origin/main`:

- **Active Task Disambiguation** (ICLR 2025 Spotlight): subir qualidade não é dar respostas melhores — é **fazer perguntas melhores** (a de maior ganho de informação).
- **INTENT-SIM** (NAACL 2025): decoupla **ambiguidade-de-intenção** (→ perguntar) de **falta-de-dado** (→ buscar). Confundir as duas é o erro nº1 dos LLMs (perguntam quando não sabem; chutam quando é ambíguo).
- A Jana hoje **chuta** no ambíguo e às vezes **pergunta** no que era só falta de dado. Esta capacidade é o **andaime** (scaffold) que conserta o pior hábito — sobe o raciocínio pela arquitetura, **não** pelo provider.

Atende os 4 testes da meta-skill: substitui o [W] *saber qual pergunta fazer* · repetitivo (todo dia, toda persona) · ROI = decisão melhor + menos chute · acelera ERP autônomo (a IA pauta, o humano decide). Começa pela **Metade A** (mais barata), como [W] sequenciou.

## 2. Passo 0 contra `origin/main` fresco (`2e9f5881e`) — onde mora, sem duplicar

| Verifiquei | Achado | Decisão |
|---|---|---|
| `LaravelAiSdkDriver::responderChat[Stream]` | Pipeline real: cache → recall(MemoriaContrato) → snapshot(ContextoNegocio) → `ChatCopilotoAgent` | **Estendi**: guard `talvezClarificar()` ANTES do recall/LLM. `ContextoNegocio` **reusado** (1 snapshot serve cascata e chat). |
| `BriefDiarioChatTrigger` | Já pré-empta o chat por intent (regex-first) | Precedente de interceptação — a cascata pluga na **mesma forma**. |
| 4 Agents (ChatCopiloto/BriefDiario/Sugestoes/Briefing) | Existem; `SugestoesMetasAgent` já usa `HasStructuredOutput` | **Estendi** com 5º agente (`ClarificadorAgent`) — mesmo padrão structured output. Os 4 ficam intactos. |
| Brief diário (ADR 0091) | É o gancho da **Metade B** (próxima-melhor-pergunta) | Não toquei nesta fase — Metade B estende o brief, spec à parte. |
| Convenção de flag (`contextual_retrieval`, `peso_real`) | Tudo que toca o coração do chat nasce **default-OFF**, [W] liga em homolog | Segui: `JANA_CLARIFY_ENABLED=false`. Com OFF o pipeline é **byte-idêntico** ao legado. |

## 3. O que entra neste PR (tudo aditivo, default-OFF)

1. **`Modules/Jana/Ai/Agents/ClarificadorAgent.php`** — disambiguador `HasStructuredOutput`. Decide `claro | falta_dado | ambiguo` (+ confiança, intenções) e, se ambíguo, devolve a **pergunta de maior ganho de informação**. Roteamento de modelo seletivo via `provider()`/`model()` (config) — **difícil → frontier** (default `gpt-4o`, vs `gpt-4o-mini` do chat), mas só dispara no cinza. Honestidade no prompt: pode classificar `claro` e deixar pergunta vazia; **não inventa** ambiguidade.
2. **`Modules/Jana/Services/Ai/Clarify/ClarifyCascadeService.php`** — a cascata por latência: **1a heurística local (zero LLM)** resolve ~80% direto; **1b disambiguador frontier** só no ~20% cinza. Fail-open (qualquer erro → responde), anti-loop (não pergunta 2× seguidas, marcador TTL), e **medição** (`clarify_event` no log `copiloto-ai`). Histórico/contexto PII-redigidos (defense-in-depth).
3. **`Modules/Jana/Support/ClarifyResult.php`** — DTO imutável do veredito.
4. **`LaravelAiSdkDriver`** — guard `talvezClarificar()` em blocking + stream (sai sem gravar cache nem extrair fatos: não houve resposta real).
5. **`Modules/Jana/Config/config.php`** — bloco `copiloto.clarify.*` (flag, model/provider, min_confiança, limites do cinza, anti-loop TTL).
6. **RUNBOOK** `memory/requisitos/Jana/RUNBOOK-jana-advisor-clarify.md` + **14 testes Pest** (verdes).

## 4. Como medir (senão é fé, não engenharia)

Log `copiloto-ai` → `clarify_event`: **gray-hit rate** (custo_llm/total), **taxa de clarify**, **false-clarify** (clarify só no cinza?). A métrica **pergunta→ação** (sinal de valor real) precisa de hook no frontend — pendência honesta listada no RUNBOOK. Ratchet futuro na família `jana:health-check`/`design:review`.

## 5. Tier 0 respeitado

- **Não cunhei nº de ADR** (soberania [W], 0238). Proposal slug-only.
- **Não mergeei** (publication-policy). PR espera [W].
- Default-OFF → custo zero até [W] ligar e escolher o modelo frontier.

## 6. Decisão aberta pra [W]

- [ ] Aprovar a Metade A (vira canon ao mergear) ou ajustar.
- [ ] Numerar ADR se elevar proposal → decisão.
- [ ] Ligar em homolog p/ medir `clarify_event` antes de prod.
- [ ] Dar sinal verde pra **Metade B** (próxima-melhor-pergunta proativa por persona, estendendo o brief diário).
