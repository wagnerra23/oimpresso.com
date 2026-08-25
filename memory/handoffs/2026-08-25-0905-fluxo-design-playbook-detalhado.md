---
date: "2026-08-25"
time: "09:05 BRT"
slug: "fluxo-design-playbook-detalhado"
tldr: "A referência Fluxo de Design foi aprofundada em um playbook operacional: E0 mais nove etapas, decisões internas, armadilhas, falsos-verdes e cobertura real. Oito lacunas residuais ficaram detalhadas; a nona foi fechada em paralelo pelo PR #6232 antes da publicação."
decided_by: [W]
prs: []
us: []
next_steps:
  - "Revisar o conteúdo detalhado de memory/reference/FLUXO-DESIGN.md"
  - "Se aprovado, autorizar explicitamente commit, push e abertura de PR"
related_adrs:
  - "0114-prototipo-ui-cowork-loop-formalizado"
  - "0282-protocolo-v2-colapso-ratificacao"
  - "0299-figma-nao-e-fonte-de-design"
---

# Handoff 2026-08-25 09:05 BRT — Fluxo de Design detalhado

## TL;DR

`memory/reference/FLUXO-DESIGN.md` deixou de ser apenas uma narrativa das etapas e passou a explicar como cada uma executa por dentro: entrada, transformação, decisão, recusa, retorno, artefato gravado, armadilha, falso-verde, prova existente e limite residual. Nenhum código produtivo, workflow, gate ou tela foi alterado.

## O que mudou

- corrigida a contagem conceitual para **E0 de ativação + nove etapas operacionais**;
- adicionados fluxos internos em árvore para E0–E9;
- adicionadas matrizes de armadilhas e falsos-verdes por etapa;
- corrigidas afirmações superficiais de ausência de teste: transporte, espelho, âncora, contrato, casos, evidência e smoke já tinham cobertura localizada;
- distinguida prova de wiring, controle positivo, controle negativo, limite semântico e enforcement;
- substituída a lista genérica de testes por oito propostas residuais com fixture boa, fixture ruim, oráculo, falso-verde, pré-requisito e prioridade;
- reconciliado o avanço concorrente do `main`: o T6 aparece como fechado pelo PR #6232, sem duplicar implementação;
- organizada uma execução dos testes em quatro ondas pequenas, sem promoção implícita a required.

## Provas executadas

- `node scripts/memory-schemas/validate.mjs memory/reference/FLUXO-DESIGN.md` — verde;
- `node prototipo-ui/protocolo.config.mjs --selftest` — verde;
- `node prototipo-ui/integrity-check.mjs` — todos os IT duros passaram;
- `node scripts/governance/design-memory-gate.test.mjs` — verde;
- `node scripts/governance/deadlink-gate.mjs --check` — nenhuma piora;
- `node scripts/governance/deadlink-gate.test.mjs` — verde;
- `git diff --check` — verde.

Após o rebase sobre `origin/main` (`00815a00e7`), duas limitações externas ao diff foram
registradas honestamente:

- `npm run test:conformance` não iniciou localmente porque o `npm-cli.js` da instalação global do
  Windows não foi encontrado; a suíte ficou para o runner limpo do CI;
- `node prototipo-ui/ancora.mjs --selftest` encontrou duas falhas de staging introduzidas no
  `main` pelo #6230. Este trabalho não toca `ancora.mjs`; a falha-base foi preservada para triagem
  do dono, sem ampliar um PR documental.

## Limites e autorização

- alterações estão na branch `codex/fluxo-design-detalhado`, com commit local criado após a
  autorização de merge;
- no fechamento deste artefato ainda não havia push, PR, merge, promoção de gate ou mudança de
  branch protection;
- T1–T5 e T7–T9 são propostas documentadas; T6 foi implementado separadamente no PR #6232;
- R10 continua exigindo autorização humana explícita para publicar.

## Estado MCP no fechamento

As tools MCP de estado vivo (`brief-fetch`, `cycles-active`, `my-work`, `sessions-recent`) não estavam expostas nesta execução. Foi usado o fallback canônico: `CLAUDE.md`, `memory/08-handoff.md`, session logs, scripts, workflows e baseline versionada.

## Referências

- Documento: [`FLUXO-DESIGN.md`](../reference/FLUXO-DESIGN.md)
- Session log: [`2026-08-25-fluxo-design-playbook-detalhado.md`](../sessions/2026-08-25-fluxo-design-playbook-detalhado.md)
