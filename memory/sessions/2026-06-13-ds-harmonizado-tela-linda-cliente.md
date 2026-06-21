---
topic: "DS harmonizado — elevar os tokens à beleza da tela Cliente antes de tokenizar"
title: "DS harmonizado — elevar os tokens à beleza da tela Cliente antes de tokenizar"
date: "2026-06-13"
status: "proposal"
tier: "Tier-0 (fundação · afeta o app todo)"
author: "Claude (especialista DS + design visual)"
related_adrs:
  - 0190-primary-roxo-universal
  - 0209-ds-ratchet
  - 0239-governanca-ds-git-ssot
related_files:
  - resources/css/inertia.css
  - resources/css/foundations.css
  - resources/js/Pages/Cliente/Index.tsx
  - resources/js/Pages/Cliente/_components/Pills.tsx
  - resources/js/Pages/Cliente/_components/Avatar.tsx
  - resources/js/Pages/Cliente/_components/KpiStripClickable.tsx
  - resources/js/Pages/governance/DsRollout.tsx
filosofia: "extrair, não repintar · a tela-ouro define o DS, não o contrário"
---

# DS harmonizado: a tela linda primeiro eleva o DS, depois é tokenizada

> Wagner: "a tela de Cliente é bonita; tenho medo que tokenizar rebaixe."
> Resposta curta: **o medo está CERTO** — tokenizar cru hoje rebaixa. A solução
> canon ("extrair, não repintar") é **subir os tokens até a beleza da tela** e só
> então tokenizar. Resultado: tela linda sobre DS elevado, e mais 50+ telas
> herdam a mesma beleza de graça.

---

## 1. VEREDITO — tokenizar cru rebaixaria? **SIM. Distância grande.**

A tela Cliente NÃO usa os tokens semânticos do DS. Ela usa a paleta Tailwind
**`-50/-200/-700` em hue refinado** (rose, emerald, amber, sky, violet, stone) +
gradientes **oklch** nos avatares. Essa paleta é a "tela-ouro". Os tokens
semânticos atuais (`--color-success/warning/destructive`) são **HSL puro de
biblioteca** (shadcn new-york slate, sem refinamento). Substituir um pelo outro
hoje seria **repintar para baixo**.

### Prova valor-a-valor (a distância que o Wagner sente no olho)

| Papel | Valor REFINADO na tela (ouro) | Token DS atual (cru) | Distância · por que rebaixa |
|---|---|---|---|
| **devedor / erro** | `rose-700` = `oklch(.51 .19 18)` — vermelho **rosado**, profundo, sério (`Pills.tsx:214`, `Index.tsx:288`) | `--color-destructive: hsl(0 84% 60%)` = `oklch(~.63 .24 27)` — **vermelho-puro saturadão, claro** | Hue 27→18 (sai do laranja, entra no rosado sofisticado); croma .24→.19 (menos "alarme de incêndio"); L .63→.51 (mais grave). O token atual é **berrante**; a tela é **séria**. |
| **ativo / sucesso** | `emerald-700` = `oklch(.51 .12 162)` sobre `emerald-50` `oklch(.97 .02 162)` (`Pills.tsx:39,128`) | `--color-success: hsl(142 71% 45%)` = `oklch(~.66 .17 150)` | Hue 150→162 (emerald é mais azulado/caro que o verde-grama 142); croma .17→.12 (menos neon); L .66→.51 no texto. Token atual é **verde-supermercado**; emerald é **verde-Stripe**. |
| **atenção / esfriando** | `amber-700` = `oklch(.55 .12 75)` sobre `amber-50` (`Pills.tsx:130`) | `--color-warning: hsl(38 92% 50%)` = `oklch(~.72 .16 70)` | Croma .16→.12, L .72→.55. O amber da tela é **mostarda madura**; o warning atual é **amarelo-de-trânsito** chapado. |
| **info / azul** | `sky-700` = `oklch(.50 .13 244)` (`Index.tsx:289`, status active) + `blue-700` nos chips | — (DS **não tem** token info; cai em `primary` roxo, que é errado p/ info) | A tela tem um azul informativo dedicado. O DS hoje **não tem** info → telas inventam `sky`/`blue` cru. Gap a preencher. |
| **borda** | `oklch(0.9–0.93 0.004 90)` — **warm cream**, hue 90 (`Index.tsx:842,1044`, KPI strip) | `--color-border: hsl(214 32% 91%)` = `oklch(~.92 .02 248)` — **cool slate** | A tela inteira foge do border slate frio e usa borda **cream quente** hue 90 pra casar com o fundo `--color-page-cream`. O token border atual é da **família errada de temperatura**. |
| **card / superfície** | branco puro sobre fundo `page-cream oklch(.985 .003 90)` (`Index.tsx:123`) | `--color-card: hsl(0 0% 100%)` (ok) mas **falta o cream de página** como par | O card está certo; o que falta é o **par cream** já existir como token de fundo de página no DS (hoje é exceção local da tela). |
| **avatar** | 12 gradientes **oklch** `linear-gradient(135deg, oklch(.65 .18 H), oklch(.55 .20 H'))` (`Avatar.tsx:32-45`) | — (DS não tem escala de avatar) | Beleza pura que o DS ignora. Vira token de "data-viz / identidade". |

