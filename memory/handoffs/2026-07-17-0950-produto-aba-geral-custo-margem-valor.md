---
date: "2026-07-17"
time: "0950"
slug: produto-aba-geral-custo-margem-valor
tldr: O [?] do cabeçalho (Custo·Margem·Valor) fechou por 5 caminhos — Custo é a âncora, Valor↔Margem são bidirecionais, e o binding do oimpresso é IDÊNTICO ao do Delphi. O mapa de paridade mentia em 2 falso-créditos. A aba geral NÃO está contratada — nenhum pedido ao Claude Design foi feito, de propósito.
owners: [W]
prs: [4321, 4370, 4405]
us: [US-PROD-020, US-PROD-023, US-PROD-024]
---

# Handoff — Produto / aba geral (cabeçalho custo·margem·valor)

> **Pedido [F]:** "preciso continuar a construção da tela… verifique a aba geral (14 campos) …
> procure no charter" + "como esses campos são tratados no oimpresso?".
> **Entregue:** 3 PRs docs-only. **NÃO entregue (de propósito):** nenhum pedido ao Claude Design —
> a aba geral **não está contratada**; pedir desenho agora seria desenhar sem contrato.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**.
- `my-work owner:wagner` → 6 tasks, todas REVIEW. Relevante: **US-PROD-023** *"[G-05] Finalizar +
  promover as 8 telas React do Produto (draft→live)"* e **US-PROD-027** *"[V0] Travar o acidente do
  0-row"* (sessão irmã, tabela de preço).
- Handoffs de 2026-07-16 (3, todos de outras sessões): smoke Financeiro · grade DS · visreg+US/UC.
- PRs desta sessão: **#4321 MERGED** (18:23Z) · **#4370 MERGED** (18:25Z) · **#4405 aberto**.

## O que ficou provado (5 caminhos independentes — satisfaz a dupla-confirmação da REGRA MESTRE)

`Margem% = ((Valor / Custo) − 1) × 100` — **markup sobre custo**. Custo é a **âncora**; Valor↔Margem
se recalculam mutuamente; **editar Custo não propaga** (a chamada que propagaria está comentada no
fonte Delphi, com a nota do autor *"AQUI DEVE PERGUNTAR SE MANTEM O CALCULO ATÉ O VALOR DE COMPRA"*).

| # | Caminho | Resultado |
|---|---|---|
| 1 | Print da aba Formação de Preço (AR-PROD-093) | 62,79% |
| 2 | Fonte Delphi — `PercAplicado(VALOR, CUSTO)` | ✅ |
| 3 | Fonte oimpresso — `Util::get_percent($base=custo, $number=venda)` | ✅ |
| 4 | Base demo do instalador (1.152 produtos) | **674/675 = 99,85%** |
| 5 | **Base real de cliente de oficina** (4.342 produtos, backup 2026-03-30) | **3.569/3.668 = 97,3%** |

Hipótese rival (margem sobre venda, `(V−C)/V`): **3,9%** → descartada.

> **Método das bases:** copiadas pra scratchpad, consultadas na cópia, cópias apagadas (4,3 GB + 1,1 GB).
> Zero escrita. Só agregados saíram — nenhum dado identificável, nenhum valor BRL.

## Os 3 achados que não existiam no canon

**A-1 · flag `TEM_MARGEM_FIXA_CONTIBUICAO`** — propagação custo→preço **por produto**. Base real:
**84% em `N`** (preço fixo, margem flutua — o modo seguro que os 17 ERPs pesquisados praticam), 8,2%
em `S`, 8% NULL. **O oimpresso não tem essa flag** — só implementa o modo `N`, implicitamente
(`ProductUtil::updateProductFromPurchase`). Migrar como está **funciona por acidente pros 84% e
quebra silenciosamente pros 8,2%**. É **capacidade a preservar**, não bug a corrigir.

