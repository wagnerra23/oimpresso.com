---
date: "2026-10-05"
hour: "09:00 BRT"
duration: "0.5h"
topic: "Refutação GT-G5 r1 do lote #8642 (21 arquivos memory/requisitos: 9 proto-baseline, 11 map.json, 1 _STATUS Sells) — 0 refutados em 283 itens, PII 0 hits com 7/7 controles, veredito aprovado"
authors: [C]
outcomes:
  - "Lote #8642 r1 APROVADO: 283 itens verificados contra a base bb34cf4ed2, 0 erros confirmados (error_rate 0%)"
  - "PII: 0 hits em 49 linhas `+`, 7 de 7 controles positivos casaram"
  - "Observação não contada: origin/main avançou para e1ef4d4530 (#8633) durante a rodada e muda os UC-SCAIXA de 12→14 — o _STATUS de Sells vai drifar de novo no rebase"
prs: [8642]
us: []
related_adrs: ["0324-frescor-espelho-cowork-dispatch-sla-limite-plataforma", "0264-governanca-executavel-trio-dominio-e2e"]
---

# Session log 2026-10-05 — Refutação GT-G5 · lote #8642 · rodada r1

## TL;DR

**Veredito: APROVADO.** 283 itens verificados contra `bb34cf4ed2` (base declarada), **0 erros confirmados**, error_rate **0,00%**, PII **0 hits** (7/7 controles positivos). O lote é 100% derivado por máquina (regeneração de `proto-baseline.json`, `map.json` e `_STATUS-GENERATED.md`): cada derivado foi regenerado pelo gerador do repo e comparado chave a chave; cada hash foi conferido com controle positivo contra o valor antigo.

## Cabeçalho

