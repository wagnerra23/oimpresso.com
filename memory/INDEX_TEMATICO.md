# INDEX_TEMATICO.md — Índice Temático da Memória (Cowork + Git)

> **Busca por tema, não por número.** Une as duas memórias: a **grande memória do git**
> (`wagnerra23/oimpresso.com` · `memory/decisions/` · ~239 ADRs Nygard, numeração monotônica
> ADR 0028) e a **memória do Cowork** (projeto de design). Para "o que decidimos sobre X?", venha aqui.
>
> Origem: seed gerado no Cowork ([CC]) como `MEMORY_INDEX.md`; **completado pelo Claude Code a partir do git**
> (backfill 0042–0235) e renomeado p/ o canônico `memory/INDEX_TEMATICO.md`.
> Última att.: 2026-05-30 (backfill 0042–0235).
>
> O índice cronológico oficial (`memory/decisions/README.md`) está **defasado** (2026-04-24, só vai até ~0023);
> este é o complemento **temático e atual**. **Fonte de verdade do lifecycle** (aceita/superseded/proposta) =
> tool MCP `decisions-search` + [`_INDEX-LIFECYCLE.md`](decisions/_INDEX-LIFECYCLE.md). O `Status` abaixo é
> melhor-esforço inferido do título; quando em dúvida, consulte a ADR.

## Legenda
- **Local:** `git` (canônico no repo) · `cowork` (projeto de design) · `bridge` (ponte p/ Code)
- **Fate (docs cowork):** `canon` (vivo) · `promote→ADR` (decisão a formalizar) · `archive` (história) · `bridge`
- **Colisão:** alguns números têm 2+ arquivos no git (drift histórico pré-checker ADR 0219). Marcados `(colisão)`.

---

## 🧠 T1 · Memória, Processo & Cowork Loop
*Como o time decide, lembra, governa e sincroniza.*

