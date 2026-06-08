# GOLDEN-REFERENCE.md — a tela-ouro do oimpresso

> **Pra que serve:** você (Wagner) não precisa ser designer. Precisa de **uma tela 10/10** que todas as outras **copiam** — em vez de regra em prosa que o Claude Design interpreta de 100 jeitos.
> **Como nasce a "regra":** design não se ensina por constituição, se ensina por **exemplo**. Esta é a fonte da verdade visual: *"faça igual a esta tela, mudando só X"*.
> **Origem:** 2026-05-30 — Wagner: *"estou muito fraco em design, o Claude Design não entende as regras e eu não sei instruir ele"*. Solução: tela-ouro + 10 perguntas sim/não (substitui ~800 linhas espalhadas em 8 docs).

---

## 1. A tela-ouro: `Sells/Create`

[`resources/js/Pages/Sells/Create.tsx`](../resources/js/Pages/Sells/Create.tsx) · charter [`Sells/Create.charter.md`](../resources/js/Pages/Sells/Create.charter.md)

**Por que esta e não outra:**
- **A+ · 9,75/10** no loop Claude Design (KB-9.75 v2, PR #1064) — "melhor da turma" no `MATRIZ_MIGRACAO_DS.md`
- Charter `status: live`, 39+ testes anti-regressão, é o **form pattern canon** do Cockpit Pattern V2 ([ADR 0110](../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md))
- Já consome `@/Components/ui` certo (Select/Card/Button/Input)
- É a tela que a **Larissa** mais usa (99% do volume) em 1280px — passou no teste de tarefa real, não só de gosto

> **O juiz final de design não é o Wagner — é a Larissa fazendo a venda.** Tela boa = ela termina mais rápido, com menos erro, em 1280px. Isso se mede com cronômetro, não com "tá bonito?".

---

## 2. As 10 regras binárias (sim/não) — você roda em 2 minutos

Cada regra tem **como testar** + **evidência na golden** (linha real). Se a tela nova responde "não" a qualquer uma, **não é canon ainda**.

| # | Regra (pergunta sim/não) | Evidência na golden |
|---|---|---|
| **R1** | **Header é sticky e o h1 é `text-2xl font-semibold` (NÃO `font-bold`)?** subtitle `text-sm text-muted-foreground`. | `Create.tsx:856` (sticky) · `:860` (h1) · charter UX target "h1 24px" + anti-pattern "font-bold em h1" |
| **R2** | **Navegação entre seções usa pills `rounded-full` (NÃO tabs `border-b-2`)?** Button `variant ghost/default` + ícone lucide 13px + counter. | `:870-908` · charter anti-pattern "Tabs border-b-2 em vez de pills" |
| **R3** | **Tem KPIs gigantes em grid 4-col, value ≥ `text-3xl tabular-nums`, label `uppercase tracking-widest text-[11px]`?** | `:914-920` · charter "KPI value 36px" |
| **R4** | **Todo status usa a escala warm semântica (`emerald/amber/rose/sky`-50 + -700), nunca cor crua (`bg-gray/red-N`)?** | `:946-964` · `CLAUDE_DESIGN_BRIEFING.md §4` · charter anti-pattern "Cor crua bg-(gray\|red)-N" |
| **R5** | **Cabe em 1280px sem scroll horizontal?** `container mx-auto max-w-7xl px-8` + grids `grid-cols-1 md:grid-cols-2 lg:grid-cols-4`. | `:912` · `:986` · charter UX target "1280px sem scroll horizontal (ROTA LIVRE)" |
| **R6** | **Todo campo vem de `@/Components/ui` (Input/Select/Card/Button) — zero `<select>`/`<input radio\|checkbox>` nativo?** | imports `:23-43` · regra DS `ds/no-native-select` etc. |
| **R7** | **Superfícies usam `<Card>` ou `rounded-lg` (NÃO `rounded-xl`)?** | `:982` (`<Card>`) · `:1375` (`rounded-lg`) — ⚠️ ver drift §4 |
| **R8** | **As ações (Cancelar/Salvar) ficam só no footer sticky, 1× (sem botão duplicado)?** | `:1582` · charter feature "footer sticky" + anti-pattern "botões duplicados" |
| **R9** | **Erro aparece inline no campo (`<FieldError role="alert">`), não em alerta global solto?** Campo colapsado com erro auto-abre. | charter feature US-SELL-010 |
| **R10** | **Espaçamento usa a escala canon: `space-y-6` entre seções · `gap-4` entre cards · `p-6` interno?** | `:912` `:915` `:979` · `CLAUDE_DESIGN_BRIEFING.md §espaçamento` |

**Placar:** 10/10 = canon. 8-9 = 1 round de ajuste. <8 = volta pro Claude Design.

---

## 3. Como usar (3 modos, nenhum exige talento de design)

### Modo A — instruir o Claude Design (o que destrava você)
Pare de escrever briefing do zero. O prompt passa a ser:
> *"Faça a tela `<X>` **idêntica à `Sells/Create`** (mesma estrutura: header sticky 24px → pills rounded-full → 4 KPIs gigantes → cards `@/ui` → footer sticky). Mude **só**: [campos/seções específicos de X]. Siga as 10 regras do `GOLDEN-REFERENCE.md`."*

Você não instrui design — você **aponta pro exemplo**.

### Modo B — você julgando (checklist de 2 min)
Abra o screenshot, rode as 10 perguntas da §2. Não pergunte *"ficou bom?"*. Pergunte *"R1 sim? R2 sim?..."*. Objetivo, não estético.

### Modo C — few-shot (ensina por contraste)
No prompt, anexe **2 exemplos**: o certo (`Sells/Create`) + o errado ([`LICOES_F3_FINANCEIRO_REJEITADO.md`](LICOES_F3_FINANCEIRO_REJEITADO.md) — 21 anti-padrões). Dois exemplos contrastados ensinam mais que 800 linhas de regra.

---

## 4. Onde a própria golden tem drift (honestidade — corrija ao copiar)

A `Sells/Create` é ouro em **estrutura/densidade/fluxo**, mas tem **1 drift** que o `ds/*` já pega — **não copie isto, já corrija:**

- ⚠️ **KPI cards usam `rounded-xl`** (`:916,924,932,942`) → o canon é `rounded-lg` ou `<Card>` (regra `ds/no-rounded-xl`). Ao replicar, troque `rounded-xl` → `rounded-lg`.

Os cores warm de status (`bg-emerald-50` etc.) são **canon hoje** (briefing §4); a evolução futura pra `<Badge variant>` é "tipo 2" da `MATRIZ_MIGRACAO_DS.md` (baseline absorve, migra por último) — **não bloqueia** copiar.

---

## 5. Validação contra o estado-da-arte (2026) + upgrades incorporados

Esta ideia foi confrontada com as melhores práticas. Veredito honesto: **conceito certo, forma fraca em 2 pontos.**

| Minha peça | Equivale a | Nota | Por quê / upgrade |
|---|---|---:|---|
| Tela-ouro única | **Golden Path / Paved Road** (Spotify/Netflix) | **6/10** | Conceito é exatamente o nome da indústria. MAS *"golden path vira sistema quando torna a escolha certa mais barata que qualquer alternativa — doc não, scaffold sim"*. **Upgrade:** o golden precisa virar **scaffold executável** (`make:page` que cospe o esqueleto da Sells/Create), não só este doc. Doc é o começo, não o fim. |
| 10 regras binárias | **Heurísticas de Nielsen** (10, binário 0/1) | **9/10** | Validado: avaliação heurística binária é padrão desde 1994. **Upgrade:** cruzar as 10 com as 10 de Nielsen pra achar buraco de **interação** (minhas são visual-estruturais; faltam "visibilidade de status/loading", "prevenção de erro", "desfazer"). |
| Juiz = Larissa, tarefa cronometrada | **NN/g Task Success Rate** + **efeito estética-usabilidade** | **10/10** | "Success rate é a métrica de usabilidade mais simples" (NN/g). E o *efeito estética-usabilidade* prova: bonito **parece** usável → por isso o Wagner não deve confiar em "tá bonito". **Upgrade:** medir os 3 — sucesso (0/1) + tempo + 1 pergunta de facilidade (SEQ). |
| Few-shot (certo+errado+diff) | **Few-shot CoT pra UI-gen** (arXiv 2025) | **7/10** | 2 achados viram regra: (1) *"o primeiro exemplo domina, os seguintes têm retorno decrescente"* → o golden TEM que ser o exemplo #1; (2) *"LLM opera por texto, designer pensa visual → saída perde direção estilística"*. **Upgrade: few-shot VISUAL** — anexar **screenshot** do golden + do rejeitado, não só link de código. Texto sozinho sempre perde. |
| Promotion (golden evolui) | **Spotify Encore promotion rules** | **8/10** | Federado validado. Mantém. |
| Condensar 8 docs em 1 | **Unified specification** (SpecifyUI) | **8/10** | "Spec unificada é a fundação da geração consistente." O charter já é metade disso. |
| *(faltava)* enforcement automático | **Visual regression baseline** (Percy/Applitools 2025) | **❌ gap** | O golden não pode ser só aspiracional. **Nova ideia:** snapshot da Sells/Create vira **baseline de visual-regression no CI** — todo PR compara contra ela, e o AI visual review filtra falso-positivo. Aí o golden deixa de ser doc e vira **gate** (conecta no ratchet que já estava solto). |

### Os 5 upgrades que entram na prática
1. **Golden executável** — `make:page-from-golden` (scaffold), não só este doc. *(torna copiar mais barato que inventar)*
2. **Few-shot VISUAL** — screenshot golden + screenshot rejeitado no prompt. *(LLM perde intenção visual via texto)*
3. **Cross-map Nielsen** — mapear as 10 regras §2 ↔ 10 heurísticas, plugar buracos de interação.
4. **Métrica tripla do juiz** — success rate (0/1) + tempo + SEQ. *(beware efeito estética-usabilidade)*
5. **Baseline de visual-regression** — a Sells/Create vira snapshot no CI; o golden vira gate, não sugestão.

---

## 6. Limite / próximo passo

Este doc resolve *"não sei instruir o Claude Design"* (Modo A) + *"não sei julgar"* (Modo B). O que **ainda não** está feito (oferta seguinte):
- **(b)** condensar os 8 docs (`CLAUDE_DESIGN_BRIEFING` + `PROTOCOL` + `CLAUDE_COWORK_PRIMER` + …) num **único** briefing de 1 página que você cola e pronto;
- **(c)** template few-shot pronto (certo + errado + diff) pro próximo prompt.

> Quando uma tela nova marcar 10/10 nas regras §2 **e** for melhor que a Sells/Create num eixo, ela vira a nova golden (promotion). A tela-ouro evolui — não é eterna.
