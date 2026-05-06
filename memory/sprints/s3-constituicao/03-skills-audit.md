# Auditoria das 19 skills atuais

> **Status:** 🔴 ESQUELETO — Sonnet vai pré-classificar quando autorizado. Wagner aprova bloco a bloco.

---

## Tabela de auditoria (a preencher)

| # | Skill | Description começa com "Use ao/quando"? | Disparos 30d (estimado) | Decisão | Justificativa |
|---|---|---|---|---|---|
| 1 | `ads-decision-flow` | ✅ "Use ao trabalhar em Modules/ADS/" | ? | TIER B | trigger por work ADS |
| 2 | `brief-first` | ✅ "ATIVAR PRIMEIRO em toda sessão" | (nova) | TIER A | Daily Brief Tier A |
| 3 | `cockpit-runbook` | ⚠️ "Skill — Gerador de RUNBOOK" | ? | TIER C | só via slash command |
| 4 | `comparativo-do-modulo` | ✅ "ATIVAR quando user pedir" | ? | TIER B | trigger contextual |
| 5 | `copiloto-arch` | ⚠️ "Arquitetura Copiloto" | ? | TIER B | reescrever description |
| 6 | `criar-modulo` | ✅ "Use ao criar novo módulo" | ? | TIER B | trigger contextual |
| 7 | `memoria-recall-flow` | ✅ "Use ao tocar Modules/Copiloto/Services/Memoria" | ? | TIER B | trigger contextual |
| 8 | `memory-sync` | ✅ "ATIVAR após criar/editar arquivo em memory/" | ? | TIER B | trigger contextual |
| 9 | `meta-skill-roi-erp-autonomo` | ✅ "ATIVAR ao criar skill nova" | ? | TIER B | trigger contextual |
| 10 | `migrar-modulo` | ✅ "Use ao mover, renomear..." | ? | TIER B | trigger contextual |
| 11 | `multi-tenant-patterns` | ✅ "Use ao criar Eloquent Model..." | ? | **TIER A (promover)** | Tier 0 — pior bug possível |
| 12 | `oimpresso-cc-watcher-setup` | ✅ "Configura o watcher local" | ? | TIER C | one-time setup |
| 13 | `oimpresso-mcp-first` | ✅ "Skill: oimpresso-mcp-first" (curta — reescrever) | ? | **TIER A (rename → mcp-first)** | sempre antes de Read |
| 14 | `oimpresso-stack` | ✅ "Use ao iniciar trabalho no oimpresso" | ? | TIER C | one-time onboarding |
| 15 | `oimpresso-team-onboarding` | ✅ "Configura ou valida acesso ao MCP" | ? | TIER C | one-time onboarding |
| 16 | `proxmox-docker-host` | ⚠️ "Proxmox + Docker Host — receitas" | ? | TIER C | só via slash command |
| 17 | `publication-policy` | ✅ "Use ANTES de qualquer git push" | ? | TIER B | trigger contextual |
| 18 | `runtime-rules-hostinger-ct100` | ✅ "Use ANTES de SSH no Hostinger" | ? | TIER B | trigger contextual |
| 19 | `sidebar-menu-arch` | ⚠️ "Reconhecer, auditar..." | ? | TIER B | trigger contextual mas description fraca |

## Skills NOVAS a criar

| Skill | Tier | Estado | Justificativa |
|---|---|---|---|
| `mcp-first` (rename `oimpresso-mcp-first`) | A | reescrever description | Tier A always-on |
| `commit-discipline` | A | criar | 1 PR = 1 intent, ≤300 linhas |
| `charter-first` | A dormente | criar | aguarda S4 |
| `ads-route` | A dormente | criar | aguarda S5 |
| `mwart-migrate` | C | spec já existe em S2 dossier, criar SKILL.md | Tier C slash command |

## Resumo decisão (após Wagner aprovar)

| Decisão | Quantidade | Ação |
|---|---|---|
| **TIER A (ativa)** | 3 | brief-first, mcp-first, multi-tenant-patterns |
| **TIER A (dormente)** | 2 | charter-first, ads-route, commit-discipline |
| **TIER B** | ~9 | trigger contextual |
| **TIER C** | ~5 | só via slash command |
| **ARQUIVAR** | 0 | (todas têm uso documentado) |

Total pós-S3: ~19 skills (mantidas) + ~3 skills novas = **~22 skills total**, sendo **5 Tier A (3 ativas + 2 dormentes) + 2 novas**.

⚠️ Nota: nenhuma skill arquivada no S3. Decisões de arquivamento esperam dados reais do `mcp_skill_telemetry` (mín. 30d de telemetria após brief-first ativo).

---

## Notas pra Sonnet preencher (quando autorizado)

- Coluna "Disparos 30d" só preenche se já tiver dados em `mcp_skill_telemetry` — caso contrário marca `?`
- Para cada skill com description fraca (⚠️), proposta de description melhorada começando com "Use ao/quando"
- Wagner aprova decisão por bloco de 5 skills (3 rodadas + 1 final)
- Após aprovação, executar moves físicos (`git mv` se rename, criar/atualizar SKILL.md)
