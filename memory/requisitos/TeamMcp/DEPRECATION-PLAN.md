---
id: requisitos-teammcp-deprecation-plan
---

# DEPRECATION-PLAN — Modules/TeamMcp

> **Status:** 📋 Planejado · **Owner:** [W] · **Decisão:** [W] 2026-07-30 (*"todos esses eu vou deletar"*)
> **Ordem no conjunto:** **5º de 6** — [proposal da ordem topológica](../../decisions/proposals/2026-07-30-deprecar-6-modulos-governanca-ordem-topologica.md)
> 🔴 **Duas travas duras:** guarda o **acesso do time ao MCP**, e teve um gate promovido a **required 3 dias antes** desta decisão.

## Fase 1 — Inventário

**Gerado:** [`SUPERFICIE.md`](SUPERFICIE.md) — **105 arquivos em 15 papéis** (`module-surface.mjs TeamMcp --write`). Frescor 2026-07-30: `--check` **exit 0**.

Contornos: **0** telas em `Pages/TeamMcp/` (as telas vivem em `Pages/team-mcp/` — kebab, com `Forja/Cockpit.charter.md`) · **0** arquivos em `Routes/` · **4 tools MCP** em `Mcp/Tools/` · **0** cron em `Kernel.php`.

## Fase 2 — Estado em produção (medido ANTES de planejar)

**Sistema medido:** `APP_ENV=live` · `u906587222_oimpresso` · 385 tabelas · **2026-07-30**.
**Controle positivo:** `business=82` · `users=124` · `transactions=75.255`.

| Tabela | Linhas | Escrita mais recente |
|---|---:|---|
| **`mcp_tokens`** | **26** | 2026-06-17 21:12 |
| `mcp_ingest_heartbeat` | 28 | 2026-07-21 17:55 |
| `mcp_actors` | 6 | 2026-05-05 23:25 |
| `cowork_handoffs` | 1 | 2026-06-18 12:13 |

**Consequência 1 — volume desprezível, criticidade máxima.** 61 linhas somadas. Mas `mcp_tokens` (26) é **o que autentica o time nas tools MCP** e `mcp_actors` (6) é o identity mesh ([ADR 0081](../../decisions/0081-identity-mesh-mcp-actors.md)). Poucas linhas, dano alto.

**Consequência 2 — o heartbeat parou há 9 dias.** Última escrita em `mcp_ingest_heartbeat` foi **2026-07-21**; hoje é 30/07. Ou o watcher de ingest morreu, ou já não é usado. **Isso é achado, não conclusão** — não medi o produtor.

⚠️ CT 100 **não medido** — e aqui é o mais grave do conjunto: o MCP server é `mcp.oimpresso.com`, no CT 100.

## Fase 3 — Acoplamento externo

`git grep -lF 'Modules\TeamMcp\'` fora da pasta → **10 arquivos de código** (11 com o charter):

| Acoplador | Sobrevive? |
|---|---|
| `Modules/Jana/Mcp/OimpressoMcpServer.php` | ✅ **registra as 4 tools** — patch obrigatório |
| `Modules/Jana/Http/routes.php` | ✅ sim |
| `Modules/Jana/Mcp/Tools/WhatsActiveTool.php` | ✅ sim |
| `Modules/Jana/Services/TaskRegistry/HitlEscalationService.php` | ✅ sim |
| `Modules/Governance/Http/Middleware/ActionGate.php` | ❌ morre (6º) |
| `Modules/Governance/Providers/GovernanceServiceProvider.php` | ❌ morre (6º) |
| `Modules/Governance/Services/Checkers/IngestLivenessChecker.php` | ❌ morre (6º) |
| `Modules/Governance/Tests/Feature/IngestLivenessCheckerTest.php` | ❌ morre (6º) |
| `Modules/ADS/Http/Requests/ExecuteToolRequest.php` | ❌ morre (4º) |
| `Modules/ADS/Routes/web.php` | ❌ morre (4º) |
| `resources/js/Pages/team-mcp/Forja/Cockpit.charter.md` | ❌ sai com o módulo |

**4 sobrevivem — todos no Jana.** É o Jana que herda ou perde as tools.

## Fase 4 — Decisão por tabela

