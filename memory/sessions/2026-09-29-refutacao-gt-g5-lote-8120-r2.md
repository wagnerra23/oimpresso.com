---
date: "2026-09-29"
topic: "Refutação GT-G5 r2 do lote PR #8120 (17 arquivos memory/requisitos/Ponto — 15 map.json + 2 gap.md, thread 17 ids feios): 118 itens, 2 refutados (banco-horas-show regride 2 âncoras que origin/main já preencheu), PII 0"
authors: ["C"]
prs: [8120]
outcomes:
  - "Lote medido 100% contra origin/main (bfef050db4): 118 itens em 6 grupos, 2 refutados, error_rate 1,69% — abaixo do corte de 2%; PII 0 hits com 7/7 controles positivos"
  - "Refutado: banco-horas-show.map.json do lote carrega `ancora: false` em 2 partes (historico-de-movimentos, ajuste-manual) onde origin/main (#8119) já tem `ancora: \"<id>\"` verificável — HEAD está 2 commits atrás de main; rebase + `gerar-map.mjs --atualizar` a partir de origin/main resolve"
  - "Observações não contadas: drift do _STATUS-GENERATED.md é herdado do merge-base (rc=1 lá também, resolvido em main); `_acionavel` de configuracoes-index já drifava em origin/main; extensão do Card com id difere entre protótipo e vivo nas 2 telas renomeadas"
---

## TL;DR

**Veredito: APROVADO** (recalculado pelo workflow). 118 itens verificados contra `origin/main` em 6 grupos; **2 erros confirmados** (os dois no mesmo arquivo, `banco-horas-show.map.json`, que regride âncoras já preenchidas em `origin/main` porque a branch está 2 commits atrás); **error_rate 1,69%**; **PII 0 hits** (7/7 controles positivos casaram).

## Cabeçalho

| Campo | Valor |
|---|---|
| Base (`origin/main`) | `bfef050db40bbe10772c0a6f040107adea0bcbb5` |
| HEAD do lote | `ad8ac9d19fd432078b9adb53a9d6b7ea40fee6a9` (branch `claude/ponto-17-ids-feios`) |
| merge-base | `11a52efb2ca1` — HEAD está **2 atrás / 3 à frente** de `origin/main` (`git rev-list --count HEAD..origin/main` = 2: #8119 e #8122) |
| Repo raso | `false` (`git rev-parse --is-shallow-repository`) |
| Sessão fresca | sim — instância nova, sem contexto do gerador nem das rodadas anteriores |
| Modelo | Fable 5.1 (tier máximo disponível) |
| Tipo do lote | anchors → amostra 100% |

⚠️ **Disclosure obrigatório (exposição incidental, não abertura):** não abri nenhum `memory/sessions/*refutacao*` nem `memory/handoffs/` de hoje. Porém um `git grep -n -e escalaform-card -e intercorrencias-card HEAD` (varredura contada dos ids velhos, repo inteiro) devolveu **8 linhas** de `memory/sessions/2026-09-29-refutacao-gt-g5-lote-8120-r1.md` no meio dos 15 hits, e elas apareceram no stdout. Não li o arquivo; as medições dos itens que aquelas linhas tocam (`ponto-telas.jsx:529/:299`, `data-contract` nos dois lados) já tinham sido feitas por mim **antes** desse grep, e os greps seguintes excluíram `memory/sessions`. Declaro `abriu_evidencia_anterior: false` e deixo o fato aqui para o dono da rodada decidir se descarta.

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (igualdade só no tier máximo — Fable 5.1 é o teto)
- [x] Amostra: 100% anchors (lote é `anchors`; sem seleção aleatória — seed não se aplica)
- [x] Cada item verificado contra o código real em `origin/main`, não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha/commit + porquê)
- [x] **Scan PII no diff** — 7 padrões × 34 linhas `+`, 0 hits, 7/7 controles positivos
- [x] `error_rate_pct` calculado e < 2 (1,69%)
- [ ] Entry no ledger — **não é do refutador** (mandato: não escrever no ledger)

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` = **17 M** (15 `*.map.json` + `escalas-form-gap.md` + `intercorrencias-create-gap.md`), `38 insertions(+), 30 deletions(-)`. Conteúdo do diff: nos 15 maps só `prototipo_sha` (`sha256:e4d0b5a3707e` → `sha256:e8b74d75e0d5`) e `gerado_em` (`2026-09-28` → `2026-09-29`); nos 2 gaps, uma seção nova cada (`## Região do form: ...`).

