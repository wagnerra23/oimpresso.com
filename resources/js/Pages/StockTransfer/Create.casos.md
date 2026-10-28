---
casos: Nova transferência de estoque · /stock-transfers/create
irmaos: Create.charter.md (lei) · Create.tsx
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a transferência tira de uma filial e põe em outra; as duas pontas têm de ser da mesma empresa.
owner: wagner
last_run: "2026-10-27"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Estoque · MySQL)"
---

> ℹ️ **`last_run` 2026-10-11 → 2026-10-14 (G-6) — tema escuro, thread 03 do playbook `cowork-inbox/tema-escuro`: só COR.**
> O [#9314](https://github.com/wagnerra23/oimpresso.com/pull/9314) pôs par `dark:` nos selos DE/PARA desta tela, e o #9321 tirou essa mudança: a tela tem o header antigo e qualquer toque nela cai na catraca do PageHeader. O `.tsx` voltou byte a byte ao do `main`; a tela segue na baseline da sonda de tema escuro e volta a ser corrigida quando o header migrar.
> **Nenhum UC foi reexecutado**: o bump registra *trio reconciliado com a tela nesta data*.
>
> ℹ️ **2026-10-14, depois — o header migrou e os selos voltaram.** [W] decidiu migrar o header no mesmo dia: o [#9405](https://github.com/wagnerra23/oimpresso.com/pull/9405) trocou o `PageHeader` antigo pelo canônico, e o PR seguinte trocou a cor fixa dos selos DE/PARA por token (`bg-border` · `bg-success-soft`/`text-success-fg`), que já tem valor no escuro. O par `dark:` do #9314 não serviu: o `ui:lint` conta cor de paleta como R1 e reprovou. Só COR e cabeçalho; **nenhum UC foi reexecutado**.

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

## UC-TRCRT-06 · A tela escolhe o produto de verdade e manda o pedido no formato do `store()` `[V0]` `[must]`
- **Persona:** quem cuida do estoque.
- **Aceite:** Dado a origem escolhida · Quando busco o produto (mesma busca `/products/list` da Blade, no local de origem) e escolho a variação · Então a linha vem de `GET /stock-transfers/produto-linha` com o saldo daquele local, e não aceita quantidade acima do saldo (ou do lote), zero, fração em unidade inteira nem a mesma variação duas vezes. Ao salvar, quantidade, custo e frete saem com vírgula decimal e sem milhar (`paraNumUf`), `base_unit_multiplier` sai com ponto (o servidor faz conta com ele sem `num_uf`) e `final_total` sai com ponto em 2 casas (entra cru). Trocar a origem limpa as linhas.
- **Teste:** [`tests/js/estoque-linhas-dominio.test.ts`](../../../../tests/js/estoque-linhas-dominio.test.ts) — os casos `UC-TRCRT-06`. O segundo caminho é o `EstoqueTransferenciaStoreTest` (UC-TRCRT-03), que grava esse mesmo formato no MySQL e confere origem 10 → 7 e destino 2 → 5.
- **Regressão que defende:** até 2026-10-27 a tela, aberta por padrão pelo menu desde o #9096, criava linha de texto livre com `product_id` nulo: não havia como transferir um produto real pelo React. E mandava os números com `Number` (ponto decimal), que o `num_uf` lê como milhar.
- **Status: 🧪**

## Backlog
- [BACKLOG] R-XFER-004 (origem ≠ destino) só é conferida no cliente (botão desabilitado). `store()` não compara `location_id` com `transfer_location_id`, nem confere se as duas filiais são do business da sessão. Grava quantidade: decisão [W].
- [BACKLOG] **Toda transferência cai pendente na requisição (US-ESTOQUE-001) — decisões de [F] em 2026-10-15; a tela da requisição fica com o [W].** Hoje quem preenche escolhe a situação (pendente, em trânsito ou concluída) num campo do formulário, e a concluída já move o saldo (UC-TRCRT-03). Vai mudar:
  - Toda transferência feita aqui nasce **pendente** na tela da requisição e não mexe no estoque (como o UC-TRCRT-04). **Não existe baixa automática.**
  - Quem tem a **permissão** de aprovar (não uma pessoa fixa) decide na requisição: aprovar executa a transferência; recusar deixa a situação **"recusada"**, no histórico (não exclui).
  - O campo situação sai deste formulário.
  - **Depende da tela do [W]:** o "Transferir" do produto (painel e menu da linha da lista de produtos) abre esta tela com produto e local de origem preenchidos.
  - Mexe em movimentação de estoque: antes de mergear, dupla confirmação do saldo (muda só na aprovação, nunca duas vezes) e o antes → depois apresentado ([`proibicoes.md`](../../../../memory/proibicoes.md) §CÁLCULO DE VALOR ou ESTOQUE).
- [BACKLOG] **Nota fiscal da transferência entre matriz e filial** (pedido de 2026-10-23) — Dado uma
  transferência de estoque entre duas filiais da empresa com CNPJ diferentes, Quando ela é concluída,
  Então sai a NF-e de transferência da origem para o destino, com os itens e valores da transferência,
  e a nota fica ligada à transferência; filiais com o mesmo CNPJ não pedem nota. CFOP de exemplo: 5.152
  no mesmo estado e 6.152 entre estados; o contador confirma. Hoje a transferência mexe no estoque e não
  emite nota.
