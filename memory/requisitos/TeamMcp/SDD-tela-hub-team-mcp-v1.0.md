---
id: requisitos-team-mcp-sdd-hub-team-mcp-v1-0
slug: team-mcp-sdd
title: "SDD — Hub TeamMcp + Forja (governança do MCP server)"
type: sdd
module: TeamMcp
status: ativo
owner: W
version: 1.0.0
last_updated: "2026-07-28"
related_docs:
  - SPEC.md
  - BRIEFING.md
  - SUPERFICIE.md
  - forja-cockpit-visual-comparison.md
  - scorecard-visual-comparison.md
related_adrs:
  - 0053-mcp-server-governanca-como-produto
  - 0070-jira-style-task-management-current-md-removed
  - 0081-identity-mesh-mcp-actors
  - 0086-fase-5-mvp-governance-actiongate-warn
  - 0093-multi-tenant-isolation-tier-0
  - 0101-tests-business-id-1-nunca-cliente
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0283-handoff-loop-zero-paste
  - 0351-sdd-from-source
related_us:
  - US-TEAM-001
  - US-TEAM-002
  - US-TEAM-003
  - US-TEAM-004
  - US-TEAM-005
  - US-TEAM-006
  - US-TEAM-007
---

# SDD — Hub TeamMcp + Forja (governança do MCP server)

> **Derivado das fontes, não escrito do zero** ([ADR 0351](../../decisions/0351-sdd-from-source.md)).
> Triangulação **de 3 fontes, não 4** — ver §0.2: a fonte Delphi **não existe** neste módulo.

<!-- derivado: re-rodável do fonte -->

## §0 — Base empírica ⚙️/🖐

### 0.1 O que este SDD cobre (medido 2026-07-28, `origin/main`)

| Elo | Nº | Porta que mediu |
|---|---:|---|
| Telas `.tsx` roteadas | **5** | `find resources/js/Pages/team-mcp -name '*.tsx'` filtrado por `page-path.mjs` |
| Telas com `casos.md` | **2** | `Forja/Cockpit` · `Scorecard/Index` |
| UC declarados | **18** | 10 Forja + 8 Scorecard |
| UC com teste que os cita (antes deste PR) | **4** | `git grep -l <uc> -- 'Modules/*/Tests/*' 'tests/*' 'e2e/*'` |
| US no SPEC | **7** | `US-TEAM-001..007`, todas já com `Implementado em:` |
| Arquivos de teste do módulo | **26** | `Modules/TeamMcp/Tests/Feature/` |

> 🖐 **Foto datada.** Os números acima envelhecem. Re-derive com
> `node scripts/governance/requisitos-status.mjs TeamMcp` — mas leia antes o §0.3,
> porque hoje essa porta **não enxerga este módulo**.

### 0.2 Fonte 4 (Delphi / Office Comercial) — **NÃO EXISTE**, declarado

`find memory -iname "*ANTI-REGRESSAO*"` = **2 arquivos, ambos do Produto**. O TeamMcp
**nunca teve equivalente Delphi**: é módulo nascido nativo (governança do MCP server,
[ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md)), não migração do
Office Comercial. A fonte 3 (Blade AdminLTE) **também não existe** — o SPEC já declara em
`na_justified.D8.b`: *"TeamMcp já nasceu Inertia/React (sem Blade legacy)"*.

**Consequência honesta:** a triangulação aqui é de **2 fontes vivas** (documentação canon +
React/Laravel atual), não 4. Não há contrato de paridade a defender — logo **nenhum CU deste
SDD pode ser justificado por "o legado fazia assim"**. Onde falta contrato, falta mesmo; não
se inventa. (Regra da [ADR 0351](../../decisions/0351-sdd-from-source.md) Fase 1.1.)

### 0.3 A porta viva está CEGA pra este módulo (achado desta corrida)

`requisitos-status.mjs TeamMcp` imprime **`Telas (.tsx) 0`** e a frase
*"Nenhuma lacuna: toda tela tem caso com UC"* — sobre um módulo com **5 telas e 14 UC órfãos**.

**Raiz medida:** o script resolve a pasta como `resources/js/Pages/${mod}` (linhas 205 e 228,
`origin/main`), e aqui o módulo é `TeamMcp` mas a pasta é **`team-mcp`** (kebab). `readdirSync`
falha, `catch { return }` engole, e a ausência vira "sem lacuna".

**Blast radius contado:** 59 módulos com `SPEC.md`; **exatamente 1** tem a pasta só em kebab —
este. Não é defeito sistêmico, é um caso isolado, e é o pior possível: o módulo com **metade do
débito de UC órfão do repo inteiro** é o que a porta declara limpo.

