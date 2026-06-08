---
slug: oimpresso-identity-mesh-manifests
title: "Identity Mesh — 5 Manifests Canônicos do Time Interno"
type: governance-spec
authority: canonical
lifecycle: ativo
version: 1.0.0
maintained_by: wagner
last_updated: 2026-05-15
charter_adr: 0081
related:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
  - 0081-identity-mesh-mcp-actors
  - 0086-fase-5-mvp-governance-actiongate-warn
  - 0093-multi-tenant-isolation-tier-0
pii: false
---

# Identity Mesh — 5 Manifests Canônicos do Time Interno

> **Doc canônica do estado declarado em `mcp_actors`**. Toda mudança de papel/escopo de membro do time interno passa por PR atualizando este doc + rodar `php artisan team-mcp:seed-actors`. Não edite o DB diretamente (drift Tier 0 — `memory/proibicoes.md`).

## §1. Por que este doc existe

Constituição v1.1.0 Art. 6 (Identity Mesh) determina:

> *Todo actor — humano ou IA — que toque o oimpresso DEVE ter manifest declarado em `mcp_actors`. Sem manifest declarado → sem ação (default-deny).*

[ADR 0081](../decisions/0081-identity-mesh-mcp-actors.md) criou o schema. [ADR 0086](../decisions/0086-fase-5-mvp-governance-actiongate-warn.md) criou ActorResolver + ActionGate em warn-only. Mas:

- Os manifests v0 da migration legacy `2026_05_05_240002_seed_initial_actors` foram um first-pass conservador genérico
- Time entra no MCP server `mcp.oimpresso.com` em breve (Felipe + Maiara + Luiz + Eliana)
- Sem manifests refletindo papel REAL de cada um, ActionGate fica genérico → warn-only impreciso → time entra e drift escala

Este doc é a **source of truth declarativa** dos 5 humanos do time. O seeder `Modules/TeamMcp/Database/Seeders/McpActorsSeeder.php` lê **daqui** (replica em PHP) e popula `mcp_actors`.

## §2. Os 5 Manifestos

### 2.1. Wagner — `slug=wagner` (L0 KERNEL)

| Campo | Valor |
|---|---|
| type | `human` |
| trust_level | **L0** (root sovereign — Constituição Art. 1) |
| parent_actor | null |
| user_id legacy | 1 (UltimatePOS) |
| display_name | Wagner Rocha |
| modules_write | `[*]` — toca tudo |
| modules_read | `[*]` |
| modules_blocked | `[]` |
| skills_required | `[brief-first, mcp-first, multi-tenant-patterns, commit-discipline]` (Tier A) |
| actions_blocked | `[]` |
| audit_required | **false** (único actor — meta-audit infinito; ações L0 vão em `mcp_audit_log.kernel_action`) |

**Por quê.** Root sovereign (Art. 1). Modificar Constituição, criar/dropar tabelas raiz, criar/revogar tokens, promover/demover actors. Nada bloqueado.

---

### 2.2. Felipe — `slug=felipe` (L2 OPERATOR)

| Campo | Valor |
|---|---|
| type | `human` |
| trust_level | **L2** OPERATOR |
| parent_actor | wagner |
| user_id legacy | null (ainda não tem UltimatePOS user — só MCP token) |
| display_name | Felipe (dev+suporte, migração WR Comercial) |
| modules_write | `[Officeimpresso, OficinaAuto, ComunicacaoVisual, legacy-delphi/*]` |
| modules_read | `[*]` |
| modules_blocked | `[Connector, Superadmin, Governance, ADS, TeamMcp]` |
| skills_required | `[brief-first, mcp-first, multi-tenant-patterns, preflight-modulo, officeimpresso-source-analysis, officeimpresso-financial-snapshot]` |
| actions_blocked | `[drop_table, schema_destructive, push_main_no_pr, merge_pr_solo, deploy_prod_solo]` |
| audit_required | true |

**Por quê.** Felipe é dev Delphi histórico que vai migrar WR Comercial (sistema legacy ainda em Delphi sob `legacy-delphi/`) pro oimpresso. Owner técnico:

- **`Officeimpresso`** — módulo histórico UltimatePOS legacy onde mora cliente WR Comercial
- **`OficinaAuto`** + **`ComunicacaoVisual`** — verticais novos (CNAE 4520-0/01 e 1813-0/01); Felipe trabalha na evolução
- **`legacy-delphi/*`** — pseudo-módulo: refere-se ao código Delphi sob `legacy-delphi/` no repo (fora de `Modules/`). Felipe precisa de write nesse path durante migração

Bloqueado de:
- **Connector + Superadmin** (L0 only — Art. 5 §1)
- **Governance + ADS + TeamMcp** (L1 only — Wagner aprova policies/skills/tokens)

`merge_pr_solo` bloqueado: Felipe pode criar PRs mas merge pra `main` exige aprovação Wagner (`push_main_no_pr` também bloqueado).

---

