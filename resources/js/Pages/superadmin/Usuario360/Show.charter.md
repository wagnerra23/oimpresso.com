---
page: /superadmin/usuarios/{id}/360
owner: wagner
status: draft
last_updated: 2026-05-31
component: resources/js/Pages/superadmin/Usuario360/Show.tsx
last_validated: "2026-05-31"
parent_module: Superadmin
related_us: [US-SUPER-010]
tier: B
charter_version: 1
---

# Charter — Usuário 360 (Raio-X / Show)

Mission: dar ao superadmin a vista ÚNICA e auditável de tudo sobre um usuário —
roles, permissões efetivas (com risco), scopes ADS/MCP, tokens MCP, quotas,
sessões, auditoria e histórico de lockouts — pra não "pular de galho em galho"
ao investigar acesso (dor original do Wagner: funcionário desviou porque não
dava pra ver o todo).

## Goals
- Consolidar 9 blocos 360 numa tela, com graceful-degradation por tabela ausente
  (`tabelas_ausentes` → aviso + cards vazios, nunca quebra).
- Ações de segurança no header: Trancar (com motivo + snapshot), Destrancar
  (reativa status, NÃO restaura tokens), Histórico.
- Navegação por blocos via Tabs (atalho pros grupos sem scroll longo).
- Risco (low/medium/high/critical) legível por Badge semântico + ícone Shield*.

## Non-Goals
- Não edita roles/permissões/tokens/scopes aqui (cada um tem fluxo próprio).
- Não restaura tokens MCP no unlock (segurança — Wagner gera novo manual).
- Não faz ação em massa. Não vaza dados cross-tenant sem o superadmin pedir.

## UX targets
- Above-the-fold: identidade + status + risco visíveis sem scroll.
- Tokens semânticos only (sem cor crua `gray/yellow/orange/red-NNN`, sem hex/oklch).
- Ícones lucide (sem emoji como ícone).
- Confirmação destrutiva (unlock) via AlertDialog DS com contexto — nunca
  `window.confirm`. Lock continua via Dialog DS com motivo obrigatório.

## Anti-hooks
- Sem polling / auto-refresh (superadmin dá refresh).
- Sem inventar rota/prop de backend — contrato vem do Usuario360Controller
  (lock/unlock/history) + UserLockoutService.