> **Não corrigido aqui por escopo** (`scripts/**` é área proibida deste chip). Reportado ao
> parent — ver §9.1.

---

## §1 — Visão geral ⚙️

**Hub único de governança do MCP server canônico** (`mcp.oimpresso.com`, CT 100). Concentra:
identidade do time (Identity Mesh), tokens MCP, audit log append-only, Kanban Jira-style,
ingest de sessões Claude Code, e o cockpit do cowork loop (Forja).

**Família de telas — 5, sob dois prefixos de URL, um só topnav:**

| Tela | Rota | Controller | `casos.md`? |
|---|---|---|---|
| `Forja/Cockpit` | `/forja` · `/forja/{backlog,quadro,changelog,mcp}` | `ForjaController` | ✅ 10 UC |
| `Scorecard/Index` | `/team-mcp/scorecard` | `ScorecardController` | ✅ 8 UC |
| `Team/Index` | `/team-mcp/team` | `TeamController` | ❌ **gap** |
| `Tasks/Index` | `/team-mcp/tasks` | `TasksAdminController` | ❌ **gap** |
| `CcSessions/Index` | `/team-mcp/cc-sessions` | `CcSessionsController` | ❌ **gap** |

**Fusão 2026-06-16 (fato que explica a topologia):** o topnav próprio do TeamMcp
(`Resources/menus/topnav.php`) foi **deletado**; `config/core_topnavs.php['Forja']` virou o
**único** grupo que casa `/team-mcp/*` no `useAutoModuleNav`. Por isso `/forja` e `/team-mcp`
são prefixos distintos com **navegação idêntica** — 13 itens (9 próprios + 4 absorvidos).

**Vertical:** nenhuma. É módulo **interno do time** (`na_justified.D5` do SPEC): Wagner,
Felipe, Maiara, Luiz, Eliana. O cliente piloto biz=4 (ROTA LIVRE) **não usa por design**.

---

## §2 — Público-alvo e personas 🖐

<!-- curado: foto que envelhece -->

| Persona | Quem | O que faz aqui |
|---|---|---|
| **Wagner [W]** (L0/superadmin) | dono | emite/revoga token, aprova triagem na Forja, lê a saúde do MCP |
| **[F]/[M]/[L]/[E]** (L2/L3) | time interno | consomem tools MCP com token próprio; **não** administram o hub |
| **IA pareada** (actor `claude-code-*`) | agente | consome tools; toda ação é atribuída ao **humano parent** (§6 CU-TEAM-06) |

> ⚠️ **Não confundir com a persona do ERP.** Larissa (ROTA LIVRE) **nunca** abre estas telas.
> Todo aceite deste SDD assume operador **superadmin com `jana.mcp.usage.all`**.
> Validação de persona é de [W] — este quadro é derivado de `regras-time.md` + do SPEC.

---

## §3 — Governança aplicável (o Tier 0 que morde AQUI) ⚙️

| Invariante | Como se manifesta neste módulo |
|---|---|
| **Multi-tenant [ADR 0093]** | ⚠️ **inversão deliberada**: `mcp_actors` é **cross-tenant por design** (sem `business_id`) e o Scorecard/Forja são **repo-wide**. Está declarado em `na_justified.D1.a` do SPEC. O que **permanece** Tier 0: `mcp_tokens` herda `business_id` via `user_id` FK, e token de A **nunca** resolve actor B (§6 CU-TEAM-03) |
| **Teste biz=1, nunca biz=4 [ADR 0101]** | `ForjaRoutesSmokeTest` usa `seededTenant()` (biz=1) — está no cabeçalho do arquivo |
| **Audit append-only [ADR 0053]** | trigger MySQL bloqueia UPDATE/DELETE em `mcp_audit_log`; INSERT é o único DML |
| **Segredo Tier 0** | raw token retornado **1×**, nunca logado, nunca persistido em claro (`McpTokenIssuer::rotate`) |
| **`[V0]` valor/estoque** | **não se aplica** — nenhuma tela deste módulo toca preço, custo, margem ou estoque |
| **PII / LGPD** | `LgpdComplianceTest` + `PiiRedactor` no ingest de sessões CC |

