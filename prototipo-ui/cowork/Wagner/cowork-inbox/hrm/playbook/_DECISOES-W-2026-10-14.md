# _DECISOES-W-2026-10-14 — Hrm

> **Fonte:** [W] 2026-10-14, no chat da sessão do playbook hrm (perguntas com opção recomendada; [W] escolheu as duas recomendadas).
> **Base medida:** `origin/main` 6511ed7c34. `node scripts/qa/placar.mjs --indice …/hrm/playbook/00-INDICE.md` → `entregue 9 de 12 · bloqueada 3` (05, 10, 11).

| id | pergunta | resposta | thread |
|---|---|---|---|
| D-FOLHA-PASSO2 | Abro já o próximo passo do §9 da ADR da folha, ou espera o Ponto ter marcação em produção? | *Abrir já* | 10 |
| D-NAVHRM | A thread 11 fecha com o `nav_hrm` como resíduo da thread 10, ou fica bloqueada até a Folha ter Page? | *Fechar com resíduo* | 11 · 10 |

## Correção de premissa (D-FOLHA-PASSO2)
O `DECISOES-W-PENDENTES-2026-10-07.md` §3.2 chama o recálculo server-side de "passo 2". Ele é o
**passo 1** do §9 da ADR-mãe (`memory/decisions/proposals/2026-09-05-folha-com-encargos-modelo-e-fronteira.md`,
`status: aceito`) e já está feito no #6880 (`PayrollTotalCalculator`). O passo aberto agora é o **2: casos-verdade
externos no repo**, com fonte e competência declaradas em cada fixture e nenhum motor ainda (§5.1).
Ele não precisa da hora do Ponto.

## Efeito
- **Folha (10):** o passo 2 do §9 abre fora deste playbook, em PR próprio. Continua sem Page. A regra mestre de
  valor (`memory/proibicoes.md`) vale desde o primeiro PR: casos-verdade só de fonte externa (holerite conferido
  por contador, calculadora oficial, tabela publicada); resultado do nosso código é fonte inadmissível.
- **Topnav (11):** feita com 2 de 3 provas (`sidebar_hrm` e `hrm_dashboard` ausentes, `_saida-11`). A remoção do
  `nav_hrm.blade.php` passa a ser resíduo da thread 10: ele sai junto com as 6 blades de `payroll/*` quando a Folha
  tiver Page, e Departamentos/Cargos ganham `navbar` próprio no mesmo movimento (`_saida-11b`, consumidores medidos).

## Pedido ao Cowork (o índice é do Cowork; esta sessão não o edita)
1. **Thread 11:** trocar a prova `{ "tipo": "ausente", "path": "Modules/Essentials/Resources/views/layouts/nav_hrm.blade.php" }`
   por um registro de resíduo transferido para a thread 10 (D-NAVHRM), para o placar contar a 11 como feita.
2. **Thread 10:** acrescentar a prova de ausência do `nav_hrm.blade.php` às condições da 10, e trocar o bloqueio por
   "ADR ratificada (#7920); passo 2 do §9 aberto (D-FOLHA-PASSO2); sem Page até o modelo de verba e o motor".
3. **Thread 05:** a 05 foi cancelada por D4 (escala é do Ponto, thread 12 feita). Marcar como `cancelada` em vez de
   `bloqueada`, ou retirá-la do denominador, para o placar não a contar como pendente do [W].
4. **§1:** remover a frase de que os planos da emenda [W] "não existiam" (já pedido no `_saida-10`).

Placar esperado depois do índice reescrito: **10 de 11** (05 fora do denominador) ou **10 de 12** (05 contada como cancelada).
