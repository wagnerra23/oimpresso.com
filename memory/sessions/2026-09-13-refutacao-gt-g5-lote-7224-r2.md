---
date: "2026-09-13"
topic: "Refutação GT-G5 rodada r2 do lote #7224 (290 arquivos em memory/requisitos, tipo anchors): 1112 itens, 22 refutados (1,98%), PII 0 — 9 map.json ficaram STALE pro consumidor e 3 notas 'removido do git' são falsas"
authors: ["C"]
prs: [7224]
outcomes:
  - "Lote medido 100% contra 85e9cbf12f: 1112 itens verificados, 22 refutados, error_rate 1,98% (< 2 → aprovado pela régua mecânica; abaixo do limiar por margem de 0,02 ponto)"
  - "9 map.json que eram SYNC no base viraram STALE em HEAD (prototipo_sha não regenerado após reescrever prototipo.arquivo) — consumir-map.mjs aborta rc=3; conserto = gerar-map.mjs --atualizar em cada um"
  - "3 notas 'removido do git em 2026-09-11 · #7224' apontam arquivos que EXISTEM em HEAD (renomeados, não removidos) + 4 'Ele vive em <dir apagado>' + 4 ponteiros a playbooks apagados sem marcador + 2 links relativos reescritos que continuam mortos"
---

# Session log 2026-09-13 — Refutação GT-G5 · lote #7224 · rodada r2

## TL;DR

Veredito **aprovado** pela régua mecânica do protocolo (error_rate < 2% e PII = 0): **1112 itens verificados · 22 erros confirmados · error_rate 1,98%** · PII hits 0 (7/7 controles positivos casaram). A margem é de 0,02 ponto — os 22 refutados são reais e dois grupos deles quebram máquina (9 `map.json` cujo consumidor `consumir-map.mjs` agora aborta) ou afirmam falso em canon (3 notas "removido do git" sobre arquivos que existem em HEAD). Recomendo follow-up antes de qualquer Fase 4 nesses 9 maps.

## Cabeçalho

| Campo | Valor |
|---|---|
| PR | #7224 (`refactor(prototipo): separar fontes por dono e remover paralelos`) — estado `MERGED` em 2026-09-11T21:14Z, head `codex/prototipo-ssot-cleanup` (gerador **Codex/não-Anthropic**, §4.1) |
| Base | `85e9cbf12f` (parent único do commit do lote) |
| HEAD | `4f51a9ec78` (= merge commit do PR) |
| Repo raso | `git rev-parse --is-shallow-repository` → **false** |
| Tipo | anchors · amostra 100% |
| Refutador | Claude Opus 5 (Anthropic, tier opus) — sessão fresca, worktree `agent-2-credits-7f5edb` |
| Escopo medido | `git diff --name-status 85e9cbf12f...HEAD -- memory/requisitos` → **290 M** (0 A/D/R) · `+1169/−1153` linhas |
| Árvore ao final | `git status --short` = só `?? scripts/governance/refutacao-recibo.mjs` (untracked pré-existente, não é meu) + esta evidência |

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador; não abri `memory/sessions/*refutacao*` nem `memory/handoffs/` — `abriu_evidencia_anterior: false`)
- [x] Modelo de tier SUPERIOR ao gerador (gerador Codex → §4.1 exige Anthropic ≥ opus; refutador = opus)
- [x] Amostra: 100% anchors (todo path das linhas `+`, todo `map.json` regenerado chave a chave, todo frontmatter tocado, toda nota "removido do git")
- [x] Cada item verificado contra o código real em `85e9cbf12f`/`HEAD` (`git ls-tree`, `git show <base>:<path>`, mapa de rename do git), não contra o texto do PR
- [x] Cada REFUTADO anotado com evidência (path + linha/commit + porquê)
- [x] Scan PII no diff — 7 padrões × controle positivo (7/7) · hits 0
- [x] `error_rate_pct` calculado = 1,98 (< 2)
- [ ] Entry no ledger — **não é desta rodada** (mandato: não escrever no ledger)

