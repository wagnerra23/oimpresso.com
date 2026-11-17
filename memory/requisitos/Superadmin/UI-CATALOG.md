# Superadmin — UI Catalog (auto-gerado bulk W31-10)

> **Ultima atualizacao:** 2026-11-16 (contagem refeita à mão — auditoria Superadmin, lote L6; a geração de 2026-05-17 é a W31-10)
> **Gerado em 2026-05-17; o gerador não está no repo** (era `/tmp/generate_catalogs.sh`, fora do git). Em 2026-11-16 os números foram refeitos à mão contra `git ls-files Modules/Superadmin/Resources/js/Pages`.
> Pages dir: `Modules/Superadmin/Resources/js/Pages/` (namespaces Inertia `superadmin` e `Site`). Em 2026-05-17 era `resources/js/Pages/superadmin/`; a Page saiu para dentro do módulo em 2026-08-19.

## Resumo

- Telas Inertia tsx: **12**
- Charters `.charter.md`: **12** (1 `live` — Dashboard; 11 `draft`) · casos `.casos.md`: **12**
  - _Em 2026-05-17 eram 2 telas e 0 charters._
- Blade legacy do modulo (Modules/<X>/Resources/views): **17** arquivos `.blade.php` (as 18 sem renderizador foram apagadas no #10967; o renderizador de cada partial restante não foi conferido aqui). Telas servidas: pagamento sem a flag `useV2SuperadminPagar`, `/pricing/old` e `/page/{slug}`; o resto são partials e modais de `subscription/`

## Telas Inertia tsx

| Tela | Charter | Casos | review.md | Status | Smoke pendente |
|---|:---:|:---:|:---:|---|:---:|
| `superadmin/Dashboard/Index.tsx` | live | sim | - | live | não medido |
| `superadmin/Negocios/Index.tsx` | draft | sim | - | awaiting-smoke-browser | sim |
| `superadmin/Pacotes/Index.tsx` | draft | sim | - | awaiting-smoke-browser | sim |
| `superadmin/Assinaturas/Index.tsx` | draft | sim | - | awaiting-smoke-browser | sim |
| `superadmin/Comunicador/Index.tsx` | draft | sim | - | awaiting-smoke-browser | sim |
| `superadmin/Configuracoes/Index.tsx` | draft | sim | - | awaiting-smoke-browser | sim |
| `superadmin/Paginas/Index.tsx` | draft | sim | - | awaiting-smoke-browser | sim |
| `superadmin/Usuario360/Index.tsx` | draft | sim | - | awaiting-smoke-browser | sim |
| `superadmin/Usuario360/Show.tsx` | draft | sim | - | awaiting-smoke-browser | sim |
| `superadmin/MinhaAssinatura/Index.tsx` | draft | sim | - | awaiting-smoke-browser | sim |
| `superadmin/Pagar/Index.tsx` | draft | sim | - | awaiting-smoke-browser | sim |
| `Site/Pricing.tsx` | draft | sim | - | awaiting-smoke-browser | sim |


## Blade legacy restantes

`git ls-files Modules/Superadmin/Resources/views` (2026-11-16): `pricing/index` (`/pricing/old`), `pages/show` (`/page/{slug}`), `subscription/pay` + 9 partials `pay_*`/`packages`, 3 modais de `subscription/` e 2 partials de `layouts/`.

## Migracao planejada (MWART — [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md))

- Estimativa de telas Blade alvo MWART: **3** (pagamento: já tem a Page `Pagar` atrás de flag; `/pricing/old`; `/page/{slug}`)
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
