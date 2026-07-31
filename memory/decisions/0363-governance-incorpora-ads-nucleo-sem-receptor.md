---
slug: 0363-governance-incorpora-ads-nucleo-sem-receptor
number: 363
title: "Modules/Governance incorpora o Modules/ADS — a política tinha posse partida e o núcleo dual-brain não tem receptor"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-31"
accepted_via: "Decisão de [W] tomada em sessão 2026-07-31, depois de a medição mostrar que 'apagar' e 'incorporar' resolviam problemas diferentes: a sessão começou pra deletar o módulo e terminou incorporando-o. O recorte revisado (política→Governance · registro→Forja · skills→Jana · núcleo morre · URLs congeladas) foi aprovado por [W] no mesmo dia. O merge deste PR é o ato formal de ratificação (R10) — o agente não ratifica."
module: ads
quarter: 2026-Q3
tags: [governanca, ads, deprecacao, incorporacao, dual-brain, policy-engine, urls-congeladas, multi-tenant]
supersedes:
  - 0145-ia-administradora-pivot-ads-fsm-piloto-cobradora
supersedes_partially: []
superseded_by: []
related:
  - 0086-fase-5-mvp-governance-actiongate-warn
  - 0087-drift-resolution-sem-mover-url
  - 0076-skills-db-primary-git-destino-drift-alert
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0357-deprecar-srs-sucessor-kb-jana-governance
  - 0360-deprecacao-admin-center-supersede-0122
pii: false
review_triggers:
  - "Algum módulo vivo passar a DECIDIR (roteamento risco/confiança com outcome que fecha) — o núcleo dual-brain volta a ter receptor e esta decisão precisa ser revista"
  - "Cliente pagante pedir agente administrativo autônomo (sinal qualificado, ADR 0105) — reabre a discussão que a 0145 antecipou sem sinal"
  - "Decisão automatizada passar a afetar cliente final — a obrigação LGPD Art. 20 herdada da 0145 (§Herança) sai do prospectivo e vira entrega"
  - "Decidir se Modules/Governance fica ou sai (deixado em aberto por esta ADR) — o destino da política incorporada depende disso"
---

# ADR 0363 — Governance incorpora o ADS

## Contexto

O `Modules/ADS` entrou na fila de deprecação de 2026-07-30 como **4º de 6**
([DEPRECATION-PLAN](../requisitos/ADS/DEPRECATION-PLAN.md)), marcado como *"o único dos 6 com volume alto
e escrita ATIVA"*. A sessão de 31/07 começou pra **apagar** o módulo e terminou **incorporando-o** ao
`Modules/Governance`. A mudança de rumo não foi preferência — foi consequência de três medições.

### 1. A política tinha posse partida — e esse era o defeito real

`mcp_governance_rules` (as "meta-skills", regras SOFT de governança) estava **dividida entre dois
módulos**, medido no repo em 2026-07-31:

| Perna | Onde vive |
|---|---|
| Migration | `Modules/ADS/Database/Migrations/2026_05_03_220001_create_mcp_governance_rules_table.php` |
| Escrita (criar/validar meta-skill) | `Modules/ADS` — `MetaSkillsController` + `StoreGovernanceMetaSkillRequest` |
| Leitura + toggle (UI de policies) | `Modules/Governance` — **8 arquivos + 4 testes** |

