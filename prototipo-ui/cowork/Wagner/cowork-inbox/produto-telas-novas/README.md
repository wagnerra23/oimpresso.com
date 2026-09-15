# Produto — 10 telas que faltavam (F1 [CC], 2026-08-21)

Leva que fecha o menu **Produtos** do UltimatePOS no app único (`oimpresso.com.html`). Nenhum `.html` novo.

## Build (destino `prototipo-ui/cowork/`)
- `produto-acoes.jsx` — Imprimir etiquetas · Importar produtos · Importar estoque inicial · Atualizar preço (`window.ProdutoAcoes`)
- `produto-cadastros.jsx` — Variações · Grupos de preço · Unidades · Categorias · Marcas · Garantias em 6 abas (`window.ProdutoCadastros`)
- Patches: `produto-blade.jsx` (roteador interno + ⌘K do módulo + rota do shell acompanhando a tela), `produto-blade.css` (densidade compacta, contagem clicável, lista de instruções), `app.jsx` (rotas `prod-*` + Tweaks do módulo), `data.jsx` (5 itens novos na sidebar).

## Trio de prontidão nesta pasta
| Arquivo | Cobre |
| --- | --- |
| `Etiquetas.charter.md` + `Etiquetas.casos.md` | rota `prod-etiquetas` |
| `Importacao.charter.md` + `Importacao.casos.md` | rotas `prod-importar`, `prod-importar-estoque`, `prod-atualizar-preco` |
| `Cadastros.charter.md` + `Cadastros.casos.md` | rota `prod-cadastros` (6 abas) |

Contratos: `prototipo-ui/contrato/produto-{etiquetas,importacao,atualizar-preco,cadastros}.contract.json`.

## Decisões pendentes de [W] (bloqueiam o F3, não o F1)
1. **Permissões por aba** dos cadastros (`unit.create`, `category.create`, `brand.create`, `warranty.create`): o blade esconde o botão Novo; a tela ainda não tem papel.
2. **Taxonomy genérica**: o legado usa `module_category_data` (categoria serve despesa, Oficina, produto). Fixei em `type=product` — uma tela por domínio ou uma tela parametrizada?
3. **Modelos de etiqueta reais**: usei as 3 medidas usuais de mercado. Quais etiquetas a gráfica compra?
4. **Campos personalizados na etiqueta** (`custom_labels.product` do blade): quais existem no cliente piloto?
5. **Relatório pós-importação** (criados/atualizados/recusados) não existe no legado — proposta nova, precisa de sim/não.

## Leitura
Espelho **local** anexado do repo (`resources/views/**`), NÃO o `main` neste turno. Antes de aplicar, [CL] revalida no git — se divergir, o git manda. Não commitado: as tools de GitHub aqui são read-only.
