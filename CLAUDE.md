# CLAUDE.md — Primer para agentes de IA no projeto Oimpresso ERP

> **Leia este arquivo ANTES de qualquer outro quando abrir este repositório pela primeira vez.**
> Este é o ponto de entrada oficial para agentes de IA (Claude, Claude Code, Cursor, outros) e para desenvolvedores humanos.

---

## 1. O que é este projeto em 30 segundos

ERP gráfico brasileiro para o setor de **comunicação visual** (gráficas rápidas, plotters, fachadas, brindes). Construído em cima do **UltimatePOS v6** com módulos próprios em `Modules/` (Copiloto IA, Financeiro, MemCofre, Cms, Officeimpresso, Ponto, Connector, etc.).

**Originalmente nasceu** como módulo Ponto WR2 (controle de ponto eletrônico Portaria MTP 671/2021) e evoluiu pra plataforma vertical completa.

**Cliente principal de produção:** ROTA LIVRE (`business_id=4`, Larissa).
**Cliente PontoWr2:** WR2 Sistemas (Eliana, eliana@wr2.com.br).

**Stack REAL:** Laravel **13.6** + PHP 8.4 (Herd) · MySQL Laragon · DB `oimpresso` · Inertia **v3** + React 19 + Tailwind 4 · Pest v4 · nWidart/laravel-modules ^10 · `spatie/laravel-html` ^3.13 com shim `App\View\Helpers\Form`.

**Stack-alvo IA (verdade canônica ADR 0035 + 0036):**
- **Camada A:** `laravel/ai` ^0.6.3 (oficial fev/2026)
- **Camada B:** Vizra ADK quando suportar L13 (hoje `LaravelAiSdkDriver` + 4 Agents)
- **Camada C:** `MemoriaContrato` + `MeilisearchDriver` default + `NullDriver` dev (Mem0 sprint 8+ condicional)
- **Tooling:** Boost + MCP + Scout + Horizon + Telescope + Pail

**Padrão arquitetural:** Modular monolith, DDD leve, append-only onde a lei exige, `business_id` global scope obrigatório.

**Módulos de referência canônica:** `Modules/Jana/`, `Modules/Repair/`, `Modules/Project/` — antes de criar ou ajustar qualquer arquivo, olhe o equivalente e imite. Ver ADR 0011.

---

## 2. Como trabalhar neste projeto (fluxo obrigatório)

Sempre que você (agente ou humano) for atuar neste projeto:

1. **Leia o estado vivo** em [`CURRENT.md`](CURRENT.md) — sprint, em-andamento, próximo passo, bloqueios.
2. **Leia o handoff** em [`memory/08-handoff.md`](memory/08-handoff.md) — estado canônico mais recente.
3. **Leia o session log mais recente** em `memory/sessions/` — contexto imediato da última sessão.
4. **Consulte ADRs relevantes** em [`memory/decisions/`](memory/decisions/) — decisões com justificativa.
5. **Siga as convenções** de [`memory/04-conventions.md`](memory/04-conventions.md).
6. **Respeite as preferências** em [`memory/05-preferences.md`](memory/05-preferences.md).

Pra qualquer coisa visual/UX, comece em [`DESIGN.md`](DESIGN.md). Pra acesso/deploy de produção, em [`INFRA.md`](INFRA.md).

**Disciplina de contexto** (importa pra economia de crédito e qualidade):

- Use **`/compact`** após cada feature mergeada/validada — comprime o histórico mantendo só o essencial.
- Use **`/clear`** ao trocar de escopo (ex.: terminou Copiloto, vai mexer em Ponto) — começa limpo.
- Use **Plan mode** (Shift+Tab×2) pra mudanças não-triviais — Claude planeja antes de tocar arquivo.
- Use **`/continuar`** pra retomar sessão sem re-explorar o repo do zero (lê CURRENT.md + handoff + último session log + abre só os arquivos do próximo passo).

