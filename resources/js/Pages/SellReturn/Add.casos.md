---
id: resources-js-pages-sellreturn-add-casos
casos: Devolução de venda · registro · /sell-return/add/{venda}
irmaos: Add.charter.md (lei) · Add.tsx
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o registro grava valor e estoque — a troca de Blade por React não pode mudar nenhum dos dois.
owner: wagner
last_run: "2026-10-27"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Sells · MySQL)"
---

<!-- REVALIDAÇÃO 2026-10-26 (G-6: o .tsx mudou depois do last_run de 05/10).
     A única mudança no Add.tsx depois de 05/10 é a do #9903 (26/10): o bloco "Pagamento ao
     cliente" (pagar depois ou vale-troca), que os UC-SRADDV-01/02 desta edição cobrem.
     Os UC-SRADD-01..06 não dependem desse bloco: o padrão segue "pagar depois", que salva
     como antes. -->

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

## UC-SRADD-07 · A segunda devolução da mesma venda desconta a primeira `[V0]` `[E0]` `[must]`
- **Origem:** pedido P-039 (processo "Devolução, troca e garantia" de Fluxos: "quantidade até a vendida").
- **Persona:** Larissa — o cliente devolveu 2 de 3 e volta depois com a terceira.
- **Aceite:** Dado 3 unidades vendidas e 2 já devolvidas · Quando reabro a devolução · Então a linha traz as 2 já devolvidas e o teto segue 3, logo só 1 ainda pode voltar · E ao salvar 3 o estoque sobe só 1 (12 → 13), a devolução continua uma só e o total vai a 3.333,15, o mesmo de devolver as 3 de uma vez · E 4 é recusado sem mexer em nada.
- **Como a tela conta:** o campo "Devolver" é acumulado (store() atualiza a devolução da venda); é por isso que o teto não cai para 1 — o que volta agora é o acumulado menos o já devolvido.
- **Teste:** `SellReturnAddContratoTest` — `UC-SRADD-07 [V0][E0] a segunda devolução da mesma venda desconta a primeira`.
- **Regressão que defende:** a segunda devolução somar de novo as unidades da primeira (estoque e valor em dobro).
- **Status: 🧪**

---

## Pagamento ao cliente com vale-troca (US-VEST-021 · R7)

> **Âncora:** US-VEST-021 e decisão [W] de 2026-10-26: o vale-troca entra **no registro da devolução**, ao salvar, e
> não é estorno. O valor vira saldo de Adiantamento do cliente (regras do saldo em `SellReturn/Index.casos.md`,
> UC-SRETVT-*). Prova: `tests/Feature/Sells/ValeTrocaContratoTest.php`.

## UC-SRADDV-01 · A tela pede o vale pela venda de origem `[V0]` `[must]`
- **Aceite:** Dado uma venda com a devolução de 150,00 já salva e o cliente com saldo 20,00 · Quando a tela pede o vale pela venda (`POST /sell-return/vale-troca-da-venda/{venda}`) · Então o vale é de 150,00 e o saldo vai a 170,00. Venda sem devolução → `404`.
- **Teste:** `tests/Feature/Sells/ValeTrocaContratoTest.php` — `UC-SRADDV-01`.
- **Status: 🧪** — roda na lane `sells-pest`.

## UC-SRADDV-02 · Devolução do Cliente padrão não vira vale `[V0]` `[must]`
- **Aceite:** Dado uma devolução do Cliente padrão (consumidor sem cadastro) · Quando peço o vale · Então `422 cliente_padrao`, nenhum pagamento e nenhum saldo. Na tela, a opção Vale-troca aparece desabilitada e diz por quê.
- **Teste:** `tests/Feature/Sells/ValeTrocaContratoTest.php` — `UC-SRADDV-02`.
- **Status: 🧪** — roda na lane `sells-pest`.

## Backlog de casos

