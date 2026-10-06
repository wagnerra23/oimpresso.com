---
date: "2026-10-06"
topic: "Refutação GT-G5 r1 do lote #8746 (28 derivados em memory/requisitos: 16 map.json · 9 proto-baseline · 3 _STATUS-GENERATED) — 191 itens, 0 refutados, PII 0/7 controles 7/7"
authors: ["C"]
prs: [8746]
outcomes:
  - "Lote APROVADO na rodada r1: 191 itens verificados contra origin/main, 0 erros confirmados, error_rate 0%"
  - "Todas as 25 identidades derivadas (prototipo_sha dos 16 maps, prototipo_sha + render_sha256 dos 9 baselines) recomputadas pelo gerador do repo e batendo; controle positivo no archive de origin/main reproduz as identidades antigas"
  - "Scan PII com 7 padrões e 7/7 controles positivos: 0 hits nas 397 linhas adicionadas; observação não contada: doc-id-index --check já estava em drift em origin/main (handoffs de 05/10), não é efeito do lote"
---

# Session log 2026-10-06 — Refutação GT-G5 · lote PR #8746 · rodada r1

## TL;DR

**Veredito: APROVADO.** 191 itens verificados em 6 grupos contra `origin/main` (`f95ca89005`) e contra os geradores do repo; **0 erros confirmados** (error_rate **0,00%**); scan PII **0 hits** com **7/7** controles positivos casando. O lote é 100% derivado (identidade de protótipo re-exportado + regeneração de `_STATUS-GENERATED` que estava stale em main), e cada derivado foi reproduzido pela máquina dona em vez de lido.

## Cabeçalho

