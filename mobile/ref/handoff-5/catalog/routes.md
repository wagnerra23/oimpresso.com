# catalog/routes.md — Contrato de navegação (autoritativo)

> Chaves de rota **verbatim** de `app/mobile-app.jsx` (objeto `map` do componente `MobileApp`).
> É a fonte de verdade para o `nav.push(<rota>)`. As build sheets usam o mesmo nome de arquivo (slug), mas a **chave de nav** é a coluna `rota`.
> API de navegação (React, interna): `nav.push(rota, params) · nav.pop() · nav.replace() · nav.canPop() · nav.gotoTab(id)`. Abas do rodapé dependem do perfil de menu ativo (Início e Mais são fixos).

| rota (chave nav) | componente | build sheet | acesso |
|---|---|---|---|
| `inicio` | HomeScreen | screens/home.md | aba (via perfil) |
| `tarefas` | TarefasScreen | screens/tarefas.md | push |
| `tarefa` | TarefaDetalheScreen | screens/tarefa.md | push |
| `pedidos` | PedidosScreen | screens/pedidos.md | aba (via perfil) |
| `pedido` | PedidoDetalheScreen | screens/pedido.md | push |
| `novo-pedido` | NovoPedidoScreen | screens/novo-pedido.md | push (form) |
| `produtos` | ProdutosScreen | screens/produtos.md | push |
| `produto` | ProdutoDetalheScreen | screens/produto.md | push |
| `novo-produto` | NovoProdutoScreen | screens/novo-produto.md | push (form) |
| `editar-produto` | NovoProdutoScreen | screens/novo-produto.md | push (form) |
| `mais` | MaisScreen | screens/mais.md | aba (via perfil) |
| `venda-rapida` | VendaRapidaScreen | screens/venda-rapida.md | push (form) |
| `vendas` | PedidosScreen (alias) | screens/pedidos.md | push |
| `financas` | FinancasScreen | screens/financeiro.md | aba (via perfil) |
| `transacao` | TransacaoDetalheScreen | screens/transacao.md | push |
| `relatorios` | RelatoriosScreen | screens/relatorios.md | push |
| `relatorio` | RelatorioDetalheScreen | screens/relatorio.md | push |
| `equipe` | EquipeScreen | screens/equipe.md | push |
| `perfis` | PerfisScreen | screens/perfis.md | push |
| `perfil-edit` | PerfilEditScreen | screens/perfil-edit.md | push |
| `clientes` | ClientesScreen | screens/clientes.md | aba (via perfil) |
| `cliente` | ClienteDetalheScreen | screens/cliente.md | push |
| `novo-cliente` | NovoClienteScreen | screens/novo-cliente.md | push (form) |
| `editar-cliente` | NovoClienteScreen | screens/novo-cliente.md | push (form) |
| `cliente-dados` | ClienteDadosScreen | screens/cliente-dados.md | push |
| `producao` | ProducaoScreen | screens/producao.md | aba (via perfil) |
| `producao-job` | ProducaoJobDetalheScreen | screens/producao-job.md | push |
| `manutencao` | ManutencaoScreen | screens/oficina.md | aba (via perfil) |
| `manut-os` | ManutOsDetalheScreen | screens/oficina-os.md | push |
| `nova-manut` | NovaManutencaoScreen | screens/nova-manutencao.md | push (form) |
| `locais` | LocaisScreen | screens/locais.md | push |
| `novo-local` | NovoLocalScreen | screens/novo-local.md | push (form) |
| `equipamentos` | EquipamentosScreen | screens/equipamentos.md | push |
| `equipamento` | EquipamentoDetalheScreen | screens/equipamento.md | push |
| `novo-equipamento` | NovoEquipamentoScreen | screens/novo-equipamento.md | push (form) |
| `editar-equipamento` | NovoEquipamentoScreen | screens/novo-equipamento.md | push (form) |
| `notificacoes` | NotifsScreen | screens/notificacoes.md | push |
| `perfil` | PerfilScreen | screens/perfil.md | push |
| `empresa` | EmpresaScreen | screens/empresa.md | push |

## Aliases / observações
- `vendas` e `pedidos` apontam para o mesmo `PedidosScreen`.
- `editar-produto`/`novo-produto`, `editar-cliente`/`novo-cliente`, `editar-equipamento`/`novo-equipamento` compartilham componente (modo criar vs editar por `params`).
- Abas do rodapé: `inicio` + `mais` fixos; os 3 slots do meio vêm de `MENU_PERFIS` (perfil ativo) — ver `app/menu-perfis.jsx`.
