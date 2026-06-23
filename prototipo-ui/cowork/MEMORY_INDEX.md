# MEMORY_INDEX.md — Índice Temático da Memória (Cowork + Git)

> **Indexado sob a constituição vigente:** ADR 0094 (Oimpresso V2) + ADR UI-0013 (UI v2) + ADR 0235 (roxo 295) + ADR 0236 (governança-doc) + ADR 0238 (soberania-[W]).
> **Fonte única de design = `memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md` (git, ADR 0236).** Este índice é **derivado/temático** — NUNCA autoridade. ADR que já vive no git: **aponto, não re-tabulo nem cunho número** (lição 0200/0201).
> Este índice é **derivado** dessa perspectiva. **Mudar a constituição = reindexar TODO o
> diretório** sob a nova lente — operação **só [W] autoriza + versiona**; [CC] propõe o plano,
> nunca dispara.
>
> **Busca por tema, não por número.** Une as duas memórias: a **grande memória do git**
> (`wagnerra23/oimpresso.com` · `memory/decisions/` · ~190 ADRs Nygard, numeração monotônica
> ADR 0028) e a **memória do Cowork** (este projeto). Para "o que decidimos sobre X?", venha aqui.
>
> Mantido por [CC]. Seed agente → **Claude Code completa do git** (tem acesso total ao repo).
> **Última att.: 2026-06-10** (DS-GUARD runtime: `qa-conformance.js` v2 G1–G6 + ritual §8.1/8.2 do PROCESSO + check `licao_sem_assercao` no memory-health — sessões `2026-06-10-*`; antes: 2026-06-04 reconciliação C1–C3/C5/C7). Git index oficial (`memory/decisions/README.md`) está
> defasado (2026-04-24) e agora carrega banner-tombstone apontando ESTE arquivo como índice único vivo — este é o complemento **temático**.
> **⚠ NOTA 06-07 pendente de absorção em T2:** ADR 0253/0254 (primitivos layout + grade) + DESIGN.md 06-06 — ver STATUS 2026-06-07.
> **DS vigente (C3 resolvido 06-04):** `ds-v5/*` = DS ativo · `ds-v6/*` = régua/contrato aditivo · v4.x = histórico. **D-02 = RESOLVIDO roxo** (não é mais proposta aberta).

## Legenda
- **Local:** `git` (canônico no repo) · `cowork` (este projeto) · `bridge` (ponte p/ Code)
- **Fate (docs cowork):** `canon` (vivo) · `promote→ADR` (decisão a formalizar) · `archive` (história) · `bridge`

---

## 🧠 T1 · Memória, Processo & Cowork Loop
*Como o time decide, lembra e sincroniza.*

