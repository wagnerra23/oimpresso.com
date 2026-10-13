---
sessao: "08g"
titulo: Configuração da empresa em React — saída (F3-3c, Compras)
playbook: sistema
thread: "08"
dono: "[CL]"
data: "2026-10-13"
tipo: recibo-parcial
base: wagnerra23/oimpresso.com@claude/sistema-08-f3-3b-pdv (empilhado no #9204)
---
# _saida-08g · F3-3c · aba Compras — grupo Operação completo

## Entregue
- Aba **Compras**: status da compra, número de lote, atualizar preço do produto pela compra, pedido e requisição de
  compra, e compra em outra moeda (moeda + taxa de câmbio).
- Nenhuma mudança no controller: todos os campos já estavam no corpo desde a F3-1.
- `EmpresaContratoTest` UC-EMP-10.

Com isso o grupo **Operação** (Vendas, Venda no PDV, Compras, Pagamento) está todo na tela nova.

## Valor (regra mestre) — taxa de câmbio
O salvar grava `p_exchange_rate` **cru** (sem `num_uf`) numa coluna `DECIMAL(20,3)`. O campo é `type=number`, como na
Blade, para chegar com ponto decimal. Provas: (1) devolver o corpo sem mexer mantém taxa 5,125, moeda e as outras colunas;
(2) digitar "5.25" grava 5,25; controle: desligar "comprar em outra moeda" grava moeda vazia e taxa 1, como na Blade.

Antes → depois: flag desligada, nada muda.

## Falta
F3-4 Sistema (Painel, Sistema, Prefixos) · F3-5 Integrações (E-mail, SMS, Fidelidade, Módulos, Rótulos) · F4 smoke
biz=1. Cutover é [W].
