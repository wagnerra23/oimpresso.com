---
id: requisitos-jana-architecture
name: Jana — arquitetura viva
description: Arquitetura canônica da Jana, derivada do código por system-map.mjs. Separa topologia versionada, inventário e estado vivo.
type: architecture
authority: generated
lifecycle: ativo
---

# Arquitetura viva da Jana

> ⚙️ **Gerado por `scripts/governance/system-map.mjs` em 2026-07-28.** NÃO edite à mão.
> Esta página deriva o que o repositório consegue provar. Saúde de máquina é verificada por probe — compose existente não significa container vivo.
> Resumo do sistema inteiro: [`PAINEL-SISTEMA.md`](../../reference/PAINEL-SISTEMA.md). Decisões donas: [ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md), [ADR 0048](../../decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md) e [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md).

## Responsabilidade deste documento

Este é o **dono canônico da arquitetura da Jana**: mostra onde a IA vive, como as zonas se conectam e quais componentes o código realmente registra. Regras funcionais continuam em [`SPEC.md`](SPEC.md), intenção do produto em [`BRIEFING.md`](BRIEFING.md), operação em [`RUNBOOK.md`](RUNBOOK.md) e decisões em ADRs.

## Topologia lógica

> “Hostinger” e “CT 100” são **zonas operacionais**, não promessa de contagem física. O MySQL gerenciado da Hostinger pode residir em host distinto do web shared.

```mermaid
flowchart LR
  subgraph HOST["Hostinger · plano gerenciado"]
    WEB["ERP web · PHP-FPM · filas curtas"]
    PRODDB[("MySQL de produção")]
    WEB --> PRODDB
  end
  subgraph CT["CT 100 · containers"]
    MCP["Servidor MCP"]
    STAGE["Staging"]
    STAGEDB[("Banco isolado de staging")]
    SEARCH["Meilisearch + Ollama"]
    OBS["Langfuse stack"]
    STAGE --> STAGEDB
  end
  MODEL["OpenAI · provedor externo"]
  CLIENT["Clientes MCP · Claude/Codex"]
  WEB --> MODEL
  WEB --> SEARCH
  WEB --> OBS
  MCP --> PRODDB
  MCP --> SEARCH
  MCP --> CLIENT
  STAGE --> SEARCH
  STAGE --> OBS
```

O desenho mostra **quem chama quem**. Ele não multiplica um serviço compartilhado por consumidor: produção e MCP apontam para o mesmo banco de produção; Meilisearch, Ollama e Langfuse aparecem uma vez.

## Inventário derivado do código

| Medida | Valor derivado | Fonte dona |
|---|---:|---|
| Agentes PHP de produto | **22** | `Modules/*/Ai/Agents/*Agent.php` + contrato `implements Agent` |
| Módulos com agentes PHP | **4** | árvore `Modules/` |
| Agentes sem referência de produção | **0** | referências PHP fora de `Tests/` |
| Tools registradas no MCP | **44** | [`OimpressoMcpServer.php`](../../../Modules/Jana/Mcp/OimpressoMcpServer.php) |
| Tools SQL do Brief Diário | **5** | `Modules/Jana/Ai/Tools/BriefDiario/` |
| Agentes de engenharia | **24** | `.claude/agents/*.md` — outra camada, não runtime PHP |
| Serviços em compose versionado | **14** | `docker/**/docker-compose.yml` — declaração, não uptime |
| Checks no baseline versionado de merge | **35** | `governance/required-checks-baseline.json` — o probe vivo é `protection-drift.mjs` |

## Agentes PHP por módulo

| Módulo | Qtd. | Classes |
|---|---:|---|
| ADS | 4 | [BrainBAgent](../../../Modules/ADS/Ai/Agents/BrainBAgent.php) · [PlannerAgent](../../../Modules/ADS/Ai/Agents/PlannerAgent.php) · [ProjectDecomposerAgent](../../../Modules/ADS/Ai/Agents/ProjectDecomposerAgent.php) · [ReviewerAgent](../../../Modules/ADS/Ai/Agents/ReviewerAgent.php) |
| Crm | 3 | [ClienteProximaAcaoAgent](../../../Modules/Crm/Ai/Agents/ClienteProximaAcaoAgent.php) · [ClienteResumoAgent](../../../Modules/Crm/Ai/Agents/ClienteResumoAgent.php) · [ClienteSegmentoAgent](../../../Modules/Crm/Ai/Agents/ClienteSegmentoAgent.php) |
| Jana | 14 | [BriefDiarioAgent](../../../Modules/Jana/Ai/Agents/BriefDiarioAgent.php) · [BriefingAgent](../../../Modules/Jana/Ai/Agents/BriefingAgent.php) · [ChatCopilotoAgent](../../../Modules/Jana/Ai/Agents/ChatCopilotoAgent.php) · [ClarificadorAgent](../../../Modules/Jana/Ai/Agents/ClarificadorAgent.php) · [DetectarSupersedeAgent](../../../Modules/Jana/Ai/Agents/DetectarSupersedeAgent.php) · [ExtrairFatosAgent](../../../Modules/Jana/Ai/Agents/ExtrairFatosAgent.php) · [HealthNarratorAgent](../../../Modules/Jana/Ai/Agents/HealthNarratorAgent.php) · [KbAnswerAgent](../../../Modules/Jana/Ai/Agents/KbAnswerAgent.php) · [ProximaPerguntaAgent](../../../Modules/Jana/Ai/Agents/ProximaPerguntaAgent.php) · [PrUiJudgeAgent](../../../Modules/Jana/Ai/Agents/PrUiJudgeAgent.php) · [SaleInsightAgent](../../../Modules/Jana/Ai/Agents/SaleInsightAgent.php) · [SinteseSemanalAgent](../../../Modules/Jana/Ai/Agents/SinteseSemanalAgent.php) · [SugestoesMetasAgent](../../../Modules/Jana/Ai/Agents/SugestoesMetasAgent.php) · [WeeklyDigestAgent](../../../Modules/Jana/Ai/Agents/WeeklyDigestAgent.php) |
| Whatsapp | 1 | [InboxAssistAgent](../../../Modules/Whatsapp/Ai/Agents/InboxAssistAgent.php) |

