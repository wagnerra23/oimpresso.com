# Sessão 2026-06-09 — "O que falta para governança?" (releitura @main + correção de stale)

## Pedido
[W]: "o que falta para governança?" (vendo `Avaliacao - Governanca IA 2026-06-09.html`).

## O que foi feito
- PORTÃO 1 cumprido ANTES de responder: releitura @main neste turno — `package.json`, `.github/workflows/` (tree), `casos-gate.yml`, `e2e-gate.yml`, `memory/dominio/`, `scripts/` (tree), `casos-test-results.json`. Tudo ✓lido.
- Descoberta: a avaliação da manhã (dim 11 = 5.5 "só proposto") ficou STALE EM HORAS — [CL] já executou F1+F2 da governança executável (ADR 0264 + 0261; casos-gate já required always-run por flip [W]).
- Correção registrada: adendo append-only no HTML da avaliação + entrada (d) no STATUS.

## Estado real @main (✓lido 06-09 tarde)
FEITO: casos-gate (G-1/2/5/6/7) Fase 2 required · dominio-gate + `memory/dominio/oficina-auto.md` · meta-gates · scripts npm casos/dominio/e2e/results · governance-drift.yml + memory-health.yml existem (⚠ conteúdo não lido).

## O que FALTA (a resposta)
1. **G-3 E2E** — `e2e-gate.yml` manual (workflow_dispatch) não-required; 1º run verde nunca validado. Próx: disparar → estabilizar → flip pull_request+required.
2. **G-7 sem prova** — manifesto `casos-test-results.json` vazio (`ucs:0`); todo ✅ declarado = unverified até a suíte rodar.
3. **F3 ratchet→0** — baseline 32KB de dívida legada (trio+UC↔teste) a zerar tela-a-tela.
4. Dicionário de domínio só Oficina — faltam Vendas/Financeiro/Fiscal.
5. Gate visual US-GOV-013 stub ⚠inferido (censo 06-04, <14d).
6. Lado [CC]: benchmark §11 parado desde 06-02 · manifesto-de-leituras @main (Portão 1 mecanizado) não construído.

## Lição
Nenhum erro novo — mas reforço positivo da Regra 6: a avaliação local stale teria sido repetida como verdade se não houvesse releitura @main no turno. O loop é mais rápido que a memória local; ler antes de afirmar pagou de novo.

## Próximo passo
[W] decide: disparar o 1º run do `e2e-gate` (workflow_dispatch) é o destrave de G-3+G-7 — depois disso o sistema cobra sozinho (F3 é autônomo do [CL]).
