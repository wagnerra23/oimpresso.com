---
status: shipped
priority: -
problem: "Promovido para `requisitos/LaravelAI/` em 2026-04-24 — não é mais ideia, é spec formal"
persona: "(ver requisitos/LaravelAI/README.md)"
estimated_effort: "(ver requisitos/LaravelAI/README.md — MVP 2-3 sem; produção 6-8 sem)"
references:
  - memory/requisitos/LaravelAI/
related_modules:
  - MemCofre
  - Officeimpresso
---

# LaravelAI — promovido em 2026-04-24

Esta ideia virou módulo real (status `spec-ready`).

**Spec atual em** [`memory/requisitos/LaravelAI/`](../../LaravelAI/) com:

- README com frase de posicionamento e revenue thesis (R$ 199-599 add-on, multiplier)
- SPEC com 5 user stories core + 10 regras Gherkin
- ARCHITECTURE com Knowledge Graph + RAG + agente conversacional (estende MemCofre)
- 8 ADRs separados (arq/0001-0003 + tech/0001-0003 + ui/0001-0002)
- GLOSSARY (vocabulário IA + grafo + RAG) + CHANGELOG

Decisão estratégica importante: **estende MemCofre** (não duplica) — ver `requisitos/LaravelAI/adr/arq/0002-estende-memcofre-vs-modulo-separado.md`.

Conteúdo histórico desta pasta (evidências de conversas Claude mobile) **mantido aqui** para rastreabilidade. Não editar — evolução acontece em `requisitos/LaravelAI/`.
