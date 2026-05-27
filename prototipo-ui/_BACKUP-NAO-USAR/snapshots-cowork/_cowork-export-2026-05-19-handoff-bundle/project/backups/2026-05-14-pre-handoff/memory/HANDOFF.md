# HANDOFF — Protótipo Oimpresso ERP

> **Para:** o próximo Claude (qualquer aba/projeto).
> **De:** Wagner + Claude (sessão atual, ~80% do escopo concluído).
> **Como usar:** abra este arquivo no início da nova conversa e diga "continue de onde parou usando memory/HANDOFF.md". Tem tudo: estado, decisões, próximos passos.

## Sidebar — layout final (2026-05-09)

Wagner removeu o toggle Chat/Menu — sidebar fica sempre no Menu (mais confortável). `SidebarTabs` continua existindo no código mas não é renderizada. Estado da tab no localStorage é ignorado.

Ordem de grupos: OFFICEIMPRESSO → PRODUÇÃO → PESSOAS → FINANCEIRO → OUTROS → CONFIGURAÇÕES. Itens fora de grupo no topo: Tarefas (badge 6), Chat.

Para reverter: restaurar `<SidebarTabs>` no componente `Sidebar` em `sidebar.jsx` e voltar o ternário no `.sb-body`.

---

## 1. O que existe hoje (arquivos)

Protótipo principal: **`Oimpresso ERP - Chat.html`** (entrada única — carrega tudo via babel).

| Arquivo | Função |
|---|---|
| `Oimpresso ERP - Chat.html` | Shell HTML — imports na ordem correta |
| `app.jsx` | Roteador interno (chat / tarefas / os / outros módulos) + AppShell |
| `sidebar.jsx` | Sidebar dual (toggle Chat/Menu) — espelha `AppShell.tsx` real |
| `chat.jsx` | Aba Chat (3 colunas: lista conversas / thread / apps vinculados) |
| `tasks.jsx` | Tarefas — inbox unificada master/detail com viewers embutidos |
| `viewers.jsx` | Viewers por tipo de tarefa (OS, CRM, PNT, FIN) |
| `os-page.jsx` | **Listagem + Detalhe + Nova OS + Editar + Aprovar arte + Bulk modal** |
| `data.jsx` | Mocks de empresas, conversas, tarefas |
| `data-os.jsx` | Mocks de OS, clientes, produtos, responsáveis, timeline |
| `linked-apps.jsx` | Painel direito do chat — apps vinculados à conversa |
| `laravel-panel.jsx` | Stub mostrando Blade equivalente (educativo) |
| `tweaks-panel.jsx` | Painel de tweaks (Vibe / Density / Accent hue) |
| `icons.jsx` | Set de ícones SVG inline (`I.search`, `I.plus`, `I.pencil`...) |
| `styles.css` | Tudo. ~3000 linhas. Tem seções comentadas por feature. |
| `Inventario - Migracao Blade React.html` | Doc separado: inventário das 23 módulos nWidart |

---

## 2. O que está PRONTO (~80%)

### Fase 1 — Chat + Tarefas (100%)
- [x] Sidebar dual Chat/Menu com persistência localStorage
- [x] Aba Menu espelhando AppShell.tsx real (ordem, labels, ícones idênticos)
- [x] Aba Chat 3 colunas (lista / thread / apps vinculados)
- [x] Tarefas master/detail com viewers por tipo
- [x] Atalhos J/K/E/A
- [x] Tweaks (Vibe, Density, Accent hue) funcionando

### Fase 3 — Clientes (NEW 2026-05-09) — 100%
- [x] `clientes-page.jsx` — listagem com KPIs (Cadastrados / Com OS aberta / Com atraso / Faturamento total)
- [x] Abas: Todos / Com OS aberta / Com atraso / Sem OS aberta
- [x] Tabela: avatar gradient + nome/CNPJ, contato, OS total, em aberto, valor, status, última OS
- [x] Drawer detalhe (620px) — KPIs cliente, contato, histórico de OS (chips por etapa), financeiro resumido, ações
- [x] CSS completo em `styles.css` (cli-* classes)
- [x] Rota `/clientes` (já wired em `app.jsx` como `<CliListPage/>`)

