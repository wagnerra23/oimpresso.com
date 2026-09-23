---
sessao: "_saida-08"
thread: "08 · StatusBadge: tirar o fill sólido (AP7)"
dono: "[CL]"
data: 2026-09-23
prefixo_tocado: resources/js/Components/shared/StatusBadge.tsx · tests/Feature/Ds/CanonStatusTokensTest.php
base_lida: wagnerra23/oimpresso.com@main 1061dbf2e (a thread foi escrita em 68a938c5ec5f — linhas remedidas na abertura)
---
# _saida-08

## 1 · Feito

- `shared/StatusBadge.tsx`: **36** entradas `variant:'default'` + `className:'bg-<tom> text-<tom>-foreground …'`
  viraram a variante soft do tom — `success` / `warning` / `info` —, sem `className`.
  **19** entradas `variant:'destructive'` (fill vermelho) viraram `danger` (par soft).
  Contagem saída do próprio script de reescrita (`{"fill":36,"destr":19}`), conferida por
  `grep`: zero `foreground` e zero `'destructive'` restam fora dos comentários.
- `animate-pulse` (urgente · Risco Crítico) ficou: é movimento, não fill.
- `ui/badge.tsx` **não** tocado (`nao_toca`). As variantes soft já existiam lá desde o #2641.

## 2 · Fora do prefixo, e por quê

`tests/Feature/Ds/CanonStatusTokensTest.php` **exigia** o fill sólido
(`->toContain('bg-success text-success-foreground hover:bg-success/90')`). Pela regra de
precedência, o perdedor se corrige no mesmo PR: o teste foi **reescrito** (não desabilitado)
para travar o par soft e proibir `text-*-foreground` e `variant: 'destructive'` no mapa.

## 3 · Não feito (declarado)

- ~~`secondary` ficou~~ — **resolvido no mesmo PR**: [W] 2026-09-23, *"secondary vira neutral
  também"*. As 9 entradas `secondary` (fill cinza `bg-secondary`) viraram `neutral`
  (`bg-muted text-muted-foreground border-border`). `outline` fica — não tem fill.
- Não rodei vitest/typecheck local (worktree sem `node_modules`); o tipo `Variant` é derivado do
  `badgeVariants`, então variante inexistente quebraria o typecheck do CI.

## 4 · Raio

15 Pages importam `@/Components/shared/StatusBadge` (grep, 2026-09-23). Toda badge de estado
colorida nelas muda de fill para soft — é o efeito pedido, não um efeito colateral. Veredito
visual: `visual-regression` do CI, com os diffs decodificados por `scripts/tests/snap-diff.mjs`
(registrado no corpo do PR).

## 5 · Prova

`placar.mjs --thread 08`: a prova `nao_contem` passa (a string de fill sumiu do arquivo).
