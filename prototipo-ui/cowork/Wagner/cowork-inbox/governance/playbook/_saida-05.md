---
thread: "05 · Gate::before deixa admin passar por qualquer can:"
dono: "[W]"
estado: decisão D-GATE proposta (opção B) + PR de código · ratificação = merge [W]
base_lida: wagnerra23/oimpresso.com@main 723d2b1e6 (2026-09-24)
prefixo_tocado: app/Providers/AuthServiceProvider.php · tests/Feature/Roles/GateBeforePlataformaTest.php · 2 testes da Jana · memory/decisions/0415-*
---
# _saida-05 · D-GATE: permissão de plataforma sai do bypass do Admin

## A premissa do índice estava desatualizada
O índice atribuía o bloqueio ao "passo 1 da ADR 0392 em aberto". O passo 1 (a **concessão**
dos scopes `admin_only`) foi fechado pelo #6962 em 2026-09-07. Só que ele não alcança o dono
da empresa: `Gate::before` liberava o papel `Admin#{empresa}` em qualquer permissão fora de
`backup`/`superadmin`/`manage_modules`, sem precisar de concessão nenhuma.

## Medido
- **2.811** checagens de permissão em **508** arquivos, **351** permissões distintas — todas
  atravessavam o bypass, menos as 3 exceções.
- Tier 0 no meio delas: `jana.mcp.usage.all` (15 arquivos: Forja + `/governance/qualidade-ia`,
  que lê `?business_id=`), `jana.mcp.memory.manage` (14), `jana.cc.read.all`/`curate` (5),
  `jana.superadmin` (6; `MetasController::store` aceita `business_id` alheio).

## Decisão proposta — opção B (ADR 0415)
Permissão de plataforma = scopes `admin_only` do catálogo MCP + `jana.superadmin` (lista
derivada, não escrita à mão). O dono de empresa não a herda pelo papel; passa quem está em
`administrator_usernames` ou tem a permissão de verdade. As outras ~345 permissões não mudam.
Rejeitadas: A (remendar caso a caso) e C (tirar o bypass inteiro → migração em todas as empresas).

## Impacto medido em produção (consulta só de leitura)
3 usuários com `Admin#1`. Wagner está em `administrator_usernames` → nada muda. Outros dois
perdem `jana.mcp.memory.manage` e `jana.cc.read.all`/`curate`, que hoje só tinham pelo bypass.
**Decisão [W] antes do merge:** conceder de verdade a quem deve manter.

## Fica em aberto (não é desta thread)
1. `jana.superadmin` segue concedível no editor de papéis da empresa → próximo PR.
2. `governance.*` como plataforma = passo 3 da 0392 (decisão de produto [W]).

## Para o índice (Cowork)
`D-GATE.respondida = true` quando o PR for mergeado. A thread 05 passa de BLOQUEADA a entregue.
