---
id: requisitos-fiscal-dfe-gap
tela: Fiscal/Dfe (/fiscal/dfe)
prototipo: prototipo-ui/cowork/fiscal-subpages.jsx
tela_viva: resources/js/Pages/Fiscal/Dfe.tsx
gerado_em: 2026-08-28
comparacao: memory/requisitos/Fiscal/fiscal-dfe-visual-comparison.md
---

# GAP-SPEC — Fiscal/Dfe

| Parte | Estado no vivo | Ação |
|---|---|---|
| Filtros e manifestação | Busca e as 5 chips com paridade exata; a manifestação EM LOTE do protótipo (fx-bulk, fiscal-subpages.jsx:134-142) não existe — o próprio vivo declara "PR seguinte" (Dfe.tsx:140) | **Decidir.** A região declarada do protótipo inclui o bloco fx-bulk (4 botões de manifestação em lote) que o Dfe.tsx não tem — existe manifestação por LINHA (:254-282), não em lote. Construir, encolher o range do protótipo para 127-133, ou rejeitar por escrito. |
