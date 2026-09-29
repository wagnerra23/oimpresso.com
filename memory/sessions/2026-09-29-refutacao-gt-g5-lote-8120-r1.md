---
date: "2026-09-29"
topic: "Refutação GT-G5 r1 do lote PR #8120 (17 arquivos memory/requisitos/Ponto — 15 map.json + 2 gap.md, thread 17 ids feios): 281 itens, 15 refutados (gerado_em falso em 15 maps), 5,34% → reprovado"
authors: ["C"]
prs: [8120]
outcomes:
  - "REPROVADO: 15 de 281 itens refutados (5,34% ≥ 2%) — um único erro sistemático: os 15 map.json carregam prototipo_sha novo (sha256:e8b74d75e0d5, correto) com gerado_em 2026-09-28 preservado, data em que o gerador NÃO pode ter produzido esse sha (o protótipo com os ids novos nasceu em 9bf8b6f1a, 2026-09-29 07:39 -0300); gerar-map.mjs --atualizar regenera os 15 com partes e sha idênticos e gerado_em 2026-09-29"
  - "CONFIRMADO tudo o resto: 199/199 paths + 6/6 refs de linha existem em origin/main; ancora.mjs resolve ✓ nas 15 telas (ponto-telas.jsx, charters sem REVOGADA/MIS-ANCHOR); 11/11 afirmações de prosa dos 2 gap.md batem com o código em origin/main; design-code-map-check --check --strict rc=0, plans-index rc=0, doc-id-index rc=0, cowork-mirror-freshness rc=0"
  - "PII: 0 hits nas 19 linhas '+' com 7/7 controles positivos; requisitos-status.mjs Ponto --check rc=1 é drift PRÉ-EXISTENTE em origin/main (conjunto de UC idêntico nas 2 refs, 0 linhas UC no diff) — não é erro do lote"
---

## TL;DR

**REPROVADO** · 281 itens verificados · 15 erros confirmados · error_rate 5,34%. O lote acerta o `prototipo_sha` (recomputado por `computeProtoHash` do próprio gerador: origin/main = `sha256:e4d0b5a3707e`, HEAD = `sha256:e8b74d75e0d5`) e todas as âncoras, mas escreveu o sha à mão nos 15 `map.json` sem passar pelo gerador canônico — e por isso os 15 arquivos afirmam `gerado_em: 2026-09-28` para um sha que só existe desde 2026-09-29. É um erro só, espalhado em 15 arquivos (o caso que o §2.6 do protocolo manda reprovar inteiro). PII = 0.

## Cabeçalho