| Ref | Título | Local | Status |
|-----|--------|-------|--------|
| ADR 0010 | Sistema de memória do projeto (CLAUDE.md + /memory/) | git | aceita |
| ADR 0027 | Gestão de memória do projeto: papéis claros por função | git | aceita |
| ADR 0028 | ADRs com numeração monotônica + formato Nygard | git | aceita |
| ADR 0040 | Policy de publicação — Claude supervisiona, Wagner escala | git | aceita |
| ADR 0053 | MCP server da empresa: governança como produto | git | aceita |
| ADR 0055 | Self-host Team plan equivalente ao Anthropic Team/Enterprise | git | aceita |
| ADR 0056 | MCP server como fonte única de memória (chat + Claude Code) | git | aceita |
| ADR 0057 | `/team-mcp`: governança de tokens MCP + distribuição `.dxt` | git | aceita |
| ADR 0059 | Governança da memória estilo Team plan adaptado | git | aceita |
| ADR 0061 | Conhecimento canônico em git/MCP, ZERO auto-mem privada | git | aceita |
| ADR 0064 | Modularização — split TeamMcp + KB + Superadmin 360° | git | aceita |
| ADR 0069 | TaskRegistry MCP tools canônico (TASKS.md ASCII deprecated) | git | aceita |
| ADR 0070 | Jira-style task management no MCP (CURRENT/TASKS.md removidos) | git | aceita |
| ADR 0071 | Auditoria tools MCP 2026-05-05: bugs + workarounds | git | aceita |
| ADR 0072 | Maturação memória + Team MCP | git | aceita |
| ADR 0073 | Team MCP P0: skills e policies como entidades governadas | git | aceita |
| ADR 0074 | P1 Temporal validity bi-temporal + time-travel | git | aceita |
| ADR 0075 | Team MCP P0 v2: UI estilo prompt-management | git | aceita |
| ADR 0076 | Skills V2: DB primary, git destino, drift por-skill | git | aceita |
| ADR 0078 | Meta-skill ROI ERP autônomo | git | parc. superseded 0079 |
| ADR 0079 | Constituição do Oimpresso ERP (v1) | git | aceita |
| ADR 0080 | Trust Tiers operacional + Architecture & Scope + audit | git | aceita |
| ADR 0086 | Fase 5 MVP: Modules/Governance scaffold + ActionGate | git | aceita |
| ADR 0089 | Capterra-driven Module Evolution (skill + artefatos) | git | aceita |
| ADR 0091 | Daily Brief: contrato de contexto consolidado L7 | git | parc. superseded 0097 |
| ADR 0094 | Constituição v2 Oimpresso (mãe das 7 camadas) | git | aceita |
| ADR 0095 | Skills Tier A/B/C (convenção interna) | git | aceita |
| ADR 0097 | BRIEF generator usa gpt-4o-mini | git | aceita |
| ADR 0101 | Sistema Charter-Capterra (governança de escopo) `(colisão)` | git | aceita |
| ADR 0102 | Sprint S6 Charter-Capterra postmortem + S7 backlog `(colisão)` | git | aceita |
| ADR 0106 | Recalibração de velocidade (fator 10x com IA-pair) | git | aceita |
| ADR 0114 | Loop Cowork ↔ Claude Code formalizado via `prototipo-ui/` | git | aceita |
| ADR 0118 | Segregação de domínios externos/legacy em `memory/` | git | aceita |
| ADR 0119 | Paralelismo de sessões — `whats-active` Tier 1 `(colisão)` | git | aceita |
| ADR 0120 | Supersession metadata housekeeping | git | aceita |
| ADR 0124 | Curador: pipeline 5-fase de ingestão de conhecimento | git | aceita |
| ADR 0128 | Smoke testing E2E pós-cycle | git | aceita |
| ADR 0130 | Handoff append-only + MCP-first antes de escrever | git | aceita |
| ADR 0131 | Tiering de memória (canônico / máquina-local / segredo) | git | aceita |
| ADR 0133 | System health audit canônico | git | aceita |
| ADR 0134 | tasks-create respeita placeholders em SPEC.md | git | aceita |
| ADR 0144 | TaskRegistry: DB é canon de estado vivo, SPEC.md template | git | aceita |
| ADR 0147 | Cascade Review §10.4 — defesa em profundidade vs drift | git | aceita |
| ADR 0148 | Cascade Review §10.4 — Onda 6 roadmap memoria-senior | git | aceita |
| ADR 0153 | Rubrica `module-grade-v1` — nota 0-100 ponderada | git | superseded |
| ADR 0154 | Rubrica `module-grade-v2` — N/A justificado | git | superseded |
| ADR 0155 | `module-grade-v3` — Perf/LGPD/Security/Observability + CI gate | git | aceita |
| ADR 0156 | `module-grade-v3` errata (D9.a OtelHelper + na_justified) | git | aceita |
| ADR 0157 | `module-grade-v3` endurecimento D2 (parser XML + Pest) | git | aceita |
| ADR 0158 | `module-grade-v3` endurecimento D1 (recursive + scope) | git | aceita |
| ADR 0159 | `module-grade-v3` errata realismo meta 97.75 | git | aceita |
| ADR 0160 | `module-grade-v4` — Scoped Scorecards (Lens per Kind) | git | aceita |
| ADR 0161 | Governance v4 — aposentar 3 dos 4 hacks 0159 | git | aceita |
| ADR 0163 | Governance v4 metas alcançadas (4/4 buckets) | git | aceita |
| ADR 0164 | Screen Review PDCA · fase C (Check) automática pós-merge | git | aceita |
| ADR 0167 | Errata 0130: índice mantém histórico longo | git | aceita |
| ADR 0168 | PROTOCOLO WAGNER SEMPRE Tier A (IRREVOGÁVEL) | git | aceita |
| ADR 0169 | Errata 0168: RUNBOOK-onda-cowork.md 4º artefato canônico | git | aceita |
| ADR 0180 | Drift de número ADR 0178 (conflito paralelo) `(colisão)` | git | housekeeping |
| ADR 0195 | Feedback indexing: relevance scoring + decay + dedup `(colisão)` | git | aceita |
| ADR 0205 | Contract tests autosave — padrão canônico | git | aceita |
| ADR 0207 | Contract test obrigatório em PR de tela autosave (amends 0205) | git | aceita |
| ADR 0213 | Audit docs com gaps criam MCP tasks (loop fechado) | git | aceita |
| ADR 0224 | Hooks block-vs-advisory (Claude 4.8-aware) | git | aceita |
| ADR 0225 | Skills Tier A recalibração (Claude 4.8) | git | aceita |
| ADR 0226 | Brief v2 — 1M-aware, rico (≤8k) | git | aceita |
| ADR 0229 | Errata 0225 — medição empírica 25/66 skills | git | aceita |
| ADR 0230 | Método Governance Scorecard | git | aceita |
| ADR 0231 | Processo de Trabalho Canônico (especialista por área) | git | aceita |
| ADR 0233 | Ativação de memória no momento-decisão | git | aceita |
| ADR 0234 | Registry de Automações no MCP (hooks/crons/rotinas) | git | aceita |
| **ADR 0236** | **Harmonização DS sem perder qualidade + v4.2** (ex-`0200` Cowork) | cowork→git | aceita (esta sessão) |
| **ADR 0237** | **Carta de Design [CC] subordinada ao protocolo do git** (ex-`0201` Cowork) | cowork→git | aceita (esta sessão) |
| **CARTA_DESIGN_CC.md** | **Como [CC] obedece o git (subordinada · NÃO é lei)** | cowork→git | canon subordinado |
| PROTOCOL.md · CLAUDE_DESIGN_BRIEFING.md | **Constituição real do design (lei suprema)** | git | canon |
| STATUS.md | Espinha viva do Cowork (estado atual, lido 1º) | cowork | canon |
| MEMORIA_F3_ZEROTOUCH.md | Padrão zero-toque Wagner | cowork | promote→ADR |
| PLANO_ORGANIZACAO_CASA.md | Reset estrutural Cowork↔Repo | cowork | canon (executar) |
| COWORK_NOTES.md · CODE_NOTES.md · SYNC_LOG.md | Inbox/handoff/sync [CC]↔[CL] | cowork/bridge | canon |

