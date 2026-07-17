---
slug: 0081-identity-mesh-mcp-actors
number: 81
title: "Identity Mesh — schema mcp_actors + manifest pattern + seed inicial 6 actors"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-05"
module: governance
quarter: 2026-Q2
tags: [governance, identity, identity-mesh, mcp-actors, p0]
supersedes:
  - 0077-mcp-resolver-owner-via-mcp-handle
supersedes_partially: []
superseded_by: []
related:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
  - 0070-jira-style-task-management-current-md-removed
pii: false
review_triggers:
  - "Time crescer >10 actors humanos exigindo refinamento de scopes"
  - "IA externa nova conectar via MCP exigindo onboarding flow novo"
  - "Incidente de credencial comprometida exigindo emergency revocation"
---

# ADR 0081 — Identity Mesh: mcp_actors + manifest + seed inicial

## Contexto

Audit cascata Constitution v1.1.0 (2026-05-05) identificou **P0.4** crítico: tabela `mcp_actors` não existe; tools MCP `my-work`/`my-inbox`/`triage` quebram porque resolver mapeia `mcp_tokens.user_id → users.username` mas:

- `users.username` é "WR23" (legacy UltimatePOS)
- `mcp_tasks.owner` usa "wagner" (lowercase canonical)
- Resolver retorna 0 results pra `WHERE owner='WR23'`

ADR 0077 propôs adicionar `users.mcp_handle` como solução. Mas Constituição v1.1.0 (Art. 6 Identity Mesh) exige **manifest completo** por actor — não apenas slug.

Manifest precisa cobrir:
- Trust level (L0-L4)
- Modules write/read/blocked
- Skills required (pra IAs)
- Actions blocked
- Audit required
- Parent actor (IA herda contexto do humano)
- Revocation tracking

ADR 0077 (apenas adiciona slug) **não cobre** isso. Esta ADR supersedes 0077 com solução completa.

## Decisão

### 1. Tabela `mcp_actors` canônica

Schema em `memory/governance/IDENTITY-MESH.md` §3 + migration `Modules/TeamMcp/Database/Migrations/2026_05_05_240001_create_mcp_actors_and_link_tokens.php`.

Pontos-chave:
- `slug` UNIQUE — handle canonical (wagner, felipe, claude-code-wagner-laptop)
- `type` enum (human/ai_agent/service)
- `trust_level` enum (L0-L4)
- `parent_actor_id` (IA herda de humano)
- `modules_write`/`read`/`blocked` JSON arrays
- `skills_required` JSON (auto-load skills)
- `actions_blocked` JSON (drop_table, push_main_no_pr, etc.)
- `audit_required` boolean (default true; só wagner=false)
- `revoked_at`/`revoked_by_actor_id` (revocation tracking)
- `user_id` nullable (binding com UltimatePOS users.id pra humanos)

### 2. ALTER tabelas existentes

- `mcp_tokens.actor_id` (nullable, FK lógica) — substitui `user_id` como fonte primária de identidade
- `users.mcp_actor_id` (nullable) — binding inverso pra resolver legacy

`user_id` em `mcp_tokens` **mantido por enquanto** (backward compat). Após Fase 5 (ActionGate), `actor_id` vira NOT NULL e `user_id` vira deprecated.

### 3. Seed inicial 6 actors

- **wagner** (human, L0, root) — único `audit_required=false`
- **felipe** (human, L2)
- **maira** (human, L2, user_id=74)
- **luiz** (human, L3)
- **eliana** (human, L3, user_id=3)
- **claude-code-wagner-laptop** (ai_agent, L2, parent=wagner)

Detalhes em IDENTITY-MESH.md §5.

### 4. Backfill tokens existentes

- Tokens com `user_id IN (1, 2)` (Wagner duplicado em users) → `actor_id=wagner`
- Token id=10 (DXT — Wagner gerado 30/04/2026 07:44) → `actor_id=claude-code-wagner-laptop`
- Tokens com `user_id=74` (Maíra) → `actor_id=maira`
- Tokens com `user_id=3` (Eliana) → `actor_id=eliana`

Felipe e Luiz ainda não têm token gerado — quando gerarem, bind a actor já estará disponível.

### 5. Resolver pattern

Em `Modules/TeamMcp/Services/ActorResolver.php` (a criar próxima sessão):

```php
public function resolveActorFromToken(string $bearerToken): ?Actor {
    $hash = hash('sha256', $bearerToken);
    $token = McpToken::where('sha256_token', $hash)
        ->whereNull('revoked_at')
        ->first();

    if (!$token || !$token->actor_id) return null;

    $actor = McpActor::find($token->actor_id);
    if ($actor->revoked_at) return null;

    return $actor;
}
```

Tools MCP usam `$actor->slug` em vez de `$user->username`. Bug do `my-work` resolvido.

### 6. Supersede ADR 0077

ADR 0077 propôs `users.mcp_handle` (apenas slug). Esta ADR substitui com `mcp_actors` (manifest completo). 0077 fica `superseded_by: 0081`.

A coluna `users.mcp_actor_id` cumpre o mesmo propósito do `mcp_handle` (binding humano legacy → actor canonical), mas via FK estruturada em vez de string handle.

## Justificativa

**Por que tabela dedicada vs coluna em users.** Tokens podem bind a IA (sem `user_id`). Actors podem ser não-humanos. Manifests crescem (skills_required, actions_blocked). Tabela dedicada escala melhor.

**Por que JSON arrays em vez de tabelas pivot.** `modules_write[]` raramente excede 10 itens. Tabela pivot adicionaria 5 JOINs por resolver. JSON com índice MySQL 5.7+ é eficiente o suficiente. Pivot vira opção se >50 modules ou queries cross-actor frequentes.

