# TASKS.md — Backlog completo do projeto

> **O que é:** lista única e canônica de tudo que está pendente, em andamento ou recém-fechado, **organizada por módulo**.
>
> **Não confundir com:**
> - [`CURRENT.md`](CURRENT.md) — Cycle ativo (goal + Active + On-deck).
> - [`TEAM.md`](TEAM.md) — perfis e matriz de atribuição.
> - [`memory/08-handoff.md`](memory/08-handoff.md) — contexto narrativo da última sessão.
> - [`memory/sessions/`](memory/sessions/) — histórico cronológico.
> - [`memory/cycles/`](memory/cycles/) — Cycles fechados com retro.
> - [`memory/requisitos/{Modulo}/SPEC.md`](memory/requisitos/) — especificação detalhada.
>
> **Quando atualizar:** daily async (cada um atualiza status próprio antes das 09h).

---

## Legenda

**Status:** ⏳ TODO · 🔄 Em andamento · ⛔ Bloqueado · 🟡 Adiado · ✅ Done · ❌ Cancelado
**Prioridade:** 🔴 P0 (Cycle atual) · 🟠 P1 (Cycle próximo) · 🟡 P2 (próximos 3 cycles) · ⚪ P3 (algum dia)
**Dono:** [W] Wagner · [M] Maíra · [F] Felipe · [L] Luiz · [E] Eliana(esposa) · [C] Claude (IA pareada) · [Cu] Cursor (IA paralela)
**Cliente externo:** [Larissa] ROTA LIVRE · [Eliana(WR2)] PontoWr2

---

## ⚡ Ativos no Cycle 01 (29-abr → 12-mai)

> Sincronizado com [`CURRENT.md`](CURRENT.md). Editar lá pra mudar o cycle ativo.

| ID | Status | Pessoa | Task | Prazo | Dias est. |
|---|---|---|---|---|---|
| A1 | ⏳ | W | Validar Larissa ROTA LIVRE (1h) | qua 30-abr | 0.5 |
| A2 | 🔄 | W | Merge US-COPI-070 Dashboard custo IA | sex 02-mai | 1 |
| A3 | ⏳ | F | PII redactor BR (LGPD-blocker) | seg 05-mai | 2 |
| A4 | ⏳ | F | OPENAI_KEY + Meilisearch daemon Hostinger | qui 30-abr | 0.5 |
| A5 | ⏳ | M | Cleanup workflows YAML 6.7→main | qua 30-abr | 0.5 |
| A6 | ⏳ | M | Smoke /copiloto manual após A4 | sex 02-mai | 0.5 |
| A7 | ⏳ | L+C | Page /copiloto/admin/qualidade Inertia (skeleton) | qui 08-mai | 3 |
| A8 | ⏳ | E | Atualizar cobrança ROTA LIVRE | sex 02-mai | 1 |

**On-deck Cycle 01:**

| ID | Pessoa | Task | Dias est. | Bloqueado por |
|---|---|---|---|---|
| O1 | F | Sprint 7 ADR 0041 — Golden set v1 (50 q.) | 3 | A1 + A2 |
| O2 | F | Sprint 7 ADR 0041 — DeepEval CI gate | 2 | O1 |
| O3 | W ou F | Langfuse self-host Hostinger | 3 | A4 |
| O4 | F | ApurarQualidadeJob + tabela | 2 | O3 |
| O5 | L+C | Page /copiloto/admin/qualidade lógica | 3 | O4 |
| O6 | M | FIN-001 Backfill purchases legadas | 1 | — |

---

## 🚨 Bloqueante crítico (resolver antes de qualquer sprint novo)

| # | Status | Pri | Dono | Task | Notas |
|---|---|---|---|---|---|
| B1 | ⏳ | 🔴 P0 | W+Larissa | **Validação Larissa** (1h, 3 cenários) | = A1 acima. Define Cycle 01 vs pivot. |

---

## 🤖 Módulo Copiloto

> Stack canônica: ADRs 0035 / 0036 / 0040.

### P0 (Cycle 01)

| # | Status | Pri | Dono | Task | Dias est. | DoD |
|---|---|---|---|---|---|---|
| COP-001 | 🔄 | 🔴 P0 | W | US-COPI-070 Dashboard custo IA — merge | 1 | UI valida em test, merge na main |
| COP-002 | ⏳ | 🔴 P0 | F | Sprint 7 ADR 0041 — Golden set v1 (50 perguntas) | 3 | CSV commitado + 5 sintéticos + 5 adversariais |
| COP-003 | ⏳ | 🔴 P0 | F | PII redactor BR (regex CPF/CNPJ/email/tel) | 2 | Test Pest passa: payload outbound = `[REDACTED]` |
| COP-004 | ⏳ | 🔴 P0 | W | OPENAI_API_KEY produção | 0.5 | `.env` Hostinger + `php artisan config:clear` |