| campo | valor |
|---|---|
| base (`origin/main`) | `11a52efb2ca11bba0d26ce186d3eb12f69851a5a` |
| HEAD | `94221d01cd0112bd2d44037fd60b7b9cd86b0a9a` |
| repo raso | `false` (`git rev-parse --is-shallow-repository`) — datas de `git log` valem |
| sessão fresca | sim — worktree `onda-sidebar-thread-08-56ceca`, sem contexto do gerador; `brief-fetch` deliberadamente NÃO chamado (poderia trazer handoff de hoje) |
| abriu evidência anterior | **não** (nenhum `memory/sessions/*refutacao*` nem `memory/handoffs/` de hoje foi aberto) |
| corpo do PR lido | **não** |
| modelo | Fable 5.1 (tier máximo) |

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (Fable — tier máximo; igualdade só aceita no máximo)
- [x] Amostra: 100% anchors (lote é `anchors` — sem prosa destilada; nenhuma seleção aleatória, logo sem seed)
- [x] Cada item verificado contra `origin/main` (`git ls-tree` / `git show origin/main:<path>` / `git grep … origin/main`), não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha/commit + porquê)
- [x] Scan PII no diff (7 padrões, controle positivo por padrão) — 0 hits
- [x] `error_rate_pct` calculado = 5,34 (≥ 2 → reprovado)
- [ ] Entry no ledger — **não escrita** (o mandato desta rodada proíbe escrever no ledger; lote reprovado volta pro gerador)

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` = **17 arquivos**, todos `M`, todos em `memory/requisitos/Ponto/`: 15 `*.map.json` + `escalas-form-gap.md` + `intercorrencias-create-gap.md` (bate com a lista do mandato). Diff total do PR = 24 arquivos (os 7 restantes: `ponto-telas.jsx`, `Escalas/Form.{tsx,casos.md}`, `Intercorrencias/Create.{tsx,casos.md}`, `enviados-cowork.json`, `.cowork-freshness-ledger.json`).

Conteúdo do lote: nos 15 map.json a ÚNICA linha alterada é `prototipo_sha: sha256:e4d0b5a3707e → sha256:e8b74d75e0d5` (`gerado_em` e `partes[]` intocados). Nos 2 gap.md, +4 linhas cada: seção `## Região do form: <id novo> (2026-09-29, thread 17)`.

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---|---|---|
| 1. Âncora existe em origin/main | 205 | 205 | 0 | 199 paths (15 `gap_fonte` + 184 `prototipo.arquivo`/`vivo.arquivo` não-`n/a`) via `git ls-tree origin/main -- <path>` → blob em 199/199; controle negativo `Pages/Ponto/__nao-existe.tsx` → false, positivo `Escalas/Form.tsx` → true. + 6 refs de linha dos gap.md abertas em origin/main (tabela abaixo) |
| 2. Âncora não revogada · leitor real | 15 | 15 | 0 | `node scripts/design/ancora.mjs Ponto/<Tela> --staging prototipo-ui/cowork/Wagner` → `âncora ✓ [related_prototype (charter)] prototipo-ui/cowork/Wagner/ponto-telas.jsx` nas 15; charters `Escalas/Form` e `Intercorrencias/Create` em origin/main: `related_prototype: prototipo-ui/cowork/Wagner/ponto-telas.jsx`, zero `REVOG`/`MIS-ANCHOR` |
| 3. Ação × veredito da prosa · afirmação sobre código | 11 | 11 | 0 | cada afirmação das 2 seções novas conferida contra `git show origin/main:<arquivo>` (tabela abaixo) |
| 4. Célula íntegra · regeneração chave a chave | 45 | 30 | **15** | `node scripts/design/gerar-map.mjs <gap.md> --atualizar` rodado nos 3 maps de amostra (escalas-form, intercorrencias-create, aprovacoes-index) e diff chave a chave: `prototipo_sha` idêntico, `partes[]` idênticas, **`gerado_em` diverge (lote 2026-09-28 · gerador 2026-09-29)**; o gerador é o único escritor do campo no repo (varredura contada abaixo), logo o resultado vale para os 15 |
| 5. Máquina derivada | 5 | 5 | 0 | rc literal de cada `--check` abaixo; o único rc≠0 (`requisitos-status`) é drift pré-existente em origin/main |
| **Total** | **281** | **266** | **15** | |

### Grupo 1 — refs de linha citadas pelos gap.md (origin/main)

| ref | o que a prosa afirma | o que origin/main tem na linha | veredito |
|---|---|---|---|
| `ponto-telas.jsx:529` | derivação gerou `escalaform-card`; Card com título `escala ? escala.nome : "Dados da escala"` | `<Card contrato="escalaform-card" icon="calendar" titulo={escala ? escala.nome : "Dados da escala"}>` | ✓ |
| `Form.tsx:89` | card de título fixo "Dados da escala" | `:89 <Card>` · `:91 <CardTitle …>Dados da escala</CardTitle>` | ✓ |
| `Form.tsx:151` | card de turnos, outra região, sem id | `:151 {isEdit && escala!.turnos && (` · `:152 <Card className="mt-4">` · `:154 Turnos por dia da semana`; `grep -c data-contract` = 0 em origin/main, e em HEAD a linha 152 segue sem atributo | ✓ |
| `ponto-telas.jsx:299` | derivação gerou `intercorrencias-card`; título dinâmico "Nova intercorrência" · "Editar rascunho <código>" | `<Card contrato="intercorrencias-card" icon="plus" titulo={editando ? "Editar rascunho " + … : "Nova intercorrência"}>` | ✓ |
| `Create.tsx:241` | card de título fixo "Dados da ocorrência" | `:241 <Card>` · `:243 …Dados da ocorrência` | ✓ |
| `Create.tsx:171` | card da IA, outra região, sem id | `:170 {/* === Campo IA === */}` · `:171 <Card className="border-primary/40 bg-primary/5">`; em HEAD segue sem `data-contract` | ✓ |

### Grupo 3 — afirmações de prosa (2 seções novas)

