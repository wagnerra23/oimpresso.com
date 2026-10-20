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

---

## Backlog de casos (sem id — a E03 não traz UC próprio; o teste já os defende)

- **[BACKLOG]** Veículo é posto de máquina com placa obrigatória e custo por km; ferramenta tem QR único por empresa.

## Trilha do tempo
- 2026-10-16 · [CL] thread 03 + E03 do playbook de Produção — trio nasce junto (charter + casos + teste).
- 2026-10-20 · [CL] UC-PCCH-01/02 documentam o custo-hora que já existe (UC-FLX-20 parcial); o bullet de backlog do custo-hora da pessoa virou o UC-PCCH-02; rateio e ponto de equilíbrio ficam no [BACKLOG] (US-FIN-070).
- 2026-10-20 · [CL] UC-PCOPE-01..05 — operadores por posto (US-PCP-030 · UC-FLX-08, aprovado por [W] em 2026-10-20).
