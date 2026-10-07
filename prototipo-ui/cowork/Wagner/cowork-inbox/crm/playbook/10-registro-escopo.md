---
sessao: "10"
titulo: Registro de acompanhamento respeita access_own_schedule
dono: "[CL]"
base: f97a0be9fa4d
decisao: D5 (_DECISOES-W-2026-10-07.md)
---
# 10 · `ScheduleLogController@store` no mesmo escopo da leitura

Achado do `_saida-07` (pendente 3), repetido no `_saida-07e`: o `store` filtra só `business_id`; quem tem só `crm.access_own_schedule` registra em acompanhamento de colega. [W] decidiu **restringir** — o dono escolhe pelo papel (`access_all_schedule` × `access_own_schedule`), sem chave de configuração nova.

1. O `store` (e `update`/`destroy`, se tiverem o mesmo furo — confira) passa pelo escopo que a listagem já usa. Reuse; não reescreva o filtro.
2. Teste: só-`own` → acompanhamento de colega do mesmo negócio → 403; só-`own` → o próprio → 201; `all` → colega → 201. Valores lidos do banco.
3. UC novo em `Index.casos.md`.

**Não reli o controller neste turno** — linha e nome do escopo saem da sua leitura.
