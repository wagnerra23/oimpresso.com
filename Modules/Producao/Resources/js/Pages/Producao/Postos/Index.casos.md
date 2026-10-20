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

## UC-PCALO-05 · Operador não aloca; outra empresa não existe · `must`

**Quando** um operador sem permissão de mover etapa tenta alocar ou ver a fila **Então** recebe 403 **E** etapa
ou posto de outra empresa respondem 404, sem mudar nada.

Status: 🧪

## UC-PCALO-06 · Fila do posto pela ordem do início previsto · `must`

**Dado** no posto uma etapa iniciada (dia 2), uma pendente (dia 3), uma pendente sem responsável (dia 5) e uma
concluída (dia 1) **Quando** o gestor abre a fila **Então** vêm as três abertas nessa ordem, com o responsável
de cada uma; a concluída não aparece.

Status: 🧪

---

## Backlog de casos (sem id — a E03 não traz UC próprio; o teste já os defende)

- **[BACKLOG]** Veículo é posto de máquina com placa obrigatória e custo por km; ferramenta tem QR único por empresa.

## Trilha do tempo
- 2026-10-16 · [CL] thread 03 + E03 do playbook de Produção — trio nasce junto (charter + casos + teste).
- 2026-10-20 · [CL] UC-PCCH-01/02 documentam o custo-hora que já existe (UC-FLX-20 parcial); o bullet de backlog do custo-hora da pessoa virou o UC-PCCH-02; rateio e ponto de equilíbrio ficam no [BACKLOG] (US-FIN-070).
- 2026-10-20 · [CL] UC-PCALO-01..06 — alocador decide quem e quando começa (US-PCP-029 · UC-FLX-10): endpoints JSON de alocação e fila do posto; a tela é a US-PCP-015.
