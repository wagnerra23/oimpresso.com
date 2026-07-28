---
id: governance-trust-tiers
slug: oimpresso-trust-tiers
title: "Trust Tiers Operacional — L0-L4 (Constituição Art. 5)"
type: governance-spec
authority: canonical
lifecycle: ativo
version: 1.0.0
maintained_by: wagner
last_updated: 2026-05-05
charter_adr: 0080
related:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
  - 0065-permission-registry-contract
pii: false
---

# Trust Tiers — Operacionalização do Artigo 5 da Constituição

> **Versão 1.0.0 — 2026-05-05**
> **Hierarquia:** subordinada à [Constituição](CONSTITUTION.md) v1.1.0 Artigo 5

Este documento operacionaliza o Artigo 5 da Constituição. Define os 5 tiers, capabilities por tier, regras de promotion/demotion, e mapeamento ao `permission_registry` existente (ADR 0065).

---

## §1. Os 5 tiers em detalhe

### L0 — KERNEL (sovereign)

**Quem ocupa.** Wagner Rocha, exclusivamente. Sem promotion possível sem mudança constitucional (Artigo 1 + 10).

**Capabilities.**

- Modificar Constituição (com ADR + version bump)
- Criar/atualizar SRS append-only
- Modificar/deletar tabelas raiz (`users`, `business`, `permissions`, `mcp_actors`)
- Migrations destrutivas (DROP TABLE, DROP COLUMN)
- Criar/revogar tokens MCP de qualquer actor
- Promover/demover qualquer actor entre tiers
- Tocar código em `Modules/Connector` e `Modules/Superadmin`

**Restrições.**

- Toda ação L0 é logada em `mcp_audit_log` com flag `kernel_action: true`
- Mesmo Wagner não pode editar `mcp_audit_log` (trigger MySQL imutabilidade)

**Quem pode invocar.** Wagner via UI Web, CLI, SSH, ou API key bind a `actor=wagner`.

---

### L1 — GOVERNANCE

**Quem ocupa.** Wagner como L1 default (todo L0 é também L1). Outro actor só por delegação explícita via ADR + manifest atualizado.

**Capabilities.**

- Editar Trust Tiers (este documento; com ADR + cascade review)
- Editar Identity Mesh (manifest patterns)
- Aprovar/rejeitar amendments constitucionais propostos
- Editar `mcp_governance_rules` (policies executáveis)
- Tocar código em `Modules/ADS`, `Modules/TeamMcp`, `Modules/Governance`, `Modules/SRS`
- Criar SRS entries (com ADR de origem)
- Aprovar versions de skills via UI `/ads/admin/skills-review`
- Promover L2 → L1 (com ADR justificando)

**Restrições.**

- Não pode tocar Connector/Superadmin (L0 only)
- Não pode editar mcp_audit_log
- Audit obrigatório (`audit_required: true`)

---

### L2 — OPERATOR (Product devs com IA pareada)

**Quem ocupa.** Wagner, Felipe, Maiara (humano dev) + Claude Code instances pareadas (IA). IA L2 carrega Skills obrigatórias antes de qualquer ação.

**Capabilities.**

- Tocar código em módulos PRODUCT: `Jana`, `Notas`, `KB`, `Project`, `Ponto`, `ConsultaOs`
- Criar/atualizar features dentro do scope declarado em SCOPE.md do módulo
- Criar tasks em `mcp_tasks` (governadas por ADR 0070)
- Criar PRs (não merge) em qualquer módulo até L3
- Editar Skills (sob aprovação L1 via review queue)
- Sugerir promotion de tasks no Kanban

**Restrições.**

- Tocar fora do `SCOPE.md.contains[]` declarado = drift, bloqueia em pre-commit
- Audit obrigatório
- Skills obrigatórias por contexto: ações tocando código com `business_id` exigem skill `multi-tenant-patterns` carregada

**Skills L2 padrão (auto-load).**

- `multi-tenant-patterns` (qualquer toque em business_id)
- `oimpresso-stack` (início de sessão)
- `publication-policy` (antes de push/PR)

---

### L3 — VERTICAL SPECIALIST

**Quem ocupa.** Especialistas internos (Eliana[E] em Financeiro, ex-WR2 em Ponto, etc.) + IA pareada com Skills do domínio.

**Capabilities.**

- Tocar código em módulos VERTICAL: Financeiro, NfeBrasil, NFSe, RecurringBilling, Officeimpresso, Grow, IProduction, Repair, Manufacturing, AssetManagement, Crm, Accounting, Essentials, Woocommerce
- Criar tasks no domínio
- Criar PRs (não merge)
- Sugerir adições à SRS via ADR proposto

**Restrições.**

- Tocar fora do domínio especialista = exige aprovação L2+
- Audit obrigatório
- Skills obrigatórias por domínio (ex: `nfe-fiscal` em NfeBrasil — a criar)

---

### L4 — CONTENT / PUBLIC

**Quem ocupa.** Editores (humano) + IA com restrição estrita. Cliente final via APIs read-only.

**Capabilities (humano editor).**

