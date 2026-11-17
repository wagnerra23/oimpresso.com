---
id: requisitos-fiscal-nfe-gap
tela: Fiscal/Nfe (/fiscal/nfe)
prototipo: prototipo-ui/cowork/Wagner/fiscal-page.jsx
tela_viva: resources/js/Pages/Fiscal/Nfe.tsx
gerado_em: 2026-08-28
comparacao: memory/requisitos/Fiscal/nfe-visual-comparison.md
---

# GAP-SPEC — Fiscal/Nfe

| Parte | Estado no vivo | Ação |
|---|---|---|
| Filtros de NF-e/NFC-e | Filtros presentes; select "Filtrar por tipo" e radiogroup de densidade ausentes | **Decidir.** A região do protótipo (fiscal-page.jsx:459-481) traz o select "Filtrar por tipo" e o radiogroup de densidade; nenhum dos dois existe no Nfe.tsx. Não são mocks. Construir ou rejeitar por escrito. |

> **Re-medido em 2026-11-06:** o radiogroup de densidade existe (`DensidadeToggle` em `Nfe.tsx`); o select "Filtrar por tipo" continua ausente. A tabela acima é o retrato de 2026-08-28.
