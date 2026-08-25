---
id: requisitos-documentacao-briefing
module: Documentacao
status: parcial
status_nota: "Superfície VIVA em Blade (4 rotas em prod); a migração para Inertia está na F1 — contrato escrito, telas ainda não em main"
updated_at: "2026-08-23"
owner: W
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0329-doutrina-documentacao-de-processo-executavel
---

# BRIEFING — `Documentacao`

> **Função única:** resumo executivo e índice. O BRIEFING aponta para os donos; não recopia SCOPE, SUPERFICIE, SPEC, tópicos ou contratos de tela.
> **Contrato:** `scripts/memory-schemas/briefing.schema.json`.

## O que é

A superfície de **leitura** da documentação do próprio sistema: renderiza `memory/GUIA-DO-SISTEMA.md` e o acervo (`mcp_memory_documents`) em runtime, com busca e uma vista do programa de documentação. Existe para que o time leia a verdade atual do sistema sem abrir o repositório — e para que essa leitura saia **do git**, nunca de uma cópia.

Não é módulo nWidart: vive no núcleo (`app/Http/Controllers/DocumentacaoController.php` + `resources/views/documentacao/`), e por isso não aparece em `Modules/`.

## Estado atual

- **4 rotas vivas, todas em Blade:** `/documentacao`, `/documentacao/buscar`, `/documentacao/programa`, `/documentacao/{slug}`. Conferir o que está registrado: `php artisan route:list --path=documentacao` (runtime é o oráculo, não a leitura do `routes/web.php`).
- **Migração Blade→Inertia (MWART) está na F1.** O contrato existe; as telas não. As Pages, charters, `casos.md` e specs e2e ficaram na branch `claude/documentacao-trio-pages-f1` — entram junto com a F3, porque dependem de baseline de regressão visual gerada no runner canônico e de aprovação visual de [W] (gate F1.5).
- **Contrato de paridade escrito e datado**, com o sha de origem declarado por seção. Contagem de asserções: veja o próprio contrato (não repetida aqui — número restateado em dois docs drifa).
- **Uma divergência aberta**, `AR-DOC-068`: os UC da tela do Programa dizem que o estado da onda vem do MCP; o código em `main` deriva do markdown do plano. Nenhum teste verde arbitra. Decisão de [W].
- **Cobertura de tela:** `npm run screen-coverage:report` e `npm run casos:report` são as portas vivas — não há mapa escrito aqui.

## Portas canônicas

- **Herança geral (componentes/layouts/templates compartilhados):** [`../_Geral/BRIEFING.md`](../_Geral/BRIEFING.md)
- **Requisitos:** [`SPEC.md`](SPEC.md) — `US-DOC-001` (migração) · `US-DOC-002` (tela do Programa)
- **Contrato de paridade:** [`ANTI-REGRESSAO-documentacao-blade.md`](ANTI-REGRESSAO-documentacao-blade.md)
- **Operação:** [`RUNBOOK-documentacao.md`](RUNBOOK-documentacao.md) (visão geral + ordem das rotas) · [`RUNBOOK-index.md`](RUNBOOK-index.md) · [`RUNBOOK-busca.md`](RUNBOOK-busca.md) · [`RUNBOOK-doc.md`](RUNBOOK-doc.md) · [`RUNBOOK-programa.md`](RUNBOOK-programa.md)
- **Programa de documentação (Trilha D):** [`PLANO-MESTRE.md`](../_Governanca/programa-ondas/PLANO-MESTRE.md) — rastreado por `US-INFRA-048`, não duplicado aqui
- **Telas (quando a F3 landar):** `resources/js/Pages/Documentacao/` + charters/casos ao lado das Pages

## Decisões e riscos que exigem atenção

- **A ordem das rotas é contrato.** `/documentacao/{slug}` casa `[A-Za-z0-9._-]+` e engole qualquer rota irmã declarada depois. Rota nova de `/documentacao/*` entra **acima** dela — `AR-DOC-020`/`AR-DOC-030`/`AR-DOC-067`. Errar não dá erro de rota: dá 404 de "documento não encontrado", que custa muito mais pra diagnosticar.
- **A migração muda quem consegue ler.** Hoje o grupo é `auth`-only; sob `AppShellV2` adota o stack completo de sessão. Consequência aceita e registrada em `AR-DOC-050`: quem não tem business em sessão deixa de ler a documentação. Decisão de [W] em 2026-08-06.
- **`/docs` não é esta rota.** Aquele caminho é servido por arquivo estático no servidor e tem precedência sobre rota do Laravel — ver o comentário no `routes/web.php`.

## Próxima ação verificável

F2/F3 da `US-DOC-001`: portar o Controller para Inertia **sobre o `main` atual** (não aplicar o patch de 2026-08-06 — `main` andou +220 linhas nesses arquivos e ganhou `programa()`), trazer o trio da branch `claude/documentacao-trio-pages-f1`, gerar a baseline visual no runner canônico e levar o screenshot para [W].