| # | afirmação | evidência em origin/main | veredito |
|---|---|---|---|
| 1 | Escalas: protótipo embrulha nome/código/tipo/cargas/banco de horas num Card de título dinâmico | `ponto-telas.jsx:529` (`titulo={escala ? escala.nome : "Dados da escala"}`) + partes do map `nome-codigo-e-tipo` (:531-537) e `cargas-e-banco-de-horas` (:538-542) dentro do Card | ✓ |
| 2 | "por isso a derivação gerou o id `escalaform-card`" | id presente literal em `ponto-telas.jsx:529`; playbook `17-data-contract-no-tsx.md:34` e `_PATCH-INDICE-2026-09-14.md:132` registram os 2 ids como derivados de Cards sem título fixo | ✓ |
| 3 | vivo = card de título fixo "Dados da escala" (`Form.tsx:89`) | tabela acima | ✓ |
| 4 | id novo "gravado nos dois lados no mesmo PR" | HEAD: `ponto-telas.jsx:529 contrato="escalaform-dados-da-escala"` + `Form.tsx:89 data-contract="escalaform-dados-da-escala"`; `contrato` sai como `data-contract` no shell (`ponto-ui.jsx:44,66,72`); `git grep escalaform-card HEAD` = 0 no jsx/tsx (sobram só citações datadas em playbook) | ✓ |
| 5 | card de turnos (`Form.tsx:151`) é outra região e segue sem id | tabela acima; parte `turnos-configurados` do map aponta `Form.tsx:151-187` | ✓ |
| 6 | Intercorrências: Card de título dinâmico | `ponto-telas.jsx:299` | ✓ |
| 7 | "por isso a derivação gerou o id `intercorrencias-card`" | idem #2 (`20-gap-intercorrencias.md:21` também) | ✓ |
| 8 | vivo = card de título fixo "Dados da ocorrência" (`Create.tsx:241`) | tabela acima | ✓ |
| 9 | id novo gravado nos dois lados | HEAD `ponto-telas.jsx:299` + `Create.tsx:241`; `intercorrencias-card` = 0 em jsx/tsx | ✓ |
| 10 | card da IA (`Create.tsx:171`) é outra região e segue sem id | tabela acima | ✓ |
| 11 | "o protótipo ainda não o modela (linha 'Campo IA em texto livre' acima)" | `intercorrencias-create-gap.md:23` em origin/main: linha `Campo IA em texto livre` com "Protótipo: não existe no símbolo"; parte `campo-ia-em-texto-livre` do map tem `prototipo.arquivo: n/a` | ✓ |

### Grupo 4 — prototipo_sha (confirmado) e gerado_em (refutado)

Recomputado com o próprio `computeProtoHash` de `scripts/design/gerar-map.mjs` sobre `prototipo-ui/cowork/Wagner/ponto-telas.jsx` materializado de cada ref (71.747 B em origin/main · 71.773 B em HEAD):

```
main : sha256:e4d0b5a3707e   ← valor antigo dos 15 maps em origin/main
head : sha256:e8b74d75e0d5   ← valor novo dos 15 maps no lote
wt   : sha256:e8b74d75e0d5
nao-existe (controle): sem-arquivo
```

Logo os 15 `prototipo_sha` estão **corretos** para o protótipo que o mesmo PR embarca. O que falha é o irmão `gerado_em` — ver REFUTADOS.

### Grupo 5 — máquinas (rc literal)

| comando | rc | leitura |
|---|---|---|
| `node scripts/governance/design-code-map-check.mjs --check --strict` | 0 | `[OK] nenhum map.json com âncora quebrada ou sha stale` · cobertura 82/82 · idade da afirmação: "mesmo dia 16" (os 15 do Ponto entram aqui POR CAUSA do gerado_em preservado — com regeneração cairiam em "1-7d") |
| `node scripts/governance/plans-index.mjs --check` | 0 | em dia |
| `node scripts/governance/doc-id-index.mjs --check-collisions` | 0 | 0 colisões em 2863 ids |
| `node scripts/governance/cowork-mirror-freshness.mjs --unverified --check` | 0 | mexido-depois 0 · intactos 878 · nunca verificados 13 |
| `node scripts/governance/requisitos-status.mjs Ponto --check` | **1** | `_STATUS-GENERATED.md DRIFADO` — `--write` muda só `UC declarados 102→103` e `UC com teste 97→98`. **Pré-existente, não é do lote:** o conjunto de `UC-*` únicos nos `*.casos.md` do Ponto é **101 em origin/main e 101 em HEAD**, e `git diff origin/main...HEAD -- resources/js/Pages/Ponto | grep -c UC-` = **0** (os 2 casos.md do PR só mudam `last_run`); o arquivo é byte-idêntico nas duas refs (último toque: `11a52efb2`, o próprio tip de main) |

