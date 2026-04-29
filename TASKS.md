# TASKS.md — Backlog completo do projeto

> **O que é:** lista única e canônica de tudo que está pendente, em andamento ou recém-fechado, **organizada por módulo**.
>
> **Não confundir com:**
> - [`CURRENT.md`](CURRENT.md) — Cycle ativo (goal + Active + On-deck).
> - [`memory/08-handoff.md`](memory/08-handoff.md) — contexto narrativo da última sessão.
> - [`memory/sessions/`](memory/sessions/) — histórico cronológico.
> - [`memory/cycles/`](memory/cycles/) — Cycles fechados com retro.
> - [`memory/requisitos/{Modulo}/SPEC.md`](memory/requisitos/) — especificação detalhada.
>
> **Quando atualizar:** daily async (Wagner atualiza status antes das 09h).

---

## Legenda

**Status:** ⏳ TODO · 🔄 Em andamento · ⛔ Bloqueado · 🟡 Adiado · ✅ Done · ❌ Cancelado
**Prioridade:** 🔴 P0 (Cycle atual) · 🟠 P1 (Cycle próximo) · 🟡 P2 (próximos 3 cycles) · ⚪ P3 (algum dia)
**Dono:** [W] Wagner · [C] Claude (IA pareada) · [Cu] Cursor (IA paralela)
**Cliente externo:** [Larissa] ROTA LIVRE · [Eliana(WR2)] PontoWr2

> ⚡ **Modo solo ativo (2026-04-28 — ADR 0047):** todo o backlog consolidado em [W]. Redistribuição quando time retornar no Cycle 02.

---

## ⚡ Ativos no Cycle 01 (29-abr → 12-mai)

> Sincronizado com [`CURRENT.md`](CURRENT.md).

| ID | Status | Pessoa | Task | Prazo | Dias est. |
|---|---|---|---|---|---|
| A1 | ⏳ | W | **MEM-MET-3: Scheduler diário** — `Console/Kernel.php->daily()` chama `copiloto:metrics:apurar --all` | qui 30-abr | 0.25 |
| A2 | ⏳ | W | **A4: Validar Larissa** — "qual meu faturamento de março?" → R$ 38.215,07 | sex 02-mai | 0.5 |

**On-deck Cycle 01 (ordem por impacto×esforço):**

| ID | Pessoa | Task | Dias est. | Bloqueado por |
|---|---|---|---|---|
| O1 | W | **COP-002 = MEM-MET-5: Golden set v1** (50 perguntas Larissa-style) — destrava 6 colunas RAGAS (gate Recall@3>0.80 do ADR 0049) | 1.5 | A2 (Larissa) |
| O2 | W | **MEM-MET-4 = COP-007 ampliada: Page `/copiloto/admin/qualidade`** — trend 30d das 8 métricas + HITL anotação | 2 | O1 |
| O3 | W | **MEM-S8-1: SemanticCacheMiddleware** (-68.8% tokens LLM) | 1.5 | — |
| O4 | W | **MEM-S8-2: ConversationSummarizer** (>15 turnos → resumo) | 1.5 | — |
| O5 | W | **MEM-S8-3: ProfileDistiller** (job diário perfil negócio <300 tokens) | 1 | O4 |
| O6 | W | **COP-P22 = MEM-P2-2: RRF tuning** A/B `semanticRatio` 0.3 vs 0.7 | 0.5 | O1 |

**Histórico do Cycle 01 — sprint memória 29-abr (todas ✅):**

| ID | Status | Task | Commit |
|----|--------|------|--------|
| ~~A1~~ | ✅ | ~~MEM-HOT-1: Hybrid embedder MeilisearchDriver~~ | `c631042c` (recall 0→190) |
| ~~A2~~ | ✅ | ~~MEM-HOT-2: ContextoNegocio → ChatCopilotoAgent~~ | `2be9930c` (164 tokens prod) |
| ~~MEM-MET-1~~ | ✅ | ~~Migration `copiloto_memoria_metricas` + Entity~~ | `21644f4e` (14 colunas) |
| ~~MEM-OTEL-1~~ | ✅ | ~~Emissão `gen_ai.*` OpenTelemetry GenAI~~ | `5acf27de` (12 atributos) |
| ~~MEM-MET-2~~ | ✅ | ~~Comando `copiloto:metrics:apurar` + baseline em prod~~ | `6d2dc7eb`+`6aa9b524` |

