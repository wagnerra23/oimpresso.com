---
owner: wagner
last_reviewed: "2026-06-06"
next_review: "2026-07-31"
related_adrs: [0235, 0249, 0253, 0254]
note: "SSOT de design. next_review obrigatório (DesignDocsFreshnessChecker ADR 0236) — é o que impede este índice de apodrecer como aconteceu com 'PT-02..05 não existem'."
---

# ÍNDICE-MESTRE — Memórias de Design que o Claude Design pode usar

> **Pra que serve:** ponto único de partida do Claude Design. Lista TODAS as memórias de design do projeto — **positivas** (o que copiar/seguir) e **negativas** (o que NÃO repetir) — já reconciliadas, com a **regra de ouro** que resolve conflito. Substitui a tarefa de "montar contexto de cabeça" (origem da invenção e do erro repetido).
> **Gerado:** 2026-05-30 por 4 agentes especialistas em paralelo (ADR 0231) revisando 27 docs `prototipo-ui/` + 26 `_DesignSystem/` + 17 ADRs UI + 18 decisions. Sessão `2026-05-30-screen-grade-metodo-estado-arte.md`. PR #1991.

---

## 0 · REGRA DE OURO (como resolver qualquer conflito)

Quando duas memórias divergem, vence nesta ordem:

1. **ADR canon mais recente com `supersedes`** (memórias são append-only — o aposentado vira histórico, NÃO se usa).
2. **DS v6 (nome canônico — ADR 0249) sobre o roxo do ADR 0235 > DS v3 > v2.** Cor canon = `primary` roxo `oklch(0.55 0.15 295)` (0235, âncora de cor); "DS v6" é o nome único da camada de tokens semânticos (`--pos/--neg/--warn/--stage-*/--origin-*`). Azul em tela = **débito a migrar**, nunca padrão novo. *(v4/v4.2/v5 = nomes antigos da mesma coisa — usar só "DS v6".)*
3. **Código real > documento.** Se a doc diz "tela X tem drift Y" e o código não tem mais, o código vence (a MATRIZ_MIGRACAO_DS tem falsos-positivos confirmados — ver §4).
4. **Constituição UI v2 (ADR UI-0013):** Fundações → Shell → Padrão de Tela → Módulo. Camada superior herda e **nunca contradiz** a inferior.
5. **Data mais recente** vence em docs operacionais (briefings, handoffs, notes).

> **Meta-regra única (a frase):** *Crie DENTRO do que existe — copie o golden do arquétipo, rode o PRE-FLIGHT, use só `@/Components/ui` + token `primary` roxo, e NUNCA invente (componente, Model, paleta) nem repita um anti-padrão já catalogado. Em dúvida, pergunte (UI-0013 regra-mestre).*

### 0.1 · Fontes e NÃO-fontes de design (resolva ANTES de agir)

> Incidente 2026-06-22 ([ADR 0299](../../decisions/0299-figma-nao-e-fonte-de-design.md)): a IA tratou "design" como **Figma** porque o MCP do Figma injeta, always-on, uma ordem pra usá-lo "para qualquer UI/tela". **Não é.** A fonte é esta.

**FONTE de design (o que vale):**
- **Protótipo Cowork** — `prototipo-ui/prototipos/<tela>/` (export do Cowork; **read-only no repo** — regra de ouro §"Regra de ouro" do `prototipo-ui/README.md`).
- **Design System** — tokens semânticos + componentes `@/Components/ui` + primitivos `@/Components/layout` (versão atual do DS = §0 item 2 acima; nunca hardcodar versão fora daqui).
- **Charter da tela** — `<Tela>.charter.md` ao lado do `.tsx`.

**NÃO-fonte (exige Wagner dizer explícito "figma" / "usa o X"):** Figma · Notion · screenshot solto · link externo · qualquer MCP de design novo. São **atratores**, não canon — trazer visual de lá sem decisão consciente É a falha de 2026-06-22.

