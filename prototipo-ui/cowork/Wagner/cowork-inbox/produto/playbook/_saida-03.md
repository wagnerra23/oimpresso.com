---
sessao: "03"
titulo: "Produto/Cadastros — abas Variações, Grupos de preço e Garantias — saída da thread"
autor: "[CL]"
criado: 2026-10-05
base: cb1fe1d6f4
thread: 03-cadastros.md
veredito: "entregue — as 6 abas da tela Produto/Cadastros estão vivas; /variation-templates, /selling-price-group e /warranties abrem a tela na aba de cada um. Criar/editar segue nos modais da Blade (thread 10)."
---

# _saída 03 · Cadastros de apoio — Variações · Grupos de preço · Garantias

**Placar: entregue 2 de 2 provas** (`VariationTemplateController` e `WarrantyController` contêm
`Inertia::render('Produto/Cadastros/Index'`). Dois PRs: [#8668](https://github.com/wagnerra23/oimpresso.com/pull/8668)
(recusa de exclusão) e o PR das abas, empilhado nele.

## Abertura
- Dependências: thread 01 (#8349, permissões `variation.*`/`warranty.*`) e 02 (#8371, #8375) mergeadas.
- A-P1 já estava fechado pela 01: os dois controllers checam `can()`. Esta thread usa essas permissões, não cria nenhuma.
- `nao_toca` (`Pages/Produto/Unificado/`) respeitado.

## O que entrou

| arquivo | o quê |
|---|---|
| `app/Http/Controllers/VariationTemplateController.php` | `index` desvia a visita Inertia pra `Produto/Cadastros/Index` na aba Variações (`X-Inertia` vence o `ajax()`, §5 2026-09-08; `?classico=1` mantém a Blade). `destroy` **recusa** modelo em uso (PR 1). |
| `app/Http/Controllers/WarrantyController.php` | `index` desvia pra aba Garantias. |
| `app/Http/Controllers/SellingPriceGroupController.php` | `index` desvia pra aba Grupos de preço (mesma `product.create` de antes). |
| `app/Http/Controllers/UnitController.php` | o montador de props virou `propsCadastros($abaPadrao)` (público), usado pelas 4 rotas. Abas novas: `variacoes` (valores + uso), `grupos` (situação), `garantias` (duração `12 meses`). Tudo por `business_id`, deferido. |
| `app/VariationTemplate.php` | `produtosQueUsam()`: a mesma contagem pra coluna "Produtos" e pra recusa (PR 1). |
| `resources/js/Pages/Produto/Cadastros/Index.tsx` | 6 abas vivas na ordem do protótipo; copy de cada aba de `produto-cadastros.jsx`; Grupos e Garantias sem coluna Produtos (como no protótipo); confirmação de excluir grupo diz que os preços do grupo somem junto. |
| `…/Index.charter.md` · `…/Index.casos.md` | R1/R4 e permissões atualizados; UC-PCADAP-13..16, cada um com teste. |
| `tests/Feature/Produto/ProdutoCadastrosContratoTest.php` | +4 testes (tenant 98 × 99), lane `estoque-pest`. |
| `.github/workflows/estoque-pest.yml` | +`VariationTemplateController`, `WarrantyController`, `app/VariationTemplate.php` nos dois filtros. |

## Decisões tomadas aqui (com o porquê)
- **Variação em uso não sai** (antes o servidor apagava, só a tela escondia o botão). A contagem é só de produtos do próprio negócio. O teste prova com um produto do vizinho apontando pro meu modelo: o resultado é 1, não 2.
- **Garantia sem Excluir:** `WarrantyController@destroy` nunca foi implementado (corpo vazio). A tela não oferece um botão que não faz nada. `can.garantias.delete` sai `false` mesmo com `warranty.delete`.
- **`/units` continua abrindo em Unidades**, não em Variações (a aba default do protótipo), para não mudar a porta de quem já usa. O contrato segue ancorado na aba Unidades: `contrato-de-tela` saiu ✅ limpo.

## Pendências
1. **Contagem da variação não é link.** O índice `/products/unificado` não filtra por modelo de variação, e ele é `nao_toca`. O número aparece sem link.
2. **Ativar/desativar grupo na linha (UC-CAD-09):** a linha mostra Ativo/Inativo; a troca segue na tela clássica. O toggle legado é um `GET` que muda estado, e trazê-lo pra tela pede rota nova.
3. **Criar/editar** nas 3 abas segue nos modais da Blade (`?classico=1`). É a thread 10.
4. **Excluir grupo de preço apaga os preços do grupo.** É o comportamento que já existia; a confirmação diz isso com a copy do protótipo. Não mexi no cálculo.
5. **Visual:** a tela não tem baseline de visual-regression própria, e não regravei nenhuma (ADR 0409). A aprovação F1.5 das abas novas e a entrada delas no contrato visual são do [W].
6. Thread 08 (menu) e 10 (drawer) estão destravadas por esta.

## Prova de execução
A lane `estoque-pest` roda `tests/Feature/Produto/**` com MySQL real. O run, a contagem de testes e assertions e os 5 testes novos (UC-PCADAP-13..16 e 15) vão no PR quando a lane fechar.
