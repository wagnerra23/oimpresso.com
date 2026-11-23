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
