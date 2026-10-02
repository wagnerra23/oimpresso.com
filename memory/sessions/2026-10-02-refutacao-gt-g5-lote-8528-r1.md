---
date: "2026-10-02"
topic: "Refutação GT-G5 r1 do lote PR #8528 (9 proto-baselines + 7 maps de Repair + 3 _STATUS-GENERATED): 19 de 19 verificados, 0 refutados, PII 0 hits"
authors: ["C"]
prs: [8528]
outcomes:
  - "Lote APROVADO na r1: 19 arquivos verificados (100%), 0 erros confirmados, error_rate 0,00%"
  - "Maps regenerados por gerar-map --atualizar saem byte-idênticos ao commitado; baselines 9/9 íntegras; _STATUS --check em dia"
  - "PII: 0 CPF/CNPJ/e-mail/telefone nas linhas + do diff"
---

# Refutação GT-G5 — PR #8528 — rodada 1

- **Data:** 2026-10-02
- **PR:** #8528 (branch `claude/repair-thread-00-puxar`, HEAD `15b297eaa2`, merge-base com `origin/main` = `4f62fc517b`; `origin/main` no momento = `e656cf4feb`, **6 commits à frente** do merge-base)
- **Refutador:** `claude-fable-5-1` (Fable 5.1), **sessão fresca** (zero contexto do gerador; worktree próprio; só leitura — nenhum commit, push ou edição em arquivo versionado)
- **Gerador alegado:** saída de 3 geradores determinísticos (`render-proto-baseline.mjs --gerar`, `gerar-map.mjs --atualizar`, `requisitos-status.mjs --write`)
- **Tipo:** `anchors` (artefatos derivados) · **amostra:** 100% (19 de 19 arquivos)
- **Protocolo lido:** `memory/requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md` §1–§4.2

## Lote (19 arquivos de `git diff --name-only --diff-filter=ACMR origin/main...HEAD -- memory/requisitos/`)

9 `*.proto-baseline.json` (Compras, Financeiro×5, KB, Sells, TeamMcp) · 7 `Repair/repair-*.map.json` · 3 `_STATUS-GENERATED.md` (Ponto, Repair, Sells).

O PR inteiro toca, além desses 19: `prototipo-ui/cowork/Wagner/repair-page.jsx`, `…/cowork-inbox/repair/playbook/_saida-00.md`, `scripts/design-sync/state/enviados-cowork.json`, `scripts/governance/.cowork-freshness-ledger.json` (fora do escopo deste refutador).

## Checklist §3

- [x] Sessão fresca
- [x] Tier: refutador fable; gerador = scripts determinísticos (alegação); fable ≥ qualquer gerador
- [x] Amostra 100% (19/19)
- [x] Cada item verificado contra o código/árvore real, não contra o texto do PR
- [x] Scan PII no diff
- [x] `error_rate_pct` calculado
- [ ] Entry no ledger — **não é tarefa do refutador** (fica pro gerador/humano no mesmo PR)

## 1. Baselines (9) — íntegras e só hash/data mudaram

```
node scripts/design/render-proto-baseline.mjs --check
✓ 9 baseline(s) íntegro(s).   RC=0
```
(cada uma: "âncora ✓ · sha ✓ · 4 células")

Diff por folha vs `origin/main` (walker JSON recursivo, ver script abaixo): em **todas as 9** exatamente **2 folhas** mudaram:
- `.gerado_em` (16:48–16:54Z → 18:04–18:09Z, mesmo dia)
- `.render_sha256`: `dbc83389f11f…` → `9046d5ef69e4…` (o MESMO par velho→novo nas 9 — consistente com `superficieRender(root)` ser o hash do grafo inteiro do bundle, não por tela)

Campos semânticos **inalterados** nas 9: `tela`, `charter`, `ancora`, `prototipo_sha`, `shell`, `celulas` (rótulos, textos, medidas). Nenhuma célula, rota ou rótulo mudou.

Por que o `prototipo_sha` da baseline NÃO mudou e o `render_sha256` mudou: `prototipo_sha` é da âncora da tela (ex. `financeiro-page.jsx`), não do `repair-page.jsx`; `render_sha256` (`render-proto-baseline.mjs:75-100`) varre o `MIRROR_DIR` inteiro, monta `payloadDependencyGraph` a partir do `oimpresso.com.html` e hasheia o conjunto alcançável — `repair-page.jsx` está nele. O `--check` verde em HEAD prova que `hashAtual == 9046d5ef…`; as baselines de `origin/main` (`dbc8…`) falhariam esse mesmo `--check` na árvore de HEAD (`:130 'baseline STALE'`), logo a regeneração era necessária e o valor commitado é o que o gerador produz hoje.

