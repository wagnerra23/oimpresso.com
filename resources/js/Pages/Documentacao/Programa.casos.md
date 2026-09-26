---
casos: Documentacao/Programa — a Trilha D como ela está de fato
irmaos: Programa.charter.md (lei) · memory/requisitos/Documentacao/ANTI-REGRESSAO-documentacao-blade.md (paridade)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-09-26"
---

# Casos de Uso & Aceite — Documentacao/Programa

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão. **Nenhum teste executa ainda** — só o stub `test.fixme` carimbado; a `.tsx` e
> as asserções nascem na thread 02. Até lá todos os UC ficam ⬜.
>
> **Fonte dos casos**, na ordem canônica: SPEC [`US-DOC-002`](../../../../memory/requisitos/Documentacao/SPEC.md)
> (escopo + acceptance criteria) · contrato de paridade `AR-DOC-060`–`AR-DOC-069` (comportamento da
> tela Blade viva, confirmado em `DocumentacaoController::programa`) · [ADR 0070](../../../../memory/decisions/0070-jira-style-task-management-current-md-removed.md)
> (estado mora nas tasks MCP). **Não** o `.tsx`, que é só esqueleto.
> **Persona:** o time ([W], [F], [M], [L], [E]) — quem precisa saber em que pé está o programa sem
> abrir cinco arquivos. Não é tela de cliente final.

---

## UC-PROGRA-01 · O estado vem do MCP, nunca do markdown
- **Persona:** [W] querendo saber o que já fechou no programa.
- **Aceite:** Dado tasks no MCP com `parent_plan=programa-ondas` · Quando a tela é montada · Então o
  estado de execução exibido é o dessas tasks, no **nível do plano** (contagem por `todo`/`doing`/`done`);
  e nenhum status (`doing`, "em execução") existe escrito no parser, no plano ou no `.tsx`.
- **Contrato:** SPEC `US-DOC-002` (AC "busca por `doing` nesses arquivos volta zero") · ADR 0070 ·
  `AR-DOC-068` (defeito da tela Blade) · `AR-DOC-069` (por que no nível do plano).
- **Regressão que defende:** o KPI da tela Blade de hoje — acha a onda por regex numa linha de tabela
  escrita à mão em 2026-08-05, enquanto o rodapé afirma que aquilo é estado vivo (`AR-DOC-068`).
- **Teste:** `e2e/documentacao-programa.spec.ts` — stub `test.fixme` citando `UC-PROGRA-01` (não executa; vira asserção na thread 02).
- **Status: ⬜**

## UC-PROGRA-02 · O plano é a fonte, e mudar o plano muda a tela
- **Persona:** [W] editando a § Trilha D do plano mestre.
- **Aceite:** Dado o plano alterado numa fixture — inclusive com o **título** de uma subseção reescrito
  e o código (`D.4`) mantido · Quando a tela é montada · Então o conteúdo muda **sem tocar PHP nem TSX**,
  a tela continua montando, e o que não está no plano volta vazio, sem campo inventado.
- **Contrato:** SPEC `US-DOC-002` (AC "plano alterado numa fixture muda o payload") · `AR-DOC-060` · `AR-DOC-062`.
- **Regressão que defende:** o parser completar lacuna com default, ou casar pelo título — a tela
  ficaria bonita afirmando o que o plano não diz.
- **Teste:** `e2e/documentacao-programa.spec.ts` — stub `test.fixme` citando `UC-PROGRA-02` (não executa; vira asserção na thread 02).
- **Status: ⬜**

## UC-PROGRA-03 · Sem MCP, a tela diz que não sabe
- **Persona:** qualquer pessoa abrindo a tela quando o MCP não responde.
- **Aceite:** Dado o MCP indisponível · Quando a tela é montada · Então a estrutura do plano aparece
  **sem estado**, com indicação explícita de indisponibilidade — nunca com status default.
- **Contrato:** SPEC `US-DOC-002` (escopo `EstadoDasOndas` com estado indisponível) · `AR-DOC-069`.
- **Regressão que defende:** ausência de medição virar afirmação de estado.
- **Teste:** `e2e/documentacao-programa.spec.ts` — stub `test.fixme` citando `UC-PROGRA-03` (não executa; vira asserção na thread 02).
- **Status: ⬜**

## UC-PROGRA-04 · A tela não muda nada
- **Persona:** qualquer pessoa autenticada.
- **Aceite:** Dado a tela renderizada · Quando se percorre a interface · Então não existe controle que
  dispare mutação — nada marca onda, DoD ou task; só navegação e o link para o plano no git.
- **Contrato:** SPEC `US-DOC-002` (escopo "`Programa.tsx` read-only") · ADR 0070.
- **Regressão que defende:** a tela virar um segundo lugar de escrever estado, competindo com as tasks MCP.
- **Teste:** `e2e/documentacao-programa.spec.ts` — stub `test.fixme` citando `UC-PROGRA-04` (não executa; vira asserção na thread 02).
- **Status: ⬜**

## UC-PROGRA-05 · Fonte ausente ou deformada falha dizendo o que falta
- **Persona:** [F] investigando por que a tela sumiu depois de um deploy.
- **Aceite:** Dado o plano ausente no deploy · Quando abre `/documentacao/programa` · Então recebe 503
  cuja mensagem **nomeia o arquivo**; e Dado uma das subseções `D.3`–`D.7` vazia · Então 503 **nomeando
  qual** — nunca página com seção vazia.
- **Contrato:** `AR-DOC-061` · `AR-DOC-063`.
- **Regressão que defende:** a falha honesta da Blade se perder na migração e virar "o programa não tem ondas".
- **Teste:** `e2e/documentacao-programa.spec.ts` — stub `test.fixme` citando `UC-PROGRA-05` (não executa; vira asserção na thread 02).
- **Status: ⬜**

## UC-PROGRA-06 · O payload não carrega tenant, host nem segredo · `[T0]`
- **Persona:** qualquer pessoa autenticada inspecionando a resposta.
- **Aceite:** Dado a tela montada · Quando se inspeciona o payload Inertia · Então não há `business_id`,
  host nem token — o conteúdo é global de governança.
- **Contrato:** SPEC `US-DOC-002` (AC "payload sem `business_id`, host ou token") · ADR 0093.
- **Regressão que defende:** documentação de infraestrutura é o lugar mais tentador pra colar um host com credencial.
- **Teste:** `e2e/documentacao-programa.spec.ts` — stub `test.fixme` citando `UC-PROGRA-06` (não executa; vira asserção na thread 02).
- **Status: ⬜**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** estado **por onda** (D0–D10). Não implementável hoje: a § D.3 não tem coluna de task e
  `parent_plan` é por plano, não por onda (`AR-DOC-069`). Falta a chave (`parent_wave` ou convenção de
  slug por onda) — decisão de [W], porque muda convenção de task.
- **[BACKLOG]** vista linkável na URL (`?vista=…`), proposta no rascunho do Cowork — sem fonte canônica ainda.

## Trilha do tempo
- 2026-09-25 · [C] thread 01 do playbook `programa-doc`: carimbado por `criar-tela.mjs` (PT-04) e UCs
  escritos a partir de `US-DOC-002` + `AR-DOC-060`–`069` + ADR 0070. `UC-PROGRA-01` escrito no nível do
  plano, como `AR-DOC-069` recomenda; o por-onda foi pro backlog. Refs: UI-0013 · ADR 0264 G-1/G-2.
