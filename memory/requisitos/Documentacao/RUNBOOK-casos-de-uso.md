---
id: requisitos-documentacao-runbook-casos-de-uso
title: "RUNBOOK — /documentacao/casos-de-uso (UC-FLX derivados de CasosDoPrototipo)"
module: Documentacao
tela: Documentacao/CasosDeUso
owner: W
status: rascunho
last_validated: "2026-10-20"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
spec_ref: memory/requisitos/Documentacao/SPEC.md
---

# RUNBOOK — `/documentacao/casos-de-uso`

> Tela nova (não migra Blade). O compartilhado de `/documentacao` vive em
> **[RUNBOOK-documentacao.md](RUNBOOK-documentacao.md)**.

## Rota

`GET /documentacao/casos-de-uso` → `Inertia::render('Documentacao/CasosDeUso')`.
Declarada **acima do `{slug}`** no grupo (mesmo motivo de `/programa`: `casos-de-uso` casa a regex
do slug e seria engolido como documento).

## A fonte — e por que não há texto escrito à mão

O catálogo de fluxos tem 38 casos de uso (`UC-FLX-01..38`). Eles aparecem aqui **derivados em
runtime** de `App\Domain\Fsm\Modelos\CasosDoPrototipo::CASOS` — a mesma constante que a aba
**Casos de uso** de `/fluxos` consome, exportada por máquina de
`prototipo-ui/cowork/Wagner/fluxos-data.jsx` e comparada com ele por `CatalogoModelosFluxoTest`.

Nada é copiado para markdown nem para o `.tsx`: id, título, processo, etapa, regra, estado e US vêm
do payload. O rótulo do estado vem de `CasosDoPrototipo::ESTADOS`. Mudou o protótipo → exporta de
novo → a página muda no próximo acesso (ADR 0256: derivado e enforçado sobrevive).

## Artefatos navegáveis (US → SPEC, caso → `.casos.md`)

A prop deferred `artefatos` sai de `App\Services\Documentacao\RastroDosCasos`:

- **US → SPEC:** extrai os ids `US-…` do texto da US e acha o `memory/requisitos/<Mod>/SPEC.md` que
  tem `### <id>`. O link não leva âncora: a rota de documento não gera id nos títulos.
- **Caso → `.casos.md`:** varre `resources/js/Pages/**` e `Modules/*/Resources/js/Pages/**` atrás do
  id do caso. Arquivo que **declara** casos próprios com o prefixo `UC-FLX-` (título de seção
  começando pelo id) fica de fora — é colisão de nome, não citação.
- **Link:** `/documentacao/<slug>` quando o arquivo está em `mcp_memory_documents` (o controller
  faz o lookup por `git_path` com a mesma `consultaBase()` da rota de documento e entrega o mapa
  pronto ao service, que assim não depende de `Modules/`); senão o arquivo no GitHub. Os `.casos.md`
  de `Modules/*` caem no GitHub porque o indexador só lê `resources/js/Pages`.
- Varredura em cache de 10 minutos (`doc:rastro:specs`, `doc:rastro:casos`).

## Pegadinhas

- **Não editar `CasosDoPrototipo.php` à mão** — o cabeçalho dele proíbe; mude o `.jsx` e exporte.
- **Estado desconhecido** (chave fora de `ESTADOS`) aparece com a própria chave e tom neutro, nunca
  some da lista — a contagem tem que bater com a constante.
- Conteúdo é global de governança (sem `business_id`, sem PII).
