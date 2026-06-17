# COWORK_NOTES.md — INBOX: Wagner → Claude Design

> 🧊 **CONGELADA pra NOVOS itens** (Onda B · proposta #2874 · [W] aprovou 2026-06-16 · ADR a numerar por [W]). Intake novo de pedido/tarefa → **GitHub Issue** (form `cowork-intake` · label/assignee/notificação/link-pro-PR) ou drop em `cowork-inbox/`. **Não adicionar item novo aqui.** Os itens ativos abaixo continuam drenando pelo gate `handoff:check` até pousarem (`CODE_NOTES.md@main`); quando o último pousar, este arquivo vira histórico. Motivo: a fila markdown apodreceu (órfãos + refs mortas) e o nativo faz intake/notificação/link melhor. Reversível.

> Wagner escreve aqui. Claude Design ([CD]/[CC]) lê aqui pra produzir protótipo + crítica.
> **Append-only.** Não edita pedidos antigos — adiciona resposta abaixo.
> Formato em [PROTOCOL.md §4](PROTOCOL.md).

> ⚠️ **LEITURA OBRIGATÓRIA antes de qualquer F3:** [`LICOES_F3_FINANCEIRO_REJEITADO.md`](LICOES_F3_FINANCEIRO_REJEITADO.md) — 6 meta-anti-padrões + 15 técnicos + pré-flight checklist + tabela convenções pt-BR. Sessão 2026-05-09 batch Financeiro rejeitado pré-merge. Aplicar literalmente em Estoque/Vendas/RH/Suprimentos/Crédito.

> 🌱 **RAIZ DO MÉTODO (anti-regressão · always-read):** [`PROCESSO_MEMORIA_CC.md`](PROCESSO_MEMORIA_CC.md) — como a memória de design evolui sem regredir (3 planos · anéis 🔍Avaliar→🧪Testar→✅Adotar→⛔Descartar · §5 REGRESSÕES PROIBIDAS · DS-GUARD §8 · Bateria §9 · Benchmark §11). Ler no início de todo chat junto com `STATUS.md` + [`memory/LICOES_CC.md`](../memory/LICOES_CC.md) (L-01..L-25). Guards rodáveis: `node prototipo-ui/ds-guard.mjs <arquivos tocados>` · `node prototipo-ui/integrity-check.mjs`. _REGRESSÃO É INACEITÁVEL._

---

## 🟢 Handoffs ATIVOS — acima da linha d'água

> Fila viva lida pelo gate `npm run handoff:check` (catraca · [`PROCESSO_MEMORIA_CC.md`](PROCESSO_MEMORIA_CC.md) §16 · IT8). Cada item ativo **cita** seu `PROMPT_PARA_CODE_<slug>.md` (que precisa existir em `prototipo-ui/`). Item que **pousou** (`CODE_NOTES.md@main` confirma) **desce** pra baixo da linha d'água — vira histórico, ignorado pelo gate. Lei: sem órfão · prompt auto-contido (sem URL efêmera) · ondas (1 arquivo = 1 PR).

- **DS Roadmap até `ds/*=0`** — fila multi-onda ([`STATUS.md`](STATUS.md) "[PRONTO p/ disparar]"). Entrada: [`PROMPT_PARA_CODE_DS-ROADMAP-ATE-ZERO.md`](PROMPT_PARA_CODE_DS-ROADMAP-ATE-ZERO.md). Sub-filas: [`PROMPT_PARA_CODE_PR-C-WORKLIST.md`](PROMPT_PARA_CODE_PR-C-WORKLIST.md) (módulos · Sells lidera) · [`PROMPT_PARA_CODE_SWEEP-arbitrary-color.md`](PROMPT_PARA_CODE_SWEEP-arbitrary-color.md) · [`PROMPT_PARA_CODE_LOTE-BADGE.md`](PROMPT_PARA_CODE_LOTE-BADGE.md) · [`PROMPT_PARA_CODE_SWEEP-formsection.md`](PROMPT_PARA_CODE_SWEEP-formsection.md).

<!-- LINHA-DAGUA-HANDOFF · acima = ATIVO (o gate vigia) · abaixo = processado/histórico (ignorado) · regra em PROCESSO_MEMORIA_CC.md §16 -->

---

## 2026-05-09 — Setup inicial: 3 perguntas pra Claude Design responder

Wagner [W] e Claude Code [CL] precisam que **Claude Design** (Cowork ou plugin) responda 3 perguntas que vão calibrar o protocolo. Resposta vai voltar pra nós dois (W + CL) lendo este arquivo abaixo.

### Pergunta 1 — Trigger de F3 (CODE)

Quando o protótipo está em F2 aprovado, quem dispara F3?

- **(a)** Manual: Wagner cita `prototipo-ui/<tela>/` em prompt explícito pro Claude Code (mais controle, menos automação)
- **(b)** Automático: hook `SessionStart` do Claude Code lê `HANDOFF.md` e oferece "tem F3 pendente em X, processo?" (menos fricção, mais surpresa potencial)
- **(c)** Outro fluxo (descreva)

**Recomendação Claude Design:**

**(a) Manual.**

Razão: Cowork e Code não compartilham estado em tempo real — Wagner é o "carteiro". Hook `SessionStart` lendo `HANDOFF.md` cria expectativa de reatividade que pode falhar silenciosamente (Code abre sessão sem zip novo extraído ainda, ou com zip antigo). Manual mantém o gate F2 (screenshot approval) como ato consciente: Wagner aprova → Wagner cita protótipo no prompt → F3 começa. Sem ambiguidade sobre "quem disparou".

Caminho de evolução: depois de 5 telas mergeadas com fluxo manual saudável, considerar (b) como conveniência opcional via flag `--auto-handoff`.

---

### Pergunta 2 — Override do gate visual

Wagner pode autorizar exceção pulando F1.5 critique (`/design-override`), F2 screenshot (`/screenshot-override`), F3.5 a11y (`/a11y-override`). Cada uso vira ADR `lifecycle: historical`.

- **(a)** Sim, criar 3 overrides independentes (proposto)
- **(b)** Não permitir override — disciplina dura, força loop completo sempre
- **(c)** Override único `/design-override` que pula tudo (mais simples mas menos granular)

**Recomendação Claude Design:**

**(a) 3 overrides independentes.**

Razão: granularidade espelha o custo de cada gate. F1.5 critique é barato (Claude roda em segundos), F2 screenshot exige Wagner síncrono, F3.5 a11y exige teste real. Override único agrupa coisas com risco assimétrico — pular a11y de tela cliente-facing é qualitativamente diferente de pular critique de copy-fix. ADR `lifecycle: historical` por uso já cuida da disciplina (auditoria).

Adendo: padronizar formato `/X-override <razão> --tela=<nome>` pra `php artisan jana:health-check` rastrear uso por tela.

---

### Pergunta 3 — ADR nova vs emend ADR 0109

O loop formalizado precisa de uma ADR documentando o protocolo, papéis, fases.

- **(a)** ADR 0114 nova (proposto) — é decisão arquitetural diferente: protocolo de loop, não plugin
- **(b)** Emendar ADR 0109 (que introduziu Claude Design plugin) — economia de ADRs, mas mistura "plugin" com "protocolo de uso"
- **(c)** ADR 0114 + ADR 0109 emendada simultaneamente — complementares

**Recomendação Claude Design:**

**(a) ADR 0114 nova.**

Razão: ADR 0109 introduziu plugin Claude Design (capability). ADR 0114 formaliza protocolo de uso (process). Misturar plugin+protocolo em um ADR cria documento bicéfalo difícil de superseder isoladamente — se um dia trocarmos o plugin (ex: Vercel v0), o protocolo continua válido. Manter separado preserva substituibilidade.

---

### Perguntas extras que podem ajudar (Claude Design responde se quiser)

- **Tokens visuais:** os tokens canônicos em [CLAUDE_DESIGN_BRIEFING.md §4](CLAUDE_DESIGN_BRIEFING.md) são suficientes ou faltam tokens (radius, animação, foco)?
- **Critique score:** escala 0-100 ok? Limites 80/70 ok? Ou prefere outro framework (1-5 estrelas, P0/P1/P2)?
- **Comparáveis preferenciais:** Linear/Mercury/Stripe/Vercel/Notion são as referências ou tem outras melhores pro vertical "ERP comunicação visual"?
- **Desktop vs mobile:** prioridade desktop primeiro (Larissa 1280px) e mobile-first só pra Repair (técnico chão de fábrica). Faz sentido?
- **Frequência de critique:** roda em todo protótipo ou só nos com score < 80 já é suficiente?

**Resposta Claude Design:**

**Tokens visuais:** suficientes pra start, mas faltam 3 — patch já aplicado no `CLAUDE_DESIGN_BRIEFING.md §4`:
- **Radius:** `rounded-md` (6px) cards/botões, `rounded-lg` (8px) drawers/dialogs, `rounded-full` apenas badges/avatars. Sem `rounded-xl+` (vira "tutorial-shadcn").
- **Animação:** `transition-colors duration-150` hovers, `transition-transform duration-200 ease-out` drawers. Sem animação >300ms exceto loading skeletons.
- **Foco:** `focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2`. Obrigatório em interativos — Larissa usa teclado pesado.

**Critique score:** escala 0-100 com limites 80/70 ok pra MVP. Após 10 telas, recalibrar com p50/p90 dos scores reais. Se p50 ficar em 90, mover bar pra 85/75.

**Comparáveis preferenciais:** Linear/Mercury/Stripe/Vercel/Notion cobrem 80%. Patch aplicado em `GLOSSARY.md` adicionando: Front (inbox 3-col), Pylon (B2B support → Repair), Attio (CRM enxuto → Cliente/Index), Cron (densidade temporal → Ponto/Marcacoes). Excluir Notion pra telas Larissa — densidade insuficiente.

**Desktop vs mobile:** correto. Larissa 1280px = canon. Repair = mobile-first não-negociável (técnico chão de fábrica). Site público P3 = mobile-first também (lead vem de WhatsApp/Insta). Resto = desktop-first com breakpoint `sm:` que não quebra.

**Frequência critique:** rodar em **todo** protótipo F1, não filtrar por threshold. Razão: não temos baseline ainda, score baixo é diagnóstico ANTES de virar problema. Após 20 telas, considerar skip-when score histórico do mesmo módulo > 85.

---

## Pedidos pendentes pra protótipo

## 2026-05-31 [CC] → [CL] · PROPOSTA §10.4 — Otimizar as ROTINAS de design (G1–G6) + F0

**Tipo:** processo+governança (não é tela). **Conteúdo verbatim:** [`COWORK_NOTES.amendment-otimizar-rotinas-design-2026-05-31.md`](COWORK_NOTES.amendment-otimizar-rotinas-design-2026-05-31.md) (salvo no git — URL expira ~1h).

**Processado por [CL] 2026-05-31, gate §10.4 contra `origin/main` — veredito: PASSA, mas a maioria dos G já está feita/superada:**
- ✅ **F0 entregue** — mapa em [`AUDITORIA_ROTINAS_DESIGN.md`](AUDITORIA_ROTINAS_DESIGN.md).
- `ds:report` (G4) **já existe no main** · canais já reancorados (05-30) · loop **0-humano** (ADR 0241) **supera o G3** · `REGRAS_DS_LINT`+ESLint `ds/*` já existem (G5 metade DONE, ADR 0209) · `REGRAS_STYLELINT_CSS.md` **não existe** (G5-`.css` é trabalho novo).
- 🆕 **Achado novo (não coberto pela fila §10.4 das 07:00):** são **6 motores de score**, em 2 camadas — cara/LLM `design:*` **DORMANTE** (`mwart-comparative` último 05-17 · `design-deep-analysis` **0 disparos** · `a11y-report.md` nunca) vs barata/estática **VIVA** (`screen-grade` 222 telas · `module:grade` · `ds/*`). PROTOCOL descreve só a dormante.
- ⏸️ **Sobra real:** **G1/G2/G6** (consolidar motores — religar `mwart-comparative` como aprofundamento sob demanda do `screen-grade`, não skill nova) + **G5-`.css`**. Ordem = decisão [W].

## 2026-06-09 [CC] → [CL] · EVAL-001 — fundação de evals de comportamento dos agentes

**[PROCESSADO]** EVAL-001 — PR #2478 aguardando merge [W]. Ondas 2 e 3 descritas em [`prototipo-ui/evals/EVAL_PROTOCOL.md`](evals/EVAL_PROTOCOL.md) (EVAL-002 = outcome metrics + rubrica [W]; EVAL-003 = red-team + US-GOV-013).

Processado por [CL] 2026-06-09, gate §10.4 contra `origin/main` (Passo 0: worktree fresco, `0 0` ahead). Entregue: `prototipo-ui/evals/{EVAL_PROTOCOL,GOLDEN_SET,REPLAY_CASES,AUTONOMY_LADDER}.md` + `results/.gitkeep` + ADR **0266** (status proposto, próximo livre após 0265). **Classe A1** pela própria AUTONOMY_LADDER → **NÃO merge autônomo**: o merge de [W] é o ato de congelamento do golden set.

## 2026-05-09 16:45 [W] → [CC]

### Tela: Jana/Chat
### Prioridade: P2
### URL atual: https://oimpresso.com/jana
### Charter existente: [resources/js/Pages/Jana/Chat.charter.md](../resources/js/Pages/Jana/Chat.charter.md)
### Persona principal: Wagner + Larissa + Eliana (multi-persona — todo mundo conversa com Jana)

### Contexto (estado F0 hoje):
Tela funcional mas em estado legacy visual. Auditoria 2026-05-09 com Wagner sobre screenshot real de prod identificou 7 problemas concretos. Tela passa por F1.5 visual gate agora pra alcançar gold-standard (Sells/Index nível). Comparáveis canônicos do charter: **Linear Inbox** (lista de conversas com hierarquia + grouping) + **Front** (3-col com avatar+subject+preview+tempo). **Excluir Notion AI e Intercom** — Notion tem densidade insuficiente pra Larissa, Intercom é support, não copilot interno.

### Pedidos (7 problemas detectados):

- [ ] **Topnav estourou em 2 linhas + "Apps Vinculados" cortado.** Hoje tem 9-10 itens (Conversar / Dashboard / Metas / Alertas / Governança MCP / KB / Qualidade IA / Team MCP / Plataforma / Apps Vinculados). Charter pede ≤6 abas. Mover "Governança MCP", "Team MCP", "Qualidade IA", "Plataforma" pra menu **"⚙️ Avançado"** ou pra área `/copiloto/admin/*`. Manter primary: **Conversar / Dashboard / Metas / Alertas / KB / Apps Vinculados**.

- [ ] **Empty state empurrado pra ~⅔ da tela.** Header "Nova conversa | Assistente IA · Jana" colado no topo com ~400px de branco vertical antes do "PERGUNTE SOBRE". Centralizar empty state verticalmente OU reduzir gap header→suggestions pra ~80px max.

- [ ] **4 chips de sugestão estreitos forçam quebra feia** ("Qual o\nfaturamento de\nhoje?"). Trocar pra **2×2 grid** com cards mais largos (cada card respira em 1 linha de copy + 1 linha de preview do que vai responder). Alternativa: linha única scrollável horizontal com chevron.

- [ ] **Lista de recentes sem hierarquia.** Hoje 3× "Nova conversa" idênticas, sem timestamp/preview/avatar. Adicionar por linha: **subject auto-gerado da 1ª pergunta** (ex: "Faturamento hoje" em vez de "Nova conversa") + **preview da última msg da Jana** (1 linha truncada) + **timestamp relativo** ("2h atrás", "ontem"). Linear Inbox e Front são referência.

- [ ] **"Smoke PII redact 2026-05-06" e "Smoke test 2026-05-06" vazaram pra UI de prod.** Filtrar por label `internal:test` OU criar regra "conversas iniciadas via Pest/dev session não aparecem em RECENTES default" (toggle "mostrar testes" pra Wagner debug).

- [ ] **Header com ícones `(i)` e `(...)` soltos** sem affordance. Adicionar tooltips PT-BR ("Detalhes da conversa" / "Mais ações") + **trocar avatar "CP" amarelo genérico** por identidade Jana coerente (proposta: gradient roxo `from-violet-500 to-purple-600` + sigla "J" — alinhado com brand IA do projeto).

- [ ] **Botão enviar azul redondo destoa da paleta neutra Tailwind.** Trocar pra `bg-primary` token (ou `bg-zinc-900` consistente com resto do AppShell). Manter shape (redondo é ok no padrão de chat IA — ChatGPT/Claude.ai/Gemini todos usam).

### Inspirações (links):
- <https://linear.app/inbox> — grouping + preview + tempo relativo
- <https://front.com/product> — 3-col com avatar+subject+preview
- <https://claude.ai> — empty state minimal centralizado, suggestions chips em 2×2

### Restrições:
- **multi-tenant:** business_id scopado em toda query de conversas/mensagens (Tier 0 obrigatório, ADR 0093)
- **PII sanitization:** suggestions chips e previews NUNCA mostram CPF/CNPJ raw — passar por `PiiRedactor` (memória cliente "Smoke PII redact" indica esse cuidado já existe)
- **mobile:** desktop-first (Wagner+Eliana usam desktop pesado), mas não pode quebrar em `sm:` (Larissa às vezes consulta no celular do balcão)
- **monitor 1280px Larissa:** crítico — testar protótipo em viewport 1280×800

### Não-fazer:
- ❌ Não usar `rounded-xl+` em cards (vira "tutorial-shadcn", briefing §4)
- ❌ Não adicionar animações >300ms (briefing §4)
- ❌ Não copiar Notion AI (densidade insuficiente Larissa) nem Intercom (é support, não copilot)
- ❌ Não criar mais 1 sidebar interna na tela — já tem AppShell + lista conversas, terceira coluna seria 4-col total = Larissa 1280px quebra
- ❌ Não inventar feature nova (busca avançada, exports, sharing) — escopo é F1 visual fix dos 7 pontos, não growth scope

---

### Template (copiar e preencher):

```markdown
## YYYY-MM-DD HH:MM [W] → [CC]

### Tela: <Modulo/Tela>
### Prioridade: P0|P1|P2|P3
### URL atual (se existe): https://oimpresso.com/<rota>
### Charter existente: <link ou "criar">
### Persona principal: <Larissa | Wagner | Técnico | Eliana | Iniciante>

### Contexto (2-3 frases):
<por que essa tela importa, o que está quebrado/faltando>

### Pedidos:
- [ ] visual X
- [ ] interação Y
- [ ] copy Z

### Inspirações (links):
- <https://...>
- <https://...>

### Restrições:
- multi-tenant: business_id scopado
- mobile: <obrigatório | desktop-first | irrelevante>
- monitor 1280px Larissa: <crítico | irrelevante>

### Não-fazer:
- <coisas que NÃO devem aparecer>
```

---

## [W → CC] Amendment ao pedido #316 — Jana/Chat avatar (2026-05-09)

**Quem:** Wagner [W], auto-correção pré-F1 após cross-check com `resources/js/Pages/Jana/Chat.charter.md`.

**Motivação:** Item 6 do pedido #316 ("avatar Jana — gradient roxo `from-violet-500 to-purple-600` + sigla 'J'") viola anti-pattern UX explícito do charter:

> ❌ **Avatar circular emoji-style.** Canônico = letra/glyph monocromático em quadrado `rounded-md`.

Gradient duas-cores não é monocromático → cai direto no anti-pattern. Pego antes de [CC] consumir o pedido falho na F1, pra evitar retrabalho no F1.5 do [CD].

### Correção do item 6 (substitui a especificação anterior)

**Avatar Jana:**
- **Forma:** quadrado `rounded-md` (≤8px radius — segue token Cockpit V2, **não** circular)
- **Conteúdo:** sigla `J` monocromática, peso 600, centralizada
- **Background:** `bg-primary` (token Cockpit V2 — laranja Oimpresso resolvido pelo tema ativo)
- **Foreground:** `text-primary-foreground`
- **Tamanho header:** 32×32px (mesma altura visual do search/composer trigger)
- **Tamanho lista recents:** 28×28px
- **Sem gradient. Sem ícone. Sem emoji. Sem borda.**

Fallback aceito se `bg-primary` ficar pesado em densidade alta da lista de conversas: `bg-zinc-900 text-zinc-50` (também monocromático, segue charter).

### Demais itens do pedido #316 — confirmados sem mudança

| # | Item | Status vs charter |
|---|---|---|
| 1 | Topnav ≤6 abas | ✅ Não-explícito no charter, mas Cockpit V2 + UX target "1280px sem scroll" implicam |
| 2 | Empty state centralizado | ✅ Gap visual real, charter não opina contra |
| 3 | Chips 2×2 | ⚠️ Charter não menciona, mas alinhado com referência "Linear Inbox densidade" — manter |
| 4 | Recents subject + preview + timestamp | ✅ Referência Linear Inbox citada explicitamente no charter §Comparáveis |
| 5 | Filtrar smoke tests | ✅ Operacional, sem conflito |
| 6 | Avatar Jana | ❌ → ✅ corrigido neste amendment |
| 7 | Composer paleta neutra | ✅ Charter não menciona, Cockpit V2 implica (mesmo argumento item 1) |

### Próxima ação

[CC] consome o pedido **#316 + este amendment como par**, ignora a especificação original do item 6, gera F1 (`prototipos/jana-chat/page.tsx`) seguindo a versão monocromática rounded-md.

[CD] no F1.5 valida que o protótipo respeita o anti-pattern do charter — score perde ≥10 pontos se reaparecer gradient ou círculo.

---

## [W → CC] Amendment ao pedido #316 — block renderer + vocabulário IA (2026-05-14)

**Quem:** Wagner [W] + Claude Code [CL] (esta sessão), revisão pós-handoff `Oimpresso-handoff.zip` (Claude Design export 2026-05-14).

**Motivação:** `chat.jsx` exportado implementa chat WhatsApp-style multi-purpose (atendimento + OS + equipe + cliente) — não é chat IA da Jana. Atendimento humano já vive em `Modules/Whatsapp/` (omnichannel oficial ADR 0096 + 0135). **Nota: 24/100** vs Glean/ChatGPT Enterprise/Copilot 2026 — **0/6 P0** charter fechados.

**Documento completo:** [`COWORK_NOTES.amendment-jana-chat-block-renderer.md`](COWORK_NOTES.amendment-jana-chat-block-renderer.md) — 19 divergências catalogadas + correção formal item-por-item + critério F1.5 ≥80.

### Resumo executivo das 3 mudanças P0

1. **Trocar 5 anti-patterns explícitos do charter** — avatar gradient circular, typing 3-dots loop, bubble WhatsApp-ish, mock response humano, `setTimeout` no lugar de streaming.
2. **Limpar vocabulário humano vazado** — remover read receipts `✓✓`, botão "Ligar", online dot, tabs `OS/Equipes/Clientes` (substituir por `Todas/Minhas/Compartilhadas/Arquivadas`).
3. **Adicionar 6 features IA que definem o produto** — 4 kinds de bubble tipados (`markdown` / `tool_use` / `data_table` / `action_card`), citations inline `[1][2]`, suggested prompts no empty state, PII warning composer, atalhos `/` `J/K` `Esc`, chip business atual no header.

### Próxima ação

[CC] consome o trio **#316 + amendment-avatar (2026-05-09) + este amendment (2026-05-14)** como pacote, gera **V2 do protótipo** em `prototipo-ui/prototipos/chat/`. F3 (Chat.tsx) bloqueado até F1.5 ≥80.

[CD] no F1.5 valida bloco A/B zero violações + bloco C 4 kinds funcionando — score perde ≥15 por anti-pattern reaparecido, ≥10 por kind IA ausente.

---

## [W → CC] Amendment Cockpit V2.1 — fechar 8 refinos pra F1.5 ≥80 (2026-05-15)

**Quem:** Wagner [W] + Claude Code [CL], pós CRITIQUE 78/100 do `chat-jana.jsx` (export 2026-05-15) + decisão Wagner caminho A (pivot Cowork aceito).

**Motivação:** Cowork pivotou no export 2026-05-15 — entregou `chat-jana.jsx` (Cockpit do Analista IA · brief diário + KPIs + análises + ações HITL · com aba IA single-thread) em vez de V2 do chat 2-col que o amendment-block-renderer pedia. **Pivot é correto**: Caixa Unificada V4 (`/atendimento/caixa-unificada`) já cumpre paradigma 2-col humano omnichannel — refazer outro 2-col em `/jana/` duplicaria conceito. Charter [`Cockpit.charter.md`](../resources/js/Pages/Jana/Cockpit.charter.md) (NOVO 2026-05-15) fixa destino. F1.5 score interim **78/100** — gate ≥80, 1 round refator necessário.

**Documento completo:** [`COWORK_NOTES.amendment-cockpit-v2.1-refinos.md`](COWORK_NOTES.amendment-cockpit-v2.1-refinos.md) — 8 refinos com snippets executáveis (CSS + JSX + JS) + critério F1.5 ponderado por categoria + checklist itens INALTERADOS (não rebobinar dashboard).

### Resumo executivo dos 8 refinos (~3-4h Cowork)

1. **A1** — `JanaAvatar` quadrado mono primary letra "J" (substitui gradient + emoji 🤖)
2. **A3** — bubbles `rounded-md` simétricos (remove tail asimétrico WhatsApp-style)
3. **A5+A2** — `mock-stream.js` SSE fake + chip "Jana está pensando" (resolve typing automático)
4. **B7** — keydown listener `/` `J/K` `Esc` filtrado por focus
5. **C1** — switch 4 kinds bubble (`markdown` + `tool_use` + `data_table` + `action_card`) + 4 componentes
6. **C2** — citations inline `[1]` clicáveis (parser regex no markdown)
7. **C4** — PII detector regex CPF/CNPJ/cartão no composer + chip amber
8. **C7** — markdown render mais robusto (cobre bold/italic/link/code · documenta troca por react-markdown na F3)

### Próxima ação

[CC] aplica os 8 refinos no `chat-jana.jsx` + `chat-jana.css` + cria `mock-stream.js`. Não toca dashboard tab (lista §3 INALTERADOS no doc completo). Score esperado pós-refator: **~90/100**.

[CD] no F1.5 valida zero violações Bloco A + 4 kinds funcionando + streaming + PII chip + atalhos. Itens dashboard INALTERADOS (regressão zero).

[W2] aprovação F2 com checklist visual (10 itens · doc §5).

[CL] F3 só depois F2 — substitui `Cockpit.tsx` in-place + sub-componentes em `Pages/Jana/_components/Cockpit/` + 7 Pest GUARDs (R-JANA-COCKPIT-001..007 spec no charter).


---

### 2026-05-18 — Vendas + Financeiro · método KB-9.75 completo

**Branch:** `feat/vendas-financeiro-kb-9.75`
**PR:** #1064
**Score:** Vendas 9,75/10 · Financeiro 9,75/10
**Reuso:** ~70% dos componentes do Financeiro vieram do Vendas

**Pra Code:**
1. Mergear o PR após screenshot approval (Wagner)
2. Atualizar `SYNC_LOG.md` apontando este sync como v2 da aplicação do método
3. Atualizar `TELAS_REVIEW_QUEUE.md`: marcar Sells/Index, Sells/Create e Financeiro/Unified como **done · A+** (≥9,5)

**Próximas aplicações sugeridas:**
- CRM (persona Bruna · score atual ~6,5)
- Compras (persona Wagner+Bruna · liga com Financeiro já feito)
- Equipe / Chat interno (persona times · multi-canal)

[PROCESSADO 2026-05-18]

---

## 2026-05-19 [W] → [CC] · F0 batch PaymentGateway UI

**Tipo:** F0 batch novo (§7 PROTOCOL.md). 3 telas relacionadas em UM pedido único.

**Disparado por:** ADR 0170 PaymentGateway (Onda 0 docs mergeada — PR #1123, originalmente proposto como 0144 pelo Cowork mas renumerado por colisão com `0144-tasks-db-canonico-spec-template.md` existente).

**3 telas:**

1. **`Financeiro/Cobranca/Index`** — rename + expansão de `/financeiro/boletos` · **P0**
   - Adiciona filtros tipo (boleto/PIX/cob/cobv/recv/card) + gateway (Inter/C6/Asaas/BCB Pix) + origem (sale/invoice/subscription_license/avulsa)
   - Drawer condicional por tipo (linha digitável vs BR Code QR vs mandato vs cartão)
   - Botão "Emitir cobrança" Sheet 4 steps
   - 301 redirect `/financeiro/boletos` → `/financeiro/cobranca` por 60d
2. **`Settings/PaymentGateways/Index`** — nova tela CRUD credenciais por gateway · **P1**
   - Lista + ativar/desativar + healthCheck button + último ping latency
   - Sheet config per gateway (Inter mTLS / Asaas API key / C6 OAuth / BCB Pix certificado)
   - Substitui edição inline em `boleto-contas-app.jsx` linhas 668-826 (SheetConfigInter atual)
3. **`Sells/Index drawer + botão "Emitir cobrança"`** — incremento cirúrgico KB-9.75 · **P0**
   - Botão "Emitir cobrança" no drawer SaleSheet (após "Imprimir" e "Estornar")
   - Sheet emissor reusa Step 2-4 da Tela 1 (tipo já definido pela venda); idempotency_key=`sale:{id}`
   - Atalho `C` no drawer foca botão

**Pedido completo:** [`COWORK_NOTES.amendment-paymentgateway-batch.md`](COWORK_NOTES.amendment-paymentgateway-batch.md) — 229 linhas, decisões Wagner F0 vinculadas, contexto canibalização tela-a-tela.

**Decisões Wagner F0 vinculadas:**
1. PIX Automático = driver BCB direto (Resolução BCB 380/2024)
2. Subscription Superadmin vira projection — Wagner cobra tenants via Plan em RB no `business_id=1` (dogfooding Onda 5 — POSTERGADA Wagner 2026-05-19: rastrear dependências Connector/Officeimpresso/permissões pacote antes de decidir)
3. PaymentGateway pode substituir PesaPal (vestigial deprecated Onda 5/6)
4. Módulo separado por tamanho + ligação cross-module

**Vinculado ADR:** [0170 PaymentGateway](../memory/decisions/0170-paymentgateway-extracao-camada-cobranca.md) (canon canônico — não 0144 como original Cowork sugeriu).

**Backend já entregue (Code) 2026-05-19:**
- Onda 0 docs · PR #1123
- Onda 1 esqueleto módulo · PR #1125
- Onda 2 DB+Models · PR #1126
- Onda 2.5 backfill artisan dry-run · PR #1127
- Onda 3 webhooks endpoints (shadow, sem cutover) · PR #1128
- Onda 4a InterDriver real (Http::fake) · PR #1130

**F3 UI = parte da Onda 4 backend** — PaymentGatewayController + CobrancaController + Pages Inertia/React virão depois de F2 (screenshot Wagner aprova). Sequência travada conforme chat11.md Cowork.


---

## [2026-06-09] F0 — Fila V2 do drawer de OS (RichSheet) · pedido [W] via Cowork

> Origem: avaliação F1.5 `prototipo-ui/AVALIACAO_OS_GIT_2026-06-09.md` + conferência pós-merge
> #2477. O drawer `ServiceOrderRichSheet` já espelha o protótipo canon nas seções principais
> (MercosulPlate, KV hero, Peças & Mão de obra, FSM, timeline de reparo, footer 3 ações).
> Os 4 itens abaixo são os gaps V2 declarados no próprio código — [W] autorizou virarem fila.
> Ordem de prioridade definida por impacto no balcão do Martinho (biz=164 LIVE).

### OS-V2-1 · Fotos & Laudo reais no drawer — P1
- **Hoje:** grid 3 placeholders hachurados + botão "Adicionar foto" disabled.
- **Quero:** upload real (câmera no celular do mecânico + arquivo no desktop) via
  Modules/Arquivos; thumbnails no grid; lightbox ao clicar; foto vira anexo da OS
  e sai no print A4 (seção opcional).
- **Persona:** Técnico Repair (tablet/celular, mãos sujas — touch ≥44px, fluxo de 1 toque).
- **F1:** [CC] protótipo do estado preenchido + vazio + uploading no drawer canon.

### OS-V2-2 · DVI inline com severidade — P1
- **Hoje:** drawer não tem o checklist DVI; protótipo canon tem itens com severidade
  ok/atenção/crítico e valor por item.
- **Quero:** seção DVI no drawer: lista de inspeção com badge de severidade, valor
  sugerido por item crítico/atenção, e o subtotal alimentando o gate de aprovação.
- **Depende:** endpoint DVI por OS (DviInspectionController já existe — verificar shape).
- **F1:** [CC] já tem o padrão visual no protótipo `producao-oficina` — extrair pro drawer.

### OS-V2-3 · Gate "Pedir aprovação" hero no drawer — P2
- **Hoje:** ApprovalGateCard existe no fluxo mas discreto; protótipo canon tem barra
  preta "Total recomendado R$ X" + CTA primário "Pedir aprovação" (WhatsApp).
- **Quero:** quando a OS está em diagnóstico com itens recomendados sem aprovação,
  o drawer mostra a barra de total + CTA hero. LGPD: link wa.me sem PII na mensagem.
- **F1:** [CC] portar a barra do protótipo pro vocabulário do DS (token, não preto cru).

### OS-V2-4 · Linha do tempo FSM auditável — P2
- **Hoje:** TimelineSkeleton derivada de entered_at/expected/completed_at.
- **Quero:** timeline real via histórico de transições FSM (quem, quando, de→pra),
  já exposto em endpoint (`fsm/history` previsto no código). Mantém vocabulário de reparo.
- **Valor:** auditoria — Wagner vê quem travou a OS e onde o lead time estoura.

### Residual técnico (não-UI, registrar como chore)
- Backfill `order_type='locacao'|null → 'mecanica'` nas OS legadas (badge "—" na lista).
- Renomear LABELS (não keys) dos estágios FSM `cacamba_locacao` pra vocabulário de reparo.

---

## [2026-06-09] F0/F2 — Drawer de OS: fechamento total (batch 2) · aprovado [W]

> Comparação drawer protótipo × `ServiceOrderRichSheet` real pós-#2482 (~85%).
> [W] deu F2 nos F1 verificados de OS-V2-3/V2-4 e aprovou registrar + executar os 2 gaps novos.
> Ponte F3 única gerada pra [CL] com os 4 itens.

### OS-V2-3 · Gate "Pedir aprovação" com ciclo de estados — P1 · F2 ✓
Barra hero abaixo da DVI: none (escura "Total recomendado"+CTA) → pending (âmbar
"Aguardando · WhatsApp há X" + Cobrar) → approved (verde "Autorizado") | declined
(vermelha "Revisar e reenviar" → reabre). Estado real vem do status da OS /
approval_requested_at / approval_decided_at — sem botões de simulação (demo-only).

### OS-V2-4 · Linha do tempo FSM auditável — P1 (era P2; subiu com F2) · F2 ✓
Substituir TimelineSkeleton pelo fetch do histórico FSM real
(`/oficina-auto/service-orders/{id}/fsm/history` — criar se não existir, lendo
sale_stage_history/equivalente): quem · quando · de→pra com chips de transição.
Eventos de aprovação (enviado/aprovado/recusado) e fotos entram na trilha.

### OS-V2-5 · StageGate — checklist de bloqueio por etapa — P1 · NOVO
Seção "Checklist de etapa" no drawer: requisitos pra avançar a etapa atual
(ex.: Diagnóstico→orçamento exige DVI com ≥1 item e foto; Aguardando aprovação→
execução exige aprovação registrada). Itens com check verde/pendente; botão
"Avançar etapa" desabilitado com tooltip do que falta. Regras data-driven por
transição (config no FSM/seeder), não hardcoded no componente.

### OS-V2-6 · Lançar item inline no drawer — P2 · NOVO
Na seção Peças & Mão de obra: botão "+ Adicionar item" abrindo o
ServiceOrderItemFormSheet (já existe) sem sair do drawer; editar/remover item
existente inline (kebab ou swipe). Total OS atualiza via refetch.

### Residual conhecido (não bloqueia)
- "Observação" vs "Sintoma reportado" — cosmético, campo único no schema.

---

## 2026-06-10 [CL] · PROCESSED PROMPT_PARA_CODE_CAIXA-UNIFICADA-COMPLETA — placar PR-1..10

Mandato [W] "vamos aplicar todas" executado fim-a-fim no dia. Gate §10.4 contra origin/main ANTES de codar mudou 2 PRs: macros backend JA EXISTIA completo (US-WA-048 MacrosController list+apply+variantes → PR-2 reusou, ZERO tabela nova) e o redirect 301 do cutover JA estava no main desde 2026-05-15 (PR-10 virou so charter historical).

| PR | Entrega | Status |
|---|---|---|
| sync | 6 prototipos V2 → prototipo-ui/prototipos/caixa-unificada/ (recria path canon do charter) | MERGED #2504 |
| PR-1 | US-WA-302 assignee picker (PATCH inbox/{id}/assign Tier 0 + availableAssignees) | MERGED #2503 |
| PR-2 | US-WA-303 composer: TemplatePicker reusado por provider + macros "/" autocomplete (backend US-WA-048 reusado) + variaveis nome/telefone/operador com preview | MERGED #2506 |
| PR-3 | US-WA-301 filas DB: ADR 0267 + whatsapp_queues + seed lazy do config + QueuesSheet CRUD + heuristica le DB c/ fallback | MERGED #2507 |
| PR-4 | US-WA-305 queue_override (vence heuristica sem re-tagar; null volta; slug orfao cai no fallback) | MERGED #2509 |
| PR-5 | US-WA-304 ChannelsDrawer in-place (ZERO backend novo — reusa payloads) | MERGED #2511 |
| PR-6 | US-WA-307 + Nova conversa (find-or-create REABRE thread; msg inicial reusa pipeline send) | MERGED #2512 |
| PR-7 | US-WA-306 broadcast FASE 1 (corte previsto no brief): ADR 0268 + whatsapp_broadcasts + contacts.whatsapp_opt_in_at LGPD + pre-flight real + draft auditavel; Disparar=disabled (fase 2 gate [W]) | MERGED #2514 |
| PR-8 | Polish V2 — 7/8: SLA pill, cheat-sheet "?", lightbox, mobile tabs, favoritos LS, transcript print, modo apresentacao. cmd-K = TODO honesto (palette global PMG-002 ja existe; estender = US cross-modulo) | MERGED #2517 |
| PR-9 | IA na thread REAL (validacao pre-PR: laravel/ai + Agents Jana existem): InboxAssistAgent + 3 endpoints (summarize/ask/suggest-reply), PiiRedactor antes do provider, dry_run gateia custo | MERGED #2518 |
| PR-10 | §6 cutover — charter Inbox legacy → deprecated/historical (3 dos 4 itens JA estavam no main) | ABERTO #2513 — GATE [W], NAO mergear sem OK |

Extra (incidente prod ~16:50 BRT, [W] "carregando canais erro 500"): deploy parcial deixou codigo pos-PR-3 sem migrate → queuesAdmin sem guard derrubava o grupo Inertia::defer inteiro. Hotfix #2515 (degrade gracioso, principio duro 8) + #2516 (workflow one-shot debug-caixa-logs.yml read-only). Diagnostico pos-fix: prod trackeia main com migrations aplicadas (whatsapp_queues [176] Ran, tabelas 1/1/1), ZERO production.ERROR no log. LICAO: payload deferred novo SEMPRE nasce com guard (deploy-ordering).

Charter CaixaUnificada/Index.charter.md → v10 (historico PR-a-PR + metricas vivas R-WA-CAIXA-UNIF-001..012). Fase 2 do broadcast (Job rate-limited) e extensao do cmd-K global = proximas US com gate [W].

---

## 2026-06-16 [CL] · PROCESSED PROMPT_PARA_CODE_CAIXA-UNIFICADA-DARK-MODE — placar 4 partes

Brief [CC]: tela aberta no tema escuro → painel da conversa branco + elementos do Contexto sem contraste (mesma classe de bug ja erradicada em Produtos/Oficina/Financeiro: cor clara crua que nao flipa no escuro; a Caixa Unificada foi portada antes da auditoria de escuro). Gate §10.4 contra main (origin/main `5253a776c`, alem do `dd09f96` que o [CC] leu — o repo venceu).

Descoberta-chave que mudou a abordagem do brief: o repo ativa dark via **`<html class="dark">` (Tailwind `.dark`), NAO `[data-theme=dark]`** (inertia.css L97 confirma que data-theme nunca e ativado). Logo a correcao usa os pares de **token semantico que ja flipam** (`warning-soft`/`warning-fg`/`warning`, `card`, `info`) — **ZERO override CSS, ZERO token novo** (conformance/foundation intactos). A propagacao de tema NAO era o furo (o `bg-muted/15` da thread flipa OK no probe); o bug estava so nas FOLHAS com cor clara hardcoded.

| Parte | Entrega | Status |
|---|---|---|
| 1 | `ConversationThreadV4`: bolha inbound `bg-white`→`bg-card` · read-tick `text-blue-600`→`oklch(0.55 0.18 250)` inline (passa R1) · nota interna + banner "em homologacao" + SLA-pill amber → `warning-soft`/`warning-fg`/`warning` · corpo da nota `text-foreground` (contraste nos 2 temas) · bolha verde-WA mantida (proposital) | feito — este PR |
| 2 | `ContextSidebarV4`: chip de Tag aplicada (oklch claro cru) → `bg-warning-soft border-warning/30 text-foreground` (flipa; antes claro-no-claro no escuro) | feito — este PR |
| 3 | `CustomerMemoryBlock` (compartilhado c/ Inbox legacy `ConversationSidebar`): sem Contact CRM **e** sem enriquecimento (reclamacoes/temas/fontes/notas/flags) → colapsa o card grande vazio numa linha ("Sem cadastro de cliente vinculado."), **sem prop nova** (nao regride o legacy) | feito — este PR |
| 4 | Chips todos "em breve": o catalogo do `CaixaUnificadaController::buildAvailableChannelsPayload` so tem `whatsapp_baileys`; o canal LIVE e `whatsapp_whatsmeow` (WuzAPI, substituiu Baileys ADR 0202) → o type ATIVO nunca casa com row do catalogo. Fix = trocar a row morta `whatsapp_baileys` pela canonica `whatsapp_whatsmeow` no catalogo (+ Pest) | **PR #2822** (off origin/main) — row morta trocada; helper Pest + R-WA-CAIXA-UNIF-001 migrados pro type LIVE (mascaravam o bug); novo R-WA-CAIXA-UNIF-013 (regressao); Tier 0 preservado. Aguardando gates/merge. |

Verificacao: probe token-flip standalone (tokens REAIS do inertia.css inlinados + markup pos-fix → headless-chrome screenshot LIGHT|DARK lado-a-lado) confirma flip correto e light intacto; ds-canon/conformance/foundation locais PASS; DS-GUARD limpo. Pest de unidade novo dispensado (dark e CSS/visual — guidance do proprio brief). Smoke vivo em prod fica pro pos-merge (`tela-smoke-pos-merge`).

PARTE 4 (chips de canal) saiu em PR backend proprio **#2822** (branch `fix/caixa-unif-canal-chips-whatsmeow`, off origin/main pos-#2818): `buildAvailableChannelsPayload` cataloga `whatsapp_whatsmeow` (LIVE, ADR 0204) no lugar da row morta `whatsapp_baileys` (ADR 0202) — o chip do canal vivo volta a 'ativo' com count real e `?channel=whatsapp_whatsmeow` filtra via whereHas channel.type. Raiz do mascaramento: o helper Pest `cuctMakeChannel` e o R-WA-CAIXA-UNIF-001 seedavam o type morto, deixando o teste verde contra um type que nunca existe em dado real — migrados pro LIVE; novo R-WA-CAIXA-UNIF-013 (regressao). Charter v12. Fecha as 4 partes do brief (PARTEs 1-3 dark via #2818).
