---
id: requisitos-jana-briefing
distilled_at: "2026-08-13"
distilled_by: "manual [W/C] — consolidação de donos: intenção e decisões abertas ficam aqui; topologia/inventário ficam nos artefatos gerados; execução de observabilidade fica em OBSERVABILITY.md"
module: Jana
status: producao
updated_at: "2026-08-27"
---

# BRIEFING — Jana (verdade destilada)

## Estado atual

> **2026-08-31 — `/ia/superadmin/metas` deixou de ser Blade: virou `Jana/Plataforma` (Inertia), e a aba entrou na faixa.** F3 do MWART, com F1 própria em [`RUNBOOK-plataforma.md`](RUNBOOK-plataforma.md). É a 3ª das telas que o `jana-telas-novas.jsx` desenhou e a produção não tinha; as outras duas (`JmAlertas`, `JmAcoesFila`) seguem sem migração. Fecha uma das pendências que o parágrafo de 27/08 abaixo listava — *"retorno Blade e não JSON"* — **só para esta tela**: as outras 8 views Blade do módulo continuam onde estavam.
>
> **O que NÃO mudou, e é o ponto:** *"a agregação cross-business do superadmin que de fato não existe"* — a outra pendência daquela lista — **continua não existindo, agora por decisão declarada em vez de omissão**. Re-medido em 31/08: zero `sum`/`count`/`groupBy` no controller. A tela lista cru e escreve a razão na própria interface, como a fonte de design manda; os contadores de cabeçalho de seção são contagem **do que está listado**, não total de plataforma. Virou Non-Goal no charter + `UC-PLATAF-05` (o payload não pode ganhar chave agregada). O que a plataforma quer medir segue **decisão [W]**.
>
> **O gate desta tela é o `podeVerPlataforma`, e ele agora tem UM dono para a rota e para o menu.** O ghost novo da faixa **não** usa `can('jana.superadmin')` — o `Gate::before` torna essa ability `true` para todo `Admin#{business_id}`, e a aba apareceria para donos de negócio que levariam 403 ao clicar. `UC-PLATAF-06` trava o acoplamento com controle positivo. ⚠️ O item do **dropdown legacy** (mais antigo, no mesmo `DataController`) segue com `can(...)` de propósito: mudá-lo altera quem enxerga o menu hoje e é decisão [W], não conserto de passagem.
>
> **Dado de contexto para quem for testar, dito uma vez:** medido em produção nesta data, `jana_metas`/`jana_meta_periodos`/`jana_meta_apuracoes` têm **0 linhas** nos 88 businesses — a tela abre no estado vazio, e é por isso que as duas copies de vazio da Blade foram preservadas literais e pinadas no contrato de tela. E a rota **é alcançável**: 5 usuários do `business_id=1` passam pela porta `hasPermissionTo`, via o papel `Operacional#1` (controle positivo em `RUNBOOK-plataforma.md` §1.1). A porta `user_type` está **morta** no ambiente (0 usuários) — ela é rede de segurança, não a porta em uso.

