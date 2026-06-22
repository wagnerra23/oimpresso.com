---
date: "2026-06-22"
topic: "Método aplicar-protótipo (adversário+benchmark) + descoberta do drift status×anchor nas SPECs"
authors: [W, C]
prs: [3228, 3229, 3231]
related_adrs: ["0297-anchor-lint-wired-testado-sa-a2-bis", "0273-anchor-spec-codigo-formato-canonico-fluxo-novo", "0264-casos-trio-rastreabilidade", "0256-knowledge-survival-meia-vida-catraca-sentinela"]
---

# aplicar-protótipo + drift status×anchor (parte 2 do dia)

## TL;DR
Continuação da sessão de anchor-fidelity. Wagner pediu o fluxo de "aplicar o que mudou no protótipo nas telas". Documentamos o método (`aplicar-prototipo`: detectar→mapear paralelo read-only→registrar→aplicar em sessão limpa→fechar), **testamos com adversário + benchmark** (achou 2 furos críticos → v2 endurecido), e **rodamos ao vivo na Vendas** — onde os gates (preflight + casos) corretamente barraram o atalho (base stale + casos stale). No fim, ao conferir a SPEC numa view de planejamento, Wagner pegou um drift mais fundo: o campo `status:` dos US **nunca foi reconciliado** (só as âncoras). Medição confirmou: sistêmico. 4 tasks spawnadas.

## Arco
1. **Método `aplicar-prototipo`** documentado — RUNBOOK (`prototipo-ui/RUNBOOK-aplicar-prototipo-orquestracao.md`) + skill, camada de orquestração multi-tela acima do `cowork-prototype-replication` (1 tela). Regra de ouro: análise barata 1x (paralela, read-only) separada da aplicação cara (sessão limpa por tela = economia O(1) vs O(N) + worktree isolada). PR #3228.
2. **Adversário + benchmark** (teste real) → v2 endurecido (PR #3229). Adversário achou 2 🔴: Fase 0 dependia de sha do SYNC_LOG (não existe → `git log -1 -- <path>`); "zero conflito" falso (baselines/DS compartilhados saem do paralelo, incidente #2495). Benchmark: SOTA em agêntico (~90%) e spec-anchored (~95%, o anchor-lint zombie supera arXiv 2602.00180); atrás em design↔código (~30%, sem Code Connect → `<tela>.map.json`) e tokens (~35%). Doc: `memory/sessions/2026-06-22-arte-design-to-code-sdd.md`.
3. **Mapa de 6 telas** (Fase 1, 6 agentes read-only): Vendas ~70%, Caixa Unificada ~90%, Compras ~80% (+ grade matrix órfã ~40%), Clientes ~95%, Oficina ~90%, CRM 0% (bloqueado por governança).
4. **Live run Vendas** (fatia "link Caixa do dia" no menu Visões — tela `/vendas/caixa` existe e estava órfã de navegação). PR #3231 **fechado honestamente**: preflight (branch não-off-main) + casos-coverage (Sells/Index.casos.md stale, ADR 0264) — os gates pegaram os 2 atalhos. Lição: é por isso que a Fase 4 exige sessão limpa off-main + revalidação de casos; não dá pra drive-by na tela-mãe.
5. **View de planejamento** da SPEC Financeiro (widget) pra Wagner criticar → ele pegou que o campo `status:` não foi reconciliado.

## Lição-chave (o drift status×anchor)
Reconciliar a SPEC mexeu em `Implementado em:` (âncora, governada pelo anchor-lint) e `Testado em:` — **não** no `status:` do blockquote. Dois campos de done-ness = dual-source = drift garantido (mesmo erro dos 4 índices de ADR). **Medição (57 SPECs, 824 US):** só 69 (8%) têm âncora verificável; 65 dizem `status: done` SEM âncora; 15 dizem `todo` MAS ancorado (inclui Jana COPI-107..113 que acabaram de ser reconciliadas → drift na hora); 309 em zona-cinza. Sistêmico, não só Financeiro.

## Tasks spawnadas (continuam em sessão limpa)
- `task_7fc84721` — SPEC done-ness: fonte única `Implementado em:` + gate status×anchor (a correção estrutural).
- `task_5197c37c` — Aplicar Vendas via Fase 4 (refazer #3231 off-main + casos).
- `task_982f7175` — Armar a rede: anchor-drift required + re-baseline scorecard (após onda-0→main).
- `task_264138a1` — Ligar GradeMatrixInput no form de compra (Larissa, componente órfão).

## Já mergeado nesta safra
ADR 0297 (anchor wired/testado, aceito) + reconciliação repo-wide (zombie/dead/dead_tests=0) + RUNBOOK/skill aplicar-prototipo v2 + benchmark doc.
