# Ponto — UI Catalog (auto-gerado bulk W31-10)

> **Ultima atualizacao:** 2026-05-17 (W31-10)
> **Auto-regeneravel.** Manter versao; reescrever via gerador (nao editar manual em campos de auto-status).
> Pages dir: `resources/js/Pages/Ponto/`

## Resumo

- Telas Inertia tsx: **20**
- Charters `.charter.md`: **0** (cobertura: 0%)
- Blade legacy do modulo (Modules/<X>/Resources/views): **26**

## Telas Inertia tsx

| Tela | Charter | review.md | Status | Round | Smoke pendente |
|---|:---:|:---:|---|---:|:---:|
| `Aprovacoes/Index.tsx` | - | - | legacy | 0 | sim |
| `BancoHoras/Index.tsx` | - | - | legacy | 0 | sim |
| `BancoHoras/Show.tsx` | - | - | legacy | 0 | sim |
| `Colaboradores/Edit.tsx` | - | - | legacy | 0 | sim |
| `Colaboradores/Index.tsx` | - | - | legacy | 0 | sim |
| `Configuracoes/Index.tsx` | - | - | legacy | 0 | sim |
| `Configuracoes/Reps.tsx` | - | - | legacy | 0 | sim |
| `Dashboard/Index.tsx` | - | - | legacy | 0 | sim |
| `Escalas/Form.tsx` | - | - | legacy | 0 | sim |
| `Escalas/Index.tsx` | - | - | legacy | 0 | sim |
| `Espelho/Index.tsx` | - | - | legacy | 0 | sim |
| `Espelho/Show.tsx` | - | - | legacy | 0 | sim |
| `Importacoes/Create.tsx` | - | - | legacy | 0 | sim |
| `Importacoes/Index.tsx` | - | - | legacy | 0 | sim |
| `Importacoes/Show.tsx` | - | - | legacy | 0 | sim |
| `Intercorrencias/Create.tsx` | - | - | legacy | 0 | sim |
| `Intercorrencias/Index.tsx` | - | - | legacy | 0 | sim |
| `Intercorrencias/Show.tsx` | - | - | legacy | 0 | sim |
| `Relatorios/Index.tsx` | - | - | legacy | 0 | sim |
| `Welcome.tsx` | - | - | legacy | 0 | sim |


## Blade legacy restantes

`Modules/Ponto/Resources/views/` — 26 arquivos

## Migracao planejada (MWART — [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md))

- Estimativa de telas Blade alvo MWART: **26**
- Prioridade: **media — Blade legacy presente (26)**
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