---

## 🚨 Bloqueante crítico

| # | Status | Pri | Dono | Task | Notas |
|---|---|---|---|---|---|
| B1 | ✅ | — | W | ~~MEM-HOT-1 hybrid fix~~ | resolvido 29-abr `c631042c` — prod log: `memoria_recall_chars: 190` (de 0) |
| B2 | ✅ | — | W | ~~MEM-HOT-2 contexto rico~~ | resolvido 29-abr `2be9930c` — prompt biz=4 com 4 meses faturamento + 5993 clientes em 164 tokens |
| B3 | ⏳ | 🔴 P0 | W | **A4 Validar Larissa** — única dependência humana pendente do Goal #1 | aguarda chat real Larissa |

---

## 🤖 Módulo Copiloto

> Stack canônica: ADRs 0035 / 0036 / 0037 / 0046 / 0047.

### P0 — Hotfix (Cycle 01 esta semana)

| # | Status | Pri | Dono | Task | Dias est. | DoD |
|---|---|---|---|---|---|---|
| COP-H01 | ✅ | — | W | ~~MEM-HOT-1: MeilisearchDriver hybrid~~ — Scout callback com `hybrid:{embedder,semanticRatio}` + filter `business_id/user_id` | 0.5 | ✅ prod 29-abr: 2 hits + log `memoria_recall_chars: 190` (commit `c631042c`) |
| COP-H02 | ✅ | — | W | ~~MEM-HOT-2: ChatCopilotoAgent ContextoNegocio~~ — `instructions()` injeta empresa/faturamento/clientes/metas | 1 | ✅ prod 29-abr (`2be9930c`): biz=4 ROTA LIVRE em 164 tokens; aguarda validação real Larissa |
| COP-001 | ✅ | — | W | US-COPI-070 Dashboard custo IA — merged | — | Mergeado em Cycle 01 |

### P1 — Sprint 8 (Cycle 01 semana 2)

| # | Status | Pri | Dono | Task | Dias est. | DoD |
|---|---|---|---|---|---|---|
| COP-S81 | ⏳ | 🔴 P0 | W | **MEM-S8-1: SemanticCacheMiddleware** — embedding query → Redis lookup → cache hit evita LLM call | 1.5 | Cache hit rate >30% após 10 convs similares |
| COP-S82 | ⏳ | 🔴 P0 | W | **MEM-S8-2: ConversationSummarizer** — >15 turnos: comprime msgs antigas em resumo <200 tokens | 1.5 | Conv 20 turnos usa <2.000 tokens de contexto total |
| COP-S83 | ⏳ | 🔴 P0 | W | **MEM-S8-3: ProfileDistiller** — job diário por business_id extrai perfil <300 tokens no Redis | 1 | Profile aparece no system prompt de toda nova conversa |
| COP-003 | ⏳ | 🟠 P1 | W | PII redactor BR (regex CPF/CNPJ/email/tel em LaravelAiSdkDriver) — LGPD-blocker | 2 | Test Pest: payload outbound = `[REDACTED]` |

### P1 — Sprint 7 RAGAS baseline + 8 métricas obrigatórias (ADR 0050)

