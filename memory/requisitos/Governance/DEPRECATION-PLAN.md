---
id: requisitos-governance-deprecation-plan
---

# DEPRECATION-PLAN — Modules/Governance

> **Status:** 📋 Inventário concluído · **Veredito: NÃO deprecar** · **Owner:** [W]
> **Medido em:** 2026-07-29 contra `origin/main` @ `9090ab9852` · **Pedido por:** [W] ("inventário de quem usa e para onde deve ir as funções, para poder apagar o módulo")
>
> ⚠️ **Reconciliação no mesmo dia.** A primeira redação mediu contra `be04516c96` e contou **19 arquivos
> em 8 módulos**, incluindo **8 do módulo `SRS`**. Horas depois o SRS foi **removido do repositório**
> ([#5036](https://github.com/wagnerra23/oimpresso.com/pull/5036) — E5+E6: módulo apagado, 7 tabelas dropadas).
> Os números abaixo foram **re-rodados** contra a base nova: **11 arquivos em 6 módulos + 1 teste**.
> O veredito **não muda** — o bloqueador sempre foi Brief + Admin + o contrato `DriftChecker`, nunca o SRS.
> **Precedentes de formato:** [SRS](../SRS/DEPRECATION-PLAN.md) · [Accounting](../Accounting/DEPRECATION-PLAN.md) · [Crm pipeline](../Crm/DEPRECATION-PLAN-pipeline.md)

---

## ⚠️ ADENDO 2026-07-30 — o limite nº 1 deste doc foi medido (produção)

> Este adendo **fecha o item 1 dos "Limites honestos"** do §0 (*"**Produção.** Volume das 5 tabelas `mcp_*` não foi contado. No SRS foi exatamente isso que virou o plano do avesso."*). **Não altera o corpo** — o corpo fica como registro do que se sabia em 29/07. Onde este adendo e o corpo discordarem sobre **dado de produção**, este vence; sobre **veredito**, ver a nota de decisão no fim.

**Sistema medido:** produção — `APP_ENV=live`, `APP_URL=https://oimpresso.com`, database `u906587222_oimpresso`, **385 tabelas**. Existência por `information_schema.tables` (**não** por exceção capturada — no oimpresso "não existe no Hostinger" pode significar **vive no CT 100**, [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)).
**Data:** 2026-07-30. **Controle positivo:** `business=82` · `users=124` · `transactions=75.255`.

| Tabela | Estado em prod | Escrita mais recente |
|---|---|---|
| **`mcp_sdd_scorecard_history`** | **20 linhas** | **2026-07-30 11:10:05** |
| `mcp_governance_initiatives` | ⚠️ **NÃO EXISTE no Hostinger** | — |
| `mcp_module_grades_history` | ⚠️ **NÃO EXISTE no Hostinger** | — |
| `mcp_observability_spans` | ⚠️ **NÃO EXISTE no Hostinger** | — |
| `mcp_observability_aggregates_daily` | ⚠️ **NÃO EXISTE no Hostinger** | — |
| `mcp_scorecard_runs` | ⚠️ **NÃO EXISTE no Hostinger** | — |

**O que isto muda no plano — 3 consequências:**

1. **O módulo está escrevendo AGORA.** A última linha entrou às **11:10 de hoje**, minutos antes da medição. Isso **reforça** o veredito "não deprecar" do corpo com dado, não com estrutura: não é casca esquecida nem hipótese — é escrita ativa.
2. **A "posse não declarada" das 5 tabelas ganha uma explicação mais simples, e mais incômoda:** 5 das 6 **não existem no Hostinger**. Nenhum `SCOPE.md` as reivindica *e* elas não estão no banco de produção. A hipótese forte é que vivem no **CT 100** (o stack de observabilidade mora lá). **Isso não foi verificado** — e enquanto não for, é hipótese, não achado.
3. **A Fase de dados fica BLOQUEADA, não vazia.** Ao contrário do SRS — onde medir prod **colapsou** a etapa (0 linhas → DROP em tudo) — aqui medir prod **não resolve**: transfere a pergunta pro CT 100. Ler "ausente" como "vazio" seria cometer o erro do SRS ao contrário.

⛔ **Pré-requisito duro que continua aberto:** `tailscale ssh root@ct100-mcp`. Sem isso, a decisão por tabela nasce com o mesmo `?` que condenou o plano do SRS.

**Acoplamento — conferido de forma independente, e bate.** Medição de 30/07 com `git grep -lF 'Modules\Governance\'` fora da própria pasta, **excluindo `memory/` e `.claude/`** → **11 arquivos** (Admin ×3 · Brief ×2 · Cms · Connector · Jana ×2 · TeamMcp · 1 teste), idêntico ao re-run do corpo. **Denominador declarado**, porque a 1ª contagem do corpo (27) usava escopo mais largo — não são números em conflito, são universos diferentes.

> 🪞 **Nota de método:** a medição de 30/07 caiu **no mesmo bug** que o §0 deste doc registra — `git grep` de namespace devolvendo **0 para todos os módulos** por escaping colapsado. Pego pelo mesmo antídoto: controle positivo (`Modules\Jana\` fora de `Modules/Jana/` = **414**; se desse 0, o instrumento estava errado). Duas sessões independentes, mesma armadilha, mesmo antídoto — o §0 deste doc está calibrado.

**Nota de decisão [W] — 2026-07-30.** O corpo conclui **"NÃO deprecar"** (29/07). Em 30/07 [W] decidiu deletar assim mesmo: *"todos esses eu vou deletar: Admin, ADS, TeamMcp, Brief, Auditoria, Governance, SRS"*. O veredito técnico do corpo **não foi refutado** — segue valendo como análise, incluindo o custo e o ganho negativo. O que mudou é que a decisão é de [W], e ela é soberana. A execução vira o **§9 (roadmap condicional)** deste doc, agora ativo, com a ordem cross-módulo em [proposals/2026-07-30-deprecar-6-modulos-governanca-ordem-topologica.md](../../decisions/proposals/2026-07-30-deprecar-6-modulos-governanca-ordem-topologica.md) — o Governance é o **6º e último**, porque 5 dos 11 acopladores saem com Admin, Brief, ADS e TeamMcp.

---

## TL;DR

O inventário foi feito para viabilizar a deleção e **concluiu o contrário**: `Modules/Governance` não é
uma casca esquecida, é **infraestrutura consumida por 6 módulos vivos** — incluindo o `Modules/Brief`,
que alimenta o Daily Brief de toda sessão (Tier A). Não existe módulo receptor para as duas peças
centrais, e os destinos que existiriam (`Observability`, `DesignSystem`) **não existem no repositório**.

O que a investigação encontrou de real não foi excesso de módulo, foi **posse não declarada**: 5 tabelas
que o módulo cria e que **nenhum dos 37 `SCOPE.md`** reivindica. Além de ~3 peças genuinamente mortas,
que valem uma poda.

Se [W] decidir deprecar assim mesmo, o §9 traz o roadmap condicional — mas ele é caro e o ganho é negativo.

---

## 0. Como isto foi medido (e os limites do que foi medido)

Cada regra abaixo pegou um erro real **durante esta própria investigação**. Ficam registradas porque
o próximo a mexer nisto vai cair nelas de novo.

| Regra | O erro que ela pegou aqui |
|---|---|
| **Controle positivo em toda varredura** — buscar algo que se *sabe* existir; se não voltar, o instrumento está errado | O acoplamento por namespace deu **0 para todos os módulos**. O real é **27**. Causa: escaping consumido dentro de `$( )`. O `0` chegou a ser reportado antes de ser corrigido |
| **Escopo total, nunca lista de pastas escolhida a dedo** | `governance:staging-freshness-alert` parecia órfão porque a varredura não incluía `docker/`. Ele **é invocado** pelo sentinel do CT 100 |
| **"Quem roda" pergunta ao runtime, não ao grep** | Módulo nWidart acopla por **registro** (ServiceProvider → schedule/rota/comando), não por `use`. Grep de namespace dá 0 em módulo que está no ar |
| **Case-sensitivity** | `git ls-tree Modules/.../Pages/Governance` deu vazio; as telas vivem em `Pages/governance` minúsculo. Quase virou "as telas não existem" |
| **Medir produção antes de decidir** | No SRS, medir o banco colapsou uma etapa inteira do plano e inverteu a ordem |
| **Ordem: código sai antes das tabelas** | O plano do SRS dropava tabela antes do refactor — teria derrubado prod com Model vivo |

**Limites honestos deste documento** — o que **não** foi medido:

1. **Produção.** Volume das 5 tabelas `mcp_*` não foi contado. No SRS foi exatamente isso que virou o plano do avesso.
2. **Runtime.** `route:list` / `schedule:list` / `artisan list` não foram rodados no CT 100. Tudo aqui é estrutural (declarado em git).
3. **Controle negativo.** Nenhuma peça marcada "morta" foi removida para ver o CI ficar vermelho. Sem isso, "morta" é hipótese, não fato.
4. **`Modules/Auditoria`** aparece citando `AuditDrillDownService` por nome de classe, mas **não** por `use Modules\Governance\...`. Fica como consumidor **não confirmado**.

---

## 1. Superfície do módulo

**137 arquivos** (`git ls-tree -r origin/main Modules/Governance | wc -l`).

| Bloco | Qtd |
|---|---:|
| `Tests/Feature` | 53 |
| `Console/Commands` | 17 |
| `Services` (incl. 12 `Checkers` + 2 `Concerns`) | ~34 |
| `Http/Controllers` | 8 |
| `Database/Migrations` | 5 |
| `Http/Requests` | 4 |
| `Entities` (`Initiative`) · `Contracts` (`DriftChecker`) · `Http/Middleware` (`ActionGate`) | 3 |

**Telas:** 7 arquivos `.tsx` em `resources/js/Pages/governance/` (minúsculo) — `Audit`, `Policies`,
`DriftAlerts`, `DsRollout`, `ModuleGrades/{Index,Show}` + `Dashboard` (legado).

**Nota de contexto ([BRIEFING](BRIEFING.md), medido 2026-07-17):** o centro de gravidade já **saiu do PHP** —
73 commits em 8 dias, sendo **68 em `scripts/governance/`** (Node) e **1** em `Modules/Governance/`.
A governança executável hoje é Node + gates de CI; o módulo Laravel é o resíduo que serve telas e cron.

---

## 2. Quem consome — 11 arquivos de código, 6 módulos (+1 teste)

Varredura: `git grep -lE 'Modules\\\\Governance' origin/main -- ':!Modules/Governance/*' '*.php' '*.ts' '*.tsx'`
(27 arquivos no total incluindo docs/baselines; 19 são código).

| Módulo | Arq. | O que consome | Se o Governance sumir |
|---|---:|---|---|
| **Brief** ⚠️ | 2 | `GenerateBriefCommand` injeta **8** `*BriefLineService`: `Sdd`, `PlanHealth`, `ShippedLog`, `AdrReview`, `AdrPendente`, `AgentOutcome`, `ObraParada`, `ExposicaoTier0` | **Daily Brief perde 8 seções.** É Tier A — roda no SessionStart de toda sessão |
| **Admin** | 3 | `Initiative` + `InitiativeService` (GovernanceV4, ScreenReview); `ObservabilitySnapshotService` (RagQuality) | **GovernanceV4 quebra** — e ele é o *sucessor* aparente, com flag `v4_enabled` default `true` |
| **Jana** | 2 | Reconcilers usam `DriftChecker`, `DeployDriftChecker`, `MeilisearchSettingsDriftChecker`, `DriftCheckResult`, `DriftFinding` | pipeline de reconciliação quebra |
| **TeamMcp** | 1 | `SyncMemoryWebhookController` → `DriftChecker` | webhook git→DB perde o checker |
| **Connector** | 1 | `ConnectorHealthCommand` | health check quebra |
| **Cms** | 1 | `SiteContentService` | a confirmar (pode ser menção) |
| ~~SRS~~ | ~~8~~ | ~~`DocValidator`, `ModuleAuditor`, `DocValidationRun`, 2 Controllers, 2 Commands~~ | **já morreu** — módulo removido em [#5036](https://github.com/wagnerra23/oimpresso.com/pull/5036) ([ADR 0357](../../decisions/0357-deprecar-srs-sucessor-kb-jana-governance.md) E5+E6). Era o maior consumidor e saiu de cena sem mover o veredito |
| `tests/` | 1 | `DashboardExtensionTest` | — |

**Fora do PHP:** `.claude/hooks/preflight-new-capability.mjs`, `.github/workflows/governance-drift.yml`,
`.github/workflows/module-grades-gate.yml`, `scripts/governance/cron-watchdog.mjs`,
`docker/oimpresso-staging/staging-freshness-sentinel.sh`, 7 skills em `.claude/skills/`.

**Schedules:** 6 diários em `app/Console/Kernel.php` — `governanca:ciclo-diario`, `module:grade-snapshot`,
`governance:scorecard-snapshot --alert`, `governance:sdd-scorecard-snapshot`, `governance:initiative-sync`,
`governance:adr-review-flush`.

---

## 3. O bloqueador: duas peças são contrato público, não feature

Esta é a razão central do veredito. Não são telas — são **interfaces que outros módulos implementam e consomem**.

### 3.1 `Contracts/DriftChecker` + `DriftCheckerRegistry` + 12 Checkers

Framework plugável ([ADR 0216](../../decisions/0216-governance-drift-framework-driftchecker-plugavel.md)),
com `drift_framework_enabled=true`. Consumido por **Jana, TeamMcp, Whatsapp, Connector**, por hook
(`preflight-new-capability.mjs`), por workflow de CI (`governance-drift.yml`) e pelo sentinel do CT 100.

Apagar não elimina este código — **redistribui** para 6 lugares. Cada módulo passaria a ter seu próprio
checker de drift, sem registry comum. É acoplamento pior, não menor.

### 3.2 Os 8 `*BriefLineService`

São os injetores de conteúdo do Daily Brief. O dono do brief (`Modules/Brief`) **depende** deles.
Mover para o Brief inverteria a dependência (Brief passaria a conhecer governança, ADR, SDD, DORA e
exposição Tier 0), o que engorda um módulo cujo escopo é montar e servir o brief.

---

## 4. Destino por função — se cada peça fosse realocada

| Peça | Módulo responsável | Base da decisão |
|---|---|---|
| 5 tabelas + `module:grade*`, `scorecard*`, `initiative-sync`, `detect-drift`, `ciclo-diario`, `adr-review-flush` | **Governance** (declarar posse) | é quem cria, escreve via cron e lê nas telas |
| `InitiativeService` + `mcp_governance_initiatives` | **Governance** dono · **Admin** consome | mesmo padrão que ADS↔Governance usa para `mcp_governance_rules` |
| `charter:audit\|health\|metrics` | **Governance** | **não existe** módulo DesignSystem — `_DesignSystem` é pasta de memória |
| `observability:aggregate-daily` + `mcp_observability_spans` | **Governance** | não existe um módulo `Observability` (planejado — não existe). O `RagQualityDashboard` do Admin é observabilidade **de RAG**, tema distinto |
| Telas `audit`, `policies`, `drift`, `module-grades`, `ds-rollout` | **Governance** | 5 rotas vivas |
| `DriftChecker` + Registry + Checkers | **Governance** | contrato público (§3.1) |
| 8 `*BriefLineService` | **Governance** produz · **Brief** consome | §3.2 |
| Gates em `scripts/governance/` (Node) | **fora de módulo, por design** | não são Laravel; é onde a governança roda hoje |
| `Dashboard` legado + `ActionGate` | **ninguém — morrem** | §6 |

**Fronteiras já declaradas pelo próprio [`SCOPE.md`](../../../Modules/Governance/SCOPE.md) (`not_contains`)** — continuam válidas:
decision flow/policy engine/skills → `Modules/ADS` · tokens MCP + Identity Mesh → `Modules/TeamMcp` ·
knowledge browsing (ADRs read-only) → `Modules/KB` · Module Grade v4 tri-pane → `Modules/Admin`
([ADR 0122](../../decisions/0122-admin-center-ct100.md): *"separação intencional, NÃO unificar"*).

**Direção inversa:** o `SCOPE.md` declara em `drift_alerts` que o dashboard de MCP usage cross-team
(`/jana/admin/governanca`, hoje em `Modules/Jana`) **pertence ao Governance** e deve migrar **para cá**.

**Fronteira não resolvida:** `governance:ragas-eval-alert`. O sinal RAGAS é do Jana
(`Admin/QualidadeController`); o comando só registra o alerta. Dono do sinal ≠ dono do alerta — decisão [W].

---

## 5. O que morre agora (poda, independe de deprecação)

| Peça | Prova | Cuidado |
|---|---|---|
| `Http/Middleware/ActionGate` | alias registrado (`GovernanceServiceProvider.php:114`), **0 rotas** o usam; docblock diz *"uso (futuro)"* | é o *"chokepoint que o fluxo real não atravessa"* — 0 cobertura com cara de defesa |
| `DashboardController` + `Pages/governance/Dashboard.tsx` | `/governance` é **302 → `/ia`**; rota nomeada `admin.dashboard.legacy`; `compliance_pct` é `// = 80` somado à mão | **5 testes** citam o controller |
| `charter:metrics` · `governance:health` | **0 invocadores** no repo inteiro | "não automatizado" ≠ "morto" — podem ser rodados à mão. Confirmar com [W] |

⚠️ **`governance:staging-freshness-alert` NÃO entra na poda** — é invocado por
`docker/oimpresso-staging/staging-freshness-sentinel.sh:61`.

---

## 6. Posse das 5 tabelas — o buraco real

O `SCOPE.md` declara `db_tables_owned: []`, mas o módulo **cria 5 tabelas**. Varredura nos 37 módulos:
**nenhum SCOPE.md as reivindica** (0/37).

| Tabela | Criada por | Declarada por |
|---|---|---|
| `mcp_module_grades_history` | Governance | **ninguém** |
| `mcp_scorecard_runs` | Governance | **ninguém** |
| `mcp_observability_spans` | Governance | **ninguém** |
| `mcp_governance_initiatives` | Governance | **ninguém** |
| `mcp_sdd_scorecard_history` | Governance | **ninguém** |

O `[]` é fóssil, não mentira: o `SCOPE.md` é da v1.0.0 (2026-05-05), quando o módulo era MVP e dizia
*"Inertia frontend pendente"*. As migrations chegaram depois (mai–jun/2026) e a declaração nunca acompanhou.

**O projeto já tem o padrão para corrigir isto** — separa criador / dono / consumidor na prosa do SCOPE:

- `mcp_governance_rules`: ADS declara posse com nota *"dono: migration + write; Governance CONSOME — ActionGate lê + toggle. Fronteira reconciliada 2026-07-26"*
- `mcp_audit_log`: criado por Jana, posse do TeamMcp, UI no Governance

**Ação:** 1 PR pequeno preenchendo `db_tables_owned` com as 5 + registrando `mcp_governance_rules` como
consumida no campo (hoje está só em comentário).

> Registro diagnóstico: o módulo que roda `governance:detect-drift` está com drift na própria declaração de posse.

---

## 7. O que falta medir antes de qualquer ato irreversível

| # | Medição | Onde | Por que importa |
|---|---|---|---|
| 1 | `SELECT COUNT(*)` nas 5 tabelas + `MAX(created_at)` | prod (Hostinger) | no SRS, tabelas vazias colapsaram uma etapa inteira. Aqui podem estar cheias e reforçar o "não apagar" |
| 2 | `route:list` + `schedule:list` + `artisan list` | CT 100 | oráculo de runtime; grep não responde "quem roda" |
| 3 | Controle negativo por peça podada | CI | remover e ver ficar vermelho. Verde = ou a peça é morta, ou o gate é teatro |

---

## 8. Riscos se a deleção for executada mesmo assim

| # | Risco | Gravidade |
|---|---|---|
| R1 | **Daily Brief perde 8 seções** — Tier A, roda no SessionStart de toda sessão | 🔴 alta |
| R2 | **GovernanceV4 (`Modules/Admin`) quebra** — o sucessor aparente é consumidor; flag default `true` | 🔴 alta |
| R3 | **`module:grade` some** — é gate de CI com baseline em `governance/module-grades-baseline.json` | 🔴 alta |
| R4 | **6 schedules diários órfãos** — falham silenciosamente ou somem sem alerta | 🟠 média |
| R5 | **Framework de drift redistribuído** para 6 módulos, sem registry comum | 🟠 média |
| R6 | 5 tabelas sem dono declarado — ninguém sabe quem deveria dropar | 🟠 média |

---

## 9. Roadmap condicional — só se [W] decidir deprecar apesar do veredito

Ordem obrigatória (a lição do SRS: **código sai antes das tabelas**; dropar antes derruba prod).

| Etapa | O quê | Gate |
|---|---|---|
| **E0** | ADR de deprecação com sucessor nomeado para **cada** peça do §4 | [W] |
| **E1** | Criar casa para o framework de drift (módulo novo ou `app/Domain/`) e migrar Contract + Registry + 12 Checkers | [W] |
| **E2** | Migrar os 8 `*BriefLineService` — decidir se o Brief passa a conhecer governança | [W] |
| **E3** | Reapontar os 11 consumidores (Admin 3 · Brief 2 · Jana 2 · Cms 1 · Connector 1 · TeamMcp 1 · tests 1) | — |
| **E4** | Migrar telas + 6 schedules + `module:grade` (gate de CI) | [W] |
| **E5** | `git rm` do módulo **+ drop das 5 tabelas junto**, após 30d de espera | [W] |

**Custo estimado:** substancialmente maior que o do SRS — lá as tabelas estavam vazias e o acoplamento
era de 6 arquivos; aqui são 11 arquivos de código, 6 módulos vivos, 2 contratos públicos e 1 gate de CI.
A diferença decisiva não é a contagem: é que **o SRS não tinha consumidor de código rodando em produção**
(uso zero medido em prod), enquanto aqui o Daily Brief Tier A e o GovernanceV4 dependem do módulo hoje.

---

## 10. Recomendação

**Não deprecar.** Fazer, em vez disso, os dois PRs pequenos que o inventário justificou:

1. **Declaração de posse** — `db_tables_owned` com as 5 tabelas + `mcp_governance_rules` como consumida (§6)
2. **Poda** — `ActionGate` + `Dashboard` legado + os 2 comandos sem invocador, com os 5 testes que os citam (§5)

E, se houver apetite por movimento maior, o que o `SCOPE.md` já pede é a **direção inversa**: trazer o
dashboard de MCP usage cross-team do Jana para cá (Fase 5).
