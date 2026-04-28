# ADR 0041 — Stack de QA de IA: Vizra ADK eval + Langfuse self-host + DeepEval CLI (Caminho B)

**Status:** ✅ Aceita — formaliza decisão do comparativo Capterra de 2026-04-28
**Data decisão:** 2026-04-28
**Autor:** Wagner — *"como fazer benchmark pra garantir a qualidade da IA? Estado-da-arte do ciclo de vida completo. Pesquise e crie os prints. Seja agressivo."* + aprovação implícita ao pedir registrar em ADR após apresentação dos 3 caminhos.
**Registrado por:** Claude (sessão `loving-black-f3caa3`)
**Nota:** este ADR foi originalmente numerado 0040 mas renumerado pra 0041 ao reconciliar com `main` em 2026-04-28 (PR #56 já tinha mergeado outro ADR 0040 — `0040-policy-publicacao-claude-supervisiona.md` — em paralelo).
**Companion:** [memory/comparativos/qa_eval_ia_estado_arte_capterra_2026_04_28.md](../comparativos/qa_eval_ia_estado_arte_capterra_2026_04_28.md) — comparativo Capterra de 8 plataformas, 42 features, 7 categorias.
**Depende de / referencia:**
- [ADR 0035 — Stack canônica IA](0035-stack-ai-canonica-wagner-2026-04-26.md) — usa Vizra ADK como camada B
- [ADR 0036 — Meilisearch first](0036-replanejamento-meilisearch-first.md) — alinhamento R$0/mês recorrente
- [ADR 0037 — Roadmap Tier 7-9 LongMemEval](0037-roadmap-evolucao-tier-7-plus.md) — sprint 7 (RAGAS evaluation) operacionaliza este ADR
- [ADR 0030 — Credenciais nunca em git](0030-credenciais-nunca-no-git.md) — exige PII redactor pré-LLM

---

## Contexto

**Problema concreto:** o módulo Copiloto está pronto pra sair de `COPILOTO_AI_DRY_RUN=true` (fixtures) e atender Larissa do ROTA LIVRE em produção real, mas o repo tem **zero infraestrutura de QA de IA**:

1. ❌ **Sem golden set** versionado — não dá pra detectar regressão quando trocar `laravel/ai 0.6.3 → 0.7` ou OPENAI_KEY.
2. ❌ **Sem LLM-judge online** sampling em produção — drift de modelo acumula 35% error em 6 meses sem ninguém ver (LLMOps 2025 report).
3. ❌ **Sem PII redactor** pré-LLM — CPF/CNPJ do cliente final do ROTA LIVRE vai pro OpenAI sem DPA = **violação LGPD ativa** (multa até R$50M).
4. ❌ **Sem RAG metrics** (faithfulness/context precision/recall) — quando Sprint 4-5 (MeilisearchDriver) entrar, vamos retornar contexto e não temos como medir se vem lixo.
5. ❌ **Sem CI/CD gate** — qualquer PR que mexe em prompt/agent passa sem validação automática.

**Industry benchmark (LangChain State of AI Agents 2026):** 32% das organizações citam **qualidade de IA como #1 barreira de deploy**. Times com eval framework completo: **40% iteração mais rápida**, **60% menos production incidents**, **6x maior taxa de sucesso em produção**.

**Restrição financeira (mantida do ADR 0036):** Copiloto tem **0 cliente pagante** hoje. Pagar Braintrust/LangSmith mensal (US$$ recorrente) **antes** de validar tese de retenção viola a filosofia "R$0/mês recorrente até comprovar valor" estabelecida no replanejamento Meilisearch-first.

---

## Decisão

**Adotar Caminho B — self-host pragmático.** 3 sprints sequenciais (7, 8, 9 do roadmap consolidado), R$0/mês recorrente, PHP-native quando possível, observabilidade nativa Laravel já existente (Pail+Telescope+Horizon de sessão 18).

### Stack escolhido

| Camada | Tool | Status hoje | Quando entra |
|---|---|---|---|
| **Offline / pre-deploy CI** | **DeepEval CLI** (Python via REST adapter PHP) → migra pra **Vizra ADK eval** quando Vizra suportar L13 | Pip install em VM/container CI, Vizra adia L13 (ADR 0035) | Sprint 7 |
| **Golden set generator** | **DeepEval Synthesizer** (7 evolutions) + manual SME | — | Sprint 7 |
| **Online tracing** | **Langfuse self-host** (Docker compose, PostgreSQL+ClickHouse) | Hostinger pronto pra hospedar (Cloud Startup, Meilisearch já roda) | Sprint 8 |
| **Drift detection visual** | **Langfuse session view + custom KS-test job** | Plumbing via Pail+Horizon já existe | Sprint 9 |
| **PII redactor pré-LLM** | **regex BR custom** (CPF/CNPJ/email/telefone) → upgrade Patronus Lynx só se falhar | Inexistente | Sprint 8 (LGPD-blocker) |
| **HITL annotation** | **Inertia page custom** (`/copiloto/admin/qualidade`) — não terceirizada | Approach já definido, padrão Chat Cockpit ADR 0039 | Sprint 9 |
| **Red teaming** | **Promptfoo CLI** sweep manual trimestral | Sweep só, não sprint dedicado | Sprint 10+ (after) |
| **Guardrails runtime** | **NeMo Guardrails / Patronus Lynx** | Adiados — só se PII regex falhar 3+ vezes em prod | Trigger-based |
| **Ensemble multi-judge** | Claude + GPT + Gemini majority vote | Adiado — só após 100k+ requests/mês | Sprint 12+ |

### Roadmap executável (sprints 7-9)

#### **Sprint 7 — Golden set + DeepEval CI gate offline** (5-7 dias)

**Bloqueante prévio:** validar com Larissa (1-2h, 3 cenários — handoff §🎯). Se feedback dela vier "Pivot ADR 0026", esse sprint **não acontece** — pivot pra PricingFpv/CT-e.

**Entregáveis:**
1. `Modules/Copiloto/Tests/Datasets/golden_v1.csv` — **50 perguntas-resposta-rubrica** (20 reais Larissa via Hostinger DB sessão 5+; 25 sintéticos DeepEval Synthesizer; 5 adversariais red team).
2. `Modules/Copiloto/Services/Eval/DeepEvalDriver.php` — wrapper REST chamando DeepEval Python container. Métricas: `faithfulness`, `answer_relevancy`, `context_precision`, threshold mínimo 0.75.
3. `Modules/Copiloto/Tests/Feature/CopilotoEvalTest.php` — Pest test rodando golden set, fail se score cai >5% absoluto vs `main` baseline (baseline armazenado em `Modules/Copiloto/Tests/Datasets/baseline.json`).
4. `.github/workflows/eval.yml` — GitHub Action triggera em PR que toca `Modules/Copiloto/`. Custa ~R$5-10/PR (LLM-judge calls).
5. ADR 0040 atualizado com lições aprendidas pós-execução.

**DoD:** PR de teste deliberadamente quebrado (regressão de prompt) FALHA o CI; PR clean PASSA. Wagner valida 1 ciclo.

---

#### **Sprint 8 — PII redactor + Langfuse self-host Hostinger** (5-7 dias)

**Bloqueante prévio:** OPENAI_API_KEY ativo em produção (handoff §"Configurar embedder" pendente). Sem isso, Copiloto não chama LLM real e Langfuse fica com traces vazios.

**Entregáveis:**
1. `Modules/Copiloto/Services/Privacy/PiiRedactorService.php` — regex BR:
   - CPF: `/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/`
   - CNPJ: `/\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/`
   - Email: `/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/`
   - Telefone BR: `/\b\(?\d{2}\)?\s?9?\d{4}-?\d{4}\b/`
   - Substitui por `[CPF_REDACTED]`, `[CNPJ_REDACTED]` etc. Logger logs APENAS o tipo + count, nunca o valor.
2. `OpenAiDirectDriver::chat()` chama `PiiRedactorService::redact($payload)` ANTES de outbound. Tests Pest: payload com CPF → outbound payload com `[CPF_REDACTED]`.
3. Hostinger SSH: `~/services/langfuse/docker-compose.yml` (PostgreSQL 16 + ClickHouse 24 + Langfuse 3.x). Coordenar porta com Meilisearch (7700) — Langfuse em 3000 atrás de Caddy/nginx.
4. Instrumentação OTEL no `LaravelAiSdkDriver` → Langfuse endpoint via env `LANGFUSE_HOST` + `LANGFUSE_PUBLIC_KEY` + `LANGFUSE_SECRET_KEY`.
5. Dashboard básico Langfuse: traces 100%, custo $/conversa aggregation, latência P95, cost spike alarme >2σ → email Wagner.

**DoD:** após deploy, mandar 5 mensagens de teste pelo `/copiloto`. Conferir Langfuse UI:
- 5 traces aparecem
- payload outbound NÃO contém CPF/CNPJ original
- $/trace registrado
- latência P95 abaixo de 5s

---

#### **Sprint 9 — Online judge 5% + drift + HITL admin page** (7-10 dias)

**Bloqueante prévio:** Sprint 8 fechado (Langfuse rodando + redactor ativo).

**Entregáveis:**
1. `Modules/Copiloto/Jobs/ApurarQualidadeJob` (Horizon background, sample 5% conversas/dia). Chama LLM-judge sobre rubrica (faithfulness + relevancy + safety), grava `copiloto_qualidade_scores` tabela `{conversa_id, business_id, faithfulness, answer_relevancy, judge_model, judge_reasoning, created_at}`.
2. Migração `2026_05_NN_create_copiloto_qualidade_scores_table.php` com `business_id` indexado + scope `ScopeByBusiness` (skill `multi-tenant-patterns` ativa aqui).
3. Eval-driven alerting: faithfulness rolling-7d <0.7 → trigger `\Modules\Copiloto\Notifications\QualidadeDegradadaNotification` → email Wagner + log critical.
4. `/copiloto/admin/qualidade` Inertia page (HITL):
   - Lista 20 conversas/semana (paginated)
   - Wagner/Larissa anotam: ⭐⭐⭐⭐⭐ + comentário
   - Anotações viram `copiloto_anotacoes_humanas` table
   - Botão "promover pra golden set v2" → exporta CSV pra `Modules/Copiloto/Tests/Datasets/golden_v2.csv` candidate
   - Padrão Chat Cockpit ADR 0039 + AppShellV2 (quando portado)
5. Drift dashboard simples no Langfuse: distribution embeddings input/output, weekly KS-test em job Horizon, flag anomaly → page Wagner.
6. Métricas integradas no `/copiloto/admin/custos` (já em andamento — branch `claude/nervous-burnell-f497b8` US-COPI-070).

**DoD:** 1 semana operação real, dashboard mostra trend visual de faithfulness, Wagner valida que o que Larissa marcou como "ruim" tem score baixo do LLM-judge (κ ≥ 0.6 mínimo).

---

## Métricas de fé (90 dias — 2026-07-27)

| Métrica | Alvo | Trigger se falhar |
|---|---|---|
| Golden set v1 commitado | ✅ 50 exemplos validados Larissa | Sprint 7 não saiu — voltar e desbloquear |
| CI/CD eval gate ativo | ✅ Bloqueia PR com regression >5% | Falha de gate = revert imediato |
| Larissa convers >30× real | ✅ não-fixture, log Langfuse | <30 = problema de adoption, não eval — pivot ADR 0026 |
| Faithfulness rolling-7d | ≥ 0.85 | <0.85 = golden set raso OU prompt agent ruim — re-train |
| PII leak detectado | **0** (zero absoluto) | ≥1 = escala Patronus Lynx imediato (Caminho B+) |
| Dashboard `/copiloto/admin/qualidade` | Trend visual rolando | Sem visual = sprint 9 incompleto |

**Se 5/6 metas batem** → tese B confirma. Próximo: red team formal sprint 10 + ADR 0041 (cost optimization Opus→Sonnet).
**Se ≤3/6** → re-avaliar caminho. Considerar Caminho A (Braintrust trial 30d, MRR-gated) ou Caminho C (defer + escope mínimo).

---

## Alternativas consideradas e rejeitadas

### A — Braintrust / LangSmith full SaaS

❌ **Rejeitado por:**
- US$$ recorrente em ERP que cobra R$497/cliente — math não fecha pré-validação
- Lock-in cloud + per-seat pricing
- Viola filosofia ADR 0036 (R$0/mês recorrente)
- DPA + LGPD complicado em vendor americano pra PII
- **Quando reconsiderar:** MRR >R$30k/mês OU cliente enterprise (>R$10k/mês ticket) pedindo explicitamente.

### C — Defer eval ("só vamos fazer quando primeiro incidente real")

❌ **Veto técnico/regulatório por:**
- GAP 3 (PII redactor) é regulatório-CRÍTICO HOJE — não pode esperar incidente
- Industry data: 6 meses sem eval = 35% error rate jump
- Larissa vira cobaia — destrói confiança permanente

### D — Esperar Vizra L13 antes de começar

❌ **Rejeitado por:**
- Vizra L13 sem ETA upstream (issue não aberta no GitHub vizra-ai/vizra-adk)
- Aceitar 6+ meses sem QA é inaceitável dado GAP 3
- Caminho B usa **DeepEval Python via REST adapter PHP** como ponte; quando Vizra L13 vier, **migra harness, não constrói do zero**

### E — Multi-judge ensemble (Claude + GPT + Gemini)

🟡 **Adiado** (não rejeitado) por:
- 3-5x custo, ROI só após 100k+ requests/mês
- Single judge (Claude Sonnet) já dá 80% agreement com humano (suficiente pra começar)
- Re-avaliar em ADR follow-up sprint 12+

### F — NeMo Guardrails / Patronus runtime

🟡 **Adiado** (não rejeitado) por:
- Adicionam latência sem ROI até PII regex falhar 3+ vezes em prod
- Trigger-based: ativar se redator BR vazar
- Re-avaliar em ADR follow-up se trigger ativar

---

## Consequências

### Positivas

1. **R$0/mês recorrente** mantido até primeira validação comercial — alinha ADR 0036.
2. **GAP LGPD fechado em sprint 8** (PII redactor) — destravalegalmente o `COPILOTO_AI_DRY_RUN=false`.
3. **Caminho de migração suave** pra Vizra ADK eval quando L13 vier (REST adapter → Vizra harness).
4. **Observabilidade real** em produção (Langfuse) — destrava demo pra prospects mostrando "veja, monitoramos".
5. **CI/CD gate** automatiza qualidade — Wagner não precisa lembrar de testar manualmente cada PR.
6. **HITL annotation interno** — Larissa vira parte do ciclo de melhoria sem precisar de plataforma externa.

### Negativas

1. **3 sprints adicionados** ao roadmap (era 7, vira 9) — atrasa Sprint 4-5 (MeilisearchDriver).
2. **DeepEval Python** adiciona dep estrangeira PHP (REST adapter) — temporário até Vizra L13.
3. **Custo LLM-judge** em sampling 5% online: estimado R$200-500/mês quando Copiloto tiver 1k conversas/dia. Não-zero, mas << SaaS players.
4. **Langfuse self-host operacional** vira responsabilidade nossa — backup, upgrade, segurança. Mitigação: Hostinger Cloud Startup já tem snapshot automático.
5. **Risco de "eval awareness"** — Anthropic descobriu (2026-04) que Claude Opus 4.6 detecta avaliação e muda comportamento. Mitigação: golden set indistinguível de tráfego real, nunca prefixar "evaluate this".
6. **Risco de fadiga da Larissa** com HITL semanal — reduzir pra 10-15/semana se necessário, aceitar agreement rate menor.

### Neutras

- ADR 0037 (roadmap Tier 7-9 LongMemEval) **continua válido**, mas agora tem stack concreta pra implementar.
- Comparativos `qa_eval_ia_estado_arte_capterra_2026_04_28.md` continua sendo a fonte da verdade pra detalhes.
- Pré-existentes (Pail/Telescope/Horizon/Meilisearch) continuam, são complementares.

---

## Reversibilidade

**Custo de reverter** (voltar pra "sem QA de IA"):
- Sprint 7: deletar pasta `Tests/Datasets/` + GitHub Action eval.yml. Trivial.
- Sprint 8: PII redactor é reversível (remover chamada de `OpenAiDirectDriver`). Langfuse self-host: parar Docker, dados ficam pra exportar.
- Sprint 9: jobs Horizon param, tabelas `copiloto_qualidade_scores` e `copiloto_anotacoes_humanas` ficam mas vazias. Inertia page é só remover rota.

**Reversão completa em ~1 dia se preciso pivotar pra Caminho A.** Dados de Langfuse são exportáveis pra Braintrust/LangSmith via OpenTelemetry standard.

---

## Histórico

- **2026-04-28** — Wagner pediu pesquisa estado-da-arte agressiva. Claude pesquisou (15 buscas paralelas), produziu comparativo Capterra (564 linhas, 8 plataformas, 42 features), recomendou Caminho B. Wagner aprovou e pediu ADR. Este ADR formaliza.

---

## Como verificar este ADR ainda é válido

A cada 90 dias, conferir:
- [ ] Métrica de fé bateu? (ver tabela acima)
- [ ] Vizra ADK liberou L13? Se sim, migrar `DeepEvalDriver` → `VizraEvalDriver` via PR dedicado
- [ ] Custo LLM-judge ainda < R$500/mês? Se >R$2k/mês, considerar self-hosted Claude/Llama judge
- [ ] PII regex BR ainda funciona? Adicionar casos novos vistos em prod (placas Mercosul, RG SP novo, etc)
- [ ] Larissa ainda conversando >30/mês? Se não, problema é adoption, não QA
- [ ] LangChain/Anthropic publicaram research que muda paradigma? (re-revisar comparativo Capterra)
