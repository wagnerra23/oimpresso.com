---
sessao: "08f"
titulo: Configuração da empresa em React — saída (F3-3b, Venda no PDV e balança)
playbook: sistema
thread: "08"
dono: "[CL]"
data: "2026-10-13"
tipo: recibo-parcial
base: wagnerra23/oimpresso.com@claude/sistema-08-f3-3a-vendas-pagamento (empilhado no #9199)
---
# _saida-08f · F3-3b · aba Venda no PDV (com balança)

## Entregue
- Aba **Venda no PDV**: as 18 opções do caixa (`pos_settings`), os 11 atalhos de teclado (`keyboard_shortcuts.pos`) e a
  balança de código de barras (`weighing_scale_setting`: prefixo, dígitos do SKU, inteiros e decimais da quantidade).
- `corpoDoSalvar()` passa a levar `weighing_scale_setting`.
- `EmpresaContratoTest` UC-EMP-09.

## Estoque (regra mestre) — a balança grava o índice
A Blade monta os seletores da balança com `Form::select(..., [1..n])`: o valor gravado é o **índice** (0 = "1 dígito"),
e o `SellPosController::__parseWeighingBarcode` lê `índice + 1` para cortar o código. Um seletor que gravasse o número
mostrado mudaria a quantidade lida em toda venda por balança. A tela nova grava o índice e mostra índice + 1, igual.

Provas: (1) devolver o corpo sem mexer deixa balança, atalhos e `pos_settings` idênticos; (2) escolher "6" dígitos de
SKU grava `5`. Nenhuma conta muda: a configuração passa intacta. Antes → depois: flag desligada, nada muda.

## Detalhe que segue a Blade
Opção do PDV que tem padrão (as 8 de `defaultPosSettings`, como "esconder rascunho"): desmarcar remove a chave e o
salvar repõe `0` — na Blade é o mesmo.

## Falta
F3-3c Compras (tem a taxa de câmbio, valor) · F3-4 Sistema · F3-5 Integrações · F4 smoke biz=1. Cutover é [W].
