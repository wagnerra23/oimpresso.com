# CODE_NOTES — amendment · auditoria pós-faxina (followup chat25)

> **De:** [CL] Claude Code · **Para:** [CC] Cowork (Wagner cola no chat do Design)
> **Data:** 2026-05-30 · **Re:** "analise se o sistema de arquivos e memória do Design ficou como planejado; se precisar mudar, use a grade."
> **Método:** grade (CARTA §3 / BRIEFING §6) — score 0–100, gate ≥80, current→meta→ação.
> **Regra:** append-only (ADR 0003 / L-07). Nada some; o que muda, vira lápide + índice.
> **Base auditada:** export handoff `Oimpresso ERP Conunicação Visual` (TC3GBu3WXBledUagxJn8nw), 911 arquivos.

---

## Veredito grade — **55 / 100** · gate ≥80 ❌ → **precisa mudar**

> **Esqueleto ≈ 90** (subordinação ao git + faxina raiz append-only: muito bom).
> **Recheio ≈ 40** (numeração ADR, dedup profundo, freshness, contrato de handoff: arrasta a nota).
> Exatamente o split que o ADR 0236 prevê: a moldura segurou, o conteúdo apodreceu nos cantos.

| # | Dimensão | Peso | Nota | Veredito |
|---|----------|------|------|----------|
| D1 | Subordinação ao git (sem overstep "lei acima dos ADRs") | 15% | **92** | ✅ CONSTITUICAO.md retirada (lápide) → CARTA subordinada. Forte. |
| D2 | Soberania de numeração ADR (não cunhar nº do git) | 15% | **38** | ❌ cunhou `0200`/`0201` que já são do git (Contacts / SEFAZ). |
| D3 | Append-only + faxina da raiz (mover, indexar, versionar) | 12% | **90** | ✅ 35 itens → `_arquivo/INDEX.md` v1.0. Manifesto honesto. |
| D4 | Dedup profundo (subpastas, não só a raiz) | 12% | **45** | ❌ `uploads/`+`backups/`+`scraps/`+`memory-para-github/` intocados (~5,7 MB). |
| D5 | Freshness (doc citando canon aposentado) | 14% | **48** | ❌ `CLAUDE.md.proposto` = navy `#1f3a5f`; canon é roxo 295 (ADR 0235). |
| D6 | Fonte única do índice (ADR 0236 §máquina-2) | 12% | **55** | 🟡 3 índices concorrentes (ver abaixo) sem hierarquia explícita. |
| D7 | Contrato de handoff `new_design_memories` (ADR 0236) | 12% | **20** | ❌ ausente no export — gatilho G2 não dispara. |
| D8 | Ritual de ponte vivo (SYNC_LOG/CODE_NOTES usados) | 8% | **40** | ❌ `SYNC_LOG.md` e `CODE_NOTES.md` vazios — 0 syncs registrados. |

---

## O que ficou COMO planejado (manter — não rediscutir)

- **Overstep corrigido.** `CONSTITUICAO.md` virou lápide apontando `CARTA_DESIGN_CC.md`; a CARTA se declara subordinada a ADR 0094 + UI-0013 + PROTOCOL + BRIEFING. §0.1/§0.2/§0.3 estão certos.
- **Faxina raiz append-only.** 35 itens movidos (não apagados) → `_arquivo/` com `INDEX.md` v1.0 (origem→destino + "coberto hoje por"). É o jeito certo.
- **Espinha viva.** STATUS.md + MEMORY_INDEX.md atualizados in-place (permitido: são estado, não registro).
- **Soberania bem tratada (1 caso).** `_PROPOSTA-constituicao-soberania-W.md` ficou SEM número, aguardando o Code — **esse é o padrão correto.** Use-o nos outros (ver D2).

---

## Instruções pra [CC] — grade de mudança (current → meta → ação)

> Tudo append-only. "Renumerar/Reescrever" abaixo = **lápide + nova versão**, nunca delete silencioso.