> ✅ Nenhuma classe de agente ficou sem referência PHP de produção.

## Tools do servidor MCP

| Módulo dono | Qtd. | Registro |
|---|---:|---|
| Brief | 1 | BriefFetchTool |
| Jana | 39 | CyclesActiveTool · MyWorkTool · MyInboxTool · TriageTool · CycleGoalsTrackTool · CyclesCloseTool · CyclesCreateTool · TasksListTool · TasksDetailTool · TasksUpdateTool · TasksCommentTool · TasksCreateTool · TasksCurrentTool · DecisionsSearchTool · DecisionsFetchTool · SessionsRecentTool · ClaudeCodeUsageSelfTool · MemoriaSearchTool · MemoriaHistoricaTool · CcSearchTool · WhatsActiveTool · TasksClaimTool · TasksHeartbeatTool · WhatsLockedTool · SystemHealthAuditTool · TasksHealthTool · KbAnswerTool · HandoffFetchSummarizedTool · HandoffDiffTool · WeeklyDigestFetchTool · CharterFetchTool · HandoffDraftTool · FlagListTool · FlagGetTool · FlagSetTool · FlagEnvToggleTool · FlagCacheClearTool · LgpdEsquecerTitularTool · AutomationsListTool |
| TeamMcp | 4 | HandoffPendingTool · HandoffAckTool · HandoffSubmitTool · HandoffLeverTool |

As **44** entradas acima são classes efetivamente registradas no array `$tools`. Uma classe `*Tool.php` solta não entra na contagem.

## Stacks Docker versionados

> Esta tabela responde “o que o repo declara?”. Para responder “o que está vivo agora?”, use os probes da seção seguinte.

| Compose | Serviços declarados | Qtd. |
|---|---|---:|
| [`docker/langfuse/docker-compose.yml`](../../../docker/langfuse/docker-compose.yml) | postgres-langfuse · clickhouse-langfuse · minio-langfuse · redis-langfuse · langfuse-web · langfuse-worker | 6 |
| [`docker/oimpresso-mcp/docker-compose.yml`](../../../docker/oimpresso-mcp/docker-compose.yml) | mcp | 1 |
| [`docker/oimpresso-staging/docker-compose.yml`](../../../docker/oimpresso-staging/docker-compose.yml) | staging | 1 |
| [`docker/oimpresso-workers/docker-compose.yml`](../../../docker/oimpresso-workers/docker-compose.yml) | oimpresso-workers · oimpresso-workers-horizon · oimpresso-workers-scheduler · oimpresso-workers-redis | 4 |
| [`docker/ollama-embedder/docker-compose.yml`](../../../docker/ollama-embedder/docker-compose.yml) | ollama-embedder | 1 |
| [`docker/otel/docker-compose.yml`](../../../docker/otel/docker-compose.yml) | jaeger | 1 |

## Estado vivo: medir, não copiar

| Superfície | Probe/recibo | O que prova |
|---|---|---|
| Web live | [`https://oimpresso.com/login`](https://oimpresso.com/login) | aplicação responde agora |
| MCP | [`https://mcp.oimpresso.com/api/mcp/health`](https://mcp.oimpresso.com/api/mcp/health) | servidor MCP responde agora |
| Staging | [`https://staging.oimpresso.com/login`](https://staging.oimpresso.com/login) | runtime de homologação responde agora |
| Langfuse | [`https://langfuse.oimpresso.com/api/public/health`](https://langfuse.oimpresso.com/api/public/health) | observabilidade responde agora |
| CT 100 | `tailscale ssh root@ct100-mcp "docker ps"` | containers realmente em execução |
| Hostinger | `php artisan schedule:list` + processo `queue:work` | cron e filas realmente carregados |

A página **não grava “verde”** no Markdown: esse estado venceria no minuto seguinte. Ela preserva o probe reproduzível.

## Como esta página continua viva

1. `system-map.mjs` varre agentes, registro MCP, tools de dados e arquivos compose.
2. `node scripts/governance/system-map.mjs --check` compara o Markdown commitado com a geração atual.
3. [`.github/workflows/system-map.yml`](../../../.github/workflows/system-map.yml) roda no PR quando uma fonte muda e também diariamente.
4. O job diário regenera e abre auto-PR; ninguém precisa editar contagem à mão.
5. Fatos de runtime ficam como probes. Se for necessário histórico de uptime, o dono deve ser monitoramento/telemetria — nunca esta página.

### O que ainda é humano

- explicar **por que** as camadas existem;
- decidir se um serviço em standby deve ser ativado ou removido;
- registrar mudança arquitetural em ADR;
- interpretar falha de probe e impacto no negócio.

---
_Gerado por `scripts/governance/system-map.mjs` · 2026-07-28 · arquitetura derivada das fontes canônicas._
