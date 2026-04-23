# CLAUDE.md — Primer para agentes de IA no projeto Ponto WR2

> **Leia este arquivo ANTES de qualquer outro quando abrir este repositório pela primeira vez.**
> Este é o ponto de entrada oficial para agentes de IA (Claude, Claude Code, Cursor, outros) e para desenvolvedores humanos.

---

## 1. O que é este projeto em 30 segundos

Módulo Laravel chamado **Ponto WR2** que adiciona controle de **ponto eletrônico brasileiro** (Portaria MTP 671/2021) ao **UltimatePOS v6** da WR2 Sistemas. Estende o **Essentials & HRM** existente sem modificar o core. Inclui: marcações (REP-P/AFD), banco de horas, intercorrências, apuração CLT, integração eSocial.

**Cliente:** WR2 Sistemas (Eliana, eliana@wr2.com.br)
**Stack REAL (atualizada em 2026-04-23):** **Laravel 13.6 + PHP 8.4** (Herd local), MySQL 8, nWidart/laravel-modules ^10, Redis 7. UltimatePOS v6.7.
**Stack helpers/UI:** `spatie/laravel-html` ^3.13 com shim `App\View\Helpers\Form` (substitui laravelcollective/html removido). **Inertia v2 + React + Tailwind 4**. Pest v4 + PHPUnit v12.
**IA:** `openai-php/laravel` REMOVIDO — Wagner vai usar **Vizra ADK + Prisma** como motor de IA.
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

## 7. Contato

**Cliente:** WR2 Sistemas — Eliana — eliana@wr2.com.br
**Repositório:** local, selecionado pelo usuário em `D:\oimpresso.com`
**Outros documentos do projeto** (fora desta pasta): `projeto_ponto_eletronico_wr2.md`, `especificacao_tecnica_laravel_wr2.md`, `ultimatepos6_hrm_especificacao_e_adaptacao.md`, `design_projeto_ponto_wr2.md` — pasta de outputs temporários do Cowork.

---

> **Última atualização deste arquivo:** 2026-04-23 (sessão 13 — Laravel 9→13.6 em 5 milestones; knox/pesapal inlined pra desbloquear L13; 99 tests Pest verdes)
> **Próxima revisão sugerida:** quando iniciar M2 Intercorrências (Vizra ADK + Prisma) OU quando Wagner integrar Laravel Boost
