# Briefing pra Claude Design — Migração dos módulos Blade do Oimpresso ERP

> **Para quem é este arquivo:** o Wagner abre uma nova sessão no **Claude Design** (canvas/artifact UI da Claude.ai) e cola este briefing como primeira mensagem. A IA da aba Design entrega mockups HTML + manuais por módulo, que depois eu (Claude Code) porto pro repo.
>
> **Por que existe:** Claude Design é excelente em iteração visual rápida e mockups HTML auto-contidos, mas NÃO tem acesso ao filesystem nem ao git. Este briefing concentra todo o contexto necessário pra ela trabalhar autônoma e produzir entregáveis aproveitáveis.
>
> **Última atualização:** 2026-04-27 (após Cockpit em produção e ADR UI-0008)

---

## 0. Como o Wagner usa este documento

1. Abre `https://claude.ai/design/` (ou continua a sessão "Oimpresso ERP Comunicação Visual")
2. Cola o conteúdo da **§9 Modelo de prompt** como primeira mensagem
3. Anexa este arquivo `BRIEFING_CLAUDE_DESIGN.md` à conversa
4. Itera com Claude Design — ela entrega zip com HTML + manuais
5. Manda o zip pra mim (Claude Code) — eu portfólio porto pro repo na branch atual ou em PR novo

---

## 1. Contexto em 2 minutos (pra Claude Design ler)

**Produto:** Oimpresso ERP — sistema de gestão pra gráficas e comunicação visual, baseado em UltimatePOS v6 (Laravel) com módulos próprios (Officeimpresso, Copiloto, MemCofre, Financeiro, PontoWr2, etc).

**Stack:**
- Backend: Laravel 13.6 + PHP 8.4 + nWidart Modules + MySQL 8 + Redis
- Frontend em migração: Blade legado → Inertia v3 + React 19 + TypeScript + Tailwind 4 + shadcn/ui + lucide-react

**Estado da migração (2026-04-27):**
- Já em React: Copiloto, MemCofre, Financeiro, Site, parcial em Essentials/Ponto
- Ainda em Blade (~18 módulos): Officeimpresso (núcleo do ERP), CRM, Sells/POS, PontoWr2, Stock, Purchases, Reports, etc.
- **Cockpit (layout-mãe novo) já em produção** em `/copiloto/cockpit` — sidebar dual Chat↔Menu + main contextual + Apps Vinculados (320px) + Tweaks panel.

**Cliente principal real:** ROTA LIVRE (biz=4) — Larissa, monitor 1280px, gráfica de bairro, opera o ERP 8h/dia. Não pode reaprender o sistema. Outros 6 clientes ativos com volume menor.

---

## 2. O que JÁ foi feito (NÃO redesenhar)

Estes pontos estão **fechados** — a aba Design não precisa repropor:

### Padrão Cockpit (ADR UI-0008)
- Layout 3 colunas: Sidebar 260px (dark) + Main 1fr + Apps Vinculados 320px
- Sidebar dual com toggle Chat↔Menu (estilo ChatGPT)
- Aba Chat: atalhos + Fixadas + Rotinas + Recentes
- Aba Menu: espelha `shell.menu` real (LegacyMenuAdapter — todos módulos do ERP)
- Rodapé: items superadmin (Backup/CMS/Connector/Office Impresso/Módulos) + user dropdown rico (perfil/disponível/aparência/atalhos/ajuda/sair)
- CompanyPicker no topo: dropdown de empresas + "Adicionar empresa"
- Apps Vinculados na direita: 5 cards canônicos (OS/Cliente/Financeiro/Anexos/Histórico) com origin badges (5 cores)
- Tweaks panel: Vibe (workspace/daylight/focus) + Densidade + Accent hue runtime
- Persistência total em `localStorage` com prefixo `oimpresso.cockpit.*`

### Tokens visuais já estabelecidos
- Tipografia: IBM Plex Sans (UI) + IBM Plex Mono (números/ID/timestamps)
- Cores semânticas em `oklch()` no CSS escopado em `.cockpit`
- Origin badges fixos: OS amber · CRM blue · FIN green · PNT violet · MFG orange
- Tipos de bolha (chat): me / them / note (📌) / file (anexo)
- Atalhos canônicos: J/K (navegar) · E (concluir) · A (adiar) · ⌘K (busca global) · / (busca local) · N (nova)

