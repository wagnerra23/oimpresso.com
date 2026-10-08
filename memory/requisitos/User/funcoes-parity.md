---
id: requisitos-user-funcoes-parity
titulo: Paridade de migração — /roles (Funções e permissões) Blade↔React
tipo: parity
status: active
owner: W
criado: '2026-10-08'
tela: /roles
related:
  - ../_DesignSystem/PARITY-TEMPLATE.md
  - ./RUNBOOK-funcoes.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
---

# Paridade — Funções e permissões (`/roles`)

Lido no `main` em 2026-10-08 (`RoleController`, `resources/views/role/{index,create,edit}.blade.php`).
Itens `alta` estão travados no `tests/Feature/Roles/FuncoesBaselineTest.php`.

## 1. Lista (`role/index` → `Funcoes/Index`)

| Blade | React | Prioridade |
|---|---|---|
| DataTable server-side, só papéis do negócio da sessão | lista do negócio da sessão (prop adiada) | alta |
| Nome sem o sufixo `#<business_id>`; `Admin`/`Cashier` traduzidos | idem | alta |
| Papel padrão (`is_default`) sem editar/excluir, exceto `Cashier#<biz>` | idem | alta |
| Editar só com `roles.update`; excluir só com `roles.delete` | idem | alta |
| Botão Adicionar só com `roles.create` | idem | alta |
| — | nº de usuários por função (pivot `model_has_roles`) | nova |
| Exclusão por ajax com confirmação; recusa 422 "em uso" **não aparecia** (o `success` do jQuery não roda em 422) | mostra a mensagem do 422 | correção visível |

## 2. Cadastro / edição (`role/create`, `role/edit`)

| Blade | React | Prioridade |
|---|---|---|
| Grava `<nome>#<business_id>` no negócio da sessão | sem mudança (mesmo `store`/`update`) | alta |
| `is_service_staff` 0/1 | idem | alta |
| `permissions[]` + `spg_permissions[]` + `radio_option[]` viram um conjunto só | idem | alta |
| Permissão fora do catálogo é descartada (e logada) | idem | alta |
| Nome repetido no negócio não cria outro papel | idem | alta |
| `update` preserva permissão que o formulário não oferece | idem | alta (coberto por `RoleAdminOnlyScopeGuardTest`) |
| 153 controles do núcleo + permissões de módulo, em blocos por assunto | F3-1 leva à Blade; editor React na F3-2 | — |

## 3. Exclusão (`destroy`)

| Comportamento | Prioridade |
|---|---|
| Papel de outro negócio: `success: false`, nada apagado | alta |
| Papel em uso por usuário: 422, nada apagado | alta |
| Papel padrão: `success: 0`, nada apagado | alta |
| Papel livre do negócio: apagado | alta |

## 4. Achados (não corrigidos nesta thread)

1. **`edit()` com id de outro negócio quebra em vez de 404.** `Role::where('business_id', …)->find($id)` devolve
   `null` e o `foreach ($role->permissions …)` estoura. Não vaza o papel alheio (o baseline prova), mas responde
   erro. Mesmo desenho que a thread 12 corrigiu em Impressora/Código de barras. Trocar por `findOrFail` é PR próprio.
2. **Grupo de preço de outro negócio aceito como permissão.** `spg_permissions[]` passa pelo padrão
   `selling_price_group.<id>` sem conferir de quem é o grupo. É só um nome de permissão, mas fica gravado no papel.
   Decisão [W] se vale conferir o `business_id` do grupo.
3. **Recusa "em uso" invisível na Blade:** o 422 do `destroy()` cai no `error` do jQuery, que a tela não trata.
