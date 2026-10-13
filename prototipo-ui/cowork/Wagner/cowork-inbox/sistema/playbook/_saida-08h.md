---
sessao: "08h"
titulo: Configuração da empresa em React — saída (F3-4, grupo Sistema)
playbook: sistema
thread: "08"
dono: "[CL]"
data: "2026-10-13"
tipo: recibo-parcial
base: wagnerra23/oimpresso.com@claude/sistema-08-f3-3c-compras (empilhado no #9207)
---
# _saida-08h · F3-4 · Painel, Sistema e Prefixos

## Entregue
- **Painel**: dias de alerta de validade.
- **Sistema**: cor do tema, linhas por tabela (`common_settings`) e textos de ajuda.
- **Prefixos**: os 17 prefixos de numeração (`ref_no_prefixes`). Ao editar, o objeto vai com as 17 chaves, como a Blade.
- `corpoDoSalvar()` passa a levar `theme_color` e `ref_no_prefixes`; `opcoes` ganha a lista de temas.
- `EmpresaContratoTest` UC-EMP-11.

Nenhum campo deste grupo é valor nem estoque. Antes → depois: flag desligada, nada muda.

## Falta
F3-5 Integrações (E-mail, SMS, Pontos de fidelidade, Módulos, Rótulos) — e-mail e SMS guardam senha, e fidelidade passa
por `num_uf`. Depois F4 smoke biz=1. Cutover é [W].