## 🎨 T2 · Design System & UI
*Tokens, componentes, padrão visual, processo de migração de tela (MWART).*

| Ref | Título | Local | Status |
|-----|--------|-------|--------|
| ADR 0008 | Sidebar com 1 item + tabs horizontais no módulo | git | aceita |
| ADR 0009 | Protótipos em HTML+Tailwind+Chart.js (não React) | git | aceita |
| ADR 0011 | Alinhamento com padrão Jana (UltimatePOS) | git | aceita |
| ADR 0039 | UI Chat — "Cockpit" 3 colunas | git | aceita |
| ADR 0104 | Processo MWART canônico (único caminho Blade→Inertia) | git | aceita |
| ADR 0107 | Emendation 0104: visual comparison gate em F3 | git | aceita |
| ADR 0108 | Regressão visual via Pest 4 Browser snapshot (CI gate F4) | git | aceita |
| ADR 0109 | Claude Design plugin integrado ao processo MWART | git | aceita |
| ADR 0110 | Cockpit Pattern V2 (list+detail) canônico p/ MWART | git | aceita |
| ADR 0112 | MWART exceção — fix bugs UI `Whatsapp/Settings.tsx` | git | aceita |
| ADR 0141 | Skill `migracao-blade-react`: orquestrador Cowork→Inertia `(colisão)` | git | aceita |
| ADR 0149 | Screen-Pattern Reuse no MWART (Index blueprint p/ Show/Edit) | git | aceita |
| ADR 0165 | DS: breakpoints canon + mobile-first em Pages Inertia | git | aceita |
| ADR 0177 | MWART exceção: Cliente/Show Wave paralela paridade tabs | git | aceita |
| ADR 0179 | Cliente: drawer lateral 760px substitui Show.tsx full-page | git | aceita |
| ADR 0180 | Sidebar v3: 5 grupos canônicos + ghosts header + Cmd+K `(colisão)` | git | aceita |
| ADR 0182 | PageHeaderTabs pattern canon (telas com sub-navegação) | git | aceita |
| ADR 0185 | Drawer 760 escala pra entidades cadastrais do projeto | git | aceita |
| ADR 0187 | Constituição UI v2 (ponteiro canon) | git | aceita |
| ADR 0189 | PageHeader canon v3.1 (bloco fechado + KPI strip + ⋮ overflow) | git | aceita |
| ADR 0190 | Primary button interno = roxo médio universal | git | aceita |
| ADR 0195 | Tabs com autosave/state user-editável ficam mount-sempre `(colisão)` | git | aceita |
| ADR 0235 | DS v4: design system roxo universal + Claude Design owner UI `(colisão)` | git | aceita |
| **ADR 0236** | DS é piso · identidade via `--accent` · PT-03 cadastro · v4.2 (ex-`0200`) | cowork→git | aceita |
| CODE_DESIGN_CONTRACT.md | Contrato visual [CC]↔[CL] | cowork | canon |
| Design System v4.2 - Evolucao.html | Spec v4.2 (cockpit/fiscal/readiness/shortcut) | cowork | canon |
| Painel Cowork - Estado Atual.html | Espelho visual da espinha | cowork | canon |
| Auditoria - O Melhor de Cada Tela.html | Lista de proteção por tela | cowork | canon |
| Design System v3/v4.html | Versões anteriores do DS | cowork | archive |

