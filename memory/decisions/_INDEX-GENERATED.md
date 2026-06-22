# ADR Index — GERADO (não editar à mão)

> ⚙️ **Auto-gerado** por `scripts/governance/adr-index-generate.mjs` a partir de `memory/decisions/[0-9]*.md`.
> Fonte única (modelo Log4brains/adr-tools, estado-da-arte 2026). Regenerar: `node scripts/governance/adr-index-generate.mjs --write`.
> Status/lifecycle normalizados no leitor (ADR 0257) — não altera os arquivos (append-only).

## Resumo
- **305** arquivos · **289** números únicos · máx **0299**
- **ADRs ATIVOS (lifecycle ativo): 265** ← resposta única a "quantos ADRs ativos"
- Por status: aceito 243 · proposto 35 · superseded 23 · (vazio) 2 · rascunho 1 · recusado 1
- Por lifecycle: ativo 265 · substituido 23 · (vazio) 8 · arquivado 6 · historical 3
- Sem frontmatter (formato-tabela legado): 4 — 0126, 0128, 0246, 0247

## Colisões de número (14) — auto-detectadas
- **0101** ×2: 0101-sistema-charter-capterra-governanca-escopo · 0101-tests-business-id-1-nunca-cliente
- **0102** ×2: 0102-nfce-status-polling-vs-broadcast · 0102-s6-charter-capterra-postmortem-s7-backlog
- **0119** ×2: 0119-migration-factory-capacidade-institucional · 0119-paralelismo-sessoes-whats-active-tier-1
- **0126** ×2: 0126-mcp-jira-projects-modulos-verticais · 0126-vault-chunked-encryption-sprint-2
- **0141** ×2: 0141-agents-tool-use-pattern-claude-code · 0141-skill-migracao-blade-react
- **0170** ×3: 0170-bancos-nativos-top5-drivers-separados · 0170-onda5-simplificada · 0170-paymentgateway-extracao-camada-cobranca
- **0178** ×2: 0178-restauracao-campos-fiscais-br-canon · 0178-sells-unified-tabs-visao-supersede-0136
- **0180** ×2: 0180-drift-numero-adr-0178-conflito-paralelo · 0180-sidebar-v3-5-grupos-ghosts-header
- **0195** ×2: 0195-feedback-relevance-scoring-decay-adaptativo · 0195-tabs-autosave-mount-sempre-hidden
- **0216** ×2: 0216-deploy-webhook-rodar-composer-dump-autoload · 0216-governance-drift-framework-driftchecker-plugavel
- **0235** ×2: 0235-ds-v4-accent-roxo-universal · 0235-staging-ct100-clone-anonimizado
- **0236** ×3: 0236-extrato-conciliacao-modelo-unificado · 0236-governanca-evolucao-doc-design · 0236-scorecard-universal-entidade-arbitraria
- **0246** ×2: 0246-sessao-2026-05-30-ds-harmonizacao · 0246-tipo-outros-default-migracoes-legacy
- **0294** ×2: 0294-mcp-audit-log-hash-chain-tamper-evident · 0294-metodo-dual-track-shapeup-catraca

## Integridade de supersessão (0 alertas)
_(íntegra)_

## Recusadas (1) — o NÃO consultável
- **0290** v0 'Fidelity Lock' (screenshot pareado em CI) — RECUSADO: fidelidade visual não  · recusada 2026-06-18 — Inviável + tautológico + backdoor de prosa (3 motivos na Decisão). REABRE só se surgir um check de fidelidade HERMÉTICO 