> **Armadilha registrada:** `Gate::before` (`AuthServiceProvider`) libera **qualquer** ability
> pra quem tem `Admin#{business_id}`. Teste de "403 sem permissão" feito com admin **passa
> mesmo se o `can:` for removido** — falso-verde. Todo caso negativo deste módulo filtra
> usuário **não-admin** (aplicado em `ForjaRoutesSmokeTest`, #4887).

---

## §4 — Design system aplicável ⚙️

- **Shell:** `AppShellV2` + `PageHeader` canon (`@/Components/PageHeader`).
- **Padrão de tela:** Forja/Cockpit = shell com abas; Scorecard = **Facts+Checks**
  ([ADR 0091](../../decisions/0091-daily-brief.md)) — Facts = números sem juízo, Checks = semáforo ok/fail.
- **Sidebar PRETA (dark-fixo)** nos dois modos — [UI-0023], definitivo.
- **DS v6:** tokens semânticos (`success`/`warning`/`destructive`), `tabular-nums`, layout por
  `inline-flex`/`KpiGrid`; zero paleta crua, zero `rounded-xl+`.
- **Juiz da conformidade é gate, não UC** — `conformance-gate` + `layout-primitives` +
  `pageheader-gate` + `eslint ds/*`. Ver §6.5 (por que `UC-FORJA-06` foi rebaixado).

---

## §5 — Arquitetura ⚙️

### 5.1 Visão em camadas

Caminho REAL, medido em `origin/main` (símbolo + `grep` que re-localiza, não `arquivo:linha`):

```
/forja[/aba]          → ForjaController@{triagem,backlog,quadro,changelog,mcp}
                        → Forja{Backlog,Quadro,Changelog,Mcp}Service
                        → McpTask (project=FORJA) · cowork_handoffs
/team-mcp/scorecard   → ScorecardController@index
                        → ScorecardBuilderService::{buildFacts,buildChecks}
                        → mcp_tokens · mcp_audit_log · mcp_briefs
/team-mcp/team        → TeamController@index → TeamUsageAggregator → mcp_tokens/mcp_audit_log
  POST /team/{user}/token    → TeamController@gerarToken   → McpToken::gerar  (inline)
  DELETE /team/token/{token} → TeamController@revogarToken → revoked_at + soft-delete
/team-mcp/tasks       → TasksAdminController@index → McpTask/McpCycle (Jira-style, ADR 0070)
/team-mcp/cc-sessions → CcSessionsController@index → mcp_cc_sessions/_messages/_blobs
```

**Middleware (todas as rotas web):** stack UltimatePOS
`['web','SetSessionData','auth','language','timezone','AdminSidebarMenu','CheckUserLogin']`
**+ `can:jana.mcp.usage.all`** — exceto `/team-mcp/cc-sessions`, que exige `jana.cc.read.team`.

> ⚠️ **A permissão mudou de nome e a doc ficou pra trás.** Era `copiloto.mcp.usage.all`;
> virou `jana.mcp.usage.all` no [#4853](https://github.com/wagnerra23/oimpresso.com/pull/4853).
> Medido 2026-07-27: `git grep -l "copiloto\.mcp\.usage\.all" -- '*.php'` = **0** PHP vivo.
> Fonte da verdade = o construtor do controller, nunca a prosa.

**Inertia::defer é o default aqui** (rule `pages.md`): `facts`/`checks` (Scorecard),
`tickets`/`triagemCount`/`backlog`/`quadro`/`changelog`/`handoffs`/`heartbeat` (Forja),
`team`/`stats_globais` (Team) — todos deferidos. Só `meta` é eager (config + timestamp, 0 query).

### 5.2 Modelo de dados (núcleo)

| Tabela | `business_id`? | Nota Tier 0 |
|---|---|---|
| `mcp_actors` | ❌ **por design** | Identity Mesh cross-tenant ([ADR 0081]); governa time INTERNO, não clientes |
| `mcp_tokens` | herdado via `user_id` FK | `user_id NULL` = **violação Tier 0** (é um dos Checks do Scorecard) |
| `mcp_audit_log` | `business_id_efetivo` | append-only por trigger MySQL |
| `mcp_briefs` | — | frescor do brief é Check do Scorecard (<24h) |
| `mcp_tasks`/`_epics`/`_cycles` | — | Jira-style ([ADR 0070]); a Forja projeta `project=FORJA` |
| `mcp_cc_sessions`/`_messages`/`_blobs` | — | ingest Claude Code; PII redactada |
| `cowork_handoffs` | — | transporte do loop Cowork↔Code ([ADR 0283]) |

### 5.3 Fluxos críticos

**F1 — Emitir token MCP pra membro do time** (US-TEAM-002 · CU-TEAM-01)
`Team/Index.tsx` → `POST /team-mcp/team/{user}/token` (`team-mcp.team.token.gerar`) →
`TeamController@gerarToken` (valida por `IssueActorTokenRequest`) → **`McpToken::gerar` inline**
→ INSERT em `mcp_tokens` + entrada em `mcp_audit_log` → raw devolvido **1×** à UI.
> 🔴 **Dívida medida (já registrada no SPEC):** `McpTokenIssuer::issue` foi extraído na Wave 18
> mas **nunca religado à rota** — o único caller de produção é `RotateTokenCommand`. Logo há
> **dois caminhos** de emissão com regras potencialmente divergentes. Ver §9.

**F2 — Revogar token** (US-TEAM-003 · CU-TEAM-02)
`DELETE /team-mcp/team/token/{token}` → `TeamController@revogarToken` → seta `revoked_at` +
soft-delete → próxima chamada MCP falha em `McpToken::encontrarPorRaw` (`whereNull revoked_at`)
via `McpAuthMiddleware` → **401**. Mesma dívida do F1: `McpTokenIssuer::revoke` não religado.

**F3 — Ler a saúde do MCP** (CU-TEAM-08)
`/team-mcp/scorecard` → `ScorecardController@index` → `Inertia::defer` de
`ScorecardBuilderService::buildFacts()` e `::buildChecks()`.
**Facts** (7 chaves, sem juízo): `tokens_ativos` · `calls_7d` · `cost_7d_brl` ·
`users_ativos_7d` · `top_tools_7d` · `audit_log_present` · `tokens_table_present`.
**Checks** (5, cada um `{name, ok, detail}`): schema `mcp_tokens` · schema `mcp_audit_log` ·
brief recente <24h · tokens sem orphan · custo médio diário 7d sob cap.
> ⚙️ **Propriedade que este SDD registra e o teste passa a defender:** todo acesso a tabela no
> builder é guardado por `Schema::hasTable`. O serviço **degrada sem quebrar** quando o schema
> MCP não existe — é o que torna o contrato de forma testável **em sqlite**, sem MySQL.

**F4 — Cockpit Forja projeta o loop** (CU-TEAM-10)
5 rotas GET de aba → todas renderizam **o mesmo componente** `team-mcp/Forja/Cockpit` com prop
`tab` distinta. Não existe `/forja/saude` — Saúde foi fundida no Scorecard real.
> 🔴 **Achado saldado em [#4887]:** `ForjaRoutesSmokeTest` cobria isto e **nunca rodou uma
> vez** — o módulo não estava em lane nenhuma, e o teste falharia se rodasse (rota fantasma
> `forja.saude` + dataset associativo com `it()` de 2 argumentos). `7 failed / 5 passed` →
> `0 failed / 5 passed / 10 skipped`.

**F5 — Handoffs reais com gate cruzado** (CU-TEAM-11 · [ADR 0283])
`/forja/mcp` → `ForjaMcpService::handoffs()` projeta `cowork_handoffs` (status
`pending/applied/rejected/stale/superseded`; `stale` derivado na leitura, >3d) →
`deriveGate` cruza o `gate_status` **auto-reportado** com os required checks REAIS do PR
(`PrChecksResolver` → GitHub API). Divergência → badge **`conflito ack×checks`**.
Best-effort: sem token/rede → segue o `gate_status`, sem conflito falso.

**F6 — Navegação única do hub** (CU-TEAM-12)
`config/core_topnavs.php['Forja']['items']` (**9** itens) → `useAutoModuleNav` casa pelo 1º
segmento da URL. É o único grupo que casa `/team-mcp/*` desde a fusão.

### 5.4 Onde os dois mundos ainda não se conversam

Não há eixo Blade↔React aqui (§0.2). As descontinuidades reais são **outras**:

1. **Rota ↔ Service extraído** — `McpTokenIssuer::{issue,revoke}` existem, têm teste, e **não
   estão no caminho da rota** (F1/F2). Extração sem religação.
2. **Entity ↔ runtime** — `McpActor::canWriteModule()`/`isActionBlocked()` existem e são
   testados, mas **nenhum código de produção os chama**; o `ActionGate` ([ADR 0086]) tem alias
   registrado e está aplicado a **zero rotas**. US-TEAM-005 está honestamente `_parcial_`.
3. **sqlite ↔ MySQL** — a stack UltimatePOS não sobe em sqlite, então os testes de rota
   **pulam** na lane sqlite. Quem executa o happy-path é a lane MySQL `teammcp-pest.yml`, que
   hoje roda **um único arquivo** (catraca deliberada, ratchet-up).
4. **Aba MCP parcialmente mockada** — contrato/tokens/auditoria seguem MOCKADOS **por design
   declarado**; só a seção de handoffs é real (Fase 1 da [ADR 0283]).

---

## §6 — Casos de uso ⚙️/🖐

> **Convenção:** `✅` provado por teste verde que o cita · `🧪` teste escrito, veredito pendente
> da lane · `⬜` não-verificado (nenhum teste o cita) · `🔴` quebrado.
> **O status vem do veredito da lane, nunca da minha leitura** (G-7 · [ADR 0264]).
> Os `UC-*` citados são os que o `casos.md` da tela declara e o Pest cita pelo id.

#### CU-TEAM-01 — Emitir token MCP pra membro do time `[must]` `[T0]` ⬜
*Dado* [W] autenticado com `jana.mcp.usage.all`; *quando* emite token pra um user;
*então* nasce token único (≥32 bytes) em `mcp_tokens` com `actor_id` linkado, o raw aparece
**1×** e o issue é registrado em `mcp_audit_log`.
1. `[must]` raw devolvido uma única vez; nunca logado, nunca persistido em claro
2. `[T0]` token nasce vinculado a `user_id` — `user_id NULL` é violação (é Check do Scorecard)
3. `[must]` INSERT correspondente em `mcp_audit_log`
→ US-TEAM-002 · F1. **Dívida:** dois caminhos de emissão (§5.4.1).

#### CU-TEAM-02 — Revogar token comprometido `[must]` `[T0]` ⬜
*Dado* token ativo; *quando* [W] revoga; *então* `revoked_at` é setado e a **próxima** chamada
MCP com aquele token recebe **401**.
1. `[must]` revoke é imediato — não espera expiração
2. `[must]` soft-delete preserva o registro (audit LGPD: `revoked_at` + `revoked_by`)
3. `[T0]` revoke de token de **outro** user retorna null, sem efeito colateral
→ US-TEAM-003 · F2. Coberto em comportamento por `Wave23ScorecardRotateTest` (rotate/revoke),
que **não cita** este CU.

#### CU-TEAM-03 — Token de A nunca resolve actor B `[must]` `[T0]` ⬜
*Dado* tokens distintos de A e B; *quando* o resolver os traduz; *então* cada um resolve **só**
o próprio actor, e capabilities não vazam.
1. `[T0]` `ActorResolver::byId(A)` nunca devolve B
2. `[T0]` `modules_write`/`modules_blocked` não cruzam entre actors
3. `[must]` `slug` unique impede duplicação
→ US-TEAM-004. Comportamento coberto por `MultiTenantTokenIsolationTest` (8 cenários) — que
**não cita** este CU.

#### CU-TEAM-04 — Onboarding de actor com manifest declarado `[must]` ⬜
*Dado* membro novo; *quando* o seeder roda; *então* nasce actor com trust_level +
modules_write/read/blocked + `parent_actor_id`, e rodar 2× não duplica.
1. `[must]` seeder idempotente (2× = 5 rows)
2. `[must]` `parent_actor_id` de não-Wagner aponta pra `wagner.id`
3. `[must]` Maiara **blocked** em `NfeBrasil`+`RecurringBilling` (fiscal só [E] ou [W])
→ US-TEAM-001. Coberto por `McpActorsSeederTest` + `ActorPermissionMatrixTest`, sem citação.

#### CU-TEAM-05 — Permissão por tool/módulo barra execução `[must]` 🔴
*Dado* actor sem write no módulo; *quando* invoca tool que escreve nele; *então* **é barrado**.
1. `[must]` `canWriteModule()` respeita write + blocked + wildcard
2. `[must]` `isActionBlocked()` consulta `actions_blocked`
3. 🔴 **`ActionGate` enforce em runtime — NÃO LIGADO** (alias registrado, zero rotas)
→ US-TEAM-005 (`_parcial_` no SPEC). **Este CU é falso hoje**: a regra existe no entity e é
testada, mas nada em produção a chama. Ver §9 e §5.4.2.

#### CU-TEAM-06 — Ação de IA é atribuída ao humano parent `[must]` ⬜
*Dado* actor IA com `parent_actor_id`; *quando* age; *então* a auditoria mostra o **humano**.
1. `[must]` `effectiveHumanSlug()` resolve IA→parent.slug
2. `[must]` parent revogado → **fallback** pro slug da própria IA (degradação graciosa)
→ US-TEAM-006. Nota de precisão: `effectiveDisplayName` vive no `ActorResolver`, não no
`McpActor` como o aceite do SPEC sugere.

#### CU-TEAM-07 — Audit log é append-only `[must]` `[T0]` ⬜
*Dado* entrada em `mcp_audit_log`; *quando* alguém tenta UPDATE/DELETE; *então* o banco recusa.
1. `[T0]` trigger MySQL bloqueia UPDATE e DELETE; INSERT é o único DML
2. `[must]` `audit_required=true` em actor L3 → entrada por chamada
→ US-TEAM-007. **Schema e trigger vivem em `Modules/Jana`**, não neste módulo — o SPEC já
registra. Aceite do SPEC diz "SPEC-only por ora": segue sem teste.

#### CU-TEAM-08 — Ler a saúde do MCP sem juízo fabricado `[must]` 🧪
*Dado* [W] em `/team-mcp/scorecard`; *quando* a tela carrega; *então* vê **Facts** (números
crus) e **Checks** (semáforo), e **nada** que não tenha sido medido.
1. `[must]` `buildFacts()` devolve as 7 chaves canônicas com os tipos certos → `UC-SC-03`
2. `[must]` cada Check tem `{name, ok:bool, detail}` → `UC-SC-04`
3. `[must]` **nenhuma série temporal** no payload — a tela não pode desenhar sparkline
   fabricado → `UC-SC-05`
4. `[must]` sem schema MCP o builder **degrada** (`ok=false` + detail), não explode → `UC-SC-01`
→ F3. **Coberto neste PR** por `ScorecardContratoTest`.

#### CU-TEAM-09 — O hub inteiro exige `jana.mcp.usage.all` `[must]` `[T0]` 🧪
*Dado* usuário autenticado **sem** a permissão; *quando* abre qualquer tela do hub; *então*
recebe **403**. Anônimo é barrado antes, pelo `auth`.
1. `[T0]` sem a permissão → 403 (o hub é repo-wide cross-business: vazar = expor governança
   de **todos** os businesses a qualquer funcionário logado)
2. `[must]` o caso negativo usa usuário **não-admin** — com admin o `Gate::before` libera tudo
   e o teste vira decorativo
→ `UC-FORJA-07` (Forja, coberto) · `UC-SC-08` (Scorecard, **coberto neste PR**).

#### CU-TEAM-10 — Cockpit Forja projeta o loop sem dado fantasma `[must]` 🧪
*Dado* [W] no cockpit; *quando* abre cada aba; *então* as **5** rotas renderizam
`team-mcp/Forja/Cockpit` com a prop `tab` correta, e o que é derivado é **rotulado** como
sugestão, não apresentado como medido.
1. `[must]` 5 abas, não 6 — `/forja/saude` **não existe** → `UC-FORJA-01`
2. `[must]` valor×esforço e risco Tier-0 são **sugestão derivada rotulada** → `UC-FORJA-10`
3. `[must]` o shell não muta nada: as rotas de aba são **GET-only** → `UC-FORJA-05`
→ F4. Perna 3 **coberta neste PR**.

#### CU-TEAM-11 — Gate do handoff cruza o ack com os checks reais `[must]` 🧪
*Dado* handoff `applied` cujo `gate_status` auto-reportado diz verde; *quando* o PR real tem
required check vermelho/pendente; *então* aparece **`conflito ack×checks`**.
1. `[must]` ack verde + checks verdes → `gate ok`
2. `[must]` ack verde + checks vermelhos/pendentes → `conflito`
3. `[must]` GitHub indisponível → **degrada** pro `gate_status`, sem conflito falso
→ F5 · `UC-FORJA-12`/`UC-FORJA-13`, cobertos por `ForjaMcpServiceTest` (19 casos).

#### CU-TEAM-12 — A navegação do hub é única e não tem item fantasma `[should]` 🧪
*Dado* qualquer tela do hub; *quando* o topnav renderiza; *então* são **9** itens e **todo**
`href` resolve pra uma rota registrada.
1. `[should]` 13 itens: 9 próprios da Forja + 4 do TeamMcp absorvido
   (6º próprio = `Roadmap (Gantt)`, adicionado 2026-08-06 — ver UC-FORJA-02 no `Cockpit.casos.md`)
2. `[must]` nenhum item aponta pra rota inexistente — é exatamente a classe do
   `forja.saude` fantasma → `UC-FORJA-02`
→ F6. **Coberto neste PR** (cruza config × router, duas fontes independentes).

#### CU-TEAM-13 — Telas do hub são read-only `[must]` 🧪
*Dado* qualquer tela de leitura do hub; *quando* o operador navega; *então* nenhuma ação
escreve no banco sem confirmação explícita.
1. `[must]` rotas de aba da Forja são GET-only → `UC-FORJA-05`
2. `[must]` Scorecard só recarrega props deferidas → `UC-SC-07`
3. `[must]` mutação na Triagem só sob `AlertDialog` de confirmação [W] → `UC-FORJA-10`

### §6.4 Non-Goals — **só [W] preenche** 🖐

> ⛔ O agente é **proibido de inferir** Non-Goal ([ADR 0351] Fase 2.6). Os itens abaixo são os
> que já estão escritos **em fonte canônica existente** — SPEC `## Não-escopo` e `SCOPE.md` —
> apenas **apontados**, não criados:
>
> - ❌ Chat IA conversacional → `Modules/Jana`
> - ❌ Knowledge browsing (ADRs/sessions UI) → `Modules/KB`
> - ❌ Skills governance + Brain A/B → `Modules/ADS`
> - ❌ Policies executáveis runtime → `Modules/Governance` (Fase 5)
> - ❌ UI de dashboard de audit → `Modules/Governance` (per `SCOPE.md`)
>
> **Pendente de [W]:** nada aqui declara se a **aba MCP mockada** (contrato/tokens/auditoria)
> é Non-Goal permanente ou Fase 2 adiada. Ver §9.2.

### §6.5 Rebaixados de UC — registro, não perda

Dois blocos deixaram de ser UC em 2026-07-27 (#4879) e **este SDD ratifica a decisão**:

- **`UC-FORJA-06` (DS v6)** → conformidade não é caso de *uso*; o juiz é `conformance-gate`.
  Mantê-lo como UC só produzia órfão permanente. ⚠️ **`UC-SC-06` é o gêmeo idêntico e
  continua UC** no Scorecard — incoerência viva, ver §9.3.
- **`UC-FORJA-04` (colisão de topnav)** → ficou **infalsificável**: não existe mais "o topnav
  da Equipe" pra diferir (foi deletado na fusão). Um UC que só pode passar não defende nada.
- **`UC-FORJA-11` (badge estático)** → limitação de plataforma, não promessa de produto.

---

## §7 — Requisitos não-funcionais ⚙️

| NFR | Alvo | Onde vive |
|---|---|---|
| **Perf 1º paint** | props caras **sempre** `Inertia::defer` | rule `pages.md`; aplicado em todos os 5 controllers |
| **Observabilidade** | span por operação | `OtelHelper::spanBiz` — `teammcp.scorecard.build_{facts,checks}`, `teammcp.token.{issue,revoke}` |
| **Degradação graciosa** | schema ausente → `ok=false` + detail | `Schema::hasTable` em todo acesso do `ScorecardBuilderService` |
| **Resiliência externa** | GitHub fora → sem conflito falso | `PrChecksResolver` best-effort (§F5) |
| **Segredo** | raw token só 1×, nunca em log | provado por `Wave23ScorecardRotateTest` (captura `Log::listen`) |

---

## §8 — Estratégia de qualidade e rollout ⚙️

**Duas lanes, propósitos diferentes — e a distinção importa:**

| Lane | O que roda | Força |
|---|---|---|
| `.github/ci-sqlite-pest.list` | **25 dos 26** arquivos do módulo (allowlist) | emite `pest-ci-junit` → alimenta o manifesto por-UC |
| `teammcp-pest.yml` (MySQL) | **1** arquivo (`ForjaRoutesSmokeTest`), catraca ratchet-up | executa o happy-path que sqlite só **pula** |

> ⚖️ **Força do veredito — consultado no dono, não deduzido:** nenhuma das duas está em
> `governance/required-checks-baseline.json`. Ambas são **advisory**: reprovam visível, **não
> bloqueiam merge**. Dizer o contrário seria fazer a prosa soar mais forte que o enforcement.

**O que "verde" significa aqui (G-7):** o `✅` de um UC é **derivado** do manifesto
`scripts/casos-test-results.json`, que o `casos-results-publish` monta do JUnit do CI.
**Não se escreve à mão.** Por isso todo UC coberto neste PR está `🧪`, não `✅` — eu não rodei
teste (CT 100/CI é o venue, [ADR 0062]).

**Armadilha do skip:** em sqlite, teste de rota `markTestSkipped` → o veredito que chega ao
manifesto é `skip`, **nunca** `pass`. Skip **não é** cobertura. Por isso os testes novos deste
PR foram desenhados pra **executar em sqlite** (route registry, config e builder puro — sem
schema UltimatePOS), em vez de pular esperando a lane MySQL.

---

## §9 — Riscos e dívidas conhecidas 🖐

### 9.1 Reportado ao parent (fora da minha área — não consertei)

| # | Achado | Arquivo (proibido a este chip) |
|---|---|---|
| R1 | **Porta viva cega pro módulo** — `Pages/${mod}` não resolve `team-mcp`; imprime "0 telas / nenhuma lacuna" sobre 5 telas e 14 UC órfãos. 1 de 59 módulos afetado (§0.3) | `scripts/governance/requisitos-status.mjs` |
| R2 | **Teste novo precisa de linha na allowlist** — `ScorecardContratoTest.php` não roda até ser listado | `.github/ci-sqlite-pest.list` |

### 9.2 Dívida de produto (decisão de [W])

- **D1 — Serviço extraído e não religado** (§5.4.1): `McpTokenIssuer::{issue,revoke}` fora do
  caminho da rota. Dois caminhos de emissão/revogação = risco de divergência silenciosa em
  operação **Tier 0 (segredo)**. Religar é mudança de comportamento → decisão [W].
- **D2 — `ActionGate` não enforça** (§5.4.2 · CU-TEAM-05 🔴): a permissão por módulo é hoje
  **decorativa em runtime**. É o maior gap de segurança do módulo.
- **D3 — Aba MCP mockada**: Non-Goal permanente ou Fase 2? Sem declaração de [W], fica ambíguo.
- **D4 — 3 telas sem `casos.md`** (`Team/Index`, `Tasks/Index`, `CcSessions/Index`).
  **Deliberadamente não criei** neste PR: escrever UC sem teste que os cite geraria órfãos que
  o `casos-gate` G-2 pune e que **bloqueiam o merge de quem for atendê-los**. Próxima corrida.

### 9.3 Incoerência viva registrada

`UC-SC-06` (DS v6, Scorecard) é **o gêmeo** do `UC-FORJA-06` que foi rebaixado a prosa por ser
"restrição de conformance cujo juiz é gate". Os dois deveriam ter o mesmo destino.
**Não rebaixei o `UC-SC-06` nesta corrida** — o chip manda não reescrever contrato existente
sem motivo medido, e o motivo aqui é coerência, não medição nova. Fica como decisão explícita
pra [W] ou pra corrida que tratar o Scorecard.

---

## §10 — Roadmap de evolução 🖐

> [W] prioriza. Derivado das dívidas acima + US pendentes do SPEC.

| Trilha | Próximo passo | Depende de |
|---|---|---|
| **Segurança** | ligar `ActionGate` em runtime (fecha CU-TEAM-05 🔴) | decisão [W] · [ADR 0086] |
| **Segurança** | religar `McpTokenIssuer` à rota (fecha D1) | decisão [W] — muda caminho Tier 0 |
| **Contrato** | `casos.md` das 3 telas restantes, **com teste junto** | corrida seguinte |
| **Contrato** | citar CU-TEAM-02/03/04 nos testes que já cobrem o comportamento | barato — 3 citações |
| **Lane** | ratchet-up do `teammcp-pest.yml` (candidatos: `SmokeRoutesTest`, `TokensListAndRevokeTest`) | verde provado 1× antes de entrar |
| **Máquina** | corrigir R1 (porta cega) | fora deste chip |

---

## §11 — Referências ⚙️

- [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md) — MCP server como produto
- [ADR 0081](../../decisions/0081-identity-mesh-mcp-actors.md) — Identity Mesh (mãe)
- [ADR 0086](../../decisions/0086-fase-5-mvp-governance-actiongate-warn.md) — ActionGate Fase 5
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — Tier 0 + biz=1
- [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) — trio + G-2/G-7
- [ADR 0283](../../decisions/0283-handoff-loop-zero-paste.md) — loop de handoff
- [ADR 0351](../../decisions/0351-sdd-from-source.md) — este método
- [SPEC.md](SPEC.md) · [BRIEFING.md](BRIEFING.md) · [SUPERFICIE.md](SUPERFICIE.md)
- Contratos: [`Forja/Cockpit.casos.md`](../../../resources/js/Pages/team-mcp/Forja/Cockpit.casos.md) · [`Scorecard/Index.casos.md`](../../../resources/js/Pages/team-mcp/Scorecard/Index.casos.md)

---

**v1.0.0** (2026-07-28) — SDD inicial derivado do fonte (chip Onda 4 do passo 5). 13 CU
(`CU-TEAM-01..13`) derivados das 7 US + 18 UC já contratados. Fonte 4 (Delphi) e fonte 3
(Blade) **declaradas inexistentes** (§0.2). Registra a porta viva cega (§0.3) e 4 dívidas.
