---
id: modules-cms-resources-js-pages-admin-sitedetails-index-charter
page: /cms/site-details
component: Modules/Cms/Resources/js/Pages/Admin/SiteDetails/Index.tsx
related_prototype: "n/a (formulário de configuração; fonte escrita em prototipo-ui/cowork/Wagner/cowork-inbox/cms/SiteDetails.charter.md)"
runbook: memory/requisitos/Cms/RUNBOOK-admin-content.md
owner: wagner
status: draft
last_validated: "2026-09-24"
parent_module: Cms
related_adrs: [93, 104]
tier: B
charter_version: 1
related_us: [US-CMS-004]
---

# Page Charter — /cms/site-details (DRAFT · fase 4a)

> **Status:** draft. Aplicação, Contato, Redes sociais e Integrações são Inertia (thread Cms/01,
> fase 4a); Estatísticas, Perguntas frequentes, Chat e Botões seguem na tela anterior
> (`?legado=1`) até a fase 4b. Casos: [`Index.casos.md`](Index.casos.md).
> O charter completo proposto pelo [CC] (Non-Goals, Anti-hooks, regras S1–S6) está em
> `prototipo-ui/cowork/Wagner/cowork-inbox/cms/SiteDetails.charter.md` e **aguarda [W]**.

## Mission

Um lugar para os dados que o site público exibe e usa: quem recebe os contatos, marca, telefones,
e-mails, redes e códigos de medição.

## Goals — Features (faz, nesta fase)

- Um formulário, um POST, só com as chaves das seções desta tela — a gravação é por chave, então
  as seções que ficaram na tela anterior não são apagadas.
- Campo vazio esconde o bloco no site (dito na interface).
- Códigos de medição e CSS/JS personalizado valem só no site público, nunca no painel.

## Pendências antes de `status: live`

- [ ] [W] aprova Non-Goals + Anti-hooks do charter completo do [CC]
- [ ] Fase 4b (Estatísticas, Perguntas frequentes, Chat, Botões) e o fim do `?legado=1`
- [ ] [W2] aprova screenshot 1280/1440 em produção
