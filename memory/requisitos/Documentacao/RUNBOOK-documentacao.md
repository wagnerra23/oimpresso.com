---
id: requisitos-documentacao-runbook-documentacao
title: "RUNBOOK — superfície /documentacao (4 telas Inertia: Index · Doc · Busca · Programa)"
module: Documentacao
tela: "Documentacao/Index · Documentacao/Doc · Documentacao/Busca · Documentacao/Programa"
owner: W
status: rascunho
last_validated: "2026-08-06"
preconditions:
  - "Usuário autenticado com business em sessão (stack completo — ver §5)"
  - "memory/GUIA-DO-SISTEMA.md presente no deploy"
  - "Corpus mcp_memory_documents acessível para busca e documento"
steps:
  - "Abrir /documentacao e confirmar rail derivado + TOC"
  - "Trocar de lente e conferir ordinais contínuos"
  - "Buscar termo curto (MCP) e conferir resultado"
  - "Abrir documento e conferir link relativo resolvido"
  - "Abrir /documentacao/programa e conferir ondas com estado do MCP"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0093-multi-tenant-isolation-tier-0
  - 0070-jira-style-task-management-current-md-removed
spec_ref: memory/requisitos/Documentacao/SPEC.md
---

# RUNBOOK — superfície `/documentacao` (Inertia/React)

> ⚠️ **NUNCA EXECUTADO.** As telas ainda não existem. O `last_validated` carrega a **data de
> criação** porque o schema exige o campo — não houve execução que batesse. A primeira execução
> real substitui a data. Ler este campo como validação aqui seria falso.
>
> **Por que um RUNBOOK para quatro telas.** As quatro compartilham controller, rail derivado,
> corpus e middleware — quatro arquivos quase idênticos seriam cópia, e cópia drifa
> ([ADR 0256](../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)). Os quatro
> charters apontam para cá via `related_runbook`.

## 1. Contexto

| Rota | Página Inertia | Substitui |
|---|---|---|
| `GET /documentacao` | `Documentacao/Index.tsx` | `resources/views/documentacao/index.blade.php` |
| `GET /documentacao/buscar` | `Documentacao/Busca.tsx` | `busca.blade.php` |
| `GET /documentacao/{slug}` | `Documentacao/Doc.tsx` | `doc.blade.php` |
| `GET /documentacao/programa` | `Documentacao/Programa.tsx` | — (nova) |

- **Controller:** `App\Http\Controllers\DocumentacaoController` (610 linhas no legado) + o controller
  da tela nova do Programa.
- **Contrato de migração:** [ANTI-REGRESSAO-documentacao-blade.md](ANTI-REGRESSAO-documentacao-blade.md)
  — 26 asserções `AR-DOC-NNN`. Nenhuma pode ser perdida sem Non-Goal aprovado por [W].

## 2. Personas

Quem lê documentação do sistema: [W], [F], [M], [L], [E] e quem entra novo no time. **Não** é tela
de cliente final — a Larissa (ROTA LIVRE) não usa esta superfície. A meta de 1280px continua
valendo por ser a régua de UI do projeto, não por causa dela.

## 3. Ordem das rotas — a pegadinha central

`/documentacao/{slug}` usa regex `[A-Za-z0-9._-]+` e **engole qualquer rota irmã registrada depois**.
`buscar` e `programa` casam nessa regex. O comentário logo acima de `Route::get('/documentacao/buscar'`
em `routes/web.php` já documenta o incidente com `/buscar` (relocalize com
`git grep -n "documentacao/buscar" routes/web.php` — ref de linha apodrece no primeiro refactor). **Registrar sempre as rotas nomeadas ANTES do `{slug}`** (AR-DOC-020,
AR-DOC-030).

Sintoma quando erra: a busca ou o Programa viram "documento não encontrado" (404), não erro de rota.

## 4. Rail derivado — não é manifesto

O rail sai do **frontmatter dos arquivos em disco** (`File::glob(memory/reference/*.md)` +
`nav_group` / `nav_order` / `lente`), calculado a cada acesso. Três invariantes que costumam ser
quebradas por quem "otimiza" (AR-DOC-010 a AR-DOC-013):

1. **Nada de JSON gerado e commitado** — seria cópia da estrutura, e cópia drifa.
2. **Opt-in** — doc sem `nav_group` não entra; perdeu o campo, sumiu.
3. **O ordinal numera a ordem visível na lente**, não `nav_order`. Se vier do campo, filtrar a
   lente deixa buracos (1, 3, 7) e o leitor acha que sumiu conteúdo.

## 5. Middleware — mudou nesta migração

O grupo legado era `auth`-only, deliberadamente sem `SetSessionData`/`AdminSidebarMenu`. O
`AppShellV2` lê `shell.menu` e `shell.cockpit` de shared props alimentadas pela **sessão**
(`HandleInertiaRequests`), então a migração adota o stack completo, no precedente de `/modulos`:

```
web · setData · auth · SetSessionData · language · timezone · AdminSidebarMenu
```

**Consequência aceita por [W] em 2026-08-06 (AR-DOC-050):** quem não tem business em sessão deixa
de ler a documentação. O comentário em `routes/web.php` que afirma o contrário precisa ser
corrigido no mesmo PR — doc que contradiz o código é instrução ativa pra regressão.

## 6. Corpus, fallback e os três estados de falha

| Situação | Comportamento correto | Item |
|---|---|---|
| `GUIA-DO-SISTEMA.md` ausente no deploy | **503 nomeando o arquivo** | AR-DOC-002 |
| Corpus inacessível + rota de busca | estado `indisponivel`, **HTTP 200** — não finge resultado vazio nem dá 503 | AR-DOC-021 |
| Corpus inacessível + rota de documento | **503** | AR-DOC-031 |
| Slug inexistente ou de tipo fora da documentação | **404 honesto** | AR-DOC-032 |

Os três são diferentes de propósito. Colapsar em um só é regressão.

## 7. Markdown é convertido no servidor

`paraHtml()` roda no PHP e o React recebe HTML já sanitizado (AR-DOC-003). **Não** instalar parser
de markdown no cliente: mudaria a superfície de ataque e duplicaria a regra de resolução de link
relativo, que depende de saber a pasta de origem do documento (AR-DOC-033).

## 8. A tela do Programa — as duas fontes

`Documentacao/Programa.tsx` cruza duas fontes que markdown sozinho não cruza:

- **`TrilhaDParser`** — lê a § Trilha D de `_Governanca/programa-ondas/PLANO-MESTRE.md`. Seções
  reais: **D.1** camadas · **D.2** onde o estado vive · **D.3** ondas D0–D10 · **D.4** ciclo de 11
  estações · **D.5** caminho por tipo · **D.6** batimento · **D.7** DoD. **Não existe D.8.** O
  parser não inventa campo: o que não está no plano volta vazio, e se o plano mudar de forma ele
  falha alto.
- **`EstadoDasOndas`** — projeção das tasks MCP (`parent_plan=programa-ondas`), que injeta
  `todo|doing|done`. Estado **nunca** é chumbado no markdown nem no `.tsx`
  ([ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md)). Sem MCP, o
  serviço devolve **indisponível** e a tela renderiza sem estado.

## 9. Tier 0 — invariantes

- Nenhum payload das quatro telas carrega segredo, token ou host (AR-DOC-051).
- A superfície é **read-only**: nada nela dispara mutação. Marcar onda ou DoD pela UI é Non-Goal.
- `business_id` não é filtro de conteúdo aqui (o acervo é do sistema, não do tenant) — mas passa a
  ser **pré-condição de acesso** por causa do §5.

## 10. Quando esta superfície quebra — sintomas

| Sintoma | Causa provável |
|---|---|
| Busca ou Programa dá 404 de "documento não encontrado" | rota registrada depois do `{slug}` (§3) |
| Rail vazio | `nav_group` ausente nos arquivos, ou glob apontando pra pasta errada |
| Ordinais com buraco (1, 3, 7) | alguém trocou o ordinal derivado por `nav_order` |
| Link relativo dentro de documento dá 404 | base de resolução voltou a ser `memory/` em vez da pasta do doc |
| Shell sem menu | rota fora do stack do §5 |
| Onda mostrando `doing` sem MCP | estado chumbado — violação do §8 |

## 11. Smoke (R1 — evidência, não narração)

Após o deploy, com sessão válida, capturar status literal de cada rota e o estado das seis
condições do §6, e preencher a coluna de verificação da lista anti-regressão. Screenshot em 1280 e
1440 para [W] aprovar antes do charter sair de `draft`.

## 12. Fases MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md))

- **F1 PLAN** — este RUNBOOK + a lista anti-regressão + SPEC. ✅ feito em 2026-08-06.
- **F1.5 gate visual** — pendente. Não há protótipo Cowork desta superfície no repositório
  (verificado: `documentacao-page.jsx` não existe); o desenho é gerado a partir do DS canon.
- **F2 backend baseline** — controller devolvendo props, com os estados de falha do §6 cobertos.
- **F3 frontend** — as 4 páginas.
- **F4 QA** — §11 + os UC dos `.casos.md`.
- **F5 cutover** — remoção das views Blade, decisão de [W].

## 13. Refs

- [ANTI-REGRESSAO-documentacao-blade.md](ANTI-REGRESSAO-documentacao-blade.md) — contrato de paridade
- [PLANO-MESTRE § Trilha D](../_Governanca/programa-ondas/PLANO-MESTRE.md) — fonte do Programa
- [GOV-PROGRAMA-DOCUMENTACAO.md](../../reference/GOV-PROGRAMA-DOCUMENTACAO.md) — leitura humana da trilha
- [Onda 0d](../_Governanca/programa-ondas/onda-0-fundacao/0d-paridade-migracao.md) — gate automático de paridade, ainda `proposto`
