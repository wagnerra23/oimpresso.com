---
date: "2026-09-15"
topic: "Refutação GT-G5 r3 do lote #7377 (73 docs em memory/requisitos, tipo anchors): 149 itens, 16 refutados, error_rate 10,7% — REPROVADO; PII 0/0 com 7/7 controles"
authors: ["C"]
prs: [7377]
outcomes:
  - "Lote REPROVADO: 16 erros confirmados em 149 itens (10,7%); só nos anchors (132 itens) 12,1% — bem acima do teto de 2%"
  - "Erro sistemático de prompt: 'proveniência não determinada' carimbada onde o próprio lote (ou o repo) determina a proveniência; e 'removido em 4f51a9ec781' num diretório que EXISTE em origin/main"
  - "PII: 0 hits em 129 linhas + com 7/7 controles positivos casando; máquina do lote (test + --json 2ª raiz) verde; --strict e design-code-map-check vermelhos são pré-existentes"
---

## TL;DR

Veredito **REPROVADO**. 149 itens verificados (132 anchors + 17 de leitor/máquina/PII), **16 refutados**, error_rate **10,7%** (12,1% só nos anchors) — teto do protocolo é 2%. PII: **0 hits**, 7/7 controles positivos. O erro é **sistemático**: o carimbo `_(alvo não resolve no repo — proveniência não determinada)_` foi aplicado (a) a alvos que **resolvem** em `origin/main` (`prototipo-ui/cowork/Wagner/legado/` tem 10 telas; `Produto/BRIEFING.md:134` tem 4 de 5 alvos vivos), (b) a linhas cuja proveniência **o próprio lote determinou** noutro arquivo para o mesmo diretório/commit, (c) a linhas que **já dizem** a proveniência (`.gitignore`, "renomeada pra"), e (d) no caso de `Sells/index-r1:374`, **substituindo um tombstone correto** (`7810bf5cb40`, D real em 2026-05-19). Fora isso, `Governance/SPEC.md:904` recebeu `removido em 4f51a9ec781` num diretório (`prototipo-ui/design-system/`) que existe como tree em `origin/main` — o commit apagou só `templates/` dentro dele.

## Cabeçalho

| Campo | Valor |
|---|---|
| Base | `origin/main` = `f0acd2db989e1a02337965fc6937c58fc795a17e` |
| HEAD do lote | `90eace108294f7044eaadc026088dca03c096ffa` (branch `claude/orfaos-mudos-requisitos`) |
| Repo raso | `git rev-parse --is-shallow-repository` → **false** (datas de `git log` valem como recibo) |
| Sessão fresca | sim — instância nova, sem contexto do gerador nem das rodadas r1/r2 |
| `abriu_evidencia_anterior` | **false** — `2026-09-15-refutacao-gt-g5-lote-7377-r{1,2}.md` estão untracked na árvore e **não foram abertos**; nenhum `memory/handoffs/` de hoje aberto |
| PR body / commit message lidos como evidência | não — só o diff, `origin/main` e o histórico |
| Arquivo de evidência já existia | não |
| Protocolo lido | §2, §3, §6 (não li §7) |

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (refutador = Fable 5.1, tier máximo; gerador do lote assina `[C]` sem tier declarado no commit — se for Opus, superior; se já for Fable, igualdade só admitida no tier máximo)
- [x] Amostra: **100%** dos anchors (129 linhas `+` = todo path/chave/anotação); prosa: N/A (lote tipo anchors) — sem seleção aleatória, logo sem seed
- [x] Cada item verificado contra `origin/main` (`git ls-tree origin/main -- <p>`, `git log origin/main --diff-filter=D -- <p>`, `git show --name-status -M <sha>`), nunca contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha + commit + porquê)
- [x] Scan PII no diff (7 padrões, 7 controles positivos) — 0 hits
- [x] `error_rate_pct` calculado: 10,7 (≥ 2 → reprovado)
- [ ] Entry no ledger — **não escrita** (mandato: não editar o ledger; reprovado volta ao gerador)

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos | wc -l` → **73** (bate com o mandato). Fora de `memory/requisitos`: `scripts/governance/charter-blueprint-pointers.mjs` (+19/−1) e `scripts/governance/reconcile-triplet.test.mjs` (+12). Linhas `+` em `memory/requisitos`: **129** (`git diff -U0 … | grep -c '^+[^+]'`). Cada linha `+` = 1 item de anchor.

Classes observadas no lote (129 linhas):

| Classe | O que a linha afirma | Linhas |
|---|---|---|
| A · re-ponteiro pro espelho | `design-docs/cowork-inbox/…` → `cowork/Wagner/cowork-inbox/…` (tombstone `#7224` removido); `cowork/repair-page.jsx` → `cowork/Wagner/repair-page.jsx` | 32 |
| D · conserto de link relativo | `../../../../memory/…` → `../../../../../memory/…` (UI-0013, UI-0017) | 4 |
| B · `_(removido em <data>, <sha>)_` | aquele commit apagou aquele path naquela data; path ausente hoje | 58 |
| C · `_(alvo não resolve no repo — proveniência não determinada)_` | path ausente em `origin/main`; origem não medida | 35 |