## Escopo medido (o que é o lote)

O lote é uma **reescrita de path** que acompanha o rename do PR (`git diff --name-status -M 85e9cbf12f HEAD`: 587 R · 504 D · 11 A). Padrões dominantes nas linhas `+` (contados): `prototipo-ui/cowork/<x>` → `prototipo-ui/cowork/Wagner/<x>` (924), `prototipo-ui/*.mjs` → `scripts/design/*.mjs`, `prototipo-ui/*.md` → `memory/reference/prototipo-ui/*.md`, `prototipo-ui/contrato/` → `governance/design/contracts/`, `scripts/design-sync/mirror-snapshot/` → `prototipo-ui/design-system/`. Word-diff (`--word-diff=porcelain`): **382 pares distintos**, dos quais só 17 não são path (5 `owner: W` · 5 `last_validated` · 4 `active→ativo` · 1 aspas em data · 1 `related_adrs` em slugs · 2 `<!-- pii-allowlist -->` · 6 redirect DS).

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---|---|---|
| 1. Âncora existe (HEAD) e mapeia por rename a partir de 85e9cbf12f | 850 | 848 | 2 | Extração de todo path/link relativo das linhas `+` (único por arquivo×path resolvido) × `git ls-tree -r HEAD`; 785 existem; 61 ausentes já eram o MESMO token no base (herdados/fragmentos, não-claim do lote); 296 pares old→new conferidos contra o mapa `R` do git (254 arquivo exato · 40 diretório · 2 redirect pra cópia blob-idêntica no base) |
| 2. Âncora não revogada / leitor real | 66 | 66 | 0 | `node scripts/design/ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork/Wagner` nas 66 telas dos `map.json`: 63 `âncora ✓`, 3 sem charter por desenho (Foundation/PageHeader, GradeMatrixInput, Shell/Sidebar); 0 REVOGADA/MIS-ANCHOR (o único MIS-ANCHOR, kanban-producao, já está `map_json: n/a`) |
| 3. Ação × veredito · afirmação sobre o repo | 62 | 51 | 11 | 31 notas "removido do git" (path × base-tree × head-tree) · 15 normalizações de frontmatter (valor derivado do próprio arquivo) · 2 `pii-allowlist` · 6 redirect `mirror-snapshot→design-system` (blobs idênticos em 85e9cbf12f: `79c31435…`, `dc67e6f5…`, `3aa50565…`) · 4 linhas "Ele vive em" · 4 ponteiros a playbook apagado |
| 4. Célula íntegra / derivado regenerado | 67 | 58 | 9 | `node scripts/design/gerar-map.mjs <gap> --atualizar` (stdout) para os 67 maps do lote × commitado, chave a chave (6044 chaves): 0 divergência em `acao`/`partes`/`vivo`/`prototipo.arquivo`; divergem só `gerado_em` (esperado) e `prototipo_sha` (65 — 9 causadas pelo lote, 56 herdadas) e 2 `_acionavel` herdados (idênticos no base). Extra: 214 `.md` do lote com tabela — contagem de linhas `|` e de colunas por linha idêntica base×HEAD (0 diff); 0 code-span truncado novo |
| 5. Máquina derivada | 67 | 67 | 0 | 7 checks (rc literal abaixo) + `scripts/memory-schemas/validate.mjs` nos 60 SPEC/RUNBOOK/BRIEFING do lote (58 OK · 2 FAIL em frontmatter NÃO tocado, família GRACE, mesmo FAIL no base) |
| 6. PII | 7 padrões | — | 0 hits | 1267 linhas `+` × 7 regex; controle positivo 7/7 |
| **Total** | **1112** | **1090** | **22** | error_rate = 22/1112 = **1,98%** |

## REFUTADOS (22)

### R1–R3 · nota "removido do git" sobre arquivo que EXISTE em HEAD (grupo 3)

