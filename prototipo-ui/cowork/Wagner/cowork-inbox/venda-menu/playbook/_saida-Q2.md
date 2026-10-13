---
sessao: "Q2"
titulo: "Quotations no cutover (chave mwart.vendas_cotacoes) — saída"
executor: "[CL]"
data: 2026-10-12
base: wagnerra23/oimpresso.com@main 711d7b00 (lido 2026-10-12)
veredito: "entregue no repo — a prova do índice (`'vendas_cotacoes'` em config/mwart.php) passa; o GET comum em produção não foi medido nesta sessão."
---
# _saida Q2 · Cotações na chave de cutover

## 1 · O que encontrei antes de codar

A ficha (`03-orcamentos.md`) pedia a chave `mwart.vendas_cotacoes` no helper do C0, nascendo
desligada e ligando junto do C1. Esse plano ficou para trás: desde o **Cutover pelo menu, thread 02
(D1 [W] 2026-10-07)** o `SellController@getQuotations` já entregava a Page React no GET comum para
**todas** as empresas, com uma condição própria (`! ajax() || X-Inertia`) e `?classico=1` para o Blade.

Criar a chave nascendo `false` desligaria a tela que já estava no ar. Por isso a chave **nasce ligada
para todas** (`env('MWART_VENDAS_COTACOES', true)`, lista vazia). O comportamento padrão não muda; o
ganho é o interruptor por empresa no `.env`, sem deploy, e a regra num lugar só (`App\Support\Mwart`).

## 2 · Feito

- `config/mwart.php`: chave `vendas_cotacoes` com `MWART_VENDAS_COTACOES` (default `true`) e
  `MWART_VENDAS_COTACOES_BIZ`. O comentário explica por que ela foge do "nascem desligadas" do bloco.
- `SellController@getQuotations`: a condição passou para
  `! request()->boolean('classico') && Mwart::telaReact('vendas_cotacoes', $business_id)`.
  Equivalência com a condição antiga, ramo a ramo, com a chave ligada:
  `X-Inertia` → React (igual) · AJAX sem `X-Inertia` → JSON do DataTable (igual) ·
  GET comum → React (igual) · `?classico=1` → Blade (igual, checado antes).
- `Sells/Quotations.charter.md`: a linha "GET sem X-Inertia → Blade" e o `status_detail`
  ("GET comum ainda entrega o Blade") eram falsos desde 07/10; reescritos com o fato datado.
  O `status: draft` não foi mexido — sair de draft é decisão [W].

## 3 · Prova

- `tests/Feature/Sells/VendasMwartCutoverTest.php`, 4 casos novos `Q2`:
  sem env a chave vem `['enabled' => true, 'business_ids' => []]` e o GET comum abre
  `Sells/Quotations` (com precondição de que o ambiente não fixa a env) · chave desligada → Blade ·
  ligada só para outra empresa → Blade · `X-Inertia` com a chave desligada → React.
- Os casos de `?classico=1` e AJAX seguem em `tests/Feature/CutoverMenu/VendasListasSemXInertiaTest.php`,
  que não foi alterado e precisa continuar verde.
- Os dois arquivos estão na allowlist da lane `sells-pest.yml` (MySQL), e `SellController.php` +
  `config/mwart.php` estão no gatilho dela. Pest não rodado nesta sessão (regra do repo: CT 100 ou CI,
  e esta sessão não alcança o CT 100). O veredito é o CI do PR.
- `php -l` limpo nos 3 PHP; frontmatter do charter validado por `scripts/memory-schemas/validate.mjs`.

## 4 · Não feito e por quê

- **GET comum em produção (biz=1) devolvendo `Sells/Quotations`** — a prova da tabela da ficha. Esta
  sessão não tem rota para `oimpresso.com` (o proxy recusa) nem SSH. Quem tiver: depois do deploy,
  conferir que o `.env` de produção não fixa `MWART_VENDAS_COTACOES=false` e abrir `/sells/quotations`
  pelo menu.
- A ficha dizia "liga junto do C1". Não se aplica mais: a tela já está ligada para todas desde 07/10.