| # | Status | Pri | Dono | Task | Dias est. | DoD |
|---|---|---|---|---|---|---|
| MEM-MET-1 | ✅ | — | W | ~~Migration `copiloto_memoria_metricas` + Entity (ADR 0050+0051) — 8 obrigatórias + 3 RAGAS~~ | 0.5 | ✅ prod 29-abr (`21644f4e`): tabela com 14 colunas, 7 testes passing, schema validado |
| MEM-MET-2 | ✅ | — | W | ~~Comando `copiloto:metrics:apurar`~~ — apura 8 obrigatórias + contadores; RAGAS NULL até golden set (MEM-P2-1) | 1.5 | ✅ prod 29-abr (`6d2dc7eb`): 3 linhas baseline (plataforma + biz=1 + biz=4); 9 testes passing |
| MEM-MET-3 | ⏳ | 🔴 P0 | W | **Scheduler diário** `Console/Kernel.php->daily()` chama `copiloto:metrics:apurar --all` (= A1 ATIVA) | 0.25 | Cron Hostinger registra 1 linha/dia/business sem intervenção; baseline 30-abr deve aparecer auto |
| MEM-OTEL-1 | ✅ | — | W | ~~Emissão OpenTelemetry GenAI~~ — log channel `otel-gen-ai` com atributos `gen_ai.*` (ADR 0051) | 0.5 | ✅ prod 29-abr (`5acf27de`): smoke gera linha JSON OTel-compliant com 12 atributos; 5 testes passing |
| COP-002 | ⏳ | 🟠 P1 | W | **MEM-P2-1: Golden set v1 (50 perguntas Larissa-style)** — pré-requisito do MEM-MET-2 | 1.5 | CSV commitado em `tests/fixtures/copiloto/golden_set_v1.csv` |
| COP-P22 | ⏳ | 🟠 P1 | W | **MEM-P2-2: RRF tuning** — A/B `semanticRatio` 0.3 vs 0.7 no Meilisearch | 0.5 | Vencedor documentado + ADR 0036 atualizado |
| COP-012 | ⏳ | 🟠 P1 | W | Sprint 7 ADR 0041 — DeepEval CI gate (`.github/workflows/eval.yml`) | 2 | PR com regression >5% em qualquer das 8 métricas FAILS CI |

### P1 — Observabilidade

| # | Status | Pri | Dono | Task | Dias est. | DoD |
|---|---|---|---|---|---|---|
| COP-005 | ⏳ | 🟠 P1 | W | Langfuse self-host CT 100 + OTEL no LaravelAiSdkDriver | 3 | 5 traces aparecem após smoke |
| COP-006 | ⏳ | 🟠 P1 | W | ApurarQualidadeJob + tabela `copiloto_qualidade_scores` | 2 | Job Horizon, 5% sampling |
| COP-007 | ⏳ | 🟠 P1 | W | Page `/copiloto/admin/qualidade` HITL — skeleton + lógica anotação + **trend 30d das 8 métricas (MEM-MET-4)** | 4 | Lista 20 conv/sem + anotação Larissa + gráfico das métricas (ADR 0050) |

### P2 (Cycle 03+)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| COP-009 | ⏳ | 🟡 P2 | W | ApurarMetasAtivasJob (scheduler diário) | 1 |
| COP-010 | ⏳ | 🟡 P2 | W | SuggestionEngine parsear JSON → Sugestao rows | 2 |
| COP-011 | ⏳ | 🟡 P2 | W | Tela LGPD `/copiloto/memoria` (listar + esquecer + opt-out) | 3 |
| COP-013 | ⏳ | 🟡 P2 | W | Drivers `php` e `http` (além de SqlDriver) | 3 |
| COP-014 | ⏳ | 🟡 P2 | W | Wizard 3 passos `/copiloto/metas/create` | 3 |
| ~~COP-015~~ | ❌ | — | — | ~~Vizra ADK install + migrar conversas~~ | **CANCELADA 29-abr (ADR 0048)** — Vizra quebrou no L13, oimpresso fica em `laravel/ai` |
| COP-016 | ✅ | — | W | MeilisearchDriver implementação (código OK; hotfix COP-H01 corrige runtime) | — |
| COP-017 | ⏳ | 🟡 P2 | W | Bridge memória↔chat (top-K + extrai async) aprimoramento | 3 |
| COP-020 | 🟡 | 🟡 P2 | W | Testes superadmin (`copiloto.superadmin`) | 1 |

### Adiado / condicional

| # | Status | Pri | Dono | Task | Trigger |
|---|---|---|---|---|---|
| COP-018 | 🟡 | ⚪ P3 | W | Mem0RestDriver upgrade managed | ADR 0036 sprint 11+ — só se trigger ativar |
| COP-019 | 🟡 | ⚪ P3 | W | Multi-judge ensemble (Claude+GPT+Gemini) | ADR 0041 — só após 100k+ req/mês |
| COP-021 | 🟡 | ⚪ P3 | W | NeMo / Patronus runtime guardrails | ADR 0041 — só se PII regex falhar 3+ vezes |

---

## 💰 Módulo Financeiro

### P1 (Cycle 02)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| FIN-001 | ⏳ | 🟠 P1 | W | Backfill purchases legadas em `due` | 1 |
| FIN-002 | ⏳ | 🟠 P1 | W | Rodar `ContaBancariaIndexTest` + `RelatoriosTest` em MySQL local | 0.5 |
| FIN-003 | ⏳ | 🟠 P1 | W | Audit "cache/estado preservado entre navegações" Financeiro | 2 |
| FIN-004 | ⏳ | 🟠 P1 | W | Atualizar cobrança ROTA LIVRE | 1 |