## 🖥️ T3 · Telas & Módulos
*Escopo, ativação e estado por módulo/tela do ERP real.*

| Ref | Título | Local | Status |
|-----|--------|-------|--------|
| ADR 0099 | Modules/Project (legacy UPOS) — Discovery pré-deletion | git | aceita |
| ADR 0100 | ProjectMgmt UI Redesign: Linear-tier UX em 4 fases | git | aceita |
| ADR 0122 | Admin Center CT 100 | git | aceita |
| ADR 0123 | Modules/Arquivos como backbone | git | aceita |
| ADR 0125 | Modules/Autopecas como feature-wish (Vargas é sinal) `(colisão)` | git | feature-wish |
| ADR 0126 | Habilitar ComVis + Vestuario + OficinaAuto como projects MCP `(colisão)` | git | aceita |
| ADR 0136 | Sells: split Lista (default) vs Grade Avançada (toggle) | git | aceita |
| ADR 0137 | Modules/OficinaAuto qualificada | git | aceita |
| ADR 0151 | Modules/Comissao como feature-wish (aguarda dor real) | git | feature-wish |
| ADR 0152 | Modules/Pcp como feature-wish (aguarda Vargas/ComVis) | git | feature-wish |
| ADR 0171 | Ativação OficinaAuto · Piloto Martinho (faseada, faturável) | git | aceita |
| ADR 0172 | Deprecar Modules/Accounting → consolidar em Financeiro | git | aceita |
| ADR 0173 | Errata: tabelas Accounting usam nomes nus (não `accounting_*`) | git | aceita |
| ADR 0174 | Errata DEPRECATION Accounting: Ondas 3+4 SKIP | git | aceita |
| ADR 0175 | Fix Observer Financeiro: baixa sem `fin_contas_bancarias` | git | aceita |
| ADR 0178 | Sells: unificar Lista + Grade Avançada em tabs de Visão `(colisão)` | git | aceita |
| ADR 0183 | Caixa físico ↔ Financeiro: ponte canon multi-caixa | git | aceita |
| ADR 0184 | Errata 0183: NÃO deprecar `/cash-register/*` UPOS core | git | aceita |
| ADR 0192 | Auto-faturar OS → Venda via JobSheetObserver | git | aceita |
| ADR 0194 | Correção domínio OficinaAuto · mecânica pesada (não locação) | git | aceita |
| MEMORIA_VENDAS_CREATE_LARISSA.md | Contexto Vendas Create (persona Larissa) | cowork | canon |
| HANDOFF_CLIENTES/FINANCEIRO/PRODUTO_F1.md | Handoffs por módulo | cowork | bridge |
| GAPS_FINANCEIRO v1–v4.md | Gaps Financeiro (4 iterações) | cowork | archive (consolidar v4) |
| AUDITORIA_MODULOS.md | Auditoria de módulos | cowork | canon |
| Diagnóstico Vendas KB-9.75.html · Cadastro Contacts KB-9.75.html | Bench por tela | cowork | canon |
| Cadastro Cliente - Pagina Inteira DS 4.2.html | Molde PT-03 | cowork | canon |

