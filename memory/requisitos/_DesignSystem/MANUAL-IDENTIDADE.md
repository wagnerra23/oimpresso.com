---
id: requisitos-design-system-manual-identidade
title: "Manual de Identidade Visual — Clareza Confiante"
owner: wagner
last_reviewed: "2026-06-06"
next_review: "2026-07-31"
related_adrs: [0235, 0249, 0253, 0254]
note: "Voz visual canon. next_review obrigatório (DesignDocsFreshnessChecker ADR 0236) — sobrevive ao tempo por revisão forçada + grade medido (ADR 0254), não por boa-vontade."
---

# Manual de Identidade Visual — oimpresso · "Clareza Confiante"

> **O que é:** a **voz visual** do produto — as decisões de identidade que toda tela/componente
> deve encarnar. É a peça que faltava: o projeto tinha governança (gates) e tokens, mas não tinha
> a **identidade decidida e escrita**. Sem ela, mecanizar (codemod/ratchet) converge pro *genérico*.
>
> **Irmã da [`MANUAL-CSS-JS.md`](MANUAL-CSS-JS.md)** (a voz de CÓDIGO). Esta é a voz de DESIGN.
> **Direção escolhida por Wagner 2026-06-06.** Subordinada à Constituição UI v2 (ADR UI-0013) e ao
> DS v6 (ADR 0235 cor / 0249 nome).
>
> **Status:** voz **decidida** (Clareza Confiante). Implementação dos tokens novos (type-scale `2xs`,
> motion) **pendente de ADR** — ver §Pendências.

---

## 0 · O princípio (a frase)

**"Clareza Confiante": cada pixel tem intenção. O roxo é pontuação, não decoração. O número manda.**

Precisão do Linear + legibilidade do Stripe, tunada pra **dona de PME brasileira não-técnica**
(Larissa, 1280px) e oficina (Martinho). Não é frio-enterprise nem startup-lúdico. **Calma, precisa, confiante.**

---

## 1 · As 10 dimensões DECIDIDAS

A ordem é por impacto na "cara" (tipografia + espaço + detalhe definem mais que cor).

### 1.1 Tipografia — **escala fechada de 8 degraus** (fim do `text-[Npx]`)
| token | px | uso |
|---|---|---|
| `2xs` | 11 | metadados, sub-labels (resolve `text-[10.5/11.5px]`) |
| `xs` | 12 | legendas, badges |
| `sm` | 13 | **corpo denso ERP (default)** |
| `base` | 14 | corpo confortável |
| `lg` | 16 | subtítulo |
| `xl` | 20 | título de seção |
| `2xl` | 24 | título de página |
| `3xl` | 30 | hero (raro) |
- **Pesos:** 400 corpo · 500 ênfase · 600 títulos/ações. **Sem 700.**
- **Tracking:** títulos `-0.01em`; uppercase de seção `+0.04em`.
- **Números: `tabular-nums` SEMPRE** (R$/qtd/data). Fonte: Inter.

### 1.2 Espaço & Densidade — confortável-densa
Unidade 4px (escala dos primitivos: 1,2,3,4,6,8,12). Linha de lista/tabela **36px** · card pad **16px** · gutter **24px**. Fim de `gap-0.5` cru.

### 1.3 Detalhe & Acabamento — **as 3 assinaturas** (onde mora a alma)
1. **Focus-ring roxo** `ring-2 ring-primary/40` em TODO interativo.
2. **Barra-accent roxa à esquerda** (2px) no item ativo/selecionado — o "você está aqui".
3. **Números tabulares** alinhados na vírgula.
+ **7 estados obrigatórios:** hover · focus-visible · active · disabled · empty · loading · error.

### 1.4 Forma
`rounded-md` 8px default · `lg` 12px (cards/sheets) · `full` (pills). Borda 1px `border-border` (hairline). Sombra só em flutuante (`shadow-sm`).

### 1.5 Movimento
Hover/cor **150ms ease-out** · enter/sheet **200ms** · sem bounce · foco anima (120ms).

