---
casos: Documentacao/Programa — a Trilha D como ela está de fato
irmaos: Programa.charter.md (lei) · memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md (fonte)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-08-06"
---

# Casos de Uso & Aceite — Documentacao/Programa

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> **Fonte dos casos:** a § Trilha D do plano mestre + [ADR 0070](../../../../memory/decisions/0070-jira-style-task-management-current-md-removed.md)
> (estado de execução mora nas tasks MCP), **não** o `.tsx`.
> **Persona:** o time — quem precisa saber em que pé está o programa sem abrir cinco arquivos.

---

## UC-PROGRA-01 · O estado vem do MCP, nunca do markdown
- **Persona:** [W] querendo saber quais ondas estão em execução.
- **Aceite:** Dado uma task `done` no MCP com `parent_plan=programa-ondas` · Quando a tela é montada · Então aquela onda aparece como `done`; e nenhum status (`doing`, "em execução") existe escrito no parser, no plano ou no `.tsx`.
- **Teste:** `e2e/documentacao-programa.spec.ts` — `UC-PROGRA-01`.
- **Regressão que defende:** alguém chumbar status no markdown pra "adiantar" — o extrato da semana passada apresentado como saldo atual (ADR 0070; anti-hook do charter).
- **Status: ⬜**

## UC-PROGRA-02 · O plano é a fonte, e mudar o plano muda a tela
- **Persona:** [W] editando a § Trilha D do plano mestre.
- **Aceite:** Dado o plano alterado numa fixture · Quando a tela é montada · Então o conteúdo apresentado muda **sem tocar em PHP nem em TSX**; e o que não está no plano volta vazio, sem campo inventado.
- **Teste:** `e2e/documentacao-programa.spec.ts` — `UC-PROGRA-02`.
- **Regressão que defende:** o parser passar a completar lacuna com valor default — a tela ficaria bonita afirmando o que o plano não diz.
- **Status: ⬜**

## UC-PROGRA-03 · Sem MCP, a tela diz que não sabe
- **Persona:** qualquer pessoa abrindo a tela quando o MCP não responde.
- **Aceite:** Dado o MCP indisponível · Quando a tela é montada · Então as ondas aparecem **sem estado**, com indicação explícita de indisponibilidade — nunca com um status default.
- **Teste:** `e2e/documentacao-programa.spec.ts` — `UC-PROGRA-03`.
- **Regressão que defende:** ausência de medição virar afirmação de estado, que é como um painel passa meses mentindo em silêncio.
- **Status: ⬜**

## UC-PROGRA-04 · A tela não muda nada
- **Persona:** qualquer pessoa autenticada.
- **Aceite:** Dado a tela renderizada · Quando se inspeciona a interface · Então não existe controle que dispare mutação — nenhum caminho para marcar onda, DoD ou task pela UI; só navegação.
- **Teste:** `e2e/documentacao-programa.spec.ts` — `UC-PROGRA-04`.
- **Regressão que defende:** a tela virar um segundo lugar de escrever estado, competindo com as tasks MCP (Non-Goal read-only do charter).
- **Status: ⬜**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** payload não carrega `business_id`, host nem token.
- **[BACKLOG]** o parser falha alto quando a § Trilha D muda de forma, em vez de devolver estrutura parcial silenciosa.

## Trilha do tempo
- 2026-08-06 · [CC] UCs reais escritos a partir da § Trilha D e da ADR 0070; cobrem os cinco UC-PROGDOC do pedido de [W]. Refs: UI-0013 · ADR 0264 G-1/G-2.
- 2026-07-11 · [CC] carimbado por criar-tela.mjs — trio nascido junto (charter + casos + teste).
