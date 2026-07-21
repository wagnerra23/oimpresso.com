---
status: deprecated
canonical: "../../memory/requisitos/Woocommerce/BRIEFING.md"
deprecated_at: "2026-07-21"
deprecated_by: "0345-topicos-vivos-aprendizado-por-critica-revisada"
---

# BRIEFING — Modules/Woocommerce — DESCONTINUADO (lápide-ponteiro)

> ⛔ **Não edite nem consulte aqui.** O BRIEFING canônico de **Woocommerce** vive em
> **[`memory/requisitos/Woocommerce/BRIEFING.md`](../../memory/requisitos/Woocommerce/BRIEFING.md)** —
> a **única casa** do BRIEFING, fixada pela [ADR 0345](../../memory/decisions/0345-topicos-vivos-aprendizado-por-critica-revisada.md) (§Decisão) e pela [proposta de taxonomia §5](../../memory/decisions/proposals/2026-07-21-taxonomia-arquivos-modulo.md).

## Por quê esta lápide existe

"BRIEFING" morava em **dois lugares** (`Modules/Woocommerce/` e `memory/requisitos/Woocommerce/`) e a IA não sabia qual era o verdadeiro — os dois divergiam no tempo (o de Jana chegou a afirmar `96/100` contra os `73` do baseline canônico). A [ADR 0345](../../memory/decisions/0345-topicos-vivos-aprendizado-por-critica-revisada.md) fixou uma casa só: `memory/requisitos/<X>/BRIEFING.md` é o resumo/índice que **aponta** (não recopia) SCOPE/SUPERFICIE/SPEC.

Este arquivo virou **ponteiro** — não foi deletado (append-only). O conteúdo histórico segue no git: `git log --follow -- Modules/Woocommerce/BRIEFING.md`.

## Onde entrar, por módulo

| Pergunta | Arquivo |
|---|---|
| Estado / resumo / índice? | [`memory/requisitos/Woocommerce/BRIEFING.md`](../../memory/requisitos/Woocommerce/BRIEFING.md) |
| O que é / não é meu? (fronteira) | [`SCOPE.md`](SCOPE.md) |
| Requisitos (US)? | [`memory/requisitos/Woocommerce/SPEC.md`](../../memory/requisitos/Woocommerce/SPEC.md) |
