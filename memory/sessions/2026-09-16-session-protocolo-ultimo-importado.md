---
date: "2026-09-16"
topic: "Precedência do último importado e retração da atribuição de perda ao bundle 20"
authors: [Codex]
prs: []
outcomes:
  - "ADR 0404 registrou a decisão expressa de W: último importado válido prevalece"
  - "Restauração dos índices antigos cancelada antes de editar o espelho"
  - "Nota existente recebeu errata baseada nos ZIPs originais; recibos históricos preservados"
related_adrs:
  - 0404-ultimo-importado-e-autoridade-do-espelho
---

# Protocolo do último importado

Após a autorização inicial para restaurar conteúdo, [W] delimitou: “o que vale sempre deve ser o ultimo importado esse deve ser o protocolo”. A instrução mais recente substituiu a restauração. Nenhum arquivo do espelho foi editado.

A auditoria anterior verificou CRC32, tamanhos, manifesto e reconstrução das partes do ZIP 20. A comparação dos originais refutou a perda 18→20: os índices Patrimônio e Sidebar eram iguais desde o ZIP 16, e os deltas de inbox 18→19→20 só adicionaram arquivos. A diferença era contra o Git restaurado/fundido, não contra o export anterior.

Foram adicionados a ADR 0404, um ponteiro no TESTE-03 do processo e uma errata na nota existente. A alteração preexistente de `.claude/launch.json` ficou intacta. Sem publicação, merge, alteração de valores/estoque ou código de produção. Tools MCP do projeto não estavam disponíveis nesta sessão.

## Verificação

`integrity-check.mjs`: todos os testes estruturais duros passaram (223 charters). `ds-guard.mjs`: saída limpa, arquivos Markdown ignorados por não serem CSS/HTML; não é prova visual. `git diff --check`: sem erros de whitespace. Nenhum teste PHP/PHPStan foi executado localmente.
