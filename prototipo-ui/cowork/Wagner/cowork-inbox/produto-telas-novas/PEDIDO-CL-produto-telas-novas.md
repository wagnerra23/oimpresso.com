# Pedido pro [CL] — Produto: 10 telas + permissões (F1 → F3)

> **F1 [CC] pronto** no Cowork (`prototipo-ui/cowork/produto/`). Este é o plano de PRs pro F3 em Inertia/React real.
> **Lido no `main` NESTE turno** (tree `4235f8df6838`): `app/Http/Controllers/{Unit,Brand,Taxonomy,Barcode,Warranty,VariationTemplate,SellingPriceGroup,ImportProducts,ImportOpeningStock}Controller.php`, `routes/web.php` (332, 336, 440-443, 613-615, 711-712, 729-730, 748-753, 838). Views lidas no espelho local.
> **8 PRs** — 1 de decisão (PR-0, sem código) + 7 de código. Ordem é dependência real, não preferência.

---

## Achado que manda na ordem — A-P1: 6 rotas sem permissão

Permissões que **existem** no legado (usei estes nomes literais no F1, sem traduzir):

| Controller | Permissões |
| --- | --- |
| `UnitController` | `unit.view` · `unit.create` · `unit.update` · `unit.delete` |
| `BrandController` | `brand.view` · `brand.create` · `brand.update` · `brand.delete` |
| `TaxonomyController` | `category.view` · `category.create` · `category.update` · `category.delete` — **só quando `category_type == 'product'`** |
| `BarcodeController` | `barcode_settings.access` (configuração da etiqueta, não a impressão) |

Permissões que **não existem** — nenhum `can()` no controller e rota só sob autenticação:

| Controller | Rota | Efeito hoje |
| --- | --- | --- |
| `VariationTemplateController` | `variation-templates` (resource) | qualquer usuário logado cria/edita/exclui modelo de variação |
| `SellingPriceGroupController` | `selling-price-group` + `activate-deactivate/{id}` + `export`/`import` | idem — inclui **atualizar preço em lote por planilha** |
| `WarrantyController` | `warranties` (resource) | idem |
| `LabelsController` | `/labels/show`, `/add-product-row`, `/preview` | qualquer usuário imprime etiqueta com preço |
| `ImportProductsController` | `/import-products` + `/store` | **qualquer usuário sobrescreve o catálogo em lote** |
| `ImportOpeningStockController` | `/import-opening-stock` + `/store` | idem, no estoque |

O F1 **não inventou permissão**: onde o legado é aberto, a tela mostra um aviso nomeando o controller (achado A-P1) em vez de esconder. Fechar isso é o PR-0 + PR-1.

---

## PR-0 — decisão de [W] (sem código)
1. Criar as 6 permissões novas? Sugestão de nomes seguindo a convenção existente: `variation.view/create/update/delete`, `selling_price_group.view/create/update/delete`, `warranty.view/create/update/delete`, `print_labels.access`, `import_products.access`, `import_opening_stock.access`.
2. Quem recebe cada uma no papel **Balcão** (Larissa) e no **Gerente de catálogo**? Minha proposta: Balcão = só `*.view` + `print_labels.access`; Gerente = tudo menos `*.delete` e menos as duas de importação; importação e preço em lote = **administrador**.
3. Categoria é genérica (`module_category_data`): uma Page por domínio (produto, despesa, Oficina) ou uma Page parametrizada?

Sem 1 e 2 o PR-1 não fecha. O resto dos PRs não depende do 3.

## PR-1 — Backend: permissões + gates (sem UI)
- Seeder/migration das permissões decididas no PR-0 (idempotente, respeitando o catálogo real de 53 grupos).
- `can()` nos 6 controllers, no mesmo formato dos outros (`abort(403, 'Unauthorized action.')`).
- Gate Tier 0 cross-tenant nas rotas de import/export (mesmo padrão do ADR 0093).
- **Testes:** um por controller — usuário sem permissão recebe 403 em index/store/destroy; com permissão, 200. Import: arquivo válido de outro `business_id` é recusado.
- **Aceite:** `php artisan test --filter=Product` verde; nenhuma rota do módulo Produto acessível sem permissão.

