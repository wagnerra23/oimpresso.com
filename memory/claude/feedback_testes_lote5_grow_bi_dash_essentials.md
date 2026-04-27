---
name: Lote 5 de testes — BI/Dashboard ausentes em 6.7-bootstrap
description: Discrepância entre briefing (4 módulos) e o que existe no branch alvo (apenas Grow + Essentials). Documentação para evitar retrabalho na próxima sessão.
type: feedback
originSessionId: tests-batch-5-grow-bi-dash-v2
---

## O que aconteceu (2026-04-27)

Briefing pediu testes Pest para **4 módulos**: Grow, BI, Dashboard, Essentials.

Realidade no `6.7-bootstrap` checkado:

- ✅ `Modules/Grow/` existe (CodeCanyon Perfect Support legado, ~800 rotas, maioria comentada).
- ✅ `Modules/Essentials/` existe (parcialmente migrado para Inertia React).
- ❌ `Modules/BI/` **não existe**.
- ❌ `Modules/Dashboard/` **não existe**.

`memory/claude/preference_modulos_prioridade.md` confirma: BI e Dashboard
existiam em `3.7-com-nfe` e foram **perdidos na migração 3.7 → 6.7**. Há
DashboardControllers em vários módulos (Essentials, Financeiro, Crm, Repair,
PontoWr2, MemCofre, Accounting, Copiloto) — mas não um módulo Dashboard
separado.

## Decisão tomada nesta sessão

- Cobrir **Grow + Essentials** com Pest (ver `Modules/<X>/Tests/Feature/`).
- Para BI/Dashboard: criar `memory/requisitos/<Modulo>/SPEC.md` com status
  "ausente" e checklist do que precisa para reintroduzir. **Não criar**
  `Modules/BI/Tests/Feature/` ou `Modules/Dashboard/Tests/Feature/` — geraria
  namespaces inválidos.

## Discrepâncias prévias com o lote anterior

A branch `claude/tests-batch-5-grow-bi-dash` (sem `-v2`) já tinha um lote
anterior por outro agente (commit `a1fedf8b`). Aquele branch foi ramificado
de um estado **muito diferente** (provável 3.7 com Laravel 5.8 + Blade) e
chegou a criar `Modules/BI/Tests/Feature/`. Em 6.7-bootstrap aquilo é
inviável — daí a branch nova `claude/tests-batch-5-grow-bi-dash-v2`.

## Como aplicar no próximo lote

- Sempre validar `ls Modules/<X>` ANTES de gerar testes.
- Se o módulo não existir, parar e documentar (não criar Tests/ órfãos).
- Diferenciar branches por base — `6.7-bootstrap` é canônico desde
  ADR 0023; qualquer branch antiga em 3.7 é descartável.

## Bloqueios não resolvidos

- `composer install` rodou OK depois de `cp ".env - Copia.example" .env`,
  criar `storage/framework/{cache,sessions,testing,views}` e setar
  `MAIL_FROM_ADDRESS=hello@example.test` (Spatie/Backup levanta
  `InvalidConfig: '' is not a valid email address` se vazio).
- `vendor/bin/pest --filter <Modulo>` roda mas a maioria dos testes
  pré-existentes está vermelha (50 errors / 17 failures / 90 skipped) por
  problemas de DB local (sem Business seedado). Os testes novos do lote 5
  marcam `markTestSkipped` automaticamente nesses casos via
  `EssentialsTestCase::actAsAdmin()` / `GrowTestCase::actAsAdmin()`.
