---
status: deprecated
canonical: "../../memory/requisitos/Jana/BRIEFING.md"
deprecated_at: "2026-07-21"
deprecated_by: "0345-topicos-vivos-aprendizado-por-critica-revisada"
---

# BRIEFING — Modules/Jana — DESCONTINUADO (lápide-ponteiro)

> ⛔ **Não edite nem consulte aqui.** O BRIEFING canônico de **Jana** vive em
> **[`memory/requisitos/Jana/BRIEFING.md`](../../memory/requisitos/Jana/BRIEFING.md)** —
> a **única casa** do BRIEFING, fixada pela [ADR 0345](../../memory/decisions/0345-topicos-vivos-aprendizado-por-critica-revisada.md) (§Decisão) e pela [proposta de taxonomia §5](../../memory/decisions/proposals/2026-07-21-taxonomia-arquivos-modulo.md).

## Por quê esta lápide existe

"BRIEFING" morava em **dois lugares** (`Modules/Jana/` e `memory/requisitos/Jana/`) e a IA não sabia qual era o verdadeiro — os dois divergiam no tempo (o de Jana chegou a afirmar `96/100` contra os `73` do baseline canônico). A [ADR 0345](../../memory/decisions/0345-topicos-vivos-aprendizado-por-critica-revisada.md) fixou uma casa só: `memory/requisitos/<X>/BRIEFING.md` é o resumo/índice que **aponta** (não recopia) SCOPE/SUPERFICIE/SPEC.

Este arquivo virou **ponteiro** — não foi deletado (append-only). O conteúdo histórico segue no git: `git log --follow -- Modules/Jana/BRIEFING.md`.

## Onde entrar, por módulo

| Pergunta | Arquivo |
|---|---|
| Estado / resumo / índice? | [`memory/requisitos/Jana/BRIEFING.md`](../../memory/requisitos/Jana/BRIEFING.md) |
| O que é / não é meu? (fronteira) | [`SCOPE.md`](SCOPE.md) |
| Requisitos (US)? | [`memory/requisitos/Jana/SPEC.md`](../../memory/requisitos/Jana/SPEC.md) |
