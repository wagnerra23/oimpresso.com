---
id: modules-producao-pages-producao-painel-index-casos
casos: Produção · Painel · /producao/painel
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do gestor + critério de aceite verificável (Dado/Quando/Então)
por_que: indicador que diverge do quadro engana o PCP; alerta decorativo ensina a ignorar alerta; evento de outra empresa no feed é vazamento Tier 0.
owner: wagner
last_run: "2026-10-27"
last_run_ci: "_pendente_ — o veredito entra quando a lane MySQL rodar o ProducaoQuadroContratoTest."
---

# Casos de Uso & Aceite — Produção · Painel (`/producao/painel`)

> **Âncora:** `prototipo-ui/cowork/Wagner/cowork-inbox/producao/Producao.casos.md` (UC-PCV-01..04, 29).
> Os UCs não derivam do `.tsx`. Teste: `Modules/Producao/Tests/Feature/ProducaoQuadroContratoTest.php`
> (o mesmo da thread 06 inteira).

## UC-PCV-01 · Vejo o que trava a produção hoje e ajo dali · `must`

**Dado** uma OS atrasada, uma arte esperando o cliente e uma máquina com mais horas programadas hoje do que
o dia dela **Quando** abro o Painel **Então** vejo os 4 indicadores e um alerta por problema, cada um com uma
ação (abrir o Quadro filtrado ou agrupado por máquina) · Dia limpo devolve nenhum alerta · A carga de uma
empresa não aparece na outra · Ver o Painel pede `producao.access`.

Status: 🧪

## UC-PCV-02 · Os indicadores levam ao quadro já filtrado · `should`

**Quando** clico em "Atrasadas ou vencendo" ou "Arte travando" **Então** abre o Quadro com esse filtro ligado
(`?so=atrasadas`, `?so=arte`) · Valor fora da lista volta a "todas". "Refugo" ainda não leva ao Custo (thread 08).

Status: 🧪

## UC-PCV-03 · Um número só para atrasadas · `must`

**Dado** uma OS pronta com prazo vencido, uma em andamento vencida e uma vencendo hoje **Então** o indicador do
Painel e o filtro do Quadro mostram as mesmas 2 · A OS pronta não conta. Regra: prazo ≤ hoje e etapa aberta, em
uma função só no servidor (`QuadroDaProducao::cartoes`), que as duas telas leem.

Status: 🧪

## UC-PCV-04 · Fila de aprovação de arte · `must`

**Dado** OS com arte "aguardando" e "ajuste pedido" **Então** a fila mostra o que o cliente disse e quando, com o
ajuste primeiro · Vale o pedido mais recente da OS: recusada e depois aprovada não trava · O cartão do Quadro
leva a mesma situação. Reenviar o link e "Enviar nova prova" vieram depois (UC-PCART-07).

Status: 🧪

## UC-PCART-07 · Reenviar o link ou enviar a nova prova pela fila · `must`

> Âncora: `Painel` de `prototipo-ui/cowork/Wagner/producao-cv-page.jsx` (botão "Enviar nova prova" no ajuste,
> com o campo do link do arquivo novo, e "Reenviar link" no aguardando; linha de resultado depois do envio). Liberado por [W] em 2026-10-27. Prova: `ProducaoAprovacaoArteContratoTest`.

**Dado** uma arte aguardando o cliente **Quando** clico em "Reenviar link" **Então** sai um link novo para a mesma
prova e o anterior deixa de valer (410) · **Dado** um ajuste pedido **Quando** envio a nova prova com o link do
arquivo **Então** sai um link novo com o arquivo novo e a OS volta a "aguardando"; sem arquivo novo, ou com o
mesmo, é recusado (422) e nada muda · Arte aprovada não reenvia (o caminho é trocar o arquivo) · OS de outra
empresa: 404. Com o WhatsApp ligado o cliente recebe o link; senão o link aparece para mandar à mão.

Status: 🧪

## UC-PCV-29 · Uma fonte de eventos para todas as telas · `must`

**Dado** eventos gravados pelo apontamento, pelo alocador e pela aprovação de arte **Então** "Acontecendo agora"
mostra os mais recentes da empresa, com a etapa, quem fez e a OS · Evento sobre uma pessoa (apontado × presença)
não entra · Evento da empresa 99 nunca aparece para a 98.

Status: 🧪

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Chego no Painel pelo item Produção do menu, sem digitar URL (`DataController::modifyAdminMenu` aponta para `/producao/painel`).
- **[BACKLOG]** O Painel cabe em 1280px sem scroll horizontal.
