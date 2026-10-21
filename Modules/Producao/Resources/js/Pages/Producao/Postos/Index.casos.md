---
id: modules-producao-pages-producao-postos-index-casos
casos: Produção · Postos e cadastros · /producao/postos
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do gestor + critério de aceite verificável (Dado/Quando/Então)
por_que: o celular só oferece os motivos do posto; motivo de outro posto ou de outra empresa corrompe o registro de pausa e refugo. Custo-hora da pessoa é dado de desempenho.
owner: wagner
last_run: "2026-10-21"
last_run_ci: "_pendente_ — o trio nasce na thread 03. O veredito entra quando a lane verticais-pest rodar o ProducaoRoteirosContratoTest."
---

# Casos de Uso & Aceite — Produção · Postos e cadastros (`/producao/postos`)

> ℹ️ **`last_run` 2026-10-20 → 2026-10-21 (G-6) — o `Index.tsx` voltou ao que era.** O 1º commit do #9716 pôs nesta tela o
> bloco "Custo-hora pelo rateio das despesas fixas" com o botão Aplicar; o 3º commit tirou o bloco por decisão [W] (o sugerido
> acumulava a parcela fixa a cada aplicação). O `Index.tsx` e o `PostosController.php` ficaram idênticos ao main
> (`b66f3970e1`). Nenhum handler, prop, rota ou copy desta tela mudou; **nenhum UC foi reexecutado**: o bump registra
> *trio reconciliado com a tela nesta data*.

> **Âncora:** `prototipo-ui/cowork/Wagner/cowork-inbox/producao/Producao-celular.casos.md` (UC-PCV-33) e a emenda
> `playbook/E03-cadastros-motivos.md`. Os UCs não derivam do `.tsx`.

---

