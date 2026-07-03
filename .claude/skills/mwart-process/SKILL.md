---
name: mwart-process
description: Use SEMPRE que o trabalho envolva migrar tela Blade legacy → Inertia/React no oimpresso (MWART). Carrega o processo canônico ÚNICO definido em ADR 0104 — 5 fases obrigatórias e sequenciais (PLAN → BACKEND BASELINE → FRONTEND INCREMENTAL → QA → CUTOVER). Não há caminho alternativo. Ativa quando o pedido é "migrar tela X pra MWART", "criar tela em Pages/<Mod>/<Tela>.tsx", "migrar Blade pra React", ou quando Edit/Write em qualquer `resources/js/Pages/<Mod>/<Tela>.tsx` ou em controller chamando `Inertia::render`.
tier: B
status: active
version: 1.3
authority: canonical
---

# Skill: mwart-process — Processo MWART canônico (Tier A always-on)

> **Documento mãe:** [ADR 0104](../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) (canônico, irrevogável append-only).
> Esta skill carrega o processo único de migração Blade→Inertia. **Não há caminho B.** Pular fase = bloqueio (camadas 2 e 3 do enforcement).

## As 5 fases (obrigatórias e sequenciais)

```
F1 PLAN          F2 BACKEND       F3 FRONTEND       F4 QA              F5 CUTOVER
RUNBOOK + SPEC → BASELINE      → INCREMENTAL     → HARDENING        → + SUNSET
                 dual+flag+Pest   1 PR = 1 US       audit ≥80          aviso prévio
                                  audit ≥70 cada    smoke biz=1        flag ON cliente
                                                    canary 7d          monitor 30d
                                                    backup DB          remove Blade
```

## Regra de ouro

**Antes de qualquer Edit/Write em `resources/js/Pages/<Mod>/<Tela>.tsx` ou em controller chamando `Inertia::render('<Mod>/<Tela>')`:**

1. Verifica F1 completa: `memory/requisitos/<Mod>/RUNBOOK-<tela-kebab>.md` existe?
2. Verifica F2 completa: SPEC.md tem epic + US `<MOD>-002` (backend baseline) com status `done`?
3. **Identifica camada-3 (Padrão de Tela)** aplicável — ver [Constituição UI v2 / ADR UI-0013](../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md):
   - Tela-lista → ler [PT-01-Lista.md](../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md) **antes** de codar (6 slots canônicos)
   - Tela form/drawer → PT-02 (não documentado ainda — drawer 760px em [ADR 0185](../../memory/decisions/0185-drawer-760-canon-entidades-cadastrais.md))
   - Tela detalhe/dashboard/config → abrir ADR ou justificar desvio
4. **Se é migração Blade→React:** o `memory/requisitos/<Mod>/<tela>-parity.md` existe (F2) e os itens de severidade `alta` têm teste de comportamento (F4)? — mapa campo-a-campo, [template](../../memory/requisitos/_DesignSystem/PARITY-TEMPLATE.md) (Onda 0d).
5. **Antes do PR:** rodar checklist [PRE-MERGE-UI](../../memory/requisitos/_DesignSystem/PRE-MERGE-UI.md) camada 4 (anti-padrões AP1-AP8)

**Se NÃO (1) OU (2):** recusa o Edit/Write. Recomenda voltar pra fase faltante. Mensagem PT-BR explicando.

## Skills associadas por fase

| Fase | Skills que ativam | Output esperado |
|---|---|---|
| **F1 PLAN** | `cockpit-runbook` (manual), `brief-first` (Tier A), `commit-discipline` (Tier A) | RUNBOOK 11 seções + SPEC com epic + ≥6 subtasks |
| **F2 BACKEND BASELINE** | `mwart-quality` (auto), `multi-tenant-patterns` (Tier A), `commit-discipline` | Action dual + flag + Pest 5+ fixtures passando + `<tela>-parity.md` (mapa Blade↔React) |
| **F3 FRONTEND INCREMENTAL** | `mwart-quality` (auto), `cockpit-runbook` modo B (audit per-PR) | 1 PR ≤300 LOC por US, score audit ≥70 cada |
| **F4 QA HARDENING** | `cockpit-runbook` modo B comprehensive | Score ≥80, smoke biz=1, canary 7d, backup DB + teste dos itens `alta` de paridade |
| **F5 CUTOVER** | `commit-discipline`, `memory-sync` | Aviso cliente, flag ON, monitor 30d, remove Blade |