- **R1** `memory/requisitos/Produto/BRIEFING.md:134` — item: `` `produtos-page.jsx` (`prototipo-ui/cowork/Wagner/produtos-page.jsx`, removido do git em 2026-09-11 · #7224 · ADR 0397) ``. **85e9cbf12f** tinha o link vivo `[produtos-page.jsx](../../../prototipo-ui/cowork/produtos-page.jsx)`. O PR fez `R100 prototipo-ui/cowork/produtos-page.jsx → prototipo-ui/cowork/Wagner/produtos-page.jsx`; `git ls-tree HEAD -- prototipo-ui/cowork/Wagner/produtos-page.jsx` → blob `937352ad5c`. Duplo erro: (a) o arquivo não foi removido, foi renomeado e existe; (b) o path citado como "removido" (`…/Wagner/…`) **nunca existiu no git antes deste commit** (base-tree: 0). O lote rebaixou um link que funcionaria pra texto com afirmação falsa.
- **R2** `memory/requisitos/_DesignSystem/adr/ui/0020-dark-warm-ds-v6-tokens.md:13` — item: `` `prototipo-ui/cowork/Wagner/legado/ds-v6/gabarito-vendas.html` (removido do git em 2026-09-11 · #7224 · ADR 0397) ``. PR: `R099 prototipo-ui/cowork/ds-v6/gabarito-vendas.html → prototipo-ui/cowork/Wagner/legado/ds-v6/gabarito-vendas.html`; `git ls-tree HEAD` → blob `0fd01151ee`. Mesmo duplo erro de R1, num ADR (canon).
- **R3** `memory/requisitos/_DesignSystem/adr/ui/0027-dark-hue-240-supersede-0020-0022.md:92` — mesma nota falsa sobre o mesmo gabarito.
- Controle: as outras **28 ocorrências** de "removido do git" (22 paths distintos sob `prototipo-ui/design-docs/**`) batem — base-tree 1, head-tree 0, todos `D` no PR.

### R4–R5 · link relativo reescrito que continua morto (grupo 1)

- **R4** `memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md:21` e `:37` — `[memory/reference/prototipo-ui/PROTOCOL.md](../../../../memory/reference/prototipo-ui/PROTOCOL.md)`. De `memory/requisitos/_DesignSystem/adr/ui/`, `../../../../` resolve pra `memory/`, logo o alvo é `memory/memory/reference/prototipo-ui/PROTOCOL.md` — não existe (`ls memory/memory` → não existe). **Herdado:** no base o link já era `../../../../prototipo-ui/PROTOCOL.md` (→ `memory/prototipo-ui/PROTOCOL.md`, também morto); o codemod trocou o alvo e preservou a profundidade errada — o link certo seria `../../../reference/prototipo-ui/PROTOCOL.md`. Contado porque a linha é do lote e o path que ela cita não existe; o irmão UI-0012 usa 5× `../` e resolve.
- **R5** `memory/requisitos/_DesignSystem/adr/ui/0017-design-system-v3-reconciliacao-ui-v2.md:19` — `[memory/reference/prototipo-ui/CODE_DESIGN_CONTRACT.md](../../../../memory/reference/prototipo-ui/CODE_DESIGN_CONTRACT.md)` → `memory/memory/…`, mesma causa.

### R6–R14 · `map.json` que era SYNC no base e ficou STALE em HEAD (grupo 4/5)

`computeProtoHash` (`scripts/design/gerar-map.mjs:124-133`) hasheia `${rel}:${contentHash}` — o **path** entra no `prototipo_sha`. O lote reescreveu `prototipo.arquivo` (`prototipo-ui/cowork/<x>` → `…/Wagner/<x>`, conteúdo R100 idêntico) à mão e **não** regenerou o sha. `node scripts/governance/design-code-map-check.mjs --check --strict`: base **55 STALE** (herdados) → HEAD **64 STALE**; os 9 novos são exatamente estes (diff de cada um = só a reescrita de path, `+N/−N`):

| # | map | sha salvo | sha atual | diff do lote |
|---|---|---|---|---|
| R6 | `memory/requisitos/Backup/backup-index.map.json` | `2d6fce561b2b` | `f5bb0276490a` | +8/−8 |
| R7 | `memory/requisitos/Cliente/clientes.map.json` | `2be4c00c452a` | `638e8efb03d1` | +8/−8 |
| R8 | `memory/requisitos/Financeiro/cobranca-index.map.json` | `be43765fdf6b` | `9fcd774eb030` | +8/−8 |
| R9 | `memory/requisitos/Financeiro/unificado.map.json` | `340b86857eb6` | `fdb5ee77ffad` | +13/−13 |
| R10 | `memory/requisitos/KB/kb-index-v2.map.json` | `3f2c78b83b65` | `3e6c15392a73` | +9/−9 |
| R11 | `memory/requisitos/KB/kb.map.json` | `3f2c78b83b65` | `3e6c15392a73` | +7/−7 |
| R12 | `memory/requisitos/PaymentGateway/settings-paymentgateways-index.map.json` | `4ceb25e0b886` | `d9e0259b9711` | +8/−8 |
| R13 | `memory/requisitos/Sells/vendas.map.json` | `0462e8dec988` | `f9ac1274756b` | +7/−7 |
| R14 | `memory/requisitos/Suporte/suporte-empresas.map.json` | `2219383cf92c` | `90be58dc3a2b` | +8/−8 |

Consequência medida: `node scripts/design/consumir-map.mjs memory/requisitos/Sells/vendas.map.json` → `⛔ ABORTAR Fase 4 — Sells/Index: prototipo_sha salvo='sha256:0462e8dec988' · atual='sha256:f9ac1274756b'` **rc=3**. É a lápide §5 2026-08-12 (*"doc que a máquina LÊ é código com cara de doc — valida rodando o consumidor"*). Conserto: `node scripts/design/gerar-map.mjs <gap> --atualizar` (preserva o preenchido; regenerei os 67 em stdout e a única chave que muda além de `gerado_em` é `prototipo_sha`).

### R15–R18 · "Ele vive em `<diretório apagado pelo PR>`" em linha que o lote editou (grupo 3)

- **R15** `memory/requisitos/Essentials/documents-index-gap.md:21` · **R16** `messages-index-gap.md:22` · **R17** `reminders-index-gap.md:22` · **R18** `todo-index-gap.md:22` — a mesma frase: *"O contrato citado ainda NÃO é gate ativo. Ele vive em `prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/`, **não** em `governance/design/contracts/`"*. O lote **editou esta linha** (trocou `prototipo-ui/contrato/` → `governance/design/contracts/`) e deixou, em presente, o diretório que o mesmo PR apagou (`D` em 454 arquivos de `prototipo-ui/design-docs/**`; head-tree: 0 entradas em `prototipo-ui/design-docs/cowork-inbox/essenciais/contrato`). Nas linhas 14–15 dos mesmos arquivos o lote aplicou a nota "removido do git" aos `.contract.json` — a linha 21/22 ficou contradizendo a 14/15 dentro do mesmo bloco.

### R19–R22 · ponteiro a playbook apagado, sem marcador, no mesmo arquivo em que o irmão recebeu o marcador (grupo 3, omissão)

- **R19** `memory/requisitos/AssetManagement/RUNBOOK-alocacoes.md:171` — `- Playbook: \`prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/06-ui-bloqueada.md\``
- **R20** `memory/requisitos/AssetManagement/RUNBOOK-bens.md:168` — idem `06-ui-bloqueada.md`
- **R21** `memory/requisitos/AssetManagement/Index-visual-comparison.md:235` — `07-painel.md` (as linhas 236–237 do MESMO arquivo receberam "removido do git" para `_saida-07.md`/`_saida-06-painel.md`)
- **R22** `memory/requisitos/AssetManagement/RUNBOOK-configuracoes.md:172` — `11-configuracoes.md`
- Todos: base-tree 1 · head-tree 0 · `D` no PR · linha em presente ("Playbook:") sem a nota. Varredura **em todo `memory/requisitos`** (não só no lote) de tokens que existiam no base e não existem em HEAD, fora linhas "removido do git": 10 distintos — além destes 4 e dos 4 de R15–R18, sobram só registros datados (INVENTARIO-ANCORAS-2026-09-09:130/835, Repair/RUNBOOK-repair-settings:27 "Data: 2026-09-04", Repair/DECISAO-W-…-2026-09-04:7, TeamMcp/forja-cockpit:134 "sha 9d2f6ce4… = pacote de 24/08", 7b-lote:30, pageheader-canon-v3-gap:4 `prototipo_nota` datada, Jana/RUNBOOK-metas:386 "27/08") — história, não contados.

## Observações não contadas

1. **Lote já mergeado** (2026-09-11T21:14Z) com labels `adr-body-edit-W` + `adr-metadata-normalization` — esta r2 é pós-merge; os 22 refutados precisam de PR follow-up, não de re-push.
2. **Reescrita uniforme dentro de registro datado** (63 linhas `+` em 20 arquivos: 12 ADR UI, `INSPECAO-FORENSE-2026-05-20`, 2 `AUDITORIA-*-2026-06-22`, `INVENTARIO-ANCORAS-2026-09-09`, `Sells-r4-…-2026-05-26`, `Produto/adr/arq/0001`, `_arquivo`, `_legado`). Ex.: `_processo/ONDAS-MWART-A-CRIAR.md:14` — *"Como foi medido (2026-09-06, origin/main 80bc4ef8b9): A_CRIAR de `scripts/design/detectar-telas.mjs` … `node scripts/design/ancora.mjs`"* — em 80bc4ef8b9 esses scripts viviam em `prototipo-ui/`. Pela lápide §5 2026-08-12 (d)/(e) o registro datado citaria o path histórico como texto com nota; o lote tratou como ponteiro vivo. Não contei porque os **fatos** (valores, datas, shas) foram preservados e o ponteiro atualizado é o que a ADR 0377 libera ("ponteiro podre atualiza, fato datado preserva") — registro pra [W] decidir se a classe conta.
3. **Frontmatter `owner: W` (5×)**: enum válido; a data de `last_validated` vem de `date:`/`session_date:` do próprio arquivo (verifiquei os 5). Não verifiquei o `W` contra a matriz do `TEAM.md` (o grep da matriz não localizou linha Sells/Financeiro) — fica como declaração do lote, não como fato conferido.
4. **61 tokens de path mortos herdados** nas linhas `+` (mesmo token já no base, fora da reescrita): ex. `prototipo-ui/cowork/prototipo-ui-patch/prototipos/produto/` (Produto/BRIEFING:134), `prototipo-ui/prototipos/vendas-cockpit/` (`blueprint_cowork` dos 4 RUNBOOK Sells — chave que o `ancora.mjs` ignora, §5 2026-09-09), `prototipo-ui/tokens.css` (UI-0017:20). Já eram mortos em 85e9cbf12f — não são claim do lote.
5. `design-code-map-check --check --strict` já saía **rc=1 com 55 STALE no base**; `doc-id-index.mjs --check` rc=1 em base e HEAD (índice atrasado de 0388–0397 e sessions/handoffs de setembro — fora de `memory/requisitos`); `requisitos-status.mjs <Mod> --check` falha em **31 dos 42 módulos** do lote e o conjunto de FAIL é **idêntico** no base. `Auditoria/BRIEFING.md` e `Mwart/BRIEFING.md` reprovam no `briefing.schema.json` por frontmatter que o lote **não** tocou (família GRACE).
6. `git ls-tree <ref> -- <path>` devolve **rc=0 mesmo quando o path não existe** (saída vazia) — usei presença de blob na saída como critério, não o rc; controle negativo `git ls-tree 85e9cbf12f -- memory/decisions/0395-nao-existe.md` → vazio.
7. `prototipo-ui/design-system/colors_and_type.css` e `_ds_bundle.js` mudaram no PR (6 e 2 linhas); as ADR UI-0027/0031 que passaram a apontar pra eles afirmam valores de `.cockpit[data-theme="dark"]` (`--bg 0.26 0.006 240` …) que conferi **iguais** em HEAD (linhas 329–334) e no base.

## Scan PII (linhas `+`, 1267)

| Padrão | Controle positivo | Hits brutos | Veredito |
|---|---|---|---|
| CPF pontuado | ✓ | 1 | máscara `000.000.000-00` (Crm/cliente-drawer-760-visual-comparison) — já no base |
| CPF cru (11 dígitos isolados) | ✓ | 1 | id numérico de run do GitHub Actions (TeamMcp/forja-cockpit) — já no base, não é PII |
| CNPJ | ✓ | 2 | máscara `00.000.000/0000-00` + exemplo sintético `11.222.333/0001-81` (Sells-r4) — ambos já no base; o lote só adicionou `<!-- pii-allowlist -->` |
| Telefone BR | ✓ | 0 | — |
| Telefone cru (10–11 dígitos) | ✓ | 1 | mesmo id de run acima |
| E-mail | ✓ | 0 | — |
| Símbolo de reais + dígito (padrão descrito, não reproduzido) | ✓ | 0 | — |
| Nomes de cliente do CRM | — | 3 menções a personas do canon (cliente piloto/monitor 1280px) já presentes no base | não conta |

**pii_hits = 0 · controles positivos 7/7.**

## Comandos reproduzíveis

```bash
git rev-parse --is-shallow-repository                                   # false
git diff --name-status 85e9cbf12f...HEAD -- memory/requisitos | wc -l  # 290
git diff --name-status -M 85e9cbf12f HEAD > ns.txt                      # 587 R · 504 D · 11 A
git ls-tree -r --name-only HEAD > head-tree.txt; git ls-tree -r --name-only 85e9cbf12f > base-tree.txt
git ls-tree HEAD -- prototipo-ui/cowork/Wagner/produtos-page.jsx prototipo-ui/cowork/Wagner/legado/ds-v6/gabarito-vendas.html   # 2 blobs (R1–R3)
git ls-tree 85e9cbf12f -- scripts/design-sync/mirror-snapshot/colors_and_type.css prototipo-ui/design-system/colors_and_type.css # mesmo blob 79c31435…
node scripts/governance/design-code-map-check.mjs --check --strict     # HEAD rc=1 · 64 STALE (base: rc=1 · 55 STALE)
node scripts/design/gerar-map.mjs memory/requisitos/Sells/vendas-gap.md --atualizar > /dev/null   # stderr: sha 0462e8dec988 → f9ac1274756b
node scripts/design/consumir-map.mjs memory/requisitos/Sells/vendas.map.json                     # rc=3 ABORTAR
node scripts/design/ancora.mjs Sells/Index --staging prototipo-ui/cowork/Wagner                  # âncora ✓ vendas-page.jsx
node scripts/governance/plans-index.mjs --check                        # rc=0
node scripts/governance/doc-id-index.mjs --check-collisions            # rc=0 (0 colisão em 2718 ids)
node scripts/governance/doc-id-index.mjs --check                       # rc=1 (drift pré-existente, idem no base)
node scripts/governance/anchor-lint.mjs --check                        # rc=0
node scripts/governance/deadlink-gate.mjs --check                      # rc=0 (794/794 grandfathered, nada piorou)
node scripts/governance/requisitos-status.mjs Sells --check            # rc=1 (idem no base; 31/42 módulos, mesmo conjunto)
node scripts/memory-schemas/validate.mjs <60 SPEC/RUNBOOK/BRIEFING do lote>   # 58 OK · 2 FAIL herdados
```

Base foi materializada em worktree temporário (`git worktree add --detach <scratch>/base85 85e9cbf12f`, sem junctions) e removida ao fim; `governance/doc-id-index.json` foi regravado e restaurado com `git checkout --` (árvore limpa).

```json
{"itens_verificados": 1112, "erros_confirmados": 22, "error_rate_pct": 1.98, "pii_hits": 0, "veredito": "aprovado"}
```
