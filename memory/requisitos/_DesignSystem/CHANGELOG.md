# Changelog · Design System

## [0.6.6] - 2026-05-24 · Onda 4 executada · pr-ui-judge agent + workflow scaffold

### Added

- **`Modules/Jana/Ai/Agents/PrUiJudgeAgent.php`**: agente Laravel/AI usando Anthropic Claude Sonnet 4.5. System prompt carrega Constituição UI v2 completa (hierarquia 4 camadas + regra-mestre + PT-01 + 8 anti-padrões + 5 origins canon + sidebar light decision). Retorna JSON estrito com score 0-100 + 9 dimensões + violações estruturais + sugestões.
- **`app/Console/Commands/UiJudgePrCommand.php`** (`php artisan ui:judge-pr {pr_number}`): orquestra `gh pr view/diff/comment` + PrUiJudgeAgent + parse JSON + render console + post comment opcional. Opções: `--post-comment`, `--strict` (exit 1 em request_changes), `--save-to`, `--repo`.
- **`.github/workflows/pr-ui-judge.yml`**: CI workflow opt-in via repo variable `PR_UI_JUDGE_ENABLED=true` (default DESLIGADO · proteção contra custo Brain B inadvertido). Trigger em PR opened/synchronize/reopened tocando UI. `workflow_dispatch` permite invocar manual em PR específico com `--strict` opcional. Upload artifact JSON 30d.
- **`.claude/skills/pr-ui-judge-manual/SKILL.md`** Tier C: skill description-match em "/pr-ui-judge", "avaliar PR contra v2", "score UI do PR". Documenta comando + 9 dimensões + custo + pegadinhas + quando NÃO usar.

### Custo Brain B

- ~$0.034/PR primeiro do dia (Claude Sonnet 4.5 · ~10k input + ~1k output)
- ~$0.005/PR após (prompt caching ~85% reuse)
- ~$3/mês a 100 PRs/mês

### Status enforcement automatizado

- Antes Onda 4: ~85%
- Pós-Onda 4: ~90% (limite teórico · ~10% restante é palpite estético sempre humano)
- Wagner ativa via `vars.PR_UI_JUDGE_ENABLED=true` quando aprovar quota Brain B

### Cobertura grep-invisível

Judge LLM detecta o que `ui:lint` (sintático) NÃO vê:
- Drawer modal sobre modal
- Slot reinventado com `<div>` custom mesmo importando shared
- Layout violando PT-01 em essência (componentes corretos · uso fora do slot)
- Copy semântica errada (ex: "Salvar" em inglês como `<button>Save</button>`)
- Atalho duplicado (e.g. 2 telas usando `J` pra ações diferentes)
- Acessibilidade básica (labels, aria-* faltando)

### Não regrediu

- Nenhum código de produção modificado
- Workflows existentes intactos
- Quota Brain B NÃO ativada (`PR_UI_JUDGE_ENABLED` não setado · workflow inativo)

### Próximo passo Wagner (ativar judge)

1. Decidir quota Brain B (~$3/mês a 100 PRs)
2. GitHub Repo → Settings → Variables → adicionar `PR_UI_JUDGE_ENABLED=true`
3. Validar `ANTHROPIC_API_KEY` secret existe (provavelmente já)
4. Testar workflow_dispatch manual em 1 PR antes de ativar em todos
5. Monitorar `storage/pr-ui-judge-*.json` artifacts pra calibrar prompt

## [0.6.5] - 2026-05-24 · Onda 3 executada · ui-canon-notify workflow

### Added

- **`.github/workflows/ui-canon-notify.yml`**: Notificação proativa pro time MCP quando UI canon muda em main. Trigger em push tocando `memory/requisitos/_DesignSystem/`, `memory/decisions/`, `Pages/`, `Components/shared/`, `cockpit.css`/`inertia.css`, ou skill `constituicao-ui-aware`. Payload JSON estruturado (commit + stats + refs). Secret opcional `UI_NOTIF_WEBHOOK_URL` (Slack/Discord/MCP/Discussions). Sem secret = no-op warning (não-bloqueante).
- **Suporte `workflow_dispatch`**: trigger manual com mensagem custom pra teste local.