**A "diff design→code"** = `memory/requisitos/<Mod>/<tela>-visual-comparison.md` via skill **`mwart-comparative`** (existe hoje). Um `/design-diff` determinístico (render protótipo vs Page) está **previsto** no [ADR 0299](../../decisions/0299-figma-nao-e-fonte-de-design.md), ainda não implementado. *(Não existe `prototipos/<tela>/COMPARISON.md` — esse path foi alucinado num rascunho e fica registrado aqui pra não voltar.)*

**Código produtivo** (editável na F3 do MWART) = `resources/js/Pages/<Mod>/<Tela>.tsx`. O `page.tsx`/`*.jsx` em `prototipo-ui/prototipos/` **não** se edita no repo (re-exporta do Cowork).

**Enforcement:** o atrator Figma é bloqueado por `block-figma-without-optin.mjs` (PreToolUse, fail-closed) — pra usar Figma de propósito diga "figma" explícito. Os demais atratores (Notion/screenshot) ainda são **advisory** por aqui; fechar a classe inteira com block = trabalho futuro no [ADR 0299](../../decisions/0299-figma-nao-e-fonte-de-design.md).

### 0.2 · OS PROJETOS COWORK REAIS (resolvido 2026-07-06 — pra NÃO errar de novo)

> Wagner não usa Figma; usa **Claude Design (Cowork)**. O `oimpresso.com` MCP-conector Figma que aparece "conectado" é **irrelevante** pro fluxo dele. A fonte viva é o Cowork, lido pela integração `DesignSync` (ferramenta nativa do Code, após `/design-login` UMA vez no terminal CLI — a autorização fica salva na máquina).

**São DOIS projetos Cowork de nomes parecidos — a confusão de hoje veio de tratar um pelo outro:**

| Projeto | ID | O que é | É fonte de tela? |
|---|---|---|---|
| **Oimpresso ERP Conunicação Visual.** | `019dcfd3-6ef2-7ee6-8512-b1b0e5544e58` | O **ERP inteiro**: o protótipo (`oimpresso.com.html` = shell que carrega os `*-page.jsx`) + espelho do código (`Unificado/Index.tsx` + `_components/`) + `memory/` + `ds-v6/`. **1337 arquivos.** | ✅ **SIM — a fonte das telas** |
| Office Impresso — Design System | `019dd02f-d2d0-7ba6-a57f-24b3ddd073ac` | Só a **biblioteca do DS**: `components/` (44 componentes), `templates/` (7 arquétipos: financeiro, oficina-auto, pt-01...), `Norte/`, `ui_kits/`. | ❌ NÃO — é o DS, não a tela. Os `templates/<x>.dc.html` são arquétipos do DS, não o design vivo de uma tela específica |

**A âncora de uma tela do Financeiro** resolve assim: `related_prototype`/`bundle_source` → o `*-page.jsx` do projeto **019dcfd3** (ex: `financeiro-page.jsx` pro `Unificado`). O shell `oimpresso.com.html` é o **visualizador** (renderiza todas as telas via Babel); o `*-page.jsx` é o **fonte** da tela específica — a âncora aponta pro fonte, não pro shell.

**O `prototipo-ui/cowork/` do repo é MIRROR do projeto 019dcfd3, em sincronia:** verificado 2026-07-06 — `financeiro-page.jsx` do repo é **idêntico sob normalização canônica** ao do Cowork vivo (md5 `ae3a2cfe8855fc41e25354fcaa03de84`, igual dos dois lados). *(Correção 2026-07-06, pós-adversário: a versão anterior dizia "byte-idêntico… a diferença de bytes é só CRLF" — logicamente impossível, ou é byte-idêntico ou difere por CRLF. **Identidade de arquivo = hash do conteúdo NORMALIZADO** — EOL→LF · sem BOM · trailing-newline única · UTF-8 — **keyed por PATH COMPLETO**, nunca bytes crus por basename. Ferramenta: `scripts/governance/cowork-mirror-freshness.mjs` (`--manifest`/`--compare`, exporta `normalize`/`contentHash` pro snapshot do vivo usar a MESMA régua). Fundamento: session 2026-07-06-arte-design-code-sync-frescor — é como git/Nx/Tokens Studio definem identidade. **Cadência:** o diff roda como rotina de dispatch logado com ledger datado + SLA 14d advisory no CI — modelo operacional na ADR-proposta 0324-frescor-espelho-cowork-dispatch-sla-limite-plataforma.)* Ou seja: o mirror **não apodrece sozinho** — quando alguém disser "esse protótipo é antigo", **diffar antes de concluir** (`git show origin/main:prototipo-ui/cowork/<arq>` vs `DesignSync get_file projectId=019dcfd3`, ambos sob a normalização acima). "Antigo" pode significar **direção de design a redesenhar** (≠ arquivo defasado) — são coisas diferentes; perguntar qual. **Pendência RESOLVIDA 2026-07-06:** [W] viu o protótipo do Financeiro renderizado (Visão Unificada, do espelho — hash-idêntico ao vivo) e **aprovou a direção**: *"esse mesmo, gostei"*. Não é redesign — a direção de design vigente do Financeiro é a do `financeiro-page.jsx`; "antigo" das falas anteriores fica encerrado como falso-alarme dos dois sentidos (arquivo estava atual E a direção está aprovada).

