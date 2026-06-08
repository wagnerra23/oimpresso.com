# Relatório de backlinks ADR — 2026-05-13

> Gerado por `php artisan jana:backlinks:sweep` (Gap G5 P1 — auditoria 2026-05-13).
> Frequência sugerida: daily 06:30 BRT via cron (após `jana:health-check`).

## Sumário

| Métrica | Valor |
|---|---|
| Total de ADRs | 139 |
| Orfãs (sem inbound link) | 18 |
| Broken links | 11 |
| Assimétricas | 21 |
| SPEC↔ADR cross-refs não-fechados | 162 |

## Top 5 ADRs mais centrais (inbound)

| # | Título | Inbound |
|---|---|---|
| 94 | Constituição v2 Oimpresso — 7 camadas + 8 princípios duros | 39 |
| 93 | Multi-tenant isolation by default — Tier 0, IRREVOGÁVEL | 30 |
| 53 | ADR 0053 — MCP server da empresa: governança como produto, não overhead | 29 |
| 35 | ADR 0035 — Stack-alvo de IA do Copiloto: declaração canônica | 23 |
| 70 | Jira-style task management no MCP — CURRENT.md/TASKS.md removidos | 19 |

## Broken links (11)

| De | Para | Tipo | Título origem |
|---|---|---|---|
| 18 | 2026 | supersedes | ADR 0018 — Log de acesso do desktop via triggers MySQL (passivo) |
| 18 | 0 | supersedes | ADR 0018 — Log de acesso do desktop via triggers MySQL (passivo) |
| 27 | 12 | related | ADR 0027 — Gestão de memória do projeto: papéis claros por função |
| 28 | 12 | related | ADR 0028 — ADRs com numeração monotônica e formato Nygard |
| 16 | 12 | inline | Plano de Otimização e Roadmap PontoWR2 |
| 27 | 12 | inline | ADR 0027 — Gestão de memória do projeto: papéis claros por função |
| 28 | 12 | inline | ADR 0028 — ADRs com numeração monotônica e formato Nygard |
| 79 | 82 | inline | Constituição do Oimpresso ERP — 10 artigos supremos sobre 7 camadas de governança |
| 79 | 83 | inline | Constituição do Oimpresso ERP — 10 artigos supremos sobre 7 camadas de governança |
| 80 | 82 | inline | Trust Tiers operacional + Architecture & Scope + audit findings v1.1.0 |
| 80 | 83 | inline | Trust Tiers operacional + Architecture & Scope + audit findings v1.1.0 |

## Assimétricas (21)

| De | Para | Tipo | Falta inverso em |
|---|---|---|---|
| 8 | 39 | superseded_by | ADR 39 (faltando `supersedes:[8]`) |
| 10 | 27 | superseded_by | ADR 27 (faltando `supersedes:[10]`) |
| 10 | 53 | superseded_by | ADR 53 (faltando `supersedes:[10]`) |
| 11 | 2 | supersedes | ADR 2 (faltando `superseded_by:[11]`) |
| 11 | 1 | supersedes | ADR 1 (faltando `superseded_by:[11]`) |
| 31 | 36 | superseded_by | ADR 36 (faltando `supersedes:[31]`) |
| 33 | 36 | superseded_by | ADR 36 (faltando `supersedes:[33]`) |
| 42 | 58 | superseded_by | ADR 58 (faltando `supersedes:[42]`) |
| 48 | 35 | supersedes | ADR 35 (faltando `superseded_by:[48]`) |
| 53 | 36 | supersedes | ADR 36 (faltando `superseded_by:[53]`) |
| 55 | 54 | supersedes | ADR 54 (faltando `superseded_by:[55]`) |
| 60 | 42 | supersedes | ADR 42 (faltando `superseded_by:[60]`) |
| 60 | 44 | supersedes | ADR 44 (faltando `superseded_by:[60]`) |
| 64 | 55 | supersedes | ADR 55 (faltando `superseded_by:[64]`) |
| 64 | 57 | supersedes | ADR 57 (faltando `superseded_by:[64]`) |
| 64 | 59 | supersedes | ADR 59 (faltando `superseded_by:[64]`) |
| 64 | 61 | supersedes | ADR 61 (faltando `superseded_by:[64]`) |
| 92 | 88 | supersedes | ADR 88 (faltando `superseded_by:[92]`) |
| 94 | 78 | supersedes | ADR 78 (faltando `superseded_by:[94]`) |
| 117 | 96 | supersedes | ADR 96 (faltando `superseded_by:[117]`) |
| 144 | 70 | supersedes | ADR 70 (faltando `superseded_by:[144]`) |