### P1 (Cycle 02 - 03)

| # | Status | Pri | Dono | Task | Dias est. | DoD |
|---|---|---|---|---|---|---|
| COP-005 | ⏳ | 🟠 P1 | F | Langfuse self-host Hostinger + OTEL | 3 | 5 traces aparecem após smoke |
| COP-006 | ⏳ | 🟠 P1 | F | ApurarQualidadeJob + tabela qualidade_scores | 2 | Job rolando Horizon, 5% sampling |
| COP-007 | ⏳ | 🟠 P1 | L+C | Page /copiloto/admin/qualidade HITL | 3 | Lista 20 conv/sem + anotação Larissa |
| COP-008 | ⏳ | 🟠 P1 | F | Configurar embedder Meilisearch | 1 | PATCH settings/embedders OpenAI v3-small |
| COP-009 | ⏳ | 🟠 P1 | F | ApurarMetasAtivasJob (scheduler diário) | 1 | Cron Horizon + log Larissa meta atual |
| COP-010 | ⏳ | 🟠 P1 | F | SuggestionEngine parsear JSON → Sugestao rows | 2 | ChatController grava sugestões parseadas |
| COP-011 | ⏳ | 🟠 P1 | F+M | Tela LGPD /copiloto/memoria | 3 | Listar fatos + esquecer + opt-out |
| COP-012 | ⏳ | 🟠 P1 | F | Sprint 7 ADR 0041 — DeepEval CI gate | 2 | PR com regression >5% FAILS CI |

### P2 (Cycle 03+)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| COP-013 | ⏳ | 🟡 P2 | F | Drivers `php` e `http` (além de `SqlDriver`) | 3 |
| COP-014 | ⏳ | 🟡 P2 | F+M | Wizard 3 passos /copiloto/metas/create | 3 |
| COP-015 | 🟡 | 🟡 P2 | F | Vizra ADK install + migrar conversas → vizra_sessions | 5 (deps Vizra L13) |
| COP-016 | ⏳ | 🟡 P2 | F | MeilisearchDriver implementação | 4 |
| COP-017 | ⏳ | 🟡 P2 | F | Bridge memória↔chat (top-K + extrai async) | 3 (deps COP-016) |
| COP-020 | 🟡 | 🟡 P2 | F | Testes superadmin (`copiloto.superadmin`) | 1 (deps MySQL) |

### Adiado / condicional

| # | Status | Pri | Dono | Task | Trigger |
|---|---|---|---|---|---|
| COP-018 | 🟡 | ⚪ P3 | F | Mem0RestDriver upgrade managed | ADR 0036 sprint 8+ — só se trigger ativar |
| COP-019 | 🟡 | ⚪ P3 | F | Multi-judge ensemble (Claude+GPT+Gemini) | ADR 0041 — só após 100k+ requests/mês |
| COP-021 | 🟡 | ⚪ P3 | F | NeMo / Patronus runtime guardrails | ADR 0041 — só se PII regex falhar 3+ vezes |

---

## 💰 Módulo Financeiro

### P0 (Cycle 01)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| FIN-001 | ⏳ | 🟠 P1 | M | Backfill purchases legadas em `due` | 1 |

### P1 (Cycle 02)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| FIN-002 | ⏳ | 🟠 P1 | F | Rodar `ContaBancariaIndexTest` + `RelatoriosTest` em MySQL local | 0.5 |
| FIN-003 | ⏳ | 🟠 P1 | M | Audit "cache/estado preservado entre navegações" Financeiro | 2 |
| FIN-004 | ⏳ | 🟠 P1 | E | Atualizar cobrança ROTA LIVRE (= A8) | 1 |

### P2

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| FIN-005 | ⏳ | 🟡 P2 | M | Tela unificada US-FIN-013 (4 estados juntos) | 5 |
| FIN-006 | ⏳ | 🟡 P2 | F | Take rate de boleto (CNAB-only mode) | 5 |
| FIN-007 | ⏳ | 🟡 P2 | F | Conciliação Pix automática | 5 |
| FIN-008 | ⏳ | 🟡 P2 | E | DRE gerencial revisão UX como usuária real | 1 |

---

## ⏰ Módulo PontoWr2

> Cliente: WR2 Sistemas / **Eliana(WR2)** [externa, não confundir com Eliana[E] esposa]. Estado dorminhoco desde upgrade 6.7.

### P1 (Cycle 02)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| PNT-001 | ⏳ | 🟠 P1 | F+M | Tier A — Dashboard vivo (3 personas, 8 capacidades) | 5 |
| PNT-002 | ⏳ | 🟠 P1 | W | Validar Eliana(WR2) — o que mudou em 6m sem PontoWr2 | 0.5 (call) |