**A-2 · custo zero → preço zero**, sem guarda nos dois sistemas. `PercAdd(0, margem) = 0` e o default
de `MARGEM` é **100**. Base real: 453 produtos (10,4%) com custo zero, **242 deles (53,4%) com preço
zerado**. Serviço é o caso sem custo. **Buraco de mercado:** dos 17 concorrentes pesquisados (8 BR +
9 globais), **nenhum** documenta tratamento de custo zero — o Odoo tem ≥7 módulos de terceiros só pra
alertar margem baixa.

**A-3 · o `Create.tsx` é subconjunto do Blade** — o Blade exige custo/margem/valor (`required`); o
React **não tem onde digitá-los**. Não consegue substituir o Blade por falta de **função**, não de
polimento. **É o que segura as 8 telas em `draft` atrás do `X-Inertia` — explica a US-PROD-023.**

## O que foi corrigido no canon (o mapa mentia)

- **`AR-PROD-008`** — `[?]` → ✅. Não é "Valor recalcula Margem **ou** vice-versa": é **ambos**, e o
  Custo é o assimétrico.
- **`AR-PROD-006`** — Custo é âncora + coluna é **DOUBLE PRECISION** (não decimal com escala) → **sem
  truncamento na persistência**; as "6 casas" são máscara de display.
- **`AR-PROD-015`** (novo) — Custo e Margem **somem** da tela sem permissão de ver custos.
- **PARIDADE §1** — "~15 cobertos" era **~9**. Dois **falso-créditos**: `AR-PROD-014` (Tipo — o
  `products.type` é estrutura de variação, não `PRODUTO_TIPO`) e `AR-PROD-007` (**Margem %** creditada
  a *"SKU server-side + duplicate"*). Mais 3 contagens duplas.
- **PARIDADE §2** — **Preço Especial movido do §3**: topologia produto→cliente **substituída** por
  produto→tabela→cliente (decisão [W] 2026-07-15 no `SellingPrices.casos.md`). Revoga `AR-PROD-111`.

## Topologia — o padrão que se repete

| Capacidade | Legado | oimpresso |
|---|---|---|
| Preço do produto | `PRODUTO.CUSTO`/`.VALOR`/`.MARGEM` — **no produto** | `variations.*` `decimal(22,4)` — **na variação** (até `single` tem DUMMY) |
| Preço por cliente | produto → cliente (lookup) | produto → **tabela** → cliente |

**Duas vezes o mesmo padrão:** a capacidade existe, a topologia é outra. E o **binding é idêntico** —
`__add_percent` ≡ `PercAdd`; `__get_rate` ≡ `PercAplicado`. A sessão não achou divergência a corrigir:
achou **convergência a preservar**.

## Grade — eixo custo/margem: 19/100 (banda honesta 19–30)

Contra 8 ERPs BR + 9 globais, com URL por célula. **O oimpresso sabe cadastrar produto (61/100 na
FICHA); não sabe precificar produto.** Faltam itens que 6-8 de 8 BR têm como **piso**: tela de formação
de preço, valor em estoque, multiplicador de tabela. O padrão inegociável do mercado (universal nos 17):
**propagação custo→preço é sempre ato deliberado, nunca silenciosa.**

## Erros meus, catalogados (para o próximo não repetir)

1. **Li o default do código (`'S'`) e concluí a população** — é **84% `'N'`**. Sem a query, teria aberto
   US pra corrigir um perigo que não existe e **perdido a real** (não perder a flag).
2. **Confiei na taxa da base demo** (91,2% na condicional custo-zero→preço-zero) — real é **53,4%**.
3. **Apontei um "swap" na linha 2171** do motor Delphi — era **código comentado**. A inversão real está
   viva em `Unit_SQL.pas:10946` (migração de upgrade), com a irmã da linha 11418 usando a ordem oposta.
4. **Recomendei "US no SPEC / editar o SDD" como ponto de entrada** — é **exatamente o caminho refutado
   em 2026-07-16** (§5 `proibicoes.md`, 2 céticos + 7 verificadores). O canal real é o **chat**.