**Meta-lição (o erro que este bloco previne):** proveniência de design se resolve por **DIFF + integração ao vivo**, NUNCA por teoria a partir de nome de arquivo. Em 2026-07-06 o agente errou 3× seguidas teorizando (título "Chat" do shell = "arquivo errado"; puxou o projeto DS achando que era a tela; concluiu "antigo" sem diffar). Todas caíram quando trocou teoria por medição. **Ver a fonte (DesignSync/render) e diffar > adivinhar.**

---

## 1 · ORDEM DE LEITURA (sempre, antes de qualquer tela)

1. **[PRE-FLIGHT-TELA.md](../../../prototipo-ui/PRE-FLIGHT-TELA.md)** — monta o pacote de pré-requisitos DAQUELA tela (identidade/não-inventar/não-repetir/validar).
2. **[GOLDEN-REFERENCE.md](../../../prototipo-ui/GOLDEN-REFERENCE.md)** — a tela-ouro do arquétipo + 10 regras binárias. "Faça idêntico, mude só X."
2b. **[MANUAL-IDENTIDADE.md](MANUAL-IDENTIDADE.md)** ⭐ — a VOZ visual "Clareza Confiante": como deve PARECER + DO/DON'T + as 3 assinaturas. Define o *feel*; o golden define a *estrutura*.
3. **[REGISTRY_DS_COMPONENTES.md](../../../prototipo-ui/REGISTRY_DS_COMPONENTES.md)** — componentes `@/Components/ui` disponíveis. Se está aqui, **não hand-rola**. ➕ **Layout: usar primitivos `@/Components/layout` ([ADR 0253](../../decisions/0253-primitivos-layout.md)) — `Stack/Inline/Grid/Box/Container/Text`.** ⚠️ Onda F (`Segmented`/`FormSection`/`InputGroup`/`FieldState`) **ainda NÃO existe** — não assuma que existe.
4. **[LICOES_F3_FINANCEIRO_REJEITADO.md](../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md)** — os 21 anti-padrões (exemplo errado). Leitura obrigatória antes de F3.
5. **[SCREEN-GRADE-METODO.md](SCREEN-GRADE-METODO.md)** — como a tela será notada (16 dim, níveis Beginner→Champion).

> **Atalho operacional:** a skill **[`screen-grade`](../../../.claude/skills/screen-grade/SKILL.md)** (Tier B) dispara o Pré-Flight resolver + a nota automaticamente — basta "nota da tela X" / "/screen-grade Mod/Tela" ou tocar um `Pages/<Mod>/<Tela>.tsx`. Materializa os 5 passos acima sem montar contexto de cabeça.

---

## 2 · ÍNDICE POSITIVO (o que usar)

