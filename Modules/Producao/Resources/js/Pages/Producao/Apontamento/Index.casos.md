---
id: modules-producao-pages-producao-apontamento-index-casos
casos: Producao · Apontamento do operador · /producao/apontamento
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o apontamento vira custo real e fecha etapa da OS. Errar a etapa aponta tempo no lugar errado; errar a empresa expõe produção alheia; duplicar o envio offline dobra o custo.
owner: wagner
last_run: "2026-10-22"
last_run_ci: "_pendente_ — o trio nasce na thread 04. O veredito por UC entra quando a lane verticais-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Produção · Apontamento (`/producao/apontamento`)

> **Âncora:** `prototipo-ui/cowork/Wagner/cowork-inbox/producao/Producao.casos.md` (UC-PCV-19..23) e
> `Producao-celular.casos.md` (UC-PCV-31, 34, 35, 36 · emenda E04), mais as decisões [W] de 2026-10-15
> (D-PONTO e fila offline). Os UCs não derivam do `.tsx`. Provas: `ProducaoApontamentoContratoTest` (regra)
> e `ProducaoApontamentoTelaTest` (HTTP).
>
> **UC-FLX-09** do catálogo de fluxos (*"A leitura do QR da OS inicia ou conclui a etapa e grava o tempo"*,
> `app/Domain/Fsm/Modelos/CasosDoPrototipo.php:62`, situação `parcial`) é o que os UC-PCV-19, 20, 21, 23 e 34
> abaixo descrevem: o QR abre a etapa do operador e o apontamento grava `origem = qr`
> (`ApontamentoOperadorService::iniciar`). O mesmo caminho pelo app das lojas tem prova própria em
> `tests/Feature/Sells/AppProducaoApontamentoContratoTest.php` (UC-APPAPT-01..10).

---

## UC-PCV-19 · Minhas etapas de hoje · `must`

**Dado** o operador Diego **Quando** abre o app **Então** vê só as etapas atribuídas a ele hoje, na ordem
programada, com máquina e horário, e não vê etapas de outro operador.

Status: 🧪

## UC-PCV-20 · QR e cronômetro · `must`

**Quando** lê o QR da OS **Então** abre a etapa dele naquela OS, e Iniciar/Pausar/Concluir gravam
apontamentos append-only com usuário, máquina e horário; o cronômetro tem `role="timer"` e botões ≥ 44px.
O QR da **etapa** (sufixo `:e<programacao_id>`, etiqueta térmica · UC-PCV-37 E1, thread 05) abre aquela
etapa, não a primeira do operador; etapa de outra OS, de outra empresa ou encerrada → "Etapa não encontrada".
Prova do formato e da leitura: `tests/Feature/Sells/AppProducaoApontamentoContratoTest.php` (UC-PCV-37 E1).

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

## Produção da impressão no concluir (US-COMVIS-004 · UC-FLX-09)