| Item | Estado atual | Meta (nota-alvo) | Ação [CC] |
|------|--------------|------------------|-----------|
| **D2 · nº de ADR** | `memory/decisions/0200-…ds-harmonizacao.md` e `0201-…ratificacao.md` colidem com git (0200=Contacts, 0201=SEFAZ). MEMORY_INDEX lista "ADR 0200/0201 aceita". | ≥90 | **Despromover os números.** Renomeie pra `_PROPOSTA-ds-harmonizacao.md` e `_PROPOSTA-ratificacao-design.md` (sem nº), igual já fez com a soberania-W. **[CC] nunca atribui nº do git — só [CL]/[W].** Atualize MEMORY_INDEX: tire "ADR 0200/0201", escreva "proposta → Code numera". *(canon git real já é ADR 0235 roxo + 0236 governança-doc.)* |
| **D5 · freshness** | `CLAUDE.md.proposto` §identidade = "accent marinho `#1f3a5f`". STATUS D-02 = "identidade por tela verde/roxo/navy/indigo". | ≥85 | **Reconcilie com roxo 295.** Marque §identidade do `.proposto` como `STALE → ver ADR 0235 (roxo `oklch(0.55 0.15 295)` universal)`. Navy/cor-de-marca = **débito a migrar**, não identidade. "Cor-por-tela" permanece **proposta F0**, não norma. |
| **D4 · dedup profundo** | `uploads/` (3,7 MB, inclui handoff antigo aninhado recursivo `Oimpresso-handoff(1)/…/Ultimotopo/`), `backups/2026-05-14-*`, `scraps/`, `memory-para-github/` — nenhum citado na "base limpa" nem em `_arquivo/`. | ≥85 | **Estenda a faxina às subpastas.** Mova `uploads/`, `backups/`, `scraps/`, `memory-para-github/` → `_arquivo/legado/` e registre no `INDEX.md` (bump v1.1). O handoff aninhado recursivo é a maior fonte de peso (export 14 MB) — arquive inteiro. |
| **D6 · índice único** | 3 índices: `MEMORY_INDEX.md` (cowork), `memory/INDEX_TEMATICO.md` (git), `memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md` (git, ADR 0236 = fonte única de design). | ≥85 | **Declare a hierarquia no topo do MEMORY_INDEX:** "fonte única de design = INDEX-DESIGN-MEMORIAS (git); este índice é **derivado/temático**, não autoridade." Pare de re-tabular ADRs que já vivem no git — aponte, não duplique. |
| **D7 · contrato handoff** | export sem bloco `new_design_memories`. | ≥90 | **Todo export futuro carrega o bloco** (ADR 0236 — gatilho G2): `## new_design_memories` com linhas `tipo: golden\|conflito\|anti-padrao\|token\|doc-novo · ref: <path/ADR> · resumo: <1 linha>`. Sem ele, o Code não sabe o que reconciliar. |
| **D8 · ritual ponte** | `SYNC_LOG.md` + `CODE_NOTES.md` vazios. | ≥80 | A cada handoff, **anexe 1 linha no SYNC_LOG** (data, o que mudou, arquivos). É o rastro auditável do §5 da CARTA. |

---

## Ações [CL] Claude Code (faço sob OK do Wagner — R10/publication-policy)

1. **Numerar a soberania-W:** `_PROPOSTA-constituicao-soberania-W.md` → próximo nº monotônico livre no git (ADR 0028; hoje o topo é 0235×2/0236×2 — resolvo a colisão dupla do git também) + versionar.
2. **Resolver colisões do git:** git tem `0235` (roxo) **e** `0235` (staging-ct100), `0236` (governança-doc) **e** `0236` (scorecard). Renomear as duplicatas pros próximos nºs + lápide.
3. **Ingerir a faxina (pendência do chat25):** as 2 URLs do Cowork expiraram (~1 h), mas tenho as cópias no export — comito `prototipo-ui/COWORK_NOTES.amendment-faxina-arquivo-2026-05-30.md` + registro do `_arquivo/INDEX.md` (branch + PR, sob OK [W]; ADR 0003 "mexeu, registra"). **Não movo nada no repo** — é faxina do Cowork.
4. **Wiring do freshness gate** (ADR 0236 máquina-4 / estender ADR 0220): CI marca §stale quando doc cita canon aposentado. Cobre o caso navy→roxo automaticamente daqui pra frente.

---

## Lembrete honesto

Esta nota é do **[CL]** pro **[CC]**. Eu **não** editei a memória do Cowork nem commitei nada no git ainda — só auditei o export e deixei o plano. Quem aplica no Cowork é o [CC]; o que vai pro git, o [CL] leva sob OK do [W].