## 🏗️ T4 · Arquitetura & Stack
*Base técnica do ERP real: runtime, deploy, modular, FSM.*

| Ref | Título | Local | Status |
|-----|--------|-------|--------|
| ADR 0001 | Estender UltimatePOS (em vez de build próprio ou fork) | git | aceita |
| ADR 0002 | Usar nWidart/laravel-modules | git | aceita |
| ADR 0005 | UUID p/ auditáveis, BigInt p/ lookups | git | aceita |
| ADR 0006 | Multi-tenancy lógica via `business_id` | git | aceita |
| ADR 0023 | Upgrade Inertia.js v2 → v3 (faseado) | git | aceita |
| ADR 0029 | Padrão Inertia + React + UltimatePOS p/ módulos novos | git | aceita |
| ADR 0038 | Promoção `6.7-bootstrap` → `main` | git | aceita |
| ADR 0042 | Reverb (self-hosted) substitui Pusher Cloud | git | superseded 0058 |
| ADR 0043 | Docker + Traefik + Portainer num LXC | git | aceita |
| ADR 0045 | Endpoint canônico Hostinger DNS API V1 | git | aceita |
| ADR 0058 | Reverb substituído por Centrifugo + FrankenPHP | git | aceita |
| ADR 0060 | IA + workers pesados na rede interna (Proxmox), app na Hostinger | git | aceita |
| ADR 0062 | Separação dura de runtime: Hostinger ≠ CT 100 Proxmox | git | aceita |
| ADR 0063 | Prevenir `composer.lock` drift permanentemente | git | aceita |
| ADR 0087 | Drift resolution sem mover URL — migration safe | git | aceita |
| ADR 0088 | Module rename PHP-only — fachada legacy na transição | git | aceita |
| ADR 0098 | `build:inertia` roda na Hostinger pós git-pull | git | aceita |
| ADR 0119 | Migration Factory — capacidade institucional `(colisão)` | git | aceita |
| ADR 0129 | State Machine canônica (FSM tabular custom + Spatie) | git | aceita |
| ADR 0143 | FSM Pipeline Canônico LIVE em prod biz=1 (2026-05-12) | git | aceita |
| ADR 0210 | Type safety end-to-end via Wayfinder | git | aceita |
| ADR 0211 | TanStack Query como padrão de data-fetching | git | aceita |
| ADR 0212 | Defensive logging em fallback paths | git | aceita |
| ADR 0214 | CT 100 MinIO bucket dedicado (docker-compose) | git | aceita |
| ADR 0216 | Deploy webhook: `composer dump-autoload -o` + `optimize` `(colisão)` | git | aceita |
| ADR 0235 | Ambiente de Staging no CT 100: clone anonimizado da prod `(colisão)` | git | aceita |

## 🤖 T5 · IA & Stack de IA
*Copiloto/Jana, memória vetorial, orquestração, observabilidade GenAI.*

