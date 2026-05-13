# Índice — Session Logs (`memory/sessions/`)

> **Session logs são narrativos do trabalho diário.** Estado VIVO está em tools MCP (`brief-fetch`, `cycles-active`, `tasks-list`). Handoffs append-only entre sessões vivem em `memory/handoffs/`. **Use este índice pra encontrar precedente histórico** — "como Wagner resolveu X em dia Y?", "qual sessão originou ADR Z?".

**O que NÃO procurar aqui:**
- Estado atual de tasks → `mcp__oimpresso__tasks-list`
- Estado do cycle ativo → `mcp__oimpresso__cycles-active`
- Decisões canônicas → `memory/decisions/*.md` (ADRs)
- Handoff entre sessões → `memory/handoffs/YYYY-MM-DD-HHMM-*.md`

**O que procurar aqui:**
- Contexto histórico de bugs/marcos ("primeiro deploy Inertia v3", "MWART hotfix marathon")
- Decisões tomadas durante uma sessão (depois viraram ADR)
- Trabalho não-trivial que merece referência futura (recovery patterns, smoke pre-flight)

---

## Tabela cronológica reversa

| Data | Slug | Linhas | Resumo |
|------|------|-------:|--------|
| 2026-05-13 | [arq-estrutura-estado-da-arte](2026-05-13-arq-estrutura-estado-da-arte.md) | 237 | Arquiteto Adversarial — estruturar ferramenta `estado-da-arte` (skill+subagent vs alternativas); v1 derrubada, v2 condicional |
| 2026-05-13 | [agents-canonicos-meta-degradacao](2026-05-13-agents-canonicos-meta-degradacao.md) | 161 | 2 agents canônicos criados + meta-aprendizado sobre degradação Claude |
| 2026-05-12 | [fsm-pipeline-canon-live-prod-50prs](2026-05-12-fsm-pipeline-canon-live-prod-50prs.md) | 196 | **Marco FSM Pipeline LIVE prod biz=1 (50 PRs em ~10h)** |
| 2026-05-12 | [pipeline-vendas-discovery-7-gaps](2026-05-12-pipeline-vendas-discovery-7-gaps.md) | 148 | Pipeline Vendas: discovery 7 GAPs + spec executável |
| 2026-05-12 | [1700-wave-ab-inventory-comvis-v0](2026-05-12-1700-wave-ab-inventory-comvis-v0.md) | 132 | Wave A consolidação + Wave B ComVis V0 + bloqueios mapeados |
| 2026-05-12 | [jana-pro-brief-prod-funcional](2026-05-12-jana-pro-brief-prod-funcional.md) | 91 | JANA Pro Brief funcional em produção (manhã) |
| 2026-05-12 | [omnichannel-wave-paralelizacao-11-prs](2026-05-12-omnichannel-wave-paralelizacao-11-prs.md) | 67 | Omnichannel Wave 1+2 paralelização (11 PRs) |
| 2026-05-11 | [jana-pro-foundation-concierge](2026-05-11-jana-pro-foundation-concierge.md) | 103 | JANA Pro Sprint A foundation + modo Concierge MVP + pegadinha NTFS |
| 2026-05-11 | [fix-ads-dual-brain-drift](2026-05-11-fix-ads-dual-brain-drift.md) | 80 | Fix schema drift `mcp_dual_brain_decisions` |
| 2026-05-10 | [claude-massive-arquivos-cv-sprint](2026-05-10-claude-massive-arquivos-cv-sprint.md) | 177 | Sessão massiva Modules/Arquivos backbone + CV Sprint 1 (Claude solo + worktrees paralelos) |
| 2026-05-10 | [tarde-d1-d4-prefix-helper-paralelo](2026-05-10-tarde-d1-d4-prefix-helper-paralelo.md) | 93 | D1-D4 pre-fix + helper sessões paralelas + audit warming |
| 2026-05-10 | [consolidacao-massiva-auto-mem](2026-05-10-consolidacao-massiva-auto-mem.md) | 80 | Consolidação massiva auto-mem |
| 2026-05-10 | [noite-pr475-ci-guards-sqlite](2026-05-10-noite-pr475-ci-guards-sqlite.md) | 62 | PR #475 CI Modules Pest guards SQLite |
| 2026-05-09 | [autonomous-handoff](2026-05-09-autonomous-handoff.md) | 764 | **Master report — execução autônoma 16h Opus 4.7** (maior sessão registrada) |
| 2026-05-09 | [smoke-sefaz-preflight](2026-05-09-smoke-sefaz-preflight.md) | 305 | SEFAZ smoke pre-flight biz=1 — emitir NFC-e homologação SC ponta-a-ponta |
| 2026-05-09 | [recuperacao-gold-pivot-manifestacao-destinatario](2026-05-09-recuperacao-gold-pivot-manifestacao-destinatario.md) | 173 | Recuperação Gold + pivot manifestação destinatário |
| 2026-05-09 | [pr349-mwart-audit-fix-23-prs](2026-05-09-pr349-mwart-audit-fix-23-prs.md) | 142 | 23 PRs, 2 telas em prod, processo MWART enforced |
| 2026-05-09 | [pipeline-legacy-migration-completo](2026-05-09-pipeline-legacy-migration-completo.md) | 100 | Pipeline legacy migration ponta-a-ponta (Fases 0-6) |
| 2026-05-08 | [madrugada-painel-fiscal-guard-biz1](2026-05-08-madrugada-painel-fiscal-guard-biz1.md) | 69 | Painel fiscal completo + guard CI biz=1 + 3 ADRs canon (8 PRs) |
| 2026-05-08 | [madrugada-inter-direto](2026-05-08-madrugada-inter-direto.md) | 101 | Inter direto (4 PRs Open Finance) |
| 2026-05-08 | [tarde-centrifugo-recovery-pr268-destravado](2026-05-08-tarde-centrifugo-recovery-pr268-destravado.md) | 72 | Recovery Centrifugo/MCP + destrava PR #268 (WhatsApp TemplatePicker) |
| 2026-05-07 | [project-mwart-discovery-fase0](2026-05-07-project-mwart-discovery-fase0.md) | 123 | Project MWART Discovery (Fase 0) — PIVOTADA mesmo dia |
| 2026-05-07 | [revisao-cycle-01-rollover-cycle-02](2026-05-07-revisao-cycle-01-rollover-cycle-02.md) | 124 | Revisão CYCLE-01 + abertura CYCLE-02 |
| 2026-05-07 | [mwart-hotfix-marathon-cockpit-feedback](2026-05-07-mwart-hotfix-marathon-cockpit-feedback.md) | 109 | MWART hotfix marathon + Cockpit feedback canônico |
| 2026-05-07 | [noite-audit-claude-desktop-nfe](2026-05-07-noite-audit-claude-desktop-nfe.md) | 101 | Audit Claude Desktop aplicada + Goal #7 fechado |
| 2026-05-07 | [whatsapp-cockpit-ui-build-hostinger](2026-05-07-whatsapp-cockpit-ui-build-hostinger.md) | 74 | Whatsapp UI estado-da-arte + build na Hostinger |
| 2026-05-07 | [brief-audit-cycle02-health-check](2026-05-07-brief-audit-cycle02-health-check.md) | 59 | BRIEF audit + GUARD-02 + Hostinger git recovery (CYCLE-02 W20) |
| 2026-05-06 | [pr-9-tabela-rename-copiloto-jana](2026-05-06-pr-9-tabela-rename-copiloto-jana.md) | 118 | PR-9 Fase 3.7: rename DB `copiloto_*` → `jana_*` |
| 2026-05-06 | [fase-3-7-pr1-drift-controllers](2026-05-06-fase-3-7-pr1-drift-controllers.md) | 101 | Fase 3.7 PR-1: drift controllers |
| 2026-05-06 | [governance-ui-completa-bugfix-marathon](2026-05-06-governance-ui-completa-bugfix-marathon.md) | 98 | UI Governance + bugfix marathon |
| 2026-05-05 | [noite-trust-tiers-architecture-enforcement](2026-05-05-noite-trust-tiers-architecture-enforcement.md) | 127 | Audit cascata + Trust Tiers + Architecture + Enforcement |
| 2026-05-05 | [triagem-roadmap-mcp-audit](2026-05-05-triagem-roadmap-mcp-audit.md) | 108 | Triagem + Roadmap + Auditoria MCP |
| 2026-05-05 | [noite-constituicao-7-camadas](2026-05-05-noite-constituicao-7-camadas.md) | 101 | Constituição em 10 artigos (rascunho 7 camadas) |
| 2026-05-05 | [noite-meta-skill-constituicao-1-frase](2026-05-05-noite-meta-skill-constituicao-1-frase.md) | 93 | A constituição é uma frase (meta-skill) |
| 2026-05-05 | [tarde-mcp-tasks-bootstrap](2026-05-05-tarde-mcp-tasks-bootstrap.md) | 46 | Bootstrap retroativo MCP tasks (ADS Skills) + ADR 0077 |
| 2026-05-04 | [ragas-baseline-infra](2026-05-04-ragas-baseline-infra.md) | 362 | **RAGAS Sprint 7 infraestrutura (US-COPI-081)** |
| 2026-05-04 | [sprint9-retrieval-diagnostico](2026-05-04-sprint9-retrieval-diagnostico.md) | 81 | Sprint 9: Retrieval diagnóstico + fixes |
| 2026-05-04 | [webhook-mcp-auto-pull](2026-05-04-webhook-mcp-auto-pull.md) | 93 | Webhook MCP sync auto-pull + scheduler 5min |
| 2026-05-04 | [auditoria-regras-concorrentes-adr0069](2026-05-04-auditoria-regras-concorrentes-adr0069.md) | 87 | Auditoria regras concorrentes + HOW_TO_ASK_CLAUDE 2026 |
| 2026-05-04 | [cockpit-sidebar-light-faxina-appshell](2026-05-04-cockpit-sidebar-light-faxina-appshell.md) | 68 | Cockpit sidebar light + faxina AppShell legado |
| 2026-04-30 | [cycle02-opcao-c2-infra-validada](2026-04-30-cycle02-opcao-c2-infra-validada.md) | 215 | Cycle 02 Opção C2 infra validada + ADR 0061 zero auto-mem |
| 2026-04-30 | [consulta-adr-0052](2026-04-30-consulta-adr-0052.md) | 31 | Consulta ADR 0052 ContextoNegocio (sessão de leitura — ⚠️ candidato arquivar) |
| 2026-04-29 | [mcp-server-bootstrap](2026-04-29-mcp-server-bootstrap.md) | 300 | **MCP server bootstrap em CT 100** |
| 2026-04-29 | [sprint-memoria-completa](2026-04-29-sprint-memoria-completa.md) | 241 | Sprint memória completa (8 entregas em 1 dia) |
| 2026-04-29 | [pacote-enterprise-memoria-evolucao](2026-04-29-pacote-enterprise-memoria-evolucao.md) | 223 | Pacote enterprise busca de memória + evolução automática |
| 2026-04-29 | [mcp-team-self-host](2026-04-29-mcp-team-self-host.md) | 83 | MCP Team self-host + memória cross-source |
| 2026-04-28 | [meilisearch-vaultwarden](2026-04-28-meilisearch-vaultwarden.md) | 262 | Meilisearch CT 100 + Vaultwarden + Inventário infra |
| 2026-04-28 | [reverb-docker-host](2026-04-28-reverb-docker-host.md) | 127 | Reverb + Docker-host CT 100 (Proxmox) ao vivo |
| 2026-04-28 | [design-prototype-chat-erp](2026-04-28-design-prototype-chat-erp.md) | 109 | Protótipo UX integrada (Chat + Tarefas + AppShell) |
| 2026-04-28 | [fix-spatie-backup-artisan-crash](2026-04-28-fix-spatie-backup-artisan-crash.md) | 108 | fix: spatie/laravel-backup quebra artisan |
| 2026-04-27 | [sprints-5-6-mcp-claude-desktop-revisao](2026-04-27-sprints-5-6-mcp-claude-desktop-revisao.md) | 175 | Sprints 5-6 + revisão Capterra + Claude Desktop |
| 2026-04-27 | [cockpit-deprecates-old-layout](2026-04-27-cockpit-deprecates-old-layout.md) | 116 | Cockpit consolidado, conhecimento antigo depreciado |
| 2026-04-27 | [promocao-6-7-bootstrap-para-main](2026-04-27-promocao-6-7-bootstrap-para-main.md) | 86 | Promoção `6.7-bootstrap` → `main` + cleanup ADR 0024 |
| 2026-04-27 | [prototipo-chat-cockpit](2026-04-27-prototipo-chat-cockpit.md) | 63 | Protótipo "Chat Cockpit" + ADR 0039 |
| 2026-04-26 | [sprint1-stack-canonica](2026-04-26-sprint1-stack-canonica.md) | 157 | Sprint 1 stack-alvo IA canônica + Meilisearch |
| 2026-04-26 | [fix-hero-hidratacao-prematura](2026-04-26-fix-hero-hidratacao-prematura.md) | 96 | Fix Hero hidratação prematura via CMS |
| 2026-04-26 | [deploy-hero-fix-e-conflitos-memoria](2026-04-26-deploy-hero-fix-e-conflitos-memoria.md) | 94 | Deploy fix do Hero + resolução de conflitos de memória |
| 2026-04-26 | [copiloto-testes-merge](2026-04-26-copiloto-testes-merge.md) | 74 | Copiloto: testes + merge (Sessão 14) |
| 2026-04-25 | [inertia-v3-execucao](2026-04-25-inertia-v3-execucao.md) | 158 | Execução: Upgrade Inertia v2 → v3 |
| 2026-04-25 | [redesign-cms-meta-5mi](2026-04-25-redesign-cms-meta-5mi.md) | 145 | Redesign site público + estratégia R$5mi/ano |
| 2026-04-25 | [maratona-financeiro](2026-04-25-maratona-financeiro.md) | 100 | Sessão maratona — manhã/tarde Financeiro |
| 2026-04-25 | [inertia-v3-handoff](2026-04-25-inertia-v3-handoff.md) | 70 | Handoff Inertia v3 upgrade |
| 2026-04-25 | [financeiro-mvp-progresso](2026-04-25-financeiro-mvp-progresso.md) | 55 | Financeiro MVP — progresso parcial (sessão pausada 95% tokens) |
| 2026-04-24 | [consolidacao-final](2026-04-24-consolidacao-final.md) | 139 | Consolidação final dia |
| 2026-04-24 | [form-shim-bool-attrs-fix](2026-04-24-form-shim-bool-attrs-fix.md) | 102 | Fix do shim Form:: que travava /sells/create |
| 2026-04-24 | [sells-labels-and-timezone](2026-04-24-sells-labels-and-timezone.md) | 92 | /sells labels + timezone end-to-end |
| 2026-04-24 | [rotalivre-venda-liberada](2026-04-24-rotalivre-venda-liberada.md) | 77 | ROTA LIVRE: venda liberada (permissão location faltando) |
| 2026-04-24 | [revert-format-date-timezone](2026-04-24-revert-format-date-timezone.md) | 51 | Revert fix `format_date` (regressão ROTA LIVRE shift +3h) |
| 2026-04-23 | [session-13](2026-04-23-session-13.md) | 127 | Sessão 13 — 2026-04-23 |
| 2026-04-23 | [deploy-plan-L13](2026-04-23-deploy-plan-L13.md) | 164 | Deploy Plan — Laravel 9.51 → 13.6 |
| 2026-04-23 | [session-12](2026-04-23-session-12.md) | 51 | Upgrade Laravel 10 → 11 (depois 11 → 13 — ⚠️ stale, decisão revisada) |
| 2026-04-23 | [session-11](2026-04-23-session-11.md) | 61 | Sessão 11 (genérica — ⚠️ candidato arquivar) |
| 2026-04-20 | [session-08](2026-04-20-session-08.md) | 335 | Sessão 08 — 2026-04-20 |
| 2026-04-19 | [session-07](2026-04-19-session-07.md) | 168 | Sessão 07 — 2026-04-19 |
| 2026-04-19 | [session-06](2026-04-19-session-06.md) | 169 | Sessão 06 — 2026-04-19 |
| 2026-04-19 | [session-05](2026-04-19-session-05.md) | 141 | Sessão 05 — 2026-04-19 |
| 2026-04-19 | [session-04](2026-04-19-session-04.md) | 103 | Sessão 04 — 2026-04-19 |
| 2026-04-19 | [session-03](2026-04-19-session-03.md) | 119 | Sessão 03 — 2026-04-19 |
| 2026-04-18 | [session-02](2026-04-18-session-02.md) | 300 | Sessão 02 — 2026-04-18 (continuação) |
| 2026-04-18 | [session-01](2026-04-18-session-01.md) | 147 | Sessão 01 — 2026-04-18 (gênese do projeto Laravel) |

