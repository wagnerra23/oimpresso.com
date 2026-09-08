---
title: "Fronteira do Governance: plano de engenharia × plano da empresa (audiência, não flag)"
status: proposta
date: "2026-09-07"
owners: [W]
parent_module: Governance
related_adrs: [53, 86, 93, 94, 363, 366]
related_specs:
  - memory/requisitos/Governance/SPEC.md
related_charters:
  - resources/js/Pages/governance/Audit.charter.md
---

# Fronteira do Governance — engenharia × empresa

> **Pergunta de [W] (2026-09-07):** *"a governança será que deve ser por empresa? eu uso a
> governança para programar e os clientes usariam para a empresa deles, aqui tem um conflito
> de interesses. acredito que sirva para os dois."*
>
> **Resposta curta:** serve para os dois — e o conflito **não é hipotético**: as duas audiências
> já convivem dentro do mesmo módulo, sem nenhuma marcação que diga qual tela é de quem. Esta
> proposta não inventa fronteira nova: ela **executa** a que a
> [ADR 0366](../0366-fronteira-jana-forja-governance-kb.md) já declarou e que o código não implementa.

## 1. O que a 0366 já decidiu (e ninguém contestou)

A 0366 tabela a audiência de cada módulo. Literal, nas linhas que interessam:

| Módulo | Papel | Audiência | Pergunta que responde |
|---|---|---|---|
| **Jana** | IA conversacional do negócio — **produto vendável** | Larissa (cliente) | *"como está meu negócio e o que eu faço?"* |
| **Governance** | Conformidade e enforcement — policies, audit, drift, grades, gates | **[W]-auditor** | *"a regra está sendo cumprida?"* |

E fecha: *"a exceção cross-tenant do Governance (Constituição Art. 6+8) permanece"*. A exceção ao
Tier 0 é legítima **porque** a audiência é [W]-auditor e o dado não é de negócio. As duas metades
andam juntas: quem muda a audiência perde o direito à exceção.

## 2. Censo medido — o módulo já tem as duas audiências dentro dele

Medido em `origin/main` = `2e8da2b6f3` (2026-09-07), lendo cada controller e cada service.
Conferido que nada disso mudou até `86b3df0d59` (13 commits depois, nenhum tocando estes paths).

| tela | fonte de dado | escopo **medido** no código | audiência natural |
|---|---|---|---|
| `Dashboard` | `mcp_audit_log`, `mcp_governance_rules`, `mcp_memory_documents`, `jana_*`, `failed_jobs` | cross-tenant | engenharia |
| `Policies` | `mcp_governance_rules` | cross-tenant | engenharia |
| `DriftAlerts` | `mcp_alertas` | cross-tenant | engenharia |
| `ModuleGrades` | `mcp_module_grades_history` | cross-tenant | engenharia |
| `DsRollout` | artefatos de repo | cross-tenant | engenharia |
| **`Audit`** | `mcp_audit_log` — **a tabela TEM `business_id`** | **cross-tenant**, e o charter promete o contrário | **ambos** |
| **`Custos`** | `CustosService` (Jana) sobre `jana_mensagens` × `contacts` | **scoped pela SESSÃO** | **empresa** |
| **`QualidadeIa`** | `MemoriaMetrica` / `jana_memoria_gabarito` | **scoped pelo REQUEST** | **ambos** |

Dois recibos que sustentam a tabela:

- `CustosController` traz no docblock, literal: *"Scope: `business_id` da SESSÃO (ADR 0093 Tier 0)
  — nunca do request"*, e executa `session()->get('user.business_id')`. **Essa tela já é de cliente.**
- Dos **9 services** do módulo que leem tabela, **0 filtram por `business_id`**
  (`AuditDrillDownService`, `DriftAlertService`, `GovernanceRulesService`, `InitiativeService`,
  `ModuleGradeService`, `ObservabilitySnapshotService`, `PolicyToggleService`,
  `ScopedScorecardEvaluator`, `SddBriefLineService`).

E o módulo **é vendável por business**: o `DataController` gate a visibilidade por
`governance_module` no pacote de subscription (Camada 1). Ou seja, hoje um cliente com o módulo
habilitado alcança as telas cross-tenant.

## 3. O que a medição levantou e pede decisão [W] (Tier 0)

⚠️ **Hipótese forte, não achado fechado.** O §5 de 2026-07-15 exige teste vermelho antes de
chamar de achado; registro como suspeita medida, não como fato provado.

`QualidadeIaController` monta o escopo assim:

```php
$businessId = $request->get('business_id') !== null ? (int) $request->get('business_id') : null;
// … depois: $query->where('business_id', $businessId);
```

Lê o tenant **do request** — exatamente o que o docblock da tela vizinha (`Custos`) declara
proibido e o que a [ADR 0093](../0093-multi-tenant-isolation-tier-0.md) trata como Tier 0. Sem o
parâmetro, a tela agrega tudo e rotula `"Plataforma"`, o que é coerente com a audiência
[W]-auditor; **com** o parâmetro, ela serve a série daquele tenant a quem alcançar a rota.