### Fase 2 — OS (Officeimpresso) — 100%
- [x] Mock de 20+ OS com cliente, etapa, prazo, valor, urgência
- [x] **Listagem** — header, KPIs, filtros por etapa, busca, bulk actions, tabela densa
- [x] **Detalhe** (drawer 540px) — meta, stages flow, timeline, ações
- [x] **Nova OS** (drawer 820px) — stepper 4 seções: Cliente / Produto / Prazo / Resp.
- [x] **Editar OS** — drawer reusa Nova com dados preenchidos
- [x] **Aprovar arte** (modal grande) — lista de versões + canvas solo/comparar + decisão aprovar/ajustar/rejeitar
- [x] **Mudar etapa em massa** + **Atribuir responsável em massa** — modal compacto via bulk

### Memória/governança
- [x] `CLAUDE.md` (raiz) — princípios, decisões, ordem de migração
- [x] `CLAUDE_REPO.md` — versão proposta para commit no repo (ainda não commitado)
- [x] `memory/decisions/0039-ui-chat-cockpit-padrao.md` — ADR
- [x] `memory/sessions/2026-04-27-prototipo-chat-cockpit.md` — session log

---

## 3. O que FALTA (~20%)

### Curto prazo — polir Fase 2
1. ~~Empty states melhorados~~ ✅ feito (filtra "atrasadas" vazio mostra "Tudo no prazo!", etc.)
2. ~~Persistir Nova OS criada na lista~~ ✅ feito (nova OS aparece no topo da tabela com id auto-incrementado)
3. ~~Botão Imprimir/PDF no drawer detalhe da OS~~ ✅ feito (window.print + @media print isolando o drawer)
4. ~~Keyboard shortcuts no modal Aprovar arte~~ ✅ feito (A/J/R + ←/→ + Esc, com kbd hints visuais)
5. ~~Bulk "Exportar"~~ ✅ feito (gera CSV das OS selecionadas com data no nome)

### Médio prazo — Fase 3 (Orçamentos, Produtos)
6. ~~Clientes~~ ✅ feito (2026-05-09)
7. **Orçamentos** — `orc-page.jsx` JÁ EXISTE com `OrcListPage` exportado. Rota `/orcamentos` wired. Verificar visual e adicionar drawer detalhe se faltar.
8. **Produtos** — `prod-page.jsx` JÁ EXISTE com `ProdListPage` exportado. Rota `/produtos` wired. Verificar visual e adicionar drawer detalhe se faltar.

### Próximas próximas — Fase 4 (Repair, Estoque, Financeiro)
9. **Repair** — chão de fábrica, mobile-first (técnico com mãos sujas, touch ≥44px). Padrão diferente do Cockpit V2 desktop. Card grandes, status visíveis a 2m.
10. **Estoque** — inventário de insumos/produtos prontos. Grid + alerts de mínimo + entrada/saída.
11. **Financeiro** — extrato + boletos + conciliação. Tabelas densas (Eliana persona).

### Patches memória pendentes (PR #295)
- Wagner ainda precisa rodar `PROMPT_PARA_CLAUDE_CODE.md` (URLs públicas válidas ~1h, regenerar se passou).
- Após merge do PR #295, próxima tela vai pelo loop F0→F4 (Sells/Create P0).

### Longo prazo — Fases 4 e 5
9. **Fila de produção** (Manufatura) — kanban por máquina/equipe.
10. **Acabamento / Expedição** — telas operacionais (bipagem, conferência).
11. **Decommission Blade** — quando rota X migrada, virar `inertia: true` no `menu.php` do módulo.

### Nice-to-have do protótipo
- [ ] Modo escuro testado em todos os modais (conferir contraste especialmente no `OsApproveArtModal`)
- [ ] Persistência de drafts de Nova OS em `localStorage`
- [ ] Tweak "Vibe" estendido para os novos modais

---

## 4. Princípios de design para manter

