---
date: "2026-08-16"
hour: "10:54 BRT"
duration: "2h"
topic: "Pergunta '/o plano mestre trilha D foi concluído?' virou o ciclo §D.4 inteiro sobre a D0 — medir, documentar no dono, validar pelo consumidor, publicar"
authors: [W, C]
outcomes:
  - "Placar da Trilha D passa a declarar 2/5 AC fechados + 3 parciais com resíduo nomeado, em vez de um 'em execução' mudo"
  - "Matriz da D0 sai de 1 eixo em 32,7% para 4 eixos derivados; owner e evidência-em-workflows RECUSADOS com medição, não preenchidos com ruído"
  - "US-GOV-059 reconciliada (status-truth invertido: corpo provava triagem completa, cabeçalho dizia todo)"
  - "Caso de teste novo converte em máquina a conferência manual da célula de status que o DocumentacaoController lê"
prs: [5833]
us:  ["US-INFRA-048", "US-GOV-059"]
related_adrs: ["0294-plano-status-vivo", "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0273-ancora-implementado-em"]
---

# Session log 2026-08-16 — a célula de status que era prosa e é máquina

## TL;DR

[W] perguntou se a Trilha D estava concluída. Não estava: 11 ondas, só a D0 tocada. Autorizou
o ciclo completo — que rodou sobre **uma** unidade (fechar a D0), como o §D.4 manda. O resultado
honesto é que **a D0 não fecha**: 2 dos 5 acceptance criteria estão fechados, e a primeira das
três partes do gate dela (*"plano ligado ao MCP"*) segue travada por credencial ausente desde
05/08 — não é mecanizável por agente. O que dava pra pagar foi pago; o que não dava está nomeado.

## Contexto

Pergunta simples, resposta que exigiu medição: o registro dizia `🟡 D0 em execução` sem dizer o
que faltava, e havia suspeita de drift entre o plano e o trabalho real de documentação das
últimas semanas.

## Cronologia

| Quando | Evento |
|---|---|
| 09:20 | Medição inicial: plano `ativo`, `plan-health` não o acusa, kill-condition longe, 11 máquinas que ele reusa presentes |
| 09:50 | 3 threads read-only em paralelo (AC da D0 · visão humana · drift plano×execução) |
| 10:05 | Thread da visão humana **corrige o briefing que eu dei a ela** — a regex do handoff de ontem é fóssil; os `####` já recebem âncora |
| 10:10 | Descoberto que a célula de status do plano é **entrada de `execucaoDaTrilha()`**, não prosa |
| 10:25 | Thread dos AC derruba minha premissa: 3 de 5 estão parciais — "fechar com a evidência que já existe" era falso |
| 10:30 | Edição do placar + US; `anchor-lint` **reprova** — a US passou a se declarar implementada sem teste que a cubra |
| 10:40 | Pago com teste real (não com recuo da afirmação); descoberto que o gate lê só testes citados em `**Testado em:**` |
| 10:45 | Thread da matriz entrega 4 eixos e **recusa 2** com medição |
| 10:52 | PR #5833 mergeado por [W] — 119 checks, 117 success + 2 skipped, zero falhas |

## Entregas

- **PR #5833** — reconcilia o placar da Trilha D com o estado medido da D0 → **merged** (`dd41a047`)
- `scripts/governance/maquinas-inventario.mjs` — 330 → 609 ln (3 eixos novos derivados)
- `memory/reference/MAQUINAS-INVENTARIO.md` — regenerado pelo script, 587 ln, `--check` verde
- `tests/Feature/DocumentacaoRouteTest.php` — +47 ln, caso que trava o contrato da célula de status
- `memory/requisitos/Infra/SPEC.md` — US-INFRA-048 com os 5 AC medidos + tabela do resíduo
- `memory/requisitos/Governance/SPEC.md` — US-GOV-059 status-truth reconciliado