### P2

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| FIN-005 | ⏳ | 🟡 P2 | W | Tela unificada US-FIN-013 (4 estados juntos) | 5 |
| FIN-006 | ⏳ | 🟡 P2 | W | Take rate de boleto (CNAB-only mode) | 5 |
| FIN-007 | ⏳ | 🟡 P2 | W | Conciliação Pix automática | 5 |
| FIN-008 | ⏳ | 🟡 P2 | W | DRE gerencial revisão UX como usuária real | 1 |

---

## ⏰ Módulo PontoWr2

> Cliente: WR2 Sistemas / **Eliana(WR2)** [externa]. Estado dorminhoco desde upgrade 6.7.

### P1 (Cycle 02)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| PNT-001 | ⏳ | 🟠 P1 | W | Tier A — Dashboard vivo (3 personas, 8 capacidades) | 5 |
| PNT-002 | ⏳ | 🟠 P1 | W | Validar Eliana(WR2) — o que mudou em 6m sem PontoWr2 | 0.5 (call) |

### P2

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| PNT-003 | ⏳ | 🟡 P2 | W | Comparativo `pontowr2_vs_concorrentes_capterra_*.md` | 2 |
| PNT-004 | ⏳ | 🟡 P2 | W | 10 moves Tier A/B/C priorizados em SPEC | 2 |
| PNT-005 | ⏳ | 🟡 P2 | W | ADR formal `requisitos/PontoWr2/adr/ui/0002` | 1 |

---

## 🗄️ Módulo MemCofre (ex-DocVault)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| MEM-001 | ⏳ | 🟠 P1 | W | UI de upload de evidência | 3 |
| MEM-002 | ⏳ | 🟡 P2 | W | Página listagem `Doc*` entidades | 2 |
| MEM-003 | ✅ | — | W | Links `/docs` legacy + dark theme shadcn | feito 2026-04-27 |

---

## 🌐 Módulo Cms (landing oimpresso.com)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| CMS-001 | 🟡 | 🟡 P2 | W | Hidratação Site/Home com `cms_pages` (re-tentar com fallback) | 2 |
| CMS-002 | ⏳ | 🟡 P2 | W | PR2+ redesign Inertia/React (blog + contact) | 4 |
| CMS-003 | ⏳ | ⚪ P3 | W | Decidir migrar landing inteira pro Inertia | 0.5 (decisão) |

---

## 🏢 Módulo Officeimpresso (superadmin-only)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| OFF-001 | ✅ | — | W | Restauração 3.7→6.7 + tela `licenca_log` v3 | feito |
| OFF-002 | ⏳ | ⚪ P3 | W | Auditoria untracked `Modules/Connector` no servidor | 1 |

---

## 📄 Módulo NfeBrasil

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| NFE-001 | 🟡 | 🟡 P2 | W | NFe Brasil — implementar do SPEC | 8 |
| NFE-002 | ⏳ | 🟡 P2 | W | CT-e + MDF-e (ADR 0026 diferencial CV) | 8 |

---

## 🔁 Módulo RecurringBilling

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| REC-001 | 🟡 | 🟡 P2 | W | Implementação do SPEC | 5 |

---

## 🌱 Módulo Grow

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| GRO-001 | ⏳ | 🟠 P1 | W | Reunião de elicitação de escopo Grow | 0.5 |
| GRO-002 | ⏳ | 🟡 P2 | W | SPEC `memory/requisitos/Grow/SPEC.md` | 2 (deps GRO-001) |

---

