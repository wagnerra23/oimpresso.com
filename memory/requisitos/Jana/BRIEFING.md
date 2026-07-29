---
id: requisitos-jana-briefing
distilled_at: "2026-07-28"
distilled_by: "manual [W/C] — consolidação de donos: intenção e decisões abertas ficam aqui; topologia/inventário ficam nos artefatos gerados; execução de observabilidade fica em OBSERVABILITY.md"
module: Jana
status: producao
updated_at: "2026-07-29"
---

# BRIEFING — Jana (verdade destilada)

## Estado atual

Camada de IA do oimpresso: chat com memória persistente, brief diário, sugestões de metas e evals, sobre a stack canônica `laravel/ai` + Agents próprios ([ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)). Em produção.

**Module grade: 73/100 (Bom · rubrica v3).** Dono do número: [`governance/module-grades-baseline.json`](../../../governance/module-grades-baseline.json) (v3.6.0, lock 2026-07-16, medição do **CI**) — recomputar com `php artisan module:grade Jana`. Travada em 73 desde o [#4194](https://github.com/wagnerra23/oimpresso.com/pull/4194); o CT 100 mede 74, e o próprio baseline usa o Jana como **controle limpo** desse delta (é instrumento, não qualidade). O gate `module-grades` é **advisory** desde 2026-06-30 ([ADR 0314](../../decisions/0314-poda-gates-onda-2-lei-fusoes.md) D-1) — a nota não bloqueia merge.

> ⛔ **Errata do destilado de 2026-07-10 — não re-alegar.** Ele dizia *"85% das funcionalidades operacionais"*. **Esse número era META, não estado**: sai dos audits de maio ([`AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md:198`](AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md) *"Payoff: 73% → ~85% maturidade"* · [`AUDIT-SENIOR-2026-05-25.md:24`](AUDIT-SENIOR-2026-05-25.md) *"73→85%+ maturidade global"*). O destilador leu um **alvo** e carimbou como retrato. O único número de estado com dono é a nota **73** acima. Mesma família da lápide *"claims REFUTADAS"* ([proibicoes.md](../../proibicoes.md) §5, 2026-07-09): claim sem data+fonte é tom inflado.

## Doutrina do produto e decisões abertas

Esta é a casa curada da intenção da Jana. Topologia e inventário não são repetidos aqui: vivem em [`ARCHITECTURE.md`](ARCHITECTURE.md) e [`PAINEL-SISTEMA.md`](../../reference/PAINEL-SISTEMA.md), ambos gerados por `system-map.mjs`. O plano operacional de observabilidade vive em [`OBSERVABILITY.md`](OBSERVABILITY.md). A proveniência dos deltas atual→alvo permanece na [proposta de 2026-07-28](../../decisions/proposals/2026-07-28-camada-ia-atual-x-alvo-e-doutrina-resgatada.md).

### Posicionamento

- **Não é BI tradicional.** Não há OLAP, cubo nem data warehouse como centro do produto.
- **Não é dashboard genérico.** A tela sustenta a conversa; não substitui a decisão.
- **É agente de IA orientado a decisão.** O valor está na proposta aceita e acompanhada, não no gráfico isolado.

### Decisões ainda abertas

| Tema | Estado da decisão |
|---|---|
| Trajetória projetada | linear é a omissão atual; sazonalidade pode ser obrigatória no varejo; enums `sazonal`, `exponencial` e `manual` ainda não constituem decisão de produto |
| Alertas por WhatsApp | adiados pelo custo da API |
| Multi-idioma | português permanece o único idioma definido |
| Cache do retrato do negócio | cache curto ainda não foi avaliado |
| Guardrails | restrição contra meta ilegal ou tributariamente inadequada continua em prompt, sem trava determinística |

Quando [W] decidir um desses temas, ele sai desta lista e ganha ADR. Decisão aberta não é copiada para `ARCHITECTURE.md` nem para o plano de observabilidade.

## Capacidades

Contagens varridas em 2026-07-17 (`git ls-files` — arquivos, não testes verdes; rodar Pest é CT 100, [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)):

- **Agentes, provedores, memória e rerankers:** consultar [`ARCHITECTURE.md`](ARCHITECTURE.md) ou regenerar com `node scripts/governance/system-map.mjs`; este briefing não mantém uma segunda contagem.
- **45 comandos artisan** (incl. `jana:health-check`, `jana:distill-module-truth`, `jana:recall-eval`, `jana:ragas-real-eval`, `jana:retention-purge`) · **16 controllers** · **138 arquivos de teste**.
- **Memória**: `MeilisearchDriver` — desde o [#4207](https://github.com/wagnerra23/oimpresso.com/pull/4207) o time-decay **reordena** o recall (antes só pontuava, não reordenava).
- **Telemetria**: mecanismo Langfuse, listener global e heartbeat foram construídos; estado de runtime e lacunas atuais são medidos pelo `jana:health-check` e catalogados em [`OBSERVABILITY.md`](OBSERVABILITY.md), nunca inferidos deste briefing.
- **Porta de memória**: o distiller que escreve os `BRIEFING.md` do projeto é deste módulo (`jana:distill-module-truth`, [ADR 0291](../../decisions/0291-distiller-modulo-verdade-contrato-emenda-0270-f3.md) D-D) — desde o [#4268](https://github.com/wagnerra23/oimpresso.com/pull/4268) emite `status`/`updated_at` no frontmatter.

## Gaps

Cada linha **aponta pro dono do número** em vez de repeti-lo ([proibicoes.md](../../proibicoes.md) §5 2026-07-17, *"fato derivado não se restateia"*) — pra ver o valor de hoje, rode o dono:

| Gap | Onde se vê / evidência | Dono |
|---|---|---|
| **Mock em rota LIVE** — `/ia/cockpit` responde mock no chat **e** no payload, sem feature-flag, e está no sidebar | `Cockpit.tsx:707` define / `:780` chama `startMockStream`; `ChatController.php:533` chama `mockJanaPayload()` (`:555`) | US-COPI-123 `todo` · [RUNBOOK-cockpit.md](RUNBOOK-cockpit.md) §10 |
| `context_recall` **baixo** — o piso já não deixa degradar calado (landou 2026-07-17), mas o valor segue baixo | rodar `jana:ragas-real-eval`; piso vive em `thresholds_regressao` | [`governance/jana-ragas-real-baseline.json`](../../../governance/jana-ragas-real-baseline.json) · US-COPI-136 **`done`** ([#4412](https://github.com/wagnerra23/oimpresso.com/pull/4412)) |
| Eval online em ativação controlada | flag canônica autorizada por [W] em 2026-07-29; cobertura e ausência de score são advisory no `jana:health-check` | [`OBSERVABILITY.md`](OBSERVABILITY.md) Etapa 3 · US-COPI-137 |
| Fluxo Langfuse | heartbeat do destino já foi construído; recibo atual vem de `jana:health-check --json`, não de texto estático | [`OBSERVABILITY.md`](OBSERVABILITY.md) Etapa 0 · US-COPI-138 |
| Sem cadeia de fallback de provider (*"se o provider cai, a Jana cai"*) | — | US-COPI-135 `todo` |
| Ratio negócio/governança **em alarme** — o cron que dispara landou 2026-07-17 ([#4410](https://github.com/wagnerra23/oimpresso.com/pull/4410)); falta o **badalo no `brief-fetch`** | rodar `node scripts/governance/negocio-vs-governanca-ratio.mjs` pro valor do dia | [`scripts/governance/negocio-vs-governanca-ratio.mjs`](../../../scripts/governance/negocio-vs-governanca-ratio.mjs) · US-COPI-139 `todo` · [ADR 0334](../../decisions/0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md) |
| **6 flags OFF por default** | `JANA_RETENTION_ENABLED` · `JANA_CLARIFY_ENABLED` · `COPILOTO_HYDE_ENABLED` · `COPILOTO_NEGATIVE_CACHE_ENABLED` | `Config/retention.php:58` · `config.php:527/234/462/631/673` |
| Hybrid **medido e rejeitado** pra prod — não re-propor sem número novo | [#4198](https://github.com/wagnerra23/oimpresso.com/pull/4198) | US-COPI-133 |

⚠️ **LGPD purge não é "só ligar".** O evidence pack de 2026-07-12 provou o path `anonymize` em staging, mas o **§3.1 dele** registra que flipar hoje violaria a própria regra: o schedule (`Kernel.php:770`) roda `jana:retention-purge` **sem `--business`** → itera todos, **incluindo biz=4 ROTA LIVRE (Larissa)**. Falta PR de allowlist + 3 sign-offs [W]. Ver [EVIDENCE-retention-purge-dry-run-2026-07-12.md](EVIDENCE-retention-purge-dry-run-2026-07-12.md).

⚠️ **Segundo BRIEFING concorrente.** [`Modules/Jana/BRIEFING.md`](../../../Modules/Jana/BRIEFING.md) (último toque real 2026-05-16) afirma *"Governance score v3 96/100"* + *"Operacional PME 95%"* — contra os **73** do baseline canônico. Mesma doença que o [#4408](https://github.com/wagnerra23/oimpresso.com/pull/4408) matou em TeamMcp/Cms; candidato à mesma cura (reconciliar apontando pro dono, ou lápide).

## Última mudança

Recibo: `git log --since=2026-07-10 -- Modules/Jana memory/requisitos/Jana`, rodado em 2026-07-17 → **21 commits** (janela 07-12→07-17), dos quais **11 tocam algum `.php` de `Modules/Jana`** (entrega real); o resto é higiene/docs.

Entregas: **piso de `context_recall`** ([#4412](https://github.com/wagnerra23/oimpresso.com/pull/4412) — o recall era medido e jogado fora; agora tem bite-test que derruba o gate); Langfuse ganhou tag `business_id` ([#4145](https://github.com/wagnerra23/oimpresso.com/pull/4145)) e 4 call-sites instrumentados ([#4208](https://github.com/wagnerra23/oimpresso.com/pull/4208)); time-decay passou a reordenar o recall ([#4207](https://github.com/wagnerra23/oimpresso.com/pull/4207)); `McpTask::openBlockers()` destravou **12 tasks** que o backlog dizia bloqueadas por bloqueador **já done**, 1 delas P0 ([#4401](https://github.com/wagnerra23/oimpresso.com/pull/4401)); forward-close de card por âncora verificada ([#4262](https://github.com/wagnerra23/oimpresso.com/pull/4262), [ADR 0337](../../decisions/0337-emenda-0144-forward-close-por-ancora-verificada.md)); drag-drop de prazo no Roadmap ([#4159](https://github.com/wagnerra23/oimpresso.com/pull/4159)).

Reconciliações que corrigiram o próprio registro: [#4144](https://github.com/wagnerra23/oimpresso.com/pull/4144) mediu as Ondas 4-5 por máquina — **real ~97%, o doc dizia 91%** (subestimava); [#4206](https://github.com/wagnerra23/oimpresso.com/pull/4206) desquarentenou o `RetentionPurgeCommandTest` em MySQL.

O SPEC foi tocado **hoje** ([#4402](https://github.com/wagnerra23/oimpresso.com/pull/4402)): 5 US novas (US-COPI-135..139), todas de produto/cliente, nascidas da **grade de réguas 2026-07-17** — cujo diagnóstico foi que a régua vinha ganhando do cliente. Dessas, a **136 já fechou no mesmo dia** ([#4412](https://github.com/wagnerra23/oimpresso.com/pull/4412)) e o cron do alarme da 0334 foi ligado ([#4410](https://github.com/wagnerra23/oimpresso.com/pull/4410)) — as notas e o ratio dessa grade **não são repetidos aqui**: donos são o session log da grade e `negocio-vs-governanca-ratio.mjs`.

## Proveniência (destilado de)

Releitura direta em 2026-07-17 — não de sessions/handoffs (o destilado anterior citava 40 fontes, **nenhuma posterior a 2026-07-05**, e por isso não enxergava a janela que importava):

- código: `Modules/Jana/Ai/Agents/` · `Console/Commands/` · `Http/Controllers/` · `Config/config.php` · `Config/retention.php` · `resources/js/Pages/Jana/Cockpit.tsx`
- contrato: [SPEC.md](SPEC.md) (84 US únicas; 28 done + 28 todo declaradas, 28 sem status declarado) · [RUNBOOK-cockpit.md](RUNBOOK-cockpit.md) · [EVIDENCE-retention-purge-dry-run-2026-07-12.md](EVIDENCE-retention-purge-dry-run-2026-07-12.md)
- números: [`governance/module-grades-baseline.json`](../../../governance/module-grades-baseline.json) v3.6.0
- janela: `git log --since=2026-07-10 -- Modules/Jana memory/requisitos/Jana` (21 commits)
</content>
</invoke>

---

## Delta 2026-07-27 (não-redistilação — só o que mudou)

**O que entrou no módulo:** `Services/TaskRegistry/HitlEscalationService.php` — o elo **detectar→decidir**. Transporte idempotente que materializa uma pendência já detectada por um sentinela como **1 task `blocked`/`wagner`** em `mcp_tasks`, que é o canal que o `brief-fetch` imprime como *HITL pending Wagner* (procedure `2026_05_06_172445`). Mora aqui porque o Jana é o dono de `mcp_tasks` (ADR 0070) — não em módulo paralelo.

**Por que existia o buraco (medido, não suposto):** varredura dos 12 sentinelas agendados (`handoff:stale-alert`, os 9 `*:health-check`, `governance:detect-drift`, `ads:learn-patterns`) → **12 de 12 criam ZERO task**. Todos notificam ou logam e param. O `handoff-stale` repetiu o mesmo alerta por **38 dias** sem virar decisão de ninguém.

**Regra que impede a máquina de brigar com o humano:** `task_id` determinístico (`HITL-<CHAVE>`) → re-escalar atualiza a MESMA task. `done`/`cancelled` **não reabre**; `todo`/`doing`/`review` **não rebaixa**. Fail-open se `mcp_tasks` não existir — o transporte nunca derruba o sentinela.

**Teste:** `Tests/Feature/TaskRegistry/HitlEscalationServiceTest.php`, 7 casos / 17 assertions, rodados no CT 100 com MySQL real. A 1ª versão dele era **corruptora** (`dropIfExists('mcp_tasks')` + DDL em `activity_log`) e o `sqlite-test-corruptors` reprovou com razão — corrigido para mock de facade + `disableLogging()`.

**O que este delta NÃO fez:** releitura de agents/commands/controllers/config. A leitura de fundo continua sendo a de 2026-07-17 acima.