## 2. Maps (7) — byte-idênticos à regeneração; só `prototipo_sha` + `gerado_em`

Para cada `repair-{dashboard,device-models,index,jobsheet,producao-oficina,settings,status}`:
```
node scripts/design/gerar-map.mjs memory/requisitos/Repair/repair-<x>-gap.md --atualizar > <scratch>/maps/<x>.regen.json
```
stderr do gerador (7×): `--atualizar: N parte(s) preservada(s) · 0 nova(s) TODO · sha sha256:f1a032ff88ed → sha256:f1a032ff88ed`
Comparação: `bytes_igual=true` nos 7 (sem precisar normalizar `gerado_em` — a regeneração saiu no mesmo dia, 2026-10-02).

Diff por campo vs `origin/main` (7×, idêntico):
```
.prototipo_sha: "sha256:f8e0e372a12a" -> "sha256:f1a032ff88ed"
.gerado_em:     "2026-09-14"          -> "2026-10-02"
partes: 5→5 · 5→5 · 7→7 · 9→9 · 7→7 · 8→8 · 5→5   (nenhuma linha/âncora/parte mudou)
```

Prova de que os dois shas são o hash canônico do `repair-page.jsx` (não valores inventados), via a própria `computeProtoHash` do gerador (ADR 0324, `contentHash(normalize())`):
```
node --input-type=module -e "import {computeProtoHash} from './scripts/design/gerar-map.mjs'; …"
HEAD  : sha256:f1a032ff88ed     (prototipo-ui/cowork/Wagner/repair-page.jsx em HEAD)
main  : sha256:f8e0e372a12a     (mesmo path, blob de origin/main, materializado no scratchpad)
```
(sha256 cru dos bytes: HEAD `d571d3ca49ee…`, main `f9261334bda3…` — difere do canônico porque o canônico normaliza; o que vale é o canônico, e bate.)

```
node scripts/governance/design-code-map-check.mjs
[OK] nenhum map.json com âncora quebrada ou sha stale. 89 âncora(s) TODO pendente(s) (não é drift).   RC=0
```
(5 WARN pré-existentes de `gerado_em` ausente em gap.md de OUTROS módulos — Cliente/Compras/KB/Produto/RecurringBilling; nenhum do lote.)

## 3. `_STATUS-GENERATED.md` (3) — em dia

```
node scripts/governance/requisitos-status.mjs Ponto  --check  → ✓ em dia. RC=0
node scripts/governance/requisitos-status.mjs Repair --check  → ✓ em dia. RC=0
node scripts/governance/requisitos-status.mjs Sells  --check  → ✓ em dia. RC=0
```

Tentativa de refutação: o diff vs `origin/main` acrescenta UCs (Ponto: UC-EXCL-01..04, UC-PRIV-01..04, UC-REPP-11..16 · Repair: UC-RIDX-05..07, UC-RSHW-05/06 · Sells: US-SELL-065, UC-S03/04) e muda contagens (Ponto 24→26 telas, 126→140 UC; Repair 81→86; Sells 57→58 US, 73→80 UC). Hipótese adversarial: texto escrito à mão ou UCs inventados. **Refutada:** todas as fontes já existem no merge-base `4f62fc517b` (`git ls-tree` acha `Pages/Ponto/{Publico/Exclusao,Publico/Privacidade,Mobile/Index}.casos.md`; `git grep UC-RIDX-05 4f62fc517b -- Pages/Repair/Index.casos.md` = 2; `US-SELL-065` no `Sells/SPEC.md` = 1). O `_STATUS` de `origin/main` é que está **stale** (`git grep UC-EXCL-01 origin/main -- Ponto/_STATUS-GENERATED.md` = 0): o PR só o põe em dia. Saída de gerador, não prosa.

## 4. PII — 0 hits no diff

Varredura nos 19 arquivos inteiros (grep -E):
| padrão | hits |
|---|---|
| CPF mascarado `\d{3}\.\d{3}\.\d{3}-\d{2}` | 0 |
| CNPJ mascarado | 0 |
| 11 dígitos crus / 14 dígitos crus (fora de hex) | 0 / 0 |
| e-mail | 0 |
| telefone BR / +55 | 0 |
| palavra `cpf`/`cnpj` | 0 |
| nomes de cliente conhecidos | **70** (larissa 56 · rota livre 22 · wr2 20) |

