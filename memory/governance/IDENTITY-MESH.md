---
id: governance-identity-mesh
slug: oimpresso-identity-mesh
title: "Identity Mesh — actors humanos + IA com manifest (Constituição Art. 6)"
type: governance-spec
authority: canonical
lifecycle: ativo
version: 1.0.0
maintained_by: wagner
last_updated: 2026-05-05
charter_adr: 0081
related:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
  - 0081-identity-mesh-mcp-actors
pii: false
---

# Identity Mesh — actors humanos + IA com manifest

> **Versão 1.0.0 — 2026-05-05**
> **Hierarquia:** subordinada à [Constituição](CONSTITUTION.md) v1.1.0 Artigo 6

Operacionaliza Artigo 6 — todo actor (humano ou IA) tem identidade declarada via manifest. Sem manifest = sem ação. Default-deny rigoroso.

---

## §1. Por que Identity Mesh

Em era de IAs conectando via MCP, "quem fez essa ação?" precisa ter resposta verificável. Manifest é a verdade declarada; audit log é a verdade observada. Quando divergem, audit ganha — Wagner investiga.

**Antes (sem mesh):** token MCP bind a `users.id` (UltimatePOS). Mas:
- `users.username` é "WR23" (legacy UltimatePOS) — não é slug canonical
- `mcp_tasks.owner` usa "wagner" (lowercase) — slug semântico
- Resolver `token → user_id → username` → busca tasks `WHERE owner=username` → 0 resultados → tools `my-work`/`my-inbox`/`triage` quebram
- IAs externas conectando via MCP não têm manifest declarado — qualquer token vira root implícito

**Com mesh:** todo actor tem slug canônico (`wagner`, `felipe`, `claude-code-wagner-laptop`). Token bind a `actor_id`. Tools resolvem `token → actor.slug` → busca por `tasks.owner=slug`. Correto.

---

## §2. Schema do manifest

```yaml
actor: <slug-único-canonical>            # ex: wagner, felipe, claude-code-wagner
type: human | ai_agent | service          # human = pessoa; ai_agent = IA conectada; service = bot interno
trust_level: L0|L1|L2|L3|L4               # ver TRUST-TIERS.md
parent_actor: <slug ou null>              # IA herda contexto do humano que a paternal
modules_write: [<lista>] ou [*]           # módulos que pode editar
modules_read: [<lista>] ou [*]            # módulos que pode ler (default *)
modules_blocked: [<exclusões explícitas>]  # módulos proibidos mesmo se outros forem *
skills_required: [<auto-load skills>]     # IAs precisam carregar antes de agir
actions_blocked: [<actions específicas>]  # ex: drop_table, schema_destructive, push_main_no_pr
audit_required: true|false                # default true; só wagner false (root)
created_by: <slug>                        # quem criou
revoked_at: null | <timestamp>            # null = ativo
revoked_by: <slug se revogado>
notes: "<contexto livre>"
```

---

## §3. Schema da tabela `mcp_actors`

```sql
CREATE TABLE mcp_actors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(60) NOT NULL UNIQUE,
    type ENUM('human','ai_agent','service') NOT NULL,
    trust_level ENUM('L0','L1','L2','L3','L4') NOT NULL,
    parent_actor_id BIGINT UNSIGNED NULL,

    -- Capabilities (JSON arrays)
    modules_write JSON NOT NULL,        -- ["Jana","KB"] ou ["*"]
    modules_read JSON NOT NULL,         -- ["*"]
    modules_blocked JSON NOT NULL,      -- ["Connector","Superadmin"]
    skills_required JSON NOT NULL,      -- ["oimpresso-stack","multi-tenant-patterns"]
    actions_blocked JSON NOT NULL,      -- ["drop_table","schema_destructive"]

    -- Governance
    audit_required BOOLEAN NOT NULL DEFAULT TRUE,

    -- Linking ao sistema legacy (UltimatePOS users)
    user_id INT UNSIGNED NULL,          -- bind to users.id se humano
    display_name VARCHAR(120) NOT NULL,

    -- Audit trail
    created_by_actor_id BIGINT UNSIGNED NULL,
    revoked_at TIMESTAMP NULL,
    revoked_by_actor_id BIGINT UNSIGNED NULL,

    notes TEXT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    INDEX idx_actors_type (type),
    INDEX idx_actors_trust (trust_level),
    INDEX idx_actors_user (user_id),
    INDEX idx_actors_revoked (revoked_at)
);
```