**Skills auto-ativáveis:** arquivos em `.claude/skills/<nome>/SKILL.md` ativam automaticamente quando o `description:` do frontmatter casa com a tarefa em andamento. Use pra encapsular padrões recorrentes (ex.: `multi-tenant-patterns` ativa ao criar nova Entity/Controller/Service que toca dados de negócio).

Ao terminar uma sessão:

7. **Atualize [`CURRENT.md`](CURRENT.md)** (sobrescrito) e [`memory/08-handoff.md`](memory/08-handoff.md) (apenda) com o novo estado.
8. **Crie um session log** em `memory/sessions/YYYY-MM-DD-*.md` descrevendo o que foi feito.
9. **Se tomou decisão arquitetural nova**, crie ADR em `memory/decisions/NNNN-slug.md`.

---

## 3. Estrutura do repositório

```
D:\oimpresso.com\
├── CLAUDE.md          # Este arquivo — primer pra agentes
├── CURRENT.md         # Estado vivo (sobrescrito a cada handoff)
├── DESIGN.md          # Hub visual/UX + padrão técnico React
├── INFRA.md           # Acesso SSH Hostinger, deploy, fixes manuais
├── AGENTS.md          # Mirror para outros agentes (opcional)
├── .claude/
│   ├── commands/      # Slash commands (ex.: /continuar)
│   ├── skills/        # Skills auto-ativáveis (ex.: multi-tenant-patterns)
│   └── settings.json  # Config Claude Code + hooks
├── memory/
│   ├── INDEX.md
│   ├── 00-user-profile.md ... 08-handoff.md
│   ├── decisions/     # ADRs (Michael Nygard format)
│   ├── sessions/      # Logs cronológicos por sessão
│   ├── requisitos/    # SPECs por módulo + ADRs específicos
│   └── comparativos/  # Capterra-style competitive briefs
├── Modules/
│   ├── Copiloto/      # Chat IA + metas + custos (ADR 0035)
│   ├── Financeiro/    # Contas a pagar/receber, dashboard, relatórios
│   ├── MemCofre/      # Cofre de memórias e evidências
│   ├── PontoWr2/      # Ponto eletrônico CLT (Portaria 671/2021)
│   ├── Jana/          # Módulo de referência canônica (imitar)
│   └── ...
└── resources/js/      # Inertia + React (Pages, Components/shared, Layouts)
```

---

## 4. O que NÃO fazer

- **Não modifique tabelas do core UltimatePOS** (`users`, `business`, `employees`, etc.). Use a tabela bridge `ponto_colaborador_config` no PontoWr2.
- **Não faça UPDATE ou DELETE em `ponto_marcacoes`** — append-only por força de lei (Portaria 671/2021). Use `Marcacao::anular()`.
- **Não remova triggers MySQL** de imutabilidade sem abrir ADR justificando.
- **Não crie novas tecnologias/dependências** sem registrar uma ADR.
- **Não responda ao usuário em inglês** — Wagner e Eliana são brasileiros e preferem PT-BR.
- **Não assuma que o usuário quer completude** — Wagner valoriza economia de crédito; confirme escopo com perguntas curtas antes de implementar massivamente.
- **Não remover o shim `App\View\Helpers\Form`** sem antes migrar ~6.433 chamadas `Form::` em ~460 Blade views.
- **Antes de criar/mudar estrutura do módulo**, abra `Modules/Jana/` (ou `Repair`/`Project`) e imite. nWidart v10+ usa `Routes/web.php` + `RouteServiceProvider`.
- **Identificadores MySQL com mais de 64 chars** — sempre passar nome explícito em índices compostos.
- **Não suba código para produção sem alertar pré-requisitos e riscos.** Histórico de crashes: 2026-04-18 (scaffold incompatível), 2026-04-19 (PHP 8 em servidor PHP 7.1), 2026-04-21 (módulo desativado após upgrade 6.7). Sempre testar em staging antes de ativar.

