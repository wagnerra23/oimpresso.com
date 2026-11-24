// Moldura das janelas da lista de produtos — a do `Modal` do Design System
// (`prototipo-ui/design-system/components/Modal/Modal.jsx`), que é o que o protótipo
// `cowork/Felipe/produto-blade.jsx` usa em todas as janelas da vista `lista`.
//
// O `ui/dialog` e o `ui/alert-dialog` trazem a moldura do shadcn (fundo `bg-background`,
// 24px de respiro, cantos `rounded-lg`, título 18px). Medido contra o protótipo em
// 2026-11-23 (`memory/requisitos/Produto/_telas/produto-index-visual-comparison.md`):
// fundo 0.26 × `--surface` 0.30, 24px × 20px, cantos 8px × 12px, título 18px × 17px.
//
// Fica aqui, e não no `ui/dialog`, porque o `ui/dialog` serve outras 31 telas e os tokens
// (`--surface`, `--radius-lg`, `--text`) só existem dentro do `.cockpit`: as janelas desta
// lista montam nele (`container`), boa parte das outras monta no `<body>`, onde estas
// classes pintariam fundo vazio. Toda janela da lista usa as duas constantes, para as
// irmãs não ficarem diferentes entre si.

/** Caixa da janela: fundo, borda, cantos e respiro do `Modal` do DS. */
export const MOLDURA_JANELA =
  'rounded-[var(--radius-lg)] border-[var(--border)] bg-[var(--surface)] p-5 text-[var(--text)]';

/** Título da janela: `600 17px/1.3`, `-.01em`, `--text`, como o `h3` do `Modal` do DS. */
export const TITULO_JANELA = 'text-[17px] leading-[1.3] font-semibold tracking-[-0.01em] text-[var(--text)]';
