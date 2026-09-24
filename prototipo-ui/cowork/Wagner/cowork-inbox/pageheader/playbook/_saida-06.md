---
sessao: "_saida-06"
thread: "06 · Título da Caixa Unificada 14px → 22px"
dono: "[C]"
data: 2026-09-24
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main a41ad0086
---
# _saida-06

## Entregue
`Modules/Whatsapp/Resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx` — o `<h1>Atendimento</h1>`:
- `text-[14px]` → `text-[length:var(--fs-7)]` (token do DS, 22px; sem valor arbitrário);
- `tracking-[-0.015em]` acrescentado — vem da mesma âncora (`prototipo-ui/cowork/Wagner/styles.css` `.os-page-h-l h1`: `--fs-7` · 600 · -0.015em);
- peso segue `font-semibold` (600), igual à thread 04.

**Não adotei o `<PageHeader>` canon.** Ele traz `pt-6 px-6 pb-3.5` + `min-h-[60px]` + `border-b`, e o header da Caixa é uma faixa compacta (`px-1`) numa tela de altura fixa em 3 colunas. Trocar mudaria altura e padding da tela inteira — é o caso de "se não couber no layout, usar `var(--fs-7)`" do `06-caixa-h1.md`.

Fora do prefixo, por obrigação da precedência (o charter perdedor é corrigido no mesmo PR): `Index.charter.md` ganhou uma linha na tabela de decisões registrando a D-PH-0923 como **exceção explícita** à LEI "não repintar" de 2026-06-18, só para o h1; `charter_version` 21 → 22.

## Provas
1. Prova do índice (`nao_contem "text-[14px]"` no `Index.tsx`): o arquivo não tem mais ocorrência.
2. A prova é válida: `git log --reverse -S` mostra que o `text-[14px]` do h1 entrou em 2026-08-12 (`47be7554d`), antes do índice (2026-09-23) — ela prova a mudança.
3. **Tamanho computado, antes e depois.** O Tailwind 4 (`@tailwindcss/node` do repo) compilou a classe literal do h1 dos dois arquivos, com os tokens de `resources/css/tokens/_generated-foundations-light.css`, e o Chromium (Playwright) leu o `getComputedStyle`:

| | classe | font-size | peso | letter-spacing | line-height |
|---|---|---|---|---|---|
| antes | `font-semibold text-[14px] leading-tight truncate` | **14px** | 600 | normal | 17.5px |
| depois | `font-semibold text-[length:var(--fs-7)] tracking-[-0.015em] leading-tight truncate` | **22px** | 600 | -0.33px | 27.5px |

O "antes" em 14px é o controle positivo: a sonda devolve o valor já medido no runtime em 2026-09-08 (`CaixaUnificadaV4-visual-comparison.md`, D4 DIVERGE 14px × 22px).

## Ausente / residual declarado
- **Não medi com o `design-diff` na tela renderizada.** Não havia app local no ar, e o staging roda o `main`, não esta branch. A medição acima prova que a classe resolve para 22px, mas **não** prova a tela inteira (outra regra do CSS do app, se existisse, poderia vencer). Varri `resources/css/` por regras de `h1` e nenhuma alcança esta tela. Falta rodar o `design-diff-lote.mjs --tela Atendimento/CaixaUnificada/Index` depois do deploy, e o visual-regression do CI no PR.
- **`line-height`**: a âncora usa 1.2 e aqui continua `leading-tight` (1.25). Não mexi porque o pedido da thread era o tamanho. Diferença de 0,05 × 22px ≈ 1,1px.
- **Baseline visual** da Caixa (`states: [default, dark]`) muda no h1. É esperado; decodificar com `scripts/tests/snap-diff.mjs`.
