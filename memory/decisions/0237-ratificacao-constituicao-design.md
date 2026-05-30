# ADR 0237 — Carta de Design [CC] subordinada ao protocolo do git

- **Data:** 2026-05-30
- **Status:** aceito
- **Tipo:** subordinado — NÃO é lei suprema. A lei é `prototipo-ui/PROTOCOL.md` +
  `prototipo-ui/CLAUDE_DESIGN_BRIEFING.md` + ADRs (0114, 0110, 0107, 0104, 0010, 0028).
- **Renumeração:** proposto como `0201` no Cowork; renumerado p/ **0237** (próximo nº livre) por colisão com a ADR 0201 já existente no git (Receita Federal + SEFAZ ConsultaCadastro). Regra ADR 0028. Par: ADR 0236 (era `0200`).

## Contexto
Numa primeira tentativa, [CC] redigiu uma "Constituição acima dos ADRs" — **overstep**.
O projeto já tem constituição: PROTOCOL.md + CLAUDE_DESIGN_BRIEFING.md. Quem manda na
memória é o git; [CC] cuida do design, subordinado. Wagner corrigiu o rumo.

## Decisão
- **Retirar** a antiga `CONSTITUICAO.md` e seu HTML (enquadramento "lei suprema" inválido).
- Adotar `CARTA_DESIGN_CC.md`: carta **subordinada** que descreve como [CC] obedece o git —
  formato de entrega (page.tsx + COMPARISON + critique-score.json), tokens canônicos (sem
  inventar paleta), método grade (15 dim + score ≥80 + benchmark + teste Vercel), regras de
  evoluir-sem-quebrar (gate visual ADR 0107, container-query, aditivo), ciclo auto-auditável
  (health-check PROTOCOL §6), memória = git.
- **Reclassificar como propostas F0** (entram pelo loop, não por decreto):
  - identidade `--accent`/oklch por tela → toca BRIEFING §7 "não invente paleta".
  - cadastro página-inteira (PT-03) → toca proibição "detalhe usa Sheet drawer".

## Consequências
- [CC] passa a entregar no formato do PROTOCOL.md; HTML standalone vira rascunho de decisão.
- STATUS.md e MEMORY_INDEX.md apontam o git como fonte; CARTA como subordinada.
- Supersede: substitui a ratificação anterior (constituição suprema) por carta subordinada.
- Refs: ADR 0114, 0110, 0107, 0104, 0010, 0028.
