---
sessao: "08j"
titulo: Configuração da empresa em React — saída (F3-5b, Pontos de fidelidade e Módulos)
playbook: sistema
thread: "08"
dono: "[CL]"
data: "2026-10-13"
tipo: recibo-parcial
base: wagnerra23/oimpresso.com@claude/sistema-08-f3-5a-email-sms (empilhado no #9217)
---
# _saida-08j · F3-5b · abas Pontos de fidelidade e Módulos

## Entregue
- Aba **Pontos de fidelidade**: ligar, nome, ganho (valor da venda para 1 ponto, venda mínima, máximo por venda),
  resgate (valor do ponto, venda mínima, mínimo e máximo de pontos) e validade.
- Aba **Módulos**: os módulos de `availableModules()` como lista de liga/desliga (`enabled_modules`).
- `corpoDoSalvar()` passa a levar os seis campos de pontos sem pós-processamento; `opcoes` ganha a lista de módulos.
- `EmpresaContratoTest` UC-EMP-13; UC-EMP-05 passa a cobrir os quatro campos de pontos com `num_uf`.

## Valor (regra mestre)
Os pontos ganhos são `floor(total / valor_por_ponto)` (`TransactionUtil.php:4694`). Os quatro campos com `num_uf` usam o
formatador da margem; a grade do UC-EMP-05 (0 a 123456,7891, precisão 0) agora cobre todos. UC-EMP-13: sem editar nada
muda; "2,5" grava 2,5 e os outros ficam iguais. Antes → depois: flag desligada, nada muda.

## Achado, não corrigido
Valor por ponto **0** divide por zero na venda. O salvar troca vazio por 1, mas aceita 0 — na Blade e na tela nova.
Barrar 0 muda o que é aceito: decisão [W].

## Falta
F3-5c Rótulos personalizados · F4 smoke biz=1. Cutover é [W].