## Orfãs aceitas sem inbound (18)

| # | Slug | Título |
|---|---|---|
| 9 | 0009-prototipos-html-puro | ADR 0009 — Protótipos visuais em HTML + Tailwind + Chart.js (não React) |
| 29 | 0029-padrao-inertia-react-ultimatepos | ADR 0024 — Padrão Inertia + React + UltimatePOS pra módulos novos |
| 38 | 0038-promocao-6-7-bootstrap-para-main | ADR 0038 — Promoção de `6.7-bootstrap` para `main` como branch principal |
| 45 | 0045-hostinger-dns-api-endpoint-canonico | ADR 0045 — Endpoint canônico da Hostinger DNS API V1 |
| 90 | 0090-nfe-replace-gradual-app-services | NFe replace gradual: app/Services → Modules/NfeBrasil |
| 92 | 0092-tabela-rename-copiloto-para-jana | Tabela rename copiloto_* → jana_* (PR-9 da Fase 3.7 — renumerada de 0090 pra 0092 por conflito monotônico ADR 0028) |
| 97 | 0097-brief-model-gpt4o-mini-supersede-parcial-0091 | BRIEF generator usa gpt-4o-mini em vez de Sonnet (supersede parcial ADR 0091) |
| 98 | 0098-build-inertia-hostinger-pos-pull | build:inertia roda na Hostinger pós git-pull (substitui GH Actions runner) |
| 102 | 0102-s6-charter-capterra-postmortem-s7-backlog | Sprint S6 Charter-Capterra postmortem + S7 backlog (5 itens, ~24h) |
| 116 | 0116-pivot-gold-manifestacao-destinatario-emenda-0115 | Pivot caso Gold — Manifestação do Destinatário (DFe) substitui escopo de emissão NF-e 55 (emenda 0115) |
| 120 | 0120-reverse-supersession-metadata-housekeeping | Supersession metadata housekeeping — fix 0079 + documenta drift de direção forward |
| 134 | 0134-tasks-create-respeita-spec-placeholders | tasks-create respeita placeholders em SPEC.md (regex headers + bullets) |
| 137 | 0137-modules-oficinaauto-qualificada | Modules/OficinaAuto qualificada — sinal confirmado por 2 de 4 candidatos OfficeImpresso saudáveis |
| 140 | 0140-jana-pro-produto-comercial-saas | JANA Pro — Produto comercial SaaS de IA pra PMEs BR (upsell sobre oimpresso, R$ 149-499/mês) |
| 141 | 0141-skill-migracao-blade-react | Skill `migracao-blade-react` — orquestrador Cowork→Inertia preservando paridade Blade legacy |
| 142 | 0142-notas-internas-sinal-treino-jana | Notas internas como sinal de treino pra Jana — slash commands + 3 tabelas + parser |
| 143 | 0143-fsm-pipeline-live-prod-marco-2026-05-12 | FSM Pipeline Canônico LIVE em prod biz=1 — marco 2026-05-12 (40+ PRs em ~10h) |
| 144 | 0144-tasks-db-canonico-spec-template | TaskRegistry — DB é canon de estado vivo, SPEC.md é template descritivo |

## SPEC↔ADR cross-refs não-fechados (162)

_SPEC menciona ADR mas ADR não cita o SPEC (heurística leve — revisão humana):_

