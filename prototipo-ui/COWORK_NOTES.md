# COWORK_NOTES.md — INBOX: Wagner → Claude Design

> Wagner escreve aqui. Claude Design ([CD]/[CC]) lê aqui pra produzir protótipo + crítica.
> **Append-only.** Não edita pedidos antigos — adiciona resposta abaixo.
> Formato em [PROTOCOL.md §4](PROTOCOL.md).

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

## 2026-05-09 17:10 [CL] → [CC] — AMENDMENT ao pedido de 16:45

> Append-only. Não edita o pedido original — corrige item 6 que viola o próprio charter `Chat.charter.md` que mergeamos junto.

### Item 6 — correção (avatar Jana)

**Pedido original (16:45) propôs:** *"trocar avatar 'CP' amarelo genérico por identidade Jana coerente (proposta: gradient roxo `from-violet-500 to-purple-600` + sigla 'J')"*.

**Conflito detectado** — charter `Chat.charter.md` §UX Anti-patterns explicitamente proíbe:

> ❌ Avatar circular emoji-style (canon = **letra/glyph monocromático em quadrado `rounded-md`**)

Gradient duas-cores não é monocromático. A proposta original cai no anti-pattern.

**Correção alinhada com charter:**

- Sigla **"J"** em **quadrado `rounded-md`** (não circular, não gradient)
- Cor **monocromática via token Cockpit V2** — `bg-primary text-primary-foreground` (alinha com tokens canônicos do AppShell) OU `bg-zinc-900 text-zinc-50` se primary já está sobrecarregado em outro componente da tela
- Tipografia: 13-14px conforme ADR 0110 (sidebar item / chip), peso `font-medium`
- Sem ring/glow/sombra além do default Cockpit V2

[CC]: ignorar a sugestão "gradient roxo" do pedido original. Seguir o canon do charter.

### Itens 1-5 e 7 — sem alteração

Continuam válidos como escritos. Releitura cruzada com charter confirmou alinhamento:
- Topnav ≤6 abas → consistente com Cockpit V2 (charter Goal "AppShellV2 + topnav inline")
- Empty state centralizado → não opinado pelo charter, gap visual real
- Chips 2×2 → não opinado pelo charter (charter foca em thread ativa, não empty state); alinhado com referência Linear Inbox densidade
- Lista recents subject+preview+timestamp → ✅ explicitamente alinhado com Linear Inbox referência principal do charter
- Filtrar smoke tests → operacional, fora do escopo do charter
- Botão composer paleta neutra → não opinado, mas Cockpit V2 implica tokens neutros

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
