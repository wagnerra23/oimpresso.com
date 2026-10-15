---
date: "2026-10-15"
topic: "Refutação GT-G5 r1 do lote #9433 (18 derivados em memory/requisitos: 9 proto-baseline · 2 map.json · 7 _STATUS-GENERATED) — 122 itens, 0 refutados, PII 0/10 controles 10/10"
authors: ["C"]
prs: [9433]
outcomes:
  - "Lote APROVADO na rodada r1: 122 itens verificados contra HEAD/origin/main e contra os geradores do repo, 0 erros confirmados, error_rate 0%"
  - "22 identidades derivadas (prototipo_sha + render_sha256 dos 9 baselines, prototipo_sha + partes preservadas dos 2 maps) recomputadas pelo gerador do repo e batendo; controle no archive de origin/main devolve valores diferentes (o recompute discrimina)"
  - "Scan PII com 10 padrões e 10/10 controles positivos: 0 hits nas 3784 linhas adicionadas; os 47 UC/US novos dos _STATUS já existiam em origin/main (casos.md + teste + SPEC) — o lote só reconciliou derivados stale"
---

# Session log 2026-10-15 — Refutação GT-G5 · lote PR #9433 · rodada r1

## TL;DR

**Veredito: APROVADO.** 122 itens verificados em 5 grupos contáveis (+ scan PII) contra `HEAD` (`2b578a6fad`) e `origin/main` (`821f08e5ee`) e contra os geradores do repo; **0 erros confirmados** (error_rate **0,00%**); scan PII **0 hits** com **10/10** controles positivos casando. O lote é 100% derivado de máquina (identidade do espelho re-importado + regeneração de `_STATUS-GENERATED` que estava stale em main), e cada derivado foi reproduzido pela máquina dona, não lido.

## Cabeçalho

