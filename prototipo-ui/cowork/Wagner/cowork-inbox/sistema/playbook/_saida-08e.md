---
sessao: "08e"
titulo: Configuração da empresa em React — saída (F3-3a, Vendas · Pagamento)
playbook: sistema
thread: "08"
dono: "[CL]"
data: "2026-10-13"
tipo: recibo-parcial
base: wagnerra23/oimpresso.com@claude/sistema-08-f3-2-empresa-abas (empilhado no #9194, inclui o #9198)
---
# _saida-08e · F3-3a · abas Vendas e Pagamento

Operação tem cerca de 70 campos e não cabe num PR de 300 linhas. Foi dividida em três: **F3-3a** Vendas + Pagamento
(este), **F3-3b** Venda no PDV + balança, **F3-3c** Compras.

## Entregue
- Aba **Vendas**: desconto padrão, imposto padrão, preço com/sem imposto, adição de item, arredondamento do total,
  preço mínimo, vender sem estoque, bloquear preço zero no app, pedido de venda, prazo obrigatório, comissão (quem
  recebe, base de cálculo, obrigatório) e link de pagamento (Razorpay e Stripe).
- Aba **Pagamento**: cédulas, onde contar, formas de pagamento com contagem, conferência estrita.
- Chaves secretas (Stripe, Razorpay) **só de escrita**: a tela recebe apenas `segredos_gravados` (quais estão gravadas),
  em branco mantém, digitar troca. Apagar continua pela tela antiga.
- `EmpresaContratoTest` UC-EMP-08; UC-EMP-05 passa a cobrir também o desconto padrão.

## Valor (regra mestre)
- **Desconto padrão** passa pelo `num_uf`: usa o mesmo formatador da margem (`1234,5`), provado pela grade do UC-EMP-05
  (0 · 0,25 · 1 · 1,5 · 25,5 · 99,99 · 1234,5 · 2500 · 25000 · 123456,7891 com precisão 0), agora também para ele.
  UC-EMP-08: "7,5" grava 7,5.
- **Arredondamento do total** e **base de comissão** são escolhas gravadas como texto (`"0.05"`, `invoice_value`), sem
  conversão, iguais às opções da Blade. Nenhum cálculo muda.

Antes → depois: com a flag desligada, nada muda. Ligada, salvar sem editar não muda nenhuma coluna (UC-EMP-04).

## Falta
F3-3b PDV + balança · F3-3c Compras · F3-4 Sistema · F3-5 Integrações · F4 smoke biz=1. Cutover é [W].
