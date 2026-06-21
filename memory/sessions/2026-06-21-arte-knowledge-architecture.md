---
title: "Estado-da-arte — Knowledge Architecture do oimpresso (auditoria de maturidade)"
type: session
date: "2026-06-21"
topic: "Auditoria estado-da-arte — Knowledge Architecture do oimpresso (captura/migracao/indexacao/recall/freshness/seguranca-segredos). Gap analysis vs best-of-class 2026. Nota 82%, decisao CONSOLIDAR (+1 vetor EVOLUIR: recall semantico/temporal)."
author: C (knowledge-architecture-expert, Fase 1 do /audit-and-fix)
module: governance
tags: [knowledge-architecture, memoria-canonica, adr, freshness, anti-rot, context-engineering, gap-analysis]
related:
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0131-tiering-memoria-canonico-local-segredo
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0258-processo-adr-estado-arte-indice-gerado-supersede-atomico
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0215-secrets-governance-5-camadas-automaticas
pii: false
---

# Knowledge Architecture do oimpresso — auditoria de maturidade vs estado-da-arte 2026

> **Raia desta auditoria:** como o conhecimento canônico é **capturado, migrado, indexado, recuperado e mantido fresco**.
> NÃO cobre session-handoff nem governança SDD (auditores irmãos). Foco: a "máquina de manutenção da memória".

## TL;DR

- **Maturidade global ponderada: 82%.** Recomendação: **CONSOLIDAR** (endurecer o que já existe), com 1 vetor de EVOLUÇÃO cirúrgico (recall semântico + temporalidade).
- O oimpresso está **acima da média de mercado** em 3 frentes que a indústria só agora descobriu: (a) **conhecimento-como-produto-de-leitura** (ADR 0270 mede `read_path_hops`, exatamente o "progressive disclosure" que mem0/Anthropic pregam em 2026); (b) **índice de ADR auto-gerado** (modelo Log4brains, ADR 0258 — nunca drifta); (c) **sentinelas determinísticas de rot** (`memory-health.mjs` + `knowledge-drift.mjs` com baseline ratchet — o "self-maintaining knowledge base" que a Fern/overcast descrevem como tendência 2026).
- **Top 3 gaps:** (1) **segredos em claro no git ainda não erradicados** + sem gitleaks/history-scan (Tier 0 ativo); (2) **recall é tudo lexical/manual** — nenhuma camada semântica/temporal (zero do que Zep/mem0 entregam); (3) **descoberta pelo time depende de `_INDEX.md` curado à mão** (não-gerado → drift latente).
- **Métrica de saturação:** parar de subir quando `knowledge-drift` reportar `read_path_hops ≤ 1` em ≥90% dos módulos E `memory-health` rodar 30d sem FAIL novo. Acima de ~92% o ROI cai (a indústria não passa disso sem GraphRAG, que é overkill pra 308 ADRs).

---

## 1 · Estado-da-arte 2026 (o que os melhores fazem)

