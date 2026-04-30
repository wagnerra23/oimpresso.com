# Plano de migração das 82 auto-mems → git/MCP (ADR 0061)

> Wagner 2026-04-30: *"todos as regras devem ir para team, por favor revise todas"*

## Inventário

| Prefixo | Quantidade | Destino majoritário |
|---|---|---|
| **reference_** | 37 | `memory/requisitos/{Mod}/RUNBOOK-tema.md` ou `INFRA.md` |
| **project_** | 21 | `memory/requisitos/{Mod}/SPEC.md` ou `CHANGELOG.md` ou ADR |
| **feedback_** | 14 | `memory/decisions/NNNN-slug.md` (decisões/preferências) |
| **preference_** | 4 | `memory/05-preferences.md` (apenda) |
| **user_/trigger_/ideia_/cockpit_/cliente_** | 5 | espalhado |
| **MEMORY.md (índice)** | 1 | mantém local como pointer pro git |

**Total: 82 arquivos · 436KB · todos cobertos pelo hook `block-automem.ps1` agora.**

## Categorias prioritárias (alta urgência migração)

### 🔴 P1 — Receitas técnicas reproduzíveis (Felipe/Maíra precisam)

| Auto-mem atual | Migra pra |
|---|---|
| `reference_ssh_hardening_ct100_2026_04_30.md` | ✅ `memory/requisitos/Infra/RUNBOOK-ssh-hardening-ct.md` (FEITO) |
| `reference_proxmox_acesso_2026_04_29.md` | `memory/requisitos/Infra/RUNBOOK-proxmox-ct-bootstrap.md` |
| `reference_hostinger_analise.md` (warm-up SSH) | `memory/requisitos/Infra/RUNBOOK-hostinger-ssh-flaky.md` |
| `reference_hostinger_ssh_credenciais.md` | `INFRA.md` (apenda + Vaultwarden ref) |
| `reference_hostinger_dns_api.md` | `memory/requisitos/Infra/RUNBOOK-hostinger-dns-api.md` |
| `reference_proxmox_credenciais.md` | `INFRA.md` apenda (sem senhas — só refs Vaultwarden) |
| `reference_router_empresa_port_forwards.md` | `memory/requisitos/Infra/RUNBOOK-tplink-port-forward.md` |
| `reference_router_empresa_dhcp.md` | `memory/requisitos/Infra/INVENTARIO-rede-empresa.md` |
| `reference_painel_kinghost.md` | `INFRA.md` apenda |
| `reference_hostinger_hpanel.md` | `INFRA.md` apenda |
| `reference_central_voip_inventario.md` | `memory/requisitos/Infra/INVENTARIO-voip.md` |
| `reference_db_schema.md` | `memory/requisitos/Database/SCHEMA-overview.md` |
| `reference_clientes_ativos.md` | `memory/requisitos/Negocio/CLIENTES-ATIVOS.md` |
| `reference_quick_sync_quebrada.md` | já era — atualizar `INFRA.md` workflows |
| `reference_hostinger_server.md` | duplica `reference_hostinger_ssh_credenciais` — merge |
| `reference_local_dev_setup.md` | `INFRA.md` apenda |

### 🟠 P2 — Estado de cada módulo (precisa pra Felipe abrir e rodar)

| Auto-mem | Migra pra |
|---|---|
| `project_modulo_copiloto.md` | `memory/requisitos/Copiloto/CHANGELOG.md` |
| `project_estado_2026_04_27.md` | `memory/sessions/2026-04-27-estado.md` |
| `project_copiloto_estado_2026_04_28.md` | `memory/sessions/2026-04-28-copiloto-estado.md` |
| `project_copiloto_estado_2026_04_29.md` | `memory/sessions/2026-04-29-copiloto-estado.md` |
| `project_meta_5mi_ano.md` | `memory/11-metas-negocio.md` (já existe — ADR 0022) |
| `project_evolutionagent_spec.md` | `memory/requisitos/Copiloto/SPEC-evolutionagent.md` |
| `project_inertia_v3_upgrade.md` | já é ADR 0023 — auto-mem dup |
| `project_financeiro_onda1.md` | `memory/requisitos/Financeiro/CHANGELOG.md` |
| `project_modulos_promovidos_2026_04_24.md` | `memory/sessions/2026-04-24-modulos-promovidos.md` |
| `project_officeimpresso_modulo.md` | `memory/requisitos/Officeimpresso/CHANGELOG.md` |
| `project_roadmap_milestones.md` | `memory/07-roadmap.md` (já existe?) |
| `project_roadmap_a_plus.md` | `memory/07-roadmap.md` apenda |
| `project_roadmap_fiscal.md` | `memory/07-roadmap.md` apenda |
| `project_cms_redesign_inertia.md` | `memory/requisitos/Cms/CHANGELOG.md` |
| `project_shell_nav_architecture.md` | `memory/03-architecture.md` apenda |
| `project_sidebar_groups_2026_04_27.md` | `memory/sessions/2026-04-27-sidebar-groups.md` |
| `project_form_shim_migration.md` | `memory/requisitos/Form/RUNBOOK-shim-migration.md` |
| `project_current_branch.md` | `INFRA.md` (já é git aware) |
| `project_modulo_copiloto.md` | já mencionado |
| `project_infra_padrao_empresa.md` | já é ADR 0042 — auto-mem dup |
| `project_adr_0026_posicionamento.md` | já é ADR 0026 — dup |

