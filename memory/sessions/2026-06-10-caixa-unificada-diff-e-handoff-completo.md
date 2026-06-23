# Sessão 2026-06-10 — Caixa unificada: diff protótipo×produção + handoff completo

**Pedido [W]:** "Caixa unificada pode ver o que falta para ficar igual em produção?" → depois "escolha, vamos aplicar todas".

**O que foi feito:**
1. Diff com leitura viva @main (Portão 1): `Atendimento/CaixaUnificada/` — `Index.tsx`, `ComposerV4.tsx`, `ContextSidebarV4.tsx`, `ConversationThreadV4.tsx`, `Index.charter.md` ✓lidos. `ConversationListV4.tsx` NÃO lido (⚠ marcado no handoff pro Code validar).
2. **Achado-chave:** produção evoluiu por Waves 1–5 + M4/M5/M6 e está NA FRENTE do protótipo em mídia real (16MB/paste/drag-drop), voz PTT, Interactive Meta, Contact CRM (vincular/criar/bloquear), tags inline, CustomerMemoryBlock 360, VoC capture, 7 tabs, filtros power-user. O charter (05-15, v1) está atrás do código — Waves documentadas só em comentário.
3. **Gaps confirmados no código (não só no charter):** US-WA-301..307 todos ainda placeholder (Filas/Broadcast/+Nova disabled; assignee "— sem atribuição"; templates/macros "em breve"; OS/Saldo/Histórico/ações da sidebar placeholders) + pacote polish V2 do protótipo nunca enfileirado (SLA pill, ⌘K, cheat-sheet, variáveis {{}}, lightbox in-app, mobile tabs, favoritos, transcript, apresentação) + pacote IA (Resumir/Perguntar/Sugerir — depende Jana) + §6 cutover aberto (Inbox legacy ainda existe).
4. **Mandato [W]: aplicar todas.** Ordem escolhida por [CC]: 302→303→301→305→304→307→306→polish→IA→cutover(gate [W] no PR).
5. **Ponte zero-toque:** `prototipo-ui-patch/PROMPT_PARA_CODE_CAIXA-UNIFICADA-COMPLETA.md` (10 PRs especificados, regras Tier 0 + gates, Pest, charter update) + 6 URLs públicas das fontes do protótipo (inbox-page/extras/ai/cur/out.jsx + inbox-page.css) embutidas. URLs ~1h.

**Decisões:** ordem dos 10 PRs ([CC] escolheu sob mandato); cutover preparado mas merge gated em OK explícito [W] no PR (charter exige canary/screenshot).

**Erros + correção:** nenhum novo nesta sessão. Disciplina mantida: nada afirmado de espelho local; ConversationListV4 declarado como não-verificado em vez de inferido.

**Residual:**
- URLs públicas expiram ~1h — se [W] demorar a colar, regenerar (6 fontes + brief).
- Charter CaixaUnificada v1 está stale vs código (Waves 1–5) — handoff já manda o Code atualizar.
- `ConversationListV4.tsx` sem leitura — SLA pill/favoritos na lista podem ter estado diferente do assumido.

**Refs:** charter `Atendimento/CaixaUnificada/Index.charter.md@main` · `inbox-page.jsx` (V2 Cowork) · handoff `prototipo-ui-patch/PROMPT_PARA_CODE_CAIXA-UNIFICADA-COMPLETA.md`.

**Próximo passo:** [W] cola o paste-block no Claude Code (1×). Depois dos merges, [CC] re-baselinar o quadro de telas (Caixa unificada vira candidata a re-avaliação 15-dim pós-onda).
