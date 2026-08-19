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

**Os dois são honestos e os dois estão datados.** O quadro se identifica como *"Fase 1 · 2026-06-23"* e cita um bundle — `clientes-975.jsx` — que **não existe mais no espelho**. Nenhum dos dois viu os protótipos que chegaram em **13/ago**.

E o `clientes-gap.md` tem um problema mais fundo que a data: **ele analisou meio arquivo.** O [#5743](https://github.com/wagnerra23/oimpresso.com/pull/5743) (13/ago) registra que *"o `clientes-page.jsx` tinha **METADE** do arquivo vivo (58.331 vs 112.096 bytes)"* e o completou (+1075/−133). O veredito `MOCKUP-STALE` sobre o Index saiu de uma fonte truncada — não é conclusão a reusar.

---

## 4.1 · Direção medida, par a par (2026-08-18)

O cabeçalho de 4 protótipos declara ter sido feito **por paridade com a produção** — `cliente-form.jsx` chega a listar os arquivos lidos (`_form/ClienteForm.tsx`, `_form/DadosFiscaisBRSection.tsx`, `_form/ClienteRail.tsx`, `Lib/format-br.ts`). Mas **declaração de autor não é medição**, e ao conferir aparecem melhorias que o redesenho trouxe:

| Par | Veredito | O que sustenta |
|---|---|---|
| **Import** | 🟠 **protótipo à frente em 4** | drag-and-drop (`onDrop`/`onDragOver` = 0 na viva) · barra de progresso (`role="progressbar"` = 0) · teclado no dropzone (`tabIndex`/`onKeyDown` = 0 — **a11y**) · "Baixar as linhas com erro" (0). A viva está à frente em 2: guard da extensão PHP `zip` e limite de 10 MB declarado |
| **Map** | 🟠 **protótipo à frente em 1** | protótipo usa **OpenStreetMap embed sem chave de API**; a viva usa `maps.google.com/maps?q=…&output=embed` hardcoded — que é exatamente o gap do scorecard (`preflight_conformance` 66, o mais baixo do módulo) |
| **Ledger** | ✅ **paridade** | o "saldo acumulado" do protótipo é a coluna `Saldo` (`line.balance`) da viva — **vocabulário diferente, mesma capacidade**; e a viva calcula no servidor em vez de recalcular no cliente |
| **Create/Edit** | ✅ **paridade estrutural** | as 5 seções do protótipo (Identificação · Dados fiscais BR · Contato · Endereço · Financeiro) existem todas em `_form/` |
| **Index** | 🟠 **1 gap estrutural** (Onda 4 · §4.2) | a viva domina em a11y, export e paginação; o protótipo tem **views por papel** que a viva não tem |

> **Armadilha registrada:** três sondas por **texto** desta medição deram falso negativo — `acumulado` × `balance` (vocabulário), `erro` × `Falha` (case), e labels literais contra tela data-driven. Comparar protótipo × tela viva por string **penaliza a produção por construção**: o protótipo carrega dados mock hardcoded, a viva recebe props. Medir **capacidade**, nunca ocorrência de palavra.

**Resposta à pergunta "copiar pra qual lado?":** hoje há **5 itens medidos para adotar do protótipo** (4 do Import + 1 do Map) — todos de UX/a11y/acabamento, **nenhum de regra de negócio**. No sentido inverso não há pendência medida: os protótipos de 13/ago já nasceram do código vivo. O `Index` tem um 6º item, de natureza diferente — §4.2.

---

## 4.2 · Onda 4 — o `Index` re-medido contra o protótipo COMPLETO

**Premissa conferida antes de medir:** o `clientes-page.jsx` do espelho tem **112.096 bytes** — bate exatamente com o "vivo" citado no [#5743](https://github.com/wagnerra23/oimpresso.com/pull/5743) (o truncado tinha 58.331). É o completo. A tela viva tem 112.605 bytes.

**A viva domina na maior parte** (contagem em `Index.tsx` + `_components/` + `_drawer/`):

| capacidade | protótipo | tela viva |
|---|---:|---:|
| `aria-*` (a11y) | 24 | **123** |
| `role=` | 6 | **33** |
| export/CSV/XLSX | 20 | **73** |
| paginação | 10 | **36** |
| favoritos · atalhos · grupos · crédito | — | 39 · 18 · 14 · 24 |

**O gap único, e ele é estrutural: views por papel.** O protótipo declara no cabeçalho *"refactor: dispatcher por role. **Cada entidade tem vocabulário próprio**"* e entrega 5 views que **de fato diferem** em filtros e colunas:

| View | filtros/colunas próprios |
|---|---|
| `SupplierView` | "Com pedido aberto" · "Críticos" · "Com saldo a pagar" / "Em dia" · coluna **Fornecedor** |
| `EmployeeView` | **CLT · PJ · Estagiário · Sócio** |
| `RepresentativeView` | "Ativos" / "Ociosos" · colunas **Comissão · Carteira · Vendas no mês** |

A tela viva **tem as 6 tabs** (`all`/`customer`/`supplier`/`employee`/`representative`/`other`), mas os 9 termos distintivos acima dão **0 ocorrências** — e o payload é **único e cliente-cêntrico**: `buildClienteIndexCustomers($business_id, $type)` recebe o `$type` mas devolve sempre `total_os`, `os_abertas`, `valor_aberto`, `saldo_devedor`, `last_purchase_at`. O tipo **filtra**, não muda os campos. Vocabulário medido: 94× "cliente" contra 5 "fornecedor", 3 "representante", 3 "funcionário".

### Custo real: isto NÃO é "copiar o protótipo"

O gap é de **backend**, não de UI — medido campo a campo:

| papel | o que falta | custo |
|---|---|---|
| Representante | comissão existe, mas em tabela separada (`crm_contact_person_commissions`); `contacts` não tem `commission` | JOIN + expor no payload |
| Funcionário | regime CLT/PJ/Estagiário/Sócio **não existe** em `Contact` | migration + UI — **feature nova** |
| Fornecedor | "pedido aberto" / "saldo a pagar" derivam de compras, não do payload atual | agregação nova |

**Conclusão da Onda 4 (dupla, e as duas partes importam):** o veredito **geral** `MOCKUP-STALE` do `gap.md` continua **não reusável** — saiu de arquivo truncado. Mas o gap **específico** que ele nomeou (*"perspectiva Fornecedor/Funcionário/Representante — vocabulário por papel"*) **se confirma** na medição contra o arquivo completo. Ele acertou o item mesmo tendo visto metade.

E como adotar exige schema novo, isto **não é onda de paridade** — é feature, e cai na [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md): entra com sinal de cliente, não por existir no protótipo.

---

## 5 · Ondas propostas

A regra que as irmãs já aplicam: **paridade tem três direções**, e a onda **encerra sem `Edit`** quando a medição der 🔵 (precedente registrado: a Caixa Unificada do Financeiro é *"o OURO, não repintar"*).

| Onda | Escopo | Toca `.tsx`? | Pré-requisito |
|---|---|---|---|
| **0** ✅ | Declarar as âncoras — **5 de 6 feitas**; `Map` bloqueado por decisão (§3.1) | não | — |
| **1** ✅ | Medir a direção par a par (§4.1) — 5 itens para adotar, 2 pares em paridade | não | Onda 0 |
| **2** | **Import:** drag-and-drop + progresso + teclado no dropzone + baixar linhas com erro | **sim** | — |
| **3** | **Map:** trocar iframe Google hardcoded por OSM sem chave (fecha o `preflight` 66) | **sim** | US do Map (§3.1) |
| **4** ✅ | Re-medir o `Index` contra o protótipo **completo** (§4.2) — viva domina; 1 gap: views por papel, que é **backend**, não UI | não | — |
| **5** | Decidir o `cliente-grupos.jsx` órfão: tela que falta, ou dropdown ⋮ do Index? | decisão [W] | — |
| **6** | Snapshot `DesignSync` + `--compare` → fidelidade visual (o que este doc **não** mede) | não | — |

**Ondas 2 e 3 não tocam valor** (Import lê planilha; Map troca provedor de mapa) — não disparam a regra-mestre Tier 0. A Onda 4, se virar trabalho no `Index`, **dispara**: a tela exibe saldo e limite de crédito, e aí vale dupla confirmação + tabela antes→depois.

A **Onda 3 está bloqueada pela mesma decisão do §3.1**: o Map não tem US porque a ADR 0105 o segurou. Vale registrar que o **custo caiu** — o desenho da solução já existe no protótipo; falta o sinal, não o desenho.

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
