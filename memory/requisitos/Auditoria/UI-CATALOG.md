# Auditoria — UI Catalog

> **Ultima atualizacao:** 2026-11-09, a mao. O gerador bulk W31-10 de 2026-05-17 nao esta versionado neste repo.
> Pages dir: `resources/js/Pages/Auditoria/`

## Resumo

Medido em 2026-11-09 (`ls resources/js/Pages/Auditoria/`).

- Telas Inertia tsx: **2**
- Charters `.charter.md`: **2** (ambos `status: draft`)
- Casos `.casos.md`: **2** (5 UCs)
- Blade legacy do modulo (Modules/<X>/Resources/views): **0**

## Telas Inertia tsx

| Tela | Charter | Casos | review.md | Status | Smoke pendente |
|---|:---:|:---:|:---:|---|:---:|
| `Detail.tsx` | OK | OK | - | awaiting-smoke-browser | sim |
| `Index.tsx` | OK | OK | - | awaiting-smoke-browser | sim |


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
  - `awaiting-smoke-browser` — charter `draft` aceito, falta smoke em tela. Teste automatizado usa o tenant ficticio 98 ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md))
  - `review-pending` — tela em revisao por Wagner
  - `reviewed-no-charter` — review.md existe mas charter ausente (regularizar)
  - `live` — charter status `live` + smoke validado

## Portas vivas

```bash
npm run screen-coverage:report
```

```bash
npm run casos:report
```
