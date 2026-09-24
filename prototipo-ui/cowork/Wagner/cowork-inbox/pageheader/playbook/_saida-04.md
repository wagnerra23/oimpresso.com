---
sessao: "_saida-04"
thread: "04 · h1 do PageHeader: padrão 600 (titleWeight default semibold)"
dono: "[C]"
data: 2026-09-23
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main 1061dbf2e
---
# _saida-04

## Entregue
`resources/js/Components/PageHeader/PageHeader.tsx`:
- default de `titleWeight` passa de `'bold'` (700) para `'semibold'` (600); `titleWeight="bold"` vira opt-in;
- `tracking-tight` (-0.025em) → `tracking-[-0.015em]` no h1;
- docblock do topo e da prop atualizados — o texto de 2026-09-21 (opt-in com default 700, decisão #1477) ficou como registro **datado**, não apagado.

Fora do prefixo, por obrigação da cadeia do eixo FORMA (UI-0029: o teste que fixou a forma antiga é **reescrito**, nunca desabilitado):
- `tests/janaAreaHeaderParidade.spec.tsx` — o controle negativo UC-JPAIN-30 travava o default em 700. Reescrito para proteger as duas pontas do contrato novo: sem prop ⇒ `font-semibold` + `tracking-[-0.015em]`; `titleWeight="bold"` ⇒ `font-bold`.

## Provas
1. Prova do índice (`nao_contem "'font-semibold' : 'font-bold'"`): o ternário agora é `titleWeight === 'bold' ? 'font-bold' : 'font-semibold'`.
2. `npx vitest run tests/janaAreaHeaderParidade.spec.tsx` → **6 passed**.
3. Mordida: default revertido para `'bold'` → `AssertionError: default do canon deveria ser 600 (D-PH-0923)` · 1 failed / 5 passed. Restaurado de cópia byte-exata, sha256 igual antes/depois (`92796e35a43952ba`).

## Raio
`git grep -n titleWeight resources Modules` no main 1061dbf2e: **um** consumidor explícito, `Pages/Jana/_components/JanaAreaHeader.tsx:162` (`titleWeight="semibold"`) — não muda (já era 600). Todas as demais telas do canon passam de 700 para 600 e de -0.025em para -0.015em. **Baseline visual mexe em massa** — esperado; decodificar com `scripts/tests/snap-diff.mjs` (Δmax pequeno e uniforme no h1 = peso/tracking; Δ grande fora do header = investigar).

## Ausente / residual declarado
- **Não medido em runtime:** o computed style (600 no browser) não foi medido nesta sessão — jsdom não carrega CSS. Fica para o visual-regression do CI e smoke pós-deploy.
- **`Pages/Jana/Index.casos.md` (~L1690-1715) e `Index.charter.md:256`** descrevem o default 700 e "41 telas não mudam". Estão em `resources/js/Pages/` (`nao_toca` desta thread) e são registro datado de 2026-09-21. Viram stale a partir deste PR — atualizar numa thread da Jana.
- O `titleWeight="semibold"` do `JanaAreaHeader` ficou redundante (igual ao default). Não removido: `nao_toca`.
