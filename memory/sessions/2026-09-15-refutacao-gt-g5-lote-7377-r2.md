---
date: "2026-09-15"
topic: "Refutação GT-G5 r2 do lote #7377 (73 docs memory/requisitos, anchors) — REPROVADO: 30 de 127 itens refutados (23,6%), PII 0"
authors: ["C"]
outcomes:
  - "Lote REPROVADO: 127 itens verificados a 100% contra origin/main, 30 refutados (error_rate 23,62% ≥ 2%)"
  - "Erro sistemático: 11 anotações citam SHA pré-rewrite fora da história de main; 8 atribuem remoção a 2bed3bbb214, que só tocou 2 .gitignore; 4 declaram 'removido' um alvo que EXISTE em main"
  - "PII: 0 hits em 7 padrões, 7/7 controles positivos casaram; scripts --check rodados com rc literal"
prs: [7377]
---

## TL;DR

**REPROVADO.** 127 itens verificados (100% dos ponteiros do lote) contra `origin/main` (`f3757e8d6e`); **30 refutados** → error_rate **23,62%** (≥ 2%). PII: **0 hits** (7/7 controles positivos ok). O lote passa no seu próprio medidor (`charter-blueprint-pointers --json` → `requisitos_total_orfaos: 0`), mas o silêncio foi comprado com recibo errado em ~1 de cada 4 anotações.

## Cabeçalho

- **Base:** `origin/main` = `f3757e8d6e26e48338e50540ce468d69ca024664` · **HEAD:** `db568a03af214c93a9168ace1053ddb60ac296fe`
- **Repo raso:** `git rev-parse --is-shallow-repository` → `false` (datas de git valem como recibo)
- **Sessão fresca:** sim — instância nova, sem contexto do gerador nem das rodadas anteriores; **não abri** nenhum `memory/sessions/*refutacao*` nem `memory/handoffs/` de hoje (`abriu_evidencia_anterior: false`); não li corpo de PR nem mensagem de commit como evidência
- **Tier:** refutador Fable 5.1 (tier máximo)
- **Arquivo de evidência existia antes?** não (`ls` → rc=2)
- **Árvore ao final:** limpa exceto esta evidência (e a evidência da r1, pré-existente e não aberta)

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (Fable = teto; igualdade só no tier máximo)
- [x] Amostra: **100% anchors** (127 linhas `+` do diff, todas com ponteiro/anotação; sem amostragem aleatória — sem seed)
- [x] Cada item verificado contra `origin/main` (`git ls-tree origin/main -- <path>`, `git ls-tree <sha>~1` × `<sha>`, `git merge-base --is-ancestor`, `git log origin/main --diff-filter=D`), nunca contra o diff
- [x] Cada REFUTADO com evidência (path + linha + commit + porquê)
- [x] Scan PII no diff (7 padrões + 7 controles positivos) — 0 hits
- [x] `error_rate_pct` calculado = 23,62 → **≥ 2, reprova**
- [ ] Entry no ledger — **não é minha** (lote reprovado volta pro gerador; não escrevi no ledger)

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` → **73 arquivos**, todos `M`; `--numstat` → **127 / 127** (troca 1:1 de linha). Fora de `memory/requisitos`: `scripts/governance/charter-blueprint-pointers.mjs` (M) e `scripts/governance/reconcile-triplet.test.mjs` (M).

As 127 linhas `+` caem em 3 formas:

| Forma | Itens | O que a linha afirma |
|---|---|---|
| **A** — reescrita de path (`design-docs/cowork-inbox/…` → `cowork/Wagner/cowork-inbox/…`, e `cowork/repair-page.jsx` → `cowork/Wagner/repair-page.jsx`), removendo a nota "removido do git em 2026-09-11 · #7224" | 32 | o path novo **existe** em main |
| **B** — sufixo `(removido em <data>, <sha>)` | 90 | o alvo **não existe** em main e `<sha>` (na história de main, naquela data) o removeu |
| **C** — sufixo `(alvo não resolve no repo — proveniência não determinada)` | 5 | o alvo não existe e a origem não foi medida |

## Resultado por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---|---|---|
| G1 — âncora existe em `origin/main` (forma A) | 32 | 32 | 0 | `git ls-tree origin/main -- <path>` devolve blob em 32/32; controle negativo `git ls-tree origin/main -- prototipo-ui/design-docs/cowork-inbox` → vazio |
| G2 — anotação de remoção: alvo ausente + SHA na história de main + SHA removeu o alvo + data = data do commit (forma B) | 90 | 61 | 29 | `git ls-tree origin/main` (ausência), `git merge-base --is-ancestor <sha> origin/main`, `git ls-tree <sha>~1 -- <p>` vs `git ls-tree <sha> -- <p>` (before=1/after=0), `git log -1 --format=%cs <sha>`; para o alvo, `git log origin/main --diff-filter=D -- <p>` como desempate. ⚠️ **`~1`, não `^`** — o `^` é escape no cmd.exe e a 1ª rodada do meu extrator saiu 100% `NOT_BEFORE` por isso; refeito em bash |
| G3 — "proveniência não determinada" (forma C) | 5 | 5 | 0 | alvo ausente em main **e** `git log --all -- <p>` = 0 commits (nunca versionado em ref nenhuma) |
| G4 — célula/linha íntegra (lente sobre as mesmas 127 linhas) | (127) | — | +1 | 3 linhas de tabela ganharam o sufixo **depois do pipe de fechamento** (4ª célula, que o GFM descarta) e 1 linha dentro de fence ```` ```bash ```` ganhou prosa; 2 dessas já contadas no G2 por outro motivo, **1 conta aqui** (0017:59) |
| **Total** | **127** | **97** | **30** | **error_rate = 30/127 = 23,62%** |