### 2a. Entrada / identidade do projeto
| Memória | Usar pra |
|---|---|
| **[MANUAL-IDENTIDADE.md](MANUAL-IDENTIDADE.md)** ⭐ | **A VOZ VISUAL — "Clareza Confiante" (2026-06-06).** Como toda tela deve PARECER: type-scale 8 tokens (incl `2xs`), 3 assinaturas (focus-ring roxo · barra-accent · tabular-nums), densidade 36px, motion 150ms, DO/DON'T. **Leitura obrigatória antes de craftar identidade.** Irmã do MANUAL-CSS-JS (voz de código) |
| [memory/why-oimpresso.md](../../why-oimpresso.md) | **Posicionamento REAL: ERP modular multi-vertical** (ADR 0121). Larissa/ROTA LIVRE = **vestuário** (não gráfica). ⚠️ corrige briefings antigos |
| [personas-por-modulo.yml](personas-por-modulo.yml) + ADR UI-0016 | Persona dona da tela (Larissa 1280px densidade · Eliana tabela densa · técnico touch ≥44px) |
| [prototipo-ui/GLOSSARY.md](../../../prototipo-ui/GLOSSARY.md) | Siglas, fases, comparáveis externos por arquétipo |

### 2b. Padrões — o que COPIAR
| Memória | Usar pra |
|---|---|
| **GOLDEN-REFERENCE.md** | tela-ouro `Sells/Create` (form) + 10 regras binárias R1-R10 |
| **padroes-tela/PT-01-Lista.md** | golden do arquétipo **lista** (status sem bg-fill, slots) |
| **REGISTRY_DS_COMPONENTES.md** | componentes reais `@/Components/ui` |
| **[ADR 0253 — primitivos de layout](../../decisions/0253-primitivos-layout.md)** ⭐ | `<Stack/Inline/Grid/Box/Container/Text>` em `@/Components/layout` — **layout é COMPOSIÇÃO de primitivo, nunca `flex`/`grid` solto** (props = token). Showcase em `/showcase/components` |
| **tokens v4** (`resources/css/inertia.css`, ADR 0235) | cor = `primary` roxo. Zero `blue-*` de marca |
| ADR 0110 (Cockpit Pattern V2) + UI-0010 `os-page.jsx` + UI-0012 | padrão list+detail / shell |
| ADR UI-0015 | campos de form default `variant="cowork"` |
| [framework-15-dimensoes.md](framework-15-dimensoes.md) | as 15 dim de qualidade + ponderação por persona |
| [pageheader-matriz-diferencas.md](pageheader-matriz-diferencas.md) | PageHeader: F1-F16 fixas vs V1-V9 variáveis |
| [PRE-MERGE-UI.md](PRE-MERGE-UI.md) · [SPEC.md](SPEC.md) (R-DS-001..012) · README.md · ARCHITECTURE.md | regras duras + checklist + mapa da stack |
| [MANUAL-CSS-JS.md](MANUAL-CSS-JS.md) | regimento **CSS/JS** — do/don't + arquitetura-alvo (token DS v6 → primitivos **layout**+ui → padrão → página) + gap dos primitivos de layout (§2.1) + roadmap de convergência F0–F7 (sair do sprawl de 28k linhas) |
| RUNBOOK-{replicar-prototipo-cowork,onda-cowork,design-deep,inertia-defer-pattern}.md | receitas de execução (portar protótipo, ondas, defer) |

> ✅ **Goldens de arquétipo (corrigido 2026-06-06): PT-01 Lista · [PT-02 Form/Drawer](padroes-tela/PT-02-Form-Drawer.md) · [PT-03 Detalhe](padroes-tela/PT-03-Detalhe.md) · [PT-04 Dashboard](padroes-tela/PT-04-Dashboard.md) · [PT-05 Kanban](padroes-tela/PT-05-Kanban.md) — TODOS existem** em [`padroes-tela/`](padroes-tela/). _(O INDEX dizia "faltam PT-02..05" — era stale de 2026-05-30; os 5 arquivos têm 116-135 linhas cada.)_

