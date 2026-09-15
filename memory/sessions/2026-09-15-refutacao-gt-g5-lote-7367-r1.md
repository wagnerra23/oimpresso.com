---
date: "2026-09-15"
topic: "Refutação GT-G5 r1 do lote #7367 (11 visual-comparison/gap em memory/requisitos) — comentário YAML inline movido para linha própria; 82 itens verificados, 0 refutados, PII 0/7"
authors: ["C"]
prs: [7367]
outcomes:
  - "Lote APROVADO na r1: 82 itens verificados em 5 grupos (âncora existe · leitor real · prosa/data/sha · célula íntegra · máquinas), 0 erros confirmados, error_rate 0,00%"
  - "Provado com o leitor real (`chaveAninhada` do ancora.mjs e `frontmatter()` do _lib-charter) que em origin/main o comentário inline era CONCATENADO ao valor das 17 chaves; em HEAD o valor sai limpo e nenhuma chave espúria nasce"
  - "PII: 0 hits em 35 linhas `+`, 7/7 controles positivos OK (o padrão de reais precisou de 3 tentativas — o par de barras colapsou no heredoc, §5 2026-08-19; a sonda cega foi detectada pelo controle, não pelo zero)"
---

## TL;DR

Veredito **aprovado**: 82 itens verificados contra `origin/main` (84af54af35), **0 erros confirmados**, error_rate **0,00%**, PII **0 hits** com 7/7 controles positivos. O lote faz uma coisa só — tira o comentário `# removido em <data>, <sha>` da mesma linha do valor em 17 chaves de frontmatter (mais 1 deleção no sidebar-gap cuja informação já vivia em `prototipo_nota`) — e cada um dos 6 pares (path, commit) que ele cita confere no git: o path existia no pai, sumiu no commit citado, está ausente em `origin/main`, e a data do comentário é a data do commit.

## Cabeçalho

