---
page: /sells/create-v3
component: resources/js/Pages/Sells/CreateV3.tsx
owner: luiz
status: draft
last_validated: "2026-08-10"
parent_module: Sells
related_us: [US-SELL-058]
related_adrs: [253, 104, 93, 62]
related_prototype: prototipo-ui/cowork/venda-v3/sells-create.jsx
tier: C
charter_version: 2
---

# Page Charter — /sells/create-v3

> **Status:** draft — **preview de design, não é tela de produção.** Dono: **[L] Luiz**, que assumiu o cadastro de venda em 2026-08-06.
> Irmão: [`CreateV3.casos.md`](CreateV3.casos.md) (contrato) · RUNBOOK: [`RUNBOOK-create-v3.md`](../../../../memory/requisitos/Sells/RUNBOOK-create-v3.md) (F1 PLAN).

---

## Mission

Ensaiar o redesenho do cadastro de venda **sem tocar na tela que o cliente opera**.

A tela viva é `Sells/Create.tsx` (`/pos/create`), e quem a usa é a **ROTA LIVRE** (`business_id=4` — Larissa dona, Guilherme retaguarda), 99% do volume de vendas. Restrição de negócio declarada por [L] em 2026-08-06, textual:

> *"Tela do Guilherme e da Larissa não pode ser alterada de forma alguma, se não eles quebram contrato e perdemos dinheiro."*

**A feature flag não protege.** Medido em `origin/main`: `FeatureFlagService::$fallbackDefaults['useV2SellsCreate'] = true` desde 2026-05-27 (*"Wagner: ative para todos pronto"*). A V2 React está ligada por padrão pra todos — editar `Create.tsx` e deployar atinge o cliente direto. Daí **arquivo novo + rota nova**, e não branch de flag.

---

## Goals — o que a tela faz

