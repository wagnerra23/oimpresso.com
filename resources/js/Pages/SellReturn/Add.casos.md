---
id: resources-js-pages-sellreturn-add-casos
casos: Devolução de venda · registro · /sell-return/add/{venda}
irmaos: Add.charter.md (lei) · Add.tsx
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o registro grava valor e estoque — a troca de Blade por React não pode mudar nenhum dos dois.
owner: wagner
last_run: "2026-10-05"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Sells · MySQL)"
---

# Casos de Uso & Aceite — Devolução de venda · registro

> **Fonte:** os `UC-DEV-01..04` do texto revisado em
> `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/Devolucao.casos.md` + o contrato do form
> Blade (`sell_return/add.blade.php`) + `memory/requisitos/Sells/CASOS-USO-DEVOLUCAO.md`.
>
> **Teste:** `tests/Feature/Sells/SellReturnAddContratoTest.php` — tenant 98 × adversário 99,
> `DatabaseTransactions`, números esperados feitos à mão no cabeçalho do teste.
> **Lane:** `PHP / Pest (Sells · MySQL)` — `.github/workflows/sells-pest.yml`.
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## UC-SRADD-01 · A venda de origem já vem carregada `[must]`
- **Origem:** `UC-DEV-04`.
- **Persona:** Larissa — clica em Devolver na venda (ou em Editar na lista de devoluções).
- **Aceite:** Dado uma visita Inertia a `/sell-return/add/{venda}` · Quando a tela abre · Então recebe o componente `SellReturn/Add` e, na carga seguinte, a venda (número, cliente, local) com cada linha vendida e a quantidade vendida de cada uma.
- **Teste:** `SellReturnAddContratoTest` — `UC-SRADD-01 a visita Inertia recebe SellReturn/Add com a venda de origem e as linhas`.
- **Regressão que defende:** a tela abrir vazia ou com a venda errada.
- **Status: 🧪**

## UC-SRADD-02 · Venda de outra empresa não abre nem é devolvida `[T0]` `[must]`
- **Persona:** usuário do business 98 com o id de uma venda do business 99.
- **Aceite:** Dado uma venda do 99 · Quando o 98 abre `/sell-return/add/{venda}` · Então recebe 404 · E quando posta a devolução dela em `/sell-return` · Então nada é gravado e o estoque do 99 não muda.
- **Teste:** `SellReturnAddContratoTest` — `UC-SRADD-02 [T0] venda de outro business: 404 na tela e nada gravado pelo POST`.
- **Regressão que defende:** consulta perdendo o `where business_id` (ADR 0093).
- **Status: 🧪**

## UC-SRADD-03 · Sem permissão, sem tela `[must]`
- **Persona:** usuário sem `access_sell_return` nem `access_own_sell_return`.
- **Aceite:** Dado esse usuário · Quando abre o registro · Então recebe 403.
- **Teste:** `SellReturnAddContratoTest` — `UC-SRADD-03 sem permissão de devolução a tela devolve 403`.
- **Regressão que defende:** o ramo novo pular a checagem de permissão.
- **Status: 🧪**

## UC-SRADD-04 · A quantidade trava na vendida `[E0]` `[must]`
- **Origem:** `UC-DEV-01`.
- **Persona:** Larissa — digita 5 numa linha com 3 vendidas.
- **Aceite:** Dado uma linha com 3 unidades vendidas · Então a tela recebe 3 como teto da linha · E se 5 chegar ao servidor mesmo assim · Então a devolução é recusada e o estoque não muda.
- **Teste:** `SellReturnAddContratoTest` — `UC-SRADD-04 [E0] o teto da linha é a quantidade vendida e o excesso é recusado`.
- **Regressão que defende:** estoque creditado sem lastro (o vetor de CU-DEV-08).
- **Status: 🧪**

## UC-SRADD-05 · A tela grava o mesmo que a Blade `[V0]` `[E0]` `[must]`
- **Origem:** `UC-DEV-03` (total = preço unitário × quantidade devolvida).
- **Persona:** Larissa — devolve 2 de 3 unidades a 1.234,50 por unidade com 10% de desconto na venda.
- **Aceite:** Dado duas vendas iguais · Quando uma é devolvida pelo payload do form Blade e a outra pelo payload da tela React · Então as duas devoluções gravam total 2.222,10, desconto 10%, imposto 0, 2 unidades devolvidas na linha, +2 no estoque e situação "a pagar" sem pagamento lançado.
- **Teste:** `SellReturnAddContratoTest` — `UC-SRADD-05 [V0][E0] o payload da tela e o da Blade gravam o mesmo valor e o mesmo estoque`.
- **Regressão que defende:** número no formato errado inflando o valor (o incidente `num_uf` de 2026-06-05).
- **Status: 🧪**

## UC-SRADD-06 · Os textos do formulário vêm no formato da Blade `[V0]` `[must]`
- **Persona:** quem mantém a tela — a Page reenvia esses textos sem reformatar.
- **Aceite:** Dado a venda acima · Quando a tela recebe a venda · Então o preço vem como `1.234,50`, o desconto como `10,00`, a quantidade já devolvida como `0,00` e a data como `dd/mm/aaaa HH:MM`.
- **Teste:** `SellReturnAddContratoTest` — `UC-SRADD-06 [V0] a tela recebe os campos como texto no formato da empresa`.
- **Regressão que defende:** a Page receber número cru e mandar `1234.5` ao parser pt-BR.
- **Status: 🧪**

---

## Backlog de casos

- **[BACKLOG]** Sem quantidade preenchida, "Salvar devolução" fica desabilitado (era `UC-DEV-02`) — comportamento só do front; entra quando houver teste de navegador para a tela.
- **[BACKLOG]** Motivo da devolução em texto livre no histórico (charter R3) — exige gravar motivo no `store()`.
- **[BACKLOG]** Aviso de item feito sob medida antes de salvar (charter R4) — o produto não guarda essa informação.

## Trilha do tempo
- 2026-10-05 · [CL] trio nascido junto (charter + casos + teste) na thread 03 de venda-menu, PR 2 de 2.