## DoD por fase (mínimos não-negociáveis)

### F1 — PLAN
- [ ] `RUNBOOK-<tela>.md` com 11 seções (template em [cockpit-runbook/TEMPLATE.md](../cockpit-runbook/TEMPLATE.md))
- [ ] `SPEC.md` com 1 epic + ≥6 subtasks com `blocked_by` chain
- [ ] PR `docs(<mod>): RUNBOOK + SPEC migração MWART <tela>` mergeado em main

### F2 — BACKEND BASELINE
- [ ] Action dual no controller: Blade fallback + `Inertia::render` se header `X-Inertia` E flag `useV2<Tela>=true`
- [ ] Feature flag default OFF em `pos_settings` JSON
- [ ] Comando artisan `<mod>:enable-v2 <biz>` liga/desliga em <30s
- [ ] Pest tests baseline ≥5 fixtures cobrem casos reais do `store()` antes de mexer
- [ ] **`memory/requisitos/<Mod>/<tela>-parity.md`** — mapa campo-a-campo Blade↔React ([template](../../memory/requisitos/_DesignSystem/PARITY-TEMPLATE.md)): todo campo do Blade tem linha + severidade + divergências deliberadas. Entregável da **Onda 0d** (piloto: [`User/perfil-parity.md`](../../memory/requisitos/User/perfil-parity.md))

### F3 — FRONTEND INCREMENTAL (cada PR)
- [ ] PR ≤ 300 LOC, 1 intent
- [ ] Persistent Layout: `Tela.layout = (page) => <AppShellV2>{page}</AppShellV2>` (não envolver em `<AppShell>`)
- [ ] Tokens shadcn semânticos (sem cor crua — R-DS-002)
- [ ] `localStorage` com prefixo `oimpresso.` (não `sessionStorage`)
- [ ] Atalhos com `removeEventListener` no cleanup + bloqueio em `<input>`
- [ ] Audit cockpit-runbook modo B ≥ 70 (CRITICAL bloqueia merge)

### F4 — QA HARDENING
- [ ] Audit modo B comprehensive — score ≥ 80, CRITICAL=0, WARN=0
- [ ] **Paridade — cada item de severidade `alta` do `<tela>-parity.md` tem teste de comportamento** que **quebra** se o campo deixar de persistir/funcionar (red/green), citando o id do UC. **Enforcement por comportamento, não presença** — "o `-parity.md` existe" NÃO conta ([proibicoes §descartados](../../memory/proibicoes.md), gate de presença já rejeitado). Espelha o `<Tela>.casos.md` (casos-gate required, [ADR 0264](../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md))
- [ ] Smoke em `business_id=1` (Wagner WR2 SC) — **NUNCA biz=4** ([ADR 0101](../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md))
- [ ] Canary Wagner 7 dias com flag ON em biz=1
- [ ] Backup DB: `mysqldump` das tabelas críticas
- [ ] Rollback plan documentado (comando exato + tempo de rollback)

### F5 — CUTOVER + SUNSET
- [ ] Aviso prévio cliente (humano-no-loop)
- [ ] Flag ON em `business_id` cliente
- [ ] Monitorar 30d em `storage/logs/laravel.log`
- [ ] Após 30d sem incidente: deletar Blade legacy + branch dual + comando artisan
- [ ] Audit final do `Pages/<Mod>/<Tela>.tsx` ≥ 80

## Adapção por tipo de módulo (F0 — antes de F1)

Antes de F1 PLAN, **classificar o módulo alvo**. Diferentes tipos = diferentes decisões em placement no menu, perfil de teste e cutover:

