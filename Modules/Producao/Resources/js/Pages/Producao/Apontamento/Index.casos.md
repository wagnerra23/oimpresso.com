---
id: modules-producao-pages-producao-apontamento-index-casos
casos: Producao · Apontamento do operador · /producao/apontamento
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o apontamento vira custo real e fecha etapa da OS. Errar a etapa aponta tempo no lugar errado; errar a empresa expõe produção alheia; duplicar o envio offline dobra o custo.
owner: wagner
last_run: "2026-10-15"
last_run_ci: "_pendente_ — o trio nasce na thread 04. O veredito por UC entra quando a lane verticais-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Produção · Apontamento (`/producao/apontamento`)

> **Âncora:** `prototipo-ui/cowork/Wagner/cowork-inbox/producao/Producao.casos.md` (UC-PCV-19..23) e
> `Producao-celular.casos.md` (UC-PCV-31, 34, 35, 36 · emenda E04), mais as decisões [W] de 2026-10-15
> (D-PONTO e fila offline). Os UCs não derivam do `.tsx`. Provas: `ProducaoApontamentoContratoTest` (regra)
> e `ProducaoApontamentoTelaTest` (HTTP).

---

## UC-PCV-19 · Minhas etapas de hoje · `must`

**Dado** o operador Diego **Quando** abre o app **Então** vê só as etapas atribuídas a ele hoje, na ordem
programada, com máquina e horário, e não vê etapas de outro operador.

Status: 🧪

## UC-PCV-20 · QR e cronômetro · `must`

**Quando** lê o QR da OS **Então** abre a etapa dele naquela OS, e Iniciar/Pausar/Concluir gravam
apontamentos append-only com usuário, máquina e horário; o cronômetro tem `role="timer"` e botões ≥ 44px.

Status: 🧪

## UC-PCV-21 · Fechar a etapa passa adiante · `must`

**Quando** o operador conclui a etapa **Então** a OS vai para a próxima etapa e o responsável seguinte é avisado.

Status: 🧪

## UC-PCV-22 · Cronômetro esquecido · `must`

**Dado** um apontamento aberto às 18h **Então** o sistema fecha às 18h marcado como automático e avisa o PCP,
e o operador pode pedir correção com justificativa (o original fica).

Status: 🧪

## UC-PCV-23 · Sem internet · `should`

**Dado** o celular offline **Quando** o operador aponta **Então** o toque fica guardado com a hora real e,
ao voltar a rede, é enviado uma vez só.

Status: 🧪

## UC-PCV-31 · Dois cronômetros: máquina e pessoa · `must`

**Dado** Diego com a Roland imprimindo **Quando** inicia o acabamento **Então** os dois ficam abertos, a hora
da Roland não conta como hora do Diego, e ele não abre um 2º apontamento de pessoa.

Status: 🧪

## UC-PCV-34 · QR de etapa alheia não aponta · `must`

**Quando** Diego lê a etiqueta da etapa do Felipe **Então** vê onde está e com quem, e nenhum apontamento é gravado.

Status: 🧪

## UC-PCV-35 · Deixar a máquina rodando · `must`

**Quando** o operador segue para outra etapa **Então** o apontamento da máquina continua aberto, sem pausa.

Status: 🧪

## UC-PCV-36 · Fecha pela saída do ponto · `must`

**Dado** apontamento de pessoa aberto e saída no ponto às 17:52 **Então** fecha às 17:52 marcado automático;
sem saída, fecha no fim do turno; horas de pessoa no dia acima da presença + 30 min geram pendência ao gestor;
o Ponto não é alterado.

Status: 🧪

---

## Material consumido na etapa (US-PCP-021 · UC-FLX-37)

