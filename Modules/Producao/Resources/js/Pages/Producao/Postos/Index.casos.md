---
id: modules-producao-pages-producao-postos-index-casos
casos: Produção · Postos e cadastros · /producao/postos
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do gestor + critério de aceite verificável (Dado/Quando/Então)
por_que: o celular só oferece os motivos do posto; motivo de outro posto ou de outra empresa corrompe o registro de pausa e refugo. Custo-hora da pessoa é dado de desempenho.
owner: wagner
last_run: "2026-10-20"
last_run_ci: "_pendente_ — o trio nasce na thread 03. O veredito entra quando a lane verticais-pest rodar o ProducaoRoteirosContratoTest."
---

# Casos de Uso & Aceite — Produção · Postos e cadastros (`/producao/postos`)

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

- **[BACKLOG]** UC-FLX-20 — fechamento do mês que rateia as despesas fixas por centro de custo (base: horas apontadas), propõe o custo-hora novo do posto com antes→depois e calcula o ponto de equilíbrio. Nada disso existe: não há classificação fixa/variável no plano de contas nem centro de custo (US-FIN-070).

---

## Prazo de entrega calculado pela fila (US-PCP-028 · UC-FLX-06)

> **Âncora:** UC-FLX-06 do catálogo de fluxos (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php:59` — *"Calcula a
> data de entrega pela capacidade livre de cada etapa"*, etapa Aprovado da venda, situação `planejado`, "SPEC do
> PCP") e a [ADR 0436](../../../../../../../memory/decisions/0436-os-por-item-producao-dona-da-os-do-pedido.md)
> (o roteiro anda nas programações do PCP; "prazo pela fila" ficou fora da fase 2). O caso mora aqui porque a
> fila e as horas por dia são do posto. Nesta entrega é o cálculo em JSON (`GET /producao/vendas/{id}/prazo`),
> só leitura; a tela ainda não mostra. Prova: `ProducaoPrazoPelaFilaContratoTest`.

## UC-PCPRZ-01 · A data soma a fila de cada posto e as etapas do roteiro · `must`

**Dado** a impressora (8 h/dia) com 16 h abertas na fila, o acabamento (10 h/dia) com 4 h iniciadas, o corte sem
horas por dia e o roteiro Impressão 4 h → [Corte 8 h ‖ Acabamento 2 h] → Conferência 4 h sem posto **Quando** o
orçamento de 1 banner pede o prazo em 20/10 **Então** Impressão 2,0→2,5 dias, Corte 2,5→3,5 (8 h/dia por padrão),
Acabamento 2,5→2,7, Conferência 3,5→4,0 e a data prevista é 24/10 — a etapa opcional, a programação concluída e a
de outra empresa no mesmo posto não contam, e as premissas dizem o padrão usado.

Status: 🧪

## UC-PCPRZ-02 · Itens da mesma venda disputam os postos · `must`

**Dado** o mesmo orçamento com 2 banners e uma instalação sem roteiro **Então** o 2º banner espera o 1º em cada
posto (Impressão 2,5→3,0, Corte 3,5→4,5, Acabamento 3,0→3,2, fim 5,0), a data vai para 25/10 e a instalação fica
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

- **[BACKLOG]** UC-FLX-06 — mostrar o prazo na aprovação do orçamento e gravar a data prometida por ação do vendedor (hoje nada grava); descontar fim de semana e o turno da empresa; descontar o já apontado da etapa iniciada.

---

## Backlog de casos (sem id — a E03 não traz UC próprio; o teste já os defende)

- **[BACKLOG]** Veículo é posto de máquina com placa obrigatória e custo por km; ferramenta tem QR único por empresa.

## Trilha do tempo
- 2026-10-16 · [CL] thread 03 + E03 do playbook de Produção — trio nasce junto (charter + casos + teste).
- 2026-10-20 · [CL] UC-PCCH-01/02 documentam o custo-hora que já existe (UC-FLX-20 parcial); o bullet de backlog do custo-hora da pessoa virou o UC-PCCH-02; rateio e ponto de equilíbrio ficam no [BACKLOG] (US-FIN-070).
- 2026-10-20 · [CL] UC-PCPRZ-01..04 — prazo de entrega pela fila dos postos (US-PCP-028 · UC-FLX-06), cálculo em JSON só leitura; tela e data prometida ficam no [BACKLOG].
