# RUNBOOK — Screen Review (loop PDCA visual `/admin/screen-review`)

> **Tier 0:** Tela Wagner-only via Tailscale (middleware `tailscale-only` + `auth` + `is-wagner`). Acesso fora de Tailscale = 403.
> **Origem:** W30 (2026-05-17) — skill `tela-smoke-pos-merge` (W30-A) + Controller (W30-B) + workflow GHA + Pest + mock (W30-C).
> **Cross-ref:** [ADR 0164](../../decisions/0164-skill-tela-smoke-pos-merge.md) · [SKILL tela-smoke-pos-merge](../../../.claude/skills/tela-smoke-pos-merge/SKILL.md) · [Workflow GHA](../../../.github/workflows/screen-smoke-after-merge.yml)

## O que é

Centraliza loop PDCA visual de toda tela `.tsx` mergeada:

```
.tsx mergeada → smoke screenshot 1440×900 → desvios catalogados → Wagner valida
   ↓
 [pending-wagner] → [approved] (loop fecha)
   ↓                  ou
                   [rejected] → Initiative governance auto P1 (cross-ref W29)
                   ou
                   [iterate]  → round++, smoke novo, repete
```

**Por que existe:** evita que telas mergeadas fiquem "no ar" sem Wagner ter visto o resultado real. Toda tela tem charter (target UX) + review (rounds PDCA) ao lado do `.tsx`.

## Como acessar (Wagner)

### 1. Ativar Tailscale local

Windows: app Tailscale tray → confirma status "Connected" → IP `100.99.x.y` ativo.

Sem Tailscale rodando → middleware `tailscale-only` retorna 403 mesmo logado.

### 2. Abrir tela

```
https://oimpresso.com/admin/screen-review
```

Login Wagner (user_id=1, business_id=1) → tela carrega:
- **Header meta:** total telas / pending / approved / rejected / iterate
- **Coluna esquerda:** módulos agrupados (Admin, KB, Repair, Sells, Inbox, Jana, ...)
- **Coluna direita:** lista telas do módulo selecionado com cards:
  - Screenshot 1440×900 (último round)
  - Status + round atual
  - UX targets do charter (first_paint, lcp, density, controller_time)
  - Botões: **Aprovar** / **Rejeitar** / **Iterate (novo round)**

## Workflow Wagner por tela

### Aprovar

1. Clica **Aprovar** no card
2. Modal pede notas opcionais (ex: "Round 2 estabilizou pós-W29 — first_paint 600ms")
3. Confirma → POST `/admin/screen-review/update-status`
4. Backend grava append em `resources/js/Pages/<Mod>/<Tela>.review.md`:

   ```markdown
   ## Round 2 — APPROVED — 2026-05-17 14h32 BRT

   Wagner aprovou. Notas: Round 2 estabilizou pós-W29 — first_paint 600ms.

   - Screenshot: storage/screen-reviews/admin-governance-v4-r2-1440.png
   - UX measured: first_paint=600ms, lcp=1200ms, controller_time=180ms
   - Desvios: 0
   ```

5. Status muda pra `approved` na tela. Loop fecha pra essa versão da tela.

### Rejeitar

Mesmo fluxo de Aprovar, mas:

- Append em `<Tela>.review.md` com status `REJECTED`
- **Cria Initiative governance automática** (cross-ref W29 `InitiativeService::createFromScorecardBreach`):
  - `module = <Modulo da tela>`
  - `bucket = cross_cutting_infra` (default — ou bucket do módulo se mapeado)
  - `rule_id = "ScreenReview/<Mod>/<Tela>"`
  - `deadline_days = 14` (Cortex/Port.io default)
  - `score_before = 0, score_target = 100` (binário visual)
- Initiative aparece em `/admin/governance/v4` na lista de open initiatives

### Iterate

Loop continua — round++:

- Append em `<Tela>.review.md` com status `ITERATE`
- Tela volta pra `pending-wagner` no próximo smoke (cron daily 09:00 BRT OU workflow_dispatch manual)
- Screenshot novo é tirado → Wagner re-valida

## Forçar smoke manual (workflow_dispatch)

Cenário: você corrigiu uma tela rejected e quer re-smoke ANTES do cron daily.

```bash
# Via gh CLI (autenticado)
gh workflow run screen-smoke-after-merge.yml \
  -f screen_path="Repair/Index" \
  -f force_smoke=true
```

OU via GitHub UI:

1. https://github.com/wagnerra23/oimpresso.com/actions/workflows/screen-smoke-after-merge.yml
2. Botão "Run workflow"
3. Branch `main`
4. `screen_path = Repair/Index`
5. `force_smoke = true`
6. Run

Workflow comenta no último PR mergeado avisando smoke pendente. Wagner abre `/admin/screen-review` e valida.

## Cron daily 09:00 BRT

Execução automática batch — pega todas as telas com status `pending-wagner` ou `iterate` e dispara smoke novo.

Comando subjacente (Console/Kernel ou similar — W30-B implementa):

```bash
php artisan screen-review:smoke-batch --status=pending-wagner,iterate
```

Output: log + screenshots regravados em `storage/screen-reviews/<modulo-kebab>-<tela-kebab>-r<round>-1440.png`.

## Ler `<Tela>.review.md` no git

Toda tela com loop ativo tem `<Tela>.review.md` ao lado do `<Tela>.tsx`:

```
resources/js/Pages/Admin/
├── GovernanceV4.charter.md     # target UX
├── GovernanceV4.tsx            # código
├── GovernanceV4.review.md      # loop PDCA (append-only)
```