## UC-PCV-33 · Motivos vêm do cadastro do posto · `must`
- **Persona:** gestor de produção; operador no celular (A4/A5).
- **Aceite:** Dado o posto Roland com 6 motivos de pausa · Então a lista do posto mostra só esses 6 · E outro posto mostra os dele · E a empresa 99 não vê nem altera os motivos da 98.
- **Teste:** `Modules/Producao/Tests/Feature/ProducaoRoteirosContratoTest.php`.
- **Status: ⬜** — teste escrito, aguarda a lane MySQL.
- **Revalidado 2026-10-19:** a tela mudou só na largura dos campos (custo-hora, nome da ferramenta, unidade — #9574); motivos por posto não foram tocados.

---

## Custo-hora do posto e da pessoa (UC-FLX-20 · parcial · US-FIN-070)

> **Âncora:** US-FIN-070 do `memory/requisitos/Financeiro/SPEC.md` (§Origem: *"o que existe é só o
> insumo, não o processo"* — `pcp_postos.custo_hora` preenchido à mão e `pcp_colaborador_custos.custo_hora`;
> o custo real da OS usa o custo-hora copiado no apontamento, sem parcela de despesa fixa), a emenda
> `playbook/E03-cadastros-motivos.md` (custo-hora da pessoa é dado de desempenho, D-PERM) e o UC-FLX-20 do
> catálogo de fluxos (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php:73`). VALOR: regra mestre.

## UC-PCCH-01 · O apontamento guarda o custo-hora do dia em que começou · `must` valor

- **Persona:** gestor de produção.
- **Aceite:** Dado a Roland a 42,00/h e o acabamento a 28,00/h · Quando a OS tem 2 h de Roland e 1 h de acabamento fechadas, mais 480,00 de terceiro · Então o custo real é 84,00 + 28,00 + 480,00 = 592,00 · E mudar o custo-hora da Roland para 999,00 depois não muda esse valor · E apontamento aberto, de outra OS ou de outra empresa não entra.
- **Teste:** `Modules/Producao/Tests/Feature/ProducaoFundoContratoTest.php` (UC-PCV-32).
- **Status: 🧪**

## UC-PCCH-02 · Custo-hora da pessoa é do gestor · `must`

- **Persona:** gestor de produção com `producao.desempenho.ver`.
- **Aceite:** Dado um gestor sem `producao.desempenho.ver` · Então a lista de colaboradores vem vazia e gravar o custo-hora é 403, sem nada gravado · Dado o gestor com a permissão · Quando grava 42,50 para o operador · Então a lista mostra 42,50 · E colaborador de outra empresa é recusado, mesmo com a permissão.
- **Teste:** `Modules/Producao/Tests/Feature/ProducaoRoteirosContratoTest.php`.
- **Status: 🧪**

## Custeio RKW e ponto de equilíbrio (US-FIN-070 · UC-FLX-20)

> **Âncora:** US-FIN-070 do `memory/requisitos/Financeiro/SPEC.md` (§Aceite: rateio das despesas fixas
> proporcional às horas apontadas, antes→depois do custo-hora, aplicar só com confirmação e sem reescrever
> apontamento fechado; ponto de equilíbrio = despesas fixas ÷ margem de contribuição %, aviso sem margem
> positiva; 404 entre empresas; `producao.desempenho.ver` **e** acesso ao Financeiro) e o UC-PCCH-01
> acima (o apontamento congela o custo-hora). VALOR: regra mestre — os testes conferem por dois caminhos.
> Fixa/variável vem só da conta do plano (`fin_planos_conta.classe_custo`, nula = fora do custeio).

## UC-PCCH-03 · Fechamento do mês rateia as despesas fixas pelas horas · `must` valor

- **Persona:** gestor financeiro com `producao.desempenho.ver`.
- **Aceite:** Dado 4.000,00 de despesas em contas fixas no mês (título cancelado e conta sem classe ficam fora) · E 30 h fechadas no posto A (30,00/h) e 10 h no posto B (50,00/h) · Quando o fechamento roda · Então a parcela fixa é 100,00/h, o rateio é 3.000,00 para A e 1.000,00 para B, e o custo-hora RKW sugerido é 130,00 e 150,00 · E divisão não exata fecha ao centavo (maior resto) · E nada é gravado (o comando também só lê).
- **Teste:** `Modules/Financeiro/Tests/Feature/CusteioRkwContratoTest.php`.
- **Status: 🧪**

## UC-PCCH-04 · Ponto de equilíbrio do mês · `must` valor

- **Persona:** gestor financeiro.
- **Aceite:** Dado receita 10.000,00, custos variáveis 2.000,00 e fixas 4.000,00 · Então a margem de contribuição é 80,00% e o ponto de equilíbrio 5.000,00 · Dado custos variáveis maiores que a receita · Então não há número: aparece o aviso de que o ponto de equilíbrio não se aplica.
- **Teste:** `Modules/Financeiro/Tests/Feature/CusteioRkwContratoTest.php`.
- **Status: 🧪**

## UC-PCCH-05 · Aplicar o custo-hora sugerido é ação explícita · `must` valor

- **Persona:** gestor financeiro.
- **Aceite:** Quando aplica o sugerido no posto A · Então a resposta traz antes 30,00 → depois 130,00 · E só o posto A muda · E o apontamento já gravado segue com 30,00 (UC-PCCH-01).
- **Teste:** `Modules/Financeiro/Tests/Feature/CusteioRkwContratoTest.php`.
- **Status: 🧪**

## UC-PCCH-06 · Isolamento e permissão do fechamento · `must`

- **Persona:** gestor de outra empresa; usuário sem uma das permissões.
- **Aceite:** Dado a empresa 99 com despesa fixa e horas próprias · Então o fechamento da 98 não as soma · E aplicar num posto da 99 é 404, sem gravar · E quem tem só o Financeiro ou só `producao.desempenho.ver` recebe 403, sem gravar.
- **Teste:** `Modules/Financeiro/Tests/Feature/CusteioRkwContratoTest.php`.
- **Status: 🧪**

## UC-PCCH-07 · O fechamento tem tela: aba Custeio da DRE · `must` valor

- **Persona:** gestor financeiro (`financeiro.relatorios.view`).
- **Aceite:** Quando abre `/financeiro/dre?aba=custeio&mes=AAAA-MM` · Então vê despesas fixas, custos variáveis, receita, margem de contribuição, ponto de equilíbrio e o rateio por posto com os MESMOS números do `GET /financeiro/custeio-rkw` (a tela só formata) · E sem `producao.desempenho.ver` vê o fechamento mas não o custo-hora dos postos · E mês inválido cai no mês anterior · E nenhuma outra aba calcula o custeio, e nada é gravado.
- **Teste:** `Modules/Financeiro/Tests/Feature/CusteioRkwContratoTest.php`.
- **Status: 🧪**

## UC-PCCH-08 · A conta do plano diz se é fixa ou variável · `must` valor

- **Persona:** gestor financeiro (`financeiro.relatorios.view`), na tela Plano de contas.
- **Aceite:** Quando marca como fixa uma conta de despesa sem classe que tem 500,00 a pagar no mês · Então as despesas fixas do fechamento passam de 4.000,00 para 4.500,00 · E "Fora do custeio" volta a deixá-la de fora · E conta de receita, valor fora de fixa/variável, conta de outra empresa (404) e usuário sem a permissão (403) não gravam.
- **Teste:** `Modules/Financeiro/Tests/Feature/CusteioRkwContratoTest.php`.
- **Status: 🧪**

## UC-PCCH-10 · O centro de custo é o setor do posto · `must` valor

- **Persona:** gestor financeiro. Decisão [W] 2026-10-21: o setor é o campo `pcp_postos.setor` do cadastro do posto; agrupa os postos e rateia por setor.
- **Aceite:** Dado o posto A (30 h) no setor "Corte" e o posto B (10 h) sem setor · Então o fechamento traz Corte com 3.000,00 e "sem setor" com 1.000,00, sem setor por último · Quando o posto B passa a "corte " · Então há um setor só, Corte, com 2 postos, 40 h e 4.000,00 (= despesas fixas) · E a aba Custeio da DRE mostra os setores mesmo para quem não vê o custo-hora dos postos.
- **Teste:** `Modules/Financeiro/Tests/Feature/CusteioRkwContratoTest.php`.
- **Status: 🧪**

## UC-PCCH-11 · Aplicar de novo não soma outra parcela · `must` valor

- **Persona:** gestor financeiro, fechando um mês depois do outro.
- **Aceite:** Dado o posto A com 30,00 digitados e parcela fixa de 100,00/h · Quando aplica o custeio · Então o custo-hora vai a 130,00 e os 30,00 ficam guardados como custo-hora direto · E aplicar de novo (o mesmo mês ou outro com a mesma parcela) mantém 130,00, nunca 230,00 · E o sugerido é sempre direto + parcela do mês · E digitar outro custo-hora no posto (40,00) troca o direto e tira a parcela até aplicar de novo (140,00) · E salvar o posto sem mexer no custo-hora mantém o direto.
- **Origem:** decisão [W] 2026-10-21 (opção a). Antes, o sugerido era o custo-hora gravado + parcela, e cada aplicação somava outra parcela (30 → 130 → 230 → 330).
- **Teste:** `Modules/Financeiro/Tests/Feature/CusteioRkwContratoTest.php`.
- **Status: 🧪**

- **[BACKLOG]** Botão "Aplicar custo-hora" em Postos — saiu do #9716 por decisão [W] (2026-10-21). O sugerido é o custo-hora GRAVADO + parcela fixa, e depois de aplicar o gravado já contém a parcela: aplicar de novo o mesmo mês, ou aplicar o mês seguinte, soma outra parcela (fixture F 4.000 / 40 h: posto A 30 → 130 → 230 → 330). O [W] escolheu guardar o custo-hora direto separado da parcela (UC-PCCH-11, `pcp_postos.custo_hora_direto`); o botão volta em PR próprio, depois que ela entrar. O endpoint `POST /financeiro/custeio-rkw/aplicar/{posto}` (UC-PCCH-05) segue no main.

---

## Operadores por posto (US-PCP-030 · UC-FLX-08)

> **Âncora:** `prototipo-ui/cowork/Wagner/fluxos-modelos.casos.md:164` (processo "OS e produção por item":
> *Iniciar etapa · só se: Operador do setor da etapa*), o UC-FLX-08 do catálogo de fluxos (*cada etapa tem
> setor e responsável*) e a aprovação do [W] em 2026-10-20 (cadastro de operadores por posto, limitando quem
> é responsável e quem começa a etapa). No legado, `SETOR_FUNCIONARIO` ligava o funcionário ao setor.

## UC-PCOPE-01 · O gestor define os operadores do posto · `must`

- **Persona:** gestor de produção com `producao.etapa.mover`.
- **Aceite:** Dado o posto Plotter · Quando o gestor grava Ana e Bia como operadoras · Então a lista do Plotter mostra só as duas · E o Acabamento segue sem cadastro · E gravar a lista vazia limpa o cadastro.
- **Teste:** `Modules/Producao/Tests/Feature/ProducaoOperadoresDoPostoContratoTest.php`.
- **Status: 🧪**

## UC-PCOPE-02 · Liberar só aceita operador do posto como responsável · `must`

- **Persona:** PCP com `producao.liberar`.
- **Aceite:** Dado o Plotter com Ana cadastrada · Quando o PCP libera a OS com Bia na etapa do Plotter · Então o Liberar é recusado com "Responsável não é operador do posto de Impressão." · E a OS segue Aguardando liberação, sem responsável · Quando escolhe Ana · Então a OS vai para Na fila com Ana na etapa.
- **Teste:** `Modules/Producao/Tests/Feature/ProducaoOperadoresDoPostoContratoTest.php`.
- **Status: 🧪**

## UC-PCOPE-03 · Posto sem operadores cadastrados segue como antes · `must`

- **Persona:** PCP e operador.
- **Aceite:** Dado o Plotter sem cadastro (e o Acabamento com Ana cadastrada) · Quando o PCP libera com Bia no Plotter · Então aceita · E Bia, responsável de outra etapa do Plotter, começa a etapa normalmente.
- **Teste:** `Modules/Producao/Tests/Feature/ProducaoOperadoresDoPostoContratoTest.php`.
- **Status: 🧪**

## UC-PCOPE-04 · Só o operador do posto começa a etapa · `must`

- **Persona:** operador no celular.
- **Aceite:** Dado a etapa do Plotter com Bia de responsável, escolhida antes do cadastro · E o Plotter com só Ana cadastrada · Quando Bia inicia · Então é recusado (`etapa_alheia`, "Você não está cadastrado como operador deste posto.") e nada é gravado · Quando a etapa passa a Ana · Então Ana começa.
- **Teste:** `Modules/Producao/Tests/Feature/ProducaoOperadoresDoPostoContratoTest.php`.
- **Status: 🧪**

## UC-PCOPE-05 · Empresa e permissão · `must`

- **Persona:** gestor de produção.
- **Aceite:** Dado um operador da empresa 99 na lista · Então gravar é recusado (422) e nada é gravado · E posto de outra empresa é 404 para ler e para gravar · E quem não tem `producao.etapa.mover` recebe 403 ao gravar.
- **Teste:** `Modules/Producao/Tests/Feature/ProducaoOperadoresDoPostoContratoTest.php`.
- **Status: 🧪**

- **[BACKLOG]** Tela de Postos com a lista de operadores de cada posto (os endpoints existem; a tela ainda não os mostra).
- **[BACKLOG]** Alocador (UC-FLX-10 · US-PCP-029, PR #9647) chamar a mesma regra ao trocar o responsável.
## Alocador decide quem e quando começa (US-PCP-029 · UC-FLX-10)

> **Âncora:** UC-FLX-10 do catálogo de fluxos (`app/Domain/Fsm/Modelos/CatalogoModelos.php`, etapa *Liberada* →
> "Alocador escolhe quem e quando"; `CasosDoPrototipo.php`: *"escolhe operador e horário para não parar máquina
> nem faltar material"*) e a US-PCP-029 do `memory/requisitos/Producao/SPEC.md`. Nesta entrega são os endpoints
> JSON (`PUT /producao/etapas/{id}/alocacao` e `GET /producao/postos/{id}/fila`); a tela do quadro é a US-PCP-015.
> Prova: `ProducaoAlocacaoContratoTest` (tenant 98 × 99).

## UC-PCALO-01 · Trocar responsável e início mantém a duração · `must`

**Dado** a etapa Impressão do Diego, pendente, prevista das 08:00 às 09:30 **Quando** o gestor a passa para a
Ana às 14:00 **Então** a etapa fica com a Ana das 14:00 às 15:30, e a situação da OS não muda.

Status: 🧪

## UC-PCALO-02 · Quem mudou e quando fica no histórico · `must`

**Quando** o gestor muda só o início da etapa **Então** o histórico guarda o gestor, a hora, o responsável e o
início de antes e de depois; o responsável continua o mesmo.

Status: 🧪

## UC-PCALO-03 · Etapa começada não se realoca · `must`

**Dado** uma etapa iniciada ou concluída **Quando** o gestor tenta trocar o responsável **Então** é recusado e
nada muda, nem o histórico.

Status: 🧪

## UC-PCALO-04 · Só pessoa da empresa com acesso à Produção · `must`

**Quando** o responsável escolhido é de outra empresa ou não tem acesso à Produção **Então** é recusado e a
etapa segue com quem estava.

Status: 🧪

## UC-PCALO-07 · Posto com operadores cadastrados só aceita um deles como responsável · `must`

**Dado** o Plotter com só a op2 cadastrada como operadora (US-PCP-030) e a etapa com a op como responsável
**Quando** o gestor troca o responsável para a op **Então** é recusado (`responsavel_invalido`, com o posto) e nada
muda, nem o histórico · mudar só o início previsto segue permitido · trocar para a op2 é aceito. Posto sem
operadores cadastrados aceita qualquer pessoa da empresa com acesso à Produção (UC-PCALO-01/04).

Status: 🧪

## UC-PCALO-05 · Operador não aloca; outra empresa não existe · `must`

**Quando** um operador sem permissão de mover etapa tenta alocar ou ver a fila **Então** recebe 403 **E** etapa
ou posto de outra empresa respondem 404, sem mudar nada.

Status: 🧪

## UC-PCALO-06 · Fila do posto pela ordem do início previsto · `must`

**Dado** no posto uma etapa iniciada (dia 2), uma pendente (dia 3), uma pendente sem responsável (dia 5) e uma
concluída (dia 1) **Quando** o gestor abre a fila **Então** vêm as três abertas nessa ordem, com o responsável
de cada uma; a concluída não aparece.

Status: 🧪

## Prazo de entrega calculado pela fila (US-PCP-028 · UC-FLX-06)

> **Âncora:** UC-FLX-06 do catálogo de fluxos (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php:59` — *"Calcula a
> data de entrega pela capacidade livre de cada etapa"*, etapa Aprovado da venda, situação `planejado`, "SPEC do
> PCP") e a [ADR 0436](../../../../../../../memory/decisions/0436-os-por-item-producao-dona-da-os-do-pedido.md)
> (o roteiro anda nas programações do PCP; "prazo pela fila" ficou fora da fase 2). O caso mora aqui porque a
> fila e as horas por dia são do posto. O cálculo sai em JSON (`GET /producao/vendas/{id}/prazo`, só leitura); a
> tela ainda não mostra. Prova: `ProducaoPrazoPelaFilaContratoTest`.
>
> **Decisão [W] 2026-10-20** (UC-PCPRZ-05..08): *"Pula sábado, domingo e feriados"*, pelo período de funcionamento
> que a empresa já cadastrou — cada dia de produção do posto vale um dia útil. Hora útil = turno da produção
> (`pcp_turnos`) → senão o expediente do RH (turno fixo do Essentials) → senão 08h–18h (D-TURNO de
> `prototipo-ui/cowork/Wagner/cowork-inbox/producao/playbook/_DECISOES-W-2026-10-15.md`), com aviso. Folgas = as do
> expediente; sem ele, sábado e domingo. Feriados = os do RH da empresa e da loja da venda — nenhum fixo no código. O
> módulo lê o RH só pelo contrato `App\Contracts\Rh\CalendarioDaEmpresa`. A data prometida fica na venda
> (`transactions.due_date`, PROJETO_DT_FIM) na aprovação e vai para a OS (`pcp_ordens.prazo_prometido`).

## UC-PCPRZ-01 · A data soma a fila de cada posto e as etapas do roteiro · `must`

**Dado** a impressora (8 h/dia) com 16 h abertas na fila, o acabamento (10 h/dia) com 4 h iniciadas, o corte sem
horas por dia e o roteiro Impressão 4 h → [Corte 8 h ‖ Acabamento 2 h] → Conferência 4 h sem posto **Quando** o
orçamento de 1 banner pede o prazo em 20/10 **Então** Impressão 2,0→2,5 dias, Corte 2,5→3,5 (8 h/dia por padrão),
Acabamento 2,5→2,7, Conferência 3,5→4,0 — 4,0 dias × 10 h do turno = 40 h úteis desde ter 20/10 09:00, e a
entrega prevista é seg 26/10 09:00 (sábado e domingo pulam) — a etapa opcional, a programação concluída e a
de outra empresa no mesmo posto não contam, e as premissas dizem o padrão usado.

Status: 🧪

## UC-PCPRZ-02 · Itens da mesma venda disputam os postos · `must`

**Dado** o mesmo orçamento com 2 banners e uma instalação sem roteiro **Então** o 2º banner espera o 1º em cada
posto (Impressão 2,5→3,0, Corte 3,5→4,5, Acabamento 3,0→3,2, fim 5,0), a entrega vai para ter 27/10 09:00 e a instalação fica
fora do prazo, listada sem roteiro.

Status: 🧪

## UC-PCPRZ-03 · Só leitura, e a venda aprovada não entra na própria fila · `must`

**Dado** a venda já aprovada com a OS dela programada 8 h na impressora **Quando** pede o prazo **Então** a fila da
impressora segue 16 h (a OS da própria venda não conta) e o cálculo não grava nada na venda nem nas programações.

Status: 🧪

## UC-PCPRZ-04 · Quem vende ou quem produz consulta · `must`

**Quando** quem tem `sell.view` ou `producao.access` pede o prazo **Então** recebe a data; sem uma das duas, ou sem o
módulo no pacote, 403; venda de outra empresa, 404.

Status: 🧪

## UC-PCPRZ-05 · Sem nada cadastrado: 08h–18h, folga no sábado e no domingo · `must`

**Dado** a empresa sem turno da produção, sem expediente e sem feriado no RH **Quando** a fila soma 6 h a partir de sex
30/10 16:00 **Então** conta 2 h na sexta, pula sábado e domingo e termina seg 02/11 12:00 — nada no código sabe que 02/11
é Finados; feriado é o que a empresa cadastra · antes das 08:00 começa às 08:00, depois das 18:00 no dia útil seguinte,
fechar às 18:00 fica no mesmo dia · as premissas avisam o padrão.

Status: 🧪

## UC-PCPRZ-06 · Feriados do RH: da empresa e da loja da venda · `must`

**Dado** no RH da 98 o feriado de 02/11 da empresa, o de 03/11 só da loja da venda e o de 04/11 de outra loja, e na 99 o de
04 a 06/11 **Então** as mesmas 6 h desde sex 30/10 16:00 terminam qua 04/11 12:00 na loja, ter 03/11 12:00 sem loja e seg
02/11 12:00 na 99 (os feriados da 98 não valem nela) · a venda leva a loja dela: com feriado da loja em 26/10, a entrega do
UC-PCPRZ-01 passa de seg 26/10 09:00 para ter 27/10 09:00. Mesma regra do Ponto (`ApuracaoService::aplicarFeriado`).

Status: 🧪

## UC-PCPRZ-07 · Hora útil: turno da produção, senão expediente do RH · `must`

**Dado** no RH o turno fixo «Comercial» 07h–12h com folga só no domingo (e um flexível, sem horário, que não conta) **Então**
6 h desde sex 30/10 16:00 terminam seg 02/11 08:00 (sáb 07→12, dom folga) · com o turno da produção 08h–18h cadastrado,
ele manda na hora e as folgas seguem o expediente: sáb 31/10 12:00 · a 99 não vê o expediente da 98.

Status: 🧪

## UC-PCPRZ-08 · Na aprovação a data prometida vai para a venda e para a OS · `must`

**Quando** a venda é aprovada e as OS nascem **Então** a venda sem data recebe a entrega prevista (26/10) em `due_date` e
a OS recebe a mesma data · venda com data digitada pelo vendedor (30/11) **não** é sobrescrita, e a OS recebe 30/11 ·
venda de outra empresa não muda.

Status: 🧪

## UC-PCPRZ-09 · A data prometida não muda o vencimento do título · `must`

**Quando** a data prometida é gravada em `transactions.due_date` **Então** o vencimento que o Financeiro calcula
(`Transaction::getDueDateAttribute`, `TituloAutoService` e `CriarTituloDeVendaJob`) continua sendo a data da venda +
condição de pagamento: venda a 30 dias de 20/10 vence 19/11; venda sem condição vence 20/10 — antes e depois da gravação
de 26/10. Regra mestre de valor.

Status: 🧪

- **[BACKLOG]** UC-FLX-06 — mostrar o prazo na aprovação do orçamento (tela de venda); descontar o já apontado da etapa iniciada; o Ponto passar a usar o contrato `CalendarioDaEmpresa` em vez de importar `EssentialsHoliday`.

---

## Backlog de casos (sem id — a E03 não traz UC próprio; o teste já os defende)

- **[BACKLOG]** Veículo é posto de máquina com placa obrigatória e custo por km; ferramenta tem QR único por empresa.

## Trilha do tempo
- 2026-10-16 · [CL] thread 03 + E03 do playbook de Produção — trio nasce junto (charter + casos + teste).
- 2026-10-20 · [CL] UC-PCCH-01/02 documentam o custo-hora que já existe (UC-FLX-20 parcial); o bullet de backlog do custo-hora da pessoa virou o UC-PCCH-02; rateio e ponto de equilíbrio ficam no [BACKLOG] (US-FIN-070).
- 2026-10-20 · [CL] UC-PCOPE-01..05 — operadores por posto (US-PCP-030 · UC-FLX-08, aprovado por [W] em 2026-10-20).
- 2026-10-20 · [CL] UC-PCALO-01..06 — alocador decide quem e quando começa (US-PCP-029 · UC-FLX-10): endpoints JSON de alocação e fila do posto; a tela é a US-PCP-015.
- 2026-10-20 · [CL] UC-PCALO-07 — o alocador usa a regra dos operadores do posto (US-PCP-030 · UC-FLX-08), a mesma do Liberar e do início da etapa.
- 2026-10-20 · [CL] UC-PCPRZ-01..04 — prazo de entrega pela fila dos postos (US-PCP-028 · UC-FLX-06), cálculo em JSON só leitura; tela e data prometida ficam no [BACKLOG].
- 2026-10-20 · [CL] UC-PCPRZ-05..08 — decisão [W]: dias úteis pelo período de funcionamento cadastrado (turno da produção, expediente e feriados do RH) e data prometida gravada na venda e na OS na aprovação; datas de UC-PCPRZ-01/02 recalculadas (24/10→26/10, 25/10→27/10).
- 2026-10-20 · [CL] UC-PCCH-03..06 — custeio RKW e ponto de equilíbrio (US-FIN-070): cálculo só leitura + aplicar explícito; o [BACKLOG] do rateio virou estes casos.
- 2026-10-21 · [CL] UC-PCCH-10 — centro de custo por setor (decisão [W]: setor = `pcp_postos.setor`).
- 2026-10-21 · [CL] UC-PCCH-07..08 — telas do custeio: aba Custeio na DRE e classe fixa/variável no plano de contas. O botão de aplicar em Postos (era o UC-PCCH-09) saiu por decisão [W] e virou [BACKLOG].
- 2026-10-21 · [CL] UC-PCCH-11 — decisão [W] (opção a): custo-hora direto guardado à parte em `pcp_postos.custo_hora_direto`; o sugerido é sempre direto + parcela do mês e aplicar de novo não soma outra parcela.