| Ref | Título | Local | Status |
|-----|--------|-------|--------|
| ADR 0031 | `MemoriaContrato` + driver default `Mem0RestDriver` | git | aceita |
| ADR 0032 | Vizra ADK + Prism PHP (orquestração / wrapper LLM) | git | superseded 0048 |
| ADR 0033 | Vector store: pgvector vs Meilisearch+Scout vs Mem0 | git | aceita |
| ADR 0034 | Laravel AI ecosystem 2026: SDK + Boost + MCP + Vizra | git | aceita |
| ADR 0035 | Stack-alvo de IA do Copiloto (declaração canônica) | git | aceita |
| ADR 0036 | Replanejamento: Meilisearch primeiro, Mem0 por último | git | aceita |
| ADR 0041 | Stack QA de IA: Vizra eval + Langfuse + DeepEval | git | aceita |
| ADR 0046 | `ChatCopilotoAgent` precisa de contexto rico + tools | git | aceita |
| ADR 0047 | Wagner solo: sprint memória do agente (token economy) | git | aceita |
| ADR 0048 | Framework de agentes: `laravel/ai` (Vizra rejeitada) | git | aceita |
| ADR 0049 | Camadas de memória do agente: ligar fase por fase | git | aceita |
| ADR 0050 | 8 métricas obrigatórias de memória + tabela `memory_metrics` | git | aceita |
| ADR 0051 | Schema próprio + adapter + OpenTelemetry GenAI | git | aceita |
| ADR 0052 | `ContextoNegocio` expõe múltiplos ângulos por métrica | git | aceita |
| ADR 0054 | Pacote enterprise de busca de memória | git | aceita |
| ADR 0067 | Sprint 8: McpMemoryDocument Searchable + retrieval hybrid | git | aceita |
| ADR 0068 | Sprint 9: Ollama embedder + reranking (superar 0.72) | git | aceita |
| ADR 0092 | Rename `copiloto_*` → `jana_*` (renumerada de 0090) | git | aceita |
| ADR 0132 | Langfuse self-host CT 100 (observabilidade GenAI) | git | aceita |
| ADR 0141 | Agents IA com tool-use loop ("Claude Code") — Camada B v2 `(colisão)` | git | aceita |
| ADR 0142 | Notas internas como sinal de treino pra Jana | git | aceita |
| ADR 0145 | IA Administradora — pivot ADS↔FSM + piloto Cobradora | git | aceita |
| ADR 0150 | KB Unificado como Grafo de Conhecimento (módulo IA central) | git | aceita |
| ADR 0162 | OpenTelemetry Collector ativo em prod (CT 100) | git | aceita |
| ADR 0166 | Errata 0162: OTel SDK em `require-dev` (Hostinger shared) | git | aceita |
| ADR 0232 | Modelo de Peso Real — classificação/retrieval por meta | git | aceita |

## 🔌 T6 · Integrações & Domínio
*Officeimpresso/Delphi, ponto, connector, fiscal (NF-e/SEFAZ), WhatsApp, contacts legacy.*

