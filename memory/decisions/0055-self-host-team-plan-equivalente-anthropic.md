# ADR 0055 — Self-host Team plan equivalente ao Anthropic Team/Enterprise

**Status:** Aceito
**Data:** 2026-04-29
**Decidido por:** Wagner [W] — *"remova Anthropic Team plan, construa o que falta"* + *"construa o team plan com as especificações do Anthropic Team plan"*
**Estende:** [ADR 0053](0053-mcp-server-governanca-como-produto.md), [ADR 0054](0054-pacote-enterprise-busca-memoria-evolucao.md)
**Supersede recommendation:** ADR 0054 §5 "híbrido com Team plan" — descartado

---

## Contexto

ADR 0054 recomendou híbrido: nosso MEM-CC-1 self-host + Anthropic Team plan ($25/seat/mês × 5 = R$ [redacted Tier 0]/mês) pelos features de admin (spend caps, analytics dashboard, Cowork sessions).

Wagner descartou o componente pago: *"remova Anthropic Team plan, construa o que falta"*. Razões:

1. **Custo recorrente alto** — R$ [redacted Tier 0]/mês × 12 = R$ [redacted Tier 0]/ano vs build = R$ [redacted Tier 0]/ano
2. **Lock-in** — features Cowork ficam dentro do dashboard Anthropic, não exportável
3. **LGPD/data residency** — tudo em US, fora do nosso DB
4. **Escala** — 5 devs hoje, com upside Felipe/Maíra/Luiz/Eliana (não justifica enterprise pricing)
5. **Stack já capaz** — temos MCP server + MySQL + Inertia/React, falta só wire-up
6. **Diferenciação produto** — Wagner pode vender o "team plan oimpresso" como feature (`/copiloto/admin/governanca` virou produto)

## Decisão

Construir equivalente self-host completo de **todas as features Anthropic Team plan**, integradas ao MCP server `mcp.oimpresso.com` + dashboard `/copiloto/admin/governanca`.

### Especificação features-equivalente

| Feature Anthropic | Self-host oimpresso | Tabelas usadas | Status |
|---|---|---|---|
| Lista de seats (devs ativos) | `/copiloto/admin/team` lista users com tokens MCP ativos | `users`, `mcp_tokens` | 🔲 TODO |
| Add/remove dev (seat management) | Cmd `copiloto:mcp-token:gerar` + `copiloto:mcp-token:revogar` | `mcp_tokens` | ✅ schema |
| Spend limits per-org | `mcp_quotas` row com user_id=NULL = limite global | `mcp_quotas` | ✅ schema |
| Spend limits per-user | `mcp_quotas` row por user_id | `mcp_quotas` | ✅ schema |
| Notificações 50/80/100% | Job diário verifica + envia email/Slack | `mcp_alertas` | ✅ schema, lógica TODO |
| Usage analytics dashboard | `/copiloto/admin/governanca` (já feito) + expansões | `mcp_audit_log`, `mcp_usage_diaria` | ✅ base, expandir |
| DAU/WAU/MAU | Widget no dashboard | `mcp_audit_log` agregado | 🔲 TODO |
| Per-user activity (sessions, tokens, custo) | Tabela no dashboard | `mcp_audit_log` + `mcp_cc_sessions` | parcial |
| Cost CSV export | Endpoint `/copiloto/admin/team/export.csv` | `mcp_audit_log` | 🔲 TODO |
| Centralized billing visibility | Total mensal + breakdown per-user | `mcp_usage_diaria.custo_brl` | 🔲 TODO |
| Spend cap enforcement | Middleware bloqueia call se limite atingido | `McpAuthMiddleware` + `QuotaEnforcer` | 🔲 TODO |
| Skill invocations analytics | Top skills por user/período | `mcp_audit_log.tool_or_resource` | ✅ tem, só dashboard pequeno |
| Cowork sessions analytics (cross-dev visibility) | `mcp_cc_sessions` table (MEM-CC-1) com summary auto | `mcp_cc_sessions`, `mcp_cc_messages` | ✅ schema, falta wire |
| Connector usage tracking | Mesmo log MCP audit | `mcp_audit_log` | ✅ tem |
| Active sessions "right now" | Widget realtime via Reverb | `mcp_cc_sessions WHERE status=active` | 🔲 P2 |

### Arquitetura