### 1.6 Cor
Accent = roxo `oklch(0.55 0.15 295)` (ADR 0235) como **pontuação** (ação primária/foco/ativo), nunca preenchimento. Superfícies: `background` ‹ `card` ‹ `muted`. Status = soft pills (`emerald/amber/rose/sky` — convenção semântica).

### 1.7–1.10
- **Hierarquia:** 3 níveis/tela — Título `2xl/600` · Seção `xs/600/uppercase/muted` · Corpo `sm/400`.
- **Ícone:** lucide, 14–16px, stroke 1.5, `text-muted-foreground` (forçado por gate).
- **Voz:** PT-BR direto, rótulo ≤2 palavras, zero jargão. "Faturar", não "Processar faturamento".
- **Sistema:** os gates mecanizam rumo a ESTA manual (não ao genérico).

---

## 2 · Como o design deve AGIR (instrução)

Ao criar/migrar qualquer tela ou componente:

1. **Rode o PRE-FLIGHT** ([`screen-grade`](../../../.claude/skills/screen-grade/SKILL.md)) — não monte contexto de cabeça.
2. **Layout = composição de primitivos** (`<Stack/Inline/Grid/Box/Container/Text>` — [ADR 0253](../../decisions/0253-primitivos-layout.md)), nunca `flex`/`grid` solto nem `.css` bespoke.
3. **Cor/tipo/espaço SÓ por token** (DS v6). Zero `text-[Npx]`, zero hex/cor crua neutra/azul.
4. **Aplique as 3 assinaturas** (§1.3) — é o que torna reconhecível "oimpresso".
5. **Meça** com o grade determinístico ([ADR 0254](../../decisions/0254-design-identity-grade-deterministico.md)): `node scripts/design-identity-grade.mjs`. **Só sobe.**

### DO / DON'T (resumo)
| ✅ DO | ❌ DON'T |
|---|---|
| `<Stack gap={4}>` | `<div className="flex flex-col gap-4">` |
| `text-sm` (token) | `text-[13px]` |
| `bg-primary` (roxo pontuação) | `bg-blue-600` / hex / roxo decorativo |
| `focus-visible:ring-primary` | interativo sem foco visível |
| `tabular-nums` em valor | número proporcional desalinhado |
| status via soft pill semântico | `bg-red-500` sólido pra ESTADO |

---

## 3 · O placar (definição de "melhorou")

Grade de identidade DETERMINÍSTICO ([ADR 0254](../../decisions/0254-design-identity-grade-deterministico.md)) — σ=0, anti-alucinação:
- **Hoje: 66/100 · Developing.** Meta: **85 · Leader.**
- Gaps reais medidos: `layout 0` (migração via codemod) · `tipografia 61` (token `2xs`).
- O grade lista os **top-5 ofensores** por dimensão (roadmap).

---

## 4 · Pendências (o que falta virar ADR/código)

- [ ] **ADR de type-scale** — formalizar os 8 tokens (incl. `2xs`) no `@theme` / tokens DS v6.
- [ ] **ADR de motion** — tokens de duração/easing (150/200ms).
- [ ] **Recraftar componentes-bandeira** (`Button`/`Card`/row) encarnando esta manual (gate visual Wagner).
- [ ] **North Star aprovado** (print 2026-06-06) → vira o golden de "componente com identidade".

---

## Refs
- [Constituição UI v2 — ADR UI-0013](../../decisions/../requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) · [DS v6 — ADR 0235](../../decisions/0235-ds-v4-accent-roxo-universal.md) / [0249](../../decisions/0249-ds-v6-naming-amends-0235.md)
- [Primitivos de layout — ADR 0253](../../decisions/0253-primitivos-layout.md) · [Grade determinístico — ADR 0254](../../decisions/0254-design-identity-grade-deterministico.md)
- [`MANUAL-CSS-JS.md`](MANUAL-CSS-JS.md) (voz de código) · [`INDEX-DESIGN-MEMORIAS.md`](INDEX-DESIGN-MEMORIAS.md) (SSOT)
