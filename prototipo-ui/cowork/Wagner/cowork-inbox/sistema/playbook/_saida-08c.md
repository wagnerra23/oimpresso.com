---
sessao: "08c"
titulo: Configuração da empresa em React — saída (F3-2, Impostos · Produtos · Contatos)
playbook: sistema
thread: "08"
dono: "[CL]"
data: "2026-10-13"
tipo: recibo-parcial
base: wagnerra23/oimpresso.com@claude/sistema-08-config-empresa (empilhado no #9172)
---
# _saida-08c · F3-2 · abas Impostos, Produtos e Contatos

Continua o `_saida-08b` (F3-1). Arquivo novo pelo mesmo motivo: `_saida-08` já está verificado no Cowork.

## Entregue
- As três abas na tela nova, com abas clicáveis no trilho (o grupo Empresa inteiro está em React; Operação, Sistema
  e Integrações seguem abrindo a Blade por `?classico=1`).
- `corpoDoSalvar()` passa a levar rótulos/números de imposto, prefixo do SKU, unidade padrão, tipo de validade e
  "parar quantos dias antes"; `opcoes` ganha a lista de unidades.
- `EmpresaContratoTest` UC-EMP-06; UC-EMP-04 passa a comparar também esses campos.

## Diferença em relação ao protótipo
A aba Produtos do protótipo mostra só SKU e validade. A Blade também tem marca, categoria, subcategoria, preço com
imposto, subunidades, 2ª unidade, prateleira/linha/posição, garantia e imagem obrigatória — ficaram todos, porque
tirá-los perderia configuração que o negócio já usa (paridade linha 6, `alta`). O protótipo deveria ganhar esses campos.

## Valor
Nenhum campo destas abas passa por `num_uf`. O limite de crédito padrão (`common_settings[default_credit_limit]`) é
texto: a Blade grava como digitado e só a tela de novo contato o lê como sugestão. A tela nova faz o mesmo, sem converter.

## Falta
F3-3 Operação (vendas, PDV, compras, pagamento) · F3-4 Sistema · F3-5 Integrações · F4 smoke biz=1. Cutover é [W].
