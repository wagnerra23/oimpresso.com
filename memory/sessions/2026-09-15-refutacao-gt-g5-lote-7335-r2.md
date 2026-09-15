---
date: "2026-09-15"
topic: "Refutação GT-G5 rodada r2 do lote PR #7335 (56 docs em memory/requisitos, ponteiros podres prototipo-ui/prototipos: anotação de remoção ou re-âncora em cowork/Wagner) - REPROVADO: 13 erros em 205 itens (6,34%), PII 0"
authors: ["C"]
prs: [7335]
outcomes:
  - "REPROVADO — 8 docs de Repair re-ancorados em repair-page.jsx (porte REVERSO dos blades) quando o sucessor de prototipos/os/cowork-app.jsx é os-page.jsx e os charters declaram os-page.jsx"
  - "4 quebras de integridade (anotação dentro de code-block do INVENTARIO, frase partida no sidebar-gap, célula extra em 2 tabelas) + 1 artefato DERIVADO (setor-matrix) editado à mão"
  - "Datas/commits de remoção: 26 de 26 tuplas batem com git log --diff-filter=D; 5 âncoras novas existem; PII 0/7 padrões com 7/7 controles positivos"
---

# Session log 2026-09-15 — Refutação GT-G5 · lote PR #7335 · rodada r2

## TL;DR

**REPROVADO.** 205 itens verificados, **13 erros confirmados**, error_rate **6,34%** (limiar < 2%), PII **0 hits** (7/7 controles positivos casaram). Os erros concentram-se em dois padrões: (a) **re-âncora pro arquivo errado** — 8 docs de Repair trocaram `prototipo-ui/prototipos/os/cowork-app.jsx` por `cowork/Wagner/repair-page.jsx`, mas o sucessor medido é `cowork/Wagner/os-page.jsx` (729 de 765 linhas únicas em comum; `repair-page.jsx` tem 13 e se declara "importado dos blades") e os charters de Repair em origin/main declaram `blueprint_cowork: os-page.jsx`; (b) **anotação `(removido em …)` colada onde quebra a estrutura** — dentro de fenced code-block que cita literal de charter, no meio de frase que já trazia a data, e depois do pipe final de 2 linhas de tabela — mais um artefato gerado (`gerado_por: reconcile-triplet.mjs`) editado à mão.

## Cabeçalho

| Campo | Valor |
|---|---|
| Base | `origin/main` = `b93f03d1fa701a9eab8090a501615859484d3e35` |
| HEAD do lote | `8336ca85e13973884ba21c383bd17a6f52be29ee` (branch `claude/ponteiros-podres-61`) |
| merge-base | `d5725cc946a` |
| Repo raso? | **false** (`git rev-parse --is-shallow-repository`) — datas de `git log` valem como recibo |
| Sessão fresca | **sim** — instância nova, sem contexto do gerador; NÃO abri `memory/sessions/*refutacao*` (nem a r1 deste PR, presente no diff) nem `memory/handoffs/` de hoje; NÃO li corpo do PR/commits como evidência |
| Refutador | Claude Fable 5.1 (`claude-fable-5-1`) |
| Tipo | anchors · amostra 100% |
| Lote medido | `git diff --name-status origin/main...HEAD -- memory/requisitos` → **56 M**; fora de `memory/requisitos`: `governance/deadlink-baseline.json` (M), `governance/sdd-scorecard-baseline.json` (M), `memory/sessions/2026-09-15-refutacao-gt-g5-lote-7335-r1.md` (A, não aberta) |
| Tamanho | `+94 −94` linhas em memory/requisitos (`--numstat`) |

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (Fable 5.1; tier máximo da tabela)
- [x] Amostra: 100% anchors (todo path, toda chave de frontmatter, toda linha alterada)
- [x] Cada item verificado contra origin/main (`git ls-tree`, `git show origin/main:`, `git log origin/main --diff-filter=D`, `git grep … origin/main`), não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha/commit + porquê)
- [x] Scan PII no diff — 7 padrões × controle positivo; nomes de cliente CRM conferidos à mão
- [x] `error_rate_pct` calculado = 6,34 (≥ 2 → reprovado)
- [ ] Entry no ledger — **não escrita** (mandato: reprovado devolve os refutados e para; conserto é do gerador)

## Escopo medido