### 2c. Validação / definição de pronto
| Memória | Usar pra |
|---|---|
| **SCREEN-GRADE-METODO.md** | nota 16-dim, níveis, score-as-code (UX amplo, por tela) |
| **[ADR 0254 — grade de identidade DETERMINÍSTICO](../../decisions/0254-design-identity-grade-deterministico.md)** ⭐ | nota de **identidade** 0-100 **σ=0** por regex (anti-alucinação — cura o LLM-judge que dava 91→71). `node scripts/design-identity-grade.mjs` · ratchet só sobe · **hoje 66, meta 85** · lista top-5 ofensores |
| [design-requests/LEDGER.md](../../governance/design-requests/LEDGER.md) | ledger incremental de pedidos (REQ-NNN, **file-based**) — checar "já processei?" antes de trabalhar; grava resultado/grade ao fechar · ⚠️ scaffold pré-ADR ([proposta](../../decisions/proposals/design-request-ledger-incremental.md)) |
| [PRE-MERGE-UI.md](PRE-MERGE-UI.md) | checklist AP1-AP8 antes de merge |
| `php artisan ui:lint` (R1-R6, CI ratchet) | cor crua, FontAwesome, emoji, PT-01, origens, blade |
| `ds:report` (regras `ds/*`, ADR 0209) | violações de DS (zero é meta) |

---

## 3 · ÍNDICE NEGATIVO (não repetir — memórias negativas)

> Estas existem pra o Claude Design **não repetir erro já pago**. Anthropic context-engineering: "manter o erro no contexto previne repeti-lo."

### 3a. Meta-anti-padrões (LICOES_F3 — comportamento)
| Código | Não fazer |
|---|---|
| M-AP-1 | auto-doc/aprendizado não é gate — não pular validação sob pressão |
| M-AP-2 | marketing otimista — não chamar 4/5 stubs mock de "F3 completo" |
| M-AP-3 | "f3" no chat ≠ aprovação dos gates F1.5+F2 |
| M-AP-4 | schema/Model proposto NÃO vira código sem ADR |
| M-AP-5 | decisão pendente NÃO vira default silencioso hardcoded |
| **M-AP-6** | **"criar componente se não existe" institucionaliza invenção** — proibido |

### 3b. Anti-padrões técnicos (não inventar)
| Código | Não fazer |
|---|---|
| T-AP-1/2 | **Models inventados** — `FinancialEntry`/`BankAccount`/`ChartOfAccount` NÃO existem; reais = `Titulo`/`ContaBancaria`/`Categoria` |
| T-AP-2 | Controller sem tenant scope (Tier 0 / ADR 0093) |
| T-AP-3 | middleware fantasma `'tenant'` — canon = `['web','auth','language','timezone','AdminSidebarMenu']` |
| T-AP-4 | sobrescrever Controller de tela já em prod (regressão silenciosa) |
| T-AP-7 | Services inventados sem `Glob Modules/<Mod>/Services/` |
| T-AP-8 | `auth()->user()->business_id` — canon = `session('user.business_id')` (Job/CLI) |
| T-AP-13 | mutação NO-OP `return back()` (botão clica, nada persiste) |
| T-AP-15 | sidebar inventado (`sidebar.php`) — canon = `DataController::user_permissions` + `SIDEBAR_GROUPS` |
| T-AP-18 | fallback default sem `Log::warning` (bug Larissa R9) |

### 3c. Proibições visuais (lista canônica = [PRE-MERGE-UI.md](PRE-MERGE-UI.md) AP1-AP10 + PT-01 §Nunca + pageheader F12-F16)
Sem: CTA WhatsApp cliente-facing · modal full-screen (usar drawer/Sheet) · inglês em UI · `rounded-xl+` · emoji em UI produtiva · cor crua fora dos tokens · gradiente 135° bluish-purple · sombra forte · **reinventar shared** (Table/Drawer/PageHeader — AP2) · localStorage sem prefixo `oimpresso.<mod>.*` · ícone fora do lucide · **status badge com bg-fill** (canon = dot + texto colorido, Stripe-style — AP7) · label não-PT-BR · `<main>` aninhado · chain de overflow sem `h-full`/`min-h-0` · primary magenta legacy (hue 330) · botão que duplica navegação já coberta por ghost.
> ⚠️ `ui:lint` **NÃO** pega AP2/AP5/AP7/AP8 (componente reinventado, gradient, bg-fill badge, copy não-PT-BR) — esses dependem de revisão humana/golden.