---

## 5. O que SEMPRE fazer

- **PT-BR em tudo** — texto, commit, comentário, label. Código (classes, métodos, variáveis) em inglês é OK; domínio de negócio usa nomes PT (ex.: `Marcacao`, `Intercorrencia`, `BancoHoras`).
- **Cite a lei** quando aplicável (ex.: *Art. 66 CLT*, *Portaria 671/2021 Anexo I*, *LGPD Art. 7º*).
- **Preserve imutabilidade** de marcações e movimentos de banco de horas.
- **Mantenha o `business_id` scopado** em todas as queries (multi-empresa UltimatePOS). Ver skill `multi-tenant-patterns`.
- **Escreva testes** ao menos para regras CLT (tolerâncias, intrajornada, interjornada, HE) e isolamento multi-tenant.
- **Antes de criar/mudar estrutura do módulo**, abra `Modules/Jana/` (ou `Repair`/`Project`) e imite. Se nenhum dos três tem — pense duas vezes. Se tem mas quero divergir — registre ADR explicando.
- **Use stack de middlewares UltimatePOS** pra rotas web: `['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']`.

---

## 6. Cofre de comparativos & gestão de memória

**Comparativos competitivos** (estilo Capterra/G2) ficam em [`memory/comparativos/`](memory/comparativos/) — template oficial em `_TEMPLATE_capterra_oimpresso.md` v1.0, índice em `_INDEX.md`.

**Trigger "guarde no cofre":** quando Wagner pedir, classifique antes de salvar:
- Comparativo competitivo → `memory/comparativos/`
- Decisão arquitetural → `memory/decisions/NNNN-slug.md` (formato Nygard, ver ADR 0028)
- User story / requisito → `memory/requisitos/{Modulo}/SPEC.md`
- Preferência do usuário ou quirk de cliente → auto-memória do agente (fora do git)
- Evidência (print, log, chat) → `Modules/MemCofre/` (entidades `Doc*`)
- Sempre confirmar com link curto pra Wagner.

**Papéis canônicos** de cada sistema de memória estão formalizados em [ADR 0027](memory/decisions/0027-gestao-memoria-roles-claros.md). Resumo: estado vivo em `CURRENT.md`, handoff em `memory/08-handoff.md`, ADRs em `memory/decisions/`, sessões em `memory/sessions/`, specs por módulo em `memory/requisitos/{Mod}/`, cross-conversation em auto-memória, evidências em MemCofre, auditoria em git.

**Não duplicar info entre sistemas.** Se já está no repo (cross-agent), auto-memória só aponta. Conflito de fato entre 2 fontes = bug.

---

## 7. Acesso à produção

Ver [`INFRA.md`](INFRA.md) — credenciais SSH Hostinger, deploy manual, patches ativos, dev local Herd/Laragon.

---

## 8. Padrão de UI/UX em React

Ver [`DESIGN.md`](DESIGN.md) — hub visual + padrão técnico Chat Cockpit (AppShellV2, tokens, atalhos J/K/E/A, TaskProvider, checklist de PR). Toda tela nova passa por lá antes de codar.

---

## 9. Contato e referências externas

**Cliente PontoWr2:** WR2 Sistemas — Eliana — eliana@wr2.com.br
**Repositório local:** `D:\oimpresso.com`
**Outros documentos do projeto** (fora desta pasta): `projeto_ponto_eletronico_wr2.md`, `especificacao_tecnica_laravel_wr2.md`, `ultimatepos6_hrm_especificacao_e_adaptacao.md`, `design_projeto_ponto_wr2.md` — pasta de outputs temporários do Cowork.

---

> **Última atualização:** 2026-04-28 (slim §8 → INFRA.md, §10 → DESIGN.md; +CURRENT.md / /continuar / skills / disciplina de contexto)
