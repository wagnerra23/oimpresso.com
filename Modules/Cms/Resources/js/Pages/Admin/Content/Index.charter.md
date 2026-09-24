---
id: modules-cms-resources-js-pages-admin-content-index-charter
page: /cms/cms-page
component: Modules/Cms/Resources/js/Pages/Admin/Content/Index.tsx
related_prototype: "n/a (herda PT-01 Lista; o cms-page.jsx do F1 não está no espelho — fonte escrita em prototipo-ui/cowork/Wagner/cowork-inbox/cms/CMS-F1-2026-08-19.md)"
runbook: memory/requisitos/Cms/RUNBOOK-admin-content.md
owner: wagner
status: draft
last_validated: "2026-09-23"
parent_module: Cms
related_adrs: [93, 104]
tier: A
charter_version: 1
related_us: [US-CMS-004]
---

# Page Charter — /cms/cms-page (DRAFT · fases 1–2b = lista + editor + destaques)

> **Status:** draft. Lista, editor e destaques da home são Inertia (thread Cms/01, fases 1–2b).
> Casos: [`Index.casos.md`](Index.casos.md) · RUNBOOK: `memory/requisitos/Cms/RUNBOOK-admin-content.md`.
> O charter **completo** proposto pelo [CC] (Non-Goals, Anti-hooks, 15 regras) está em
> `prototipo-ui/cowork/Wagner/cowork-inbox/cms/Index.charter.md` e **aguarda [W]** — Non-Goals e
> Anti-hooks são dele, não se promovem aqui.

## Mission

Uma tela para todo o conteúdo do site — páginas, blog e depoimentos — dizendo o que está no ar,
o que é rascunho, o que é página de sistema e o que não tem descrição de busca.

## Goals — Features (faz, nesta fase)

- Lista por tipo (`page` · `blog` · `testimonial`) com abas e contadores, ordenada por `priority`
  (vazio no fim).
- Selos em PT-BR: Publicada / Rascunho · Página de sistema · Sem descrição de busca.
- Endereço público da linha (página e blog).
- Excluir só em página livre; página de sistema mostra "Fixa".
- Criar/editar em drawer lateral (PT-02), rótulos por tipo e layout, descrição para buscadores
  derivada no servidor quando vazia, aviso de endereço ao mudar o título.
- Destaques da página inicial editáveis no drawer — e são os que a home de `/` mostra.

## Pendências antes de `status: live`

- [ ] [W] aprova Non-Goals + Anti-hooks do charter completo do [CC]
- [ ] Fases 2–5 do RUNBOOK (editor, recusa de exclusão no servidor, detalhes do site, cutover)
- [ ] [W2] aprova screenshot 1280/1440 em produção
