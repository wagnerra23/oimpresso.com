<<<<<<< HEAD
# Admin — UI Catalog (auto-gerado bulk W31-10)

> **Ultima atualizacao:** 2026-05-17 (W31-10)
> **Auto-regeneravel.** Manter versao; reescrever via gerador (nao editar manual em campos de auto-status).
> Pages dir: `resources/js/Pages/Admin/`

## Resumo

- Telas Inertia tsx: **6**
- Charters `.charter.md`: **3** (cobertura: 50%)
- Blade legacy do modulo (Modules/<X>/Resources/views): **0**

## Telas Inertia tsx

| Tela | Charter | review.md | Status | Round | Smoke pendente |
|---|:---:|:---:|---|---:|:---:|
| `FeatureFlags/Index.tsx` | - | - | legacy | 0 | sim |
| `FeatureFlags/Show.tsx` | - | - | legacy | 0 | sim |
| `GovernanceV4.tsx` | OK | - | awaiting-smoke-browser | 1 | sim |
| `GovernanceV4Dashboard.tsx` | OK | - | awaiting-smoke-browser | 1 | sim |
| `Index.tsx` | OK | - | awaiting-smoke-browser | 1 | sim |
| `RagQualityDashboard.tsx` | - | - | legacy | 0 | sim |


## Blade legacy restantes

sem Blade legacy direto neste modulo

## Migracao planejada (MWART — [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md))

- Estimativa de telas Blade alvo MWART: **0**
- Prioridade: **N/A — Inertia/React majoritario**
- Skill canon: `migracao-blade-react` (Tier B auto-trigger)
- Cross-ref: `memory/sessions/2026-05-17-blade-migration-plan.md` (plano executivo cross-projeto)

## Convencoes

- **Charter obrigatorio** antes de `Write` em `.tsx` ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md))
- **review.md** = avaliacao Wagner pos-smoke browser (criar quando aprovar visualmente em prod)
- **Status pipeline:**
  - `legacy` — sem charter, sem review (precisa charter + MWART F1 design)
  - `charter-WIP` — charter aberto sem status valido
  - `awaiting-smoke-browser` — charter `draft` aceito, falta smoke biz=1 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md))
  - `review-pending` — tela em revisao por Wagner
  - `reviewed-no-charter` — review.md existe mas charter ausente (regularizar)
  - `live` — charter status `live` + smoke validado biz=1

## Como regenerar

```bash
bash /tmp/generate_catalogs.sh
```

(Em S4+ vira `php artisan ui:catalog-generate` — issue futura)

---
Gerado por W31-10 (bulk-screen-review-r1, areas isoladas, sem git ops).
=======
# Admin — UI Catalog

> Gerado por `php artisan admin:ui-catalog-generate Admin` — daily 09:30 BRT.
> Última geração: 2026-05-17 (seed manual W30 — próximas gerações automáticas via cron Schedule).

## Telas (4)

| Tela | Charter | Status review | Round | Última smoke | UX targets |
|---|---|---|---:|---|:---:|
| Admin/GovernanceV4 | draft | pending-wagner | 1 | (pendente) | ⏸ |
| Admin/GovernanceV4Dashboard | draft | no-review | - | - | ⏸ |
| Admin/Index | draft | no-review | - | - | ⏸ |
| Admin/ScreenReview | **sem charter** | pending-wagner | 1 | (pendente) | ⏸ |

## Pendências

- **2 telas pending-wagner** — aguardando Wagner aprovar smoke (Tailscale / bypass dev / rota fora Admin — 3 opções W29-smoke)
- **0 telas rejected**
- **1 tela SEM charter** (`Admin/ScreenReview.charter.md`) — rodar `/charter-write resources/js/Pages/Admin/ScreenReview.tsx` (W30-B já criou Page mas charter pendente)
- **2 telas SEM review.md** (`GovernanceV4Dashboard`, `Index`) — skill `tela-smoke-pos-merge` (Tier B — W30) cria no próximo merge que tocar essas telas

## Cross-ref

- [CHARTER-TEMPLATE.md](../_DesignSystem/CHARTER-TEMPLATE.md) — template canônico (com bloco novo W30 `smoke_pos_merge` + `ux_targets`)
- [RUNBOOK-charters-s4-ativacao.md](../_DesignSystem/RUNBOOK-charters-s4-ativacao.md) — workflow draft → live
- ADR 0164 — Screen Review PDCA (W30-A pendente)
- ADR 0101 — Sistema Charter-Capterra
- ADR 0094 — Constituição V2 §princípio #3 Charter > Spec
- Skill `charter-first` (Tier A) · `charter-write` (Tier C) · `tela-smoke-pos-merge` (Tier B — W30)
- Command `admin:ui-catalog-generate` ([Modules/Admin/Console/Commands/ScreenCatalogGenerateCommand.php](../../../Modules/Admin/Console/Commands/ScreenCatalogGenerateCommand.php))

## Estatísticas

- Total telas: 4
- Approved: 0
- Pending Wagner: 2
- Rejected: 0
- Sem charter: 1
- Sem review: 2

## Notas operacionais

**Schedule:** daily 09:30 BRT em `app/Console/Kernel.php` (depois cron smoke 09:00 BRT pra capturar telas mergidas durante a noite). Wagner monitora pendências via `/copiloto/admin/screen-review` (Admin/ScreenReview.tsx) ou via este catálogo.

**Pattern auto-update:** comando varre `resources/js/Pages/Admin/**/*.tsx` recursivamente, exclui `_components/` + `_lib/` + `*.test.tsx`, correlaciona com `.charter.md` + `.review.md` irmãos, e regenera tabela completa. Idempotente — pode rodar N vezes sem efeito colateral (overwrite final).

**Multi-tenant Tier 0:** governance é repo-wide intencional (ADR 0093 §"Governance Wagner-only"). Catálogo UI é estrutural (filesystem), não DB scoped — sem `business_id`.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-17 | W30 Agent D | UI-CATALOG.md seed criado pra Admin (4 telas: GovernanceV4 / GovernanceV4Dashboard / Index / ScreenReview). Próximas gerações automáticas via `php artisan admin:ui-catalog-generate Admin` daily 09:30 BRT. |
>>>>>>> origin/main
