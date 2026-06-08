---
name: automem-pending
description: BLOQUEADOR — quando user mencionar tópico/módulo OU Edit/Read em path com auto-mem stale pendente migração (ADR 0061), esta skill carrega manifesto AUTO-MEM-PENDING.md, lê auto-mem relacionada, e força decisão "migrar pro git OU deletar". NUNCA ignorar auto-mem sem ler. Ativa quando — (1) Edit/Read em `Modules/{NfeBrasil,Officeimpresso,Copiloto,Cms,Financeiro,Form,PontoWr2}/`; (2) user pergunta sobre cliente RotaLivre / Larissa / biz=4; (3) Bash com SSH pra Hostinger/CT 100 ou composer install em servidor; (4) referência a Asaas/concorrentes/revenue/Delphi/MCP endpoints; (5) DataController hooks UltimatePOS; (6) `session('business')`, sidebar, topnav, datatables.
trust_level: L2
owner: wagner
parent_adr: 0061
tier: B
---

# Skill — Auto-mem pending: reativar conteúdo + decidir destino

Wagner pediu 2026-05-09: *"eu vou esquecer desses arquivos me lembre bote uma triger quando for mecher reative o conteudo separe coretamente"*. Esta skill é o trigger.

## Por que existe

Após lote F4+F5 ([PR #287](https://github.com/wagnerra23/oimpresso.com/pull/287)) sobraram **~52 auto-mems** em `C:\Users\wagne\.claude\projects\D--oimpresso-com\memory\` ainda não migradas pro git. O risco é Wagner mexer num módulo, esquecer da auto-mem relacionada, e deixar info útil apodrecer fora do git (viola [ADR 0061](../../memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)).

## Quando ativa (gatilhos contextuais)

| Trigger | Auto-mem(s) candidata(s) |
|---|---|
| Edit/Read `Modules/NfeBrasil/`, NFC-e, SEFAZ | `project_nfebrasil_estado_2026_05_07`, `runbook_smoke_sefaz_biz1` |
| Edit/Read `Modules/Officeimpresso/`, contrato Delphi, `D:/Programas/WR Comercial/` | `project_officeimpresso_modulo`, `reference_diff_3_7_vs_6_7_officeimpresso`, `reference_delphi_wr_comercial`, `reference_branch_3_7` |
| Edit/Read `Modules/Copiloto/`, recall, MCP tools, RAG | `project_copiloto_estado_2026_04_29`, `reference_rag_estado_arte_2026`, `reference_pesquisa_wagner_2026_04_29` |
| Edit/Read `Modules/Cms/`, landing oimpresso.com, blog | `project_cms_redesign_inertia`, `reference_modules_cms_landing` |
| Edit/Read `Modules/Financeiro/`, `transaction_payment`, contas a pagar/receber | `project_financeiro_onda1`, `reference_financeiro_integracao`, `project_asaas_como_banco` |
| Edit em `App\View\Helpers\Form`, Blade `Form::*` | `project_form_shim_migration` |
| Edit/Read `Modules/PontoWr2/`, Marcacao, Apuracao, CLT | `reference_ponto_evolucao_estado_arte` |
| User menciona ROTA LIVRE / Larissa / `biz=4` | `cliente_rotalivre`, `reference_clientes_ativos` |
| DataController, `modifyAdminMenu`, `user_permissions`, `superadmin_package` | `reference_audit_modulos_datacontroller`, `reference_ultimatepos_integracao` |
| `session('business')`, multi-tenant Eloquent, business_id scope | `project_session_business_model` |
| Sidebar, `app/Services/Menu`, `topnav.php`, sidebar groups | `project_shell_nav_architecture`, `project_sidebar_groups_2026_04_27` |
| Schema DB, `transactions` core, business model | `reference_db_schema` |
| DataTables jQuery init | `reference_datatables_locale` |
| Bash SSH `oimpresso.com` / `u906587222` / `148.135.133.115` | `reference_hostinger_analise`, `reference_hostinger_server`, `reference_hostinger_ssh_credenciais` |
| Hostinger DNS API, hPanel | `reference_hostinger_dns_api`, `reference_hostinger_hpanel`, `reference_hostinger_api_uso_autorizado` |
| `composer install/update` em servidor + deploy quick-sync | `reference_composer_install_obrigatorio_pos_deploy` |
| WordPress `/ajuda/`, plugins WP | `reference_wp_ajuda_fix` |
| CT 100, Proxmox, FrankenPHP, Tailscale, autossh tunnel | `reference_proxmox_acesso_2026_04_29`, `reference_proxmox_credenciais`, `reference_proxmox_empresa`, `reference_ssh_hardening_ct100_2026_04_30`, `project_infra_padrao_empresa` |
| MCP server, `mcp.oimpresso.com` | `reference_mcp_endpoints` |
| Concorrentes, mercado, Capterra | `reference_concorrentes_com_visual` |
| Revenue, pricing, take rate | `reference_revenue_thesis_modulos` |
| Backlog: chat IA contextual flutuante | `ideia_chat_ia_contextual` |

Ver manifesto completo + status de cada uma em [memory/requisitos/Infra/AUTO-MEM-PENDING.md](../../memory/requisitos/Infra/AUTO-MEM-PENDING.md).

## Workflow obrigatório

```
- [ ] 1. Trigger ativou — qual auto-mem(s) candidata(s)?
- [ ] 2. Ler manifesto AUTO-MEM-PENDING.md pra confirmar destino sugerido
- [ ] 3. Read da auto-mem COMPLETA (não pular)
- [ ] 4. Avaliar:
        ✅ Útil agora → usar info pra task atual + propor migrar pro git
        ❌ Stale/superseded → propor deletar (já coberto por ADR/code/skill)
        📝 Útil mas precisa rewrite → propor migrar com rewrite
- [ ] 5. PROPOR ao Wagner em 1 linha: migrar pra <dest>? OU deletar (já coberto por <X>)?
- [ ] 6. Wagner aprova → executar:
        - Migrar: criar/apender em destino git → deletar auto-mem → remover linha MEMORY.md
        - Deletar: rm + remover linha MEMORY.md
- [ ] 7. Atualizar AUTO-MEM-PENDING.md marcando ✅ DONE
- [ ] 8. Commit + PR (skill memory-sync cobre)
```

## Princípios não-negociáveis

1. **Não ignorar auto-mem sem ler** — o "esquece" do Wagner é o anti-pattern que motivou esta skill
2. **1 auto-mem por vez** — `commit-discipline` Tier A, não migrar lote sem aviso
3. **Default agressivo: deletar** — se conteúdo já está em git OU stale → deletar, não migrar
4. **Migrar só se 70%+ é único + útil pro time** — info repetida não vai pro git
5. **PROPOR antes de executar** — Wagner aprova destino + ação. Skill não executa migração silenciosa
6. **Atualizar manifesto** após cada decisão pra manter status vivo

## Anti-padrões (NUNCA fazer)

- ❌ Detectar trigger e silenciosamente continuar com a task — é regressão. Tem que pausar pra reativar.
- ❌ Ler auto-mem só pra confirmar e ignorar — se leu, decide.
- ❌ Migrar 5 auto-mems de uma vez — quebra commit-discipline.
- ❌ Marcar como DONE no manifesto sem executar — manifesto é truth source.
- ❌ Migrar conteúdo já coberto por ADR/skill/código — duplicação desnecessária.

## Refs

- [ADR 0061](../../memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — zero auto-mem privada (mãe)
- [PLANO-MIGRACAO-AUTOMEM.md](../../memory/requisitos/Infra/PLANO-MIGRACAO-AUTOMEM.md) — plano original 7 fases (F1+F4+F5 done; F2+F3+F6 pending)
- [AUTO-MEM-PENDING.md](../../memory/requisitos/Infra/AUTO-MEM-PENDING.md) — manifesto vivo das 52 restantes
- Skill irmã: [`memory-sync`](../memory-sync/SKILL.md) — push pro MCP via webhook GitHub
