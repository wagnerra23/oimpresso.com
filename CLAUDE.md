# CLAUDE.md — Primer para agentes de IA no projeto Ponto WR2

> **Leia este arquivo ANTES de qualquer outro quando abrir este repositório pela primeira vez.**
> Este é o ponto de entrada oficial para agentes de IA (Claude, Claude Code, Cursor, outros) e para desenvolvedores humanos.

---

## 1. O que é este projeto em 30 segundos

Módulo Laravel chamado **Ponto WR2** que adiciona controle de **ponto eletrônico brasileiro** (Portaria MTP 671/2021) ao **UltimatePOS v6** da WR2 Sistemas. Estende o **Essentials & HRM** existente sem modificar o core. Inclui: marcações (REP-P/AFD), banco de horas, intercorrências, apuração CLT, integração eSocial.

**Cliente:** WR2 Sistemas (Eliana, eliana@wr2.com.br)
**Stack REAL (atualizada em 2026-04-23):** **Laravel 13.6 + PHP 8.4** (Herd local), MySQL 8, nWidart/laravel-modules ^10, Redis 7. UltimatePOS v6.7.
**Stack helpers/UI:** `spatie/laravel-html` ^3.13 com shim `App\View\Helpers\Form` (substitui laravelcollective/html removido). **Inertia v3 + React + Tailwind 4** (upgrade v2→v3 mergeado em 2026-04-25, ver ADR 0023). Pest v4 + PHPUnit v12.
**IA:** sem pacote IA instalado em `composer.lock` (`openai-php/laravel` foi removido). Drivers do Copiloto ainda referenciam `OpenAI\Laravel\Facades\OpenAI` em [Modules/Copiloto/Services/Ai/OpenAiDirectDriver.php](Modules/Copiloto/Services/Ai/OpenAiDirectDriver.php), portanto **Copiloto só roda em `COPILOTO_AI_DRY_RUN=true`** (devolve fixtures).

