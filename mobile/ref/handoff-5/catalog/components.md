# catalog/components.md — Registro de componentes e globais

> Onde mora **cada símbolo** usado nas telas. Regra: se um símbolo aparece numa tela, ele está aqui.
> Namespaces globais (`window.*`) são a forma como arquivos Babel isolados compartilham código.
> Ordem de carga em `manifest.yaml → load_order_prototype`.

## Camada base / infra

| símbolo | namespace | arquivo | papel |
|---|---|---|---|
| Ic.<nome> | `window.Ic` | app/icons.jsx | ícones (lucide-like); `Ic.frame`, `Ic.clock`, `Ic.bell`, `Ic.chevL`, … |
| OIStore / useStore | `window.OIStore` | app/oi-store.jsx | estado pub/sub em localStorage (`init/get/set/subscribe` + hook `useStore(key,initial)`) |
| OISheet | `window.OISheet` | app/oi-store.jsx | bottom sheet compartilhado |
| OITasks | `window.OITasks` | app/oi-store.jsx | mutações de tarefas (done/snooze/created) |
| OIPedidos | `window.OIPedidos` | app/oi-store.jsx | pipeline canônico ORDER/PROGRESS + advance |
| OIFlow | `window.OIFlow` | app/oi-flow.jsx | faturamento: `gerarParcelas/faturar/estornar/jaFaturado/faturamentoDe` → **contrato**, mapeia p/ backend |
| BRL / BRLcompact | `window.BRL` | app/mock-data.jsx | formatação monetária pt-BR |
| MobileApp | `window.MobileApp` | app/mobile-app.jsx | shell: tab bar + nav stack |
| oiToast(msg,tone?) | `window.oiToast` | app/mobile-app.jsx | toast global |

## Camada 1 — UI Kit (`window.OIUi`) · app/oi-ui.jsx

| componente | assinatura / props | usado por |
|---|---|---|
| ScreenHeader | { eyebrow?, title, actions?, search?, sticky?=true, nav } | telas de nível de aba |
| DetailHeader | { nav, title, eyebrow?, actions? } | todas as telas de detalhe |
| HeaderTopRight | { nav, tenant } | sino de notificações + avatar |
| OriginBadge | { origin, size?='sm'\|'lg' } | selo OS/CRM/FIN/PNT/MFG/OFI |
| StageStatus | { etapaKey, label? } | pílula de etapa (aprov/prod/entrega/done/orc/cancel) |

## Camada 2 — Domínio Manutenção (`window.OIManut`) · app/oi-manutencao.jsx

| símbolo | tipo | assinatura | usado por |
|---|---|---|---|
| Placa | componente | { placa, size?='sm'\|'lg' } | oficina, oficina-os, equipamentos |
| mDue | util | (os) → { label, tone, icon } | prazo da OS |
| mDur | util | (min) → string ("45min"/"3h20"/"2d") | duração |
| statusTone | util | (status) → classe | tom do status |
| MANUT_TONE | mapa | tone → cor CSS var | — |
| ITEM_STATUS | mapa | estado do item + próxima ação | detalhe de OS |

## Widgets de dashboard (`window.Widgets`) · app/dashboard-widgets.jsx

`Sparkline, ProductThumb, OrderStepper, CubeTile, SparkCard, MiniTrend, AssistCard, OrderCard` + constantes `PIPELINE, STAGE_IDX, STATUS_COLOR`. Consumidos por Início e listas de pedidos/produção.

## Molduras & tweaks (reference — descartáveis na produção)

- `ios-frame.jsx` → `IOSDevice, IOSStatusBar, IOSNavBar, IOSGlassPill, IOSList, IOSListRow, IOSKeyboard`
- `android-frame.jsx` → `AndroidDevice, AndroidStatusBar, AndroidAppBar, AndroidListItem, AndroidNavBar, AndroidKeyboard`
- `tweaks-panel.jsx` → `useTweaks, TweaksPanel, Tweak*` (só ferramenta de protótipo)
- `task-viewers.jsx` → `window.TaskViewer`

## Telas (`window.Screens`) — cada arquivo exporta só as suas rotas