O lote faz duas coisas: (1) anexa `(removido em <data>, <sha>)` a 72 menções de paths sob `prototipo-ui/prototipos/**` (deletados em 4 commits: `9da73296d34` 2026-06-23 · `1070e3759b7` 2026-05-20 · `7810bf5cb40` 2026-05-19 · `4f51a9ec781` 2026-09-11); (2) troca 22 menções por âncoras vivas em `prototipo-ui/cowork/Wagner/` (`clientes-page.jsx` ×5 · `repair-page.jsx` ×8 · `vendas-page.jsx` ×8 · `inbox-page.jsx` ×3 · `pg-payment-gateways-page.jsx` ×1) e desfaz links markdown pra paths mortos (texto com nota de data — forma correta pela lápide §5 2026-08-12).

Claim negativa de fundo: `git ls-tree -r --name-only origin/main -- prototipo-ui/prototipos | wc -l` → **0**; controle positivo `… -- prototipo-ui/cowork/Wagner | wc -l` → **652**. Nenhum path sob `prototipos/` existe em origin/main.

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---|---|---|
| 1 · Âncora existe em origin/main | 31 | 31 | 0 | `git ls-tree origin/main -- <path>` nas 5 âncoras novas (5 blobs; controle negativo `NAO-EXISTE.jsx` → AUSENTE) + 26 paths "removidos" ausentes (claim negativa 0/0 sob `prototipos/`, controle 652) |
| 2 · Não revogada e lida pelo leitor real | 29 | 21 | **8** | `git grep` de `related_prototype`/`bundle_source`/`visual_source`/`blueprint_cowork` nos charters em origin/main + `node scripts/design/ancora.mjs <tela> --staging prototipo-ui/cowork/Wagner` em 8 telas |
| 3 · Ação × veredito · afirmação sobre histórico | 29 | 29 | 0 | 26 tuplas (path, sha, data) × `git log origin/main --diff-filter=D --format='%h %cd' -- <path>` (+ último D por diretório) + 3 reescritas de prosa |
| 4 · Célula/linha íntegra | 94 | 90 | **4** | Todas as 94 linhas `+` do diff lidas no HEAD; contagem de pipes/pipe final; posição em code-block/frontmatter; parse YAML (pyyaml 6.0.2, o mesmo do gate) HEAD × origin/main chave a chave |
| 5 · Máquina derivada | 15 | 14 | **1** | rc literal de cada `--check` (abaixo) + regeneração de baseline/derivado e diff contra o commitado |
| 6 · PII | 7 | 7 | 0 | 7 regex sobre as 94 linhas `+`, cada uma rodada também contra linha sintética que casa |
| **Total** | **205** | **192** | **13** | error_rate = 13/205 = **6,34%** |

## REFUTADOS

### R1–R8 · Repair — re-âncora pro arquivo ERRADO (8 arquivos)

- **Arquivos/itens:** `Repair/RUNBOOK-jobsheet-create.md:16` · `RUNBOOK-jobsheet-edit.md:16` · `RUNBOOK-jobsheet-index.md:18` · `RUNBOOK-jobsheet-show.md:18` · `RUNBOOK-repair-index.md:18` · `RUNBOOK-repair-show.md:16` · `jobsheet-visual-comparison.md:13` · `repair-visual-comparison.md:12`
- **Afirmação do lote:** `prototipo-ui/prototipos/os/cowork-app.jsx` → `prototipo-ui/cowork/Wagner/repair-page.jsx`, mantendo o rótulo "Blueprint canônico … (listagem + detalhe OS Cowork)" / "Pattern reuse: blueprint …".
- **O que origin/main diz:**
  - O arquivo antigo (`git show 1070e3759b7^:prototipo-ui/prototipos/os/cowork-app.jsx`, 1021 ln) abre com `// os-page.jsx — Listagem + Detalhe de Ordens de Serviço`. `prototipo-ui/cowork/Wagner/os-page.jsx` (origin/main, 995 ln) abre com a MESMA linha e compartilha **729 de 765** linhas únicas do antigo (`comm -12`). `repair-page.jsx` compartilha **13**.
  - `repair-page.jsx:1-2` se declara: `// repair-page.jsx — módulo Repair (assistência técnica) importado dos blades Modules/Repair/Resources/views/{…}` — porte REVERSO. O próprio `Repair/Settings/Index.charter.md:4` em origin/main já registra isso ("porte REVERSO do Blade … ancorar aqui seria ancorar a tela nela mesma (§5 2026-08-28)").
  - Leitor real: `git grep blueprint_cowork origin/main -- 'resources/js/Pages/Repair/**'` → `JobSheet/{Create,Edit,Index,Show,AddParts}.charter.md`, `DeviceModels/Index.charter.md` e `Repair/Show.charter.md` declaram **`prototipo-ui/cowork/Wagner/os-page.jsx`**. `ancora.mjs Repair/JobSheet/Index` e `Repair/JobSheet/Show` imprimem `mwart_pattern_reuse.blueprint_cowork: prototipo-ui/cowork/Wagner/os-page.jsx`.
