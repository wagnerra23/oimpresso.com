---
date: "2026-10-06"
time: "07:27 BRT"
slug: handoffs-cowork-45-e-projeto-threads-paralelas
tldr: "Dois imports do Cowork (handoff 45 no #8642, export de projeto de 06/10 no #8746), cerca de 30 threads de playbook despachadas em sessões paralelas, ADR 0427 (auto-merge de PR de thread verde) e eixo 3 do test-lane-coverage (--pr: o teste tocado executou no head?) depois de um 500 em produção que passou com tudo verde."
decided_by: [W]
prs: [8642, 8666, 8682, 8703, 8746]
related_adrs: [0427-auto-merge-de-pr-de-thread-emenda-0040, 0040-policy-publicacao-claude-supervisiona, 0409-zero-baseline-de-tolerancia-conformidade-absoluta]
next_steps:
  - "Acompanhar as 9 sessões abertas em 06/10 (Essenciais 01/02, Estoque 01, Crm 09, Produto 04, Officeimpresso 09, Placar A-LOTE + Superadmin A2, Tema escuro 01, Telas soltas A1)."
  - "Officeimpresso 09 liga flag em produção: merge do [W] com plano de canário."
  - "Threads [CC] dependem do Cowork: os PUXAR de Sistema, Essenciais, Estoque, Recorrente e Produto, Telas soltas 02 e Crm 05."
  - "Regerar o doc-id-index do main (3 handoffs de 05/10 + RUNBOOK-grupos fora do índice; achado do refutador do #8746)."
---

# Handoffs Cowork 45 e export de 06/10 · threads em paralelo

## Estado MCP no momento
- `cycles-active`: nenhum cycle ativo em COPI.
- `my-work` (@wr23): sem tasks ativas.
- Repositório: nenhum PR aberto ao fechar; todos os PRs da rodada mergeados.

## O que aconteceu
1. **Handoff 45** importado pelo `receber-handoff` (#8642): o Cowork renumerou as fichas dos playbooks; baselines e maps regerados; GT-G5 0/283. Uma cópia antiga do protótipo Mobile veio junto e quebrava o `cowork-pele-paralela` em todo PR; tirada do espelho com autorização [W] (#8666, `.gitignore`).
2. **Threads em paralelo:** 7 agentes e depois cerca de 25 chips de sessão. Connector 09/10 e Officeimpresso 03 já estavam feitas: só recibo. Recibos sobem ao Cowork pela rota isenta depois do merge.
3. **ADR 0427** (#8682): PR de thread verde entra por auto-merge, com exceções para o [W] (valor, estoque, migration destrutiva, cutover e baseline visual).
4. **Incidente:** o #8669 (Minha assinatura) entrou com tudo verde, mas o teste dele nunca rodou no head: lane advisory sem `synchronize`. `/subscription` deu 500 em produção. Hotfix #8700 (moduleUtil) mais uma sessão para o `System::getCurrency`.
5. **Conserto da brecha** (#8703): `test-lane-coverage --pr <N>` lê o passo do Pest dos jobs no head. Medido nos 10 PRs da rodada sem falso positivo; 6 não tinham rodado o teste. A condição 3 da publication-policy passou a exigir exit 0 antes de ligar o auto-merge.
6. **Export de projeto de 06/10** (feito pela sessão da fila de merges) importado no #8746: 47 arquivos de playbook que só estavam no Cowork, com 6 módulos novos (sistema, essenciais, estoque, recorrente, telas-soltas, tema-escuro). GT-G5 0/191. 9 chips abertos.
7. **Cronograma** do protótipo inteiro em produção publicado como artifact (privado): https://claude.ai/artifact/ERMXsn7reJgX7DzFxfVsJo

## Artefatos gerados
- `memory/decisions/0427-auto-merge-de-pr-de-thread-emenda-0040.md` + `.claude/skills/publication-policy/SKILL.md` §Auto-merge de thread.
- `scripts/governance/test-lane-coverage.mjs`: `--pr` (eixo 3), com 7 asserts novos e selftest 44/44.
- `memory/sessions/2026-10-05-refutacao-gt-g5-lote-8642-r1.md`, `memory/sessions/2026-10-06-refutacao-gt-g5-lote-8746-r1.md`.

## Persistência
Git: PRs mergeados acima. Cowork: 6 recibos subidos ao projeto w e registrados em `scripts/design-sync/state/enviados-cowork.json`. MCP: sem task (rodada guiada por playbook).

## Próximos passos pra retomar
`node scripts/qa/placar.mjs --indice prototipo-ui/cowork/Wagner/cowork-inbox/<mod>/playbook/00-INDICE.md --proximo` em cada módulo; antes de ligar auto-merge, `node scripts/governance/test-lane-coverage.mjs --pr <N>`.

## Lições catalogadas
- Verde de check não é execução: lane com paths-filter sai `success` com o passo do Pest pulado, e lane advisory sem `synchronize` não roda nos commits seguintes. Virou o `--pr`.
- Mandei um agente regravar baseline visual no mesmo PR, e ele recusou corretamente (ADR 0409). Receita de bot não substitui ADR.
- Branch em uso por outra sessão: conferir `git worktree list` antes de criar worktree para consertar PR alheio (quase pisei no #8719 e no worktree da Superadmin/03).
- Barra invertida colapsou duas vezes em heredoc e jq (LC-26); a saída foi `String.fromCharCode(92)` e classe de caractere.
- O auto-merge de exceções (#8721, #8725, #8726) foi ligado por alguém sob a conta `wagnerra23`; não dá para saber qual sessão. Desliguei; os três entraram depois.

## Pointers detalhados
Cronograma (artifact acima) · ADR 0427 · #8703 (corpo com a medição) · recibos `_saida-*` em `prototipo-ui/cowork/Wagner/cowork-inbox/*/playbook/`.
