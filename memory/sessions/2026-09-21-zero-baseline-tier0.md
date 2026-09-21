---
date: "2026-09-21"
hour: "08:50 BRT"
topic: "Zero baseline de tolerância — decisão e primeira catraca Tier 0"
authors: [C]
outcomes:
  - "ADR 0409 proposta com plano de retirada dos baselines de tolerância"
  - "Model grandfathered tocado passa a reprovar se continuar sem escopo de tenant"
  - "Exceção global NfeSefazStatus separada da dívida em contrato nominal verificável"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0208-larastan-baseline-ratchet
  - 0409-zero-baseline-de-tolerancia-conformidade-absoluta
---

# Zero baseline de tolerância — primeira catraca Tier 0

## Contexto

Depois da auditoria dos baselines, Wagner concordou com o veredito de que baseline de
tolerância não deveria existir. O trabalho foi preparado em worktree isolada, branch
`codex/zero-baseline-tier0`, baseada em `origin/main` `7f995280849301a9d05f079a9162b7c854198874`.

## O que foi feito

1. A [ADR 0409](../decisions/0409-zero-baseline-de-tolerancia-conformidade-absoluta.md)
   registrou a distinção entre tolerância, contrato e evidência e definiu a retirada em ondas.
2. `.github/workflows/multi-tenant-gate.yml` passou a entregar em `MTS_CHANGED_MODELS` os
   Models do diff que disparou a lane required.
3. `MultiTenantScopeArchitectureTest.php` passou a cruzar Models tocados, infratores reais e
   dívida grandfathered. A interseção reprova com instrução de cura no mesmo PR.
4. O parser da lista ganhou bite-test de normalização, filtro e deduplicação.
5. `NfeSefazStatus` saiu do arquivo de dívida e entrou em
   `governance/multi-tenant-global-model-contract.json`, com razão e ponteiro para a ADR de
   módulo. O teste exige arquivo, Model, razão e decisão existentes e impede sobreposição com
   `grandfathered`.
6. O relatório da auditoria foi preservado em
   [`memory/audits/2026-09-21-baselines-de-tolerancia.md`](../audits/2026-09-21-baselines-de-tolerancia.md).

## Verificações

- os dois JSONs modificados/criados foram parseados por `ConvertFrom-Json`;
- `git diff --check` passou;
- `node scripts/governance/adr-index-generate.mjs --check` passou com 0 colisão nova;
- o índice de ADRs foi regenerado;
- Pest/PHPStan não foram executados localmente, conforme ADR 0062 e a proibição do repo;
- a prova executável completa depende do commit/push para rodar na lane PHP do CI/CT 100.

## Estado ao final

O resultado foi apresentado sem commit, push ou PR. Wagner concedeu depois a aprovação R10
para commitar, publicar a branch e abrir o PR; a autorização veio sobre este conjunto concreto.

## Próximo passo

Commitar, publicar a branch, abrir PR, acompanhar todos os requireds e corrigir qualquer
falha antes de pedir merge. O merge da ADR proposta é o ato de ratificação.
