---
date: "2026-09-16"
time: "18:09 UTC"
slug: paridade-bundle-por-conta
tldr: "Último ZIP aplicado com paridade exata de Wagner e Felipe preservado; árvore completa, poda transacional e unicidade por dono"
prs: []
decided_by: [W]
related_adrs:
  - 0404-ultimo-importado-e-autoridade-do-espelho
  - 0405-espelhos-cowork-independentes-por-conta
next_steps:
  - "Publicar/validar/mergear a implementação e a reconciliação locais, com aprovação da ADR 0405"
---

# Paridade e contas independentes

Wagner ficou com 695 fontes do ZIP 20, sem sobras/ausências/bytes divergentes. Os 24 arquivos de Felipe ficaram byte-idênticos pelo fingerprint completo. A reconciliação removeu 75 sobras de Wagner, recuperáveis no Git, e incluiu 95 arquivos novos.

O motor só poda com manifesto tree autenticado; valida o gate em staging antes da promoção e protege base/delta/rollback. Estados por conta e `--owner Felipe` foram preparados e testados em fixtures; nenhum ZIP novo de Felipe foi importado. R4 por dono autorizada por [W] na ADR 0405, com unicidade interna e DS preservadas.

## Estado MCP no momento do fechamento

Tools MCP do projeto indisponíveis; nenhum estado vivo inventado. Alterações locais, sem push/merge. Session log: [paridade por conta](../sessions/2026-09-16-paridade-bundle-por-conta.md).