## 🎨 Módulo CockpitBootstrap (Sidebar/AppShell)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| UI-001 | ⏳ | 🟠 P1 | W | Portar `AppShellV2.tsx` (Fase 1 ADR 0039) | 3 |
| UI-002 | ⏳ | 🟠 P1 | W | Componentes shared `LinkedApps/*` | 4 |
| UI-003 | ⏳ | 🟡 P2 | W | TaskProvider + `Pages/Tarefas/Index.tsx` | 3 |
| UI-004 | ⏳ | 🟡 P2 | W | Tweaks panel (vibe/densidade/accent) | 2 |
| UI-005 | ✅ | — | W | Páginas internas full-width (PR #54) | feito |

---

## 🤖 Módulo EvolutionAgent (meta-tool)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| EVO-001 | ⏳ | 🟡 P2 | W | Fase 1 implementação (CC + Vizra ADK + Prism PHP) | 8 |

---

## 🛠️ Stack / Infra / DevOps

### Resolvidos neste Cycle 01 (2026-04-28)

| # | Status | Task | Resolvido |
|---|---|---|---|
| INF-DNS | ✅ | DNS `meilisearch.oimpresso.com` | API `developers.hostinger.com` PUT overwrite:false |
| INF-TLS | ✅ | Cert TLS Meilisearch Let's Encrypt R12 | Restart Traefik pós DNS |
| INF-KEY | ✅ | OPENAI_API_KEY no Hostinger .env | Wagner forneceu 2026-04-28 |
| INF-ENV | ✅ | SCOUT_DRIVER + MEILISEARCH_HOST + KEY no Hostinger .env | Configurado 2026-04-28 |
| INF-EMB | ✅ | Embedder OpenAI text-embedding-3-small configurado no índice | PATCH + validado e2e |
| INF-NET | ✅ | Docker network `bridge` vs `docker-host_default` (504 Traefik) | Recriou container com NetworkMode correto |
| INF-003 | ✅ | Cleanup workflows YAML `6.7-bootstrap` → `main` | Feito |

### P1 (Cycle 02)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| INF-004 | ⏳ | 🟠 P1 | W | Mergear PRs deploy SSH pendentes | 1 |
| INF-005 | ⏳ | 🟠 P1 | W | Rebase PR #18 (DRAFT) | 0.5 |
| INF-006 | ⏳ | 🟠 P1 | W | Rebuild assets `npm run build:inertia` formalizar receita | 0.5 |

### P2

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| INF-007 | ⏳ | 🟡 P2 | W | Sentry (observabilidade aplicação) | 2 |
| INF-008 | ⏳ | 🟡 P2 | W | Backup automático pré-deploy (formalizar) | 1 |

---

## 📚 Memory / Documentação

### Concluído em Cycle 01 (2026-04-28)

| # | Status | Task | Data |
|---|---|---|---|
| DOC-001 | ✅ | ADR 0041 — Stack QA de IA | 2026-04-28 |
| DOC-002 | ✅ | TASKS.md backlog completo por módulo | 2026-04-28 |
| DOC-003 | ✅ | CURRENT.md template Cycle estado-da-arte | 2026-04-28 |
| DOC-004 | ✅ | TEAM.md perfis + matriz | 2026-04-28 |
| DOC-005 | ✅ | INFRA.md + §6.2.1 DNS API | 2026-04-28 |
| DOC-006 | ✅ | DESIGN.md | 2026-04-28 |
| DOC-00A | ✅ | ADR 0042–0046 (Reverb/Docker/Vault/DNS/ChatAgent) | 2026-04-28 |
| DOC-00B | ✅ | ADR 0047 — Wagner solo + sprint memória agente | 2026-04-28 |

### P1 (Cycle 02)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| DOC-007 | ⏳ | 🟠 P1 | W | Aprovar/commitar branch `loving-black-f3caa3` | 0.5 |
| DOC-008 | ⏳ | 🟠 P1 | W | Comparativo `pontowr2_vs_concorrentes_capterra_*.md` | 2 |
| DOC-009 | ⏳ | 🟠 P1 | W | Comparativo `copiloto_vs_concorrentes_capterra_*.md` | 2 |
| DOC-010 | ⏳ | 🟡 P2 | W | Comparativo `financeiro_vs_concorrentes_capterra_*.md` | 2 |
| DOC-011 | ⏳ | 🟠 P1 | W | `/memoria-consolidar` slash command + skill | 1 |

---

## 🧪 Backlog longo prazo / "futuro"

| ID | Pri | Task | Quando |
|---|---|---|---|
| FUT-001 | ⚪ P3 | Mobile app (React Native) | Após 50+ clientes |
| FUT-002 | ⚪ P3 | Marketplace de skins/temas Cockpit | Cycle 12+ |
| FUT-003 | ⚪ P3 | API pública B2B | Após Copiloto pago + 10 clientes |
| FUT-004 | ⚪ P3 | BI / analytics avançado | Volume real |
| FUT-005 | ⚪ P3 | App fiscal completo (SPED/ECF/ECD) | Após NFe/CT-e estáveis |
| FUT-006 | ⚪ P3 | Onboarding intro tour | Cockpit V2 estável |
| FUT-007 | ⚪ P3 | i18n (en/es) | Sem demanda BR cobre |

---

## 🏆 Concluído nas últimas 2 semanas

| Data | Módulo | Task |
|------|--------|------|
| 2026-04-29 | Copiloto | **MEM-MET-2** comando `copiloto:metrics:apurar` + baseline 29-abr em prod (`6d2dc7eb`) |
| 2026-04-29 | Copiloto | **MEM-OTEL-1** emissão `gen_ai.*` OTel — 12 atributos por evento (`5acf27de`) |
| 2026-04-29 | Copiloto | **MEM-MET-1** tabela `copiloto_memoria_metricas` em prod — 14 colunas (`21644f4e`) |
| 2026-04-29 | Memory | **ADRs 0048-0051 + 0036 estendida** — Vizra rejeitada, 6 camadas memória, 8 métricas, schema próprio + OTel |
| 2026-04-29 | Copiloto | **MEM-HOT-2** ContextoNegocio injetado no ChatCopilotoAgent (164 tokens prod) — `2be9930c` |
| 2026-04-29 | Copiloto | **MEM-HOT-1** Hybrid embedder MeilisearchDriver (recall 0→190) — `c631042c` |
| 2026-04-29 | Memory | **ADR 0047** Wagner solo + sprint memória priorizado (`da6ce166`) |
| 2026-04-28 | Infra | Meilisearch v1.10.3 + TLS + embedder OpenAI e2e validado |
| 2026-04-28 | Infra | Reverb daemon + smoke test ponta-a-ponta |
| 2026-04-28 | Infra | Traefik + Portainer + Vaultwarden — 5 containers running |
| 2026-04-28 | Copiloto | IA real em produção — gpt-4o-mini respondendo |
| 2026-04-28 | Copiloto | config/ai.php commitado + log channel copiloto-ai |
| 2026-04-28 | Memory | ADR 0042-0047 + session log + handoff consolidado |
| 2026-04-27 | MemCofre | Links `/docs` legacy + dark shadcn (`86ce9537`) |
| 2026-04-27 | UI | Páginas internas full-width (PR #54) |
| 2026-04-27 | UI | Tema dark + apps vinculados vazio (PR #53) |
| 2026-04-27 | Memory | ADR 0038 promoção `6.7-bootstrap` → `main` |
| 2026-04-26 | Copiloto | Sprint 4 PR #25 — `MemoriaContrato` + LGPD soft delete |
| 2026-04-26 | Copiloto | Sprint 1 PR #24 — `laravel/ai ^0.6.3` + 4 Agents |
| 2026-04-25 | Inertia | Upgrade v2 → v3 (ADR 0023) |

---

## ❌ Cancelado / abandonado

| Task | Motivo |
|------|--------|
| Vizra ADK install imediato | **REJEITADO 29-abr (ADR 0048)** — Vizra quebrou no L13, oimpresso fica em `laravel/ai` indefinidamente |
| Reverb broadcasting | Conflita pusher 5.0; `BROADCAST_DRIVER=null` era → agora Reverb ✅ |
| spatie/laravel-data | Conflito phpdocumentor/reflection 6.0 |
| pgvector | Exige PostgreSQL — não temos (ADR 0033) |
| Migrar 6.433 chamadas `Form::` em 460 Blades | Shim funciona, ROI baixo |

---

## Como esta lista evolui

- **Daily async (09h):** Wagner atualiza status próprio (✅ feito ontem / 🔄 hoje / ⛔ bloqueio)
- **Quando criar task:** módulo certo + pri (P0 só Cycle atual!) + dono [W] + estimativa + DoD em 1 frase
- **Quando matar task:** mover pra `❌ Cancelado` com motivo
- **Final de Cycle (sex 12-mai):** Wagner faz pass — ✅ tasks → "Concluído"; não-fechadas → repriorizar; arquivar CURRENT.md em `memory/cycles/CICLO-01-2026-05-12.md` com retro de 5 linhas

> **Última atualização:** 2026-04-28 — modo solo ativo [ADR 0047]; todos donos → [W]; sprint memória agente P0 adicionado