### Reference de implementação atual
- Protótipo HTML que materializou: `Oimpresso ERP - Chat.html` (projeto Cowork)
- Implementação React: `resources/js/Pages/Copiloto/Cockpit.tsx` + `resources/css/cockpit.css`
- ADR canônica: `memory/requisitos/_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md`
- Live: `https://oimpresso.com/copiloto/cockpit`

---

## 3. O que PRECISA ser feito (pedido principal)

Para cada módulo Blade que ainda não foi migrado, **Claude Design produz um Manual de Migração visual** com:

1. **Inventário das telas** do módulo no Blade atual (screenshots ou descrição)
2. **Mockup Cockpit-aware** de cada tela na nova UX (HTML auto-contido + screenshots)
3. **Mapa de migração**: qual tela Blade vira qual rota Inertia + qual layout (Cockpit envelope)
4. **Apps Vinculados específicos** que esse módulo expõe pra outros contextos (ex: módulo Sells expõe `LinkedSell` pra ver vendas do cliente em foco)
5. **TaskProvider stub** declarando quais tarefas o módulo registra na inbox unificada
6. **Lista de gaps** entre o Blade atual e o Cockpit-target (o que precisa ser reescrito vs reaproveitado)
7. **Ordem de execução** sugerida (do menos arriscado pro mais)

---

## 4. Inventário dos módulos Blade a migrar

| # | Módulo | Estado | Volume real | Priorização sugerida |
|---|---|---|---|---|
| 1 | **Officeimpresso** (OS, orçamento, produção, expedição) | Blade | Alto — núcleo ROTA LIVRE | **🔥 P0** — começar aqui |
| 2 | **Sells / POS** | Blade | Alto — venda diária | **🔥 P0** — junto com Officeimpresso |
| 3 | **Customers / CRM Lite** | Blade | Alto — cadastro cliente toda hora | **P1** |
| 4 | **PontoWr2** (marcações, banco horas, intercorrências) | Blade parcial | Médio — só usuário interno | **P1** — já tem ADRs próprias |
| 5 | **Stock / Inventory** (produtos, categorias, variantes, ajustes) | Blade | Médio | **P2** |
| 6 | **Purchases** (compras, fornecedores, ordens) | Blade | Médio | **P2** |
| 7 | **Expenses** (despesas, contas a pagar) | Blade | Médio | **P2** |
| 8 | **Reports** (vários relatórios diversos) | Blade | Baixo — uso pontual | **P3** — adapta caso a caso |
| 9 | **Cocina / Kitchen** | Blade | Zero — feature de restaurante | **Skip** — desativar |
| 10 | **Restaurant / Modifiers / Reservations** | Blade | Zero | **Skip** |
| 11 | **Woocommerce / Connector** | Blade | Baixo — superadmin | **P3** — fica no rodapé do Cockpit |

**Atalho de priorização:** começa pelo que ROTA LIVRE usa todo dia (1, 2, 3) e só depois o resto.

---

## 5. Estrutura do "Manual de Migração" por módulo (template a preencher)

Pra cada módulo, Claude Design entrega **um arquivo HTML auto-contido** + **um manual MD**, seguindo este esqueleto:

### Arquivo `manual-{modulo}.md`

```markdown
# Manual de Migração — Módulo {Nome}

## 0. Resumo executivo
- Estado atual: Blade
- Estado alvo: Inertia + React dentro do Cockpit
- Telas total: N (lista abaixo)
- Esforço estimado: S/M/L (sub-1h / 1-3h / 3h+)
- Risco: baixo/médio/alto (uso por cliente final?)

## 1. Inventário das telas Blade
| Rota | View atual | Tela alvo Inertia | Layout |
|------|-----------|-------------------|--------|
| GET /sells | Resources/views/sell/index.blade.php | Pages/Sells/Index.tsx | Cockpit (envelope) + UI-0006 (CRUD template no main) |
| ... | ... | ... | ... |

## 2. Mockup de cada tela
[Link pra arquivo HTML auto-contido demonstrando a tela alvo dentro do Cockpit]

## 3. Apps Vinculados que o módulo expõe
- LinkedSell — saldo/total vendido do cliente em foco
- LinkedOrcamento — orçamentos abertos do cliente
- LinkedProduto — produtos do mesmo lote/categoria

## 4. TaskProvider que o módulo registra
- VendaPendentePagamento — venda há >7 dias sem pagamento (origem FIN, badge red)
- OrcamentoExpirando — orçamento expira em 3 dias (origem CRM, badge amber)

## 5. Componentes shared a reusar (não recriar)
- PageHeader, KpiGrid, KpiCard, PageFilters, DataTable, EmptyState, BulkActionBar, StatusBadge

## 6. Endpoints backend que já existem (não recriar)
- GET /sells (controller atual)
- POST /sells (...)

## 7. Gaps a resolver
- Backend X falta endpoint Y
- Componente Z não existe nos shared

## 8. Ordem de execução
1. Tela mais simples (ex: lista) primeiro
2. ...

## 9. Snapshot visual antes/depois
[2 screenshots lado-a-lado]
```

