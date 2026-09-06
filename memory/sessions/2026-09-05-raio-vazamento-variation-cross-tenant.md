---
date: "2026-09-05"
topic: "Raio do vazamento cross-tenant Tier 0 na model Variation (nucleo) — medido, nao corrigido; escolha e de [W]"
authors: ["C"]
prs: [6883]
outcomes:
  - "Cadeia do achado confirmada nas duas pontas por leitura de origin/main"
  - "Raio medido: 124 arquivos, 75 blocos de query, 57 sem filtro nenhum"
  - "Contexto sem sessao medido: 0 jobs de fila, 0 comandos artisan"
  - "Numero de PRODUCAO NAO medido — o CT 100 nao carrega dado de negocio real"
  - "3 opcoes com custo; nenhuma aplicada — decisao [W]"
related_adrs: ["0093-multi-tenant-isolation-tier-0", "0358-doutrina-de-teste-tenant-98-supersede-0101"]
---

# Raio do vazamento cross-tenant na model `Variation` — medição para decisão de [W]

## TL;DR

O achado do [#6883](https://github.com/wagnerra23/oimpresso.com/pull/6883) é real e está confirmado nas duas pontas. O raio é **maior do que o Repair**: `Variation` é model do núcleo e tem **75 blocos de query em 34 arquivos**, dos quais **57 (76%) não filtram por tenant de forma alguma**.

Mas **o número que decidiria a urgência não foi obtido**, e isso é um resultado, não um esquecimento: **o CT 100 não tem nenhum negócio real** — os tenants dos 3 bancos lá são todos fictícios de CI. As 7 ocorrências de vazamento que a query encontrou são **do próprio teste do #6883, rodado hoje entre 19:12 e 19:59**. Produção continua **não medida**, porque o único lugar com dado de cliente é o Hostinger, que esta sessão foi instruída a não tocar.

Leitura honesta com o que se sabe: **falha latente, de raio grande, com reprodução provada** — não incidente confirmado de cliente. A urgência muda se o número de produção vier.

## O achado — cadeia confirmada (leitura de `origin/main`, não do working tree)

| Elo | Onde | O que faz |
|---|---|---|
| Grava sem conferir dono | `Modules/Repair/Http/Controllers/JobSheetController.php:1094` `saveParts` | `$parts = $request->input('parts')` vai direto para `$job_sheet->parts`. A OS é escopada (`JobSheet::where('business_id', ...)`); **o conteúdo de `parts` não é validado**. |
| Resolve sem escopo | `Modules/Repair/Entities/JobSheet.php:199` `getPartsUsed()` | `Variation::whereIn('id', $variation_ids)` com `->with('product')`. Sem filtro de tenant. |
| Model sem escopo | `app/Variation.php` | Zero `addGlobalScope`, zero `business_id`, zero trait de tenant. A tabela `variations` também não tem a coluna — **o dono do tenant é `products.business_id`**. |

Viola a [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) (Tier 0 irrevogável).

## Item 1 — varredura contada (`git grep` contra `origin/main`, sem `head_limit`)

Quatro formas de referência, unidas e deduplicadas por arquivo:

| Forma | Linhas |
|---|---|
| `Variation::` estático (word-boundary exclui `ProductVariation::` — controle negativo: 0) | 82 |
| FQCN/import seguido de não-letra (exclui as irmãs `VariationGroupPrice` / `VariationLocationDetails`) | 45 |
| `->variations` (relação) | 170 |
| `variations` como tabela crua em `table(` / `join(` / `from(` | 42 |

**União: 124 arquivos PHP distintos.** Decompostos:

- **31 blades** — views, só renderizam dado já carregado; não são call-sites de query
- **31 testes**
- **62 arquivos executáveis** — o denominador que importa

Distribuição dos executáveis por área: `app/Http/Controllers` 16 · `Modules/Manufacturing` 12 · `app/` (models raiz) 8 · `Modules/Repair` 5 · `Modules/ProductCatalogue` 5 · `Modules/Officeimpresso` 5 · `database` 5 · `Modules/Woocommerce` 4 · `app/Utils` 3 · `app/Domain` 2 · `Modules/Connector` 2 · `Modules/Compras` 2 · `Modules/Vestuario` 1 · `app/Exports` 1.

**Contexto sem sessão — a pergunta que mais pesava no custo:**

- **0** classes com `implements ShouldQueue` (job de fila)
- **0** classes `extends Command` (comando artisan)
- **2** controllers de API: `Modules/Connector/Http/Controllers/Api/ProductController.php` e `.../SellController.php`

Os 2 da API rodam sob `auth:api` (Passport — `Modules/Connector/Routes/api.php:10`), **sem middleware de sessão web**, e resolvem o tenant do token (`$business_id = $user->business_id`, `ProductController.php:285`), não da sessão.

## Item 2 — quem já filtra por conta própria

Medido por bloco de query (`Variation::` até o `;`, multi-linha), não por linha:

| | Blocos |
|---|---|
| Total de blocos fora de teste | **75** |
| Filtram sozinhos (`business_id`, `whereHas('product')`, `join('products')`) | **18** (24%) |
| **Sem filtro nenhum** | **57** (76%) |

Concentração dos sem-filtro: `app/Utils/ProductUtil.php` (18 blocos, 5 filtram) · `app/Http/Controllers/ProductController.php` (7, 3 filtram) · `PurchaseController` e `SellPosController` (4 cada, 2 filtram cada) · `Modules/Manufacturing` (9 blocos ao todo, 2 filtram).

> Nota de método: a primeira versão deste detector era line-based e deu "não filtra" em **34 de 34** — distribuição que não discrimina é sinal de medidor quebrado, não de mundo (§5 2026-07-17). Refeito multi-linha, com controle positivo em `ProductController` (achou 2 blocos com `business_id`).

## Item 3 — o dado real: **NÃO MEDIDO em produção**

O que **foi** medido, no CT 100 (`oimpresso-staging-db`, MariaDB 11.8):

| Banco | Tabelas | OS (`repair_job_sheets`) | Negócios |
|---|---|---|---|
| `oimpresso_staging` | 387 | 173 (25 com peças) | 4 |
| `oimpresso_qa` | 379 | 0 | 4 |
| `oimpresso_kbf` | 373 | 0 | 3 |

Query de vazamento no staging (`JSON_TABLE` sobre `JSON_KEYS(parts)` → `variations` → `products`), com controle positivo (25 pares OS↔peça resolvidos) e 0 referências órfãs:

- **7 ocorrências** de peça de outro negócio dentro da OS — **todas** `os_biz=98 → peca_biz=99`
- Criadas **hoje, 2026-09-05, entre 19:12 e 19:59**
- Vazamento **excluindo** os tenants fictícios 98/99: **0** — mas o denominador fora deles **também é 0**

Os negócios do staging: `1 = CI Biz` · `2 = CI Biz 2` · `98 = CI Tenant 98 (ficticio)` · `99 = CTM Test Biz Adversario#99`. **Nenhum é cliente.** Os 98/99 são os tenants fictícios da [ADR 0358](../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md), e as 7 ocorrências são o rastro do `JobSheetAddPartsContratoTest` do #6883 rodando — o CT 100 persiste dado entre runs, como o `CLAUDE.md` avisa.

**Portanto:** aquele `0` é o zero de um conjunto vazio, não evidência de ausência (§5 2026-07-29 — não colapsar "não consegui medir" num estado do objeto medido). O CT 100 foi provisionado por schema + seed do CI, **não por dump de produção**; ele não tem como responder a esta pergunta. O dado de cliente está no Hostinger, que esta sessão foi instruída a não tocar.

**O que os 7 provam, e é valioso:** o mecanismo funciona ponta a ponta — foi possível gravar peça de um negócio na OS de outro **e exibi-la com o nome do produto alheio**. Reprodução, não incidente.

## Item 4 — as três opções, com custo medido

### (a) Escopo global na model `Variation`

O mecanismo **já existe e não precisaria ser inventado**: `app/Concerns/BelongsToBusinessViaParent` + `app/Scopes/ScopeByBusinessViaParent`, feitos exatamente para model filha cujo tenant vem do pai. **26 models já o usam** (Jana, Essentials, Ponto). Aplicar seria declarar o trait e apontar a relação pai `product`, que já existe em `Variation.php`.

| | |
|---|---|
| Raio | 75 blocos, 34 arquivos, 13 áreas — **máximo** |
| Fecha a classe? | **Sim** — é a única que fecha |
| Risco de contexto sem sessão | **Menor do que se supunha.** O scope é fail-open por desenho: sem `auth()->check()` ou sem `session('user.business_id')` ele retorna sem filtrar. Some-se a isso os **0 jobs / 0 comandos** medidos, e a API Connector que resolve por token — a exceção explícita que a hipótese previa tem superfície medida perto de zero |
| Custo real 1 | **5 dos 75 blocos tocam preço/quantidade/estoque** (`ProductUtil` 2, `TransactionUtil` 1, `ImportOpeningStockController` 1, `SellingPriceGroupController` 1) → dispara a **REGRA MESTRE** de `proibicoes.md`: dupla prova por 2 caminhos independentes + tabela antes→depois **antes** de aplicar |
| Custo real 2 | `whereHas('product')` injeta subquery em caminho quente — `ProductUtil` tem 18 blocos e serve venda, compra e estoque. Precisa de medição de plano de query, não de suposição |
| Custo real 3 | O mesmo fail-open que reduz o risco **também significa que o scope não fecha o buraco em CLI** — ali a defesa continua sendo passar o tenant à mão |
| Conserta o dado já gravado? | **Não** |

### (b) Filtro no ponto de uso do Repair

Filtrar em `saveParts` + `getPartsUsed`.

| | |
|---|---|
| Raio | 2 métodos, 1 módulo — **mínimo** |
| Fecha a classe? | **Não** — deixa os outros 57 blocos abertos |
| Custo | Baixo. Nenhum dos 2 toca cálculo de valor/estoque (`getPartsUsed` lê `quantity` do JSON já gravado, não recalcula) |
| Conserta o dado já gravado? | **Não**, mas **para de exibi-lo** |

### (c) Validação na entrada

Exigir em `saveParts` que cada identificador de peça pertença ao business da sessão.

| | |
|---|---|
| Raio | 1 método |
| Fecha a classe? | **Não** |
| Custo | Baixo |
| Conserta o dado já gravado? | **Não**, e **continua exibindo** o que já está lá |

> (b) e (c) são complementares, não alternativas: (c) barra o novo, (b) esconde o antigo. Nenhuma das três limpa o que já foi gravado — isso seria um quarto trabalho, e ele exige antes o número do item 3.

## Recomendação

**(c) + (b) agora; (a) como trabalho próprio e separado.** As duas primeiras são baratas, cabem num PR pequeno, e fecham o caminho provado pelo teste — o `UC-JSP-05` fica verde por correção, não por conveniência. A (a) é a única que fecha a classe e o mecanismo já existe, mas ela atravessa 5 pontos de valor/estoque e um caminho quente: é PR com dupla prova e antes→depois, não carona.

O que eu **não** recomendo é tratar (a) como pré-requisito de (b)/(c) — isso deixaria a porta provada aberta enquanto se mede o resto.

## O que esta sessão não fez (deliberado)

- Não alterou `app/Variation.php` nem nenhum caminho de gravação
- Não usou `withoutGlobalScopes` em lugar nenhum
- Não tocou nos outros 3 achados do #6883 (500 em vez de 404, 302 em vez de 404, divergência `submit_type`)
- Não rodou Pest local — as medições de banco foram no CT 100
- Não mediu produção — declarado acima, com a razão

## Sessões paralelas no momento (Repair)

`whats-active` não está exposta como tool nesta sessão (o brief chegou via hook curl); usei `list_sessions` como substituto — fallback legítimo por `how-trabalhar.md` §Fallback, e declaro que é substituto, não a porta canônica. **6 sessões no Repair**, das quais uma toca o mesmo arquivo do achado: `claude/elegant-lamarr-3a2b38` ("Pôr os 4 contratos do JobSheet na lane MySQL", rodando, sem PR). As outras: `claude/zen-liskov-985128` (#6886), `claude/strange-zhukovsky-3926b3` (#6882), `claude/gallant-boyd-9979a0` (#6884), `claude/brave-wescoff-375ae1` (#6878), `claude/magical-jones-9c25ce` (#6874, merged).

Este PR toca **um arquivo novo em `memory/sessions/`** — sem interseção com `SUPERFICIE.md` nem com código.

## Reprodução

Os comandos completos (varredura, contexto sem sessão, detector multi-linha e o `.sql` do CT 100) estão no corpo do PR desta sessão, para poderem ser colados e re-rodados sem edição.

## Pointers

- [#6883](https://github.com/wagnerra23/oimpresso.com/pull/6883) — o teste que provou (UC-JSP-05, vermelho de propósito)
- [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0
- [ADR 0358](../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md) — tenants fictícios 98/99
- [Triagem 2026-07-17](2026-07-17-triagem-floor-mt-vazamento-scope-viaparent.md) — irmã: o bug do `resolveRelation` no **mesmo** scope. Não cobriu `Variation` (grep: 0 ocorrências)
