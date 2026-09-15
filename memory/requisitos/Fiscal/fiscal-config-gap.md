---
id: requisitos-fiscal-config-gap
tela: Fiscal/Config (/fiscal/config)
prototipo: prototipo-ui/cowork/Wagner/fiscal-subpages.jsx
tela_viva: resources/js/Pages/Fiscal/Config.tsx
gerado_em: 2026-08-28
comparacao: memory/requisitos/Fiscal/fiscal-config-visual-comparison.md
---

# GAP-SPEC — Fiscal/Config

| Parte | Estado no vivo | Ação |
|---|---|---|
| Certificado e regime | Card do certificado na região ancorada; regime e tributação default existem FORA dela (Config.tsx:469-488); "Envio de documentos" não existe no arquivo | **Nada a fazer** - FECHADO em 2026-09-04 (item A5, PR 1/3): "Envio de documentos" foi CONSTRUIDO e a aba `cert` virou um unico `data-contract=fiscal-config-cert-regime` (Config.tsx:371). A analise anterior media 573 linhas e 0 ocorrencias de "Envio de documentos" - era verdade em 2026-08-28, quando este gap foi gerado. Re-medido contra origin/main em 2026-09-14: 900 linhas e 4 ocorrencias. Nao ressuscitar o veredito por re-derivacao desta linha. |
