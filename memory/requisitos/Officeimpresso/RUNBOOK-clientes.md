---
owner: W
last_validated: "2026-10-09"
slug: officeimpresso-runbook-clientes
title: "Officeimpresso — RUNBOOK Clientes OAuth (ClientController@index → Inertia)"
type: runbook
module: Officeimpresso
status: ativo
date: 2026-10-09
related:
  - 0104  # Processo MWART canônico (mãe)
  - 0093  # Multi-tenant Tier 0
  - 0358  # Doutrina de teste — tenant 98
---

# RUNBOOK — Officeimpresso `Clientes/` (thread 10 do playbook)

| Blade origem | Rota | Page alvo | Padrão de Tela |
|---|---|---|---|
| `clients/index.blade.php` | `GET /officeimpresso/client` | `Officeimpresso/Clientes/Index` | [PT-01 Lista](../_DesignSystem/padroes-tela/PT-01-Lista.md) |

Âncora de forma: `prototipo-ui/cowork/Wagner/officeimpresso-page.jsx` → `ViewClientes()` (rota `oi-clientes`).
O protótipo mostra "revelar/copiar secret" por linha — **não** entra: desde a thread 05 o segredo
não sai do banco para a lista, só aparece uma vez no flash da criação.

## F1 — PLAN (este arquivo)
Casos em `Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Clientes/Index.casos.md` (UC-OICLI-*),
derivados da ficha 10, da thread 05 (`_saida-05.md`) e da decisão D1 — não do `.tsx`.

## F2/F3 — BACKEND + FRONTEND (1 PR, thread 10)
- `ClientController::index` ganha o caminho dual pela flag `useV2OfficeimpressoClientes` (default OFF:
  o GrowthBook não conhece a chave e a Blade continua servindo).
- Props: `credencial` e `permissions` eager (a credencial é flash — adiada chegaria sem ele), `clientes`
  em `Inertia::defer` com DTO `{id, name, tipo}`, sem `secret`.
- Criar/excluir seguem as rotas da Blade (`POST /officeimpresso/client`, `DELETE /officeimpresso/client/{id}`);
  "Regenerar chaves" segue o `GET /officeimpresso/regenerate`, só para `superadmin`.
- Teste: `Modules/Officeimpresso/Tests/Feature/ClientesIndexContratoTest.php`, lane `officeimpresso-pest`.

## F4 — QA
Smoke com a flag ligada para um negócio de teste: lista, criar (bloco copiável aparece uma vez), excluir.

## F5 — CUTOVER (decisão [W])
Ligar `useV2OfficeimpressoClientes` em produção (toggle no GrowthBook, rota de fuga = flag OFF → Blade);
depois de estabilizado, apagar a Blade `clients/index` e a flag no mesmo PR.

## Pegadinhas
- Prop adiada roda num 2º request: o flash `officeimpresso_credencial` já foi consumido lá.
- A permissão delegável `officeimpresso.clientes.liberar` só vale para usuário da empresa operadora
  (`AcessoOperador`) — o caminho React passa pela mesma `authorizeLiberar()`.