### Arquivo `mockup-{modulo}.html`

HTML único auto-contido (estilo do `Oimpresso ERP - Chat.html`) usando React via CDN + Babel. Mostra a tela do módulo **dentro do Cockpit** — sidebar com Menu ativo no item correspondente, main column renderizando o template UI-0006 (PageHeader + KpiGrid + Table) ou um custom (Dashboard, gráfico), e Apps Vinculados específicos do contexto.

**Reaproveita** a base do Cockpit já existente (sidebar dark, vibe workspace, accent hue 220). Não redesenha — só **encaixa** o conteúdo do módulo.

---

## 6. Restrições e princípios (LEIA ANTES DE COMEÇAR)

### O que NÃO inventar

- ❌ **Não muda o padrão Cockpit** — sidebar dual, 3 colunas, dark sidebar, IBM Plex são fixos
- ❌ **Não inventa nova cor de origin** — se módulo precisar, escolhe entre as 5 (OS/CRM/FIN/PNT/MFG)
- ❌ **Não muda labels/ordem de itens do menu existente** — Eliana (cliente WR2) e Larissa (ROTA LIVRE) reconhecem o sistema atual
- ❌ **Não usa cor crua** (`bg-blue-500`) — só tokens semânticos `--accent`, `--text`, etc.
- ❌ **Não usa ícone de outra biblioteca** que não lucide-react

### O que SEMPRE faz

- ✅ **Persiste estado em `localStorage`** com prefixo correto (`oimpresso.{modulo}.*`)
- ✅ **Usa atalhos canônicos** J/K/E/A/⌘K
- ✅ **Mantém densidade respondendo ao Tweaks** (`--row-h`, `--card-pad`)
- ✅ **Apps Vinculados colapsáveis** com persistência individual
- ✅ **Origin badge** sempre que mostrar item cross-módulo
- ✅ **PT-BR em TODO label/copy/comentário** — sem texto em inglês na UI
- ✅ **Acessibilidade** — focus visível, aria-labels, contraste ≥ 4.5:1 nas duas Vibes

### Quando descobrir que não dá

Se um módulo tem **fluxo único que não cabe no Cockpit** (ex: tela de cadastro multi-step gigante, dashboard com gráfico custom de tela inteira), Claude Design propõe **exceção justificada** e abre uma sub-ADR `memory/requisitos/{Modulo}/adr/ui/NNNN-...md` no manual.

---

## 7. Output esperado (entregável)

Por módulo, um zip com:

```
manuais-{modulo}-{data}.zip
├── manual-{modulo}.md            ← documento principal
├── mockup-{modulo}.html          ← HTML auto-contido (React via CDN)
├── styles-{modulo}.css           ← (opcional) overrides locais — só se inevitável
├── components/                   ← componentes JSX do mockup
│   ├── ListaPrincipal.jsx
│   ├── Detalhe.jsx
│   ├── LinkedXXX.jsx             ← apps vinculados específicos
│   └── ...
├── screenshots/                  ← capturas de cada tela do mockup
│   ├── lista.png
│   ├── detalhe.png
│   ├── empty-state.png
│   └── ...
└── ANTES_E_DEPOIS.md             ← comparativo visual de cada tela
```

**Mais de 1 módulo na mesma sessão?** Mantém a mesma estrutura mas com prefixos: `manuais-officeimpresso-{data}.zip`, `manuais-sells-{data}.zip`, etc.

**Master index** opcional: `INDEX-MANUAIS.md` listando todos os módulos cobertos com links pros zips individuais e prioridade sugerida.

---

## 8. Como tirar o máximo do Claude Design (estilo de trabalho)

### Aproveitar pontos fortes da ferramenta

| Forte | Como explorar |
|---|---|
| **Iteração visual rápida** | Pede 2-3 variações de cada tela (densa / espaçada / com KPIs hero) e Wagner escolhe |
| **HTML auto-contido roda em qualquer browser** | Cada mockup vira "demo navegável" pra Wagner mostrar pra Eliana sem precisar deploy |
| **Componentes reutilizáveis em JSX inline** | Componentes do mockup já saem prontos pra eu portar pro `.tsx` real (~70% código reaproveitado) |
| **Variações de Vibe / Densidade** | Mostra como cada tela responde ao Tweaks panel — valida que o sistema escala |
| **Estados ricos** (vazio, error, loading, populado) | Gera 4 versões da mesma tela mostrando todos os estados — economiza N iterações no React real |

