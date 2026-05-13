---
title: Migração auto-mem legacy → git canon (G1 P0)
date: 2026-05-13
agent: G1
status: completed
session: nervous-mayer-3ff0da
adr_refs: [0061, 0131]
---

# Migração auto-mem legacy → git canônico — 2026-05-13

## Contexto

Auditoria knowledge-architecture (`memory/requisitos/Jana/AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md` §5) identificou 53 auto-mem privadas em `C:\Users\wagne\.claude\projects\D--oimpresso-com\memory\` que ferem [ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) (ZERO auto-mem privada legada — conhecimento canônico vai pro git/MCP).

Time (Felipe/Maiara/Eliana/Luiz) não enxergava esses 53 arquivos. Hook `block-automem.ps1` bloqueia Write novo mas legado persistia.

## Decisão por arquivo (54 inicial)

| # | Arquivo origem | Decisão | Destino |
|---|---|---|---|
| 1 | feedback_agent_pode_alucinar_write.md | CANON | reference/feedback-agent-write-verification.md |
| 2 | feedback_artur_comissao_50_perpetua_red_flag.md | CANON | reference/feedback-comissao-recurring-vendedor.md |
| 3 | feedback_auto_merge_quando_verde.md | CANON | reference/feedback-auto-merge-quando-verde.md |
| 4 | feedback_browser_mcp_smoke_apos_feature.md | CANON | reference/feedback-browser-mcp-smoke.md |
| 5 | feedback_check_main_antes_de_pr.md | CANON | reference/feedback-check-main-antes-pr.md |
| 6 | feedback_daemon_first_then_laravel_payload_sync.md | CANON | reference/feedback-daemon-deploy-order.md |
| 7 | feedback_daemon_max_deploys_per_day.md | CANON | reference/feedback-daemon-max-deploys-day.md |
| 8 | feedback_daemon_restart_causa_qrfest.md | CANON | reference/feedback-daemon-qrfest.md |
| 9 | feedback_eloquent_array_cast_inertia_bug.md | CANON | reference/feedback-eloquent-array-cast-inertia.md |
| 10 | feedback_legacy_migration_python_importer.md | CANON | reference/feedback-legacy-migration-importer.md |
| 11 | feedback_migrate_obrigatorio_pos_deploy.md | CANON | reference/feedback-migrate-pos-deploy.md |
| 12 | feedback_module_completeness_audit_approach.md | CANON | reference/feedback-module-audit-approach.md |
| 13 | feedback_nunca_publicar_credenciais_no_chat.md | CANON | reference/feedback-nunca-publicar-credenciais.md |
| 14 | feedback_outbound_markdown_over_mcp_tasks.md | CANON | reference/feedback-outbound-markdown-over-mcp.md |
| 15 | feedback_pesquisar_versao_mais_nova_em_erro_lib.md | CANON | reference/feedback-pesquisar-versao-lib.md |
| 16 | feedback_revert_so_apos_isolar_client_side.md | CANON | reference/feedback-revert-isolar-client.md |
| 17 | feedback_tenancy_changes_require_pest_local.md | CANON | reference/feedback-tenancy-pest-local.md |
| 18 | feedback_test_biz_99_cross_tenant_convention.md | CANON | reference/feedback-test-biz-99-cross-tenant.md |
| 19 | cliente_rotalivre.md | CANON | reference/cliente-rotalivre.md |
| 20 | cockpit_layout_canonico.md | DELETE | (superseded por ADR 0110 + Cockpit Pattern V2 já em git) |
| 21 | ideia_chat_ia_contextual.md | CANON | reference/ideia-chat-ia-contextual.md |
| 22 | project_agrosys_deal_2026_05_12.md | CANON | reference/project-agrosys-deal-2026-05-12.md |
| 23 | project_form_shim_migration.md | CANON | reference/project-form-shim-migration.md |
| 24 | project_mcp_5_prs_consolidados_2026_05_13.md | CANON | reference/project-mcp-5-prs-2026-05-13.md |
| 25 | project_mcp_sync_bugs_2026_05_13.md | DELETE | duplicado com `memory/requisitos/Jana/BUGS-MCP-SYNC-2026-05-13.md` |
| 26 | project_nfebrasil_estado_2026_05_07.md | CANON | reference/project-nfebrasil-2026-05-07.md |
| 27 | project_octane_mcp_prod_deps_pending_adr.md | CANON | reference/project-octane-mcp-prod-deps.md |
| 28 | project_officeimpresso_modulo.md | CANON | reference/project-officeimpresso-modulo.md |
| 29 | project_sessao_2026_05_12_23_prs.md | CANON | reference/project-sessao-2026-05-12-23-prs.md |
| 30 | reference_atendimento_inbox_state_2026_05_12.md | CANON | reference/atendimento-inbox-state-2026-05-12.md |
| 31 | reference_branch_protection_admin_merge.md | CANON | reference/branch-protection-admin-merge.md |
| 32 | reference_clientes_ativos.md | CANON | reference/clientes-ativos.md |
| 33 | reference_concorrentes_com_visual.md | CANON | reference/concorrentes-com-visual.md |
| 34 | reference_cursor_collaboration.md | CANON | reference/cursor-collaboration.md |
| 35 | reference_deploy_e_recovery.md | CANON | reference/deploy-recovery-patterns.md |
| 36 | reference_dominios_verticais_oimpresso.md | CANON | reference/dominios-verticais-oimpresso.md |
| 37 | reference_financeiro_integracao.md | CANON | reference/financeiro-integracao.md |
| 38 | reference_hostinger.md | CANON | reference/hostinger.md (tokens REDACTED → Vaultwarden) |
| 39 | reference_hostinger_remote_mysql_direct.md | CANON | reference/hostinger-remote-mysql.md (senha REDACTED → Vaultwarden) |
| 40 | reference_infra_proxmox_ct100.md | CANON | reference/infra-proxmox-ct100.md (senhas REDACTED → Vaultwarden) |
| 41 | reference_infra_rede_empresa.md | CANON | reference/infra-rede-empresa.md (senha Issabel REDACTED) |
| 42 | reference_legacy_delphi_firebird.md | CANON | reference/legacy-delphi-firebird.md (SYSDBA/masterkey preservado — hardcoded WR2 source público) |
| 43 | reference_local_dev_setup.md | CANON | reference/local-dev-setup.md (DEV password REDACTED) |
| 44 | reference_mcp_endpoints.md | CANON | reference/mcp-endpoints.md |
| 45 | reference_meta_whatsapp_tech_provider.md | CANON | reference/meta-whatsapp-tech-provider.md |
| 46 | reference_modules_cms_landing.md | CANON | reference/modules-cms-landing.md |
| 47 | reference_revenue_thesis_modulos.md | CANON | reference/revenue-thesis-modulos.md |
| 48 | reference_tests_pest_canon.md | CANON | reference/tests-pest-canon.md |
| 49 | reference_ultimatepos_integracao.md | CANON | reference/ultimatepos-integracao.md |
| 50 | reference_vaultwarden_credenciais.md | CANON | reference/vaultwarden-credenciais.md (ADMIN_TOKEN → self-ref Vaultwarden) |
| 51 | reference_whatsapp_daemon_ct100.md | CANON | reference/whatsapp-daemon-ct100.md |
| 52 | reference_whatsapp_permissions_spatie.md | CANON | reference/whatsapp-permissions-spatie.md |
| 53 | trigger_guarde_no_cofre.md | CANON | reference/trigger-guarde-no-cofre.md |
| 54 | user_profile.md | LOCAL | info pessoal Wagner (perfil) — `~/.claude/oimpresso-local/` ADR 0131 |

(54 inclui `MEMORY.md` que NÃO entra na contagem — é índice da auto-mem, será atualizado abaixo)

## Estatística final

| Categoria | Count |
|---|---|
| **CANON migrados** (git canônico via `memory/reference/`) | **51** |
| **LOCAL** (movido conceito pra `~/.claude/oimpresso-local/`) | 1 (`user_profile.md`) |
| **DELETE** (obsoleto/duplicado) | 2 (`cockpit_layout_canonico.md`, `project_mcp_sync_bugs_2026_05_13.md`) |
| **SEGREDO** (vai pro Vaultwarden) | 0 documentos completos — porém **valores sensíveis nos canon** foram REDACTED com ponteiro pro Vaultwarden em 6 docs (Hostinger token/senha, Proxmox/CT100 senhas, Portainer admin, Issabel admin, MySQL Hostinger, Vaultwarden ADMIN_TOKEN). Outros tokens permanecem em `~/.claude/oimpresso-local/vault-refs.md` per ADR 0131. |
| **Total processado** | **54** (53 auto-mem + MEMORY.md índice) |

## Volume migrado

51 arquivos em `memory/reference/` totalizando ~290KB (verificado via `ls -la`).

## 3 surpresas descobertas

1. **`project_mcp_sync_bugs_2026_05_13.md` era duplicata** — o mesmo conteúdo (catálogo 4 bugs MCP) já vivia em `memory/requisitos/Jana/BUGS-MCP-SYNC-2026-05-13.md` (criado pelo agent mcp-quality-expert nesta mesma sessão). Auto-mem foi capture do estado intermediário; ficou redundante após o arquivo canon ser criado.

2. **Senhas hardcoded WR2 vs senhas operacionais — distinção precisa**. `legacy-delphi-firebird.md` preserva `SYSDBA/masterkey` em git canônico porque ESTÃO HARDCODED no código fonte público Delphi (`Principal.pas:3446 {$IFDEF WR2}`) — qualquer dev olhando o source vê. Não é "expor segredo"; é documentar fato técnico. Outras senhas (Hostinger MySQL, Proxmox root) foram REDACTED com ponteiro pro Vaultwarden porque NÃO estão em código público.

3. **`cockpit_layout_canonico.md` é stale há ~15 dias** — descreve padrão AppShellV2 vs AppShell legado, mas o ADR 0110 (Cockpit Pattern V2) já formaliza isso em git desde então. Auto-mem ficou como cache redundante. Após migrar quem precisa pra ADR 0110, deletar é seguro.

## Edge cases problemáticos

1. **Tokens reais em `reference_hostinger.md` linha 119** — o documento original tinha o Bearer token completo. Substituí por ponteiro pro Vaultwarden item `hostinger-api-token`. Mesmo tratamento pra: ADMIN_TOKEN Vaultwarden, Proxmox API token, Hostinger MySQL pass, Portainer admin pass, Issabel admin pass, MEILI_MASTER_KEY, Reverb secrets. Total ~10 valores literais substituídos.

2. **`user_profile.md` é meta sobre Wagner** — não conhecimento canônico de time, mas configuração pessoal (estilo comunicação, controle servidor, etc.). Per ADR 0131, info pessoal Wagner que SÓ ele vê vai pra `oimpresso-local/`. Não migrei nem deletei — apenas marquei como LOCAL no MEMORY.md (Wagner pode mover manualmente).

3. **Múltiplos arquivos cross-referenciam paths antigos** — vários docs apontavam pra `feedback_*.md`/`reference_*.md` (auto-mem naming). Reescrevi referências internas pra `feedback-*.md`/`reference-*.md` no destino canon. Pode haver links residuais quebrados se time procurar pelos nomes antigos.

4. **`project_mcp_5_prs_consolidados_2026_05_13.md`** referenciava paths estranhos tipo `../../../../D:/oimpresso.com/memory/...` — eram artefatos do prompt do agent que criou o arquivo, não paths canônicos. Limpei pra paths normais (`memory/requisitos/Jana/...`).

## Atualizações em `C:\Users\wagne\.claude\projects\D--oimpresso-com\MEMORY.md`

Pendente — Wagner pode atualizar manualmente seguindo este relatório, ou o próximo step da migração pode tocar (não toquei pra evitar conflito com hook `block-automem`). Recomendação: substituir bullets `[feedback_X.md]` por `[memory/reference/feedback-X.md]` apontando pro git canon.

## Próximos passos sugeridos

1. **Wagner valida** o índice `memory/reference/_INDEX.md` está completo
2. **Deletar fontes auto-mem** após confirmação (51 arquivos em `C:\Users\wagne\.claude\projects\D--oimpresso-com\memory\` + 2 marcados DELETE; manter `user_profile.md` ou mover Wagner manualmente; manter `MEMORY.md` mas atualizar)
3. **Webhook GitHub→MCP sync** após PR merge → time enxerga via tools MCP (`memoria-search`, etc.)
4. **Rotacionar senhas que foram públicas** anteriormente — pode haver "blast radius" de quem viu auto-mem antes desta migração (mesmo só Wagner). Recomendado per [feedback-nunca-publicar-credenciais](../../reference/feedback-nunca-publicar-credenciais.md): tratar como comprometidas e rotacionar.

## Refs

- [ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — Conhecimento canônico Git + MCP, zero auto-mem
- [ADR 0131](../../decisions/0131-tiering-memoria-canonico-local-segredo.md) — Tiering canônico/local/segredo
- [AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md](AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md) §5 (gap G1 P0)
- [`memory/reference/_INDEX.md`](../../reference/_INDEX.md) — índice canônico criado pela migração