### Descoberta importante (2026-05-24)

Investigação pré-Onda 3 revelou que **2/3 do escopo original já existem** no projeto:

1. **`SyncMemoryWebhookController`** (ADR 0053) — webhook GitHub que indexa `memory/` no MCP server automaticamente em push pra main. JÁ FAZ a sincronização canon→MCP. Onda 3 inicial planejada criar "webhook MCP-notif" do zero — desnecessário.
2. **`visual-regression.yml`** (ADR 0108) — Pest 4 Browser + Playwright snapshot pixel-diff já implementado. INFRA-ONLY mode (migration order issue UltimatePOS legacy bloqueia tests reais), mas estrutura está pronta. Não precisa Playwright/Percy paralelo.

**AUTOMATION-ROADMAP atualizado** marcando 3.2 visual regression como "já existe (ADR 0108)" + adicionando Item 3.3 (futuro · MCP team-notify endpoint custom · ~2h quando time ≥3 actors).

### Status enforcement automatizado

- Antes Onda 3: ~75%
- Pós-Onda 3: ~85% (push notif + visual regression existente referenciada)
- Próximo (Onda 4 ~1-2d · sob demanda): ~90% (agente CI LLM-as-judge pr-ui-judge)

### Esforço real vs estimado

- Onda 3 estimada: 4-6h
- Onda 3 real: ~1h (graças à descoberta de infra existente)
- Lição: investigar **antes** de criar · reduz duplicação

### Não regrediu

- Nenhum código de produção modificado
- Workflows existentes intactos (visual-regression.yml ADR 0108 · SyncMemoryWebhookController ADR 0053)
- ADRs UI-0001..UI-0014 + ADR 0187 permanecem

### Próximo passo Wagner (ativar notif)

1. Decidir endpoint webhook: Slack incoming webhook · Discord · ou criar MCP team-notify (Item 3.3 futuro)
2. Adicionar secret `UI_NOTIF_WEBHOOK_URL` em GitHub Repo Settings → Secrets → Actions
3. Validar workflow via `workflow_dispatch` manual antes do primeiro push real
4. Time MCP começa a receber notif push (não-pull) de UI canon changes

## [0.6.4] - 2026-05-24 · Onda 2 executada · CI gate + pre-commit hook + R4/R5

### Added

- **`.github/workflows/ui-lint.yml`**: CI gate · dispara em PRs que tocam `resources/js/`, `resources/css/`, `app/Console/Commands/UiLintCommand.php`, `config/ui-lint-baseline.json`. Roda `ui:lint --baseline --strict` · falha PR em regressão. Step "Diagnóstico em caso de falha" mostra próximos passos.
- **`.githooks/pre-commit`** apendado: bloco `ui:lint --changed-only --baseline=...` opt-in via `OIMPRESSO_UI_LINT_STRICT=1`. Default = warning. Pular emergência: `git commit --no-verify`.
- **Regra R4 PT-01** em `UiLintCommand`: detecta `Pages/<X>/Index.tsx` sem `<PageHeader>` (Slot 1) OU sem `<DataTable>` (Slot 5) shared. **27 hits em 17 arquivos** detectados (Sells/Cliente/Compras/Repair/etc usam `<h1>` próprio ao invés de shared). Excluded: Home/Jana/Settings/Modules (não-PT-01).
- **Regra R5 origens canon** em `UiLintCommand`: scaneia `cockpit.css` + `inertia.css` por `--origin-<X>-(bg|fg|border|soft)` · permite só OS/CRM/FIN/PNT/MFG. **0 hits** — confirmação que projeto respeita 5 origins canon, rejeita 11 hues da v2 externa.

