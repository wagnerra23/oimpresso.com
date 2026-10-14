# Handoff 2026-10-14 15:45 BRT — Painel do produto: cores pendentes e o que falta na lista

> Sessão [F+C] (Felipe). Tela: `/products` (lista de produtos React) e o painel de detalhe
> (`resources/js/Pages/Produto/_components/lista/PainelDetalhe.tsx`). Protótipo:
> `prototipo-ui/cowork/Felipe/produto-blade.jsx` (vista `lista`, `DetalheDrawer`). Inventário vivo da
> tela: `memory/requisitos/Produto/_telas/produto-index-visual-comparison.md` (ler antes de tudo).

## Entregue hoje (todos mergeados e conferidos em produção, empresa de teste biz 1)

| PR | O quê |
|---|---|
| [#9354](https://github.com/wagnerra23/oimpresso.com/pull/9354) | Aba "Relatório de estoque": lucro verde/vermelho, linha de alerta vermelha, lista vazia esmaecida (cor por `style`/variável — utilitário não vence o bundle sem `@layer`) |
| [#9357](https://github.com/wagnerra23/oimpresso.com/pull/9357) | Lápide §5 + rec LC-30 do erro de cor acima |
| [#9373](https://github.com/wagnerra23/oimpresso.com/pull/9373) | Painel: composição com "Total líquido", preço da composição, "Preços dos itens"; imagens das variações e dos itens (UC-PIDX-20) |
| [#9386](https://github.com/wagnerra23/oimpresso.com/pull/9386) | Painel: produto sem controle de estoque mostra a aba Estoque com o aviso (UC-PIDX-21) |
| [#9397](https://github.com/wagnerra23/oimpresso.com/pull/9397) | Painel: grupo A de medidas do protótipo (altura de linha 1,45, títulos 1,2, faixa das abas 39px, botão do histórico pequeno, espaços da aba Estoque) |

## FAZER NO PRÓXIMO CHAT — decidido por [F] em 2026-10-14 (itens 1, 2 e 3)

Medição de cores do painel, protótipo × produção (mesma sonda, tema escuro, 1593px). Iguais: fundo,
título, subtítulo, rótulos e valores, selo do tipo, tabelas, bordas, roxo do botão principal.
Diferentes:

1. **Imagem do produto sem foto (defeito).** O servidor manda `/img/default.png` (o acessor
   `image_url` do `App\Product` devolve a imagem genérica quando não há foto), e o painel mostra um
   quadro branco em vez da caixa escura "Sem imagem" do protótipo (`.pb-img`, fundo `oklch(0.23 …)`).
   Medido: produtos 1817 e 10740 (biz 1) têm `imagem = https://oimpresso.com/img/default.png`.
   Conserto: `buildProdutoDetalhe` manda `imagem = null` quando `$produto->image` está vazio (o painel
   já trata null). **Mesmo problema na coluna "Imagem" da lista** (o protótipo mostra o `.pb-thumb`
   "IMG"): consertar junto, no mesmo PR — a decisão de [F] cobre as duas telas.
2. **Botões do rodapé do painel — ajustar SÓ neste painel** (não mexer no `Button` do DS):
   | | Protótipo | Produção |
   |---|---|---|
   | Texto de "Editar produto" | `rgb(255,255,255)` | `oklch(0.205 0.009 240)` |
   | Fundo dos de contorno (Comprar, Preços por grupo, Imprimir) | `oklch(0.3 0.008 240)` sólido | translúcido `oklab(0.34 … / 0.3)` |
   | Texto de "Fechar" | `oklch(0.72 0.005 90)` | `oklch(0.94 0.005 90)` |
3. **Abas do painel — ajustar SÓ neste painel** (não mexer no `PageHeaderTabs`):
   | | Protótipo | Produção |
   |---|---|---|
   | Texto da aba ativa | `oklch(0.94 0.005 90)` | `oklch(0.965 0.004 240)` |
   | Texto das abas inativas | `oklch(0.72 0.005 90)` | `oklch(0.74 0.008 240)` |
   | Fundo do contador ("Variações 1") | `oklch(0.31 0.008 240)` | `oklch(0.23 0.006 240)` |
   Usar os tokens do DS que dão esses valores (ex.: `--text`, `--text-dim`), nunca cor crua.

**Como medir (Regra 0, LC-30):** cor se prova lendo `getComputedStyle` no navegador, não a classe.
Antes de pôr utilitário num elemento do painel, varrer `document.styleSheets` por regra fora de
`@layer` que pinte a mesma propriedade (foi o que matou o #9324). Simular a mudança no painel aberto
em produção (estilo inline, só no navegador), medir, e só então publicar; depois do deploy, medir de
novo. A sonda usada hoje acha cada papel pelo texto e lê o estilo calculado — a descrição e os
números estão no comparativo da tela (seção "Painel de detalhe — 2ª medição, 2026-10-14"); a forma
de abrir o protótipo local: servir `prototipo-ui/cowork/Felipe` com
`scripts/design/design-diff-lote.mjs` (`servirEspelho`), `localStorage['oimpresso.route']='prod-lista'`,
`localStorage['oimpresso.theme']='dark'`. ⚠️ Na sonda, achar texto por `textContent` (o `innerText`
devolve o texto já em maiúsculas pelo CSS) e reencontrar a raiz a cada troca de aba (no protótipo o
painel remonta). ⚠️ Não varrer produção com dezenas de requisições seguidas: em 2026-10-14, ~74
consultas ao painel em sequência deram 503 por ~4 minutos.

## Pendências que seguem paradas (não fazer sem pedido)

- **Faixa "Você veio de… / Voltar e usar"** — parada por [F]; falta dizer de qual tela a pessoa
  chega e o que o "Voltar e usar" leva de volta.
- **"Transferir" por local (painel) e "Transferir entre locais" (menu da linha)** — entram com a
  transferência como requisição, que espera #9141, #9142 e #9126 com o [W].
- **"Gerar OP"** — não feito: a Fabricação produz por receita; composição não é receita.
- **Extras da aba Fiscal** (origem da mercadoria, "Abrir no módulo Fiscal", texto de ajuda) — não
  feitos, motivo no `Index.casos.md` (UC-PIDX-19).
- **Lista: ícone do WooCommerce na linha** — a Blade mostrava; o servidor já manda `woocommerce_sync`
  e a tela não usa. Pequeno, não começado.
- **Lista: `Ctrl+K` com as telas do módulo** (protótipo `PaletaProduto`) — conflita com o `Ctrl+K`
  global; decisão de [F]/[W] sobre a tecla.
- **Prateleira sem cadastro** — o protótipo lista os locais com "—"; produção mostra o aviso, como a
  Blade. Trocar só se [F] preferir.
- **Padrão de botões e abas no sistema inteiro** (o grupo B fora deste painel) — decisão de [W].
- **Aba Composição não medida em produção** — a biz 1 não tem produto composto; para ver, criar um de
  teste na biz 1 (nunca biz 4).
- **`Index.casos.md` desatualizado** — as "Divergências abertas" D-1..D-4 e quatro itens do backlog
  descrevem a lista antiga de cartões e já não valem; marcar como resolvidos, com data, num PR só de
  documentação.
- Produto de teste 16673 "TESTE CADASTRO RÁPIDO" segue inativo, com 7 unidades, na biz 1.

## Estado MCP no momento do fechamento

O servidor MCP `oimpresso` não respondeu nesta sessão (HTTP 401, cabeçalho de autorização recusado),
então o checklist `cycles-active` / `my-work` / `sessions-recent` não pôde ser rodado. O estado acima
vem do git e dos PRs.
