# Resposta Claude Design → PR #295 (cole em `prototipo-ui/COWORK_NOTES.md`)

> Cole o bloco abaixo logo após "**Recomendação Claude Design:** *[aguardando resposta]*" de cada pergunta.

---

## Pergunta 1 — Trigger de F3

**Recomendação Claude Design: (a) Manual.**

Razão: Cowork e Code não compartilham estado em tempo real — Wagner é o "carteiro". Hook `SessionStart` lendo `HANDOFF.md` cria expectativa de reatividade que pode falhar silenciosamente (Code abre sessão sem zip novo extraído ainda, ou com zip antigo). Manual mantém o gate F2 (screenshot approval) como ato consciente: Wagner aprova → Wagner cita protótipo no prompt → F3 começa. Sem ambiguidade sobre "quem disparou".

Caminho de evolução: depois de 5 telas mergeadas com fluxo manual saudável, considerar (b) como conveniência opcional via flag `--auto-handoff`.

---

## Pergunta 2 — Override do gate visual

**Recomendação Claude Design: (a) 3 overrides independentes.**

Razão: granularidade espelha o custo de cada gate. F1.5 critique é barato (Claude roda em segundos), F2 screenshot exige Wagner síncrono, F3.5 a11y exige teste real. Override único agrupa coisas com risco assimétrico — pular a11y de tela cliente-facing é qualitativamente diferente de pular critique de copy-fix. ADR `lifecycle: historical` por uso já cuida da disciplina (auditoria).

Adendo: padronizar formato `/X-override <razão> --tela=<nome>` pra `php artisan jana:health-check` rastrear uso por tela.

---

## Pergunta 3 — ADR nova vs emend ADR 0109

**Recomendação Claude Design: (a) ADR 0114 nova.**

Razão: ADR 0109 introduziu plugin Claude Design (capability). ADR 0114 formaliza protocolo de uso (process). Misturar plugin+protocolo em um ADR cria documento bicéfalo difícil de superseder isoladamente — se um dia trocarmos o plugin (ex: Vercel v0), o protocolo continua válido. Manter separado preserva substituibilidade.

---

## Extras (respondendo as 5)

**Tokens visuais:** suficientes pra start, mas faltam 3:
- **Radius:** padronizar `rounded-md` (6px) cards/botões, `rounded-lg` (8px) drawers/dialogs, `rounded-full` apenas badges/avatars. Sem `rounded-xl+` (vira "tutorial-shadcn").
- **Animação:** `transition-colors duration-150` hovers, `transition-transform duration-200 ease-out` drawers. Sem animação >300ms exceto loading skeletons.
- **Foco:** `focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2`. Obrigatório em interativos — Larissa usa teclado pesado.

**Critique score:** escala 0-100 com limites 80/70 ok pra MVP. Após 10 telas, recalibrar com p50/p90 dos scores reais. Se p50 ficar em 90, mover bar pra 85/75.

**Comparáveis preferenciais:** Linear/Mercury/Stripe/Vercel/Notion cobrem 80%. Adicionar:
- **Front** (inbox 3-col) pra Whatsapp/Conversations
- **Pylon** (B2B support) pra Repair operacional
- **Attio** (CRM enxuto) pra Cliente/Index
- **Cron** (densidade temporal) pra Ponto/Marcacoes
Excluir Notion pra telas Larissa — densidade insuficiente.

**Desktop vs mobile:** correto. Larissa 1280px = canon. Repair = mobile-first não-negociável (técnico chão de fábrica). Site público P3 = mobile-first também (lead vem de WhatsApp/Insta). Resto = desktop-first com breakpoint sm: que não quebra.

**Frequência critique:** rodar em **todo** protótipo F1, não filtrar por threshold. Razão: não temos baseline ainda, score baixo é diagnóstico ANTES de virar problema. Após 20 telas, considerar skip-when score histórico do mesmo módulo > 85.

---

## Memória otimizada — pra ambos os Claudes consultarem

Adicionar ao `CLAUDE_DESIGN_BRIEFING.md` §4 (após "Sombras"):

```markdown
### Radius
- `rounded-md` (6px) — cards, buttons, inputs (default)
- `rounded-lg` (8px) — drawers, dialogs, popovers
- `rounded-full` — badges, avatars, status dots
- Evitar `rounded-xl+` — fica "tutorial-shadcn"

### Animação
- `transition-colors duration-150` em hovers
- `transition-transform duration-200 ease-out` em drawers
- `animate-pulse` apenas skeleton loading
- Sem animação > 300ms (exceto loading)

### Foco (obrigatório)
- `focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2`
- Em todo interativo (button, input, link, custom)
- Não usar `outline-none` sem substituir
```

Adicionar ao `PROTOCOL.md` §5 (após tabela overrides):

```markdown
**Formato canônico:** `/X-override <razão curta> --tela=<nome-kebab>`
**Exemplo:** `/screenshot-override Wagner aprovou no Cowork --tela=sells-create`
```

Adicionar ao `GLOSSARY.md` seção nova "Comparáveis canônicos":

```markdown
## Comparáveis canônicos por tipo de tela

| Tipo de tela | Comparável primário | Secundário |
|---|---|---|
| Lista densa (Larissa) | Linear | Attio |
| Form complexo | Stripe Checkout | Mercury onboarding |
| Dashboard KPIs | Vercel | Mercury |
| Inbox 3-col | Front | Pylon |
| Kanban | Linear Cycles | — |
| Chat IA | ChatGPT | Claude.ai |
| Operacional mobile | Pylon mobile | Linear mobile |
| Densidade temporal (ponto) | Cron | Notion calendar |
| CRM | Attio | Pipedrive |
| Site público SaaS | Linear marketing | Vercel marketing |
```

---

## Próxima ação Wagner

1. Cola este bloco no `COWORK_NOTES.md` do PR #295 (substituindo os 3 "*[aguardando resposta]*")
2. Aplica os 3 patches de memória otimizada (radius/animação/foco em DESIGN_BRIEFING, override format em PROTOCOL, comparáveis em GLOSSARY)
3. Mergeia PR #295
4. Quando quiser começar **Sells/Create** (P0), abra entrada formal em `COWORK_NOTES.md` seguindo template já no arquivo
