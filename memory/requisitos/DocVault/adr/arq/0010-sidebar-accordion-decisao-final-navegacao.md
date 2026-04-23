# ADR ARQ-0010 (DocVault) · Sidebar accordion como navegação principal (decisão final)

- **Status**: accepted
- **Data**: 2026-04-23
- **Decisores**: Wagner, Claude
- **Categoria**: arq
- **Supersede**: ADR arq/0009 (topnav declarativo) — mesma sessão
- **Relacionado**: _DesignSystem UI-0001

## Contexto

Exploração de 3 padrões de navegação nesta sessão:

1. **2 colunas fixas** (estado inicial do AppShell): col 1 = ícones módulos 64px, col 2 = sub-páginas 256px
2. **1 coluna flat + ModuleTopNav horizontal** (ADR arq/0009 piloto): sidebar só com módulos top-level, sub-items em barra horizontal no topo da página
3. **Sidebar vertical com accordion** (decisão final): módulos com chevron expansível, sub-items indentados verticalmente — padrão AdminLTE/Blade original

Wagner comparou visualmente e preferiu **opção 3**. Referência visual: screenshot do AppShell legado Blade com módulos (Superadmin, Iniciar ativo, Gerenciamento de usuários `<`, Contatos `<`, Produtos `<`, Fabricação, Accounting, Reparar, Compras `<`, Ponto WR2 `v` expandido mostrando Dashboard/Espelho/Aprovações/Intercorrências/Banco Horas/Escalas/Importações/Relatórios/Colaboradores/Configurações, vender `<`).

## Decisão

**AppShell usa sidebar vertical com accordion** (`resources/js/Layouts/AppShell.tsx`):

- **Largura fixa 256px** desktop (`w-64`)
- **Topo**: brand do business + status dot verde
- **Menu accordion**:
  - Módulos top-level renderizados como botões ou links
  - Módulo sem children: `<Link>` direto
  - Módulo com children: `<button>` que toggla `expanded` state local
  - Chevron `▾` (expandido) / `▸` (colapsado) à direita
  - Sub-items indentados (`pl-9 pr-3 py-1.5`) com ícones menores (14px)
  - Módulo contendo URL corrente: **auto-expandido no mount**
  - Estado ativo destacado: `bg-primary/10 text-primary font-medium`
- **Rodapé**: avatar + nome + email do usuário + theme toggle + botão logout
- **Mobile**: Sheet drawer com mesma estrutura accordion

**Fonte de dados**: `shell.menu` vindo do backend via `LegacyMenuAdapter::build()`.
Mesma fonte que alimentava o layout anterior — zero mudança no pipeline de
`DataController::modifyAdminMenu()` dos módulos.

## Características preservadas do Blade original

| Característica | Blade | React |
|---|---|---|
| Ordem dos módulos | `usort` por `order` no backend | Idem (mesma fonte) |
| Permissões Spatie | `@can()` / `$menu->add() if ...` | Filtrado no backend antes do JSON |
| Ícones | Font Awesome inline SVG | Lucide (mapeado heuristicamente em LegacyMenuAdapter::guessIcon) |
| Estado ativo | `request()->segment()` | Path match em JS |
| Sub-items expansíveis | `collapse` do Bootstrap | useState local, expansão imediata |
| Módulo ativo auto-expandido | `class="active"` no `<li>` pai | useEffect → setExpanded(true) |
| User no rodapé | `<div class="user-panel">` | Avatar + nome + email + theme + logout |
| Status online indicator | `<span class="status-indicator">` | Dot verde no brand |

## Consequências

**Positivas:**
- **UX familiar** pro Wagner (mesma estrutura do Blade que ele usa há anos).
- **Espaço horizontal 100%** pra conteúdo principal — nenhuma barra horizontal competindo.
- **Escalável**: 20+ módulos cabem sem overflow; accordion evita parede de items.
- **Consistente com o `modules_statuses.json`**: mesma fonte, filtrada pelo backend.
- **Zero backend novo**: usa `shell.menu` que já existia.

**Negativas:**
- Consome ~256px fixos do viewport desktop.
- Sub-items escondidos até usuário clicar (1 clique extra pra navegar).

**Mitigação da segunda**: auto-expand do módulo ativo reduz pra zero cliques quando já se está dentro. Primeira navegação ao módulo continua 1 clique.

## Código removido nesta decisão

Tudo que era do ADR arq/0009 foi removido:

- `resources/js/Components/shared/ShellTopBar.tsx` (deletado — nunca foi integrado)
- `resources/js/Components/shared/ModuleTopNav.tsx` (deletado)
- `resources/js/Hooks/usePageProps.ts::useModuleNav()` (função removida, import limpo)
- `resources/js/Types/index.ts::ModuleTopNav` + `ShellProps.topnavs` (interface removida)
- `app/Services/LegacyMenuAdapter::buildTopNavs()` + `resolveLabel()` (métodos removidos)
- `app/Services/ShellMenuBuilder::buildTopNavs()` (método removido)
- `app/Http/Middleware/HandleInertiaRequests::share()['shell']['topnavs']` (prop removida)
- `Modules/PontoWr2/Resources/menus/topnav.php` (arquivo removido)
- Prop `moduleNav` de `AppShell.tsx` (removida)
- 10 pages PontoWR2: imports e chamadas de `useModuleNav` limpos

Inventário de mudança: `git diff` mostra ~400 linhas removidas + ~320 linhas adicionadas (AppShell reescrito).

## Alternativas consideradas (fechamento)

- **2 colunas fixas** (estado original): descartada — col 2 vazia quando módulo sem children é confuso.
- **1 coluna flat + ModuleTopNav horizontal** (ADR arq/0009): descartada — 2 barras horizontais competem por espaço com 20+ módulos.
- **Sidebar accordion** (esta decisão): escolhida.

## Sinais de conclusão

- [x] AppShell reescrito com accordion
- [x] `shell.menu` continua sendo única fonte de navegação
- [x] Mobile drawer com mesma estrutura
- [x] User no rodapé preservado
- [x] Código temporário (ShellTopBar, ModuleTopNav, buildTopNavs) removido
- [x] ADR arq/0009 marcado como superseded
- [ ] Testar visualmente com Wagner (pendente)

## Aprendizado

Dois ADRs numa sessão, um supersedendo o outro — prática saudável:
- ADR arq/0009 documenta a tentativa de TopNav declarativo com arquivo por módulo
- ADR arq/0010 registra a decisão final após avaliação empírica
- Ambos permanecem no repo como registro da exploração técnica
