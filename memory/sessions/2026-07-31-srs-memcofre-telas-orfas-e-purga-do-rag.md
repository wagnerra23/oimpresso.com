---
date: "2026-07-31"
topic: "Resíduo do SRS/MemCofre: 6 telas órfãs + 33 docs servidos pelo RAG — purga, 3 ADRs salvas por governarem código vivo, e 2 varreduras abortadas por medição"
prs: [5088, 5092, 5102, 5103]
related_adrs:
  - 0357-deprecar-srs-sucessor-kb-jana-governance
  - 0264-governanca-executavel-trio-dominio-e2e
outcomes:
  - "6 telas MemCofre + 6 charters removidos; screen-coverage 227 → 221, charter segue 100%"
  - "33 docs de memory/requisitos/MemCofre/ purgados — 22 deles eram servidos pelo RAG à Jana"
  - "3 ADRs de navegação salvas (UI-0024..0026): governam buildTopNavs vivo em 14 módulos"
  - "anchored_dead 2 → 0 (dívida do rename Brief→Forja, não minha — medida no commit anterior)"
  - "Varredura @memcofre ABORTADA: 70 de 111 arquivos ficavam decapitados"
  - "LC-08 ocorrência 35 (o $? medindo o sed) e LC-13 ocorrência 5 (verde por clone shallow)"
---

# SRS/MemCofre — o resíduo que sobrou do módulo deletado

`Modules/SRS` (ex-MemCofre) saiu em 2026-07-29 ([ADR 0357](../decisions/0357-deprecar-srs-sucessor-kb-jana-governance.md)). Esta sessão limpou o que ficou para trás — e, no caminho, duas varreduras que **pareciam** óbvias morreram na medição.

## 1. As 6 telas órfãs (#5088)

Protocolo de medição antes de apagar, na ordem:

| passo | resultado |
|---|---|
| Rota alcança? | **Não.** As 5 rotas `/memcofre/*` em `routes/web.php` são `Route::redirect(…, 301)` para `/ia`, `/kb`, `/governance` — compat de bookmark (risco R5 do DEPRECATION-PLAN). **Ficam.** |
| Consumidor de código? | **Zero.** Os 3 candidatos são inertes: `RenameRegressionTest:92` lê keys de `modules_statuses.json`; `ModuleGradeServicePathResolutionTest:60` cita MemCofre só em **comentário**; o regex de `ScopedScorecardEvaluator:357` opera em `Modules/` e `memory/requisitos/`, nunca em `resources/js/Pages/`. |
| Vínculo de entrada? | 6 links, todos em `memory/sessions/2026-06-08-mapa-telas-projeto.md` — fóssil datado, isento por design no `deadlink-gate`. |

**O primeiro grep quase fabricou um falso "zero rotas":** procurei `MemCofre` case-sensitive e as rotas usam `/memcofre/` minúsculo. Elas existem — só não renderizam as telas.

Reconciliado no mesmo PR: 5 baselines (`casos`(6) · `layout-primitives`(6) · `eslint`(10) · `ui-lint`(3) · `dsih`(1)), 6 scorecards YAML, e o `screen-coverage-baseline.json` **regenerado pela porta viva** (`--json`), não editado à mão. `config/pageheader-shared-baseline.json` foi medido e tem **0** ocorrências — não precisava ser tocado.

## 2. A pasta de 33 docs (#5092) — e por que a lápide não bastava

Eu tinha argumentado para **manter** a pasta com lápide `⚰️`, citando a convenção do `memory/INDEX.md`. [W] cortou: *"basicamente isso se tornou lixo, e mesmo com lápide fixo como lixo tóxico"*.

Ele estava certo, e o meu erro tem nome: **medi o que os gates fazem, não o que o RAG serve**. A lápide mora no `INDEX.md`; a busca semântica (Jana, `memoria-search`, MCP) **não passa por índice nenhum** — devolve o doc direto, com cara de canon.

Medido com a porta viva `coletadoPeloIndexador` ([`.claude/hooks/doc-fora-do-rag.mjs`](../../.claude/hooks/doc-fora-do-rag.mjs), validada 486/486 contra o índice de produção): **22 dos 33 docs eram coletados**.

E o conteúdo não era só velho, era **enganoso** — o módulo **nunca rodou** (todas as `docs_*` com 0 linhas, medido na E2 do plano): `ARCHITECTURE.md` descrevia tabelas dropadas, `RUNBOOK.md` ensinava a operar o que não existe, `adr/arq/0006` se apresentava como *"padrão de busca e listagem"*, e `README.md`/`RUNBOOK.md` ainda tinham **`status: ativo`**.

### O que foi salvo, e por quê

3 ADRs de navegação migraram para `_DesignSystem/adr/ui/`:

| origem | destino | status |
|---|---|---|
| `arq/0009` topnav declarativo | **UI-0024** | superseded |
| `arq/0010` sidebar accordion | **UI-0025** | superseded |
| `arq/0011` duas fontes independentes | **UI-0026** | **accepted** |

A cadeia inteira migrou porque a UI-0026 supersede as outras duas — deixar só a viva quebraria os ponteiros dela.