## REFUTADOS

### R1–R15 · `memory/requisitos/Ponto/*.map.json` (15 arquivos) · chave `gerado_em`

- **Arquivos:** `aprovacoes-index` · `banco-horas-index` · `banco-horas-show` · `colaboradores-edit` · `colaboradores-index` · `configuracoes-index` · `configuracoes-reps` · `escalas-form` · `escalas-index` · `importacoes-create` · `importacoes-index` · `importacoes-show` · `intercorrencias-create` · `intercorrencias-index` · `relatorios-index` (`.map.json`, linha 7 em todos).
- **Item:** o par `prototipo_sha: "sha256:e8b74d75e0d5"` + `gerado_em: "2026-09-28"`.
- **Afirmação do lote:** o gerador rodou em 2026-09-28 e produziu um map cujo protótipo tem hash `e8b74d75e0d5`.
- **O que origin/main + o gerador dizem:**
  - `scripts/governance/design-code-map-check.mjs:277` (origin/main): *"`map.gerado_em` é a data em que o GERADOR RODOU"*. `scripts/design/gerar-map.mjs:399`: o CLI sempre chama `gerar(gapPath, { hoje: new Date().toISOString().slice(0,10) })`; `:209` `gerado_em: hoje || …`; `:251` na fusão `--atualizar` "o derivado (prototipo_sha, gerado_em, partes) continua vindo do esqueleto".
  - O hash `e8b74d75e0d5` só existe para o conteúdo com `contrato="escalaform-dados-da-escala"`/`"intercorrencias-dados-da-ocorrencia"`, introduzido em `9bf8b6f1a` (author e committer date **2026-09-29 07:39 -0300**; repo não-raso). Em 2026-09-28 o gerador só podia produzir `e4d0b5a3707e` (o valor que origin/main tem).
  - Varredura contada de quem escreve o campo (`rg --hidden -g '!.git/**' "prototipo_sha" -- scripts .claude .github` = 7 arquivos; com atribuição, excluídos selftests/checker/consumidor: **1 escritor de map.json, `gerar-map.mjs:170`**; `render-proto-baseline.mjs:515` escreve o SEU baseline, não o map). Não existe caminho canônico que atualize o sha e preserve `gerado_em`.
  - Prova por regeneração: `node scripts/design/gerar-map.mjs memory/requisitos/Ponto/{escalas-form,intercorrencias-create,aprovacoes-index}-gap.md --atualizar` → nos 3, `partes[]` byte-idênticas ao lote, `prototipo_sha` idêntico, **`gerado_em: "2026-09-29"`**; único delta chave a chave. Árvore restaurada com `git checkout HEAD -- <map>` (status limpo antes e depois).
- **Porquê é erro do lote:** o `_doc` de cada arquivo declara "Gerado por scripts/design/gerar-map.mjs … regenerar com gerar-map.mjs --atualizar"; o lote editou à mão um derivado que a máquina lê (§5 2026-08-12) e deixou o irmão com um fato datado falso (LC-10 no eixo comentário/derivado). Consequência medida: o `design-code-map-check` computa "idade da afirmação = map.gerado_em − gap.gerado_em" e coloca os 15 no balde "mesmo dia" (16 no corpus) quando a regeneração honesta os poria em "1-7d". Conserto: rodar o `--atualizar` nos 15 gaps (saída idêntica ao lote exceto a data) — ou, se a intenção for preservar 09-28, isso exige mudar o gerador/docblock, não o dado.

## Observações (não contadas)