| Ref | Título | Local | Status |
|-----|--------|-------|--------|
| ADR 0013 | Ecossistema de Módulos: inventário, categorias, padrões | git | aceita |
| ADR 0014 | Integração PontoWR2 × Essentials (HRM) | git | aceita |
| ADR 0015 | Connector: API Gateway p/ integrações externas | git | aceita |
| ADR 0019 | Delphi legado não autentica após upgrade 3.7→6.7 | git | aceita |
| ADR 0020 | Grupo econômico (matriz + filiais) no Officeimpresso | git | aceita |
| ADR 0021 | Contrato real da API consumida pelo Delphi | git | aceita |
| ADR 0024 | Instalação 1-clique padronizada p/ todos os módulos | git | aceita |
| ADR 0025 | Redesign landing pública (`Modules/Cms`) em Inertia/React | git | aceita |
| ADR 0066 | `format_date` +3h preservado — quirk legacy ROTA LIVRE | git | aceita |
| ADR 0090 | NFe replace gradual: `app/Services` → `Modules/NfeBrasil` | git | aceita |
| ADR 0096 | Módulo Whatsapp: Z-API/Baileys + Meta fallback (Evolution OFF) | git | aceita |
| ADR 0102 | UI status NFC-e via polling JSON (broadcast adiado) `(colisão)` | git | aceita |
| ADR 0103 | Eventos fiscais Laravel separados por modelo NFe | git | aceita |
| ADR 0111 | Emenda 5 ao 0096: bypass Meta-fallback per-business (biz=1) | git | aceita |
| ADR 0113 | Integração Delphi WR Comercial ↔ Laravel ↔ ADs (3 caminhos) | git | aceita |
| ADR 0116 | Pivot Gold: Manifestação do Destinatário substitui NF-e 55 | git | aceita |
| ADR 0117 | Múltiplos números Whatsapp por business | git | aceita |
| ADR 0135 | Omnichannel inbox (Channel polimórfico + Driver pattern) | git | aceita |
| ADR 0146 | `contact_lid` como chave canônica de identidade WhatsApp | git | feature-wish |
| ADR 0170 | `Modules/PaymentGateway`: extração da camada de cobrança `(colisão ×3)` | git | aceita |
| ADR 0178 | Restauração dos campos fiscais BR em `contacts` (regressão 6.7) `(colisão)` | git | aceita |
| ADR 0186 | Chain de certificado A1 + SEFAZ ConsultaCadastro | git | aceita |
| ADR 0188 | Contatos multi-type · flags aditivas | git | aceita |
| ADR 0197 | Extend `contacts` p/ absorver `PESSOAS` legacy · Fase 1 | git | aceita |
| ADR 0198 | Hot/Cold tiering p/ migração transacional histórica | git | aceita |
| ADR 0199 | Errata Bucket B · JSON catch-all (amends 0197) | git | aceita |
| ADR 0200 | `contacts` adota canon sync bidirecional (Wagner 2024-11) | git | aceita |
| ADR 0201 | Receita Federal + SEFAZ ConsultaCadastro = padrão coleta cadastral BR | git | aceita |
| ADR 0202 | WhatsApp Meta Cloud default universal + BaileysDriver OUT | git | proposta |
| ADR 0203 | Pipeline legacy-migration Firebird → oimpresso (Wave 29-1) | git | aceita |
| ADR 0204 | WhatsApp whatsmeow Go driver (substituto Baileys, amend 0202) | git | aceita |
| ADR 0206 | Whatsmeow profissionalização: SM + Reconciler + circuit breaker | git | aceita |

## 💼 T7 · Negócio & Posicionamento
| Ref | Título | Local | Status |
|-----|--------|-------|--------|
| ADR 0016 | Plano de Otimização e Roadmap PontoWR2 | git | aceita |
| ADR 0022 | Meta financeira oimpresso: R$ 5 milhões/ano | git | aceita |
| ADR 0026 | Posicionamento: ERP de Comunicação Visual com IA | git | aceita |
| ADR 0037 | Roadmap evolução pós-Sprint 5: Tier 5-6 → Tier 7-9 | git | aceita |
| ADR 0105 | Cliente como sinal + guiar sem mandar | git | aceita |
| ADR 0115 | Recuperação cliente Gold via bundle oimpresso + NF-e 55 | git | aceita |
| ADR 0121 | oimpresso é ERP modular especializado por vertical | git | aceita |
| ADR 0140 | JANA Pro: produto comercial SaaS de IA pra PMEs BR | git | aceita |

## 🔒 T8 · Segurança & Governança de Dados
*Append-only, credenciais, identidade, multi-tenant, supply-chain, gates de qualidade, LGPD.*

