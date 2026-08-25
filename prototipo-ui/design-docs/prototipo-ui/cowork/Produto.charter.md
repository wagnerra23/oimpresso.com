# Produto — charter da tela (F1 [CC])

> **Alvo no vivo:** `resources/js/Pages/Product/*` · **Origem:** `resources/views/product/*` (Blade UltimatePOS v6)
> **Protótipo:** `prototipo-ui/cowork/produto-blade.jsx` + `produto-blade-forms.jsx` + `produto-analises.jsx` (+ `produto-blade.css`)
> **Rotas no shell:** `prod-lista` · `prod-novo` · `prod-estoque` · `prod-historico` · `prod-precos` · `prod-massa` · `prod-analises`
> **Aviso de fidelidade:** blades lidos do espelho local anexado ao Cowork, não do `main` no turno. Reconferir antes do PR.

## Para quem é
| Persona | O que faz aqui | O que não pode faltar |
| --- | --- | --- |
| **Larissa** (balcão, 1280px) | acha o produto pelo nome ou bipando o SKU, confere preço e saldo | busca `/`, densidade compacta, atalho `N`, teclado na grade de variações |
| **Wagner** (escritório, 1440px) | precifica, revisa margem, decide reposição | análises (margem, ABC, reposição), edição em massa, export |
| **Eliana** (financeiro) | confere imposto, NCM/CFOP antes da NF-e | aba Fiscal do drawer, validação NCM, preços por grupo |
| **Técnico Repair** (tablet) | consulta saldo e prateleira em pé | alvo ≥44px, drawer legível, sem hover obrigatório |
| **Iniciante** | entende o domínio pela tela | ajuda em cada campo, empty state que explica o porquê |

## O que a tela decide
1. **Este produto existe e como se chama** — nome, SKU, tipo (único/variável/composição), unidade.
2. **Quanto custa e por quanto sai** — compra sem imposto → % de lucro → venda → imposto (inclusivo/exclusivo), por variação e por grupo de preço.
3. **Se controla estoque** — `enable_stock`, quantidade de alerta, saldo por local, prateleira.
4. **Como sai na nota** — imposto, NCM, CEST, CFOP, origem.
5. **Se ainda vale vender** — ativo/inativo, não-para-venda, margem, giro.

## Fronteiras (o que NÃO é desta tela)
- Lançar movimento de estoque → módulo **Estoque** (aqui só o histórico, em leitura).
- Fechar pedido de compra → módulo **Compras** (aqui só a sugestão de reposição).
- Emitir NF-e → módulo **Fiscal** (aqui só os campos que a nota consome).
- Gerar OP → módulo **Comunicação Visual/Produção** (aqui só o atalho a partir da composição).

## Sistema visual
Cockpit V2: sidebar + page header + body em cards (`.pb-widget`) + drawer lateral pro detalhe (PT-02) + modal só pra confirmação (PT-04).
Primitivos do DS v6 em uso: `Pagination` · `FilterChip` · `EmptyState` · `Tooltip` · `Alert` · `Skeleton` · `DropdownMenu` · `DatePicker` · `PeriodBar` · `Progress` · `Chart` · `ProofFrame` · `ProofStrip`.
Nada de paleta inventada: só tokens do DS vivo. Primary roxo `oklch(0.55 0.15 295)`.

## Estados que a tela tem de saber viver
carregando (Skeleton) · vazio de catálogo · vazio por filtro (EmptyState no-results) · produto inativo · sem controle de estoque · abaixo do alerta · margem negativa · SKU duplicado · exclusão recusada por movimento · chegada de outro módulo (faixa de contexto) · período sem movimento.

## Dívida declarada
- Código de barras é desenho CSS — falta primitivo no DS e o modelo real de papel de etiqueta.
- Reposição/ABC não existem no UltimatePOS: proposta nova, aguarda [W].
- Convivem `produtos` (catálogo picker) e `prod-lista` (índice fiel ao blade) — [W] decide fundir.
