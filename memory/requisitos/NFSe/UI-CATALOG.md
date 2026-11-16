# NFSe — UI Catalog (auto-gerado bulk W31-10)

> **Ultima atualizacao:** 2026-11-16 (contagem refeita à mão — auditoria NFSe, lote 5.2; a geração de 2026-05-17 é a W31-10)
> **Gerado em 2026-05-17; o gerador não está no repo** (era `/tmp/generate_catalogs.sh`, fora do git). Em 2026-11-16 os números foram refeitos à mão contra `resources/js/Pages/Nfse/`.
> Pages dir: `resources/js/Pages/Nfse/`

## Resumo

- Telas Inertia tsx: **3**
- Charters `.charter.md`: **3** (as 3 telas; todos `status: draft`) · casos `.casos.md`: **3**
  - _Em 2026-05-17 eram 0 charters._
- Blade legacy do modulo (Modules/<X>/Resources/views): **0**

## Telas Inertia tsx

| Tela | Charter | Casos | review.md | Status | Smoke pendente |
|---|:---:|:---:|:---:|---|:---:|
| `Emitir.tsx` | draft | sim | - | awaiting-smoke-browser | sim |
| `Index.tsx` | draft | sim | - | awaiting-smoke-browser | sim |
| `Show.tsx` | draft | sim | - | awaiting-smoke-browser | sim |


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
  - `awaiting-smoke-browser` — charter `draft` aceito, falta smoke (a doutrina de teste vigente é a [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md), que superseded a antiga 0101-tests)
  - `review-pending` — tela em revisao por Wagner
  - `reviewed-no-charter` — review.md existe mas charter ausente (regularizar)
  - `live` — charter status `live` + smoke validado biz=1

## Como regenerar

O gerador original (`bash /tmp/generate_catalogs.sh`) nunca esteve no repo e `php artisan ui:catalog-generate` não existe. Para o estado vivo por tela, use as portas derivadas:

```bash
npm run screen-coverage:report
```

```bash
npm run casos:report
```

---
Gerado por W31-10 (bulk-screen-review-r1, areas isoladas, sem git ops).
