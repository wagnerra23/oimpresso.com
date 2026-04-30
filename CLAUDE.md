# CLAUDE.md — Primer para agentes de IA no projeto Oimpresso ERP

> **Leia este arquivo ANTES de qualquer outro quando abrir este repositório pela primeira vez.**
> Este é o ponto de entrada oficial para agentes de IA (Claude, Claude Code, Cursor, outros) e para desenvolvedores humanos.

---

## 1. O que é este projeto em 30 segundos

ERP gráfico brasileiro para o setor de **comunicação visual** (gráficas rápidas, plotters, fachadas, brindes). Construído em cima do **UltimatePOS v6** com módulos próprios em `Modules/` (Copiloto IA, Financeiro, MemCofre, Cms, Officeimpresso, Ponto, Connector, etc.).

**Originalmente nasceu** como módulo Ponto WR2 (controle de ponto eletrônico Portaria MTP 671/2021) e evoluiu pra plataforma vertical completa.

> ⚠️ **Foco Cycle 01 (30-abr-2026):** Copiloto em espera. **Foco total: MCP memória + ADRs.**

**Stack REAL:** Laravel **13.6** + PHP 8.4 (Herd) · MySQL Laragon · DB `oimpresso` · Inertia **v3** + React 19 + Tailwind 4 · Pest v4 · nWidart/laravel-modules ^10 · `spatie/laravel-html` ^3.13 com shim `App\View\Helpers\Form`.

**Governança canônica (ADR 0059 — estilo Anthropic Team plan adaptado):**
- 10 pilares: source-of-truth git, dual-mode edição (commit / UI manual tags), PR-based approval, 3-layer backup, LGPD-aware retention 365d, audit total, RBAC fino (cc.read.self/team/all), spend caps, token zero-fricção (DXT one-click), lifecycle docs (status/authority).
- Self-host equivalente — **NÃO** SaaS Anthropic (LGPD + custo + custom).

**Stack-alvo IA (verdade canônica ADR 0035 + 0036 + 0048):**
- **Camada A:** `laravel/ai` ^0.6.3 (oficial fev/2026)
- **Camada B:** `LaravelAiSdkDriver` + 4 Agents próprios em `Modules/Copiloto/Agents/` — **Vizra ADK REJEITADA (ADR 0048, ADR 0032 superseded)** — não puxar/sugerir
- **Camada C:** `MemoriaContrato` + `MeilisearchDriver` (hybrid embedder OpenAI text-embedding-3-small ativo, ADR 0036) + `MeilisearchDriver` default + `NullDriver` dev
- **MCP server canônico:** `mcp.oimpresso.com` (CT 100/FrankenPHP) — 352 docs sincronizados de `memory/*` (ADR 0053). Token gerenciado em `/copiloto/admin/team`, KB inspecionável em `/copiloto/admin/memoria`
- **Real-time:** Centrifugo + FrankenPHP (CT 100, ADR 0058) — **Reverb ABANDONADO** após crash em testes
- **Tooling:** Boost + MCP + Scout + Horizon + Telescope + Pail

**Padrão arquitetural:** Modular monolith, DDD leve, append-only onde a lei exige, `business_id` global scope obrigatório.

**Módulos de referência canônica:** `Modules/Jana/`, `Modules/Repair/`, `Modules/Project/` — antes de criar ou ajustar qualquer arquivo, olhe o equivalente e imite. Ver ADR 0011.

---

## 2. Como trabalhar neste projeto (fluxo obrigatório)

### Caminho preferido: tools MCP (quando conectado)

Se você tem o MCP server `oimpresso` conectado (`.claude/settings.local.json` com Bearer `mcp_*` apontando pra `mcp.oimpresso.com/api/mcp`), **prefira tools MCP em vez de Read** — são governadas, auditadas em `mcp_audit_log` e retornam só o que importa:

| Pergunta | Tool MCP |
|---|---|
| "Qual o estado do cycle?" | `tasks-current` |
| "Qual ADR fala sobre X?" | `decisions-search query:"X"` |
| "Ler ADR 0053 completa" | `decisions-fetch slug:"0053-mcp-server-governanca-como-produto"` |
| "Últimas sessões" | `sessions-recent limit:5` |
| "Fato do business sobre Y" | `memoria-search query:"Y"` |
| "O que time usou no Claude Code?" | `cc-search query:"..."` |
| "Quanto eu consumi?" | `claude-code-usage-self` |

UI humana: `/copiloto/admin/memoria` lista 352 docs (ADRs/sessions/refs/comparativos/audits/runbooks) com filtros + preview markdown.

### Fallback: filesystem (se sem MCP)

Sem MCP conectado, lê na ordem:

1. **Estado vivo** em [`CURRENT.md`](CURRENT.md) — sprint, em-andamento, próximo passo, bloqueios.
2. **Handoff** em [`memory/08-handoff.md`](memory/08-handoff.md) — estado canônico mais recente.
3. **Session log mais recente** em `memory/sessions/` — contexto imediato da última sessão.
4. **ADRs relevantes** em [`memory/decisions/`](memory/decisions/) — decisões com justificativa.
5. **Convenções** de [`memory/04-conventions.md`](memory/04-conventions.md).
6. **Preferências** em [`memory/05-preferences.md`](memory/05-preferences.md).

Pra qualquer coisa visual/UX, comece em [`DESIGN.md`](DESIGN.md). Pra acesso/deploy de produção, em [`INFRA.md`](INFRA.md).

**Disciplina de contexto** (importa pra economia de crédito e qualidade):

- Use **`/compact`** após cada feature mergeada/validada — comprime o histórico mantendo só o essencial.
- Use **`/clear`** ao trocar de escopo (ex.: terminou Copiloto, vai mexer em Ponto) — começa limpo.
- Use **Plan mode** (Shift+Tab×2) pra mudanças não-triviais — Claude planeja antes de tocar arquivo.
- Use **`/continuar`** pra retomar sessão sem re-explorar o repo do zero (lê CURRENT.md + handoff + último session log + abre só os arquivos do próximo passo).

**Skills auto-ativáveis:** arquivos em `.claude/skills/<nome>/SKILL.md` ativam automaticamente quando o `description:` do frontmatter casa com a tarefa em andamento. Use pra encapsular padrões recorrentes:
- `multi-tenant-patterns` — ativa ao criar Entity/Controller/Service/Job que toca dados de negócio (`business_id`).
- `publication-policy` — ativa antes de git push, abertura/merge de PR, deploy em produção, ou postagem externa. Decide se Claude executa direto ou escala pro Wagner. Wagner explicitamente delegou supervisão; Claude não pergunta antes de ação rotineira reversível. Ver [ADR 0040](memory/decisions/0040-policy-publicacao-claude-supervisiona.md).

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

**Fluxo:** git (source-of-truth) → webhook GitHub → `mcp_memory_documents` (DB cache governado) → tools MCP / `/copiloto/admin/memoria` UI.

**Comparativos competitivos** (estilo Capterra/G2) ficam em [`memory/comparativos/`](memory/comparativos/) — template oficial em `_TEMPLATE_capterra_oimpresso.md` v1.0, índice em `_INDEX.md`.

**Trigger "guarde no cofre":** quando Wagner pedir, classifique antes de salvar:
- Comparativo competitivo → `memory/comparativos/`
- Decisão arquitetural → `memory/decisions/NNNN-slug.md` (formato Nygard, ver ADR 0028)
- User story / requisito → `memory/requisitos/{Modulo}/SPEC.md`
- ADR específico de módulo → `memory/requisitos/{Modulo}/adr/{arq|tech|ui}/NNNN-slug.md`
- Runbook/audit/architecture → `memory/requisitos/{Modulo}/{RUNBOOK|ARCHITECTURE|GLOSSARY|CHANGELOG}.md`
- Preferência do usuário ou quirk de cliente → auto-memória do agente (fora do git, fora do MCP)
- Evidência (print, log, chat) → `Modules/MemCofre/` (entidades `Doc*`)
- Após `git push`, **webhook GitHub sincroniza em <60s** pra `mcp_memory_documents` automaticamente. Confirmar via `decisions-search` ou na tela.

**Papéis canônicos** de cada sistema de memória estão formalizados em [ADR 0027](memory/decisions/0027-gestao-memoria-roles-claros.md) e expandidos em [ADR 0053](memory/decisions/0053-mcp-server-governanca-como-produto.md):

| Sistema | Conteúdo | Source-of-truth | Acesso IA |
|---|---|---|---|
| `CURRENT.md` | Estado vivo cycle/sprint | git | tool MCP `tasks-current` |
| `memory/08-handoff.md` | Handoff estado canônico | git | tool MCP via slug `handoff` |
| `memory/decisions/*.md` | ADRs Nygard | git | tools `decisions-search`/`decisions-fetch` |
| `memory/sessions/*.md` | Logs cronológicos | git | tool MCP `sessions-recent` |
| `memory/requisitos/{Mod}/` | SPECs + ADRs por módulo + runbook + audit | git | tool MCP `decisions-search module:` |
| `memory/comparativos/*.md` | Capterra-style competitive briefs | git | tool MCP via slug `comparativo-*` |
| Auto-memória | Cross-conversation Claude (preferências) | local user | NÃO sobe pro MCP |
| MemCofre | Evidências (DocVault) | DB | tela `/memcofre` |
| `mcp_memory_documents` | DB cache de tudo acima exceto auto-mem | sync git→DB | tools MCP |

**Não duplicar info entre sistemas.** Git é canônico; MCP é cache governado.