**Stack-alvo (VERDADE CANÔNICA — declarada por Wagner em 2026-04-26 sessão 17 como "melhor ROI", formalizada em ADR 0035 que consolida ADRs 0031/0032/0033/0034):**
- **Camada A (LLM wrapper):** **Laravel AI SDK oficial** ([`laravel/ai`](https://github.com/laravel/ai), lançado fev/2026, first-party Laravel team). Cobre texto+audio+images+embeddings+tools+structured+vector store. Fallback documentado: Prism PHP.
- **Camada B (framework de agente):** **Vizra ADK** ([`vizra/vizra-adk`](https://github.com/vizra-ai/vizra-adk)). Multi-agent workflows, sub-agent delegation, eval LLM-as-Judge, auto-tracing, 20+ assertions, Vizra Cloud opcional.
- **Camada C (memória):** `MemoriaContrato` interface PHP com 3 drivers — **`MeilisearchDriver` DEFAULT** (self-hosted, R$0/mês recorrente — local rodando + Hostinger v1.10.3 instalado), `Mem0RestDriver` upgrade **condicional sprint 8+** (managed $25-300/mês — só quando trigger ativar), `NullMemoriaDriver` em dev. **Replanejamento por ADR 0036** após confirmar Meilisearch funcional na sessão 17 — economiza R$1.500-18.000/ano até comprovar tese. **`pgvector` rejeitado** — exige PostgreSQL (não temos). Ver ADRs 0033/0036.
- **Tooling dev:** **Laravel Boost** (`laravel/boost`) — MCP tools + 17k pieces de Laravel knowledge + AI guidelines pra Cursor/Claude escreverem Laravel idiomático. **Laravel MCP** (`laravel/mcp`) — futuro: expor Copiloto pra Claude Desktop / agentes externos.
- **Search engine non-Copiloto:** Scout database driver default; Meilisearch self-hosted só quando volume justificar.

**Roadmap revisado 7 sprints (ADR 0036):** sprint 1 = `laravel/ai` swap ✅ FEITO ([PR #24 mergeado](https://github.com/wagnerra23/oimpresso.com/pull/24)); 2 = **deploy do sprint 1 + iniciar Meilisearch daemon Hostinger** (tira Copiloto de fixtures EM PRODUÇÃO); 3 = Vizra ADK (sessions/tools/traces); 4-5 = **`MeilisearchDriver` primeiro** (R$0/mês recorrente vs $25-300 do Mem0); 6 = tela LGPD `/copiloto/memoria`; 7 = eval LLM-as-Judge + stress test; 8+ (CONDICIONAL) = `Mem0RestDriver` só se trigger ativar (dedup falha, conversa >50 turnos perde contexto, etc).

**Comparativos completos:**
- [stack_agente_php_vizra_prism_mem0_capterra_2026_04_26.md](memory/comparativos/stack_agente_php_vizra_prism_mem0_capterra_2026_04_26.md) (7 players)
- [copiloto_runtime_memory_vs_mem0_langgraph_letta_zep_capterra_2026_04_26.md](memory/comparativos/copiloto_runtime_memory_vs_mem0_langgraph_letta_zep_capterra_2026_04_26.md) (Camada C apenas)
**Padrão arquitetural:** Modular monolith, DDD leve, append-only onde a lei exige.
**Módulos de referência canônica:** `Modules/Jana/`, `Modules/Repair/`, `Modules/Project/` — antes de criar ou ajustar qualquer arquivo no PontoWr2, olhe o equivalente em um desses três (preferir o mais próximo em complexidade/propósito) e imite. Ver ADR 0011.
**Status atual (2026-04-23):** Laravel 13.6 rodando; 99 tests automatizados verdes (26 Form shim + 73 crawler); browser validado via oimpresso.test (Herd). Upgrade em cascata 9→10→11→12→13 executado no mesmo dia. `knox/pesapal` inlined em `app/Vendor/Pesapal` pra destravar L13 (upstream sem versão L13).

---

## 2. Como trabalhar neste projeto (fluxo obrigatório)

Sempre que você (agente ou humano) for atuar neste projeto:

1. **Leia o índice de memória** em `memory/INDEX.md` — ele tem a lista completa de documentos.
2. **Leia o handoff** em `memory/08-handoff.md` — tem o estado mais recente, pendências e como retomar.
3. **Leia o session log mais recente** em `memory/sessions/` (última data) — tem o contexto imediato da última sessão.
4. **Consulte ADRs relevantes** em `memory/decisions/` — decisões arquiteturais com justificativa e alternativas consideradas.
5. **Siga as convenções** de `memory/04-conventions.md` — código, nomenclatura, idioma, commit, etc.
6. **Respeite as preferências do usuário** em `memory/05-preferences.md` — capturadas ao longo das conversas.

Ao terminar uma sessão:

7. **Atualize o handoff** (`memory/08-handoff.md`) com o novo estado.
8. **Crie um session log** em `memory/sessions/YYYY-MM-DD-session-NN.md` descrevendo o que foi feito.
9. **Se tomou alguma decisão arquitetural nova, crie uma ADR** em `memory/decisions/NNNN-slug.md`.

---

## 3. Ordem de leitura para onboarding completo

Se você tem tempo de ler tudo antes de começar, esta é a ordem ideal (≈20 min):

1. `CLAUDE.md` (este arquivo)
2. `memory/INDEX.md`
3. `memory/00-user-profile.md` — Quem é o cliente e o que ele valoriza
4. `memory/01-project-overview.md` — O que estamos construindo e por quê
5. `memory/02-technical-stack.md` — Tecnologias escolhidas e razões
6. `memory/03-architecture.md` — Visão arquitetural de alto nível
7. `memory/04-conventions.md` — Como escrever código aqui
8. `memory/05-preferences.md` — Preferências capturadas do usuário
9. `memory/06-domain-glossary.md` — Termos de legislação trabalhista BR
10. `memory/07-roadmap.md` — Fases do projeto e status
11. `memory/08-handoff.md` — Onde paramos na última sessão
12. `memory/decisions/` — ADRs em ordem cronológica (0001 → mais recente)
13. `memory/sessions/` — Session logs do mais antigo ao mais recente

---

## 4. Estrutura do repositório

```
D:\oimpresso.com\                  # Raiz do projeto (workspace do usuário)
├── CLAUDE.md                       # Este arquivo
├── AGENTS.md                       # Mirror para outros agentes (opcional)
├── memory/                         # Sistema de memória do projeto
│   ├── INDEX.md                    # Índice mestre
│   ├── 00-user-profile.md
│   ├── 01-project-overview.md
│   ├── 02-technical-stack.md
│   ├── 03-architecture.md
│   ├── 04-conventions.md
│   ├── 05-preferences.md
│   ├── 06-domain-glossary.md
│   ├── 07-roadmap.md
│   ├── 08-handoff.md
│   ├── decisions/                  # ADRs (Michael Nygard format)
│   └── sessions/                   # Logs cronológicos por sessão
├── Modules/
│   ├── Jana/                       # Módulo de referência canônica (imitar sempre)
│   └── PontoWr2/                   # Módulo principal — padrão Jana após sessão 02
│       ├── start.php               # ← Carregado via module.json "files"
│       ├── module.json
│       ├── composer.json
│       ├── Config/
│       ├── Console/Commands/
│       ├── Database/Migrations/
│       ├── Entities/               # Models Eloquent
│       ├── Http/
│       │   ├── routes.php          # ← ÚNICO arquivo de rotas (web + api + install)
│       │   ├── Controllers/
│       │   ├── Middleware/
│       │   └── Requests/
│       ├── Providers/              # Apenas PontoWr2ServiceProvider (sem RouteServiceProvider)
│       ├── Resources/
│       │   ├── views/              # Blade templates
│       │   └── lang/pt/            # Idioma com código curto (não pt-BR)
│       ├── Services/               # ApuracaoService, BancoHorasService, etc.
│       └── Tests/
└── (UltimatePOS + Essentials vivem fora deste repo, na instalação cliente)
```

---

## 5. O que NÃO fazer

- **Não modifique tabelas do core UltimatePOS** (`users`, `business`, `employees`, etc.). Use a tabela bridge `ponto_colaborador_config`.
- **Não faça UPDATE ou DELETE em `ponto_marcacoes`** — é append-only por força de lei (Portaria 671/2021). Use `Marcacao::anular()`.
- **Não remova triggers MySQL** de imutabilidade sem abrir ADR justificando.
- **Não crie novas tecnologias/dependências** sem registrar uma ADR.
- **Não responda ao usuário em inglês** — este cliente é brasileiro e prefere PT-BR.
- **Não assuma que o usuário quer completude** — ele valoriza economia de crédito; confirme escopo com perguntas curtas antes de implementar massivamente.
- ~~**Não use sintaxe PHP 8+.**~~ **REVOGADO em 2026-04-21** — stack atual é PHP 8.4 + Laravel 13.6. Toda sintaxe PHP 8.x está liberada.
- **Não remover o shim `App\View\Helpers\Form`** sem antes migrar as ~6.433 chamadas `Form::` em ~460 Blade views. O shim preserva paridade HTML com laravelcollective (removido). Ver `memory/sessions/2026-04-23-session-13.md`.
- **Antes de criar/mudar estrutura do módulo, verifique o padrão dos módulos existentes no servidor atualizado.** nWidart v10+ usa `Routes/web.php` + `RouteServiceProvider`. Inspecionar `Modules/Jana/` antes de codificar.
- **Identificadores MySQL com mais de 64 chars** — ainda válido. Sempre passar nome explícito em índices compostos em tabelas com nome longo.
- **Não faça UPDATE ou DELETE em `ponto_marcacoes`** — append-only por lei (Portaria 671/2021).
- **Não suba código para produção sem alertar o usuário de pré-requisitos e riscos.** Histórico de crashes: 2026-04-18 (scaffold incompatível), 2026-04-19 (PHP 8 em servidor PHP 7.1), 2026-04-21 (módulo desativado após upgrade para 6.7). **Lição:** sempre testar em staging antes de ativar módulo no servidor.

---

## 6. O que SEMPRE fazer

- Respeite o idioma: **todo texto, commit, comentário, label → PT-BR**. Código (classes, métodos, variáveis) em inglês é OK, mas domínio de negócio usa nomes PT (ex.: `Marcacao`, `Intercorrencia`, `BancoHoras`).
- Cite a lei quando aplicável (ex.: *Art. 66 CLT*, *Portaria 671/2021 Anexo I*, *LGPD Art. 7º*).
- Preserve imutabilidade de marcações e de movimentos de banco de horas.
- Mantenha o `business_id` scopado em todas as queries (multi-empresa UltimatePOS).
- Escreva testes ao menos para regras CLT (tolerâncias, intrajornada, interjornada, HE).
- **Antes de criar/mudar estrutura do módulo, abra `Modules/Jana/` (ou `Repair`/`Project`) e imite.** Triangular entre os 3 ajuda a identificar o que é essencial do padrão vs. variação de caso de uso. Se nenhum dos três tem — pense duas vezes. Se tem mas eu quero divergir — registre ADR explicando por quê.
- **Use stack de middlewares UltimatePOS:** `['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']` para rotas web.

---

## 7. Cofre de comparativos & gestão de memória

**Comparativos competitivos** (estilo Capterra/G2) ficam em [`memory/comparativos/`](memory/comparativos/):
- Template oficial: [`memory/comparativos/_TEMPLATE_capterra_oimpresso.md`](memory/comparativos/_TEMPLATE_capterra_oimpresso.md) v1.0
- Índice: [`memory/comparativos/_INDEX.md`](memory/comparativos/_INDEX.md)
- Checklist obrigatório: TL;DR 5 frases + ≥4 concorrentes + 30+ features + exatamente 3 GAPs + exatamente 3 vantagens + ≥3 caminhos de posicionamento + math da meta + 3 ações prioritárias + métrica de fé 90d + sources literais

**Trigger "guarde no cofre":** quando Wagner pedir, classifique antes de salvar:
- Comparativo competitivo → `memory/comparativos/`
- Decisão arquitetural → `memory/decisions/NNNN-slug.md` (formato Nygard, ver ADR 0028)
- User story / requisito → `memory/requisitos/{Modulo}/SPEC.md`
- Preferência do usuário ou quirk de cliente → auto-memória do agente (fora do git)
- Evidência (print, log, chat) → `Modules/MemCofre/` (entidades `Doc*`) — ainda sem UI de upload, registrar em arquivo na branch enquanto isso
- Sempre confirmar com link curto pra Wagner.

**Papéis canônicos** de cada sistema de memória estão formalizados em [ADR 0027](memory/decisions/0027-gestao-memoria-roles-claros.md) (meta-ADR). Resumo: handoff em `memory/08-handoff.md`, ADRs em `memory/decisions/`, sessões cronológicas em `memory/sessions/`, specs por módulo em `memory/requisitos/{Mod}/`, cross-conversation em auto-memória, evidências em MemCofre, auditoria em git.

**Não duplicar info entre sistemas.** Se já está no repo (cross-agent), auto-memória só aponta. Conflito de fato entre 2 fontes = bug.

---

## 8. Acesso à produção (Hostinger)

Servidor de produção do oimpresso.com (Cloud Startup, IPv4 only — sempre `-4`):

```
Host: 148.135.133.115
Port: 65002
User: u906587222
Key:  ~/.ssh/id_ed25519_oimpresso
Repo: ~/domains/oimpresso.com/public_html      (Laravel)
DB:   u906587222 / o51617061                    (MySQL, no mesmo host)
PHP:  /usr/bin/php          (8.4.19)
Composer: /usr/local/bin/composer
```

**Comando padrão:**
```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 -o ConnectTimeout=60 u906587222@148.135.133.115 'CMD'
```

**Cuidados:**
- Sempre `-4` (IPv4 forçado). Sem isso o handshake falha intermitentemente.
- SSH é **flaky**: 1ª tentativa frequentemente dá timeout. Receita: `curl -s4 https://oimpresso.com/ > /dev/null` pra warm e retry com `ConnectTimeout=120`.
- Multiplexing (`ControlMaster=auto`) **não funciona** no Hostinger — uma conexão por comando.
- **Nunca editar arquivo direto via SSH** — sempre `git pull origin 6.7-bootstrap` no repo. Bypass git = drift permanente (já queimou Eliana no 3.7→6.7).
- **Após push em 6.7-bootstrap com mudança em `composer.json`/`composer.lock`:** rodar `composer install` (sem `--no-dev` — Faker é usado em prod). O workflow `quick-sync.yml` NÃO faz isso; sintoma de skip é tela branca Inertia. Ver `memory/sessions/` para incidente do upgrade Inertia v3.
- WP `/ajuda/` tem patch manual de PHP 8.4 (`create_function` → closures) — atualização via wp-admin reverte; ver auto-memória `reference_wp_ajuda_fix.md` se precisar repatchar.

**Receita de deploy manual** (quando `quick-sync.yml` falhar — ver auto-memória `reference_quick_sync_quebrada.md`):
```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  "cd domains/oimpresso.com/public_html && \
   git pull origin 6.7-bootstrap && \
   php artisan optimize:clear && \
   composer dump-autoload"
```
Se `composer.lock` mudou, trocar `dump-autoload` por `composer install`.

---

## 9. Contato

**Cliente:** WR2 Sistemas — Eliana — eliana@wr2.com.br
**Repositório:** local, selecionado pelo usuário em `D:\oimpresso.com`
**Outros documentos do projeto** (fora desta pasta): `projeto_ponto_eletronico_wr2.md`, `especificacao_tecnica_laravel_wr2.md`, `ultimatepos6_hrm_especificacao_e_adaptacao.md`, `design_projeto_ponto_wr2.md` — pasta de outputs temporários do Cowork.

---

## 10. Padrão de UI/UX para criar ou alterar telas no React

> **Leia esta seção SEMPRE que for criar nova tela ou alterar tela existente em React (Inertia v3).**
> Designer canônico do ERP em React = **Claude** (Anthropic). Padrão formalizado em [ADR 0039](memory/decisions/0039-ui-chat-cockpit-padrao.md).

### 10.1 — Antes de codificar qualquer tela

1. **Leia [ADR 0039](memory/decisions/0039-ui-chat-cockpit-padrao.md)** — define layout-mãe "Chat Cockpit" 3-colunas, dual-tab Chat/Menu, painel direito de Apps Vinculados, atalhos J/K/E/A, Tweaks (vibe/densidade/accentHue).
2. **Leia o session log mais recente em `memory/sessions/`** — pode ter ajuste de design não refletido no ADR ainda.
3. **Olhe o protótipo de referência** — projeto Cowork "Oimpresso ERP Comunicação Visual", arquivo `Oimpresso ERP - Chat.html`. É a verdade visual mais atual.
4. **Olhe `resources/js/Layouts/AppShell.tsx`** (atual) e o futuro `AppShellV2.tsx` (quando portado) — cliente final NÃO pode reaprender o sistema. Qualquer mudança de menu/labels/ícones precisa ser aprovada explicitamente.

### 10.2 — Hierarquia de decisões de UI

Em ordem de precedência (de cima pra baixo, regra mais alta vence em conflito):

1. **Stack-target do projeto** (Inertia v3 + React 19 + TS + Tailwind 4) — não muda sem ADR.
2. **Layout-mãe "Chat Cockpit"** (ADR 0039) — não muda sem ADR substitutivo.
3. **Padrão Jana** (ADR 0011) — UltimatePOS-like; vale para tudo que não conflita com 0039.
4. **Componentes shared do projeto** (`PageHeader`, `DataTable`, `PageFilters`, `KpiCard`, `ModuleTopNav`, `StatusBadge`, `EmptyState`) — usar antes de criar novo.
5. **Convenções 04** (`memory/04-conventions.md`) — naming PHP, rotas, blade.
6. **Bom gosto do designer** — em última instância, Claude decide visual sem perguntar; mas registra a decisão no session log.

### 10.3 — Layout obrigatório de tela nova

Toda tela React do ERP **nasce dentro do `AppShellV2`** (3 colunas), com:

- **Sidebar (260px)** vinda do shell — você não recria sidebar dentro de página.
- **Topbar com breadcrumb** vinda do shell — você só passa `crumb={[...]}` via Inertia layout.
- **Coluna principal (1fr)** = sua tela.
- **Coluna direita (320px) "Apps Vinculados"** — *opcional*. Se sua tela tem contexto vinculado (uma OS em foco, um cliente, uma marcação), **você é obrigado a entregar o painel direito** com os blocos relevantes. Se não tem, a coluna some.

Para tela em modo **master/detail** (lista + viewer), use o padrão de `Pages/Tarefas/Index.tsx`:
- Lista à esquerda da coluna principal (ex.: 360px), viewer à direita (1fr).
- Atalhos **J/K** (navegar), **E** (concluir/confirmar), **A** (adiar/voltar) ligados via `useEffect` + listener global escopado à página.

Para tela em modo **CRUD clássico** (cadastro, listagem, edição), siga padrão Jana: `PageHeader` + `PageFilters` + `DataTable` + drawer/modal de edição.

### 10.4 — Tokens visuais

Use **sempre** as variáveis CSS do shell (definidas em `resources/css/app.css`):

```
--bg, --bg-2, --panel, --panel-2, --border, --border-2
--text, --text-mute, --accent, --accent-2, --accent-soft
--origin-OS-{bg,fg}, --origin-CRM-{bg,fg}, --origin-FIN-{bg,fg}, --origin-PNT-{bg,fg}
--row-h, --card-pad, --card-gap
```

**Não invente cor solta.** Se precisar de uma cor nova, derive via `oklch()` a partir de `--accent` ou da origem do módulo.

### 10.5 — Apps Vinculados (coluna direita)

Cada bloco do painel direito é um componente em `resources/js/Components/LinkedApps/`:

- `LinkedOs.tsx` — número, cliente, prazo, estágio, CTA `[abrir]`
- `LinkedClient.tsx` — nome, telefone, último contato, CTA `[ligar]` `[whatsapp]`
- `LinkedPonto.tsx` — marcações do colaborador no dia, CTA `[justificar]`
- `LinkedFinanceiro.tsx` — saldo cliente + boletos abertos, CTA `[emitir cobrança]`
- `LinkedAttachments.tsx` — anexos da conversa/tarefa
- `LinkedHistory.tsx` — eventos cronológicos

**Regra:** cada bloco é colapsável (estado em `localStorage` por chave `oimpresso.linked.<bloco>.collapsed`), mostra um resumo enxuto e UMA ação primária. Se a tela não tem dado para um bloco, ele simplesmente não renderiza.

### 10.6 — TaskProvider (quando criar tela de inbox)

Se a tela nova é uma inbox de pendências do módulo, **NÃO crie tela própria**. Em vez disso, registre um `TaskProvider`:

```php
// Modules/<Mod>/Tasks/<Slug>Task.php
class <Slug>Task implements TaskProvider {
  public function origin(): string { return 'OS'|'CRM'|'FIN'|'PNT'|'MFG'; }
  public function color(): string  { return 'amber'|'blue'|'emerald'|'violet'|'orange'; }
  public function for(User $u): Collection { /* o que esse usuário precisa fazer */ }
  public function viewerComponent(): string { return '<NomeDoComponenteReact>'; }
}
```

E entregue o componente viewer em `resources/js/Components/Viewers/<NomeDoComponenteReact>.tsx`. A tela `Pages/Tarefas/Index.tsx` agrega via `TaskRegistry` e renderiza o viewer correto.

### 10.7 — Persistência de estado de UI

**Sempre** persistir em `localStorage` com prefixo `oimpresso.`:
- estado de empresa ativa, aba, rota, conversa, tarefa selecionada, filtros
- estado de painéis (colapsado/aberto), accordions do menu
- preferências do Tweaks panel

Nunca persistir em `sessionStorage` para esses casos — perdem na nova aba.

### 10.8 — Atalhos de teclado

Lista canônica do ERP (toda tela nova herda):
- **⌘K / Ctrl+K** — busca global (já no shell)
- **J / K** — navegar lista (em master/detail)
- **E** — concluir/confirmar item em foco
- **A** — adiar/postergar item em foco
- **N** — nova entidade (em listagem CRUD; verbo do módulo)
- **/** — focar busca da lista atual

Toda tela com lista deve registrar listener via `useEffect` e `removeEventListener` no cleanup.

### 10.9 — Quando divergir do padrão

Se você (humano ou agente) achar que precisa quebrar o padrão de UI:

1. **Pare antes de codificar.**
2. **Abra ADR nova** (próximo número sequencial após o último em `memory/decisions/`) explicando contexto/decisão/alternativas/consequências.
3. **Peça aprovação do Wagner** antes de mergear.
4. **Atualize esta §10** com a nova regra.

Padrão muda por ADR, nunca por commit solto.

### 10.10 — Checklist mínimo antes de PR

- [ ] Tela vive dentro de `AppShellV2`
- [ ] Tokens CSS do shell (sem cor hardcoded)
- [ ] Coluna direita "Apps Vinculados" entregue se houver contexto vinculado
- [ ] Atalhos J/K/E/A ativos se for master/detail
- [ ] Estado persistido em `localStorage` com prefixo `oimpresso.`
- [ ] Componentes shared reusados antes de criar novo
- [ ] PT-BR em todo label/copy/comentário
- [ ] Se inbox de módulo → `TaskProvider` em vez de tela nova
- [ ] Session log atualizado em `memory/sessions/`
- [ ] ADR nova se quebrou padrão

---

> **Última atualização deste arquivo:** 2026-04-27 (sessão de design — ADR 0039 padrão "Chat Cockpit" formalizado, §10 adicionada via PR `feat/adr-0039-chat-cockpit-padrao`)
> **Próxima revisão sugerida:** quando portar `AppShellV2.tsx` pro repo (Fase 1 da migração ADR 0039)
