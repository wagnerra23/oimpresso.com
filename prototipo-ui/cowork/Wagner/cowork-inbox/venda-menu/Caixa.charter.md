---
page: /cash-register
component: (F1) venda-blade.jsx
owner: wagner
status: draft
last_validated: "2026-08-22"
parent_module: Sells
related_prototype: prototipo-ui/cowork/venda-menu/
---

# Charter — Caixa registradora (turno)

**Fonte legado:** `cash_register/{index,create,register_details,close_register_modal,payment_details}` · **Permissão:** `sell.create (abrir/fechar o próprio turno)`
**Frescor:** 🟠 catch-up real — medido no `main` em 2026-08-22 (tree `6a8e45998ee5`): nenhum `Inertia::render` no controller, a tela ainda é Blade.

## Missão
Turno de gaveta: abrir com troco, acompanhar o que entrou por forma de pagamento e fechar contando dinheiro.

## Regras
- R1 Um turno aberto por usuário e local — só abre outro depois de fechar o atual.
- R2 Dinheiro esperado = valor inicial + dinheiro recebido − despesas − devoluções.
- R3 Fechar exige contagem por denominação; a diferença é dita em reais, com sinal.
- R4 Observação de fechamento é obrigatória quando há diferença.

## Fronteira com o vivo
O **Caixa do dia** (conferência por forma de pagamento e por origem) já é Inertia em `/vendas/caixa` (`Sells/Caixa/Index.tsx`), que declara em tela que movimentos e conferência física seguem no legado `/cash-register`. Esta tela é exatamente esse pedaço.