| Campo | Valor |
|---|---|
| PR / lote | #8746 · tipo **anchors** · amostra **100%** dos itens |
| HEAD medido | `0d7977cc7ce078486d920a4e64f0f19e8fb34349` (branch `claude/import-projeto-2026-10-06`) |
| Base | `origin/main` = `f95ca89005cc2dc35118d107db983f1619632b7d` |
| Repo raso? | `git rev-parse --is-shallow-repository` → **false** (datas de `git log` valem) |
| Sessão fresca | sim — instância nova, sem contexto do gerador; **não** abri `memory/sessions/*refutacao*` nem `memory/handoffs/` de hoje; **não** li corpo do PR nem commit message como evidência |
| Modelo | Fable 5.1 (tier máximo disponível) |
| Árvore ao final | `git status --short` vazio exceto esta evidência (a única sondagem que escreveu — `doc-id-index --write` — foi restaurada com `git checkout --`) |

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (igualdade só no tier máximo — Fable é o teto)
- [x] Amostra: 100% anchors (lote é só anchors/derivados; não há prosa destilada → sem seed)
- [x] Cada item verificado contra o código real em origin/main / gerador do repo, não contra o diff
- [x] Cada REFUTADO anotado com evidência — **nenhum**
- [x] **Scan PII no diff** — 7 padrões × controle positivo → 0 hits
- [x] `error_rate_pct` calculado e < 2 → **0,00**
- [ ] Entry no ledger no mesmo PR — fora do meu mandato (o workflow/gerador registra; eu não escrevo no ledger)

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` → **28** arquivos, todos `M` (bate com o enunciado): **16** `*.map.json` (Atendimento 1 · Essentials 6 · Officeimpresso 2 · OficinaAuto 2 · Superadmin 5), **9** `*.proto-baseline.json` (Compras 1 · Financeiro 5 · KB 1 · Sells 1 · TeamMcp 1), **3** `_STATUS-GENERATED.md` (Cliente · Crm · Produto). O PR inteiro toca 127 arquivos; os 99 fora do lote são `prototipo-ui/cowork/Wagner/**` (import do export Cowork) + `scripts/design-sync/state/*` + `.cowork-freshness-ledger.json` — fora do mandato, usados só como insumo dos geradores.

**Natureza do diff do lote (lido arquivo a arquivo):**
- 16 maps: só `prototipo_sha` e `gerado_em` mudaram (4 linhas cada); `partes` intactas.
- 9 baselines: `prototipo_sha` **inalterado** em todos; `render_sha256` `3d6db528…` → `a669c36d…` em todos; `gerado_em` novo; células re-renderizadas (Sells 54 hunks por `vendas.css`; os demais, trocas `rgb(0,0,0)`→`rgb(255,255,255)` em células dark).
- 3 `_STATUS-GENERATED`: +24 linhas de UC (CGRP-01..05 · CRMACO-08..20 · PCADAP-17..22) e 8 contadores do cabeçalho.

## Tabela por grupo

| # | Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---:|---:|---:|---|
| 1 | Âncora existe em origin/main | 66 | 66 | 0 | 58 paths únicos extraídos dos 25 JSON (`gap_fonte`, `prototipo.arquivo`, `vivo.arquivo`, `ancora`) → 57 blobs via `git ls-tree origin/main` + 1 valor `n/a` (declaração, não path) + 9 `charter` dos baselines (9 blobs). Classes: 24 `Pages/**`, **0** `Components/**`, 17 `prototipo-ui/`, 17 outros. Controle negativo: path inexistente → saída vazia |
| 2 | Âncora não revogada · lida pelo leitor real | 58 | 58 | 0 | `ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork/Wagner` → `âncora ✓` nas 9 telas dos baselines + 15 telas dos maps (24), e o arquivo resolvido = o citado no map/baseline; `computeProtoHash(resolverArquivosPrototipo(gap.prototipo))` recomputado em HEAD = `prototipo_sha` novo nos **16/16** maps (controle: o mesmo cálculo no `git archive origin/main` = sha antigo **16/16**); `git log -1 --format=%H origin/main -- <ancora>` = `prototipo_sha` dos **9/9** baselines; `superficieRender()` em HEAD = `a669c36d…` (**9/9** baselines) e no archive de main = `3d6db528…` (controle) |
| 3 | Ação × veredito da prosa · afirmação sobre código | 32 | 32 | 0 | 24 UC novos: cada um existe no `.casos.md` da tela indicada na coluna "Tela" **e** é citado em ≥1 `*Test.php` em origin/main (24/24); `Grupos/Index.tsx` existe em main (Telas 7→8); os 8 contadores regenerados por `requisitos-status.mjs <Mod> --check` → "em dia" nos 3 módulos |
| 4 | Célula íntegra | 28 | 28 | 0 | 25 JSON parseiam (`JSON.parse`) e as chaves derivadas (`prototipo_sha`/`render_sha256`/`gerado_em`) são as que o gerador emite hoje; 3 tabelas md regeneradas byte-a-byte pelo gerador (`--check` = igual ao commitado); nenhuma célula `ModuleStub`/"Módulo legado" nos baselines (grep rc=1; controle `"texto": "Autorizada` = 20 hits) |
| 5 | Máquina derivada | 7 | 7 | 0 | rc literal de cada `--check` (abaixo) |
| 6 | PII (não conta como item) | 7 padrões | — | 0 hits | 7/7 controles positivos casam |
| | **Total** | **191** | **191** | **0** | |

## Grupo 5 — rc literal de cada máquina

| Comando | rc | Saída-chave |
|---|---:|---|
| `node scripts/governance/requisitos-status.mjs Cliente --check` | 0 | `✓ … em dia.` |
| `node scripts/governance/requisitos-status.mjs Crm --check` | 0 | `✓ … em dia.` |
| `node scripts/governance/requisitos-status.mjs Produto --check` | 0 | `✓ … em dia.` (varri também todos os outros módulos com `_STATUS-GENERATED.md`: nenhum stale em HEAD) |
| `node scripts/governance/design-code-map-check.mjs --check --strict` | 0 | `[OK] nenhum map.json com âncora quebrada ou sha stale` · 82/82 maps · 5 WARN de idade não-medida em maps **fora** do lote |
| `node scripts/design/render-proto-baseline.mjs --check` | 0 | `✓ 9 baseline(s) íntegro(s)` — todos `âncora ✓ · sha ✓ · 4 células`, sem "render NÃO MEDIDO" (logo o `render_sha256` foi medido e bate) |
| `node scripts/governance/plans-index.mjs --check` | 0 | `✓ PLANS-INDEX-GENERATED.md em dia` |
| `node scripts/governance/doc-id-index.mjs --check-collisions` | 0 | `OK: 0 colisão de id em 2912 ids` |

Arquivo que o lote regenerou e não está no diff: **nenhum** — os 9 baselines do repo são exatamente os 9 do lote (o `--check` varre tudo) e os 16 maps cujo protótipo mudou neste PR são exatamente os 16 do lote (o strict acusa stale em qualquer outro, e acusou 0).

## REFUTADOS

Nenhum.

## Observações não contadas

1. **`doc-id-index.mjs --check` (frescor) sai rc=1 em HEAD — mas o drift é PRÉ-EXISTENTE em origin/main, não do lote.** Sondei com `--write` (restaurado via `git checkout -- governance/doc-id-index.json`): o delta são 3 handoffs de 2026-10-05 + `memory/requisitos/Cliente/RUNBOOK-grupos.md`, e os 4 **existem em `origin/main`** (`git ls-tree` devolve blob para os 4). O lote não adiciona nenhum `.md` ao corpus do índice. O CI roda só `--check-collisions` por desenho (nota no header do script, medido 2026-07-30), e o hook de sonda confirmou que `doc-id` **não** está nas listas de required (48 contexts). Fica como higiene fora do PR.
2. **Causa do `render_sha256` novo (não é defeito):** o PR muda `oimpresso.com.html` (52 linhas), `app.jsx`, `styles.css` (+4, incl. `html[data-theme="dark"]{ color-scheme: dark; }`) e `vendas.css` (+11). `superficieRender()` hasheia o grafo inteiro (shell + CSS + JS + DS), então a regeneração dos 9 baselines é **obrigatória** — e as trocas `rgb(0,0,0)`→`rgb(255,255,255)` nas células dark são consistentes com `color-scheme: dark`. O `prototipo_sha` (git-sha da âncora) não mudou porque nenhuma das 6 âncoras `.jsx` dos baselines foi tocada no PR — `vendas-page.jsx` idem; o Sells mudou 54 hunks via `vendas.css`.
3. **Os `_STATUS-GENERATED` estavam stale em main, não "inventados" pelo lote:** os 24 UC novos estão em `.casos.md` + testes **de origin/main** (outro PR adicionou os UC sem regenerar o derivado). O lote só reconciliou.
4. `design-code-map-check` lista `Atendimento/caixa-unificada.map.json` com 105d entre `gap.gerado_em` (2026-06-23) e `map.gerado_em` (2026-10-06) — idade de afirmação, declarada pelo próprio gate como "não é erro". Não contei.
5. O `ancora.mjs` reporta "⚠️ cobertura: o espelho NÃO cobre o vivo — 19 arquivo(s) de 1210 nunca desceram" — aviso sobre o espelho inteiro (ADR 0374), não sobre o lote.
6. `cowork-mirror-freshness.mjs --unverified --check` → rc 0 (`🔴 mexido-depois: 0 · ✓ intactos: 1168 · ⬜ nunca verificados: 6`) — rodado porque o PR edita o espelho (§5 2026-09-24); fora do lote, só recibo.

## Scan PII (linhas `+` de `git diff origin/main...HEAD -- memory/requisitos`, 397 linhas)

| Padrão | Hits | Controle positivo |
|---|---:|---|
| CPF pontuado (`\d{3}\.\d{3}\.\d{3}-\d{2}`) | 0 | casa |
| CPF cru (11 dígitos isolados) | 0 | casa |
| CNPJ (`\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}`) | 0 | casa |
| Telefone BR (DDD + 4-4 com hífen) | 0 | casa |
| Telefone cru (10–11 dígitos isolados) | 0 | casa |
| E-mail | 0 | casa |
| Valor em moeda (símbolo seguido de dígito — padrão montado por charCode, não reproduzido aqui) | 0 | casa |

Nomes de cliente do CRM: as 397 linhas são JSON de identidade/render e tabelas de UC-id — sem nome próprio. **7/7 controles · 0 hits.** (A 1ª rodada do controle de moeda FALHOU por `$` não-escapado dentro de `new RegExp` — corrigido montando a barra por charCode e re-rodado; registro aqui porque sonda que não casa o próprio controle não vale.)

## Comandos reproduzíveis

```bash
# escopo
git rev-parse HEAD origin/main --is-shallow-repository
git diff --name-status origin/main...HEAD -- memory/requisitos | wc -l            # 28

# grupo 1 — existência (controle negativo = saída vazia)
git ls-tree origin/main -- prototipo-ui/cowork/Wagner/compras-page.jsx
git ls-tree origin/main -- prototipo-ui/cowork/Wagner/nao-existe-xyz.jsx

# grupo 2 — identidades
for f in compras-page financeiro-telas-extras kb-page forja-page financeiro-page vendas-page; do
  git log -1 --format=%H origin/main -- prototipo-ui/cowork/Wagner/$f.jsx; done   # = prototipo_sha dos 9 baselines
node scripts/design/ancora.mjs Sells/Index --staging prototipo-ui/cowork/Wagner      # idem p/ as outras 23 telas
# recompute (import file:///…/scripts/design/gerar-map.mjs → computeProtoHash/resolverArquivosPrototipo
#   sobre gap.prototipo; import render-proto-baseline.mjs → superficieRender(MIRROR_DIR))
#   HEAD: a669c36dff7df11220c60352e87030d7330178dd6c9baa62b7c7d6ce8176b743
#   git archive origin/main prototipo-ui/cowork/Wagner prototipo-ui/design-system | tar -x -C <scratch>/main
#   MAIN: 3d6db528cd4c5c7471fd4d5e9e51b68baedb1eea5b18db780bd87dd214c542fd

# grupo 3
git grep -l UC-CGRP-01 origin/main -- '*.casos.md' 'tests/**/*Test.php'

# grupo 5
node scripts/governance/requisitos-status.mjs Cliente --check
node scripts/governance/design-code-map-check.mjs --check --strict
node scripts/design/render-proto-baseline.mjs --check
node scripts/governance/plans-index.mjs --check
node scripts/governance/doc-id-index.mjs --check-collisions

# grupo 6
git diff origin/main...HEAD -- memory/requisitos | grep '^+' | grep -v '^+++' > plus.txt   # 397 linhas → 7 regex + 7 controles
```

```json
{"itens_verificados": 191, "erros_confirmados": 0, "error_rate_pct": 0.0, "pii_hits": 0, "veredito": "aprovado"}
```
