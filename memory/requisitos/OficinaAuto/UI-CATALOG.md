# OficinaAuto — UI Catalog (auto-gerado bulk W31-10)

> **Ultima atualizacao:** 2026-05-17 (W31-10)
> **Auto-regeneravel.** Manter versao; reescrever via gerador (nao editar manual em campos de auto-status).
> Pages dir: `resources/js/Pages/OficinaAuto/`

## Resumo

- Telas Inertia tsx: **10**
- Charters `.charter.md`: **6** (cobertura: 60%)
- Blade legacy do modulo (Modules/<X>/Resources/views): **0**

## Telas Inertia tsx

| Tela | Charter | review.md | Status | Round | Smoke pendente |
|---|:---:|:---:|---|---:|:---:|
| `AprovacaoPublica.tsx` | OK | - | awaiting-smoke-browser | 1 | sim |
| `ProducaoOficina/Index.tsx` | OK | - | live | 2 | opcional |
| `ServiceOrders/Create.tsx` | OK | - | live | 2 | opcional |
| `ServiceOrders/Edit.tsx` | - | - | legacy | 0 | sim |
| `ServiceOrders/Index.tsx` | OK | - | live | 2 | opcional |
| `ServiceOrders/Show.tsx` | OK | - | live | 2 | opcional |
| `Vehicles/Create.tsx` | - | - | legacy | 0 | sim |
| `Vehicles/Edit.tsx` | - | - | legacy | 0 | sim |
| `Vehicles/Index.tsx` | OK | - | live | 2 | opcional |
| `Vehicles/Show.tsx` | - | - | legacy | 0 | sim |


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
