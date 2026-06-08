# Protótipo F1 — financeiro-conciliacao

**Status:** 🟡 WAITING_FOR_BACKEND — bloqueio pesado.
**Aprovado por:** [W] 2026-05-09 (Cowork)
**Stories:** US-FIN-015 (conciliacao-ofx)

> ⚠️ **Antes de começar F3:** ler [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../LICOES_F3_FINANCEIRO_REJEITADO.md) — pré-flight checklist obrigatório (T-AP-1 a T-AP-15) + 6 meta-anti-padrões + convenções pt-BR. Sem ele, mesmo erro do batch original repete.

## Por que está bloqueado

Conciliação bancária é o item de maior peso desse batch — exige:

- Tabela nova `bank_statement_lines` (extrato OFX importado, append-only)
- `OfxImporter` parser + storage
- `ConciliacaoService::sugerir(linhaId)` com fuzzy match confidence (R-FIN-009)
- Idempotência em aceitar/desfazer (não pode aceitar 2x mesma linha)

`ConciliacaoController.php` do Cowork era stub: `aceitar()` e `desfazer()` retornavam `back()` vazio (NO-OP) — usuário clicava achando que aceitou e nada acontecia. Sem tenant scope.

## Pré-requisito

ADR técnica nova `arq/0006-importador-ofx.md` (não existe ainda) decidindo:
- formato canônico (OFX vs CNAB-240 vs CSV?)
- estratégia parser (composer package vs manual)
- onde guardar arquivo bruto (Storage local? S3?)

## Próximo passo

Discussão de escopo separada — **não atacar antes de Fluxo + DRE entregues** (mais simples, mais valor imediato).