| Ref | Título | Local | Status |
|-----|--------|-------|--------|
| ADR 0010 | Sistema de memória do projeto (CLAUDE.md + /memory/) | git | aceita |
| ADR 0027 | Gestão de memória com roles claros | git | aceita |
| ADR 0028 | ADRs com numeração monotônica | git | aceita |
| ADR 0040 | Policy de publicação — Claude supervisiona | git | aceita |
| ADR 0114 | Protótipo-UI cowork loop formalizado (6 papéis × 7 fases) | git | aceita |
| **_PROPOSTA-ds-harmonizacao** | **Harmonização DS + v4.2** — proposta F0 (Code numera; **não cunhar — 0200 do git = Contacts**) | cowork | proposta |
| **_PROPOSTA-ratificacao-design** | **Carta [CC] subordinada** — proposta F0 (Code numera; **0201 do git = SEFAZ**) | cowork | proposta |
| **ADR 0238** | **Soberania de [W] sobre a constituição** (`0238-soberania-constituicao-wagner.md` · **canon em main · PR #2007**) | git | aceito |
| **PROTOCOL §10.4** | **Gate [CL]-valida-prompt-[CC]** (proposta≠ordem; bloqueia ADR-duplicado/renome/nº-alucinado · não depende de [W]) | git | aceito |
| **_PROPOSTA-ADR-governanca-executavel-trio-dominio-e2e** | **Governança executável** — trio-de-tela + caso↔teste + Playwright + dicionário-domínio viram **gates de CI ratchet** (estende `_PROPOSTA-0244-estratégia-teste`; [CL] numera — ⚠ **0244 do git pode ser ds-v5 #2123**, confirmar nº livre via §10.4). Autorizada [W] 2026-06-09. Handoff transportado. | cowork | proposta |
| **_PROPOSTA-ADR-oficina-reparo-erradica-locacao** | **Oficina = reparo** — erradica `order_type=locacao` (resíduo da ADR 0194); "Caçambas"=nome do cliente; semeia o dicionário de domínio. [W] override "é alucinação". | cowork | proposta |
| feedback-cowork-sync-now-prompt-stale | **Lição L-09** — meu prompt stale mandou re-numerar 0238 + renomear 0235/0236; pego pelo [CL] | git | canon |
| **CARTA_DESIGN_CC.md** | **Como [CC] obedece o git (subordinada · NÃO é lei)** | cowork→git | canon subordinado |
| ~~CONSTITUICAO.md~~ | **RETIRADO** → lápide aponta `CARTA_DESIGN_CC.md` (`_PROPOSTA-ratificacao-design`) | cowork | superseded |
| PROTOCOL.md · CLAUDE_DESIGN_BRIEFING.md | **Constituição real do design (lei suprema)** | git | canon |
| STATUS.md | Espinha viva (estado atual, lido 1º) | cowork | canon |
| **`_arquivo/INDEX.md`** | **Manifesto do arquivo de design (v1.0) — origem→destino de tudo que saiu da raiz** | cowork | canon |
| PLANO_ORGANIZACAO_CASA.md | Reset estrutural Cowork↔Repo | cowork | **executado** (faxina 2026-05-30 → `_arquivo/`) |
| MEMORIA_F3_ZEROTOUCH.md | Padrão zero-toque Wagner | cowork | promote→ADR |
| COWORK_NOTES.md · CODE_NOTES.md · SYNC_LOG.md | Inbox/handoff/sync entre [CC]↔[CL] | cowork/bridge | canon |

## 🎨 T2 · Design System & UI
*Tokens, componentes, padrão visual.*

| Ref | Título | Local | Status |
|-----|--------|-------|--------|
| ADR 0008 | Sidebar única + tabs horizontais | git | aceita |
| ADR 0009 | Protótipos em HTML puro (não React) | git | aceita |
| ADR 0011 | Alinhamento com padrão Jana (UltimatePOS) | git | aceita |
| ADR 0039 | UI Chat — Cockpit V2 padrão | git | aceita |
| ADR 0190 | Primary roxo universal (PageHeader) | git | aceita |
| _PROPOSTA-ds-harmonizacao | DS é piso · identidade via `--accent` · PT-03 cadastro · v4.2 — **proposta** (canon real = ADR 0235 roxo) | cowork | proposta |
| CODE_DESIGN_CONTRACT.md | Contrato visual [CC]↔[CL] | cowork | canon |
| **ds-v5/*** | **DS ÚNICO ATIVO** (tokens+components · roxo canon · `[data-theme]×[data-density]`) — ⚠ por STATUS (e): ADR **0244** no git (#2123) | cowork→git | **canon ativo** |
| **ds-v6/*** | **Régua/contrato aditivo** (showcase 11 comp · receita · gabarito-vendas) — ⚠ #2165 referência | cowork→git | **canon régua** |
| Design System v4.2 - Evolucao.html | Spec v4.2 — **superado por v5/v6** | cowork | **histórico** |
| Painel Cowork - Estado Atual.html | Espelho visual da espinha | cowork | canon |
| Auditoria - O Melhor de Cada Tela.html | Lista de proteção por tela | cowork | canon |
| Design System v3/v4.html | Versões anteriores do DS | cowork | histórico |

## 🖥️ T3 · Telas & Módulos
*Estado e diagnóstico por tela.*

| Ref | Título | Local | Status |
|-----|--------|-------|--------|
| MEMORIA_VENDAS_CREATE_LARISSA.md | Contexto Vendas Create (persona Larissa) | cowork | canon |
| HANDOFF_CLIENTES / FINANCEIRO / PRODUTO_F1.md | Handoffs por módulo | cowork | bridge |
| GAPS_FINANCEIRO v1–v4.md | Gaps Financeiro (4 iterações) | cowork | archive (consolidar v4) |
| AUDITORIA_MODULOS.md | Auditoria de módulos | cowork | canon |
| Diagnóstico Vendas KB-9.75.html | Bench Vendas | cowork | canon |
| Cadastro de Contacts - Diagnóstico KB-9.75.html | Diagnóstico cadastro Cliente | cowork | canon |
| Cadastro Cliente - Pagina Inteira DS 4.2.html | Molde PT-03 | cowork | canon |
| Piloto Vendas - Antes Depois.html | Piloto harmonização | cowork | canon |

## 🏗️ T4 · Arquitetura & Stack
*Base técnica do ERP real.*

| Ref | Título | Local | Status |
|-----|--------|-------|--------|
| ADR 0001 | Estender UltimatePOS (opção C) | git | aceita |
| ADR 0002 | nWidart/laravel-modules | git | aceita |
| ADR 0005 | UUID p/ auditáveis, BigInt p/ lookups | git | aceita |
| ADR 0006 | Multi-tenancy lógica via business_id | git | aceita |
| ADR 0023 | Upgrade Inertia v2 → v3 | git | aceita |
| ADR 0029 | Padrão Inertia/React UltimatePOS | git | aceita |
| ADR 0038 | Promoção bootstrap → main | git | aceita |
| LARAVEL_REPO_CONTEXT.md · ARQUITETURA.md | Contexto do repo real | cowork | canon |

## 🤖 T5 · IA & Stack de IA
*Copiloto, memória vetorial, orquestração.*

| Ref | Título | Local | Status |
|-----|--------|-------|--------|
| ADR 0031 | MemóriaContrato — mem0 default | git | aceita |
| ADR 0032 | Vizra ADK + Prism PHP (orquestração) | git | aceita |
| ADR 0033 | Vector store — Meilisearch + pgvector + mem0 | git | aceita |
| ADR 0034 | Laravel AI SDK oficial + Boost MCP | git | aceita |
| ADR 0035 | Stack AI canônica (Wagner 2026-04-26) | git | aceita |
| ADR 0036 | Replanejamento Meilisearch-first | git | aceita |
| ADR 0041 | Stack QA IA — Vizra + Langfuse + DeepEval | git | aceita |

## 🔌 T6 · Integrações & Domínio
*Officeimpresso/Delphi, ponto, connector, fiscal.*

| Ref | Título | Local | Status |
|-----|--------|-------|--------|
| ADR 0013 | Ecossistema de módulos / inventário | git | aceita |
| ADR 0014 | Essentials + PontoWR2 integração | git | aceita |
| ADR 0015 | Connector — API gateway | git | aceita |
| ADR 0019/0020/0021 | Officeimpresso Delphi · grupo econômico · contrato API | git | aceita |
| ADR 0024 | Instalação 1-clique de módulos | git | aceita |
| ADR 0025 | CMS redesign Inertia/React | git | aceita |

## 💼 T7 · Negócio & Posicionamento
| Ref | Título | Local | Status |
|-----|--------|-------|--------|
| ADR 0016 | Plano de otimização e roadmap | git | aceita |
| ADR 0022 | Meta R$5mi/ano (financeira) | git | aceita |
| ADR 0026 | Posicionamento ERP gráfico com IA | git | aceita |
| ADR 0037 | Roadmap evolução tier-7+ | git | aceita |

## 🔒 T8 · Segurança & Governança de Dados
| Ref | Título | Local | Status |
|-----|--------|-------|--------|
| ADR 0003 | Marcações append-only (triggers + app) | git | aceita |
| ADR 0004 | Tabela bridge ponto_colaborador_config | git | aceita |
| ADR 0007 | Banco de horas como ledger append-only | git | aceita |
| ADR 0017/0018 | Officeimpresso superadmin · log acesso passivo | git | aceita |
| ADR 0030 | Credenciais jamais em git | git | aceita |

## 🌉 T9 · Ponte Cowork → Code (handoff zero-toque)
| Ref | Título | Local | Status |
|-----|--------|-------|--------|
| CLAUDE_CODE_BRIEFING.md | Briefing pro Code | bridge | canon |
| PROMPT_PARA_CODE_*.md · PROMPT_v3/v4 | Prompts de sync (vários) | bridge | archive (após processados) |
| FORCE_OVERWRITE_V3_PARA_CODE.md · COWORK_RESPONSE_PR295.md | Syncs específicos | bridge | archive |
| prototipo-ui-patch/ | Espelho 1:1 do repo p/ patches | bridge | canon |

---

## ⚠️ Gap a completar (Claude Code, do git)
Este seed cobre ADRs **0001–0041** + **0114, 0190, 0200**. Faltam **0042–0189** (visíveis só
no git). **Tarefa pro Code:** ler `memory/decisions/0042..0189`, classificar em T1–T9 e completar
esta tabela; commitar como `memory/INDEX_TEMATICO.md` no repo. Regerar a cada novo ADR.
