---
slug: 0426-preferencia-de-barra-do-app-guardada-no-erp
number: 426
title: "A barra do app das lojas guarda a escolha do usuário no ERP, numa tabela própria por business"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-02"
module: null
tags: [app-mobile, multi-tenant, migration]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0093-multi-tenant-isolation-tier-0
pii: false
---

# ADR 0426 — A barra do app guarda a escolha do usuário no ERP, numa tabela própria por business

## Contexto

A tela 30 do app das lojas (Perfil de menu) deixa o usuário escolher os até 3 módulos do meio da
barra de baixo. Início e Mais ficam fixos. O [W] decidiu, conforme relatado pela sessão do app
em 2026-10-02:

- a escolha fica guardada **no ERP**, não no aparelho;
- o `/api/app/inicio` devolve `barra` = escolha ∩ `areas`, e sem escolha vale o padrão do ERP;
- deve ser gravada sem tabela nova se possível; se precisar de migration, com ADR e `business_id`.

Onde a escolha poderia caber sem tabela nova (medido no `database/schema/mysql-schema.sql` em
2026-10-02):

- `users` tem `ui_theme` e `ui_sidebar_collapsed`, colunas tipadas de uma preferência cada, e
  nenhuma coluna JSON livre. Uma coluna nova em `users` mexeria em tabela core do UltimatePOS,
  o que a regra de `proibicoes.md` §Código barra sem bridge table.
- `custom_field_1..4` são campos de negócio que o cliente vê e preenche. Usá-los seria sequestrar
  dado do cliente.
- `dashboard_configurations` é de painel por business, não de usuário.
- Não existe tabela chave-valor de preferências por usuário.

## Decisão

Criar a tabela `app_menu_preferencias`, com `business_id` (FK, NOT NULL), `user_id` (FK),
`modulos` (JSON, lista ordenada, no máximo 3) e chave única `(business_id, user_id)`.

- Toda leitura e escrita filtra pelo `business_id` e pelo `user_id` do token.
- Sem linha, vale o padrão do ERP (Tarefas, Pedidos, Produção — §7.1 do contrato), dentro das
  `areas` do usuário.
- `PUT /api/app/perfil-menu { modulos: [] }` apaga a linha.
- A interseção com `areas` é calculada a cada leitura. Assim, quem perde acesso a um módulo não
  o vê mais na barra, e a escolha volta a valer se o acesso voltar.

## Justificativa

Uma tabela aditiva não toca `users` e segue a regra Tier 0 de tabela de negócio nova
(`business_id` indexado + FK, [ADR 0093](0093-multi-tenant-isolation-tier-0.md)). Ela também
comporta outras preferências do app depois, sem nova migration, se a coluna virar um objeto.

Reabrir se surgir um mecanismo de preferências por usuário do ERP inteiro. Nesse caso, a barra
migra para ele.

## Consequências

**Positivas:** a escolha acompanha o usuário em qualquer aparelho, e o ERP decide a barra com as
mesmas regras de acesso das `areas`.

**Negativas / Trade-offs:** uma tabela a mais para uma preferência pequena. A migration roda
sozinha no deploy (`migrate --force`), e por isso é só aditiva: `CREATE TABLE`, idempotente, com
`down()`.
