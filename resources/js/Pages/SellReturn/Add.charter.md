---
page: /sell-return/add/{venda}
component: resources/js/Pages/SellReturn/Add.tsx
owner: wagner
status: draft
last_validated: "2026-10-05"
parent_module: Sells
related_prototype: n/a (o desenho de venda-menu cobre só a lista; o registro herda PT-02 e segue o Padrão de Tela)
runbook: memory/requisitos/Sells/RUNBOOK-sell-return-add.md
alcance:
  rota: /sell-return/add/{id}
  rota_nome: n/a (Route::get do núcleo em routes/web.php sem ->name literal)
  permission: n/a (permissão do núcleo access_sell_return / access_own_sell_return, checada no corpo de SellReturnController@add; não vem de DataController::user_permissions)
  menu_hook: n/a (a tela é aberta pela venda — Sells/Index e a lista de devoluções —, não pelo menu)
tier: B
charter_version: 1
related_us: [US-SELL-066]
---

# Charter — Devolução de venda · registro (`/sell-return/add/{venda}`)

> Texto revisado em `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/Devolucao.charter.md`
> (2026-08-22). A lista é `Index.charter.md` (PR 1); esta é o **registro** (PR 2), que grava
> **valor** e **estoque** — REGRA MESTRE de `memory/proibicoes.md`.

**Fonte legado:** `sell_return/add.blade.php` · **Permissão:** `access_sell_return · access_own_sell_return`
**Grava por:** `POST /sell-return` → `SellReturnController@store` (o mesmo do form Blade; não muda).

## Missão

Devolver item de uma venda: quanto volta pro estoque e quanto vira saldo a pagar ao cliente.

## Regras

- R1 A devolução é por LINHA: quantidade devolvida nunca maior que a vendida. A tela trava no
  teto; o servidor recusa o excesso mesmo assim (`SellReturnExceedsSold`, CU-DEV-08).
- R2 O total devolvido é o somatório das linhas, com o desconto e o imposto da venda — a tela
  mostra antes de salvar, com a mesma conta que o servidor faz ao gravar.
- R5 A tela envia os campos como o form Blade envia (texto no formato da empresa) e não
  recalcula nada no servidor: a regra de cálculo é a de `TransactionUtil::addSellReturn`.
- R6 Venda de outra empresa não abre (404) e não pode ser devolvida pelo POST (ADR 0093).

## Fora desta tela (do texto revisado, não implementado)

- R3 Motivo em texto livre no histórico: o `store()` não grava motivo — exigiria mudar a escrita.
- R4 Aviso de item feito sob medida: o produto não guarda essa informação.

## Non-goals

- ❌ Não estornar pagamento aqui (é do Financeiro).
- ❌ Não mudar `store()` nem `addSellReturn()` — esta tela só troca a forma, não a regra.
- ❌ Não usar o termo "estorno" para devolução (`memory/dominio/vendas.md`).

## UX Targets

- Cabe em 1280px sem rolagem horizontal.
- PT-BR em todo rótulo.

## Refs

- Casos: [`Add.casos.md`](./Add.casos.md) · Domínio: `memory/requisitos/Sells/CASOS-USO-DEVOLUCAO.md`
- Runbook: `memory/requisitos/Sells/RUNBOOK-sell-return-add.md`
