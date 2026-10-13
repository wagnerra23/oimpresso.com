---
sessao: "08k"
titulo: Configuração da empresa em React — saída (F3-5c, Rótulos personalizados — fecha a F3)
playbook: sistema
thread: "08"
dono: "[CL]"
data: "2026-10-13"
tipo: recibo-parcial
base: wagnerra23/oimpresso.com@claude/sistema-08-f3-5b-fidelidade-modulos (empilhado no #9219)
---
# _saida-08k · F3-5c · aba Rótulos personalizados — as 16 abas na tela nova

## Entregue
- Aba **Rótulos personalizados**, os 10 grupos de `custom_labels`: formas de pagamento (7), contato (10), produto (20,
  mostra 4 e expande), local (4), usuário (4), compra (4), frete da compra (5), venda (4), frete da venda (5), tipo de
  serviço (6). Mais as marcações "obrigatório" (compra, fretes, venda) e "puxar do contato" (frete da venda), e o tipo e
  as opções de cada campo de produto (`product_cf_details`).
- Opções de lista num campo de várias linhas, **uma por linha**: o cadastro de produto lê com `explode(PHP_EOL, …)`.
- Controller sem mudança: `custom_labels` já ia inteiro no corpo desde a F3-1.
- `EmpresaContratoTest` UC-EMP-14.

Com isso **as 16 abas** da Configuração da empresa estão na tela nova, atrás da flag desligada. Nenhum campo deste PR é
valor nem estoque.

## Falta (fora da F3)
- F4: smoke biz=1 com a flag `useV2ConfiguracoesEmpresa` ligada só para biz=1.
- F5 cutover e remoção do trilho `?classico=1`: decisão [W].
- Na tela antiga ainda: testar envio de e-mail/SMS e apagar segredo gravado (F3-5a).
- Achado aberto: valor por ponto 0 divide por zero (F3-5b) — decisão [W].