### 🟡 P3 — Decisões de produto / preferências

| Auto-mem | Migra pra |
|---|---|
| `feedback_claude_supervisiona_decisoes.md` | já é ADR 0040 — auto-mem dup |
| `feedback_processo_canonico_claude_team_2026_04_30.md` | já é ADR 0061 (esta!) |
| `feedback_vizra_reverb_deprecated_2026_04_30.md` | já são ADRs 0048+0058 |
| `feedback_topnav_i18n_pattern.md` | `memory/04-conventions.md` apenda |
| `feedback_delphi_contrato_imutavel.md` | `memory/requisitos/Officeimpresso/quirks.md` |
| `feedback_testes_com_nova_feature.md` | `memory/04-conventions.md` apenda |
| `feedback_hostinger_ipv4.md` | `INFRA.md` apenda |
| `feedback_blade_double_escape_bug.md` | `memory/04-conventions.md` apenda (NÃO fazer) |
| `feedback_carbon_timezone_bug.md` | `memory/04-conventions.md` apenda |
| `feedback_format_now_local_e_default_datetime.md` | `memory/04-conventions.md` apenda |
| `feedback_form_shim_bool_attrs.md` | `memory/04-conventions.md` apenda |
| `feedback_adr_separados_por_categoria.md` | já é convenção ADR 0028 — dup |
| `feedback_pattern_install_modulos.md` | `memory/04-conventions.md` apenda + ADR 0023/0024 |
| `feedback_remote_ccr_bug.md` | `memory/04-conventions.md` (NÃO fazer) |
| `cliente_rotalivre.md` | `memory/requisitos/Negocio/CLIENTE-ROTA-LIVRE.md` (sem PII real, ref) |

### 🟢 P4 — Preferências Wagner (já tem destino claro)

| Auto-mem | Migra pra |
|---|---|
| `preference_modulos_prioridade.md` | `memory/05-preferences.md` apenda |
| `preference_drive_browser.md` | `memory/05-preferences.md` apenda |
| `preference_persistent_layouts.md` | `memory/04-conventions.md` apenda |
| `preference_cache_estado_preservado.md` | `memory/04-conventions.md` apenda |
| `user_profile.md` | `memory/00-user-profile.md` (já existe!) |
| `trigger_guarde_no_cofre.md` | `memory/04-conventions.md` apenda |
| `ideia_chat_ia_contextual.md` | `memory/07-roadmap.md` apenda |
| `cockpit_aceito.md` | já era — ADR Chat Cockpit |

## Plano de execução

| Fase | Conteúdo | Esforço | Quando |
|---|---|---|---|
| **F1 — agora ✅** | Hook `block-automem` + skill atualizada + ADR 0061 + RUNBOOK ssh-hardening | feito | 30-abr |
| **F2 — próx sessão** | Migrar P1 (16 references infra) | 2h | 01-mai |
| **F3** | Migrar P2 (21 project_* — bulk pra sessions/changelogs) | 2h | 01-mai |
| **F4** | Migrar P3 (14 feedback) — maioria vira lines em conventions | 1h | 02-mai |
| **F5** | Migrar P4 (8 preference) — append em files já existentes | 30min | 02-mai |
| **F6** | Cleanup: marcar TODAS auto-mems com header `⛔ DEPRECATED — ver memory/path/`. Manter por 90 dias depois deletar. | 1h | 05-mai |
| **F7** | Validar: `decisions-search` retorna info que era auto-mem | 30min | 05-mai |

**Total: ~7h pra migrar 82 auto-mems** (não bloqueante — pode fazer em paralelo com Cycle 02 Opção C2 workers).

## Diferimento explicito

Wagner pediu também 5h pra Cycle 02 Opção C2 (Laravel 2 connections, models switch, autossh tunnel, workers container, A/B test embedder).

**Ordem proposta:**
1. **Esta sessão** ✅ — ADR 0061 + hook + skill + RUNBOOK ssh + plano (este doc)
2. **Próxima sessão** — Cycle 02 Opção C2 workers (5h) — destrava `copiloto:eval` no CT
3. **Sessões subsequentes** — F2-F5 migração auto-mems (5.5h distribuída)
4. **Cycle 03** — F6-F7 cleanup e validação

Ambos podem rodar em paralelo. Wagner valida prioridade.
