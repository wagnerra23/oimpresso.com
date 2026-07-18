---
date: "2026-07-17"
topic: "Grade de réguas parcial — dimensão memoria-conhecimento repontuada pós-chips C8/C12 (7,0 → 7,4)"
authors: [C]
prs: []
outcomes:
  - "memoria-conhecimento repontuada ~7,4/10 (era 7,0 na grade da manhã) — rodada parcial 1-dimensão via workflow reguas-do-sistema"
  - "Meta-achado: 5 das 7 fraquezas que a pesquisa de mercado apontou JÁ estão construídas — padrão existia-mas-invisível (7/9 reincide). Gap real = discoverability + armar fechadores-de-loop, não capacidade"
  - "Placar: 0 acima-de-categoria · 2 à-frente-por-integração · 2 empatadas · 0 refutadas (regra 7 anti-falácia-de-composição aplicada)"
  - "C8 (#4429) e C12 (#4431) creditados: fecham F3 (drift de símbolo file-level) e avançam F2 (supersede armado em staging)"
  - "Próximo degrau estratégico: armar recall_eval_violations com a lane real do CT100 (mock→métrica-que-morde) — casa com COPI-28/COPI-25 do backlog"
  - "Nenhum rejeitado novo pro §5: os 6 sugeridos pela grade já estão catalogados (last_validated/LLM-judge/2º-motor) ou são non-goal de ADR (grafo Neo4j 0072/0295)"
related_adrs:
  - 0295-bitemporal-event-time-memoria-jana
  - 0333-emenda-0330-eixo-rodar-e-observar-submedido
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
---

# Grade de réguas — memoria-conhecimento pós-C8/C12

Rodada **parcial** (1 dimensão) do workflow `reguas-do-sistema.js`, disparada logo após fechar a leva de
chips C8+C12 — cadência canônica da skill ("pós-conclusão de leva de chips"). Medida contra worktree fresco
de `origin/main` (64a7593d63, já com os 2 chips mergeados). 14 agentes · ~1,78M tokens · ~20min.

## Nota: ~7,4/10 (era 7,0 na grade de 2026-07-17 manhã)

**Placar honesto** (regra 7 — duas colunas de superioridade, sem falácia de composição):

| coluna | n | o quê |
|---|---|---|
| acima-de-categoria (`ACIMA_CONFIRMADO`) | **0** | nenhuma peça isolada sem par publicado 2026 |
| à-frente-por-integração (`DIFERENCIAL_SISTEMA`) | **2** | registro-de-refutações auto-citado + doutrina-de-frescor-determinístico-como-lei |
| empatadas | 2 | as *mesmas* 2 ideias vistas como peça isolada — cada slice tem peer |
| refutadas | 0 | ninguém provou fazer este recorte melhor em prod |

Os 2 diferenciais e os 2 empates **são as mesmas 2 ideias em duas altitudes**: a peça empata (tem par —
arXiv 2606.21024/SkillSmith pro registro-negativo; Microsoft AGT/OpenLore/Dosu pro frescor determinístico),
o **todo montado dentro de um ERP vertical multi-tenant BR em prod, auto-aplicado** está à frente porque
nenhum peer monta o conjunto no mesmo contexto. Não é dupla contagem — é a lápide 2026-07-10 aplicada certo.

## O achado que importa — "existia-mas-invisível" reincide (7/9 → 5/7)

**5 das 7 "fraquezas" que o pesquisador de mercado apontou já estão construídas.** Ele leu ADRs e §5 em
markdown e **não abriu `Modules/Jana/Services/Memoria/`** nem as tools MCP. O gap real não é capacidade —
é **discoverability + armar os fechadores-de-loop**. As 5 já-construídas, com evidência:

| "fraqueza" | realidade (file:line) | nota |
|---|---|---|
| sem modelo bi-temporal | `BiTemporalResolver::vigenteEm($from,$until,$asOf)` + tool MCP `memoria-historica` (param `as_of`) + ADR 0295 cita Zep/Graphiti | 8 |
| sem memória por entidade | `copiloto_memoria_facts` + `DetectarSupersedeAgent` (grava `supersedes_id` append-only) | 7,5 |
| drift de símbolo fraco | **fechado hoje pelo C8** — `anchor-lint.mjs` `tocadosDesde(sha)` → `anchor_stale` | 7 |
| sem contrato de TTL/re-teste | `doc-freshness-score.mjs` **é** a régua Dosu (100−penalidades, anti-gaming, cron semanal) + 6 dentes | 8 |
| sem eval de memory-update gap | `recall-golden.yaml` campo `violations:` + `JanaRecallEvalCommand` + scorecard SDD | 7 |
| sem sleeptime/consolidação | `jana:profile-distill` ativo diário 04:50 (Kernel:937), ~76 LLM/dia reescreve profile | 6,5 |
| padrão memory-tool JIT | ADR 0225 (skill no momento) + `.claude/rules/` path-scoped | 8 |

