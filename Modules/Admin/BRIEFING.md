---
status: deprecated
canonical: "../../memory/requisitos/Admin/BRIEFING.md"
deprecated_at: "2026-07-21"
deprecated_by: "0345-topicos-vivos-aprendizado-por-critica-revisada"
---

# BRIEFING — Modules/Admin — DESCONTINUADO (lápide-ponteiro)

> ⛔ **Não edite nem consulte aqui.** O BRIEFING canônico de **Admin** vive em
> **[`memory/requisitos/Admin/BRIEFING.md`](../../memory/requisitos/Admin/BRIEFING.md)** —
> a **única casa** do BRIEFING, fixada pela [ADR 0345](../../memory/decisions/0345-topicos-vivos-aprendizado-por-critica-revisada.md) (§Decisão) e pela [proposta de taxonomia §5](../../memory/decisions/proposals/2026-07-21-taxonomia-arquivos-modulo.md).

## Por quê esta lápide existe

"BRIEFING" morava em **dois lugares** (`Modules/Admin/` e `memory/requisitos/Admin/`) e a IA não sabia qual era o verdadeiro — os dois divergiam no tempo (o de Jana chegou a afirmar `96/100` contra os `73` do baseline canônico). A [ADR 0345](../../memory/decisions/0345-topicos-vivos-aprendizado-por-critica-revisada.md) fixou uma casa só: `memory/requisitos/<X>/BRIEFING.md` é o resumo/índice que **aponta** (não recopia) SCOPE/SUPERFICIE/SPEC.

Este arquivo virou **ponteiro** — não foi deletado (append-only). O conteúdo histórico segue no git: `git log --follow -- Modules/Admin/BRIEFING.md`.

## Onde entrar, por módulo

| Pergunta | Arquivo |
|---|---|
| Estado / resumo / índice? | [`memory/requisitos/Admin/BRIEFING.md`](../../memory/requisitos/Admin/BRIEFING.md) |
| O que é / não é meu? (fronteira) | [`SCOPE.md`](SCOPE.md) |
| Requisitos (US)? | [`memory/requisitos/Admin/SPEC.md`](../../memory/requisitos/Admin/SPEC.md) |