E **duas telas leem as mesmas 4 linhas**: `GovernanceRulesService::listAll()` (linha 80 — morava em
`Modules/ADS/Services/` até o [PR #5128](https://github.com/wagnerra23/oimpresso.com/pull/5128), que o
levou pro destino desta ADR:
[`Modules/Governance/Services/GovernanceRulesService.php`](../../Modules/Governance/Services/GovernanceRulesService.php))
e `PolicyToggleService::listPolicies()`
([`Modules/Governance/Services/PolicyToggleService.php:35`](../../Modules/Governance/Services/PolicyToggleService.php)) —
as duas `DB::table('mcp_governance_rules')->get()`, sem filtro. Aqui **não há violação Tier 0**: a tabela
não tem coluna `business_id` (é config global de superadmin, conferido na migration, ADR 0093 não se
aplica). O que há é **dois donos para o mesmo fato** — o padrão que a Constituição v2 §5 (SoC brutal)
existe para impedir.

Fundir resolve isso por construção. Deletar não: deletar o ADS deixaria a migration órfã e a UI do
Governance lendo uma tabela sem dono declarado.

### 2. O núcleo dual-brain não tem receptor porque nenhum módulo vivo decide

O coração do ADS — `DecisionRouter` · `RiskEngine` · `ConfidenceEngine` · `BrainBService` ·
`PatternLearningService` · `PlannerService` · `ReviewerService` — não tem análogo em módulo nenhum. E o
retrato do que ele produziu (**medido em prod `u906587222_oimpresso`, 2026-07-31**, recibo no
[handoff da sessão](../handoffs/2026-07-31-1636-ads-incorporado-pelo-governance-3-de-7.md)):

| Métrica | Valor |
|---|---:|
| Total de decisões em `mcp_dual_brain_decisions` | **36.862** |
| `resolved_by` preenchido | **41** (0,11%) |
| `pr_url` preenchido | **0** |
| `commit_sha` preenchido | **0** |
| Tenants | `business_id=1` apenas (negócio interno, **não** o cliente piloto) |

**`outcome='cancelled'` em 100% NÃO significa "canceladas"** — é o `->default('cancelled')` da coluna
([migration `2026_05_03_000004`, linha 43](../../Modules/ADS/Database/Migrations/2026_05_03_000004_create_mcp_dual_brain_decisions_table.php)),
e o `DecisionPresenter` o exibe como *"Aguardando você decidir"*. O retrato honesto é **36.862 itens que
nunca saíram do estado inicial**. Sem outcome o `PatternLearning` não aprende; sem aprender, nada é
promovido; e tudo continua indo pra fila. Era um laço que dependia de um passo humano que nunca teve dono.

Produzir isso custava: **5 crons** em `live` mais o daemon systemd `ads-brain-a` no CT 100
(`Restart=on-failure`, ativo desde maio; journal de ~73 dias com **16.832 `HTTP 503`**). Ambos desligados
em [PR #5127](https://github.com/wagnerra23/oimpresso.com/pull/5127), já deployado, com smoke real. A fila
**parou de crescer** — vinha a +204 linhas/dia e hoje não tem nenhum produtor.

### 3. A palavra "governance" nomeia três coisas — e a que morde não é módulo

A dúvida de [W] que reorientou a sessão foi *"ads e governance são a mesma coisa?"*. Não são, e a
confusão tem causa medível:

| # | O que é | O que é de fato |
|---|---|---|
| 1 | `scripts/governance/*.mjs` — pasta na raiz, **não é módulo** | onde vive a maior parte da maquinaria de gate |
| 2 | `Modules/Governance` — o módulo Laravel | 7 telas · 5 tabelas (4 inexistentes em prod) |
| 3 | `GovernanceRulesService` + `mcp_governance_rules` | vivia **dentro do ADS** |

**O peso de CI não está no módulo.** Medido em
[`governance/required-checks-baseline.json`](../../governance/required-checks-baseline.json) (2026-07-31):
são **35 contextos required** (34 classic + 1 ruleset), e **exatamente 1** roda artisan de
`Modules/Governance` — o `ADR 0216 PR scan (governance:audit --diff-only)`, único workflow com
`artisan governance:` no repo. O resto vem de `scripts/governance/*.mjs` e de toolchain que não é
"governance" em sentido nenhum (Vite, Pest, PHPStan, ESLint, Stylelint, gitleaks, visual-regression).

Isso **rebaixa o risco** de mexer nos dois módulos e é o que torna a incorporação uma operação de
arrumação, não de infraestrutura crítica.

> ⚠️ **Correção de um número que circulou nesta sessão.** O
> [handoff da sessão](../handoffs/2026-07-31-1636-ads-incorporado-pelo-governance-3-de-7.md)
> diz que *"34 dos 35"* required vêm de `scripts/governance`. **É falso como está escrito** — basta ler a
> lista pra ver `Frontend / Vite build`, `PHP / Pest`, `PHPStan`, `gitleaks`. O fato que sustenta o
> argumento é o outro lado da conta, e esse é verificável: **1 de 35** vem do módulo. Registrado aqui em
> vez de repetido — a lição é a de sempre ([`proibicoes.md`](../proibicoes.md) §5, LC-08): número que
> outro sistema sabe melhor se aponta ou se mede, não se restateia.

## Decisão

**O `Modules/Governance` incorpora o `Modules/ADS`.** O módulo ADS deixa de existir como unidade; suas
peças vão para donos determinados por **domínio**, não por acidente de acoplamento:

| Peça | Destino | Base da decisão |
|---|---|---|
| `PolicyEngine` · `PolicyResult` · `GovernanceRulesService` · `mcp_governance_rules` | **Governance** | fecha a posse partida do §1 — o dono conceitual passa a ser o dono do código ([PR #5128](https://github.com/wagnerra23/oimpresso.com/pull/5128)) |
| `SkillsService` · `ScaffoldSkillFromMissionService` · `skill:scaffold` | **Jana** | as 5 tabelas `mcp_skills*` **já são da Jana**; o service já importava `Modules\Jana\Entities\Mcp\McpSkill` ([PR #5129](https://github.com/wagnerra23/oimpresso.com/pull/5129), merged) |
| `ToolRegistry` · `UserScopeService` · `ProjectDecomposerService` | **Forja** | 3 dos 4 consumidores vivos são a Forja |
| **Núcleo dual-brain** + `mcp_dual_brain_decisions` | **ninguém — morre** | sem receptor: nenhum módulo vivo decide (§2) |

### O núcleo morre, e isso é aceitar uma perda, não realocar

Não existe módulo que possa herdar o dual-brain, porque a capacidade que ele implementa — rotear uma
ação por risco e confiança até um outcome — **não é exercida por nenhum outro lugar do sistema**. Herdar
o código sem herdar a capacidade seria criar dono por acidente. A decisão é **aceitar a perda**, com
`mcp_dual_brain_decisions` indo de **ARCHIVE → DROP** preservando as 41 linhas que tiveram decisão humana
(dump no CT 100, **nunca em git** — 87,75 MB). O daemon **não volta a ser ligado**; só se o Governance
ganhar um consumidor real da fila.

### URLs, rotas, permissions e `ads_module` ficam CONGELADOS

Nada de `/ads/admin/*` muda de endereço. O congelamento cobre quatro superfícies, e o motivo de cada uma
é mecânico:

1. **URLs** — a [ADR 0087](0087-drift-resolution-sem-mover-url.md) decide *"drift resolution = mover só o
   controller físico; URL fica onde está"*, e o `governance/ghost-rename-map.json`, **curado em
   2026-07-31**, diz textualmente que `/ads/admin/*` estão *"preservadas (ADR 0087)"*. Esta decisão foi
   tomada **com** a 0087, não contra ela (ver §Relação com o canon).
2. **Nomes de rota** (`ads.admin.*`) — Pages React resolvem por `route('ads.admin.…')`; renomear quebra
   em runtime, não em build.
3. **Permissions Spatie** — o `AdsAdminSkillsPermissionsSeeder` (ADR 0076) **grava 6 linhas na tabela
   `permissions`** (`ads.admin.skills.{read,edit,test,approve,publish,config}`), e o
   `DataController::user_permissions()` declara mais 4 na tela de Roles (`ads.access`,
   `ads.decisoes.review`, `ads.decisoes.approve`, `ads.policy.manage`). Concessões vivem em
   `role_has_permissions`/`model_has_permissions` **por id de linha**; renomear o prefixo revoga acesso
   **em silêncio** — sem erro, sem log, só a tela sumindo pra quem tinha direito.
4. **Chave de assinatura `ads_module`** — o gate de sidebar é
   `hasThePermissionInSubscription($business_id, 'ads_module', 'superadmin_package')`. Mudar a chave é
   mudar o que cada pacote assinou, o que é ato de superadmin, não de refactor
   (§Multi-tenant de [`memory/proibicoes.md`](../proibicoes.md)).

### O que esta ADR NÃO decide

- **Se o `Modules/Governance` fica ou sai.** Ele é o 6º da fila original. Fica em aberto — e é
  justamente por isso que o destino da política incorporada é um `review_trigger` desta ADR.
- **O destino das 12 telas do núcleo, uma a uma.** Não foram avaliadas individualmente. Já há um defeito
  concreto conhecido: `Decisoes.tsx:219` linka **cada linha da lista** para `DecisaoShow` — matar o
  detalhe sem patchar a lista deixaria a sobrevivente com tudo em 404.
- **O `loadMigrationsFrom` do Governance.** O módulo é 1 de 4 (em 33) sem `loadMigrationsFrom`, e não há
  loader global — é a causa de **4 das 5 migrations dele nunca terem rodado em prod**. Ligar no
  automático **acorda 4 migrations dormentes e cria 4 tabelas que ninguém pediu**. É decisão [W]
  separada.
- **A execução da remoção.** `git rm` do módulo, das telas e das rotas é a parte 6 do plano, gated por
  [W]. Esta ADR registra a decisão; não a executa.

## Relação com o canon

Esta decisão contradiz canon aceito em dois pontos e é **compatível** com um terceiro. Os três estão
registrados aqui porque ADR é append-only e silêncio vira drift.

### `supersedes: [0145]` — a ADR-mãe do pivot ADS↔FSM

A [ADR 0145](0145-ia-administradora-pivot-ads-fsm-piloto-cobradora.md) (`aceito`/`ativo`, 2026-05-15)
decidiu *"pivotar Modules/ADS de roteador de atendimento pra roteador de ações administrativas
auditáveis, conectando-o ao FSM Pipeline"*. Ela afirma o ADS como sistema de decisão vivo; esta ADR o
dissolve. São incompatíveis, logo é supersessão total, não emenda.

**O que se perde é um plano, não código em produção.** Medido no repo em 2026-07-31 — nenhuma das
entregas da 0145 existe:

| Entrega prevista pela 0145 | Estado real |
|---|---|
| `Modules/ADS/Services/FsmActionBridge` (US-ADS-070) | **não existe** — a única menção no código é um comentário em `ChatCopilotoAgent.php:43` dizendo que está *"fora do escopo desta US"* |
| `CobradoraAgent` + 5 tools (US-COPI-080) | **não existe** |
| Audit Card `/copiloto/decisoes/{id}/revisao` (US-ADS-071) | **não existe** — `client_visible` e `audit_card_url` têm **0 ocorrências** no código |
| Tabela `cobranca_tentativas` · action FSM `cobrar_fatura` | **não existem** |
| As 12 US (US-ADS-070..074 · US-COPI-080..086) | nunca entraram em SPEC — sobrevive só o batch em [`COBRADORA-PILOTO-TASKS-BATCH.md`](../requisitos/Jana/COBRADORA-PILOTO-TASKS-BATCH.md) |

A 0145 nasceu de uma hipótese de estado-da-arte, não de sinal de cliente — exatamente o caso que a
[ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) manda parquear como feature wish em vez de
manter como decisão ativa. Ficou 2,5 meses `ativo` sem uma linha entregue. Superseder é reconhecer isso.

**Marcação:** 0145 recebe `status: superseded` · `lifecycle: substituido` · `superseded_by: [0363-…]`,
aplicada com `node scripts/governance/adr-supersede.mjs --new 0363 --old 0145 --write` — transação
atômica que **só toca frontmatter** (corpo byte-idêntico, append-only intacto; exceção do gate exige o
label `adr-metadata-normalization`, ADR 0257). Medido: **17 arquivos / 29 ocorrências** citam a 0145;
nenhuma precisa mudar — handoffs e a ADR 0146 são append-only e continuam corretos citando uma decisão
que na data deles estava viva.

#### Herança da 0145 que NÃO morre: o Audit Card / LGPD Art. 20

A 0145 tinha `amends: [0094]` — ela **acrescentou à Constituição** o princípio Tier 0 *"Audit Card
visível ao cliente final"* (LGPD Art. 20 + ANPD NT 12/2025: decisão automatizada que afete o titular
exige informar que é automatizada e oferecer canal de revisão humana).

**Esse princípio sobrevive à supersessão.** Ele não é do ADS — é do sistema, e a obrigação é legal, não
arquitetural: não deixa de existir porque uma ADR morreu. Esta ADR o **carrega adiante** como obrigação
permanente sobre qualquer decisão automatizada futura, em qualquer módulo.

A exposição hoje é **zero, e prospectiva**: nenhuma decisão automatizada chegou a cliente final —
`pr_url` e `commit_sha` em 0, 100% do volume em `business_id=1` (interno), e o Audit Card nunca foi
construído. Ou seja, não há passivo a remediar; há um dever a cumprir **antes** do primeiro agente que
volte a decidir sobre titular. O `review_trigger` correspondente está no frontmatter.

### Emenda à 0086 — a fronteira *decision flow ≠ governance UI* cai

A [ADR 0086](0086-fase-5-mvp-governance-actiongate-warn.md) criou o `Modules/Governance` justificando
*"Modules/Governance dedicado — separação semântica vs ADS (decision flow ≠ governance UI)"*. Essa
fronteira vive hoje no `not_contains` do
[`Modules/Governance/SCOPE.md:15`](../../Modules/Governance/SCOPE.md) — *"Decision flow
(Risk/Confidence/Policy Engine) → Modules/ADS"*.

**Ela foi relida e mantida em 2026-07-31**, e isso é fato de git, não impressão: o commit `c9da3549d55d`
(#5122, deleção do TeamMcp) reescreveu as linhas **17-18** do mesmo bloco (`Tokens MCP CRUD` e
`Identity Mesh`, de `TeamMcp` para `Forja`) e **deixou a 15 intacta** — `3 insertions(+), 3 deletions(-)`.
Quem passou por ali no mesmo dia olhou a linha e não a mexeu.

Esta ADR a revoga, com o motivo explícito: a fronteira era **semântica** (dois conceitos distintos) e o
que a medição encontrou foi **posse partida do mesmo dado**. Distinguir `PolicyEngine` (firewall HARD,
código no git) de `mcp_governance_rules` (regras SOFT, configuráveis na UI) continua sendo distinção
verdadeira e útil — o que deixa de valer é que essas duas coisas justifiquem **dois módulos**. A 0086
segue `ativo` em tudo o mais (scaffold, ActionGate warn-only, grupo GOVERNANÇA na sidebar); só a linha 15
do `not_contains` sai, no PR que executar a incorporação (parte 6).

### Errata à 0087 — o congelamento é cumprimento, não exceção

A [ADR 0087](0087-drift-resolution-sem-mover-url.md) decide *"URL fica onde está"*. Registra-se aqui, pra
que nenhuma sessão futura leia a incorporação como licença pra mover endereço: **a 0087 foi honrada, não
contornada**. As URLs `/ads/admin/*` seguem congeladas exatamente pelo padrão que ela prescreve — mover o
controller físico e trocar só o FQCN no `Route::` (`[Class::class, 'method']`), mantendo URL e route name.

O que esta ADR acrescenta à 0087 é **alcance**: a 0087 argumentava a partir de webhooks, watchers e
bookmarks. Aqui aparece um custo que ela não nomeia — **permissions Spatie persistidas no banco** (as 6
linhas do seeder da ADR 0076 + as 4 da tela de Roles). Renomear prefixo de permission não quebra
webhook: **revoga acesso em silêncio**, sem erro e sem log. É um argumento novo a favor da mesma
conclusão, e por isso entra como errata, não como contradição.

### 0357 conferida — sem re-apontamento necessário

A [ADR 0357](0357-deprecar-srs-sucessor-kb-jana-governance.md) nomeia `Modules/Governance` +
`mcp_audit_log` como sucessor de *"Validação / drift / auditoria"* do SRS. **Conferido: nada a
re-apontar.** O Governance não morre nesta decisão — ele **recebe**. A incorporação da política reforça a
sua posição de sucessor em vez de enfraquecê-la. Se e quando a decisão em aberto (§O que esta ADR NÃO
decide) resolver o destino do próprio Governance, aí sim a 0357 precisa ser revisitada — está registrado
como `review_trigger`.

## Justificativa

**Por que incorporar em vez de apagar.** As duas operações resolvem problemas diferentes. Apagar
resolveria custo de manutenção; incorporar resolve **posse partida**, que é o defeito arquitetural. E o
contrafactual foi **rodado**, não estimado: pelos gates, deletar é mais barato que fundir — deadlink
**6** arquivos e `anchored_dead` **3** na deleção, contra **7** e **5** na fusão. Dois pioram, porque o
baseline do deadlink é chaveado por **caminho**: mover um arquivo zera a folga no destino. Ou seja, a
incorporação foi escolhida **apesar** de custar mais em CI, não porque parecia mais fácil.

**Por que o núcleo não vai pra lugar nenhum.** A tentação era dar o dual-brain ao KB (único consumidor
sobrevivente do `PolicyEngine` via `GraphController`). Isso seria criar dono por **acoplamento**, não por
domínio — KB é grafo de conhecimento, não motor de política. O plano de deprecação nomeou isso como
buraco aberto e recusou escolher no automático; a decisão de [W] foi fechar por domínio, e o núcleo não
tem domínio vivo que o receba.

**Por que congelar em vez de arrumar os nomes.** `/ads/admin/*` sob um módulo chamado Governance é feio.
Feiura não é bug; permission revogada em silêncio é. A 0087 já pesou esse trade-off e a medição das
permissions só reforça o lado dela.

**Quando reabrir.** Quando algum módulo vivo passar a decidir de fato — com outcome que fecha e
aprendizado que promove. Aí o núcleo dual-brain volta a ter receptor e esta ADR precisa de sucessora.
Enquanto o que existir for fila que ninguém consome, a resposta é esta.

## Consequências

**Positivas.** `mcp_governance_rules` passa a ter **um** dono, com migration, escrita, leitura e toggle
no mesmo módulo. Uma fila que crescia +204 linhas/dia sem produzir nada **parou** (5 crons + 1 daemon
desligados, smoke real em prod). Três serviços vão para donos com consumidor real (Jana, Forja,
Governance). E o maior parque de telas do conjunto de deprecação sai do mapa de manutenção.

**Negativas e trade-offs assumidos.** A capacidade de roteamento por risco/confiança **deixa de
existir** — é perda real, aceita por escrito, não realocação disfarçada. Os gates de CI **pioram** no
caminho escolhido (deadlink 6→7, `anchored_dead` 3→5). E o endereçamento fica semanticamente torto:
`/ads/admin/*` servido por Governance, pelo mesmo motivo que a 0087 já aceitou em 2026-05.

**Riscos que continuam de pé, e onde estão tratados.**

| Risco | Onde é tratado |
|---|---|
| `Decisoes.tsx:219` linka cada linha pra `DecisaoShow`; matar o detalhe sem patchar a lista = 404 em tudo | parte 6, gated [W] |
| `tests/Feature/Skills/SkillsControllerTest.php` bate em `/ads/admin/skills*` e vive **fora** de `Modules/` — varredura módulo-escopada não o enxerga | parte 6 |
| `loadMigrationsFrom` ausente: ligar no automático cria 4 tabelas que ninguém pediu | decisão [W] separada |
| Skills `.claude/skills/ads-route` e `ads-decision-flow` ficam órfãs (a segunda cita a 0145) | aposentar junto, parte 6 |
| `memory/requisitos/Jana/SPEC.md:1807` diz *"write-action segue sendo a ADR 0145"* — vira "morta-mas-canon" no [O] do `memory-health` assim que a 0145 é rebaixada | **não corrigido aqui de propósito**: é SPEC de outro módulo (1 PR = 1 intent) e tocá-lo acorda gates diff-aware sobre dívida pré-existente da Jana ([`proibicoes.md`](../proibicoes.md) §5 2026-07-12 + emenda 2026-07-27). Sentinela é 🟡 e tem precedente (a 0122 está na mesma lista desde a ADR 0360). Repontar junto do PR que a Jana já for tocar |

**Riscos mitigados.** Cross-tenant: **nenhum** — 100% do volume em `business_id=1`, e
`mcp_governance_rules` não tem coluna `business_id`. Cliente piloto (`biz=4`) **não é tocado** em
nenhuma superfície desta decisão. E `mcp_projects`/`mcp_project_parts` saíram do DROP antes desta ADR
(errata E3 do plano), porque a Forja escreve nelas.

## Referências

- [ADR 0145](0145-ia-administradora-pivot-ads-fsm-piloto-cobradora.md) — supersedida por esta
- [ADR 0086](0086-fase-5-mvp-governance-actiongate-warn.md) — emendada (fronteira *decision flow ≠ governance UI*)
- [ADR 0087](0087-drift-resolution-sem-mover-url.md) — errata/reafirmação (URLs congeladas)
- [ADR 0076](0076-skills-db-primary-git-destino-drift-alert.md) — Skills V2; o `AdsAdminSkillsPermissionsSeeder` que a cita é quem persiste as 6 permissions
- [ADR 0357](0357-deprecar-srs-sucessor-kb-jana-governance.md) — conferida, sem re-apontamento
- [ADR 0360](0360-deprecacao-admin-center-supersede-0122.md) — precedente de deprecação de módulo no mesmo ciclo
- [DEPRECATION-PLAN — Modules/ADS](../requisitos/ADS/DEPRECATION-PLAN.md) — inventário + errata desta sessão
- PRs: [#5127](https://github.com/wagnerra23/oimpresso.com/pull/5127) (crons+daemon off, merged) ·
  [#5128](https://github.com/wagnerra23/oimpresso.com/pull/5128) (política→Governance, merged) ·
  [#5129](https://github.com/wagnerra23/oimpresso.com/pull/5129) (skills→Jana, merged)
- [Handoff 2026-07-31 16:36](../handoffs/2026-07-31-1636-ads-incorporado-pelo-governance-3-de-7.md) —
  os recibos de prod e do CT 100, e o que o review adversarial derrubou ([#5130](https://github.com/wagnerra23/oimpresso.com/pull/5130), merged)