- **Porquê é erro do lote:** o lote INVENTA fonte de design (porte reverso rotulado "Blueprint canônico … Cowork"), CONTRADIZ o charter (lei) e a lápide §5 2026-08-28 (`bundle_source` de hub reverso não vira design aprovado) — tendo o sucessor correto a um `ls` de distância. Os 8 itens são o mesmo erro sistemático de prompt (um mapeamento `os → repair` decidido por nome de módulo, não por conteúdo).

### R9 · `_DesignSystem/INVENTARIO-ANCORAS-2026-09-09.md:130` — anotação DENTRO de fenced code-block que cita literal de charter

- **Afirmação:** linha `prototipo-ui/prototipos/inventario-migracao/visual-source.html _(removido em 2026-09-11, 4f51a9ec781)_` inserida dentro do bloco ``` que o §4.2 apresenta como *"Os 4 charters de Estoque declaram, em `mwart_pattern_reuse.blueprint_cowork`:"*.
- **O que origin/main diz:** o bloco é uma CITAÇÃO literal do valor dos charters; markdown de itálico não renderiza em code-block, e o leitor passa a ler que os charters declaravam esse sufixo. A linha seguinte (L132) continua afirmando *"Esse arquivo existe, tem 30 KB"* — o doc fica autocontraditório sem errata. O INVENTARIO é snapshot datado (2026-09-09); a data da remoção (2026-09-11, `4f51a9ec781`) está certa — o lugar da anotação, não.
- **Porquê é erro:** célula/code-span corrompido (grupo 4); registro datado alterado por dentro em vez de nota fora do bloco.

### R10 · `_DesignSystem/sidebar-v3-unificado-gap.md:63` — anotação no MEIO de frase que já trazia a data

- **Afirmação:** `…visual-source.html`, apagado em _(removido em 2026-06-23, 9da73296d34)_` seguido, na L64, de `2026-06-23 (ver `prototipo_nota` no frontmatter)`.
- **O que origin/main diz:** a errata de 2026-09-08 já dizia "apagado em 2026-06-23" com a data na linha seguinte; o lote não leu a quebra de linha e produziu "apagado em (removido em 2026-06-23, …) 2026-06-23".
- **Porquê é erro:** prosa partida e redundante (grupo 4) — a informação já existia; a inserção só degrada.

### R11 · `RecurringBilling/cobranca-recorrente-configuracoes-gap.md:26` — célula EXTRA em tabela de 3 colunas

- **Afirmação:** linha da tabela "Linguagem visual" termina em `… não deste gap | _(removido em 2026-05-20, 1070e3759b7)_`.
- **Medido no HEAD:** L25 `pipes=4 termina_com_pipe=True` (3 células); L26 `pipes=4 termina_com_pipe=False` → 4ª célula fora da grade. A anotação deveria estar dentro da célula que cita `prototipos/recurring/`, não depois do pipe final.
- **Porquê é erro:** grupo 4 (pipe não escapado / célula fora do contrato da tabela).

### R12 · `Sells/index-r1-visual-comparison.md:363` — célula EXTRA no sync log (4 colunas)

- **Afirmação:** `| 2026-05-17 | [CL] Claude Code | F0 | Bundle copiado em … (2.8MB, 96 arquivos) | _(removido em 2026-05-20, 1070e3759b7)_`.
- **Medido:** header L361 e separador L362 `pipes=5, termina_com_pipe=True` (4 colunas); L363 `pipes=5, termina_com_pipe=False` → 5ª célula. É uma linha de LOG datado (evento de 2026-05-17), que ganhou uma célula solta.
- **Porquê é erro:** grupo 4; mesmo defeito de R11.

### R13 · `Produto/_telas/produto-index-setor-matrix.md:24-25` — artefato DERIVADO editado à mão

- **Afirmação:** anotou `_(removido em 2026-05-20, 1070e3759b7)_` nos 2 bullets "Ponteiros de protótipo órfãos".
- **O que origin/main diz:** frontmatter `gerado_por: scripts/governance/reconcile-triplet.mjs (v1 heurístico)` · `gerado_em: "2026-06-22"` e rodapé "Gerado por `reconcile-triplet.mjs --write`". Regenerado sem `--write` (`node scripts/governance/reconcile-triplet.mjs --module=Produto --tela=Index`, rc=0): o protótipo hoje é `prototipo-ui/cowork/Wagner/produtos-page.jsx (frontmatter:blueprint_cowork)` e o único órfão é `ui_kits/cowork-2026-05-09/prod-page.jsx (charter:Refs)` — os dois bullets anotados **não existem mais na saída do gerador**. O CI (`reconcile-triplet.yml`) roda `--all` (advisory).
- **Porquê é erro:** doc que a máquina PRODUZ não se corrige à mão (§5 2026-08-02 "recuar à mão em vez de virar regra do mecanismo"; §5 2026-07-17 "doc canônico restatear número que outro sistema sabe melhor"). O arquivo agora afirma `gerado_em: 2026-06-22` com corpo de 2026-09-15. Conserto certo: `--write` (ou deixar o snapshot intocado e datado).