### 3d. Drifts de implementação catalogados
| Origem | Erro |
|---|---|
| GOLDEN-REFERENCE §4 | a própria golden usa `rounded-xl` nos KPI (`Sells/Create:916,924,932,942`) → corrigir p/ `rounded-lg` ao copiar |
| DS_ADOCAO_INDICE | drift-raiz: hand-rolou `<input radio>` existindo `radio-group.tsx` — falta de guard |
| HANDOFF/SYNC_LOG | NÃO copiar zip Cowork `{Modules,Pages,resources}` direto pra raiz (viola Tier 0, regride prod) |

### 3e. Lápides — mortos com lição (grande inspeção 2026-06-06)

> **Histórico negativo é first-class** (decisão Wagner 2026-06-06: *"ter o histórico negativo pra não voltar a errar é muito importante"*). Estes docs/ADRs **ficam no lugar** (append-only, status de lápide + `morreu_porque` no frontmatter) — listados aqui pra **não repetir o erro já pago**.

| Morto | Estado | Lição (`morreu_porque`) |
|---|---|---|
| **UI-0010 / UI-0012** (zip-cowork "canon visual") | `superseded` → [UI-0018](adr/ui/0018-canon-visual-vivo-ds-v6-manual-identidade.md) | zip datado ≠ canon. Verdade viva = DS v6 + primitivos ([0253](../../decisions/0253-primitivos-layout.md)) + [Manual de Identidade](MANUAL-IDENTIDADE.md). **NÃO copiar HTML/cor de zip.** |
| **BRIEFING_CLAUDE_DESIGN / _PROXIMA_SESSAO** | `accepted-historical` | propunham azul 220° + sidebar dark — canon = roxo 295 + light. NÃO usar de ponto de partida |
| **CATALOGO_ACABAMENTOS** | `accepted-historical` (parcial) | só a **COR** (azul 220) morreu; estrutura/tipo/espaço seguem válidos |
| **sidebar-rail-mode / GUIA-SIDEBAR-V3** | `superseded`/`historical` | hue-per-grupo morto — verdade = `cockpit/shared.ts` (código>doc) |
| **AUTOMATION-ROADMAP** | `accepted-historical` | dizia "zero ondas" — falso, ondas+6 gates entregues. NÃO ler como roadmap futuro |
| **from-claude-design/04-conventions · decisions-README** | `superseded` | importados PontoWr2 legado (Laravel 10) → `.claude/rules/` + MCP `decisions-search` |
| **audits/2026-04-24** | `deprecated` | duplicata byte-a-byte de `2026-04-22` |
| **ui_kits/_BACKUP-NAO-USAR-cowork-2026-04-27** | tombstone (no nome) | snapshot abril — **NÃO usar** (mantido como histórico negativo · decisão Wagner) |

---

## 4 · CONFLITOS RECONCILIADOS (a regra de ouro aplicada)

