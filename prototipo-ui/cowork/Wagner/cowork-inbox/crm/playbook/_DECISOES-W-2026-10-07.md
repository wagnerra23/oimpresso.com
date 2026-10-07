# _DECISOES-W-2026-10-07 — Crm (Cowork → Code)

> **Fonte:** [W] 2026-10-07, textual: *"crm deveria ter essa restrição, o responsável pode decidir ou não as agendas? empresa pequena e grande são diferentes?"*
> **Origem da pergunta:** `_saida-07e.md` §Pendente 1 (= `_saida-07` pendente 3).

## D-ACO-PERM · registro em acompanhamento de colega

**Resposta: sim, restringir — e quem decide é o dono da empresa, pelo papel.** Sem configuração nova.

- O próprio CRM já tem as duas permissões: `crm.access_all_schedule` (vê/registra em todos) e `crm.access_own_schedule` (só os seus). O defeito é o `ScheduleLogController@store` não respeitar a segunda — a listagem respeita, o registro não. Corrigir = o `store` passa pelo mesmo escopo da leitura.
- **Empresa pequena** (todos atendem todos): o dono dá `access_all_schedule` ao papel → nada muda pra eles.
- **Empresa grande** (carteira por vendedor): papel só com `access_own_schedule` → cada um registra só no que é seu; supervisor recebe `all`.
- Não criar chave de configuração por empresa: o papel já é o lugar dessa escolha (não reinventar o decidido).

**Aceite:** teste com usuário só-`own` tentando registrar em acompanhamento de colega do mesmo negócio → 403; com `all` → 201. UC novo no `Index.casos.md`.

## Edição pedida no json
```json
[{ "id": "D-ACO-PERM", "respondida": true, "resposta": "restringir: store respeita access_own_schedule; dono decide pelo papel; sem config nova" }]
```
