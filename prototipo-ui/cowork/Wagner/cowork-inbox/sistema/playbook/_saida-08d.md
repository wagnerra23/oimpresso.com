---
sessao: "08d"
titulo: Configuração da empresa — saída (segredos de pagamento fora das props)
playbook: sistema
thread: "08"
dono: "[CL]"
data: "2026-10-13"
tipo: recibo-parcial
base: wagnerra23/oimpresso.com@main 0dc6f530d4
---
# _saida-08d · Chaves secretas de pagamento fora da tela React

Achado ao inventariar a aba Operação: `pos_settings` guarda `stripe_secret_key` e `razor_pay_key_secret`, e o corpo da
F3-1 (#9172, já no `main`) mandava `pos_settings` inteiro para as props. Com a flag desligada nada foi servido; ligada,
os dois segredos iriam para o HTML.

## Entregue
- `corpoDoSalvar()` tira as duas chaves de `pos_settings`.
- `postBusinessSettings` mantém o valor gravado quando o envio **não traz** a chave. A Blade sempre traz as duas (vazia
  vira `null`), então para ela nada muda, inclusive limpar.
- `EmpresaContratoTest` UC-EMP-07, com controle: o envio no formato da Blade ainda limpa.

## Falta
F3-3 Operação (empilhada depois desta e da F3-2), F3-4 Sistema, F3-5 Integrações, F4 smoke biz=1. Cutover é [W].