| Tabela | Decisão | Por quê |
|---|---|---|
| **`mcp_tokens`** | ⛔ **MIGRATE obrigatório** — nunca DROP | 26 tokens = acesso do time. DROP = Felipe/Maiara/Luiz cegos. Receptor natural: `Modules/Jana` (já dono de `mcp_tokens` nas migrations dele). |
| `mcp_actors` | **MIGRATE** | identity mesh, [ADR 0081](../../decisions/0081-identity-mesh-mcp-actors.md) |
| `mcp_ingest_heartbeat` | **DROP** se o produtor estiver morto · **MIGRATE** se vivo | 28 linhas, parado há 9 dias — medir o produtor antes (E1) |
| `cowork_handoffs` | **ARCHIVE** (1 linha) | append-only + HMAC ([ADR 0283](../../decisions/0283-handoff-loop-zero-paste.md)) — 1 linha cabe em seeder |

**Ordem:** MIGRATE/DROP **depois** do refactor (lição E3 do SRS).

## Destino por função — realocação

> Medido 2026-07-30. Dos 10 acopladores de código, **4 sobrevivem e os 4 são da Jana** — inclusive o `OimpressoMcpServer`. Isso não é coincidência de acoplamento: em cada linha abaixo, **o dado já é da Jana ou o servidor que consome já é da Jana**.

| Peça | Módulo dono correto | Base da decisão |
|---|---|---|
| `Services/McpTokenIssuer` · `ActorResolver` · `McpActorRepository` + `Entities/McpActor` + `mcp_tokens` (26) + `mcp_actors` (6) | **Jana** ⛔ Tier 0 | quem autentica e registra as tools é o `OimpressoMcpServer` da Jana ([ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md)); o emissor de token não pode viver fora do dono do servidor. ⚠️ **Contradiz o [`SCOPE.md` do Governance](../../../Modules/Governance/SCOPE.md)**, que declara *"tokens MCP + Identity Mesh → Modules/TeamMcp"* — essa fronteira **caduca** com o módulo, e a errata vai no mesmo PR. |
| 4 tools `Handoff*` (`Ack`/`Lever`/`Pending`/`Submit`) | **Jana** | já são **registradas** pelo `OimpressoMcpServer`. Mudam de pasta, não de servidor. |
| `HandoffIngestService` · `HandoffLeverService` + `Entities/CoworkHandoff` + `cowork_handoffs` (1) | **Jana** | [ADR 0283](../../decisions/0283-handoff-loop-zero-paste.md); o consumidor sobrevivente é `Jana/Services/TaskRegistry/HitlEscalationService` |
| `CcIngestService` · `IngestLivenessService` + `McpIngestHeartbeat` + `mcp_ingest_heartbeat` (28) | **Jana** | as tabelas irmãs (`mcp_cc_sessions`/`_messages`/`_blobs`) **já são da Jana**. ⚠️ decidir junto com a Fase 4: se o produtor estiver morto (parado desde 21/07), é DROP e não move nada. |
| **`Http/Controllers/Mcp/SyncMemoryWebhookController`** | **Jana** ⛔ **Tier 0 — o mais silencioso** | é o webhook GitHub → `mcp_memory_documents` (tabela da Jana) que faz o **canon chegar ao time**. Se sair sem receptor, Felipe/Maiara/Luiz param de ver doc novo por busca e **nada alarma**: a busca velha responde. Sintoma sem sinal. |
| `GitMainResolver` · `PrChecksResolver` | **Jana** | servem o loop de handoff; sem consumidor fora dele |
| `TeamUsageAggregator` · `UsageCsvExporter` + tela `team-mcp/CcSessions` | **Jana** | consumo/quota por ator pareia com `mcp_quotas`/`mcp_usage_diaria`, já da Jana |
| **`ScorecardBuilderService` + `Services/Forja/*` + tela `team-mcp/Forja/Cockpit`** (+ `charter` + `casos.md`) | 🔴 **BURACO — decisão [W]** | é o cockpit do loop Cowork↔Code. **Não há módulo receptor natural** e é o único conteúdo genuinamente do TeamMcp. Registro como buraco: inventar dono aqui seria pior que declarar ausência. |

**Leitura honesta:** 7 de 8 linhas vão pra **Jana**. Medido, o TeamMcp é uma **pasta de peças da Jana** com nome próprio — e a Forja é a única coisa que é dele de fato.

## Fase 5 — Riscos Tier 0