- Editar conteúdo em `Modules/Cms` (landing/blog)
- Atualizar `Modules/Spreadsheet` (uso interno)
- Atualizar `Modules/ProductCatalogue` (catálogo + media)

**Capabilities (cliente final via APIs).**

- Read-only sobre dados do próprio business
- Endpoints públicos: `/consulta-os/{id}` (portal OS), `/c/page/*` (blog), `/c/contact-us`
- Sem capability write em DB (exceção: form submissions limitadas)

**Restrições.**

- Não pode tocar lógica/código — apenas conteúdo
- Cliente final tem rate limit (não declarado nesta versão; Fase 5)

---

## §2. Mapa de mudança de tier (promotion/demotion)

| De | Pra | Como | Aprova |
|---|---|---|---|
| L4 → L3 | promotion | adicionar especialização (skill domínio) | Wagner via ADR |
| L3 → L2 | promotion | dev faz PRs aprovadas em ≥3 módulos product | Wagner via ADR |
| L2 → L1 | promotion | trabalho em governance (ADRs, SCOPE.md, audit) consistente por trimestre | Wagner via ADR explícito |
| L1 → L0 | promotion | NÃO existe na v1.0 — só Wagner é L0 |
| qualquer → revogado | demotion | violação de SCOPE.md, drift recorrente, ou compromisso de credenciais | Wagner imediato (sem ADR) |

**Promotion não é automática.** Wagner aprova explicitamente cada caso.

---

## §3. Mapeamento `permission_registry.risk` → Trust Tier (ADR 0065)

| ADR 0065 risk | Trust Tier requerido |
|---|---|
| **critical** | L1 GOVERNANCE (ou L0 se KERNEL) |
| **high** | L2 OPERATOR |
| **medium** | L3 VERTICAL |
| **low** | L4 CONTENT |

**Conversão automática.** ActionGate middleware (Fase 5) lê `permission.risk` da action e exige `actor.trust_level ≥ tier_correspondente`.

**Override explícito.** Algumas actions podem declarar `trust_required` no SCOPE.md do módulo, sobrepondo o mapping default.

---

## §4. Atributos do actor manifest (referência cruzada com Artigo 6)

```yaml
actor: <slug>
type: human | ai_agent
trust_level: L0|L1|L2|L3|L4
parent_actor: <slug ou null>
modules_write: [<lista>] ou [*]
modules_read: [<lista>] ou [*]
modules_blocked: [<exclusões>]
skills_required: [<auto-load por contexto>]
actions_blocked: [<lista de actions específicas>]
audit_required: true|false
created_by: <slug>
revoked_at: null | <timestamp>
```

**Defaults por tier:**

| Tier | audit_required | modules_write default |
|---|---|---|
| L0 | true | [*] |
| L1 | true | [Modules de governance] |
| L2 | true | [SCOPE.md product authorized] |
| L3 | true | [SCOPE.md vertical authorized] |
| L4 | true | [SCOPE.md content authorized] |

---

## §5. Ações por tier — exemplos concretos

| Ação | Tier mínimo | Por quê |
|---|---|---|
| `git push origin main` em prod | L1 | publicação afeta cliente |
| Criar `Modules/X/SCOPE.md` novo | L1 | charter de módulo |
| `php artisan migrate` em prod | L0 | schema |
| `php artisan skill:scaffold "..."` | L2 | scaffolder operacional |
| Editar SKILL.md de skill existente | L1 (com aprovação L1 via review queue) | governance de skill |
| Criar `mcp_tasks` row | L2 | task tracking |
| Aprovar version de skill em `/ads/admin/skills-review` | L1 | release decision |
| Editar config de business (multi-tenant) em prod | L0 | tenant integrity |
| Criar issue no GitHub | L2 | externo |
| Comentar PR | L3 | low impact |
| Editar landing page (Cms) | L4 | content |
| `mysql ... DELETE FROM ponto_marcacoes` | **BLOCKED** (Artigo 3 imutabilidade) | trigger nega |

---

## §6. Auto-onboarding de IA externa

Quando IA externa quer conectar via MCP server:

1. **Wagner cria actor** em `mcp_actors` com `trust_level: L3` default (mais conservador)
2. Wagner declara `modules_write[]` mínimo necessário
3. Wagner declara `skills_required[]` que a IA precisa carregar
4. Token bind a `actor=<slug>` é gerado
5. ActionGate (Fase 5) consulta manifest a cada request
6. Wagner pode revogar em <1h via UI

**Default-deny rigoroso.** IA externa sem manifest declarado **não consegue ação**.

---

## §7. Revisão e evolução

- **Quarterly review:** Wagner revisa promotions/demotions trimestrais
- **Em incidente:** demotion imediato sem ADR; ADR posterior justificando
- **Mudança neste documento:** ADR formal + Cascade Review (§10.4) auditando IDENTITY-MESH.md, manifests existentes, SCOPE.md por módulo, mcp_governance_rules

---

## Histórico

- **v1.0.0** (2026-05-05) — Definição inicial dos 5 tiers + mapping ADR 0065 + actor manifest schema.