| Tipo | Exemplos | Placement no sidebar React | Cutover F5 | RUNBOOK exemplar |
|---|---|---|---|---|
| **Operativo** (uso day-to-day cliente) | Sells, Repair, Financeiro, NfeBrasil | Grupos `office`/`fin`/`fiscal`/`rh`/`ia`/etc via `SIDEBAR_GROUPS` em `Sidebar.tsx` | Aviso prévio cliente + canary 7d + monitor 30d | [RUNBOOK Sells/create](../../memory/requisitos/Sells/RUNBOOK-create.md) |
| **Admin de plataforma — uso pesado pelo owner** | Officeimpresso (gestão licenças desktop legacy WR) | Grupo `office` (ACESSOS RÁPIDOS) — fica perto dos itens day-to-day | Sem cliente externo → cutover sem janela | [RUNBOOK Officeimpresso](../../memory/requisitos/Officeimpresso/RUNBOOK-migracao-react.md) |
| **Admin de plataforma — uso esporádico** | CMS, Conector, Backup, Módulos, Personalizar | Grupo `plataforma` (PLATAFORMA) — no fim, collapsed por default | Sem cliente externo → cutover sem janela | (mesmo RUNBOOK) |
| **Público** (clientes finais sem login) | Catalogue QR, Status reparo público | Rota separada, layout próprio sem AppShellV2 | Cache CDN + canary IP-based | (criar quando aparecer) |