Os 70 estão em `celulas[].texto` dos proto-baselines (Financeiro×5, KB, Sells) + 2 no `Sells/_STATUS` — texto capturado do **mock do protótipo Cowork** (ex. "Mai 2026 · ROTA LIVRE · caixa unificado", "TED FOLHA LARISSA … Salário Larissa"). Classificação:
- **Não introduzido por este PR.** Scan nas **68 linhas `+`** do diff: nomes = **0**, CPF/CNPJ/e-mail/tel = **0**. Todas as 68 são `render_sha256`, `gerado_em`, `prototipo_sha` ou linha de tabela do `_STATUS`. `git grep "FOLHA LARISSA" origin/main -- …conciliacao.proto-baseline.json` = 8 (já lá) e a fonte é `prototipo-ui/cowork/{Wagner,Felipe}/financeiro-telas-extras.jsx` em `origin/main`.
- "ROTA LIVRE"/"Larissa" é o cliente piloto nomeado no próprio canon always-on (`memory/why-oimpresso.md`, `regras-time.md`) — não é dado de CRM; e proto-baseline é artefato de **medição de design**, isento por construção (ADR 0407).

Residual honesto fora do escopo deste PR (não conta como erro do lote, porque é pré-existente em main): o mock do Financeiro carrega "Salário Larissa" com valor em linha (`− 2.800,00`) enquanto só os totais recebem `<BRL>`. Se [W] considerar isso sensível, o conserto é no protótipo-fonte + no redator do gerador, em PR próprio.

**pii_scan = true · pii_hits = 0.**

## 5. Resultado

| | |
|---|---|
| itens_verificados | **19** arquivos (61 checagens: 9×{check, diff-folha} + 7×{regen byte a byte, diff-campo, hash canônico} + 3×check + 19×PII) |
| erros_confirmados | **0** |
| error_rate_pct | **0.0** |
| pii_scan / pii_hits | true / 0 |
| **veredito** | **aprovado** |

## 6. Observações que NÃO são erro do lote (pro gerador/[W] antes do merge)

1. **Branch 6 commits atrás de `origin/main`.** Os 6 tocam `memory/requisitos/Sells/{SPEC,RUNBOOK-pos,RUNBOOK-sales-order,SUPERFICIE}.md`, `_BACKLOG-GENERATED.md` e dois `.md` de `cowork-inbox/venda-menu/playbook/`. Consequência provável pós-merge: `requisitos-status Sells --check` pode voltar a acusar stale (SPEC do Sells mudou no main) — rodar o `--check` no merge-ref antes de mergear. As baselines **não** devem driftar: `superficieRender` só hasheia o grafo alcançável a partir do `oimpresso.com.html`, e os `.md` novos não entram nele (inferência da leitura de `:100-125`, não medida no merge-ref).
2. Registro próprio de método (LC-36 / §5 2026-08-21): caí **duas vezes** no mesmo turno no `/tmp` ↔ Node-Windows (`$HOME` virou `D:\c\…`; `/tmp/old.json` virou `D:\tmp\…`) — nenhum efeito no veredito, corrigido com caminho Windows absoluto no scratchpad.

## Comandos (reprodutíveis)

```bash
cd D:/oimpresso.com/.claude/worktrees/great-burnell-e64147 && git fetch origin main
git diff --name-only --diff-filter=ACMR origin/main...HEAD -- memory/requisitos/
node scripts/design/render-proto-baseline.mjs --check
node scripts/governance/design-code-map-check.mjs
for m in Ponto Repair Sells; do node scripts/governance/requisitos-status.mjs $m --check; done
for x in dashboard device-models index jobsheet producao-oficina settings status; do
  node scripts/design/gerar-map.mjs memory/requisitos/Repair/repair-$x-gap.md --atualizar > "$S/maps/$x.regen.json"
  cmp "$S/maps/$x.regen.json" memory/requisitos/Repair/repair-$x.map.json   # idêntico nos 7
done
# diff por folha (JSON): walk(a,b) recursivo, imprime caminho + antes->depois — 2 folhas em cada um dos 16 JSON
# hash canônico: import {computeProtoHash} from './scripts/design/gerar-map.mjs' sobre HEAD e sobre blob de origin/main
git diff origin/main...HEAD -- memory/requisitos/ | grep -E '^\+' | grep -vE '^\+\+\+' | grep -ciE 'larissa|martinho|rota ?livre|…'   # 0
```