### P2

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| PNT-003 | ⏳ | 🟡 P2 | F+W | Comparativo `pontowr2_vs_concorrentes_capterra_*.md` | 2 |
| PNT-004 | ⏳ | 🟡 P2 | F | 10 moves Tier A/B/C priorizados em SPEC | 2 |
| PNT-005 | ⏳ | 🟡 P2 | W | ADR formal `requisitos/PontoWr2/adr/ui/0002` | 1 |

---

## 🗄️ Módulo MemCofre (ex-DocVault)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| MEM-001 | ⏳ | 🟠 P1 | M+L | UI de upload de evidência | 3 |
| MEM-002 | ⏳ | 🟡 P2 | M | Página listagem `Doc*` entidades | 2 |
| MEM-003 | ✅ | — | F | Links `/docs` legacy + dark theme shadcn | feito 2026-04-27 |

---

## 🌐 Módulo Cms (landing oimpresso.com)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| CMS-001 | 🟡 | 🟡 P2 | M+L | Hidratação Site/Home com `cms_pages` (re-tentar com fallback) | 2 |
| CMS-002 | ⏳ | 🟡 P2 | M+L+E | PR2+ redesign Inertia/React (blog + contact) | 4 |
| CMS-003 | ⏳ | ⚪ P3 | W | Decidir migrar landing inteira pro Inertia | 0.5 (decisão) |

---

## 🏢 Módulo Officeimpresso (superadmin-only)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| OFF-001 | ✅ | — | F | Restauração 3.7→6.7 + tela `licenca_log` v3 | feito |
| OFF-002 | ⏳ | ⚪ P3 | W+F | Auditoria untracked `Modules/Connector` no servidor | 1 (SSH flaky) |

---

## 📄 Módulo NfeBrasil

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| NFE-001 | 🟡 | 🟡 P2 | F | NFe Brasil — implementar do SPEC | 8 |
| NFE-002 | ⏳ | 🟡 P2 | F | CT-e + MDF-e (ADR 0026 diferencial CV) | 8 |

---

## 🔁 Módulo RecurringBilling

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| REC-001 | 🟡 | 🟡 P2 | F+E | Implementação do SPEC | 5 |

---

## 🌱 Módulo Grow

> Auto-mem diz "prioridade", mas tarefas concretas indefinidas.

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| GRO-001 | ⏳ | 🟠 P1 | W+F | Reunião de elicitação de escopo Grow | 0.5 |
| GRO-002 | ⏳ | 🟡 P2 | F | SPEC `memory/requisitos/Grow/SPEC.md` | 2 (deps GRO-001) |

---