> **Âncora:** decisão [W] 2026-10-19 — *"a OS baixa do estoque o material apontado em cada etapa, pelo
> custo do lote que saiu"* — e o UC-FLX-37 do catálogo de fluxos (`fluxos-data.jsx`: *"congela as horas
> apontadas e o material pelo custo da data da baixa; compra depois não reescreve a OS"*). Nesta entrega
> são os endpoints (PWA e app); a tela ainda não tem o campo — o Non-Goal "baixa de material" do charter
> é da thread 08. Prova: `ProducaoMaterialContratoTest`. ESTOQUE e VALOR: regra mestre.

## UC-PCMAT-01 · O material sai do estoque do local da venda · `must`

**Dado** a OS de um item da venda feita na loja L, com a etapa do Diego iniciada **Quando** ele aponta 3 un de
lona **Então** o saldo da lona em L cai 3, e a baixa fica gravada com a etapa, quem apontou e quando.

Status: 🧪

## UC-PCMAT-02 · Custo do lote que saiu · `must` valor

**Dado** em L o lote A (2 un × 10,00, mais antigo) e o B (5 un × 14,00), e em outro local um lote de 1,00
**Quando** a etapa baixa 3 un **Então** saem 2 de A e 1 de B e o custo é 2 × 10,00 + 1 × 14,00 = 34,00 —
nem o preço do dia nem o lote de outro local; compra feita depois não muda esse valor.

Status: 🧪

## UC-PCMAT-03 · Concluir congela horas e material · `must` valor

**Dado** 1 h de pessoa a 30,00 e 34,00 de material na OS **Quando** a última etapa é concluída **Então** o custo
real congelado é 64,00; material apontado depois da conclusão é recusado e não muda o congelado.

Status: 🧪

## UC-PCMAT-04 · Reenvio não baixa duas vezes · `must`

**Dado** a fila offline reenviando a mesma chave **Então** a baixa acontece uma vez só; a mesma chave com outra
quantidade é recusada.

Status: 🧪

## UC-PCMAT-05 · Só quem está na etapa, só material da empresa · `must`

**Quando** outro operador aponta na etapa do Diego, ou a etapa ainda não começou, ou o material é de outra
empresa, ou a quantidade não é positiva **Então** nada é baixado.

Status: 🧪

## UC-PCMAT-06 · Sem lote no local, a baixa é recusada inteira · `must`

**Dado** 7 un em lote na loja L e a empresa não vende sem estoque **Quando** a etapa aponta 8 **Então** a baixa
é recusada e nada muda (saldo, lote, saída de estoque).

Status: 🧪

## UC-PCMAT-07 · O operador não vê o custo · `must`

**Quando** o material é apontado pela PWA ou pelo app **Então** a resposta diz o que saiu, sem o custo (que é do
gestor), e o reenvio responde sem gravar de novo.

Status: 🧪

---

## Custo da OS em andamento (US-PCP-022 · UC-FLX-36)

