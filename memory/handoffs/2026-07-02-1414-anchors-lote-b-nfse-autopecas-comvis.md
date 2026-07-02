---
date: "2026-07-02"
time: "14:14 BRT"
slug: anchors-lote-b-nfse-autopecas-comvis
tldr: "Lote B do backfill de ancoras spec-codigo (SA-A5 P10 wave3) fechado 3/3: NFSe (#3627), Autopecas (#3628), ComunicacaoVisual (#3638) todos mergeados. 48 US ancoradas, 0 ambiguas, refutador Fable tier-superior aprovou todos a 0 por cento. Inventory ficou de fora (FUNDIR)."
prs: [3627, 3628, 3638]
decided_by: [W]
related_adrs: [0273-anchor-spec-codigo-formato-canonico-fluxo-novo, 0302-doneness-lint-fonte-unica-status-ancora, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes]
next_steps: ["Lotes A e C do P10 (sessões paralelas) — verificar se fecharam", "Inventory aguarda decisão de identidade trilha E (FUNDIR) antes de ancorar"]
---

# Handoff — Backfill de âncoras LOTE B (SA-A5 P10 wave 3)

## Estado MCP no momento do fechamento
Tools MCP não conectadas nesta sessão (snapshot via git/gh). Base de trabalho: worktree fresca `origin/main` (partiu de `0bb65dd`, evoluiu até `caad489` conforme merges paralelos). Coverage global de âncora subiu de **60.1%** (início) — os 3 módulos do lote saíram de 0%.

## O que aconteceu
Backfill de âncoras `**Implementado em:**` (ADR 0273) em 4 módulos a 0% de coverage. Protocolo: gerador Opus (agente isolado por módulo) → refutador Fable 5 tier-superior em sessão fresca (protocolo G5) → ledger append-only → 1 PR por módulo.

- **NFSe** (#3627): 15 US (9 anchored_ok + 2 _parcial_ + 4 _pendente_). Refutador aprovou 0% rodada 1.
- **Autopecas** (#3628): 15 US todas _pendente_ (módulo wish, `Modules/Autopecas/` não existe). Refutador aprovou 0%.
- **ComunicacaoVisual** (#3638): 18 US (4 _parcial_ + 14 _pendente_). Refutador **reprovou r1 (11,1%)** — US-001 overclaim (`anchored_ok`→`_parcial_`), US-008 justificativa falsa ("NFSe não existe" — existe desde 2026-05-01). Corrigido → **r2 aprovou 0%**.
- **Inventory**: NÃO ancorado — é FUNDIR na `_TRIAGEM-IDENTIDADE-2026-06.md` (repartir 29 docs, cluster P6/P7 adiado). Documentado na fila A6.

**Gates required que morderam a dívida recém-visível (não bastava o backfill):**
- **entry/covers baseline grow** (NFSe +22, ComVis +4): US recém-"implementadas" sem aceite/teste-que-cobre — grow mínimo por módulo com trailers `BASELINE-GROW`/`BASELINE-ABSORB` (padrão wave 2).
- **status-truth NFSe** (7 US `todo`→`done`): doneness-lint (ADR 0302, âncora = fonte-única) exigia status coerente com âncora viva.
- **ghost fix Autopecas** (GT-G3): justificativa citava literal `Modules/Autopecas/` (inexistente) → ghost novo; reformulado.

**Conflitos resolvidos:** as merges sequenciais do Wagner (NFSe, depois Autopecas) fizeram o ledger/baseline compartilhados divergirem 2×; rebasei os PRs restantes na main fresca e reconstruí ledger (30 entries) + baseline (463 chaves, 0 dups) manualmente a cada rodada.

## Artefatos gerados
- `memory/requisitos/{NFSe,Autopecas,ComunicacaoVisual}/SPEC.md` — campo `**Implementado em:**` em 48 US
- `governance/sdd-verification-ledger.json` — 4 entries append-only (NFSe, Autopecas, ComVis-r1-reprovado, ComVis-r2-aprovado)
- `governance/anchor-entry-baseline.json` — +26 chaves grandfather (dívida legada recém-visível)
- `memory/requisitos/_ANCHOR-REVIEW-QUEUE.md` — §1 taxa de ambiguidade wave 3 (0%) + nota Inventory FUNDIR

## Persistência
- **git:** 3 PRs mergeados no main (#3627/#3628/#3638) via squash pelo Wagner + auto-merge
- **MCP:** webhook GitHub→MCP propaga os SPECs/ledger em ~2min
- **BRIEFING:** não aplicável (mudança de governança/rastreabilidade, não capacidade de módulo)

## Próximos passos pra retomar
```
node scripts/governance/anchor-lint.mjs --json   # ver coverage global atualizado + próximos módulos a 0%
```
Lotes A e C do P10 rodaram em paralelo (branches `claude/anchor-backfill-lote-a` etc) — conferir se fecharam. Inventory só ancora após decisão de identidade (trilha E, Wagner).

## Lições catalogadas
- **Backfill de âncora não é só escrever o campo:** virar US pra "implementada" (anchored_ok/parcial) dispara os gates required `anchor entry/covers` + `doneness-lint` sobre a dívida legada de aceite/teste — que fica *visível* só ao ancorar. Resolução canônica: baseline-grow por módulo (trailers) + status-truth. Não é bug do backfill; é a régua funcionando.
- **Justificativa de `_pendente_` não pode citar `Modules/<X>/` literal de módulo inexistente** — o knowledge-drift conta como ghost (GT-G3). Escrever "sem código do módulo no repo".
- **Merges sequenciais de PRs irmãos que tocam ledger/baseline append-only conflitam** — rebase + reconstrução manual dos objetos JSON. Esperado quando N PRs do mesmo lote compartilham governança.

## Pointers detalhados
- Protocolo: [P10](../requisitos/_Governanca/roadmap/P10-sa-a5-a6-batches-ia-fila-wagner.md) · [G5 refutador](../requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md) · [ADR 0273](../decisions/0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md)
- Fila A6: [`_ANCHOR-REVIEW-QUEUE.md`](../requisitos/_ANCHOR-REVIEW-QUEUE.md)