| SPEC | ADR | Título ADR |
|---|---|---|
| Arquivos/SPEC.md | 93 | Multi-tenant isolation by default — Tier 0, IRREVOGÁVEL |
| Auditoria/SPEC.md | 93 | Multi-tenant isolation by default — Tier 0, IRREVOGÁVEL |
| Auditoria/SPEC.md | 104 | Processo MWART canônico — único caminho de migração Blade→Inertia |
| Auditoria/SPEC.md | 94 | Constituição v2 Oimpresso — 7 camadas + 8 princípios duros |
| Auditoria/SPEC.md | 107 | Emendation ADR 0104 — Visual comparison gate obrigatório em F3 (loop design supervisionado) |
| Auditoria/SPEC.md | 101 | Tests SEMPRE business_id=1 (Wagner) — nunca cliente real, com guard CI |
| Auditoria/SPEC.md | 123 | Modules/Arquivos — backbone DMS (todo arquivo anexado entra aqui) |
| Autopecas/SPEC.md | 105 | Cliente como sinal + guiar sem mandar (3 graus de regulação) |
| Autopecas/SPEC.md | 11 | ADR 0011 — Alinhamento com o padrãa Jana (UltimatePOS) |
| Autopecas/SPEC.md | 93 | Multi-tenant isolation by default — Tier 0, IRREVOGÁVEL |
| Autopecas/SPEC.md | 35 | ADR 0035 — Stack-alvo de IA do Copiloto: declaração canônica |
| Autopecas/SPEC.md | 94 | Constituição v2 Oimpresso — 7 camadas + 8 princípios duros |
| Autopecas/SPEC.md | 119 | Paralelismo de sessões — Tier 1 `whats-active` aceito, Tier 2 lease formal dormente |
| Autopecas/SPEC.md | 110 | Cockpit Pattern V2 — list+detail canônico para todas as migrações MWART (header + KPIs + pills + drawer) |
| Autopecas/SPEC.md | 106 | Recalibração de velocidade — fator 10x em tarefas codáveis (IA-pair) |
| Autopecas/SPEC.md | 70 | Jira-style task management no MCP — CURRENT.md/TASKS.md removidos |
| Autopecas/SPEC.md | 121 | oimpresso é ERP modular especializado por vertical — núcleo comum + Modules/<Vertical> profundo |
| Autopecas/SPEC.md | 22 | ADR 0022 — Meta financeira oimpresso: R$ 5 milhões/ano |
| Autopecas/SPEC.md | 101 | Tests SEMPRE business_id=1 (Wagner) — nunca cliente real, com guard CI |
| Autopecas/SPEC.md | 89 | Capterra-driven Module Evolution (skill + 3 artefatos) |
| Autopecas/SPEC.md | 95 | Skills Tier A/B/C — convenção interna pra controle de always-on |
| Comissao/SPEC.md | 143 | FSM Pipeline Canônico LIVE em prod biz=1 — marco 2026-05-12 (40+ PRs em ~10h) |
| Comissao/SPEC.md | 93 | Multi-tenant isolation by default — Tier 0, IRREVOGÁVEL |
| Comissao/SPEC.md | 142 | Notas internas como sinal de treino pra Jana — slash commands + 3 tabelas + parser |
| Comissao/SPEC.md | 106 | Recalibração de velocidade — fator 10x em tarefas codáveis (IA-pair) |
| Comissao/SPEC.md | 101 | Tests SEMPRE business_id=1 (Wagner) — nunca cliente real, com guard CI |
| Comissao/SPEC.md | 94 | Constituição v2 Oimpresso — 7 camadas + 8 princípios duros |
| Comissao/SPEC.md | 104 | Processo MWART canônico — único caminho de migração Blade→Inertia |
| Comissao/SPEC.md | 129 | State Machine canônica — FSM tabular custom + Spatie Permission por transição |
| ComunicacaoVisual/SPEC.md | 11 | ADR 0011 — Alinhamento com o padrãa Jana (UltimatePOS) |
| ComunicacaoVisual/SPEC.md | 119 | Paralelismo de sessões — Tier 1 `whats-active` aceito, Tier 2 lease formal dormente |
| ComunicacaoVisual/SPEC.md | 52 | ADR 0052 — `ContextoNegocio` deve expor múltiplos ângulos por métrica (não 1 número) |
| ComunicacaoVisual/SPEC.md | 35 | ADR 0035 — Stack-alvo de IA do Copiloto: declaração canônica |
| ComunicacaoVisual/SPEC.md | 93 | Multi-tenant isolation by default — Tier 0, IRREVOGÁVEL |
| ComunicacaoVisual/SPEC.md | 53 | ADR 0053 — MCP server da empresa: governança como produto, não overhead |
| ComunicacaoVisual/SPEC.md | 94 | Constituição v2 Oimpresso — 7 camadas + 8 princípios duros |
| ComunicacaoVisual/SPEC.md | 110 | Cockpit Pattern V2 — list+detail canônico para todas as migrações MWART (header + KPIs + pills + drawer) |
| ComunicacaoVisual/SPEC.md | 106 | Recalibração de velocidade — fator 10x em tarefas codáveis (IA-pair) |
| ComunicacaoVisual/SPEC.md | 22 | ADR 0022 — Meta financeira oimpresso: R$ 5 milhões/ano |
| ComunicacaoVisual/SPEC.md | 105 | Cliente como sinal + guiar sem mandar (3 graus de regulação) |
| ComunicacaoVisual/SPEC.md | 62 | Separação dura de runtime: Hostinger ≠ CT 100 Proxmox |
| ComunicacaoVisual/SPEC.md | 24 | ADR 0024 — Instalação 1-clique padronizada para todos os módulos |
| ComunicacaoVisual/SPEC.md | 117 | Múltiplos números Whatsapp por business — 1 driver + escopo de atendimento por número (WR2 piloto: Comercial + Financeiro) |
| Crm/SPEC.md | 117 | Múltiplos números Whatsapp por business — 1 driver + escopo de atendimento por número (WR2 piloto: Comercial + Financeiro) |
| Crm/SPEC.md | 135 | Omnichannel inbox — schema polimórfico Channel+Driver, 4 fases com gate cliente-sinal |
| Crm/SPEC.md | 143 | FSM Pipeline Canônico LIVE em prod biz=1 — marco 2026-05-12 (40+ PRs em ~10h) |
| Crm/SPEC.md | 35 | ADR 0035 — Stack-alvo de IA do Copiloto: declaração canônica |
| Crm/SPEC.md | 93 | Multi-tenant isolation by default — Tier 0, IRREVOGÁVEL |
| Crm/SPEC.md | 129 | State Machine canônica — FSM tabular custom + Spatie Permission por transição |
| Crm/SPEC.md | 106 | Recalibração de velocidade — fator 10x em tarefas codáveis (IA-pair) |
| Crm/SPEC.md | 104 | Processo MWART canônico — único caminho de migração Blade→Inertia |
| Crm/SPEC.md | 110 | Cockpit Pattern V2 — list+detail canônico para todas as migrações MWART (header + KPIs + pills + drawer) |
| Crm/SPEC.md | 11 | ADR 0011 — Alinhamento com o padrãa Jana (UltimatePOS) |
| Crm/SPEC.md | 105 | Cliente como sinal + guiar sem mandar (3 graus de regulação) |
| EvolutionAgent/SPEC.md | 26 | ADR 0026 — Posicionamento estratégico: ERP de Comunicação Visual com IA |
| FinanceiroAvancado/SPEC.md | 143 | FSM Pipeline Canônico LIVE em prod biz=1 — marco 2026-05-12 (40+ PRs em ~10h) |
| FinanceiroAvancado/SPEC.md | 106 | Recalibração de velocidade — fator 10x em tarefas codáveis (IA-pair) |
| FinanceiroAvancado/SPEC.md | 94 | Constituição v2 Oimpresso — 7 camadas + 8 princípios duros |
| Garantia/SPEC.md | 106 | Recalibração de velocidade — fator 10x em tarefas codáveis (IA-pair) |
| Garantia/SPEC.md | 105 | Cliente como sinal + guiar sem mandar (3 graus de regulação) |
| Infra/SPEC.md | 93 | Multi-tenant isolation by default — Tier 0, IRREVOGÁVEL |
| Infra/SPEC.md | 94 | Constituição v2 Oimpresso — 7 camadas + 8 princípios duros |
| Infra/SPEC.md | 91 | Daily Brief: contrato de contexto consolidado L7 |
| Inventory/SPEC.md | 105 | Cliente como sinal + guiar sem mandar (3 graus de regulação) |
| Inventory/SPEC.md | 93 | Multi-tenant isolation by default — Tier 0, IRREVOGÁVEL |
| Inventory/SPEC.md | 143 | FSM Pipeline Canônico LIVE em prod biz=1 — marco 2026-05-12 (40+ PRs em ~10h) |
| Inventory/SPEC.md | 106 | Recalibração de velocidade — fator 10x em tarefas codáveis (IA-pair) |
| Jana/SPEC.md | 37 | ADR 0037 — Roadmap de evolução pós-Sprint 5: Tier 5-6 → Tier 7-9 LongMemEval |
| Jana/SPEC.md | 69 | Governança de tasks: TaskRegistry MCP tools canônico, TASKS.md ASCII deprecated |
| Jana/SPEC.md | 91 | Daily Brief: contrato de contexto consolidado L7 |
| Jana/SPEC.md | 53 | ADR 0053 — MCP server da empresa: governança como produto, não overhead |
| Jana/SPEC.md | 62 | Separação dura de runtime: Hostinger ≠ CT 100 Proxmox |
| Jana/SPEC.md | 106 | Recalibração de velocidade — fator 10x em tarefas codáveis (IA-pair) |
| Jana/SPEC.md | 110 | Cockpit Pattern V2 — list+detail canônico para todas as migrações MWART (header + KPIs + pills + drawer) |
| Jana/SPEC.md | 35 | ADR 0035 — Stack-alvo de IA do Copiloto: declaração canônica |
| Jana/SPEC.md | 52 | ADR 0052 — `ContextoNegocio` deve expor múltiplos ângulos por métrica (não 1 número) |
| Jana/SPEC.md | 101 | Tests SEMPRE business_id=1 (Wagner) — nunca cliente real, com guard CI |
| Marketplaces/SPEC.md | 105 | Cliente como sinal + guiar sem mandar (3 graus de regulação) |
| Marketplaces/SPEC.md | 143 | FSM Pipeline Canônico LIVE em prod biz=1 — marco 2026-05-12 (40+ PRs em ~10h) |
| Marketplaces/SPEC.md | 117 | Múltiplos números Whatsapp por business — 1 driver + escopo de atendimento por número (WR2 piloto: Comercial + Financeiro) |
| Marketplaces/SPEC.md | 11 | ADR 0011 — Alinhamento com o padrãa Jana (UltimatePOS) |
| Marketplaces/SPEC.md | 93 | Multi-tenant isolation by default — Tier 0, IRREVOGÁVEL |
| Marketplaces/SPEC.md | 94 | Constituição v2 Oimpresso — 7 camadas + 8 princípios duros |
| Marketplaces/SPEC.md | 106 | Recalibração de velocidade — fator 10x em tarefas codáveis (IA-pair) |
| Marketplaces/SPEC.md | 101 | Tests SEMPRE business_id=1 (Wagner) — nunca cliente real, com guard CI |
| Marketplaces/SPEC.md | 70 | Jira-style task management no MCP — CURRENT.md/TASKS.md removidos |
| Marketplaces/SPEC.md | 121 | oimpresso é ERP modular especializado por vertical — núcleo comum + Modules/<Vertical> profundo |
| Marketplaces/SPEC.md | 35 | ADR 0035 — Stack-alvo de IA do Copiloto: declaração canônica |
| Marketplaces/SPEC.md | 129 | State Machine canônica — FSM tabular custom + Spatie Permission por transição |
| Marketplaces/SPEC.md | 119 | Paralelismo de sessões — Tier 1 `whats-active` aceito, Tier 2 lease formal dormente |
| Marketplaces/SPEC.md | 95 | Skills Tier A/B/C — convenção interna pra controle de always-on |
| MemCofre/SPEC.md | 5 | ADR 0005 — UUID para entidades auditáveis, BigInt para lookups |
| MemoriaAutonoma/SPEC.md | 35 | ADR 0035 — Stack-alvo de IA do Copiloto: declaração canônica |
| MemoriaAutonoma/SPEC.md | 50 | ADR 0050 — 8 métricas obrigatórias de memória + tabela `memory_metrics` |
| MemoriaAutonoma/SPEC.md | 61 | ADR 0061 — Conhecimento canônico em git/MCP, ZERO auto-mem privada |
| MemoriaAutonoma/SPEC.md | 105 | Cliente como sinal + guiar sem mandar (3 graus de regulação) |
| MemoriaAutonoma/SPEC.md | 53 | ADR 0053 — MCP server da empresa: governança como produto, não overhead |
| MemoriaAutonoma/SPEC.md | 62 | Separação dura de runtime: Hostinger ≠ CT 100 Proxmox |
| MemoriaAutonoma/SPEC.md | 121 | oimpresso é ERP modular especializado por vertical — núcleo comum + Modules/<Vertical> profundo |
| Mwart/SPEC.md | 94 | Constituição v2 Oimpresso — 7 camadas + 8 princípios duros |

_Mostrando 100 de 162._

---

**Próximos passos:**

1. Corrigir broken links (CI gate futuro) — remover refs ou criar ADR faltante
2. Fechar pares assimétricos — adicionar `superseded_by:` reverso
3. Revisar orfãs — adicionar link de ADR mãe relacionada se relevante
4. Decidir cross-refs SPEC↔ADR caso a caso

_Append-only — esta varredura NÃO modifica ADRs. Auto-fix recusado por design (`--fix` apenas lista)._
