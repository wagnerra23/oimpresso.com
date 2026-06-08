# Design Request Ledger — registro incremental de pedidos de design (file-based)

> **Pra que serve:** dar a cada pedido de design um número **REQ-NNN** com registro em **arquivo git**,
> pra o agente saber (a) se **já processou** [idempotência], (b) **o que mudou** [delta manifest],
> (c) **até onde fez** [checkpoint] — sem reler a tela toda a cada pedido.
> **Por que arquivo e não MCP:** o **Claude Design (Cowork) só lê arquivos** (GitHub + file-server),
> nunca o MCP do oimpresso. Ver [feedback-claude-design-so-arquivos](../../reference/feedback-claude-design-so-arquivos.md).
> **Proposta:** [design-request-ledger-incremental](../../decisions/proposals/design-request-ledger-incremental.md) ·
> **status: scaffold pré-ADR** (vira mecanismo canon quando aceito via emenda [ADR 0236](../../decisions/0236-governanca-evolucao-doc-design.md) ou ADR novo).

## Como funciona (sem DB)
1. **Novo pedido** → próximo `REQ-NNN` = maior número nesta pasta + 1 (monotônico).
2. **Cria** `REQ-NNN.md` a partir de [`_TEMPLATE-REQ.md`](_TEMPLATE-REQ.md).
3. **Antes de trabalhar** o Claude Design lê a tabela abaixo: REQ `done` = **pula** (idempotente).
4. **Trabalha** lendo só o `delta_manifest` do REQ (não a tela toda); grava `checkpoint` por seção.
5. **Fecha** com `status: done` + `resultado` (hash/PR do diff). **Append-only** — REQ nunca se apaga.

## Índice (o "já processei?")
| REQ | data | tela / arquétipo | status | delta (resumo) | resultado |
|---|---|---|---|---|---|
| _(vazio)_ | — | — | — | _o 1º pedido cria `REQ-001`_ | — |

> Atualizar esta tabela faz parte do "pronto" de cada REQ — espelha a regra **"índice = fonte única"** do [ADR 0236](../../decisions/0236-governanca-evolucao-doc-design.md).