## Observações (NÃO contadas como erro)

1. **Baseline do scorecard SDD absorveu piora causada pelo próprio PR.** `governance/sdd-scorecard-baseline.json` `metrics.distiller_freshness.value` 0 → **8**, com `nota_absorcao_2026_09_15_pr7335` declarando "2 das 8 portas são deste PR" (BRIEFINGs de Crm e Whatsapp ficam stale porque o lote toca `HANDOFF-cliente-drawer-760-canon.md` e `CaixaUnificadaV4-visual-comparison.md`; 6 herdadas de main). Transparente (ADR 0275 §3 regra_piora, marcador BASELINE-ABSORB reafirmado) — é decisão [W] aceitar absorver em vez de atualizar os 2 BRIEFINGs. Ver rc do `--ratchet` na seção de máquina.
2. **RUNBOOK-produto-{create,edit,show}** citam "o charter de Produto/**Index** declara `related_prototype: n/a (herda PT-01 Lista)`" — literalmente verdadeiro, mas o charter relevante de cada tela é o próprio: `Produto/Create.charter.md:5` e `Edit.charter.md:5` = `n/a (herda PT-02 Form-Drawer…)`, `Show.charter.md:5` = `n/a (herda PT-03 Detalhe…)`. Ponteiro pro charter de outra tela.
3. **`# removido em …` em frontmatter é comentário YAML válido** (pyyaml: 12 arquivos com frontmatter textual alterado e valores parseados IGUAIS), **mas os leitores do repo são line-based** (`chaveAninhada` em `ancora.mjs:166-176`, `frontmatter()` em `_lib-charter.mjs:22-32`): `ancora.mjs Produto/Index` imprime `canon_reference (visual-comparison): prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx  # removido em 2026-05-20, 1070e3759b7`. Sem mudança de veredito (o path já não existia), mas o comentário vaza pro output.
4. **Swaps de mesma linhagem aceitos** (leitor real concorda): Cliente (`clientes/cowork-app.jsx` abre com `// clientes-page.jsx — … (Fase 3)`; `Cliente/Index.charter.md:5` = `clientes-page.jsx`), Sells (`vendas-cockpit/cowork-app.jsx` abre com `// vendas-page.jsx — Vendas/Index + Vendas/Create`; `sells-index/vendas-page.jsx` mesmo cabeçalho; 6 charters Sells declaram `vendas-page.jsx`), Whatsapp (`9da73296d34` moveu `caixa-unificada/inbox-page.jsx` pra `cowork/`; charter `visual_source: cowork/Wagner/inbox-page.jsx`), PaymentGateway (mesmo arquivo, 74 linhas de delta; charter `related_prototype` idem). Ressalva: em `_legado-fullpage/create-visual-comparison.md:21` e `cliente-index-visual-comparison.md:10` a "Referência" de uma comparação DATADA passou a apontar a versão evoluída (235 → 2049 ln); a linhagem é rastreável por git, então não contei — mas a forma canônica pra registro datado é texto + nota, não swap.
5. **Vermelhos herdados (não são do lote):** `requisitos-status Jana --check` rc=1 (drift = UC-JPAIN-21..23, `_STATUS-GENERATED.md` idêntico em HEAD e origin/main); `requisitos-status Whatsapp --check` rc=1 (`_STATUS-GENERATED.md` ausente em origin/main também); `design-code-map-check --check --strict` rc=1 (Ponto `espelho-{index,show}.map.json` STALE — lote não toca Ponto).
6. `ancora.mjs` avisa "frescor: SEM VEREDITO NOVO" pra `clientes-page.jsx` e `vendas-page.jsx` (última rodada 2026-09-11 não os incluiu) — estado do espelho, não do lote.
7. Nenhum charter em origin/main cita mais `inventario-migracao` (`git grep` → 0), o que reforça que o INVENTARIO §4.2 é snapshot e devia ter recebido nota FORA do code-block (R9).

## Máquina derivada — rc literais