| Campo | Valor |
|---|---|
| PR / lote | #9433 · tipo **anchors/derivados** · amostra **100%** dos itens |
| HEAD medido | `2b578a6fad` (branch `claude/producao-handoff-receber`) |
| Base | `origin/main` = `821f08e5ee` (= merge-base; `git fetch origin main` feito antes) |
| Repo raso? | `git rev-parse --is-shallow-repository` → **false** |
| Sessão fresca | sim — instância nova; **não** li corpo do PR, commit messages, `memory/sessions/*refutacao*` (exceto o de 06/10, só como modelo de formato) nem `memory/handoffs/` de hoje |
| Modelo | Fable 5.1 (tier máximo disponível) |
| Árvore ao final | `git status --short` vazio exceto esta evidência — nenhuma sondagem escreveu no repo (recompute rodou em scratchpad + `git archive` de origin/main) |

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (igualdade só no tier máximo — Fable é o teto)
- [x] Amostra: 100% anchors (lote é só derivados; não há prosa destilada → sem seed)
- [x] Cada item verificado contra o código real em HEAD/origin/main ou recomputado pelo gerador do repo, não contra o diff
- [x] Cada REFUTADO anotado com evidência — **nenhum**
- [x] **Scan PII no diff** — 10 padrões × controle positivo → 0 hits
- [x] `error_rate_pct` calculado e < 2 → **0,00**
- [ ] Entry no ledger no mesmo PR — fora do meu mandato (não escrevo no ledger)

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` → **18** arquivos, todos `M` (bate com o enunciado): **9** `*.proto-baseline.json` (Compras 1 · Financeiro 5 · KB 1 · Sells 1 · TeamMcp 1), **2** `*.map.json` (Officeimpresso `logs-index` · `logs-timeline`), **7** `_STATUS-GENERATED.md` (Cliente · Compras · Crm · Financeiro · Fiscal · Officeimpresso · Sells). O PR inteiro toca 95 arquivos; os 77 fora do lote são `prototipo-ui/cowork/Wagner/**` (import do handoff Cowork, 18 fontes `.jsx/.css/.html` na raiz + playbooks) + `scripts/design-sync/**` (state + `receber-handoff.mjs` + teste) + `scripts/governance/.cowork-freshness-ledger.json` — fora do mandato, usados só como insumo dos geradores.

**Natureza do diff do lote (lido arquivo a arquivo, via JSON diff de chaves de topo):**
- 2 maps: só `prototipo_sha` (`sha256:34f2006fced0` → `sha256:a4005459f4dc`) e `gerado_em` mudaram; o resto do objeto (sem essas 2 chaves) é **byte-idêntico** a origin/main — 13 e 12 `partes` preservadas, 0 `vivo.arquivo = TODO`.
- 9 baselines: chaves alteradas = `gerado_em`, `celulas`, `render_sha256` em todos; `prototipo_sha` mudou em **4** (Compras, Unificado, KB, Sells) e ficou igual em 5 (Conciliação/Dre/Fluxo/Impostos/Forja). `render_sha256` `a669c36d…` → `99dffa67…` nos 9. 4 células (`1280|1440 × light|dark`) em todos; 0 `ModuleStub`/"Módulo legado".
- 7 `_STATUS-GENERATED`: +46 linhas de UC + 1 US (US-COM-023) + contadores do cabeçalho; Sells perdeu as 2 lacunas "Drafts/Subscriptions sem casos.md".

## Tabela por grupo

| # | Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---:|---:|---:|---|
| 1 | Path citado existe | 20 | 20 | 0 | 20 paths únicos extraídos dos 11 JSON (`gap_fonte`, `ancora`, `charter`, `partes[].prototipo.arquivo`, `partes[].vivo.arquivo`) → **20/20** blobs via `git ls-tree HEAD`. Classes: 11 `Pages/**` (charters + 2 `.tsx` vivos), 7 `prototipo-ui/`, 2 `gap.md`; 0 `Components/**`. Controle negativo: `nao-existe-xyz.jsx` → saída vazia |
| 2 | Âncora é a que o leitor real resolve | 11 | 11 | 0 | `node scripts/design/ancora.mjs <tela> --staging prototipo-ui/cowork/Wagner` nas 9 telas dos baselines + 2 dos maps → `âncora ✓` em **11/11**, rc=0, e o arquivo resolvido = o `ancora` do baseline / `partes[].prototipo.arquivo` do map (Officeimpresso → `officeimpresso-page.jsx`); frescor "verificado contra o Cowork vivo em 2026-10-15T14:32:47Z" nos 11 |
| 3 | Identidade derivada recomputada bate | 22 | 22 | 0 | script no scratchpad importando `gerar-map.mjs` (`resolverArquivosPrototipo`/`computeProtoHash`) e `render-proto-baseline.mjs` (`superficieRender`): **(a)** `git log -1 --format=%H HEAD -- <ancora>` (= `computeGitSha`) = `prototipo_sha` dos **9/9** baselines; **(b)** `superficieRender(prototipo-ui/cowork/Wagner)` em HEAD = `99dffa67a4b0…` = `render_sha256` dos **9/9**; **(c)** `computeProtoHash([officeimpresso-page.jsx])` em HEAD = `sha256:a4005459f4dc` = `prototipo_sha` dos **2/2** maps; **(d)** maps sem `prototipo_sha`/`gerado_em` **idênticos** a origin/main (**2/2** — o `--atualizar` preservou as partes). Controle: o mesmo recompute sobre `git archive origin/main` dá `sha256:34f2006fced0` (= maps antigos, 2/2) e render `cfc2b261…` (≠ HEAD — o cálculo discrimina) |
| 4 | `_STATUS-GENERATED` reflete a árvore | 54 | 54 | 0 | `requisitos-status.mjs <Mod> --check` → "em dia" rc=0 nos **7** módulos (varri os 19 módulos com `_STATUS-GENERATED.md`: nenhum stale em HEAD); **46 UC novos** (não amostra — 100%): cada um existe no `.casos.md` da tela da coluna "Tela" **e** é citado por ≥1 `*Test.php` em HEAD (46/46, `git grep -l 'UC-X\b'`); **US-COM-023** existe em `Compras/SPEC.md` (1/1). Os casos.md "novos" (Drafts, Subscriptions, Campanhas, Chamadas, Relatorios, CategoriasDespesa, Despesas) e os testes **já estão em origin/main** — amostra de 7 UC: 1 teste cada em main |
| 5 | JSON parseia · máquinas `--check` | 15 | 15 | 0 | 11/11 `JSON.parse` ok; rc literal de 4 máquinas abaixo (todos 0) |
| 6 | PII (não conta como item) | 10 padrões | — | 0 hits | 10/10 controles positivos casam (ver tabela) |
| | **Total** | **122** | **122** | **0** | |

## Grupo 5 — rc literal de cada máquina

| Comando | rc | Saída-chave |
|---|---:|---|
| `node scripts/design/render-proto-baseline.mjs --check` | 0 | `✓ 9 baseline(s) íntegro(s)` — 9× `âncora ✓ · sha ✓ · 4 células`, sem "render NÃO MEDIDO" |
| `node scripts/governance/design-code-map-check.mjs --check --strict` | 0 | `[OK] nenhum map.json com âncora quebrada ou sha stale` · 89 âncoras TODO pendentes (não é drift) · 5 WARN de idade não-medida em maps **fora** do lote (Cliente/Compras/KB/Produto/RecurringBilling) |
| `node scripts/governance/plans-index.mjs --check` | 0 | `✓ PLANS-INDEX-GENERATED.md em dia (8 registrados, 27 pendentes)` |
| `node scripts/governance/requisitos-status.mjs <Mod> --check` ×7 (Cliente, Compras, Crm, Financeiro, Fiscal, Officeimpresso, Sells) | 0 ×7 | `✓ … em dia.` em todos; loop nos 19 módulos com o arquivo: 0 não-"em dia" |
| `node scripts/governance/doc-id-index.mjs --check-collisions` | 0 | `OK: 0 colisão de id em 2979 ids` |
| `node scripts/governance/cowork-mirror-freshness.mjs --unverified --check` (recibo — o PR edita o espelho, §5 2026-09-24) | 0 | `🔴 mexido-depois: 0 · ✓ intactos: 1582 · ⬜ nunca verificados: 5` |

Arquivo que o lote deveria ter regenerado e não está no diff: **nenhum** — os 9 baselines do repo são exatamente os 9 do lote (`--check` varre tudo); o `--strict` acusa sha stale em qualquer map cujo protótipo mudou e acusou 0 (os 2 do lote são os únicos cujo `prototipo` aponta pra `officeimpresso-page.jsx`, tocado no PR); os 19 `_STATUS-GENERATED` estão todos "em dia".

## REFUTADOS

Nenhum.

## Observações não contadas

1. **Os 9 baselines e os 2 maps já estavam STALE em origin/main — o lote reconciliou, não inventou.** O `prototipo_sha` que os 4 baselines trocaram (`1ab4ab51b1` Compras/Unificado/Sells, `6735567526` KB) são commits **já em origin/main** (`#8806` de 06/10 e `#8897` de 07/10, ambos ancestrais de main e de HEAD) — i.e., main carregava baselines apontando pra commits anteriores ao último toque da âncora. Idem o render: `superficieRender` sobre o archive de **origin/main** dá `cfc2b261…`, não o `a669c36d…` que os baselines de main declaram (o espelho em main mudou em 14/10, #9406/#9410 etc.). Em HEAD tudo bate; o estado stale de main é higiene pré-existente, não defeito do lote.
2. **Causa do `render_sha256` novo (não é defeito):** o PR muda 18 fontes da raiz do espelho (`app.jsx`, `oimpresso.com.html`, `officeimpresso-page.jsx/.css`, `crm-portal.*`, `producao-cv-*`, `repair-*`, `os-acompanhamento.*`, `produto-cadastros.jsx`, `data.jsx`). `superficieRender()` hasheia o grafo inteiro, então regenerar os 9 era obrigatório. Os 5 baselines cujo `prototipo_sha` não mudou têm âncora (`financeiro-telas-extras.jsx`, `forja-page.jsx`) não tocada desde `4f51a9ec78` (#7224, 11/09).
3. **Os 47 UC/US novos dos `_STATUS` já existiam em origin/main** (casos.md + teste + SPEC): outro(s) PR(s) adicionaram os contratos sem regenerar o derivado; o lote só reconciliou. O PR **não toca** nenhum `.casos.md` real, teste ou SPEC — os 3 `.casos.md` do diff são do espelho (`prototipo-ui/cowork/Wagner/**`), fora do corpus do `requisitos-status`.
4. **Texto das células contém nomes** — `Wagner Rocha` (dono, header do shell) e fornecedores fictícios do mock do protótipo (`Lonas & Vinis`, `Tintas Coral`, `Papel & Cia Atacado`, `Importadora Têxtil RH`). Nenhum é cliente do CRM: os 4 vêm de `prototipo-ui/cowork/Wagner/*.jsx` em origin/main (8/4/3/2 arquivos) e já estavam nos baselines de main (9/1/1/1). Valores BRL do mock saem redigidos pelo gerador como `<BRL>` (0 hits de moeda no diff).
5. `design-code-map-check` WARN de "idade NÃO MEDIDA" são 5 maps fora do lote (gap.md sem `gerado_em`) — o próprio gate diz que não bloqueia. Não contei.
6. `ancora.mjs` imprime o aviso de cobertura do espelho (ADR 0374) em toda tela — fala do espelho inteiro, não do lote; omiti da leitura.

## Scan PII (linhas `+` de `git diff origin/main...HEAD -- memory/requisitos`, 3784 linhas)

| Padrão | Hits | Controle positivo |
|---|---:|---|
| CPF pontuado (`\d{3}\.\d{3}\.\d{3}-\d{2}`) | 0 | casa |
| CPF cru (11 dígitos isolados, não-hex) | 0 | casa |
| CNPJ (`\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}`) | 0 | casa |
| CNPJ cru (14 dígitos isolados) | 0 | casa |
| Telefone BR formatado (DDD + 4/5-4) | 0 | casa |
| Telefone cru (10–11 dígitos isolados) | 0 | casa |
| E-mail | 0 | casa |
| CEP (`\d{5}-\d{3}`) | 0 | casa |
| Token/segredo (`mcp_[0-9a-f]{32,}`, `ghp_`, `sk-`, `AKIA`, `Bearer …`) | 0 | casa |
| Valor em moeda (símbolo seguido de dígito; padrão montado por charCode) | 0 | casa (**2ª rodada** — a 1ª falhou por `$` sem escape dentro de `new RegExp`, mesma armadilha de 06/10; corrigido com classe `[$]` e re-rodado; registro porque sonda que não casa o próprio controle não vale) |

Nomes de cliente do CRM: 0 (ver observação 4 — os nomes presentes são o dono e mock de protótipo já em main). **10/10 controles · 0 hits.**

## Comandos reproduzíveis

```bash
# escopo
git fetch origin main; git rev-parse --short HEAD origin/main; git rev-parse --is-shallow-repository
git diff --name-status origin/main...HEAD -- memory/requisitos | wc -l            # 18

# grupo 1 — existência (controle negativo = saída vazia)
git ls-tree HEAD -- prototipo-ui/cowork/Wagner/officeimpresso-page.jsx
git ls-tree HEAD -- prototipo-ui/cowork/Wagner/nao-existe-xyz.jsx

# grupo 2
node scripts/design/ancora.mjs Officeimpresso/Logs/Timeline --staging prototipo-ui/cowork/Wagner   # idem p/ as outras 10

# grupo 3 — identidades (script no scratchpad, imports por file://)
git log -1 --format=%H HEAD -- prototipo-ui/cowork/Wagner/compras-page.jsx       # 1ab4ab51b1… = prototipo_sha
#   superficieRender(<repo>/prototipo-ui/cowork/Wagner, <repo>)  → 99dffa67a4b0d096d8d200790e68e4c62c733f267e097abe3bf402bc2a72905c
#   computeProtoHash(['prototipo-ui/cowork/Wagner/officeimpresso-page.jsx'], <repo>) → sha256:a4005459f4dc
#   controle: git archive origin/main prototipo-ui/cowork/Wagner prototipo-ui/design-system | tar -x -C <scratch>/main
#             → render cfc2b261d3c5…  · proto sha256:34f2006fced0
#   maps: JSON sem {prototipo_sha,gerado_em} === o mesmo em `git show origin/main:<map>`

# grupo 4
git diff origin/main...HEAD -- memory/requisitos/*/_STATUS-GENERATED.md | grep -E '^\+\| (UC|US)-'   # 47
git grep -l -- 'UC-SSUB-05\b' HEAD -- '*.casos.md' 'tests/**/*Test.php' 'Modules/**/Tests/**/*.php'
for m in Cliente Compras Crm Financeiro Fiscal Officeimpresso Sells; do node scripts/governance/requisitos-status.mjs $m --check; done

# grupo 5
node scripts/design/render-proto-baseline.mjs --check
node scripts/governance/design-code-map-check.mjs --check --strict
node scripts/governance/plans-index.mjs --check
node scripts/governance/doc-id-index.mjs --check-collisions

# grupo 6
git diff origin/main...HEAD -- memory/requisitos | grep '^+' | grep -v '^+++' > plus.txt   # 3784 linhas → 10 regex + 10 controles
```

```json
{"itens_verificados": 122, "erros_confirmados": 0, "error_rate_pct": 0.0, "pii_hits": 0, "veredito": "aprovado"}
```
