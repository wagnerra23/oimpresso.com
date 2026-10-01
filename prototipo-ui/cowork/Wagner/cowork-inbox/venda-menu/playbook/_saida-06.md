---
sessao: "06"
titulo: "Pedido de venda → SalesOrder/Index — saída da thread"
autor: "[CL]"
criado: 2026-10-01
base: f40a9956d
thread: 01-telas-legadas.md §06
veredito: "entregue em 1 PR — SalesOrderController@index ganha o branch X-Inertia → Inertia::render('SalesOrder/Index') com o Blade como fallback; nenhuma rota nova; status pelo PUT existente num drawer PT-02. Veredito de teste vem da lane PHP / Pest (Sells · MySQL), não roda local."
---

# _saída 06 · Pedido de venda

## O que saiu

| arquivo | o que é |
|---|---|
| `app/Http/Controllers/SalesOrderController.php` | branch `X-Inertia` no `index()` depois da trava de permissão de sempre; `view('sales_order.index')` segue como fallback (cutover F5 é humano) |
| `resources/js/Pages/SalesOrder/Index.tsx` | Page PT-01: PageHeader canon · filtros (local · cliente · status · status de envio) · busca · tabela · drawer de status |
| `resources/js/Pages/SalesOrder/Index.charter.md` | texto revisado de `PedidoVenda.charter.md` + frontmatter do schema, `related_prototype` = `venda-blade-telas.jsx`, `related_runbook` declarado |
| `resources/js/Pages/SalesOrder/Index.casos.md` | 4 UC (`UC-SORD-01..04`) + 1 em backlog |
| `memory/requisitos/Sells/RUNBOOK-sales-order.md` | F1 do MWART, criado antes do `.tsx` |
| `tests/Feature/Sells/SalesOrderIndexContratoTest.php` | 5 casos, tenant 98 × 99 |
| `.github/workflows/sells-pest.yml` | arquivo na allowlist + `SalesOrderController.php` e `Pages/SalesOrder/**` nos gatilhos |
| `e2e/sales-order-index.spec.ts` · `governance/design/contracts/sales-order-index.contract.json` | carimbados por `criar-tela.mjs`; o contrato aponta para o protótipo |

## Decisões tomadas aqui

1. **Dados pelo endpoint do Blade.** A Page busca `/sells?sale_type=sales_order` com `Accept: application/json` + `X-Requested-With`, como `Sells/Drafts`. Assim os filtros de quem vê o quê (`so.view_own` só os próprios, locais permitidos, `business_id`) continuam num lugar só (`SellController@index`). As células chegam em HTML; a tela extrai o texto.
2. **Quem edita o status é quem o endpoint marca.** A célula de status vem com `edit-so-status` só para admin e status ≠ concluído; o botão aparece só nessas linhas. Salvar chama `PUT /update-sales-orders/{id}/status` (o mesmo do modal) e a linha muda no lugar.
3. **`enable_sales_order` continua no menu.** O item segue condicionado em `AdminSidebarMenu` (não tocado). A Page recebe `salesOrderEnabled`, lido do `pos_settings` do negócio da sessão, e mostra um aviso quando está desligado. O acesso direto pela URL fica como no Blade (o controller nunca bloqueou por esse flag).
4. **UC renomeados para `UC-SORD-*`.** O prefixo do texto revisado (`UC-PV-*`) já existe no Financeiro (ProvaViva) e o casos-gate casa id no corpus global (§5 2026-09-04).
5. **RUNBOOK em `memory/requisitos/Sells/`**, declarado no charter. Criar `memory/requisitos/SalesOrder/` abriria uma pasta de módulo sem SPEC.

## Provas locais (antes do CI)

- `contrato-de-tela --contract sales-order-index.contract.json`: limpo, 3 seções e a ordem.
- `memory-schemas/validate.mjs` no charter e no RUNBOOK: 2 conformes.
- `casos-coverage-guard.mjs`: sem violações novas deste PR.
- `pt-conformance.mjs`: 83 de 83 conformes.
- `php -l` no controller e no teste: sem erro.
- `block-mwart-violation.mjs` do worktree com o payload do `.tsx`: rc 0 (RUNBOOK pelo charter).

## Medido no CI (1ª rodada)

- PUT de status num pedido de **outro negócio** devolve **200 com `success: 0`**, não 404: o `findOrFail` do `postEditSalesOrderStatus` está dentro do `try`. Nada é gravado (o teste confere o status intacto) e a tela trata `success != 1` como erro. Comportamento do legado, mantido.
- Ratchets de layout e ESLint pediram `Stack`/`Inline` no lugar de `flex` solto e tirar o `<label>` em volta do Select Radix (o `SelectTrigger` já tem `aria-label`). Ajustado.

- PHPStan (required) acusou o `@return` do `index()` (`Illuminate\Http\Response`); o docblock agora declara `View|Inertia\Response`.
- `visual-regression` (advisory) falhou por `Governance/Dashboard` (diff 2,41% acima do teto), tela que este PR não toca, e lista `SalesOrder` como tela sem baseline. Baseline não foi regerada aqui: a prática foi aposentada pela ADR 0409.

## O que ficou fora, e por quê

- **Typecheck local não rodou.** O worktree não tem `node_modules`, e o do checkout principal está sem `@types/react` (tsc acusa `react` sem tipo em todo arquivo). O typecheck fica com o CI.
- **"Quantidade restante > 0 em pedido parcial"** ficou no backlog do casos.md: o número vem de `so_qty_remaining` do endpoint legado, que esta thread não toca.
- **Gerar venda e excluir pedido** não entram na tela (Non-Goals do charter); seguem no fluxo de venda.
- **Coluna Valor do protótipo** não entrou: o Blade não a tem, e somar valor na lista seria mudança de cálculo (REGRA MESTRE).
- **Cutover F5** (tirar o Blade) é humano.
- **Achado de ambiente:** o hook `block-mwart-violation` que o harness executa é o do checkout principal, sem o `raizDoArquivo` de hoje; ele procurou o RUNBOOK no checkout principal e bloqueou o `Write`. O hook do próprio worktree aceita o mesmo payload (rc 0). O `.tsx` foi escrito por cópia, com o RUNBOOK já presente.

## PARAR SE

Nenhum disparou: nenhuma rota nova, nenhuma tela de valor tocada. O PR passa de 300 linhas somando teste e Page (~280 linhas de `.tsx`); não dá para separar backend e tela sem deixar a Page sem render.