| # | Risco | Severidade | Mitigação |
|---|---|---|---|
| **R1** | **Time perde acesso ao MCP.** `mcp_tokens` autentica as tools que Felipe/Maiara/Luiz usam. | **ALTA** | MIGRATE antes de remover código; smoke com token real do time |
| **R2** | ~~Gate required órfão travando `main`~~ → **REBAIXADO por medição.** A [ADR 0354](../../decisions/0354-teammcp-pest-required-emenda-0314.md) (`decided_at: 2026-07-27`, `[W]`) declara `teammcp-pest` promovido a **required**, mas **o flip nunca chegou na proteção viva** (ver §Achado). Não trava merge. Sobra um problema de **canon**, não de CI. | ~~ALTA~~ → **média** | Errata da 0354 — append-only, sucessora, nunca editar. **NÃO é bloqueador da E4.** |
| **R3** | 4 tools MCP registradas no `OimpressoMcpServer` param de resolver | **ALTA** | Migrar as 4 pro Jana ou aposentar declaradamente |
| **R4** | Telas `/forja` + charter somem | média | Decidir receptor antes da E4 |
| **R5** | cross-tenant | **atenção** | `cowork_handoffs` é **sem `business_id` por design** (cross-tenant intencional, ADR 0093/0283) — não "consertar" no receptor |
| **R6** | **Canon para de sincronizar em silêncio.** `SyncMemoryWebhookController` morre → webhook GitHub sem destino → `mcp_memory_documents` congela. **Sem alarme:** a busca velha responde, só não tem doc novo — o time não descobre por erro, descobre por ausência. | **CRÍTICA — a mais grave deste módulo** | Realocar **primeiro** (E2b), antes de qualquer remoção. Validar com commit de teste em `memory/` + conferir chegada em `mcp_memory_documents`. |

### Achado — o required da ADR 0354 nunca existiu na proteção viva

Medido 2026-07-30 contra a **autoridade** (a proteção viva), não contra o baseline:

```bash
gh api repos/wagnerra23/oimpresso.com/branches/main/protection --jq '.required_status_checks.contexts[]'
```

→ **34 contexts**, e nenhum é `teammcp-pest`. Os únicos com `Pest` são `(Unit)`, `(Financeiro · MySQL)` e `(NfeBrasil · MySQL)`. O `governance/required-checks-baseline.json` também não o lista.

**Consequência boa:** deletar o TeamMcp **não deadlockará o `main`** — nenhum context required aponta pra ele. O R2 sai do caminho crítico.
**Consequência ruim:** a [ADR 0354](../../decisions/0354-teammcp-pest-required-emenda-0314.md) é uma promoção que **a máquina nunca executou** — canon afirmando enforcement inexistente, a classe [LC-10](../../LICOES_CODE.md). Isso vale **independente** da deprecação: mesmo se [W] mudar de ideia sobre deletar, ou o flip acontece ou a ADR ganha errata. Não deixar morrer junto com o módulo, senão o registro fica dizendo que houve um gate que nunca houve.

## Roadmap

| Etapa | O que | Gate [W] |
|---|---|---|
| **E1** | Medir CT 100 · medir o produtor do heartbeat (vivo ou morto?) | — |
| **E2** | Errata da ADR 0354 (o required nunca existiu na proteção viva — §Achado) | ✋ [W] ratifica — **não bloqueia a E4** |
| **E2b** | ⛔ Realocar `SyncMemoryWebhookController` → Jana + smoke do webhook com commit de teste em `memory/` | ✋ **[W] aprova — é o risco silencioso (R6)** |
| **E3** | MIGRATE `mcp_tokens` + `mcp_actors` pro receptor · smoke com token real do time (R1) | ✋ [W] confere |
| **E4** | Migrar as 4 tools MCP + patch nos 4 acopladores do Jana | ✋ [W] aprova |
| **E5** | Remover `Modules/TeamMcp/` + telas `/forja` + permissions + `modules_statuses.json` | ✋ [W] aprova |
| **E6** | Migration final (DROP/ARCHIVE conforme Fase 4) | ✋ [W] aprova |
| **E7** | Smoke real: tools respondem pelo receptor com token do time · rotas em 301/410 | ✋ [W] confere |
| **E8** | Lápide §5 + `BRIEFING` final | — |

## Resíduo honesto

- **CT 100 não medido** — bloqueante aqui mais que em qualquer outro dos 6: o MCP server vive lá.
- **Não medi o produtor do heartbeat.** "Parado há 9 dias" é o dado; "morreu" seria conclusão. A pergunta se responde no runtime, não no código (lápide §5 2026-07-17).
- **Não conferi se `mcp_tokens` do Hostinger é a MESMA tabela que o MCP server do CT 100 lê.** Se forem bancos diferentes, o R1 muda de forma.
- **Pest não rodado** (Tier 0 → CT 100) — e o gate dele é required.
