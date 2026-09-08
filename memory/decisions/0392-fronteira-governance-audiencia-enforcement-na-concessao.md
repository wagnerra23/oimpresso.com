---
slug: 0392-fronteira-governance-audiencia-enforcement-na-concessao
number: 392
title: "Fronteira do Governance por audiência — o enforcement vive na CONCESSÃO da permissão, não na leitura do request"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-07"
module: governance
tags: [fronteira, audiencia, multi-tenant, permissao, control-plane, governance, jana, forja]
supersedes: []
superseded_by: []
supersedes_partially:
  - 0366-fronteira-jana-forja-governance-kb
related:
  - 0366-fronteira-jana-forja-governance-kb
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0053-mcp-server-governanca-como-produto
  - 0363-governance-incorpora-ads-nucleo-sem-receptor
pii: false
---

# ADR 0392 — Fronteira do Governance por audiência: o gate é a permissão, não o request

> **Nasce `proposto`.** Qual fronteira adotar é decisão de [W] — esta ADR registra o problema
> medido, as opções e o trade-off, não decide sozinha. **O merge deste PR é o ato de
> ratificação (R10).** Número alocado por `next-id.mjs` ([ADR 0304](0304-alocacao-numero-ciente-trabalho-em-voo.md)).
>
> **Pergunta de [W] (2026-09-07):** *"a governança será que deve ser por empresa? eu uso a
> governança para programar e os clientes usariam para a empresa deles, aqui tem um conflito
> de interesses. acredito que sirva para os dois."*
>
> **Resposta curta:** serve para os dois, e o conflito é real — mas ele **não está onde a
> primeira análise apontou**. Não é a tela que lê `business_id`; é **quem pode conceder a
> permissão que abre a porta**.

## Contexto

### C-1 · O que a 0366 já decidiu, e o que ela deixou em aberto

A [ADR 0366](0366-fronteira-jana-forja-governance-kb.md) §D-A tabela a audiência de cada módulo
pela pergunta que ele responde:

| Módulo | Audiência | Pergunta que responde |
|---|---|---|
| **Jana** | Larissa (cliente) | *"como está meu negócio e o que eu faço?"* |
| **Governance** | **[W]-auditor** | *"a regra está sendo cumprida?"* |

A exceção cross-tenant do Governance (Constituição Art. 6+8) é legítima **porque** a audiência é
[W]-auditor e o dado não é de negócio. As duas metades andam juntas: quem muda a audiência perde
o direito à exceção.

O que a 0366 **declarou** foi a audiência. O que ela **não** declarou foi a *consequência de
enforcement* — qual mecanismo garante que só a audiência declarada alcança a tela. Esta ADR
emenda esse ponto (`supersedes_partially`), sem tocar no resto da 0366.

### C-2 · Censo por tela — medido, com a coluna que faltava

Medido em `origin/main` = `493f8eb535` (2026-09-07), lendo cada controller. A coluna
**gate de permissão** é a que a análise anterior não tinha, e é ela que muda o diagnóstico.

| tela | fonte de dado | escopo no código | **gate de permissão medido** | audiência |
|---|---|---|---|---|
| `Dashboard` | `mcp_audit_log`, `mcp_governance_rules`, `jana_*`, `failed_jobs` | cross-tenant | `auth` — seção MCP por `Gate::allows('jana.mcp.usage.all')` **in-code**, não middleware | engenharia |
| `Policies` | `mcp_governance_rules` | cross-tenant | **`auth` apenas** | engenharia |
| `DriftAlerts` | `mcp_alertas` | cross-tenant | **`auth` apenas** | engenharia |
| `ModuleGrades` | `mcp_module_grades_history` | cross-tenant | **`auth` apenas** | engenharia |
| `DsRollout` | artefatos de repo | cross-tenant | **`auth` apenas** | engenharia |
| `Audit` | `mcp_audit_log` — a tabela **tem** `business_id` | cross-tenant | **`auth` apenas** | ambos |
| `Custos` | `CustosService` sobre `jana_mensagens` × `contacts` | **sessão** | `can:jana.admin.custos.view` | empresa |
| `QualidadeIa` | `MemoriaMetrica` / `jana_memoria_gabarito` | **request** (declarado intencional) | `can:jana.mcp.usage.all` | plataforma |

São 8 telas (9 arquivos `.tsx`, contando `ModuleGrades/Index` e `/Show`).

Dois recibos que sustentam a tabela:

