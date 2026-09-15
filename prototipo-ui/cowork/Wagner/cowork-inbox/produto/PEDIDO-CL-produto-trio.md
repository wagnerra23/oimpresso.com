# Pedido [CL] — Módulo Produto: trio + contratos de tela

> **Origem:** F1 [CC] no Cowork. Telas construídas em `prototipo-ui/cowork/` como rotas do shell único
> (`oimpresso.com.html`), traduzidas dos blades `resources/views/product/*`.
> **Aviso de fidelidade:** os blades foram lidos do **espelho local anexado** ao Cowork, não do `main`
> neste turno. Antes do PR, reconferir contra `main` (`resources/views/product/*` + `ProductController`).

## Arquivos do protótipo (F1)
| Arquivo | Papel |
| --- | --- |
| `produto-blade.jsx` | Dados de domínio (`window.PBD`), peças (`window.PBUI`), tela de lista, relatório de estoque, drawer de detalhe, modais (estoque inicial, localização, cadastro rápido, confirmação, etiquetas), export CSV |
| `produto-blade-forms.jsx` | Cadastro/edição (único · variável · composição), histórico de estoque, preços por grupo, edição em massa |
| `produto-analises.jsx` | Reposição, margem, curva ABC, sem giro, duplicatas (não existe no blade — proposta nova) |
| `produto-blade.css` | Escopo `.pb-root`, só tokens do DS vivo |

Rotas no shell: `prod-lista`, `prod-novo`, `prod-estoque`, `prod-historico`, `prod-precos`, `prod-massa`, `prod-analises` (ghosts de `produtos` em `data.jsx`/`app.jsx`).

## Origem por tela (paridade a conferir no `main`)
| Tela F1 | Blade de origem |
| --- | --- |
| Lista + filtros + abas | `product/index.blade.php` |
| Colunas, ações em massa | `product/partials/product_list.blade.php` |
| Cadastro/edição | `product/create.blade.php`, `edit.blade.php` + `partials/single|variable|combo_product_form_part` |
| Drawer de detalhe | `product/view-modal.blade.php` + `partials/*_product_details` |
| Estoque inicial (modal) | `partials/quick_product_opening_stock.blade.php` |
| Localização (modal) | `partials/edit_product_location_modal.blade.php` |
| Cadastro rápido (modal) | `partials/quick_add_product.blade.php` |
| Relatório de estoque | `report/partials/stock_report_table.blade.php` |
| Histórico de estoque | `product/stock_history.blade.php` + `stock_history_details.blade.php` |
| Preços por grupo | `product/add-selling-prices.blade.php` |
| Edição em massa | `product/bulk-edit.blade.php` + `partials/bulk_edit_product_row` |
| Prateleira | `product/show.blade.php` |

## Decisões que preciso de [W]
1. **Catálogo × lista do blade.** Hoje convivem: rota `produtos` (catálogo picker, visual de balcão) e `prod-lista` (índice fiel ao blade). Fundir numa só ou manter as duas com papéis distintos?
2. **Curva ABC / reposição** (`produto-analises.jsx`) não existe no UltimatePOS. Entra como tela do módulo Produto, vai pro BI, ou morre?
3. **Sugestão de compra** deve gerar rascunho no módulo Compras (integração real) ou só exportar lista?
4. **Etiquetas.** Layout de etiqueta é por papel (Pimaco?) — precisa do modelo real antes do F3.
5. **Campos personalizados 1–7:** confirmar os rótulos reais do `business.custom_labels` do cliente piloto.

## Pedidos ao DS
- `DataTablePro` com **menu de colunas** embutido (hoje o menu é local do módulo).
- Primitivo de **etiqueta / código de barras** (grupo Print-craft) — hoje as barras são desenho CSS do módulo. **Único pedido em aberto.**
- ~~`Alert` inline pra sumário de validação~~ → resolvido: o módulo passou a usar `Alert` do DS.

## Trio e contratos (prontidão de aplicação)
| Artefato | Caminho |
| --- | --- |
| Charter | `prototipo-ui/cowork/Produto.charter.md` |
| Casos de uso (39 UCs) | `prototipo-ui/cowork/Produto.casos.md` |
| Contrato — índice | `prototipo-ui/contrato/produto-lista.contract.json` |
| Contrato — cadastro | `prototipo-ui/contrato/produto-form.contract.json` |
| Contrato — análises | `prototipo-ui/contrato/produto-analises.contract.json` |

Âncoras `data-contract` já existem no protótipo (`produto-kpis`, `produto-filtros`, `produto-abas`, `produto-toolbar`, `produto-chips`, `produto-tabela`, `produto-rodape-selecao`, `produto-paginacao`).

## Integrações declaradas (Onda A)
Saem do módulo Produto via `window.PBIr(rota, ctx)`, com `window.__PBCtx` carregando `{origem, produto, nome, acao}`:

| Ação no Produto | Rota destino | `acao` no contexto |
| --- | --- | --- |
| Comprar este produto | `compras` | `novo-item` |
| Transferir entre locais | `compras` (Estoque) | `transferencia` |
| Usar em uma OS | `os` | `novo-item` |
| Gerar OP (composição) | `cv` | `nova-op` |
| Abrir no módulo Fiscal | `fiscal` | `produto` |
| Reposição em lote | `compras` | `rascunho-reposicao` + `itens[]` |

**[CL]:** o formato de `ctx` é proposta do F1 — precisa casar com o que cada módulo destino espera receber. A tela também **lê** `__PBCtx` na entrada e mostra faixa "você veio de X" com botão de volta.

## Fiscal (novo nesta onda)
Campos trazidos do Model Product que faltavam: `ncm`, `cest`, `cfop`, `origem` (tabela de origem 0/1/2/3/6 e CFOPs 5101/5102/5405/5933). Aba **Fiscal** no drawer + widget **Fiscal** no formulário, com validação de NCM de 8 dígitos quando o produto tem estoque.

## Casos de uso a cobrir no F3 (resumo)
- UC-01 filtrar por tipo/categoria/unidade/imposto/marca/local/situação e ver chips do que está ativo.
- UC-02 ordenar por nome, preços, estoque, tipo, categoria, marca, SKU; paginar 10/25/50.
- UC-03 escolher colunas visíveis (persistente por usuário) e densidade compacto/confortável.
- UC-04 selecionar com Shift+clique e aplicar ação em massa (excluir, edição em massa, local, desativar, etiquetas).
- UC-05 exportar catálogo completo e exportar só o filtro atual.
- UC-06 cadastrar produto único com cálculo compra → % lucro → venda → imposto.
- UC-07 cadastrar produto variável com sub-SKU gerado ao vivo; Enter abre a linha seguinte.
- UC-08 cadastrar composição buscando itens; total líquido e venda por margem.
- UC-09 validação inline: nome obrigatório, SKU único, preço > 0, margem negativa avisada.
- UC-10 rascunho recuperado depois de recarregar; aviso ao sair com alteração pendente.
- UC-11 estoque inicial por local com validade e lote.
- UC-12 preços por grupo fixo/percentual por variação.
- UC-13 edição em massa de categoria/subcategoria/marca/imposto/locais + preços por variação.
- UC-14 histórico de estoque por variação e local, com entradas/saídas/totais.
- UC-15 exclusão pede confirmação e é recusada com movimento de estoque.
- UC-16 etiquetas com preço, N cópias, impressão em papel.
- UC-17 análises: reposição por dias de cobertura, margem abaixo de 25%, curva ABC, sem giro, duplicatas.
