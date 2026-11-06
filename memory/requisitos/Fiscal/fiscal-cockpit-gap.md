---
id: requisitos-fiscal-cockpit-gap
tela: Fiscal/Cockpit (/fiscal)
prototipo: prototipo-ui/cowork/Wagner/fiscal-page.jsx
tela_viva: resources/js/Pages/Fiscal/Cockpit.tsx
gerado_em: 2026-08-28
comparacao: memory/requisitos/Fiscal/fiscal-cockpit-visual-comparison.md
---

# GAP-SPEC — Fiscal/Cockpit

| Parte | Estado no vivo | Ação |
|---|---|---|
| KPIs fiscais | Ribbon e KPIs presentes; as 3 sparklines do protótipo não renderizam (prop recebida, não usada) | **Decidir.** O protótipo tem 3 sparklines no ribbon (fiscal-page.jsx:113-124) e o vivo tem 0 — a prop `sparklines` chega em Cockpit.tsx:126 e nunca é usada, e o charter promete no Goal #2. Construir ou rejeitar por escrito. |

> **Re-medido em 2026-11-06:** a linha "KPIs fiscais" envelheceu: `Cockpit.tsx` renderiza as 3 sparklines com `<RibbonSpark>` (PR #6732, 2026-09-04), e o `fiscal-cockpit-visual-comparison.md` já registra o item como resolvido. A tabela acima é o retrato de 2026-08-28.
