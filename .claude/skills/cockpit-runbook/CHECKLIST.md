# CHECKLIST — Validação do RUNBOOK gerado

Aplica DUAS funções:
- **Modo Generate (validate phase):** rodar contra o `.PLAN.md` antes de salvar versão final.
- **Modo Audit:** rodar contra tela existente, gerar relatório `file:line` de violações.

Inspirado em [Vercel Web Design Guidelines](https://www.firecrawl.dev/blog/best-claude-code-skills) (audit 100+ rules) e Anthropic feedback loop pattern.

## A. Estrutura do RUNBOOK (modo Generate)

| # | Regra | Como verificar |
|---|---|---|
| A1 | Frontmatter YAML completo (slug/title/type/module/status/date) | Frontmatter parsea como YAML válido |
| A2 | 11 seções numeradas (`## 1. Objetivo` → `## 11. ADR de origem`) + opcionalmente 1 seção introdutória `## Estado final esperado` antes de §1 (padrão `Infra/RUNBOOK-criar-modulo.md`) | `grep -c "^## " runbook.md` retorna 11 ou 12 |
| A3 | Slug em kebab-case `<mod-lower>-runbook-<tela-kebab>` | Regex `^[a-z]+-runbook-[a-z-]+$` |
| A4 | Linha final `**Última atualização:** YYYY-MM-DD` | Última linha do arquivo |
| A5 | Comprimento entre 200-400 linhas | `wc -l < runbook.md` |
| A6 | PT-BR em todos os cabeçalhos `##` | Sem `## Goals`, `## Steps` etc. |
| A7 | Forward slashes em todos os paths | `grep -c '\\\\' runbook.md` retorna 0 |

## B. Conteúdo das 11 seções

| # | Seção | Mínimo |
|---|---|---|
| B1 | §1 Objetivo | 3-5 linhas + tabela "Estado final" com 3-5 verificações |
| B2 | §2 Pré-condições | ≥4 itens `- [ ]`; cita módulo + permissão + rota + page |
| B3 | §3 Passo-a-passo | 5-10 passos `### N.` cada um com snippet executável |
| B4 | §4 Tokens CSS | Tabela cobrindo todas as 13 vars canônicas com ✅/❌ |
| B5 | §5 Estados visuais | Tabela com 7 estados (default/hover/focus/active/disabled/loading/empty/error) |
| B6 | §6 Responsividade | Tabela com ≥3 breakpoints (mobile, tablet, desktop) |
| B7 | §7 Atalhos | Tabela com 7 teclas canônicas (⌘K/J/K/E/A/N/`/`). **Tela sem nenhum atalho:** marcar todas com `—` + nota "Tela não implementa atalhos — navegação por mouse/touch". Não é falha. |
| B8 | §8 Component contract | Interface TypeScript dos props + lista shared usados |
| B9 | §9 DoD | ≥10 itens `- [ ]` |
| B10 | §10 Pegadinhas | ≥5 itens `❌ NÃO X — sintoma → fix`; ≥1 específica da tela |
| B11 | §11 ADR de origem | ≥3 ADRs com link clicável + 1 linha de justificativa |

## C. Links e referências

| # | Regra | Como verificar |
|---|---|---|
| C1 | Todos os ADRs citados existem | `ls memory/decisions/NNNN-*.md` resolve para cada slug citado |
| C2 | Path da Page Inertia existe | `ls resources/js/Pages/<Mod>/<Tela>.tsx` resolve |
| C3 | Componentes shared citados existem | Cada `@/Components/...` resolve em `resources/js/Components/` |
| C4 | Sem links quebrados (relativos) | Cada `[texto](../../path)` aponta pra arquivo existente |
| C5 | Sem links absolutos pra outros sistemas (Notion, Drive) | `grep -E "https?://" runbook.md` só retorna URLs públicas (Anthropic docs) |

## D. Anti-padrões (modo Generate + Audit)

| # | Anti-pattern | Detecção |
|---|---|---|
| D1 | Cor crua Tailwind (`bg-blue-500`, `text-gray-700`) | `grep -E "bg-(blue|red|green|yellow|gray)-[0-9]+"` em snippets retorna 0 |
| D2 | `route('xxx')` em React snippet | `grep "route\\(" runbook.md` em blocos tsx retorna 0 |
| D3 | `<button>` HTML cru em snippet | `grep "<button " runbook.md` em blocos tsx retorna 0 |
| D4 | `npm run build` (sem `:inertia`) | `grep "npm run build[^:]" runbook.md` retorna 0 |
| D5 | `sessionStorage` pra estado UI | `grep "sessionStorage" runbook.md` retorna 0 |
| D6 | Page envolta em `<AppShell>` (sem V2) ou inline em vez de `.layout` | snippets seguem `Tela.layout = (page) => <AppShellV2>{page}</AppShellV2>` |
| D7 | Cor hardcoded em CSS (`#FFFFFF`, `rgb(...)`) | `grep -E "#[0-9a-fA-F]{6}|rgb\\(" runbook.md` em snippets css/tsx retorna 0 |
| D8 | Atalho registrado sem `removeEventListener` | Aplica SÓ se a tela registra atalhos. Cada `addEventListener` tem cleanup correspondente. Se §7 está toda `—`, regra não se aplica. |

## E. Audit-mode (rodar contra tela existente, NÃO contra runbook)

Quando o gatilho é "audita tela X contra Cockpit", aplicar D1-D8 + as regras do Design System ([_DesignSystem/SPEC.md](../../memory/requisitos/_DesignSystem/SPEC.md)):

| Regra | Origem | Output |
|---|---|---|
| R-DS-001 — sem `<button>` HTML cru | _DS SPEC | `file:line — usar <Button> de @/Components/ui/button` |
| R-DS-002 — sem cor crua Tailwind | _DS SPEC | `file:line — usar bg-primary / text-muted-foreground` |
| R-DS-003 — ícones só lucide-react | _DS SPEC | `file:line — importar de lucide-react, não @radix-ui/icons` |
| R-DS-004 — espaçamento múltiplo de 4px | _DS SPEC | `file:line — não usar arbitrary values <-[17px]>` |
| R-DS-005 — dark mode obrigatório | _DS SPEC | `file:line — testar em dark, contraste < 4.5:1` |
| ADR 0039 §3 — coluna direita se contexto vinculado | ADR 0039 | `file:line — entregar <LinkedXxx/> ou justificar em ADR` |
| ADR 0039 §4 — `localStorage` com prefixo `oimpresso.` | ADR 0039 | `file:line — usar localStorage, não sessionStorage` |
| Persistent layout (DESIGN.md §4) | DESIGN.md | `file:line — usar Tela.layout = (page) => <AppShellV2>...` |

**Formato do relatório de audit:**

```
[CRITICAL] resources/js/Pages/<Mod>/<Tela>.tsx:42 — R-DS-002 violado (bg-blue-500) — fix: bg-primary
[CRITICAL] resources/js/Pages/<Mod>/<Tela>.tsx:88 — R-DS-001 violado (<button>) — fix: importar <Button>
[WARN]     resources/js/Pages/<Mod>/<Tela>.tsx:120 — ADR 0039 §3 — coluna direita ausente apesar de contexto vinculado (meta selecionada)
[INFO]     resources/js/Pages/<Mod>/<Tela>.tsx:150 — R-DS-004 — espaçamento -[17px]; usar -4 (16px) ou -5 (20px)
```

Ordem: CRITICAL → WARN → INFO. Wagner decide se ajusta tela ou registra exceção em ADR.

## F. UX heurísticas (modo Audit + Generate)

**Por quê esta seção existe:** auditoria 100% técnica (R-DS-001..012, ADR 0039) detecta violações de regra mas pode dar 30+ findings sem capturar "usuário sente isso?". Sessão de audit Whatsapp/Conversations 2026-05-07 (PR #173) reportou 30 findings técnicos e zero observação tipo "empty state não tem CTA — usuário vê tela vazia sem saber o próximo passo". Esta seção fecha esse gap.

8 heurísticas Nielsen aplicáveis a SaaS B2B + 5 perguntas pré-merge. Cada uma vira finding `[UX]` no relatório de audit (severidade WARN por default — INFO se cosmético, CRITICAL se bloqueia tarefa).

### F.1 Heurísticas Nielsen (8 aplicáveis)

| # | Heurística | Como verificar na tela |
|---|---|---|
| H1 | **Visibility of system status** | Loading state em ações >300ms? Presence (Centrifugo `● live`)? Status badge visível? Conta de unread? |
| H2 | **Match system ↔ real world** | Linguagem do usuário (PT-BR sem jargão dev)? Ordem natural (cliente acima do telefone)? Termos do domínio (OS, repair, NFe)? |
| H3 | **User control and freedom** | Undo após ação destrutiva? `Esc` fecha modal/drawer? Botão voltar em fluxos? Confirma antes de deletar? |
| H4 | **Consistency and standards** | Mesmo pattern em telas similares (inbox Whatsapp ↔ inbox Copiloto)? Componentes shared reusados (`PageHeader`, `EmptyState`)? Atalhos canônicos (J/K/E/A) onde aplicável? |
| H5 | **Error prevention** | Confirma destrutivo (resolver, arquivar, deletar)? Valida campos antes de submit? Avisa janela 24h fechada antes de tentar enviar freeform? |
| H6 | **Recognition over recall** | Mostra opções (dropdown, lista) em vez de exigir lembrança (digitar comando)? Filtros visíveis com contadores? Ações da entidade ao lado dela? |
| H7 | **Flexibility and efficiency** | Atalhos pra power user (J/K/E/A)? Bulk actions onde aplicável? Search debounced? Persistência de filtro/aba? |
| H8 | **Aesthetic and minimalist** | Hierarquia visual clara (1 título principal + 1 ação primária por seção)? Sem informação irrelevante competindo? Density apropriada (densa pra inbox, espaçada pra detalhe)? |

> **H9 (recover from errors)** e **H10 (help/documentation)** ficam de fora porque são cobertas em cross-cutting concerns (Sentry pra H9; tooltip+docs pra H10) e não pertencem a audit per-tela.

### F.2 5 perguntas pré-merge

Antes do PR aprovar, mentalmente abrir a tela e responder:

| # | Pergunta | Falha = WARN | Falha = CRITICAL |
|---|---|---|---|
| Q1 | Em <3 segundos, usuário entende o que fazer aqui? | Layout confuso / hierarquia fraca | Tela vazia sem instrução / título ambíguo |
| Q2 | Se usuário errar (clicar errado, digitar errado), fácil voltar? | Sem botão "voltar" óbvio | Ação destrutiva sem confirma / undo |
| Q3 | Botões parecem clicáveis (affordance via shadcn)? | `<button>` HTML cru sem `<Button>` | Ação primária invisível (sem cor distinta) |
| Q4 | Loading visível em ações >300ms (PATCH, fetch, build)? | Sem spinner/skeleton em ação lenta | Tela parece "travada" durante PATCH |
| Q5 | Empty state convida ação ou só informa? | "Nada aqui." sem CTA | Empty bloqueia fluxo (usuário não sabe próximo passo) |

### F.3 Output formato no audit

```
[UX-WARN]  resources/js/Pages/<Mod>/<Tela>.tsx:120 — H8 (minimalist) — header dispara 5 elementos competindo (avatar+nome+telefone+presence+badge); reduzir a 3 ou hierarquizar
[UX-CRITICAL] resources/js/Pages/<Mod>/<Tela>.tsx:88 — H5 (error prevention) — botão "Marcar resolvida" sem confirma; usuário pode resolver conversa ativa por engano
[UX-INFO]  resources/js/Pages/<Mod>/<Tela>.tsx:45 — Q5 — empty state mostra só "💬 Nenhuma conversa" sem CTA; sugerir "Webhooks Meta/Z-API ainda não chegaram — [Configurar driver →]"
```

Severidade UX é semi-objetiva. Fallback se houver dúvida: **WARN** + nota.

## G. Score quantificado 0-100

**Por quê:** dado o ERP tem 30+ telas Inertia, "esta tela tem 12 CRITICAL + 8 WARN" não ajuda Wagner priorizar. Score numérico permite ordenar refactor backlog.

### G.1 Fórmula

```
DS_score   = clamp(40 - critical_ds * 5  - warn_ds * 2  - info_ds * 0.5, 0, 40)
ADR_score  = clamp(30 - violacao_adr * 5, 0, 30)
UX_score   = clamp(30 - critical_ux * 6  - warn_ux * 3  - info_ux * 1, 0, 30)

TOTAL = DS_score + ADR_score + UX_score   # range 0-100
```

Onde:
- `critical_ds` = R-DS-001/002/003 (ícones, cor crua, button HTML cru) violations
- `warn_ds` = R-DS-004/005/006/007 (espaçamento, dark mode, focus, CSS custom) violations
- `info_ds` = R-DS-008/011/012 (templates, origin badges, localStorage prefix) violations
- `violacao_adr` = ADR 0039 §1-§5 violations
- `critical_ux`/`warn_ux`/`info_ux` = findings UX da §F.3

### G.2 Bandas

| Score | Banda | Ação sugerida |
|---|---|---|
| 90-100 | 🟢 Estado da arte | OK pra mergear; só polish opcional |
| 70-89 | 🟡 Bom, alguns gaps | Mergear se velocidade > polish; refactor em ciclo seguinte |
| 50-69 | 🟠 Precisa refactor | Não mergear sem corrigir CRITICAL; criar tasks pros WARN |
| <50 | 🔴 Reescrever | Pause; abrir ADR de exceção ou refatorar antes de seguir |

### G.3 Output do audit termina com bloco Score

```
## Score

| Categoria | Score | Detalhe                                              |
|-----------|-------|------------------------------------------------------|
| DS (40)   | 22/40 | 3 CRITICAL (ícones), 5 WARN (cor crua), 2 INFO       |
| ADR (30)  | 15/30 | 3 ADR 0039 violations (§3 Apps Vinculados, §2 J/K, §4 localStorage) |
| UX (30)   | 22/30 | 1 CRITICAL (Q5 empty state), 2 WARN (H8, H5)         |
| **TOTAL** | **59/100** | 🟠 **Precisa refactor** — corrigir 3 CRITICAL antes de mergear   |
```

### G.4 Tradeoff conhecido

Score é **semi-objetivo** — depende de classificar finding em CRITICAL/WARN/INFO, que tem subjetividade. Não tratar como nota absoluta; usar pra **comparar** telas (Whatsapp/Index 67 vs Copiloto/Cockpit 54 → priorizar Copiloto refactor) ou **rastrear** (mesma tela 67 → 89 após refactor).

## H. Feedback loop (modo Generate)

```
1. Gere o RUNBOOK em memória → escreva como .PLAN.md
2. Rode A1-A7, B1-B11, C1-C5 contra .PLAN.md
3. Se alguma falhar: corrija .PLAN.md e revalide
4. Quando todas passarem: salve final em RUNBOOK-<tela>.md, apague .PLAN.md
5. Ao final: lembre Wagner do git add + commit + push (skill memory-sync)
```

Esse loop é o que separa runbook bom de stub. Não pular.