Só **2** são gap-de-verdade e ambos são *"armar o que existe"*, não *"construir"*:
- **F5** — `recall_eval_violations` está `armed:false / not_yet_measured`: o mock em CI valida a consistência
  do golden mas **não roda recall vivo**; a lane real do CT100 ainda não alimentou a catraca. É o
  **fechador-de-loop mais estratégico** (transforma sentinela-que-alerta em métrica-que-morde).
- **F6** — `DistillerModuloVerdade` (reescreve as portas BRIEFING) está **kill-switched de propósito**
  (Kernel:275 comentado 2026-07-01: o write era `file_put_contents` na árvore deployada sem git = write-loss).
  Máquina pronta, desligada aguardando **venue git-backed + auto-PR** (respeita R10 + ADR 0061 zero auto-mem).

## Diferenciais reais (altitude de sistema, limite honesto de cada)

- **D1 — registro de refutações auto-citado** (`proibicoes.md §5`): o agente-codador cita o próprio §5 no
  pré-flight pra se auto-vetar, **durante trabalho de feature no ERP** (não num tool de governança). Peer
  mais forte do todo (world-model-mcp) falha o contexto — a recursão dele é trivial/auto-selecionada.
  *Limite:* a peça (bank de conhecimento-negativo) empata; o diferencial é instanciação+recursão+loop.
- **D2 — frescor determinístico ELEVADO a lei enforçada**, com a variante frágil (`last_validated`/`ttl_days`/
  grep-de-'advisory') **banida no §5**. Nenhum peer monta o todo. *Limite:* a peça "no-LLM-in-the-hot-path"
  empata (Microsoft AGT/Dosu); é diferencial de *enforcement+auto-aplicação*, não de arquitetura de store.

## Já feito desde o retrato da manhã (creditado, não re-listado como gap)

C8 (#4429, eixo temporal do anchor → fecha F3) · C12 (#4431, supersede armado em staging → avança F2) ·
§5 higienizado (#4428). A grade os creditou automaticamente lendo o `origin/main` fresco (regra 7 corolário).

## Top-3 do "que roubar" (impacto÷esforço)

1. **Armar `recall_eval_violations` com a lane real do CT100** — 90% já construído, impacto alto. Casa com
   **COPI-28** (MEM-MET-4 trend de qualidade) e **COPI-25** (MEM-EVAL-3 backfill) já no backlog do [W].
2. **Promover supersede staging→prod** após event-time popular em homolog (C12 armou staging hoje).
3. **Revogar formalmente o campo morto `last_tested`** (ADR 0094, zero adoção em 14 meses, mapa 0330:39) —
   substituído pelo `doc-freshness-score` não-declarável. É faxina, não capacidade.

## Rejeitados — nada novo pro §5 (verificado)

A grade sugeriu 6 rejeitados; a verificação no `proibicoes.md` mostra que **nenhum é novo**:
`last_validated`/`ttl_days`/`verificado_em` (3 ocorrências, família já catalogada 2026-07-09) · gate julgado
por LLM (2 ocorrências, render-diff/fidelity-lock) · 2º motor de frescor (8 ocorrências, a família mais
catalogada). O grafo Neo4j/Graphiti **não** vai pro §5 — é **non-goal de ADR** (0072/0295), não uma
tentativa-que-reprovou; o §5 é registro de regressões, não de escolhas de design. Não dupliquei nada.

## Ponto cego honesto desta rodada

Rodada de 1 dimensão: os eixos **RODAR-E-OBSERVAR** (observabilidade/drift/segurança/custo) e
**SERVIR-O-NEGÓCIO** (inteligência-de-negócio) **não foram medidos** — saem sem nota, e isso é buraco de
cobertura desta leva, não "passou". A próxima grade completa (ou parcial desses eixos) fecha.

## Pointers

- Output bruto do workflow: `wf_96919d27-b13` (journal.jsonl no transcript dir)
- Chips C8/C12: session `2026-07-17-memoria-conhecimento-c8-c12-portas.md`
- Grade completa origem: `2026-07-17-reguas-grade-truncagem-silenciosa.md`
- Worktree base da grade: `.claude/worktrees/_reguas-base-20260717-pm` (detached, seguro pra remover)
