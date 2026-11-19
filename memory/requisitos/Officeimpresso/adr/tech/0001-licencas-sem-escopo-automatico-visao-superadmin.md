---
id: requisitos-officeimpresso-adr-tech-0001-licencas-sem-escopo-automatico-visao-superadmin
---

# ADR TECH-0001 (Officeimpresso) · Licenças sem escopo automático de empresa: a visão de todas é do superadmin

- **Status**: accepted
- **Data**: 2026-11-19
- **Decisores**: Wagner
- **Categoria**: tech
- **Relacionado**: [ADR 0093](../../../../decisions/0093-multi-tenant-isolation-tier-0.md), auditoria Officeimpresso 2026-11-05 (eixo 5: A3, M2, M3)

## Contexto

`Licenca_Computador` (tabela `licenca_computador`) e `LicencaLog` (tabela `licenca_log`) guardam as
máquinas Delphi de cada cliente e o log de acesso delas. Cada linha tem o `business_id` da empresa do
cliente. Nenhum dos dois Models tem escopo automático de business: eles estavam na lista
`grandfathered` do `MultiTenantScopeArchitectureTest`, e qualquer alteração neles reprovava o gate.

O módulo é operado pela empresa operadora (`constants.operator_business_id`). O suporte dela precisa ver
as máquinas de todos os clientes: é a função da tela de licenças. Quem decide isso no código são os
controllers:

- `LicencaComputadorController`: a visão de todas as empresas exige `can('superadmin')`; sem ela, as
  consultas filtram por `business_id` da sessão (`->when(! $todas, ...)`, marcado `// SUPERADMIN:`).
- `LicencaLogController`: sem superadmin, filtra pela empresa da sessão.

A API do desktop (Connector) também resolve a licença antes de existir sessão de business.

## Decisão

Pergunta feita ao Wagner em 2026-11-19: *"registro no contrato de isolamento que elas atravessam
empresas (recomendado), ou ponho filtro por empresa?"*. Resposta textual: *"Sim, registrar"*. Em
seguida: *"isso é superadmin"*.

Os dois Models entram em `governance/multi-tenant-global-model-contract.json` e saem da lista
`grandfathered`. O isolamento continua sendo por consulta, nos controllers: a visão sem filtro é só do
superadmin, e todo o resto filtra pela empresa da sessão.

## Consequências

- Os Models podem ser alterados sem reprovar o gate de escopo. Isso destravou tirar credenciais do
  `activity_log` (M2/M3).
- Risco aceito: um chamador novo dos Models (job, comando, service) não herda filtro nenhum. Quem
  escrever consulta nova sobre licenças precisa filtrar por empresa ou exigir superadmin, como os
  controllers fazem.