1. **Padrão MWART** — toda rota nova respeita ordem/labels/ícones do `menu.php` real. Migração = virar flag `inertia: true`.
2. **Drawers para criar/editar; modais para ações pontuais** — a partir de uma listagem, criar/editar abre drawer lateral; ações do tipo "aprovar arte" ou "mudar etapa em massa" abrem modal centrado.
3. **Master/detail é o template padrão** — esquerda lista, direita conteúdo da seleção. Reuse o pattern da Tarefas e da OS.
4. **localStorage para tudo** — filtro ativo, conversa selecionada, aba atual, tweak escolhido. Sobrevive F5.
5. **Visual minimalista** — sem ícones decorativos, sem gradientes gratuitos, sem emoji. IBM Plex Sans + Mono. Contagens são número puro, sem ícone ao lado.
6. **CSS tokens** — sempre `var(--accent)`, `var(--bg-2)`, `var(--text-dim)`. Nunca hex hardcoded (exceto placeholders gráficos como nas thumbs do approve modal).
7. **Tweaks** — qualquer nova feature visual ganha um tweak para experimentação.

---

## 5. Convenções de código

- **JSX inline via Babel** (não tem build). Cada `.jsx` é um `<script type="text/babel">`.
- **Componentes ficam globais** via `window.NomeComponent = NomeComponent;` no fim de cada arquivo.
- **Hooks renomeados por arquivo** para evitar colisão entre script tags: `const { useState: useStateO } = React;` em `os-page.jsx`.
- **Style objects únicos por componente** (`const osStyles = {...}`), nunca `const styles = {...}`.
- **Mocks isolados** em `data*.jsx` exportados via `window.OS_DATA = {...}`.
- **Width de drawers** — sempre `min(Npx, 100%)` para respeitar o container; nunca `vw` (cobre o sidebar).
- **Ícones** — só os que existem em `icons.jsx` (`I.pencil`, `I.check`, `I.close`, `I.plus`, `I.search`, `I.folder`, etc.). Não inventar `I.edit` etc.

---

## 6. Como o próximo Claude deve começar

1. Ler `CLAUDE.md` (princípios estáveis) + este `HANDOFF.md` (estado vivo).
2. Abrir `Oimpresso ERP - Chat.html` no preview e clicar pelas telas — entender o que existe.
3. Perguntar ao Wagner qual item da seção 3 atacar primeiro.
4. **Não recomeçar do zero.** Reusar `OsListPage`, `OsNewDrawer`, `OsApproveArtModal`, `TasksPage` como templates.
5. Quando concluir uma feature significativa, atualizar a seção 3 deste arquivo (mover de "falta" pra "pronto") e criar uma session log em `memory/sessions/YYYY-MM-DD-slug.md`.

---

## 7. Decisões importantes já tomadas (não revisitar sem motivo)

- **`OS` no menu virou `Tarefas`** — inbox unificada, agrega todos os módulos.
- **Sidebar dual com toggle no topo** (Chat/Menu), não duas barras lado a lado.
- **Sem seletor de "projeto" no topo da sidebar** — vira breadcrumb na página.
- **Drawers laterais à direita** para criar/editar/detalhar — nunca modais centrais para CRUD.
- **Modais centrais APENAS para ações pontuais** (aprovar arte, mudar etapa em massa).
- **As telas React atuais (Copiloto/MemCofre/Financeiro/Site) podem ser refeitas** — não estão em produção real ainda.
- **TaskProvider é o contrato backend** — cada módulo registra suas tarefas, `TaskRegistry` agrega.

---

## 8. Repositório

- **GitHub:** `wagnerra23/oimpresso.com`
- **Memória local sincronizada:** `memory-para-github/` espelha o que vai pro repo.
- **Atenção:** se houver outra aba do Claude trabalhando no mesmo repo, coordenar antes de commitar (risco de conflito em ADRs e CLAUDE.md).

---

## 9. Quando dividir entre múltiplas contas Claude

Se rodar 3 contas em paralelo, divisão sugerida:

- **Conta A** → polir itens 1-5 da seção 3 (curto prazo) — todo no `os-page.jsx` e `styles.css`.
- **Conta B** → atacar item 6 (Página Clientes) — abre arquivo novo `clients-page.jsx`, replica pattern de `os-page.jsx`, adiciona route em `app.jsx`.
- **Conta C** → começar a transcrever os mocks pra backend Laravel real (`Modules/Officeimpresso/Tasks/*Task.php`, Inertia pages).

Cada conta atualiza só sua seção do `HANDOFF.md` quando terminar.

---

_Última atualização: sessão atual, ~80% do escopo._