> Atualizado em 2026-08-10 com o porte do handoff `design_handoff_cadastro_venda`
> (a v1 descrevia o scaffold de 280 linhas que shipou no [#5356](https://github.com/wagnerra23/oimpresso.com/pull/5356)).

- `AppShellV2` como layout persistente (mesma convenção de `Sells/Create.tsx`).
- Faixa âmbar permanente no topo: quem abrir por engano sabe em 1 segundo que não é produção.
- **4 passos numerados na coluna esquerda** — 1 Cliente · 2 Itens · 3 Entrega e frete · 4 Observações e produção — espelhando a âncora de design.
- **Coluna direita de fechamento**: Tabela de preço · Fechamento (plate escuro com o total, único bloco de peso visual) · Pagamento · Comissão · Situação (FSM) · finalizador *sticky*.
- Grid de itens **editável** (quantidade, valor, desconto %, acréscimo %) com alerta de linha inválida e coluna de Ações fixa.
- **Lançamento do item por modal** (`_components/v3/LancarItem.tsx`, onda 1): escolher o produto na busca abre o lançamento, onde a **unidade do cadastro** decide o modo — `m²` pede peças+altura+largura, `m³` acrescenta espessura, `m` usa só a largura, e o resto pede a quantidade direto. A quantidade faturada é **derivada** (`peças × área`), nunca digitada, e o preview do total muda enquanto se digita. Alerta de preço abaixo da alçada (85% da tabela) e de saldo negativo de estoque — o segundo **avisa, não bloqueia**.
- Situação FSM **fail-secure**: papel ausente NEGA a ação e a tela **diz qual papel falta**; os efeitos colaterais da transição são declarados ANTES de executá-la.
- Recurso ainda não portado aparece como gatilho que **diz o que falta** (`AindaNao`) — botão que promete e não entrega é pior que botão ausente.
- Layout composto por primitivos (`Stack`/`Inline`/`Grid` de `@/Components/layout`) — [ADR 0253](../../../../memory/decisions/0253-primitivos-layout.md).

---

## Non-Goals — o que a tela NÃO faz

> ⚠️ Os dois primeiros são **declaração literal de [L]**, não inferência minha. O restante desta seção fica **pendente de [W]/[L]** — a skill `charter-write` é proibida de inferir Non-Goal, e anti-padrão inventado no charter é pior que ausente porque parece canon.

- ❌ **Não calcula.** Subtotal, desconto, imposto, acréscimo, frete e total chegam prontos do controller como dados de cena. Cálculo de valor/estoque é território `[V0]` (REGRA MESTRE, `memory/proibicoes.md`) e não entra em tela de preview — foi assim que nasceu o incidente `num_uf` de 2026-06-05 (`final_total` inflado ~×100.000 em 16 vendas do `biz=4`).

  > 🔴 **CONFLITO ABERTO — decisão de [L], não minha (2026-08-10).**
  > O porte do handoff **contradiz este Non-Goal**: a tela agora calcula subtotal,
  > desconto, imposto, frete e total no front, porque é o que a fonte de design
  > descreve (§9 — o número muda enquanto se digita, senão não há o que avaliar
  > no preview). **Não reescrevi o Non-Goal**: ele é declaração literal de [L], e
  > `charter-write` é proibida de inferir esta seção.
  >
  > O que foi feito para não repetir o `num_uf` enquanto o conflito não é decidido:
  > a tela **não é autoridade** (não grava, não tem `store()`, não tem POST), e o
  > parse pt-BR (`parseBR`) + arredondamento a 2 casas no submit (`submitSafe`)
  > vieram **literais** do handoff — são exatamente o guard daquele incidente.
  >
  > **[L] decide entre:** (a) manter o cálculo no preview e reescrever este
  > Non-Goal, ou (b) voltar aos valores prontos do controller e perder o feedback
  > ao digitar. Enquanto não decidir, o charter fica com os dois registrados.
  >
  > **Atualização 2026-08-10 (onda 1) — o conflito CRESCEU, e é honesto dizer.**
  > O lançamento do item calcula área, quantidade faturada, unitário líquido e
  > total (`_components/v3/calculo-item.ts`). Segue valendo que a tela **não é
  > autoridade** (não grava), e o guard do `num_uf` está **intacto** onde é dele:
  > preço unitário e total do item passam por `submitSafe`.
  > **Uma divergência consciente do handoff, com prova:** `submitSafe` é o guard de
  > **dinheiro** (2 casas) e o handoff o aplicava também na **medida** — o que
  > zerava item fino (tira de `0,50 × 0,004 m` → quantidade `0,00`, total
  > zerado, botão desabilitado, item fora da venda). Área não arredonda;
  > quantidade arredonda a 4 casas. Provado por dois caminhos e com controle de
  > que o caso normal não se move — detalhe em [`CreateV3.casos.md`](CreateV3.casos.md).

- ❌ **Não grava.** Sem `store()`, sem POST, sem rota de escrita, sem migration. _(Este segue intacto e é o que sustenta o item acima.)_
- ❌ **Não substitui `/pos/create`.** Não há cutover previsto (RUNBOOK §F5).
- _pendente [W]/[L]_ — demais Non-Goals.

---

## Fronteira — o que este trabalho não pode tocar

| ⛔ Proibido | Por quê |
|---|---|
| Editar `Pages/Sells/Create.tsx` | é a tela deles |
| Editar `SellPosController@create` | serve a tela deles |
| Editar componente compartilhado que `Create.tsx` importa | a alteração vaza pra tela deles pelo import |

Precisando de variação de um componente existente, **nasce cópia local** em `Pages/Sells/_components/v3/` — nunca edição do original. A subpasta `v3/` é deliberada: deixa óbvio, no path, que aquele arquivo nasceu para esta tela e **não** é consumido por `Create.tsx`.

---

## Automation Anti-hooks

> _pendente [W]_ — só [W] preenche esta seção (cada item vira Pest GUARD no CI). Não infiro.

---

## Contrato visual

> _pendente [W]/[L]_ — copy literal e ordem dos blocos. Enquanto vazio, a âncora de design abaixo é a referência.

**Âncora de design:** [`prototipo-ui/cowork/venda-v3/sells-create.jsx`](../../../../prototipo-ui/cowork/venda-v3/sells-create.jsx) — cockpit "Venda — Guia de Produção", do handoff `design_handoff_cadastro_venda` (projeto de design Oimpresso `019e2365`, 2026-08-06). Declarada em `related_prototype`, então `node prototipo-ui/ancora.mjs Sells/CreateV3` a resolve. Contexto do bundle: [`prototipo-ui/FONTE-DESIGN-venda-v3.md`](../../../../prototipo-ui/FONTE-DESIGN-venda-v3.md) — o doc mora no root porque `cowork/` é **build-only** e não aceita `.md` (regra R1 do `cowork-ssot-guard`).

> ⚠️ **Errata 2026-08-10 — esta linha apontava pro vazio, e o registro fica.**
> A redação anterior declarava a âncora em `prototipo-ui/design-oimpresso/04-modulos/vendas/sells-create.jsx`
> e prometia `http://localhost:5570` pela config `cockpit-vendas` do `.claude/launch.json`.
> **Nada disso existia.** Medido por quatro oráculos independentes: `ancora.mjs` respondia
> *"charter sem `related_prototype` nem `-page.jsx`"*; `git ls-files 'prototipo-ui/design-oimpresso/**'`
> devolvia **0 arquivos**; o diretório não existia no disco; e `grep cockpit-vendas .claude/launch.json`
> não achava entrada. Não era gitignore (`git check-ignore` limpo). As fontes viviam só dentro
> do zip do handoff, fora do repo — nenhum gate conseguia conferir fidelidade das ondas.
>
> Corrigido versionando as 12 fontes + 8 CSS em `prototipo-ui/cowork/venda-v3/`, que é onde o
> gate **required** `Ancora de design nao-shell` resolve (`anchor-content-check.mjs` monta o path
> dentro de `prototipo-ui/cowork/`; uma âncora fora daí vira `MISSING` e derruba o gate).
>
> **O `launch.json` segue sem entrada, de propósito:** a camada 0 do Design System não veio no
> handoff (`_ds/colors_and_type.css`, `styles.css`, `_ds_bundle.js` — ausentes, medido). Servido
> em `127.0.0.1:5599` e aberto no browser, o entry **não renderiza — tela em branco**: `#root`
> com 0 caracteres, `window.I` undefined, `TypeError: … reading 'Button'` e `reading 'Provider'`
> no `<App>`. Config que promete preview que não renderiza é afordância anunciada e não
> implementada. O estado está declarado em
> [`prototipo-ui/FONTE-DESIGN-venda-v3.md`](../../../../prototipo-ui/FONTE-DESIGN-venda-v3.md).

---

## Pest GUARD

`tests/Feature/Sells/SellsCreateV3ContratoTest.php` — UC-V301 (rota registrada) · UC-V302 (nenhuma rota de escrita) · UC-V303 (fronteira: o preview não encosta em `Create.tsx`/`SellPosController`/`_components` da tela viva).

Só isto está declarado porque só isto existe. **Esta seção não promete teste que não existe** — charter que promete GUARD inexistente é revogável (`how-trabalhar.md`, regra dura da perna Charter). O que ainda não tem prova está em `[BACKLOG]` no [`CreateV3.casos.md`](CreateV3.casos.md).