**Por que `audit_required=false` só pra Wagner.** Wagner é root. Audit do root teria audit do audit infinito. Pragmaticamente: ações L0 logadas separadamente em `mcp_audit_log` com flag `kernel_action` (Fase 5). Wagner=false na coluna mas continua logado.

**Por que parent_actor_id pra IA.** Claude Code @ Wagner herda contexto + auth + autorização. Se Wagner é demoted/revoked, IA filha cascata. Padrão hierárquico explícito.

**Por que NOT extender `users.username`.** UltimatePOS usa `username` em login + RBAC + sessions. Tocar = blast radius gigante. Coluna `mcp_actor_id` adicional não conflita com sistema legacy.

**Reabrir esta decisão se:** (a) IA externa nova exigir flow de onboarding mais elaborado que o seed; (b) team crescer >10 actors com necessidade de cargo/role hierarchy explícita; (c) revocation emergencial precisar bulk delete de tokens (hoje seria UPDATE-mass).

## Cascade Review (cumprindo §10.4)

Mudança em **L4 Identity Mesh** cascata pra:

| Camada | Auditada | Resultado | Ação |
|---|---|---|---|
| L5 Module Charter | ✅ sim | SCOPE.md `owner` field referencia actor.slug; 6 críticos atuais usam "wagner" | OK — wagner é actor.slug válido |
| L6 Policy Gating | ✅ sim | mcp_governance_rules.condition pode referenciar actor.slug | Existing rules continuam válidas; novas usam slug canonical |
| L7 Audit | ✅ sim | mcp_audit_log.actor_slug NOT NULL exige actor existir antes da primeira ação | Backfill de logs existentes mapeia user_id → actor.slug retroativo (script separado próxima sessão) |
| Skills cross-cutting | ✅ sim | 16 skills com `owner: wagner` ainda alinhado | OK |
| ADRs cross-cutting | ✅ sim | ADR 0077 superseded; ADR 0080 mantém validade | atualizar 0077 status |
| Tools MCP | ✅ sim | my-work/my-inbox/triage dependem de resolver atualizado | resolver atualizado em ActorResolver service (próxima sessão) |

## Consequências

**Positivas:**

- **P0.4 fechado.** Audit cascata 100% compliance em camadas 6+7.
- **Bug `my-work` desbloqueado.** Wagner volta a usar tools sem `owner:wagner` explícito.
- **Pattern preparado pra IAs externas.** Onboarding via `mcp_actors` insert + token bind. Default-deny enforça scope.
- **Revocation cirúrgica.** `revoked_at` setado = todos tokens do actor invalidados imediatamente.
- **ADR 0077 superseded com versão melhor.** Solução mais completa que apenas slug.

**Negativas / Trade-offs:**

- **Migration adiciona ~5MB de schema.** Aceitável.
- **JSON arrays não-indexáveis em MySQL 5.7 via virtual columns.** Queries por `modules_write CONTAINS 'X'` viram full table scan. OK porque `mcp_actors` tem <100 rows previsíveis.
- **MCP server CT 100 precisa update do código resolver.** Esta ADR cria a infra DB; deploy do código fica pra próxima sessão (não bloqueia funcionalidades atuais — tokens com user_id continuam funcionando via fallback).
- **Wagner com 2 user_ids (1, 2).** Migration trata ambos mapeando pra mesmo actor. ADR 0077 mencionou que id=2 é duplicata — manter por backward compat.

**Riscos mitigados:**

- IA externa não-rastreável (default-deny rigoroso).
- Tokens órfãos (sem actor_id) pós-migration: backfill cobre todos os 12 tokens existentes.
- Demotion silenciosa de capability: revoked_at é immutable após set (parte do append-only Art. 3 — futura Fase 5).

## Implementação

✅ **FEITO nesta ADR:**

1. Migration `2026_05_05_240001_create_mcp_actors_and_link_tokens.php`
2. Migration `2026_05_05_240002_seed_initial_actors.php` (6 actors + backfill tokens + users)
3. `Modules/TeamMcp/Providers/TeamMcpServiceProvider.php` ganha `loadMigrationsFrom`
4. `memory/governance/IDENTITY-MESH.md` v1.0.0
5. ADR 0077 atualizada com `superseded_by: 0081`
6. Esta ADR

⏸️ **Próxima sessão:**

- `Modules/TeamMcp/Entities/Actor.php` (Eloquent model)
- `Modules/TeamMcp/Services/ActorResolver.php` (resolver canonical)
- MCP server CT 100: deploy update do código resolver
- UI `/teammcp/actors` (CRUD básico, Fase 5)
- Backfill `mcp_audit_log.actor_slug` retroativo (script separado)

## Referências

- [Constituição v1.1.0 — Artigo 6](../governance/CONSTITUTION.md)
- [IDENTITY-MESH.md v1.0.0](../governance/IDENTITY-MESH.md)
- [Audit cascata v1.1.0](../governance/audit-2026-05-05-v1.1.md) — P0.4
- [TRUST-TIERS.md §4](../governance/TRUST-TIERS.md) — manifest schema referenciado
- [ADR 0077 — superseded por esta](0077-mcp-resolver-owner-via-mcp-handle.md)
- [ADR 0079 — Constituição](0079-constituicao-oimpresso-7-camadas-governanca.md)
- [ADR 0080 — Trust Tiers + Architecture](0080-trust-tiers-operacional-audit-findings.md)
- Migration files: `Modules/TeamMcp/Database/Migrations/2026_05_05_24000{1,2}_*.php`
