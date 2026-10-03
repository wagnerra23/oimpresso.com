# App — Perfil de menu (tela 30) — escrita

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6).

`PUT /api/app/perfil-menu { modulos:[≤3, em ordem] }` → `200 { modulos, barra }`.

- A escolha fica guardada no ERP (`app_menu_preferencias`, [ADR 0426](../../../decisions/0426-preferencia-de-barra-do-app-guardada-no-erp.md)), por usuário e business.
- `modulos` aceita qualquer chave de `areas` (§6), exceto `inicio` e `mais`, que são fixos. O app
  mostra como opção só as áreas que já têm tela de aba no build dele; o ERP não mantém essa lista.
  Mais de 3, repetido, ou módulo fora disso → `422 { erro:"validacao", campos:{ modulos:"mensagem" } }`.
- `modulos: []` apaga a escolha e volta ao padrão.
- `GET /api/app/inicio` traz `barra` (lista com até 3 itens, nunca `null`) = escolha ∩ módulos permitidos, na ordem escolhida. Sem
  escolha, ou se nada da escolha estiver mais em `areas`, vale o padrão do ERP: Tarefas, Pedidos,
  Produção (§7.1), completado com as outras áreas do usuário na ordem de `areas`.