---

## ⚠️ Candidatos a arquivar (Wagner decide)

Critérios: <50 linhas + sem decisão durável capturada, ou duplicação semântica, ou info stale.

1. **`2026-04-30-consulta-adr-0052.md`** (31 linhas) — Sessão pura de leitura/consulta ("5 min, leitura"). Não capturou decisão nova; só sumarizou ADR 0052 já canônico. ADR 0052 existe em `memory/decisions/` — leia direto a ADR.
2. **`2026-04-23-session-12.md`** (51 linhas) — Decidiu migrar Laravel 10 → 11. Decisão **stale**: dia seguinte (session-13 + deploy-plan-L13) re-pivotou pra Laravel 13.6 direto. Conteúdo confunde se buscado fora de contexto.
3. **`2026-04-24-revert-format-date-timezone.md`** (51 linhas) — Já consolidado em [ADR 0066](../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md) + memória `cliente_rotalivre.md`. Conteúdo histórico capturado em fonte canônica.
4. **`2026-04-23-session-11.md`** (61 linhas) — Nome genérico ("Sessão 11"), sem slug semântico. Conteúdo provavelmente consolidado em `2026-04-23-deploy-plan-L13.md` (mesmo dia, mais completo).
5. **`2026-05-05-tarde-mcp-tasks-bootstrap.md`** (46 linhas) — Bootstrap retroativo já consumado; ADR 0077 (mencionado) é a fonte durável; sessão narra o processo que não vai repetir.