## 🎨 Módulo CockpitBootstrap (Sidebar/AppShell)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| UI-001 | ⏳ | 🟠 P1 | F+L | Portar `AppShellV2.tsx` (Fase 1 ADR 0039) | 3 |
| UI-002 | ⏳ | 🟠 P1 | M+L | Componentes shared `LinkedApps/*` | 4 |
| UI-003 | ⏳ | 🟡 P2 | F | TaskProvider + `Pages/Tarefas/Index.tsx` | 3 |
| UI-004 | ⏳ | 🟡 P2 | M+L | Tweaks panel (vibe/densidade/accent) | 2 |
| UI-005 | ✅ | — | F | Páginas internas full-width (PR #54) | feito |

---

## 🤖 Módulo EvolutionAgent (meta-tool)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| EVO-001 | ⏳ | 🟡 P2 | F | Fase 1 implementação (CC + Vizra ADK + Prism PHP) | 8 |

---

## 🛠️ Stack / Infra / DevOps

### P0 (Cycle 01)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| INF-001 | ⏳ | 🔴 P0 | F | Iniciar daemon Meilisearch Hostinger (= A4) | 0.5 |
| INF-002 | ⏳ | 🔴 P0 | M | Smoke manual `/copiloto` em prod (= A6) | 0.5 |
| INF-003 | ⏳ | 🔴 P0 | M | Cleanup workflows YAML `6.7-bootstrap` → `main` (= A5) | 0.5 |

### P1 (Cycle 02)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| INF-004 | ⏳ | 🟠 P1 | W+F | Mergear PRs deploy SSH #26 / #27 / #29 | 1 |
| INF-005 | ⏳ | 🟠 P1 | W | Rebase PR #18 (DRAFT) | 0.5 |
| INF-006 | ⏳ | 🟠 P1 | M | Rebuild assets `npm run build:inertia` formalizar receita | 0.5 |

### P2

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| INF-007 | ⏳ | 🟡 P2 | F | Sentry (observabilidade aplicação) | 2 |
| INF-008 | ⏳ | 🟡 P2 | F | Backup automático pré-deploy (formalizar) | 1 |

---

## 📚 Memory / Documentação

### P0 (esta sessão / Cycle 01)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| DOC-001 | ✅ | — | C | ADR 0041 — Stack QA de IA | feito 2026-04-28 |
| DOC-002 | ✅ | — | C | TASKS.md backlog completo por módulo | este arquivo |
| DOC-003 | ✅ | — | C | CURRENT.md template Cycle estado-da-arte | feito 2026-04-28 |
| DOC-004 | ✅ | — | C | TEAM.md perfis + matriz | feito 2026-04-28 |
| DOC-005 | ✅ | — | C | INFRA.md (extração §8) | feito 2026-04-28 |
| DOC-006 | ✅ | — | C | DESIGN.md (fundir §10) | feito 2026-04-28 |

### P1 (Cycle 02)

| # | Status | Pri | Dono | Task | Dias est. |
|---|---|---|---|---|---|
| DOC-007 | ⏳ | 🟠 P1 | W | Aprovar/commitar branch `loving-black-f3caa3` | 0.5 |
| DOC-008 | ⏳ | 🟠 P1 | F | Comparativo `pontowr2_vs_concorrentes_capterra_*.md` | 2 (antes PNT-001) |
| DOC-009 | ⏳ | 🟠 P1 | F+W | Comparativo `copiloto_vs_concorrentes_capterra_*.md` | 2 (após DRY_RUN=false) |
| DOC-010 | ⏳ | 🟡 P2 | F | Comparativo `financeiro_vs_concorrentes_capterra_*.md` | 2 (antes take rate) |
| DOC-011 | ⏳ | 🟠 P1 | C+W | `/memoria-consolidar` slash command + skill | 1 (Camada 1+2 sugestão) |

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

| Data | Quem | Módulo | Task |
|---|---|---|---|
| 2026-04-28 | C | Memory | TASKS/CURRENT/TEAM/INFRA/DESIGN refactor + ADR 0041 |
| 2026-04-27 | F | MemCofre | Links `/docs` legacy + dark shadcn (`86ce9537`) |
| 2026-04-27 | F | UI | Páginas internas full-width (PR #54) |
| 2026-04-27 | F | UI | Tema dark + apps vinculados vazio (PR #53) |
| 2026-04-27 | W | Memory | ADR 0038 promoção `6.7-bootstrap` → `main` |
| 2026-04-27 | W | Memory | Cleanup ADR 0024 dup → 0029 |
| 2026-04-27 | W+F | Memory | ADR 0039 — UI Chat Cockpit |
| 2026-04-26 | F | Copiloto | Sprint 4 PR #25 — `MemoriaContrato` + LGPD soft delete |
| 2026-04-26 | F | Copiloto | Sprint 1 PR #24 — `laravel/ai ^0.6.3` + 4 Agents |
| 2026-04-26 | F | Stack | Meilisearch local + Hostinger v1.10.3 |
| 2026-04-26 | W | Memory | ADRs 0035/0036/0037 |
| 2026-04-25 | F | Inertia | Upgrade v2 → v3 (ADR 0023) |
| 2026-04-25 | W | Memory | ADR 0026 — Posicionamento ERP gráfico com IA |
| 2026-04-25 | W+C | Memory | Comparativo `oimpresso_vs_concorrentes_capterra_2026_04_25` |

---

## ❌ Cancelado / abandonado

| Task | Motivo |
|---|---|
| Vizra ADK install imediato | Vizra requer L11/L12, projeto é L13 — adia |
| Reverb broadcasting | Conflita pusher 5.0; `BROADCAST_DRIVER=null` em uso |
| spatie/laravel-data | Conflito phpdocumentor/reflection 6.0 |
| pgvector | Exige PostgreSQL — não temos (ADR 0033) |
| Migrar 6.433 chamadas `Form::` em 460 Blades | Shim funciona, ROI baixo |

---

## Como esta lista evolui

- **Daily async (cada manhã 09h):** cada pessoa atualiza próprio status (✅ feito ontem / 🔄 hoje / ⛔ bloqueio)
- **Quando criar task:** módulo certo + pri (P0 só Cycle atual!) + dono [iniciais] + estimativa em dias úteis + DoD em 1 frase
- **Quando matar task:** mover pra `❌ Cancelado` com motivo (não deletar — git tem)
- **Final de Cycle (sex 12-mai):** Wagner faz pass:
  - ✅ tasks Active+On-deck → mover pra "Concluído últimas 2 semanas"
  - 🔄 não-fechadas → repriorizar (entram Cycle 02 ou viram P2)
  - Arquivar `CURRENT.md` em `memory/cycles/CICLO-01-2026-05-12.md` com retro de 5 linhas
  - Re-escrever `CURRENT.md` com Cycle 02

> **Última atualização:** 2026-04-28 (refactor pra time de 5 + estimativa em dias + Cycle 01 ativos)