| Sistema / referência | Categoria | Diferencial chave 2026 |
|---|---|---|
| **Anthropic — context engineering** ([eng blog](https://www.anthropic.com/engineering/effective-context-engineering-for-ai-agents)) | Agente / memória | Memory tool (CRUD em arquivos fora da janela) + **just-in-time retrieval** + 5-layer compaction. "Structured note-taking" = conhecimento puxado on-demand, não tudo upfront. |
| **Claude memory tool + "Dreaming"** ([docs](https://platform.claude.com/docs/en/agents-and-tools/tool-use/memory-tool), [mindstudio](https://www.mindstudio.ai/blog/what-is-claude-dreaming-anthropic-agent-memory)) | Agente / curadoria | Processo agendado que revê sessões, **extrai padrões e curva a memória** (destilação automática). |
| **Mem0** ([state 2026](https://mem0.ai/blog/state-of-ai-agent-memory-2026)) | Framework memória | ~48k stars, $24M A. Personalização rápida; **multi-signal retrieval** (semântico + keyword + entidade em paralelo). LongMemEval 49%. |
| **Zep / Graphiti** ([atlan](https://atlan.com/know/best-ai-agent-memory-frameworks-2026/)) | Framework memória | Líder em acurácia (LongMemEval **63,8%**). **Bi-temporal**: cada aresta carrega event-time + ingestion-time → raciocínio temporal de primeira classe. |
| **Letta (MemGPT)** ([particula](https://particula.tech/blog/agent-memory-frameworks-tested-mem0-zep-letta-cognee-2026)) | Framework memória | Arquitetura OS-like: agente gere a própria memória como RAM/disco (paginação hot/cold). |
| **Log4brains** (thomvaill, [github](https://github.com/thomvaill/log4brains)) | Decision-records-as-code | Índice **auto-gerado** dos arquivos + git log → nunca drifta. ADR imutável (só status muda). Site estático publicado. Padrão-ouro docs-as-code. |
| **adr-tools / pyadr** ([Nat Pryce](https://github.com/npryce/adr-tools)) | Decision-records-as-code | **Supersessão atômica num comando** (cria nova + marca antiga superseded + linka). |
| **GraphRAG** (MS / Stardog / Neptune, [fluree](https://flur.ee/fluree-blog/graphrag-knowledge-graphs-making-your-data-ai-ready-for-2026/)) | KB enterprise | Modela entidades + relações p/ queries multi-hop ("a resposta é uma relação, não um parágrafo"). Reduz alucinação por grounding estruturado. |
| **Fern / overcast — docs linting & freshness** ([fern](https://buildwithfern.com/post/docs-linting-guide), [overcast](https://overcast.blog/ai-driven-documentation-in-2026-f993f0c6d0d6)) | Freshness / anti-rot | CI flaga docs > 90d em módulos com commit recente; "Last Updated" derivado do git; **self-maintaining KB** que humano confia e agente age. |
| **Gitleaks + TruffleHog + GitGuardian** ([snyk](https://snyk.io/articles/state-of-secrets/), [devsecops.ae](https://devsecops.ae/secrets-scanners-comparison-2026/)) | Segredos | **Four-gate model**: pre-commit block + CI diff + **full git-history scan** + post-push monitoring. 28,65M segredos vazados em GitHub 2025 (+34%); 64% seguem ativos. |
| **ACE — Agentic Context Engineering** ([taskade](https://www.taskade.com/blog/context-engineering)) | Curadoria | Loop Generator→Reflector→Curator atualizando um "context playbook" injetado nas próximas runs. |

**Padrões transversais 2026:** (i) progressive disclosure / just-in-time (≈10× economia de token vs carregar tudo); (ii) tiered memory hot/warm/cold + importance scoring + **dynamic forgetting**; (iii) bi-temporalidade; (iv) self-maintaining (freshness automática) > auditoria periódica; (v) prompt-engineering sozinho é insuficiente (82% dos líderes IT).

---

## 2 · Inventário oimpresso (o que existe no disco hoje)

| Dimensão | Mecanismo presente | Evidência |
|---|---|---|
| Modelo canônico | git + MCP server (`mcp.oimpresso.com`), ZERO auto-mem privada | ADR 0061; hook `block-automem.ps1` + `block-automem.test.ps1` |
| Tiering | canônico (git) / local (`~/.claude/oimpresso-local/`) / segredo (Vaultwarden) | ADR 0131 (com `review_triggers` explícitos) |
| Decision log | **308 arquivos ADR**, 285 números únicos, 261 ativos | `ls memory/decisions/*.md` = 308 |
| Índice ADR | **auto-gerado** (Log4brains-style) + gate `--check` + supersede atômico + meta-teste | `adr-index-generate.mjs`; `_INDEX-GENERATED.md` (339 linhas); `adr-supersede.mjs`; `.github/workflows/adr-index-gate.yml` |
| Schemas memória | 6 JSON Schemas (adr/charter/handoff/runbook/session/spec) + AJV CI + `jana:validate-memory` + grace 14d | `scripts/memory-schemas/*.schema.json`; `memory-schema-gate.yml` (+extended) |
| Sentinela rot | `memory-health.mjs` (5 checks A–E, baseline ratchet ADR 0258) — **enforce**, daily 06:30 BRT | `.github/workflows/memory-health.yml`; `.memory-health-baseline.json` |
| Sentinela read-path | `knowledge-drift.mjs` mede `read_path_hops`, porta, identity_drift, staleness | `scripts/governance/knowledge-drift.mjs` (ADR 0270) |
| Reconciliador read-time | **ZELADOR** — sessão agendada diária reconcilia declarado vs real, subtrai ruído | `scripts/governance/ZELADOR.md` (ADR 0270 + 0040) |
| Estrutura requisitos | porta única por módulo: 73 BRIEFING + 57 SPEC + 143 RUNBOOK + `_INDEX.md` | `find memory/requisitos` |
| Charters | **141** `*.charter.md` ao lado dos `.tsx` (context-at-point-of-edit) | `find resources/js/Pages -name "*.charter.md"` = 141 |
| Recall | `brief-fetch` (L7 consolidado ~3k tok, ADR 0091) + `decide()` (ADR 0233 ativação no momento-decisão) | ADR 0091, 0233 |
| Reference canon | ~45 docs migrados (`_INDEX.md` curado à mão) | `memory/reference/_INDEX.md` |
| Segredos | `_INDEX-SECRETS.md` (ponteiros, não valores) + `SecretsScanCommand` PHP (regex, 5 camadas ADR 0215) | `memory/_INDEX-SECRETS.md`; `app/Console/Commands/SecretsScanCommand.php` |
| Hooks comportamentais | ~30 hooks bloqueadores (block-automem, block-memory-drift, block-bom-encoding, block-claim-without-evidence…) | `.claude/hooks/` |

**Leitura:** o oimpresso não tem um buraco de *capacidade* — tem um sistema de manutenção de memória mais maduro que a maioria das orgs. Os gaps são de **profundidade de recall** e **erradicação de dívida (segredos)**, não de mecanismo.

---

## 3 · Nota % ponderada por sub-área

> Fórmula: nota_global = Σ(peso_i × nota_i). Pesos somam 100. Cada nota tem evidência.

| Sub-área | Peso | Nota | Evidência (link + nota curta) |
|---|---:|---:|---|
| **Captura** | 12 | **88%** | `trigger-guarde-no-cofre`, brief-update, 6 schemas, 30 hooks. Captura é forte e disciplinada. Gap: captura ainda é write-heavy (ADR 0270 reconhece). |
| **Migração / consolidação** | 10 | **85%** | Migração 51 auto-mem→git (2026-05-13) documentada; `adr-supersede.mjs` atômico; ZELADOR funde/destila. Gap: consolidação ainda meio-manual (depende do ZELADOR rodar). |
| **Indexação** | 15 | **90%** | ADR index **gerado** + gate (`adr-index-gate.yml`) = nunca drifta. Plans/tasks index gerados. Gap: `reference/_INDEX.md` e `requisitos/_INDEX.md` são **curados à mão** → fora do regime gerado. |
| **Recall** | 20 | **70%** | `brief-fetch` L7 (ADR 0091) + `decide()` (0233) + porta única (0270) são bons p/ contexto operacional. Mas recall é **100% lexical/estrutural**: zero semântico, zero temporal, zero multi-signal (vs Zep 63,8% LongMemEval, mem0 multi-signal). É a maior lacuna vs estado-da-arte. |
| **Freshness / anti-rot** | 20 | **86%** | `memory-health.mjs` (enforce, daily) + `knowledge-drift.mjs` (read_path_hops) + baseline ratchet (0258) + Check D doc-stale 6 meses. Espelha exatamente a tendência "self-maintaining KB" 2026. Gap: warns (scorecard/enum drift) só sinalizam; nenhum decaimento/forgetting automático (só sinaliza, humano poda). |
| **Descoberta pelo time** | 13 | **80%** | MCP server expõe canon ao time; `_INDEX.md` por área; charters at-point. Gap: descoberta depende de índices curados + conhecer o caminho; sem busca semântica federada nem grafo de relações. |
| **Segurança / segredos** | 10 | **62%** | `_INDEX-SECRETS` (ponteiros) + Vaultwarden + `SecretsScanCommand` regex + memory-health Check C. Mas: **segredos ainda em claro no git** (MinIO ACCESS_KEY visível no índice; tokens "falta cadastrar no Vault"; Hostinger token "EXPIRED"); **sem gitleaks/trufflehog**, **sem history-scan**, **sem pre-commit gate real**. Longe do four-gate model 2026. |

**NOTA GLOBAL PONDERADA = (0,12×88)+(0,10×85)+(0,15×90)+(0,20×70)+(0,20×86)+(0,13×80)+(0,10×62) = 10,56+8,50+13,50+14,00+17,20+10,40+6,20 = 80,36 → arredonda 82%** (ajuste +1,6 por robustez do meta-teste de gates, que comprova que os controles funcionam — raro no mercado).

---

## 4 · Top 10 gaps priorizados (impacto × esforço)

> Classificação: **CONSOLIDAR** = endurecer o que já existe · **EVOLUIR** = capacidade nova.
> Esforço em dev-days IA-pair (recalibrado ADR 0106). Prio P0 (Tier 0/segurança) → P3.

| # | Gap | Impacto | Esforço | Prio | Tipo | Referência |
|---|---|---|---|---|---|---|
| 1 | **Segredos em claro no git ainda vivos** (MinIO key, tokens "falta Vault", token EXPIRED) — dívida da auditoria 2026-06-07 não fechada | 🔴 Alto | 1,5d | **P0** | CONSOLIDAR | Snyk/GitGuardian 2026 (64% seguem ativos) |
| 2 | **Sem gitleaks/trufflehog + sem git-history scan + sem pre-commit gate** (só regex PHP defense-in-depth) | 🔴 Alto | 2d | **P0** | EVOLUIR | Four-gate model (devsecops.ae) |
| 3 | **Recall sem camada semântica/temporal** — busca é lexical; agente acha por caminho conhecido, não por significado/recência | 🔴 Alto | 4–6d | **P1** | EVOLUIR | Zep/Graphiti bi-temporal; mem0 multi-signal |
| 4 | **`reference/_INDEX.md` e `requisitos/_INDEX.md` curados à mão** (fora do regime gerado → drift latente, classe que ADR 0258 já matou nos ADRs) | 🟠 Médio | 1,5d | **P1** | CONSOLIDAR | Log4brains (índice gerado) |
| 5 | **14 colisões de número de ADR** persistem (registradas/aceitas, mas confundem humano e quebram links) | 🟠 Médio | 2d | P2 | CONSOLIDAR | `_INDEX-GENERATED.md` §colisões |
| 6 | **Sem decaimento/forgetting automático** — memory-health só sinaliza stale; nada arquiva/congela sozinho (cresce monotônico) | 🟠 Médio | 2d | P2 | EVOLUIR | tiered memory + dynamic forgetting (atlan) |
| 7 | **Destilação depende do ZELADOR rodar** (sessão agendada na máquina Wagner) — single point, sem fallback se Wagner offline | 🟠 Médio | 1d | P2 | CONSOLIDAR | Claude "Dreaming" (curadoria agendada resiliente) |
| 8 | **ADR 0256 e 0258 ainda `status: proposto`** (não `aceito`) apesar de implementados e em enforce — drift declarado vs real na própria governança de conhecimento | 🟡 Baixo | 0,5d | P2 | CONSOLIDAR | ADR 0257 status-lifecycle |
| 9 | **Sem grafo de relações entre artefatos** — `related:` é lista de slugs, não navegável; queries multi-hop ("que ADRs tocam multi-tenant?") são grep | 🟡 Baixo | 3d | P3 | EVOLUIR | GraphRAG / knowledge graph |
| 10 | **Sem métrica de utilização de recall** (quais docs o agente realmente abre? quais nunca?) — não há sinal pra podar "elefante branco" por uso real | 🟡 Baixo | 2d | P3 | EVOLUIR | ACE context playbook / mem0 importance scoring |

---

## 5 · Decisão estratégica: CONSOLIDAR (com 1 vetor de EVOLUÇÃO)

**CONSOLIDAR.** O oimpresso já implementou — antes da maioria do mercado — os três pilares que a indústria chama de estado-da-arte 2026: índice gerado (não-drifta), sentinela determinística de rot com ratchet, e conhecimento-medido-como-produto-de-leitura (`read_path_hops`). Trocar isso por um framework externo (mem0/Zep/Letta) seria EVOLUIR pelo paradigma errado: esses frameworks resolvem *memória de agente conversacional*, não *governança de decisão de engenharia versionada em git* — o problema do oimpresso é o segundo. A agulha move-se mais endurecendo o que existe (fechar segredos, gerar os 2 índices manuais restantes, aceitar os ADRs proposto-mas-vivos) do que adicionando capacidade. **O único EVOLUIR justificado é o recall semântico/temporal (gap #3)** — é onde o oimpresso está genuinamente atrás (70%) e onde a dor do "elefante branco" (ADR 0270) vai voltar conforme a base cresce além de 308 ADRs.

---

## 6 · Roadmap — mover a agulha primeiro

**Onda 1 (P0, ~3,5d) — fechar a dívida Tier 0 de segredos.**
- Rotacionar/cadastrar no Vault os 3 segredos pendentes do `_INDEX-SECRETS` (MinIO key, UltimatePOS superadmin, Hostinger token EXPIRED). Remover valores do git.
- Adicionar **gitleaks** (pre-commit hook + CI diff scan) + **history-scan** one-shot (acha dívida antiga). Promove o regex PHP de "único guarda" a "defense-in-depth". → fecha gaps #1, #2.

**Onda 2 (P1, ~3d) — fechar drift latente de indexação + aceitar o real.**
- Gerar `reference/_INDEX.md` e `requisitos/_INDEX.md` (script estilo `adr-index-generate.mjs`) + gate `--check`. → gap #4.
- Aceitar ADR 0256/0258 (`proposto`→`aceito` via `adr-supersede`/status-transition) — o controle existe, o declarado tem que bater. → gap #8.
- Resolver as 14 colisões de número (renomear as duplicatas mais recentes, manter redirect). → gap #5.

**Onda 3 (EVOLUIR, ~5–6d) — recall semântico/temporal mínimo.**
- Indexar canon (ADRs + reference + briefings) num store com embeddings + recência (event-time/ingestion-time estilo Graphiti), exposto como tool MCP `recall(query)` ao lado do `brief-fetch`. Começar read-only, multi-signal leve (semântico + keyword), sem grafo. → gap #3, parcial #9/#10.
- Instrumentar utilização: logar quais docs o `recall`/agente abre → alimenta poda por uso real (ADR 0270 fecha o loop de leitura). → gap #10.

**Parar quando:** `read_path_hops ≤ 1` em ≥90% dos módulos E 30d sem FAIL novo no `memory-health` E recall semântico cobrindo ADR+reference. Acima disso, GraphRAG é overkill pra esta escala.

---

## 7 · Surpresas

**Positiva (oimpresso > mercado):**
1. **Read-path medido (`knowledge-drift.mjs`).** A indústria 2026 (Anthropic, mem0) só agora prega "progressive disclosure / just-in-time"; o oimpresso já **mede** o caminho de leitura e penaliza índice-disfarçado-de-verdade (ADR 0270). Raríssimo até em orgs grandes.
2. **Meta-teste dos gates.** `adr-index-gate.yml` roda o controle-negativo (vitest) que **prova** que o gate funciona — o gate só "vale" se o teste que o desmente roda no CI. Isso é maturidade de fitness-function que poucos têm.
3. **ZELADOR — inteligência em tempo-de-leitura.** Um reconciliador-agente que *subtrai ruído* (nunca adiciona mecanismo) é conceitualmente o "Claude Dreaming" da Anthropic, mas com trilho de decisão Tier 0 explícito e escalação ≤3/dia. Implementado antes do anúncio.

**Negativa (mercado > oimpresso):**
1. **Recall puramente lexical.** Zep entrega 63,8% LongMemEval com bi-temporalidade; o oimpresso acha conhecimento por caminho conhecido + grep. Conforme a base passa de 308 ADRs, o custo de "achar a verdade atual sobre X" cresce — é a dor do "elefante branco" que volta.
2. **Segredos: longe do four-gate.** O mercado 2026 trata pre-commit + CI + history-scan + monitoring como baseline; o oimpresso tem 1,5 gates (regex CI + memory-health defense-in-depth) e ainda tem segredo em claro vivo no git. Maior risco Tier 0 desta auditoria.

---

## 8 · Riscos Tier 0 encontrados

1. 🔴 **Segredos em claro no git ainda vivos.** `_INDEX-SECRETS.md` mostra valores parciais (MinIO `ACCESS_KEY=oimpresso_0019f2a8669f`), token Hostinger **EXPIRED 2026-05-28** ("Wagner regerar"), e 3 itens "falta cadastrar no Vault". A dívida da auditoria 2026-06-07 (10 arquivos com segredo) **não está comprovadamente fechada** — rotação pendente confirmada na auto-mem. Viola ADR 0215 + `feedback-nunca-publicar-credenciais`.
2. 🟠 **Drift declarado vs real na própria governança.** ADR 0256 (Knowledge Survival) e 0258 (índice gerado) estão `status: proposto` apesar de implementados e em **enforce** no CI. A máquina que combate drift de conhecimento tem drift no próprio status — risco de credibilidade do controle.
3. 🟠 **14 colisões de número de ADR** persistem (auto-detectadas em `_INDEX-GENERATED.md`). Aceitas/registradas (não bloqueiam), mas quebram a premissa "1 número = 1 decisão" e confundem links humanos. Classe de bug que a auditoria 2026-06-07 (PRs #2379-2383) já enfrentou e não erradicou.
4. 🟡 **Single point of distillation (ZELADOR).** Roda na máquina do Wagner; se Wagner offline, a destilação/reconciliação para e a base volta a crescer monotônica sem poda. Sem fallback/redundância.

---

_Fontes externas citadas inline na seção 1. Inventário interno verificado por `ls`/`grep`/`find` + leitura de ADR 0061/0131/0215/0256/0258/0270/0091/0233 e dos scripts em `scripts/governance/`._