| Ref | Título | Local | Status |
|-----|--------|-------|--------|
| ADR 0003 | Marcações append-only (triggers MySQL + app layer) | git | aceita |
| ADR 0004 | Tabela bridge `ponto_colaborador_config` | git | aceita |
| ADR 0007 | Banco de horas como ledger append-only | git | aceita |
| ADR 0017 | Officeimpresso restaurado como módulo Superadmin exclusivo | git | aceita |
| ADR 0018 | Officeimpresso superadmin · log de acesso passivo | git | aceita |
| ADR 0030 | Credenciais sensíveis: nunca em git | git | aceita |
| ADR 0044 | Vaultwarden self-hosted como cofre de credenciais | git | aceita |
| ADR 0065 | Permission Registry — contrato declarativo per-módulo | git | aceita |
| ADR 0077 | Identity Mesh (v1) | git | superseded 0081 |
| ADR 0081 | Identity Mesh: `mcp_actors` + manifest + seed inicial | git | aceita |
| ADR 0084 | Triggers MySQL append-only em `mcp_audit_log` + correção | git | aceita |
| ADR 0085 | Fase 3.4 SCOPE.md + ActorResolver + PII Redactor | git | aceita |
| ADR 0093 | Multi-tenant isolation by default (Tier 0, IRREVOGÁVEL) | git | aceita |
| ADR 0101 | Tests SEMPRE `business_id=1`, NUNCA cliente real `(colisão)` | git | aceita |
| ADR 0126 | Vault chunked encryption Sprint 2 `(colisão)` | git | proposta |
| ADR 0127 | Modules/Auditoria — undo + activity log | git | aceita |
| ADR 0191 | Microsoft Clarity: session replay + heatmap LGPD-compliant | git | aceita |
| ADR 0208 | Larastan/PHPStan baseline ratchet | git | aceita |
| ADR 0209 | ESLint 9 flat-config baseline ratchet | git | aceita |
| ADR 0215 | Secrets governance — 5 camadas automáticas | git | aceita |
| ADR 0216 | Governance drift framework — DriftChecker plugável `(colisão)` | git | aceita |
| ADR 0217 | Composer audit checker — supply-chain detection | git | aceita |
| ADR 0218 | Multi-tenant scope checker (Tier 0) | git | aceita |
| ADR 0219 | ADR links checker — integridade canon da memória | git | aceita |
| ADR 0220 | Charters freshness checker (adapter) | git | aceita |
| ADR 0221 | Routes zombie checker — blast radius | git | aceita |
| ADR 0222 | Renovate config — supply-chain defense | git | aceita |
| ADR 0223 | npm audit checker — frontend supply-chain | git | aceita |

## 🌉 T9 · Ponte Cowork → Code (handoff zero-toque)
| Ref | Título | Local | Status |
|-----|--------|-------|--------|
| CLAUDE_CODE_BRIEFING.md | Briefing pro Code | bridge | canon |
| PROMPT_PARA_CODE_*.md · PROMPT_v3/v4 | Prompts de sync (vários) | bridge | archive (após processados) |
| FORCE_OVERWRITE_V3_PARA_CODE.md · COWORK_RESPONSE_PR295.md | Syncs específicos | bridge | archive |
| prototipo-ui-patch/ | Espelho 1:1 do repo p/ patches | bridge | canon |

---

## 🔧 Manutenção
- **Cobertura:** ADRs **0001–0237** classificados (backfill 0042–0235 feito pelo Claude Code a partir dos títulos
  reais do git; seed Cowork cobria 0001–0041 + 0114/0190 + as duas ADRs da sessão).
- **Lacunas conhecidas no git** (números sem arquivo): 0012, 0082–0083, 0138–0139, 0176, 0181, 0193, 0196, 0227–0228.
- **Colisões de número** (2+ arquivos): 0101, 0102, 0119, 0125/0126, 0141, 0170 (×3), 0178, 0180, 0195, 0216, 0235 —
  drift histórico pré-checker ADR 0219. Aqui listei a decisão dominante por número + nota `(colisão)`.
- **Regerar a cada novo ADR:** ao aceitar um ADR novo, adicionar 1 linha no tema certo aqui **e** no
  `memory/decisions/README.md` (índice cronológico).
- **Fonte de verdade do lifecycle:** `decisions-search` (MCP) + [`_INDEX-LIFECYCLE.md`](decisions/_INDEX-LIFECYCLE.md).
  Os `Status` acima são best-effort por título.
