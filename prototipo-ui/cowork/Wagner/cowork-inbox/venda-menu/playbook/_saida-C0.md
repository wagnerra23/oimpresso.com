---
sessao: "C0"
titulo: "Cutover: chaves mwart.vendas_* + helper + teste por tela — recibo RETROATIVO"
executor: "[CL]"
data: 2026-10-06
retroativo: true
pr: "#8630"
merge: 2d23c5a1ad9e (2026-10-05T04:03:42Z)
veredito: "entregue — as 3 provas do json verdes, e o teste EXECUTOU na lane Sells (33 passed · 152 assertions · 0 skipped)."
---
# _saida C0 · recibo retroativo

**Por que retroativo.** O C0 foi entregue pelo [#8630](https://github.com/wagnerra23/oimpresso.com/pull/8630)
(`feat(sells): flags de cutover MWART por empresa nas telas React de Vendas`), mergeado em
2026-10-05 04:03Z, seis minutos depois da ficha `02-cutover-e-fechamento.md` (#8629). Ninguém
escreveu o `_saida-C0.md` — e não houve recibo apagado a restaurar: `git log --all` sobre
`venda-menu/playbook/_saida-C0.md` volta vazio (clone não-raso, `--is-shallow-repository` = `false`).
O placar mostrava a thread como `sem recibo` (provas verdes, sem `_saida`).

## As provas, conferidas contra a data

| prova do json | estado | nasceu em |
|---|---|---|
| `tests/Feature/Sells/VendasMwartCutoverTest.php` existe | ✅ | #8630 (`--diff-filter=A`) |
| `app/Support/Mwart.php` existe | ✅ | #8630 (`--diff-filter=A`) |
| `config/mwart.php` contém `'vendas_discount_index'` | ✅ | #8630 (`git log -S`) |

Nenhuma das três já existia antes da ficha: não é prova casada por padrão antigo.

## O teste rodou (não só "existe")

A prova da ficha é *"`VendasMwartCutoverTest` verde na lane Sells"*. Conferido no run de `push`
em `main` [37486886677](https://github.com/wagnerra23/oimpresso.com/actions/runs/37486886677)
(`sells-pest.yml`, 2026-10-06 15:22Z, `success`): o arquivo está na allowlist da lane
(`.github/workflows/sells-pest.yml:323`) e o JUnit da run registra
`tests/Feature/Sells/VendasMwartCutoverTest.php — tests 33 · passed 33 · failed 0 · skipped 0 ·
assertions 152`. Cobre, por tela, (a) flag desligada → Blade, (b) ligada para a empresa → Page
React (e lista vazia vale para todas), (c) ligada só para outra empresa → segue no Blade,
(d) `X-Inertia` com a flag desligada → React; mais AJAX sem `X-Inertia` → JSON do DataTable e a
prévia da importação.

## O que o C0 entregou (do corpo do #8630)

- 6 chaves em `config/mwart.php`, uma por tela, no padrão `env(...,false)` + `_BIZ`:
  `vendas_pos_index` · `vendas_shipments_index` · `vendas_sell_return_index` ·
  `vendas_discount_index` · `vendas_import_sales` · `vendas_sales_order_index`.
- `App\Support\Mwart` com a regra num lugar só (`ativo()` / `telaReact()`); os 6 controllers só
  trocaram a condição do `if`.
- **Nasce desligado.** Sem as envs, todo `config('mwart.vendas_*.enabled')` é `false`.

## O que NÃO está feito (não confundir com o C0)

- **C1** (ligar biz=1: Descontos, Importação, Pedido de venda) é ato no `.env` de produção, fora
  do repo; fecha pelo recibo dela com GET comum devolvendo a Page. Não executado aqui.
- **C3–C5** são do [W] (observação de 7 dias e aviso à ROTA LIVRE). Não executados.