| Tema | Canon HOJE | Aposentado / não-usar | Regra |
|---|---|---|---|
| **Cor / accent** | **ADR 0235 — roxo `primary` `oklch(0.55 0.15 295)`** universal | ADR 0190 (`superseded`) · azul de marca | R1+R2 |
| **Sidebar (cor)** | **light** por padrão (UI-0009 + UI-0014) | "sidebar dark sempre" (rejeitada) | R1 |
| **Sidebar (estrutura)** | single-pane scroll, user menu cascata (UI-0011) | toggle Chat↔Menu dual-pane (UI-0008/0039) | R1 |
| **DS v3 vs UI v2** | **coexistem** — DS v3 é o "como", UI v2 é o "o quê" (UI-0017) | — | R4 |
| **MATRIZ Cliente/Index "modais/select nativo"** | **código real**: já é `rounded-lg` + `<Select>` shadcn | entradas P0-3 da MATRIZ (falsos-positivos) | R3 |
| **Azul na golden** | preservar azul **semântico** (status); trocar azul **de marca** (link/foco) → `primary` | — | R2 (nuance AUDITORIA_DS_V4 §3) |
| **Posicionamento do produto** | **ERP multi-vertical**, Larissa = vestuário (why-oimpresso, ADR 0121) | "só comunicação visual" (briefing antigo) | R5 |
| **Onda F componentes** | **não existem ainda** (REGISTRY) — hand-rola até criar | assumir `<Segmented>` etc. existe (= M-AP-6) | R3 |
| **Topbar** | presente no Cockpit (UI-0008) | UI-0007 (removida) — só AppShell legado | R1 |
| **Sidebar hue-per-grupo** | **código `cockpit/shared.ts SIDEBAR_GROUP_HUE`** (reconciliado 2026-05-25) | hues de GUIA-SIDEBAR / sidebar-rail-mode (antigos) | R3 |
| **PageHeader (estrutura)** | **3 blocos fechados** separados, gap 12px (ADR 0189 v3.1, pageheader-matriz F1) | "layout flat 3 zonas" (ADR 0182) | R1 |
| **Azul que SOBREVIVE** | **origin-badge CRM = blue** (sistema de badge de origem, ≠ botão primary) — continua válido | confundir com cor de marca | R2 (nuance) |
| **Canon visual (vivo vs snapshot)** | **DS v6 + primitivos (0253) + Manual de Identidade — vivo e MEDIDO** (grade 0254) | zip-cowork UI-0010/0012 (`superseded` por UI-0018) — snapshot datado | R1+R5 |
| **DS naming (v3/v4/v5/v6)** | **"DS v6"** é o nome único (ADR 0249) | "v3/v4/v5" = nomes antigos da MESMA coisa (UI-0017 aditiva, não morre — só o nome) | R2 |

---

## 5 · HIERARQUIA CANÔNICA HOJE (estado final)

**Fundações** (tokens DS v3 + camada semântica **DS v6**/ADR 0249, accent **roxo 295** via ADR 0235) → **Shell** (AppShellV2/Cockpit, sidebar **light** single-pane) → **Padrão de Tela** (PT-01 Lista / Cockpit Pattern V2 / `os-page.jsx`) → **Módulo**. Owner da UI = **Claude Design plugin** (ADR 0235 §3), dentro do loop MWART (0104→0114) + persona (UI-0016).

---

## 6 · DOCS STALE (a refrescar — não são canon de cor/posicionamento)

| Doc | Problema | Ação |
|---|---|---|
| `CLAUDE_COWORK_PRIMER.md` | `last_sync 2026-05-09` — não conhece DS v4/GOLDEN/PRE-FLIGHT | **header canon adicionado 2026-05-30** (aponta pra este índice) |
| `CLAUDE_DESIGN_BRIEFING.md` ✅ reconciliado 2026-06-04 | §2 "só comunicação visual" + §4 azul/shadcn — errata "CANON ATUAL" no topo (roxo 295 + multi-vertical + DS v6/ADR 0249) | feito |
| `CODE_DESIGN_CONTRACT.md` ✅ reconciliado 2026-06-04 | citava "DS v3.1"/"Design System.html" como SoT — errata no topo: DS v6 (ADR 0249) + fonte `tokens.css` git (ADR 0239); regra-única segue válida | feito |
| `PROTOCOL.md` | v1.0, F3 "1 linha" (já estendido por PROTOCOL-F3) | costurar GOLDEN/PRE-FLIGHT como gate |
| `HANDOFF.md` | congelado 2026-05-15 | regenerar |
| `GLOSSARY.md` | `[M]=Manus` diverge de `[M]=Maiara` (canon) | reconciliar tabela de pessoas |
| `MATRIZ_MIGRACAO_DS.md` §P0-3 | 3 falsos-positivos Cliente/Index | corrigir (Cliente já migrado) |
| **`BRIEFING_CLAUDE_DESIGN.md`** ✅ reconciliado 2026-06-04 | doc-âncora do Claude Design; corpo "sidebar dark + hue 220" corrigido → sidebar light + roxo 295 (errata topo + linha 167) | feito |
| `CATALOGO_ACABAMENTOS.md` ✅ reconciliado 2026-06-04 | snapshot 2026-04-27 (hue 220) — errata no topo: paleta defasada, cor via ADR 0235; tipografia/estrutura seguem | feito |
| `BRIEFING_PROXIMA_SESSAO.md` ✅ reconciliado 2026-06-04 | briefing de sessão antiga (azul 220 + sidebar dark) — errata no topo → roxo 295 + sidebar light | feito |
| `SPEC.md` (R-DS-002) + `ARCHITECTURE.md` + `AUTOMATION-ROADMAP.md` | exemplos `bg-blue-500` | trocar exemplo p/ roxo (confunde com primary) |
| `GUIA-SIDEBAR-V3-PASSO-A-PASSO.md` ✅ reconciliado 2026-06-04 | hues divergem de `shared.ts` — errata no topo: código real > doc, não copiar hue daqui (pegar do `SIDEBAR_GROUP_HUE`); estrutura segue útil. `sidebar-rail-mode` idem | feito |