> **2026-08-27 — o Painel `/ia` tem 5 botões clicáveis que não fazem nada, e agora eles são dívida DECLARADA.** Medido nesta data com parse estrutural sobre `Index.tsx` + `_components/JanaCockpit.tsx` (o mesmo que o `PainelContratoTest::painelBotoesMudos()` roda; recontar é rodar o teste, não reler este parágrafo). Dois já eram conhecidos — `Exportar` e `Ouvir áudio`, ambos com `title="(em breve)"` e ambos catalogados no `jana-painel.contract.json` como **decisão [W] aberta** (*"Some, vira `disabled` com o motivo, ou entrega?"*). Os outros três são os chips do rodapé do brief — `Disparar régua WhatsApp pros {n} atrasados`, `Ver top devedores`, `Investigar queda ticket médio` — e são **piores**: não prometem nada, o usuário clica e nada acontece, sem explicação. O `Index-visual-comparison.md` já os registrava como "🟡 botão morto", mas apontando `JanaCockpit.tsx:479-500`, onde hoje está o parágrafo do brief; a ref apodreceu e foi trocada por âncora de símbolo no mesmo PR. **O destino dos 5 continua sendo decisão [W]** — a barreira nova (`UC-JPAIN-16`) não escolhe por ele: ela trava o conjunto conhecido e derruba o **sexto** (forward-only, [ADR 0275](../../decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md)). Consertar um é apagar a linha dele na lista, no mesmo PR.
>
> **Por que uma barreira nova, e não o conserto um-a-um:** os três chips cresceram no MESMO arquivo onde o `UC-JPAIN-12` acabara de consertar os CTAs vizinhos. O conserto foi por instância; a classe reincidiu ao lado. É o sinal de two-strikes ([ADR 0344](../../decisions/0344-two-strikes-cobre-processo.md)).
>
> ⚠️ **A lane onde essa barreira roda é ADVISORY.** Dono do fato: [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json) — consultado em 2026-08-27, `PHP / Pest (Jana · MySQL)` não está entre os 45 contexts required, enquanto as lanes irmãs de Compras, Estoque, Financeiro, KB, NfeBrasil, Ponto e Sells estão. Não estou propondo promover: isso é flip [W] com mordida provada ([ADR 0336](../../decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)), e o dado de mordida ainda não existe. Registro porque um leitor pode supor que "tem teste na lane" significa "não passa sem".
>
> **O que a medição REFUTOU no mesmo turno, pra ninguém repetir:** a hipótese de estender o `JanaViewsSemAndaimeTest` (dono de "andaime não chega ao cliente" nas views Blade) para varrer `*.php` do módulo. Medido ANTES de ligar: **60 hits, ~58 falso-positivos** — 14 em `Tests/` (incluindo a fixture do próprio dono), 36 em linha de comentário (fato datado que o canon manda preservar), 12 são placeholders de TEMPLATE no `ScaffoldSkillFromMissionService`, 1 é a regex do `SpecAnchorClassifier` que *procura* `TODO`, e um é português (`"Invalidar TODO cache de biz=?"`). O guard não olha PHP **por desenho**: o predicado dele é *"chega ao cliente"*, e docblock não chega. É a família de guard sintático que o §5 já enterrou 5×.
>
> **O defeito real que havia nos docblocks era outro, e foi corrigido:** cinco classes carregavam `STUB spec-ready` em tempo PRESENTE sobre si mesmas — `MetasController`, `PeriodosController`, `SuperadminController`, `SuggestionEngine`, `ContextSnapshotService` — e o rótulo era falso em todas. O `MetasController` tem CRUD completo com FormRequests (D8.c Wave 14); o `PeriodosController` carrega o gate Tier 0 que fechou um IDOR cross-tenant (#4474); o `SuggestionEngine` já tinha sido medido e desmentido pelo próprio `SPEC.md` (US-COPI-003, 2026-08-10) sem que ninguém corrigisse o docblock. É LC-10 puro (§5 2026-08-17: comentário não afirma o próprio estado em presente, porque apodrece). As **pendências** que os docblocks listavam foram re-verificadas uma a uma e ficam — `index()` sem filtro, sem permissão por ação, retorno Blade e não JSON, e a agregação cross-business do superadmin que de fato não existe.

> **2026-08-13 — o item 4 FOI autorizado, e a onda 1 saiu: as 61 migrations `mcp_*` estão no Modules/Forja.** [W] em 2026-08-13: *"mcp foi para forja, isso já decidi"* — a decisão de destino já era canon desde a [ADR 0366](../../decisions/0366-fronteira-jana-forja-governance-kb.md) (aceita 2026-08-03); o que faltava era executar o item 4 do §D-C, que ela própria deixou pendente de "ADR própria + janela". As entradas de 2026-08-05 abaixo dizem *"o item 4 não está autorizado"* — **isso valia naquela data e não vale mais**; ficam como registro, não como estado. Nome de arquivo preservado em todas as 61, então a tabela `migrations` casa e nada re-roda em prod. Muda o dono DERIVADO do schema (quem faz `Schema::create`), não o código: **ainda AQUI**, nas próximas ondas — 30 `Entities/Mcp`, o servidor JSON-RPC + 40 tools em `Mcp/`, 5 `Services/Mcp`, 10 comandos `Console/Commands/Mcp*`, o `McpAuthMiddleware` e 44 testes. Efeito medido na fronteira: a dívida de acoplamento por tabela caiu de 20 para 18 pares (curados `Forja>Jana`, `Governance>Jana`, `Superadmin>Jana`), e `Jana>Forja` subiu de 4 pra 54 queries — **transitório por construção**, some quando o código seguir o schema.
>
> ⚠️ **Duas consequências da onda 1 que NÃO podem viver só em mensagem de commit** (achado do refutador adversarial GT-G5, rodada 2 — a errata tocou 7 arquivos e nenhum em `memory/`, então quem lesse este BRIEFING não saberia):
>
> 1. **`module:migrate Jana` deixou de provisionar as 61 tabelas.** O `InstallController` deste módulo estende `BaseModuleInstallController` ([:128](../../../app/Http/Controllers/BaseModuleInstallController.php)), que chama `module:migrate` com o nome do módulo, e a rota `/ia/install` está viva. Com as migrations na Forja e o código MCP ainda aqui, uma instalação POR MÓDULO da Jana não cria o schema que as 30 `Entities/Mcp`, 40 tools, 5 `Services/Mcp` e 10 comandos daqui consomem. **Em produção isso é mascarado** pelo `migrate --force` global do deploy ([deploy.yml:520](../../../.github/workflows/deploy.yml)) — some de vez quando o código seguir o schema, mas até lá é real. O PR que moveu afirmava o contrário (*"não há `module:migrate` por módulo"*) — era falso fora do escopo do deploy, e é essa frase que escondia esta superfície.
> 2. **A ADR própria do item 4 NÃO existe.** A [ADR 0366](../../decisions/0366-fronteira-jana-forja-governance-kb.md) §D-C diz que mover as `Mcp*` *"exige ADR própria + janela"*; a autorização que destravou a onda 1 foi a frase de [W] no chat, registrada em prosa. Não há canary nem janela declarados. Escrever essa ADR é ato de [W] — enquanto ela não existir, este parágrafo é o único lugar onde a pendência está registrada.
> **2026-08-05 — Custos de IA e Qualidade IA sairam pra Modules/Governance** (ADR 0366 §D-B, ratificada por [W] em 2026-08-03: cada modulo responde UMA pergunta, e a da Jana e "como esta meu negocio e o que eu faco?"). Muda o dono da TELA, nao o do DADO: CustosService e MemoriaMetrica continuam aqui, e as permissions jana.admin.custos.view / jana.mcp.usage.all foram PRESERVADAS — renomear revogaria acesso em silencio (ADR 0087). URLs antigas seguem vivas por 301 preservando a query. O item 4 do plano (mover as Mcp*) NAO esta autorizado.
> **2026-08-05 — a tela de Governanca MCP saiu da Jana** (ADR 0366 §D-C item 1). Ela nao foi movida: foi FUNDIDA no painel do Modules/Governance, porque era a mesma tela que o dashboard de la (sobreposicao #4 da ADR). Fecha um drift que o SCOPE.md da Governanca declarava desde 2026-05-17 com eta_migracao Fase 5. O GovernancaService FICA aqui — mudou o dono da TELA, nao o do DADO; a permission jana.mcp.usage.all foi PRESERVADA e continua gateando a secao MCP dentro do painel novo. A URL antiga /ia/admin/governanca redireciona 301.
> **2026-08-05 — a Jana está devolvendo as telas admin que não são dela** (ADR 0366 §D-B, ratificada por [W] em 2026-08-03: cada módulo responde UMA pergunta, e a da Jana é *"como está meu negócio e o que eu faço?"*). O Roadmap Gantt (`/ia/admin/roadmap`) foi pra **Forja** — usa `TaskCrudService`/`McpTask`, e tasks é Forja; mandar pro Governance criaria a 3ª tela de roadmap. Em PRs irmãos: Custos, Qualidade IA e a Governança MCP vão pra **Governance**. Em todos os casos **muda o dono da TELA, não o do DADO** — `CustosService`, `MemoriaMetrica`, `GovernancaService` e `TaskCrudService` continuam aqui; o item 4 do plano (mover as `Mcp*`) **não está autorizado**. URLs antigas sobrevivem por 301. Permissions `jana.*` **preservadas**: renomear revogaria acesso em silêncio ([ADR 0087](../../decisions/0087-drift-resolution-sem-mover-url.md)).

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
| ~~**Mock em rota LIVE**~~ — **RESOLVIDO 2026-08-07** por remoção, não por conserto: `/ia/cockpit` deixou de existir (301 → `/ia`) e as duas metades do mock saíram junto com a tela | `Cockpit.tsx` apagado · `ChatController@cockpit` + `mockJanaPayload()` removidos | US-COPI-123 `done` · onda 4 da US-COPI-148 · [RUNBOOK-cockpit.md](RUNBOOK-cockpit.md) (lápide) |
| `context_recall` **baixo** — o piso já não deixa degradar calado (landou 2026-07-17), mas o valor segue baixo | rodar `jana:ragas-real-eval`; piso vive em `thresholds_regressao` | [`governance/jana-ragas-real-baseline.json`](../../../governance/jana-ragas-real-baseline.json) · US-COPI-136 **`done`** ([#4412](https://github.com/wagnerra23/oimpresso.com/pull/4412)) |
| Eval online em ativação controlada | flag canônica autorizada por [W] em 2026-07-29; cobertura e ausência de score são advisory no `jana:health-check` | [`OBSERVABILITY.md`](OBSERVABILITY.md) Etapa 3 · US-COPI-137 |
| Fluxo Langfuse | heartbeat do destino já foi construído; recibo atual vem de `jana:health-check --json`, não de texto estático | [`OBSERVABILITY.md`](OBSERVABILITY.md) Etapa 0 · US-COPI-138 |
| Sem cadeia de fallback de provider (*"se o provider cai, a Jana cai"*) | — | US-COPI-135 `todo` |
| Ratio negócio/governança **em alarme** — o cron que dispara landou 2026-07-17 ([#4410](https://github.com/wagnerra23/oimpresso.com/pull/4410)); falta o **badalo no `brief-fetch`** | rodar `node scripts/governance/negocio-vs-governanca-ratio.mjs` pro valor do dia | [`scripts/governance/negocio-vs-governanca-ratio.mjs`](../../../scripts/governance/negocio-vs-governanca-ratio.mjs) · US-COPI-139 `todo` · [ADR 0334](../../decisions/0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md) |
| **6 flags OFF por default** | `JANA_RETENTION_ENABLED` · `JANA_CLARIFY_ENABLED` · `COPILOTO_HYDE_ENABLED` · `COPILOTO_NEGATIVE_CACHE_ENABLED` | `Config/retention.php:58` · `config.php:527/234/462/631/673` |
| Hybrid **medido e rejeitado** pra prod — não re-propor sem número novo | [#4198](https://github.com/wagnerra23/oimpresso.com/pull/4198) | US-COPI-133 |

⚠️ **LGPD purge não é "só ligar".** O evidence pack de 2026-07-12 provou o path `anonymize` em staging, mas o **§3.1 dele** registra que flipar hoje violaria a própria regra: o schedule (`Kernel.php:770`) roda `jana:retention-purge` **sem `--business`** → itera todos, **incluindo biz=4 ROTA LIVRE (Larissa)**. Falta PR de allowlist + 3 sign-offs [W]. Ver [EVIDENCE-retention-purge-dry-run-2026-07-12.md](EVIDENCE-retention-purge-dry-run-2026-07-12.md).

✅ **Segundo BRIEFING concorrente — RESOLVIDO em 2026-07-30.** O `Modules/Jana/BRIEFING.md` afirmava *"Governance score v3 96/100"* + *"Operacional PME 95%"* contra os **73** do baseline canônico (último toque real 2026-05-16). Virou lápide-ponteiro em 21/07 ([taxonomia §5](../../decisions/proposals/2026-07-21-taxonomia-arquivos-modulo.md)) e foi **deletado** em 30/07, decisão [W] (*"quero mover tudo para memory, apagar os outros e revisar os vínculos"*). Este arquivo é a **casa única** do BRIEFING da Jana. Mesma cura aplicada aos 10 módulos que tinham o par.

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
