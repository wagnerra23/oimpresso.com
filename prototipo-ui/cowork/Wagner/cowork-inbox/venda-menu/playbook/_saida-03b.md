---
sessao: "03b"
titulo: "Devolução → SellReturn/Add (registro) — saída da thread, PR 2 de 2"
autor: "[CL]"
criado: 2026-10-05
thread: 01-telas-legadas.md §03
veredito: "PR 2 de 2 entregue — registro /sell-return/add/{venda} em React pela visita Inertia, gravando pelo MESMO store(); Blade preservada. Merge é [W] (REGRA MESTRE: tabela antes→depois no corpo do PR)."
---

# _saída 03b · Devolução — PR 2 de 2 (o registro)

## O que saiu

| arquivo | o quê |
|---|---|
| `app/Http/Controllers/SellReturnController.php` | ramo `X-Inertia` no `add()` + `inertiaAdd` (404 se a venda não é do business) + `inertiaAddVenda` (prop deferida). `store()` e `addSellReturn()` **não mudaram** |
| `resources/js/Pages/SellReturn/Add.tsx` | registro da devolução: venda de origem · itens a devolver · desconto e total · salvar |
| `resources/js/Pages/SellReturn/_components/devolucaoCalculo.ts` | espelho de `Util::num_uf` + `ProductUtil::calculateInvoiceTotal`, só para mostrar o total antes de salvar |
| `resources/js/Pages/SellReturn/Add.charter.md` · `Add.casos.md` | trio do registro; UC-SRADD-01..06 derivados de `Devolucao.casos.md` (UC-DEV-01, 03, 04) |
| `memory/requisitos/Sells/RUNBOOK-sell-return-add.md` | RUNBOOK MWART, apontado pelo `runbook:` do charter |
| `tests/Feature/Sells/SellReturnAddContratoTest.php` | contrato Pest, tenant 98 × 99, dupla prova de valor/estoque |
| `.github/workflows/sells-pest.yml` | o teste entra na allowlist da lane |
| `resources/js/Pages/SellReturn/Index.tsx` · `Index.charter.md` · `Index.casos.md` | "Editar" da lista vira `<Link>` (visita Inertia → `SellReturn/Add`); backlog do registro aponta para `Add.casos.md` |

Prova da thread: `SellReturnController.php` contém `Inertia::render('SellReturn/Add'`.

## Decisões e por quê

1. **A Page envia texto, não número.** O servidor entrega à Page cada campo já como o form
   Blade o põe na tela (as mesmas expressões de `@num_format`, `@format_quantity`,
   `@format_datetime` e `num_f(..., true)`), e a Page devolve esses textos ao `store()` sem
   reformatar. A quantidade é o texto digitado, como na Blade. Assim o `store()` recebe a mesma
   coisa pelos dois caminhos, e o risco do `num_uf` (incidente 2026-06-05: float cru lido como
   milhar) não aparece — a Page nunca serializa `1234.5`.
2. **O total mostrado copia o servidor, não o JS da Blade.** O JS da Blade trata desconto
   "Nenhum" como zero; o servidor recebe `discount_type` vazio como `null` e grava como `fixed`
   com o valor digitado. A Page mostra o número que vai ser gravado.
3. **404 na carga inicial.** `inertiaAdd` confere com `exists()` que a venda é do business e é
   `type=sell` antes de responder; a venda com as linhas vem deferida (grupo `venda`).
4. **Teto da linha na tela** (R1): digitar acima da vendida trava no valor vendido; o servidor
   continua recusando o excesso (`SellReturnExceedsSold`).

## Divergências e o que ficou fora

- **R3 motivo** e **R4 aviso de sob medida** do texto revisado: o `store()` não grava motivo e o
  produto não diz se é sob medida. Implementar R3 exige mudar o `store()` → fora, por regra.
- **UC-DEV-02** (salvar desabilitado sem quantidade) está na tela mas sem teste: é comportamento
  só do front; fica `[BACKLOG]` até haver teste de navegador da tela.
- Impressão do comprovante depois de salvar (a Blade chama `pos_print`) não foi portada: a tela
  volta para a lista de devoluções.
- Os links de devolução em `Sells/Index` e `Sells/Pos/Index` são `<a>` (carga completa) e seguem
  abrindo a Blade — cutover F5 é humano.
- **Achado, não consertado:** no ramo Blade, venda de outro business dá 500 (o `find()` volta
  `null` e a view acessa `->sell_lines`), não 404. Não vaza dado; o ramo Inertia responde 404.

## Verificação local

- `tsc --noEmit`: 0 erro em `SellReturn/`; `typecheck-baseline` sem regressão.
- `eslint` limpo em `SellReturn/` · `casos-coverage-guard` sem violação nova ·
  `screen-coverage-map --check` sem perda · `memory-schemas/validate` OK no charter e no RUNBOOK.
- Pest **não** roda local (proibicoes.md); o veredito vem da lane `PHP / Pest (Sells · MySQL)`.

## PARAR SE

Nenhum disparou: sem rota nova (ramo no `add()` existente), sem endpoint novo, `store()` e
cálculo intactos. A tela toca valor e estoque, e por isso o PR traz o teste que prova o mesmo
total e o mesmo estoque pelos dois caminhos + a conta à mão.