> **Histórico:** cascata "Superadmin" do user dropdown footer existiu de 2026-04-27 a 2026-05-10 ([PR #516](https://github.com/wagnerra23/oimpresso.com/pull/516) removeu). Decisão Wagner: admin de plataforma é menu como qualquer outro — não merece tratamento especial em UX. `SUPERADMIN_LABELS` em `shared.ts` está esvaziado (deprecated, mantém callers sem quebrar).

**Pegadinhas específicas catalogadas em [`mwart-quality`](../mwart-quality/SKILL.md) Checks 11-12** — ler ANTES de F2 backend baseline em módulo admin de plataforma (parent dropdown sem `url` + Spatie perm `superadmin` ausente).

## Anti-padrões (NUNCA fazer)

- ❌ **Pular F1** (codar antes de RUNBOOK + SPEC) — bloqueado por hook + CI gate
- ❌ **Pular F2** (mexer no controller `Inertia::render` sem Pest baseline) — bloqueado
- ❌ **Pular F0** (não classificar tipo do módulo) — gera placement errado no sidebar (ex: módulo de uso esporádico no grupo `office` topo, polui ACESSOS RÁPIDOS)
- ❌ **Audit modo B pós-merge** — sempre antes do PR mergear, não depois
- ❌ **Habilitar flag em cliente real (biz≠1) sem F4 completa** — quebra ROTA LIVRE; auto-mem `feedback_test_business_id_1_nunca_4` é IRREVOGÁVEL
- ❌ **PR > 300 LOC** ou **mistura intents** — quebra `commit-discipline` Tier A
- ❌ **Caminho alternativo** "rápido" — não existe. Velocidade aparente vira refactor caro depois.
- ❌ **Migração Blade→React sem `<tela>-parity.md`** (F2) ou com item de severidade `alta` sem teste (F4) — a migração pode ter perdido campo/comportamento **silenciosamente** (a pior dimensão da régua: 8/100 — [Onda 0d](../../memory/requisitos/_Governanca/programa-ondas/onda-0-fundacao/0d-paridade-migracao.md))
- ❌ **Módulo no grupo errado do `SIDEBAR_GROUPS`** — uso esporádico (Backup mensal, CMS raríssimo) NÃO vai pra ACESSOS RÁPIDOS topo; usa grupo `plataforma` no fim. Regra de bolso: se usuário comum (não-superadmin) NÃO precisa ver, vai pra `plataforma`

## Como cuidar (3 camadas de enforcement)

| Camada | Mecanismo | Quando trava |
|---|---|---|
| 1 | **Esta skill Tier A** | Lembra agent a cada sessão; recusa Edit/Write se F1 ou F2 incompleta |
| 2 | **Hook PreToolUse** `block-mwart-violation.ps1` | Trava em runtime — `Edit` em `Pages/<Mod>/<Tela>.tsx` sem RUNBOOK = erro |
| 3 | **CI workflow** `.github/workflows/casos-gate` (required, ADR 0264) | Trava no merge — cobertura de tela. _(O antigo `mwart-gate.yml` era soft/teatro e foi deletado na ADR 0271 onda 2; a régua viva migrou pro casos-gate.)_ |

Camadas 2 e 3 são US-MWART-001 e US-MWART-002 (próximo PR após este ADR mergear).

## Override (exceções autorizadas)

Wagner pode autorizar exceção **de processo** via comentário em PR: `/mwart-override <razão>`. Exceção fica registrada em ADR per-tela `memory/decisions/<NNNN>-mwart-excecao-<mod>-<tela>.md` (lifecycle `historical` — auditoria).

⚠️ **O comentário não mexe em check de CI.** Nenhum workflow processa `issue_comment` — `/mwart-override` é registro humano, não comando de máquina (caso real PR #2544, 2026-06-11: Wagner comentou override e o check `visual-regression` seguiu vermelho). Pra deixar `visual-regression` verde o único caminho é atualizar a baseline: `./vendor/bin/pest tests/Browser/ --update-snapshots` + commit dos screenshots (ADR 0271 — sem gate-teatro).

Sem `/mwart-override`, gates de processo não cedem. Iniciante (`[L]`), esposa (`[E]`), Maíra, Felipe — todos passam pelo mesmo caminho. Wagner também (não pode skipar pra si).

## Refs

- [ADR 0104 — Processo MWART canônico](../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) — documento mãe
- [PARITY-TEMPLATE.md](../../memory/requisitos/_DesignSystem/PARITY-TEMPLATE.md) — template do `-parity.md` (F2) + regra dos itens `alta` (F4) · [Onda 0d](../../memory/requisitos/_Governanca/programa-ondas/onda-0-fundacao/0d-paridade-migracao.md)
- [Skill cockpit-runbook](../cockpit-runbook/SKILL.md) — gera RUNBOOK + audit modo B
- [Skill mwart-quality](../mwart-quality/SKILL.md) — pré-flight checks na implementação (incluindo Checks 11-12 superadmin)
- [Skill sidebar-menu-arch](../sidebar-menu-arch/SKILL.md) — placement de menu (DataController + SUPERADMIN_LABELS)
- [Skill commit-discipline](../commit-discipline/SKILL.md) — Tier A; 1 PR = 1 intent ≤300 LOC
- [Skill multi-tenant-patterns](../multi-tenant-patterns/SKILL.md) — Tier A; `business_id` global scope
- [GOTCHAS](../cockpit-runbook/GOTCHAS.md) — bugs catalogados que motivaram este processo
- [RUNBOOK exemplar Sells/create](../../memory/requisitos/Sells/RUNBOOK-create.md) — módulo operativo
- [RUNBOOK exemplar Officeimpresso/migracao-react](../../memory/requisitos/Officeimpresso/RUNBOOK-migracao-react.md) — módulo superadmin

---

**Última atualização:** 2026-07-02 — **v1.3** (Onda 0d): F2 passa a exigir o `<tela>-parity.md` (mapa campo-a-campo Blade↔React, [template](../../memory/requisitos/_DesignSystem/PARITY-TEMPLATE.md)); F4 exige teste de comportamento pros itens de severidade `alta` (enforcement por comportamento, não presença). Piloto: [`User/perfil-parity.md`](../../memory/requisitos/User/perfil-parity.md). Autorizado pela **[ADR 0320](../../memory/decisions/proposals/0320-programa-ondas-regua-correcao.md)** (Onda 0a, aceita 2026-07-02) — o mecanismo do programa; a paridade é o **piso Tier-0 (c)** de migração Blade→React.
> v1.1 (2026-05-10) adiciona F0 (classificação por tipo de módulo). v1.1.1 (mesmo dia, pós-PR #516): cascata Superadmin removida; placement via `SIDEBAR_GROUPS` (`office` pra uso pesado, `plataforma` pra esporádico)