**A prova de que não é lixo:** `buildTopNavs` existe em `app/Services/LegacyMenuAdapter.php`, `app/Services/ShellMenuBuilder.php` e `app/Http/Middleware/HandleInertiaRequests.php`, e **14 módulos** têm `Resources/menus/topnav.php`. Dois deles citam a ADR literalmente no PHPDoc — repontados para `ADR UI-0026 · _DesignSystem`.

⚠️ **Errata embutida na UI-0024:** ela afirmava *"código backend removido (… `topnav.php`)"*. Verdade que durou horas — a UI-0026, do **mesmo dia**, ressuscitou o mecanismo. Migrar sem errata injetaria a mentira no Design System.

## 3. As duas varreduras que morreram na medição

### `memory/modulos/SRS.md` — eu declarei impossível, e estava errado

Dizia `Status: 🟢 ativo` para módulo removido. Eu tinha registrado como *"não dá, é derivado de `php artisan module:specs`"*. Certo que é derivado; **errado que não dava** — o próprio diretório já tinha o padrão canônico em `Admin.md` (`— REMOVIDO` + bloco ⛔ + `Status: ⛔ removido (data, ADR)`). Copiei o padrão (#5102).

A varredura do diretório achou **3 outros** presente-falso: `PontoWr2`, `Accounting`, `AiAssistance`. **Não toquei** — cada um exige medir o destino, e varredura cega ali é exatamente o erro seguinte.

### As 114 anotações `@memcofre` — ABORTADA

Metadado lido só pelo SRS. Apliquei em 113 arquivos, o `casos-gate` ficou verde, e fui conferir o diff: **70 dos 111 arquivos ficaram decapitados**.

`// @memcofre` quase nunca é linha solitária — é o **cabeçalho de um bloco**:

```
// @memcofre
//   tela: /fiscal/nfe
//   us: US-NFE-010 fase 2
//   module: NfeBrasil
```

Remover a primeira linha deixa metadado órfão em 70 telas. Minha conferência inicial não pegou porque procurei só por `tela|stories|rules|adrs` — os outros usam `us:`, `module:`, `componente:`, `modulo:`.

**A Consequência 6 do DEPRECATION-PLAN não é "apagar 114 linhas" — é triagem bloco-a-bloco.** O plano já dizia que não é bloqueador (nenhum gate consome essas anotações).

Colateral do abort: o toque em `_Showcase/OndaF.tsx` acordava o `PII scan` (required) por **CNPJ literal pré-existente** (linhas 66/86) — dívida grandfathered por não-toque. **Continua lá**, e é Tier 0.

## 4. `anchored_dead` 2 → 0 (#5103) — dívida de terceiro, corrigida

Validando o main **depois** do merge, achei `anchor-lint` e `doc-id-index` vermelhos. Rodei os dois em `32c357dc61d` (commit **anterior** ao meu): já falhavam. Vinha do [#5098](https://github.com/wagnerra23/oimpresso.com/pull/5098) (Brief→Forja), que moveu o código sem repontar as âncoras de `US-INFRA-043` e `US-COPI-088`.

Corrigido por **reconciliação** (o gate avisa: *"nunca inventar path"*) — conferido que os 2 arquivos existem em `Modules/Forja/` e que `Modules/Brief/` tem 0 arquivos. `verificado@<sha>` **mantido**: o path mudou por rename, não por re-verificação.

## 5. Lições de método (as duas foram ao ledger)

**LC-08 #35 — o `$?` que media o `sed`.** Meu loop de validação era `node $c >/dev/null 2>&1; printf "exit=%s %s" "$(echo $c|sed …)" "$?"`. A substituição `$(...)` roda **antes** do `printf` e **reseta o `$?`** para o do `sed` — sempre 0. Reportei três baterias como "todas verdes" enquanto o `doc-id-index --check` estava **vermelho**. Só apareceu porque estranhei um verde *inesperado* num gate que eu sabia ter mexido.

**LC-13 #5 — verde por clone shallow.** O `casos-gate` deu exit 0 com zero violações num worktree shallow: o G-6 **pula gracioso** sem sinal git (`casos-coverage-guard.mjs:287`). Depois de `--unshallow`, o mesmo gate no mesmo commit acusou **35 `stale`**. Agravante: `git fetch --force origin main:refs/…` **re-rasa** o clone no meio da sessão.

Outros tropeços menores, todos pegos por verificação: `git cat-file -e` com falso negativo em `.dsih-baseline.json`; padrão sem barras escapadas subestimando o `ui-lint` (formato `json_encode` do PHP); `JSON.stringify(…,2)` explodindo um baseline em 2135 linhas para remover 3 entradas; grep por slug genérico devolvendo 14.321 refs para `"er"`; e um `exit=1` de **comando inexistente** que quase virei veredito (a armadilha do `crontab -l`).

## Pointers

- Handoff: [`2026-07-31-1100-srs-memcofre-purga-e-abortos.md`](../handoffs/2026-07-31-1100-srs-memcofre-purga-e-abortos.md)
- Plano de origem: [`memory/requisitos/SRS/DEPRECATION-PLAN.md`](../requisitos/SRS/DEPRECATION-PLAN.md)
- Ledger: [`memory/LICOES_CODE.md`](../LICOES_CODE.md) LC-08 e LC-13
