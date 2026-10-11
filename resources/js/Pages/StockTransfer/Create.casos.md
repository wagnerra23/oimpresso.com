---
casos: Nova transferência de estoque · /stock-transfers/create
irmaos: Create.charter.md (lei) · Create.tsx
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a transferência tira de uma filial e põe em outra; as duas pontas têm de ser da mesma empresa.
owner: wagner
last_run: "2026-10-11"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Estoque · MySQL)"
---

# Casos de Uso & Aceite — Nova transferência de estoque

> **Fonte:** [DOC-RAIZ-ESTOQUE](../../../../memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md) §3/§6/§7 + RUNBOOK da tela (`memory/requisitos/Estoque/_telas/`); o controller só confirma. Nada aqui deriva do `.tsx`.
> Ajuste e transferência gravam quantidade: conserto de qualquer item do Backlog segue a REGRA MESTRE e o merge é do [W].
> **Teste:** `tests/Feature/Stock/MovimentacoesTelasContratoTest.php` (só leitura · tenants 98 × 99 · lane `PHP / Pest (Estoque · MySQL)`).
> **Status:** ✅ passa (G-7) · 🧪 cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## UC-TRCRT-01 · Só abre para quem pode lançar `[must]`
- **Aceite:** Dado um usuário só com `purchase.view` · Quando abre `/stock-transfers/create` · Então recebe 403.
- **Teste:** `MovimentacoesTelasContratoTest` — `UC-AJCRT-01 e UC-TRCRT-01 sem purchase.create os dois formulários respondem 403`.
- **Status: ⬜**

## UC-TRCRT-02 · Origem e destino só oferecem filiais do próprio business `[T0]` `[must]`
- **Aceite:** Dado filiais no business 98 e no 99 · Quando abro o formulário pelo browser · Então `business_locations` traz a minha filial e não a do 99, e `statuses` traz `pending`, `in_transit` e `completed`.
- **Teste:** `MovimentacoesTelasContratoTest` — `UC-TRCRT-02 [T0] origem e destino só oferecem filiais do próprio business, com os 3 status`.
- **Regressão que defende:** RUNBOOK §3 ("origem E destino DEVEM ser da MESMA business").
- **Status: ⬜**

## UC-TRCRT-03 · Concluída tira da origem e põe no destino a mesma quantidade, nos dois formatos de envio `[V0]` `[must]`
- **Persona:** quem cuida do estoque e passa mercadoria de uma filial para outra.
- **Aceite:** Dado um produto com 10 na origem e 2 no destino · Quando salvo uma transferência **concluída** de 3 unidades · Então a origem fica com 7, o destino com 5 e o total continua 12. O resultado é o mesmo quando o envio vem no formato da tela React (data ISO, vírgula decimal sem milhar) e no formato da Blade (data e números no formato da empresa). Preço com 3 casas (`12,125`) e preço de milhar (`1500`) são gravados sem inflar.
- **Teste:** [`EstoqueTransferenciaStoreTest`](../../../../tests/Feature/Estoque/EstoqueTransferenciaStoreTest.php) — os 3 casos `UC-TRCRT-03`.
- **Contrato:** DOC-RAIZ-ESTOQUE §3 (`sell_transfer` + `purchase_transfer`: SAI da origem, ENTRA no destino) + charter §Estados (`completed` move o estoque na hora).
- **Regressão que defende:** a tela React mandava a data em ISO e o `store()` só lia o formato da empresa; e número com ponto decimal é lido como milhar pelo `num_uf` (incidente de 2026-06-05).
- **Status: ⬜** — nasce neste PR; verde esperado. Só a lane decide.

## UC-TRCRT-04 · Pendente fica registrada e não mexe no estoque `[must]`
- **Aceite:** Dado o mesmo produto · Quando salvo a transferência como **pendente** · Então ela existe com seus itens e o saldo da origem e do destino não muda.
- **Teste:** `EstoqueTransferenciaStoreTest` — `UC-TRCRT-04`.
- **Contrato:** charter §Estados (só `completed` move o estoque).
- **Status: ⬜**

## UC-TRCRT-05 · Os dados da linha vêm do local de origem e só do próprio business `[T0]` `[must]`
- **Aceite:** Dado um produto com saldo na origem · Quando a tela pede a linha (`GET /stock-transfers/produto-linha`) · Então recebe o produto, a variação, a unidade, o saldo **daquele local** e o último custo de compra — os mesmos dados da linha da Blade (`get_product_row`). Variação de outro business devolve 404; sem `purchase.create`, 403.
- **Teste:** `EstoqueTransferenciaStoreTest` — os 3 casos `UC-TRCRT-05`.
- **Contrato:** RUNBOOK §3 (multi-tenant) + a linha da Blade (`stock_transfer/partials/product_table_row.blade.php`).
- **Status: ⬜**

## UC-TRCRT-06 · Os itens são produtos do cadastro, escolhidos na busca da filial de origem `[must]`
- **Persona:** quem cuida do estoque, montando a transferência.
- **Aceite:** Dado a filial de origem escolhida · Quando busco um produto · Então a busca procura no saldo **daquela filial**: produto sem controle de estoque não aparece e produto sem saldo aparece desabilitado. Ao escolher, a linha entra com o saldo do local, a unidade e o último custo de compra; o mesmo produto não entra duas vezes; a quantidade não passa do saldo (nem do lote), na unidade escolhida; e o que vai no salvar são os campos da linha da Blade, com vírgula decimal e sem milhar. Sem a filial de origem a busca fica fechada.
- **Teste:** [`transferencia-itens.test.tsx`](../../../../tests/js/transferencia-itens.test.tsx) (Vitest, componente real; só o `fetch` é simulado) — `UC-TRCRT-06`.
- **Contrato:** a tela Blade (`public/js/stock_transfer.js` + `stock_transfer/partials/product_table_row.blade.php`) e a chave única `transaction_sell_lines.uk_tsl_dup_prevent` (uma variação por transferência).
- **Regressão que defende:** a linha voltar a ser texto livre sem `product_id` (como era até 2026-10-11), ou o envio voltar a sair com ponto decimal.
- **Status: 🧪** — rodado à mão em 2026-10-11 (10 de 10, com 3 mutações que reprovam); **nenhuma lane de CI roda este arquivo ainda** (cada lane Vitest lista os próprios specs, e `.github/` é do [W]).

## Backlog
- [BACKLOG] R-XFER-004 (origem ≠ destino) só é conferida no cliente (botão desabilitado). `store()` não compara `location_id` com `transfer_location_id`, nem confere se as duas filiais são do business da sessão. Grava quantidade: decisão [W].