Pattern review.md:

```markdown
# Review — Admin/GovernanceV4

## Round 1 — PENDING-WAGNER — 2026-05-17 07h32 BRT

Smoke automático pós-merge PR #1234 (W30).

- Screenshot: storage/screen-reviews/admin-governance-v4-r1-1440.png
- UX measured: first_paint=820ms, lcp=1480ms
- Desvios catalogados:
  - [DV-1] Sparkline cor errada vs charter (target #3b82f6, atual #6366f1)
  - [DV-2] Botão "Initiative" desalinhado 2px à direita

## Round 2 — APPROVED — 2026-05-17 14h32 BRT
...
```

**Append-only:** novos rounds adicionam ao final. NUNCA editar rounds antigos.

## Edge cases catalogados

### Tela sem charter.md

- `has_charter = false` no payload
- UI mostra badge "Sem charter — aprovar gera charter retroativo"
- Wagner pode aprovar mesmo assim — Controller W30-B gera `<Tela>.charter.md` skeleton com UX targets medidos

### Cron silencioso (smoke não roda)

Sintoma: `meta.last_batch_at` mais velho que 24h.

Investigar:

```bash
# Tailscale CT 100
tailscale ssh root@ct100-mcp 'tail -200 /var/log/oimpresso/screen-review.log'

# Hostinger (cron schedule)
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  'cd ~/oimpresso.com && php artisan schedule:list | grep screen-review'
```

Causas comuns:
- Browser headless (Playwright) crashou no CT 100 — `docker restart` no container que roda smoke
- Disk full em `storage/screen-reviews/` — purgar screenshots antigos (`find storage/screen-reviews -mtime +90 -delete`)

### Initiative auto duplicada

Cenário: Wagner rejeita Round 3 → Initiative criada. Wagner reabre rejeição (raro) e re-rejeita.

`InitiativeService::createFromScorecardBreach` é **idempotente** por `module + rule_id` — não cria duplicata. Apenas atualiza `score_target` ou `deadline_days` se Service implementar lógica de "renew".

### Page tsx renomeada/deletada após smoke

Cenário: smoke gerou screenshot pra `Sells/Index.tsx`, depois Page renomeada pra `Sells/Listing.tsx`.

- `<Tela>.review.md` antigo permanece no git (histórico append-only preservado)
- Cron pula tela inexistente sem 500
- Wagner vê tela nova como `pending-wagner` round 1 (review.md novo)
- Cleanup manual opcional: mover review.md antigo pra `_archive/` se quiser

### Storage screenshots cresce indefinidamente

Política: manter 90 dias rolling. Cron daily 02:00 BRT executa:

```bash
php artisan screen-review:purge-screenshots --older-than-days=90
```

Apaga PNG órfãos (não referenciados em nenhum `<Tela>.review.md` round atual).

## Tier 0 IRREVOGÁVEL

- ⛔ **Multi-tenant repo-wide intencional** — `mcp_screen_reviews` table (se W30-B criou) NÃO tem `business_id`. Governance pertence ao repo, não a tenant comercial. Documentar em comentário SQL.
- ⛔ **Append-only em review.md** — NUNCA editar rounds antigos. Hook `block-mwart-violation.ps1` pode ser estendido pra detectar.
- ⛔ **Tailscale-only obrigatório** — sem bypass local. Wagner trabalha sempre conectado.
- ⛔ **PII zero em notes** — Wagner é único usuário, mas evitar CPF/CNPJ/email cliente em notas (pode vazar via session log/handoff).
- ⛔ **Screenshots NÃO commitados** — `storage/screen-reviews/*.png` em `.gitignore`. Persistência em CT 100 (volume Docker) ou S3 futuro.

## Quando NÃO usar essa tela

- Refactor cosmético sem mudança visível (CSS interno, hook React reorganizado) — não dispara workflow (paths matcher só `Pages/**/*.tsx`)
- Tela `.tsx` sem charter E sem review (nova tela WIP) — appears como `pending-wagner` round 1 mas Wagner pode marcar `iterate` indefinidamente até ter charter

## Cross-ref

- Skill: [`.claude/skills/tela-smoke-pos-merge/SKILL.md`](../../../.claude/skills/tela-smoke-pos-merge/SKILL.md) (W30-A)
- ADR proposta: [`memory/decisions/0164-skill-tela-smoke-pos-merge.md`](../../decisions/0164-skill-tela-smoke-pos-merge.md) (W30-A)
- Controller + FormRequest + Page: `Modules/Admin/Http/Controllers/ScreenReviewController.php` + `Modules/Admin/Http/Requests/UpdateReviewStatusRequest.php` + `resources/js/Pages/Admin/ScreenReview.tsx` (W30-B)
- Workflow GHA: [`.github/workflows/screen-smoke-after-merge.yml`](../../../.github/workflows/screen-smoke-after-merge.yml) (W30-C)
- Pest: `Modules/Admin/Tests/Feature/ScreenReviewPageTest.php` + `UpdateReviewStatusRequestTest.php` (W30-C)
- Mock TS: `resources/js/Pages/Admin/_lib/mockScreenReview.ts` (W30-C)
- Cross-ref governance: [ADR 0160 — buckets canon](../../decisions/0160-governance-v4-scoped-scorecards-bucket-meta.md)
- Cross-ref Initiative auto: W29 `InitiativeService::createFromScorecardBreach`

---

**Última atualização:** 2026-05-17 — W30 Agent C release inicial (workflow GHA + Pest + mock + runbook). Próximos: aguardar W30-B mergear Controller pra Pest sair do skip graceful.
