---
id: requisitos-fiscal-fiscal-cockpit-visual-comparison
tela: Fiscal/Cockpit
url: /fiscal
status: approved
approver: wagner
approved_at: 2026-05-20
prototype_source: "prototipo-ui/.../fiscal-page.jsx §8 FiscalCockpit"
implementation: resources/js/Pages/Fiscal/Cockpit.tsx
adr: 0107
---

# Visual Comparison — Fiscal/Cockpit (PR #2 Wave)

## Blueprint Cowork

`prototipo-ui/.../fiscal-page.jsx §8 FiscalCockpit` + `fiscal-data.jsx::FISCAL_KPIS/SPARKLINES/FISCAL_ALERTS` (R#1 KB-9.75).

## Approval

Wagner aprovou Wave consolidada (Cockpit + NFS-e + Eventos) — 2026-05-20.

## 8 dimensões

### 1. Layout grid

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| KPIs grid | grid-template-columns: 1.4fr repeat(5, 1fr) | ✅ idem | ✅ |
| Alertas card branco | bg white + border + padding 14px | ✅ `.fx-alerts` | ✅ |
| Quick links 3 cols | grid-template-columns: repeat(3, 1fr) | ✅ idem | ✅ |

### 2. Tipografia

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| KPI big number | 22px font-weight 700 | ✅ `.fx-kpi b` | ✅ |
| KPI small label | 10.5px uppercase + tracking | ✅ idem | ✅ |
| Alert título | 12.5px font-weight 600 | ✅ idem | ✅ |

### 3. Densidade

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Gap entre KPIs | 8px | ✅ idem | ✅ |
| Padding KPI card | 12px 14px | ✅ idem | ✅ |
| Alertas inset border-left 3px | inset esquerdo colorido por level | ✅ `.fx-alert.{crit,warn,info}` | ✅ |

### 4. Iconografia

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Icon hero KPI | sparkline SVG inline branco | ❌→✅ ver nota A | ⚠️→✅ |
| Icon alert | ShieldAlert/Shield/Receipt/RefreshCw lucide | ✅ ICON map dinâmico | ✅ |
| Icon quick card | Receipt/FileText/Archive/Shield etc. lucide | ✅ idem | ✅ |

### 5. Cores/Estados

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| KPI hero (emitidas) bg fis | rosa fiscal saturado | ✅ `.fx-kpi.hero` | ✅ |
| KPI rejeitadas pulse | animação box-shadow infinite | ✅ `@keyframes fx-pulse` | ✅ |
| Alert color tone | bad/warn/info via border-left | ✅ idem | ✅ |

### 6. Animações

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Pulse rejeitadas | 2.5s infinite (oklch bad 50%→0%) | ✅ idem | ✅ |
| Hover quick card | border-color → fis transition .12s | ✅ idem | ✅ |
| Hover alert | bg fx-bg-2 transition .12s | ✅ idem | ✅ |

### 7. Estados condicionais

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Alertas hide se vazio | `alerts.length > 0` render condicional | ✅ idem | ✅ |
| Pulse só se rejeitadas > 0 | classe condicional | ✅ idem | ✅ |
| Cert sem dados → "—" | fallback null | ✅ idem | ✅ |
| Quick cards disabled (4/6/7) | opacity 0.55 | ✅ inline style | ✅ |

### 8. Componentes reutilizados

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| FxShell wrapper | sub-nav + cheats + atalhos 1-7 | ✅ `_components/FxShell.tsx` | ✅ |
| MiniSparkline SVG | `<polyline>` (não `path + circle`) | ❌→✅ ver nota A | ⚠️→✅ |
| brl helper | format moeda | ✅ `_lib/fiscal-helpers.ts` | ✅ |

> **Nota A — ⚠️ correção de veredito, 2026-09-04.** As duas linhas acima diziam **✅** e citavam
> um `MiniSparkline component` que **nunca existiu**. Medido em `origin/main` (tip `d23bc3df34`)
> por dois oráculos independentes com controle positivo — `rg --hidden -g '!.git/**'` e
> `git grep` —, o identificador `MiniSparkline` aparecia em **2 arquivos, nenhum de código**:
> este doc e `governance/sdd-verification-ledger.json`. Em `resources/js/`, **zero**. No mesmo
> tip, `grep -c polyline resources/js/Pages/Fiscal/Cockpit.tsx` dava **0** e a prop `sparklines`
> nunca era desestruturada.
>
> O registro original fica **preservado**: ele é o fato datado do que se afirmou em 2026-05-20,
> e apagá-lo esconderia justamente o mecanismo do defeito. **Este `✅` é a causa de o gap ter
> ficado invisível por ~3,5 meses** — sendo este doc o dono do inventário por tela, quem o
> consultasse concluiria que a peça estava pronta e não olharia o `.tsx`. Um refutador
> adversarial já havia registrado a discrepância no `sdd-verification-ledger.json` (append-only,
> por isso não tocado aqui); o que faltou foi ela voltar para cá.
>
> **Fechado em 2026-09-04** pelo item A2 (autorizado por [W]): `_components/RibbonSpark.tsx`,
> porte 1:1 do `FxSpark` de `fiscal-page.jsx:80-84`, aplicado aos **3** KPIs que o protótipo
> marca (`:114-116`). Contrato em `Cockpit.casos.md` **UC-FCKP-12**, lane
> `fiscal-cockpit-sparklines-gate.yml` — agora existe máquina vigiando, e um `✅` sem recibo
> volta a ficar vermelho. Detalhe de forma: o protótipo desenha `<polyline>`, **sem** o
> `circle` de endpoint que a linha original descrevia.

## Histórico

- **2026-05-20** — Wave consolidada PR #2 (Cockpit + NFS-e + Eventos). Implementação fiel ao protótipo Cowork.
- **2026-09-04** — Item A2 ([W]): as sparklines passam a ser DESENHADAS (`_components/RibbonSpark.tsx`, 3 KPIs, UC-FCKP-12 + lane própria). Corrigido o veredito de 05-20 nas duas linhas do `MiniSparkline` — ver **Nota A**. O Goal #2 do charter também cedeu de *4 KPIs* para *3*, pela cadeia de FORMA (ADR UI-0029).

## Medição de runtime — 2026-11-23 · produção (oimpresso.com) × espelho · tema escuro nos dois

> Prod medida no Chrome logado em `https://oimpresso.com/fiscal`; espelho por
> `design-diff-lote.mjs --lado design --tela Fiscal/Cockpit --tema dark`. A MESMA sonda
> (`design-diff.mjs --probe`) nos dois lados, provada idêntica por SHA-256 dentro da página da prod
> (`358fd0f0…ebe20`, 26.291 bytes). Papéis lidos no DOM dos dois lados e gravados em
> `governance/design/targets/roles/Fiscal--Cockpit.json` (antes não havia papéis e a sonda media
> `kpi = 0` no protótipo). O `--compare` do dono NÃO rodou: a extensão do Chrome redige parte da
> saída (dados de cliente), então o `prod.json` completo não saiu da página. Os números abaixo são
> os campos que saíram inteiros; a assinatura de texto da prod não foi gravada (tem dado de cliente).
>
> ⚠️ **Tema misturado na prod:** só o `<html>` foi posto em escuro. Peso, tamanho, borda, fonte mono e
> forma das pílulas não dependem do tema e valem; COR da prod vale só onde o token vem do `<html>`
> (`.dark`), que é o caso do botão primário e dos tokens `--fx-*` do Fiscal.

| dim | campo | produção | protótipo | veredito |
|---|---|---|---|---|
| D2 | KPIs: quantidade, alinhamento, valor | 6 · start · 18px | 6 · start · 18px | IGUAL |
| D4 | título | 22px · 700 | 22px · 600 | **DÍVIDA A FECHAR** (já registrada em 11-05, segue aberta) |
| D6 | botão primário (Emitir) no escuro | fundo `oklch(0.68 0.16 295)`, texto branco | fundo `oklch(0.70 0.15 295)`, texto `oklch(0.14 0.02 295)` | **DÍVIDA A FECHAR** — a diferença grande é o texto (branco × escuro). ⚠️ Errata: a 1ª redação desta linha dizia `≈0.55`, lido numa medição com tema misturado (só o `<html>` em escuro; o `.cockpit` segue o `data-theme` do usuário e ficou claro). Remedido com o tema aplicado como o app faz: 0.68 0.16 |
| D7 | chips de visão | 1 linha | 1 linha | IGUAL |
| D8 | tabela | `auto` · 8 linhas · 0 com estado | `auto` · 8 linhas · 0 com estado | IGUAL |
| D8 | col 1 (Tipo) | pílula 99px sem borda, 12px 600 | texto mono 11px 400, sem pílula | **DÍVIDA A FECHAR** |
| D8 | col 2 (Número) | mono 14px 700, 2 blocos | mono 14px 700, 2 blocos | IGUAL |
| D8 | col 3 (Cliente/chave) | link, 13px 700, sem mono | botão, 14px 700, com mono (chave) | **DÍVIDA A FECHAR** — tamanho e chave em mono |
| D8 | col 4 (Status) | pílula 99px **sem borda**, 12px 500 | pílula 999px **com borda**, 12px 600 | **DÍVIDA A FECHAR** — borda e peso |
| D8 | col 5 | 13px 400 | 13px 400 | IGUAL |
| D8 | col 6 (ações/valor) | 11px 600, sem mono | 11px 400, mono | **DÍVIDA A FECHAR** |
| D1 | trocar a visão salva | 0 requisição de rede (filtro local) | — | IGUAL (sem recarga) |
| — | bloco "Débitos conhecidos" | Alert do DS: padding 12/16, raio 12px, título 14px 500, texto 14px, com selo | `.fx-debito`: padding 10/14, raio 8px, fundo `--bg-2`, título `--fs-3` (12,5px), texto `--fs-2` (11,5px), sem selo | **DÍVIDA A FECHAR** — o `DebitosConhecidos.tsx` declara o Alert como "delta consciente", o que a UI-0029 não aceita no eixo forma |

- `saude` da prod: 7 de 7 tokens do DS ausentes no `:root` (`--accent`, `--pos`…). A prod declara os
  tokens dentro do `.cockpit`, não no `:root`; não é tela quebrada, mas é por isso que a sonda não
  acha os tokens na raiz.
- No espelho, o Cockpit não desenha o bloco de débitos (só as sub-telas desenham); a linha acima
  compara o item com a regra CSS `.fx-debito` do `fiscal-page.css`.
- Larguras de coluna diferem, mas dependem do dado (`table-layout: auto` nos dois): ficam como dado, sem veredito.

## Medição de runtime — 2026-11-05 · veredito DIVERGE (bug) · 1

> Substitui, como estado atual, a rodada de 2026-09-08 logo abaixo. Aquela não chegou a comparar
> nada (staging com deploy defasado, temas diferentes) e caducou; fica como fato datado.

| eixo | resultado |
|---|---|
| comando | `node scripts/design/design-diff-lote.mjs --incluir-comparadas --tema light --tela Fiscal/Cockpit --base-url http://127.0.0.1:8000 --user-id <admin do biz seedado>` |
| onde | `design-smoke-ci.yml` por `workflow_dispatch` no branch de medição `claude/fiscal-lote5-medir-nao-mergear` (run [42085947187](https://github.com/wagnerra23/oimpresso.com/actions/runs/42085947187)), app efêmero buildado de `main` @ `7d997591d7`, seeds Visreg, login pelo `/_visreg-login` |
| tema | `light` forçado nos dois lados (`sameTheme: true`) |
| âncora | `node scripts/design/ancora.mjs Fiscal/Cockpit` → `prototipo-ui/cowork/Wagner/fiscal-page.jsx` |
| veredito | **DIVERGE (bug) · 1** |

| dim | campo | produção | protótipo | veredito |
|---|---|---|---|---|
| D4 | título `font-weight` | 700 | 600 | **DÍVIDA A FECHAR** — Δ 100 |
| D9 | subnav (fora da sonda, lido da assinatura de texto) | 7 abas | 9 abas (+ "MDF-e · CT-e", + "Tributação") | **ACHADO, decisão [W]** — o `fiscal-subnav.contract.json` e o `paginas-fiscais.tsx` têm 7; o `FX_TABS` do protótipo tem 9 |

- As regiões D9 (`data-contract`) quase não se casam por **nome**: a produção expõe `fiscal-subnav`, `debitos-conhecidos`, `sb-*` e as âncoras `fiscal-*`, o protótipo `procedencia`, `tabela-*`, `toolbar-*`. O comparador as registra como `SEM-DADO`, não como divergência.
- A produção mostrou o banner de consentimento de cookies por cima da tela; ele entra no texto medido, não nos estilos medidos.
- O token do shell do Cockpit não saía derivado (`sem rota derivável`, duas fontes no application-report); a rodada usou o override `{"token": "fiscal"}`, que é o que o `app.jsx` declara para a view `notas`.

## Medição de runtime — 2026-09-08 · veredito NÃO MEDI (Onda 7)

> ⚠️ **Caducou em 2026-11-05** — ver a medição acima. Preservado como fato datado.


> Registro do passo 3 do método da Onda 7. O `status: approved` do frontmatter é de
> **2026-05-20** e NÃO foi revisado aqui — auto-declarado não prova paridade de hoje, e
> esta medição não conseguiu substituí-lo. O que segue é o recibo do que foi medido.

| eixo | resultado |
|---|---|
| comando | `node scripts/design/design-diff-lote.mjs --tela Fiscal/Cockpit --base-url https://staging.oimpresso.com` |
| veredito | **NÃO MEDI** (`rc=2`) — portão D0 recusou: identidade da view não provada |
| lado design | ✅ **renderizou** — 6/6 copies do contrato D0 presentes no render do espelho |
| lado prod | ❌ **não renderizou a tela** — o staging devolveu `RouteNotFoundException: Route [jana.acoes.index] not defined` (`Modules/Jana/Http/Controllers/DataController.php:295`) |
| causa | **deploy defasado do staging**, não divergência de fidelidade: a rota EXISTE no main (`Modules/Jana/Http/routes.php:157`). Quebra é geral — 4/4 telas de 3 módulos distintos (`/fiscal`, `/fiscal/nfe`, `/sells`, `/cliente`) devolvem a mesma exceção, porque o `MenuBuilder` do AppShell a consome |
| espelho (lado design) | fresco: bundle `2026-09-07T21:00Z` · `--preview-ds` COMPLETO · `ds-mirror-drift` 0 nos 4 temas · a âncora bate **sha256** com o manifesto do bundle |

⚠️ **Ao reabrir:** o D0 desta tela tem contrato (`governance/design/contracts/`), então a medição
volta a valer assim que o lado prod renderizar. Alinhar o tema com `--tema` — nesta rodada o
espelho veio `dark` e o alvo `light`, e comparar temas diferentes fabrica divergência falsa.


## Medição 2026-11-26 — protótipo × produção, mesma sonda

**Como foi medido.** A mesma função de `getComputedStyle` (31 propriedades por elemento) rodou nos dois
lados, com um mapa de papéis por lado (as classes diferem, o papel é o mesmo):

- **Protótipo:** `prototipo-ui/cowork/Wagner/oimpresso.com.html` servido localmente, rota `fiscal`, tema claro.
- **Antes:** `https://oimpresso.com/fiscal` (empresa WR2 Sistemas), mesma janela e largura.
- **Depois:** a mesma página de produção com a folha `fiscal-cockpit-*.css` servida **trocada pelo arquivo
  inteiro deste PR** e a marcação deste PR aplicada no DOM. É simulação no navegador, não deploy: o
  smoke real é depois do merge.

**Leitura da coluna final.** `✅ igual` = depois bate com o protótipo. `≠ dado` = altura ou presença que
depende do conteúdo da linha (o protótipo tem 3 alertas e nota autorizada na 1ª linha; produção tem 1
alerta e nota rejeitada). As pílulas de status e prazo também caem em linhas de tons diferentes; comparadas
pela **mesma classe** (`.fx-sefaz.bad`, `.fx-timepill.u-crit`) os valores batem 1 a 1.
Bordas de largura 0 e propriedades de flex em elemento `block` foram omitidas (não desenham nada).

### Tela Notas fiscais (/fiscal)

| Componente | Propriedade | Antes (produção) | Depois (PR) | Protótipo | |
|---|---|---|---|---|---|
| Página | gap | normal | 18px | 18px | ✅ igual |
| Página | line-height | 20.25px | 19.575px | 19.575px | ✅ igual |
| Página | padding-top | 18px | 22px | 22px | ✅ igual |
| Página | padding-right | 24px | 26px | 26px | ✅ igual |
| Página | padding-bottom | 0px | 44px | 44px | ✅ igual |
| Página | padding-left | 24px | 26px | 26px | ✅ igual |
| Cabeçalho | flex-wrap | nowrap | wrap | wrap | ✅ igual |
| Cabeçalho | align-items | center | flow-start | flow-start | ✅ igual |
| Cabeçalho | justify-content | normal | space-between | space-between | ✅ igual |
| Cabeçalho | gap | 16px | 14px | 14px | ✅ igual |
| Cabeçalho | line-height | 20.25px | 19.575px | 19.575px | ✅ igual |
| Cabeçalho | border-bottom-width | 1px | 0px | 0px | ✅ igual |
| Cabeçalho | padding-bottom | 14px | 2px | 2px | ✅ igual |
| Cabeçalho | height | 68.75px | 48.4219px | 48.4219px | ✅ igual |
| Título h1 | line-height | 33px | 25.3px | 25.3px | ✅ igual |
| Título h1 | letter-spacing | -0.33px | -0.44px | -0.44px | ✅ igual |
| Título h1 | height | 33px | 25.2969px | 25.2969px | ✅ igual |
| Subtítulo | tag | span | p | p | ✅ igual |
| Subtítulo | line-height | 18.75px | 18.125px | 18.125px | ✅ igual |
| Subtítulo | color | oklch(0.65 0.01 80) | oklch(0.5 0.01 80) | oklch(0.5 0.01 80) | ✅ igual |
| Subtítulo | margin-top | 2px | 3px | 3px | ✅ igual |
| Subtítulo | height | 18.75px | 18.125px | 18.125px | ✅ igual |
| Ações do topo | flex-wrap | nowrap | wrap | wrap | ✅ igual |
| Ações do topo | gap | 8px | 6px | 6px | ✅ igual |
| Ações do topo | line-height | 20.25px | 19.575px | 19.575px | ✅ igual |
| Ações do topo | height | 30px | 30.125px | 30.125px | ✅ igual |
| Pílula SEFAZ | gap | 4px | 5px | 5px | ✅ igual |
| Pílula SEFAZ | font-weight | 600 | 400 | 400 | ✅ igual |
| Pílula SEFAZ | line-height | 17.25px | 16.675px | 16.675px | ✅ igual |
| Pílula SEFAZ | color | oklch(0.55 0.13 145) | oklch(0.5 0.01 80) | oklch(0.5 0.01 80) | ✅ igual |
| Pílula SEFAZ | background-color | oklch(0.94 0.06 145) | oklch(1 0 none) | rgb(255, 255, 255) | ✅ igual |
| Pílula SEFAZ | border-top-width | 0px | 1px | 1px | ✅ igual |
| Pílula SEFAZ | border-top-color | oklch(0.92 0.006 90) | oklch(0.9 0.004 90) | oklch(0.9 0.004 90) | ✅ igual |
| Pílula SEFAZ | border-bottom-width | 0px | 1px | 1px | ✅ igual |
| Pílula SEFAZ | border-bottom-color | oklch(0.92 0.006 90) | oklch(0.9 0.004 90) | oklch(0.9 0.004 90) | ✅ igual |
| Pílula SEFAZ | border-left-width | 0px | 1px | 1px | ✅ igual |
| Pílula SEFAZ | border-left-color | oklch(0.92 0.006 90) | oklch(0.9 0.004 90) | oklch(0.9 0.004 90) | ✅ igual |
| Pílula SEFAZ | border-radius | 99px | 999px | 999px | ✅ igual |
| Pílula SEFAZ | height | 23.25px | 24.6719px | 24.6719px | ✅ igual |
| Botão Procedência | justify-content | center | normal | normal | ✅ igual |
| Botão Procedência | font-weight | 500 | 400 | 400 | ✅ igual |
| Botão Procedência | line-height | 18.75px | 18.125px | 18.125px | ✅ igual |
| Botão Procedência | color | oklch(0.22 0.01 80) | oklch(0.5 0.01 80) | oklch(0.5 0.01 80) | ✅ igual |
| Botão Procedência | border-radius | 5px | 6px | 6px | ✅ igual |
| Botão Procedência | padding-top | 0px | 5px | 5px | ✅ igual |
| Botão Procedência | padding-right | 12px | 10px | 10px | ✅ igual |
| Botão Procedência | padding-bottom | 0px | 5px | 5px | ✅ igual |
| Botão Procedência | padding-left | 12px | 10px | 10px | ✅ igual |
| Botão Procedência | height | 30px | 30.125px | 30.125px | ✅ igual |
| Botão Procedência | white-space | nowrap | normal | normal | ✅ igual |
| Botão Eventos | justify-content | center | normal | normal | ✅ igual |
| Botão Eventos | font-weight | 500 | 400 | 400 | ✅ igual |
| Botão Eventos | line-height | 18.75px | 18.125px | 18.125px | ✅ igual |
| Botão Eventos | color | oklch(0.22 0.01 80) | oklch(0.5 0.01 80) | oklch(0.5 0.01 80) | ✅ igual |
| Botão Eventos | border-radius | 5px | 6px | 6px | ✅ igual |
| Botão Eventos | padding-top | 0px | 5px | 5px | ✅ igual |
| Botão Eventos | padding-right | 12px | 10px | 10px | ✅ igual |
| Botão Eventos | padding-bottom | 0px | 5px | 5px | ✅ igual |
| Botão Eventos | padding-left | 12px | 10px | 10px | ✅ igual |
| Botão Eventos | height | 30px | 30.125px | 30.125px | ✅ igual |
| Botão Eventos | white-space | nowrap | normal | normal | ✅ igual |
| Botão Emitir | justify-content | center | normal | normal | ✅ igual |
| Botão Emitir | font-weight | 600 | 400 | 400 | ✅ igual |
| Botão Emitir | line-height | 18.75px | 18.125px | 18.125px | ✅ igual |
| Botão Emitir | border-top-width | 0px | 1px | 1px | ✅ igual |
| Botão Emitir | border-top-color | oklch(1 0 0) | oklch(0.55 0.15 295) | oklch(0.55 0.15 295) | ✅ igual |
| Botão Emitir | border-bottom-width | 0px | 1px | 1px | ✅ igual |
| Botão Emitir | border-bottom-color | oklch(1 0 0) | oklch(0.55 0.15 295) | oklch(0.55 0.15 295) | ✅ igual |
| Botão Emitir | border-left-width | 0px | 1px | 1px | ✅ igual |
| Botão Emitir | border-left-color | oklch(1 0 0) | oklch(0.55 0.15 295) | oklch(0.55 0.15 295) | ✅ igual |
| Botão Emitir | border-radius | 5px | 6px | 6px | ✅ igual |
| Botão Emitir | padding-top | 0px | 5px | 5px | ✅ igual |
| Botão Emitir | padding-right | 14px | 10px | 10px | ✅ igual |
| Botão Emitir | padding-bottom | 0px | 5px | 5px | ✅ igual |
| Botão Emitir | padding-left | 14px | 10px | 10px | ✅ igual |
| Botão Emitir | height | 30px | 30.125px | 30.125px | ✅ igual |
| Botão Emitir | white-space | nowrap | normal | normal | ✅ igual |
| Fileira de abas | flex-wrap | wrap | nowrap | nowrap | ✅ igual |
| Fileira de abas | align-items | normal | center | center | ✅ igual |
| Fileira de abas | gap | 4px | 0px | 0px | ✅ igual |
| Fileira de abas | line-height | 20.25px | 19.575px | 19.575px | ✅ igual |
| Fileira de abas | background-color | oklch(0.965 0.004 90) | oklch(0.985 0.003 90) | oklch(0.985 0.003 90) | ✅ igual |
| Fileira de abas | border-bottom-width | 0px | 1px | 1px | ✅ igual |
| Fileira de abas | border-bottom-color | oklch(0.92 0.006 90) | oklch(0.9 0.004 90) | oklch(0.9 0.004 90) | ✅ igual |
| Fileira de abas | border-radius | 10px | 0px | 0px | ✅ igual |
| Fileira de abas | padding-top | 4px | 0px | 0px | ✅ igual |
| Fileira de abas | padding-right | 4px | 0px | 0px | ✅ igual |
| Fileira de abas | padding-bottom | 4px | 0px | 0px | ✅ igual |
| Fileira de abas | padding-left | 4px | 0px | 0px | ✅ igual |
| Fileira de abas | margin-top | 14px | 0px | 0px | ✅ igual |
| Fileira de abas | margin-bottom | 18px | 0px | 0px | ✅ igual |
| Fileira de abas | height | 41.75px | 36px | 36px | ✅ igual |
| Aba ativa | font-size | 12.5px | 13px | 13px | ✅ igual |
| Aba ativa | line-height | 18.75px | 13px | 13px | ✅ igual |
| Aba ativa | color | oklch(0.55 0.15 295) | oklch(0.22 0.01 80) | oklch(0.22 0.01 80) | ✅ igual |
| Aba ativa | background-color | oklch(1 0 0) | rgba(0, 0, 0, 0) | rgba(0, 0, 0, 0) | ✅ igual |
| Aba ativa | border-top-width | 1px | 0px | 0px | ✅ igual |
| Aba ativa | border-bottom-width | 1px | 2px | 2px | ✅ igual |
| Aba ativa | border-bottom-color | oklch(0.9 0.004 90) | oklch(0.55 0.15 295) | oklch(0.55 0.15 295) | ✅ igual |
| Aba ativa | border-left-width | 1px | 0px | 0px | ✅ igual |
| Aba ativa | border-radius | 7px | 0px | 0px | ✅ igual |
| Aba ativa | padding-top | 6px | 0px | 0px | ✅ igual |
| Aba ativa | padding-right | 10px | 14px | 14px | ✅ igual |
| Aba ativa | padding-bottom | 6px | 0px | 0px | ✅ igual |
| Aba ativa | padding-left | 10px | 14px | 14px | ✅ igual |
| Aba ativa | margin-bottom | 0px | -1px | -1px | ✅ igual |
| Aba ativa | height | 33.75px | 36px | 36px | ✅ igual |
| Aba ativa | white-space | normal | nowrap | nowrap | ✅ igual |
| Aba | font-size | 12.5px | 13px | 13px | ✅ igual |
| Aba | line-height | 18.75px | 13px | 13px | ✅ igual |
| Aba | border-top-width | 1px | 0px | 0px | ✅ igual |
| Aba | border-bottom-width | 1px | 2px | 2px | ✅ igual |
| Aba | border-left-width | 1px | 0px | 0px | ✅ igual |
| Aba | border-radius | 7px | 0px | 0px | ✅ igual |
| Aba | padding-top | 6px | 0px | 0px | ✅ igual |
| Aba | padding-right | 10px | 14px | 14px | ✅ igual |
| Aba | padding-bottom | 6px | 0px | 0px | ✅ igual |
| Aba | padding-left | 10px | 14px | 14px | ✅ igual |
| Aba | margin-bottom | 0px | -1px | -1px | ✅ igual |
| Aba | height | 33.75px | 36px | 36px | ✅ igual |
| Aba | white-space | normal | nowrap | nowrap | ✅ igual |
| Faixa de KPIs | line-height | 20.25px | 19.575px | 19.575px | ✅ igual |
| Faixa de KPIs | background-color | rgba(0, 0, 0, 0) | oklch(1 0 none) | rgb(255, 255, 255) | ✅ igual |
| Faixa de KPIs | border-left-width | 0px | 1px | 1px | ✅ igual |
| Faixa de KPIs | border-left-color | oklch(0.92 0.006 90) | oklch(0.9 0.004 90) | oklch(0.9 0.004 90) | ✅ igual |
| Faixa de KPIs | border-radius | 0px | 12px | 12px | ✅ igual |
| Faixa de KPIs | padding-top | 12px | 0px | 0px | ✅ igual |
| Faixa de KPIs | padding-bottom | 12px | 0px | 0px | ✅ igual |
| Faixa de KPIs | margin-bottom | 14px | 0px | 0px | ✅ igual |
| Faixa de KPIs | height | 99.2969px | 98.5781px | 98.5781px | ✅ igual |
| KPI | gap | 2px | 1px | 1px | ✅ igual |
| KPI | line-height | 20.25px | 19.575px | 19.575px | ✅ igual |
| KPI | padding-top | 0px | 12px | 12px | ✅ igual |
| KPI | padding-right | 22px | 18px | 18px | ✅ igual |
| KPI | padding-bottom | 0px | 12px | 12px | ✅ igual |
| KPI | padding-left | 0px | 18px | 18px | ✅ igual |
| KPI | height | 73.2969px | 96.5781px | 96.5781px | ✅ igual |
| Rótulo KPI | font-weight | 600 | 400 | 400 | ✅ igual |
| Rótulo KPI | line-height | 15.75px | 15.225px | 15.225px | ✅ igual |
| Rótulo KPI | color | oklch(0.65 0.01 80) | oklch(0.555 0.01 80) | oklch(0.555 0.01 80) | ✅ igual |
| Rótulo KPI | height | 15.75px | 15.2188px | 15.2188px | ✅ igual |
| Valor KPI | font-weight | 700 | 600 | 600 | ✅ igual |
| Valor KPI | line-height | 19.8px | 20.7px | 20.7px | ✅ igual |
| Valor KPI | letter-spacing | -0.27px | normal | normal | ✅ igual |
| Valor KPI | height | 19.7969px | 20.6875px | 20.6875px | ✅ igual |
| Rodapé KPI | font-size | 10.5px | 11.5px | 11.5px | ✅ igual |
| Rodapé KPI | line-height | 15.75px | 16.675px | 16.675px | ✅ igual |
| Rodapé KPI | color | oklch(0.55 0.13 145) | oklch(0.555 0.01 80) | oklch(0.555 0.01 80) | ✅ igual |
| Rodapé KPI | margin-top | 1px | 0px | 0px | ✅ igual |
| Rodapé KPI | height | 15.75px | 16.6719px | 16.6719px | ✅ igual |
| Fechar mês | display | flex | block | block | ✅ igual |
| Fechar mês | font-size | 12px | 12.5px | 12.5px | ✅ igual |
| Fechar mês | font-weight | 500 | 600 | 600 | ✅ igual |
| Fechar mês | line-height | 16px | 18.125px | 18.125px | ✅ igual |
| Fechar mês | color | oklch(0.22 0.01 80) | oklch(0.55 0.15 295) | oklch(0.55 0.15 295) | ✅ igual |
| Fechar mês | background-color | oklch(1 0 0) | oklch(0.965 0.004 90) | oklch(0.965 0.004 90) | ✅ igual |
| Fechar mês | border-top-width | 1px | 0px | 0px | ✅ igual |
| Fechar mês | border-bottom-width | 1px | 0px | 0px | ✅ igual |
| Fechar mês | border-left-color | oklch(0.92 0.006 90) | oklch(0.9 0.004 90) | oklch(0.9 0.004 90) | ✅ igual |
| Fechar mês | border-radius | 6px | 0px | 0px | ✅ igual |
| Fechar mês | padding-right | 8px | 18px | 18px | ✅ igual |
| Fechar mês | padding-left | 8px | 18px | 18px | ✅ igual |
| Fechar mês | height | 24px | 96.5781px | 96.5781px | ✅ igual |
| Fechar mês | box-shadow | rgba(0, 0, 0, 0) 0px 0px 0px 0px, rgba(0, 0, 0, 0) 0px 0px 0px 0px, rgba(0, 0, 0, 0) 0px 0px 0px 0px, rgba(0, 0, 0, 0) 0px 0px 0px 0px, rgba(0, 0, 0, 0.05) 0px 1px 2px 0px | none | none | ✅ igual |
| Lista de alertas | line-height | 20.25px | 19.575px | 19.575px | ✅ igual |
| Lista de alertas | margin-bottom | 18px | 0px | 0px | ✅ igual |
| Lista de alertas | height | 60.5px | 59.2344px | 189.703px | ≠ dado |
| Alerta | line-height | 20.25px | 19.575px | 19.575px | ✅ igual |
| Alerta | background-color | oklch(0.973 0.0108 1.5) | oklch(0.9748 0.0126 25) | oklch(0.974794 0.0126468 25) | ✅ igual |
| Alerta | border-top-color | oklch(0.795 0.0568 70.5) | oklch(0.804 0.0658 70.5) | oklch(0.804 0.0658 70.5) | ✅ igual |
| Alerta | border-bottom-color | oklch(0.795 0.0568 70.5) | oklch(0.804 0.0658 70.5) | oklch(0.804 0.0658 70.5) | ✅ igual |
| Alerta | border-left-color | oklch(0.795 0.0568 70.5) | oklch(0.804 0.0658 70.5) | oklch(0.804 0.0658 70.5) | ✅ igual |
| Alerta | height | 60.5px | 59.2344px | 59.2344px | ✅ igual |
| Ícone alerta | line-height | 20.25px | 19.575px | 19.575px | ✅ igual |
| Ícone alerta | color | oklch(0.55 0.18 25) | oklch(0.58 0.21 25) | oklch(0.58 0.21 25) | ✅ igual |
| Ícone alerta | background-color | oklch(0.55 0.18 25 / 0.14) | oklch(0.58 0.21 25 / 0.14) | oklch(0.58 0.21 25 / 0.14) | ✅ igual |
| Título alerta | line-height | 20.25px | 19.575px | 19.575px | ✅ igual |
| Título alerta | height | 20.25px | 19.5625px | 19.5625px | ✅ igual |
| Texto alerta | line-height | 17.25px | 16.675px | 16.675px | ✅ igual |
| Texto alerta | height | 17.25px | 16.6719px | 16.6719px | ✅ igual |
| Botão alerta | justify-content | center | normal | normal | ✅ igual |
| Botão alerta | font-weight | 500 | 400 | 400 | ✅ igual |
| Botão alerta | line-height | 18.75px | 18.125px | 18.125px | ✅ igual |
| Botão alerta | color | oklch(0.22 0.01 80) | oklch(0.5 0.01 80) | oklch(0.5 0.01 80) | ✅ igual |
| Botão alerta | border-radius | 5px | 6px | 6px | ✅ igual |
| Botão alerta | padding-top | 0px | 5px | 5px | ✅ igual |
| Botão alerta | padding-right | 12px | 10px | 10px | ✅ igual |
| Botão alerta | padding-bottom | 0px | 5px | 5px | ✅ igual |
| Botão alerta | padding-left | 12px | 10px | 10px | ✅ igual |
| Botão alerta | height | 30px | 30.125px | 30.125px | ✅ igual |
| Botão alerta | white-space | nowrap | normal | normal | ✅ igual |
| Barra de busca | line-height | 20.25px | 19.575px | 19.575px | ✅ igual |
| Barra de busca | margin-bottom | 10px | 0px | 0px | ✅ igual |
| Barra de busca | height | 137px | 32.125px | 32.125px | ✅ igual |
| Caixa de busca | display | block | flex | flex | ✅ igual |
| Caixa de busca | align-items | normal | center | center | ✅ igual |
| Caixa de busca | gap | normal | 7px | 7px | ✅ igual |
| Caixa de busca | line-height | 20.25px | 19.575px | 19.575px | ✅ igual |
| Caixa de busca | background-color | rgba(0, 0, 0, 0) | oklch(1 0 none) | rgb(255, 255, 255) | ✅ igual |
| Caixa de busca | border-top-width | 0px | 1px | 1px | ✅ igual |
| Caixa de busca | border-top-color | oklch(0.92 0.006 90) | oklch(0.9 0.004 90) | oklch(0.9 0.004 90) | ✅ igual |
| Caixa de busca | border-bottom-width | 0px | 1px | 1px | ✅ igual |
| Caixa de busca | border-bottom-color | oklch(0.92 0.006 90) | oklch(0.9 0.004 90) | oklch(0.9 0.004 90) | ✅ igual |
| Caixa de busca | border-left-width | 0px | 1px | 1px | ✅ igual |
| Caixa de busca | border-left-color | oklch(0.92 0.006 90) | oklch(0.9 0.004 90) | oklch(0.9 0.004 90) | ✅ igual |
| Caixa de busca | border-radius | 0px | 6px | 6px | ✅ igual |
| Caixa de busca | padding-top | 0px | 5px | 5px | ✅ igual |
| Caixa de busca | padding-right | 0px | 10px | 10px | ✅ igual |
| Caixa de busca | padding-bottom | 0px | 5px | 5px | ✅ igual |
| Caixa de busca | padding-left | 0px | 10px | 10px | ✅ igual |
| Caixa de busca | height | 30px | 32.125px | 32.125px | ✅ igual |
| Campo de busca | display | inline-block | block | block | ✅ igual |
| Campo de busca | line-height | 18.75px | 18.125px | 18.125px | ✅ igual |
| Campo de busca | background-color | rgb(255, 255, 255) | rgba(0, 0, 0, 0) | rgba(0, 0, 0, 0) | ✅ igual |
| Campo de busca | border-top-width | 1px | 0px | 0px | ✅ igual |
| Campo de busca | border-bottom-width | 1px | 0px | 0px | ✅ igual |
| Campo de busca | border-left-width | 1px | 0px | 0px | ✅ igual |
| Campo de busca | border-radius | 5px | 0px | 0px | ✅ igual |
| Campo de busca | padding-top | 0px | 1px | 1px | ✅ igual |
| Campo de busca | padding-right | 8px | 2px | 2px | ✅ igual |
| Campo de busca | padding-bottom | 0px | 1px | 1px | ✅ igual |
| Campo de busca | padding-left | 34px | 2px | 2px | ✅ igual |
| Campo de busca | height | 30px | 20.125px | 20.125px | ✅ igual |
| Filtro tipo | tag | button | button | select | ≠ |
| Filtro tipo | display | flex | flex | block | ≠ |
| Filtro tipo | justify-content | space-between | space-between | normal | ≠ |
| Filtro tipo | gap | 8px | 8px | normal | ≠ |
| Filtro tipo | line-height | 18.75px | 18.125px | normal | ≠ |
| Filtro tipo | text-align | center | center | start | ≠ |
| Filtro tipo | color | oklch(0.22 0.01 80) | oklch(0.5 0.01 80) | oklch(0.5 0.01 80) | ✅ igual |
| Filtro tipo | border-radius | 5px | 6px | 6px | ✅ igual |
| Filtro tipo | padding-top | 0px | 5px | 5px | ✅ igual |
| Filtro tipo | padding-bottom | 0px | 5px | 5px | ✅ igual |
| Filtro tipo | white-space | normal | normal | pre | ≠ |
| Densidade | line-height | 20.25px | 19.575px | 19.575px | ✅ igual |
| Densidade | background-color | oklch(0.965 0.004 90) | rgba(0, 0, 0, 0) | rgba(0, 0, 0, 0) | ✅ igual |
| Densidade | border-top-width | 0px | 1px | 1px | ✅ igual |
| Densidade | border-top-color | oklch(0.92 0.006 90) | oklch(0.9 0.004 90) | oklch(0.9 0.004 90) | ✅ igual |
| Densidade | border-bottom-width | 0px | 1px | 1px | ✅ igual |
| Densidade | border-bottom-color | oklch(0.92 0.006 90) | oklch(0.9 0.004 90) | oklch(0.9 0.004 90) | ✅ igual |
| Densidade | border-left-width | 0px | 1px | 1px | ✅ igual |
| Densidade | border-left-color | oklch(0.92 0.006 90) | oklch(0.9 0.004 90) | oklch(0.9 0.004 90) | ✅ igual |
| Densidade | border-radius | 7px | 6px | 6px | ✅ igual |
| Densidade | padding-top | 2px | 0px | 0px | ✅ igual |
| Densidade | padding-right | 2px | 0px | 0px | ✅ igual |
| Densidade | padding-bottom | 2px | 0px | 0px | ✅ igual |
| Densidade | padding-left | 2px | 0px | 0px | ✅ igual |
| Densidade | height | 23px | 28px | 28px | ✅ igual |
| Botão densidade ativo | display | flex | block | block | ✅ igual |
| Botão densidade ativo | font-size | 13.5px | 11.5px | 11.5px | ✅ igual |
| Botão densidade ativo | font-weight | 400 | 600 | 600 | ✅ igual |
| Botão densidade ativo | line-height | 20.25px | 16.675px | 16.675px | ✅ igual |
| Botão densidade ativo | color | oklch(0.22 0.01 80) | oklch(0.55 0.15 295) | oklch(0.55 0.15 295) | ✅ igual |
| Botão densidade ativo | background-color | oklch(1 0 0) | oklch(0.945 0.045 295) | oklch(0.945 0.045 295) | ✅ igual |
| Botão densidade ativo | border-left-width | 0px | 1px | 1px | ✅ igual |
| Botão densidade ativo | border-left-color | oklch(0.22 0.01 80) | oklch(0.9 0.004 90) | oklch(0.9 0.004 90) | ✅ igual |
| Botão densidade ativo | border-radius | 5px | 0px | 0px | ✅ igual |
| Botão densidade ativo | padding-top | 4px | 1px | 1px | ✅ igual |
| Botão densidade ativo | padding-right | 8px | 6px | 6px | ✅ igual |
| Botão densidade ativo | padding-bottom | 4px | 1px | 1px | ✅ igual |
| Botão densidade ativo | padding-left | 8px | 6px | 6px | ✅ igual |
| Botão densidade ativo | height | 19px | 26px | 26px | ✅ igual |
| Botão densidade ativo | box-shadow | rgba(0, 0, 0, 0.06) 0px 1px 2px 0px | none | none | ✅ igual |
| Visões salvas | gap | 4px | 6px | 6px | ✅ igual |
| Visões salvas | line-height | 20.25px | 19.575px | 19.575px | ✅ igual |
| Visões salvas | margin-top | 0px | -8px | -8px | ✅ igual |
| Visões salvas | height | 29.25px | 28.125px | 28.125px | ✅ igual |
| Visão ativa | existe | não | sim | sim | ✅ igual |
| Visão | existe | não | sim | sim | ✅ igual |
| Contador da visão | existe | não | sim | sim | ✅ igual |
| Tabela | line-height | 20.25px | 19.575px | 19.575px | ✅ igual |
| Tabela | border-radius | 10px | 12px | 12px | ✅ igual |
| Tabela | height | 792.75px | 809.5px | 659.531px | ≠ dado |
| Cabeçalho coluna | font-weight | 700 | 600 | 600 | ✅ igual |
| Cabeçalho coluna | line-height | 15.75px | 15.225px | 15.225px | ✅ igual |
| Cabeçalho coluna | letter-spacing | 0.525px | 0.63px | 0.63px | ✅ igual |
| Cabeçalho coluna | color | oklch(0.65 0.01 80) | oklch(0.555 0.01 80) | oklch(0.555 0.01 80) | ✅ igual |
| Cabeçalho coluna | padding-top | 8px | 9px | 9px | ✅ igual |
| Cabeçalho coluna | padding-right | 12px | 14px | 14px | ✅ igual |
| Cabeçalho coluna | padding-bottom | 8px | 9px | 9px | ✅ igual |
| Cabeçalho coluna | padding-left | 12px | 14px | 14px | ✅ igual |
| Cabeçalho coluna | height | 32.5px | 34.5px | 38.7188px | ≠ dado |
| Cabeçalho coluna | white-space | normal | nowrap | nowrap | ✅ igual |
| Célula | line-height | 18.75px | 18.125px | 18.125px | ✅ igual |
| Célula | color | oklch(0.22 0.01 80) | oklch(0.5 0.01 80) | oklch(0.5 0.01 80) | ✅ igual |
| Célula | padding-top | 9px | 10px | 10px | ✅ igual |
| Célula | padding-right | 12px | 14px | 14px | ✅ igual |
| Célula | padding-bottom | 9px | 10px | 10px | ✅ igual |
| Célula | padding-left | 12px | 14px | 14px | ✅ igual |
| Célula | height | 97px | 96.6875px | 76.5469px | ≠ dado |
| Selo tipo | line-height | 15.75px | 15.225px | 15.225px | ✅ igual |
| Selo tipo | height | 21.75px | 21.2188px | 21.2188px | ✅ igual |
| Célula número | line-height | 18.75px | 18.125px | 18.125px | ✅ igual |
| Célula número | color | oklch(0.22 0.01 80) | oklch(0.5 0.01 80) | oklch(0.5 0.01 80) | ✅ igual |
| Célula número | padding-top | 9px | 10px | 10px | ✅ igual |
| Célula número | padding-right | 12px | 14px | 14px | ✅ igual |
| Célula número | padding-bottom | 9px | 10px | 10px | ✅ igual |
| Célula número | padding-left | 12px | 14px | 14px | ✅ igual |
| Célula número | height | 97px | 96.6875px | 76.5469px | ≠ dado |
| Nome cliente | line-height | 18.75px | 18.125px | 18.125px | ✅ igual |
| Nome cliente | color | oklch(0.22 0.01 80) | oklch(0.5 0.01 80) | oklch(0.5 0.01 80) | ✅ igual |
| Nome cliente | height | 20.25px | 19.5625px | 19.5625px | ✅ igual |
| Chave | existe | não | não | sim | ≠ dado |
| Pílula status | line-height | 17.25px | 16.675px | 16.675px | ✅ igual |
| Pílula status | color | oklch(0.62 0.13 60) | oklch(0.63 0.16 68) | oklch(0.55 0.17 150) | ≠ |
| Pílula status | background-color | oklch(0.62 0.13 60 / 0.08) | oklch(0.63 0.16 68 / 0.08) | oklch(0.55 0.17 150 / 0.08) | ≠ |
| Pílula status | border-top-color | oklch(0.62 0.13 60 / 0.3) | oklch(0.63 0.16 68 / 0.3) | oklch(0.55 0.17 150 / 0.28) | ≠ |
| Pílula status | border-bottom-color | oklch(0.62 0.13 60 / 0.3) | oklch(0.63 0.16 68 / 0.3) | oklch(0.55 0.17 150 / 0.28) | ≠ |
| Pílula status | border-left-color | oklch(0.62 0.13 60 / 0.3) | oklch(0.63 0.16 68 / 0.3) | oklch(0.55 0.17 150 / 0.28) | ≠ |
| Pílula status | height | 23.25px | 22.6719px | 22.6719px | ✅ igual |
| Célula valor | line-height | 18.75px | 18.125px | 18.125px | ✅ igual |
| Célula valor | padding-top | 9px | 10px | 10px | ✅ igual |
| Célula valor | padding-right | 12px | 14px | 14px | ✅ igual |
| Célula valor | padding-bottom | 9px | 10px | 10px | ✅ igual |
| Célula valor | padding-left | 12px | 14px | 14px | ✅ igual |
| Célula valor | height | 97px | 96.6875px | 76.5469px | ≠ dado |
| Pílula prazo | font-size | 10.5px | 11.5px | 11.5px | ✅ igual |
| Pílula prazo | font-weight | 500 | 400 | 400 | ✅ igual |
| Pílula prazo | line-height | 15.75px | 16.675px | 16.675px | ✅ igual |
| Pílula prazo | color | oklch(0.55 0.18 25) | oklch(0.58 0.21 25) | oklch(0.5 0.01 80) | ≠ |
| Pílula prazo | background-color | oklch(0.95 0.04 25) | rgba(0, 0, 0, 0) | rgba(0, 0, 0, 0) | ✅ igual |
| Pílula prazo | border-top-width | 0px | 1px | 1px | ✅ igual |
| Pílula prazo | border-top-color | oklch(0.92 0.006 90) | oklch(0.58 0.21 25 / 0.32) | oklch(0.9 0.004 90) | ≠ |
| Pílula prazo | border-bottom-width | 0px | 1px | 1px | ✅ igual |
| Pílula prazo | border-bottom-color | oklch(0.92 0.006 90) | oklch(0.58 0.21 25 / 0.32) | oklch(0.9 0.004 90) | ≠ |
| Pílula prazo | border-left-width | 0px | 1px | 1px | ✅ igual |
| Pílula prazo | border-left-color | oklch(0.92 0.006 90) | oklch(0.58 0.21 25 / 0.32) | oklch(0.9 0.004 90) | ≠ |
| Pílula prazo | border-radius | 99px | 999px | 999px | ✅ igual |
| Pílula prazo | height | 19.75px | 22.6719px | 22.6719px | ✅ igual |
| Pílula prazo | white-space | normal | nowrap | nowrap | ✅ igual |
| Paginador | line-height | 17.25px | 16.675px | 16.675px | ✅ igual |
| Paginador | color | oklch(0.65 0.01 80) | oklch(0.555 0.01 80) | oklch(0.555 0.01 80) | ✅ igual |
| Paginador | height | 93.25px | 30.125px | 30.125px | ✅ igual |

### Gaveta da nota

| Componente | Propriedade | Antes (produção) | Depois (PR) | Protótipo | |
|---|---|---|---|---|---|
| Gaveta | w | 480 | 520 | 520 | ✅ igual |
| Gaveta | line-height | 20.25px | 19.575px | 19.575px | ✅ igual |
| Gaveta | border-left-width | 0px | 1px | 1px | ✅ igual |
| Gaveta | border-left-color | oklch(0.92 0.006 90) | oklch(0.9 0.004 90) | oklch(0.9 0.004 90) | ✅ igual |
| Gaveta | box-shadow | rgba(0, 0, 0, 0.12) -8px 0px 24px 0px | none | none | ✅ igual |
| Fundo escurecido | existe | não | sim | sim | ✅ igual |
| Cabeçalho gaveta | tag | header | header | div | ≠ |
| Cabeçalho gaveta | w | 480 | 519 | 519 | ✅ igual |
| Cabeçalho gaveta | gap | 12px | 10px | 10px | ✅ igual |
| Cabeçalho gaveta | line-height | 20.25px | 19.575px | 19.575px | ✅ igual |
| Cabeçalho gaveta | background-color | rgba(0, 0, 0, 0) | oklch(0.965 0.004 90) | oklch(0.965 0.004 90) | ✅ igual |
| Cabeçalho gaveta | padding-top | 16px | 14px | 14px | ✅ igual |
| Cabeçalho gaveta | padding-right | 20px | 16px | 16px | ✅ igual |
| Cabeçalho gaveta | padding-bottom | 16px | 14px | 14px | ✅ igual |
| Cabeçalho gaveta | padding-left | 20px | 16px | 16px | ✅ igual |
| Cabeçalho gaveta | height | 102px | 68.3594px | 68.3594px | ✅ igual |
| Título gaveta | w | 402 | 451 | 174 | ≠ |
| Título gaveta | font-weight | 700 | 600 | 600 | ✅ igual |
| Título gaveta | line-height | 27px | 20.7px | 20.7px | ✅ igual |
| Título gaveta | margin-top | 2px | 0px | 0px | ✅ igual |
| Título gaveta | margin-bottom | 4px | 0px | 0px | ✅ igual |
| Título gaveta | height | 27px | 20.6875px | 20.6875px | ✅ igual |
| Subtítulo gaveta | tag | small | p | p | ✅ igual |
| Subtítulo gaveta | w | 94 | 451 | 174 | ≠ |
| Subtítulo gaveta | display | inline | block | block | ✅ igual |
| Subtítulo gaveta | font-weight | 600 | 400 | 400 | ✅ igual |
| Subtítulo gaveta | line-height | 17.25px | 16.675px | 16.675px | ✅ igual |
| Subtítulo gaveta | letter-spacing | 0.575px | normal | normal | ✅ igual |
| Subtítulo gaveta | text-transform | uppercase | none | none | ✅ igual |
| Subtítulo gaveta | color | oklch(0.555 0.01 80) | oklch(0.5 0.01 80) | oklch(0.5 0.01 80) | ✅ igual |
| Subtítulo gaveta | margin-top | 0px | 2px | 2px | ✅ igual |
| Subtítulo gaveta | height | auto | 16.6719px | 16.6719px | ✅ igual |
| Botão × | display | flex | block | block | ✅ igual |
| Botão × | font-size | 13.5px | 13.5px | 13.3333px | ≠ |
| Botão × | line-height | 20.25px | 13.5px | 13.3333px | ≠ |
| Botão × | color | oklch(0.5 0.01 80) | oklch(0.555 0.01 80) | oklch(0.555 0.01 80) | ✅ igual |
| Botão × | background-color | rgba(0, 0, 0, 0) | oklch(1 0 none) | rgb(255, 255, 255) | ✅ igual |
| Botão × | padding-top | 4px | 1px | 1px | ✅ igual |
| Botão × | padding-right | 8px | 6px | 6px | ✅ igual |
| Botão × | padding-bottom | 4px | 1px | 1px | ✅ igual |
| Botão × | padding-left | 8px | 6px | 6px | ✅ igual |
| Botão × | height | 30.25px | 26px | 26px | ✅ igual |
| Botão × | box-shadow | none | none | oklch(0.945 0.045 295) 0px 0px 0px 3px | ≠ |
| Corpo gaveta | w | 480 | 519 | 519 | ✅ igual |
| Corpo gaveta | display | block | flex | flex | ✅ igual |
| Corpo gaveta | flex-direction | row | column | column | ✅ igual |
| Corpo gaveta | gap | normal | 14px | 14px | ✅ igual |
| Corpo gaveta | line-height | 20.25px | 19.575px | 19.575px | ✅ igual |
| Corpo gaveta | padding-top | 16px | 14px | 14px | ✅ igual |
| Corpo gaveta | padding-right | 20px | 16px | 16px | ✅ igual |
| Corpo gaveta | padding-bottom | 16px | 14px | 14px | ✅ igual |
| Corpo gaveta | padding-left | 20px | 16px | 16px | ✅ igual |
| Corpo gaveta | height | 794px | 827.516px | 827.516px | ✅ igual |
| Seção | tag | section | section | div | ≠ |
| Seção | w | 440 | 487 | 487 | ✅ igual |
| Seção | display | block | flex | flex | ✅ igual |
| Seção | flex-direction | row | column | column | ✅ igual |
| Seção | gap | normal | 6px | 6px | ✅ igual |
| Seção | line-height | 20.25px | 19.575px | 19.575px | ✅ igual |
| Seção | margin-bottom | 18px | 0px | 0px | ✅ igual |
| Seção | height | 116px | 102.922px | 22.6719px | ≠ |
| Título seção | tag | h4 | h4 | h3 | ≠ |
| Título seção | w | 440 | 487 | 487 | ✅ igual |
| Título seção | font-size | 11.5px | 10.5px | 10.5px | ✅ igual |
| Título seção | font-weight | 700 | 600 | 600 | ✅ igual |
| Título seção | line-height | 17.25px | 12.6px | 12.6px | ✅ igual |
| Título seção | letter-spacing | 0.575px | 0.735px | 0.735px | ✅ igual |
| Título seção | margin-bottom | 8px | 0px | 0px | ✅ igual |
| Título seção | height | 17.25px | 12.5938px | 12.5938px | ✅ igual |
| Lista dados | w | 440 | 487 | 487 | ✅ igual |
| Lista dados | line-height | 18.75px | 18.125px | 18.125px | ✅ igual |
| Lista dados | margin-top | 0px | 12.5px | 12.5px | ✅ igual |
| Lista dados | margin-bottom | 0px | 12.5px | 12.5px | ✅ igual |
| Lista dados | height | 109.75px | 106.625px | 173px | ≠ |
| Rótulo dado | w | 100 | 68 | 74 | ≠ |
| Rótulo dado | line-height | 18.75px | 18.125px | 18.125px | ✅ igual |
| Rótulo dado | height | 18.75px | 18.125px | 18.125px | ✅ igual |
| Valor dado | w | 328 | 407 | 401 | ≠ |
| Valor dado | line-height | 18.75px | 18.125px | 18.125px | ✅ igual |
| Valor dado | text-align | start | right | right | ✅ igual |
| Valor dado | height | 18.75px | 18.125px | 18.125px | ✅ igual |
| Pílula status | w | 100 | 103 | 78 | ≠ |
| Pílula status | gap | 6px | 5px | 5px | ✅ igual |
| Pílula status | line-height | 17.25px | 16.675px | 16.675px | ✅ igual |
| Pílula status | color | oklch(0.22 0.01 80) | oklch(0.63 0.16 68) | oklch(0.55 0.17 150) | ≠ |
| Pílula status | background-color | rgba(0, 0, 0, 0) | oklch(0.63 0.16 68 / 0.08) | oklch(0.55 0.17 150 / 0.08) | ≠ |
| Pílula status | border-top-width | 0px | 1px | 1px | ✅ igual |
| Pílula status | border-top-color | oklch(0.92 0.006 90) | oklch(0.63 0.16 68 / 0.3) | oklch(0.55 0.17 150 / 0.28) | ≠ |
| Pílula status | border-bottom-width | 0px | 1px | 1px | ✅ igual |
| Pílula status | border-bottom-color | oklch(0.92 0.006 90) | oklch(0.63 0.16 68 / 0.3) | oklch(0.55 0.17 150 / 0.28) | ≠ |
| Pílula status | border-left-width | 0px | 1px | 1px | ✅ igual |
| Pílula status | border-left-color | oklch(0.92 0.006 90) | oklch(0.63 0.16 68 / 0.3) | oklch(0.55 0.17 150 / 0.28) | ≠ |
| Pílula status | border-radius | 99px | 999px | 999px | ✅ igual |
| Pílula status | padding-right | 8px | 9px | 9px | ✅ igual |
| Pílula status | padding-left | 8px | 9px | 9px | ✅ igual |
| Pílula status | height | 21.25px | 22.6719px | 22.6719px | ✅ igual |
| Pílula status | white-space | normal | nowrap | nowrap | ✅ igual |
| Rodapé gaveta | tag | footer | footer | div | ≠ |
| Rodapé gaveta | w | 480 | 519 | 519 | ✅ igual |
| Rodapé gaveta | flex-wrap | nowrap | wrap | wrap | ✅ igual |
| Rodapé gaveta | gap | 8px | 6px | 6px | ✅ igual |
| Rodapé gaveta | line-height | 20.25px | 19.575px | 19.575px | ✅ igual |
| Rodapé gaveta | padding-right | 20px | 16px | 16px | ✅ igual |
| Rodapé gaveta | padding-left | 20px | 16px | 16px | ✅ igual |
| Rodapé gaveta | height | 55px | 55.125px | 55.125px | ✅ igual |
| Botão rodapé | w | 108 | 89 | 85 | ≠ |
| Botão rodapé | justify-content | center | normal | normal | ✅ igual |
| Botão rodapé | font-weight | 500 | 400 | 400 | ✅ igual |
| Botão rodapé | line-height | 18.75px | 18.125px | 18.125px | ✅ igual |
| Botão rodapé | color | oklch(0.22 0.01 80) | oklch(0.5 0.01 80) | oklch(0.5 0.01 80) | ✅ igual |
| Botão rodapé | border-radius | 5px | 6px | 6px | ✅ igual |
| Botão rodapé | padding-top | 0px | 5px | 5px | ✅ igual |
| Botão rodapé | padding-right | 12px | 10px | 10px | ✅ igual |
| Botão rodapé | padding-bottom | 0px | 5px | 5px | ✅ igual |
| Botão rodapé | padding-left | 12px | 10px | 10px | ✅ igual |
| Botão rodapé | height | 30px | 30.125px | 30.125px | ✅ igual |
| Botão rodapé | white-space | nowrap | normal | normal | ✅ igual |

**O que fica diferente e por quê** — ver o corpo do PR.