**Conclusão:** a tela é refinada em **3 eixos que o DS atual erra**: (a) hue mais
sofisticado (rosado/emerald/mostarda vs vermelho/verde/amarelo de biblioteca),
(b) croma menor (sério, não berrante), (c) temperatura de borda quente (cream)
em vez de slate frio. Tokenizar cru destruiria os 3. **Distância média ≈ 10–14°
de hue + ~0.05 de croma + família de temperatura trocada** — perfeitamente
visível a olho nu. O medo do Wagner é tecnicamente correto.

---

## 2. PROPOSTA — DS elevado (extrair a beleza da tela → virar token)

Princípio: **cada token semântico passa a ter um par `soft` (fundo) + `fg`
(texto/borda)** extraído exatamente do par `-50/-700` que a tela já usa, e
convertido pra **oklch** (precisão de hue/croma que HSL não dá). Refinamento
best-practice Linear/Stripe: **croma baixo no texto-em-superfície, hue estável
entre soft e fg** (o olho lê "mesma cor, dois pesos").

### 2.1 — Tabela ANTES → DEPOIS (LIGHT)

| Token | ANTES (cru) | DEPOIS (elevado · oklch) | Justificativa (hue/croma/L) |
|---|---|---|---|
| `--color-destructive` | `hsl(0 84% 60%)` | `oklch(0.58 0.20 18)` | hue 27→**18** (rosado sério, não laranja-fogo); croma →**.20**; L →**.58**. Casa com o `rose-600` do botão Excluir (`Index.tsx:1473`). |
| `--color-destructive-foreground` | `hsl(210 40% 98%)` | `oklch(0.98 0 0)` | branco — mantém. |
| `--color-destructive-soft` *(novo)* | — | `oklch(0.97 0.02 18)` | = `rose-50` da tela. Fundo de pill/linha devedor. |
| `--color-destructive-fg` *(novo)* | — | `oklch(0.51 0.19 18)` | = `rose-700`. Texto "devedor" / "com saldo". |
| `--color-success` | `hsl(142 71% 45%)` | `oklch(0.62 0.13 162)` | hue 150→**162** (emerald, não verde-grama); croma →**.13** (menos neon); L levemente abaixo. |
| `--color-success-foreground` | `hsl(143 64% 24%)` | `oklch(0.51 0.12 162)` | = `emerald-700` da tela. |
| `--color-success-soft` *(novo)* | — | `oklch(0.97 0.02 162)` | = `emerald-50`. Fundo "ativo/fresc/parceiro". |
| `--color-warning` | `hsl(38 92% 50%)` | `oklch(0.70 0.13 75)` | hue 70→**75** (mostarda madura); croma .16→**.13** (tira o neon de trânsito). |
| `--color-warning-foreground` | `hsl(26 90% 30%)` | `oklch(0.55 0.12 75)` | = `amber-700`. |
| `--color-warning-soft` *(novo)* | — | `oklch(0.97 0.03 75)` | = `amber-50`. Fundo "recente/esfriando/varejo". |
| `--color-info` *(novo)* | — *(não existe)* | `oklch(0.58 0.13 244)` | **preenche o gap**: azul informativo dedicado (= `sky-600`). Hoje telas caem em `primary` roxo (errado) ou `sky` cru. |
| `--color-info-foreground` *(novo)* | — | `oklch(0.50 0.13 244)` | = `sky-700`. |
| `--color-info-soft` *(novo)* | — | `oklch(0.97 0.02 244)` | = `sky-50`. |
| `--color-border` | `hsl(214 32% 91%)` (slate frio) | `oklch(0.92 0.006 90)` | **vira warm hue 90** — exatamente o `oklch(0.9–0.93 0.004 90)` que a tela usa manualmente. Casa com o fundo cream. **Esta é a mudança de maior impacto perceptual** (toda borda do app esquenta). |
| `--color-muted` | `hsl(210 40% 96%)` | `oklch(0.97 0.004 90)` | warm, par do border. |
| `--color-muted-foreground` | `hsl(215 16% 47%)` | `oklch(0.55 0.012 90)` | levemente quente; legível AA. |
| `--color-card` | `hsl(0 0% 100%)` | `oklch(1 0 0)` | mantém branco (já certo). |
| `--color-page` *(promove o local)* | — *(é `page-cream` local)* | `oklch(0.985 0.003 90)` | promove o `--color-page-cream` da tela a **token de fundo de página oficial** do DS. |