### Status enforcement automatizado

- Antes Onda 2: ~50% (skill always-on + lint sob demanda local)
- Pós-Onda 2: ~75% (CI gate em PR + pre-commit hook + R4/R5 estrutural)
- Próximo (Onda 3 ~4-6h · sob demanda time MCP >3 ativos): ~85% (webhook MCP-notif + visual regression)

### Baseline atualizado

- 7280 → 7307 (+27 R4 hits, R5 = 0)
- Snapshot em `config/ui-lint-baseline.json`
- Modo ratchet validado: delta +0 · exit 0 ✓

### Sinais positivos (oimpresso já respeita parte da v2)

- **R2 FontAwesome:** 0 hits em 408 arquivos · projeto 100% lucide-only ✓ (UI-0003 confirmada)
- **R5 5 origins:** 0 hits · CSS canon respeita decisão Wagner de manter 5 origins ✓
- **R4 PT-01 Slot 5 DataTable:** ausente em maioria · sinal: padrão emergente é `<table>` direto, não componente shared · próxima evolução PR consolidar `<DataTable>` shared ou aceitar como justificável

### Não regrediu

- Nenhum código `resources/js/Pages/` ou `Components/shared/` modificado nesta onda
- ADRs UI-0001..UI-0014 e ADR 0187 permanecem
- `.githooks/pre-commit` ganhou bloco apendado · check-scope.php intacto
- Skill `constituicao-ui-aware` v1.0 intacta (Onda 1)

## [0.6.3] - 2026-05-24 · Onda 1 executada · skill + ui:lint + baseline

### Added

- **Skill `constituicao-ui-aware` Tier A** ([SKILL.md](../../../.claude/skills/constituicao-ui-aware/SKILL.md)): description-match em Edit/Write em `resources/js/Pages/`, `Components/shared/`, `cockpit.css`, `inertia.css`. Carrega no contexto antes de codar — Hierarquia 4 camadas + regra-mestre + PT aplicável + 8 anti-padrões AP1-AP8. Substitui leitura repetida de UI-0013 + PT-01 + PRE-MERGE-UI a cada sessão.
- **`app/Console/Commands/UiLintCommand.php`** (`php artisan ui:lint`): 3 regras críticas (R1 cor crua, R2 FontAwesome, R3 emoji UI) + modo `--baseline` ratchet + `--changed-only` pra pre-commit hook + `--write-baseline` pra atualizar estado aceito.
- **`config/ui-lint-baseline.json`**: snapshot inicial 2026-05-24 — 7280 violações (R1: 6859 · R2: 0 · R3: 119) em 317 arquivos. Modo ratchet só falha em **regressão** vs esse baseline. (Versionado em `config/` porque `/storage/` é gitignored.)
- **[UI-LINT-USAGE.md](UI-LINT-USAGE.md)**: doc curto de uso do command + workflow CI sugerido (Onda 2) + workflow pre-commit (Onda 2.2).

### Status enforcement automatizado

- Antes Onda 1: ~30% (CLAUDE.md SessionStart + skills Tier A + Module Grades Gate CI)
- Pós-Onda 1: ~50% (adiciona skill always-on + lint sob demanda local)
- Próximo (Onda 2 ~2-3h): ~75% (CI lint automático + pre-commit hook + PT-01 + origens lock)

### Validação experimental (teste pré-commit)

- R2 (FontAwesome): **0 hits em 408 arquivos** ✓ — projeto já é lucide-only, ADR UI-0003 confirmada na prática
- R1 (cor crua): 6859 hits — alta, mas é estado atual aceito · refator gradual via baseline ratchet
- R3 (emoji): 119 hits (de 313 inicial · -62% após excluir ✓ ✗ ⚠ text-style)
- Modo ratchet validado: baseline 7280 · current 7280 · delta +0 · exit 0 ✓

### Não regrediu

