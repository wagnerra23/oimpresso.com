# Screenshot Guide — Jana Demo Pilot 2026-05-16

> **Contexto:** demo CYCLE-06 G3 — tela polida `/copiloto/dashboard` (`resources/js/Pages/Jana/Dashboard.tsx`).
> **Audiência:** Wagner + cliente piloto (Larissa / ROTA LIVRE biz=4) ou prospect.
> **Charter de referência:** [`resources/js/Pages/Jana/Dashboard.charter.md`](../../../../resources/js/Pages/Jana/Dashboard.charter.md) v2.

---

## Como capturar

- **Resolução alvo:** 1280×800 (monitor Larissa) — Chrome DevTools → Device Toolbar → Responsive → 1280×800.
- **Tema:** dark mode (default `AppShellV2`). Confirmar `prefers-color-scheme: dark` no DevTools.
- **Business:** `biz=1` (oficina interna) — NUNCA biz=4 cliente real ([ADR 0101](../../../decisions/0101-tests-business-id-1-nunca-cliente.md)).
- **Rota:** `https://oimpresso.test/copiloto/dashboard` (Herd local) ou `https://oimpresso.com/copiloto/dashboard` (Hostinger prod biz=1).
- **Estados a capturar:** ambos (com metas + empty state) — toggle via biz que tenha 0 metas pra empty.

---

## Pontos visuais a destacar na demo

### 1. Header com badge `JANA V2`

- **O que aparece:** Badge gradient `from-violet-600 via-fuchsia-500 to-pink-500` com ícone `Sparkles` + texto "JANA V2" em branco, shadow sutil.
- **Por que destacar:** Marca a versão do produto pro cliente — sinaliza "isto é o copiloto IA novo, não é o ERP cru". Posiciona como produto premium.
- **Sub-elemento:** "Copiloto operacional" em texto muted ao lado — reforça posicionamento operacional (não-marketing).
- **Não confundir com:** o badge de unidade (`R$`/`%`) nos cards de meta — esse é variant `outline`, sem gradient.

### 2. KPI Strip (3 cards horizontais)

- **Cards:** Memória ativa (ícone `Brain` violet) · Última conversa (ícone `Clock` sky) · Brain B hoje (ícone `Zap` amber).
- **Valores atuais:** `—` / `—` / `0/50` (placeholders intencionais — Brain B ainda não preenche).
- **Por que destacar:** Mostra ao cliente que a Jana tem **memória persistente + histórico + governança de custo**. O `0/50` em Brain B comunica controle de orçamento ([ADR 0094](../../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §2 Tiered cost).
- **Mensagem de discurso:** "Estes números vão aparecer dinâmicos quando a Jana começar a operar pro seu business — hoje estão como placeholder porque você ainda não conversou com ela."
- **Cuidado:** NÃO prometer datas específicas — placeholder vira deferred prop futura.

### 3. Card "Próxima ação sugerida"

- **Visual:** Border violet 20% + gradient sutil (violet→fuchsia 5%), ícone `Sparkles`, título violet-700/300 (dark mode).
- **Texto:** "Quando houver sinal claro nas metas, a Jana vai sugerir aqui o próximo passo prático — sem você precisar perguntar."
- **CTA:** Botão outline "Conversar agora" com ícone `MessageSquare`.
- **Por que destacar:** Diferencial vs Bling/Tiny/Omie — **proatividade**. O cliente não precisa saber o que perguntar; a Jana antecipa.
- **Discurso:** "Em vez de você abrir relatório e tentar interpretar, a Jana lê o estado das metas e diz 'olha, aqui tá pegando — vamos olhar?'."

### 4. Grid de metas (sparkline + farol)

- **Já existia (v1):** card por meta com farol lateral (verde/amarelo/vermelho/cinza), valor realizado, alvo, %, sparkline 12 janelas, link "Ver detalhe".
- **Por que ainda importa:** densidade informacional real — não é mock. Mostra que o produto **funciona** e tem dados reais.
- **Discurso:** "Cada barrinha colorida à esquerda é o farol — verde tá no rumo, amarelo desviou ≤15%, vermelho cai forte. Você bate o olho e sabe."

### 5. Empty state polished (quando 0 metas)

- **Visual:** Card com border-dashed, ícone `Sparkles` em círculo violet-tinted (bg-violet-500/10), título "Nada por aqui ainda", subtítulo explicativo + CTA "Pergunte algo a Jana".
- **Por que destacar:** Onboarding suave — o cliente nunca vê tela vazia hostil. Toda tela tem CTA pra próxima ação.
- **Discurso:** "Sem meta cadastrada? Sem problema — você conversa com a Jana e ela cria a meta entendendo o seu negócio. Não precisa de wizard de 7 passos."

### 6. FAB Jana (canto inferior direito)

- **Visual:** `FabJana` flutuante sempre disponível.
- **Por que destacar:** A Jana acompanha em **qualquer tela** — não tem "abrir aplicativo separado de IA". O copiloto é parte do ERP.

---

## Roteiro narrativo sugerido (90 segundos)

1. **0:00–0:15** — Abrir `/copiloto/dashboard`. Destacar badge `JANA V2` + título "Dashboard de Metas".
2. **0:15–0:30** — Passar pelos 3 KPIs do strip: "memória, histórico, orçamento — tudo controlado".
3. **0:30–0:45** — Apontar "Próxima ação sugerida" — diferencial proatividade.
4. **0:45–1:10** — Passear pelos cards de meta — farol, sparkline, drilldown.
5. **1:10–1:30** — Clicar "Conversar com a Jana" e iniciar conversa real (sai do escopo deste guide).

---

## Anti-mensagens (NÃO falar)

- ⛔ "A Jana ainda não tem memória" — ela tem (`MemoriaContrato` + Meilisearch hybrid). KPIs estão `—` porque Larissa não conversou ainda, não porque falta capacidade.
- ⛔ "Em breve vamos ter Brain B" — Brain B existe (Sonnet/Opus via `laravel/ai`). O `0/50` é orçamento diário, não ausência.
- ⛔ Prometer features que não estão live (auto-mem por business, multi-conta, voice). Ficar no que a tela mostra.

---

## Checklist pré-demo

- [ ] Tema dark confirmado no browser
- [ ] Viewport 1280×800
- [ ] biz=1 logado (não biz=4 cliente real — proibição [ADR 0101](../../../decisions/0101-tests-business-id-1-nunca-cliente.md))
- [ ] Pelo menos 1 meta com sparkline visível (alternativa: empty state intencional)
- [ ] FAB Jana visível canto inferior direito
- [ ] DevTools fechado durante captura
- [ ] Console limpo (sem warnings React em vermelho)

---

## Referências

- Charter: [`resources/js/Pages/Jana/Dashboard.charter.md`](../../../../resources/js/Pages/Jana/Dashboard.charter.md) v2
- Runbook operacional: [`memory/requisitos/Jana/RUNBOOK-dashboard.md`](../RUNBOOK-dashboard.md)
- ADRs: [0052 memória 3 ângulos](../../../decisions/0052-memoria-jana-3-angulos-faturamento.md) · [0094 constituição](../../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) · [0101 tests biz=1](../../../decisions/0101-tests-business-id-1-nunca-cliente.md)