Mitigações que existem hoje, e por isso é suspeita e não incêndio: a rota exige `auth`, e a
Camada 1 só mostra o item a quem tem `governance_module`. O que **não** existe: nenhum `can:` nas
rotas do módulo, e o `ActionGate` — o middleware de policy do próprio módulo — **não é aplicado
por rota nenhuma**. `git grep -iln actiongate origin/main -- '*routes*.php'` devolve 0 arquivos,
com controle positivo (`Route::` no mesmo pathspec devolve 10).

Consertar isso é ato Tier 0 e decisão de [W] — não entra por PR de refactor silencioso.

## 4. Prior art (a técnica tem nome)

Não é invenção nossa e não precisa de vocabulário próprio: é a separação **control plane × data
plane**, que em SaaS aparece como **internal admin × tenant-facing admin**. O padrão diz o mesmo
que a 0366 — o plano de controle é da operação do produto e enxerga tudo; o plano de dados é do
cliente e enxerga só o dele. Adotar o vocabulário ajuda quem chega depois.

## 5. Opções

### A) Uma tela só, alternando por perfil (flag ou `if superadmin`)

**Rejeitada.** Mantém dado cross-tenant e dado de cliente no mesmo controller, com a fronteira
dependendo de um `if` — a forma que a [ADR 0093](../0093-multi-tenant-isolation-tier-0.md) e o
`NoHardcodeBusinessIdInModulesTest` já barram, e que transforma cada tela nova numa chance de
vazamento. Também colide com a exceção da Constituição: um módulo declarado cross-tenant que
serve cliente perde a justificativa do Art. 6+8.

### B) Separar por AUDIÊNCIA (recomendada)

Duas superfícies com donos distintos, cada uma com a regra que lhe cabe:

1. **`Modules/Governance` = plano de engenharia.** Cross-tenant por decisão (Art. 6+8, 0366),
   audiência [W]-auditor. Consequência que hoje falta: **fechar de verdade** — gate explícito nas
   rotas, e reavaliar se `governance_module` deve seguir comprável por business.
2. **Governança da empresa = plano de dados**, sempre `business_id` da sessão, **embutida no
   fluxo de trabalho** (§6.1), não em tela própria: `Modules/Auditoria` (trilha por registro — a
   [lápide de 2026-07-30](../../licoes-rejeitadas.md) já a classificou como capacidade de negócio,
   e o controller já escopa por sessão), alçadas do Financeiro (`aprovacao_status`), RBAC do FSM
   por business, permissões Spatie da Camada 3.
3. **As três telas ambíguas viram decisão explícita, uma a uma:**
   - `Custos` — já é de cliente e já escopa. Formalizar: sai do plano de engenharia, ou ganha um
     par (custo da plataforma × custo do tenant).
   - `Audit` — o dado tem `business_id`. Ou vira duas leituras (auditoria da plataforma × trilha
     do tenant), ou o charter é corrigido para parar de prometer um escopo que o código não faz.
   - `QualidadeIa` — trocar `request` por sessão, **ou** declarar a tela como plataforma-only e
     remover o parâmetro.

### C) Deprecar ou absorver o módulo

**Proibido re-propor.** A lápide §5 de 2026-07-31 registra que o inventário foi feito duas vezes
para viabilizar a deleção e concluiu o contrário. Esta proposta **não** a reabre: o módulo fica;
o que muda é a fronteira de audiência.

## 6. Decisão proposta (B) — regra curta o bastante para ser lembrada

> **Tela do Governance nunca lê `business_id` do request.**
> Ou o escopo vem da sessão — e aí é governança da empresa, e ela aparece **no fluxo** —
> ou não há escopo nenhum, e a tela é da plataforma, fechada para [W].
> Não existe terceira forma.

Corolário para quem for construir: se a tela responde *"a regra está sendo cumprida no meu
sistema?"*, é engenharia. Se responde *"a regra está sendo cumprida na minha empresa?"*, é
produto — e produto tem `business_id`, gate de permissão e teste cross-tenant.

## 6.1 Decisão [W] de 2026-09-08 — o formato do lado da empresa

Perguntado se a governança do cliente deveria ser uma **tela** ou aparecer **onde o trabalho
acontece**, [W] respondeu, textual: *"que apareça onde o trabalho acontece"*.

Isso fecha o formato do plano de dados e não é virada de rumo: **é o padrão que o sistema já
pratica**. Medido em `origin/main`, a governança da empresa já vive embutida em quatro lugares,
cada um dentro da tela onde a decisão é tomada:

| onde | o que mostra | âncora |
|---|---|---|
| `Cliente/_drawer/AuditoriaTab.tsx` | timeline de alterações do cadastro, paginada, com export | `ClienteAuditoriaController` · [ADR 0127](../0127-modules-auditoria-undo-activity-log.md) |
| `Sells/_components/SaleAuditTrail.tsx` | histórico de edições, emissões fiscais e transições de estágio, dentro do drawer da venda | `sale_stage_history` · [ADR 0143](../0143-fsm-pipeline-live-prod-marco-2026-05-12.md) |
| `Financeiro/Unificado/_components/FinAuditTrail.tsx` | trilha do título, dentro do próprio título | idem |
| `Sells/_components/FsmActionPanel.tsx` | as ações que **este** usuário pode executar neste estágio | RBAC do FSM |

