---
date: "2026-07-28"
topic: "MCP 'fora' era 403 de permissão: um save da role Operacional#1 tirou o gate jana.mcp.use e derrubou 4 dos 7 tokens ativos — invisível por ~6h porque o cache do Spatie servia o estado antigo"
authors: [C, W]
type: session
module: Jana
pii: false
related_adrs:
  - 0053-mcp-server-governanca-como-produto
  - 0093-multi-tenant-isolation-tier-0
  - 0062-separacao-runtime-hostinger-ct100
---

# MCP "fora do ar" — era permissão, não serviço

## Sintoma

`brief-fetch` falhou no SessionStart com "MCP retornou error JSON-RPC". Leitura natural: servidor caiu.

## O que estava de fato acontecendo

O serviço estava **saudável o tempo todo**. `POST /api/mcp` respondia `401` sem token (auth viva), container `oimpresso-mcp` Up e healthy, `restarts=0`. Com token válido, a resposta era:

> `User não tem permission 'jana.mcp.use'. Atribua via Spatie role/permission.`

O token (id=4, user 1 / WR23 / biz=1) estava válido e não revogado. O que faltava era o gate grosso que o `McpAuthMiddleware` exige.

## Causa raiz

O user 1 tem só a role `Operacional#1` (id 695). Essa role tem 209 permissions e **nenhuma `jana.mcp.*`** — o pacote MCP vive na `Admin#1`, e o user 1 não está entre os 6 dela. A role 695 foi salva em `2026-07-28 10:32:16` (relógio do app).

Duas suspeitas foram medidas e **descartadas**:

- rename `copiloto.*` → `jana.*` ([#4853](https://github.com/wagnerra23/oimpresso.com/pull/4853)) — 100% aplicado, **zero** permissions `copiloto.*` residuais;
- `MCP_TOOLS_EXPOSED` — correto, a rota estava registrada (o `404` inicial foi meu, testei `/mcp` em vez de `/api/mcp`).

## Por que demorou ~6h para aparecer

O cache de permissions do Spatie segurava o estado antigo. Houve `tools/call status=ok` até **13:30 UTC**; o container reiniciou **16:01:57 UTC** e limpou o cache; a partir daí passou a refletir o banco e negar.

Ressalva honesta: que o save de 10:32 foi o que *removeu* a permission é a explicação mais provável, **não um fato provado** — `model_has_roles`/`role_has_permissions` não guardam histórico e o `activity_log` só registra logins. Medido: a role foi alterada hoje, não tem as permissions MCP, e funcionava antes do restart.

## Raio de alcance — 4 de 7

| Usuário | Role | Antes |
|---|---|---|
| 1 WR23 | Operacional#1 | 🔴 bloqueado |
| 74 maiara-01 | Operacional#1 ×2 | 🔴 bloqueado |
| 12 felipe-01 | Operacional#1 ×2 | 🔴 bloqueado |
| 569 luizaugusto-01 | Operacional#1 ×2 | 🔴 bloqueado |
| 3 Eliana-01 | Admin#1 | 🟢 ok |
| 2 e 5 (serviço) | permissions diretas | 🟢 ok |

## Correção aplicada (autorizada por [W])

`jana.mcp.use` (id 303) atribuída à role `Operacional#1` (id 695) — o mínimo necessário, sem conceder Admin a ninguém. Tools de leitura exigem só esse gate; scopes finos (`tasks.write`, `cycles.manage`) seguem valendo para mutação.

Smoke real pós-fix: `initialize` → `200` (`serverInfo: oimpresso-mcp v0.1`) · `tools/list` → **15 tools** · `tools/call brief-fetch` → brief real (cycle, 4 HITL, 5 itens em voo).

## Por que o registro NÃO foi em seeder

A recomendação inicial (pôr `Operacional#1` no `McpScopesSeeder`) estava errada e foi revertida antes de virar código. O cabeçalho do próprio seeder declara: *"Atribuição aos roles é manual (cada cliente tem seus próprios roles `Admin#{biz}`)"*, e ele encerra imprimindo a instrução de fazer via tinker. O comando `mcp:assign-default-permissions` que ele cita **não existe** — é nota de "futuramente".

Fixar `Operacional#1` num seeder global hardcodaria uma role do biz=1 em código, contra o design multi-tenant do arquivo e contra a Camada 3 do modelo de permissões (atribuição é operação de UI em `/roles/{id}/edit`). O git nunca foi a fonte desse dado — não havia drift código↔prod a corrigir, e registrar em código criaria uma segunda fonte competindo com a UI.

## O que virou máquina

O ponto cego real não era o registro, era a **ausência de detecção**: quebrou às ~09:32 UTC e ninguém soube até 16:29. Foi adicionado o check `mcp_token_sem_permission` ao `jana:health-check` — estendendo o dono do tema, sem gate novo.

Ele conta users com token MCP ativo (não revogado/expirado) sem `jana.mcp.use`, por permission direta **ou** via role.

**FP e mordida medidos em prod ANTES de instalar:**

| cenário | resultado |
|---|---|
| árvore limpa (pós-fix) | **0 users** → zero falso-positivo |
| contrafactual (role sem a permission = estado do incidente) | **4 users** → morde |

Não escrevi teste Pest para o check: ele depende de `mcp_tokens` + tabelas Spatie, e um teste que nasça `skipped` seria verde por não-execução (LC-13). A evidência é o bite-test acima, medido contra o banco real e registrado no docblock com data.

## Aberto

- **Duplicata de role** — Maiara, Felipe e Luiz têm `Operacional#1` duplicada em `model_has_roles` (2 linhas cada). Inofensivo hoje, mas sinaliza processo que reatribui role sem checar existência. Não tocado.
- **`GET /` do mcp.oimpresso.com dá 500** (~15s) — `ViewException` em `Vite.php:960`, manifest do front. Recorrente desde 2026-07-26, S3. Não afeta o JSON-RPC.
- **`app.timezone = Europe/London`** no container MCP (app marca 17:38 com banco em 16:38 UTC). Desloca 1h todo timestamp que ele grava — audit, brief. Conferir o `.env` do container.