## PR-2 — Page `Product/Cadastros` (6 abas)
- Inertia Page com as 6 abas (Variações, Grupos de preço, Unidades, Categorias, Marcas, Garantias), tabela + modal criar/editar + confirmação de exclusão.
- Props de permissão por aba (`can: { unit: {view,create,update,delete}, … }`): botão Novo escondido/desabilitado, Excluir ausente, aba sem `view` → `EmptyState variant="no-perm"`.
- Recusa de exclusão de registro em uso: contagem vem do backend (`withCount`), não do front.
- Contagem clicável → `/products?unit=…` (índice filtrado).
- **Fonte F1:** `produto-cadastros.jsx` · **Contrato:** `produto-cadastros.contract.json` · **Trio:** `Cadastros.charter.md`/`.casos.md` (16 UC).

## PR-3 — Page `Product/Etiquetas`
- `LabelsController` → Inertia (mantendo `/labels/preview` pro render final).
- Linha por produto com nº de etiquetas, lote, validade, data de embalagem e grupo de preço; campos da etiqueta com liga/desliga + corpo; modelo de folha (medida em mm + por folha); prévia paginada por folha.
- Aposentar o modal de etiquetas do índice: a lista manda pra esta Page com a seleção.
- **Fonte F1:** `produto-acoes.jsx` (Etiquetas) · **Contrato:** `produto-etiquetas.contract.json` · **Trio:** `Etiquetas.charter.md` (10 UC).

## PR-4 — Importações (produtos + estoque inicial)
- Duas Pages sobre o mesmo componente de importação: enviar + baixar modelo + **conferência** + instruções.
- Conferência client-side de CSV (colunas + obrigatórios, com nº da linha) e bloqueio do envio com erro; .xls/.xlsx segue pro servidor com o aviso.
- Backend: validar antes de gravar e devolver o relato por linha (hoje é tudo-ou-nada silencioso).
- **Contrato:** `produto-importacao.contract.json` · **Trio:** `Importacao.charter.md` (UC-IMP-01..07).

## PR-5 — Atualizar preço por planilha
- Page com os 2 passos (exportar → devolver) + conferência do arquivo + as 4 regras.
- Backend: `export` com uma coluna por grupo **ativo**; `import` recusando linha com SKU/variação alterados.
- **Contrato:** `produto-atualizar-preco.contract.json` · **Trio:** UC-PRC-01..04.

## PR-6 — Menu, rotas e teclado
- 5 itens novos no menu Produtos (Imprimir etiquetas · Atualizar preço · Importar produtos · Importar estoque inicial · Cadastros de apoio) **filtrados por permissão**.
- Remover os 6 itens antigos que viraram abas (Variations, Grupo de preços de venda, Unidades, Categorias, Marcas, Garantias) — ou mantê-los como deep-link pra aba (decisão barata, prefiro deep-link).
- ⌘K com as telas novas; `/` na busca de cada aba.

## PR-7 — Contratos no CI + trio no repo
- Copiar os 4 `*.contract.json` pra `prototipo-ui/contrato/` do `main` e ligar no teste de contrato (ADR 0286).
- Charters + casos pra `resources/js/Pages/Product/` (`Cadastros.charter.md`, `Etiquetas.charter.md`, `Importacao.charter.md` + `.casos.md`), pra o `prototipo-readiness.mjs` reconhecer o trio.
- **Aceite:** `npm run qa:prototipo-readiness` marca as 5 telas como ✅ (trio + scorecard).

---

## Resumo de esforço (minha estimativa)
| PR | Risco | Tamanho |
| --- | --- | --- |
| PR-1 permissões | alto (segurança) | M |
| PR-2 cadastros | médio | G |
| PR-3 etiquetas | médio (impressão) | M |
| PR-4 importações | alto (grava em lote) | G |
| PR-5 preço | médio | M |
| PR-6 menu/teclado | baixo | P |
| PR-7 contratos/trio | baixo | P |

**Caminho mínimo pra valor:** PR-0 → PR-1 → PR-2. Etiquetas e importações podem ir depois sem bloquear ninguém.
