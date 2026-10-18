---
id: modules-fisio-pages-fisio-biblioteca-index-charter
page: /fisio/biblioteca
component: Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/Index.tsx
related_prototype: "n/a (sem vista do Fisio no protótipo Cowork; grade de cards com o DS v6 — Card, Badge, Select e editor em Sheet de 760)"
owner: wagner
status: draft
last_validated: "2026-10-18"
parent_module: Fisio
related_adrs: [93, 358, 437]
related_us: [US-FISIO-004]
alcance:
  rota: /fisio/biblioteca
  rota_nome: fisio.biblioteca.index
  permission: fisio.access
  menu_hook: Modules/Fisio/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: fisio_module
tier: B
charter_version: 1
---

# Page Charter — /fisio/biblioteca (DRAFT · biblioteca de exercícios)

> Nasce na US-FISIO-004 (ADR 0437 · HANDOFF-F1 T-04 · `etapas/01-biblioteca.md`). Casos: [`Index.casos.md`](Index.casos.md).
> O handoff cita o canvas "Prescreve Pro" (tela Biblioteca). Medido em 2026-10-18: `git grep -il prescreve -- prototipo-ui/`
> casa 4 arquivos, todos com o verbo "prescrever", nenhum com o canvas; e o `DesignSync` **não foi consultado** nesta sessão (sem acesso) — então a ausência
> vale só para o git. Até a âncora ser resolvida, a forma segue o DS v6, com o editor em drawer lateral de 760. Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Mostrar ao fisioterapeuta, em grade, os exercícios que ele pode prescrever: os da biblioteca do oimpresso
(revisados pela responsável técnica) e os que a própria clínica cadastrou — filtrando por região e por
equipamento caseiro.

## Goals — Features (faz)

- Globais (`business_id` nulo) + próprios da clínica; nunca os de outra clínica — UC-FBI-01.
- Criar exercício próprio (nome, região, tipo, posição, passos, dose, avisos, equipamento, contraindicações) — UC-FBI-02.
- Editar e remover só o próprio; o global não é editável por ninguém — UC-FBI-03 / UC-FBI-06.
- Filtro por região e por equipamento (inclui "sem equipamento") — UC-FBI-04.
- Conteúdo global só aparece depois da revisão clínica (`publicado`); o seed L0 entra pendente — UC-FBI-05.

## Non-Goals — Features (NÃO faz)

- ❌ Busca por texto — a decisão nº 3 da ADR 0437 §14 não foi aceita por [W] (HANDOFF-F1, ajuste nº 3).
- ❌ Prescrever ou montar programa (é o editor de programa, US-FISIO-006).
- ❌ Protocolos-modelo na grade (`[BACKLOG]` dos casos).

## Refs

- ADR 0437 §5 (não é SaMD: o profissional escolhe) e §12 (revisão clínica antes do seed) · etapa 01 §3.3, §3.8 · UI-0013