E ALTER em tabelas existentes:

```sql
ALTER TABLE mcp_tokens ADD COLUMN actor_id BIGINT UNSIGNED NULL AFTER user_id;
ALTER TABLE mcp_tokens ADD INDEX idx_tokens_actor (actor_id);

ALTER TABLE users ADD COLUMN mcp_actor_id BIGINT UNSIGNED NULL AFTER username;
```

---

## §4. Resolver pattern (substitui ADR 0077)

```php
// Antes (ADR 0077 propunha): token → user → user.mcp_handle
// Agora (ADR 0081): token → actor → actor.slug

// Em Modules/TeamMcp/Services/ActorResolver.php (a criar)

public function resolveActorFromToken(string $bearerToken): ?Actor {
    $hash = hash('sha256', $bearerToken);
    $token = McpToken::where('sha256_token', $hash)
        ->whereNull('revoked_at')
        ->first();

    if (!$token || !$token->actor_id) {
        return null; // default-deny
    }

    $actor = McpActor::find($token->actor_id);

    if ($actor->revoked_at) {
        return null; // actor revogado
    }

    return $actor;
}

// Tools MCP usam:
// $actor = resolveActor($bearerToken);
// if (!$actor) reject();
// $tasks = McpTask::where('owner', $actor->slug)->get();
```

---

## §5. Os 6 actors iniciais (seed)

### `wagner` (humano, L0)

```yaml
actor: wagner
type: human
trust_level: L0
parent_actor: null
modules_write: ["*"]
modules_read: ["*"]
modules_blocked: []
skills_required: []
actions_blocked: []
audit_required: false   # único actor com audit_required=false (root)
user_id: 1
display_name: "Wagner Rocha"
notes: "Root sovereign — Constituição Art. 1"
```

### `felipe` (humano, L2)

```yaml
actor: felipe
type: human
trust_level: L2
parent_actor: null
modules_write: ["Jana", "Notas", "KB", "Project", "Financeiro", "ConsultaOs"]
modules_read: ["*"]
modules_blocked: ["Connector", "Superadmin", "TeamMcp"]
skills_required: []
actions_blocked: ["drop_table", "schema_destructive", "push_main_no_pr"]
audit_required: true
display_name: "Felipe (dev+suporte)"
```

### `maira` (humano, L2)

```yaml
actor: maira
type: human
trust_level: L2
parent_actor: null
modules_write: ["Jana", "Notas", "KB", "Project"]
modules_read: ["*"]
modules_blocked: ["Connector", "Superadmin", "TeamMcp"]
skills_required: []
actions_blocked: ["drop_table", "schema_destructive", "push_main_no_pr", "deploy_prod_solo"]
audit_required: true
user_id: 74
display_name: "Maiara (suporte+dev)"
```

### `luiz` (humano, L3)

```yaml
actor: luiz
type: human
trust_level: L3
parent_actor: null
modules_write: ["Jana", "Notas"]
modules_read: ["*"]
modules_blocked: ["Connector", "Superadmin", "TeamMcp", "ADS"]
skills_required: []
actions_blocked: ["merge_pr", "push_main", "drop_table"]
audit_required: true
display_name: "Luiz (iniciante + IA-pair)"
notes: "L3 — não mergeia PR sozinho (Felipe ou Wagner aprova)"
```

### `eliana` (humano, L3)

```yaml
actor: eliana
type: human
trust_level: L3
parent_actor: null
modules_write: ["Financeiro", "Notas"]
modules_read: ["*"]
modules_blocked: ["Connector", "Superadmin", "TeamMcp", "Jana", "ADS"]
skills_required: []
actions_blocked: ["deploy_prod_solo", "drop_table"]
audit_required: true
user_id: 3
display_name: "Eliana (financeiro + IA-pair, esposa Wagner)"
notes: "Não mexe em Jana sprints LGPD (TEAM.md)"
```

### `claude-code-wagner-laptop` (ai_agent, L2)

