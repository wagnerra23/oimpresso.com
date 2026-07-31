---
id: requisitos-ads-deprecation-plan
---

# DEPRECATION-PLAN — Modules/ADS

> **Status:** 📋 Planejado · **Owner:** [W] · **Decisão:** [W] 2026-07-30 (*"todos esses eu vou deletar"*)
> **Ordem no conjunto:** **4º de 6** — [proposal da ordem topológica](../../decisions/proposals/2026-07-30-deprecar-6-modulos-governanca-ordem-topologica.md)
> 🔴 **O único dos 6 com volume alto e escrita ATIVA.** Não é zumbi: gravou hoje.
>
> ⚠️ **O plano MUDOU de rumo em 2026-07-31.** O destino não é mais "deletar": o `Modules/Governance`
> **incorpora** o ADS — [ADR 0363](../../decisions/0363-governance-incorpora-ads-nucleo-sem-receptor.md),
> que supersede a 0145. Ver a **ERRATA 2026-07-31** logo abaixo: ela corrige 4 fatos que a execução
> mediu e que o corpo (incluindo a errata de 07-30) erra.

## ⚠️ ERRATA 2026-07-31 — quatro correções que a EXECUÇÃO mediu (não altera o corpo)

> Origem: as partes 1-3 da incorporação (PRs [#5127](https://github.com/wagnerra23/oimpresso.com/pull/5127) ·
> [#5128](https://github.com/wagnerra23/oimpresso.com/pull/5128) · [#5129](https://github.com/wagnerra23/oimpresso.com/pull/5129))
> mais o review adversarial de 4 agentes. **Onde este bloco discordar do corpo OU da errata de 07-30
> sobre dado medido, este vence** — ele é o único escrito depois de rodar as coisas.

### C1 — São **5** crons, não 6. A errata E2 (de 07-30) errou por filtro.

A E2 declara *"O plano declara 0 crons. São **6**, e todos rodam em `live`"*. O oráculo de runtime em
prod dá **5**. Reconstrução do 6: filtrando `ads` **sem** os dois-pontos aparecem 7 linhas — as 5 reais
mais duas do WhatsApp com "downl**oads**"; e como a linha do Job não carrega string de comando, um filtro
sobre `$e->command` fecha em 5+1.

Os cinco, nomeados (hoje registrados no comentário-lápide de [`app/Console/Kernel.php`](../../../app/Console/Kernel.php),
que os desligou): `ads:review-decisions` (15min) · `ads:learn-patterns` (02:00) ·
`ads:auto-generate-tasks` (9-18h úteis) · `ads:plan-decisions` (10min) · `ads:process-brain-b` (5min).

**E o produtor nunca foi só cron.** Havia um sexto que nenhuma varredura de `Kernel.php` acharia:
`ads-brain-a.service`, **systemd no CT 100**, `Restart=on-failure`, ativo desde maio, fazendo poll de
`/api/ads/*` a cada ~5s. Journal dos ~73 dias: **16.832 `HTTP 503`** · 5.074 decisões enviadas · **2.199**
classificadas `unknown_commit`. Desligado (`inactive`/`disabled`, 0 processos), reversível por
`systemctl enable --now`. Efeito medido pós-deploy: `schedule:list` de **103 → 98**, com **0** crons
`ads:`. A lição operacional: *"quem grava"* não se responde só pelo scheduler do app quando existe host
próprio rodando daemon.

### C2 — 🔴 O DROP de `mcp_dual_brain_decisions` **também** quebra a Forja, não só as 2 tabelas de projeto.

A errata E3 corrigiu o caso de `mcp_projects`/`mcp_project_parts`. Faltou o principal: a **Fase 4 marca a
própria `mcp_dual_brain_decisions` como decisão ARCHIVE-ou-DROP** sem registrar que ela tem **consumidor
externo sobrevivente**.

[`Modules/Forja/Services/ProjectService.php:160`](../../../Modules/Forja/Services/ProjectService.php)
faz `DB::table('mcp_dual_brain_decisions')->where('project_id', …)->get(...)`, **sem `try/catch`**. A
Forja não está na lista de deleção. O DROP converte a tela de detalhe de projeto em `SQLSTATE 42S02` →
**500** — exatamente a mesma classe de falha que a E3 descreveu, na tabela que a E3 não olhou.

**Correção:** o DROP de `mcp_dual_brain_decisions` exige **patch prévio no `ProjectService`** (o bloco de
decisões vira opcional/removido), na mesma leva. A decisão de dado ficou **ARCHIVE → DROP preservando as
41 linhas com decisão humana** (dump no CT 100, **nunca em git** — 87,75 MB), registrada na ADR 0363.

### C3 — `Modules/ADS/Routes/web.php` é o **único host** de 9 rotas da Forja.

O plano trata as rotas do ADS como se fossem todas do ADS. Não são: **9** apontam para controllers da
**Forja**, e o arquivo de rotas da Forja **não tem nenhuma delas**. Contado no arquivo:

| Bloco | Rotas | Controller (Forja) |
|---|---:|---|
| `/ads/admin/tools*` | 2 | `Admin\ToolsController` |
| `/ads/admin/team-scopes*` | 3 | `Admin\TeamScopesController` |
| `/ads/admin/projects*` | 4 | `Admin\ProjectsController` |

Apagar `Modules/ADS/Routes/web.php` sem receptor derruba as 9 — três telas vivas de um módulo que
sobrevive. **A Forja precisa assumir as 9 ANTES da remoção do arquivo** (é a parte 5 da incorporação), e
com **URL e route name congelados** (`ads.admin.*`), pelo padrão da [ADR 0087](../../decisions/0087-drift-resolution-sem-mover-url.md):
troca-se só o FQCN no `Route::`, nunca o endereço.

### C4 — `outcome='cancelled'` em 100% **não** significa "canceladas". É o DEFAULT da coluna.

A errata E1 lê os 36.658 como *"todas foram canceladas"* e conclui que a decisão da Fase 4 fica mais
fácil. A leitura do dado está errada — embora a conclusão continue valendo, por outro motivo.

`outcome` é `->default('cancelled')` na
[migration `2026_05_03_000004`, linha 43](../../../Modules/ADS/Database/Migrations/2026_05_03_000004_create_mcp_dual_brain_decisions_table.php),
e o `DecisionPresenter` exibe esse mesmo estado como **"Aguardando você decidir"**. Ou seja: 100% de
`cancelled` é **100% de linhas que nunca foram tocadas depois do INSERT** — não são decisões recusadas,
são decisões que ninguém respondeu.

O retrato correto (**prod `u906587222_oimpresso`, medido 2026-07-31**): **36.862** itens que nunca saíram
do estado inicial · `resolved_by` em **41** (0,11%) · `pr_url` e `commit_sha` em **zero**. Contra os
36.658 de 07-30, são **+204 em um dia** — a cadência que o C1 desligou.

**Por que muda o argumento:** "canceladas" sugere um sistema que decidiu e disse não. O que havia era um
laço aberto — sem outcome o `PatternLearning` não aprende, sem aprender nada é promovido, e tudo
continua indo pra fila. O que faltava era um passo humano que nunca teve dono. É esse fato, e não o
volume, que sustenta a decisão de que **o núcleo não tem receptor** (ADR 0363).

## ⚠️ ERRATA 2026-07-30 — três correções medidas (não altera o corpo)

> Origem: revisão adversarial pedida por [W] (*"quero um adversário aqui"*), com dois agentes de
> mandato oposto + medição em produção que nenhum deles tinha. **O corpo fica como registro do que
> se sabia; onde este bloco e o corpo discordarem sobre dado medido, este vence.**

### E1 — "escrita ATIVA" prova CADÊNCIA, não uso. O header exagera.

O header diz *"Não é zumbi: gravou hoje"*. Gravou — e **nunca produziu nada**:

```sql
-- prod u906587222_oimpresso, 2026-07-30
total ....................... 36.658
outcome = 'cancelled' ....... 36.658  (100,00%)
pr_url    NOT NULL .......... 0       (0,00%)
commit_sha NOT NULL ......... 0       (0,00%)
resolved_by NOT NULL ........ 41      (0,11%)
wagner_modified_to .......... 10      (0,03%)
```

**Nenhuma das 36.658 decisões virou PR ou commit; todas foram canceladas.** É laço fechado: os
crons do próprio ADS escrevem e os dashboards do próprio ADS leem. O único leitor externo
(`Modules/Forja/Services/ProjectService.php`) filtra por `project_id` em `mcp_projects`, que
tem **0 linhas** — retorna vazio, sempre. A decisão da Fase 4 sobre este dado fica **mais fácil**,
não mais difícil.

### E2 — O plano declara **0 crons**. São **6**, e todos rodam em `live`.

O R2 do corpo diz *"achar e desligar o produtor antes (**não medido**: quem escreve?)"*. O produtor
está no `app/Console/Kernel.php`, medido pelo **oráculo de runtime** (não por parse — lápide §5
2026-07-17: gate de ambiente tem ≥2 formas sintáticas):

```php
// em prod, APP_ENV=live
foreach (app(Schedule::class)->events() as $e) { $e->runsInEnvironment('live'); }
// → 110 registrados · 108 rodam · ads: 6 registrados, 6 rodam
```

**Desligar os 6 crons é pré-requisito da Fase 4**, e não estava no plano.

### E3 — 🔴 A Fase 4 marca `mcp_projects` e `mcp_project_parts` como DROP. Isso quebra módulo SOBREVIVENTE.

`Modules/Forja` **não está na lista de deleção** e escreve nas duas
(`ProjectService.php` — `insertGetId` entre outros), com **`grep -c catch` = 0**. As tabelas têm
**0 linhas**, mas a dependência é de **schema**, não de dado: o DROP converte "tela vazia" em
`SQLSTATE 42S02` → **500**.

**Correção:** as duas tabelas saem do DROP e vão para o inventário de realocação (dono natural =
ProjectMgmt, que é quem escreve — e que por decisão [W] de 2026-07-30 vira a **Forja**).

## Fase 1 — Inventário

**Gerado:** [`SUPERFICIE.md`](SUPERFICIE.md) — **152 arquivos em 14 papéis** (`module-surface.mjs ADS --write`), o maior dos 6. Frescor 2026-07-30: `--check` **exit 0**.

Contornos: **19 telas** `.tsx` (o maior número do conjunto) · 2 arquivos em `Routes/` · **0** tools MCP · **0** cron em `Kernel.php`.

## Fase 2 — Estado em produção (medido ANTES de planejar)

**Sistema medido:** `APP_ENV=live` · `u906587222_oimpresso` · 385 tabelas · **2026-07-30**. Existência por `information_schema.tables`.
**Controle positivo:** `business=82` · `users=124` · `transactions=75.255`.

| Tabela | Linhas | Escrita mais recente | Tenants |
|---|---:|---|---|
| **`mcp_dual_brain_decisions`** | **36.607** | **2026-07-30 08:02:11** | `business_id=[1]` |
| `mcp_governance_rules` | 4 | 2026-05-04 20:15 | — |
| `mcp_decision_thresholds` | 1 | — | — |
| `mcp_decision_patterns` · `mcp_confidence_scores` · `mcp_projects` · `mcp_project_parts` · `mcp_tool_executions` · `mcp_file_locks` · `mcp_user_module_access` · `mcp_decision_links` | **0** cada | — | — |

**Consequência 1 — o módulo está VIVO.** 36.607 decisões de dual-brain, a última **poucas horas antes desta medição**. Isto **não** é o caso do SRS (0 linhas, nunca usado) — aqui há trilha auditável real e o dado precisa de decisão explícita.

**Consequência 2 — o volume está em UMA tabela.** 36.607 das 36.612 linhas (**99,99%**) vivem em `mcp_dual_brain_decisions`. As outras 10 tabelas somam **5 linhas**. Isso simplifica a Fase 4: **uma** decisão difícil, dez triviais.

**Consequência 3 — tenant único.** Todo o volume é `business_id=1` (o negócio interno), **não** o cliente piloto biz=4. Isso **remove o risco de impacto em cliente**, mas **não** remove o dever de guarda da trilha.

⚠️ CT 100 **não medido**.

## Fase 3 — Acoplamento externo

`git grep -lF 'Modules\ADS\'` fora da pasta, sem `memory/`/`.claude/` → **5 arquivos**:

| Acoplador | Sobrevive ao conjunto? |
|---|---|
| `Modules/KB/Http/Controllers/Admin/GraphController.php` | ✅ **sim** — exige patch |
| `Modules/Forja/Http/Controllers/Admin/ProjectsController.php` | ✅ **sim** — exige patch |
| `Modules/TeamMcp/Http/Controllers/Admin/TeamScopesController.php` | ❌ morre (5º) |
| `Modules/TeamMcp/Http/Controllers/Admin/ToolsController.php` | ❌ morre (5º) |
| `tests/Feature/Skills/SkillsServiceTest.php` | ✅ sim — teste a re-apontar |

**3 dos 5 sobrevivem.** E o ADS **consome** TeamMcp de volta (`Http/Requests/ExecuteToolRequest.php`, `Routes/web.php`) — é o **ciclo** `ADS ↔ TeamMcp` que obriga a ordem do conjunto.

## Fase 4 — Decisão por tabela

| Tabela | Decisão | Por quê |
|---|---|---|
| **`mcp_dual_brain_decisions`** | ⛔ **DECISÃO [W] — ARCHIVE ou DROP** | 36.607 linhas de trilha de decisão auditável, escrita ativa. **Não é decisão de agente.** Se ARCHIVE: dump + retenção declarada. Se DROP: [W] assume a perda por escrito. |
| `mcp_governance_rules` (4) · `mcp_decision_thresholds` (1) | **ARCHIVE trivial** (5 linhas — cabem num seeder) | volume desprezível, mas são configuração, não lixo |
| as outras 8 (0 linhas) | **DROP** | arquivar zero linhas é cerimônia vazia (precedente T1..T7 do SRS) |

**Ordem obrigatória:** DROP **depois** do refactor (lição E3 do SRS — dropar antes *"derrubaria produção"*).

## Destino por função — realocação

> Medido 2026-07-30 **por símbolo importado**, não por namespace — o namespace diz *que* depende, o `use` diz **de quê**. É o que separa "3 dos 5 sobrevivem" de saber quais peças ficam órfãs:
>
> | Consumidor sobrevivente | `use` exato |
> |---|---|
> | `KB/Http/Controllers/Admin/GraphController.php` | `PolicyEngine` · `GovernanceRulesService` · `ToolRegistry` |
> | `Forja/Http/Controllers/Admin/ProjectsController.php` | `ProjectDecomposerService` |
> | `tests/Feature/Skills/SkillsServiceTest.php` | `SkillsService` |
> | ~~`TeamMcp/…/TeamScopesController`~~ (morre 5º) | `UserScopeService` |
> | ~~`TeamMcp/…/ToolsController`~~ (morre 5º) | `ToolRegistry` |

| Peça | Módulo dono correto | Base da decisão |
|---|---|---|
| `ProjectDecomposerService` + `mcp_projects` + `mcp_project_parts` | **Forja** | é o **único** caso do conjunto com receptor inequívoco: o consumidor sobrevivente *é* o dono do domínio (projeto/parte). Move sem discussão. |
| `SkillsService` + `ScaffoldSkillFromMissionService` + `mcp_skills*` | **Jana** | as tabelas `mcp_skills`/`_versions`/`_labels`/`_approvals`/`_test_runs` **já são da Jana** no mapa de migrations. O service está do lado errado da fronteira desde sempre. |
| `mcp_tool_executions` + `mcp_file_locks` | **Jana** | audit de chamada de tool pareia com `mcp_audit_log`; lock de arquivo pareia com `mcp_work_leases` — as duas irmãs já são da Jana |
| **`PolicyEngine` · `GovernanceRulesService` · `ToolRegistry` · `mcp_governance_rules`** | 🔴 **BURACO — decisão [W]** | o único consumidor sobrevivente é o `GraphController` do **KB**. Mas KB é módulo de **grafo de conhecimento** — dar-lhe um motor de política é criar dono por acidente de acoplamento, não por domínio. O dono conceitual (`Governance`) morre no 6º. **Não escolho por você:** ou KB assume, ou Jana assume, ou o `GraphController` perde a checagem de política. |
| `mcp_user_module_access` + `UserScopeService` | **morrem juntos** | único consumidor era `TeamMcp/TeamScopesController`, que morre no 5º. Sem sobrevivente. |
| **`BrainBService` · `DecisionRouter` · `RiskEngine` · `ConfidenceEngine` · `PatternLearningService` · `PlannerService` · `ReviewerService` · `DecisionPresenter` · `DecisionLinksService` + `mcp_dual_brain_decisions` (36.607) + `mcp_confidence_scores` · `_decision_links` · `_decision_patterns` · `_decision_thresholds`** | **ninguém — é o ADS** | este é o **núcleo** do módulo: o dual-brain que decide. Não tem receptor porque não tem análogo — nenhum módulo vivo decide. Morre com o módulo, e é aqui que vivem as **36.607 linhas escritas hoje às 08:02**. A decisão da Fase 4 (ARCHIVE ou DROP) **é sobre esta linha**, não sobre as outras. |
| 19 telas `.tsx` (`Pages/ads/**`) | **decisão [W]** | maior parque de telas do conjunto; nota média 74, pior `ads/Admin/Graph` (68). Não avaliadas uma a uma. |
| `Console/Commands/*` (7) | **seguem o service que orquestram** | `AdsHealthCommand` morre; `AutoGenerateTasksCommand`/`PlanDecisions`/`ReviewDecisions`/`ProcessBrainB`/`LearnPatterns` morrem com o núcleo; `SkillScaffoldCommand` vai com `SkillsService` → Jana |

**Assimetria que decide o esforço:** 3 grupos têm receptor derivável (Forja, Jana ×2), **1 é buraco de política** e **1 é o núcleo que simplesmente acaba**. O trabalho de realocação do ADS é pequeno; o que é grande é a **decisão sobre o núcleo** — e ela não é de realocação, é de aceitar a perda.

## Fase 5 — Riscos Tier 0

| # | Risco | Severidade | Mitigação |
|---|---|---|---|
| **R1** | **Perda de 36.607 linhas de trilha de decisão** sem decisão explícita | **ALTA** | Fase 4 linha 1 — gate [W] obrigatório antes de qualquer migration |
| **R2** | **Escrita ativa durante a remoção.** Gravou às 08:02 de hoje; um DROP a quente pode falhar ou perder escrita em vôo | **ALTA** | Achar e desligar o produtor **antes** (não medido: quem escreve? cron? job? request?) |
| **R3** | 3 acopladores sobreviventes (KB, Forja, teste) quebram | média | Patch na mesma leva |
| **R4** | **19 telas** somem | média | Decidir receptor por tela antes da E3 |
| **R5** | Skill `ads-route`/`ads-decision-flow` em `.claude/skills/` ficam órfãs | baixa | Aposentar as skills junto |
| **R6** | cross-tenant | **nenhum** | volume 100% em `business_id=1` |

Nenhum check **required** cita ADS.

## Roadmap

| Etapa | O que | Gate [W] |
|---|---|---|
| **E1** | **Achar o produtor** das 36.607 linhas (R2) e medir CT 100 | — |
| **E2** | ⛔ **[W] decide ARCHIVE vs DROP** de `mcp_dual_brain_decisions` (R1) | ✋ **bloqueia tudo** |
| **E3** | Desligar o produtor · patch nos 3 sobreviventes · decidir receptor das 19 telas | ✋ [W] aprova |
| **E4** | Remover `Modules/ADS/` + telas + rotas + permissions + skills · aposentar `modules_statuses.json` | ✋ [W] aprova |
| **E5** | Migration: ARCHIVE/DROP conforme E2 | ✋ [W] aprova |
| **E6** | Smoke real em prod: rotas em 301/410 + estado das tabelas conforme E2 | ✋ [W] confere |
| **E7** | Lápide §5 + `BRIEFING` final | — |

## Resíduo honesto

- **Não sei quem escreve** em `mcp_dual_brain_decisions` — medi o dado, não o produtor. É o **pré-requisito nº 1** (E1), e a pergunta "quem grava" se responde no runtime (`schedule:list`, fila, log), **nunca** parseando código (lápide §5 2026-07-17).
- **CT 100 não medido.**
- **As 19 telas não avaliadas uma a uma** — dono: `screen-coverage:report`.
- **Pest não rodado** (Tier 0 → CT 100).
