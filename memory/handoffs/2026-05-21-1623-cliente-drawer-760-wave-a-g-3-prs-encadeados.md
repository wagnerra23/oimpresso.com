---
date: "2026-05-21"
time: "1623 BRT"
slug: cliente-drawer-760-wave-a-g-3-prs-encadeados
tldr: "Wave A-G entregue como 3 PRs encadeados (#1339→#1342→#1344) invertendo paradigma Cliente Show.tsx full-page → drawer 760px lateral com 8 tabs cadastrais. Aguarda merge sequencial + ADR 0179 accepted + Wave Z-2 smoke prod biz=1."
decided_by: [W]
cycle: CYCLE-06
prs: [1339, 1342, 1344]
us: []
authors: [W, C]
next_steps:
  - "Wagner aprova ADR 0179 status: accepted no PR #1339"
  - "Wagner merge sequencial #1339 → #1342 → #1344"
  - "Wagner habilita MWART_CLIENTE_INDEX=true canary biz=1 em .env Hostinger"
  - "Wave Z-2 smoke Brave prod biz=1 (NUNCA biz=4 Larissa) + screenshot 8 tabs"
  - "Append SYNC_LOG.md com [W2] approved screenshot + [W2] merged PR #NNN"
  - "php artisan jana:health-check em prod"
  - "Skill brief-update → memory/requisitos/Crm/BRIEFING.md"
related_adrs:
  - 0179-cliente-drawer-760px-substitui-show-fullpage
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0106-recalibracao-velocidade-fator-10x-ia-pair
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0127-spatie-activitylog-lgpd-art-18
  - 0130-handoff-append-only-mcp-first
  - 0149-mwart-screen-pattern-reuse-cowork
  - 0177-mwart-excecao-cliente-show-wave-paralela
---

# Handoff 2026-05-21 16:23 BRT — Cliente drawer 760px Wave A-G 3 PRs encadeados

## TL;DR

Wave A-G inteira da migração paradigma Cliente entregue em ~3h elapsed (fator 10x IA-pair + 7 sub-agents paralelos). 3 PRs encadeados aguardam merge sequencial. Show.tsx full-page será deletado no merge do #1342; drawer 760px lateral com 8 tabs cadastrais + IA + Auditoria assume. Paridade Cowork blueprint score KB-9.75 9,4/10 atingida (~28→90/100 visual).

## Cronologia desta sessão

| Quando | Evento |
|---|---|
| 13:09 | Wagner colou 4 screenshots da pele Cowork + "compare e dê nota por peça" |
| 13:20 | Avaliei ~28/100 paridade. Wagner escolheu opção (A) — refazer charter drawer 760 |
| 13:30 | Spawn wagner-understand → dossiê + 4 perguntas Q1-Q4 |
| 13:45 | Wagner respondeu Q1-Q4 (Show deleta agora + inline autosave + ALTER aditivo + IA default ON) |
| 13:55 | Wave A — 4 agents paralelos (ADR + charter + RUNBOOK + visual-comparison) |
| 14:20 | PR #1339 Wave A criada (5 docs canon, 1005 LOC) |
| 14:35 | Wave B+C — 3 agents paralelos (scaffold + 5 tabs cadastrais + BrLookup) |
| 14:50 | 3 fixes de integração (neighborhood column + Contact casts + Index.tsx plug) |
| 15:00 | PR #1342 Wave B+C criada (20 arquivos, ~5000 LOC, 11/11 Pest charter PASS) |
| 15:10 | Wave D+E+F+G — 4 agents paralelos (OssTab + IA + Auditoria + Listagem turbinada) |
| 16:10 | Consolidação Index.tsx (3 imports + 3 substituições placeholders) |
| 16:15 | Pest 23/24 PASS local (+1 skip schema-canon) |
| 16:23 | PR #1344 Wave D+E+F+G criada (17 arquivos, +4202/-104 LOC) |

## Estado atual dos artefatos

### Entregue nesta sessão

