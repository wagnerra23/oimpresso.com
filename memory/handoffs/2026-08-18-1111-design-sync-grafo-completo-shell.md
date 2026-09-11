---
date: "2026-08-18"
time: "11:11 BRT"
slug: design-sync-grafo-completo-shell
tldr: "[W] perguntou se o conserto baixaria todas as dependências do oimpresso.com.html; ainda não. O #5910 foi ampliado sobre a rota de payload servido já existente: Cowork+DS fecham HTML/CSS/JS transitivamente, persistem _ds e falham atomicamente. No espelho real, o grafo nomeou somente as quatro ausências conhecidas."
prs: [5910]
decided_by: [W]
next_steps:
  - "Claude/DesignSync autenticado deve servir os dois payloads reais, Cowork + DS; aplicar com `--require-complete-shell`."
  - "Só `GRAFO COMPLETO` seguido de `--preview-ds` exit 0 autoriza edição de produto."
  - "Auto-merge do #5910 pode ser reativado depois deste fechamento."
---

# O payload já derrubava o teto; faltava provar que o shell fechava

## TL;DR

O primeiro conserto impedia aceitar bundle truncado, mas não baixava automaticamente todo o shell.
O fluxo agora usa dois payloads servidos — Cowork + DS — e recalcula o grafo local no lado Code.
Ausência, corrupção ou traversal cancela o lote inteiro antes da primeira escrita.

## Evidência que mudou o escopo

O payload real de 17/08 já transportava **118 arquivos e 3.504.544 bytes** sem o teto de 256 KiB.
Seu manifesto tinha `manifestCount:121`, `fileCount:118` e os três `_ds` apenas em `dsLinked`:
provava o Cowork, mas não entregava o DS. Rodado no novo portão, ele saiu 1, nomeou bundle + dois
CSS ausentes e imprimiu `Nada foi escrito deste lote`.

O grafo contra o espelho real encontrou **124 arquivos alcançáveis, 127 arestas, 8 referências
externas e zero referência insegura**. As únicas ausências foram `_ds_bundle.js` e as fontes mono
400/500/600. Não houve falso positivo sobre os outros 200 arquivos do espelho.

## O que entrou no #5910

- `payload-dependency-graph.mjs`: fechamento iterativo de HTML `src/link/srcset`, CSS
  `@import/url` e JS `import/export/import()/require()/new URL`, incluindo assets literais;
- `aplicar-payload.mjs` aceita vários lotes e o modo `--require-complete-shell`;
- payloads precisam trazer o entry, `missing:[]`, bytes válidos e zero path duplicado/inseguro;
- CDN/URL externa, pacote npm e rota de API são classificados fora do grafo local;
- `_ds/<slug>/**` pousa em `scripts/design-sync/mirror-snapshot/`; base64 vira bytes reais;
- preflight é atômico: um erro significa zero writes;
- protocolo, F3, runbook, skill, hook e painel executável passaram a ensinar os dois payloads.

## Provas

Suites `aplicar-payload`, `cowork-mirror-freshness`, configuração e hooks passaram. Os novos bites
cobrem dois payloads, dependência JS e CSS transitiva, CDN/pacote externo, bundle/CSS/fonte DS,
base64 byte-idêntico, missing declarado e atomicidade. `node --check` e `git diff --check` passaram.
Não há diff em `Modules/` nem `resources/js/Pages/`.

## Estado MCP

DesignSync não estava exposto ao Codex; o login do navegador interno continuava indisponível. A
máquina e o contrato ficaram prontos, mas os quatro artefatos reais não foram fabricados nem
recuperados de cache stale. A próxima sessão autenticada deve produzir os dois payloads e provar o
verde completo.
