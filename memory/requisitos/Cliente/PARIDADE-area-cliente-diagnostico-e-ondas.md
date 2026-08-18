# Área Cliente (`contacts`) — paridade protótipo × produção: diagnóstico medido e ondas

- **Data da medição:** 2026-08-18 · **base:** `origin/main` `6b923a26d` (worktree 0 ahead / 0 behind)
- **Origem:** [W] perguntou *"qual a paridade do modulo contacts? contacts com o prototipo. acho que tem que fazer a paridade de ondas? como funciona?"*
- **Irmãos vivos:** [Jana](../Jana/PARIDADE-area-jana-diagnostico-e-ondas.md) (#5916) · [Forja](../Forja/PARIDADE-area-forja-diagnostico-e-ondas.md) (#5921) · [Financeiro](../Financeiro/RUNBOOK-paridade-ondas.md) (#5917) — os três mergeados em `main` em 2026-08-18. Mesmo protocolo, mesma Onda 0 bloqueante.

> **Limite deste documento.** Tudo abaixo é **estrutural** (leitura de código + espelho versionado + portas vivas). **Não mede fidelidade visual:** isso exige `cowork-mirror-freshness --compare` (SYNC) + sonda `design-diff` nos dois renders. O `--compare` **abortou** aqui — exige um `snapshot.json` do `DesignSync` que não existe. Onde este doc diz "à frente" ou "atrás", é **estrutura**, nunca pixel.

---

## 0 · Correção de premissa: não há módulo `Contacts`

`contacts` é **tabela core do UltimatePOS**, não módulo. A superfície real:

| Camada | Onde |
|---|---|
| 7 telas Inertia | `resources/js/Pages/Cliente/{Index,Show,Create,Edit,Import,Ledger,Map}.tsx` |
| Drawer 760px | `Pages/Cliente/_drawer/*` (10 abas) — [ADR 0179](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md) |
| Módulo | `Modules/Crm` (login de contato, comissões) + `Modules/Connector` (API) |
| Docs | `memory/requisitos/Cliente/` **e** `memory/requisitos/Crm/` (os 8 `*-visual-comparison.md` vivem em `Crm/`) |

ADRs donas: [0179](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md) · [0188](../../decisions/0188-contacts-multi-type-flag-aditiva.md) · [0197](../../decisions/0197-extend-contacts-absorcao-pessoas-legacy.md) · [0200](../../decisions/0200-contacts-sync-canon-amends-0197-0199.md) · [0301](../../decisions/0301-separar-cliente-deprecar-crm-pipeline.md)

---

## 1 · Retrato medido

Cada número traz a porta viva que o reproduz. Nada estimado.

| O que | Valor | Porta viva |
|---|---|---|
| Telas · charter · casos.md · scorecard | **7 · 7 · 7 · 7** | `npm run screen-coverage:report` |
| E2E · VRT · L2 | **1 · 1 · 1** | idem |
| UC declarados | **22** (Index 4 · Show 3 · Create 3 · Edit 4 · Import 1 · Ledger 5 · Map 2) | `grep -cE "^\s*###? *UC-" <Tela>.casos.md` |
| Âncora Cowork **antes** desta sessão | **1/7** (só `Show`, e como `n/a`) | `node prototipo-ui/ancora.mjs Cliente/<Tela>` |
| Âncora Cowork **depois** (Onda 0) | **6/7** — 5 `✓` + 1 `n/a` legítimo · **`Map` bloqueado** (§3.1) | idem |
| `.map.json` versionado | **0** | `node scripts/governance/design-code-map-check.mjs --check` |
| Frescor do espelho | **⚠️ NÃO MEDIDO** | `--compare` aborta sem snapshot `DesignSync` |
| `gap.md` | **2** (`Cliente/clientes-gap.md` + `Crm/clientes-gap.md`, conteúdos **diferentes**) | `design-code-map-check --check` |
| Débito `casos:check` | sem violações novas (**−17** vs baseline) | `node scripts/casos-coverage-guard.mjs` |

---

## 2 · Os 8 protótipos de cliente e a quem cada um serve

Pareamento feito por **estrutura** (`<h1>` + conteúdo renderizado), nunca por semelhança de nome — a lápide de 2026-08-11 (`chat-jana.jsx`) é exatamente sobre isso.

| Protótipo (espelho) | `<h1>` / conteúdo | Serve | Entrou no espelho |
|---|---|---|---|
| `clientes-page.jsx` (2060 ln) | lista · `FilterDropdown` · `Avatar` · `SaldoNeg` | **Cliente/Index** | 2026-06-23 |
| `cliente-form.jsx` (312 ln) | `{modo === "editar" ? "Editar cadastro" : "Novo contato"}` | **Create + Edit** (o protótipo tem os 2 modos) | 2026-08-13 |
| `cliente-import.jsx` (120 ln) | "Importar clientes" | **Import** | 2026-08-13 |
| `cliente-extrato.jsx` (170 ln) | "Extrato do cliente" | **Ledger** | 2026-08-13 |
| `cliente-mapa.jsx` (105 ln) | "Mapa de clientes" | **Map** — pareado, **não declarado** (§3.1) | 2026-08-13 |
| `cliente-drawer760.jsx` (834 ln) | `CdIdentificacao`, `CdStatus`… | **componente** do Index (ADR 0179) — não é tela | 2026-08-13 |
| `cliente-grupos.jsx` (156 ln) | "Grupos de cliente" | **⚠️ nenhuma tela Inertia** — ver Onda 4 | 2026-08-13 |
| `crm-ficha.jsx` (184 ln) | frota: "Actros" · "Motorista" · "Abrir na Oficina" | **❌ NÃO é âncora do Cliente** — é cockpit de frota/oficina | — |

**`Cliente/Show` fica `n/a` de propósito:** declara *"herda PT-03 Detalhe"*, e a ADR 0179 substituiu o Show fullpage pelo drawer. Usar `crm-ficha.jsx` nele seria ancorar numa tela que desenha outra coisa.

---

## 3 · Onda 0 — **executada** nesta sessão (as irmãs a planejaram)

**O defeito:** 6 dos 7 charters não tinham `related_prototype`. Declaravam a ligação em `bundle_source:` (Index) e em `mwart_pattern_reuse.blueprint_cowork:` (Create/Edit/Import/Ledger) — **campos que o resolvedor não usa como âncora persistente**: o caminho `bundle_source` do `ancora.mjs` só roda `if (stagingDir)`, isto é, apenas quando se passa `--staging`.

**A causa não foi descuido — foi envelhecimento.** Os charters foram validados em **2026-06-24**; naquele dia o único protótipo de cliente no espelho era o `clientes-page.jsx` (entrou em 23/jun). Apontar todos para ele era **correto na data**. Os 6 protótipos específicos chegaram em **2026-08-13** e ninguém religou os charters.

**A armadilha que quase peguei** — e que o PR irmão do Financeiro (#5917) mediu em **12 dos 20** charters não-`n/a` do repo: escrever `related_prototype: <path> (PT-0X)`. O sufixo entre parênteses entra no path, o arquivo resolvido não existe, e o comando estampa `✓` por ausência. O selftest do `ancora.mjs` tem BITE dedicado a isso. **Path puro, sempre.**

Aplicado (+1 linha / −0 por arquivo, com teste de identidade — remover a linha inserida devolve o original byte-a-byte):

```
Index  → prototipo-ui/cowork/clientes-page.jsx
Create → prototipo-ui/cowork/cliente-form.jsx
Edit   → prototipo-ui/cowork/cliente-form.jsx
Import → prototipo-ui/cowork/cliente-import.jsx
Ledger → prototipo-ui/cowork/cliente-extrato.jsx
```

**Recibo (o consumidor rodado com a mudança aplicada, não a leitura do texto):**

| | antes | depois |
|---|---|---|
| `ancora.mjs Cliente/*` | 6× `⚠️ charter sem related_prototype` | 5× `âncora ✓ [related_prototype (charter)]` |
| `git diff --numstat` | — | `1 0` em cada um dos 5 |
| `cowork-ssot-guard` | — | `✓ fonte única OK` |
| `casos-coverage-guard` | — | sem violações novas (−17) |

### 3.1 · O `Map` ficou de fora — e a razão é uma decisão registrada, não esquecimento

A âncora do `Map` **existe e é conhecida**: `prototipo-ui/cowork/cliente-mapa.jsx` (`<h1>Mapa de clientes`). Ela foi declarada e **revertida** no mesmo PR, por este motivo:

O gate `charter-us-lint` é **no-new-lie**: morde charter *tocado* que não tenha `related_us` válido. Dos 7 charters, o `Map` é o único sem — e **não é lacuna a preencher**. O [SPEC do Cliente](SPEC.md) (linha 331, 2026-07-03) registra:

> Segurados ⏸️ ADR 0105: RFM, campos custom, **Map lib**, merge dup, header DS.

O `Map` foi **deliberadamente segurado** pela [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) (backlog só recebe item com sinal de cliente). As US-CRM-079..085 criadas naquele dia cobrem Import (082) e Ledger (084); **nenhuma cobre o Map**, por decisão.

O lint exige slug `US-…` real — não aceita `n/a` nem `_pendente_`. Logo as saídas eram: **(a)** inventar uma US — falsificação, e contra a ADR 0105; **(b)** criar US nova — decisão [W] que exige sinal; **(c)** não tocar o charter. Escolhi **(c)**, que é a doutrina forward-only: *normalizar legado só quando o toque paga a dívida que ele acorda*.

**Desbloqueio:** quando existir sinal de cliente para o Map (ADR 0105), a US nasce, o `related_us` é declarado e a âncora entra em 1 linha — o pareamento já está medido aqui.

---

## 4 · O veredito 🔵 "À FRENTE" é um fóssil de 23/jun

`prototipo-ui/FRESCOR-PRODUCAO-vs-PROTOTIPO.md:14` declara o Cliente **🔵 produção À FRENTE**, com o aviso *"Não propor split/layout antigo"*. O `Cliente/clientes-gap.md` (30/jun) concorda: veredito **MOCKUP-STALE**, tela viva à frente em header, KPIs, filtros e busca.

**Os dois são honestos e os dois estão datados.** O quadro se identifica como *"Fase 1 · 2026-06-23"* e cita um bundle — `clientes-975.jsx` — que **não existe mais no espelho**. Nenhum dos dois viu os 6 protótipos que chegaram em **13/ago**.

Isso **não** significa que a produção ficou atrás. Significa que o veredito atual **não cobre** o que entrou depois, e re-medir é barato agora que a Onda 0 deu âncora às 6. Tratar o 🔵 de junho como estado de hoje seria ler fóssil como retrato — e tratá-lo como obsoleto sem medir seria o erro simétrico.

---

## 5 · Ondas propostas

A regra que as irmãs já aplicam: **paridade tem três direções**, e a onda **encerra sem `Edit`** quando a medição der 🔵 (precedente registrado: a Caixa Unificada do Financeiro é *"o OURO, não repintar"*).

| Onda | Escopo | Toca `.tsx`? | Pré-requisito |
|---|---|---|---|
| **0** ✅ | Declarar as âncoras — **5 de 6 feitas**; `Map` bloqueado por decisão (§3.1) | não | — |
| **1** | Snapshot `DesignSync` + `--compare` → saber se o espelho está fresco | não | Onda 0 |
| **2** | Re-medir o FRESCOR do Cliente contra os protótipos de 13/ago | não | Onda 1 |
| **3** | Uma tela por onda, começando por `Ledger` e `Map` (menor risco) | só se der 🟠 | Onda 2 |
| **4** | Decidir o `cliente-grupos.jsx` órfão: tela que falta, ou dropdown ⋮ do Index? | decisão [W] | — |
| **5** | `Index` + drawer 760 — **última** | só se der 🟠 | Ondas 2-3 |

**`Index`, `Create` e `Edit` exibem valor** (saldo, crédito, limite) e herdam a regra-mestre Tier 0: dupla confirmação por dois caminhos independentes + tabela antes→depois ao [W] **antes** de aplicar.

---

## 6 · O que NÃO fazer

- **Não** re-adicionar counter numérico na tab do Index — foi decisão explícita de [W] em 2026-05-25 (*"duplicava o KPI strip"*), registrada como anti-regressão no `clientes-gap.md`.
- **Não** adotar o card "Faturamento +12% vs ontem" do mockup — número decorativo sem fonte; viola a regra de valor por dois caminhos.
- **Não** ancorar tela do Cliente em `crm-ficha.jsx` — desenha frota/oficina.
- **Não** absorver `crm-page.jsx` (funil de deals) na tela Cliente — outra entidade, e a [ADR 0301](../../decisions/0301-separar-cliente-deprecar-crm-pipeline.md) já separou.

---

## 7 · Achado alheio a este escopo (reportado, não consertado)

`node prototipo-ui/ancora.mjs --selftest` **falha 1 asserção** neste worktree:

```
[FAIL] BITE real: zero fantasma na âncora da Jana (P-1 consertado em 2026-08-13)
```

Os 6 fantasmas são `AnaliseChequesService`, `AnaliseChurnService`, `AnaliseConcentracaoService`, `AnaliseFaturamentoService`, `AnaliseFrotaService`, `AnaliseInadimplenciaService` — símbolos citados por `prototipo-ui/cowork/jana-merge.jsx` que **não existem no repo**.

**Não foi causado por esta sessão:** `grep Cliente prototipo-ui/ancora.mjs` → 0 ocorrências; a asserção lê `jana-merge.jsx` e não passa por charter de Cliente. O arquivo foi tocado por #5738 e #5761 em 13/ago — o #5738 declara ter consertado o P-1, e a asserção que afirma isso está vermelha. A sessão da Jana estava ativa no momento desta medição; **não toquei** para não colidir.
