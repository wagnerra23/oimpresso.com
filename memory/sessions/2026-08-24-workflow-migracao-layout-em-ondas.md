---
date: "2026-08-24"
hour: "17:11 BRT"
topic: "Workflow em três gates para planejar e executar a migração visual React × Blade × Claude Design"
authors: [W, C]
outcomes:
  - "Workflow migracao-layout-em-ondas criado com modos plano, dossie e executar"
  - "Skill migracao-blade-react reconciliada com tenants e gate visual vigentes"
  - "Selftest ligado à lane de governança"
prs: []
us: []
related_adrs:
  - "0062-separacao-runtime-hostinger-ct100"
  - "0093-multi-tenant-isolation-tier-0"
  - "0130-handoff-append-only-mcp-first"
  - "0141-skill-migracao-blade-react"
  - "0277-rota-migracao-blade-ondas-completude"
---

# Session log 2026-08-24 — Workflow de migração visual em ondas

## TL;DR

O pedido de transformar o protocolo React × Blade × Claude Design em mecanismo persistente resultou em um workflow versionado de três gates. Planejamento e dossiê são read-only; a execução exige duas aprovações booleanas, referências, SHA, onda de no máximo duas telas — alinhada ao backpressure do `prototipo-ui/PROTOCOL.md` §8 — e preflight limpo, terminando antes da onda seguinte.

## Contexto

O protocolo textual estava completo, mas dependia de disciplina manual e coexistia com a skill `migracao-blade-react`, que ainda carregava instruções históricas sobre tenants, smoke e aprovação visual síncrona. A decisão foi estender o dono existente, sem criar uma segunda skill concorrente.

## Entregas

- `.claude/workflows/migracao-layout-em-ondas.js` — censo, reconciliação, plano mestre, dossiê, preflight, execução e verificação.
- `scripts/governance/migracao-layout-workflow.test.mjs` — executa o fonte real com dublê de agente e prova os gates negativos.
- `.claude/skills/migracao-blade-react/SKILL.md` — porta de entrada atualizada e apontando para o workflow.
- `.github/workflows/governance-script-tests.yml` — selftest ligado à lane já existente; zero gate novo.
- `.claude/skills/_SKILLS-INDEX.md` — índice regenerado pelo dono.

## Decisões cinzentas resolvidas

| Pergunta | Decisão | Justificativa |
|---|---|---|
| Playbook ou workflow? | Workflow versionado | O pedido tem estados, aprovações e bloqueios que precisam morder. |
| Criar skill nova? | Não | `migracao-blade-react` já é o dono canônico da intenção. |
| Tamanho de uma onda | No máximo duas telas | Mantém o escopo pequeno, reversível e verificável e respeita o backpressure do `prototipo-ui/PROTOCOL.md` §8. |
| Aprovação em texto livre vale? | Não | Somente booleanos literais em entrada estruturada liberam dossiê/execução. |

## Verificação

- `node scripts/governance/migracao-layout-workflow.test.mjs` — verde.
- `node scripts/governance/skills-index-generate.mjs --check` — verde.
- `node scripts/governance/selftest-registry-check.mjs --check` — zero selftests órfãos.
- `git diff --check` — verde.

## Próximos passos (não-bloqueante)

- [ ] Rodar o workflow em `modo: "plano"` para gerar o censo real do programa.
- [ ] Submeter o plano mestre à aprovação de Wagner antes de gerar qualquer dossiê.

## Referências

- Handoff: [2026-08-24-1711-workflow-migracao-layout-em-ondas.md](../handoffs/2026-08-24-1711-workflow-migracao-layout-em-ondas.md)
- Workflow: [migracao-layout-em-ondas.js](../../.claude/workflows/migracao-layout-em-ondas.js)
