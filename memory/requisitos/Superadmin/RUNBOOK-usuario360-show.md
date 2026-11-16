---
id: requisitos-superadmin-runbook-usuario360-show
title: "RUNBOOK — /superadmin/usuarios/{id}/360 (Usuário 360 · raio-X)"
module: Superadmin
tela: superadmin/Usuario360/Show
owner: W
status: ativo
last_validated: "2026-11-16"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
spec_ref: memory/requisitos/Superadmin/SPEC.md
---

# RUNBOOK — `/superadmin/usuarios/{id}/360` (Usuário 360, raio-X)

Escrito em 2026-11-16 (lote L6 da auditoria) para uma tela que já existia sem RUNBOOK. Descreve o código em `origin/main` 580be57.

- **Page:** `Modules/Superadmin/Resources/js/Pages/superadmin/Usuario360/Show.tsx`.
- **Charter + casos:** `Show.charter.md` (`draft`) e `Show.casos.md` (UC-SAUX-01..06).
- **US:** US-SUPER-010.
- **Rotas** (`Modules/Superadmin/Routes/web.php:16-19`), todas no grupo `superadmin` + `throttle:superadmin`:
  - `GET /superadmin/usuarios/{id}/360` → `show`
  - `POST …/lock` → `lock`
  - `POST …/unlock` → `unlock`
  - `GET …/history` → `history` (JSON dos lockouts)

## 1. O que o backend faz

**`show`:**
1. carrega o usuário com os papéis e o business dele;
2. **grava um log de acesso** (abaixo);
3. devolve os blocos:
   - `user`, `roles`, `permissions` (do `PermissionRegistry`);
   - `scopes_ads`, `tokens_mcp`, `quotas_copiloto`, `sessions_ativas`;
   - `auditoria`, `lockouts`;
   - `tabelas_ausentes` (bloco cuja tabela não existe no ambiente vem vazio e é listado ali, em vez de quebrar).

**Log de acesso (LGPD Art. 7º, #8364, 2026-10-01):**
- grava `activity('superadmin_acesso')->event('usuario360_visualizado')` (`Usuario360Controller.php:177-193`);
- o `business_id` do registro é o do **usuário visto**, não o da sessão do superadmin, para o dono do negócio enxergar quem abriu o raio-X de alguém da casa dele;
- o payload leva só o id do titular; IP e user agent são do superadmin.

**Trancar e destrancar:**
- `lock` exige `reason` com 5 a 500 caracteres; sem motivo, devolve erro em `reason` e não muda nada (UC-SAUX-04);
- `UserLockoutService::lock` revoga os tokens MCP;
- `unlock` reativa o usuário **sem devolver os tokens** (UC-SAUX-05).

## 2. Tier 0 — invariantes

- Cross-tenant intencional: o superadmin abre o raio-X de usuário de qualquer business (UC-SAUX-03).
- O admin de negócio é barrado (UC-SAUX-02) e, barrado, não gera registro de acesso.
- O log de acesso é append-only: um INSERT por abertura.

## 3. Testes

`Modules/Superadmin/Tests/Feature/Usuario360ContratoTest.php` (UC-SAUX-01..06). Rodar no CT 100.

## 4. Smoke prod (R1)

Não registrado nesta data. A medição de tela é o lote L7.

## 5. Limites conhecidos

- Cada abertura grava um registro, inclusive recarregar a página.
- `tabelas_ausentes` esconde bloco faltante em vez de falhar. Útil em ambiente parcial, mas pode mascarar migration não rodada em prod.