⛔ **ZERO auto-mem privada** (ADR 0061). Hook `block-automem.ps1` BLOQUEIA `Write/Edit` em `~/.claude/projects/*/memory/*.md`. Todo conhecimento canônico vai pra git → webhook → MCP. As 82 auto-mems históricas estão sendo migradas (plano em [`memory/requisitos/Infra/PLANO-MIGRACAO-AUTOMEM.md`](memory/requisitos/Infra/PLANO-MIGRACAO-AUTOMEM.md)). Conflito de fato entre 2 fontes = bug.

**KB MCP UI (`/copiloto/admin/memoria`)** — tela de governança Wagner: lista 352 docs, filtros (type/module/PII), Sheet preview markdown render + git_sha→GitHub, soft-delete LGPD double-confirm, history. Permission: `copiloto.mcp.memory.manage` (ADR 0057).

---

## 7. Acesso à produção

Ver [`INFRA.md`](INFRA.md) — credenciais SSH Hostinger, deploy manual, patches ativos, dev local Herd/Laragon.

**SSH é flaky** — sempre **warm-up + retry**:
```bash
# 1) Warm-up (5 hits curl)
for i in 1 2 3 4 5; do curl -s -o /dev/null --max-time 15 https://oimpresso.com/login; done
# 2) SSH robusto (auto-mem reference_hostinger_analise.md)
ssh -4 -o ConnectTimeout=900 -o ServerAliveInterval=3 \
    -o ServerAliveCountMax=200 -o ConnectionAttempts=5 \
    -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 'CMD'
```
Sem isso, primeiro try quase sempre dá `Connection timed out`.

---

## 8. Padrão de UI/UX em React

Ver [`DESIGN.md`](DESIGN.md) — hub visual + padrão técnico Chat Cockpit (AppShellV2, tokens, atalhos J/K/E/A, TaskProvider, checklist de PR). Toda tela nova passa por lá antes de codar.

---

## 9. Contato e referências externas

**Cliente PontoWr2:** WR2 Sistemas — Eliana(WR2) — eliana@wr2.com.br
**Cliente principal Copiloto:** ROTA LIVRE — Larissa (`business_id=4`)
**Repositório local:** `D:\oimpresso.com`
**Outros documentos do projeto** (fora desta pasta): `projeto_ponto_eletronico_wr2.md`, `especificacao_tecnica_laravel_wr2.md`, `ultimatepos6_hrm_especificacao_e_adaptacao.md`, `design_projeto_ponto_wr2.md` — pasta de outputs temporários do Cowork.

---

## 10. Equipe interna e atribuição de tasks

**Time atual (5 pessoas):** Wagner [W] líder · Maíra [M] suporte+dev · Felipe [F] dev+suporte · Luiz [L] iniciante+dev IA-pair · Eliana [E] financeiro+dev IA (esposa Wagner)

**Antes de atribuir / pegar uma task:** ler [`TEAM.md`](TEAM.md) — perfis, **WIP máximo por pessoa** (W/M/F=2, L/E=1), e **matriz "quem pode pegar qual tipo de task"** com 4 níveis (✅ owner / 🟢 pode / 🟡 com supervisão / ❌ não-pegar).

**Regras duras:**
- Luiz [L] não mergeia PR sozinho (Felipe ou Wagner aprova).
- Eliana [E] não mexe em Copiloto sprints LGPD.
- Maíra [M] não faz deploy produção sozinha.
- Wagner [W] deve evitar virar bottleneck — delegar code review pra Felipe quando puder.
- PIIs reais (CPF/CNPJ de cliente) NUNCA aparecem em PR ou commit. Logs com `[REDACTED]`.

**2 Elianas no projeto:** `Eliana[E]` (esposa, time interno) ≠ `Eliana(WR2)` (cliente externa, PontoWr2). Sempre desambiguar em commits/notas.

**Convenção em commits:** `[W]`, `[M]`, `[F]`, `[L]`, `[E]`, `[L+C]` (Luiz pareado Claude), etc. Ex.: `feat(copiloto): PII redactor BR [F]`.

**Ciclo de trabalho:** Cycle de 2 semanas — `CURRENT.md` define goal outcome-oriented + Active (WIP por pessoa) + On-deck. Daily async 09h cada um atualiza próprio status no `TASKS.md`. Sex final do cycle: Wagner arquiva `CURRENT.md` em `memory/cycles/CICLO-NN-YYYY-MM-DD.md` com retro de 5 linhas.

---

> **Última atualização:** 2026-04-30 — §1 stack atualizada (Vizra REJEITADA ADR 0048, Reverb ABANDONADO ADR 0058 → Centrifugo); §2 fluxo MCP-first (tools sobre filesystem); §6 KB MCP UI `/copiloto/admin/memoria` + 352 docs sincronizados (F1 sync expansion)