```
┌─────────────────────────────────────────────────────────────────┐
│  /copiloto/admin/team  (Inertia React, agora)                  │
│  ┌────────────────────────────────────────────────────────┐    │
│  │ Wagner [W]    | tokens=1 | hoje=R$ [redacted Tier 0] | limite=R$ [redacted Tier 0]/d │    │
│  │ Felipe [F]    | tokens=0 | (revoke)    | (atribuir)   │    │
│  │ Maíra  [M]    | tokens=0 | -           | -            │    │
│  │ Luiz   [L]    | tokens=0 | -           | -            │    │
│  │ Eliana [E]    | tokens=0 | -           | -            │    │
│  └────────────────────────────────────────────────────────┘    │
│  [+ Gerar token novo dev] [Editar limites globais]             │
└─────────────────────────────────────────────────────────────────┘
                          ↓ rota web
┌─────────────────────────────────────────────────────────────────┐
│  TeamController @ Hostinger Laravel app                         │
│  ├─ index()   → lista users + tokens + quotas + uso 7d         │
│  ├─ gerarToken(user)   → McpToken::criar()                     │
│  ├─ revogarToken(id)   → McpToken::revogar()                   │
│  ├─ atualizarQuota(user, brl_dia) → mcp_quotas upsert          │
│  └─ exportCsv(periodo) → CSV mcp_audit_log filtrado            │
└─────────────────────────────────────────────────────────────────┘
                          ↓ DB queries
┌─────────────────────────────────────────────────────────────────┐
│  MySQL Hostinger (mesmo DB do MCP server)                      │
│  - users (Spatie permissions)                                   │
│  - mcp_tokens (Sanctum tokens, last_used_at)                    │
│  - mcp_quotas (user_id NULL=org, +brl/dia, +brl/mes)            │
│  - mcp_audit_log (cada call, tokens, cost_usd, custo_brl)       │
│  - mcp_usage_diaria (agregação cron 23:55)                      │
│  - mcp_alertas (50/80/100% triggered, status, ack)              │
│  - mcp_cc_sessions/messages/blobs (Cowork data, MEM-CC-1)       │
└─────────────────────────────────────────────────────────────────┘
                          ↓ enforcement
┌─────────────────────────────────────────────────────────────────┐
│  McpAuthMiddleware + QuotaEnforcer (CT 100 Proxmox)             │
│  ├─ valida Bearer token                                         │
│  ├─ checa Spatie permission `copiloto.mcp.use`                 │
│  ├─ checa quota: SUM(custo_brl WHERE user_id=X AND DATE=hoje)  │
│  │   < mcp_quotas.brl_dia                                       │
│  ├─ se 50% atingido → INSERT mcp_alertas tipo=50pct             │
│  ├─ se 80% → INSERT alerta + email                              │
│  └─ se 100% → INSERT alerta + 429 Too Many Requests             │
└─────────────────────────────────────────────────────────────────┘
                          ↓ daily 23:55 cron
┌─────────────────────────────────────────────────────────────────┐
│  copiloto:mcp:agregar-usage-diaria                              │
│  - Lê mcp_audit_log do dia                                       │
│  - GROUP BY user_id × business_id                                │
│  - INSERT/UPDATE mcp_usage_diaria                                │
│  - Verifica quotas, enfileira alertas pendentes                  │
└─────────────────────────────────────────────────────────────────┘
```

### Vantagens vs Anthropic Team plan

| Vetor | Anthropic Team plan | Self-host oimpresso |
|---|---|---|
| Custo | R$ [redacted Tier 0]/mês × 12 = R$ [redacted Tier 0]/ano | R$ [redacted Tier 0]/ano (Hostinger já pago) |
| Compliance LGPD | dados em US | DB Brasil (Hostinger) |
| Customização dashboard | ❌ fixo Anthropic | ✅ qualquer KPI customizado |
| Integração com workflow oimpresso | ❌ silo separado | ✅ mesma stack |
| Lock-in | alto (export limitado) | zero (DB próprio) |
| Latência admin actions | ~500ms (US) | <50ms (Hostinger) |
| Suporte | Anthropic SLA | nós |
| Time to ship feature nova | depende roadmap Anthropic | nosso |

### Pré-requisitos pra ativar Sprint A

1. ✅ Schema MCP completo (`mcp_tokens`, `mcp_quotas`, `mcp_audit_log`, `mcp_alertas`)
2. ✅ MCP server vivo `mcp.oimpresso.com`
3. ✅ Audit log gravando 100% calls
4. ✅ Sanctum tokens (Bearer mcp_*)
5. ✅ Spatie permissions `copiloto.mcp.*` seeded
6. ✅ Wagner com Admin#1 ganhou todas
7. 🔲 Schema MEM-CC-1 (já commitado, falta migrate prod)
8. 🔲 Watcher Node + onboarding time (falta implementar)