## Resultado por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---|---|---|
| 1 · Âncora existe em origin/main (A + D) | 36 | 36 | 0 | `git ls-tree origin/main -- <p>` nos 22 paths distintos do espelho → 22 blobs; controle negativo (`…/NAO-EXISTE.md`) vazio; controle positivo `CLAUDE.md` blob. **Identidade de conteúdo**: blob de `prototipo-ui/design-docs/cowork-inbox/<x>` em `4f51a9ec781^` == blob de `prototipo-ui/cowork/Wagner/cowork-inbox/<x>` em `origin/main` para **21 de 21** (SHA idêntico). `repair-page.jsx`: `cowork/repair-page.jsx` foi movido pra `cowork/Wagner/` no mesmo `4f51a9ec781`. Links D: de `memory/requisitos/_DesignSystem/adr/ui/`, 5 níveis = raiz → `memory/reference/prototipo-ui/{PROTOCOL,CODE_DESIGN_CONTRACT}.md` são blobs; o `../../../../` antigo resolvia pra `memory/memory/…` |
| 2 · Leitor real lê a anotação | 4 | 4 | 0 | `ancora.mjs`/charters: **N/A** — o lote não toca `related_prototype` nem charter de tela (o único `.charter.md` no diff é `Orcamento/_arquivo/Index.charter.md`, doc de requisitos). Leitor real das anotações = `charter-blueprint-pointers.mjs::declaraMorte` — as 3 formas (`removido em`, `o resolve no repo`, `nunca versionado`) casam; `--json` → `requisitos_total_orfaos = 0`, `requisitos_docs_com_orfao = 0` |
| 3 · Ação × veredito da prosa / afirmação sobre o repo (B + C) | 93 | 79 | **14** | por path: `git ls-tree origin/main` (ausência), `git log origin/main --diff-filter=D -- <p>` (commit/data), `git show --name-status -M <sha>` (o que o SHA fez), leitura da linha em `origin/main` |
| 4 · Célula íntegra (linhas de tabela tocadas) | 3 | 1 | **2** | anotação appendada **depois do pipe de fechamento** em `UI-0012:61` (header 3 col) e `UI-0017:59` (header 2 col) → célula excedente, **descartada no render GFM** (o registro fica invisível pra humano; só o gate lê). `PARIDADE:206` idem, mas o erro dela já está contado no G3 |
| 5 · Máquina derivada | 6 | 6 | 0 | rc literal abaixo; os 2 rc=1 são pré-existentes e fora do lote |
| 6 · PII | 7 | 7 | 0 | 7 padrões × 129 linhas `+`, 7/7 controles positivos, 0 hits |
| **Total** | **149** | **133** | **16** | error_rate = 16/149 = **10,7%** (anchors-only: 16/132 = 12,1%) |

## REFUTADOS (16)