## Decisões cinzentas resolvidas

| Pergunta | Decisão | Justificativa |
|---|---|---|
| O placar deve dizer "D0 fechada"? | Não — `em execução` com o resíduo explícito | O consumidor lê `D<n> em execução`; dizer "fechada" ou blanquear a linha apaga os cartões da página. E seria falso |
| Preencher o eixo `owner` com o handle único do CODEOWNERS? | **Não** | 123/474 e todas no mesmo handle: valor único em 26%, vazio em 74% — os dois critérios de ruído juntos. §D.2 do plano proíbe inventário editado à mão |
| Marcar `evidência` em workflows pelo proxy do script? | **Não** | O proxy prova que o *script* morde, não o *workflow*. Marcar seria medir outra propriedade |
| Escrever o session log ausente da D0 (06/08)? | **Não** | Seria fabricar registro de sessão alheia. Fica declarado como lacuna na US |

## Aprendizados / pegadinhas

- **Doc que a máquina lê é código com cara de doc, e o caso aqui é literal:** a célula de status
  do Plano Mestre alimenta `DocumentacaoController::execucaoDaTrilha()`. Reescrevê-la sem rodar o
  consumidor faz os cartões de `/documentacao/programa` sumirem **em silêncio** — a página segue
  200, porque o controller devolve `null` de propósito ("melhor um vazio honesto que um D0
  fossilizado"). Validar mexida ali é rodar o consumidor, nunca reler o texto.
- **Trocar `_pendente_` por âncora real acorda o gate de entrada.** A US passa a *se declarar
  implementada*, e o `anchor-lint` cobra `@covers-us`. A saída barata (voltar pra `_pendente_`) é
  falsa ao contrário. A saída certa é o teste — e aqui ele valia por si, porque trava uma falha
  demonstrada.
- **O gate `--check-covers` não varre `tests/`** — ele lê só os testes citados numa linha
  `**Testado em:**` da própria US. Eu presumi varredura global e fiquei 2 rodadas no vermelho.
- **Relatório de agente que contradiz sua medição é hipótese a testar, não erro dele.** A thread
  da visão humana provou, com a linha do código colada, que a regex que eu passei no briefing era
  um fóssil do handoff do dia anterior. Ela estava certa.
- **Denominador importa mais que o percentual.** Publiquei cobertura sobre 450 de 474 linhas
  (meu parser não casava 24) rotulando como "piso"; a thread mediu contra as 474 e contra as
  fontes. Troquei os meus números pelos dela.
- **`EXIT=0` depois de um pipe é do último comando.** Um `php … | tail` devolveu 0 com fatal
  error no PHP. Quando a decisão é do primeiro comando, não canalize.
- **A recusa medida é entrega.** Dois eixos não entraram, e o valor está na razão registrada +
  condição de reabertura nomeada — não numa coluna decorativa que ninguém confiaria.

## Próximos passos (não-bloqueante)

- [ ] **[W]** colar a credencial MCP em `.claude/settings.local.json` — destrava a 1ª das 3 partes do gate da D0 e a materialização da task com `parent_plan=programa-ondas`
- [ ] **[W]** decidir qual onda entra depois da D0 (D1 infraestrutura crítica é a ordem declarada no §D.3)
- [ ] Smoke autenticado em `/documentacao/programa` pós-deploy, conferindo que os cartões seguem em D0
- [ ] Levar o eixo `Invocador` a skills/agents **só se** surgir sinal derivável — hoje as ocorrências são menção, não invocação

## Referências

- Handoff: [2026-08-16-1054-trilha-d-d0-placar-medido.md](../handoffs/2026-08-16-1054-trilha-d-d0-placar-medido.md)
- Plano: [PLANO-MESTRE § Trilha D](../requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md)
- US: [US-INFRA-048](../requisitos/Infra/SPEC.md) · [US-GOV-059](../requisitos/Governance/SPEC.md)