| Comando | rc | Nota |
|---|---|---|
| `node scripts/governance/requisitos-status.mjs Cliente --check` | 0 | em dia |
| `… Financeiro --check` | 0 | em dia |
| `… Jana --check` | 1 | herdado (UC-JPAIN-21..23) |
| `… Whatsapp --check` | 1 | herdado (arquivo ausente em origin/main) |
| `node scripts/governance/plans-index.mjs --check` | 0 | 8 registrados, 25 pendentes |
| `node scripts/governance/design-code-map-check.mjs --check --strict` | 1 | herdado (Ponto STALE) |
| `node scripts/governance/doc-id-index.mjs --check-collisions` | 0 | 0 colisão em 2742 ids |
| `node scripts/governance/anchor-lint.mjs --check` | 0 | Cliente 🟢 100 |
| `node scripts/governance/deadlink-gate.mjs --check` | 0 | 779/779 grandfathered; baseline 793 → 779 |
| `deadlink-gate.mjs --write-baseline` → `git diff` | — | **idêntico ao commitado** (regeneração bate) |
| `node scripts/governance/reconcile-triplet.mjs --all` | 0 | 183 telas · 51 divergência muda · 8 órfãos (advisory) — e Produto/Index regenerado ≠ setor-matrix commitada (R13) |
| `node scripts/governance/sdd-scorecard.mjs --ratchet` | 1 | vermelho é `full_suite_pass_rate` (fonte órfã ausente neste checkout — ambiente, não lote). `--json` ao vivo: `distiller_freshness.value = 8` (`stale: 8` de 80 portas, `oldest_distilled_at 2026-09-06`) — **bate com o 8 absorvido na baseline** (observação 1) |
| parse YAML (pyyaml) HEAD × origin/main, 56 arquivos | 0 erros | 6 valores mudaram (os swaps esperados: 4 RUNBOOK Sells `mwart_pattern_reuse.blueprint_cowork`, `index-r1` `canon_reference`, `CaixaUnificadaV4` `visual_source`); 12 só comentário |

## Scan PII (linhas `+` do diff, 94 linhas)

| Padrão | Hits | Controle positivo |
|---|---|---|
| CPF pontuado | 0 | casou |
| CPF cru (11 dígitos isolados) | 0 | casou |
| CNPJ | 0 | casou |
| Telefone BR formatado | 0 | casou |
| Telefone cru (10–11 dígitos) | 0 | casou |
| E-mail | 0 | casou |
| Valor em reais (símbolo + dígito; padrão montado por `chr`, não literal) | 0 | casou |

Nomes de cliente CRM: grep manual por piloto/clientes conhecidos → 1 ocorrência de "Larissa biz=4 ROTA LIVRE" numa linha que já existia em origin/main (`RUNBOOK-Cliente-drawer-760px.md:23`, só recebeu sufixo) — persona interna canon, não hit. **pii_hits = 0 · controles 7/7.**

## Comandos reproduzíveis

```bash
git rev-parse HEAD origin/main; git rev-parse --is-shallow-repository
git diff --name-status origin/main...HEAD -- memory/requisitos | wc -l          # 56
git ls-tree -r --name-only origin/main -- prototipo-ui/prototipos | wc -l        # 0 (claim negativa)
git ls-tree -r --name-only origin/main -- prototipo-ui/cowork/Wagner | wc -l     # 652 (controle)
for p in <26 paths>; do git log origin/main --diff-filter=D --format='%h %cd' --date=short -- "$p"; done
git show 1070e3759b7^:prototipo-ui/prototipos/os/cowork-app.jsx | head -1        # "// os-page.jsx — …"
comm -12 <(git show 1070e3759b7^:prototipo-ui/prototipos/os/cowork-app.jsx | sort -u) <(git show origin/main:prototipo-ui/cowork/Wagner/os-page.jsx | sort -u) | wc -l   # 729
git grep -n blueprint_cowork origin/main -- 'resources/js/Pages/Repair/**/*.charter.md'   # os-page.jsx
node scripts/design/ancora.mjs Repair/JobSheet/Index --staging prototipo-ui/cowork/Wagner
node scripts/governance/reconcile-triplet.mjs --module=Produto --tela=Index       # sem --write
python - <<'EOF'   # pipes/pipe final das linhas de tabela
# ver seção R11/R12
EOF
```

Árvore deixada limpa: `git status --short` vazio exceto este arquivo (o `_STATUS-GENERATED.md` de Jana e a `deadlink-baseline.json` foram regenerados só pra medir e restaurados com `git checkout --` após `git status --short <path>` confirmar que estavam limpos).

```json
{"itens_verificados": 205, "erros_confirmados": 13, "error_rate_pct": 6.34, "pii_hits": 0, "veredito": "reprovado"}
```
