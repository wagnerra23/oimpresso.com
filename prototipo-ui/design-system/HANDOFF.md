# HANDOFF — espelho → repo

> **Para quem:** Claude Code (ou qualquer agente) trabalhando em `wagnerra23/oimpresso.com`, e para quem edita este espelho.
> **Baseline:** reset em **2026-08-31**, contra `main` @ `84b62eb785e8`. Tudo que estava aqui antes está em [`arquivo/HANDOFF-ate-2026-08-24.md`](arquivo/HANDOFF-ate-2026-08-24.md) — não é leitura necessária.
> **Direção:** `git` é SSOT (ADR 0239). Este projeto é espelho derivado. O que sai daqui entra como **proposta auditada**; **[W] clica o merge**.

---

## Por que este arquivo foi resetado

A versão anterior tinha virado catálogo da própria bagunça: uma seção "Errata de paths" explicando quais caminhos citados no documento não existiam mais, um mapa de ~40 componentes cuja maioria nunca fora reconferida, e regras misturando três épocas. Documento que precisa de errata sobre si mesmo pede reescrita, não emenda.

**A regra do reset:** aqui só entra o que é vigente **e** verificado. Afirmação sobre o `main` sem `arquivo:linha` medido não entra — vai pra §5 como não-verificada. Regra morta não é anotada como morta; é apagada.

---

## 1. Ordem de leitura

| # | Abra | Por quê |
|---|---|---|
| 1 | este arquivo | regras, mapa verificado, DoD |
| 2 | [`README.md`](README.md) | fundações visuais, voz PT-BR, iconografia |
| 3 | `colors_and_type.css` + `cockpit_domains.css` | tokens espelhados verbatim do git |
| 4 | `templates/<slug>/<Slug>.dc.html` | a tela desenhada |
| 5 | no repo: `CLAUDE.md` → `memory/requisitos/_DesignSystem/PRE-MERGE-UI.md` | canon que manda no merge |

---

## 2. Regras duras (violar = regressão, não estilo)

1. **Entrega repo-nativa** — Tailwind 4 + tokens existentes. Proibido `.om-*`, CSS cru do espelho, `#hex`/`oklch()` literal em arquivo de módulo.
2. **Leu o `main` antes de afirmar.** Toda afirmação sobre o código cita **arquivo + linha medido por ferramenta**. Linha citada de memória é defeito — se não dá pra medir, cite arquivo e símbolo, sem número.
3. **Canal é o repo.** Bloco de handoff em `memory/reference/prototipo-ui/COWORK_NOTES.md`. Sem URL efêmera como canal.
4. **Contrato mora no elemento, não num wrapper.** `data-contract`, `aria-label` e recuo lateral vão na própria `<nav>`/`<table>`/`<section>`. `<div>` só pra pendurar atributo é regressão: o atributo deixa de descrever o elemento que nomeia, e o nó extra entra na chain de overflow (§2.11).
5. **Sidebar é PRETA (dark-fixo) nos dois modos.** Fonte: bloco `Sidebar — DARK FIXO` em `resources/css/cockpit.css`.
6. **Primary é roxo** `oklch(0.55 0.15 295)`.
7. **PT-BR** em label, copy, erro, commit, PR. Código em inglês; domínio em PT.
8. **Componente vem do shared/ui** — não reinventa o que o §4 já mapeia.
9. **`localStorage` prefixado** `oimpresso.<modulo>.*` / `oimpresso.cockpit.*`.
10. **Ícone só `lucide-react`.** Sem emoji em UI de produto — emoji só no `FeatureGrid` de marketing.
11. **Um `<main>` por documento** e **chain de overflow respeitada**: nó `flex-1` em coluna precisa de `h-full` **ou** pai `flex flex-col min-h-0`.
12. **Sem gradiente decorativo 135deg.** Status badge = dot + texto colorido + tinta ≤10%, nunca `bg-fill` sólido/pastel.
13. **Multi-tenant Tier 0 IRREVOGÁVEL** — query com `business_id` global scope; job recebe `$businessId` no constructor.
14. **Regressão é inaceitável.** Score baixou → pare, reporte, não sobrescreva baseline.

---

## 3. Telas deste espelho → alvo no `main`

| Template daqui | Alvo no repo | Estado |
|---|---|---|
| `templates/clientes-crm/` | `Pages/Cliente/Index.tsx` (+ `_drawer/`, `_show/`) | existe — diff, não criar |
| `templates/financeiro/` | `Pages/Financeiro/Unificado/` | existe — diff; ler `LICOES_F3_FINANCEIRO_REJEITADO.md` antes |
| `templates/oficina-auto/` | `Pages/OficinaAuto/ServiceOrders/` + `Vehicles/` | existe — diff |
| `templates/pt-07-os-detail/` | detalhe de OS em `OficinaAuto/ServiceOrders/` | existe |
| `templates/pt-01-lista/` | padrão, não tela — `Cliente/Index`, `Produto/Index`, `Sells/Index` | canon PT-01 |
| `templates/pt-05-dashboard/` | `Pages/Home/Index.tsx`, `Pages/governance/Dashboard.tsx` | canon PT-05 |
| `templates/atendimento/` | sem par 1:1 (adjacentes: `Pages/Jana/`, `Pages/Whatsapp/_components/`) | tela nova → exige charter |

---

## 4. Mapa componente DS → arquivo do repo

`reusar` = existe, use como está · `estender` = existe, falta prop · `criar` = não existe no `main`.

### Verificado em 2026-08-31 (com linha medida)

