---
id: requisitos-fiscal-config-gap
tela: Fiscal/Config (/fiscal/config)
prototipo: prototipo-ui/cowork/fiscal-subpages.jsx
tela_viva: resources/js/Pages/Fiscal/Config.tsx
gerado_em: 2026-08-28
comparacao: memory/requisitos/Fiscal/fiscal-config-visual-comparison.md
---

# GAP-SPEC — Fiscal/Config

| Parte | Estado no vivo | Ação |
|---|---|---|
| Certificado e regime | Card do certificado na região ancorada; regime e tributação default existem FORA dela (Config.tsx:469-488); "Envio de documentos" não existe no arquivo | **Decidir.** Dos 4 cards do protótipo (fiscal-subpages.jsx:257-288), 1 não existe no Config.tsx ("Envio de documentos", 0 ocorrências em 573 linhas) e 2 existem fora da região ancorada (regime :469-470, tributação default :483-488). Construir o que falta, reancorar a região, ou rejeitar por escrito. |
