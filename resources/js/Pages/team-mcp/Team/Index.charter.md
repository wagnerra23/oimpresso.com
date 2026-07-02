---
page: /team-mcp/team
component: resources/js/Pages/team-mcp/Team/Index.tsx
owner: wagner
status: draft
last_validated: "2026-05-25"
parent_module: TeamMcp
related_us: [US-TEAM-002, US-TEAM-003]
related_adrs:
  - "0053-mcp-server-governanca-como-produto"
  - "0055-self-host-team-plan-equivalente-anthropic"
  - "0057-tela-team-admin-regras-governanca-tokens-mcp"
  - "0070-jira-style-task-management-current-md-removed"
  - "0081-identity-mesh-mcp-actors"
  - "0093-multi-tenant-isolation-tier-0"
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0095-skills-tiers-convencao-interna"
  - "0105-cliente-como-sinal-guiar-sem-mandar"
related_ficha: memory/requisitos/TeamMcp/CAPTERRA-DESIGN-FICHA.md
tier: A
charter_version: 1
---

# Page Charter — `/team-mcp/team` (DRAFT)

> **Status:** draft criado em 2026-05-25 junto com o PR `feat(team-mcp): drill-down tokens individuais + revoke por token + audit IP/last-used` a partir da [FICHA CAPTERRA](../../../../../memory/requisitos/TeamMcp/CAPTERRA-DESIGN-FICHA.md). Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/TeamMcp/Http/Controllers/TeamController.php` (Inertia::defer dupla — team rows + stats_globais).
> Persona ÚNICA: Wagner [W] @ biz=1 (superadmin com `copiloto.mcp.usage.all`). Felipe/Maiara/Eliana/Luiz NÃO usam a tela (consomem MCP, não geram).

---

## Mission

Console superadmin Wagner-only de **governança de credenciais MCP Tier 0**: emitir/revogar tokens por dev, auditar IP+last_used por credencial individual, controlar quotas BRL (diária/mensal), exportar CSV. É o único ponto de criação/revogação de tokens MCP (ADR 0057). Operação primária: onboarding seguro de Felipe/Maiara/Eliana/Luiz no MCP server + offboarding emergencial em caso de vazamento.

---

## Goals — Features (faz)

- Lista de devs com KPIs globais (Devs ativos hoje, Calls MCP hoje, Custo hoje/mês)
- Tabela por dev com 9 colunas (tokens ativos, custo hoje/mês, quotas dia/mês, top tools, último uso, ações)
- **Reveal-once** de token raw no momento da criação (Stripe-pattern, ADR 0057 §2)
- **Drill-down `<TokensListDialog>`** ao clicar contador "N ativos": lista tokens individuais com `name | created_at | expires_at | last_used_at | last_used_ip | status_pill | revoke` (G-DESIGN-01)
- **Revoke por token individual** scopado por user + business_id (G-DESIGN-02, Tier 0)
- **AlertDialog destrutivo** em gerar token / gerar .dxt / revogar token — substitui `window.confirm()` nativo (G-DESIGN-03)
- **Expor `last_used_ip` + `last_used_at` por token** com formato relativo PT-BR ("há 2 horas") + tooltip absoluto + "Nunca usado" cinza pra null (G-DESIGN-04)
- **Status pill semântico** por token: Ativo (verde) / Expira em Nd (amarelo se ≤7d) / Expirado (cinza) / Revogado (cinza) — G-DESIGN-05
- Gerar arquivo `.dxt` (Claude Desktop Extension) com token embutido + bridge stdio↔HTTP nativo Node
- Editor de quota daily/monthly em BRL via shadcn `<Dialog>` + `<QuotaForm>` (period toggle + block on exceed)
- Export CSV de audit log filtrado por período

---

## Non-Goals — Features (NÃO faz)

- ❌ NÃO permite **self-service** token-by-dev (Cycle 02 ADR 0057 §C — Wagner solo emite)
- ❌ NÃO mostra raw de token previamente emitido — apenas hash (ADR 0057 §2, reveal-once IRREVOGÁVEL)
- ❌ NÃO permite revogar token cross-tenant — backend força `where business_id = session business_id` ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
- ❌ NÃO faz 2FA / step-up auth ainda (G-DESIGN-10 backlog — sinal Vercel breach 2026)
- ❌ NÃO mostra chart de evolução custo (Cycle 03 dataviz)
- ❌ NÃO mostra audit log inline por user (G-DESIGN-08 PR seguinte)
- ❌ NÃO força expiration policy obrigatória ainda (G-DESIGN-06 PR seguinte)
- ❌ NÃO permite bulk revoke filtrando "não usado em 30d" (G-DESIGN-15 backlog)
- ❌ NÃO suporta atalhos teclado locais (Cmd+K global existe — G-DESIGN-24 backlog)

---

## UX targets (FICHA CAPTERRA 2026-05-25)

- 4 KPIs visíveis simultaneamente em 1280px+ (Wagner padrão 1920px)
- `<Deferred>` dupla: team + stats_globais (~50ms first paint vs 300-800ms hard load)
- Skeleton fallback coerente (5 rows pulse + KPIs 24h pulse)
- Modal token raw com `<Input readOnly>` + onClick select + aviso "COPIE AGORA"
- Snippet inline `<code>` com JSON `.claude/settings.local.json` pré-formatado pra colar
- Microcopy técnico PT-BR ("Devs ativos hoje", "Quota dia", "Top tools", "Reset diário 00:00 BRT")
- Status pill verde/amarelo/cinza semântico no drill-down (não cor crua, tokens Tailwind)
- AlertDialog destrutivo com descrição explícita do efeito ("acesso a 107 docs / não pode desfazer")

---

## Automation hooks (faz)

- Inertia::defer pra team rows (N×6 queries) + stats_globais (4 agg queries)
- OTel spans `teammcp.token.issue` / `teammcp.token.revoke` / `teammcp.tokens.list` (governança crítica MCP)
- McpToken::gerar() helper canônico (computa sha256_token + raw uma vez)
- McpToken::revogar() helper canônico (revoked_at + revoked_by + audit log Spatie)
- Soft-delete em revoke preserva `mcp_audit_log` queryable
- Permission gate `copiloto.mcp.usage.all` no construtor do Controller

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO chama `prompt()` ou `confirm()` nativos do browser (Constituição UI v2 — shadcn canon)
- ❌ NÃO loga token raw em log/audit/error message (ADR 0081 Tier 0 segredo)
- ❌ NÃO permite reconstruir raw a partir de hash (one-way SHA256)
- ❌ NÃO faz forceDelete em mcp_tokens (preserva audit log — soft-delete via revoke)
- ❌ NÃO carrega TODOS os tokens do business numa lista global (drill-down per-user)
- ❌ NÃO inclui `raw` em atributos de OTel spans (ADR 0081)
- ❌ NÃO permite revogar com tokenId vindo do user input sem confirmar `user_id` pertence ao business da sessão

---

## Restrições Tier 0 IRREVOGÁVEIS

- **Multi-tenant ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)):** toda query `mcp_tokens` filtra pelo `user.business_id == session business_id`. Endpoints novos `listTokens` e `revokeToken` fazem `User::where('id', userId)->where('business_id', $sessionBusinessId)->firstOrFail()` ANTES de tocar token.
- **Reveal-once ([ADR 0057](../../../../../memory/decisions/0057-tela-team-admin-regras-governanca-tokens-mcp.md) §2):** raw mostrado 1× só no `gerarToken` response. `listTokens` retorna SOMENTE metadados (sem `sha256_token`, sem raw).
- **Soft-delete em revoke ([ADR 0057](../../../../../memory/decisions/0057-tela-team-admin-regras-governanca-tokens-mcp.md) §6):** `$token->revogar($byUserId)` grava `revoked_at` + `revoked_by` + log Spatie. Nunca forceDelete.
- **PageHeader canon roxo 295 (Camada Shell — Constituição UI v2):** imutável via ADR. Este PR não toca PageHeader.

---

## Métricas de sucesso (validação Wagner)

- ✅ Wagner clica "N ativos" → modal abre <500ms com lista de tokens do dev
- ✅ Wagner clica "🗑 Revogar" → AlertDialog mostra "Revogar token X de Felipe? Vai cortar acesso..." → confirma → row some da lista + contador na tabela principal cai 1
- ✅ Multi-tenant: Wagner em biz=A NÃO consegue ver tokens de user de biz=B (mesmo se tentar URL manual `/team-mcp/team/{biz_B_user}/tokens` → 404)
- ✅ Status pill: token com `expires_at = now()+5d` aparece amarelo "Expira em 5d"
- ✅ Status pill: token com `revoked_at != null` aparece cinza "Revogado" + botão revoke disabled
- ✅ `last_used_at = null` aparece "Nunca usado" cinza
- ✅ `last_used_ip` aparece mono `192.168.1.5`

---

## Sprint seguinte evoluções planejadas

- G-DESIGN-06: expiration policy default 90d na criação + warning "expira em <7d" no row principal
- G-DESIGN-07: substituir `prompt()` de Export CSV por `<Dialog>` com date-range picker shadcn
- G-DESIGN-08: audit log inline por user (botão "Ver atividade" no row → últimas N calls MCP do user)
- G-DESIGN-09: `aria-label` em botões emoji-only + `aria-describedby` no Input readOnly
- G-DESIGN-10: 2FA / step-up auth em revoke all / regenerate em massa (sinal Vercel breach 2026)