| Campo | Valor |
|---|---|
| Base | `origin/main` = `84af54af35bccd0d116ac2af09f4b5ae4eef6ed2` |
| HEAD do lote | `187056f616978e401d3d7591461539c991902ad9` |
| Repo raso | `false` (`git rev-parse --is-shallow-repository`) — datas de `git log` valem como recibo |
| Sessão fresca | sim — instância nova; não abri `memory/sessions/*refutacao*` nem `memory/handoffs/` de hoje (um `git grep -c` contou 2 arquivos de refutação em `memory/sessions/` como HITS de um padrão; só o nome apareceu, conteúdo não foi lido) |
| Corpo do PR / commit message | não usados como evidência |
| Arquivo de evidência pré-existente | não (`NAO_EXISTE` antes da escrita) |
| Tipo | anchors · amostra 100% |

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (refutador Fable 5.1; o gerador é `[C]` no assunto do commit — tier não declarado no lote, o ledger-check resolve)
- [x] Amostra: 100% anchors (18 linhas tocadas + 26 âncoras irmãs do frontmatter + 9 telas via `ancora.mjs`)
- [x] Cada item verificado contra o código real em origin/main, não contra o diff (`git ls-tree`, `git diff-tree`, `git show origin/main:`, worktree temporário em `origin/main` para as máquinas)
- [x] Cada REFUTADO anotado com evidência — não houve refutado
- [x] Scan PII no diff — 7 padrões × controle positivo, 0 hits
- [x] `error_rate_pct` calculado e < 2 (0,00)
- [ ] Entry no ledger — não é papel desta rodada (não escrevo no ledger)

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` → 11 arquivos `M` (bate 1:1 com a lista do mandato); `git diff --name-status origin/main...HEAD` sem filtro → os mesmos 11 (o PR não toca nada fora de `memory/requisitos`). `--stat`: 35 inserções / 18 deleções.

O que o lote faz, por arquivo:

| Arquivo | Chaves | Comentário movido para linha própria |
|---|---|---|
| Financeiro/boletos-visual-comparison.md | `canon_reference` | removido em 2026-05-20, `1070e3759b7` |
| Financeiro/fluxo-visual-comparison.md | `canon_reference` | removido em 2026-05-20, `1070e3759b7` |
| Produto/_telas/produto-{bulk-edit,create,edit,index,selling-prices,show,stock-history}-visual-comparison.md (7) | `canon_reference` + `blueprint_cowork` | removido em 2026-05-20, `1070e3759b7` (×14) |
| Sells/index-r1-visual-comparison.md | `visual_source_html` | removido em 2026-05-19, `7810bf5cb40` |
| _DesignSystem/sidebar-v3-unificado-gap.md | `prototipo_nota` | comentário inline **deletado** (sem linha nova); a mesma informação — apagado em 2026-06-23, commit `9da73296d3` — já está dentro do próprio valor de `prototipo_nota` |

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---|---|---|
| 1. Âncora existe / foi removida como o lote diz | 18 | 18 | 0 | Para cada um dos 6 pares (path, commit): `git cat-file -t` + `merge-base --is-ancestor origin/main` (3 commits, todos ancestrais de main); `git ls-tree -r c^ -- <path>` (existia no pai), `git ls-tree -r c -- <path>` = 0, `git ls-tree -r origin/main -- <path>` = 0, `git diff-tree -r --diff-filter=D c^ c -- <path>` = 1 (dir `produto-cockpit/` = 3). Controle negativo `prototipo-ui/prototipos/NAO-EXISTE.jsx` → 0; `prototipo-ui/prototipos` inteiro não existe em main (0). Contagem = 18 linhas de frontmatter (2 Financeiro + 14 Produto + 1 Sells + 1 sidebar) |
| 2. Lida pelo leitor real / não revogada | 27 | 27 | 0 | Regenerei o valor das 18 chaves com os DOIS leitores de verdade (`chaveAninhada` de `scripts/design/ancora.mjs` e `frontmatter()` de `scripts/design/_lib-charter.mjs`) sobre `git show origin/main:<f>` × HEAD: em main o valor vem com `  # removido em …` colado (`desasparValor` só tira aspas, não tira comentário); em HEAD vem limpo; 0 chaves espúrias começando com `#`. Mais 9 telas via `node scripts/design/ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork/Wagner` (rc=0 em todas): nenhuma REVOGADA/MIS-ANCHOR; `canon_reference (visual-comparison)` listado como não-âncora com o path limpo |
| 3. Prosa: data e sha do comentário × git; nada reaberto; nada carimbado hoje | 19 | 19 | 0 | 18 comentários: `git log -1 --date=short` dá cd=ad=2026-05-20 para `1070e3759b7`, 2026-05-19 para `7810bf5cb40`, 2026-06-23 para `9da73296d34` — batem com o texto; shas resolvem. +1: sidebar — comentário deletado, informação preservada literalmente em `prototipo_nota` (conferido no valor regenerado). Nenhum `date:`/`status`/`approved_*` alterado; nenhum veredito reaberto |
| 4. Célula íntegra / YAML | 11 | 11 | 0 | Frontmatter dos 11 parseado com `js-yaml` (OK, 11–16 chaves cada); o comentário em linha própria é comentário YAML legítimo; `frontmatter()` não gera chave a partir de linha iniciada por `#`. Sem tabela derivada/map.json no lote |
| 5. Máquinas derivadas | 7 | 7 | 0 | `doc-id-index.mjs --check-collisions` rc=0 (0 colisão em 2747 ids); `plans-index.mjs --check` rc=0; `requisitos-status.mjs Financeiro --check` rc=0; `… Produto --check` rc=0; `… Sells --check` **rc=1 herdado** (idêntico em worktree de `origin/main`, e o `--write` local não toca nenhuma linha com `index-r1` — restaurado com `git checkout HEAD --`); `design-code-map-check.mjs --check --strict` **rc=1 herdado** (4 STALE, todos `Ponto/espelho-*.map.json`, 0 hits para sidebar/_DesignSystem; as linhas de veredito são idênticas main==HEAD); `validate-memory-schema.sh` não tem família para `visual-comparison`/`gap-spec` (nada a validar — js-yaml acima é o proxy do AJV) |
| **Total** | **82** | **82** | **0** | |

## REFUTADOS

Nenhum.

## Observações não contadas