| DS | Arquivo no `main` | Ação |
|---|---|---|
| `TabBar` | `shared/PageHeaderTabs.tsx` | **estender** — raiz sem `...rest` (`:163`), `aria-label` cravado (`:202`), padding cravado (`:237`). `icon` (`:69`) e `count`/`badge` (`:76`) **já existem** |
| `PageHeader` | `Components/PageHeader/PageHeader.tsx` (canon v3.8) | **`leading` reusar** (`:58`, render `:123`) · `subnav` reusar (`:66`) · `context` e `freshness` **criar** |
| — | `shared/PageHeader.tsx` | **NÃO USAR** — `@deprecated` CONGELADO (`:9`); ratchet `pageheader-gate` falha o CI se tela nova importar |

### Herdado do mapa antigo — **não reconferido**

Os pares abaixo vêm do mapa anterior e valiam em 2026-08-24. Dois deles estavam errados quando foram medidos nesta rodada, então trate a lista como **pista, não como fato**: confira o arquivo antes de citar.

`Button`→`ui/button.tsx` · `Input`→`ui/input.tsx`+`ui/label.tsx` · `Select`→`ui/select.tsx` · `Switch`/`Checkbox`/`RadioGroup`→`ui/*.tsx` · `Modal`→`ui/dialog.tsx` · `Drawer`→`ui/sheet.tsx` · `DropdownMenu`/`Tooltip`/`Alert`/`Skeleton`/`Command`→`ui/*.tsx` · `Avatar`→`ui/avatar.tsx` · `TagChip`→`ui/badge.tsx` · `StatusBadge`→`shared/StatusBadge.tsx` · `KpiCard`→`shared/KpiCard.tsx` · `DataGrid`→`shared/DataTable.tsx` · `BulkBar`→`shared/BulkActionBar.tsx` · `EmptyState`→`shared/EmptyState.tsx` · `PlacaVeiculo`→`shared/MercosulPlate.tsx` · `FilterChip`→`ui/badge.tsx`+`shared/PageFilters.tsx` · `AppSidebar`→`Layouts/AppShellV2.tsx` · `Toast`→pacote `sonner`.

**Sem par no `main` (criar):** `Progress` · `DatePicker` · `PeriodBar` · `Breadcrumb` · `Pagination` · `FsmStepper` · `Chart` · `TaskCard`/`BoardColumn` · `Logo` (ativos existem) · print-craft (`ProofFrame`, `Dimension`, `ProofStrip`, `RegistrationMark`, `PresenterMode`).

---

## 5. Não verificado — confirmar antes de agir

O que este espelho **afirma sem ter medido**. Nada aqui deve virar código sem checagem.

1. **Os pares herdados do §4** — 40+ linhas do mapa antigo, das quais só 3 foram remedidas.
2. **Paridade entre `PageHeader.leading` e o antigo `cli-pagehead`** — esse arquivo não está no espelho e não aparece no `main` por busca de código nem de path.
3. **Os seis mini-DS** (`AcessosDS` 20 consumidores · `PBUI` 15 · `ModuloPadrao` 12 · `HrmUI` 7 · `CatchupUI` · `PontoUI`) — **não existem com esses nomes no `main`**. A triagem está bloqueada no mapeamento nome → pasta.
4. **Comportamento do `ds-mirror-drift.mjs`** — sei o papel (sentinela de divergência), não li o código.

---

## 6. Tokens (VALOR:0)

Nascem em `resources/css/tokens/*.tokens.json` (DTCG) → Style Dictionary → `_generated-*.css`. O espelho recebe cópia montada, **nunca transcrita à mão**.

```bash
npm run tokens:build
node scripts/design-sync/ds-push.mjs            # monta e valida (sai !=0 se VALOR>0)
node scripts/design-sync/ds-push.mjs --write    # atualiza o design-system canônico
node scripts/governance/ds-mirror-drift.mjs     # sentinela de divergência
```

---

## 7. DoD por máquina

```bash
node scripts/design/ds-guard.mjs <arquivos tocados>
npm run typecheck && npm run lint:baseline:check && npm run stylelint:baseline:check
npm run ds:canon:check && npm run foundation:check && npm run conformance:check
npm run pt:conformance:check && npm run design:coverage:check
npm run a11y:check && npm run dominio:check && npm run no-mock:check
npm run test && npm run visreg:pixel
```

Checklist humano: `memory/requisitos/_DesignSystem/PRE-MERGE-UI.md`. **Item que falha → comunica o [W], não corrige silenciosamente.**

---

## 8. Bloco a colar em `memory/reference/prototipo-ui/COWORK_NOTES.md`

```md
## HANDOFF <data> · <tela> · espelho DS v6
- fonte visual: <projeto>/templates/<slug>/<Slug>.dc.html
- tokens: colors_and_type.css + cockpit_domains.css (VALOR:0 em <data>)
- alvo: <arquivo real no main>
- reusar: <lista>   estender: <lista>   criar: <lista + ADR necessária?>
- auditoria do main (linha medida no ref <sha>): <arquivo:linha> …
- NÃO verificado: <o que ficou por confirmar>
- gates: ds-guard ✓ conformance ✓ pt:conformance ✓ a11y ✓ visreg ✓
- pendências pra [W]: <decisão de produto / token novo / componente novo>
```

**Pronto quando:** §7 verde, bloco commitado, PR ≤300 linhas, 1 intent, conventional commit com `Refs:`.

---

## 9. O que este espelho não decide

Token novo · componente novo no canon · produto · merge. O espelho **propõe** (com ADR proposta quando for canon), nunca numera nem canoniza sozinho.

---

## Handoffs abertos

- [`HANDOFF-2026-08-31-tabbar-pageheader.md`](HANDOFF-2026-08-31-tabbar-pageheader.md) — TabBar sem wrapper + `context`/`freshness` no header canon.
