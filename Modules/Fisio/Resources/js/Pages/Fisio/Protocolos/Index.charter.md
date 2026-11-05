---
id: modules-fisio-pages-fisio-protocolos-index-charter
page: /fisio/protocolos
component: Modules/Fisio/Resources/js/Pages/Fisio/Protocolos/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/prototipos/fisio/Protocolos.dc.html
owner: wagner
status: draft
last_validated: "2026-11-04"
parent_module: Fisio
related_adrs: [93, 358, 437, 438]
alcance:
  rota: /fisio/protocolos
  rota_nome: fisio.protocolos.index
  permission: fisio.access
  menu_hook: Modules/Fisio/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: fisio_module
tier: B
charter_version: 2
---

# Page Charter — /fisio/protocolos (DRAFT · protocolos-modelo)

> Nasce no Lote 2 da Fisio (US-FISIO-006; `etapas/01-biblioteca.md` B-09). Casos: [`Index.casos.md`](Index.casos.md).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Mostrar ao fisioterapeuta os protocolos que ele pode usar — os modelos do produto e os da clínica — com os exercícios e a dose de cada um, e deixá-lo copiar um modelo para a clínica e editar a cópia.

## Goals — Features (faz)

- Lista com os modelos do produto (só leitura) e os protocolos da empresa da sessão; protocolo aberto à direita — UC-FPR-01.
- Exercícios do protocolo com nome e dose; exercício que a clínica não enxerga não aparece — UC-FPR-02.
- Abre com `fisio.access`; copiar e editar exigem `fisio.editar` — UC-FPR-03.
- Copiar o modelo para a clínica; o modelo nunca muda — UC-FPR-04. Protocolo de outra clínica responde 404 — UC-FPR-05.
- Aviso fixo "Modelo de referência. A decisão clínica é do fisioterapeuta." (ADR 0437 §5, P58).

## Non-Goals — Features (NÃO faz)

- ❌ Escolher protocolo para o paciente: aplicar é ato do profissional, no editor de programa da ficha (ADR 0437 §5).
- ❌ Passar de fase sozinho: a passagem é sempre do profissional (P58).
- ❌ Editar modelo do produto: o conteúdo é da responsável técnica e chega por seed revisado (403).

## Diferença para o protótipo (registrada, não inventada)

O protótipo desenha diretriz citada (título, ano, grau), fases com critério de passagem, versão de crise e alerta de dor por protocolo. O schema atual de `fisio_protocols` tem só nome, região, itens e observações; os campos de B-09 (`guideline_ref`, `phases`, `status`) ainda não existem. A tela mostra o que o banco tem e não simula o resto.

## Refs

- ADR 0437 · ADR 0438 · `memory/requisitos/Fisio/etapas/01-biblioteca.md` §3.4 e B-09 · `memory/requisitos/Fisio/protocolos/11-fibromialgia.md` · UI-0013
