---
status: proposal
title: "MCP é Forja, Jana é usuário — a permissão de plataforma sai do papel de tenant e o ProjectMgmt assume o nome que já exerce"
proposed_by: Claude — decisão [W] 2026-07-30 "MCP desenvolvimento, Jana Usuario" · "MCP deveria estar na Forja" · "leia o ProjectMgmt, ele deve se tornar a Forja" · "MCP é Forja"
proposed_at: 2026-07-30
relates_to:
  - 0053-mcp-server-governanca-como-produto
  - 0062-separacao-runtime-hostinger-ct100
  - 0081-identity-mesh-mcp-actors
  - 0088-module-rename-php-only
  - 0093-multi-tenant-isolation-tier-0
  - 0092-tabela-rename-copiloto-para-jana
---

# MCP é Forja, Jana é usuário

> **Origem:** o incidente de 2026-07-29 ([#5060](https://github.com/wagnerra23/oimpresso.com/pull/5060)) — um save em `/roles/695/edit` apagou os 17 `jana.mcp.*` e derrubou o MCP dos 4 devs. Investigando o *porquê estrutural*, [W] perguntou: *"a Jana e o MCP não deveriam ter escopo diferente? Jana para clientes e MCP para desenvolvimento?"*
>
> **Este doc não reabre o mérito da deleção dos 6 módulos** ([proposta de ordem topológica](2026-07-30-deprecar-6-modulos-governanca-ordem-topologica.md)). Ele corrige **o receptor**: os planos mergeados hoje mandam MCP e Brief para dentro da **Jana**; a decisão [W] de hoje é **Forja**.

## A medição (recibo — leia antes do plano)

**Sistema medido:** produção `u906587222_oimpresso`, `APP_ENV=live`, 2026-07-30.
**Controle positivo:** `business=82` · `users=124` · `roles=472`.

### 1. O MCP nunca foi multi-tenant — o schema já dizia

| | |
|---|---:|
| Tabelas `mcp_*` **sem** `business_id` | **47 de 62** |
| …incluindo o núcleo inteiro | `mcp_tasks` · `mcp_cycles` · `mcp_epics` · `mcp_tokens` · `mcp_scopes` · `mcp_workflows` · `mcp_views` · `mcp_handoff_*` · `mcp_task_*` |
| Businesses com token MCP ativo | **1 de 82** (biz=1 · 7 users · 21 tokens) |

O `MultiTenantTokenIsolationTest` do próprio TeamMcp declara textualmente:
*"`mcp_actors` SÃO cross-business (sem `business_id`) **por design** — ADR 0081"*. E o que ele
isola é **dev × dev**, nunca tenant × tenant.

Do outro lado, a Jana **é** tenant: **9 de 17** tabelas `jana_*` têm `business_id`, o
`BriefDiarioAgent` recebe `businessId` no constructor (*"Tier 0 mecânico — nunca no prompt"*), e
há conversa real fora do biz=1 (**biz=164**: 4 · **biz=4**: 2).

### 2. O atrito é um ponto só — e foi ele que quebrou

`roles.business_id` é **NOT NULL**. Logo uma capacidade de **plataforma** só pode ser carregada
por um papel de **tenant**. Foi por isso que `jana.mcp.use` teve que morar em `Operacional#1`
(biz=1), e por isso um save de papel de tenant conseguiu apagá-la.

O diagnóstico do incidente estava incompleto: não é *"o formulário não renderiza os scopes"* — é
**a permissão está guardada no objeto errado**, e o formulário está certo em não conhecê-la.

Corolário que condena parte do fix do #5060: ele fez 22 permissões de plataforma renderizarem na
tela de papéis dos **82 tenants**. Para 81 é ruído, e institucionaliza o endereço errado. Ele
estanca o sangramento; não é a arquitetura.

### 3. Existem dois sistemas de autorização, e o MCP usa o que não é dele

| Sistema | Tenant? | Quem consome |
|---|---|---|
| `mcp_actors.capabilities` | **livre** (ADR 0081) | `Governance/ActionGate` + `TeamMcp/TasksAdminController` |
| Spatie `jana.mcp.*` | **preso ao business** | `McpAuthMiddleware` + trait `AuthorizesMcpMutation` — **o gate das tools** |

E `mcp_user_scopes` — o modelo de scope nativo do MCP — tem **0 linhas**. Nasceu e nunca foi usado.

### 4. O ProjectMgmt não *vai virar* a Forja: ele já é

| | |
|---|---:|
| Arquivos / telas | 50 / **19 páginas** |
| Tabelas **próprias** | **nenhuma** — é a UI web sobre `mcp_*` |
| Tabelas que consulta | `mcp_projects` · `mcp_jira_projects` · `mcp_jira_cycles` · `mcp_jira_epics` · `mcp_project_parts` · `mcp_tasks` |
| Permissions no papel de tenant | **nenhuma** |

Telas: `Triage` · `Backlog` · `Board` · `Burndown` · `Inbox` · `MyWork` · `Roadmap` · `Activity`.

O `/forja` do TeamMcp (`ForjaController`) tem: `triagem` · `backlog` · `quadro` · `changelog` —
**4 de 4 sobrepostas**. São duas implementações da mesma coisa, e a do ProjectMgmt é a grande.

Isso **fecha o 🔴 que o plano do TeamMcp deixou aberto** (*"Não há módulo receptor natural [para a
Forja] e é o único conteúdo genuinamente do TeamMcp"*): o receptor é o **ProjectMgmt**.

### 5. O código MCP mora na casa errada

`Modules/Jana` tem **554 arquivos**, dos quais **44 são `Mcp/`**. O TeamMcp tem 4, o Brief 1.
Ou seja: 90% do servidor MCP vive dentro do módulo de IA do **cliente**.

## Decisão proposta

1. **`Modules/ProjectMgmt` assume o nome Forja** — é o cockpit de desenvolvimento, dono das telas
   e das tabelas Jira-style. O `/forja` do TeamMcp é absorvido (não duplicado).
2. **O MCP passa a ser da Forja** — código (`Mcp/`), scopes e tokens saem da Jana.
3. **O gate das tools sai do Spatie de tenant.** Uma capacidade de plataforma não pode viver num
   objeto cujo dono é o business. O destino natural já existe e está vazio: `mcp_user_scopes`
   (0 linhas) e/ou `mcp_actors` (6 linhas, tenant-free por design, ADR 0081).
4. **A Jana fica com o que é do usuário** — chat, metas, custos, e o `BriefDiarioAgent`
   (ver a proposta irmã do Brief — [PR #5073](https://github.com/wagnerra23/oimpresso.com/pull/5073),
   ainda não em `main`, por isso a referência é ao PR e não ao arquivo).

## O que este doc NÃO propõe

- **Não** propõe deletar o ProjectMgmt (ele não está entre os 6).
- **Não** reabre se TeamMcp/Brief morrem — morrem. Muda **para onde vai o conteúdo**.
- **Não** propõe renomear tabelas `mcp_*` → `forja_*`. Precedente contrário explícito:
  [ADR 0092](../0092-tabela-rename-copiloto-para-jana.md) fez rename de tabela como big-bang e o
  custo é conhecido. As tabelas ficam.

## Errata aos planos mergeados hoje

| Plano | Diz | Passa a dizer |
|---|---|---|
| [`TeamMcp/DEPRECATION-PLAN`](../../requisitos/TeamMcp/DEPRECATION-PLAN.md) | *"7 de 8 linhas vão pra **Jana**"* · `mcp_tokens` → *"Receptor natural: `Modules/Jana`"* | receptor = **Forja** (exceto `SyncMemoryWebhookController`, ver aberto #2) |
| idem | `Services/Forja/*` + Cockpit = 🔴 *"buraco, sem receptor"* | receptor = **ProjectMgmt** |
| [`Brief/DEPRECATION-PLAN`](../../requisitos/Brief/DEPRECATION-PLAN.md) | `brief-fetch` → *"Candidato natural: `Modules/Jana`"* | **Forja** — ver proposta irmã |

## Blast radius medido — e por que agora é o momento mais barato

A [ADR 0088](../0088-module-rename-php-only.md) adiou o rename `copiloto.*` citando
*"5993 clientes ROTA LIVRE com permissions no DB"*. **Para a família MCP esse argumento não vale:**

| | |
|---|---:|
| Linhas em `role_has_permissions` | **39** |
| Linhas em `model_has_permissions` | **11** |
| **Total a migrar** | **50 linhas** |
| Businesses afetados | **2 de 82** |
| Tokens ativos a preservar | 21 |

Cinquenta linhas em dois businesses. Nunca vai estar mais barato.

## Riscos

| # | Risco | Grau | Contenção |
|---|---|---|---|
| R1 | **Rename de família de permissão já mordeu 4×** ([#4853](https://github.com/wagnerra23/oimpresso.com/pull/4853)/[#4886](https://github.com/wagnerra23/oimpresso.com/pull/4886)/[#4928](https://github.com/wagnerra23/oimpresso.com/pull/4928) + o incidente de ontem) | **ALTA** | **Máquina, nunca `sed`**: catálogo como fonte única + comando idempotente + gate de paridade. Regra [W]: *"arrumar a origem dos dados é melhor que fazer na mão"* |
| R2 | Time cego durante a janela (21 tokens) | **ALTA** | Gate novo **aditivo** primeiro (aceita nome velho E novo), migra, só então remove o velho |
| R3 | `SyncMemoryWebhookController` morre sem receptor → canon para de sincronizar **em silêncio** (o plano do TeamMcp já marca como a mais grave) | **CRÍTICA** | Realocar **antes** de qualquer remoção + smoke com commit real em `memory/` |
| R4 | Ruído nas 82 telas de papel persiste até o gate sair do Spatie | média | Fase 1 (audiência no catálogo) resolve sozinha e é reversível |

## Execução proposta (fases — cada uma decidível)

| Fase | O quê | Reversível? |
|---|---|---|
| **F1** | Catálogo ganha `audiencia: plataforma \| produto`; `user_permissions()` filtra pelo business da sessão → os 81 tenants param de ver os 22 checkboxes | ✅ sim |
| **F2** | Gate aditivo: middleware/trait aceitam `forja.*` **e** `jana.mcp.*` | ✅ sim |
| **F3** | Comando idempotente migra as 50 linhas + seeder passa a semear `forja.*` | ✅ sim |
| **F4** | `Mcp/` sai de `Modules/Jana` → Forja (PHP-only, padrão ADR 0088) | ⚠️ |
| **F5** | ProjectMgmt vira Forja (rota `/project-mgmt` → `/forja` com 301) | ⚠️ |
| **F6** | Remove o gate velho `jana.mcp.*` | ❌ |

## Gate de reversão

Se em F2/F3 qualquer token do time deixar de responder `200` em `POST /api/mcp`, **para** e volta
pro nome velho (o gate aditivo garante que isso é só remover a migração de linhas).

## Aberto — precisa de [W]

1. **O nome do namespace:** `forja.*` (proposto) ou `mcp.*`?
2. **`SyncMemoryWebhookController`** — Forja ou Jana? Ele escreve em `mcp_memory_documents`, que é
   tabela da Jana **e tem `business_id`**. É o único ponto onde a fronteira não é limpa.
3. **O resíduo `Copiloto`** (lang 31 · config 228 · log channel 247 · `copiloto_module` 3) entra
   nesta migração ou continua como fachada da [ADR 0088](../0088-module-rename-php-only.md)?
