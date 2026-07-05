---
date: "2026-07-05"
time: "01:30 BRT"
slug: rag-investigacao-profunda-sync-fix
tldr: "Investigação profunda do retrieval da Jana (Wagner: 'compare em grade / se esforce, resolva'). Auditoria estado-da-arte (#3814, maturidade ~46%) apontou reranker/contextual como P0; MEDINDO no CT 100, todas as camadas 'sofisticadas' deram +3.7pp (reranker 68s/20docs CPU, inviável). A alavanca real era SYNC GAP: BRIEFINGs (estado do módulo, caso de uso #1) nem indexados. Fix #3815 (1 glob) → recall@5 0.704→0.815 (+11pp). Auditoria invertida por medição 3×. Residuais: briefings só via semantic (prod=FULLTEXT/0312) + sync completo falha (deadlock+OOM)."
prs: [3814, 3815, 3801, 3791, 3792, 3793]
related_adrs:
  - 0312-decisions-search-fulltext-hybrid-docs-off
  - 0318-ragas-eval-real-mata-tautologia-ct100-staging
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0053-mcp-server-governanca-como-produto
next_steps:
  - "Sync robusto: mcp:sync-memory completo falha (deadlock MySQL Hostinger + OOM em 1500 docs). Indexar os 73 BRIEFINGs restantes exige rodar em janela sem concorrência OU batch/retry/lock. Os 3 do golden foram indexados à mão (drift temporário, reconcilia no próximo sync limpo)"
  - "Semantic-para-briefings em prod: os briefings só são achados via semantic (FULLTEXT não casa 'estado consolidado' com o corpo). Prod usa FULLTEXT por ADR 0312 → o caso de uso não fecha 100% em prod. Reabrir hybrid COM as condições da 0312 (instruction-prefix qwen3 + template rico), agora que os dados estão melhores"
  - "Máquina anti-apodrecimento: sentinela 'doc canônico ausente do índice' (pega sync gap) + canary recall@k (US-COPI-130)"
---

# Retrieval da Jana — investigação profunda, sync fix (+11pp), auditoria invertida por medição

## Estado MCP no momento do fechamento

- **Cycle ativo:** nenhum em COPI.
- **PRs desta sessão (todos MERGED):** #3791 (buscarHybrid HTTP), #3792 (runbook Gold), #3793 (US-COPI-130), #3801 (handoff hybrid), #3814 (auditoria RAG grade), #3815 (sync fix BRIEFINGs).
- **US-COPI-130:** timeline com 4 comentários — bloqueadores camadas 2/3, reconciliação ADR 0312, veredito por evidência, resultado final +11pp.

## O arco desta sessão (2º — investigação profunda)

Wagner pediu grade estado-da-arte + "não sei se só isso resolve" + "se esforce mais, resolva as dificuldades" + acesso total.

1. **Auditoria** (agente `audit-research-expert` → [#3814](https://github.com/wagnerra23/oimpresso.com/pull/3814)): grade 11 dimensões, maturidade **~46%**, apontou reranker=alavanca #1 (paper *Beyond the Reranker*), contextual/prefix P0.
2. **Medição derrubou a auditoria 3×** (CT 100, golden set N=27, recall@5):
   | Alavanca | Ganho | Custo |
   |---|---|---|
   | **Sync fix (BRIEFINGs)** | **+11pp (0.704→0.815)** | 1 glob |
   | Reranker BGE | +3.7pp | **68s/20docs CPU** (inviável chat) |
   | Instruction-prefix | +3.7pp | trivial |
   | Fusão sem∪ft | teto 0.815 (rerank não converte) | — |
3. **A alavanca real = SYNC GAP.** O `IndexarMemoryGitParaDb` não tinha glob pra `BRIEFING.md` — os BRIEFINGs (estado consolidado do módulo, caso de uso #1 Larissa/Wagner) existiam no git mas fora do índice. [#3815](https://github.com/wagnerra23/oimpresso.com/pull/3815) adicionou o glob (slug `briefing:<Mod>`, ADR 0270 D-2). Provado: as 3 queries de "estado do módulo" (recall ZERO) passam a achar `briefing:<Mod>` no top-1.

## Dificuldades resolvidas (acesso total)

- Rede docker do BGE isolada → `docker network connect` (reranker no ar, health 200).
- Sync gap dos BRIEFINGs → fix mergeado.
- Todas as alavancas medidas empiricamente (não pesquisadas).

## Dificuldades residuais (ver next_steps)

1. Sync completo falha (deadlock Hostinger + OOM) — 73 BRIEFINGs pendentes.
2. Briefings só via semantic; prod usa FULLTEXT (0312).

## Lição perene

**A grade sofisticada (46%, reranker P0) estava certa no diagnóstico global mas errada nas prioridades — medir inverteu tudo.** O gargalo da Jana não era RAG avançado; era o básico (documentos-chave não indexados). O conserto mais barato (1 glob) deu 3× o ganho da "alavanca #1". Regra reforçada: cada nota de grade é HIPÓTESE até A/B empírico próprio.

## Pointers

- Grade completa: `memory/sessions/2026-07-04-arte-rag-retrieval-conhecimento-jana.md` (+ adendo empírico)
- Fix: `Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php` (glob BRIEFING)
- Régua: `tests/eval/recall-golden.yaml` (N=27) — expandir pra 100 (US-COPI-130 G7)