## Todas as ADRs (305)
| Nº | Status | Lifecycle | Kind | Título |
|---|---|---|---|---|
| 0001 | superseded | substituido | decision | Estender UltimatePOS em vez de build próprio ou fork |
| 0002 | superseded | substituido | decision | Usar nWidart/laravel-modules como sistema de módulos |
| 0003 | aceito | ativo | decision | Marcações append-only com triggers MySQL + proteção na aplicação |
| 0004 | aceito | ativo | decision | Tabela bridge `ponto_colaborador_config` |
| 0005 | superseded | substituido | decision | UUID para entidades auditáveis, BigInt para lookups |
| 0006 | aceito | ativo | decision | Multi-tenancy lógica via `business_id` |
| 0007 | aceito | ativo | decision | Banco de horas como ledger append-only |
| 0008 | superseded | substituido | decision | Sidebar com 1 item + menu horizontal em abas dentro do módulo |
| 0009 | aceito | ativo | decision | Protótipos visuais em HTML + Tailwind + Chart.js (não React) |
| 0010 | superseded | substituido | decision | Sistema de memória do projeto (CLAUDE.md + /memory/) |
| 0011 | aceito | ativo | decision | Alinhamento com o padrão Jana (UltimatePOS) |
| 0013 | aceito | arquivado | decision | Ecossistema de Módulos: Inventário, Categorias e Padrões |
| 0014 | aceito | arquivado | decision | Integração PontoWR2 × Essentials (HRM) |
| 0015 | aceito | arquivado | decision | Connector: API Gateway para Integrações Externas |
| 0016 | aceito | arquivado | decision | Plano de Otimização e Roadmap PontoWR2 |
| 0017 | aceito | ativo | decision | Officeimpresso restaurado da 3.7 como módulo Superadmin exclusivo |
| 0018 | aceito | arquivado | decision | Log de acesso do desktop via triggers MySQL (passivo) |
| 0019 | aceito | ativo | decision | Delphi legado não autentica após upgrade 3.7→6.7 (investigação) |
| 0020 | proposto | ativo | decision | Grupo econômico (matriz + filiais) no Officeimpresso |
| 0021 | aceito | ativo | decision | Contrato real da API consumida pelo Delphi |
| 0022 | aceito | ativo | decision | Meta financeira oimpresso: R$ 5 milhões/ano |
| 0023 | aceito | ativo | decision | Upgrade para Inertia.js v3 |
| 0024 | aceito | ativo | decision | Instalação 1-clique padronizada para todos os módulos |
| 0025 | aceito | ativo | decision | Redesign da landing pública (`Modules/Cms`) em Inertia/React |
| 0026 | aceito | ativo | decision | Posicionamento estratégico: ERP de Comunicação Visual com IA |
| 0027 | aceito | ativo | decision | Gestão de memória do projeto: papéis claros por função |
| 0028 | superseded | substituido | decision | ADRs com numeração monotônica e formato Nygard |
| 0029 | aceito | ativo | decision | Padrão Inertia + React + UltimatePOS pra módulos novos |
| 0030 | aceito | ativo | decision | Credenciais sensíveis: nunca em git |
| 0031 | superseded | substituido | decision | `MemoriaContrato` interface PHP + driver default `Mem0RestDriver` |
| 0032 | superseded | substituido | decision | Vizra ADK + Prism PHP como camada de orquestração e wrapper LLM do Copiloto |
| 0033 | superseded | substituido | decision | Vector store / search backend do oimpresso: pgvector vs Meilisearch+Scout vs Mem |
| 0034 | aceito | ativo | decision | Laravel AI ecosystem 2026: SDK oficial + Boost + MCP + Vizra ADK + alternativas |
| 0035 | superseded | substituido | decision | Stack-alvo de IA do Copiloto: declaração canônica |
| 0036 | superseded | substituido | decision | Replanejamento canônico: Meilisearch primeiro, Mem0 por último |
| 0037 | aceito | ativo | decision | Roadmap de evolução pós-Sprint 5: Tier 5-6 → Tier 7-9 LongMemEval |
| 0038 | aceito | ativo | decision | Promoção de `6.7-bootstrap` para `main` como branch principal |
| 0039 | aceito | ativo | decision | Padrão de UI "Chat Cockpit" (3 colunas) para o ERP |
| 0040 | aceito | ativo | decision | Policy de publicação: Claude supervisiona, Wagner escala |
| 0041 | aceito | ativo | decision | Stack de QA de IA: Vizra ADK eval + Langfuse self-host + DeepEval CLI (Caminho B |
| 0042 | superseded | substituido | decision | Reverb (self-hosted) substitui Pusher Cloud como broadcaster |
| 0043 | aceito | ativo | decision | Docker + Traefik + Portainer num LXC, em vez de N LXCs nativos |
| 0044 | superseded | substituido | decision | Vaultwarden self-hosted como cofre de credenciais |
| 0045 | aceito | ativo | decision | Endpoint canônico da Hostinger DNS API V1 |
| 0046 | aceito | ativo | decision | `ChatCopilotoAgent` precisa de contexto rico + tools (gap descoberto) |
| 0047 | aceito | ativo | decision | Wagner solo: sprint memória do agente (token economy + assertividade) |
| 0048 | aceito | ativo | decision | Framework de agentes IA: `laravel/ai` (Vizra ADK rejeitada oficialmente) |
| 0049 | aceito | ativo | decision | Camadas de memória do agente: ligar fase por fase, medir antes de evoluir |
| 0050 | aceito | ativo | decision | 8 métricas obrigatórias de memória + tabela `memory_metrics` |
| 0051 | aceito | ativo | decision | Schema próprio + adapter pattern + emissão OpenTelemetry GenAI |
| 0052 | aceito | ativo | decision | `ContextoNegocio` deve expor múltiplos ângulos por métrica (não 1 número) |
| 0053 | aceito | ativo | decision | MCP server da empresa: governança como produto, não overhead |
| 0054 | superseded | substituido | decision | Pacote enterprise de busca de memória: por quê + como evolui |
| 0055 | aceito | ativo | decision | Self-host Team plan equivalente ao Anthropic Team/Enterprise |
| 0056 | aceito | ativo | decision | MCP server como fonte única de memória pro Copiloto chat + Claude Code |
| 0057 | aceito | ativo | decision | Tela `/team-mcp/team`: regras de governança de tokens MCP e distribuição via `.d |
| 0058 | aceito | ativo | decision | Reverb substituído por Centrifugo + FrankenPHP |
| 0059 | aceito | ativo | decision | Governança da memória estilo Anthropic Team plan adaptado |
| 0060 | aceito | ativo | decision | IA + workers pesados na rede interna (Proxmox), app principal continua Hostinger |
| 0061 | aceito | ativo | decision | Conhecimento canônico em git/MCP, ZERO auto-mem privada |
| 0062 | aceito | ativo | decision | Separação dura de runtime: Hostinger ≠ CT 100 Proxmox |
| 0063 | aceito | ativo | decision | Prevenir composer.lock drift permanentemente |
| 0064 | aceito | ativo | decision | Modularização — split TeamMcp + KB + Superadmin 360° |
| 0065 | aceito | ativo | decision | Permission Registry — contrato declarativo de permissions per-módulo |
| 0066 | aceito | ativo | decision | format_date com shift +3h preservado intencionalmente — quirk legacy ROTA LIVRE |
| 0067 | aceito | ativo | decision | Sprint 8 — McpMemoryDocument Searchable + retrieval hybrid na pipeline RAGAS |
| 0068 | rascunho | ativo | decision | Sprint 9 — Estratégia retrieval: Ollama embedder + reranking + documentTemplate  |
| 0069 | superseded | substituido | decision | Governança de tasks: TaskRegistry MCP tools canônico, TASKS.md ASCII deprecated |
| 0070 | aceito | ativo | decision | Jira-style task management no MCP — CURRENT.md/TASKS.md removidos |
| 0071 | aceito | ativo | decision | Auditoria tools MCP 2026-05-05 — bugs descobertos + workarounds |
| 0072 | proposto | ativo | decision | Maturação memória + Team MCP — gaps identificados vs OpenClaw/Mem0/Letta/Zep/A-M |
| 0073 | superseded | substituido | decision | Team MCP P0 — skills e policies como entidades governadas (mcp_skills + mcp_poli |
| 0074 | proposto | ativo | decision | P1 — Temporal validity bi-temporal: event-time vs system-time + time-travel quer |
| 0075 | superseded | substituido | decision | Team MCP P0 v2 — UI gestão de skills estilo prompt-management (5 tabelas, 5 tela |
| 0076 | proposto | ativo | decision | Skills V2 — DB é primary, git é destino auditável; drift por-skill (auto/manual/ |
| 0077 | superseded | substituido | decision | MCP resolver via users.mcp_handle (SUPERSEDED por ADR 0081 — Identity Mesh) |
| 0078 | superseded | substituido | decision | Meta-skill ROI ERP autônomo — skill+missão como unidade operacional (parcialment |
| 0079 | superseded | substituido | decision | Constituição do Oimpresso ERP — 10 artigos supremos sobre 7 camadas de governanç |
| 0080 | aceito | ativo | decision | Trust Tiers operacional + Architecture & Scope + audit findings v1.1.0 |
| 0081 | aceito | ativo | decision | Identity Mesh — schema mcp_actors + manifest pattern + seed inicial 6 actors |
| 0084 | aceito | ativo | decision | Triggers MySQL append-only em mcp_audit_log + correção audit P0.1 |
| 0085 | aceito | ativo | decision | Fase 3.4 SCOPE.md completo + ActorResolver + PII Redactor + roadmap pendências |
| 0086 | aceito | ativo | decision | Fase 5 MVP — Modules/Governance scaffold + ActionGate (warn-only) + Sidebar GOVE |
| 0087 | aceito | ativo | decision | Drift resolution sem mover URL — pattern de migration safe |
| 0088 | superseded | substituido | decision | Module rename PHP-only — fachada legacy mantida durante transição |
| 0089 | aceito | ativo | decision | Capterra-driven Module Evolution (skill + 3 artefatos) |
| 0090 | aceito | ativo | decision | NFe replace gradual: app/Services → Modules/NfeBrasil |
| 0091 | aceito | ativo | decision | Daily Brief: contrato de contexto consolidado L7 |
| 0092 | aceito | ativo | decision | Tabela rename copiloto_* → jana_* (PR-9 da Fase 3.7 — renumerada de 0090 pra 009 |
| 0093 | aceito | ativo | decision | Multi-tenant isolation by default — Tier 0, IRREVOGÁVEL |
| 0094 | aceito | ativo | decision | Constituição v2 Oimpresso — 7 camadas + 8 princípios duros |
| 0095 | aceito | ativo | decision | Skills Tier A/B/C — convenção interna pra controle de always-on |
| 0096 | aceito | ativo | decision | Módulo Whatsapp — Z-API default + Meta Cloud fallback + BaileysDriver custom (Sp |
| 0097 | aceito | ativo | decision | BRIEF generator usa gpt-4o-mini em vez de Sonnet (supersede parcial ADR 0091) |
| 0098 | aceito | ativo | decision | build:inertia roda na Hostinger pós git-pull (substitui GH Actions runner) |
| 0099 | aceito | ativo | decision | Modules/Project (legacy UltimatePOS) — Discovery pré-deletion (Fase 3.8) |
| 0100 | aceito | ativo | decision | ProjectMgmt UI Redesign — Linear-tier UX em 4 fases capterra-driven |
| 0101 | aceito | ativo | decision | Sistema Charter-Capterra — governança de escopo em 2 níveis × 3 eixos |
| 0101 | aceito | ativo | decision | Tests SEMPRE business_id=1 (Wagner) — nunca cliente real, com guard CI |
| 0102 | aceito | ativo | decision | US-NFE-002 fase 2C — UI status NFC-e via polling JSON (broadcast adiado) |
| 0102 | aceito | ativo | decision | Sprint S6 Charter-Capterra postmortem + S7 backlog (5 itens, ~24h) |
| 0103 | aceito | ativo | decision | Eventos fiscais Laravel separados por modelo NFe (NFeAutorizada / NFCeAutorizada |
| 0104 | aceito | ativo | decision | Processo MWART canônico — único caminho de migração Blade→Inertia |
| 0105 | aceito | ativo | decision | Cliente como sinal + guiar sem mandar (3 graus de regulação) |
| 0106 | aceito | ativo | decision | Recalibração de velocidade — fator 10x em tarefas codáveis (IA-pair) |
| 0107 | aceito | ativo | decision | Emendation ADR 0104 — Visual comparison gate obrigatório em F3 (loop design supe |
| 0108 | aceito | ativo | decision | Regressão visual via Pest 4 Browser snapshot — Tier 2 (CI gate F4 QA) |
| 0109 | aceito | ativo | decision | Claude Design plugin (Anthropic) integrado ao processo MWART — design supervisio |
| 0110 | aceito | ativo | decision | Cockpit Pattern V2 — list+detail canônico para todas as migrações MWART (header  |
| 0111 | aceito | ativo | decision | Emenda 5 ao ADR 0096 — Bypass Meta-fallback per-business via env (piloto biz=1 s |
| 0112 | aceito | arquivado | decision | MWART exceção — fix bugs UI Settings.tsx Whatsapp (sem migração nova) |
| 0113 | aceito | ativo | decision | Integração Delphi WR Comercial ↔ Laravel oimpresso ↔ ADs em 3 caminhos aditivos |
| 0114 | aceito | ativo | decision | Loop Cowork ↔ Claude Code formalizado via prototipo-ui/ |
| 0115 | aceito | ativo | decision | Recuperação cliente Gold Comunicação Visual via bundle oimpresso + NF-e 55 (anti |
| 0116 | aceito | ativo | decision | Pivot caso Gold — Manifestação do Destinatário (DFe) substitui escopo de emissão |
| 0117 | aceito | ativo | decision | Múltiplos números Whatsapp por business — 1 driver + escopo de atendimento por n |
| 0118 | proposto | ativo | decision | Segregação de domínios externos e clientes-legacy em pastas top-level no memory/ |
| 0119 | proposto | ativo | decision | Migration Factory — capacidade institucional do oimpresso pra ingerir cliente de |
| 0119 | aceito | ativo | decision | Paralelismo de sessões — Tier 1 `whats-active` aceito, Tier 2 lease formal dorme |
| 0120 | aceito | ativo | decision | Supersession metadata housekeeping — fix 0079 + documenta drift de direção forwa |
| 0121 | aceito | ativo | decision | oimpresso é ERP modular especializado por vertical — núcleo comum + Modules/<Ver |
| 0122 | proposto | ativo | decision | Admin Center — Centro de Operações @ CT 100 (Tailscale-only, Wagner-only, agrega |
| 0123 | aceito | ativo | decision | Modules/Arquivos — backbone DMS (todo arquivo anexado entra aqui) |
| 0124 | aceito | ativo | decision | Curador — pipeline canônico de ingestão de conhecimento (computador → empresa →  |
| 0125 | proposto | historical | decision | Modules/Autopecas como feature-wish — Vargas é sinal qualificado |
| 0126 | proposto | ativo | decision | Habilitar ComunicacaoVisual + Vestuario + OficinaAuto como projects canônicos no |
| 0126 | proposto | (vazio) | decision | ADR 0126 — Vault chunked encryption Sprint 2 (proposed) |
| 0127 | aceito | ativo | decision | Modules/Auditoria — UI rica + undo sobre activity_log existente |
| 0128 | proposto | (vazio) | decision | ADR 0128 — Smoke testing E2E pós-cycle |
| 0129 | aceito | ativo | decision | State Machine canônica — FSM tabular custom + Spatie Permission por transição |
| 0130 | aceito | ativo | decision | Handoff append-only + MCP-first antes de escrever — fim do overwrite cego de 08- |
| 0131 | aceito | ativo | decision | Tiering de memória — canônico (git/MCP) / máquina-local / segredo (Vaultwarden) |
| 0132 | aceito | ativo | decision | Langfuse self-host CT 100 — observabilidade GenAI canônica oimpresso |
| 0133 | aceito | ativo | decision | System health audit canônico — 5 dimensões automáticas (Tool MCP + cron daily) |
| 0134 | aceito | ativo | decision | tasks-create respeita placeholders em SPEC.md (regex headers + bullets) |
| 0135 | aceito | ativo | decision | Omnichannel inbox — schema polimórfico Channel+Driver, 4 fases com gate cliente- |
| 0136 | superseded | substituido | decision | Sells: split Lista (default) vs Grade Avançada (toggle) — migração legacy Office |
| 0137 | aceito | ativo | decision | Modules/OficinaAuto qualificada — sinal confirmado por 2 de 4 candidatos OfficeI |
| 0140 | aceito | ativo | decision | JANA Pro — Produto comercial SaaS de IA pra PMEs BR (upsell sobre oimpresso, R$  |
| 0141 | aceito | ativo | decision | Agents IA com tool use loop (pattern "Claude Code") — Camada B v2 |
| 0141 | aceito | ativo | decision | Skill `migracao-blade-react` — orquestrador Cowork→Inertia preservando paridade  |
| 0142 | aceito | ativo | decision | Notas internas como sinal de treino pra Jana — slash commands + 3 tabelas + pars |
| 0143 | aceito | ativo | decision | FSM Pipeline Canônico LIVE em prod biz=1 — marco 2026-05-12 (40+ PRs em ~10h) |
| 0144 | aceito | ativo | decision | TaskRegistry — DB é canon de estado vivo, SPEC.md é template descritivo |
| 0145 | aceito | ativo | decision | IA Administradora do oimpresso — pivot ADS↔FSM + piloto Cobradora ROTA LIVRE |
| 0146 | proposto | ativo | decision | Refactor contact_lid como chave canônica de identidade WhatsApp |
| 0147 | aceito | ativo | decision | Cascade Review §10.4 — Defesa em profundidade contra drift pré-entrada time MCP |
| 0148 | aceito | ativo | decision | Cascade Review §10.4 — Onda 6 fechamento roadmap memoria-senior pra nota 98 |
| 0149 | aceito | ativo | decision | Screen-Pattern Reuse no MWART — Index Cowork blueprint pra Show/Edit/Detail da m |
| 0150 | aceito | ativo | decision | KB Unificado como Grafo de Conhecimento — módulo IA central do oimpresso |
| 0151 | proposto | historical | decision | Modules/Comissao como feature-wish — aguarda cliente que reporta dor real |
| 0152 | proposto | historical | decision | Modules/Pcp como feature-wish — aguarda Vargas ou ComVis 1º piloto |
| 0153 | proposto | ativo | decision | Rubrica oficial `module-grade-v1` — nota 0-100 ponderada pra cada Module |
| 0154 | aceito | ativo | decision | Rubrica `module-grade-v2` — regra N/A justificado pra dimensões inaplicáveis por |
| 0155 | aceito | ativo | decision | module-grade-v3 — 4 sub-dimensões novas (Performance/LGPD/Security/Observability |
| 0156 | aceito | ativo | decision | module-grade-v3 errata — D9.a regex inclui OtelHelper canônico + ratifica na_jus |
| 0157 | aceito | ativo | decision | module-grade-v3 — endurecimento D2 detection (parser XML + verificação subpastas |
| 0158 | aceito | ativo | decision | module-grade-v3 — endurecimento heurística D1 (recursive + scope singular + Job  |
| 0159 | proposto | ativo | decision | module-grade-v3 errata — realismo meta 97.75 (D5 cross-cutting / D9.b ready / D4 |
| 0160 | aceito | ativo | decision | module-grade-v4 — Scoped Scorecards (Lens per Module Kind) com 4 buckets + meta  |
| 0161 | aceito | ativo | decision | Governance v4 — aposentar 3 dos 4 hacks ADR 0159 redundantes com Scoped Scorecar |
| 0162 | aceito | ativo | decision | OpenTelemetry Collector ativo em prod (CT 100) — destrava D6.b + D9.b governance |
| 0163 | aceito | ativo | decision | Governance v4 — metas por bucket alcançadas (Ondas 19-28) · 4/4 buckets acima da |
| 0164 | aceito | ativo | decision | Screen Review PDCA — fase C (Check) automática pós-merge via skill tela-smoke-po |
| 0165 | proposto | ativo | decision | Design System — breakpoints canon + regra mobile-first em todas as Pages Inertia |
| 0166 | aceito | ativo | decision | Errata ADR 0162 — OTel SDK em require-dev (Hostinger shared sem ext-opentelemetr |
| 0167 | aceito | ativo | decision | Errata ADR 0130 — Índice de handoff mantém histórico longo (não trunca 5) |
| 0168 | proposto | ativo | decision | PROTOCOLO WAGNER SEMPRE — 10 regras canon Tier A always-on (Constituição v2 emen |
| 0169 | proposto | ativo | decision | Errata ADR 0168 — RUNBOOK-onda-cowork.md como artefato 4º da triade governance |
| 0170 | aceito | (vazio) | decision | PaymentGateway Top-5 bancos brasileiros — drivers REST e CNAB SEPARADOS (Ondas 4 |
| 0170 | aceito | (vazio) | decision | PaymentGateway Onda 5 SIMPLIFICADA — Dogfooding SaaS via 6º gateway adicional |
| 0170 | proposto | ativo | decision | Modules/PaymentGateway — extração da camada técnica de cobrança |
| 0171 | aceito | ativo | decision | Ativação Modules/OficinaAuto — Piloto Martinho Caçambas (faseada, add-on faturáv |
| 0172 | aceito | (vazio) | decision | Deprecar Modules/Accounting e consolidar contabilidade operacional no Modules/Fi |
| 0173 | aceito | (vazio) | decision | Errata ARQ-0005 — tabelas Accounting usam nomes nus (não prefixo `accounting_*`) |
| 0174 | aceito | ativo | decision | Errata DEPRECATION-PLAN Accounting — Ondas 3+4 SKIP (audit prod 0 rows) |
| 0175 | aceito | ativo | decision | Fix arquitetural — Observer Financeiro permite baixa sem fin_contas_bancarias (r |
| 0177 | aceito | ativo | decision | MWART exceção — Cliente/Show Wave paralela paridade tabs (visual regression over |
| 0178 | aceito | ativo | decision | Restauração dos campos fiscais BR em `contacts` (regressão UPOS 6.7) |
| 0178 | aceito | ativo | decision | Sells: unificar Lista + Grade Avançada numa só tabela com tabs de Visão (Operaci |
| 0179 | aceito | ativo | decision | Cliente — drawer lateral 760px substitui Show.tsx full-page (paradigma cadastral |
| 0180 | aceito | ativo | decision | Drift de número ADR 0178 — conflito paralelo PR #1323 (Sells) × PR #1324 (Client |
| 0180 | aceito | ativo | decision | Sidebar v3 — 5 grupos canônicos (VENDER · OPERAR · FINANÇAS · PESSOAS · SISTEMA) |
| 0182 | aceito | ativo | decision | PageHeaderTabs pattern canon — header obrigatório de telas Inertia com sub-naveg |
| 0183 | aceito | ativo | decision | Caixa físico (cash_registers) ↔ Financeiro (fin_titulos) — ponte canon multi-cai |
| 0184 | aceito | ativo | decision | Errata ADR 0183 — NÃO deprecar rotas `/cash-register/*` UPOS core (descoberta pó |
| 0185 | aceito | ativo | decision | Drawer 760 escala pra entidades cadastrais do projeto — substitui Edit.tsx/Creat |
| 0186 | aceito | ativo | decision | Chain de certificado A1 + SEFAZ ConsultaCadastro merge paralelo — IRREVOGÁVEL |
| 0187 | aceito | ativo | decision | Constituição UI v2 — ponteiro canon (hierarquia 4 camadas + regra-mestre + PT-01 |
| 0188 | aceito | ativo | decision | Contatos multi-type · flags aditivas is_customer/is_supplier/is_employee/is_repr |
| 0189 | aceito | ativo | decision | PageHeader canon v3.1 — bloco fechado + KPI strip separado + ⋮ overflow + roxo m |
| 0190 | superseded | substituido | decision | Primary button interno = roxo médio universal oklch(0.55 0.15 295) — hue per gru |
| 0191 | aceito | ativo | decision | Microsoft Clarity como ferramenta canon de session replay + heatmap LGPD-complia |
| 0192 | aceito | ativo | decision | Auto-faturar OS → Venda via JobSheetObserver (Integração Vendas × Oficina A1 KB- |
| 0193 | proposto | ativo | decision | NfeService.retransmitir sem forceDelete (Wave 27 D6 · CONFAZ SINIEF 07/2005 Art. |
| 0194 | aceito | ativo | decision | Correção domínio OficinaAuto Martinho — mecânica pesada caminhão basculante (não |
| 0195 | aceito | ativo | decision | Feedback indexing — relevance scoring + decay adaptativo + signature dedup |
| 0195 | aceito | ativo | decision | Tabs com autosave/state user-editável ficam mount-sempre (hidden via CSS) — rend |
| 0197 | aceito | ativo | decision | Extensão `contacts` pra absorver schema legacy `PESSOAS` (WR Comercial Delphi/Fi |
| 0198 | aceito | ativo | decision | Estratégia hot/cold tiering pra migração transacional histórica de 8-10 clientes |
| 0199 | aceito | ativo | decision | Errata Bucket B · pivot tabela satélite 10 cols → 2 cols JSON catch-all em conta |
| 0200 | aceito | ativo | decision | Contacts adopta canon sync bidirecional Wagner 2024-11 (officeimpresso_codigo +  |
| 0201 | aceito | ativo | decision | Receita Federal + SEFAZ ConsultaCadastro é o padrão canon de coleta de dados cad |
| 0202 | aceito | ativo | decision | WhatsApp profissionalização — Meta Cloud API default universal + Z-API opcional  |
| 0203 | aceito | ativo | decision | Pipeline legacy-migration Firebird → oimpresso completo (Wave 29-1) |
| 0204 | aceito | ativo | decision | WhatsApp whatsmeow Go driver — substituto não-oficial Baileys (amend ADR 0202) |
| 0205 | aceito | ativo | decision | Contract tests autosave como padrão canônico pra toda tela com endpoint PATCH |
| 0206 | aceito | ativo | decision | Whatsmeow profissionalização — State Machine + Reconciler + circuit breaker + ba |
| 0207 | aceito | ativo | decision | Contract test obrigatório em PR que toque tela autosave — CI gate hard |
| 0208 | aceito | ativo | decision | Larastan PHPStan baseline ratchet — enforcement passivo de anti-padrões PHP |
| 0209 | aceito | ativo | decision | ESLint 9 flat-config + react-hooks + jsx-a11y baseline ratchet — enforcement pas |
| 0210 | aceito | ativo | decision | Type safety end-to-end via Wayfinder — eliminar R8/AP-12 (type drift backend↔fro |
| 0211 | aceito | ativo | decision | TanStack Query como padrão de data-fetching em componentes — eliminar R7 (race c |
| 0212 | aceito | ativo | decision | Defensive logging em fallback paths — eliminar R9-class (silent fallback) via Lo |
| 0213 | aceito | ativo | decision | Audit docs com gaps criam MCP tasks automaticamente — loop fechado audit → backl |
| 0214 | aceito | ativo | decision | Arquivos backbone — aceite ADR 0123 + emenda storage default S3 MinIO CT 100 |
| 0215 | aceito | ativo | decision | Secrets governance — 5 camadas automáticas (auto-discovery + auto-validate + aut |
| 0216 | aceito | ativo | decision | Deploy webhook Hostinger deve rodar `composer dump-autoload -o` + `php artisan o |
| 0216 | aceito | ativo | decision | Governance Drift Framework — interface DriftChecker plugável (generaliza ADR 021 |
| 0217 | aceito | ativo | decision | ComposerAuditChecker — CVE detection deps composer.lock (supply chain) |
| 0218 | aceito | ativo | decision | MultiTenantScopeChecker — Tier 0 IRREVOGÁVEL (ADR 0093 defesa em profundidade) |
| 0219 | aceito | ativo | decision | AdrLinksChecker — link rot + lifecycle integrity de ADRs Nygard |
| 0220 | aceito | ativo | decision | ChartersFreshnessChecker — adapter pattern do charter:audit existente |
| 0221 | aceito | ativo | decision | RoutesZombieChecker — routes sem hits = tech debt + blast radius |
| 0222 | aceito | ativo | decision | Renovate config — defesa proativa supply chain (lições Shai-Hulud + axios + lara |
| 0223 | aceito | ativo | decision | NpmAuditChecker — frontend supply chain CVE detection (complementa 0217) |
| 0224 | aceito | ativo | decision | Triagem hooks block vs advisory — Claude 4.8-aware (rebaixa enforcement semântic |
| 0225 | aceito | ativo | decision | Recalibração skills Tier A pós-Claude 4.8 — 8 always-on → 5 núcleo + 7 auto-trig |
| 0226 | aceito | ativo | decision | Brief v2 (1M-aware) — régua 3.5k → 8k tokens, reposiciona como estado-rico-pro-W |
| 0229 | aceito | ativo | decision | Errata 0225 — medição empírica 25/66 skills eager (corrige diagnóstico 8→5 estim |
| 0230 | proposto | ativo | decision | Método Governance Scorecard — pontuar regras vs estado-da-arte + anti-regressão  |
| 0231 | proposto | ativo | decision | Processo de Trabalho Canônico — dividir → especialista por área → Método Scoreca |
| 0232 | proposto | ativo | decision | Modelo de Peso Real — classificar memórias, decisões e iniciativas por contribui |
| 0233 | proposto | ativo | decision | Ativação de memória no momento-decisão — ciclo de vida 8 etapas + convenção gati |
| 0234 | aceito | ativo | decision | Registry de Automações no MCP — hooks/crons/rotinas governados |
| 0235 | aceito | ativo | decision | DS v4 — design system roxo universal (accent oklch 0.55 0.15 295); supersede ADR |
| 0235 | aceito | ativo | decision | Ambiente de Staging no CT 100 — clone anonimizado da produção |
| 0236 | aceito | ativo | decision | Extrato bancário + Conciliação: modelo unificado (origem como atributo, concilia |
| 0236 | aceito | ativo | decision | Governança de evolução da documentação de design — append-only + índice fonte-ún |
| 0236 | aceito | ativo | decision | Scorecard Universal — entidade avaliável arbitrária (blueprint pattern): temas/c |
| 0237 | aceito | ativo | decision | jana:reconcile — loop de reconciliação único (git == índice == MCP == settings = |
| 0238 | aceito | ativo | decision | Soberania de [W] sobre a constituição — modificação autorizada e versionada |
| 0239 | aceito | ativo | decision | Governança do Design System — git é fonte única; mudança flui Cowork→Code→git co |
| 0240 | aceito | ativo | decision | Task ledger git-native + handoff-por-tarefa + fechamento por evidência + antifra |
| 0241 | aceito | ativo | decision | Loop design Cowork↔Code autônomo — humano sai do loop (gates CI no lugar de [W2] |
| 0242 | aceito | ativo | decision | Charters de papel — [W] soberano + agentes champion ([CC]/[CL]/[CD]/[CA]): memór |
| 0243 | aceito | ativo | decision | Processo de memória/evolução de design do Cowork — loop medido e auto-corretivo  |
| 0244 | aceito | ativo | decision | DS v5 = Design System único ativo (v4 lápide) · Oficina = tela-padrão/semente do |
| 0245 | aceito | ativo | decision | Jana Modo Consultor (Advisor) — clarify reativo (cascata Decidir→Clarificar→Resp |
| 0246 | (vazio) | (vazio) | decision | ADR 0246 — Harmonização DS sem perder qualidade + caminho v4.2 |
| 0246 | aceito | ativo | decision | Tipo "Outros" como categoria default pra cadastros legacy em migrações |
| 0247 | (vazio) | (vazio) | decision | ADR 0247 — Carta de Design [CC] subordinada ao protocolo do git |
| 0248 | proposto | ativo | decision | Gate de exclusão do Sells/Show espelha a autorização do servidor (sell.delete // |
| 0249 | aceito | ativo | decision | DS v6 — nome canônico único da camada de tokens semânticos (resolve divergência  |
| 0250 | aceito | ativo | decision | QA-de-tela sustentável: enforcement determinístico do screen-grade (seed + catra |
| 0251 | aceito | ativo | decision | Veículo na venda direta de oficina (transactions.vehicle_id) — extensão da Integ |
| 0252 | aceito | ativo | decision | Provider LLM default da camada A = OpenAI (gpt-4o-mini / gpt-4o) |
| 0253 | aceito | ativo | decision | Primitivos de layout (Components/layout/): Box/Stack/Inline/Grid/Container/Text  |
| 0254 | aceito | ativo | decision | Grade de identidade visual DETERMINÍSTICO — rubrica binária anti-alucinação (end |
| 0255 | aceito | ativo | decision | Contrato de view determinístico: charter (intenção) + design-spec.json derivado  |
| 0256 | aceito | ativo | decision | Knowledge Survival — conhecimento tem meia-vida; sobrevive por catraca + sentine |
| 0257 | aceito | ativo | decision | Modelo canônico de status/lifecycle/kind de ADR + exceção de normalização no app |
| 0258 | aceito | ativo | decision | Processo de ADR estado-da-arte — índice gerado + supersede atômico + status-mutá |
| 0259 | aceito | ativo | errata | Errata 0091 — modelo do Brief é gpt-4o-mini (não Sonnet); §Geração superseded po |
| 0260 | aceito | ativo | errata | Errata 0182 — cor primária do PageHeader é roxo universal 295 (não hue-per-grupo |
| 0261 | aceito | ativo | decision | Enforcement faseado dos gates de CI: required-checks por níveis + skip-as-pass + |
| 0262 | aceito | ativo | decision | Governança escala com o time: review opcional pra dev solo + evolução = mais fác |
| 0263 | aceito | ativo | decision | Identidade de cor vira gate bloqueante no main: chrome roxo único + semântica go |
| 0264 | aceito | ativo | meta | Governança executável: trio-de-tela + caso↔teste + domínio + E2E viram gates de  |
| 0265 | aceito | ativo | errata | Oficina = reparo é o único domínio; erradicar resíduo de order_type=locacao; 'Ca |
| 0266 | aceito | ativo | meta | Evals de comportamento dos agentes — golden set + replay cases + escada de auton |
| 0267 | proposto | ativo | decision | whatsapp_queues — filas de atendimento persistidas em DB (US-WA-301) |
| 0268 | proposto | ativo | decision | whatsapp_broadcasts — campanha broadcast cross-canal (modelo + pre-flight; dispa |
| 0269 | aceito | ativo | decision | Deploy automático em push pra main + build no runner (manual → automático, JS sa |
| 0270 | aceito | ativo | decision | Ciclo de vida da informação — porta única + destilação + decaimento + medir o ca |
| 0271 | aceito | ativo | decision | Revisão dos 64 gates CI — estado real dos required (corrige drift da 0261), font |
| 0272 | aceito | ativo | decision | Árvore canônica de componentes — camadas UI-0013 viram pastas enforçadas (allowl |
| 0273 | aceito | ativo | decision | Anchor spec↔código — formato canônico do campo 'Implementado em', sentinela _pen |
| 0274 | aceito | ativo | meta | Referência canônica a ADR = SLUG completo (NNNN-titulo) + alias map das 13 colis |
| 0275 | aceito | ativo | decision | Scorecard SDD canônico — 10 métricas com catraca, composta v1/v2 (regimes não co |
| 0276 | aceito | ativo | meta | Decisão pelo fluxo — 3 classes de decisão; pares adversariais substituem aprovaç |
| 0277 | aceito | ativo | meta | Rota de migração do backbone Blade (UltimatePOS) — contrato de completude por ro |
| 0278 | aceito | ativo | meta | Arquitetura durável de automação multi-IA (anti-vazamento, thread-aware, em rede |
| 0279 | aceito | ativo | meta | Fechar o elo MEDIR→GOVERNAR do floor (transporte CT100 → scorecard, Opção A) |
| 0280 | aceito | ativo | meta | Postura multi-tenant das tabelas mcp_* — governança de plataforma é repo-wide (s |
| 0281 | aceito | ativo | decision | Dark mode ativa por [data-theme=dark] (mecanismo real do AppShellV2), não só pel |
| 0282 | aceito | ativo | decision | Protocolo v2 (colapso) — ratificação: 6→2 papéis · 7→3 fases · memória=git SSOT  |
| 0283 | aceito | ativo | decision | Loop de handoff zero-paste — repo fonte única, gate de conteúdo, sem auto-merge  |
| 0284 | aceito | ativo | decision | Pipeline de incidente graduado por confiança — porta única, redação cross-tenant |
| 0285 | aceito | ativo | decision | Publisher Cowork→repo — fechar o 1º hop do loop zero-paste reusando a cowork-inb |
| 0286 | aceito | ativo | decision | channel_health de canal whatsmeow é corroborado por fluxo de mensagem real — inb |
| 0287 | proposto | ativo | decision | probe whatsmeow trata PROVISION_PENDING em canal que estava healthy como queda ( |
| 0288 | proposto | ativo | decision | SLO/SLI de saúde de canal WhatsApp — uptime%, time-to-detect e alerta canal-down |
| 0289 | proposto | ativo | decision | failover automático por saúde de canal: tenant crítico cai pro Cloud API (oficia |
| 0290 | recusado | ativo | decision | v0 'Fidelity Lock' (screenshot pareado em CI) — RECUSADO: fidelidade visual não  |
| 0291 | aceito | ativo | meta | Emenda 0270 F3/D-5 — contrato do distiller-módulo-verdade (diário→manual) + inst |
| 0292 | proposto | ativo | errata | Errata 0291 D-D — distiller_freshness no scorecard mede staleness vs doc mais no |
| 0293 | proposto | ativo | decision | Governança da decisão de design: responsável por etapa do ciclo + Decision Regis |
| 0294 | aceito | ativo | decision | mcp_audit_log tamper-evident por hash-chain SHA-256 (cadeia global) — transplant |
| 0294 | aceito | ativo | decision | Método de planejamento: dual-track + Shape Up travado por catraca (incubadora →  |
| 0295 | aceito | ativo | decision | aceitar e implementar bi-temporal event-time na memoria Jana (ratifica desenho 0 |
| 0296 | proposto | ativo | infra | Plano de capacidade à prova de falhas — taxonomia canônica de dados + placement  |
| 0297 | aceito | ativo | meta | Exceção append-only: migração legacy→canônico de frontmatter de ADR sob label, c |
| 0298 | aceito | ativo | meta | Teto de governança — todo workflow novo nasce com classe terminal e âncora de cu |
| 0299 | proposto | ativo | decision | Figma não é fonte de design: bloqueio determinístico do atrator + fonte única (C |