### 2.2 — Tabela ANTES → DEPOIS (DARK)

Regra Linear/Stripe pro dark: **soft = `hue` com L ~0.20–0.25 e croma baixo**
(o `-950/40` da tela), **fg = L ~0.72–0.78** (o `-300` da tela). Hue idêntico ao
light (consistência de marca cross-theme).

| Token (dark) | ANTES | DEPOIS (elevado) | Justificativa |
|---|---|---|---|
| `--color-destructive` | `hsl(0 63% 31%)` | `oklch(0.55 0.17 18)` | hue rosado consistente com o light; um pouco mais claro pra contraste em fundo escuro. |
| `--color-destructive-soft` | — | `oklch(0.26 0.07 18)` | = `rose-950/40`. |
| `--color-destructive-fg` | — | `oklch(0.74 0.13 18)` | = `rose-300`. |
| `--color-success` | `hsl(142 71% 45%)` | `oklch(0.68 0.13 162)` | emerald consistente; mais luminoso pro dark. |
| `--color-success-foreground` | `hsl(143 70% 78%)` | `oklch(0.78 0.11 162)` | = `emerald-300`. |
| `--color-success-soft` | — | `oklch(0.27 0.06 162)` | = `emerald-950/40`. |
| `--color-warning` | `hsl(38 92% 55%)` | `oklch(0.74 0.13 75)` | mostarda clara legível no escuro. |
| `--color-warning-foreground` | `hsl(35 90% 78%)` | `oklch(0.80 0.10 75)` | = `amber-300`. |
| `--color-warning-soft` | — | `oklch(0.27 0.06 75)` | = `amber-950/40`. |
| `--color-info` | — | `oklch(0.66 0.13 244)` | = `sky` dark. |
| `--color-info-foreground` | — | `oklch(0.78 0.11 244)` | = `sky-300`. |
| `--color-info-soft` | — | `oklch(0.27 0.05 244)` | = `sky-950/40`. |
| `--color-border` | `hsl(217 33% 18%)` | `oklch(0.27 0.006 250)` | mantém **cool** no dark (o cream warm não inverte coerente — a própria tela já decide isso em `inertia.css:176`). Só refinamento de neutralidade. |
| `--color-muted-foreground` | `hsl(215 20% 65%)` | `oklch(0.68 0.01 250)` | mantém. |

> **Nota dark importante:** a tela já documenta que "cream warm não inverte
> coerente — usa slate neutro" (`inertia.css:175`). A proposta **respeita isso**:
> no dark a beleza vem dos pares `soft/fg` saturados, não da borda quente. Não
> inventa o que a tela-ouro já rejeitou.

### 2.3 — Por que `soft` + `fg` (e não só 1 valor por token)

A beleza da tela é **pill = fundo claríssimo + texto escuro do mesmo hue**
(`emerald-50` + `emerald-700`). Um token único (`--color-success`) não consegue
ser fundo E texto. Por isso a elevação **adiciona o par** `*-soft` (fundo) +
`*-fg` (texto), espelhando 1:1 o que `Pills.tsx`, `KpiStripClickable.tsx` e
`ActiveChip.tsx` já fazem com `-50/-700`. Depois de tokenizado, `<TipoPill>`
vira `bg-success-soft text-success-fg` — **mesma beleza, zero cor crua**.

---

## 3. DIREÇÃO "TELA LINDA" — como Cliente fica + o polish além da cor

Com o DS elevado, a tela tokeniza **sem perder nada** e ganha consistência. Mas
"linda de verdade" (calibre Attio/Linear) pede polish **além da cor**:

1. **Hierarquia tipográfica via RAMP** (`foundations.css` já tem `--fs-1..9`):
   - H1 da página → `--fs-7` (22px/700) — já bate.
   - Nome do cliente na linha → `--fs-4` (13.5px/500); sub-nome (fantasia/tel) →
     `--fs-2` (11.5px) `muted-foreground/70`. Hoje há mistura de `text-[11px]`/
     `text-[12.5px]` cru (`Index.tsx:1246,1265`) — snapar no ramp dá ritmo.
   - **Números sempre `tabular-nums`** (já é regra; manter em Saldo/OS/KPI).
2. **Densidade**: linha `py-2.5` (`Index.tsx:1237`) é o sweet-spot Linear (40px
   row). Manter. Header `min-h-[60px]` ok. **Não adensar mais** — respiro é
   metade da beleza.
3. **Avatar como assinatura visual**: os 12 gradientes oklch (`Avatar.tsx`) são o
   elemento mais "caro" da tela. Promovê-los a **token de identidade**
   (`--avatar-grad-01..12`) deixa Sells/OS/Produção herdarem o mesmo
   reconhecimento por cor — coerência de marca no app inteiro.
4. **Foco/hover refinado**: linha aberta usa `bg-primary/10`, focada
   `ring-primary/40` (`Index.tsx:1227-1229`). Com `primary` roxo elevado isso já
   fica elegante. Padronizar `hover:bg-muted/40` (warm) em vez de slate.
5. **Estados vazios com microcopy**: hoje "Nenhum cliente encontrado nesse filtro"
   (`Index.tsx:1208`) é seco. Linda = ícone + frase + ação ("Limpar filtros" /
   "+ Novo cliente"). Pequeno toque, grande percepção de cuidado.
6. **Frescor como narrativa, não só cor**: `FrescorPill` (`Pills.tsx:166`) já
   conta "fresc · há 3d". Manter o `·` separador opacity-60 — é detalhe Stripe.
7. **Dark refinado**: com os pares `*-soft/*-fg` no dark, as pills param de
   "apagar" (hoje `dark:bg-rose-950/40` é local; vira token). Borda fica cool
   neutra (decisão da tela respeitada). Resultado: dark deixa de parecer
   "modo economia" e vira **modo premium**.

**Resumo da direção:** cor elevada (DS) + tipografia no RAMP + densidade
preservada + avatar como assinatura + vazios com microcopy + dark com pares
saturados = a "tela linda" que o Wagner quer, e que vira **template** pras
outras 50+ telas do rollout (Bloco B do `DsRollout.tsx`).

---

## 4. TRAVA / BLAST-RADIUS — isto é Tier-0 (fundação · app inteiro)

Mudar estes tokens afeta **todo o app**, não só Cliente. Por isso a proposta é
**aditiva onde dá** (novos `*-soft/*-fg/info` não quebram nada) e **refinamento
de hue/croma onde substitui** (border/muted/success/warning/destructive — mesmo
papel, valor mais bonito). Contagem real (varredura `resources/js`, 555 arquivos
`.tsx`):

| Token tocado | Arquivos `.tsx` que usam | Risco | Natureza da mudança |
|---|---|---|---|
| `border-border` / `bg-muted` / `text-muted-foreground` | **360** | **ALTO** (maior blast) | só refina hue→warm 90 + croma. Sem troca de papel. **A mudar com mais cuidado** (toda borda do app esquenta de leve). |
| `*-primary` (bg/text/border/ring) | **195** | médio | **não muda** nesta proposta (roxo 295 já é canon ADR 0190). Listado só pra mostrar escala. |
| `destructive` | **170** | médio | refina hue 27→18 + croma. Papel idêntico. |
| `bg-card` / `text-card` | **104** | baixo | mantém branco; só ganha o par `--color-page` oficial. |
| `bg/text/border-success` | **23** | baixo | refina hue→emerald 162. + novo `-soft`. |
| `bg/text-warning` | **16** | baixo | refina hue→75. + novo `-soft`. |
| `info` (novo) | **0 hoje** (telas usam `sky` cru) | nulo | **puramente aditivo** — preenche gap. |

### Quanto de cor crua ainda existe (o que a elevação destrava migrar)
- `rose-*` cru: **93** arquivos · `emerald-*`: **164** · `amber-*`: **143** ·
  `sky-*`: **19** · `violet-*`: **21**.
- Hoje o DS define os tokens semânticos em **1 só** arquivo CSS
  (`inertia.css`) — `--color-success/warning/destructive` têm **1 ponto de
  definição**. Ou seja: **a mudança de fundação é cirúrgica (1 arquivo,
  `@theme` + `.dark`)**, mas o *efeito* se propaga pros 360/170/104 consumidores.
  Por isso é Tier-0 e precisa do olhar do Wagner.