### R1 · `memory/requisitos/Governance/SPEC.md:904` — `_(removido em 2026-09-11, 4f51a9ec781)_`
- **Afirma:** algo citado na linha foi removido por `4f51a9ec781`.
- **origin/main diz:** os paths da linha são `prototipo-ui/design-system/` (**tree, existe**), `cockpit_domains.css` dentro dele (**blob, existe**), `resources/css/tokens/semantic.tokens.json` (**blob, existe**) e `prototipo-ui/cowork/Wagner/legado/ds-v6/tokens.css` — que `4f51a9ec781` **ADICIONOU** (`A`), não removeu (quem removeu foi `d86c977a8dd`, #7225). `git show --name-status 4f51a9ec781 -- prototipo-ui/design-system/` → só `M` em 3 handoffs + `D` em `design-system/templates/*` (11).
- **Porquê é erro do lote:** `git log -1 --diff-filter=D -- prototipo-ui/design-system` devolve `4f51a9ec781` porque ele apagou `templates/` **dentro** do dir — o gerador leu isso como "o dir foi removido". Carimbo de morte num diretório vivo.

### R2 · `memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md:34` — `prototipo-ui/cowork/Wagner/legado/<tela>/` → "alvo não resolve no repo"
- **origin/main diz:** `git ls-tree --name-only origin/main prototipo-ui/cowork/Wagner/legado/` → **10** diretórios de tela (`financeiro-contador`, `nfse-emitir`, `payment-gateway-ui`, …).
- **Porquê é erro:** é a linha que define **onde vive o Protótipo Cowork** no índice que o `CLAUDE.md` manda usar pra resolver a fonte de design (§0). Dizer que "não resolve" é falso e instrui a próxima sessão a concluir que a fonte sumiu (§5 2026-09-01: afirmação de bloqueio em doc canon vira instrução de desistência). É placeholder `<tela>` — a classe que o próprio `ehPlaceholder` do lote diz que **não se carimba**.

### R3 · `memory/requisitos/_DesignSystem/RUNBOOK-onda-cowork.md:208` — `prototipo-ui/cowork/Wagner/legado/<modulo>/` → "alvo não resolve no repo"
- Mesma evidência do R2: o padrão de diretório resolve pra 10 módulos. (A linha 81, `legado/<modulo>/styles.css`, é diferente: nenhum dos 10 tem `styles.css` — ver observações.)

### R4 · `memory/requisitos/Produto/BRIEFING.md:134` — "alvo não resolve no repo — proveniência não determinada"
- **origin/main diz:** dos 5 alvos da linha, **4 resolvem**: `Produto/PROTOTIPO-preco-especial.md` (blob), `prototipo-ui/cowork/Felipe/produto-preco-especial/` (**tree**), `prototipo-ui/cowork/Wagner/produtos-page.jsx` (**blob** — e a linha já o declara "removido do git em 2026-09-11 · #7224", o que é falso desde `origin/main`), `Produto/produtos-gap.md` (blob). O único que não resolve, `prototipo-ui/cowork/prototipo-ui-patch/prototipos/produto/`, tem **um** commit D: `539efa2a8aa` (2026-08-31) — exatamente o commit que **este lote** usou em `Produto/RUNBOOK-unificado.md:32` para o `produto-app.jsx` desse mesmo diretório.
- **Porquê é erro:** anotação de linha inteira que engole 4 alvos vivos; e "não determinada" contradiz o próprio lote.

### R5 · `memory/requisitos/Sells/index-r1-visual-comparison.md:374` — tombstone correto trocado por "proveniência não determinada"
- **origin/main (antes do lote) dizia:** `_(removido em 2026-05-19, 7810bf5cb40)_`.
- **git diz:** `git log origin/main --diff-filter=D -- 'prototipo-ui/prototipos/sells-index/Oimpresso ERP - Chat.html'` → **`7810bf5cb40 2026-05-19`**, único D; `git show --name-status 7810bf5cb40 | grep sells-index` → `D prototipo-ui/prototipos/sells-index/Oimpresso ERP - Chat.html`; author = committer = 2026-05-19; ancestral de `origin/main`.
- **Porquê é erro:** o lote **apagou um fato datado verdadeiro** e pôs no lugar "não determinada" — regressão de informação, o inverso do que o lote existe pra fazer.

### R6 · `memory/requisitos/Sells/index-r1-visual-comparison.md:14` — `visual_source_html:` mesmo arquivo → "proveniência não determinada"
- Mesma evidência do R5: proveniência determinável em um comando (`7810bf5cb40`), e o próprio doc a tinha 360 linhas abaixo.

### R7 · `memory/requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md:118` — `…/project/Oimpresso ERP - Chat.html` → "proveniência não determinada"
- **git diz:** D em `8bd2b479db4` (2026-06-23) — **o mesmo commit que este lote carimbou 3 linhas acima** (`:102`, `:20`, `:5`) para `vendas-page.jsx`/`vendas-flow.jsx`/o diretório, que têm o **mesmo** perfil de histórico (D em `c3abe6ea51c` + `8bd2b479db4`). `git show --name-status 8bd2b479db4` lista `D prototipo-ui/cowork-2026-05-26-comunicacao-visual/project/Oimpresso ERP - Chat.html`.
- **Porquê é erro:** contradição interna — determinou para o irmão, "não determinou" para este.

### R8 · `memory/requisitos/Produto/adr/arq/0001-selling-price-multiplier.md:173` — `ui_kits/cowork-2026-05-09/produto-app.jsx` → "proveniência não determinada"
- **git diz:** `memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/produto-app.jsx` → D **único** em `4fad2f11f60` (2026-05-20, "apagar …/ui_kits/cowork-2026-05-09 (snapshot stale) #1210"). Este lote usou `4fad2f11f60` para `chat.jsx` (`Jana/Chat-visual-comparison.md:165`) e `README.md` (`UI-0012:16`) do **mesmo** diretório.

### R9 · `memory/requisitos/Orcamento/_arquivo/Index.charter.md:157` — `ui_kits/cowork-2026-05-09/orc-page.jsx` → "proveniência não determinada"
- Mesma evidência do R8: D único em `4fad2f11f60` (`git show --name-status 4fad2f11f60 | grep orc-page` → `D …/orc-page.jsx`).

### R10 · `memory/requisitos/Jana/AUDITORIA-reconciliacao-tripla-analise-por-setor-2026-06-22.md:177` — `ui_kits/cowork-2026-05-09/prod-page.jsx` → "proveniência não determinada"
- Mesma evidência do R8: D único em `4fad2f11f60`.

### R11 · `memory/requisitos/_DesignSystem/CHANGELOG.md:487` — `ui_kits/cowork-2026-04-27/` → "proveniência não determinada"
- **git diz:** `memory/requisitos/_DesignSystem/ui_kits/cowork-2026-04-27` → D único em `1070e3759b7` (2026-05-20). Este lote carimbou `1070e3759b7` em `UI-0010:16` (`README.md`), `UI-0011:11` (`sidebar.jsx`), `Financeiro/financeiro-unificado…:28` (`tasks.jsx`) e `Sells/sells-create…:23` (`os-page.jsx`) — todos do **mesmo** diretório.

### R12 · `memory/requisitos/_DesignSystem/adr/ui/0018-canon-visual-vivo-ds-v6-manual-identidade.md:54` — "proveniência não determinada" numa sentença que **determina** a proveniência
- **origin/main diz (linhas 54-55):** *"Link quebrado em UI-0010 (aponta `ui_kits/cowork-2026-04-27/` que foi renomeada pra `_BACKUP-NAO-USAR-cowork-2026-04-27/`)"*.
- **git confirma a sentença:** `git show --name-status -M 1070e3759b7` → `R100 …/ui_kits/cowork-2026-04-27/* → …/ui_kits/_BACKUP-NAO-USAR-cowork-2026-04-27/*` (15 arquivos).
- **Porquê é erro:** carimbar "não determinada" na própria frase que diz "foi renomeada pra X" é anotar sem ler a linha (§5 2026-08-10: abrir a linha antes de afirmar).

### R13 · `memory/requisitos/Jana/PARIDADE-area-jana-diagnostico-e-ondas.md:231` — `prototipo-ui/cowork/_ds/` → "proveniência não determinada"
- **A linha diz:** *"`prototipo-ui/cowork/_ds/` como **cache derivado, gitignored**"*. **O repo diz:** `.gitignore` de `origin/main` (lido por `git cat-file -p <blob>` — o `origin/main:.gitignore` mangleia no Git Bash, §5 2026-08-23) linhas **177** `prototipo-ui/cowork/**/_ds/` e **182** `prototipo-ui/cowork/_ds/`. `git log origin/main -- prototipo-ui/cowork/_ds` → vazio (nunca versionado, por desenho).
- **Porquê é erro:** a proveniência está determinada pela linha E pelo `.gitignore`; "não resolve no repo" é consequência declarada, não mistério.

### R14 · `memory/requisitos/Jana/PARIDADE-area-jana-diagnostico-e-ondas.md:206` — mesma cláusula, na linha de tabela `| 2 | prototipo-ui/cowork/_ds/… | … vazio |`
- Mesma evidência do R13 (a célula já diz **vazio**). Agravante de forma: a anotação foi appendada **depois do último pipe** de uma tabela de 3 colunas → 4ª célula, descartada no render.

### R15 · `memory/requisitos/_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md:61` — anotação fora da célula
- Header (`:52`): `| Arquivo do UI Kit | Padrão canônico de | Status atual no repo |` = 3 colunas. A linha ficou `| … | … | P0 — pino F1 em […] | _(removido em 2026-05-20, 1070e3759b7)_` = 4 células. GFM descarta a excedente: o "removido" **não aparece** no render. O fato em si (`prototipo-ui/prototipos/sells-create` → R100 em `1070e3759b7`) é verdadeiro — o defeito é de integridade da célula (G4).

### R16 · `memory/requisitos/_DesignSystem/adr/ui/0017-design-system-v3-reconciliacao-ui-v2.md:59` — anotação fora da célula
- Header (`:57`): `| Camada UI v2 | Artefato DS v3 |` = 2 colunas. A linha ficou `| 1 · Fundações (tokens) | prototipo-ui/tokens.css | _(removido em 2026-09-11, 878e6069d1a)_` = 3 células → descartada no render. Fato verdadeiro (`prototipo-ui/tokens.css` D em `878e6069d1a`), célula quebrada.

## Observações não contadas

1. **`1070e3759b7` não "removeu" — RENOMEOU (R100)** os 21 paths que o lote carimba com "removido em 2026-05-20, 1070e3759b7": `ui_kits/cowork-2026-04-27/*` → `ui_kits/_BACKUP-NAO-USAR-cowork-2026-04-27/*`; `prototipo-ui/prototipos/{boletos,financeiro-fluxo,produto-cockpit,produto-unificado,sells-create}/*` e `_cowork-export-2026-05-15/{app,sidebar}.jsx` → `prototipo-ui/_BACKUP-NAO-USAR/…`. Confirmei-os porque o path citado de fato deixou de existir ali e os destinos `_BACKUP-NAO-USAR*` também já foram apagados (`48b6911de90`/`ce0c0684fc5` 2026-06-18; `989dc211623` 2026-08-30) — hoje nada resolve. Mas "movido pra `_BACKUP-NAO-USAR`, apagado em <data>" seria o registro exato; o commit é um `docs(oficinaauto)` de 1.103 arquivos, o que já denuncia que não é uma remoção deliberada daquele artefato.
2. **`0020:13` e `0027:92`**: a linha já dizia *"removido do git em 2026-09-11 · #7224"* (pré-existente em `origin/main`) e o lote appendou `d86c977a8dd` (= **#7225**). O lote está certo e o tombstone antigo errado (`4f51a9ec781`/#7224 **adicionou** `legado/ds-v6/*`; `d86c977a8dd` apagou) — mas a linha agora carrega dois recibos contraditórios sem reconciliar.
3. **`MANUAL-IDENTIDADE.md:131`**: a Emenda 2026-09-11 (pré-existente) diz *"Sobrevivem no espelho … `tokens.css` … e `gabarito-vendas.html`"*; o lote appendou `_(removido em 2026-09-11, d86c977a8dd)_` (verdadeiro). A frase "sobrevivem" ficou falsa e sem errata — contradição deixada no doc.
4. **`Essentials/{documents,messages,reminders,todo}-index-gap.md:21`**: a frase *"Ele vive em `design-docs/…/contrato/`, não em `governance/design/contracts/`"* recebeu `removido` — verdadeiro — mas 7 linhas acima o mesmo lote re-apontou os contratos pro espelho; a frase deveria apontar pro espelho também. O ponto de fundo (contrato não é gate ativo) segue válido.
5. **`Design System v4.html` (`PARIDADE:728`)**: D em `878e6069d1a` (o commit que o lote usou em UI-0017 pra `tokens.css`, mesmo perfil de 2 D). Borderline da regra R8-R11 (mesmo commit, artefato diferente) — **não contei**.
6. **Placeholders `<tela>`/`<modulo>` em arquivo** (`RUNBOOK-replicar-prototipo-cowork.md:23,29,94` `legado/<tela>/visual-source.html`; `RUNBOOK-onda-cowork.md:81` `legado/<modulo>/styles.css`): confirmei como fato (nenhum dos 10 dirs tem esses arquivos; `git log --diff-filter=A` em `legado/*/visual-source.html|styles.css` → 0, nunca existiram), mas é a classe que o docblock do próprio `ehPlaceholder` do lote define como "falso-positivo do extrator, não dívida do doc" — o `ehPlaceholder` só cobre `YYYY-MM-DD` e `.../`, e o lote carimbou os `<…>` mesmo assim.
7. **`Produto/BRIEFING.md:134`**: o tombstone pré-existente *"`prototipo-ui/cowork/Wagner/produtos-page.jsx`, removido do git em 2026-09-11 · #7224"* é **falso em `origin/main`** (o arquivo é blob). Não é do lote, mas o lote re-tocou a linha e não corrigiu.
8. **Charters da 1ª raiz seguem apontando pra `design-docs/`**: `--strict` rc=1 com 9 charters/10 órfãos, entre eles `Patrimonio/{Alocacoes,Bens,Configuracoes}.charter.md` → `prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/*` — os mesmos paths que o lote re-apontou nos RUNBOOKs. Fora do escopo do lote (é `resources/js/Pages/**`), pré-existente, mas o trabalho ficou pela metade.
9. **`UI-0032:12`**: `handoff-crm/PEDIDO-CODE.md` ficou com dois tombstones (`#7224` + `4f51a9ec781`) — consistentes (mesmo commit), redundantes. Não há cópia no espelho (`git ls-tree -r origin/main prototipo-ui/cowork | grep PEDIDO-CODE` → só `modulos-faltantes/PEDIDO-CODE.md` e `2026-08-31-PEDIDO-CODE-guard-pele-paralela.md`, arquivos diferentes), então manter o path morto está correto.
10. **`OficinaAuto/…:105`** `prototipo-ui/alvos/roles/OficinaAuto--ServiceOrders--Show.json`: nunca versionado (`git log` vazio; `governance/design/targets/roles/` tem 7 arquivos, não este). "Não resolve" correto; "nunca versionado" seria a forma exata.
11. **Nunca versionados** (confirmados; "não determinada" é aceitável, "nunca versionado" seria melhor): `prototipo-ui/design-oimpresso/…` (×2), `prototipo-ui/jana-metas/`, `prototipo-ui/cowork-snapshot/`, `prototipo-ui/audit/reports/…`, `ui_kits/cockpit/index.html` (só existiu dentro de snapshots `_BACKUP-NAO-USAR/…/uploads/Design System/ui_kits/cockpit/`, adicionado em `8cd20a34863`).
12. **`prototipo-ui/prototipos/`** (`UI-0012:38,93`): ausente em `origin/main`, 5 commits D distintos (`7810bf5cb40`, `1070e3759b7`, `c3abe6ea51c`, `9da73296d34`, `4f51a9ec781`) — aqui "não determinada" é honesto. Confirmado.

## Scan PII (linhas `+` de `git diff origin/main...HEAD -- memory/requisitos`, 129 linhas)

| Padrão | Hits | Controle positivo |
|---|---|---|
| CPF pontuado | 0 | OK |
| CPF cru (11 dígitos isolados) | 0 | OK |
| CNPJ | 0 | OK |
| Telefone BR (com hífen) | 0 | OK |
| Telefone cru (10–11 dígitos) | 0 | OK |
| E-mail | 0 | OK |
| Valor em reais (símbolo seguido de dígito — padrão montado por `fromCharCode`, não reproduzido aqui) | 0 | OK |

Controles: **7/7** casaram. Nomes de cliente do CRM: nenhum nas 129 linhas (só paths, SHAs e datas). O scanner (`pii.mjs`) monta os regex sem par de barras literal — a 1ª versão via heredoc colapsou o par e quebrou o parse (§5 2026-08-19), reescrita com escrita direta de arquivo.

## Máquina derivada (rc literal)

| Comando | rc | Leitura |
|---|---|---|
| `node scripts/governance/reconcile-triplet.test.mjs` | **0** | todas as asserções passaram (incl. as 3 novas do lote) |
| `node scripts/governance/charter-blueprint-pointers.mjs --json` | **0** | `requisitos_total_orfaos=0`, `requisitos_docs_com_orfao=0`; `charters_com_orfao=9`, `total_orfaos=10` (1ª raiz) |
| `node scripts/governance/charter-blueprint-pointers.mjs --strict` | **1** | vermelho vem da 1ª raiz (9 charters em `resources/js/Pages/**`, nenhum no diff do lote) — **pré-existente** |
| `node scripts/governance/doc-id-index.mjs --check-collisions` | **0** | 0 colisão em 2753 ids |
| `node scripts/governance/plans-index.mjs --check` | **0** | índice em dia |
| `node scripts/governance/design-code-map-check.mjs --check --strict` | **1** | STALE em `memory/requisitos/Ponto/espelho-show.map.json` — `git diff --quiet origin/main...HEAD -- memory/requisitos/Ponto` → sem diff, **pré-existente** |

Árvore após as sondagens: `git status --short` → só os 2 untracked r1/r2 (não meus) + esta evidência. Nada escrito no lote nem no ledger.

## Comandos reproduzíveis

```bash
# base / raso / escopo
git rev-parse origin/main HEAD; git rev-parse --is-shallow-repository
git diff --name-status origin/main...HEAD -- memory/requisitos | wc -l          # 73
git diff -U0 origin/main...HEAD -- memory/requisitos | grep -c '^+[^+]'        # 129

# G1 — espelho existe + identidade de conteúdo
git ls-tree origin/main -- prototipo-ui/cowork/Wagner/cowork-inbox/hrm/PEDIDO-CL-hrm.md
git ls-tree '4f51a9ec781^' -- prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md   # mesmo blob

# R1 — dir vivo carimbado como removido
git ls-tree origin/main -- prototipo-ui/design-system                            # tree
git log -1 --format='%h %as' --diff-filter=D -- prototipo-ui/design-system       # 4f51a9ec781 (apagou só templates/)
git show --format= --name-status 4f51a9ec781 -- prototipo-ui/cowork/Wagner/legado/ds-v6/tokens.css   # A

# R2/R3 — legado resolve
git ls-tree --name-only origin/main prototipo-ui/cowork/Wagner/legado/          # 10 dirs

# R4 — 4 de 5 alvos vivos
git ls-tree origin/main -- prototipo-ui/cowork/Felipe/produto-preco-especial prototipo-ui/cowork/Wagner/produtos-page.jsx
git log origin/main --format='%h %as' --diff-filter=D -- prototipo-ui/cowork/prototipo-ui-patch/prototipos/produto   # 539efa2a8aa

# R5/R6 — tombstone correto apagado
git log origin/main --format='%h %as' --diff-filter=D -- 'prototipo-ui/prototipos/sells-index/Oimpresso ERP - Chat.html'  # 7810bf5cb40 2026-05-19

# R7–R11 — proveniência determinável em 1 comando (e usada pelo próprio lote)
git log origin/main --format='%h %as' --diff-filter=D -- 'prototipo-ui/cowork-2026-05-26-comunicacao-visual/project/Oimpresso ERP - Chat.html'
git log origin/main --format='%h %as' --diff-filter=D -- memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/produto-app.jsx   # 4fad2f11f60
git log origin/main --format='%h %as' --diff-filter=D -- memory/requisitos/_DesignSystem/ui_kits/cowork-2026-04-27                   # 1070e3759b7

# R12 — a sentença determina
git show origin/main:memory/requisitos/_DesignSystem/adr/ui/0018-canon-visual-vivo-ds-v6-manual-identidade.md | sed -n '54,55p'
git show --format= --name-status -M 1070e3759b7 | grep 'ui_kits/cowork-2026-04-27' | head -3   # R100 → _BACKUP-NAO-USAR

# R13/R14 — gitignored (sem <ref>:<path>, que mangleia no Git Bash)
git cat-file -p $(git ls-tree origin/main -- .gitignore | awk '{print $3}') | grep -n '_ds'   # 177, 182

# R15/R16 — header das tabelas
git show origin/main:memory/requisitos/_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md | sed -n '52,53p'
git show origin/main:memory/requisitos/_DesignSystem/adr/ui/0017-design-system-v3-reconciliacao-ui-v2.md | sed -n '57,58p'

# Máquina
node scripts/governance/reconcile-triplet.test.mjs; echo rc=$?
node scripts/governance/charter-blueprint-pointers.mjs --json | node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{const j=JSON.parse(s);console.log(j.requisitos_total_orfaos,j.charters_com_orfao)})'
```

```json
{"itens_verificados": 149, "erros_confirmados": 16, "error_rate_pct": 10.7, "pii_hits": 0, "veredito": "reprovado"}
```