---

> _Atualizar este índice sempre que um doc de design for criado/aposentado. É a fonte da verdade do que o Claude Design consome._

---

## 7 · Estado do reprocesso (mantido pela skill `design-memoria-reprocess`)

- **last_reprocess:** `2026-05-30` (consolidação inicial — 4 agentes paralelos, ADR 0231)
- **Como evolui sem quebrar:** [ADR 0236](../../decisions/0236-governanca-evolucao-doc-design.md) (aceito 2026-05-30) — append-only + ratchet + freshness + 3 gatilhos (G1 incremental / G2 reconciliar / G3 total). Workflow executável: skill [`design-memoria-reprocess`](../../../.claude/skills/design-memoria-reprocess/SKILL.md).

### Regras canônicas de governança do DS (toda regra de design mora aqui)

> **Invariante testada** (`tests/Feature/Design/DesignIndexSingleSourceTest.php` inv. c · ADR 0239 R5): toda regra de design canônica é referenciada NESTE índice — **CI falha se faltar**. Ninguém precisa lembrar de pedir.

| ADR | Regra |
|---|---|
| [ADR 0235](../../decisions/0235-ds-v4-accent-roxo-universal.md) | Cor canon — tokens + `primary` roxo `oklch(0.55 0.15 295)` universal; Claude Design plugin dono da interface (âncora de cor do DS v6) |
| [ADR 0249](../../decisions/0249-ds-v6-naming-amends-0235.md) | **DS v6** — nome canônico único da camada de tokens semânticos; amends 0235 (roxo permanece); resolve divergência v4×v5×v6 |
| [ADR 0236](../../decisions/0236-governanca-evolucao-doc-design.md) | Evolução da doc de design — append-only + índice fonte-única + ratchet + freshness + reprocesso |
| [ADR 0238](../../decisions/0238-soberania-constituicao-wagner.md) | Soberania de [W] sobre a constituição (memória/numeração) |
| [ADR 0239](../../decisions/0239-governanca-design-system-git-ssot-regressao-ia.md) | Governança do DS — git SSOT · Cowork→Code · regressão-IA · 1 spec vigente na raiz |
| [ADR 0243](../../decisions/0243-processo-memoria-evolucao-design-cowork.md) | Processo de memória/evolução de design do Cowork — loop medido/auto-corretivo (3 planos · anéis · DS-GUARD · bateria · benchmark · gatilho); método em `prototipo-ui/PROCESSO_MEMORIA_CC.md` |

### Changelog
| Data | Gatilho | Mudança |
|---|---|---|
| 2026-05-30 | G3 (inicial) | Índice criado: 88 docs revisados, regra de ouro, conflitos reconciliados, positivo+negativo. |
| 2026-05-30 | G1 | Ledger de pedidos **file-based** (scaffold `governance/design-requests/`) + correção canon "Claude Design = só arquivos, nunca MCP" ([feedback](../../reference/feedback-claude-design-so-arquivos.md)). |
| 2026-05-30 | G1 | **Regras de governança do DS** (0235/0236/0238/0239) referenciadas + invariante (c) no `DesignIndexSingleSourceTest` — "toda regra de design mora no índice" (ADR 0239 R5). |
| 2026-06-02 | G1 | **ADR 0243** (processo de memória/evolução de design do Cowork) referenciado — ratifica `PROCESSO_MEMORIA_CC.md` como método canônico (handoff `ALwoVssQOY` · PR #2106). |
