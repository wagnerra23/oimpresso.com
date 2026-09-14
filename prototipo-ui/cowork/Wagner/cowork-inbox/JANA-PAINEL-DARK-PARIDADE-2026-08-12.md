# Pedido [CC] → [CL] · Painel da Jana (`/ia`) — paridade de tema escuro

- **Revisão 2** · 2026-08-12 · autor [CC] (Cowork) · aguarda ratificação [W]
- **Base de leitura:** `origin/main` **lido neste turno** (2026-08-12, 20:50→20:54Z). Trees: `9a121aea5957` (Jana) e `47be7554d189` (css — o `main` andou durante a leitura). Nenhum dos dois é commit sha.
- **Lido arquivo a arquivo:** `Pages/Jana/Index.tsx` · `Pages/Jana/_components/JanaCockpit.tsx` (28 KB) · `Components/shared/KpiCard.tsx` · `resources/css/inertia.css` · `tokens/_generated-inertia-theme.css` · `tokens/_generated-inertia-dark.css` · blocos `[data-theme="dark"]` de `cockpit.css`.
- **Evidência do sintoma:** screenshot de `oimpresso.com/ia` no escuro, enviado por [W] em 12/08.
- **Referência:** `prototipo-ui/cowork/chat-jana.css` (`.jc-*`) — o próprio `JanaCockpit.tsx` a declara como âncora de design no cabeçalho.

---

## 0. Errata da revisão 1 — leia antes

A revisão 1 acusava o `JanaCockpit` de usar cor crua Tailwind e chamava isso de causa-raiz. **Está errado, e o erro foi meu:** escrevi os itens D-5 a D-9 do screenshot sem abrir o arquivo, e marquei 🔶 justamente porque não tinha medido. Medido agora:

- **`JanaCockpit.tsx` está limpo.** Zero `violet-`/`fuchsia-`/`sky-`/`emerald-`/`rose-`. Usa `primary`, `destructive-soft`, `warning-soft`, `success`, `muted`, `border` — tokens semânticos, como manda o cabeçalho dele.
- **`KpiCard.tsx` está limpo.** Tints de 5% + borda 20% por `tone`, exatamente o canon.
- **Cor crua existe, mas só no `Index.tsx`** — e é o item #10 abaixo, não a causa do que [W] viu.

A causa real é outra, mais interessante, e nenhum dos dois arquivos é culpado: **a tela veste duas famílias de token que colidem no escuro.**

---

## 1. A medição — duas paletas, e uma delas inverte no escuro

O shell (`.cockpit`, sidebar, header) veste `--bg/--surface/--border/--text/--accent`. O Painel veste `--color-*` (Tailwind/shadcn, DTCG). No **claro** as duas quase coincidem — por isso ninguém viu. No **escuro** elas divergem em três pontos, e todos aparecem no screenshot.

| Papel | Cockpit (`.cockpit[data-theme="dark"]`) | Tailwind (`_generated-inertia-dark.css`) | Bate? |
|---|---|---|---|
| fundo da página | `--bg: oklch(0.26 0.006 240)` | `--color-background: oklch(0.26 0.006 240)` | ✅ idêntico |
| superfície de card | `--surface: oklch(0.30 0.008 240)` | `--color-card: oklch(0.30 0.008 240)` | ✅ idêntico |
| borda | `--border: oklch(0.34 0.008 240)` | `--color-border: oklch(0.34 0.008 240)` | ✅ idêntico |
| **roxo da marca** | `--accent: oklch(0.55 0.15 295)` (sem override no escuro) | `--color-primary: oklch(0.70 0.15 295)` | ❌ **dois roxos na mesma tela** |
| **`--accent`** | **o roxo da marca** | `--color-accent: oklch(0.235 0.010 240)` — **um cinza de hover** | ❌ **mesmo nome, sentido oposto** |
| superfície "muted" | (não existe; usa `--bg-2` 0.23) | `--color-muted: oklch(0.235 0.010 240)` — **mais escuro que o card** | ⚠️ inverte |
| foco | accent + halo (canon DS) | `--color-ring: oklch(0.87 0.012 240)` — quase branco | ❌ |

> **O achado acima dos achados:** `--accent` significa **duas coisas** dentro da mesma página. No cockpit é o roxo `295`; no Tailwind é o cinza `240` de estado hover. Todo `hover:bg-accent` escrito por quem pensava "accent = roxo da marca" entrega **cinza**. Isso não é preferência estética — é uma armadilha de vocabulário, e vai reincidir em toda tela nova.

