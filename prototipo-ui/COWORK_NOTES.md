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
*[aguardando resposta]*

---

### Pergunta 2 — Override do gate visual

Wagner pode autorizar exceção pulando F1.5 critique (`/design-override`), F2 screenshot (`/screenshot-override`), F3.5 a11y (`/a11y-override`). Cada uso vira ADR `lifecycle: historical`.

- **(a)** Sim, criar 3 overrides independentes (proposto)
- **(b)** Não permitir override — disciplina dura, força loop completo sempre
- **(c)** Override único `/design-override` que pula tudo (mais simples mas menos granular)

**Recomendação Claude Design:**
*[aguardando resposta]*

---

### Pergunta 3 — ADR nova vs emend ADR 0109

O loop formalizado precisa de uma ADR documentando o protocolo, papéis, fases.

- **(a)** ADR 0114 nova (proposto) — é decisão arquitetural diferente: protocolo de loop, não plugin
- **(b)** Emendar ADR 0109 (que introduziu Claude Design plugin) — economia de ADRs, mas mistura "plugin" com "protocolo de uso"
- **(c)** ADR 0114 + ADR 0109 emendada simultaneamente — complementares

**Recomendação Claude Design:**
*[aguardando resposta]*

---

### Perguntas extras que podem ajudar (Claude Design responde se quiser)

- **Tokens visuais:** os tokens canônicos em [CLAUDE_DESIGN_BRIEFING.md §4](CLAUDE_DESIGN_BRIEFING.md) são suficientes ou faltam tokens (radius, animação, foco)?
- **Critique score:** escala 0-100 ok? Limites 80/70 ok? Ou prefere outro framework (1-5 estrelas, P0/P1/P2)?
- **Comparáveis preferenciais:** Linear/Mercury/Stripe/Vercel/Notion são as referências ou tem outras melhores pro vertical "ERP comunicação visual"?
- **Desktop vs mobile:** prioridade desktop primeiro (Larissa 1280px) e mobile-first só pra Repair (técnico chão de fábrica). Faz sentido?
- **Frequência de critique:** roda em todo protótipo ou só nos com score < 80 já é suficiente?

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
