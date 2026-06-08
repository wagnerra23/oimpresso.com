---
title: Usuário "Eliana Teste" movido pra role Operacional#1 (teste de restrição) [E]
date: 2026-06-08
owner: Eliana [E]
slug: eliana-teste-restricao-operacional-1
related_handoff: memory/handoffs/2026-06-07-0220-migracao-financeira-wr2-completa-fix-kpi-juros.md
related_session: memory/sessions/2026-06-06-migracao-wr-comercial-financeiro-eliana.md
---

# Sessão — Restrição Operacional#1 aplicada ao usuário "Eliana Teste"

## TL;DR (em PT-BR claro)

Eliana criou um usuário de teste pra simular o que Felipe, Maiara e Luiz enxergam. Apliquei a mesma restrição: tirei Admin#1 (que vê tudo) e coloquei Operacional#1 (sem Financeiro, com WhatsApp).

## Usuário-alvo

| Campo | Valor |
|---|---|
| ID | 607 |
| Username | eliana-02-01 |
| Email | nana.alvez@gmail.com |
| Nome | Eliana Teste |
| Empresa | biz=1 (WR2 Sistemas) |
| Role ANTES | Admin#1 (id=1, 43 permissões — vê tudo) |
| Role DEPOIS | Operacional#1 (id=695, 19 permissões) |

## O que foi feito (passo a passo)

1. **Warm-up + SSH** Hostinger prod
2. **SELECT** confirmou user_id=607, email correto, biz=1, role Admin#1
3. **Backup defensivo** → `output/backup-users-pre-mover/rollback-eliana-teste-2026-06-08.sql`
4. **Transação MySQL**:
   - DELETE de `model_has_roles` onde user_id=607 (removeu Admin#1)
   - INSERT com role_id=695 (colocou Operacional#1)
5. **Validação** das permissões finais

## Validação (igualzinha aos 3 ops)

```
TOTAL_PERMS=19
FINANCEIRO_perms=0  (esperado 0) ✅
WHATSAPP_perms=6    (esperado 6) ✅
JANA_perms=10       (esperado 10) ✅
```

**WhatsApp ativas (6):** access, assign, metricas.view, send, settings.manage, templates.manage

**Jana ativas (10):** mcp.cycles.manage, mcp.decisions.read, mcp.governanca.tecnico, mcp.projects.manage, mcp.sessions.read, mcp.tasks.read, mcp.tasks.write, mcp.usage.all, mcp.usage.self, mcp.use

**Outras (3):** access_all_locations, print_invoice, superadmin

## Como reverter (se precisar)

```bash
mysql -u <user> -p <db> < output/backup-users-pre-mover/rollback-eliana-teste-2026-06-08.sql
```

Volta o user 607 pra Admin#1 instantaneamente.

## Propósito

Eliana vai logar como `nana.alvez@gmail.com` pra testar a experiência exata que Felipe/Maiara/Luiz têm hoje — sem precisar pedir um deles pra fazer screenshot, sem invadir conta de ninguém.

## Tier 0 respeitado

- ✅ Backup defensivo ANTES de DELETE/INSERT
- ✅ Transação MySQL atômica (rollback automático se algum INSERT falhasse)
- ✅ biz=1 (WR2) preservado — sem vazamento cross-tenant
- ✅ Validação pós-mudança confirmou 0 Financeiro
- ✅ Anti-regressão: biz=4 (Larissa ROTA LIVRE) e biz=164 (Martinho) não foram tocados