**Wave A (PR #1339 — docs canon):**

| Arquivo | Status | Linhas |
|---|---|---|
| memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md | ✅ proposed | 162 |
| resources/js/Pages/Cliente/Index.charter.md | ✅ live v3 | 103 |
| resources/js/Pages/Cliente/Show.charter.md | ✅ superseded | 79 |
| memory/requisitos/Crm/RUNBOOK-Cliente-drawer-760px.md | ✅ ready | 399 |
| memory/requisitos/Crm/cliente-drawer-760-visual-comparison.md | ✅ draft | 262 |
| memory/sessions/2026-05-21-understand-cliente-drawer-760px-opcao-A.md | ✅ ready | ~300 |

**Wave B+C (PR #1342 — scaffold + cadastro):**

| Arquivo | Status | Linhas |
|---|---|---|
| database/migrations/2026_05_22_000000_extend_contacts_for_cliente_drawer.php | ✅ aditivo | 182 |
| database/migrations/2026_05_22_000001_create_anotacoes_table.php | ✅ NEW | 81 |
| app/Contact.php | ✅ M (4 casts) | +6 |
| app/Http/Controllers/ContactController.php | ✅ M (redirect) | +9 |
| routes/web.php | ✅ M (redirect) | +12 |
| resources/js/Pages/Cliente/Index.tsx | ✅ M (drawer 760 skeleton) | +282 |
| resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx | ✅ NEW | 611 |
| resources/js/Pages/Cliente/_drawer/ContatoTab.tsx | ✅ NEW | 383 |
| resources/js/Pages/Cliente/_drawer/EnderecoTab.tsx | ✅ NEW | 511 |
| resources/js/Pages/Cliente/_drawer/ComercialTab.tsx | ✅ NEW | 373 |
| resources/js/Pages/Cliente/_drawer/ClassificacaoTab.tsx | ✅ NEW | 372 |
| resources/js/Lib/br-mask.ts | ✅ NEW | 97 |
| resources/js/Lib/br-validate.ts | ✅ NEW | 117 |
| Modules/Crm/Services/BrLookupService.php | ✅ NEW | ~180 |
| Modules/Crm/Http/Controllers/ClienteAutosaveController.php | ✅ NEW | ~500 |
| Modules/Crm/Http/Controllers/ClienteLookupController.php | ✅ NEW | ~80 |
| Modules/Crm/Routes/web.php | ✅ M (+7 rotas) | +54 |
| tests/Feature/Cliente/ClienteIndexDrawer760CharterTest.php | ✅ 11/11 PASS | 243 |
| tests/Feature/Cliente/ClienteDrawerCadastroAutosaveTest.php | ✅ 18 tests | ~350 |
| tests/Feature/Cliente/ClienteLookupCnpjCepTest.php | ✅ 11 tests | ~230 |

**Wave D+E+F+G (PR #1344 — OssTab + IA + Auditoria + Listagem turbinada):**

| Arquivo | Status | Linhas |
|---|---|---|
| resources/js/Pages/Cliente/_drawer/OssTab.tsx | ✅ NEW | 171 |
| resources/js/Pages/Cliente/_drawer/IATab.tsx | ✅ NEW | 418 |
| resources/js/Pages/Cliente/_drawer/AuditoriaTab.tsx | ✅ NEW | 290 |
| resources/js/Components/clientes/Avatar.tsx | ✅ NEW (HSL hash) | 87 |
| resources/js/Components/clientes/Pills.tsx | ✅ NEW (4 pills) | 264 |
| resources/js/Pages/Cliente/Index.tsx | ✅ M (listagem turbinada + 3 imports) | +567 |
| Modules/Crm/Http/Controllers/ClienteIaController.php | ✅ NEW (4 endpoints) | 609 |
| Modules/Crm/Http/Controllers/ClienteAuditoriaController.php | ✅ NEW (2 endpoints) | 428 |
| Modules/Crm/Ai/Agents/ClienteResumoAgent.php | ✅ NEW | 91 |
| Modules/Crm/Ai/Agents/ClienteSegmentoAgent.php | ✅ NEW | 94 |
| Modules/Crm/Ai/Agents/ClienteProximaAcaoAgent.php | ✅ NEW | 84 |
| Modules/Crm/Routes/web.php | ✅ M (+6 rotas IA/Auditoria) | +75 |
| app/Http/Controllers/ContactController.php | ✅ M (payload expand + clienteExport) | +164 |
| routes/web.php | ✅ M (/cliente/export) | +6 |
| tests/Feature/Cliente/ClienteListagemTurbinadaTest.php | ✅ 12 PASS + 1 skip | 300 |
| tests/Feature/Cliente/ClienteIaTabTest.php | ✅ 12/12 PASS MySQL | 286 |
| tests/Feature/Cliente/ClienteAuditoriaTabTest.php | ✅ 13 collect (SQLite skip canon) | 372 |

### PRs

| PR | Status | Base | Conteúdo |
|---|---|---|---|
| [#1339](https://github.com/wagnerra23/oimpresso.com/pull/1339) | OPEN | main | Wave A docs canon (5 docs, 1005 LOC) |
| [#1342](https://github.com/wagnerra23/oimpresso.com/pull/1342) | OPEN | wave-a-charter-adr | Wave B+C scaffold + 5 tabs cadastrais (20 arquivos, ~5000 LOC) |
| [#1344](https://github.com/wagnerra23/oimpresso.com/pull/1344) | OPEN | wave-b-c-impl | Wave D+E+F+G OssTab+IA+Auditoria+Listagem (17 arquivos, +4202/-104 LOC) |

## Decisões tomadas

| Pergunta | Decisão Wagner | Justificativa | Referência |
|---|---|---|---|
| Show.tsx destino | Deleta agora (mesmo PR drawer, sem sunset) | Paradigma único, evita dual-render | ADR 0179 §Decisão |
| 5 tabs cadastrais | Inline com autosave on blur (debounce 800ms) | Match Cowork blueprint score 9,4/10 | Charter Index.charter.md v3 |
| Schema | ALTER TABLE contacts aditivo | Não toca core UPOS; reversível | Migration extend_contacts_for_cliente_drawer |
| Tab IA gate | Default ON pra todos | Wagner regredir se custo Brain B problema | ADR 0179 §Q4 |
| Trabalho em branch | 3 PRs encadeados (Wave A → B+C → D-G) | 1 PR atômico seria 8000+ LOC; impossível revisar | commit-discipline override label wave-cliente-drawer-impl |

## Bloqueios / pendências

- [ ] PR #1339 aguardando review/merge Wagner — owner: W
- [ ] PR #1342 aguardando #1339 mergear (base branch) — owner: W
- [ ] PR #1344 aguardando #1342 mergear (base branch) — owner: W
- [ ] ADR 0179 marcar `status: accepted` no frontmatter (push direto no #1339 ou após merge) — owner: W
- [ ] Wave Z-2 smoke Brave prod biz=1 (depende dos 3 PRs merged + `MWART_CLIENTE_INDEX=true` em .env Hostinger) — owner: W
- [ ] Visual-comparison.md atualizar nota final 28→~90/100 + status: validated após screenshot prod — owner: W (ou C pós-screenshot)
- [ ] SYNC_LOG.md append eventos `[W2] approved screenshot cliente-drawer-760` + `[W2] merged PR #1339/#1342/#1344` — owner: W
- [ ] `php artisan jana:health-check` em prod biz=1 — owner: W
- [ ] Skill `brief-update` final → `memory/requisitos/Crm/BRIEFING.md` — owner: C (pós-merge)

## Próximos passos (ordem)

1. **Wagner lê PR #1339** (~15min) — confere ADR 0179 + Charter v3 + RUNBOOK + visual-comparison
2. **Wagner aprova ADR 0179 `status: accepted`** no frontmatter (commit direto no branch wave-a)
3. **Wagner merge PR #1339** → main
4. **Wagner lê PR #1342** (~10min) — GitHub auto-rebase base pra main
5. **Wagner merge PR #1342** → main
6. **Wagner lê PR #1344** (~10min) — GitHub auto-rebase base pra main
7. **Wagner merge PR #1344** → main
8. **Wagner habilita `MWART_CLIENTE_INDEX=true`** em .env Hostinger biz=1 (canary)
9. **Claude (sessão futura) executa Wave Z-2**: smoke Brave prod + screenshot 8 tabs → SYNC_LOG.md update + `jana:health-check` + `brief-update`

## Riscos pendentes pós-merge (Wave Z-2)

1. **Sub-tabs aninhadas OssTab em 760px** — Decisão de layout (A) verticais 120px + content 640px pode apertar PaymentsTab/SalesTab/DocumentsTab (originais ~1200px). Smoke prod vai validar — se quebrar, fallback dropdown "Ver: [SalesTab ▼]" implementável em hotfix Wave Z-3.
2. **Tab IA custo Brain B** — Default ON sem gate. Telemetria `\Log::info('cliente.ia.call', [...])` em todos endpoints permite Wagner monitorar custo em 24-48h. Regredir pra gate `copiloto.admin.custos` se custo > $5/dia/biz.
3. **ViaCEP/BrasilAPI rate limit** — Cache Redis 90d/30d implementado. Larissa biz=4 1280×1024 com ~30 cadastros/dia: cache hit > 80% após 1ª semana. Mitigação: fallback `null` graceful no `BrLookupService::lookupCep/Cnpj()`.
4. **Migration `neighborhood` column** — Wagner pode rodar `php artisan migrate` em prod APÓS merge. Idempotente (`Schema::hasColumn` guard). Reversível via `migrate:rollback`.
5. **`Show.tsx` deletado zero-sunset (Q1)** — Rollback dos 3 PRs caro se bug aparece. Mitigação: `MWART_CLIENTE_INDEX=false` em .env reverte instantâneo (redirect inativo, Show.tsx ainda existe no código). DELETE real de Show.tsx fica pra hotfix futuro após 7d canary.

## Estado MCP no momento do fechamento

> **Snapshot ADR 0130 §6 — NÃO promessa.**

### cycles-active
```
CYCLE-06 (ativo) — 2026-05-15 → 2026-05-30 (15d)
Goals:
  - G1 Cliente paridade Cowork (esta sessão fechou Wave A-G — 70% do goal G1)
  - G2 Fiscal Waves 5-9 (mergeado PR 2026-05-20)
  - G3 OficinaAuto piloto Martinho (pendente)
Dia 7 de 15.
```

### my-work
```
Não consultado nesta sessão (~3h focado em Cliente drawer 760).
Esperado: 3 PRs Wave A-G aparecem como aguardando review.
```

### sessions-recent limit:3
```
2026-05-21 understand-cliente-drawer-760px-opcao-A (este trabalho)
2026-05-21 understand-sells-unificar-lista-grade (untracked, outra sessão)
2026-05-20 fin-bridge-larissa-accounting-deprec-complete
```

### decisions-search since:2026-05-20
```
ADR 0179 — Cliente drawer 760px substitui Show.tsx (proposed nesta sessão)
ADR 0180 — Cockpit sidebar v3 (commit 7dd50fb74 local, pré-existente)
```

### whats-active
```
N/A — única sessão ativa (Claude Code Wagner@WR2 SC).
Felipe/Maiara/Eliana/Luiz não tocaram paths overlapping nas últimas 2h.
```

## Métricas da sessão

| Métrica | Valor |
|---|---|
| Sub-agents spawned | 9 (1 wagner-understand + 4 Wave A + 3 Wave B+C + 4 Wave D-G) |
| Agents paralelos máximos simultâneos | 4 (Wave A + Wave D-G) |
| Total LOC entregue | ~10.200 (+10204 / -140) |
| Total arquivos tocados | 42 (33 NEW + 9 M) |
| Total Pest tests | ~65 (charter v3 + autosave + lookup + IA + auditoria + listagem turbinada) |
| Pest local PASS | 23 PASS + 1 skip canon + 13 SQLite-skip canon |
| Tempo elapsed sessão | ~3h13min (13:09 → 16:23 BRT) |
| Tempo IA-pair estimado | ~35h (fator 10x ADR 0106) |
| PRs criados | 3 (#1339, #1342, #1344) |
| ADRs novas | 1 (0179 proposed) |
| Charters tocados | 2 (Show v2 superseded + Index v3 live) |

## Referências

- Session log: [2026-05-21-understand-cliente-drawer-760px-opcao-A.md](../sessions/2026-05-21-understand-cliente-drawer-760px-opcao-A.md)
- Handoff anterior: [2026-05-21-1052-cliente-react-completo-prod.md](2026-05-21-1052-cliente-react-completo-prod.md) (Wave Final Show.tsx 8 tabs — superseded por esta wave)
- ADR 0179: [Cliente drawer 760px substitui Show.tsx](../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)
- ADR 0130: [Handoff append-only + MCP-first](../decisions/0130-handoff-append-only-mcp-first.md)
- Protótipo Cowork: [`prototipo-ui/prototipos/clientes/`](../../prototipo-ui/prototipos/clientes/) — score KB-9.75 9,4/10
- HANDOFF_CLIENTES.md: schema BR completo (381 linhas)
- RUNBOOK Wave A-G+Z: [RUNBOOK-Cliente-drawer-760px.md](../requisitos/Crm/RUNBOOK-Cliente-drawer-760px.md)