```yaml
actor: claude-code-wagner-laptop
type: ai_agent
trust_level: L2
parent_actor: wagner   # herda contexto do Wagner
modules_write: ["Jana", "Notas", "KB", "Project"]
modules_read: ["*"]
modules_blocked: ["Connector", "Superadmin", "MemCofre"]   # MemCofre vira SRS, regras imutáveis — só Wagner edita
skills_required: ["oimpresso-stack", "multi-tenant-patterns", "publication-policy"]
actions_blocked: ["drop_table", "schema_destructive", "push_main_no_pr", "delete_prod_data"]
audit_required: true
display_name: "Claude Code @ Wagner Laptop"
notes: "Token DXT — Wagner gerou em 2026-04-30. Sessões aparecem em mcp_cc_sessions."
```

---

## §6. Auto-onboarding de IA externa (futuro)

Pattern proposto pra Fase 5:

```
1. IA externa solicita conexão MCP via /teammcp/onboarding
2. Wagner recebe notification em /governance dashboard
3. Wagner avalia em UI:
   - Tipo: ai_agent
   - Trust default: L3 (conservador)
   - Modules permitted: Wagner declara explicitamente (subset)
   - Skills requeridas: Wagner escolhe pacote
4. Wagner aprova → INSERT em mcp_actors
5. Token gerado bind a actor.id
6. ActionGate enforça em toda request
```

Sem essa cerimônia, IA externa **não consegue ação**.

---

## §7. Promotion / Demotion

| De | Pra | Como | Aprova | Tempo |
|---|---|---|---|---|
| L4 → L3 | promotion | adicionar especialização (skill domínio) | Wagner via ADR | dias |
| L3 → L2 | promotion | dev fez ≥3 PRs aprovadas em módulos product | Wagner via ADR | semanas |
| L2 → L1 | promotion | trabalho consistente em governance por trimestre | Wagner via ADR explícito | meses |
| L1 → L0 | promotion | NÃO existe (Wagner é único L0 por Art. 1) | — | — |
| qualquer → revoked | demotion | violação SCOPE.md, drift recorrente, credencial comprometida | Wagner imediato (sem ADR) | minutos |

**Demotion = `mcp_actors.revoked_at` + `revoked_by_actor_id` setados.** Tokens vinculados ficam inválidos imediatamente (resolver checa `actor.revoked_at IS NULL`).

---

## §8. Cascade Review (cumprindo §10.4)

Mudança neste documento (L4 Identity Mesh) cascata pra:

- **L5 Module Charter** — `not_contains[]` referenciam owner; verificar se actors mudaram
- **L6 Policy Gating** — `mcp_governance_rules.condition` pode referenciar actor.slug
- **L7 Audit** — `mcp_audit_log.actor_slug` é NOT NULL; novos actors precisam estar registrados antes da primeira ação

---

## §9. Verificação operacional

Tools MCP que dependem de actor identificado:

| Tool | Comportamento sem actor | Comportamento com actor |
|---|---|---|
| `my-work` | "Owner não pôde ser resolvido" | Lista tasks WHERE owner=actor.slug |
| `my-inbox` | "Sem user autenticado" | Lista notifications WHERE recipient=actor.slug |
| `triage` | OK (não filtra por actor) | OK (mesmo) |
| `cycles-active` | OK | OK |
| `tasks-list` | OK (filtros explícitos) | OK |

Após Fase 4 (este ADR aplicado), `my-work` e `my-inbox` voltam a funcionar sem `owner:wagner` explícito.

---

## §10. Estado de implementação

| Item | Status |
|---|---|
| IDENTITY-MESH.md (este doc) | ✅ v1.0.0 |
| Migration `mcp_actors` table | ⏸️ ADR 0081 |
| ALTER `mcp_tokens.actor_id` | ⏸️ ADR 0081 |
| Seed 6 actors | ⏸️ ADR 0081 (esta sessão) |
| Backfill tokens → actor_id | ⏸️ ADR 0081 |
| ActorResolver service | ⏸️ Modules/TeamMcp/Services/ |
| MCP server CT 100 update (resolver code) | ⏸️ deploy CT 100 separado |
| UI Modules/TeamMcp/admin/actors | ⏸️ Fase 5 |

---

## Histórico

- **v1.0.0** (2026-05-05) — Doc inicial. 6 actors definidos. Schema mcp_actors detalhado. Supersedes ADR 0077.
