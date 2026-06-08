---
slug: 2026-06-07-understand-governanca-modulo-tela
title: "wagner-understand — governança 5 dimensões por módulo e por tela (metas/deve-pode-não-pode/permissão/autoridade-dev/autoridade-override)"
type: understand-decode
date: "2026-06-07"
topic: "Decodificação wagner-understand — governança em 5 dimensões por módulo e por tela (metas, deve/pode/não-pode, permissão, autoridade-dev, autoridade-override)"
session: governanca-modulo-tela
spawned_by: claude-pai
status: ready-for-execution
---

# Decodificação

## TL;DR

Wagner quer um trilho único, por módulo E por tela, declarando 5 dimensões — meta, escopo (deve/pode/não-pode), permissão (RBAC), autoridade de dev (quem coda ali) e autoridade de override (quem afrouxa regra Tier 0) — pro time MCP entrante. Inventário mostra: dimensões 1-2-3 já existem espalhadas (SCOPE.md + charter); **4 e 5 são o gap real** (sem artefato canônico legível). Provável saída: 1 ADR + extensão de schema de frontmatter, não código de runtime.

## Pedido cru de Wagner (texto exato)
> "quero ver e definir as metas claras modo modulo e telas o que deve o que pode e não pode e permissão, quem pode e não pode programar na tela quem tem autoridade para remover regras perigosas"

Contexto: pivotou de testar o "jira" (ProjectMgmt, achou aquém) pra GOVERNANÇA. Driver real inferido: time MCP (Felipe/Maiara/Eliana/Luiz) entrando — quer trilhos ANTES de mais mãos tocarem módulos/telas.

## Decodificação refinada