5. **Escrevi "ausentes do charter E do código"** — generalizava de `Create.tsx` pro sistema. Corrigido
   no #4405.
6. **Perguntei ao [F] escolhas que eram minhas** — ele cortou: *"não sei porque você continua me
   perguntando, se eu não sei e provavelmente vou errar"*. Justo: eram docs reversíveis, dentro do
   escopo já aprovado. Ver `hostinger-dns-autonomy` (*"não é helpdesk do agente"*) + R11.

## Aberto (declarado, não escondido)

- **`CALC_PMARGEM_CONTRIBUICAO`** — **não** é margem sobre venda (9,7%). Melhor hipótese: markup sobre
  **custo mínimo** (`CALC_VVENDA_CUSTO_MINIMO`) — **64,1%**, **não fechado**. Decide se o mapeamento
  `→ profit_percent` (`Controller.OImpresso.pas:856`) subfatura. O da linha 1313 (`MARGEM`) é seguro.
- **`CALC_PMARKUP`** — 0/3.668. Sem fórmula conhecida.
- **Custo médio ponderado** — piso de 8/8 ERPs BR; **não medido**. É o SPIKE da **US-PROD-024**.
- **`CUSTO_LOJA`** — existe na base demo, **ausente** na base real de oficina; o reajuste em massa da
  grade usa essa coluna (`Frame_CadProduto_Mestre.pas:2565`). Não determinado.
- **Escopo medido = oficina.** Comunicação visual (gráfica) não foi medida — perfil de custo pode diferir.
- **Resíduo do Preço Especial** — `%acr`/`%desc` por cliente (112/113) + "Manter Desconto" (114) vs
  `price_type ∈ {fixed, percentage}`. **Não verificado.**

## Próxima ação

**Nada disso tem teste.** É evidência de fonte + dado + schema, não contrato executável. A **REGRA
MESTRE §2/§3** (tabela antes→depois + aprovação) segue pendente pra qualquer ação em código de valor.

O caminho está pesquisado e registrado no PARIDADE §5: **o trio** ([ADR 0264](../decisions/0264-governanca-executavel-trio-dominio-e2e.md), `casos-gate` required) +
a regra **F3** — as 7 telas de Produto estão no baseline (`missing_casos: 243`) e *"cada tela tocada
fecha o trio dela"*: **mexer no `Create.tsx` obriga a criar o `Create.casos.md`**. Molde vivo: o
[#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300).

**O slot certo pro que ainda não tem teste** é o bullet **`[BACKLOG] <frase>` sem id** no `casos.md`
(prosa honesta pré-teste, sem gate) — **não** US, **não** UC ([how-trabalhar.md §Pedido de tela](../how-trabalhar.md)).

**Bloqueio real:** decidir se a aba geral contrata os 3 campos de dinheiro ou se eles pertencem à
**Formação de Preço** (`AR-PROD-090..103`, item nº 1 do roadmap de charters, sem charter). É decisão de
produto — [W]. **Sem ela não há o que desenhar.**

## Dívida de infra (não desta sessão, mas atrapalha)

- **PAT inválido embutido no remote** (`https://ghp_…@github.com/...`) — quebra `git push` direto e
  **vaza em toda mensagem de erro do git**. Contornei via `gh auth git-credential`. Conserto:
  `git remote set-url origin https://github.com/wagnerra23/oimpresso.com.git` + `gh auth setup-git`,
  e **revogar o token** em `github.com/settings/tokens`.
- **3 worktrees** em `.claude/worktrees/` (`produto-custo-margem`, `produto-paridade`, `produto-mapa`).
  Remover **a junction ANTES** do `git worktree remove` — senão esvazia o `vendor`/`node_modules` real
  ([proibicoes](../proibicoes.md), 2 incidentes: 05-11 e 07-14).
