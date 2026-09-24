---
slug: 0415-gate-before-permissoes-de-plataforma-fora-do-bypass
number: 415
title: "Gate::before — permissão de plataforma sai do bypass do papel Admin#{empresa}"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-24"
module: governance
tags: [permissao, multi-tenant, tier0, gate, plataforma, governance, jana, forja]
supersedes: []
superseded_by: []
related:
  - 0392-fronteira-governance-audiencia-enforcement-na-concessao
  - 0093-multi-tenant-isolation-tier-0
  - 0366-fronteira-jana-forja-governance-kb
pii: false
---

# ADR 0415 — Permissão de plataforma sai do bypass do papel `Admin#{empresa}`

> **Nasce `proposto`.** Fecha a decisão D-GATE do playbook de Governança (thread 05).
> O agente recomendou a opção B e implementou no mesmo PR; **o merge do [W] é a
> ratificação (R10)**. Sem merge, nada muda.

## Contexto

`app/Providers/AuthServiceProvider.php` registra um `Gate::before`, herdado do UltimatePOS.
Para quem tem o papel `Admin#{business_id}` — o dono de **cada** empresa, inclusive clientes
como a ROTA LIVRE — ele devolve `true` em **qualquer** permissão, exceto três
(`backup`, `superadmin`, `manage_modules`, que ficam só para `administrator_usernames`).

**Alcance medido** em `origin/main` = `723d2b1e6` (2026-09-24), varrendo `*.php` e
`*.blade.php` fora de testes, seeders e protótipo: **2.811 checagens** (`can:`, `->can(`,
`authorize(`, `Gate::…`, `@can`) em **508 arquivos**, sobre **351 permissões distintas**.
Todas atravessam o bypass, menos as três exceções.

**O que é Tier 0 nisso** são as permissões de **plataforma** — as que abrem dado de todas as
empresas (plano de engenharia, [ADR 0392](0392-fronteira-governance-audiencia-enforcement-na-concessao.md)):

| permissão | arquivos | abre |
|---|---|---|
| `jana.mcp.usage.all` | 15 | hub Forja (8 telas) · `/governance/qualidade-ia`, que lê `?business_id=` da URL |
| `jana.mcp.memory.manage` | 14 | gestão da memória/KB canônica (ADRs, sessões, runbooks) |
| `jana.cc.read.all` · `jana.cc.curate` | 5 | sessões Claude Code do time inteiro |
| `jana.superadmin` | 6 | inclui `MetasController::store`, que aceita `business_id` alheio quando `can()` é `true` |

Os testes `SuperadminMetasCrossTenantTest` e `ProPreviewPermissaoTest` asseriam como
**controle** que o dono de empresa passava em `can('jana.superadmin')`. O #6421 tapou uma
porta trocando `can()` por `hasPermissionTo()`; as outras seguiam abertas.

**Correção do índice do playbook:** ele atribuía o bloqueio ao "passo 1 da 0392 em aberto".
O passo 1 (a *concessão* dos scopes `admin_only`) foi fechado pelo #6962 em 2026-09-07 —
mas isso não alcança o dono, que nunca precisou da permissão concedida.

## Decisão

**Opção B.** Permissão de plataforma não é herdada pelo papel `Admin#{business_id}`.

- **Lista (fonte única, derivada):** os scopes `admin_only => true` do catálogo MCP
  (`McpScopesSeeder::catalogo()`, o mesmo que o #6962 já tira do editor de papéis) +
  `jana.superadmin`. Marcar um scope novo como `admin_only` já o tira do bypass.
  Exposta em `AuthServiceProvider::permissoesDePlataforma()`.
- **Quem passa:** usuários em `administrator_usernames`, ou quem tem a permissão **de
  verdade** (checagem normal do Spatie). O time com papel próprio (`Operacional#1`) segue
  entrando.
- **Nada muda** para as outras ~345 permissões do ERP: o dono segue liberado nelas.

## Opções consideradas

- **A — manter e remendar caso a caso** com `hasPermissionTo()`. Rejeitada: os ~40 pontos da
  tabela seguem abertos e cada permissão nova repete o buraco.
- **B — plataforma fora do bypass (adotada).** ~15 linhas, lista derivada, teste que morde.
- **C — remover o bypass inteiro.** Rejeitada: todo dono passaria a precisar de 351
  permissões explícitas, com migração de dados sobre todas as empresas em produção; uma
  permissão esquecida quebra a tela de um cliente.

## Consequências

- **Fecha:** dono de empresa deixa de abrir Forja, `/governance/qualidade-ia`, a gestão do KB
  e as rotas `jana.superadmin` sem permissão real.
- **Risco a conferir antes do merge (não medido nesta sessão, precisa de produção):** quem da
  equipe hoje entra nessas telas **só** pelo papel `Admin#1`, sem estar em
  `administrator_usernames` e sem a permissão real, perde o acesso. Consulta:
  `SELECT u.username FROM users u JOIN model_has_roles mr ON mr.model_id=u.id JOIN roles r ON r.id=mr.role_id WHERE r.name='Admin#1'`
  cruzado com `ADMINISTRATOR_USERNAMES` e com quem tem `jana.mcp.usage.all`.
- **Fica em aberto, fora desta ADR:**
  1. `jana.superadmin` segue **concedível** no editor de papéis da empresa
     (`Modules/Jana/Http/Controllers/DataController.php`, item `jana.superadmin`). Tirá-lo
     do catálogo, revogando concessões existentes, é o próximo PR.
  2. `governance.*` — são concedíveis pelo dono, e o módulo é vendável por empresa. Tratá-las
     como plataforma é o passo 3 da 0392 (decisão de produto do [W]).

## Recibos

| afirmação | como reproduzir |
|---|---|
| 2.811 checagens / 508 arquivos / 351 permissões | varredura por regex (`can:`, `->can(`, `authorize(`, `Gate::`, `@can`) em `git ls-files '*.php'` sem `tests/`, `/Tests/`, `database/`, `prototipo-ui/` |
| lista derivada = `admin_only` + `jana.superadmin` | `tests/Feature/Roles/GateBeforePlataformaTest.php` caso "fonte única" |
| o dono não herda plataforma, o resto segue liberado | mesmo teste: casos MORDE e CONTROLE; CT 100 (`oimpresso-staging`, cópia isolada em `/tmp/gbp`) |
| o teste morde | provisão revertida → casos MORDE vermelhos (registrado no PR) |
