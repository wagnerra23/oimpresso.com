---
id: resources-js-pages-configuracoes-contas-index-charter
page: /account/account
component: resources/js/Pages/Configuracoes/Contas/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/financeiro-legado.jsx
owner: wagner
status: draft
last_validated: "2026-10-13"
parent_module: Configuracoes
related_adrs: [93, 104, 358]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/financeiro-legado.jsx"
  blueprint_screenshot_approval: "pendente [W]"
  derived_screens: [Contas]
  divergence_from_blueprint: "sem KPIs nem total do rodapé (somar saldo é decisão [W]); ações de cadastro e movimento na tela clássica até o drawer"
related_runbook: memory/requisitos/Configuracoes/RUNBOOK-contas.md
---

# Page Charter — /account/account (DRAFT)

> **Status:** draft. Thread `sistema/playbook/06` (Contas). `GET /account/account` responde Inertia só com a flag
> `useV2ConfiguracoesContas` ligada para o negócio; desligada, a Blade `account/index` segue. Vira `live` no
> cutover, decisão [W]. Casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Mostrar as contas de pagamento do negócio e o saldo de cada uma, com o mesmo número que a Blade mostra.

## Goals

- Lista das contas (tipo, subtipo, número, observação, saldo, detalhes, quem cadastrou) com o filtro ativa/encerrada.
- Saldo do mesmo SQL da Blade (`AccountController::consultaContas`) e formatado pelo mesmo `num_f`.
- Mesma restrição por local da Blade para quem não tem `access_all_locations`.
- Aviso de pagamentos sem conta vinculada, com link para o relatório.
- Livro da conta e a tela clássica (`?legacy=1`) alcançáveis.

## Non-Goals

- ❌ Calcular, somar ou formatar saldo no navegador (regra mestre de valor). Total e KPIs só com dupla prova e [W].
- ❌ Cadastrar, editar, depositar, transferir ou encerrar conta nesta tela antes do drawer da F3-2.
- ❌ Tipos de conta: são do `AccountTypeController`, fora do prefixo da thread.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só contas do negócio da sessão | `AccountController::consultaContas` |
| R2 | Com a flag desligada, ou com `?legacy=1`, a rota devolve a Blade | `AccountController::index` (`FLAG_V2`) |
| R3 | Saldo = crédito − débito sem lançamento apagado, um só SQL para Blade e React | `consultaContas` |
| R4 | Sem `access_all_locations` e sem `Admin#`, só contas dos locais permitidos | `consultaContas` |

## Refs

RUNBOOK: [`RUNBOOK-contas.md`](../../../../../memory/requisitos/Configuracoes/RUNBOOK-contas.md) · paridade: [`contas-parity.md`](../../../../../memory/requisitos/Configuracoes/contas-parity.md) · ADR 0093 · ADR 0104 · ADR 0358.
