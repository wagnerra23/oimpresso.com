---
page: /officeimpresso/client
component: Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Clientes/Index.tsx
status: draft
owner: wagner
parent_module: Officeimpresso
last_validated: '2026-10-09'
related_prototype: prototipo-ui/cowork/Wagner/officeimpresso-page.jsx
related_runbook: memory/requisitos/Officeimpresso/RUNBOOK-clientes.md
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
charter_version: 1
---

# Charter — Clientes OAuth (`/officeimpresso/client`)

**Missão.** Deixar quem libera cliente (superadmin ou funcionário da operadora com
`officeimpresso.clientes.liberar`) criar a credencial password-grant de um Delphi e ver as que existem
no negócio da sessão, sem nunca reexibir um secret.

**Padrão de tela:** [PT-01 Lista](../../../../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md).
**Plano:** [RUNBOOK-clientes.md](../../../../../../../memory/requisitos/Officeimpresso/RUNBOOK-clientes.md) ·
**Casos:** [Index.casos.md](./Index.casos.md).

## Âncora de design

`officeimpresso-page.jsx` → `ViewClientes()` (rota `oi-clientes`): cabeçalho com "Regenerar chaves" e
"Nova credencial", tabela de credenciais. A coluna de secret com "revelar/copiar" do protótipo **não**
entra (thread 05: o secret não sai do banco para a lista). "Em uso / último handshake" não tem fonte hoje.

## Goals

- Lista das credenciais password-grant cujo dono é usuário do negócio da sessão: nome, Client ID, tipo.
- "Nova credencial": cria pelo `POST /officeimpresso/client`; o secret aparece **uma vez**, num bloco
  copiável que fica até o usuário fechar.
- Excluir (`DELETE /officeimpresso/client/{id}`) e "Regenerar chaves" (`/officeimpresso/regenerate`)
  só aparecem para `superadmin` — são as ações que o controller já restringe a ele.

## Non-Goals (NÃO faz)

- ❌ Exibir o secret de credencial existente — nem mascarado, nem por "revelar" (thread 05, D1).
- ❌ Mostrar credencial de outro negócio.
- ❌ Ampliar quem cria: a delegação vale só para usuário da empresa operadora (`AcessoOperador`, D1).

## Anti-hooks

- ❌ Não serve a lista em prop eager com o secret selecionado — o payload é DTO `{id, name, tipo}`.
- ❌ Não liga a flag `useV2OfficeimpressoClientes` por default: ligar em produção é cutover do [W].

## Dados / props

```
Officeimpresso/Clientes/Index (Inertia)
  is_demo: bool                                   // eager
  credencial: { name, secret } | null             // eager — flash da criação, lido uma vez
  permissions: { pode_excluir: bool, pode_regenerar: bool }   // eager
  clientes: Inertia::defer → Array<{ id, name, tipo: 'password'|'personal'|'authz_code' }>
```

## Testes

`Modules/Officeimpresso/Tests/Feature/ClientesIndexContratoTest.php` (lane `officeimpresso-pest`,
MySQL, tenant 98) — UC-OICLI-01..06.
