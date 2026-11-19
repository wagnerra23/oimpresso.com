---
id: reference-feedback-pageheader-largura-total
name: feedback-pageheader-largura-total
description: Reclamação recorrente do [W] em todos os módulos — o PageHeader tem de seguir o canon (largura total, primeiro filho da tela, abas pelo componente canon). Conferir antes de declarar qualquer tela pronta.
metadata:
  type: feedback
---

# Feedback canon — PageHeader fora do padrão é a reclamação recorrente do [W]

**Regra:** o PageHeader de toda tela segue o canon de [ADR 0189](../decisions/0189-pageheader-canon-v3-1-cadastro-roxo.md)
e [ADR 0190](../decisions/0190-primary-button-roxo-universal-295.md), sem variação por módulo:

1. **Largura total.** O `<PageHeader>` é o primeiro filho da tela, logo abaixo do `AppShellV2`. Nunca
   dentro de um container estreito (`max-w-* mx-auto`): isso encolhe o header, centraliza o título e
   corta a linha divisória no meio da tela. Se o CORPO precisa de largura máxima, o `max-w` vai no
   corpo, abaixo do header.
2. **Abas pelo componente canon** (`PageHeaderTabs`), não `<nav>` com botões escritos à mão.
3. **Primary roxo universal** via `PageHeaderPrimary`, um só por tela.
4. **Componente canon** `@/Components/PageHeader` (o `@/Components/shared/PageHeader` é deprecado).

**Why:** [W] 2026-11-19, ao revisar `/perfil`: *"page header é minha reclamação constante em todos os
módulos"*. O defeito repete entre módulos porque cada tela monta o próprio wrapper; o componente está
certo, o uso não.

**How to apply:** ao criar, migrar ou revisar tela, abrir a tela em produção e conferir os 4 itens
acima ANTES de declarar pronto. Comparar com uma tela de referência que monta o header em largura
total (ex.: `resources/js/Pages/Produto/Index.tsx`).

**O que o [W] olha:** posição do header e tamanho de fonte. O alvo vem do próprio componente
(`resources/js/Components/PageHeader/PageHeader.tsx`, padding `pt-6 px-6 pb-3.5`): título começa
**24px à direita e 24px abaixo** do início da área de conteúdo, h1 **22px peso 600** (decisão [W]
D-PH-0923), subtítulo **12px**. Abas e botão primary têm de ter **um só** tamanho em todas as telas.

**Medição em produção, 2026-11-19** (Chrome, viewport 2560px, área de conteúdo começa em x=260; estilo
computado, mesma sonda em todas as telas):

| Tela | Header x / largura | Título x,y | h1 | Abas (fonte/altura) | Primary (fonte/altura) |
|---|---|---|---|---|---|
| `/products` | 260 / 2300 | 284, 24 | 22px · 600 | — | 14px / 32 |
| `/sells` | — | 284, 24 | 22px · **700** | 13.5px / 34 | — |
| `/cliente` | 284 / 2237 | 308, **40** | 22px · **700** | 13px / 36 | 12.5px / 32 |
| `/financeiro/contas-receber` | **847 / 1112** | **871, 48** | 22px · 600 | 13px / 36 | — |
| `/financeiro/categorias` | **918 / 984** | **942, 48** | 22px · 600 | 13px / 36 | — |
| `/perfil` | **950 / 920** | **974**, 24 | 22px · 600 | 12.5px / 32 | 12.5px / 32 |

Leitura: 4 posições diferentes de header, 2 pesos de h1, 3 tamanhos de aba e 3 de primary. Só
`/products` está no alvo de posição. O valor único de aba/primary ainda precisa ser fixado contra o
protótipo (`design-diff.mjs`), não escolhido no olho. ⚠️ Na coluna Primary o Financeiro foi omitido:
a sonda casou a aba ativa (mesmo roxo), não o botão.

**Candidatas medidas em 2026-11-19** (telas que usam `<PageHeader>` e têm `max-w-* mx-auto` no
arquivo — 17 de 162; o `grep` não prova que o wrapper envolve o header, conferir uma a uma):

```bash
git grep -l "<PageHeader" -- 'resources/js/Pages/**/*.tsx' | xargs grep -l "max-w-[0-9a-z]* mx-auto"
```

Esse número é retrato do dia: re-rode o comando em vez de citá-lo.

Relacionado: skill `pageheader-canon` · [feedback-drawer-760-canon-style](feedback-drawer-760-canon-style.md).