**Importante:** este índice NÃO deleta nada. Wagner pode revisar e mover pra `memory/sessions/_archive/` ou deletar via PR dedicado.

---

## Sessions notáveis (>200 linhas + decisão durável)

| Arquivo | Linhas | Por quê é notável |
|---------|------:|--------------------|
| [2026-05-09-autonomous-handoff.md](2026-05-09-autonomous-handoff.md) | **764** | Master report execução autônoma 16h Opus 4.7 — maior sessão registrada, frontmatter completo, capturou padrão de autonomia |
| [2026-05-04-ragas-baseline-infra.md](2026-05-04-ragas-baseline-infra.md) | 362 | RAGAS Sprint 7 infra (US-COPI-081) com frontmatter tags+related_us+related_adr — referência de "como escrever session log canônico" |
| [2026-05-09-smoke-sefaz-preflight.md](2026-05-09-smoke-sefaz-preflight.md) | 305 | Smoke ponta-a-ponta SEFAZ-SC NFC-e biz=1 — receita executável reutilizável pra próximos smokes fiscais |
| [2026-05-12-fsm-pipeline-canon-live-prod-50prs.md](2026-05-12-fsm-pipeline-canon-live-prod-50prs.md) | 196 | Marco FSM Pipeline LIVE prod biz=1 (50 PRs em ~10h) — capturou padrão paralelização N agents |
| [2026-04-29-mcp-server-bootstrap.md](2026-04-29-mcp-server-bootstrap.md) | 300 | Bootstrap MCP server CT 100 — sessão fundadora da infraestrutura MCP |