---

## 2. As correções, uma por uma

Ordem = impacto visual no escuro. Todas em `resources/js/Pages/Jana/`, salvo onde indicado.

### #1 — Brief diário lê como buraco, não como plate `JanaCockpit.tsx`

```tsx
<Card className="border-primary/20 bg-primary/5">
```

`bg-primary/5` é **translúcido**: os 95% restantes compõem sobre o que está atrás, que é `--color-background` (**0.26**) — **mais escuro que um card** (0.30). O bloco mais importante da tela afunda em vez de subir. O protótipo faz o oposto, e por isso lê:

```css
.jc-brief{ background: color-mix(in oklch, var(--accent) 9%, var(--surface)); border: 1px solid var(--accent-line); }
```

**Vira** — tint opaco sobre a superfície de card, nunca sobre o fundo:

```tsx
<Card className="border-primary/25 bg-[color-mix(in_oklch,var(--color-primary)_9%,var(--color-card))]">
```

### #2 — Faixa do devedor: bloco vermelho chapado `JanaCockpit.tsx`

```tsx
<p className="… border-l-[3px] border-destructive bg-destructive-soft …">
```

`--color-destructive-soft` no escuro é `oklch(0.26 0.07 18)` — **opaco, e na mesma luminância do fundo da página**. Sobre o brief (que já afundou, #1) o resultado é a mancha sólida do screenshot. O canon do DS para alerta inline é **fundo 6% + borda 22% no tom**, nunca sólido.

**Vira:**

```tsx
<p className="… rounded-md border border-destructive/25 bg-destructive/6 px-3 py-2.5 …">
```

E **sai o `border-l-[3px]`**: contêiner arredondado com barra de cor à esquerda não é padrão do sistema — o tom já está no fundo e no ícone.

### #3 — 3 dos 4 KPIs pintados `JanaCockpit.tsx`

```tsx
tone={overdueValue > 0 ? 'danger' : 'success'}   // inadimplência
tone="info"                                       // PIX hoje
```

Canon do protótipo: **todos** os KPIs em `--surface` + `1px --border`; **um só** ganha ênfase, e só quando há alerta (`.jc-kpi.emph` = `--neg-soft` + borda 35%). Hoje o escuro mostra um card vermelho, um azul e um verde lado a lado — três ênfases é zero ênfase.

**Vira:** PIX → `tone="default"` · inadimplência → `tone={overdueValue > 0 ? 'danger' : 'default'}`.

> ⚠️ O `'success'` no ramo `else` é pior que redundante: **quando não há dívida, o card fica verde exibindo `R$ 0,00`** — verde afirmando "bom" sobre uma ausência de dado.

### #4 — Valor zero em verde `JanaCockpit.tsx`

```tsx
big={<span className="text-success">{fmtShort(sparkSum)}</span>}      // Faturamento
big={<span className="text-destructive">{fmtShort(ageingTotal)}</span>} // Inadimplência
```

No screenshot isso é **`R$ 0,00` em verde**. No protótipo o valor é sempre `--text` com `tabular-nums`, e só o negativo vira `--neg` (`.jc-kpi-v.red`).

**Vira:** Faturamento → sem classe de cor (herda `text-foreground`). Inadimplência → mantém `text-destructive` **apenas quando `ageingTotal > 0`**.

### #5 — Gradiente na barra de aging `JanaCockpit.tsx`

```tsx
<div className="h-full rounded-full bg-gradient-to-r from-warning to-destructive" …/>
```

Degradê de dois tons ao longo de uma barra que representa **um** bucket — a cor varia sem que nada varie no dado.

**Vira:** `bg-destructive` chapado (ou o tom do bucket: `0-30d` → `warning`, demais → `destructive`).

### #6 — Chips do brief mais escuros que o card `JanaCockpit.tsx`

```tsx
className="… border-border bg-muted … hover:bg-accent hover:text-accent-foreground"
```

Dois defeitos numa linha: `--color-muted` no escuro é **0.235**, mais escuro que o card (0.30) — o chip vira buraco; e `hover:bg-accent` é o cinza da colisão da §1, não o roxo que o autor quis.

**Vira:** `bg-card border-border hover:border-primary/40 hover:bg-[color-mix(in_oklch,var(--color-primary)_8%,var(--color-card))]`.

### #7 — Dois roxos na mesma tela `tokens/*.tokens.json` · Tier 0

`--color-primary` clareia no escuro (0.55 → **0.70**); o `--accent` do cockpit **não tem override** e fica em 0.55. Resultado: a aba ativa e o botão "Conversar" do Painel são um roxo mais claro que o roxo da sidebar, na mesma janela.

**Decisão de [W]** (ADR 0190/0235), duas saídas honestas: **(a)** o cockpit também clareia no escuro (`--accent: oklch(0.70 0.15 295)` no bloco dark), ou **(b)** o token Tailwind volta pra ~0.62. Não escolho por você — é a cor da marca.

### #8 — Foco quase branco `tokens/*.tokens.json`

`--color-ring` no escuro é `oklch(0.87 0.012 240)`: cinza-claro neutro. O canon do DS é **anel de accent + halo suave**. Acessibilidade é não-negociável no sistema, e hoje o foco não diz de quem é o produto.

**Vira:** `--color-ring` dark ← `--color-primary`.

### #9 — Colisão de nome `--accent` · Tier 0, **não** aplicar de afogadilho

Nomear é a correção; renomear é uma onda própria. **Regra imediata, custo zero:** em `Pages/Jana/**`, roxo é sempre `primary`. `accent` só quando se quer de fato a superfície de hover cinza do shadcn. Um comentário no topo do `JanaCockpit.tsx` dizendo isso vale mais que um gate.

### #10 — Cor crua, e agora sim medida `Index.tsx`

Único arquivo da Jana com escala Tailwind literal:

| Linha | Hoje | Vira |
|---|---|---|
| badge `METAS` | `bg-gradient-to-r from-violet-600 via-fuchsia-500 to-pink-500 text-white` | `Badge` sólido em `primary`. **Gradiente de 3 cores é paleta inventada** — some, não vira gradiente de token |
| `ProximaAcaoCard` | `border-violet-500/20 bg-gradient-to-br from-violet-500/5 via-transparent to-fuchsia-500/5` | mesmo tratamento do #1 (tint 6% opaco sobre `--color-card` + borda `primary/22`) |
| `JanaKpiStrip` | `text-violet-500` · `text-sky-500` · `text-amber-500` + plate `bg-muted/40` | ícone em `primary`; plate `bg-primary/10` |
| `FAROL_CLASSES` | `bg-emerald-500` · `bg-amber-400` · `bg-rose-500` | `bg-success` · `bg-warning` · `bg-destructive` (mesma semântica, hue do sistema) |
| empty state | `bg-violet-500/10` + `text-violet-500` | `bg-primary/10` + `text-primary` |

**Teste que fecha a classe:** `rg "violet-|fuchsia-|pink-|sky-|emerald-|rose-|amber-" resources/js/Pages/Jana/` deve dar **rc=1**.

---

## 3. O que eu **não** peço

- **Não adicionar variantes `dark:`.** Nenhum dos 10 itens é override de tema faltando — são tint translúcido sobre o fundo errado (#1, #2), tom semântico errado (#3, #4) e nome ambíguo (#6, #9). `dark:` dobra a superfície e mantém a causa.
- **Não criar gate novo** pra cor crua sem FP medido antes: o §5 de `proibicoes.md` já tem cinco lápides de guard sintático que reprovou o legítimo. Consertar e ver se reincide (two-strikes, ADR 0344).
- **Não mexer no `sells-cowork-insights.css`** nesta leva. São 776 linhas com `@import` global em `inertia.css:13` e zero consumidor JS desde a remoção do `JanaCockpitV2` (gap #28 do `AUDIT-GAPS-2026-08-10.md`) — remoção é ato de governança, decisão [W], e não bloqueia nada daqui.

---

## 4. O que **não** verifiquei

- **Contraste AA no escuro depois da troca** — os tints de 6-9% sobre `--color-card` (0.30) não foram medidos contra WCAG. [CA] fecha na F3.5.
- **Se algum tom vem do payload** do servidor em vez de literal no `.tsx` — li os dois arquivos e não vi, mas não varri os controllers por esse eixo.
- **Efeito do #7/#8 fora da Jana.** São tokens globais: mudar `--color-ring` e `--color-primary` no escuro toca **toda** tela feita com utilities. Por isso os dois estão marcados Tier 0 e separados dos 8 itens locais — dá pra aplicar #1–#6 e #10 sem tocar em token nenhum.
- **`Modules/Jana/Resources/views/*`** (as 8 blades órfãs) não entram aqui: estão em `layouts.app` AdminLTE, fora deste sistema de token. São a onda [CC]-8/10/11 do pedido de 09/08.
