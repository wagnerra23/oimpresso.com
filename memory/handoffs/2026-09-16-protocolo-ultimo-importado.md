---
date: "2026-09-16"
slug: protocolo-ultimo-importado
tldr: "W determinou que o último importado prevalece; restauração local cancelada e atribuição de perda 18→20 retratada"
prs: []
decided_by: [W]
related_adrs:
  - 0404-ultimo-importado-e-autoridade-do-espelho
next_steps:
  - "Revisar e publicar as alterações documentais; não restaurar versões antigas no espelho"
---

# Protocolo do último importado

Decisão expressa do dono registrada na ADR 0404: última importação válida e aceita é a autoridade dos artefatos da conta, dentro do escopo importado. Histórico Git mais extenso não prevalece. Gates, anti-replay, delta e autoridade do DS canônico não foram afrouxados.

Nenhuma restauração foi aplicada no espelho. A nota de errata existente retratou a atribuição de perda entre exports 18 e 20 após leitura dos ZIPs originais, mantendo a análise anterior como histórico identificado. Recibos históricos da conta não foram reescritos.

MCP do projeto indisponível nesta sessão; nenhum estado vivo foi inventado. Alterações ficaram locais, sem push ou merge. Detalhe no [session log](../sessions/2026-09-16-session-protocolo-ultimo-importado.md).