---

## Estatísticas

- **Total de arquivos:** 79 session logs (+ este `_INDEX.md` + `README.md`)
- **Período coberto:** 2026-04-18 → 2026-05-13 (26 dias corridos)
- **Total de linhas:** ~10.300
- **Média:** 130 linhas/sessão (mediana ~100; outlier 764 em `autonomous-handoff`)
- **Distribuição por categoria (heurística por slug):**
  - MWART/Migração Blade→React: 8 sessões
  - Infraestrutura (MCP, CT 100, Centrifugo, Meilisearch, Reverb): 9 sessões
  - Financeiro/NFe/SEFAZ: 7 sessões
  - JANA Pro / Brief / IA / Memória: 8 sessões
  - Cockpit / UI / Design / Inertia: 7 sessões
  - FSM / Pipeline Vendas: 3 sessões
  - WhatsApp / Omnichannel: 4 sessões
  - ADRs / Constituição / Governance: 7 sessões
  - Cycles / Roadmap / Triagem: 5 sessões
  - ROTA LIVRE / Vestuario fixes: 4 sessões
  - Auto-mem / Consolidação: 3 sessões
  - Genéricas "sessão NN" (gênese projeto): 10 sessões (2026-04-18 a 2026-04-23)
  - Outros: ~4 sessões

---

**Última atualização:** 2026-05-13 (Agent G5 — Auditoria Jana §5 P0)