- `CustosController` traz no docblock, literal: *"Scope: `business_id` da SESSÃO (ADR 0093 Tier 0)
  — nunca do request"*, e executa `session()->get('user.business_id')`. **Essa tela já é de cliente.**
- Dos **9 services** do módulo que leem tabela — `AuditDrillDownService`, `DriftAlertService`,
  `GovernanceRulesService`, `InitiativeService`, `ModuleGradeService`,
  `ObservabilitySnapshotService`, `PolicyToggleService`, `ScopedScorecardEvaluator`,
  `SddBriefLineService` — **nenhum** tem `where('business_id'`. O `business_id => 1` do
  `InitiativeService` é *escrita* em `mcp_alertas` (convenção `mcp_*` meta), não filtro de leitura.

E o módulo **é vendável por business**: o `DataController` gate a visibilidade por
`governance_module` no pacote de subscription. Esse gate governa o **item de sidebar**, não a URL
— o próprio comentário do arquivo registra que *"o módulo seguia acessível por URL direta"*.

### C-3 · Errata da análise anterior (registrada, não apagada)

A proposta de 2026-09-07 ([PR #6950](https://github.com/wagnerra23/oimpresso.com/pull/6950))
afirmou três coisas que a medição desmentiu. Ficam registradas porque o erro de método é
reutilizável:

1. **"Nenhuma rota tem `can:`"** — verdadeiro sobre `Modules/Governance/Http/routes.php`,
   **falso sobre o sistema**. O gate mora no **construtor do controller**: `CustosController`,
   `QualidadeIaController` e outros 30 controllers do repo usam `$this->middleware('can:…')`.
   Medir `routes.php` e concluir sobre o módulo é a classe **LC-08** — afirmar ausência medindo a
   fonte errada ([§5 2026-07-28](../proibicoes.md): claim de ausência exige varredura no repo
   inteiro **e** o dono do inventário).
2. **`QualidadeIaController` ler `business_id` do request seria o risco central** — não é. O
   controller é fechado por `can:jana.mcp.usage.all`, e o docblock declara o comportamento como
   deliberado: *"Cross-business é INTENCIONAL aqui: a tela é de PLATAFORMA (superadmin) […]
   exceção da Constituição Art. 6+8 preservada pela ADR 0366. A própria métrica
   `cross_tenant_violations == 0` é o vigia do isolamento."* **Ler o request numa tela de
   plataforma fechada por permissão é filtro de UI, não controle de acesso.**
3. **A regra proposta — *"tela do Governance nunca lê `business_id` do request"*** — proibia o
   legítimo (filtro de UI de plataforma) e não alcançava o perigoso (a concessão). Substituída
   pela D-B abaixo.

### C-4 · O buraco real: a CONCESSÃO da permissão

Um nível antes da leitura. Medido, e com teste vermelho de recibo:

| passo | medido em `origin/main` |
|---|---|
| 1 | `McpScopesSeeder` marca **5 de 22** scopes como `admin_only => true`: `jana.mcp.projects.manage`, `jana.mcp.usage.all`, `jana.mcp.memory.manage`, `jana.cc.read.all`, `jana.cc.curate` |
| 2 | `DataController@user_permissions` faz `...$this->mcpScopePermissions()`, cujo `array_map` percorre o catálogo **inteiro sem filtrar `admin_only`** — os 5 viram checkbox em `/roles/{id}/edit` |
| 3 | Essa tela exige `roles.update` — permission de **admin de business** (Camada 3), não de superadmin. O próprio docblock do método diz: *"Quem concede é o admin do business"* |
| 4 | `RoleController@__somenteDoCatalogo` **não barra**: `PermissionCatalog::intrusas` descarta só o que está **fora** do catálogo, e a permission está **dentro** |
| 5 | `syncPermissions` concede |

**O raio disso não é uma tela.** `jana.mcp.usage.all` é o único gate de `/governance/qualidade-ia`,
da seção MCP do `/governance/dashboard`, e das **8 telas do hub de engenharia da Forja**
(`Forja`, `Scorecard`, `Team`, `Roadmap`, `TasksAdmin`, `Search`, `Trabalho`, `Aprovacoes`) —
contados um a um nos construtores.

**Recibo do vermelho:** `McpScopeAdminOnlyNaoAutoConcedivelTest` já rodou vermelho com a asserção
correta (`toBe([])`) — run `34169613882`, lane `PHP / Pest (Unit)`: `1 failed, 79 skipped,
1206 passed (4561 assertions)`. Mergeado como catraca da lista medida no
[PR #6952](https://github.com/wagnerra23/oimpresso.com/pull/6952), porque um vermelho permanente
num context required trancaria o merge do repo inteiro até a decisão sair.

> **Não medido nesta ADR, declarado.** [W] relata (2026-09-07) que **biz=164 (Martinho, cliente
> piloto LIVE) tem os 5 scopes concedidos numa role com 5 users**. Esta ADR **não reproduziu** essa
> consulta — ela exige acesso ao banco de produção, que esta sessão não teve. Reprodução:
> `SELECT r.business_id, r.name, COUNT(*) FROM roles r JOIN role_has_permissions rhp ON rhp.role_id = r.id
> JOIN permissions p ON p.id = rhp.permission_id WHERE p.name IN (os 5 slugs) GROUP BY r.id`.
> O que está **provado por teste** é que a concessão *é possível*; que ela *ocorreu* é relato de
> [W] — e a diferença importa para dimensionar urgência, não para decidir a fronteira.

### C-5 · Prior art — a técnica tem nome

Não é invenção nossa: é a separação **control plane × data plane**, que em SaaS aparece como
**internal admin × tenant-facing admin**. O padrão diz o mesmo que a 0366 — o plano de controle é
da operação do produto e enxerga tudo; o plano de dados é do cliente e enxerga só o dele. E diz
também o que a 0366 não disse: **o plano de controle se protege por identidade e concessão, não
por filtro de consulta**. Adotar o vocabulário ajuda quem chega depois.

## Decisão

### D-A · A audiência de uma tela é definida pela PERMISSÃO que abre a porta

Não pelo módulo onde o arquivo mora, não pela tabela que ela lê, não pelo `if` dentro do
controller. Consequência direta: **toda tela do plano de engenharia precisa de gate explícito** —
hoje 5 das 8 telas do Governance (`Policies`, `DriftAlerts`, `ModuleGrades`, `DsRollout`, `Audit`)
têm apenas `auth`, servindo dado cross-tenant a qualquer usuário autenticado que alcance a URL.

### D-B · A regra curta, o bastante para ser lembrada

> **Permissão que abre o plano de engenharia não pode ser concedível por admin de business.**
>
> Ler `business_id` do request numa tela de plataforma **fechada por permissão** é filtro de UI.
> Ler `business_id` do request numa tela **sem gate de permissão** é vazamento.
> A diferença não está no `request` — está no gate.

Corolário para quem for construir: se a tela responde *"a regra está sendo cumprida no meu
sistema?"*, é engenharia, e o gate é uma permission que só o superadmin concede. Se responde
*"a regra está sendo cumprida na minha empresa?"*, é produto — e produto tem `business_id` da
sessão, gate de permissão do business e teste cross-tenant.

### D-C · As três telas ambíguas viram decisão explícita, uma a uma

- **`Custos`** — já é de cliente e já escopa por sessão. Formalizar: sai do plano de engenharia,
  ou ganha um par (custo da plataforma × custo do tenant).
- **`Audit`** — o dado tem `business_id`. Ou vira duas leituras (auditoria da plataforma × trilha
  do tenant), ou o charter é corrigido para parar de prometer um escopo que o código não faz.
- **`QualidadeIa`** — **fica como está**. É plataforma, é declarada, é gateada. O que muda é o
  degrau anterior: o gate dela precisa deixar de ser auto-concedível.

### D-D · Esta ADR NÃO move um arquivo e NÃO conserta nada

Ela declara a fronteira e nomeia o buraco. **Todo conserto vem em PR separado, um por vez**, com
teste cross-tenant **antes** do fix — a [0093](0093-multi-tenant-isolation-tier-0.md) exige provar
isolamento, não afirmar. Ordem proposta, cada etapa decisão [W] independente:

| # | Movimento | Ordem |
|---|---|---|
| 1 | Resolver o conflito A×B da concessão (ver Consequências) | é o Tier 0 — primeiro |
| 2 | Gate explícito nas 5 telas do Governance que só têm `auth` | depois de 1 |
| 3 | Decidir se `governance_module` deve seguir comprável por business | depende de 1 e 2 |
| 4 | As três telas ambíguas de D-C, uma a uma | independente |

## Opções consideradas

### A) Uma tela só, alternando por perfil (flag ou `if superadmin`)

**Rejeitada.** Mantém dado cross-tenant e dado de cliente no mesmo controller, com a fronteira
dependendo de um `if` — a forma que a [0093](0093-multi-tenant-isolation-tier-0.md) e o
`NoHardcodeBusinessIdInModulesTest` já barram. Também colide com a exceção da Constituição: um
módulo declarado cross-tenant que serve cliente perde a justificativa do Art. 6+8.

### B) Separar por audiência, com o enforcement na concessão (**recomendada**)

O que a D-A/D-B descrevem. `Modules/Governance` segue sendo o plano de engenharia, cross-tenant
por decisão, com gate explícito por permissão não-auto-concedível. A governança **da empresa** é
plano de dados e vive nos donos de domínio que **já existem**: `Modules/Auditoria` (trilha por
registro, já escopa por sessão), alçadas do Financeiro (`aprovacao_status`), RBAC do FSM por
business, permissões Spatie da Camada 3.

### C) Deprecar ou absorver o módulo

**Proibido re-propor.** A [lápide §5 de 2026-07-31](../proibicoes.md) registra que o inventário foi
feito **duas vezes** para viabilizar a deleção e concluiu o contrário, e que reabrir exige decisão
[W] nova e explícita. Esta ADR **não** a reabre: o módulo fica; o que muda é o enforcement da
audiência.

## Consequências

**O conflito que a decisão [W] precisa resolver** — as duas defesas não são satisfazíveis juntas
no desenho atual:

- **A** — `McpScopesVisiveisNoRoleEditTest` exige que **todo** scope apareça no form, porque
  `syncPermissions` é destrutivo: scope ausente é apagado a cada save de qualquer role. Em
  2026-07-29 um save na role `Operacional#1` zerou os 17 scopes e derrubou o MCP dos 4 users do
  time.
- **B** — Tier 0: scope `admin_only` não pode ser auto-concedível por admin de business.

Sumir com os 5 checkboxes satisfaz B e reintroduz o incidente de A. Caminhos possíveis, **não
decididos aqui**: renderizar os `admin_only` como *disabled + hidden input* (aparecem no POST, não
são editáveis); filtrar no `RoleController` em vez do form; ou separar a família `admin_only` numa
tela de superadmin. Escolher é ato [W].

**O que melhora se B for adotada:** a exceção cross-tenant do Art. 6+8 volta a ter fundamento
verificável — hoje ela é justificada por uma audiência que nenhum mecanismo garante.

**O que piora:** gate explícito nas 5 telas hoje só-`auth` é estritamente mais restritivo que o
estado atual e pode esconder tela de quem a enxergava ontem. O `DashboardController` já registra
essa exata preocupação em comentário. Cada etapa precisa de smoke real (R1), não só CI verde.

## O que NÃO muda

- A exceção cross-tenant do Governance (Art. 6+8) **permanece** para o plano de engenharia.
- `Modules/Governance` **não** é deprecado, absorvido nem consolidado (lápide 2026-07-31).
- O `QualidadeIaController` **não** é "consertado": o cross-business dele é intencional e declarado.
- A governança **executável** (gates de CI em `scripts/governance/`) não é tocada — ela não tem
  audiência de cliente por construção.
- Nada aqui autoriza mexer em `AuditDrillDownService`, `DataController` ou `RoleController` sem
  decisão [W]: são Tier 0.

## Recibos

Medições em `origin/main` = `493f8eb535` (2026-09-07), repo não-raso.

| afirmação | como reproduzir |
|---|---|
| gate por controller | `git show origin/main:Modules/Governance/Http/Controllers/<X>Controller.php` + grep de `middleware('can:` |
| 33 controllers com `can:` no repo | `git grep -l "middleware('can:" origin/main -- 'Modules/**/Http/Controllers/*.php'` contado |
| 8 telas da Forja sob `jana.mcp.usage.all` | mesmo grep em `Modules/Forja/Http/Controllers/` |
| 5 de 22 scopes `admin_only` | `grep -cE` com regex **tolerante a espaços** no `McpScopesSeeder` — o padrão de 1 espaço devolve **0**, falso negativo (§5 2026-08-01: controle positivo) |
| 9 services, 0 filtram | `grep -cE "where\(\s*['\"]business_id"` em cada um dos 9 |
| `Custos` escopa por sessão | `session()->get('user.business_id')` no `CustosController` |
| cross-business intencional | docblock do `QualidadeIaController` |
| gate de sidebar não é gate de URL | comentário do `Modules/Governance/Http/Controllers/DataController.php` |
| teste vermelho | run `34169613882`, PR #6952 |
| audiência declarada | [ADR 0366](0366-fronteira-jana-forja-governance-kb.md) §D-A |
