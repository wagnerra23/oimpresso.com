---
id: requisitos-configuracoes-contas-parity
titulo: Paridade de migração — /account/account (Contas de pagamento) Blade↔React
tipo: parity
status: active
owner: W
criado: '2026-10-13'
tela: /account/account
related:
  - ../_DesignSystem/PARITY-TEMPLATE.md
  - ./RUNBOOK-contas.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0320-programa-ondas-regua-correcao'
---

# Paridade — Contas de pagamento (`/account/account`)

## Metadados

- **Tela React:** `resources/js/Pages/Configuracoes/Contas/Index.tsx` (F3 — ainda não existe)
- **Blade legado:** `resources/views/account/{index,create,edit,transfer,deposit,show}.blade.php` (modais + livro)
- **Controller (persistência):** `App\Http\Controllers\AccountController` — `store()`, `update()`, `postDeposit()`, `postFundTransfer()`, `close()`, `activate()`; tipos em `AccountTypeController` (fora do prefixo)
- **Rota nova / legada:** a mesma `Route::resource('/account')` sob o prefixo `account`; a Blade segue com a flag desligada
- **Auditado em:** `2026-10-13` · **por:** [CL]

## Mapa campo-a-campo

| # | Feature do Blade | Está no React? | Evidência | Severidade se perdido | Defendido por |
|---|---|---|---|---|---|
| 1 | Lista só as contas do negócio | ⬜ F3 | `index()` | **alta** | `ContasBaselineTest` |
| 2 | Saldo = crédito − débito, sem lançamento apagado | ⬜ F3 | `index()` (SQL) | **alta** (valor) | `ContasBaselineTest` (dois caminhos) |
| 3 | Filtro ativa/encerrada | ⬜ F3 | `index()` `account_status` | **alta** | `ContasBaselineTest` |
| 4 | Sem todos os locais: só contas dos locais permitidos | ⬜ F3 | `index()` + `permitted_locations()` | **alta** | `ContasBaselineTest` |
| 5 | Cadastro com saldo de abertura pelo `num_uf`, como crédito | ⬜ F3 drawer | `store()` | **alta** (valor) | `ContasBaselineTest` (dois caminhos) |
| 6 | Permissão `account.access` em lista e ações | ⬜ F3 | controller | **alta** | `ContasBaselineTest` (403) |
| 7 | Tipo / subtipo / número / observação / detalhes / quem cadastrou | ⬜ F3 | `index()` colunas | média | UC da F3 |
| 8 | Ações por linha: editar, livro, transferir, depositar, encerrar/reativar | ⬜ F3 drawer | coluna `action` | **alta** (valor) | UC da F3 + baseline próprio do drawer |
| 9 | Total do rodapé (soma dos saldos visíveis, em JS) | ⬜ decisão [W] | `index.blade.php` | média (valor) | — |
| 10 | Aviso de pagamentos sem conta vinculada | ⬜ F3 | `index()` `$not_linked_payments` | baixa | UC da F3 |
| 11 | Aba "Tipos de conta" | ⬜ link para Blade | `AccountTypeController` | baixa | — |

`⬜ F3` = a coluna React é preenchida no PR da F3, que cita o UC de cada linha `alta`.