Leitor real do frontmatter (G2 do mandato): `scripts/design/ancora.mjs:208` lê `canon_reference` dos `*-visual-comparison.md` e o **reporta verbatim com o sufixo** (report-only, não quebra); `charter-blueprint-pointers.mjs` extrai `blueprint_cowork` com `^\s*blueprint_cowork:\s*["']?(\S+?)["']?\s*$` — com o sufixo a regex **não casa mais** (testado: `match= 0`), então o ponteiro sai do censo por **falha de parse**, não por `declaraMorte()`. Resultado igual, mecanismo diferente — registrado como observação, não como erro.

## REFUTADOS (30)

### Família 1 — SHA citado NÃO está na história de `origin/main` (11 itens)

`2f6892d06ca` e `799af73a3a7` existem no repo local porque **742 refs estagnadas** (634 `refs/remotes/origin/*` de branches antigas) ainda os alcançam, mas `git merge-base --is-ancestor <sha> origin/main` → **NO** para os dois. São os gêmeos **pré-rewrite** (o `filter-repo` de 2026-06-08 reescreveu 5.033 commits): o mesmo conteúdo em main é `4fad2f11f60` (#1210, 41 `D` em `ui_kits/cowork-2026-05-09/`) e `1070e3759b7` (#1218, 1095 `R100` — inclusive `ui_kits/cowork-2026-04-27/*` → `_BACKUP-NAO-USAR-cowork-2026-04-27/`). O próprio lote cita `1070e3759b7` em 23 outras linhas — i.e., usou os dois SHAs do mesmo commit conforme o alvo, sinal de `git log --all` em vez de `git log origin/main`. Um clone de `main` não resolve o recibo.

| # | Arquivo:linha | Item | O que main diz |
|---|---|---|---|
| 1 | `Financeiro/financeiro-unificado-visual-comparison.md:28` | `tasks.jsx` → `removido em 2026-05-20, 799af73a3a7` | SHA fora de main; em main é `1070e3759b7` (R100 pra `_BACKUP-NAO-USAR-cowork-2026-04-27/tasks.jsx`) |
| 2 | `Sells/sells-create-visual-comparison.md:23` | `os-page.jsx` → `799af73a3a7` | idem |
| 3 | `_DesignSystem/CHANGELOG.md:487` | `ui_kits/cowork-2026-04-27/` → `799af73a3a7` | idem |
| 4 | `_DesignSystem/adr/ui/0010-zip-cowork-2026-04-27-canon-visual.md:16` | `README.md` → `799af73a3a7` | idem |
| 5 | `_DesignSystem/adr/ui/0011-sidebar-single-pane-cascata-user-menu.md:11` | `sidebar.jsx` → `799af73a3a7` | idem |
| 6 | `_DesignSystem/adr/ui/0018-canon-visual-vivo-ds-v6-manual-identidade.md:54` | `ui_kits/cowork-2026-04-27/` → `799af73a3a7` | idem |
| 7 | `Jana/AUDITORIA-reconciliacao-tripla-analise-por-setor-2026-06-22.md:177` | `prod-page.jsx` → `2f6892d06ca` | SHA fora de main; em main é `4fad2f11f60` (D confirmado: before=1/after=0) |
| 8 | `Jana/Chat-visual-comparison.md:165` | `chat.jsx` → `2f6892d06ca` | idem |
| 9 | `Orcamento/_arquivo/Index.charter.md:157` | `orc-page.jsx` → `2f6892d06ca` | idem |
| 10 | `Produto/adr/arq/0001-selling-price-multiplier.md:173` | `produto-app.jsx` → `2f6892d06ca` | idem |
| 11 | `_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md:16` | `README.md` do kit 05-09 → `2f6892d06ca` | idem |

### Família 2 — "removido" num alvo que EXISTE em `origin/main` (4 itens)

`c3abe6ea51c` (2026-06-08) removeu `prototipo-ui/PROTOCOL.md` e `prototipo-ui/CODE_DESIGN_CONTRACT.md` (raiz). Os alvos **nomeados nas linhas** são `memory/reference/prototipo-ui/PROTOCOL.md` e `memory/reference/prototipo-ui/CODE_DESIGN_CONTRACT.md` — e `git ls-tree origin/main` devolve blob para os dois (`before=0 after=0 main=1` no `c3abe`). O defeito real é o link relativo curto um nível (`../../../../` de `adr/ui/` cai em `memory/`, não na raiz → resolve `memory/memory/reference/…`); a resposta era consertar o link, não carimbar morte num doc vivo.

| # | Arquivo:linha | Item |
|---|---|---|
| 12 | `_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md:21` | `[memory/reference/prototipo-ui/PROTOCOL.md](…)` → `removido em 2026-06-08, c3abe6ea51c` |
| 13 | `_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md:37` | idem (PROTOCOL.md) |
| 14 | `_DesignSystem/adr/ui/0017-design-system-v3-reconciliacao-ui-v2.md:19` | `CODE_DESIGN_CONTRACT.md` → `c3abe6ea51c` |
| 15 | `_DesignSystem/adr/ui/0017-design-system-v3-reconciliacao-ui-v2.md:109` | idem |

### Família 3 — atribuição a `2bed3bbb214` (2026-09-15), que só tocou 2 arquivos: `M .gitignore` e `D prototipo-ui/cowork/Wagner/.gitignore` (8 itens)

`git show --name-status 2bed3bbb214` → exatamente 2 linhas, **nenhuma sob `legado/` nem `_ds/`**.

| # | Arquivo:linha | Item | O que main diz |
|---|---|---|---|
| 16 | `Jana/PARIDADE-area-jana-diagnostico-e-ondas.md:206` | `prototipo-ui/cowork/_ds/office-impresso-design-system-019dd02f…/` → `removido em 2026-09-15, 2bed3bbb214` | `_ds/` **nunca foi versionado** (`git log --all -- prototipo-ui/cowork/_ds` = 0; `.gitignore:182` o ignora) — a própria prosa da L231 diz "cache derivado, gitignored". Anotar "removido" inventa uma remoção. Agravante: sufixo caiu **depois do pipe de fechamento** da linha de tabela (4ª célula descartada pelo GFM) |
| 17 | `Jana/PARIDADE-area-jana-diagnostico-e-ondas.md:231` | `prototipo-ui/cowork/_ds/` → `2bed3bbb214` | idem (nunca versionado) |
| 18 | `_DesignSystem/INDEX-DESIGN-MEMORIAS.md:34` | `prototipo-ui/cowork/Wagner/legado/<tela>/` → `2bed3bbb214` | `prototipo-ui/cowork/Wagner/legado/` **existe** em main com 10 subpastas (`financeiro-contador`, `nfe-tributacao`, `recurring-billing-planos`, …); `2bed3` não a tocou |
| 19 | `_DesignSystem/RUNBOOK-onda-cowork.md:81` | `legado/<modulo>/styles.css` → `2bed3bbb214` | `2bed3` não removeu nada em `legado/`; nenhum `styles.css` sob `legado/` em main (0) — quem removeu foi outro commit |
| 20 | `_DesignSystem/RUNBOOK-onda-cowork.md:208` | `legado/<modulo>/` → `2bed3bbb214` | pasta existe em main (item 18) |
| 21 | `_DesignSystem/RUNBOOK-replicar-prototipo-cowork.md:23` | `legado/<tela>/visual-source.html` → `2bed3bbb214` | `visual-source.html` (0 em main) foi removido por `7810bf5cb40` 05-19 · `c3abe6ea51c` 06-08 · `48b6911de90` 06-18 · `9da73296d34` 06-23 · `4f51a9ec781` 09-11 — nunca por `2bed3` |
| 22 | `_DesignSystem/RUNBOOK-replicar-prototipo-cowork.md:29` | idem | idem |
| 23 | `_DesignSystem/RUNBOOK-replicar-prototipo-cowork.md:94` | idem — e a linha é `wc -l …` **dentro de ```` ```bash ````**: a prosa `(removido em …)` virou argumento do comando | idem + célula não íntegra |

### Família 4 — atribuição a `4f51a9ec781` (#7224, 2026-09-11) para alvo que ele não removeu (4 itens)

| # | Arquivo:linha | Item | O que main diz |
|---|---|---|---|
| 24 | `OficinaAuto/oficina-os-nova-prototipo-visual-comparison.md:105` | `prototipo-ui/alvos/roles/OficinaAuto--ServiceOrders--Show.json` → `removido em 2026-09-11, 4f51a9ec781` | arquivo **nunca existiu em ref nenhuma** (`git log --all -- <p>` = 0; a pasta `alvos/roles/` só teve `Compras--Index.json` e 6 `Fiscal--*.json`). `4f51` removeu a pasta `prototipo-ui/alvos/`, não este arquivo — o vocabulário honesto do próprio gerador seria "nunca versionado" |
| 25 | `Jana/AUDITORIA-reconciliacao-tripla-analise-por-setor-2026-06-22.md:210` | linha "Refs internos" → `4f51a9ec781` | nenhum alvo da linha foi removido por `4f51`: `memory/reference/prototipo-ui/PROTOCOL.md` **existe**; `prototipo-ui/audit/reports/Produto__Index.design-report.json` nunca esteve em main (0 commits); `prototipo-ui/cowork-2026-05-26…/HANDOFF_PRODUTO_F1.md` (dir real) morreu em `8bd2b479db4` 2026-06-23 |
| 26 | `_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md:61` | link `sells-create/` → `4f51a9ec781` | `prototipo-ui/prototipos/sells-create` removido em **`1070e3759b7` 2026-05-20** (único `D` em main); em `4f51~1` a pasta `prototipos/` não tinha `sells-create` (listado: 11 subpastas, nenhuma). Agravante: sufixo após o pipe de fechamento da linha de tabela |
| 27 | `_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md:88` | link `sells-create/` → `4f51a9ec781` | idem |
| 28 | `_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md:93` | "pinos F1 de Financeiro/{Fluxo,PlanoContas,DRE,Conciliacao} em `prototipos/financeiro-*/`" → `4f51a9ec781` | os 4 pinos nomeados (`financeiro-fluxo`, `financeiro-plano-contas`, `financeiro-dre`, `financeiro-conciliacao`) morreram em **`1070e3759b7` 2026-05-20**; o que `4f51` removeu sob `financeiro-*` eram `assinatura-atualizar`/`contador`/`prova-viva`, que a frase não referencia |

### Família 5 — data/SHA contraditos pelo próprio arquivo (1 item)

| # | Arquivo:linha | Item | O que main diz |
|---|---|---|---|
| 29 | `Sells/index-r1-visual-comparison.md:14` | `visual_source_html: …/sells-index/Oimpresso ERP - Chat.html (removido em 2026-05-20, 1070e3759b7)` | a **L13 do mesmo arquivo, já em origin/main**, diz `# visual_source_html: o path abaixo foi removido em 2026-05-19, 7810bf5cb40` — e é isso que o git confirma (`git log origin/main -- "<p>"` → `7810bf5cb40 2026-05-19`); em `1070e3759b7~1` a pasta `sells-index/` já não tinha esse HTML. O lote empilhou um segundo recibo, errado, sob o certo |

### Família 6 — célula não íntegra sem outro defeito (1 item)

| # | Arquivo:linha | Item | Porquê |
|---|---|---|---|
| 30 | `_DesignSystem/adr/ui/0017-design-system-v3-reconciliacao-ui-v2.md:59` | `| 1 · Fundações (tokens) | \`prototipo-ui/tokens.css\` | _(removido em 2026-09-11, 878e6069d1a)_` | SHA/data corretos (`878e` before=1/after=0), mas o sufixo foi colado **depois do pipe de fechamento** — vira 4ª célula numa tabela de 3 colunas e o GFM **descarta células excedentes**: o registro é invisível no render. Mesma forma nos itens 16 e 26 |

## Observações (não contadas)

- **`1070e3759b7` é `R100`, não `D`:** em 2026-05-20 `prototipo-ui/prototipos/*` e `ui_kits/cowork-2026-04-27/*` foram **movidos** para `_BACKUP-NAO-USAR*/` (1095 renames); a deleção de verdade veio em `c3abe6ea51c` (2026-06-08). "removido em 2026-05-20" é verdade para o *path*, imprecisa para o *conteúdo*. 23 linhas do lote dependem disso.
- **Forma C (5 itens) confirmada, mas fraca:** `prototipo-ui/jana-metas/`, `prototipo-ui/cowork-snapshot/`, `prototipo-ui/design-oimpresso/04-modulos/vendas/sells-create.jsx` (×2) e `ui_kits/cockpit/index.html` têm **0 commits em qualquer ref** — "nunca versionado" era mensurável em 1 comando e é o vocabulário que o próprio `declaraMorte()` do lote já aceita.
- **Contradições pré-existentes que o lote empilhou em vez de resolver:** `0020:…` e `0027:…` diziam "gabarito-vendas.html removido do git em 2026-09-11 · **#7224**" e o lote acrescentou `d86c977a8dd` — que é **#7225**; o git dá razão ao lote (`d86c` before=1/after=0), mas a linha agora carrega dois PRs distintos para a mesma remoção. `0032:…` (3 linhas) ganhou `_(removido em 2026-09-11, 4f51a9ec781)_` ao lado de uma nota idêntica já existente — redundância, não erro.
- **Falso pré-existente, não do lote:** `Produto/BRIEFING.md` afirma que `prototipo-ui/cowork/Wagner/produtos-page.jsx` foi "removido do git em 2026-09-11 · #7224" — o arquivo **existe** em `origin/main` (`git ls-tree` devolve blob; `539efa2a8aa` não o tocou). `MANUAL-IDENTIDADE.md` linka `prototipo-ui/_arquivo/ds/ds-v6-README.md`, que não está em main. Ambos já estavam em `origin/main`.
- **Ambiguidade de anotação por linha:** onde a linha tem 2+ paths (`Governance/SPEC.md`, `MANUAL-IDENTIDADE`, `Produto/BRIEFING`, `Jana/AUDITORIA-…:210`), o sufixo não diz **qual** alvo morreu. Só contei como erro quando nenhum alvo da linha casa com o SHA (item 25).

## Máquina derivada (rc literal)

| Comando | rc | Leitura |
|---|---|---|
| `node scripts/governance/charter-blueprint-pointers.mjs --json` | 0 | `requisitos_total_orfaos: 0` · `requisitos_docs_com_orfao: 0` (o objetivo do lote, atingido); `charters_com_orfao: 9` / `total_orfaos: 10` (1ª raiz, fora do escopo do lote, advisory) |
| `node scripts/governance/reconcile-triplet.test.mjs` | 0 | todas as asserções passaram, incluindo as 3 novas (e2) |
| `node scripts/governance/plans-index.mjs --check` | 0 | — |
| `node scripts/governance/doc-id-index.mjs --check-collisions` | 0 | 0 colisão em 2752 ids |
| `node scripts/governance/design-code-map-check.mjs --check --strict` | **1** | `Ponto/espelho-show.map.json` STALE (`prototipo_sha`) — **herdado**: nenhum `*.map.json` no diff do lote |
| `node scripts/governance/requisitos-status.mjs Produto --check` | 0 | — |
| `node scripts/governance/requisitos-status.mjs Governance --check` | **1** | `_STATUS-GENERATED.md` não existe — **herdado** (o lote muda 1 linha de prosa no SPEC, nenhum status de US; nenhum `_STATUS-GENERATED.md` no diff) |
| `node scripts/governance/requisitos-status.mjs Sells --check` | **1** | `_STATUS-GENERATED.md` DRIFADO — **herdado**, mesma razão |
| `bash .github/scripts/validate-memory-schema.sh spec memory/requisitos/Governance/SPEC.md memory/requisitos/Sells/SPEC.md` | 0 | 2/2 validados, 0 erros (4 warnings de seções recomendadas, pré-existentes) |

Não achei no diff nenhum arquivo que o lote afirme ter regenerado e não esteja lá — mas não li o corpo do PR (proibido), então essa checagem é só "o que a árvore mostra".

## Scan PII (linhas `+` do diff: 127)

| # | Padrão | Hits | Controle positivo casou |
|---|---|---|---|
| 1 | CPF pontuado `\d{3}\.\d{3}\.\d{3}-\d{2}` | 0 | sim |
| 2 | CPF cru (11 dígitos isolados) | 0 | sim |
| 3 | CNPJ `\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}` | 0 | sim |
| 4 | telefone BR `(\d{2}) 9?\d{4}-\d{4}` | 0 | sim |
| 5 | telefone cru (10–11 dígitos) | 0 | sim |
| 6 | e-mail | 0 | sim |
| 7 | valor em reais (símbolo seguido de dígito — padrão não reproduzido aqui de propósito) | 0 | sim (a 1ª forma do meu regex não casou por escape do cifrão em ERE; corrigido com classe de caractere e re-rodado: controle 1, diff 0) |

**Controles: 7/7. Hits: 0.** Nenhum nome de cliente do CRM nas linhas `+` (os nomes que aparecem são de arquivos/telas).

## Comandos reproduzíveis

```bash
git rev-parse --is-shallow-repository                     # false
git diff --name-status origin/main...HEAD -- memory/requisitos | wc -l   # 73
git diff --numstat  origin/main...HEAD -- memory/requisitos | awk '{a+=$1;d+=$2} END{print a,d}'   # 127 127

# SHA fora de main (família 1)
git merge-base --is-ancestor 2f6892d06ca origin/main; echo $?   # 1
git merge-base --is-ancestor 799af73a3a7 origin/main; echo $?   # 1
git log origin/main --format='%h %as %s' --grep='#1210' --grep='#1218'   # 4fad2f11f60 · 1070e3759b7

# alvo vivo carimbado como removido (família 2)
git ls-tree origin/main -- memory/reference/prototipo-ui/PROTOCOL.md memory/reference/prototipo-ui/CODE_DESIGN_CONTRACT.md
git show --name-status --format= c3abe6ea51c | grep -E 'PROTOCOL|CODE_DESIGN_CONTRACT'

# 2bed3bbb214 tocou só 2 arquivos (família 3)
git show --name-status --format= 2bed3bbb214
git ls-tree -d origin/main -- prototipo-ui/cowork/Wagner/legado
git log --all --format=%h -- prototipo-ui/cowork/_ds | wc -l     # 0
git check-ignore -v prototipo-ui/cowork/_ds/x                    # .gitignore:182

# família 4/5
git log --all --format=%h -- prototipo-ui/alvos/roles/OficinaAuto--ServiceOrders--Show.json | wc -l   # 0
git log origin/main --format='%h:%cs' --diff-filter=D -- prototipo-ui/prototipos/sells-create          # 1070e3759b7:2026-05-20
git ls-tree --name-only 4f51a9ec781~1 prototipo-ui/prototipos/
git log origin/main --format='%h %cs' -- "prototipo-ui/prototipos/sells-index/Oimpresso ERP - Chat.html"   # 7810bf5cb40 2026-05-19

# remoção por SHA (padrão usado em todos os 90 itens; ~1 e não ^ — cmd.exe come o ^)
chk(){ echo "$1 before=$(git ls-tree $1~1 -- "$2"|wc -l) after=$(git ls-tree $1 -- "$2"|wc -l) main=$(git ls-tree origin/main -- "$2"|wc -l) :: $2"; }

# máquina
node scripts/governance/charter-blueprint-pointers.mjs --json | node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{const j=JSON.parse(s);console.log(j.requisitos_total_orfaos,j.requisitos_docs_com_orfao)})'
node scripts/governance/reconcile-triplet.test.mjs; echo rc=$?

# PII (linhas +)
git diff origin/main...HEAD -- memory/requisitos | grep -E '^\+' | grep -vE '^\+\+\+' > plus.txt
```

```json
{"itens_verificados": 127, "erros_confirmados": 30, "error_rate_pct": 23.62, "pii_hits": 0, "veredito": "reprovado"}
```