> **Âncora:** US-COMVIS-004 do `ComunicacaoVisual/SPEC.md` (*"registrar início/fim do trabalho + m² impresso
> + consumo tinta (CMYK ml)"*) e o UC-FLX-09 do catálogo de fluxos (`CasosDoPrototipo.php:62`). Na linha de
> máquina que está rodando, o "Concluir etapa" leva junto o m² impresso e a tinta C/M/Y/K em ml, todos
> opcionais; eles vão na linha `fim` do apontamento, gravada uma vez (append-only). Ficam fora o painel da
> máquina e a leitura automática do equipamento (UC-FLX-11, decisão D-RIP). Provas:
> `ProducaoApontamentoContratoTest` (regra) e `ProducaoApontamentoTelaTest` (HTTP).

## UC-PCIMP-01 · Concluir grava m² e tinta na etapa · `must`

**Dado** a etapa do Diego no plotter rodando **Quando** ele conclui informando 12,5 m², tinta C 3,2 ml e K 1 ml
**Então** a linha `fim` guarda 12,500 m², C 3,20 e K 1,00; M e Y ficam sem valor (não informado ≠ zero). Concluir
sem informar nada grava o fim como antes, com os campos vazios. Os campos aparecem só na linha de máquina,
com toque ≥ 44px.

Status: 🧪

## UC-PCIMP-02 · Valor fora do formato recusa o concluir · `must`

**Dado** a mesma etapa **Quando** o valor chega negativo, com letra, com vírgula na API, com casas demais ou com
dois separadores **Então** o concluir é recusado com `quantidade_invalida`, nada grava e a etapa segue aberta. A
tela troca a vírgula por ponto antes de enviar.

Status: 🧪

## UC-PCIMP-03 · Reenvio offline não duplica · `must`

**Dado** a fila offline reenviando o mesmo concluir (mesma chave) com m² e tinta **Então** existe uma linha `fim`
só, com os valores do primeiro envio.

Status: 🧪

---

## Material consumido na etapa (US-PCP-021 · UC-FLX-37 · UC-FLX-14)

> **Âncora:** decisão [W] 2026-10-19 — *"a OS baixa do estoque o material apontado em cada etapa, pelo
> custo do lote que saiu"* — e o UC-FLX-37 do catálogo de fluxos (`fluxos-data.jsx`: *"congela as horas
> apontadas e o material pelo custo da data da baixa; compra depois não reescreve a OS"*). Os endpoints
> (PWA e app) vieram primeiro; desde 2026-10-21 a tela tem o botão "Material" na etapa rodando, com material,
> quantidade e rolo (UC-PCMAT-09/10). O estorno segue fora. Prova: `ProducaoMaterialContratoTest`. ESTOQUE e
> VALOR: regra mestre.
>
> Os mesmos UCs cobrem a parte já feita do **UC-FLX-14** (*"Consome do estoque a quantidade real, não a
> orçada"*, `CasosDoPrototipo.php:67`, situação `parcial`): o que baixa é a quantidade apontada na etapa.

> A regra "ao entrar na etapa → Baixa o material do estoque" do catálogo está só nos modelos de venda
> sem OS (Balcão enxuto e Brindes). Nos modelos com OS a baixa é a do apontamento, acima — não há regra
> que baixe de novo. Nos sem OS ela não executa de propósito e grava o porquê
> (`CatalogoModelos::PENDENCIAS`): ligar o `ConsumirEstoque` baixaria a reserva por cima da baixa da
> venda finalizada. Prova em `resources/js/Pages/Fluxos/Index.casos.md` (UC-FLXT-20 e UC-FLXT-21); a
> venda sem OS segue baixando a peça na finalização (decisão [W] 2026-10-20). No item sob encomenda a venda
> aprovada só RESERVA o insumo e a baixa é a apontada (UC-PCRES-01..08, abaixo).

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

## UC-PCMAT-08 · A baixa sai do rolo escolhido · `must` estoque

> US-COM-025 · UC-FLX-18. Prova: `ProducaoRoloRastreioContratoTest`.

**Dado** em L o lote A (2 un × 10,00, mais antigo) e o B (5 un × 14,00) **Quando** a etapa baixa 3 un escolhendo o lote B
(`lote_id`) **Então** as 3 saem de B e o custo é 3 × 14,00 = 42,00; sem escolha a baixa segue o FIFO, como antes. Lote de
outro local, de outro material, de outra empresa, de compra ainda não recebida ou sem saldo para a quantidade inteira é recusado e nada é baixado; a
mesma chave com outro lote é conflito.

Status: 🧪

## UC-PCMAT-09 · A tela oferece o material da receita e os rolos com saldo · `must` estoque

> UC-FLX-18 (*"o campo do rolo na tela do operador"*). A receita do produto da OS (`InsumosDaReceita`) diz o
> material; o roteiro não tem material próprio. Prova: `ProducaoMaterialContratoTest`.

**Dado** a OS de 4 un com receita de 0,5 de lona por unidade, e em L os lotes A (2 un, mais antigo) e B (5 un), e em M
o lote C **Quando** o operador abre "Material" na etapa rodando **Então** vê a lona com previsto 2 e os rolos A (saldo 2)
e B (saldo 5), nessa ordem, sem o C (outro local), sem material de outra empresa e sem custo. **E** depois de baixar 3
pelo FIFO o A some (saldo 0) e o B mostra 4, o mesmo saldo da linha da compra.

Status: 🧪

## UC-PCMAT-10 · O rolo escolhido na tela é o que sai · `must` estoque

**Dado** a mesma lista **Quando** o operador escolhe o rolo B e baixa 3 **Então** saem as 3 de B (sobra 2) e o A fica com 2
— o FIFO teria tirado do A. A quantidade digitada com vírgula sai com ponto (`2,5` → `2.5`; `1.000,25` → `1000.25`) e o
botão mostra o que vai baixar (vitest `producao-apontamento-quantidade`).

Status: 🧪

---

## Reserva do insumo na venda (US-PCP-031 · UC-FLX-14)

> **Âncora:** decisão [W] 2026-10-20 — *"Reserva insumo na venda, baixa real na produção"* — para o item feito
> sob encomenda; o caso prático da Comunicação Visual (*"reserva 6m² lona em 'aprovado' → consome em 'produção
> concluída'"*) e o UC-FLX-14 do catálogo (*"Consome do estoque a quantidade real, não a orçada"*). A receita do
> Manufacturing é a fonte do material (decisão [W] 2026-10-15). Sob encomenda = o item gera OS, o produto vendido
> não controla estoque e tem receita. Chave por empresa `common_settings['producao_reserva_insumo']`, desligada por
> padrão. Prova: `ProducaoReservaInsumoContratoTest`. ESTOQUE: regra mestre — saldo físico e reservado em cada passo,
> por dois caminhos.

## UC-PCRES-01 · Venda aprovada reserva o insumo da receita · `must` estoque

**Dado** o banner (não controla estoque) com receita de 5 lona para 2 banners, e a chave ligada **Quando** a venda de
2 banners é aprovada e a OS nasce **Então** a OS reserva 2,5 × 2 = 5,0000 de lona no local da venda, sem data de
expiração, e o saldo da lona não muda; aprovar de novo não reserva de novo.

Status: 🧪

## UC-PCRES-02 · Revenda e chave desligada não reservam · `must` estoque

**Dado** a placa pronta (controla estoque, vende da prateleira) com a mesma receita **Quando** a OS dela nasce **Então**
nada é reservado — o núcleo segue baixando a peça na venda. **E** com a chave desligada a OS do banner nasce sem
reserva.

Status: 🧪

## UC-PCRES-03 · A baixa real abate a reserva · `must` estoque

**Dado** 5 de lona reservados e saldo 10 **Quando** a etapa aponta 3 **Então** o saldo vai a 7 e a reserva a 2 — uma
baixa só.

Status: 🧪

## UC-PCRES-04 · Consumo além do reservado baixa normalmente · `must` estoque

**Dado** 5 reservados **Quando** a etapa aponta 3 e depois 4 **Então** o saldo cai 7 (10 → 3) e a reserva abate só o
que tinha: fica zerada e `consumed`.

Status: 🧪

## UC-PCRES-05 · OS concluída libera o que sobrou · `must` estoque

**Dado** 5 reservados e 3 apontados **Quando** a OS é concluída **Então** os 2 que sobraram são liberados e o saldo
segue 7.

Status: 🧪

## UC-PCRES-06 · Venda cancelada libera tudo · `must` estoque

**Dado** 5 reservados e nada apontado **Quando** a venda vai para a etapa cancelada **Então** a reserva é liberada e o
saldo segue 10.

Status: 🧪

## UC-PCRES-07 · Reserva só dentro da empresa · `must`

**Quando** outra empresa lê a receita, abate ou libera a reserva da OS **Então** nada muda; a mesma chamada na empresa
dona abate.

Status: 🧪

## UC-PCRES-08 · A venda não baixa nem solta a reserva de insumo da OS · `must` estoque

**Dado** a OS reservou 5 de lona e já apontou 3 (saldo 7, 2 reservados) e a venda tem uma reserva comum de 1
**Quando** a venda passa por "Concluir produção" (ConsumirEstoque) ou "Reabrir para revisão" (LiberarReserva)
**Então** só a reserva comum é baixada (saldo 6); a reserva da OS segue ativa com 2 — quem baixa o insumo é o
apontamento, e quem solta a reserva é a OS concluída ou a venda cancelada.

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

---

## Cliente aprova a arte por link (US-COMVIS-NEW-004 · UC-FLX-02)

> **Âncora:** UC-FLX-02 do catálogo de fluxos (`fluxos-data.jsx`, etapa "Aprovação do cliente"):
> *"Envia link da prova; aprovado avança para Impressão, recusado volta para Arte com o comentário"* —
> e a US-COMVIS-NEW-004 (`memory/requisitos/ComunicacaoVisual/SPEC.md`: link de curta validade, 7 dias,
> página pública sem login, uso único). A etapa "Aprovação do cliente" é uma etapa do roteiro desta OS;
> por isso o contrato mora aqui, junto do apontamento que conclui as outras etapas. Nesta entrega são o
> endpoint do operador (gera o link) e a página pública; o envio por WhatsApp veio depois (UC-PCART-06,
> 2026-10-27). E-mail e o botão na tela ficam para depois. Prova: `ProducaoAprovacaoArteContratoTest`.

## UC-PCART-01 · Aprovar leva a OS para a Impressão · `must`

**Dado** a OS com Arte feita, "Aprovação do cliente" pendente e Impressão depois, e o link da prova
enviado **Quando** o cliente abre o link e aprova **Então** a etapa de aprovação fica concluída, a OS segue em
execução pelo motor (sem mexer na situação à mão) e a Impressão pode começar — antes da aprovação, não podia.
O banco guarda só o hash do token.

Status: 🧪

## UC-PCART-02 · Recusar volta para a Arte com o comentário · `must`

**Quando** o cliente pede alteração com o comentário "Trocar o telefone do rodapé" **Então** a Arte volta como
etapa nova "Arte (alteração)" para o mesmo designer, o comentário fica gravado, a aprovação continua pendente
e a Impressão segue esperando. Pedido de alteração sem comentário é recusado e nada muda.

Status: 🧪

## UC-PCART-03 · O link vale uma resposta só · `must`

**Dado** um link já respondido **Quando** alguém tenta aprovar ou recusar de novo **Então** a resposta é recusada
(410) e nada muda.

Status: 🧪

## UC-PCART-04 · Link vencido é recusado · `must`

**Dado** um link com mais de 7 dias **Quando** o cliente abre ou aprova **Então** a resposta é recusada (410) e a
etapa fica como estava.

Status: 🧪

## UC-PCART-05 · Só a empresa da OS gera link · `must`

**Quando** um usuário da empresa 99 pede o link de uma OS da empresa 98 **Então** recebe 404 e nada é gravado;
token desconhecido também é 404.

Status: 🧪

## UC-PCART-06 · O link da prova vai ao cliente por WhatsApp · `must`

> Âncora: US-COMVIS-NEW-004 (`memory/requisitos/ComunicacaoVisual/SPEC.md`, Acceptance: envio pelo WhatsApp,
> LGPD antes do envio, "sem canal → UI alerta contate manualmente"). O envio é do Modules/Whatsapp pelo
> contrato `AvisaClienteWhatsapp` (canal, opt-out LGPD e celular conferidos lá, US-WA-319). Flag
> `producao.aprovacao_arte.whatsapp` (env `PRODUCAO_ARTE_WHATSAPP`) nasce desligada.

**Dado** a flag desligada **Quando** o designer gera o link **Então** nada é enviado e o link volta ao operador
(`whatsapp: null`) · **Dado** a flag ligada e a venda da OS com cliente **Quando** o link é gerado ou trocado
**Então** o cliente da venda, na empresa da OS, recebe o texto com o link e a validade, e a resposta diz
`whatsapp: {enviado: true}` · Venda de outra empresa ou sem cliente: nada é enviado, o link é gerado igual e a
resposta diz `{enviado: false, motivo: sem_cliente}` para a tela pedir contato manual.

Status: 🧪

## UC-PCV-11 · Arte aprovada travada · `must`

> Âncora: `prototipo-ui/cowork/Wagner/cowork-inbox/producao/Producao.casos.md` UC-PCV-11 · thread 10 do playbook
> (D-ARTE-PAGINA, [W] 2026-10-21: a página `/arte/{token}` fica; a thread 10 é a trava).

**Dado** a arte aprovada **Quando** alguém troca o arquivo da arte (`POST /producao/ordens/{id}/arte/trocar`) **Então**
a Aprovação do cliente volta como etapa nova "(nova versão)" no mesmo bloco, as etapas seguintes voltam a esperar e
sai um link novo · Enquanto o cliente não aprova, a arte que vale (`GET /producao/ordens/{id}/arte`) segue sendo a
última aprovada · Sem arte aprovada, ou com o mesmo arquivo, a troca é recusada e nada muda · OS de outra empresa: 404.
A arte vigente ainda não aparece na tela do operador (fica para quando os PRs abertos do apontamento entrarem).

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

## Refugo e retrabalho com motivo (US-PCP-027 · UC-FLX-16)

> **Âncora:** UC-FLX-16 do catálogo de fluxos (Acabamento: *refugo com motivo; refugo e retrabalho somam
> custo à OS*), o cadastro de motivos de refugo por posto (UC-PCV-33, `pcp_motivos.tipo = refugo`), o
> "Reprovar no controle" do processo da OS (ADR 0436: Em execução → Retrabalho, papel
> `producao.qualidade`) e a baixa de material da US-PCP-021. O endpoint da PWA é
> `POST /producao/apontamento/refugo`; desde 2026-10-21 a tela tem o botão "Registrar refugo" na etapa
> que está rodando (UC-PCREF-07). O app das lojas e o relatório por motivo e posto ainda não. Provas:
> `ProducaoRefugoContratoTest` (regra) e `ProducaoApontamentoTelaTest` (o que a tela recebe).
> ESTOQUE e VALOR: regra mestre.

## UC-PCREF-01 · Refugo com quantidade, motivo, quem e quando · `must`

**Dado** a etapa de Impressão do Diego iniciada **Quando** ele aponta 2 peças refugadas com o motivo "Cor" do
posto **Então** o refugo fica gravado com a etapa, a OS, o motivo, a quantidade, quem e quando; sem material
nem retrabalho, o estoque e a situação da OS não mudam.

Status: 🧪

## UC-PCREF-02 · Material reposto sai pelo lote · `must` valor

**Dado** em L o lote A (2 un × 10,00) e o B (5 un × 14,00) **Quando** o refugo repõe 3 un de lona **Então** o
saldo cai 3 e a baixa custa 2 × 10,00 + 1 × 14,00 = 34,00, igual à baixa de material da etapa.

Status: 🧪

## UC-PCREF-03 · Retrabalho e custo do refugo na OS · `must` valor

**Dado** a Impressão feita em 1 h a 30,00 **Quando** o controle aponta o refugo com 3 un de lona e manda para
retrabalho **Então** a OS vai para Retrabalho com a etapa "Impressão (retrabalho)"; refeita em 0,5 h e com o
Acabamento em 0,5 h, a OS conclui com custo real 94,00 (pessoa 60,00 + material 34,00) — sem o refugo seriam
45,00.

Status: 🧪

## UC-PCREF-04 · Reenvio não grava nem baixa duas vezes · `must`

**Dado** a fila offline reenviando a mesma chave **Então** o refugo e a baixa acontecem uma vez só; a mesma
chave com outra quantidade é recusada.

Status: 🧪

## UC-PCREF-05 · Só motivo de refugo do posto, da empresa · `must`

**Quando** o motivo é de outra empresa, é de pausa ou é de outro posto, a etapa é de outro operador ou ainda
não começou, a quantidade passa das peças da OS, ou falta lote para o material **Então** nada é gravado nem
baixado.

Status: 🧪

## UC-PCREF-06 · O operador não vê custo e não manda sozinho para retrabalho · `must`

**Quando** o operador aponta o refugo pela PWA **Então** a resposta não traz custo e o reenvio não grava de
novo; pedindo retrabalho sem o papel de qualidade **Então** 403 e nada fica (nem o refugo, nem a baixa).

Status: 🧪

## UC-PCREF-07 · Registrar refugo pela etapa que está rodando · `must`

**Dado** a etapa de Acabamento do Diego rodando, com o motivo de refugo "Corte torto" e o de pausa "Falta
material" no posto, e um motivo de refugo de outra empresa **Quando** ele toca em "Registrar refugo" **Então**
o formulário pede o motivo (só os de refugo daquele posto, da empresa dele), as peças refugadas e se vai para
retrabalho, com todo controle de toque ≥ 44px; a lista de pausa continua sem os motivos de refugo.

Status: 🧪

---

## Romaneio de impressão peça a peça (US-PCP-023 · UC-FLX-12)

> **Âncora:** UC-FLX-12 do catálogo de fluxos (*"Pré-impressão → Impressão gera a lista do que foi enviado
> ao setor de impressão e corte"*, referência Mubisys) e a US-PCP-023 do SPEC. As medidas por peça vêm do
> item do orçamento da OS de comunicação visual (`comvis_orcamento_itens`: largura × altura × quantidade).
> JSON (`POST /producao/os/{id}/romaneio`, `GET /producao/romaneios/{id}`); a tela ainda não mostra.
> Prova: `ProducaoRomaneioContratoTest`.

## UC-PCROM-01 · Uma linha por peça da OS · `must`

**Dado** a OS com 2 peças no orçamento — Banner 3,000 × 1,000 m × 2 em Lona 440 g (6,000 m²) e Adesivo
0,500 × 0,500 m × 10 sem material (2,500 m²) — e a etapa de impressão no posto Plotter **Quando** o PCP gera o
romaneio para essa etapa **Então** sai o `ROM-<nº da OS>-v1` com 2 linhas, na ordem do orçamento, cada uma
com descrição, mídia, largura, altura, quantidade e área, o posto de destino e quem gerou; o operador lê o
mesmo romaneio.

Status: 🧪

## UC-PCROM-02 · Gravado não muda; gerar de novo é a v2 · `must`

**Dado** o romaneio v1 gravado com o Banner × 2 **Quando** o orçamento passa o Banner para × 5 **Então** a v1
segue com × 2 e 6,000 m², e editar ou apagar o romaneio ou a linha é recusado; **Quando** o PCP gera de novo
**Então** sai a v2 com × 5, e a linha do Banner aponta que a peça já saiu no romaneio v1.

Status: 🧪

## UC-PCROM-03 · Gerar é do PCP; ler é de quem produz · `must`

**Quando** o operador sem o papel `producao.liberar` tenta gerar **Então** 403 e nada é gravado; sem
`producao.access` a leitura é 403. OS sem peças no orçamento **Então** 422 `quantidade_invalida`.

Status: 🧪

## UC-PCROM-04 · Outra empresa, 404 · `must`

**Quando** o PCP do tenant 98 gera romaneio de OS do tenant 99, ou aponta como destino a etapa de outra OS,
**Então** 404 e nada é gravado; romaneio de outra empresa na leitura **Então** 404.

Status: 🧪

---

## Aproveitamento de mídia na bobina (US-PCP-024 · UC-FLX-13)

> **Âncora:** UC-FLX-13 do catálogo de fluxos (*"Agrupa os jobs na bobina para reduzir a perda de mídia"*,
> etapa Pré-impressão, referências Holdprint, printIQ e Calcgraf) e a US-PCP-024 do SPEC. As peças vêm dos
> itens do orçamento das OS de comunicação visual (`comvis_orcamento_itens`, só os da mídia pedida); a
> largura útil da bobina é do PCP (`pcp_bobinas`). Arranjo em faixas: da peça mais alta para a mais baixa,
> cada uma na primeira faixa onde cabe; com giro, a peça deita quando cabe. JSON (`PUT /producao/bobinas/{mídia}`,
> `POST /producao/aproveitamento/simular`, `POST /producao/aproveitamento`); a tela ainda não mostra.
> Prova: `ProducaoAproveitamentoMidiaContratoTest`.

## UC-PCAPV-01 · A mídia ganha largura útil, margem e giro · `must`

**Dado** a Lona sem bobina cadastrada **Quando** simula **Então** 422 `material_invalido` · **Quando** cadastra
1,600 m de largura útil e 0,020 m de margem **Então** a bobina fica com giro permitido; trocar para sem giro não
duplica (uma bobina por mídia); largura 0 é recusada.

Status: 🧪

## UC-PCAPV-02 · Girando, 4 peças em 2 faixas · `must`

**Dado** a OS A com 2 × 0,780 × 0,500 m de Lona (e um adesivo, outra mídia) e a OS B com 0,500 × 1,000 m e
0,580 × 0,500 m de Lona **Quando** simula as duas **Então** o banner deita (1,000 × 0,500); faixa 1 = banner + placa
(1,600 m), faixa 2 = as duas faixas (1,580 m); 1,020 m de rolo, 1,570 m² úteis em 1,632 m², perda 3,80% — uma a
uma seriam 2,060 m e 52,37% · o consumo previsto rateado pela área: A 0,507 m, B 0,513 m · simular não grava nada.

Status: 🧪

## UC-PCAPV-03 · Sem giro o rolo cresce; peça maior que a bobina é recusada · `must`

**Dado** a mesma Lona sem giro **Quando** simula **Então** o banner fica em pé (faixa de 1,000 m) e o rolo vai a
1,520 m · peça de 1,700 × 1,700 m numa bobina de 1,600 m **Então** 422 `material_invalido`.

Status: 🧪

## UC-PCAPV-04 · Aceitar vira o romaneio de cada OS · `must`

**Quando** o operador sem o papel `producao.liberar` aceita **Então** 403 e nada é gravado · **Quando** o PCP aceita
com a etapa de destino de cada OS **Então** grava o arranjo (1,020 m, 2,060 m uma a uma, quem aceitou), o consumo
previsto de cada OS (0,507 m e 0,513 m) e gera o romaneio de impressão de cada OS nessa etapa (US-PCP-023), ligado
ao arranjo.

Status: 🧪

## UC-PCAPV-05 · Outra empresa, 404 · `must`

**Quando** o PCP do tenant 98 cadastra bobina ou simula com mídia do tenant 99, põe OS do tenant 99 no arranjo,
ou aponta etapa do tenant 99 como destino **Então** 404, e nenhuma bobina, arranjo ou romaneio é gravado.

Status: 🧪

---

## Liberação da OS para a produção (UC-FLX-28 · parcial · US-PCP-025)

> **Âncora:** US-PCP-025 do `memory/requisitos/Producao/SPEC.md` (§Origem: *"Existe: o estado inicial
> `aguardando_liberacao` e a transição `liberar` → `na_fila` só com `producao.liberar`;
> `SituacaoDaOrdem::liberar()` confere apenas os responsáveis das etapas"*), a decisão [W] 16/10 (opção c,
> protótipo #9530: o Liberar pede o responsável de cada etapa aberta) e o UC-FLX-28 do catálogo de fluxos
> (`CasosDoPrototipo.php:80`). Ainda não há rota nem tela: é o serviço `SituacaoDaOrdem::liberar`; a
> Ficha da OS é da thread 08. O que o operador sente nesta tela é a recusa do apontamento. Provas:
> `ProducaoSituacaoDaOrdemContratoTest` e `ProducaoLiberarResponsavelContratoTest`.

## UC-PCLIB-01 · OS não liberada não aponta · `must`

**Dado** a OS por item com as etapas Impressão, Laminação e Recorte, ainda em Aguardando liberação **Quando**
o operador inicia a Impressão **Então** o apontamento é recusado com `os_nao_liberada` (409); depois do
Liberar, a OS fica Na fila.

Status: 🧪

## UC-PCLIB-02 · Liberar exige o responsável de cada etapa aberta · `must`

**Dado** a OS com Corte CNC e Montagem sem responsável **Quando** o PCP libera escolhendo só o do Corte CNC
**Então** a liberação é recusada com *"Escolha o responsável de: Montagem."* e nada muda; escolhendo os dois,
cada etapa grava o seu responsável, a OS vai para Na fila e o histórico guarda os responsáveis. Etapa que já
tinha responsável e não vem na escolha fica com o dele.

Status: 🧪

## UC-PCLIB-03 · Liberar só dentro da empresa · `must`

**Quando** o PCP escolhe responsável de outra empresa, ou uma etapa que não é desta OS, ou a empresa 99 tenta
liberar a OS da 98 **Então** a liberação é recusada (`responsavel_invalido` ou OS não encontrada) e a OS
segue em Aguardando liberação.

Status: 🧪

- **[BACKLOG]** Liberar sem o papel `producao.liberar` é recusado pelo motor FSM (`ProcessoDaOrdem::ACOES`),
  mas ainda não tem teste próprio nesta tela.

---

## Aguardando material (US-PCP-032 · D3)

> **Âncora:** D3 da proposta [2026-10-09-fabricacao-vs-producao-decisao-w](../../../../../../../memory/decisions/proposals/2026-10-09-fabricacao-vs-producao-decisao-w.md)
> (*"deixar criar a ordem e bloquear iniciar/concluir sem material, com estado 'aguardando material' e atalho
> para Compras"*, aprovada por [W] em 2026-10-13) e a revisão de 2026-10-21, que pôs o estado na Produção.
> Necessário = receita × quantidade da OS (a mesma conta da reserva, US-PCP-031); disponível = saldo no local da
> venda − reservas ativas de outras OS e vendas. Só lê estoque. Chave da empresa
> `producao_aguarda_material`, desligada por padrão. JSON (`GET /producao/ordens/{id}/material`,
> `POST /producao/ordens/{id}/material/conferir`); a tela ainda não mostra. Prova: `ProducaoAguardandoMaterialContratoTest`.

## UC-PCMAT-01 · Chave desligada: nada muda · `must`

**Dado** a lona com 3 de saldo e a OS do banner que precisa de 5 **Quando** o PCP libera com a chave da empresa
desligada **Então** a OS vai para Na fila, como antes, e o saldo segue 3.

Status: 🧪

## UC-PCMAT-02 · Liberada sem material fica Aguardando material e não começa · `must`

**Dado** a chave ligada, saldo 3 e compras da lona pedida (4) e pendente (1), e uma já recebida (9) **Quando** o
PCP libera **Então** a OS vai para Aguardando material; a lista mostra necessário 5,0000, disponível 3,0000, falta
2,0000 e 5,0000 a caminho nas duas compras não recebidas · o operador tenta começar a etapa **Então** é recusado e
nada muda no saldo.

Status: 🧪

## UC-PCMAT-03 · Reserva de outra venda tira do disponível; a da própria OS não · `must`

**Dado** saldo 10, reserva ativa de 5 desta OS, reserva ativa de 6 de outra venda e uma liberada de 50 **Então** o
disponível é 4,0000 e a falta 1,0000.

Status: 🧪

## UC-PCMAT-04 · Conferir devolve para Na fila quando o material chega · `must`

**Dado** a OS em Aguardando material **Quando** o operador sem o papel `producao.liberar` confere **Então** 403 ·
**Quando** o PCP confere ainda faltando **Então** fica onde está e a resposta traz a falta · com o saldo em 5
**Então** volta para Na fila, a lista vem vazia e a etapa começa; o saldo segue 5.

Status: 🧪

## UC-PCMAT-05 · Outra empresa, 404 · `must`

**Quando** o PCP da 98 lê ou confere o material de uma OS da 99 **Então** 404 e a OS da 99 não muda.

Status: 🧪

- **[BACKLOG]** Conferir sozinho quando a compra do insumo é recebida (hoje o PCP confere); mostrar a falta e o
  atalho para Compras na tela da OS; ligar a chave pela aba Fluxos da empresa.

---

## Previsto × realizado por OS (US-COMVIS-005 · UC-FLX-15)

> **Âncora:** UC-FLX-15 do catálogo de fluxos (*"Previsto × realizado por OS — congela o custo real e compara
> com o orçado e o aprovado"*, etapa Entregue) e US-COMVIS-005 (*"ver, ao fechar OS, comparativo: custo orçado
> vs custo real"*). Orçado = custo guardado na linha da venda no dia do orçamento (US-SELL-067); realizado =
> custo congelado ao concluir (US-PCP-021). Leitura para o gestor (`GET /producao/ordens/{id}/previsto-realizado`
> e `GET /producao/vendas/{id}/previsto-realizado`); a tela ainda não mostra. Prova:
> `ProducaoPrevistoRealizadoContratoTest`. Só leitura: nada do gravado muda.

## UC-PCPR-01 · OS concluída: orçado × congelado · `must` valor

**Dado** a linha de 4 × 25,00 com custo guardado 12,50 e a OS concluída com 62,00 congelado **Quando** o gestor
consulta **Então** vê orçado 50,00, realizado 62,00, diferença +12,00 (+24,00%), margem orçada 50,00% e real
38,00%; nada é gravado.

Status: 🧪

## UC-PCPR-02 · OS aberta mostra o parcial; cancelada, nada · `must` valor

**Dado** a OS em execução com 7,50 de material apontado (linha 2 × 30,00, custo guardado 10,00) **Então** vê
"em andamento", realizado 7,50, diferença −12,50 (−62,50%) e o congelado segue vazio; a OS cancelada não tem
realizado.

Status: 🧪

## UC-PCPR-03 · A venda soma as OS · `must` valor

**Dado** a venda com as duas OS acima e uma cancelada **Então** o total é receita 160,00, orçado 70,00,
realizado 69,50, diferença −0,50 (−0,71%), margem orçada 56,25% e real 56,56%, "em andamento" enquanto
alguma OS não concluiu; a cancelada fica fora.

Status: 🧪

## UC-PCPR-04 · Linha sem custo guardado · `must`

**Dado** a linha vendida sem custo guardado **Então** não há orçado, diferença nem margem orçada; a margem real
aparece, e a venda avisa `sem_orcado`.

Status: 🧪

## UC-PCPR-05 · Custo é do gestor · `must`

**Quando** o operador sem `producao.desempenho.ver` consulta a OS ou a venda **Então** 403; OS ou venda de outra
empresa **Então** 404.

Status: 🧪
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

## UC-PCLIB-08 · O retorno do banco grava o pagamento da venda · `must` valor

**Dado** uma venda de 1.000,00 com cobrança (`origem_type = sale`) e a config
`paymentgateway.lancar_pagamento_na_venda` ligada **Quando** o banco avisa que a cobrança foi paga com
30.000 centavos **Então** a venda ganha um pagamento de 300,00 (`transaction_no = PG-COB-<id>`), o status vai
para parcial e o sinal de 30% passa a constar como pago; o mesmo retorno de novo não duplica. Pago o total, a
venda fica paga. **E** se o valor não cabe no saldo em aberto (já registrado à mão), nada é lançado e fica no
log. Cobrança não paga ou que não é de venda é ignorada. Cobrança ou evento de outra empresa nunca paga a venda
da 98. O business dono do SaaS fica de fora. Desligada a config, nada muda.

Teste: `tests/Feature/PaymentGateway/RetornoDoBancoPagamentoNaVendaContratoTest.php`.

Status: 🧪

- **[BACKLOG]** UC-FLX-28 — parcelas de sinal e saldo geradas na aprovação do orçamento (Vendas/Financeiro);
  hoje o retorno do banco lança o pagamento na venda inteira (UC-PCLIB-08), sem parcela de sinal separada. O
  campo do sinal mínimo na aba Fluxos da empresa vem no #9701.

## Pedido de compra do cliente na OS (pedido de 2026-10-23)

- **[BACKLOG]** A OS mostra o número do pedido de compra do cliente — Dado uma venda com o pedido do
  cliente "4500012345", Quando as OS dos itens nascem, Então cada OS e a ficha impressa mostram "Pedido do
  cliente 4500012345", e a busca do apontamento acha a OS por esse número. Venda sem o número não mostra
  nada. Fonte: o número entra na venda (Sells/Create.casos.md, mesmo pedido) e vai para a NF-e no campo
  `xPed`.
  - Pedido: P-022
  - Âncora: NF-e (`xPed`) · caso 12
  - Fazer: Copiar o número da venda para a OS; mostrar na OS e na ficha impressa; incluir na busca.
  - Hoje: miss | Não tem | 
  - Comunicação visual: part | Cliente empresa | redes e construtoras
  - Clientes: Zoom, Techpress
  - Etiquetas: miss, cv

## Instrução de execução do serviço (pedido de 2026-10-23)

- **[BACKLOG]** A OS mostra a instrução escrita no item da venda — Dado um item vendido com a instrução
  "instalar no 2º andar, cliente libera o acesso às 8h", Quando a OS do item nasce, Então a instrução
  aparece no apontamento de cada etapa e na ficha impressa, em destaque. Item sem instrução não mostra
  nada. A instrução nasce na venda (Sells/Create.casos.md, mesmo pedido).
  - Pedido: P-058
  - Âncora: `Producao/Apontamento/Index.casos.md`
  - Fazer: Mostrar a instrução do item no apontamento e na ficha da OS.
  - Hoje: miss | Não tem | O apontamento não mostra observação do item
  - Comunicação visual: ok | Sim | instalação e acabamento
  - Clientes: Extreme, Zoom, Max Comunicação
  - Etiquetas: miss, cv

## Datas limite da arte, da qualidade e da entrega (pedido de 2026-10-23)

- **[BACKLOG]** A OS tem três datas: arte, qualidade e entrega final — Dado uma venda com entrega
  final em 30/10, Quando a OS nasce, Então ela traz a data limite para criar a arte (24/10), a data
  limite para ir ao setor de qualidade (28/10) e a entrega final (30/10); cada data aparece na etapa
  dela e na ficha impressa, e a etapa que passou da sua data fica marcada como atrasada no apontamento
  e no Painel. As duas primeiras podem ser mudadas na OS. Hoje a OS tem só o prazo prometido da venda
  (`pcp_ordens.prazo_prometido`, a entrega final) e o prazo calculado pela fila dos postos
  (Postos/Index.casos.md, UC-PCPRZ); não há data limite por etapa.
  A data prometida segue a decisão [W] de 2026-10-20 (fica na venda e vai para
  `pcp_ordens.prazo_prometido`), e o número de atrasadas continua um só, pela mesma função do Painel
  (UC-PCV-03): marcar a etapa atrasada não muda essa contagem sem decisão do [W].
  - Pedido: P-068
  - Âncora: Prazo prometido da OS (`pcp_ordens.prazo_prometido`) · prazo pela fila (UC-PCPRZ) · `Producao/Apontamento/Index.casos.md`
  - Fazer: Data limite por etapa na OS (arte e qualidade), além da entrega final, com o atraso de cada uma, sem mudar a contagem de atrasadas do Painel.
  - Hoje: part | Em parte | A OS tem só a entrega final vinda da venda e o prazo calculado pela fila
  - Comunicação visual: ok | Sim | arte, conferência e entrega
  - Clientes: Copylan
  - Etiquetas: part, cv

## Imagens e arte anexadas à OS (pedido de 2026-10-23)

- **[BACKLOG]** Anexar imagens e a arte à OS — Dado uma OS de fachada, Quando anexo a arte final e duas
  fotos do local, Então os arquivos ficam na OS, aparecem no apontamento de cada etapa e na ficha
  impressa (a arte em miniatura), e quem executa abre cada um. Hoje a OS guarda o arquivo da arte que
  passa pela aprovação do cliente (UC-PCART e UC-PCV-11), mas a tela do operador ainda não mostra essa
  arte e não há outros anexos.
  - Pedido: P-089
  - Âncora: Arte da aprovação (`UC-PCART`, `UC-PCV-11`) · `Producao/Apontamento/Index.casos.md`
  - Fazer: Anexos na OS e a arte vigente na tela do operador e na ficha.
  - Hoje: part | Em parte | A OS guarda a arte da aprovação, mas o operador não a vê e não há outros anexos
  - Comunicação visual: ok | Sim | arte e fotos do local
  - Clientes: Chico e Lu, Display Paraná, Plakart, Fixar
  - Etiquetas: part, cv

## Terceiro que executa o serviço da OS (pedido de 2026-10-23)

- **[BACKLOG]** Indicar o terceiro que vai executar o serviço `[V0]` — Segue a decisão D-TERCEIRO do
  [W] (`cowork-inbox/producao/playbook/_DECISOES-W-2026-10-15-celular.md`) e o UC-PCV-48: Dado uma OS
  com a instalação feita por um instalador de fora, Quando indico o terceiro e o valor combinado, Então
  ele recebe um link com código, sem conta no sistema, e o custo da OS passa a ser o valor combinado;
  Quando ele conclui o protocolo, Então nasce uma conta a pagar desse valor, e a OS mostra o terceiro à
  parte do custo próprio. A thread 19 da Produção (`playbook/19-terceiro.md`) é a dona da execução.
  Ficam com o [W], sem regra hoje: a comissão separada desse serviço e o pedido de compra ao terceiro
  gerado da OS (MRPeasy, Tekla). Mexe em valor: Regra Mestre. Verificado contra as regras do [W] em 2026-10-23.
  - Pedido: P-090
  - Âncora: Decisão D-TERCEIRO do Wagner · `UC-PCV-48` · thread 19 da Produção
  - Fazer: Seguir a thread 19 da Produção, que é a dona da execução.
  - Hoje: miss | Não tem | A OS não registra terceiro
  - Comunicação visual: ok | Sim | instalação terceirizada
  - Clientes: Gold
  - Etiquetas: valor, miss, cv, decisao

## Despesas da OS cobradas do cliente (comparação com concorrentes, 2026-10-23)

- **[BACKLOG]** Lançar despesas reembolsáveis na OS `[V0]` — Dado uma instalação fora da cidade, Quando
  lanço na OS o deslocamento e a hospedagem com o recibo anexado, Então as despesas aparecem na OS e
  entram no faturamento do cliente, separadas do serviço; despesa marcada como não reembolsável fica só
  no custo. Fonte: Omie (despesas reembolsáveis na OS, com anexos, faturadas ao cliente). Hoje a OS não
  tem despesas. Mexe em valor: Regra Mestre.
  Se a despesa entrar no total da venda, a regra A do [W] de 2026-10-21 (`Fiscal/Nfse.casos.md`)
  divide esse valor entre a NF-e e a NFS-e pelo bruto; como ela fica separada do serviço é decisão do
  [W].
  - Pedido: P-098
  - Âncora: Omie (despesas reembolsáveis na OS) · `Producao/Apontamento/Index.casos.md`
  - Fazer: Despesas na OS, com anexo e marca de reembolsável, levadas ao faturamento. Na nota, segue a regra A do Wagner (divide o acréscimo entre NF-e e NFS-e pelo bruto).
  - Hoje: miss | Não tem | A OS não tem despesas
  - Comunicação visual: ok | Sim | instalação fora da cidade
  - Etiquetas: valor, miss, cv

## Tarefas da OS com responsável e prazo (comparação com concorrentes, 2026-10-23)

- **[BACKLOG]** Tarefas paralelas da pré-produção — Dado uma OS que precisa de "separar as chapas" e
  "fechar a arte", Quando crio as duas tarefas com responsável e prazo, Então cada responsável recebe o
  aviso dentro do sistema (D-AVISO do [W]: WhatsApp só para o cliente), a OS mostra quantas tarefas
  faltam, e a tarefa vencida aparece como atrasada no Painel. Falta de material não vira tarefa: segue o
  fluxo do [W], em que o item "Em falta" abre requisição de compra e pausa a etapa da OS. A OS também
  guarda a data da produção e a combinada com o cliente (decisão [W] de 2026-10-20). Fonte: Printavo
  (tarefas com responsável e prazo). Hoje cada etapa grava o seu responsável, mas não há tarefas
  paralelas com prazo nem aviso. Verificado contra as regras do [W] em 2026-10-23.
  - Pedido: P-099
  - Âncora: Printavo (tarefas com aviso por e-mail; data de produção e do cliente) · `Producao/Apontamento/Index.casos.md`
  - Fazer: Tarefas paralelas na OS com responsável, prazo e aviso dentro do sistema (D-AVISO: WhatsApp só para o cliente), e as duas datas da OS.
  - Hoje: part | Em parte | Cada etapa grava o responsável; não há tarefas com prazo nem aviso
  - Comunicação visual: ok | Sim | pré-produção: compra, arte e separação
  - Etiquetas: part, cv

## Cliente desiste com o serviço já em produção (pedido da Copylan, 2026-10-28)

- **[BACKLOG]** Cancelar a OS que já começou e apurar o que foi gasto `[V0]` `[E0]` — Dado uma venda
  aprovada com sinal pago e a OS em execução, com parte do material já apontado, Quando o cliente
  desiste e o gerente cancela informando o motivo, Então a OS sai da fila e das máquinas, o material já
  consumido fica como perda da OS, o que ainda estava reservado volta ao estoque, e a venda mostra o que
  já foi gasto (material e horas apontadas) para decidir o que cobrar: reter o sinal, cobrar o gasto ou
  devolver o resto. A nota, se já saiu, segue a regra de cancelamento da nota. Cancelar a venda cancela
  as OS dela.
  **Depende do [W]:** no fluxo "OS e produção por item" dele (`prototipo-ui/cowork/Wagner/fluxos-modelos.casos.md`),
  a OS só é cancelada em Aguardando liberação, com nada consumido ou o consumo lançado como perda, e no
  fluxo "Orçamento e venda" a venda aprovada é cancelada estornando o sinal. Falta a regra para a OS que
  já está na fila, em execução, pausada ou em retrabalho, e para o que cobrar do cliente. A proposta é
  cobrar o que já foi gasto, retendo o sinal até esse valor e devolvendo o resto, com a política de
  cancelamento escrita no orçamento; a alternativa é o sinal ficar sempre.
  Hoje "Cancelar item" (`ProcessoDaOrdem::ACOES`, `SituacaoDaOrdem::cancelarItem`) só existe em
  Aguardando liberação e Aguardando material, e não pede motivo. Cancelar a venda
  (`CancelarVendaCascade`) libera a reserva de material da OS (`ProducaoServiceProvider`, UC-PCRES-06),
  mas a OS continua na fila. Não há apuração do gasto nem cobrança parcial.
  Fontes: Alterdata Wise PCP (no cancelamento da ordem iniciada, informa por insumo o que volta ao
  estoque e o que foi perdido); shopVOX, DecoNetwork, Cyrious e Omie cancelam a venda iniciada e
  reembolsam ou dão crédito, sem taxa de cancelamento documentada. Na lei, o serviço sob encomenda se
  parece com a empreitada (Código Civil, art. 623: quem desiste paga as despesas e o lucro do que já foi
  feito); a retenção do sinal segue o combinado (arts. 417 a 420), e o [E] confere antes de virar regra.
  Mexe em valor e estoque: Regra Mestre. Verificado contra as regras do [W] em 2026-10-28.
  - Pedido: P-113
  - Âncora: Fluxos: "OS e produção por item" e "Orçamento e venda" · `ProcessoDaOrdem` · `CancelarVendaCascade` · PR #10105
  - Fazer: "Cancelar item" também na fila, em execução, pausada e em retrabalho, com motivo e papel de gerente. Uma apuração do que foi gasto: consumido vira perda, reservado volta, horas apontadas viram custo. A venda cancelada cancela as OS dela. O que cobrar segue a política de cancelamento que o Wagner escolher.
  - Hoje: miss | Não tem | A OS só é cancelada em Aguardando liberação ou Aguardando material, e sem motivo. Cancelar a venda libera a reserva de material da OS, mas a OS continua na fila; não há apuração do gasto nem cobrança parcial
  - Comunicação visual: ok | Sim | desistência depois da arte aprovada
  - Clientes: Copylan
  - Etiquetas: valor, miss, cv, decisao