⚠️ O diff de **dois pontos** `git diff --name-status origin/main HEAD -- memory/requisitos/Ponto` lista **18** arquivos: os 17 acima **+ `_STATUS-GENERATED.md`** (que `origin/main` regenerou em #8119/#8122 e a branch não tem). É daí que saem as duas observações de staleness de base abaixo.

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---|---|---|
| 1. Âncora existe em `origin/main` | 35 | 35 | 0 | 33 paths únicos extraídos dos 15 maps (`gap_fonte` + `prototipo.arquivo` + `vivo.arquivo` de todas as 103 partes) → `git ls-tree origin/main -- <p>` devolve blob para 33/33; controle negativo `Pages/Ponto/NaoExiste/Index.tsx` → 0. `n/a` é sentinela, não path. +2: `_saida-17.md` e `17-data-contract-no-tsx.md` (a "thread 17" citada) existem em `origin/main` (`git grep`). Nenhum path aponta `Components/**` como âncora de tela |
| 2. Âncora não revogada · lida pelo leitor real | 15 | 15 | 0 | `node scripts/design/ancora.mjs Ponto/<Tela> --staging prototipo-ui/cowork/Wagner` → `âncora ✓ [related_prototype (charter)] ponto-telas.jsx` para as 15; `git grep -i -e REVOGAD -e MIS-ANCHOR origin/main -- 'resources/js/Pages/Ponto/**/*.charter.md'` = **0** (controle positivo repo inteiro = 26 arquivos); os 15 charters em `origin/main` declaram `related_prototype: prototipo-ui/cowork/Wagner/ponto-telas.jsx`. O frontmatter do gap é lido por `fmVal` (`gerar-contrato.mjs:37`, reusado por `gerar-map.mjs`) e o `gerado_em` do gap pelo `design-code-map-check.mjs:277-302` (idade da afirmação) |
| 3. Ação × veredito da prosa · afirmação sobre código | 12 | 12 | 0 | 6 claims por seção nova (2 seções). Cada linha citada aberta em HEAD **e** `origin/main` (ver §Prosa abaixo) |
| 4. Célula íntegra · map == gerador | 45 | 43 | **2** | 15 maps regenerados com `gerar-map.mjs <gap> --atualizar` (stdout, sem escrever) e comparados chave a chave com o lote: **14 idênticos**, `configuracoes-index` difere só em `_acionavel` (4 chaves — pré-existente em `origin/main`, ver observações). `prototipo_sha` recomputado por `computeProtoHash`: `origin/main` → `e4d0b5a3707e`, HEAD → `e8b74d75e0d5` (15/15 batem). `gerado_em` = data do run (15/15). **Refutados:** `banco-horas-show.map.json` partes `historico-de-movimentos` e `ajuste-manual` — lote `ancora: false`, `origin/main` `ancora: "<id>"` |
| 5. Máquina derivada | 4 | 4 | 0 | rc literais abaixo. `requisitos-status Ponto --check` = **rc=1**, mas o drift é **herdado do merge-base** (rc=1 lá também, medido em worktree limpo) e já está resolvido em `origin/main` — não é do lote (observação) |
| 6. PII | 7 | 7 | 0 | 7 padrões × 34 linhas `+`: 0 hits; 7/7 controles positivos casaram |
| **Total** | **118** | **116** | **2** | error_rate = 2/118 = **1,69%** |

## REFUTADOS

### R1 — `memory/requisitos/Ponto/banco-horas-show.map.json` · parte `historico-de-movimentos` · `vivo.ancora`

- **Afirmação do lote:** `"ancora": false` (opt-out consciente — `optOutConsciente()` em `design-code-map-check.mjs:158`).
- **O que `origin/main` diz:** `git show origin/main:memory/requisitos/Ponto/banco-horas-show.map.json` linha 48 → `"ancora": "bancohoras-historico-de-movimentos"`, introduzido por `bfef050db feat(ponto): âncoras do extrato de banco de horas no map — thread 17 PR-2b [CL] (#8119)`. A âncora é real: `git show origin/main:resources/js/Pages/Ponto/BancoHoras/Show.tsx | grep -n data-contract` → `:179 <Card data-contract="bancohoras-historico-de-movimentos">`.
- **Porquê é erro do lote:** a parte, medida contra `origin/main`, **regride** de "âncora exigida e verificável" para "opt-out" — o oposto do que o `--atualizar` promete ("preserva o preenchido"). Causa: a branch partiu do merge-base `11a52efb`, 2 commits atrás; o lote não foi regenerado a partir de `origin/main`. Conserto: rebase + `node scripts/design/gerar-map.mjs memory/requisitos/Ponto/banco-horas-show-gap.md --atualizar`. (Nota honesta: como o lote não tocou essas linhas, um merge 3-way preservaria o valor de `main`; mesmo assim, o artefato do lote como está contradiz `origin/main`.)

### R2 — `memory/requisitos/Ponto/banco-horas-show.map.json` · parte `ajuste-manual` · `vivo.ancora`

- **Afirmação do lote:** `"ancora": false`.
- **O que `origin/main` diz:** mesmo arquivo, linha 78 → `"ancora": "bancohoras-ajuste-manual"` (#8119); `Show.tsx:270 <Card data-contract="bancohoras-ajuste-manual">` em `origin/main`.
- **Porquê é erro do lote:** idem R1 — regressão de âncora preenchida, staleness de base.

## Prosa das 2 seções novas (grupo 3 — 12 claims, todas conferidas em HEAD e `origin/main`)

`escalas-form-gap.md:26-28` (seção `## Região do form: escalaform-dados-da-escala (2026-09-29, thread 17)`):

| # | Claim | Evidência | |
|---|---|---|---|
| 1 | Protótipo embrulha nome/código/tipo/cargas/banco de horas num `Card` com título `escala.nome` na edição e "Dados da escala" na criação | `ponto-telas.jsx:529` (HEAD e main): `titulo={escala ? escala.nome : "Dados da escala"}`; `:531-539` PtCampo Nome/Código, PtEscolha Tipo, cargas, PtCheck banco de horas | ✓ |
| 2 | "a derivação gerou o id `escalaform-card`" (`:529`) | `origin/main:ponto-telas.jsx:529` → `contrato="escalaform-card"` | ✓ |
| 3 | Vivo: card de título fixo "Dados da escala" (`Form.tsx:89`) | HEAD e main `Form.tsx:89 <Card…>` + `:91 <CardTitle>Dados da escala` | ✓ |
| 4 | Novo id gravado nos dois lados no mesmo PR | HEAD `ponto-telas.jsx:529 contrato="escalaform-dados-da-escala"` + `Form.tsx:89 data-contract="escalaform-dados-da-escala"`; `git grep escalaform-card HEAD` = 0 em `.jsx/.tsx` (sobram só citações datadas do playbook) | ✓ |
| 5 | Card de turnos (`Form.tsx:151`) é outra região e segue sem id | HEAD `Form.tsx:151 {isEdit && escala!.turnos && (` · `:152 <Card className="mt-4">` sem `data-contract` (a tabela do gap já cita `:151-187`) — verdadeiro **no vivo**; ver observação O3 sobre o lado protótipo | ✓ |
| 6 | "(2026-09-29, thread 17)" | thread 17 = `_saida-17.md` / `17-data-contract-no-tsx.md`, existentes em `origin/main` | ✓ |

`intercorrencias-create-gap.md:32-34` (seção `## Região do form: intercorrencias-dados-da-ocorrencia (2026-09-29, thread 17)`):

| # | Claim | Evidência | |
|---|---|---|---|
| 7 | Card de título dinâmico "Nova intercorrência" · "Editar rascunho <código>" | `ponto-telas.jsx:299` (HEAD e main): `titulo={editando ? "Editar rascunho " + (editando.codigo || …) : "Nova intercorrência"}` | ✓ |
| 8 | "a derivação gerou o id `intercorrencias-card`" (`:299`) | `origin/main:ponto-telas.jsx:299` → `contrato="intercorrencias-card"` | ✓ |
| 9 | Vivo: card de título fixo "Dados da ocorrência" (`Create.tsx:241`) | HEAD e main `Create.tsx:241 <Card…>` + `:243 <CardTitle>Dados da ocorrência` | ✓ |
| 10 | Novo id gravado nos dois lados | HEAD `ponto-telas.jsx:299 contrato="intercorrencias-dados-da-ocorrencia"` + `Create.tsx:241 data-contract="intercorrencias-dados-da-ocorrencia"` | ✓ |
| 11 | Card da IA (`Create.tsx:171`) é outra região, sem id, e o protótipo não o modela | HEAD e main `Create.tsx:171 <Card className="border-primary/40 …">` sem `data-contract`; `grep -c 'Descrever em texto livre\|ai_enabled' ponto-telas.jsx` = 0 | ✓ |
| 12 | "linha 'Campo IA em texto livre' acima" existe e diz que o protótipo não modela | `intercorrencias-create-gap.md:23`: "Protótipo: não existe no símbolo" | ✓ |

## Máquina derivada (grupo 5 — rc literal)

| Comando | rc | Saída relevante |
|---|---|---|
| `node scripts/governance/design-code-map-check.mjs --check --strict` | **0** | `[OK] nenhum map.json com âncora quebrada ou sha stale. 88 âncora(s) TODO pendente(s)`; 5 WARN, nenhum em Ponto |
| `node scripts/governance/requisitos-status.mjs Ponto --check` | **1** | `_STATUS-GENERATED.md está DRIFADO` (`UC declarados 102→103 · com teste 97→98`). **Herdado:** em worktree limpo do merge-base `11a52efb` → rc=1 também; em worktree limpo de `origin/main` → rc=0 (`em dia`). Bissecção em worktree descartável: reverter os 2 gaps → 1; reverter os 15 maps → 1; trazer `memory/requisitos/Ponto` inteiro de main → 0 — o que zera é o **próprio `_STATUS-GENERATED.md` de main**, não os 17 do lote. O step da umbrella é `continue-on-error: true` |
| `node scripts/governance/plans-index.mjs --check` | **0** | `em dia (8 registrados, 27 pendentes)` |
| `node scripts/governance/doc-id-index.mjs --check-collisions` | **0** | `0 colisão de id em 2864 ids` |

Nenhum arquivo que o PR devesse ter regenerado está fora do diff **do lote** — o `_STATUS-GENERATED.md` é de `main`, não do PR.

## Observações não contadas

- **O1 — `_acionavel` em `configuracoes-index.map.json`:** regenerar com `--atualizar` flipa 4 partes `false → true`; `git show origin/main:…configuracoes-index.map.json` já tem os 4 `false` (linhas 22/37/52/67) — pré-existente, e o próprio gerador declara o campo "CONSUMIDOR ZERO" (`gerar-map.mjs:186-199`). Não é do lote.
- **O2 — linha stale pré-existente em `intercorrencias-create-gap.md:29`** ("Nota de rascunho e append-only": *Vivo diz "Eles serão submetidos ao RH"* `Create.tsx:244-246`): `git show origin/main:…Create.tsx | grep -c 'submetidos ao RH'` = **0** — a copy foi trocada em `65952cb6f7 (#8076, 2026-09-28)`. A linha já estava assim em `origin/main` (blob `2b73e7c19e`); o lote só apendou uma seção abaixo. Fica registrado porque o `gerado_em: 2026-09-28` do frontmatter (que o checker usa como idade da afirmação) não foi tocado apesar de o arquivo agora carregar prosa de 2026-09-29.
- **O3 — extensão do Card com id difere entre os lados (as 2 telas):** no protótipo o `Card contrato="escalaform-dados-da-escala"` vai de `:529` a `:558` e **inclui** a tabela "Turnos configurados" (`:541-551`), a `Nota` CLT (`:552`) e o rodapé (`:553-556`); no vivo o Card `:89-149` tem só os dados, turnos é outro Card (`:152-187`) e o rodapé fica fora (`:190-198`). Idem `intercorrencias-dados-da-ocorrencia`: protótipo `:299-301` embrulha o `FormIntercorrencia` inteiro (anexo, nota, rodapé), vivo `:241-385` com rodapé fora (`:394-402`). A prosa diz "a mesma região" e não registra essa diferença. Não contei como erro: cada frase, lida contra o arquivo que cita, é verdadeira; e o `design-code-map-check` só exige presença do id. Mas quem for medir FORMA por região vai tropeçar nisso.
- **O4 — `frescor: verificado contra o Cowork vivo em 2026-09-29`** que o `ancora.mjs` imprime vem do `.cowork-freshness-ledger.json` que **este PR** atualizou — é parte da claim, não recibo independente; a resolução da âncora (charter → `ponto-telas.jsx`) não depende dele.
- **O5 — ids velhos sobrevivem em `origin/main` só em documentos datados do playbook** (`17-data-contract-no-tsx.md:34`, `20-gap-intercorrencias.md:21` "TODO · a renomear", `_PATCH-INDICE-2026-09-14.md:132`, `github.md:302`), todos sob `prototipo-ui/cowork/Wagner/` (espelho de leitura, fora do lote). `git grep -e escalaform-card -e intercorrencias-card origin/main` = 7 linhas (5 playbook + 2 no `ponto-telas.jsx`, que o PR renomeia).

## Scan PII (grupo 6)

Corpus: as **34** linhas `+` de `git diff origin/main...HEAD -- memory/requisitos`. Cada padrão rodado também contra uma linha sintética que casa.

| Padrão | Hits | Controle positivo |
|---|---|---|
| CPF pontuado (`ddd.ddd.ddd-dd`) | 0 | casou |
| CPF cru (11 dígitos isolados) | 0 | casou |
| CNPJ (`dd.ddd.ddd/dddd-dd`) | 0 | casou |
| Telefone BR (`(dd) 9dddd-dddd`) | 0 | casou |
| Telefone cru (10–11 dígitos isolados) | 0 | casou |
| E-mail | 0 | casou |
| Valor em reais (símbolo seguido de dígito) | 0 | casou (na 2ª rodada — na 1ª o par de barras do regex colapsou no transporte do heredoc, §5 2026-08-19; refeito com regex literal e `od -c` conferido) |

Nomes de cliente do CRM: `grep -oE` de pares Capitalizados nas 34 linhas → 0. **pii_hits = 0 · controles 7/7.**

## Comandos reproduzíveis

```bash
git rev-parse HEAD origin/main; git rev-parse --is-shallow-repository
git merge-base origin/main HEAD; git rev-list --count HEAD..origin/main
git diff --name-status origin/main...HEAD -- memory/requisitos          # 17
git diff --name-status origin/main HEAD  -- memory/requisitos/Ponto      # 18 (+ _STATUS-GENERATED.md)
# grupo 1
for p in <33 paths>; do git ls-tree origin/main -- "$p"; done; git ls-tree origin/main -- resources/js/Pages/Ponto/NaoExiste/Index.tsx | wc -l
# grupo 2
node scripts/design/ancora.mjs Ponto/<Tela> --staging prototipo-ui/cowork/Wagner   # ×15
git grep -n -i -e REVOGAD -e MIS-ANCHOR origin/main -- 'resources/js/Pages/Ponto/*/*.charter.md' | wc -l
# grupo 4
node scripts/design/gerar-map.mjs memory/requisitos/Ponto/<tela>-gap.md --atualizar > /scratch/<tela>.map.json   # stdout, não escreve
node -e "import('./scripts/design/gerar-map.mjs').then(m=>console.log(m.computeProtoHash(['prototipo-ui/cowork/Wagner/ponto-telas.jsx'],'<root-com-a-versao-de-origin/main>')))"
git show origin/main:memory/requisitos/Ponto/banco-horas-show.map.json | grep -n '"ancora": "'
git show origin/main:resources/js/Pages/Ponto/BancoHoras/Show.tsx | grep -n data-contract
# grupo 5
node scripts/governance/design-code-map-check.mjs --check --strict; echo rc=$?
node scripts/governance/requisitos-status.mjs Ponto --check; echo rc=$?        # e o mesmo em `git worktree add --detach <tmp> 11a52efb2ca` / `origin/main`
node scripts/governance/plans-index.mjs --check; echo rc=$?
node scripts/governance/doc-id-index.mjs --check-collisions; echo rc=$?
# grupo 6
git diff origin/main...HEAD -- memory/requisitos | grep -E '^\+[^+]' > /scratch/plus.txt   # 34 linhas; scan em node com regex literais + 7 controles
```

Árvore deixada limpa: `git status --short` vazio exceto este arquivo; worktrees temporários (`wt-omain`, `wt-head`, `wt-bisect*`, `wt-mb`) criados e removidos com `git worktree remove` (sem junction — eram checkouts sem `vendor/`/`node_modules/`).

```json
{"itens_verificados": 118, "erros_confirmados": 2, "error_rate_pct": 1.69, "pii_hits": 0, "veredito": "aprovado"}
```
