---
slug: financeiro-runbook-contas-bancarias
title: "Financeiro — Runbook da tela Contas bancárias (tela única de contas)"
type: runbook
module: Financeiro
tela: Financeiro/ContasBancarias/Index
owner: W
status: ativo
last_validated: "2026-10-27"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Contas bancárias (`/financeiro/contas-bancarias`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).
> **Decisão [W] 2026-10-27 (opção A):** a tela única de contas é esta. As contas do núcleo (`/account/account`,
> Blade + a Page `Configuracoes/Contas/Index` atrás de flag) migram para cá; `/account/*` redireciona.
> **Fonte de design:** `prototipo-ui/cowork/Wagner/financeiro-legado.jsx` → `telaContas()` (lista, filtro
> Ativa/Fechada, menu da linha). O formulário de nova conta não está no protótipo: desenhado no DS e publicado
> como Artefato "Contas no Financeiro" para o [W] antes do `.tsx`.

## Estado final esperado

Uma tela lista as contas da empresa (núcleo `accounts` + complemento `fin_contas_bancarias`) com saldo, tipo e
situação, e cadastra/edita/encerra/reativa a conta; "Configurar boleto" continua no mesmo lugar.

## 1. Objetivo

Trocar o cadastro de contas da Blade do núcleo pela tela do Financeiro sem mudar o SQL do saldo, o filtro por local
nem o caminho do saldo de abertura.

## 2. Pré-condições

- Permissão `financeiro.contas_bancarias.manage` (gate do controller).
- `accounts` (núcleo) é o cadastro; `fin_contas_bancarias.account_id` é FK única para ele (complemento 1:1).
- Serviço único: `App\Services\Contas\ContasDoNucleo` (saldo, filtro por local, criar/atualizar/encerrar).

## 3. Passo-a-passo

1. **PR 1 (backend):** serviço `ContasDoNucleo`, `AccountController` delega; rotas `POST /financeiro/contas-bancarias`,
   `PUT /{id}/cadastro`, `POST /{id}/encerrar|reativar`; contrato UC-CTB-05..11 (#10046).
2. **PR 2 (tela):** filtro de situação, coluna de saldo, menu da linha, `ContaSheet` (nova/editar).
3. **PR 3 (cutover):** `/account/account*` redireciona para cá; saem as Blades `account/*` de cadastro e de
   movimento manual (transferência, depósito, editar lançamento — uso zero medido em prod em 2026-10-27).

## 4–8. Tokens · estados · responsividade · atalhos · contrato

Tokens do tema global (`.cockpit`), fora da ilha `.fin-cowork`. Estados: ativas · encerradas · vazio. Tabela rola
abaixo de 768px. `data-contract`: `contas-table`, `filtro-situacao`, `conta-sheet`.

## 9. DoD checklist

- [ ] PR 1 backend + contrato · [ ] PR 2 tela · [ ] PR 3 cutover + Blades apagadas · [ ] smoke prod com screenshot

## 10. Pegadinhas

- **Saldo é SQL, não front.** Vem de `ContasDoNucleo::consulta` (crédito − débito, sem lançamento apagado) e chega
  formatado pelo `num_f`. A tela não soma nem formata saldo; KPIs/total do protótipo ficam fora até decisão [W].
- **Saldo de abertura vai como texto.** O servidor converte com o `num_uf` do cadastro antigo; a tela não interpreta
  número (§ regra mestre de valor).
- **Filtro por local.** Sem `access_all_locations` e sem `Admin#`, só contas habilitadas nos locais permitidos.
- **Acesso.** O cadastro antigo exigia `account.access` + módulo `account`; aqui vale o gate do Financeiro. Medido
  2026-10-27: das 9 empresas com o módulo `account`, 2 têm Financeiro; as 7 outras sem venda nem login desde 2021–2023.
- **Radix Select:** "sem tipo" usa sentinela, nunca `value=""` (§5 2026-06-29).

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