### Evitar pontos fracos

| Fraco | Como contornar |
|---|---|
| **Sem acesso ao filesystem** | Não pede pra "ler arquivo X do repo" — passa contexto via texto neste briefing |
| **Não roda código real do Laravel** | Mockup é puramente visual, dados mockados. Eu plugo backend depois |
| **Pode "esquecer" do contexto longo** | Cada mensagem nova relembra: "padrão Cockpit, sidebar dual, 3 colunas, IBM Plex" — pelo menos 1 frase |
| **Não persiste entre sessões** | Sempre exporta zip ao final — se sessão acabar, perde |

### Padrão de iteração recomendado

1. **Abre sessão** com o **Modelo de prompt da §9** + anexa este arquivo
2. Pede **inventário** do módulo primeiro (Claude Design lista o que vai cobrir)
3. Wagner aprova o inventário
4. Itera tela por tela: Claude Design mostra mockup → Wagner ajusta → próxima tela
5. Quando módulo completo, pede zip com todos os artefatos
6. Wagner manda zip pra mim (Claude Code) — eu porto pro repo

### Checkpoint humano por tela (não pular)

Antes de Claude Design dar uma tela como "pronta":

- ☐ Tela vive dentro do Cockpit (sidebar + topbar + main + apps opcional)?
- ☐ Tokens CSS do Cockpit (sem cor hardcoded)?
- ☐ Apps Vinculados quando há contexto?
- ☐ Atalhos canônicos respeitados?
- ☐ Estado persiste em `localStorage`?
- ☐ PT-BR em todo label?
- ☐ Empty state pensado?
- ☐ Mostrou nas 3 Vibes (workspace/daylight/focus)?
- ☐ Wagner viu e aprovou?

---

## 9. Modelo de prompt (copy-paste pra abrir sessão Claude Design)

```
Oi! Estou trabalhando na migração do ERP Oimpresso (Laravel) — telas Blade legadas pra
React/Inertia dentro do padrão "Cockpit" (3 colunas + sidebar dual + Apps Vinculados)
que já está em produção em /copiloto/cockpit.

Anexei o briefing completo (BRIEFING_CLAUDE_DESIGN.md). Por favor:

1. Lê o briefing inteiro antes de propor qualquer coisa
2. Confirma que você entende o padrão Cockpit (NÃO redesenhar)
3. Pega o módulo {Officeimpresso | Sells | Customers | etc} listado na §4
4. Produz o **Manual de Migração** seguindo o template da §5
5. Entrega zip com mockup HTML + manual MD + screenshots por tela

Restrições críticas (§6):
- Sidebar/topbar/colunas do Cockpit são FIXAS — não muda
- Origin badges só nas 5 cores semânticas (OS/CRM/FIN/PNT/MFG)
- PT-BR em todo label
- localStorage com prefixo correto
- Reaproveitar componentes shared do projeto antes de criar novo

Antes de começar a desenhar, me mostra o **inventário das telas** que você cobrirá
neste módulo + ordem de execução, pra eu validar o escopo. Quando aprovar, segue.

Quero 2 variações de cada tela importante (denso vs espaçado), pra escolher.
Quero estados completos (vazio, error, loading, populado).
Quero ver cada tela renderizada nas 3 Vibes (workspace/daylight/focus).

Vamos começar pelo módulo: **{Officeimpresso}**.
```

(Wagner substitui `{Officeimpresso}` pelo módulo da vez)

---