> **Âncora:** UC-FLX-36 do catálogo de fluxos (*"mostra o custo da abertura e o de hoje; a regra da compra
> decide se a OS mantém o custo ou acompanha"*) e a regra "Atualizar o custo dos insumos" das etapas da
> Compra (`RegrasCompra`, parâmetro `ops`: *Mantêm o custo da abertura* | *Passam a usar o custo novo*).
> Leitura para o gestor (`GET /producao/ordens/{id}/custo`); a tela ainda não mostra. Prova:
> `ProducaoCustoOsAbertaContratoTest`. Só leitura: nada do congelado muda.

## UC-PCOSA-01 · Congela: abertura × hoje, previsão na abertura · `must` valor

**Dado** a OS aberta em 12/03 com 3 un de vinil (médio 10,00 em 12/03; 15,00 em 20/03) e a regra em
"Mantêm o custo da abertura" **Quando** o gestor consulta em 20/03 **Então** vê abertura 30,00 e hoje 45,00,
o item marcado como mudou, e a previsão em 30,00; nada é gravado.

Status: 🧪

## UC-PCOSA-02 · Vivo: a previsão acompanha · `must` valor

**Dado** a regra em "Passam a usar o custo novo" **Então** a previsão é 45,00, o custo do lote segue 30,00 e
nada é gravado; com a regra desligada, vale a abertura.

Status: 🧪

## UC-PCOSA-03 · Sem regra gravada, congela · `must`

**Dado** a empresa que nunca mexeu na regra **Então** vale o padrão: a OS mantém o custo da abertura.

Status: 🧪

## UC-PCOSA-04 · OS concluída mostra o congelado · `must` valor

**Dado** a OS concluída (1 h × 30,00 + 30,00 de material) **Então** a consulta mostra só o congelado, 60,00,
nas duas regras.

Status: 🧪

## UC-PCOSA-05 · Custo é do gestor · `must`

**Quando** o operador sem `producao.desempenho.ver` consulta **Então** 403; OS de outra empresa **Então** 404.

Status: 🧪

---

## Sinal pago libera a OS (US-PCP-025 · UC-FLX-28)

> **Âncora:** US-PCP-025 do `memory/requisitos/Producao/SPEC.md` (*"A empresa configura se a produção exige
> sinal; desligado, o `liberar` segue como hoje"* · *"`liberar` recusa com motivo enquanto o título não estiver
> quitado, inclusive para quem tem `producao.liberar`"* · *"um listener executa `liberar` pela FSM com ator
> sistema"*) e o UC-FLX-28 do catálogo de fluxos (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php:80`). Nesta
> entrega o sinal é um percentual mínimo do total da venda, pago em `transaction_payments` (troco
> descontado); não há parcela de sinal separada. Ainda sem campo na tela de Fluxos. VALOR (só leitura):
> regra mestre. Prova: `ProducaoSinalPagoLiberaContratoTest`.

## UC-PCLIB-04 · Regra desligada não muda o Liberar · `must`

**Dado** a empresa sem sinal mínimo **Quando** o PCP libera a OS de uma venda de 1.000,00 sem nada pago
**Então** a OS vai para Na fila, como antes.

Status: 🧪

## UC-PCLIB-05 · Com a regra ligada, sem o sinal o Liberar recusa · `must` valor

**Dado** a empresa com sinal mínimo de 30% e a venda de 1.000,00 **Quando** o PCP com `producao.liberar` libera
sem pagamento, ou com 299,99 pago **Então** a liberação é recusada com `sinal_pendente` e *"exigido 300,00,
pago …"*, e a OS segue em Aguardando liberação. O exigido arredonda ao centavo: 333,33 × 30% = 100,00, e
99,99 não basta.

Status: 🧪

## UC-PCLIB-06 · Sinal alcançado, o Liberar manual passa · `must` valor

**Dado** a mesma regra e 350,00 de entrada com 50,00 de troco (pago 300,00) **Quando** o PCP libera **Então** a
OS vai para Na fila.

Status: 🧪

## UC-PCLIB-07 · O pagamento libera a OS sozinho · `must`

**Dado** a regra ligada e a OS com responsável em todas as etapas abertas **Quando** um pagamento de 300,00 é
gravado na venda **Então** a OS vai para Na fila pela ação `liberar_pelo_sinal`, com `ator = sistema`, pago e
exigido no histórico; registrar de novo não executa a transição outra vez. OS com etapa sem responsável fica
em Aguardando liberação. Pagamento gravado por outra empresa não conta e não libera, e a empresa 99 não acha
a OS da 98.

Status: 🧪

- **[BACKLOG]** UC-FLX-28 — parcelas de sinal e saldo geradas na aprovação do orçamento (Vendas/Financeiro) e o
  retorno do banco criando o pagamento da venda (PaymentGateway): hoje o retorno só grava o evento. O campo do
  sinal mínimo na aba Fluxos da empresa também falta (o valor é gravado por `RegrasDaEmpresa::mudarSinalMinimo`).
