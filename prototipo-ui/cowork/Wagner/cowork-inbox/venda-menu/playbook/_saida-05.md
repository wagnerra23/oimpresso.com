---
sessao: "05"
titulo: "Importação de vendas → ImportSales/{Index,Preview} — saída da thread"
autor: "[CL]"
criado: 2026-10-02
base: 22aaf8be3f
thread: 01-telas-legadas.md §05
veredito: "entregue em 2 PRs, NÃO mergeados (REGRA MESTRE: [W] aprova depois de ver o antes→depois). D2 implementada (fila acima de 200 linhas, worker medido em produção). D3 PARADA: não há caminho de cancelar venda sem efeito externo — opções descritas para [W]."
---

# _saída 05 · Importação de vendas

## O que saiu

| PR | branch | conteúdo |
|---|---|---|
| backend | `claude/vendas-thread-05-importacao-backend` | `App\Services\Sells\ImportSalesService` (cálculo movido do controller, sem reescrever) · `App\Jobs\ImportarVendasJob` na fila `sales-import` · worker no Kernel · fila protegida no `jobs:purge-represados` · `config('sells.import.limite_sincrono')` (200) · SKU, unidade e local escopados por business · `ImportSalesContratoTest` UC-IMPV-01..06 |
| tela (empilhado) | `claude/vendas-thread-05-importacao-tela` | branch `X-Inertia` em `index` e `preview` (Blade segue de fallback) · `ImportSales/Index.tsx` (PT-01) + `ImportSales/Preview.tsx` (PT-02) · charter + casos dos dois · RUNBOOK `memory/requisitos/Sells/RUNBOOK-import-sales.md` · contratos de tela · UC-IMPV-07..11 |

Prova do índice: `ImportSalesController.php` contém `Inertia::render('ImportSales/` (PR da tela).

## D2 — fila acima do limite (feito)

Medido no Hostinger antes de implementar (SSH, leitura, 2026-10-02): `queue.default = database` · `app.env = live` · 5 workers `queue:work` no `schedule:list` · tabela `jobs` vazia · `failed_jobs` com falha em `default` de 2026-09-28. Há quem processe fila. A fila nova é dedicada (`sales-import`) pelo mesmo motivo de `backups` e `attendance-import`: `default` está atrás de `queue.backlog_worker_enabled`.

O progresso chega à tela pela própria `GET /import-sales` (recarga parcial de `estado` a cada 4 s enquanto o job anda). **Nenhuma rota nova.**

## D3 — reverter lote cancela em vez de apagar (PARADO)

Não existe hoje um "cancelar venda" que tire a venda dos totais sem efeito externo:

- o cancelamento canônico (FSM `cancelar_venda` → `CancelarVendaCascade`) dispara SEFAZ, gateway e WhatsApp, **não devolve** o estoque baixado e **não muda `status`** — a venda continua `final` e continua nos totais;
- um `status = 'cancelled'` novo seria somado por 8 linhas de código que filtram `status != 'draft'` (saldo e LTV do cliente) e teria de ser revisto em 93 consultas `DB::table('transactions')` cruas;
- `transactions` não tem `deleted_at`.

O reverter segue o legado (apaga) e a tela diz isso na confirmação. As três opções para [W] e a tabela do que o reverter faz hoje estão no corpo do PR do backend.

## Ajustes ao charter/casos revisados

- R7 do charter revisado ("reverter apaga") ficou como R8 com a decisão D3 e o motivo da parada; R7 novo = D2.
- Prefixo dos casos virou `UC-IMPV-*`: `UC-IMP-*` já é usado pela tela Impostos do Financeiro (`ImpostosContractTest`), e o casos-gate casa id no corpus global (§5 2026-09-04).
- UC-IMP-02 (campo em duas colunas) e UC-IMP-05 (lote some da lista) ficaram como `[BACKLOG]`: o primeiro é regra só do front, o segundo depende da D3.

## Achados fora do escopo

- A planilha da prévia fica em `public/uploads/temp/` (raiz pública) até o import.
- `GET /revert-sale-import/{batch}` é GET que apaga.
- Venda com devolução não é apagada pelo reverter, e o reverter responde "sucesso" assim mesmo.
- A mesma fatura não pode repetir produto: `transaction_sell_lines` tem `UNIQUE (transaction_id, product_id, variation_id)` (`uk_tsl_dup_prevent`) e o import mostra o erro SQL cru — no legado também. Medido no 1º run da lane `sells-pest`.

## PARAR SE

- **rota nova:** não disparou — nenhuma rota criada.
- **PR > 300 linhas:** o backend passa (o grosso é o cálculo **movido** do controller para o serviço, sem reescrita) — partido em backend → tela como manda a ficha.
- **valor sem teste que prove o mesmo total:** coberto — `UC-IMPV-01` (request) e `UC-IMPV-03` (fila) contra a conta à mão (130,00 + 150,00, estoque P 10 → 5 e Q 10 → 9).
- **D3:** disparou — parada descrita acima.