E o mesmo vale para alçada: `Financeiro/Unificado` filtra por `aprovacao_status`
(`pendente` / `aprovado` / `rejeitado` / sem workflow) na própria lista de títulos — a pergunta
*"o que espera minha aprovação?"* já se responde onde o trabalho está, sem tela de governança.

**Consequência prática:** o lado da empresa **não vira módulo nem tela**. Ele cresce por
presença — mais trilha embutida onde falta, selo de quem aprovou, aviso de alçada no ponto da
ação — e, se um dia precisar de agregação, ela nasce como resumo de pendências dentro do fluxo
que já existe, nunca como um segundo cockpit.

**Corolário que fecha a fronteira:** com o lado da empresa embutido, `Modules/Governance` deixa
de ter ambiguidade — ele é integralmente plano de engenharia, e as três telas do §2 marcadas como
"empresa" ou "ambos" têm destino determinado, não em aberto:

| tela | destino que a decisão determina |
|---|---|
| `Custos` | é do cliente e já escopa: o custo de IA da empresa passa a aparecer onde ela já olha IA; a versão da plataforma, se necessária, fica sem escopo e fechada |
| `Audit` | a trilha da empresa **já** é embutida (as quatro linhas acima); a tela do módulo vira plataforma-only e o `Audit.charter.md` é corrigido para parar de prometer escopo por tenant |
| `QualidadeIa` | plataforma-only: o parâmetro `business_id` do request sai |

## 7. O que NÃO muda — e o que fica proibido

- A exceção cross-tenant do Governance (Art. 6+8) **permanece** para o plano de engenharia.
- `Modules/Governance` **não** é deprecado, absorvido nem consolidado (lápide 2026-07-31).
- ⛔ **Não criar módulo, tela ou cockpit de "governança do cliente"** — nem como
  `Modules/GovernancaEmpresa`, nem como aba nova no Governance, nem como dashboard de conformidade
  por tenant. É a proposta que nasce sozinha toda vez que alguém lê "governança serve pros dois",
  e foi **decidida contra** por [W] em 2026-09-08 (§6.1): o lado da empresa aparece **no fluxo**.
  Candidata a lápide no §5 quando a ADR canônica for aceita.
- Nada aqui autoriza mexer no `AuditDrillDownService` ou no `QualidadeIaController` sem decisão
  [W]: são Tier 0, e a 0093 pede aprovação explícita.
- A governança **executável** (gates de CI em `scripts/governance/`) não é tocada — ela não tem
  audiência de cliente por construção.

## 8. Se [W] aprovar, o caminho é este (nesta ordem)

1. ADR canônica registrando a fronteira por audiência **e o formato embutido decidido em §6.1**,
   emendando a 0366 — que declarou a audiência sem declarar a consequência de escopo.
2. Fechar o plano de engenharia: `can:` nas rotas do módulo, e decidir o destino do `ActionGate`
   (implementar o gate que ele promete, ou remover a promessa — o §5 de 2026-07-30 proíbe
   mecanismo que anuncia saída que não honra).
3. Só então, e uma a uma, as três telas do §6.1 — cada uma com teste cross-tenant **antes** do
   fix, porque a 0093 exige provar isolamento, não afirmar.
4. Do lado da empresa, nada de novo nasce até haver **sinal de cliente** (ADR 0105): o padrão já
   está no ar em quatro telas, e o trabalho futuro é estendê-lo onde faltar — não inaugurar
   superfície.

### Chip menor, achado na medição (não é objeto desta proposta)

`ClienteAuditoriaController` cita no docblock `memory/decisions/0127-modules-auditoria-ui-undo.md`;
o arquivo real é `0127-modules-auditoria-undo-activity-log.md`. Ponteiro podre num comentário —
conserto de uma linha, PR próprio.

## 9. Recibos

Todas as medições em `origin/main` = `2e8da2b6f3`, repo não-raso.

| afirmação | como reproduzir |
|---|---|
| escopo por tela | `git show origin/main:Modules/Governance/Http/Controllers/<X>Controller.php`, grep de `session('user.business_id')` e `where('business_id'` |
| 9 services, 0 filtram | grep de `DB::table(` × `where('business_id'` em `Modules/Governance/Services/` |
| `mcp_audit_log` tem tenant | migration `create_mcp_audit_log_table` — 3 ocorrências de `business_id` |
| `ActionGate` sem rota | `git grep -iln actiongate origin/main -- '*routes*.php'` = 0 arquivos (controle positivo: `Route::` = 10) |
| nenhum `can:` no módulo | `git show origin/main:Modules/Governance/Http/routes.php` — só `throttle:` e o grupo `web/auth` |
| `Custos`/`Auditoria` escopam por sessão | `session()->get('user.business_id')` nos dois controllers |
| audiência declarada | [ADR 0366](../0366-fronteira-jana-forja-governance-kb.md), tabela de papéis por módulo |