## Implementação em fases

### Fase 1 — Admin Team page (1d)

- [ ] Migration: ajustar `mcp_quotas` se necessário (já existe schema)
- [ ] `TeamController` Inertia + page `Copiloto/Admin/Team/Index.tsx`
- [ ] Cmd `php artisan copiloto:mcp:gerar-token --user=email@oimpresso.com`
- [ ] Cmd `php artisan copiloto:mcp:revogar-token --id=N`
- [ ] Cmd `php artisan copiloto:mcp:atualizar-quota --user=N --brl-dia=50`

### Fase 2 — Analytics expandido (1d)

- [ ] Widget DAU/WAU/MAU em `/copiloto/admin/governanca`
- [ ] Tabela per-user (sessions, tokens, custo, último login, % quota)
- [ ] Endpoint `/copiloto/admin/team/export.csv` com filtros (periodo, user)
- [ ] Skills/tools breakdown por user

### Fase 3 — Spend cap enforcement (0.5d)

- [ ] `QuotaEnforcer` service: valida `mcp_audit_log SUM` vs `mcp_quotas`
- [ ] Hook em `McpAuthMiddleware` antes de retornar OK
- [ ] Notificações 50/80/100% via mail (config) + dashboard alerta
- [ ] Job diário `copiloto:mcp:verificar-quotas`

### Fase 4 — MEM-CC-1 ativo (Cowork analytics) (1.5d)

- [ ] Migrate `mcp_cc_sessions/messages/blobs` em prod
- [ ] Rota `POST /api/cc/ingest` no MCP server
- [ ] Tool MCP `cc-search` no `OimpressoMcpServer`
- [ ] Watcher Node 80-150 linhas
- [ ] Wagner ingere 1ª session (smoke)
- [ ] Widget "Cowork: top sessões da semana" no dashboard

### Fase 5 — Onboarding time (0.5d)

- [ ] `.mcp.json` no root do repo
- [ ] `MEMORY_TEAM_ONBOARDING.md` (passo a passo Felipe/Maíra/Luiz/Eliana)
- [ ] `.claude/settings.json.example` template
- [ ] Setup script (Windows/Linux/Mac) instala watcher como serviço
- [ ] Wagner roda token-gerar pra cada dev e entrega via Vault

**Total: ~4.5 dias trabalho.**

## Métricas que provam ROI

| Antes | Depois (Sprint A-E completo) |
|---|---|
| 1 dev (Wagner) usando MCP | 5 devs com tokens + quotas |
| Sem visibilidade de gasto per-user | Dashboard real-time + alertas |
| Sem spend cap | Wagner define R$ X/dia/dev, sistema bloqueia |
| Sem analytics consolidado | DAU/WAU/MAU + CSV export |
| MEM-CC-1 schema only | Cowork ativo, cross-dev search |
| Custo Anthropic Team plan | R$ [redacted Tier 0] (vs R$ [redacted Tier 0]/ano) |

**Economia anual: R$ [redacted Tier 0]** (vs ter comprado Team plan).

## Consequências

**Positivas:**
- Zero lock-in
- Customização total de dashboard/relatórios
- LGPD-friendly
- Ferramenta que pode virar **produto**: oimpresso pode vender "MCP team admin" pra outros
- ROI claro: investimento de 4-5 dias compra R$ [redacted Tier 0]/ano + flexibilidade infinita

**Negativas / trade-offs:**
- Manutenção: bugs e features futuras são nosso problema
- Sem suporte oficial Anthropic
- Versão 1 será mais simples que Anthropic (sem Cowork em tempo real, sem inter-agent messaging na 1ª versão)
- Esforço inicial: 4.5 dias

**Mitigação:**
- Foco em features 80/20: spend cap + analytics + cc-search são 80% do valor
- Cowork em tempo real (Pilar A Anthropic) fica P2
- Documentação rica em ADRs evita rework

## Próximos passos

Implementar Fase 1 (admin team page) imediatamente nesta sessão, depois Fase 2-5 em sequência.

## Referências

- ADR 0053 — MCP server governança como produto
- ADR 0054 — Pacote enterprise busca memória + evolução
- Anthropic Cowork docs: https://support.claude.com/en/articles/13455879-use-claude-cowork-on-team-and-enterprise-plans
- Anthropic Team plan: https://support.claude.com/en/articles/9266767-what-is-the-team-plan
- Anthropic admin console + analytics: https://support.claude.com/en/articles/12883420-view-usage-analytics-for-team-and-enterprise-plans
