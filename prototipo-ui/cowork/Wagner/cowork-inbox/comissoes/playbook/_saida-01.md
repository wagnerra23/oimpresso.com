---
sessao: "01"
titulo: Comissionados → Inertia — saída
playbook: comissoes
thread: "01"
dono: "[CL]"
data: "2026-10-06"
base: wagnerra23/oimpresso.com@main 1ab4ab51b1
---

# _saida-01 · Comissionados → Inertia

A thread 01 do `comissoes` é ponteiro para `sistema/playbook/03`. Executei a de lá, uma vez só; este é o
único recibo (não há `_saida-03` no `sistema`).

## 1 · Feito
- `app/Http/Controllers/SalesCommissionAgentController.php` — `index()` responde Inertia `Comissionados/Index`
  (prop `agentes` deferida com a contagem de vendas vinculadas + `pode.gerenciar`); o ramo DataTable saiu.
  `edit()` passa a filtrar por negócio e `is_cmmsn_agnt` (era `findOrFail($id)` cru — Tier 0).
- `resources/js/Pages/Comissionados/Index.tsx` — PT-01 (busca, tabela, menu da linha) + drawer PT-02
  (cadastrar/editar) + confirmação de remover que avisa venda vinculada antes do clique.
- Trio: `Index.charter.md` · `Index.casos.md` (UC-CMSN-01…05) · `tests/Feature/Users/ComissionadosContratoTest.php`
  (lane `acessos-pest`).
- `memory/requisitos/Comissao/RUNBOOK-comissionados.md` (F1 MWART, exigido pelo hook).
- Mesmas rotas e permissões; `store/update/destroy` intactos (o percentual segue como texto pt-BR pro `num_uf`).

## 2 · Não feito e por quê
- KPIs (vendas, comissão apurada, a pagar), período, meta e situação de pagamento do protótipo: **sem fonte
  no legado**. São a apuração (`comissoes/02`, presa em D-COM-2). Ficaram fora em vez de número inventado (C7).
- Regra por faixa de meta / sobre margem: `Modules/Comissao`, ADR 0151 dormente (fronteira do playbook).
- Abas Usuários/Funções/Comissionados/Apuração do protótipo: Usuários e Funções ainda são Blade (threads
  `sistema/01` e `/02`) e Apuração não existe — abas apontando para telas que não existem ficam para depois.
- Contrato de forma: depende do alvo (não há `governance/design/targets/*comissionados*`).
- Cutover (apagar `resources/views/sales_commission_agent/*`): F5, decisão [W] depois do screenshot.

## 3 · Pedido literal pro [W]
> Aprovar o screenshot de `/sales-commission-agents` (React) para o charter virar `live` e liberar o cutover
> das 3 Blades `sales_commission_agent/*`.

## 4 · Descobertas que mudam outra sessão
- **Prefixo divergente entre os dois índices:** `comissoes/00-INDICE` diz `resources/js/Pages/SalesCommissionAgent/`;
  `sistema/00-INDICE` (o dono) diz `resources/js/Pages/Comissionados/`. Segui o dono. Corrigir o do `comissoes`.
- `sistema/03` declara `depende_threads: ["00"]` (mapa), que não tem `_saida-00`. O mapa "não mede nada" e a
  âncora `comissionados-page.jsx` existe no espelho; não bloqueou, mas a dependência segue aberta no índice.
- **Copy de remoção:** o protótipo diz "Excluir"; o `destroy()` só **desmarca** o papel desde #5970. A tela diz
  "Remover dos comissionados" — o protótipo deveria acompanhar.
- O `edit()` vazava cadastro de outro negócio pela URL — vale conferir o mesmo padrão em `ManageUserController`
  e `RoleController` nas threads `sistema/01` e `/02`.

## 5 · Prefixo tocado
`app/Http/Controllers/SalesCommissionAgentController.php` · `resources/js/Pages/Comissionados/` (prefixo do
`sistema/03`) · fora dele, por exigência de processo: `memory/requisitos/Comissao/RUNBOOK-comissionados.md` (hook
MWART), `tests/Feature/Users/` (teste novo + 1 comentário que ficou falso no `SalesCommissionAgentGuardTest`) e este `_saida-01.md`.