## 10. Ciclo completo de uma tela (passo a passo real)

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Wagner abre sessão Claude Design + cola prompt + anexa MD    │
│    [tempo: 2 min]                                               │
└─────────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. Claude Design devolve INVENTÁRIO do módulo                   │
│    (lista de telas, ordem, esforço estimado)                    │
└─────────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. Wagner aprova ou ajusta inventário                           │
│    [tempo: 5 min]                                               │
└─────────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. Claude Design entrega Tela 1 (mockup HTML + 2 variações)     │
│    + screenshots (vazio/populado/error/3 vibes)                 │
└─────────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│ 5. Wagner valida — escolhe variação, pede ajuste se preciso     │
│    [tempo: 5-15 min de iteração]                                │
└─────────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│ 6. Repete 4-5 pra cada tela do módulo                           │
└─────────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│ 7. Quando módulo completo, Claude Design empacota zip com tudo  │
│    + Manual MD final                                            │
└─────────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│ 8. Wagner baixa zip, manda pra mim (Claude Code via "@arquivo") │
└─────────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│ 9. Claude Code lê zip, porta JSX → TSX, cria controller Inertia,│
│    plug backend real, build, commit, push, deploy local + prod  │
│    [tempo: 1-3h por módulo dependendo do tamanho]               │
└─────────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│ 10. Wagner valida em produção (smoke test) → mergeia PR no main │
└─────────────────────────────────────────────────────────────────┘
```

---

## 11. Apêndice — Referências do projeto

### Arquivos de memória relevantes (ordem de leitura)

1. **CLAUDE.md** — primer geral do projeto (§10 trata de UI)
2. **memory/decisions/0039-ui-chat-cockpit-padrao.md** — ADR original do Cockpit
3. **memory/requisitos/_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md** — consolidação canônica
4. **memory/requisitos/_DesignSystem/SPEC.md** — regras R-DS-001 a R-DS-012
5. **memory/requisitos/_DesignSystem/CHANGELOG.md** — histórico de mudanças
6. **memory/sessions/2026-04-27-cockpit-deprecates-old-layout.md** — re-análise

### Arquivos do projeto Cowork "Oimpresso ERP Comunicação Visual"

- `Oimpresso ERP - Chat.html` — entry HTML do protótipo de referência
- `app.jsx`, `sidebar.jsx`, `chat.jsx`, `linked-apps.jsx`, `tweaks-panel.jsx`, `viewers.jsx`, `tasks.jsx`, `data.jsx`, `icons.jsx` — componentes JSX do protótipo
- `styles.css` — CSS completo do Cockpit (já portado, escopado em `.cockpit`)
- `Inventario - Migracao Blade React.html` — possível mapa visual de migração (consultar)

### Arquivos da implementação React real (referência viva)

- `resources/js/Pages/Copiloto/Cockpit.tsx` (~700 linhas)
- `resources/css/cockpit.css` (~1000 linhas)
- `resources/js/Layouts/AppShell.tsx` (legado, ainda usado em telas standalone)
- `resources/js/Components/shared/*.tsx` — `PageHeader`, `KpiGrid`, `KpiCard`, `DataTable`, `PageFilters`, `EmptyState`, `BulkActionBar`, `StatusBadge`, `ModuleTopNav`

### Live em produção

- `https://oimpresso.com/copiloto/cockpit` — Cockpit em produção (Wagner pode logar como referência viva)
- `https://oimpresso.com/copiloto` — versão Chat.tsx atual (ainda em uso)
- `https://oimpresso.com/showcase/components` (superadmin) — design system showcase

---

## 12. Pergunta-padrão pra Wagner antes de cada módulo

Antes de pedir Claude Design começar um módulo, Wagner se pergunta:

1. **Quem usa esse módulo na vida real?** (Larissa? Funcionário interno? Superadmin Wagner?)
2. **Quantas vezes por dia?** (Várias = P0, semanal = P2, mensal = P3)
3. **Quebra se ficar fora do ar 1 dia?** (Sim = não migrar antes de testar bem; não = pode arriscar)
4. **Tem monitor pequeno?** (Larissa = 1280px, mantém Apps Vinculados colapsáveis)
5. **Tem fluxo crítico que não cabe no Cockpit?** (Se sim, abrir exceção em ADR per-módulo)

---

## 13. Critério de "pronto" pra um manual de módulo

Manual está **pronto pra portar** quando:

- ☐ Inventário cobre 100% das telas Blade do módulo (incluindo as raras)
- ☐ Cada tela tem mockup HTML navegável (não só screenshot)
- ☐ Cada tela tem versão "vazia" (empty state) e "populada"
- ☐ LinkedApps específicos do módulo estão definidos (mesmo que stub)
- ☐ TaskProvider stub criado (mesmo que sem implementação backend)
- ☐ Mapa de rotas Blade → Inertia preenchido
- ☐ Wagner viu, brincou no HTML, aprovou
- ☐ Zip bem-formado seguindo §7

---

> **Última frase importante:** Claude Design **NÃO substitui o pensamento de produto** — ela acelera a iteração visual. Wagner continua sendo o owner da decisão de UX. Eu (Claude Code) continuo sendo o portador do código pro repo. Esse triângulo (Wagner ↔ Claude Design ↔ Claude Code) é o que faz a migração caber em sprints curtos sem perder qualidade.
