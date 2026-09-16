---
slug: 0404-ultimo-importado-e-autoridade-do-espelho
number: 404
title: "Último importado é a autoridade do espelho Cowork"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-16"
module: governance
tags: [design, cowork, importacao, espelho, ssot]
supersedes: []
supersedes_partially: [0398-espelho-cowork-recebe-a-arvore-da-conta]
superseded_by: []
related:
  - 0398-espelho-cowork-recebe-a-arvore-da-conta
  - 0379-bundle-design-transacao-manifesto-delta-staging
pii: false
---

# ADR 0404 — último importado é a autoridade do espelho

## Decisão do dono

Em 2026-09-16, [W] determinou: **"o que vale sempre deve ser o ultimo importado esse deve ser o protocolo"**.

Para o conteúdo da conta Cowork, **prevalece a última importação válida e aceita**, não a versão anterior do Git, a maior quantidade de linhas ou a avaliação de que um documento antigo era mais completo.

- O espelho preserva os bytes e caminhos da fonte, conforme a ADR 0398. Não se fundem versões antigas com a nova, nem se restauram trechos antigos automaticamente.
- Diferenças, inclusive redução de conteúdo, são achados para comparação e comunicação; não autorizam corrigir a fonte no espelho. Correções do conteúdo da conta acontecem na origem e chegam por nova importação.
- Histórico anterior permanece no Git, apenas como evidência. A fusão excepcional descrita nas consequências da ADR 0398 ficou como fato histórico; não é receita para as próximas importações.
- "Último" não significa aceitar um pacote atrasado, inválido ou incompleto à força: continuam valendo os gates de integridade, procedência, anti-replay e as regras de aplicação de delta da ADR 0379. Um delta não equivale à substituição integral da árvore.
- A precedência vale para os artefatos da conta dentro do escopo importado. Não transfere ao pacote a autoridade sobre DS canônico, código de produção ou documentação canônica do Code.

## Aplicação neste ciclo

O bundle 20 foi mantido como fonte do espelho. A restauração local dos índices antigos foi cancelada antes de qualquer edição. A atribuição de perda entre os exports 18 e 20 foi retratada na [nota existente](../reference/prototipo-ui/CODE_NOTES.errata-patrimonio-perdida-no-handoff-20-2026-09-16.md), sem reescrever recibos históricos da conta.

## Limite de implementação

Esta decisão registra a precedência e orienta o processo; não afirma que todos os importadores já possuam um teste automatizado específico contra fusão de versões. Os gates existentes continuam obrigatórios.
