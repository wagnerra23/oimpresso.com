# Manifesto AUTO-MEM-PENDING — auto-mems aguardando migração

> **Tipo:** manifesto vivo (atualizar a cada decisão)
> **Skill auto-trigger:** [`automem-pending`](../../../.claude/skills/automem-pending/SKILL.md) — Tier B
> **Plano original:** [PLANO-MIGRACAO-AUTOMEM.md](PLANO-MIGRACAO-AUTOMEM.md) — F1+F4+F5 done, F2+F3+F6 pending
> **ADR mãe:** [0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — zero auto-mem privada
> **Última atualização:** 2026-05-09

## Resumo

| Bucket | Quantidade | Status |
|---|---|---|
| **A. Migrar pra `memory/requisitos/<Mod>/`** (módulos com SPEC/CHANGELOG) | 14 | 🟡 Pending |
| **B. Migrar pra `memory/requisitos/Infra/`** (RUNBOOK/INVENTARIO) | 16 | 🟡 Pending |
| **C. Migrar pra `memory/requisitos/Negocio/`** (CRM/mercado/strategy) | 4 | 🟡 Pending |
| **D. Apender 1-2 linhas em arquivo existente** (04-conventions, etc) | 5 | 🟡 Pending |
| **E. Deletar (já coberto por ADR/skill/code)** | 2 | 🟡 Pending |
| **F. Local-only legítimo (recurso máquina/credencial/preferência pessoal)** | 8 | ✅ Manter local |
| **G. Já deletadas no lote 2026-05-09 tarde** | 4 | ✅ Done (riscadas abaixo) |
| **TOTAL ativos pendentes** | **49** | — |

> Quando esta skill ativa, **não migrar tudo de uma vez**. 1 auto-mem por trigger contextual, decisão consciente.

---

## A. Por módulo (Edit/Read em `Modules/<X>/`)

### A1. NfeBrasil

| Auto-mem | Status | Destino sugerido |
|---|---|---|
| `project_nfebrasil_estado_2026_05_07.md` | 🟡 | `memory/requisitos/NfeBrasil/CHANGELOG.md` (apenda entry com link pra commits) |
| `runbook_smoke_sefaz_biz1.md` | 🟡 | `memory/requisitos/NfeBrasil/RUNBOOK-smoke-sefaz-biz1.md` (mover inteiro) |

**Trigger:** Edit/Read em `Modules/NfeBrasil/` ou rota `/nfe-brasil/*` ou termo "NFC-e"/"SEFAZ".

### A2. Officeimpresso (Connector + Delphi)

| Auto-mem | Status | Destino sugerido |
|---|---|---|
| `project_officeimpresso_modulo.md` | 🟡 | `memory/requisitos/Officeimpresso/CHANGELOG.md` |
| `reference_diff_3_7_vs_6_7_officeimpresso.md` | 🟡 | `memory/requisitos/Officeimpresso/DIFF-3-7-vs-6-7.md` (histórico) |
| `reference_delphi_wr_comercial.md` | 🟡 | `memory/requisitos/Officeimpresso/RUNBOOK-delphi-wr-comercial.md` (receita Python pra ler wc.db) |
| `reference_branch_3_7.md` | 🟡 | `memory/requisitos/Officeimpresso/CHANGELOG.md` (apenda decisão pendente Connector untracked) |

**Trigger:** Edit/Read em `Modules/Officeimpresso/` ou path `D:/Programas/WR Comercial/` ou termo "Delphi"/"connector"/"licenca".

### A3. Copiloto

| Auto-mem | Status | Destino sugerido |
|---|---|---|
| ~~`project_copiloto_estado_2026_04_29.md`~~ | ✅ DONE 2026-05-09 | DELETED — snapshot temporal sem valor residual |
| `reference_rag_estado_arte_2026.md` | 🟡 | `memory/requisitos/Copiloto/RAG-ESTADO-ARTE.md` (pesquisa profunda, citar papers) |
| ~~`reference_pesquisa_wagner_2026_04_29.md`~~ | ✅ DONE 2026-05-09 | DELETED — virou ADRs 0048/0049/0050/0036 |

**Trigger:** Edit/Read em `Modules/Jana/` ou termo "recall"/"hybrid"/"meilisearch hybrid embedder"/"HyDE".

### A4. Cms

| Auto-mem | Status | Destino sugerido |
|---|---|---|
| ~~`project_cms_redesign_inertia.md`~~ | ✅ DONE 2026-05-09 | DELETED — snapshot temporal sem valor residual |
| `reference_modules_cms_landing.md` | 🟡 | `memory/requisitos/Cms/SPEC.md` (header "Tabelas + Rotas") |

**Trigger:** Edit/Read em `Modules/Cms/` ou rota `/c/*` ou termo "landing"/"blog"/"cms_pages".

### A5. Financeiro

| Auto-mem | Status | Destino sugerido |
|---|---|---|
| ~~`project_financeiro_onda1.md`~~ | ✅ DONE 2026-05-09 | DELETED — snapshot temporal sem valor residual |
| `reference_financeiro_integracao.md` | 🟡 | `memory/requisitos/Financeiro/SPEC.md` (header "Integração UltimatePOS") |
| `project_asaas_como_banco.md` | 🟡 | `memory/requisitos/Financeiro/RUNBOOK-asaas-como-banco.md` |

**Trigger:** Edit/Read em `Modules/Financeiro/` ou termo "Asaas"/"transaction_payment"/"contas a pagar"/"contas a receber".

### A6. Form (shim spatie)

| Auto-mem | Status | Destino sugerido |
|---|---|---|
| `project_form_shim_migration.md` | 🟡 | `memory/requisitos/Form/RUNBOOK-shim-migration.md` |

**Trigger:** Edit em `App\View\Helpers\Form` ou Blade `Form::open/text/select/...` em qualquer view.

### A7. PontoWr2

| Auto-mem | Status | Destino sugerido |
|---|---|---|
| `reference_ponto_evolucao_estado_arte.md` | 🟡 | `memory/requisitos/PontoWr2/CAPTERRA-FICHA.md` (apenda 8 capacidades + 10 moves) ou inline em `SPEC.md` |

**Trigger:** Edit/Read em `Modules/Ponto/` ou termo "Marcacao"/"Apuracao"/"CLT"/"banco horas".

---

## B. Infra (`memory/requisitos/Infra/`)

### B1. UltimatePOS core

| Auto-mem | Status | Destino sugerido |
|---|---|---|
| `reference_audit_modulos_datacontroller.md` | 🟡 | `memory/requisitos/Infra/AUDIT-datacontroller.md` (receita) |
| `reference_ultimatepos_integracao.md` | 🟡 | `memory/requisitos/Infra/RUNBOOK-ultimatepos-integracao.md` |
| `reference_db_schema.md` | 🟡 | `memory/requisitos/Database/SCHEMA-overview.md` (criar dir Database/) |
| `project_session_business_model.md` | 🟡 | DELETE — `multi-tenant-patterns` skill cobre |
| `project_shell_nav_architecture.md` | 🟡 | DELETE — `sidebar-menu-arch` skill cobre |
| `project_sidebar_groups_2026_04_27.md` | 🟡 | DELETE — Sidebar groups Superadmin é histórico, code é fonte |
| `reference_datatables_locale.md` | 🟡 | apender 1 linha em `04-conventions.md` §UI |

**Trigger:** Edit em controller chamando `Inertia::render` ou `LegacyMenuAdapter` ou `topnav.php` ou DataController.

### B2. Hostinger

| Auto-mem | Status | Destino sugerido |
|---|---|---|
| `reference_hostinger_analise.md` | 🟡 | `memory/requisitos/Infra/RUNBOOK-hostinger-ssh.md` (consolidar 3) |
| `reference_hostinger_server.md` | 🟡 | merge no RUNBOOK acima |
| `reference_hostinger_ssh_credenciais.md` | 🟡 | merge no RUNBOOK acima (sem senha — só path key) |
| `reference_hostinger_dns_api.md` | 🟡 | `memory/requisitos/Infra/RUNBOOK-hostinger-dns-api.md` |
| `reference_hostinger_api_uso_autorizado.md` | 🟡 | apender em `INFRA.md` ou criar `INFRA.md` se não existir |
| `reference_hostinger_hpanel.md` | 🟡 | DELETE — credencial pessoal Wagner, fica local OU mover stub pra `INFRA.md` |
| `reference_composer_install_obrigatorio_pos_deploy.md` | 🟡 | `memory/requisitos/Infra/RUNBOOK-deploy-hostinger.md` |
| `reference_wp_ajuda_fix.md` | 🟡 | `memory/requisitos/Infra/RUNBOOK-wp-ajuda-fix.md` (gotcha PHP 8.4) |

**Trigger:** Bash com `ssh.*148.135.133.115` ou `u906587222` ou `oimpresso.com` ou `composer install` em SSH.

### B3. CT 100 / Proxmox

| Auto-mem | Status | Destino sugerido |
|---|---|---|
| `reference_proxmox_acesso_2026_04_29.md` | 🟡 | `memory/requisitos/Infra/RUNBOOK-ct100-bootstrap.md` |
| `reference_proxmox_credenciais.md` | 🟡 | DELETE — credencial sensível, fica local |
| `reference_proxmox_empresa.md` | 🟡 | apender em `INFRA.md` (recursos hardware empresa) |
| `reference_ssh_hardening_ct100_2026_04_30.md` | 🟡 | já existe `RUNBOOK-ssh-hardening-ct.md`? Verificar; se sim, DELETE |
| `project_infra_padrao_empresa.md` | 🟡 | DELETE — virou ADR 0042 |
| `reference_mcp_endpoints.md` | 🟡 | `memory/requisitos/Infra/RUNBOOK-mcp-endpoints.md` (ADR 0053 cobre arquitetura, mas receita re-deploy é única) |

**Trigger:** Bash com `tailscale ssh ct100` ou `100.99.207.66` ou `192.168.0.50` ou termo "FrankenPHP"/"docker-compose" no contexto CT 100.

### B4. Empresa local (rede física, não soft)

| Auto-mem | Status | Destino sugerido |
|---|---|---|
| `reference_router_empresa_dhcp.md` | 🟡 | `memory/requisitos/Infra/INVENTARIO-rede-empresa.md` |
| `reference_router_empresa_port_forwards.md` | 🟡 | merge no INVENTARIO acima |
| `reference_central_voip_inventario.md` | 🟡 | merge no INVENTARIO acima |
| `reference_central_voip_issabel.md` | 🟡 | merge no INVENTARIO acima |
| `reference_painel_kinghost.md` | 🟡 | DELETE — credencial pessoal (não usar pra oimpresso, é DNS de wr2.com.br) |

**Trigger:** Bash com IP `192.168.0.*` ou termo "router empresa"/"VOIP"/"Issabel"/"KingHost".

---

## C. Negócio (`memory/requisitos/Negocio/`)

| Auto-mem | Status | Destino sugerido |
|---|---|---|
| `cliente_rotalivre.md` | 🟡 | `memory/requisitos/Negocio/CLIENTE-ROTA-LIVRE.md` (sem PII real, ref) |
| `reference_clientes_ativos.md` | 🟡 | `memory/requisitos/Negocio/CLIENTES-ATIVOS.md` |
| `reference_concorrentes_com_visual.md` | 🟡 | `memory/requisitos/Negocio/CONCORRENTES.md` (já há `memory/comparativos/`?) |
| `reference_revenue_thesis_modulos.md` | 🟡 | `memory/requisitos/Negocio/REVENUE-THESIS.md` |

**Trigger:** Termo "ROTA LIVRE"/"Larissa"/"biz=4"/"clientes"/"concorrentes"/"revenue"/"pricing"/"take rate".

---

## D. Apender 1-2 linhas em arquivo existente

| Auto-mem | Status | Destino |
|---|---|---|
| `reference_datatables_locale.md` | 🟡 | `04-conventions.md` §UI: 1 linha sobre `language: { url: asset('locale/datatables/pt-BR.json') }` |
| `ideia_chat_ia_contextual.md` | 🟡 | criar task no MCP via `tasks-create` (ideia backlog) — DELETE auto-mem após |

---

## E. Deletar (já coberto)

| Auto-mem | Razão |
|---|---|
| `cockpit_layout_canonico.md` | ADR 0039 + ADR 0008 + Cockpit Pattern V2 (ADR 0110) — verificar conteúdo, provável dup |
| `reference_pesquisa_wagner_2026_04_29.md` | Virou ADRs 0048/0049/0050 |
| `project_session_business_model.md` | Skill `multi-tenant-patterns` cobre |
| `project_shell_nav_architecture.md` | Skill `sidebar-menu-arch` cobre |
| `project_infra_padrao_empresa.md` | Virou ADR 0042 |
| `project_sidebar_groups_2026_04_27.md` | Histórico PR #32 — git é fonte |
| `reference_quick_sync_quebrada.md` | Não existe mais (deletado em lote anterior?) — confirmar |

---

## F. Local-only legítimo (NÃO migrar — fica em auto-mem)

Estes arquivos têm motivo legítimo pra ficar fora do git:

| Auto-mem | Por quê fica local |
|---|---|
| `user_profile.md` | Perfil Wagner |
| `trigger_guarde_no_cofre.md` | Frase-gatilho específica do Wagner |
| `reference_local_dev_setup.md` | Path Windows `D:\oimpresso.com` + Herd config local |
| `reference_cursor_collaboration.md` | Wagner usa Cursor paralelo, time todo não |
| `reference_hostinger_hpanel.md` | OAuth Google **wagnerra@gmail.com** pessoal |
| `reference_painel_kinghost.md` | Credencial KingHost pessoal |
| `reference_proxmox_credenciais.md` | Senha Proxmox |
| `reference_vaultwarden_credenciais.md` | ADMIN_TOKEN Vaultwarden |

---

## Workflow quando skill ativa

1. **Identificar trigger** — qual contexto disparou (módulo, termo, comando)?
2. **Consultar este manifesto** — qual auto-mem(s) candidata(s)?
3. **Read da auto-mem completa** — ver conteúdo na íntegra
4. **Avaliar 4 opções:**
   - ✅ Migrar pra `<destino sugerido>` — apender ou criar arquivo no git
   - ❌ Deletar — conteúdo já coberto por <ADR/skill/código>
   - 📝 Migrar com rewrite — útil mas precisa polir antes
   - ⏸ Adiar — não é o momento, marcar `🔒 SKIP` neste manifesto pra não disparar de novo
5. **PROPOR ao Wagner** — 1 linha clara
6. **Wagner aprova** → executar
7. **Atualizar este manifesto** — mudar 🟡 → ✅ DONE com data
8. **Atualizar MEMORY.md** — remover linha indexada do arquivo deletado/migrado
9. **Commit + PR** (skill `memory-sync` cobre)

## Pegadinhas

- **Não migrar 5 de uma vez** — `commit-discipline` Tier A. 1 PR = 1 intent.
- **Default agressivo: deletar** — Conteúdo já em ADR/code/skill = duplicação.
- **Ler o manifesto antes da auto-mem** — economiza Read. Manifesto já tem destino + razão.
- **Atualizar manifesto após cada decisão** — manifesto é truth source. Stale aqui = skill silenciosa pra sempre.

---

**Próxima sessão:** quando skill ativar, executar passos 1-9 acima. Não tentar processar todas em batch.