1. `banco-horas-index.map.json` parte `ordenacao`: `vivo.arquivo = Modules/Ponto/Http/Controllers/BancoHorasController.php` (fora de `resources/js/Pages/**`). Pré-existente em origin/main, o lote não tocou; não é âncora de fundação (`Components/**`) apontada pra tela — é o controller que ordena; fica como nota, não como erro.
2. Citações dos ids antigos (`escalaform-card`, `intercorrencias-card`) sobrevivem em HEAD só em documentos DATADOS do playbook (`17-data-contract-no-tsx.md:34`, `20-gap-intercorrencias.md:21`, `_PATCH-INDICE-2026-09-14.md:132`, `_saida-17.md:46`, `github.md:302`) — todos sob `prototipo-ui/cowork/Wagner/`, espelho de leitura fora do lote, registrando o estado do dia em que foram escritos; `20-gap-intercorrencias.md:21` diz "TODO · a renomear" e agora está atrás do vivo, mas é retrato, não canon vivo.
3. Os 2 gap.md ganharam seção datada 2026-09-29 sem tocar o `gerado_em: 2026-09-28` do frontmatter — coerente (o frontmatter marca quando a TELA foi medida; a seção nova é nota de nomeação, não re-medição). Não contado como erro.
4. Se o erro R1–R15 fosse contado como UMA causa sistemática em vez de 15 itens, a taxa cairia a 0,36%; o protocolo (§2.5 "100% dos itens", §2.6 "erro sistemático de prompt costuma estar espalhado") manda contar por item e reprovar o lote inteiro — o workflow recalcula.
5. `brief-fetch` (Tier A) foi pulado de propósito nesta sessão: o brief traz handoffs do dia, que o mandato proíbe abrir. Registrado como desvio consciente, não esquecimento.

## Scan PII (linhas `+` de `git diff origin/main...HEAD -- memory/requisitos` = 19 linhas)

| padrão | hits | controle positivo |
|---|---|---|
| CPF pontuado | 0 | OK |
| CPF cru (11 dígitos isolados) | 0 | OK |
| CNPJ | 0 | OK |
| telefone BR (DDD + 8/9 dígitos) | 0 | OK |
| telefone cru (10–11 dígitos) | 0 | OK |
| e-mail | 0 | OK |
| valor em reais (símbolo montado por `chr()`, seguido de dígito) | 0 | OK (a 1ª rodada do controle FALHOU por `$` não escapado no regex — corrigido com `re.escape`, re-rodado: 7/7) |

Nomes de cliente do CRM: nenhum nas 19 linhas (conteúdo = 15 hashes + 2 seções sobre ids de região).

## Comandos reproduzíveis

```bash
git rev-parse HEAD origin/main --is-shallow-repository
git diff --name-status origin/main...HEAD -- memory/requisitos
git diff origin/main...HEAD -- memory/requisitos/Ponto/*.map.json | grep -E '^[-+] '     # só prototipo_sha muda
git grep -n -e escalaform-card -e intercorrencias-card -e escalaform-dados-da-escala -e intercorrencias-dados-da-ocorrencia origin/main -- .
git show origin/main:prototipo-ui/cowork/Wagner/ponto-telas.jsx | awk '(NR>=296&&NR<=300)||(NR>=526&&NR<=530){print NR": "$0}'
git show origin/main:resources/js/Pages/Ponto/Escalas/Form.tsx | awk 'NR>=86&&NR<=95||NR>=149&&NR<=157{print NR": "$0}'
git show origin/main:resources/js/Pages/Ponto/Intercorrencias/Create.tsx | awk 'NR>=168&&NR<=175||NR>=238&&NR<=247{print NR": "$0}'
# hash: import { computeProtoHash } from 'file:///.../scripts/design/gerar-map.mjs' sobre o jsx materializado de cada ref
git log --format='%h %ad %cd %s' --date=iso origin/main..HEAD
rg --hidden -g '!.git/**' -l "prototipo_sha" -- scripts .claude .github
node scripts/design/gerar-map.mjs memory/requisitos/Ponto/escalas-form-gap.md --atualizar && git diff -- memory/requisitos/Ponto/escalas-form.map.json && git checkout HEAD -- memory/requisitos/Ponto/escalas-form.map.json
node scripts/governance/design-code-map-check.mjs --check --strict
node scripts/governance/requisitos-status.mjs Ponto --check
node scripts/governance/plans-index.mjs --check
node scripts/governance/doc-id-index.mjs --check-collisions
node scripts/governance/cowork-mirror-freshness.mjs --unverified --check
for t in Aprovacoes/Index …; do node scripts/design/ancora.mjs Ponto/$t --staging prototipo-ui/cowork/Wagner; done
for r in origin/main HEAD; do git grep -ho -E "UC-[A-Z0-9]+-[0-9]+" $r -- 'resources/js/Pages/Ponto/**/*.casos.md' | sort -u | wc -l; done   # 101 · 101
```

```json
{"itens_verificados": 281, "erros_confirmados": 15, "error_rate_pct": 5.34, "pii_hits": 0, "veredito": "reprovado"}
```