- **[BACKLOG]** Sem quantidade preenchida, "Salvar devolução" fica desabilitado (era `UC-DEV-02`) — comportamento só do front; entra quando houver teste de navegador para a tela.
- **[BACKLOG]** Motivo da devolução em texto livre no histórico (charter R3) — exige gravar motivo no `store()`.
- **[BACKLOG]** Aviso de item feito sob medida antes de salvar (charter R4) — o produto não guarda essa informação.
- **[BACKLOG]** A devolução pode virar saldo a favor do cliente (pedido de 2026-10-23) `[V0]` — Aceite:
  Dado uma venda paga de 200,00 e um cliente identificado, Quando devolvo um item de 80,00 e o gerente
  aprova "trocar com crédito", Então nenhum dinheiro sai do caixa e o cliente fica com 80,00 a favor,
  válidos por 6 meses, que aparecem no drawer do cliente e podem ser usados na próxima venda; escolher
  "devolver o dinheiro" segue como hoje. Segue o processo "Devolução, troca e garantia" do [W]
  (`fluxos-processos.jsx`: "Trocar com crédito", aprovado pelo gerente com cliente identificado; "Lançar
  crédito", com validade de 6 meses). Depende do [W]: o charter desta tela diz "não mudar `store()` nem
  `addSellReturn()`" (regra escrita na migração), e lançar o crédito ao salvar muda isso. Mexe em valor:
  Regra Mestre. Verificado contra as regras do [W] em 2026-10-23.
  - Pedido: P-017
  - Âncora: Processo "Devolução, troca e garantia" do Wagner · US-VEST-021
  - Fazer: Opção "trocar com crédito" aprovada pelo gerente; crédito com validade de 6 meses.
  - Hoje: miss | Não tem | 
  - Comunicação visual: gray | Pouco | veio do vestuário
  - Clientes: NewPrint
  - Etiquetas: valor, miss, decisao
- **[BACKLOG]** Devolver tudo (pedido de 2026-10-23) `[V0]` `[E0]` — Dado uma venda de 3 itens, Quando
  clico "Devolver tudo", Então cada linha vem com a quantidade que ainda pode voltar e o total é o da
  venda menos o já devolvido; a devolução parcial continua digitando a quantidade de cada linha
  (UC-SRADD-04). Mexe em valor e estoque: Regra Mestre.
  - Pedido: P-038
  - Âncora: `UC-SRADD-04` · processo de devolução de Fluxos
  - Fazer: Botão que preenche as quantidades que faltam voltar.
  - Hoje: part | Em parte | A devolução parcial existe e trava na vendida
  - Comunicação visual: gray | Pouco | sob encomenda quase não volta
  - Clientes: Fixar, Safety, Estilo, Max Comunicação, Ciasul, Extreme, Mecânica Casa Grande, Wow Comunicação, Litoral Comunicação, Placa Fácil, Makan, Lamac, Zoom, Lessa Cortinas, Movesul, Sabor Brasil, DMB, Mecânica Lebrinha, Jo Painéis, CyberStudio, GP, GSX, Molas Patrício, Display Paraná, Ntrogen, MidiaOff, Quimeco, Vargas
  - Etiquetas: valor, part
- **[BACKLOG]** A segunda devolução da mesma venda só aceita o que ainda não voltou (pedido de
  2026-10-23) `[E0]` — Dado 3 unidades vendidas e 2 já devolvidas, Quando abro a devolução de novo,
  Então o teto da linha é 1. Fonte: processo "Devolução, troca e garantia" de Fluxos ("quantidade até
  a vendida, devolução total ou parcial").
  - Pedido: P-039
  - Âncora: Processo de devolução de Fluxos
  - Fazer: Descontar do teto o que já foi devolvido.
  - Hoje: ok | Tem | Medido em 29/10/2026: desconta. O campo é acumulado (reabrir traz as 2 já devolvidas, teto 3) e salvar 3 sobe o estoque só 1 — UC-SRADD-07
  - Comunicação visual: gray | Pouco | sob encomenda quase não volta
  - Clientes: Fixar, Safety, Estilo, Max Comunicação, Ciasul, Extreme, Mecânica Casa Grande, Wow Comunicação, Litoral Comunicação, Placa Fácil, Makan, Lamac, Zoom, Lessa Cortinas, Movesul, Sabor Brasil, DMB, Mecânica Lebrinha, Jo Painéis, CyberStudio, GP, GSX, Molas Patrício, Display Paraná, Ntrogen, MidiaOff, Quimeco, Vargas
  - Etiquetas: valor, decisao
- **[BACKLOG]** Receber a nota de devolução emitida pelo cliente (comparação com concorrentes,
  2026-10-23) `[V0]` `[E0]` — Dado um cliente empresa que devolve 2 itens com a própria NF-e de
  devolução, Quando informo a chave dessa nota (ou ela chega pelo DF-e), Então a devolução é registrada
  ligada à venda original, com os itens e as quantidades da nota, o estoque volta e o financeiro fica
  igual ao da devolução que nós emitimos; nota com item que não estava na venda é recusada. Fonte: Omie
  (importação da NF-e de devolução pela chave ou pelo XML, associada ao pedido de venda). Hoje a nota
  do cliente pode chegar pela manifestação do DF-e, mas não vira devolução ligada à venda.
  - Pedido: P-093
  - Âncora: Omie (importar a NF-e de devolução pela chave ou XML) · `SellReturn/Add.casos.md`
  - Fazer: Registrar a devolução a partir da nota do cliente, ligada à venda.
  - Hoje: part | Em parte | A nota pode chegar pela manifestação do DF-e, mas não vira devolução ligada à venda
  - Comunicação visual: part | Cliente empresa | cliente empresa devolve com nota
  - Etiquetas: valor, part, cv

## Trilha do tempo
- 2026-10-28 · [CC] `last_run` 2026-10-26 → 2026-10-27, a data do commit que mudou o `.tsx` (6c422a53e1, vale-troca). Ele mexeu no `.tsx` e no casos.md no mesmo commit; ao ligar os pedidos do documento a este arquivo, o commit do casos.md deixou de ser o do `.tsx` e o G-6 passou a cobrar. Prova: os 8 UCs desta tela com veredito pass no manifesto do main (`scripts/casos-test-results.json`). Nenhum caso foi reexecutado agora.
- 2026-10-23 · [M+C] Backlog: devolver tudo e segunda devolução da mesma venda, sem id.
- 2026-10-23 · [M+C] Backlog: a devolução pode virar saldo a favor do cliente, sem id.
- 2026-10-05 · [CL] trio nascido junto (charter + casos + teste) na thread 03 de venda-menu, PR 2 de 2.