- Nenhum código `resources/js/Pages/` ou `Components/shared/` tocado nesta onda (só leu pra gerar baseline)
- Nenhum token CSS modificado
- ADRs UI-0001..UI-0014 permanecem aceitas
- Skills Tier A existentes (`brief-first`, `mcp-first`, `multi-tenant-patterns`, `commit-discipline`, `mwart-process` v1.2) intactas — `constituicao-ui-aware` é a **5ª** skill Tier A

## [0.6.2] - 2026-05-24 · AUTOMATION-ROADMAP planejado (4 ondas · 30→90%)

### Added

- **[AUTOMATION-ROADMAP.md](AUTOMATION-ROADMAP.md)**: plano executável de 4 ondas pra subir enforcement da Constituição UI v2 de **~30% automatizado** (hoje) pra **~90%** (alvo).
  - **Onda 1** (1h30 · sobe 30→50%): skill `constituicao-ui-aware` Tier A + artisan `php artisan ui:lint` esqueleto com 3 regras (cor crua, FontAwesome, emoji UI)
  - **Onda 2** (2-3h · sobe 50→75%): GitHub Actions `ui-lint.yml` + hook pre-commit + regras PT-01 + "não introduzir 6ª origin"
  - **Onda 3** (4-6h · sobe 75→85%): webhook GitHub → MCP-notif + visual regression (Playwright/Percy)
  - **Onda 4** (1-2d · sobe 85→90%): agente CI `pr-ui-judge` (LLM Brain B Sonnet ~$3/mês a 100 PRs)
- Critério de start explícito por onda (não cronograma — sinal real, e.g. "primeira regressão de cor crua aparece")
- 10-20% sempre humano declarado (palpite estético · desempate ADR · voice&tone · Larissa-fit)

### Status

- Plano: **planejado · zero ondas executadas**
- Quando: sob demanda · ondas independentes · cada uma vira PR isolado
- Wagner decide ordem · pode pular ondas se sinal não justificar (Onda 3 depende time MCP >3 ativos)

### Não regrediu

- Nenhum código de produção tocado nesta entrega
- ADRs UI-0013 + UI-0014 + ADR 0187 + PT-01 + PRE-MERGE-UI permanecem vigentes

## [0.6.1] - 2026-05-24 · Wagner aprova v2 + desempate sidebar (opção A · light mantido)

### Changed

- **ADR UI-0013** status `proposed` → `accepted`. Wagner aprovou explícito ("eu aporvo") em 2026-05-24. Constituição UI v2 oficialmente adotada (hierarquia 4 camadas + regra-mestre + vocabulário + PT-01 + PRE-MERGE-UI).
- **proposal [sidebar-dark-vs-light](../../decisions/proposals/2026-05-24-sidebar-dark-vs-light.md)** status `discussion` → `decided`. Opção **A** escolhida (manter UI-0009 light). Comando Wagner: *"eu realmente gosto como esta hoje. não gostaria de mudar"*.

### Added

- **ADR UI-0014** [sidebar-light-mantida-v2-parcial](adr/ui/0014-sidebar-light-mantida-v2-parcial.md): formaliza desempate. Constituição UI v2 adotada **integralmente exceto trecho "sidebar dark sempre"**. UI-0009 (sidebar light padrão) confirmada vigente. v2 ADR 0041 externa entra como referência rejeitada. Zero refactor de código — `cockpit.css` intacto.

### Não regrediu

- Nenhum token `--sb-*` movido — UI-0009 segue vigente.
- Nenhuma página Inertia tocada.
- UI-0008 (Cockpit layout-mãe) e UI-0009 (sidebar light) **permanecem aceitas** — UI-0014 confirma, não substitui.

### Não fez (intencional)

