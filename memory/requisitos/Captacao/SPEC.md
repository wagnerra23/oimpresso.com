---
id: requisitos-captacao-spec
module: Captacao
status: ativo
version: "1.0"
last_updated: "2026-11-14"
lifecycle: em-construcao
related_adrs:
  - "0452-captacao-projetos-de-captacao-de-recursos"
  - "0093-multi-tenant-isolation-tier-0"
  - "0358-doutrina-de-teste-tenant-98-supersede-0101"
owner: [W]
---

# Especificação funcional — Captação

> Convenção do ID: `US-CAPT-NNN`. A ordem segue as ondas da [ADR 0452](../../decisions/0452-captacao-projetos-de-captacao-de-recursos.md).

## User stories

### US-CAPT-001 · Camada de dados dos projetos de captação — **P0**

**Implementado em:** `Modules/Captacao/Database/Migrations/2026_11_14_000001_create_captacao_tables.php` · `Modules/Captacao/Entities/` · `Modules/Captacao/Services/CaptacaoAcesso.php`

**Testado em:** `Modules/Captacao/Tests/Feature/CaptacaoEsquemaContratoTest.php`

**DoD:** as quatro tabelas têm business_id com FK; os models filtram por empresa (98 não vê 99); aporte e comprovante não se apagam pelo Model e saem da lista com `removido_em` + motivo; a regra de acesso (`CaptacaoAcesso`) libera só com o pacote `captacao_module` e `captacao.access`.

### US-CAPT-002 · Fontes e projetos — **P0**

**Implementado em:** _pendente_ — onda 2 da ADR 0452.

Cadastro e lista das fontes (edital, lei de incentivo, fundo, doação PJ e PF, parceria, voluntariado) e dos projetos (título, objetivo, proponente com nome e CNPJ, valores, período de execução), com mudança de situação de `ideia` a `encerrado`.

### US-CAPT-003 · Aportes — **P0**

**Implementado em:** _pendente_ — onda 3 da ADR 0452.

Registro dos recursos que entram em cada projeto (dinheiro, bens, serviços, horas de voluntário), com o total captado contra o aprovado. O cálculo de total segue a regra mestre de valor (dupla prova).

### US-CAPT-004 · Comprovantes e prestação de contas — **P1**

**Implementado em:** _pendente_ — onda 4 da ADR 0452.

Despesas com arquivo e rubrica, relatório de prestação de contas por projeto e alerta de prazo vencendo ou vencido.

### US-CAPT-005 · Integração com o Financeiro — **P2**

**Implementado em:** _pendente_ — onda 5 da ADR 0452, opcional e atrás de flag.

## Histórico

- 2026-11-14 · v1.0 — criado com a onda 1 da ADR 0452; US-CAPT-001 entregue (camada de dados), 002 a 005 planejadas.

## Referências

- [ADR 0452 — projetos de captação de recursos](../../decisions/0452-captacao-projetos-de-captacao-de-recursos.md)
- [ADR 0093 — multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0358 — doutrina de teste tenant 98](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
- [SCOPE](SCOPE.md) · [BRIEFING](BRIEFING.md)
