---
date: 2026-05-06
slot: noite
title: "UI Governance completa em prod + 10 bugs corrigidos + skill criar-modulo documentada"
participants: [W, C]
duration_min: 180
tags: [governance, ui-inertia, bugfix, skill-update, prod-deploy]
---

# 2026-05-06 noite — UI Governance + bugfix marathon

## Trajetória

Sessão sequência da fundação governance (2026-05-05). Wagner pediu:

1. *"confira os navbar se necessario"* — audit topnav.php em 29 módulos (17 sem topnav = OK, UltimatePOS legacy via modifyAdminMenu)
2. *"Dashboard.tsx (React) + PoliciesController + AuditController + DriftAlertsController"*
3. *"sem ação no botão instalar"* — bug rotas Install
4. Wagner abriu `/governance` e viu sequência de erros SQL/PHP
5. *"busque a skill para isso"* — eu tinha que ter usado skill `criar-modulo` ANTES
6. *"faltou até tradução"* — sem `pt/` + `en/`
7. *"documente para vc não errar mais"*

Resposta: UI completa entregue em prod, 10 bugs corrigidos um por um, skill atualizada com lessons learned.

## Entregas

| Categoria | Item | Status |
|---|---|---|
| **Frontend Inertia** | `Dashboard.tsx` (KPIs + ADRs + audit) | ✅ |
| | `Policies.tsx` (toggle inline) | ✅ |
| | `Audit.tsx` (filtros período/actor/endpoint/status) | ✅ |
| | `DriftAlerts.tsx` (runtime scan) | ✅ |
| **Backend** | `DashboardController` + `PoliciesController` + `AuditController` + `DriftAlertsController` | ✅ |
| **Routes** | `/governance` + `/policies` + `/audit` + `/drift` + 3 install hooks | ✅ |
| **Lang** | `pt/governance.php` + `en/governance.php` | ✅ |
| **Topnav** | i18n keys (governance::governance.menu.*) | ✅ |
| **Sidebar** | Grupo GOVERNANÇA reflete trust hierarchy | ✅ |
| **Build** | `public/build-inertia/manifest.json` com 12 entries governance | ✅ |
| **Skill** | `criar-modulo` atualizada com 4 seções novas | ✅ |

## 10 bugs corrigidos (sequência cronológica)

| # | Bug | Causa raiz | Commit |
|---|---|---|---|
| 1 | Botão Install sem ação | URL `install/install` + action `install` (não existe) | `7b1e1b0a` |
| 2 | `Column 'frontmatter_json' not found` | Schema mcp_memory_documents tem `status` direto | `7b1e1b0a` |
| 3 | AuditController quebra | created_at vs `ts` canonical | `7b1e1b0a` |
| 4 | DriftAlerts erro DB | mcp_alertas tem `kind`, não `category` | `7b1e1b0a` |
| 5 | `Undefined array key 0` | superadmin_package formato (key string → array com `name`) | `cb47de32` |
| 6 | Middleware faltando | sem `'authh'` + `'SetSessionData'` | `46fd81d6` |
| 7 | `Column 'status' not found` mcp_skill_approvals | schema usa `decision`; pending = `versions.status='review'` | `f1d61be3` |
| 8 | Translation só em `pt-BR/` | Canonical UltimatePOS é `pt/` + `en/` | `541a4fb2` |
| 9 | Bundles Inertia ausentes | Build local rodado em D:/oimpresso.com main path | `6b3cc65d` |
| 10 | Compliance 8% (era pra ser 80) | Bug aritmético `round(7*10/10)+round(2*5/10)+0` | `5da2fc02` |

## Lição central — skill criar-modulo atualizada

**Antídoto registrado:** *"PRIMEIRO comando ao iniciar criação de módulo: invocar skill `criar-modulo` via tool Skill. Antes de escrever 1 linha de código novo em `Modules/<Nome>/`."*

Adicionadas 4 seções com exemplos ✅/❌ explícitos:
- DataController formato (superadmin_package, user_permissions)
- Middleware stack canonical
- Schemas DB de 5 tabelas mais usadas
- Pasta lang canonical (pt/ não pt-BR/)

Custou ~10 round-trips desnecessários hoje. Próximas sessões evitam.

## Estado em prod

```
https://oimpresso.com/governance        → ✅ Dashboard renderiza (Wagner validado visualmente)
https://oimpresso.com/governance/policies → ✅ rota OK
https://oimpresso.com/governance/audit  → ✅ rota OK
https://oimpresso.com/governance/drift  → ✅ rota OK
https://oimpresso.com/governance/install → ✅ botão Install funciona
```

Sidebar grupo GOVERNANÇA visível com Governança, ADS, Team MCP.

## P0 amanhã (deferred com transparência)

1. **Fase 3.7 renames** — Jana→Jana, PontoWr2→Ponto, MemCofre→SRS, ProjectMgmt→Project + 9 drift controllers (`MODULE-DRIFT-MIGRATION-PLAN.md`). 4-6h sessão dedicada.
2. **ActionGate gradual rollout** em rotas L1+ (modo warn calibração 4 semanas)
3. **PiiRedactor wire-in** nos LLM calls externos (Art. 4 LGPD compliance pleno)
4. **Backfill `mcp_audit_log.actor_slug`** retroativo (script SQL)
5. **Mode warn → strict** após 4 semanas calibração
6. **Refactor MyWorkTool/MyInboxTool** pra usar ActorResolver service (cosmético)

## Aprendizado meta

Wagner alertou: *"por que não usar os conhecimentos internos de como fazer os módulos?"*

A skill `criar-modulo` estava disponível desde o início. Auto-load por description match deveria ter disparado quando criei `Modules/Governance/`. Não disparou — possível porque trabalhei via commands tool em vez de paths semanticamente "criar módulo".

**Pattern futuro:** quando ação implica criar diretório novo em `Modules/`, **manualmente invocar Skill antes do primeiro Write**. Não confiar só no auto-load por description match.

Skills são contratos de proteção contra erros conhecidos. Ignorá-las = pagar o preço dos erros já documentados.
