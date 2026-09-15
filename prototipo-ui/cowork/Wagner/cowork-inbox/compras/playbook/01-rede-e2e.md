---
sessao: "01"
titulo: Rede — 2 specs E2E do Compras (cockpit + grade tam×cor)
dono: "[CL]"
base: 9101f86af501
prefixo: e2e/compras-cockpit.spec.ts (CRIAR) · e2e/purchase-create.spec.ts (CRIAR) · lane no workflow (${LANE} — D-LANE pendente)
nao_toca: resources/js/Pages/{Compras,Purchase}/** · Modules/Compras/** · os 2 contratos (JÁ existem) · os 10 Pest (reusar como oráculo, não editar)
depende: — (vaga 1). D-LANE só decide ONDE a lane mora, não bloqueia escrever os specs.
---
# 01 · Rede E2E

## A · Identidade — ancoragem dupla
- **alvo (layout medido no protótipo, dark, T1 estável 1245 nós):** aba **Pedidos** = `.cmp-main` com 3 filhos · `table.purchases` com **9 colunas** (Ação · Compra · Fornecedor · … · Total · A pagar · NF-e) · `SortTh` com `aria-sort` **e `<button type="button">` interno** · 34 botões · drawer de detalhe com `table.items-tbl` (Produto · Qtd · Custo unit. · Total · Venda · Margem — **a coluna Margem é do protótipo e não tem fonte: ver thread 02**).
- **âncora (código, lida em 9101f86af501):** `resources/js/Pages/Compras/Index.tsx` (28.813 B) + `components/{Drawer,AcoesDropdown,VisibilidadeColunas}.tsx` · `resources/js/Pages/Purchase/Create.tsx` (28.232 B) com `GradeMatrixInput` importado em `:26` e usado em `:459`.
- **contratos JÁ existem e são o roteiro do spec, não o produto dele:** `prototipo-ui/contrato/compras-cockpit.contract.json` (3.946 B) e `purchase-create.contract.json` (4.272 B). Leia as seções/copy/estados declarados lá e asserte **exatamente** aquilo — spec que inventa seletor próprio duplica canon.
- **por que esta thread existe:** `e2e/` tem **17 arquivos e nenhum de compras/purchase** (medido). É a única lacuna de código do módulo.

## B · Não inventar
- **Reusar:** `e2e/global-setup.ts` (sessão autenticada) · o formato dos specs vivos mais próximos — `e2e/sells-index.spec.ts` (índice com grade) e `e2e/sells-venda-balcao.spec.ts` (fluxo de escrita) · `e2e/produto-show.spec.ts` (drawer/detalhe).
- **Oráculo de regra:** os **10 Pest** de `Modules/Compras/Tests/Feature/**` (`PurchaseCalculoValorEstoqueE2ETest`, `MultiTenantTest`, `ComprasContratoFiltrosTest`, `GapsHardeningTest`…). O E2E prova o **caminho de tela**; não reimplemente asserção de domínio que o Pest já cobre.
- **Dados:** `transactions type=purchase` + `transaction_lines` com `business_id` via `Transaction::auth_scope()` (Tier 0). Nenhum Model novo, nenhuma factory nova se o Pest já tiver uma.
- Zero `page.waitForTimeout` como sincronismo: espere por seletor/estado. Copy PT-BR literal, `NF-e` com o casing legal.

## C · Comportamento a provar (EARS)
| # | QUANDO → O SISTEMA DEVE | prova no spec |
|---|---|---|
| 1 | abrir `/compras` → renderizar a grade com as colunas do contrato | tabela visível; contagem de colunas = a do contrato |
| 2 | clicar no `<button>` de um `SortTh` → reordenar server-side | `aria-sort` muda de `none` → `ascending` e a 1ª linha troca |
| 3 | filtrar → total reduzir | contador reflete; querystring carrega o filtro num reload |
| 4 | clicar na linha → abrir o drawer de detalhe | drawer monta com `table.items-tbl`; `esc` fecha 1 nível |
| 5 | desligar coluna em `VisibilidadeColunas` → coluna sai e **persiste** no cliente | reload mantém a preferência |
| 6 | ação de criar/editar/excluir → **navegar pra `/purchases`**, nunca mutar em `/compras` | asserção de URL (invariante 1 do módulo: cockpit não escreve) |
| 7 | `/purchases/create` em modo grade → 1 POST único de N `purchase_lines`, 1 célula = 1 `variation_id` | interceptar a request e contar as linhas |
| 8 | produto sem variação composta → grade de **1 eixo** (auto-detect), **nunca grade vazia silenciosa** | eixo único renderizado, com aviso |
| 9 | estoque **só após `received`** (R-PUR-004) | nenhum incremento de estoque enquanto o status não for `received` |
| 10 | sem `purchase.create` → 403 (R-PUR-003) | sem alias `compras.create` |

## Execução
```
ARQUIVOS A EDITAR : e2e/compras-cockpit.spec.ts   (CRIAR — casos 1..6)
                    e2e/purchase-create.spec.ts   (CRIAR — casos 7..10)
                    lane no workflow (D-LANE) — ver PARAR SE (c)
REUSAR            : e2e/global-setup.ts · o formato de sells-index/sells-venda-balcao/produto-show
                    os 2 contratos existentes como roteiro de seletor e copy
                    os 10 Pest como oráculo de domínio (não reimplementar)
PASSO A PASSO     : 1) gh pr list --state open × e2e/ e workflows
                    2) LER os 2 contract.json e derivar seletores/copy DELES
                    3) escrever o spec do cockpit (6 casos) e rodar local
                    4) escrever o spec da grade (4 casos), interceptando o POST
                    5) ligar na lane e rodar 3× (flake é reprovação)
                    6) placar no corpo do PR · _saida-01.md
DADO              : nenhum campo novo; nenhuma rota nova.
PARAR SE          : (a) o contrato citar âncora data-contract que a tela NÃO tem → o spec NÃO inventa
                        seletor: reporte no _saida e a âncora entra num PR próprio de 1 arquivo
                    (b) caso 7/8 exigir seed de produto variável que não existe → declare a pendência,
                        não crie fixture paralela ao Pest
                    (c) D-LANE sem resposta → deixe os specs fora do CI e diga isso no PR; NÃO crie
                        workflow novo por conta própria
                    (d) qualquer caso exigir escrita em /compras → PARE: viola a lei 1 do módulo
                        (cockpit é de LEITURA), e o defeito é da tela, não do spec
```

## Prova (o que o PLACAR confere no `main`)
- `e2e/compras-cockpit.spec.ts` e `e2e/purchase-create.spec.ts` existem
- **guarda:** os 2 `contract.json` continuam existindo e `Purchase/Create.tsx` continua contendo `GradeMatrixInput`
- `_saida-01.md` nesta pasta · placar no corpo do PR
- Não verificável daqui: verde no CI · T7 `design-diff --compare --check` · screenshot prod dark 1280