| Campo | Valor |
|---|---|
| PR | #8642 · branch `claude/crm-thread-07a` |
| HEAD medido | `18be637e0a7c7b8bf68003ad14f0c4086bb23b2f` |
| Base declarada (`origin/main` no despacho) | `bb34cf4ed26cdeb5ff94a63869fa83b1475596d1` = `git merge-base origin/main HEAD` |
| ⚠️ `origin/main` durante a rodada | avançou para `e1ef4d4530` (#8633) por fetch de outra sessão — todas as medições abaixo foram feitas (ou refeitas) contra o sha da base **declarada**, não contra o ref móvel |
| Repo raso | `git rev-parse --is-shallow-repository` → **false** (datas de `git log` valem) |
| Sessão fresca | sim — instância nova, sem contexto do gerador; não abri `memory/sessions/*refutacao*` nem `memory/handoffs/` de hoje; corpo do PR/commit não foi lido como evidência |
| Modelo | Fable 5.1 (tier máximo; gerador do lote = [CL] agente, tier ≤ este) |
| Protocolo | PROTOCOLO-REFUTADOR-BACKFILL §2–§4 + §6 lidos; §7 não lido |

## §3 Checklist

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (igualdade só no tier máximo — este é o tier máximo)
- [x] Amostra: 100% anchors (lote é só anchors/derivados; sem prosa destilada → sem seed)
- [x] Cada item verificado contra o código real na base `bb34cf4ed2`, não contra o diff
- [x] Cada REFUTADO anotado com evidência — **nenhum REFUTADO**
- [x] Scan PII no diff — 7 padrões × 49 linhas `+`, 0 hits, 7/7 controles positivos
- [x] `error_rate_pct` calculado = 0 < 2
- [ ] Entry no ledger — **fora do mandato desta rodada** (não escrevi no ledger; o workflow faz)

## Escopo medido

`git diff --name-status bb34cf4ed2...HEAD -- memory/requisitos` → **21 arquivos, todos `M`** (49 `+` / 43 `−`), batendo com a lista do despacho:

| Grupo de arquivo | N | O que mudou no diff |
|---|---:|---|
| `*.proto-baseline.json` (Compras, Financeiro×5, KB, Sells, TeamMcp) | 9 | só `gerado_em` + `render_sha256` (`9046d5ef…` → `3d6db528…`, idêntico nos 9); `prototipo_sha`, `ancora`, `celulas` intactos |
| `Repair/*.map.json` | 7 | só `prototipo_sha` (`sha256:f1a032ff88ed` → `sha256:0508ec5a639a`) + `gerado_em` 2026-10-02 → 2026-10-05 |
| `Superadmin/*.map.json` | 4 | só `prototipo_sha` (`sha256:003ec9abd842` → `sha256:2aa2d686e92e`) + `gerado_em` 2026-09-14 → 2026-10-05 |
| `Sells/_STATUS-GENERATED.md` | 1 | placar 59→60 US · 97→102 UC · 96→101 UC-com-teste; +1 linha US-SELL-066; +5 linhas UC (S07, SCAIXA-10/11/12, SEDIT-12) |

Fora do lote (não avaliado, só anotado): o PR também toca `prototipo-ui/cowork/Wagner/**` (handoff 45 do Cowork), `scripts/design-sync/state/*.json` e `.cowork-freshness-ledger.json`.

## Tabela por grupo

| # | Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---:|---:|---:|---|
| 1 | Âncora existe na base | 45 | 45 | 0 | 318 citações de path nos 20 JSON (130 `prototipo.arquivo` + 130 `vivo.arquivo` + 11 `gap_fonte` + 9 `ancora` + 9 `charter` + 20 `tela` + 9 `prototipo_sha`) resolvem para **39 paths únicos** → `git ls-tree bb34cf4ed2 -- <path>` devolve blob em 39/39 (controle negativo `resources/js/Pages/NAO/Existe.tsx` → 0 linhas); + US-SELL-066 no SPEC da base (`git grep -c` = 1, SPEC.md:1481) + 5 UC-ids nos `.casos.md` da base (UC-S07 Create.casos.md · UC-SCAIXA-10/11/12 Caixa/Index.casos.md · UC-SEDIT-12 Edit.casos.md). 0 paths sob `Components/**` (fundação apontada pra tela: 0) |
| 2 | Âncora não revogada · lida pelo leitor real | 51 | 51 | 0 | (a) `ancora.mjs <tela> --staging prototipo-ui/cowork/Wagner` nas 20 telas: 20× rc=0, âncora resolve (16 `âncora ✓`; 4 telas Repair declaram `n/a (herda PT-0X)` **e** o gap.md/map carregam a nota de modo PARIDADE, coerente); `git grep -i 'REVOGAD\|MIS-ANCHOR'` nos 20 charters → 4 hits, **nenhum sobre a âncora usada** (Negocios:94 revoga "DECLARADAS"; JobSheet:109/128 revoga GUARDs; ProducaoOficina:15 revoga `oficina-page.jsx`, não `repair-page.jsx`); controle positivo do grep: `MIS-ANCHOR` casa em ProducaoOficina. (b) 11 maps regenerados com `gerar-map.mjs <gap.md>` → `prototipo_sha` gerado == committed em 11/11. (c) 9 baselines: `prototipo_sha` == `git log -1 --format=%H HEAD -- <ancora>` em 9/9 (todos `cat-file -t` = commit). (d) 11 `prototipo_sha` dos maps conferidos por `computeProtoHash` exportado de `gerar-map.mjs`: HEAD → `0508ec5a639a` / `2aa2d686e92e`; **controle positivo**: o mesmo hash sobre o blob da base (`git show bb34cf4ed2:…repair-page.jsx` / `superadmin-page.jsx` em scratch) devolve exatamente os valores ANTIGOS `f1a032ff88ed` / `003ec9abd842` — o hash discrimina e os dois lados batem |
| 3 | Ação × veredito · afirmação sobre código | 140 | 140 | 0 | 130 `acao`/`id`/`prototipo.arquivo` das partes: esqueleto regenerado × committed, igual em 11/11 maps (ids, ordem, acao, prototipo.arquivo); as partes **não mudaram** no diff (só sha/data). 10 itens do `_STATUS`: 4 contagens + 6 linhas novas — `requisitos-status.mjs Sells --check` rc=0 ("em dia") e os insumos do gerador (SPEC, casos, tests de Sells) são **idênticos** entre base e HEAD (`git diff --stat bb34cf4ed2...HEAD -- …` vazio) → drift herdado do main, como o lote afirma; `desconhecido` em US-SELL-066 = ausência de linha `Status:` no SPEC (gerador L143/L387: anchor `Implementado em` não altera o status), igual às US-063/064/065 vizinhas — não contradiz código |
| 4 | Célula íntegra | 27 | 27 | 0 | 20 JSON parseiam (20/20 `JSON.parse` ok); 7 linhas `+` de tabela no `_STATUS` com contagem de colunas correta (3× 2 colunas no placar, 6× 3 colunas nas tabelas US/UC — a linha US-SELL-066 tem o code-span `/sell-return` fechado); nenhum pipe não-escapado, nenhuma reticência engolindo veredito |
| 5 | Máquina derivada | 13 | 13 | 0 | `render-proto-baseline.mjs --check` sobre os 9 baselines → 9× "íntegro (âncora ✓ · sha ✓ · 4 células)", rc=0 — e **controle positivo**: a versão da BASE do `compras.proto-baseline.json` (extraída pra scratch) sob o mesmo `--check` → "baseline STALE: protótipo, shell ou DS mudaram", rc=1 (o `render_sha256` novo é o do grafo HEAD, onde `app.jsx`/`data.jsx`/`oimpresso.com.html` mudaram); `design-code-map-check.mjs --check --strict` → rc=0 ("nenhum map.json com âncora quebrada ou sha stale", 82/82 cobertura); `requisitos-status.mjs Sells --check` → rc=0; `plans-index.mjs --check` → rc=0; `doc-id-index.mjs --check-collisions` → rc=0 (0 colisões em 2900 ids) |
| 6 | PII | 7 | 7 | 0 | ver tabela abaixo |
| | **Total** | **283** | **283** | **0** | |

## REFUTADOS

Nenhum.

## Observações (não contadas como erro)

1. **`origin/main` moveu durante a rodada** (`bb34cf4ed2` → `e1ef4d4530`, #8633 "Caixa ganha conferência física do turno"). Contra o main novo, `Caixa/Index.casos.md` passa de **12 → 14** ids `UC-SCAIXA-*` e `SellsCaixaContratoTest.php` muda; logo o `_STATUS-GENERATED.md` de Sells que este lote traz (102 UC) estará **de novo desatualizado após o rebase** — é a classe LC-20 (base envelhece sozinha), não erro contra a base declarada. Quem mergear deve re-rodar `requisitos-status.mjs Sells --check` no head rebaseado.
2. Os 8 protótipos-âncora (`repair-page.jsx`, `superadmin-page.jsx`, `compras-page.jsx`, `financeiro-*.jsx`, `kb-page.jsx`, `vendas-page.jsx`, `forja-page.jsx`) têm **blob idêntico** entre `bb34cf4ed2` e `e1ef4d4530` — os hashes dos maps/baselines não são afetados pelo avanço do main.
3. `render_sha256` é idêntico nos 9 baselines **por desenho**: é `superficieRender(root)` = identidade do grafo local inteiro (shell + CSS + JS + DS), não do render da célula; as `celulas` dos 9 arquivos não mudaram.
4. `requisitos-status.mjs --check` em **Superadmin** e **TeamMcp** devolve rc=1 porque `_STATUS-GENERATED.md` **não existe** nesses módulos — nem na base (`git ls-tree` = 0). Pré-existente, fora do lote.
5. `cowork-mirror-freshness.mjs --unverified --check` → rc=0 (0 "mexido-depois", 2 nunca-verificados, não bloqueia) — anotado porque o PR edita arquivos do espelho (§5 2026-09-24), mas é fora do lote.
6. A 1ª versão da minha sonda PII tinha o cifrão sem escape dentro de `new RegExp` e o controle positivo do padrão de reais **falhou (6/7)** — corrigido escapando o metacaractere, 7/7. Fica registrado: sem o controle, o "0 hits" daquele padrão seria vazio de instrumento.

## Scan PII (linhas `+` de `git diff bb34cf4ed2...HEAD -- memory/requisitos`, 49 linhas)

| Padrão | Hits | Controle positivo |
|---|---:|---|
| CPF pontuado (`\d{3}\.\d{3}\.\d{3}-\d{2}`) | 0 | casou |
| CPF cru (11 dígitos isolados) | 0 | casou |
| CNPJ (`\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}`) | 0 | casou |
| Telefone BR formatado (DDD + 4/5-4 dígitos) | 0 | casou |
| Telefone cru (10–11 dígitos isolados) | 0 | casou |
| E-mail | 0 | casou |
| Valor em reais (símbolo da moeda com cifrão escapado, seguido de dígito — literal não reproduzido aqui de propósito) | 0 | casou |
| **Total** | **0** | **7/7** |

Nomes de cliente do CRM: nenhum nas 49 linhas (são timestamps, hashes, ids de US/UC e contagens).

## Comandos reproduzíveis

```bash
B=bb34cf4ed26cdeb5ff94a63869fa83b1475596d1
git rev-parse --is-shallow-repository                      # false
git diff --name-status $B...HEAD -- memory/requisitos      # 21 M
# G1 — existência na base (39 paths únicos extraídos dos 20 JSON em scratch/paths.txt)
while read p; do git ls-tree $B -- "$p" | grep -q . || echo MISSING $p; done < paths.txt
git ls-tree $B -- resources/js/Pages/NAO/Existe.tsx         # controle negativo: vazio
git grep -c 'US-SELL-066' $B -- memory/requisitos/Sells/SPEC.md
git grep -c 'UC-SCAIXA-1[012]\b' $B -- resources/js/Pages/Sells/Caixa/Index.casos.md
# G2 — âncora + hashes
node scripts/design/ancora.mjs Repair/Settings/Index --staging prototipo-ui/cowork/Wagner
node scripts/design/gerar-map.mjs memory/requisitos/Repair/repair-settings-gap.md | node -e '…prototipo_sha…'
node -e 'import("./scripts/design/gerar-map.mjs").then(m=>console.log(m.computeProtoHash(["prototipo-ui/cowork/Wagner/repair-page.jsx"], "<scratch com git show $B:…>")))'  # → sha256:f1a032ff88ed
git log -1 --format=%H HEAD -- prototipo-ui/cowork/Wagner/compras-page.jsx   # == prototipo_sha do baseline
# G5 — máquina derivada
node scripts/design/render-proto-baseline.mjs --check memory/requisitos/*/*.proto-baseline.json   # rc=0
git show $B:memory/requisitos/Compras/compras.proto-baseline.json > scratch/ctrl.json && node scripts/design/render-proto-baseline.mjs --check scratch/ctrl.json   # rc=1 STALE (controle)
node scripts/governance/design-code-map-check.mjs --check --strict   # rc=0
node scripts/governance/requisitos-status.mjs Sells --check          # rc=0
node scripts/governance/plans-index.mjs --check                      # rc=0
node scripts/governance/doc-id-index.mjs --check-collisions          # rc=0
# G6 — PII (sonda em scratch/pii.mjs, símbolo de moeda montado por charCode e escapado)
git diff $B...HEAD -- memory/requisitos | grep '^+' | grep -v '^+++' > plus.txt && node pii.mjs plus.txt
git status --short   # vazio (árvore limpa) antes de escrever esta evidência
```

```json
{"itens_verificados": 283, "erros_confirmados": 0, "error_rate_pct": 0, "pii_hits": 0, "veredito": "aprovado"}
```
