repo: wagnerra23/oimpresso.com
branch: main
path: mobile/

## Last sync
date: 2026-10-01T18:22:18Z

### Updated in this project
- Pacotes de handoff das 5 ondas em handoff/ (tokens, Oficina, adaptadores erp-ui, offline visível, Ponto nativo)
- Lidos: oi-theme.ts, _layout.tsx, mutation-queue.ts, offline-banner.tsx, erp-ui.tsx, oss.tsx, oss/[id].tsx, veiculos.tsx, componentes oi/*
- Avaliação corrigida: fila offline e registro de push já existem no app
- Rev. 2: rotas reais da /ponto/api (Modules/Ponto/Http/routes.php + MobileMarcacaoController); achado app de ponto Capacitor (ADR 0423)

## Sync history
- 2026-09-29T19:51:44Z · commit 7a6c977b621b4169050b3184ac1f4a95bdd04b3c · espelho inicial (PR #8193), telas 00–39, pele do DS

## Screen map
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