- Migração 5 origin badges → 11 hues semânticos v2 — sem ADR específica (próxima decisão Wagner se quiser)
- PT-02..PT-05 — abrem ADR cada um quando ≥2 módulos pedirem
- Update `CLAUDE.md` raiz citando UI-0013 Tier A — pendente, Wagner pode pedir
- Voice & tone formalizado · animação tokens — sem dor que justifique ainda

## [0.6.0] - 2026-05-24 · Constituição UI v2 incorporada

### Added

- **ADR UI-0013**: Constituição UI v2 — hierarquia de 4 camadas (Fundações → Shell → Padrão de Tela → Módulo) com princípio "camada superior herda e nunca contradiz". Regra-mestre "não-gastar-tokens-com-pedido-vago" + vocabulário canônico de pedido. Status: `proposed` (aguarda Wagner). Origem: handoff Claude Design 2026-05-24 (sessão chat8 projeto Cowork "Constituição UI v2").
- **`padroes-tela/PT-01-Lista.md`** (NOVA PASTA `padroes-tela/` + primeiro doc canônico): template de 6 slots (PageHeader, ModuleTopNav, Toolbar, BulkBar, Table, Drawer) com DNA por slot, regras de ouro, estados obrigatórios, atalhos canônicos, snippet pronto. Documenta paridade de 12 telas-lista que JÁ implementam o padrão (Sells/Cliente/Compras/Purchase/Repair/etc) — não introduz mudança visual.
- **`PRE-MERGE-UI.md`**: checklist obrigatório por camada (1-Fundações/2-Shell/3-PT/4-Módulo/5-Protocolo/6-ADR) antes de PR que toca UI. Anti-padrões AP1-AP8 (cor hardcoded, componente reinventado, localStorage sem prefixo, ícone fora lucide, gradient decorativo, emoji UI, bg-fill status badge, copy não-PT-BR). Sinais de regressão "alerte Wagner, não corrija silenciosamente".
- **`memory/decisions/proposals/2026-05-24-sidebar-dark-vs-light.md`** (proposal, não ADR ainda): conflito formal entre v2 ADR 0041 (dark sempre) vs UI-0009 vigente (light padrão). 4 opções (A manter light · B adotar dark · C híbrido toggle · D postergar) com recomendação A ou B explícito. Wagner desempata.

### Changed

- **`README.md`** (DS root) — adicionada seção "Hierarquia de 4 camadas" + ponteiros pra UI-0013, PT-01, PRE-MERGE-UI. Índice expandido. Mantém estrutura anterior.

### Não regrediu

- Nenhuma ADR UI-0001..UI-0012 alterada — UI-0013 é **aditiva**.
- Nenhum token CSS canon (`cockpit.css`, `inertia.css`) tocado nesta entrega.
- Nenhuma Page Inertia ou Component shared modificado.
- Sidebar UI-0009 (light) permanece vigente até Wagner decidir proposal.

### Não fez (lacunas explícitas)

- PT-02 Form/Drawer · PT-03 Detalhe · PT-04 Dashboard · PT-05 Config — abrir ADR quando ≥2 módulos pedirem
- Migração 5 origin badges → 11 hues semânticos da v2 — sem ADR específica
- Voice & tone formalizado · iconografia stroke sizes · animação tokens — sem dor que justifique

### Skills correlatas (não tocadas, só citadas)

- `mwart-process` (Tier A) — segue válida, ganha referência futura à PT-01
- `charter-first` (Tier A · dormente S4) — segue válida
- `wagner-request-refiner` + agente `wagner-understand` — operacionalizam regra-mestre da UI-0013
- `commit-discipline` (Tier A) — aplicada nesta entrega (1 PR = 1 intent, ≤300 linhas, conventional commits)

## [0.5.0] - 2026-05-05 (tarde)

### Added

