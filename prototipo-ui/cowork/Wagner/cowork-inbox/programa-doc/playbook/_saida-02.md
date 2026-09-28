---
sessao: "02"
titulo: Tela do Programa em React/Inertia — recibo
autor: "[C]"
data: 2026-09-28
---
# _saida-02 · Tela do Programa em React/Inertia

## Placar
entregue 1 de 1 prova (`resources/js/Pages/Documentacao/Programa.tsx`, agora a tela real) ·
**smoke visual em produção: pendente** (só depois do deploy) · **baseline de pixel: pendente, decisão [W]** (ADR 0411).

## O que foi feito
- `DocumentacaoController::programa` devolve `Inertia::render('Documentacao/Programa')`. Os parsers da § Trilha D
  (`secaoDoPlano`, `linhasDeTabela`, `estacoesDoCiclo`, `itensDeLista`) ficaram como estavam — mesma fonte,
  mesma falha honesta (503 nomeando arquivo/estrutura). Só a camada de render mudou.
- **Estado de execução vem das tasks MCP** (`parent_plan=programa-ondas`), agrupado pela meta-line `onda:`
  (convenção [W] 2026-09-28, #8018). Prop `estado` em `Inertia::defer` (lê `mcp_tasks`). Onda sem task =
  "sem task"; MCP fora = `disponivel=false` e a tela diz que não sabe; carregando = "…".
- **Removido `execucaoDaTrilha()`** — o defeito `AR-DOC-068` (KPI lido de célula escrita à mão no plano).
- Rota ganhou o stack de sessão **na própria declaração** (`routes/web.php`): mudar de grupo a jogaria
  depois do `{slug}` e ela viraria 404 de documento (AR-DOC-067).
- `Programa.tsx`: PT-04 (`KpiGrid`/`KpiCard`), abas `SubNav` underline com `?vista=` (paridade com o script
  da Blade), rail `DocRail`, 4 vistas. Nenhuma escrita. Blade `documentacao/programa` apagada.
- Testes (`tests/Feature/DocumentacaoRouteTest.php`, lane `ci-sqlite-pest`): render Inertia + `UC-PROGRA-01..06`.
  O caso `@covers-us US-INFRA-048` que exercia `execucaoDaTrilha` foi trocado pelo `UC-PROGRA-01`.

## Desvios de escopo declarados
- **Fora do prefixo da thread** (necessários à migração): `routes/web.php`, `tests/Feature/DocumentacaoRouteTest.php`,
  remoção de `resources/views/documentacao/programa.blade.php`, e o trio `Programa.charter.md`/`.casos.md`.
- **`memory/` é `nao_toca` desta thread**, então ficaram SEM atualizar (precisam de PR próprio):
  1. `memory/requisitos/Infra/SPEC.md` · US-INFRA-048 `**Testado em:**` ainda descreve o caso antigo
     (`execucaoDaTrilha` por reflection). O caso novo que cobre a US é o `UC-PROGRA-01`.
  2. `ANTI-REGRESSAO-documentacao-blade.md` · `AR-DOC-068`/`069` seguem ❌/⚠️ — ficaram resolvidos por este PR.
  3. `SPEC Documentacao` · `US-DOC-002` segue `todo` com âncora `_pendente_`.

## Não verificado (e por quê)
- **PHP não roda nesta máquina** (sem `php`/`vendor`); a prova dos testes é o CI.
- **Render da tela não foi visto**: não há app local e o staging do CT 100 serve outro checkout. O smoke
  1280/1440 fica para depois do deploy, e o charter só sai de `draft` com screenshot aprovado por [W].
- **Fidelidade ao protótipo `programa-doc-page.jsx` não foi medida** (`design-diff`) — a tela segue a
  estrutura da Blade viva, com a forma do shell irmão (`Documentacao/Index`).