1. **Âncora irmã morta (pré-existente, não tocada pelo lote):** `boletos-visual-comparison.md:11` `inertia_target_atual: resources/js/Pages/Financeiro/Boletos/Index.tsx` — `git ls-tree origin/main` = 0; apagado em `83b735348d1` (2026-05-19, #1142 "cleanup Pages/Boletos legacy"). O lote passou por este frontmatter e anotou só o `canon_reference`; a linha 11 segue apontando para arquivo inexistente sem anotação. Não é erro do lote (a linha não foi escrita nem datada por ele), é dívida do arquivo. Das 26 âncoras irmãs conferidas, 25 existem em main (Controllers, Services com `projetar(int $businessId, int $dias = 35)` na L57 e `index()` na L46, blades, Pages, `vendas-page.jsx`, os 4 `tela_viva`, `prototipo-ui/design-system`), US-FIN-014 no SPEC (2 hits), ADRs 0093/0104/0107/0109/0110/0114/0141/0143 existem.
2. **`related_adrs: [ui/0114, 0093]`** (boletos e fluxo, pré-existente): não existe ADR UI-0114 — a série `adr/ui/` termina em 0033. Provavelmente queria a 0114 raiz. Fora do lote.
3. **Mesmo defeito em 2 arquivos fora do lote:** `git grep -E "\S\s+# removido em 20" origin/main -- memory resources` = 24 linhas; após o lote sobram 6 em HEAD: `KB/CAPTERRA-FICHA.md:8-9` (itens de lista) e `OficinaAuto/producao-oficina-cacamba-visual-comparison.md:9-10` (`canon_reference_v1/_v2`) + 2 em sessões de refutação. Nenhum leitor real casa essas chaves (`chaveAninhada` usa `startsWith('canon_reference:')`, que não casa `canon_reference_v1:`), então o buraco de leitor que o lote fecha está fechado; ficam como higiene de consistência, não como omissão contável.
4. **Assimetria de convenção no sidebar-gap:** único arquivo em que o comentário foi deletado em vez de movido. Justificável (a `prototipo_nota` já diz tudo, inclusive o sha), mas quem for repetir o padrão deve saber que são duas formas.
5. **Prosa stale herdada no sidebar-gap (não tocada):** `governanca` cita UI-0009/UI-0014 "sidebar light" — a UI-0023 supersede ambas (sidebar preta DEFINITIVO). Fora do lote; não é carimbo de hoje.
6. **Lição própria da rodada (não é do lote):** minha sonda do padrão de reais ficou cega duas vezes — primeiro o símbolo não escapado virou âncora de fim de linha, depois o par de barras colapsou no heredoc (o `re.source` impresso perdeu as barras e as classes `s`/`d` viraram letras literais — §5 2026-08-19). Só o controle positivo denunciou; um `hits=0` sem controle teria passado como limpo.

## Scan PII (linhas `+` de `git diff origin/main...HEAD -- memory/requisitos` — 35 linhas)

| Padrão | Hits | Controle positivo |
|---|---|---|
| CPF pontuado (`\d{3}\.\d{3}\.\d{3}-\d{2}`) | 0 | OK |
| CPF cru (11 dígitos isolados) | 0 | OK |
| CNPJ (`\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}`) | 0 | OK |
| Telefone BR (DDD + 8/9 dígitos formatado) | 0 | OK |
| Telefone cru (10–11 dígitos isolados) | 0 | OK |
| E-mail | 0 | OK |
| Símbolo de reais seguido de dígito (padrão não reproduzido aqui, de propósito) | 0 | OK (após corrigir a sonda; controle negativo `RS 12` não casa) |
| Nomes de cliente CRM (sanity: LTDA / ME / Larissa / ROTA LIVRE / Martinho) | 0 | — |

**pii_hits = 0 · controles positivos = 7/7.**

## Comandos reproduzíveis

```bash
git rev-parse --is-shallow-repository; git rev-parse HEAD origin/main
git diff --name-status origin/main...HEAD -- memory/requisitos
for c in 1070e3759b7 7810bf5cb40 9da73296d34; do git log -1 --format='%h %cd %ad' --date=short $c; git merge-base --is-ancestor $c origin/main && echo ok; done
# remoção: existia no pai, sumiu no commit, ausente em main
p='prototipo-ui/prototipos/boletos/cowork-app.jsx'; c=1070e3759b7
git ls-tree -r --name-only $c^ -- "$p"; git ls-tree -r $c -- "$p" | wc -l; git ls-tree -r origin/main -- "$p" | wc -l; git diff-tree -r --diff-filter=D --name-only $c^ $c -- "$p"
git ls-tree origin/main -- prototipo-ui/prototipos/NAO-EXISTE.jsx | wc -l   # controle negativo = 0
# leitor real (main × HEAD) — ver scratch leitor.mjs: importa chaveAninhada (ancora.mjs) e frontmatter (_lib-charter.mjs)
node scripts/design/ancora.mjs Produto/Index --staging prototipo-ui/cowork/Wagner
node scripts/governance/doc-id-index.mjs --check-collisions
node scripts/governance/plans-index.mjs --check
node scripts/governance/requisitos-status.mjs Sells --check       # rc=1 também em origin/main
node scripts/governance/design-code-map-check.mjs --check --strict # rc=1 também em origin/main (4 STALE Ponto)
git grep -E "\S\s+# removido em 20" origin/main -- memory resources | wc -l   # 24
git grep -E "\S\s+# removido em 20" HEAD -- memory resources | wc -l          # 6
git diff origin/main...HEAD -- memory/requisitos | grep -E '^\+' | grep -vE '^\+\+\+' > plus.txt  # base do scan PII
git status --short   # vazio antes da evidência
```

```json
{"itens_verificados": 82, "erros_confirmados": 0, "error_rate_pct": 0.0, "pii_hits": 0, "veredito": "aprovado"}
```
