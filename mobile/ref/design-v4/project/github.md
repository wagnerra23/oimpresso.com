repo: wagnerra23/oimpresso-app
branch: main
path: /

> **Alvo mudou em 2026-10-01 (decisão [W]).** O app das lojas é o `oimpresso-app` (Capacitor + React/Vite, telas próprias, dados pela API Passport do ERP). O app Expo (`wagnerra23/oimpresso.com` · `mobile/`) saiu das lojas e o `/m` dentro do ERP foi descartado. Novos handoffs deste projeto miram o `oimpresso-app`; as ondas em `handoff/` foram escritas para o Expo e valem só como referência (tokens, toque ≥ 44, status da OS, offline, regras do Ponto).
> Cópia de referência deste projeto no ERP: `wagnerra23/oimpresso.com` · `mobile/ref/design-v4/` (PR #8467 mergeado; PR #8485 aberto, traz a versão atual).

## Last sync
date: 2026-10-01T20:23:45Z

### Updated in this project
- Alvo trocado de `oimpresso.com/mobile/` (Expo) para `oimpresso-app`
- Escopo da v1 ([W] 2026-10-01, D13 do `docs/lojas-app/DECISOES.md`, PR #8473 aberto): **7 áreas** — Início, Tarefas, Pedidos, Produção, Pessoas, Ponto e Mais. v2: Produtos, Venda rápida e Finanças. A submissão às lojas só sai com as 7.
- Tema ([W] 2026-10-01, D14): o app segue o tema claro/escuro do celular, como este protótipo.
- Hoje o oimpresso-app tem Login, Início (só ponto), Ponto e Conta; as outras áreas da v1 estão em construção.

## Screen map (oimpresso-app)
| Tela | Arquivos do repo |
| --- | --- |
| 00 Login | src/telas/Login.tsx |
| 01 Início | src/telas/Inicio.tsx |
| 36 Bater ponto · 37 Meu espelho · 38 Justificar | src/telas/Ponto.tsx, src/ponto-regras.ts, src/api.ts |
| Conta (excluir conta, sair) | src/telas/Conta.tsx |
| Tokens | src/styles/oimpresso-tokens.css (v4: design/oi-theme.v4.ts → src/styles/oi-v4.css, PR #6 aberto) |
| Lembrete de ponto | src/push.ts |
| 01 Início (gestão) · 12 Tarefas · 21–22 Pedidos · 02/27 Produção · 17–18 Pessoas · 10 Mais | **v1 (D13)** — em construção no oimpresso-app |
| 19–20 Produtos · 11 Venda rápida · 06/15 Finanças | v2 |
| 35 Dashboard | **a decidir pelo [W]** (v1 em Mais, como Início, ou depois) |
| demais telas do protótipo (Oficina, Estoque, Fiscal…) | fora da v1 e da v2 por ora |
| 39 Marcações a validar | fora do app (tela de gestor, desktop) |

## Histórico — mapa do app Expo (`oimpresso.com` · `mobile/`), alvo até 2026-10-01
### Sync history (Expo)
- 2026-09-29T19:51:44Z · commit 7a6c977b621b4169050b3184ac1f4a95bdd04b3c · espelho inicial (PR #8193), telas 00–39, pele do DS

### Screen map (Expo)
| Tela | Arquivos do repo |
| --- | --- |
| 01 Início | mobile/app/(tabs)/index.tsx, components/oi/OiHeader.tsx, OiKpi.tsx |
| 02 Produção | mobile/app/(tabs)/producao.tsx, components/oi/OiStatus.tsx |
| 03 OS da oficina | mobile/app/oss/[id].tsx |
| 04 Orçamentos | mobile/app/(tabs)/orcamentos.tsx |
| 05 Estoque | mobile/app/(tabs)/estoque.tsx |
| 06 Financeiro | mobile/app/(tabs)/financeiro.tsx, lib/use-financeiro.tsx |
| 07 Ordens de serviço | mobile/app/(tabs)/oss.tsx |
| 08 Veículos | mobile/app/(tabs)/veiculos.tsx |
| 09 Nova pessoa | mobile/app/clientes/_wizard.tsx |
| 00 Login + empresa | mobile/app/login.tsx, app/empresas.tsx |
| 10 Mais + perfis | mobile/app/(tabs)/mais.tsx, lib/menu-perfis.ts, lib/menu-modules.ts |
| 11 Venda rápida | mobile/app/venda-rapida.tsx |
| 12 Tarefas | mobile/app/(tabs)/tarefas.tsx |
| Offline (todas) | mobile/components/offline-banner.tsx, OFFLINE.md |
| Faturar (03) | mobile/components/oi/OiFaturarSheet.tsx |
| 13 Relatórios | mobile/app/(tabs)/relatorios.tsx |
| 14 Fiscal | mobile/app/(tabs)/fiscal.tsx |
| 15 Pagamentos | mobile/app/(tabs)/pagamentos.tsx |
| 16 Notificações | mobile/app/notificacoes/index.tsx |
| 17 Pessoas | mobile/app/(tabs)/clientes.tsx |
| 18 Ficha do cliente | mobile/app/clientes/[id]/index.tsx |
| 19 Produtos · 20 Novo produto | mobile/app/(tabs)/produtos.tsx, app/produtos/_wizard.tsx |
| 21 Pedidos · 22 Detalhe | mobile/app/(tabs)/vendas.tsx, app/pedidos/[id].tsx |
| 23 Manutenção · 24 Equipamentos | mobile/app/(tabs)/manutencao.tsx, (tabs)/equipamentos.tsx, lib/equipamentos-mock.ts |
| 25 Chat · 26 Equipe | mobile/app/(tabs)/chat.tsx, app/equipe/index.tsx |
| 27 OP · 28 Tarefa · 29 Movimentações | mobile/app/producao/[id].tsx, tarefas/[id].tsx, estoque/[id].tsx |
| 30 Editar perfil de menu | mobile/app/perfis/index.tsx, perfis/[id]/edit.tsx |
| 31 Equipamento · 32 Novo | mobile/app/equipamentos/[id].tsx, equipamentos/new.tsx |
| 33 Locais | mobile/app/locais/index.tsx |
| 34 Ficha cadastral | mobile/app/clientes/[id]/ficha.tsx |
| 35 Dashboard | mobile/app/(tabs)/dashboard.tsx |
| 36 Bater ponto · 37 Meu espelho · 38 Justificar | resources/js/Pages/Ponto/Mobile/Index.tsx, _components/MeuEspelho.tsx, _components/Justificar.tsx (repo web) |
| 39 Marcações a validar | resources/js/Pages/Ponto/Aprovacoes/_components/FilaMobile.tsx (repo web) |
| Tabbar | mobile/app/(tabs)/_layout.tsx, components/oi/OiTabbar.tsx |
| Tokens | mobile/lib/oi-theme.ts |
