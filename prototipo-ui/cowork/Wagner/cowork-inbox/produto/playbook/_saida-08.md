---
sessao: "08"
titulo: "Menu Produtos — saída da thread"
autor: "[CL]"
criado: 2026-10-06
base: 1ab4ab51b1
thread: 08-menu.md
veredito: "entregue no código — 5 itens novos atrás da permissão da tela; os 6 de apoio viram deep-link pra aba de Produto/Cadastros; nenhum item aponta pra rota Blade aposentada. Smoke de UI em produção só depois do merge."
---

# _saída 08 · Menu Produtos

## O arquivo do menu (o "não medido" do pedido)
**Fixado:** `app/Http/Middleware/AdminSidebarMenu.php`, bloco `//Products dropdown` (`$menu->dropdown(__('sale.products'), …)->order(20)`).
O índice tinha marcado como suposição; conferido no `main` `1ab4ab51b1`. O menu de Produto não vem de DataController de módulo.

## O que mudou (1 arquivo)

| item | antes | depois | guarda (= 1ª checagem do método que serve a tela) |
|---|---|---|---|
| Imprimir etiquetas | `/labels/show`, guarda `product.view` | mesma rota, rótulo do protótipo | `print_labels.access` (`LabelsController::show`) |
| Atualizar preço | `/update-product-price`, guarda `product.create` | mesma rota | `product.update` (`SellingPriceGroupController::updateProductPrice`) |
| Importar produtos | `/import-products` | mesma rota | `product.create` (sem mudança) |
| Importar estoque inicial | `/import-opening-stock` | mesma rota | `product.opening_stock` (sem mudança) |
| Cadastros de apoio | não existia | `/units` (abre na 1ª aba visível) | qualquer aba visível |
| Variações | `/variation-templates`, guarda `product.create` | `/units?aba=variacoes` | `variation.view` ou `.create` |
| Grupos de preço | `/selling-price-group` | `/units?aba=grupos` | `product.create` |
| Unidades | `/units` | `/units?aba=unidades` | `unit.view` ou `.create` |
| Categorias | `/taxonomies?type=product` (Blade) | `/units?aba=categorias` | `category.view` ou `.create` |
| Marcas | `/brands` (Blade) | `/units?aba=marcas` | `brand.view` ou `.create` |
| Garantias | `/warranties`, **sem guarda** | `/units?aba=garantias` | `warranty.view` ou `.create` |

As permissões das 6 abas são as mesmas de `UnitController::propsCadastros()`, que decide quais abas a tela mostra e que `?aba=` aceita. O dropdown aparece se o usuário tem qualquer permissão de um dos itens.

**Defeitos que o menu tinha:** Etiquetas e Atualizar preço apareciam pra quem tomava 403 ao clicar (guarda do menu ≠ guarda da tela). Garantias aparecia pra todo mundo. Variações escondia de quem tinha `variation.*` sem `product.create`.

Ordem e rótulos seguem `prototipo-ui/cowork/Wagner/data.jsx` (ghosts de Produtos) e as abas de `Pages/Produto/Cadastros/Index.tsx:114`. Os 3 itens do topo (Lista, Consulta, Adicionar) não mudaram.

## Rotas Blade que o menu deixou de apontar
`/brands` e `/taxonomies?type=product`: as duas ainda servem a Blade (os modais de criar/editar das abas Categorias e Marcas moram lá até a thread 10 PR-b). Saem do menu, não do sistema.
`/variation-templates`, `/selling-price-group`, `/warranties` já abriam a aba certa; o menu passa a ir direto em `/units?aba=`.

## Prova
- `php -l` limpo.
- **Não feito aqui:** teste Pest (o prefixo da thread é só o middleware; Pest roda no CI/CT 100) e smoke de UI. O smoke em produção (menu aberto, screenshot, clique em cada deep-link caindo na aba certa) é o próximo passo depois do merge.

## Fora do escopo
- O menu de Produto continua dropdown. Virar link único + abas no cabeçalho (ADR 0180) é migração própria.
- `prod-estoque` e `prod-analises` não entram (índice §4, D5).