- **ADR UI-0011**: sidebar single-pane minimalista contextualizada + user menu cascata lateral. Wagner pediu em sessão direta. Documenta toggle Chat/Menu REMOVIDO, items agrupados por scope (OFFICEIMPRESSO/FINANCEIRO/ESTOQUE/RELATÓRIOS/IA/CONFIG), Tarefas+Chat como atalhos primários no topo, user menu cascata estilo Claude Desktop.
- **R-DS-015**: items do shell.menu sempre agrupados por scope visual via `SIDEBAR_GROUPS` lookup table. Items não-mapeados caem em "MAIS" (collapse fechado por default).
- **R-DS-016**: cascade trigger (`▶` no item do user menu) abre subpainel à direita; padrão Claude Desktop / Linear / Notion.
- **`<SidebarShortcuts>`**: Tarefas + Chat como ações primárias no topo da sidebar com badges live (count).
- **`<SidebarGroup>`**: header uppercase mute + chevron + items, colapsável; persistência por `key` em `oimpresso.cockpit.group.<key>.expanded`.
- **Subpainel Aparência funcional**: usa `useTheme()` hook existente; 3 botões (Claro/Escuro/Sistema) com check no ativo, persiste em `users.ui_theme` via POST `/user/preferences/theme`.
- **Rota `/tarefas`**: stub Page Inertia placeholder pra inbox cross-módulo (Fase 4 plano migração ADR 0039).

### Removed

- Componentes `SidebarTabs` e `SidebarChat` deletados (eram parte da v UI-0008 dual-pane).
- Imports lucide unused no Sidebar.tsx limpos: `MessageCircle`, `Hash`, `Bell`, `Cog`, `Inbox`, `Pin`, `Plus` da SidebarChat.

### Changed

- **ADR UI-0008** patched parcialmente: trecho "SidebarTabs (toggle Chat ↔ Menu)" e "SidebarChat" superseded por UI-0011. Estrutura 3-colunas continua válida.
- **AppShellV2** sem state `tab` + sem `<SidebarTabs>`. `LS.TAB` continua existindo no shared.ts mas é ignorado (compat zerado — pode ser removido em ADR futura).

### Débito técnico assumido

- `SIDEBAR_GROUPS` lookup table está hardcoded em `Sidebar.tsx`. Migração planejada pra `LegacyMenuAdapter` (campo `group: string` no `MenuItem`) após validação UX em produção (~2 sprints).
- Subpainel "Disponível" tem 3 placeholders estáticos (Disponível/Ausente/Não perturbe) — backend de status real pendente.

## [0.4.0] - 2026-05-05

### Added