### 2.3. Maiara — `slug=maira` (L2 OPERATOR)

> ⚠️ **Slug legado `maira` (typo preservado).** Migration `2026_05_05_240002_seed_initial_actors` seedou com typo "maira"; `2026_05_07_140000_update_actor_display_name_maiara` corrigiu apenas `display_name`. Slug mantido pra preservar FKs em `mcp_tokens.actor_id`, `mcp_audit_log.actor_slug` etc. **Quem aparece em UI:** "Maiara".

| Campo | Valor |
|---|---|
| type | `human` |
| trust_level | **L2** OPERATOR |
| parent_actor | wagner |
| user_id legacy | 74 (UltimatePOS) |
| display_name | Maiara (suporte+dev) |
| modules_write | `[Crm, Sells, Repair, Inventory, Purchase]` |
| modules_read | `[*]` |
| modules_blocked | `[Connector, Superadmin, Governance, ADS, TeamMcp, NfeBrasil, RecurringBilling]` |
| skills_required | `[brief-first, mcp-first, multi-tenant-patterns, preflight-modulo, ticket-triage]` |
| actions_blocked | `[drop_table, schema_destructive, push_main_no_pr, deploy_prod_solo]` |
| audit_required | true |

**Por quê.** Maiara é suporte+dev all-around — atende ticket de cliente final, ajusta CRM/Vendas/OS/Estoque/Compras. Bloqueado de:

- **L0/L1** (Connector/Superadmin/Governance/ADS/TeamMcp)
- **`NfeBrasil`** e **`RecurringBilling`** — fiscal só L3 Eliana ou L0 Wagner. Maiara nunca toca código fiscal sem supervisão (risco SEFAZ + LGPD)

`deploy_prod_solo` bloqueado por convenção dura do TEAM.md ("M não faz deploy produção sozinha").

---

### 2.4. Luiz — `slug=luiz` (L3 VERTICAL)

| Campo | Valor |
|---|---|
| type | `human` |
| trust_level | **L3** VERTICAL |
| parent_actor | wagner |
| user_id legacy | null (ainda não tem UltimatePOS user — só MCP token) |
| display_name | Luiz (iniciante + IA-pair, owner Modules/Mobile) |
| modules_write | `[Mobile, Pages/Mobile/*]` |
| modules_read | `[*]` |
| modules_blocked | `[Connector, Superadmin, Governance, ADS, TeamMcp]` |
| skills_required | `[brief-first, mcp-first, multi-tenant-patterns, criar-modulo, charter-first]` |
| actions_blocked | `[merge_pr_solo, push_main, drop_table, schema_destructive, prod_migration_solo, deploy_prod_solo]` |
| audit_required | true |

**Por quê.** Luiz é iniciante. Está criando o **Modules/Mobile** (ainda não existe no repo — vai criar do zero). Escopo declarado pequeno e cirúrgico:

- **`Mobile`** — `Modules/Mobile/` (a criar)
- **`Pages/Mobile/*`** — `resources/js/Pages/Mobile/*.tsx`

Skills incluem `criar-modulo` (vai criar módulo novo) e `charter-first` (Inertia/React futuros).

Actions bloqueados são **fortes** porque é iniciante:
- `merge_pr_solo` — TEAM.md: "L não mergeia PR sozinho (F ou W aprova)"
- `prod_migration_solo` — migrations em prod sempre L2+ (Felipe ou Wagner)

> **Evolução prevista.** Quando Luiz criar `Modules/Mobile`, atualizar `modules_write` aqui pra incluir submódulos (ex: `Mobile/Domain`, `Mobile/Http`, etc) e rodar `php artisan team-mcp:seed-actors` pra propagar. Updates per PR.

---

### 2.5. Eliana[E] — `slug=eliana` (L3 VERTICAL)

> ⚠️ Distinguir de **Eliana(WR2)** (cliente externa, eliana@wr2.com.br) — `Eliana[E]` aqui é esposa Wagner, advogada+financeiro+dev IA-pair (TEAM.md).

| Campo | Valor |
|---|---|
| type | `human` |
| trust_level | **L3** VERTICAL |
| parent_actor | wagner |
| user_id legacy | 3 (UltimatePOS) |
| display_name | Eliana (advogada + financeiro, esposa Wagner) |
| modules_write | `[Financeiro, FinanceiroAvancado, NfeBrasil, NFSe, Accounting, RecurringBilling]` |
| modules_read | `[*]` |
| modules_blocked | `[Connector, Superadmin, Governance, ADS, TeamMcp, Mobile, Copiloto, Jana]` |
| skills_required | `[brief-first, mcp-first, multi-tenant-patterns, preflight-modulo]` |
| actions_blocked | `[drop_table, schema_destructive, push_main_no_pr, deploy_prod_solo, edit_non_financial_code]` |
| audit_required | true |

**Por quê.** Eliana é advogada+financeiro estudando LGPD (decisão Wagner 2026-05-09: NÃO assume DPO formal ainda — vai estudar primeiro). É **OWNER de fiscal**:

- **`Financeiro` + `FinanceiroAvancado` + `Accounting`** — contas a pagar/receber, plano de contas, conciliação
- **`NfeBrasil` + `NFSe`** — emissão fiscal (CNAE Comunicação Visual exige NFe-de-boleto-pago automática — US-RB-044)
- **`RecurringBilling`** — billing recorrente + boletos (relação com fiscal)

Bloqueada de:
- **L0/L1** (Connector/Superadmin/Governance/ADS/TeamMcp)
- **`Mobile`** — Luiz é owner
- **`Copiloto` + `Jana`** — TEAM.md regra dura: "E não mexe em Copiloto sprints LGPD". Sprints LGPD na Jana são restritos (Wagner conduz)

Action `edit_non_financial_code` é declarativo (gate enforce em ActionGate Fase 5 strict-mode futuro).

---

## §3. Tabela resumo

| slug | display_name | tier | modules_write (top) | OWN | BLOCKED notável |
|---|---|---|---|---|---|
| wagner | Wagner Rocha | L0 | `[*]` | tudo | — |
| felipe | Felipe (dev+suporte) | L2 | Officeimpresso, OficinaAuto, ComunicacaoVisual, legacy-delphi/* | migração Delphi + 2 verticais | L0/L1 modules |
| maira | Maiara (suporte+dev) | L2 | Crm, Sells, Repair, Inventory, Purchase | suporte all-around | NfeBrasil, RecurringBilling |
| luiz | Luiz (iniciante) | L3 | Mobile, Pages/Mobile/* | Modules/Mobile futuro | merge_pr_solo |
| eliana | Eliana (advogada+financeiro) | L3 | Financeiro, NfeBrasil, NFSe, Accounting, RecurringBilling | fiscal+contábil | Copiloto/Jana (sprints LGPD) |

## §4. Como atualizar manifests

Mudou papel? Promotion/demotion? Module novo entrou no escopo de alguém?

1. **PR atualizando este doc** + atualizando array em `Modules/TeamMcp/Database/Seeders/McpActorsSeeder.php` (replica fiel deste doc)
2. **ADR** se mudança é tier (promotion L2→L1) — não exigida pra ajuste de `modules_write/blocked` rotineiro
3. Wagner aprova merge
4. Em prod: `php artisan team-mcp:seed-actors` (idempotente — só atualiza diff)
5. Pest tests rodam — invariantes garantem que os 5 ainda existem + tier correto

> **NUNCA edite `mcp_actors` direto em prod (drift Tier 0).** O caminho é PR → migrate → seeder. `memory/proibicoes.md` §"REGRA PRIMÁRIA — Mexeu, REGISTRA".

## §5. Como ActionGate consome

[ADR 0086](../decisions/0086-fase-5-mvp-governance-actiongate-warn.md) — `ActionGate` middleware (warn-only em 2026-05-15) lê via `ActorResolver::fromRequest()`:

1. Request MCP entra → `mcp_token` resolvido pelo `McpAuthMiddleware`
2. `ActorResolver::fromRequest()` retorna `$actor` (ou null se revogado)
3. `ActionGate` verifica:
   - `$actor->canWriteModule($targetModule)` — checa modules_write vs modules_blocked
   - `$actor->isActionBlocked($actionKey)` — checa actions_blocked
   - `$actor->trust_level` ≥ tier requerido pela ação (mapping ADR 0065 risk → tier)
4. **Warn-only** atual: log estruturado em `mcp_audit_log` mas request prossegue
5. **Strict futuro** (Fase 5 §7): 4xx response se denied

Sem manifests populados, passo 3 sempre passa (genérico). **Após este doc + seeder rodando**, ActionGate emite warnings precisos por slug.

## §6. Edge cases catalogados

| Edge case | Estado | Resolução |
|---|---|---|
| Wagner tem 2 user_ids (1, 2) em UltimatePOS | conhecido | `user_id=1` canônico; row id=2 é duplicata legada |
| Slug `maira` ≠ display `Maiara` | conhecido | typo legado preservado pra FK; UI mostra display_name |
| `claude-code-wagner-laptop` ainda existe na tabela | conhecido | IA-agent L2 com `parent_actor=wagner` — não é gerenciado por este doc (gerado pelo seed legacy) |
| `Modules/Mobile` ainda não existe (Luiz vai criar) | aguardando | manifest pré-declarado; atualizar quando Luiz fizer scaffold |
| Felipe sem `user_id` UltimatePOS | aceitável | bind via mcp_token apenas; quando criar user UltimatePOS, atualizar `user_id` aqui + seeder |

## §7. Histórico

- **v1.0.0** (2026-05-15) — Doc inicial. 5 manifests canônicos declarados. Seeder + comando `team-mcp:seed-actors` + 8 Pest tests entregues. Gap "mcp_actors vazia" fechado antes do time entrar no MCP.