### Aprovável "com 1 olhada"
Wagner aprova vendo **a tabela §2.1/§2.2** (15 linhas, antes→depois, justificadas)
+ **1 screenshot** da tela Cliente tokenizada sobre o DS elevado vs hoje
(gate visual F1.5/F3, ADR 0107/0114). Se o screenshot estiver igual-ou-mais-lindo,
aprova. A regra canon do projeto é essa: **Wagner aprova SCREENSHOT, não tabela**
— a tabela é só pra ele entender *o que* mudou nos números.

---

## 5. PLANO DE 3 PASSOS (eleva DS → prova numa tela → tokeniza Cliente)

> Ordem canon "extrair, não repintar": **o DS sobe primeiro**, a tela só é
> tokenizada quando o DS já tem a beleza dela. Nunca o contrário.

### PASSO 1 — Elevar o DS (fundação)
- Editar **só** `resources/css/inertia.css` (`@theme` + `.dark`) com os valores
  oklch da §2.1/§2.2. Adicionar `*-soft` / `*-fg` / `--color-info*` /
  `--color-page`. **Nenhuma tela é tocada.**
- Gerar utilities Tailwind v4 automaticamente (`bg-success-soft`,
  `text-info-fg`, etc).
- Rodar `node prototipo-ui/ds-guard.mjs` + `ds-report.mjs` (baseline cor crua = 0
  preservada) e `php artisan jana:health-check`.
- **Sem screenshot ainda** — DS isolado não muda nenhuma tela visualmente
  (tokens novos, ninguém consome). Risco ZERO de regressão visual neste passo.

### PASSO 2 — Provar numa tela controlada (a tela-ouro como prova)
- Tokenizar **só Cliente/Index** (`Pills.tsx`, `KpiStripClickable.tsx`,
  `ActiveChip.tsx`, `Index.tsx` status) — trocar `rose-50/emerald-700/...` pelos
  novos `*-soft/*-fg`. É a tela onde o Wagner **mais nota** qualquer rebaixe →
  melhor termômetro.
- **Gate visual F1.5/F3**: screenshot antes (cru) vs depois (tokenizado sobre DS
  elevado), light **e** dark, 1440 + 1280. Diff de pixel deve ser ≈ 0 (ou melhor).
- Wagner aprova o **screenshot**. Se algum valor ficou pálido/berrante, ajusta
  croma na §2.1 e repete (loop barato — só 1 arquivo CSS).

### PASSO 3 — Tokenizar Cliente + abrir o rollout
- Com a prova aprovada, fechar a tokenização de Cliente (lista + ficha = item
  **B1** do `DsRollout.tsx:90`) e marcar `tokens · avatar · form` adotados.
- O mesmo DS elevado destrava B2..B13 (Orçamentos, Vendas, OS, Produção,
  Financeiro...) herdarem a beleza **sem repintar cada tela** — cada uma só troca
  `-50/-700` cru por `*-soft/*-fg`. Promover os 12 gradientes de `Avatar.tsx` a
  `--avatar-grad-*` aqui (vira identidade de marca cross-app).
- Cada tela tokenizada = `+1` no ledger (`ds-ledger.mjs`), probe G1–G13 verde,
  conformance-gate trava regressão (ADR 0209).

---

## TL;DR pro Wagner
1. **Seu medo está certo**: tokenizar cru HOJE rebaixa (os tokens são vermelho/
   verde/amarelo de biblioteca; sua tela é rosado/emerald/mostarda séria + borda
   cream quente). Distância ~10–14° de hue + croma menor + temperatura trocada.
2. **A correção é a sua filosofia**: subir o DS até a beleza da tela (extrair os
   pares `-50/-700` da tela pra tokens `*-soft/*-fg` em oklch), e só então
   tokenizar. Tabela §2.1/§2.2 mostra os 15 valores antes→depois.
3. **Tier-0, mas cirúrgico**: 1 arquivo (`inertia.css`) muda; o efeito se propaga
   pra 360 (border/muted), 170 (destructive), 104 (card) telas — por isso passa
   pelo seu olho via **1 screenshot** (não tabela).
4. **3 passos**: eleva DS (zero tela tocada, risco zero) → prova em Cliente
   (gate visual, você aprova o print) → tokeniza + rollout B1..B13.
