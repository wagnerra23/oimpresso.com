---
date: "2026-11-14"
hour: "19:00 BRT"
duration: "1.5h"
topic: "Auditoria do deploy (staging CT 100 → produção Hostinger) + RUNBOOK + dry-run sem deploy"
authors: [W, C]
outcomes:
  - "RUNBOOK-deploy-producao.md (rascunho) com pré-check, staging, produção, smoke, rollback A-E, DNS/TLS e MCP"
  - "Dry-run só-leitura: pipeline de prod saudável; 10 gaps priorizados no handoff"
prs: []
us: []
related_adrs:
  - "0062-separacao-runtime-hostinger-ct100"
  - "0269-deploy-automatico-build-no-runner"
  - "0235-staging-ct100-clone-anonimizado"
---

# Session log 2026-11-14 — RUNBOOK de deploy em produção

## TL;DR

[W] pediu um orquestrador de deploy staging → produção com RUNBOOK e lista de gaps, sem deployar nada. O deploy de
produção já é automático no merge (ADR 0269) e está saudável. O staging não está no caminho. O que trava "pronto
para produção" é a rede de segurança: backup do banco, rollback ensaiado e o papel do staging.

## Contexto

Pedido com o histórico de 3 crashes em mente. Regra da sessão: nada que mude servidor, só leitura e validação.

## Cronologia

| Quando | Evento |
|---|---|
| início | Protocolo MCP (brief, my-work, cycles-active, ADR 0062) |
| — | Worktree da sessão vazio e checkout local 2192 commits atrás de `origin/main`; worktree novo criado |
| — | Auditoria dos workflows, scripts e RUNBOOKs |
| — | Dry-run só-leitura (gh, SSH Hostinger, curl, DNS, TLS) |
| — | RUNBOOK + handoff escritos |

## Entregas

- **RUNBOOK** — `memory/requisitos/Infra/RUNBOOK-deploy-producao.md`
- **Handoff** — `memory/handoffs/2026-11-14-1900-runbook-deploy-producao.md` (tabela de gaps)

## Aprendizados / pegadinhas

- Antes de auditar, confira se o checkout está atualizado: o `deploy.yml` local estava 87 linhas atrás do da `main`.
- "Não deployar sem [W]" = "não mergear PR de runtime sem [W]": o merge é o deploy.
- Um `curl` dentro de `$(...)` no shell padrão do Actions (`bash -e`) encerra o passo em erro de rede, mesmo com `set -uo pipefail` sem `-e`.
- A receita "Rollback rápido" do `hostinger.md` vai para frente, não para trás.

## Próximos passos (não-bloqueante)

- [ ] Plano de fixes aprovado por [W] (ver handoff)

## Referências

- Handoff: [2026-11-14-1900-runbook-deploy-producao.md](../handoffs/2026-11-14-1900-runbook-deploy-producao.md)
- ADRs: [0062](../decisions/0062-separacao-runtime-hostinger-ct100.md), [0269](../decisions/0269-deploy-automatico-build-no-runner.md), [0235](../decisions/0235-staging-ct100-clone-anonimizado.md)