| tela | rota | tipo | arquivo | build sheet |
|---|---|---|---|---|
| Dados do cliente | `cliente-dados` | push | app/screens-novo-cliente.jsx | screens/cliente-dados.md |
| Detalhe do cliente | `cliente` | push | app/screens-clientes-producao.jsx | screens/cliente.md |
| Clientes | `clientes` | push | app/screens-clientes-producao.jsx | screens/clientes.md |
| Empresa | `empresa` | push | app/screens-modules.jsx | screens/empresa.md |
| Detalhe do equipamento | `equipamento` | push | app/screens-equipamentos.jsx | screens/equipamento.md |
| Frota / equipamentos | `equipamentos` | push | app/screens-equipamentos.jsx | screens/equipamentos.md |
| Equipe | `equipe` | push | app/screens-relatorios-equipe.jsx | screens/equipe.md |
| Financeiro | `financeiro` | tab | app/screens-financeiro.jsx | screens/financeiro.md |
| Início (dashboard + tarefas) | `home` | tab | app/screens-home-tasks.jsx | screens/home.md |
| Locais | `locais` | push | app/screens-oficina-cadastro.jsx | screens/locais.md |
| Login | `login` | root | app/screens-modules.jsx | screens/login.md |
| Mais (menu por perfil) | `mais` | tab | app/screens-clientes-producao.jsx | screens/mais.md |
| Oficina — pátio | `oficina` | push | app/screens-oficina.jsx | screens/oficina.md |
| Detalhe da OS | `oficina-os` | push | app/screens-oficina-os.jsx | screens/oficina-os.md |
| Notificações | `notificacoes` | push | app/screens-modules.jsx | screens/notificacoes.md |
| Nova manutenção/OS | `nova-manutencao` | push | app/screens-oficina-cadastro.jsx | screens/nova-manutencao.md |
| Novo cliente | `novo-cliente` | push | app/screens-novo-cliente.jsx | screens/novo-cliente.md |
| Novo equipamento | `novo-equipamento` | push | app/screens-equipamentos.jsx | screens/novo-equipamento.md |
| Novo local | `novo-local` | push | app/screens-oficina-cadastro.jsx | screens/novo-local.md |
| Novo pedido (wizard) | `novo-pedido` | push | app/screens-novo-pedido.jsx | screens/novo-pedido.md |
| Novo produto (margem) | `novo-produto` | push | app/screens-novo-produto.jsx | screens/novo-produto.md |
| Detalhe do pedido (+ faturar) | `pedido` | push | app/screens-modules.jsx | screens/pedido.md |
| Pedidos | `pedidos` | tab | app/screens-modules.jsx | screens/pedidos.md |
| Editar perfil | `perfil-edit` | push | app/screens-perfis.jsx | screens/perfil-edit.md |
| Perfil do usuário | `perfil` | push | app/screens-modules.jsx | screens/perfil.md |
| Perfis / permissões | `perfis` | push | app/screens-perfis.jsx | screens/perfis.md |
| Detalhe do job de produção | `producao-job` | push | app/screens-producao.jsx | screens/producao-job.md |
| Produção (triagem/fila/estações) | `producao` | tab | app/screens-producao.jsx | screens/producao.md |
| Detalhe do produto | `produto` | push | app/screens-modules.jsx | screens/produto.md |
| Produtos | `produtos` | push | app/screens-modules.jsx | screens/produtos.md |
| Detalhe de relatório | `relatorio` | push | app/screens-relatorios.jsx | screens/relatorio.md |
| Relatórios | `relatorios` | push | app/screens-relatorios.jsx | screens/relatorios.md |
| Detalhe de tarefa | `tarefa` | push | app/screens-home-tasks.jsx | screens/tarefa.md |
| Tarefas | `tarefas` | push | app/screens-home-tasks.jsx | screens/tarefas.md |
| Detalhe de transação | `transacao` | push | app/screens-financeiro.jsx | screens/transacao.md |
| Venda rápida (scanner) | `venda-rapida` | push | app/screens-clientes-producao.jsx | screens/venda-rapida.md |
