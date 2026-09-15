---
page: /financeiro/unificado
component: resources/js/Pages/Financeiro/Unificado/Index.tsx
related_prototype: public/cowork-preview/Chat.html (design aprovado — NÃO é o -page.jsx do bundle)
bundle_source: financeiro-page.jsx
---
# Unificado (charter de fixture) — prova do charter-first via bundle_source.
Reproduz o bug real (musing-elion 2026-06-30): o bundle nomeia o mockup pela RAIZ do módulo
(financeiro-page), a tela vive numa sub-pasta (Unificado). Nem component-mining nem a heurística
startsWith(dir) casam. `bundle_source:` ancora financeiro-page.jsx → Financeiro/Unificado/Index.tsx
SEM ALIAS. related_prototype (design aprovado) fica intocado — campos separados de propósito.
