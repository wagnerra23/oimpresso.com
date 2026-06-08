# COWORK_NOTES.md — INBOX: Wagner → Claude Design

> Wagner escreve aqui. Claude Design ([CD]/[CC]) lê aqui pra produzir protótipo + crítica.
> **Append-only.** Não edita pedidos antigos — adiciona resposta abaixo.
> Formato em [PROTOCOL.md §4](PROTOCOL.md).

> ⚠️ **LEITURA OBRIGATÓRIA antes de qualquer F3:** [`LICOES_F3_FINANCEIRO_REJEITADO.md`](LICOES_F3_FINANCEIRO_REJEITADO.md) — 6 anti-padrões + pré-flight checklist. Documenta entrega rejeitada por [CL] em 2026-05-09 (F3 Financeiro: 4 controllers sem tenant scope, middleware fantasma, Models inventados, Unificado regredido). Aplicar checklist em Estoque/Vendas/RH/Suprimentos/Crédito.

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

*[Wagner adiciona aqui após perguntas respondidas]*

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
