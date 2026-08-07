---
casos: Jana/Operacao — carimbado do Padrão de Tela
irmaos: Operacao.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-07-31"
---

# Casos de Uso & Aceite — Jana/Operacao

> Nascido de `criar-tela.mjs`. **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão. Por isso só **UC-OPERAC-01** tem id —
> é o único que o stub `e2e/jana-operacao.spec.ts` cita. Os demais ficam no Backlog como prosa até
> existir teste que os defenda ([ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-2).

> **Fonte do contrato:** as 5 perguntas formuladas pelo [W] em 2026-07-31 + a Mission do charter
> irmão. **Não** derivado do `.tsx` (stub) — derivar caso de código é tautológico e proibido
> ([proibicoes.md §5, 2026-06-05](../../../../memory/proibicoes.md)).

---

## UC-OPERAC-01 · Métrica sem medida não pode parecer saudável

- **Persona:** [W] — precisa distinguir *"medi e está zerado"* de *"nunca medi"*. Hoje as 4
  métricas da camada de IA (`coverage_pct`, `ragas_real_uptime`, `recall_eval_violations`,
  `backfill_error_rate`) estão em `not_yet_measured`, e qualquer tela que as renderize como `0`
  faria o buraco parecer sucesso.
- **Aceite:**
  **Dado** que uma métrica está com `status: not_yet_measured` no `sdd-scorecard-baseline.json`,
  **Quando** o [W] abre `/ia/operacao`,
  **Então** aquela linha exibe o rótulo **`sem medida`** em slate, **não** exibe o número `0`, traz
  a ação pendente com o responsável, e **nunca** é contada como verde em nenhum agregado da tela.
- **Teste:** `e2e/jana-operacao.spec.ts` — hoje `test.fixme` citando `UC-OPERAC-01`. Troca por
  asserção real: fixture com métrica `not_yet_measured` → a célula contém `sem medida` e não
  contém `0`; a contagem de "defendidas" no topo não a inclui.
- **Regressão que defende:** o buraco de medição virar zero silencioso. É a diferença entre *"a IA
  está bem"* e *"eu não sei se a IA está bem"* — e essa confusão é a origem do pedido que criou
  esta tela.
- **Status: ⬜** — stub; vira 🧪/✅ quando o teste executar e passar.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Série constante há N execuções é sinalizada como suspeita de instrumento morto,
  não como estabilidade. Precedente: `jana:drift-sentinel` deu 1.0 em 51 de 51 perguntas porque
  media auto-consistência, não a IA.
- **[BACKLOG]** Classe de erro reincidente sem defesa mecânica aparece como ação, com o número de
  ocorrências, lida do `LICOES_CODE.md`.
- **[BACKLOG]** Taxa de conversão do aprendizado: quantas classes viraram defesa mecânica e o
  tempo entre a 2ª ocorrência e a defesa.
- **[BACKLOG]** Bus factor da camada: quantas pessoas distintas tocaram cada área em 180d. Medido
  em 2026-07-31 — 96,7% dos commits do repo numa identidade só.
- **[BACKLOG]** Custo por PR entra como relato com data, nunca como gate — e mostra explicitamente
  a fatia não-atribuível a PR nenhum, em vez de escondê-la.
- **[BACKLOG]** Nenhum índice único de "saúde da IA": agregado de recall + custo + reincidência
  aponta pro lado errado.
- **[BACKLOG]** Projeção como "N dias no ritmo atual", nunca como probabilidade calibrada.
- **[BACKLOG]** Toda célula derivada traz a data da medição ao lado e o link pra re-rodar a fonte.
- **[BACKLOG]** Cross-tenant: `business_id` scopado, biz=1 não enxerga biz=99.
- **[BACKLOG]** Nenhuma prop editável — prova o Non-Goal "não guarda estado próprio".

## Trilha do tempo

- 2026-07-31 · [CC] UC-OPERAC-01 escrito das 5 perguntas do [W]; demais casos em Backlog sem id
  pra não nascerem órfãos (G-2). Tela renomeada de `Responsabilidades` → `Operacao` (camada
  Operacional da pilha de governança). Refs: ADR 0264 · UI-0013.