- **Objetivo principal:** ter um artefato canônico único que, por módulo E por tela, declare explicitamente as 5 dimensões — meta, escopo (deve/pode/não-pode), quem acessa (RBAC), quem pode codar, quem pode afrouxar regra perigosa — para servir de trilho ao time MCP que está entrando.
- **Sub-objetivos:**
  1. Inventariar o que das 5 dimensões já existe e onde (evitar 3º silo).
  2. Tornar EXPLÍCITA e legível a autoridade de dev (#4) e de override Tier 0 (#5), que hoje existem só dispersas/constitucionais.
  3. Mapear o eixo módulo (SCOPE.md + SPEC/BRIEFING) ao eixo tela (charter.md) sem duplicar.
  4. Onboarding-ready: um dev novo abre 1 doc e sabe se pode tocar aquela tela.
- **Critério de pronto:** Wagner consegue abrir um módulo (ex: Financeiro) ou uma tela (ex: Sells/Edit) e ver as 5 respostas sem perguntar; e um dev novo (Luiz/Felipe) sabe na hora se está autorizado a codar ali e quem ele aciona pra afrouxar uma regra.
- **Persona alvo:** Wagner (define) + time MCP entrando (consome) — NÃO cliente final. É governança interna de engenharia.
- **Implícitos detectados:**
  - Wagner NÃO quer um terceiro documento novo — ele já tem charter (tela) + SPEC/BRIEFING/SCOPE (módulo). Quer consolidação/extensão.
  - "regra perigosa" = Tier 0 (multi-tenant, append-only ADR, FSM guard, imutabilidade legal). A resposta constitucional já existe (só Wagner/L0 via ADR), o gap é torná-la visível/aplicável ao time novo.
  - Isto NÃO é codar feature — é definir/formalizar política. Provavelmente vira 1 ADR + extensão de schema de frontmatter, não código de runtime.
- **Ambiguidades a confirmar:** ver §"Perguntas pro Wagner" no fim.

## Regras protocolo aplicáveis (R1-R10)

| Regra | Aplica? | O que exige aqui |
|---|---|---|
| **R3** Workflow 3 fases (pré-flight) | SIM | Este doc É o pré-flight. Qualquer formalização que toque `Modules/<X>/SCOPE.md` ou charters exige ler SPEC+SCOPE antes. |
| **R5** PT-BR + economia (confirmar escopo) | SIM | Escopo é estrutural e amplo (34 módulos × N telas). NÃO sair formalizando os 34 — confirmar formato com Wagner ANTES (perguntas no fim). |
| **R9** Zero auto-mem | SIM | Tudo vai pra git canon (`memory/decisions/` ADR + `Modules/*/SCOPE.md`), nunca auto-mem. |
| **R10** Aprovação humana | SIM | Definir autoridade É decisão constitucional (toca Trust Tiers / Constituição). Só Wagner aprova via ADR. |
| **R13** Recomendar, não menu | SIM | Devolvo recomendação cravada de estrutura, não 3 opções pra Wagner escolher arquitetura. |
| R1/R2/R4/R6/R7/R8 | NÃO (agora) | Não há smoke, design aprovado, Eloquent, Pest, Edit de Page nem worktree-path nesta fase de decodificação. Entram SE virar implementação. |

---

# Inventário no projeto — 5 dimensões × (existe? onde? gap?)

| # | Dimensão | Existe? | Onde vive hoje (eixo módulo) | Onde vive hoje (eixo tela) | Gap |
|---|---|---|---|---|---|
| **1** | **META** (pra que serve) | ✅ Parcial-bom | `Modules/<X>/SCOPE.md` frontmatter `purpose:` + §Missão; `memory/requisitos/<X>/SPEC.md` + `BRIEFING.md` (37 BRIEFINGs, 58 SPECs) | `<Tela>.charter.md` §Mission (≈100 charters em `resources/js/Pages/**`) | Cobertura desigual de charter por tela (rubrica D3.c exige só ≥30%). Não é gap de formato, é de cobertura. |
| **2** | **DEVE / PODE / NÃO PODE** | ✅ Bom | SCOPE.md `contains` (deve) + `not_contains` (não-pode, cross-cutting) + `drift_alerts`; proibições Tier 0 globais em `memory/proibicoes.md` | charter §Goals (deve) + §Non-Goals (não-faz) + §UX Anti-patterns | Forte no eixo módulo+tela. "PODE" (permitido mas opcional) não tem campo dedicado — hoje é inferido. |
| **3** | **PERMISSÃO** (quem ACESSA — RBAC) | ✅ Bom (runtime) | SCOPE.md `permission_prefix: X.*`; Spatie 260+ permissions Camada 3 (`/roles/{id}/edit`); 3 camadas (módulo nWidart / core UltimatePOS / Spatie por user) catalogadas em `feedback-habilitar-modulo-por-business.md` | charter §Endpoints lista rota+permission entre parênteses (ex `governance.policies.toggle`) | Declarado em código/runtime. NÃO há visão consolidada legível "esta tela exige permissão X" fora do SCOPE/charter individual. |
| **4** | **AUTORIDADE DE DEV** (quem pode/não-pode PROGRAMAR) | ⚠️ Parcial + AMBÍGUO | `Modules/<X>/SCOPE.md` `trust_required: L0-L4` (36/36 módulos têm!) + `TRUST-TIERS.md` define capabilities por tier + `TEAM.md §3` matriz quem-pega-qual-task | ❌ NÃO existe no charter | **GAP REAL.** `trust_required` é tier de ACTOR/AGENTE em runtime (ADR 0080), NÃO "qual humano do time pode editar este código". TEAM.md mapeia por TIPO de task (frontend/financeiro/LGPD), não por MÓDULO/TELA. Ninguém cruza os dois. Dev novo não sabe "posso codar em Modules/Fiscal?". |
| **5** | **AUTORIDADE DE OVERRIDE** (quem REMOVE regra perigosa) | ⚠️ Existe constitucional, NÃO explícito-por-artefato | Constituição: Tier 0 IRREVOGÁVEL só muda via ADR mãe nova `supersedes:` + aprovação Wagner; `TRUST-TIERS.md` L0=Wagner sovereign; `publication-policy` skill matriz Tier0/1/2; hooks bloqueadores (block-automem, mwart-gate, governance-gate, infra-contract) | ❌ NÃO existe no charter nem por-regra | **GAP REAL.** A resposta É constitucional ("só Wagner/L0 via ADR"), mas está dispersa em 4 lugares e NÃO listada como "estas são as regras perigosas deste módulo + quem pode afrouxar cada uma". Dev novo não sabe que mexer no `business_id` scope ou no FSM guard exige Wagner. |

## Síntese do gap (confirma hipótese do pedido)

- **Dimensões 1, 2, 3 existem e estão razoavelmente bem** — espalhadas mas presentes em SCOPE.md (módulo) + charter (tela). Gap é cobertura/consolidação, não ausência.
- **Dimensão 4 (quem-pode-codar-aqui): existe um proxy (`trust_required`) mas semanticamente errado pro pedido** — ele mira o actor-runtime, não o humano-dev. E TEAM.md mira tipo-de-task, não módulo. Falta a ponte.
- **Dimensão 5 (quem-remove-regra-perigosa): a regra existe (constitucional), o ARTEFATO LEGÍVEL não.** Não há, por módulo/tela, a lista "regras Tier 0 que incidem aqui + autoridade pra afrouxar".

Hipótese do pedido **CONFIRMADA**: 1-2-3 parciais e espalhadas; 4 e 5 sem artefato canônico aplicável ao time novo.

---

# Pegadinhas conhecidas

- `[Tier 0]` **Constituição/Trust Tiers são append-only** — formalizar autoridade NÃO pode editar `TRUST-TIERS.md` accepted nem `CONSTITUTION.md` sem ADR `supersedes` + label `constitution-amendment` + `audit-*.md` no mesmo PR (governance-gate.yml Job 1 bloqueia). Definir #4/#5 É amendment constitucional.
- `[Tier 0]` **`business_id` global scope é a regra perigosa nº 1** — qualquer matriz de override tem que listá-la como L0-only.
- `[Pegadinha]` **`trust_required` ≠ autoridade humana** — não reusar o campo direto pra #4 sem desambiguar, senão confunde "agente runtime L2" com "Felipe pode codar". Precisa de campo NOVO (ex `dev_authority:`) ou mapa explícito tier→pessoa.
- `[Pegadinha]` **SCOPE.md NÃO está no eixo tela** — ele é por módulo. Charter é por tela. Não dá pra colocar #4/#5 só no SCOPE e achar que cobriu tela; charter precisa herdar do SCOPE (igual Constituição UI v2: camada superior herda da inferior).
- `[Pegadinha]` **Não criar 3º silo** (proibição `memory/proibicoes.md §Memória/governança` — não duplicar info). Reusar SCOPE.md (módulo) + charter (tela) + 1 matriz central de autoridade. NÃO inventar `GOVERNANCE.md` por módulo.
- `[Pegadinha]` **Glob de worktree atual é esparso** — este worktree (`frosty-greider-83ab2f`) tem checkout parcial; charters/SPECs reais estão em `D:\oimpresso.com\...` (main) e no worktree `epic-hermann-aa6de9`. Sempre buscar com path absoluto do main repo.
- `[Lição]` TEAM.md está desatualizado (última revisão 2026-04-28, "próxima após cycle 01 12-mai") — se #4 reusar TEAM.md, atualizar junto.

---

# Plug-points (ONDE plugar #4 e #5 — recomendação)

| Camada | Arquivo | Mudança proposta (NÃO implementada) |
|---|---|---|
| ADR mãe (constitucional) | `memory/decisions/02NN-governanca-5-dimensoes-modulo-tela.md` (NEW) | Define as 5 dimensões como contrato canônico + mapa tier→pessoa + autoridade override. `supersedes_partially: [0080]` no que toca Trust Tiers. Label `constitution-amendment`. |
| Eixo módulo | `Modules/<X>/SCOPE.md` frontmatter (36 arquivos) | Adicionar 2 campos: `dev_authority:` (lista pessoas/tiers que podem editar este módulo, distinto de `trust_required` runtime) + `dangerous_rules:` (lista regras Tier 0 que incidem + `override: L0-wagner-adr`). |
| Eixo tela | `<Tela>.charter.md` frontmatter (≈100 arquivos) | Charter HERDA do SCOPE (não duplica). Adicionar só override pontual quando tela diverge do módulo: `dev_authority_override:` opcional. Default = herda do parent_module SCOPE. |
| Matriz central | `memory/governance/AUTORIDADE-DEV-OVERRIDE.md` (NEW) ou estender `TEAM.md §3` | Tabela única: pessoa × módulo × (pode-codar? / pode-override?) cruzando TEAM.md (tipo-task) com SCOPE trust_required. Fonte legível pro onboarding. |
| Enforcement (futuro, opcional) | hook `block-dev-authority.ps1` + CI | Só DEPOIS de Wagner validar formato. Bloqueia Edit em `Modules/<X>/` por actor sem authority. NÃO propor agora. |

## Proposta de estrutura (recomendação cravada — R13)

**Reusar+estender, 3 níveis herdados (espelha Constituição UI v2):**

1. **Constituição/Trust Tiers** (já existe) = autoridade base #4/#5 — só Wagner L0 afrouxa Tier 0. Tornar explícito num ADR mãe que consolida as 5 dimensões.
2. **SCOPE.md por módulo** (já existe, 36/36) = home natural de #1 `purpose`, #2 `contains/not_contains`, #3 `permission_prefix`, #4 `trust_required`+novo `dev_authority`, #5 novo `dangerous_rules`. É O artefato consolidador por módulo — só faltam 2 campos.
3. **charter.md por tela** (já existe, ~100) = #1 Mission, #2 Goals/Non-Goals, #3 endpoints+permission. HERDA #4/#5 do SCOPE; só declara override quando a tela diverge.
4. **1 matriz legível** (`AUTORIDADE-DEV-OVERRIDE.md`) = view de onboarding cruzando pessoa×módulo, derivável dos campos acima.

**Por que esta e não um doc novo por módulo:** SCOPE.md já é declarado fonte-primária no pré-flight (`.claude/rules/modules.md`), já tem 36/36 cobertura, já carrega 4 das 5 dimensões. Adicionar 2 campos > criar 3º silo (que viola proibição não-duplicar). Charter herdando do SCOPE replica o padrão herança da Constituição UI v2 (camada superior herda, nunca contradiz).

---

# Recomendação pro Claude pai

**Caminho recomendado:** NÃO sair formalizando 34 módulos. Sequência:
1. Wagner valida o FORMATO (perguntas abaixo) — 1 ADR mãe define as 5 dimensões + os 2 campos novos de SCOPE + regra de herança charter.
2. Piloto em 1 módulo de cada bucket de risco (ex: Fiscal L3, Sells/Financeiro, Connector/Superadmin L0) pra Wagner ver o preenchimento real antes de escalar.
3. Backfill dos 36 SCOPE.md + matriz central só após aprovação do piloto.

**O que confirmar com Wagner ANTES de codar/formalizar:**

1. **`dev_authority` = pessoas ou tiers?** Você quer listar nomes (`[F, M]`) por módulo, ou mapear via tier (`trust_required: L3` → tabela tier→pessoa em 1 lugar)? Tier escala melhor com time crescendo; nomes são mais legíveis pro onboarding agora.
2. **#5 override granular ou global?** Basta a regra global "toda regra Tier 0 → só Wagner/L0 via ADR", ou você quer por módulo a LISTA das regras perigosas específicas (ex Fiscal: imutabilidade NFe; Ponto: append-only marcações) com autoridade por regra?
3. **Charter herda silenciosamente ou repete?** OK charter SÓ declarar override quando diverge do módulo (default = herda do SCOPE), ou você quer a dimensão visível em TODO charter mesmo repetindo (mais explícito, mais manutenção)?
4. **Matriz central nova OU estender TEAM.md?** TEAM.md já tem matriz §3 (por tipo-de-task) mas está desatualizado (abr/2026) e não é por-módulo. Crio `AUTORIDADE-DEV-OVERRIDE.md` novo (por-módulo) ou refaço TEAM.md §3 cruzando módulo?
5. **Escopo do piloto:** topo agora = formato + 1-3 módulos piloto, e backfill dos 34 entra como cycle/tasks depois? (alinha com R5 economia — não formalizar 34 sem Wagner ver 1 primeiro).

**Skills que DEVEM ativar (se virar implementação):** `preflight-modulo` (Tier A, lê SCOPE antes de tocar), `commit-discipline`, `multi-tenant-patterns` (se tocar Eloquent — não toca aqui), `charter-first` (se mexer charter).

**ADRs canon relacionadas:** 0079 (Constituição 7 camadas governança) · 0080 (Trust Tiers L0-L4 + SCOPE.md origem) · 0086 (Governance ActionGate) · 0094 (Constituição v2) · 0065 (permission registry) · 0153-0159 (module-grade rubric, mede governança mas não autoridade) · UI-0013 (herança de camadas — padrão a copiar). Formalização provável = ADR nova com `supersedes_partially: [0080]` + label `constitution-amendment`.