- **UI Kit canônico Cowork 2026-04-27** importado em [`ui_kits/cowork-2026-04-27/`](ui_kits/cowork-2026-04-27/) (14 arquivos: 12 `.jsx` + `styles.css` 90 KB + HTML entry + README). Snapshot do projeto Anthropic Cowork "Oimpresso ERP Comunicação Visual" exportado por Wagner em 2026-04-27. Ratificado como **fonte da verdade visual** em 2026-05-05.
- **ADR UI-0010**: zip Cowork 2026-04-27 é canon visual; **`os-page.jsx` é padrão canônico de tela list+detail**, substituindo parcialmente UI-0006 (template tela operacional) e Pattern Jana (ADR raiz 0011) onde houver conflito visual. ADR documenta tabela de **conflitos resolvidos** (ex.: UI-0009 sidebar light SOBREVIVE — Wagner explícito 2026-05-05 "manter sidebar").
- **R-DS-013**: telas list+detail (Officeimpresso/OS, Repair, Project, Financeiro, Copiloto/Admin/*) seguem `os-page.jsx` como referência visual canônica.
- **R-DS-014**: telas inbox unificada (Pages/Tarefas/Index.tsx, futuras) seguem `tasks.jsx` + `viewers.jsx`.
- **Session 2026-04-28-design-prototype-chat-erp.md** apendida em `memory/sessions/` (estava em `memory-para-github/sessions/` do zip — sinal que era pra entrar no repo e nunca entrou).

### Changed

- **ADR UI-0006** (padrão tela operacional) — agora **substituído parcialmente por UI-0010** quando o conflito for visual. Continua válido pra estrutura de módulo (DataController hooks, modules_statuses.json) que UI-0010 não toca.
- **DESIGN.md §1** apontando explicitamente pro UI Kit + ADR UI-0010 como referência visual antes de qualquer portagem.

## [0.3.1] - 2026-05-04

### Changed

- **Sidebar do Cockpit** segue agora `data-theme` do usuário (light por padrão, dark elegante azul-cinza profundo) — antes era dark fixo. Formalizado em [ADR UI-0009](adr/ui/0009-cockpit-sidebar-light-padrao.md). Tokens `--sb-*` em `resources/css/cockpit.css` agora têm variante em ambos temas; hardcodes pretos substituídos por tokens auxiliares (`--sb-bg-2`, `--sb-scroll`, `--sb-bullet-out`).
- **ADR UI-0008** patchado: trecho "Sidebar 260px, dark fixo na vibe workspace" agora aponta pra UI-0009. Substituição parcial (estrutura do Cockpit segue válida).
- **BRIEFING_CLAUDE_DESIGN.md §2 §6** atualizados pra refletir sidebar segue tema.

### Removed

- **`resources/js/Layouts/AppShell.tsx`** (legado AdminLTE-like) — removido. Já estava órfão (zero imports). Todas as 78 páginas Inertia agora usam `AppShellV2` (Cockpit) — shell único do ERP. Refs JSDoc em `Types/index.ts`, `Hooks/usePageProps.ts`, `Components/shared/ModuleTopNav.tsx`, `Pages/ConsultaOs/Index.tsx` atualizadas pra mencionar AppShellV2.

## [0.3.0] - 2026-04-27

### Added

- **Cockpit é o layout-mãe canônico do ERP** (ADR UI-0008): sidebar dual Chat↔Menu (260px) + main contextual (1fr) + Apps Vinculados (320px). Implementado em `Pages/Copiloto/Cockpit.tsx`, CSS escopado em `resources/css/cockpit.css`. Em produção `https://oimpresso.com/copiloto/cockpit`.
- **R-DS-009**: telas core do ERP nascem dentro do Cockpit (AppShellV2).
- **R-DS-010**: Apps Vinculados renderizam blocos por módulo na coluna direita quando há entidade em foco.
- **R-DS-011**: origin badges com 5 cores semânticas (OS amber, CRM blue, FIN green, PNT violet, MFG orange).
- **R-DS-012**: persistência de UI em `localStorage` com namespace `oimpresso.cockpit.*`.
- **CompanyPicker** funcional no topo da sidebar — lista businesses do user (todas se superadmin, current senão), avatar com gradiente determinístico, "+ Adicionar empresa" no footer.
- **Aba Menu real** carregando `shell.menu` do `LegacyMenuAdapter` (mesma fonte do AppShell legado). 33 itens espelhados.
- **Rodapé com superadmin items separados** (Backup, Módulos, CMS, Office Impresso, Superadmin) acima do user dropdown rico (perfil/disponível/aparência/atalhos/ajuda/sair).
- **Tweaks panel** flutuante (FAB bottom-right): Vibe (workspace/daylight/focus) · Densidade (Skim↔Briefing 0-100%) · Accent hue (0-360°). Repintura em runtime via CSS vars `oklch()`.
- **LinkedApps** completos: 5 cards colapsáveis com origin badge — OS, Cliente (CRM), Financeiro, Anexos, Histórico (timeline).
- **Thread polish**: header com avatar+dot online+actions, context bar (OS pill + cliente + estágio + prazo), bolhas com author label + grouping continued + ✓✓ vs ✓, typing indicator (3 dots animados), composer auto-grow.

### Deprecated

- **ADR raiz 0008** (sidebar 1-item + tabs horizontais) — `superseded by ADR raiz 0039 + UI-0008`. Era pro Ponto isolado dentro do AppShell legado; agora todo o ERP vive dentro do Cockpit.
- **ADR UI-0007** (topbar desktop removida) — parcialmente deprecada. Continua válida pro AppShell legado (telas standalone). No Cockpit, topbar volta com função real (breadcrumb dinâmico + ações contextuais).
- **Auto-memória `project_sidebar_groups_2026_04_27`** — superseded pela posição superadmin no rodapé do Cockpit. Permissões Spatie permanecem, mas localização visual mudou.

### Changed

- **ADR UI-0006** (padrão tela operacional) — escopo redefinido: continua canônico pro **conteúdo** da main column (`PageHeader+KpiGrid+PageFilters+Card(Table)`), mas o **envelope** migra de `<AppShell>` para `<AppShellV2>` (Cockpit).
- **AppShell legado** rebaixado a "shell secundário" — mantido só pra telas administrativas isoladas (Showcase, Modulos manage). Cockpit é o default pra qualquer tela operacional.

### Notes

- Branch `feat/copiloto-cockpit-piloto` em produção como teste do padrão. PR pendente pra mergear no `main` quando Wagner aprovar.
- Backend ainda mock pra `conversas`/`mensagens` no Cockpit. Plug do chat real do Copiloto = Fase 3 do plano de migração (ver ADR UI-0008).
- Heurística de "superadmin label" hardcoded por enquanto (set + regex). TODO Fase 5: virar flag `is_superadmin` no `MenuItem` do `LegacyMenuAdapter`.

## [0.2.0] - 2026-04-24

### Added

- **Camada de componentes de produto em `Components/shared/`** (ADR UI-0005):
  - `PageHeader`, `KpiCard` (+ onClick/selected), `KpiGrid`, `StatusBadge` (6 domínios), `PageFilters` + `FilterChip`, `EmptyState` (4 variants), `BulkActionBar`.
  - Showcase em `/showcase/components` (superadmin) com todos os componentes em estados típicos.
  - ~48 kB gzipped de código reutilizável cobrindo ~80% do padrão visual das telas operacionais.
- **Padrão de tela operacional formalizado** (ADR UI-0006): esqueleto `PageHeader → KpiGrid → PageFilters → Card(Table/EmptyState) → BulkActionBar → Dialogs` pra todas as listagens filtradas. Exceções documentadas (Espelho/Show canvas, Chat, Memoria, formulários).
- **Regra R-DS-008** (SPEC): toda tela de listagem operacional nova deve usar o template da ADR 0006.

### Changed

- **Topbar desktop removida** (ADR UI-0007). `<header>` do AppShell passou a ser `md:hidden` — só mobile tem topbar (precisa do hamburger). Desktop economiza 48px de altura; breadcrumb vira primeira linha após ModuleTopNav.
- **Prova de conceito**: `Ponto/Aprovacoes/Index` refatorada usando 6 dos 7 componentes shared + adicionada nova feature (bulk approve) que o backend já suportava mas a UI não expunha. Commit `22d0fdc5`.

### Notes

- O ganho em linhas de código é cumulativo — primeira tela refatorada quase empata (480 → 568 com bulk approve novo), mas a próxima (`Intercorrencias/Index` de 206 linhas) deve cair pra ~120 sem perder nada, porque não precisa redefinir `estadoConfig`/`prioridadeConfig`/empty state/filter chips.

## [0.1.0] - 2026-04-22

### Added

- Módulo virtual `_DesignSystem/` criado como piloto de pasta cross-cutting (ADR 0007 do MemCofre).
- README + ARCHITECTURE + SPEC + CHANGELOG + GLOSSARY + adr/ui/ com 4 ADRs iniciais.
- 7 regras globais (R-DS-001 a 007): primitivas shadcn, tokens semânticos, lucide, espaçamento 4px, dark mode, focus visível, sem CSS custom sem ADR.
