---
date: "2026-09-11"
hour: "17:40 BRT"
topic: "Refutação GT-G5 rodada 1 do lote PR #7224 (memory/requisitos reescrito por path) — REPROVADO: 189 erros em 1387 pares (13,63%); 3,46% mesmo sem contar path fabricado"
authors: ["C"]
outcomes:
  - "Lote de 337 arquivos M em memory/requisitos verificado a 100% (1387 pares -/+) contra a árvore real do HEAD e origin/main, com script próprio no scratch"
  - "4 pares apontam pro dono errado (git moveu pra cowork/Felipe/, o lote escreveu cowork/Wagner/) e 141 pares reescrevem path já morto em main pra path que nunca existiu (49 paths fabricados)"
  - "15 fatos datados falsificados (ex.: 'corrigido em 2026-09-09: apontava X' com X reescrito; 'pasta legado/ expurgada em 2026-06-23') e 28 hunks de reescrita de prosa fora do intent, incluindo uma varredura contada apagada em Ponto/espelho-show-gap.md"
  - "Refs podres em memory/requisitos: origin/main 165 → HEAD 171 (+6); 11 arquivos ganharam ref podre nova, 6 deles apontando pra prototipo-ui/design-docs/** que o próprio PR deletou"
  - "Máquinas derivadas: deadlink-gate, anchor-content-check, casos-coverage-guard, cowork-ssot-guard e runbook.schema todos rc=0; pii-scan rc=0 em 992 arquivos com controle positivo mordendo (rc=1)"
prs: [7224]
related_adrs:
  - "0397-prototipo-minimo-por-dono-e-ds-direto"
  - "0377-append-only-adr-excecao-por-label-emenda-0094"
---

# Refutação GT-G5 — PR #7224 · rodada 1 · veredito **REPROVADO**

> Protocolo: [`memory/requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md`](../requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md) §2–§4.
> Lote: PR #7224 *"refactor(prototipo): separar fontes por dono e remover paralelos"* (ADR 0397). Gerador: **Codex (OpenAI, externo)** → refutador Anthropic tier alto (§4.1). Tipo `anchors` → amostra **100%**.
> Refutador: claude-opus-5, subagente em contexto próprio, sessão fresca, instância nova. Não abriu `memory/sessions/*refutacao*` nem `memory/handoffs/2026-09-11-*`.
> Worktree: `claude/pr-7224-review-563828` · HEAD `bfd95ba952` · base `origin/main` `85e9cbf12f` · `git rev-parse --is-shallow-repository` = `false`.
> Nenhum arquivo do lote foi editado; nenhum commit/push/stash/checkout. Árvore limpa ao final (`git status --short` vazio).

## TL;DR

Refutação GT-G5 rodada 1 do lote `memory/requisitos/**` do PR #7224 (gerador Codex): **REPROVADO** — 189/1387 pares (13,63%): 49 paths fabricados (141 pares), 15 fatos datados falsificados, 28 hunks de prosa reescrita, 4 âncoras no dono errado, 1 sha divergente. PII 0 hits (controle positivo mordeu). O conserto é do gerador; re-verificação do lote inteiro na rodada 2.

## Checklist §3 do protocolo

- [x] Sessão fresca (sem nenhum contexto do gerador) — instância nova; proibição de abrir refutações anteriores cumprida
- [x] Modelo de tier SUPERIOR ao gerador — gerador externo (Codex) → refutador Anthropic `opus` (§4.1: ≥ opus)
- [x] Amostra: 100% anchors (1387 pares -/+ em 337 arquivos) — sem seleção aleatória, logo sem seed
- [x] Cada item verificado contra a árvore real (`git ls-files` HEAD · `git ls-tree origin/main` · `git diff -M --name-status`), não contra o texto do PR
- [x] Cada REFUTADO anotado com arquivo + linha + evidência (bloco `json` no fim; 97 entradas, sendo os 141 paths fabricados agregados em 49 linhas por path)
- [x] Scan PII no diff — controle positivo mordeu (rc=1), lote rc=0 em 992 arquivos, `pii_hits = 0`
- [x] `error_rate_pct` calculado: **13,63%** (189/1387) — **≥ 2 → reprovado**. Sem contar a classe B (path fabricado sobre ref já morta): **3,46%** (48/1387), ainda ≥ 2
- [ ] Entry no ledger — **não cabe ao refutador em lote reprovado** (§7: reprovado devolve `refutados[]` e PARA; conserto é do gerador/humano)

## 1. Universo

```
$ git diff --name-status origin/main...HEAD -- memory/requisitos | awk '{print substr($1,1,1)}' | sort | uniq -c
    337 M
```

**N = 337** arquivos, todos `M` (0 A, 0 D, 0 R em `memory/requisitos/**`). PR inteiro: 2099 entradas (`A 10 · D 504 · M 998 · R 587`). Labels do PR (`gh pr view 7224 --json labels`): `adr-metadata-normalization`, `adr-body-edit-W`.

## 2. Mapa de renames do git (oráculo)

```
$ git diff -M --name-status origin/main...HEAD | awk '$1 ~ /^R/' | wc -l
587
# similaridade: R100=428 · R096=19 · R098=17 · R097=17 · R099=14 · R095=14 · R092=10 · R094=9 · R093=7 · R086=5 · … (159 com conteúdo alterado no mesmo PR: +843/−856 linhas, todas da mesma classe de substituição de path — fora de memory/requisitos, NÃO refutadas item a item)
# padrões (dir antigo → dir novo · n):
  278 prototipo-ui/cowork            → prototipo-ui/cowork/Wagner
   75 prototipo-ui                   → memory/reference/prototipo-ui
   38 prototipo-ui/contrato          → governance/design/contracts
   23 prototipo-ui                   → scripts/design
   14 prototipo-ui/audit             → scripts/design/audit
   13 prototipo-ui/cowork/venda-v3   → prototipo-ui/cowork/Felipe/venda-v3
    7 prototipo-ui/alvos/roles       → governance/design/targets/roles
    2 prototipo-ui/cowork/produto-preco-especial → prototipo-ui/cowork/Felipe/produto-preco-especial
    2 prototipo-ui/prototipos/<x>    → prototipo-ui/cowork/Wagner/legado/<x>   (só 10 pastas: ds-v6, financeiro-assinatura-atualizar, financeiro-contador, financeiro-prova-viva, inventario-migracao, nfe-tributacao, nfse-emitir, payment-gateway-cnab, payment-gateway-ui, recurring-billing-planos, transaction-payment)
# deletados (não renomeados): prototipo-ui/design-docs/** (≈370), prototipo-ui/prototipos/{compras-grade-matrix,perfil}, prototipo-ui/_arquivo/ds/*, scripts/design-sync/mirror-snapshot/** (11)
```

Ponto que decide o lote: **o git NÃO renomeou `prototipo-ui/prototipos/{clientes,produto-cockpit,vendas-cockpit,os,sells-index,boletos,caixa-unificada,kb,chat,recurring,compras,producao-oficina,pageheader-canon-v3,sidebar-v3-unificado,…}`** — essas pastas já não existiam em `origin/main` (foram expurgadas em 2026-06-23, commits `e8b49f4b63`/`9da73296d3`). O lote reescreveu essas referências por prefixo mesmo assim.

## 3. Fidelidade de cada par -/+ (100% dos hunks)

Script no scratch (`verifica.py`, não no repo): parse de `git diff -U0 origin/main...HEAD -- memory/requisitos` → 1288 hunks → 1387 pares; tokenização `[A-Za-z0-9_./-]+`; `difflib.SequenceMatcher` por token; par "puro" = só opcodes `replace` de 1 token-path por 1 token-path; cada par (antigo→novo) classificado contra `git ls-files` (HEAD), `git ls-tree origin/main` e o mapa de renames.

```
files 337 hunks 1288 pairs 1387 pure_subst_ok 1326
 EXACT_RENAME (novo == rename-map[antigo])            1250
 DIR_RENAME  (dir antigo→dir novo consistente)          20
 NEW_EXISTS_UNMAPPED / NEW_PREEXISTING (existe; revisto) 71
 NEW_MISSING_old_existia  → classe A                     4
 NEW_MISSING_dead_to_dead → classe B                   141
 NOT_PURE + HUNK_LINECOUNT_DIFF (reescritas)            37  → 28 erro (D) + 7 normalização de frontmatter + 2 pii-allowlist (excluídos)
```

### Erros consolidados (por par, sem dupla contagem)

| Classe | n | O que é | Evidência-tipo |
|---|---|---|---|
| **A** path novo inexistente, antigo existia em main | **4** | git moveu para `cowork/Felipe/…`; o lote escreveu `cowork/Wagner/…` | `Produto/BRIEFING.md`, `Produto/PROTOTIPO-preco-especial.md` (`produto-preco-especial` → R100 para **Felipe**), `Sells/CreateV3-visual-comparison.md` (`venda-v3/sells-create.jsx` → R100 para **Felipe**; o `anchor-content-check` resolve o charter certo: `Sells/CreateV3 → Felipe/venda-v3/sells-create.jsx`), `_DesignSystem/INVENTARIO-ANCORAS-2026-09-09.md:130` (`inventario-migracao/visual-source.html` foi **D**, não R — só `index.html` sobreviveu como `legado/inventario-migracao/index.html`) |
| **B** path fabricado (antigo já morto em main) | **141** (49 paths distintos, 63 arquivos) | substituição por prefixo `prototipos/ → cowork/Wagner/legado/` (e 2× `cowork/Felipe/legado/`) sem nenhum rename no git; o novo nunca existiu | ex.: `prototipo-ui/cowork/Wagner/legado/clientes/HANDOFF_CLIENTES.md` (14 ocorrências), `…/legado/produto-cockpit` (11), `…/legado/os/cowork-app.jsx` (8), `…/legado/vendas-cockpit` (8). `git ls-files prototipo-ui/cowork/Wagner/legado/clientes` = 0; em main `prototipo-ui/prototipos/clientes` = 0 |
| **C** fato datado falsificado | **15** | a linha afirma estado histórico de um path e o path foi trocado pelo de hoje | ver §3.1 |
| **D** reescrita de prosa fora do intent | **28** hunks | não é substituição de path: apaga citações/links e uma medição contada | ver §3.2 |
| **E** path novo com conteúdo diferente | **1** | `mirror-snapshot/_ds_bundle.js` (blob `3aa50565ca52`) → `design-system/_ds_bundle.js` (blob HEAD `a63184bfeb1f`, modificado neste PR) numa linha que cita sha do bundle | `TeamMcp/forja-cockpit-visual-comparison.md:134` |

**itens_verificados = 1387 · erros_confirmados = 189 · error_rate_pct = 13,63.** Excluindo a classe B inteira (leitura mais benevolente possível, "dívida pré-existente"): 48/1387 = **3,46%** — o veredito não depende dessa escolha.

### 3.1 Classe C — fatos datados falsificados (os 15)

Regra violada: [ADR 0377](../decisions/0377-append-only-adr-excecao-por-label-emenda-0094.md) (*"libera mexer, não falsificar — ponteiro podre atualiza, fato datado preserva"*) + lápide §5 2026-08-12 (d)/(e) (*"substituição uniforme não distingue doc VIVO de registro DATADO"*).

```
$ sed -n 12p memory/requisitos/Estoque/_telas/RUNBOOK-stock-adjustment-create.md
# corrigido em 2026-09-09: apontava prototipo-ui/cowork/Wagner/legado/inventario-migracao/,
$ git show origin/main:memory/requisitos/Estoque/_telas/RUNBOOK-stock-adjustment-create.md | sed -n 12p
# corrigido em 2026-09-09: apontava prototipo-ui/prototipos/inventario-migracao/,
```
Em 2026-09-09 `prototipo-ui/cowork/Wagner/legado/` não existia (nasce neste PR). ×8 (4 RUNBOOK + 4 visual-comparison de `Estoque/_telas/`).

```
$ grep -n 'expurgada em 2026-06-23' memory/requisitos/_DesignSystem/pageheader-canon-v3.map.json memory/requisitos/_DesignSystem/sidebar-v3-unificado.map.json
…"(pasta prototipo-ui/cowork/Wagner/legado/ expurgada em 2026-06-23, …"
$ git show --name-status --format= 9da73296d3 | awk '{print $1,$2}' | sed 's#/[^/]*$##' | sort | uniq -c | sort -rn | head -4
    203 A prototipo-ui/cowork
     54 A prototipo-ui/cowork/prototipo-ui-patch
     24 A prototipo-ui/cowork/Unificado/_components
     20 D prototipo-ui/prototipos/clientes
```
O commit citado expurgou `prototipo-ui/prototipos/`; a pasta que o HEAD diz "expurgada" tem 19 arquivos vivos no HEAD. ×2 map.json + `pageheader-canon-v3-gap.md:4` (`o campo era …/legado/pageheader-canon-v3/`) + `sidebar-v3-unificado-gap.md` (`prototipo_nota` e `Elas medem contra …/legado/sidebar-v3-unificado/visual-source.html, apagado em 2026-06-23`).

Outros da classe C: `TeamMcp/forja-cockpit-visual-comparison.md` (`O charter apontava related_prototype: prototipo-ui/cowork/Wagner/forja-page.jsx` — nunca apontou); `_DesignSystem/INDEX-DESIGN-MEMORIAS.md` (`verificado 2026-07-06 … git show origin/main:prototipo-ui/cowork/Wagner/<arq>` — comando irreproduzível em julho); `Financeiro/RUNBOOK-prova-viva.md` + `prova-viva-visual-comparison.md` (`vive versionado em …/legado/financeiro-prova-viva/ desde 2026-09-01`); `Jana/Pro-visual-comparison.md:72` (`…/legado/ tem só compras-grade-matrix, inventario-migracao, perfil` — `git ls-files` mostra 11 subpastas e as duas citadas foram **deletadas** neste PR).

Contados na classe **B** (uma vez só), mas com a mesma falsificação de fato datado em cima do path fabricado: `Crm/clientes-gap.md` (linha `prototipo:` que dizia literalmente **"NÃO trocar pelo espelho de hoje sem reler"** e "consolidação SSOT em prototipo-ui/cowork/" — ambos os paths trocados); `Sells/RUNBOOK-show.md` (`# Corrigido 2026-07-28: …/legado/vendas-cockpit/ NÃO EXISTE no repo`); `Sells/index-r1-visual-comparison.md` (log `| 2026-05-17 | … Bundle copiado em …/legado/sells-index/ (2.8MB, 96 arquivos)`).

### 3.2 Classe D — reescrita de prosa fora do intent (28 hunks)

- **`Ponto/espelho-show-gap.md:29`** — apagada a **varredura contada** (`[Aa]nular em Pages/Ponto/** = 0`, `routes.php:39-41`, `rg AnularMarcacaoRequest = 10 hits todos em memory/**`, `rg -c "anula" MarcacaoService.php = 7`) e substituída por *"A regra existe no serviço, mas falta decisão para superfície e rota."* — o recibo que a §5 exige virou opinião. Nenhum path desta célula precisava mudar além do link no fim.
- **`_DesignSystem/adr/ui/0032-alvo-de-toque-erp-denso-1280.md`** (4 hunks) e **`0020`**, **`0027`** — corpo de ADR UI: links a `REPAIR-ONDAS-2026-09-04.md`/`PEDIDO-CODE.md` viraram *"evidência histórica de CRM (linha 213)"* (linha sem arquivo); em 0020 *"Gabarito canônico … fonte de TODOS os valores novos"* → *"Gabarito histórico aprovado … origem dos"* (troca o sentido da decisão). O label `adr-body-edit-W` libera **mexer**, não reescrever a decisão.
- **`Essentials/*-gap.md`** (13 hunks, `gerado_em: 2026-09-06`) — contratos de intake (`documentos/mensagens/lembretes/tarefas.contract.json`), charters e threads do playbook foram **deletados** pelo PR (status D, não R) e o gap doc ficou com *"foi removido com a árvore duplicada"*, sem apontar sucessor — o gap perdeu o contrato que o definia.
- **`AssetManagement/*`**, **`Essentials/RUNBOOK-{licencas,metas,tipos}.md`** — mesma forma (evidência apagada, não realocada).
- **`Compras/_telas/purchase-create-visual-comparison.md:69`** — *"Blueprint Cowork: prototipos/compras-grade-matrix/Compras - Grade Matrix.html"* (consolidado 2026-07-02) → `cowork/Wagner/compras-grade-matrix.jsx` + " consolidado em 2026-09-11": outro artefato (o HTML foi deletado; o `.jsx` pré-existia).

Excluídos do erro (registrados): 7 hunks de normalização de frontmatter (`status: active→ativo`, `owner`, `last_validated` quoted, `related_adrs` expandido em `Sells/RUNBOOK-{drafts,edit,quotations,subscriptions}.md`, `Financeiro/RUNBOOK-cobranca.md`, `Crm/_legado-fullpage/RUNBOOK-index-fullpage.md`) — oportunístico em arquivo já tocado (lápide §5 2026-07-12 permite); 2 inserções de `<!-- pii-allowlist … -->` em `Crm/cliente-drawer-760-visual-comparison.md:140` e `Sells/Sells-r4-…:54` sobre máscaras/CNPJ-exemplo sintético (conferido: são placeholders de formato).

## 4. Referências podres deixadas (mesmo medidor nos dois lados)

Medidor: `podres.py` — regex `(?<![/\w.\-])(prototipo-ui|scripts/design|governance/design|memory/reference/prototipo-ui)/[A-Za-z0-9_./-]+` em `memory/requisitos/**/*.{md,json,yml}` do ref, existência via `git ls-tree -r <ref>` (arquivo ou diretório), sufixos `:NN`/pontuação removidos, globs ignorados.

```
REF=origin/main docs=1369 refs_delimitadas=1452 ok=1287 podres=165 paths_podres_distintos=70 arquivos_com_podre=97
REF=HEAD        docs=1369 refs_delimitadas=1451 ok=1280 podres=171 paths_podres_distintos=75 arquivos_com_podre=106
delta = +6 podres · +9 arquivos com podre · 119 ocorrências podres NOVAS · 113 resolvidas (quase todas dead→dead, i.e. só trocaram de nome)
```

Podres **novas** que o lote criou apontando para o que o próprio PR deletou (`prototipo-ui/design-docs/**`, status D): `AssetManagement/RUNBOOK-alocacoes.md:171`, `RUNBOOK-bens.md:168`, `RUNBOOK-configuracoes.md:172`, `Jana/RUNBOOK-metas.md:386`, `Repair/DECISAO-W-portal-publico-2026-09-04.md:7` (**arquivo não tocado pelo PR** — ficou podre por deleção do alvo), `Repair/RUNBOOK-repair-settings.md:27`, `_DesignSystem/INVENTARIO-ANCORAS-2026-09-09.md:835`, `_Governanca/programa-ondas/onda-7-paridade-prototipo/7b-lote-crm-jana-forja.md:30`, `_processo/ONDAS-MWART-A-CRIAR.md:14`. Mais as 49 fabricadas da classe B e as 4 da classe A.

**O lote aumentou a dívida** (165 → 171); a dívida pré-existente (165) não é erro do lote e não entrou no numerador.

## 5. Máquinas derivadas (rc literal)

```
$ node scripts/governance/deadlink-gate.mjs --check            → rc=0  "OK — nenhum arquivo vivo piorou vs baseline (794/794 grandfathered)"
$ node scripts/governance/anchor-content-check.mjs --check     → rc=0  "⛔ podre 0 · 🟡 0 módulo: 5 · ✓ ok: 87" (92 charters)
$ node scripts/casos-coverage-guard.mjs                        → rc=0  "77 violações (telas 222, casos.md 157) · Sem violações novas DESTE PR (débito caiu −8)"
$ node scripts/governance/cowork-ssot-guard.mjs                → rc=0  "estrutura mínima e fonte única OK (Wagner + Felipe + design-system)"
$ git diff --name-only origin/main...HEAD > changed-files.txt && node scripts/memory-schemas/validate.mjs --schema scripts/memory-schemas/runbook.schema.json --glob 'memory/requisitos/**/RUNBOOK*.md'
                                                               → rc=0  (0 ::error; só ::warning "frontmatter ausente (legacy)" em 6 RUNBOOK do Repair)
$ rm changed-files.txt && git status --short                   → (vazio)
```

Leitura honesta: **as cinco máquinas ficam verdes num lote com 13,6% de erro** — o `deadlink-gate` só mede *piora vs baseline por arquivo* e as 141 fabricações trocam podre por podre no mesmo arquivo; nenhuma delas lê fato datado nem prosa. Verde de máquina aqui não é prova de fidelidade (LC-13 no eixo *gate*).

## 6. Scan PII com controle positivo

```
# controle positivo — arquivo FORA do repo (scratch), com um CPF sintético formatado NNN.NNN.NNN-NN
$ bash .github/scripts/pii-scan.sh -v <scratch>/pii-controle-positivo.md
::error::PII detectada (1 ocorrência(s) CPF/CNPJ literal).   … :1:contato do cliente: CPF [REDACTED-CPF] (sintetico)
controle positivo rc=1                                          ✓ o scanner morde

# lote — mesma lista do job governance-gate.yml "Run pii-scan.sh nos arquivos do PR"
$ git diff --name-only --diff-filter=AM origin/main...HEAD | grep -vE '^(vendor|node_modules|public|storage|bootstrap/cache)/' | grep -vE '\.(lock|min\.js|min\.css|map|svg|png|jpg|jpeg|gif|webp|pdf|zip)$' | grep -vE 'pii-scan\.sh$|pii-scan-allowlist\.txt$|governance-gate\.yml$' | grep -vE '^prototipo-ui/'
arquivos elegíveis: 992
$ bash .github/scripts/pii-scan.sh -v "${arquivos[@]}"
[pii-scan] ✅ Nenhuma PII literal detectada em 992 arquivo(s).
pii-scan lote rc=0                                              → pii_hits = 0
```
(O valor do CPF sintético não é reproduzido nesta evidência de propósito: o mesmo scanner reprovaria este arquivo.)

## 7. Veredito e o que devolver ao gerador

**REPROVADO** — `error_rate_pct = 13,63` (≥ 2). Três defeitos de mecanismo, não 189 acidentes:

1. **Substituição por prefixo sem oráculo** — o codemod aplicou `prototipos/ → cowork/Wagner/legado/` (e `cowork/ → cowork/Wagner/`) a TODA ocorrência, inclusive às 141 que já eram podres em main e às 4 cujo dono real é `cowork/Felipe/`. Conserto: só reescrever pares que estejam no `git diff -M --name-status` (rename-map); ref já morta fica como estava (é registro datado), ou vira TEXTO com nota de data.
2. **Não distinguiu doc vivo de registro datado** — 15 linhas com `apontava / expurgada em / NÃO EXISTE / copiado em / desde <data>` foram reescritas e agora afirmam o falso (lápide §5 2026-08-12; ADR 0377 "fato datado preserva"). Conserto: reverter essas 15 para o texto de main.
3. **Reescreveu prosa e apagou recibos** onde o alvo foi deletado (28 hunks) — em vez de citar o path histórico como texto datado, apagou a citação (e em `Ponto/espelho-show-gap.md` apagou uma medição contada inteira; em ADR UI-0020 trocou o sentido da decisão). Conserto: restaurar o texto de main e, se o link precisa morrer, `~~link~~ (removido em 2026-09-11, PR #7224)`.

Mais: 9 refs em `memory/requisitos/**` apontam para `prototipo-ui/design-docs/**` deletado pelo PR e não foram tocadas (§4). Re-verificação: lote inteiro de novo (§2.6 — erro sistemático de prompt).

## Recibo

```json
{
 "pr": 7224,
 "tipo": "anchors",
 "amostra_pct": 100,
 "itens_verificados": 1387,
 "erros_confirmados": 189,
 "error_rate_pct": 13.63,
 "pii_scan": true,
 "pii_hits": 0,
 "controle_positivo_pii": true,
 "refutados": [
  {
   "arquivo": "memory/requisitos/Produto/BRIEFING.md",
   "linha": 134,
   "item": "- [PROTOTIPO-preco-especial.md](PROTOTIPO-preco-especial.md) · [produto-preco-especial](../../../prototipo-ui/cowork/produto-preco-especial/",
   "categoria": "A_path_novo_inexistente_antigo_existia",
   "evidencia": "prototipo-ui/cowork/produto-preco-especial -> prototipo-ui/cowork/Wagner/produto-preco-especial (novo não está em git ls-files HEAD)"
  },
  {
   "arquivo": "memory/requisitos/Produto/PROTOTIPO-preco-especial.md",
   "linha": 169,
   "item": "cd prototipo-ui/cowork/produto-preco-especial",
   "categoria": "A_path_novo_inexistente_antigo_existia",
   "evidencia": "prototipo-ui/cowork/produto-preco-especial -> prototipo-ui/cowork/Wagner/produto-preco-especial (novo não está em git ls-files HEAD)"
  },
  {
   "arquivo": "memory/requisitos/Sells/CreateV3-visual-comparison.md",
   "linha": 15,
   "item": "> `prototipo-ui/cowork/venda-v3/sells-create.jsx`, e o drawer de item mora em",
   "categoria": "A_path_novo_inexistente_antigo_existia",
   "evidencia": "prototipo-ui/cowork/venda-v3/sells-create.jsx -> prototipo-ui/cowork/Wagner/venda-v3/sells-create.jsx (novo não está em git ls-files HEAD)"
  },
  {
   "arquivo": "memory/requisitos/_DesignSystem/INVENTARIO-ANCORAS-2026-09-09.md",
   "linha": 130,
   "item": "prototipo-ui/prototipos/inventario-migracao/visual-source.html",
   "categoria": "A_path_novo_inexistente_antigo_existia",
   "evidencia": "prototipo-ui/prototipos/inventario-migracao/visual-source.html -> prototipo-ui/cowork/Wagner/legado/inventario-migracao/visual-source.html (novo não está em git ls-files HEAD)"
  },
  {
   "arquivo": "memory/requisitos/Estoque/_telas/RUNBOOK-stock-adjustment-create.md",
   "linha": 12,
   "item": "# corrigido em 2026-09-09: apontava prototipo-ui/prototipos/inventario-migracao/,",
   "categoria": "C_fato_datado_falsificado",
   "evidencia": "linha afirma estado histórico de um path; o path histórico foi reescrito para um que não existia na data"
  },
  {
   "arquivo": "memory/requisitos/Estoque/_telas/RUNBOOK-stock-adjustment-index.md",
   "linha": 12,
   "item": "# corrigido em 2026-09-09: apontava prototipo-ui/prototipos/inventario-migracao/,",
   "categoria": "C_fato_datado_falsificado",
   "evidencia": "linha afirma estado histórico de um path; o path histórico foi reescrito para um que não existia na data"
  },
  {
   "arquivo": "memory/requisitos/Estoque/_telas/RUNBOOK-stock-transfer-create.md",
   "linha": 12,
   "item": "# corrigido em 2026-09-09: apontava prototipo-ui/prototipos/inventario-migracao/,",
   "categoria": "C_fato_datado_falsificado",
   "evidencia": "linha afirma estado histórico de um path; o path histórico foi reescrito para um que não existia na data"
  },
  {
   "arquivo": "memory/requisitos/Estoque/_telas/RUNBOOK-stock-transfer-index.md",
   "linha": 12,
   "item": "# corrigido em 2026-09-09: apontava prototipo-ui/prototipos/inventario-migracao/,",
   "categoria": "C_fato_datado_falsificado",
   "evidencia": "linha afirma estado histórico de um path; o path histórico foi reescrito para um que não existia na data"
  },
  {
   "arquivo": "memory/requisitos/Estoque/_telas/stock-adjustment-create-visual-comparison.md",
   "linha": 12,
   "item": "# corrigido em 2026-09-09: apontava prototipo-ui/prototipos/inventario-migracao/,",
   "categoria": "C_fato_datado_falsificado",
   "evidencia": "linha afirma estado histórico de um path; o path histórico foi reescrito para um que não existia na data"
  },
  {
   "arquivo": "memory/requisitos/Estoque/_telas/stock-adjustment-index-visual-comparison.md",
   "linha": 12,
   "item": "# corrigido em 2026-09-09: apontava prototipo-ui/prototipos/inventario-migracao/,",
   "categoria": "C_fato_datado_falsificado",
   "evidencia": "linha afirma estado histórico de um path; o path histórico foi reescrito para um que não existia na data"
  },
  {
   "arquivo": "memory/requisitos/Estoque/_telas/stock-transfer-create-visual-comparison.md",
   "linha": 12,
   "item": "# corrigido em 2026-09-09: apontava prototipo-ui/prototipos/inventario-migracao/,",
   "categoria": "C_fato_datado_falsificado",
   "evidencia": "linha afirma estado histórico de um path; o path histórico foi reescrito para um que não existia na data"
  },
  {
   "arquivo": "memory/requisitos/Estoque/_telas/stock-transfer-index-visual-comparison.md",
   "linha": 12,
   "item": "# corrigido em 2026-09-09: apontava prototipo-ui/prototipos/inventario-migracao/,",
   "categoria": "C_fato_datado_falsificado",
   "evidencia": "linha afirma estado histórico de um path; o path histórico foi reescrito para um que não existia na data"
  },
  {
   "arquivo": "memory/requisitos/Financeiro/RUNBOOK-prova-viva.md",
   "linha": 10,
   "item": "canonical_session: design-handoff Cowork \"Financeiro - Prova Viva (primitivos).html\" (chat46, 2026-06-07; o arquivo vive versionado em proto",
   "categoria": "C_fato_datado_falsificado",
   "evidencia": "linha afirma estado histórico de um path; o path histórico foi reescrito para um que não existia na data"
  },
  {
   "arquivo": "memory/requisitos/Financeiro/prova-viva-visual-comparison.md",
   "linha": 9,
   "item": "canon_reference: design-handoff Cowork \"Financeiro - Prova Viva (primitivos).html\" (chat46, aprovado no loop de design 2026-06-07; o arquivo",
   "categoria": "C_fato_datado_falsificado",
   "evidencia": "linha afirma estado histórico de um path; o path histórico foi reescrito para um que não existia na data"
  },
  {
   "arquivo": "memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md",
   "linha": 248,
   "item": "O charter apontava `related_prototype: prototipo-ui/cowork/forja-page.jsx`. **O markup da view",
   "categoria": "C_fato_datado_falsificado",
   "evidencia": "linha afirma estado histórico de um path; o path histórico foi reescrito para um que não existia na data"
  },
  {
   "arquivo": "memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md",
   "linha": 59,
   "item": "**O `prototipo-ui/cowork/` do repo é MIRROR do projeto 019dcfd3, em sincronia:** verificado 2026-07-06 — `financeiro-page.jsx` do repo é **i",
   "categoria": "C_fato_datado_falsificado",
   "evidencia": "linha afirma estado histórico de um path; o path histórico foi reescrito para um que não existia na data"
  },
  {
   "arquivo": "memory/requisitos/_DesignSystem/pageheader-canon-v3.map.json",
   "linha": 190,
   "item": "  \"_nota_mapeamento\": \"2026-09-06 [C]: FUNDAÇÃO/SHELL, não tela — o lado protótipo é TODO por desenho (pasta prototipo-ui/prototipos/ expurg",
   "categoria": "C_fato_datado_falsificado",
   "evidencia": "linha afirma estado histórico de um path; o path histórico foi reescrito para um que não existia na data"
  },
  {
   "arquivo": "memory/requisitos/_DesignSystem/sidebar-v3-unificado.map.json",
   "linha": 265,
   "item": "  \"_nota_mapeamento\": \"2026-09-06 [C]: FUNDAÇÃO/SHELL, não tela — o lado protótipo é TODO por desenho (pasta prototipo-ui/prototipos/ expurg",
   "categoria": "C_fato_datado_falsificado",
   "evidencia": "linha afirma estado histórico de um path; o path histórico foi reescrito para um que não existia na data"
  },
  {
   "arquivo": "memory/requisitos/AssetManagement/Index-visual-comparison.md",
   "linha": 235,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -3/+1: - Playbook: `prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/07-painel.md`",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -3/+1: - Playbook: `prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/07-painel.md` ·"
  },
  {
   "arquivo": "memory/requisitos/AssetManagement/RUNBOOK-manutencoes.md",
   "linha": 171,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1: - Playbook: [`_saida-06-manutencoes.md`](../../../prototipo-ui/design-docs/cowork-in",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1: - Playbook: [`_saida-06-manutencoes.md`](../../../prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/_saida-06-ma"
  },
  {
   "arquivo": "memory/requisitos/Compras/_telas/purchase-create-visual-comparison.md",
   "linha": 69,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1: > Consolidado P5 (2026-07-02): incorpora o gate visual do **modo grade** (antes em `",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1: > Consolidado P5 (2026-07-02): incorpora o gate visual do **modo grade** (antes em `Purchase/create-visual-comparison.md"
  },
  {
   "arquivo": "memory/requisitos/Essentials/RUNBOOK-licencas.md",
   "linha": 19,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -2/+1: > [`PEDIDO-CL-hrm.md`](../../../prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -2/+1: > [`PEDIDO-CL-hrm.md`](../../../prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md)."
  },
  {
   "arquivo": "memory/requisitos/Essentials/RUNBOOK-licencas.md",
   "linha": 22,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1: > a [emenda de 2026-09-05 do pedido](../../../prototipo-ui/design-docs/cowork-inbox/",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1: > a [emenda de 2026-09-05 do pedido](../../../prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md)"
  },
  {
   "arquivo": "memory/requisitos/Essentials/RUNBOOK-metas.md",
   "linha": 26,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -2/+1: > Onda 9 do [`EXPORT-HRM-2026-09-04`](../../../prototipo-ui/design-docs/cowork-inbox",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -2/+1: > Onda 9 do [`EXPORT-HRM-2026-09-04`](../../../prototipo-ui/design-docs/cowork-inbox/hrm/EXPORT-HRM-2026-09-04.md) ·"
  },
  {
   "arquivo": "memory/requisitos/Essentials/RUNBOOK-tipos.md",
   "linha": 26,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1: > do pedido [`PEDIDO-CL-hrm.md`](../../../prototipo-ui/design-docs/cowork-inbox/hrm/",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1: > do pedido [`PEDIDO-CL-hrm.md`](../../../prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md)."
  },
  {
   "arquivo": "memory/requisitos/Essentials/RUNBOOK-tipos.md",
   "linha": 148,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -2/+1: - [`PEDIDO-CL-hrm.md`](../../../prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -2/+1: - [`PEDIDO-CL-hrm.md`](../../../prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md) — HRM-O7 PR-9"
  },
  {
   "arquivo": "memory/requisitos/Essentials/documents-index-gap.md",
   "linha": 14,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -2/+1: > Contratos do intake: [`documentos.contract.json`](../../../prototipo-ui/design-doc",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -2/+1: > Contratos do intake: [`documentos.contract.json`](../../../prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/d"
  },
  {
   "arquivo": "memory/requisitos/Essentials/documents-index-gap.md",
   "linha": 20,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1: > `prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/`, **não** em `prototip",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1: > `prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/`, **não** em `prototipo-ui/contrato/`"
  },
  {
   "arquivo": "memory/requisitos/Essentials/holidays-index-gap.md",
   "linha": 13,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1: > Este gap executa a thread [`08-feriados-puxar.md`](../../../prototipo-ui/design-do",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1: > Este gap executa a thread [`08-feriados-puxar.md`](../../../prototipo-ui/design-docs/cowork-inbox/hrm/playbook/08-feri"
  },
  {
   "arquivo": "memory/requisitos/Essentials/knowledge-index-gap.md",
   "linha": 15,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1: > Charter: [`BaseConhecimento.charter.md`](../../../prototipo-ui/design-docs/cowork-",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1: > Charter: [`BaseConhecimento.charter.md`](../../../prototipo-ui/design-docs/cowork-inbox/essenciais/BaseConhecimento.ch"
  },
  {
   "arquivo": "memory/requisitos/Essentials/messages-index-gap.md",
   "linha": 13,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1: > Contrato: [`mensagens.contract.json`](../../../prototipo-ui/design-docs/cowork-inb",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1: > Contrato: [`mensagens.contract.json`](../../../prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/mensagens.con"
  },
  {
   "arquivo": "memory/requisitos/Essentials/messages-index-gap.md",
   "linha": 22,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1: > `prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/`, **não** em `prototip",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1: > `prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/`, **não** em `prototipo-ui/contrato/`"
  },
  {
   "arquivo": "memory/requisitos/Essentials/reminders-index-gap.md",
   "linha": 13,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1: > Contrato: [`lembretes.contract.json`](../../../prototipo-ui/design-docs/cowork-inb",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1: > Contrato: [`lembretes.contract.json`](../../../prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/lembretes.con"
  },
  {
   "arquivo": "memory/requisitos/Essentials/reminders-index-gap.md",
   "linha": 15,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1: > Charter: [`Lembretes.charter.md`](../../../prototipo-ui/design-docs/cowork-inbox/e",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1: > Charter: [`Lembretes.charter.md`](../../../prototipo-ui/design-docs/cowork-inbox/essenciais/Lembretes.charter.md) —"
  },
  {
   "arquivo": "memory/requisitos/Essentials/reminders-index-gap.md",
   "linha": 22,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1: > `prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/`, **não** em `prototip",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1: > `prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/`, **não** em `prototipo-ui/contrato/`"
  },
  {
   "arquivo": "memory/requisitos/Essentials/settings-index-gap.md",
   "linha": 24,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1: > Este gap executa a thread [`07-configuracoes-puxar.md`](../../../prototipo-ui/desi",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1: > Este gap executa a thread [`07-configuracoes-puxar.md`](../../../prototipo-ui/design-docs/cowork-inbox/hrm/playbook/07"
  },
  {
   "arquivo": "memory/requisitos/Essentials/tipos-gap.md",
   "linha": 18,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1: > Playbook: [`03-tipos-licenca.md`](../../../prototipo-ui/design-docs/cowork-inbox/h",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1: > Playbook: [`03-tipos-licenca.md`](../../../prototipo-ui/design-docs/cowork-inbox/hrm/playbook/03-tipos-licenca.md)."
  },
  {
   "arquivo": "memory/requisitos/Essentials/todo-index-gap.md",
   "linha": 14,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -2/+1: > Contrato do intake: [`cowork-inbox/essenciais/contrato/tarefas.contract.json`](../",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -2/+1: > Contrato do intake: [`cowork-inbox/essenciais/contrato/tarefas.contract.json`](../../../prototipo-ui/design-docs/cowor"
  },
  {
   "arquivo": "memory/requisitos/Essentials/todo-index-gap.md",
   "linha": 19,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1: > `prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/`, **não** em `prototip",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1: > `prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/`, **não** em `prototipo-ui/contrato/`"
  },
  {
   "arquivo": "memory/requisitos/Ponto/espelho-show-gap.md",
   "linha": 29,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1: | Anular marcação (append-only) | **Ausente na UI e SEM ROTA — a capacidade existe p",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1: | Anular marcação (append-only) | **Ausente na UI e SEM ROTA — a capacidade existe pela metade no backend.** Varredura c"
  },
  {
   "arquivo": "memory/requisitos/_DesignSystem/adr/ui/0020-dark-warm-ds-v6-tokens.md",
   "linha": 13,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1:   - Gabarito canônico: [`prototipo-ui/cowork/ds-v6/gabarito-vendas.html`](../../../.",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1:   - Gabarito canônico: [`prototipo-ui/cowork/ds-v6/gabarito-vendas.html`](../../../../../prototipo-ui/cowork/ds-v6/gabar"
  },
  {
   "arquivo": "memory/requisitos/_DesignSystem/adr/ui/0027-dark-hue-240-supersede-0020-0022.md",
   "linha": 92,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1: Quem abrir [`prototipo-ui/cowork/ds-v6/gabarito-vendas.html`](../../../../../prototi",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1: Quem abrir [`prototipo-ui/cowork/ds-v6/gabarito-vendas.html`](../../../../../prototipo-ui/cowork/ds-v6/gabarito-vendas.h"
  },
  {
   "arquivo": "memory/requisitos/_DesignSystem/adr/ui/0032-alvo-de-toque-erp-denso-1280.md",
   "linha": 11,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -3/+1: - **Fecha a pergunta aberta em**: [REPAIR-ONDAS-2026-09-04](../../../../../prototipo",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -3/+1: - **Fecha a pergunta aberta em**: [REPAIR-ONDAS-2026-09-04](../../../../../prototipo-ui/design-docs/cowork-inbox/REPAIR-"
  },
  {
   "arquivo": "memory/requisitos/_DesignSystem/adr/ui/0032-alvo-de-toque-erp-denso-1280.md",
   "linha": 138,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1:   pergunta está *\"aberta também na Forja\"* — [handoff-crm/PEDIDO-CODE](../../../../.",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1:   pergunta está *\"aberta também na Forja\"* — [handoff-crm/PEDIDO-CODE](../../../../../prototipo-ui/design-docs/handoff-c"
  },
  {
   "arquivo": "memory/requisitos/_DesignSystem/adr/ui/0032-alvo-de-toque-erp-denso-1280.md",
   "linha": 140,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1:   [REPAIR-ONDAS](../../../../../prototipo-ui/design-docs/cowork-inbox/REPAIR-ONDAS-2",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1:   [REPAIR-ONDAS](../../../../../prototipo-ui/design-docs/cowork-inbox/REPAIR-ONDAS-2026-09-04.md) (linha 283) —"
  },
  {
   "arquivo": "memory/requisitos/_DesignSystem/adr/ui/0032-alvo-de-toque-erp-denso-1280.md",
   "linha": 376,
   "item": "reescrita de prosa fora do intent (não é substituição de path): hunk -1/+1: [PEDIDO-CODE](../../../../../prototipo-ui/design-docs/handoff-crm/PEDIDO-CODE.md) (`",
   "categoria": "D_reescrita_prosa_fora_do_intent",
   "evidencia": "hunk -1/+1: [PEDIDO-CODE](../../../../../prototipo-ui/design-docs/handoff-crm/PEDIDO-CODE.md) (`:179`: svg do"
  },
  {
   "arquivo": "memory/requisitos/Jana/Pro-visual-comparison.md",
   "linha": 72,
   "item": "\"`prototipo-ui/cowork/Wagner/legado/` tem só `compras-grade-matrix`, `inventario-migracao`, `perfil`\"",
   "categoria": "C_fato_datado_falsificado",
   "evidencia": "git ls-files prototipo-ui/cowork/Wagner/legado → 11 subpastas (ds-v6, financeiro-*, inventario-migracao, nfe-tributacao, nfse-emitir, payment-gateway-*, recurring-billing-planos, transaction-payment); compras-grade-matrix e perfil foram DELETADOS neste PR (status D). A medição era sobre prototipo-ui"
  },
  {
   "arquivo": "memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md",
   "linha": 134,
   "item": "`prototipo-ui/design-system/_ds_bundle.js`, sha `9d2f6ce4…` = o do pacote de 24/08 (antes: scripts/design-sync/mirror-snapshot/_ds_bundle.js)",
   "categoria": "E_path_novo_conteudo_diferente",
   "evidencia": "blob main mirror-snapshot/_ds_bundle.js = 3aa50565ca52; blob HEAD design-system/_ds_bundle.js = a63184bfeb1f (o PR modificou o alvo: 2 +- ; 348172→348179 bytes). O sha citado na linha datada deixa de corresponder ao arquivo apontado."
  },
  {
   "arquivo": "memory/requisitos/Cliente/SDD-cadastro-cliente-v1.0.md",
   "linha": 204,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md -> prototipo-ui/cowork/Wagner/legado/clientes/HANDOFF_CLIENTES.md",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 14 ocorrência(s) em 9 arquivo(s): memory/requisitos/Cliente/SDD-cadastro-cliente-v1.0.md:204, memory/requisitos/Cliente/SPEC.md:357, memory/requisitos/Crm/HANDOFF-cliente-drawer-760-canon.md:11, memory/requisitos/Crm/RUNBOOK-Cliente-drawer-760px.md:41, memory/requisitos/Crm/_legado-fullpage/create-visual-comparison.md:142, memory/requisitos/Crm/_legado-fullpage/edit-visual-comparison.md:122"
  },
  {
   "arquivo": "memory/requisitos/Compras/CAPTERRA-DESIGN-FICHA.md",
   "linha": 9,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/cowork/compras-page -> prototipo-ui/cowork/Wagner/compras-page",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 2 ocorrência(s) em 2 arquivo(s): memory/requisitos/Compras/CAPTERRA-DESIGN-FICHA.md:9, memory/requisitos/Compras/SPEC.md:205"
  },
  {
   "arquivo": "memory/requisitos/Compras/_telas/RUNBOOK-purchase-create.md",
   "linha": 16,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/compras/visual-source.html -> prototipo-ui/cowork/Wagner/legado/compras/visual-source.html",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 5 ocorrência(s) em 5 arquivo(s): memory/requisitos/Compras/_telas/RUNBOOK-purchase-create.md:16, memory/requisitos/Compras/_telas/RUNBOOK-purchase-edit.md:15, memory/requisitos/Compras/_telas/index-visual-comparison.md:11, memory/requisitos/Compras/_telas/purchase-create-visual-comparison.md:12, memory/requisitos/Compras/_telas/purchase-edit-visual-comparison.md:12"
  },
  {
   "arquivo": "memory/requisitos/Compras/_telas/index-visual-comparison.md",
   "linha": 212,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "memory/prototipo-ui/prototipos/compras/visual-source.html -> memory/prototipo-ui/cowork/Wagner/legado/compras/visual-source.html",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/Compras/_telas/index-visual-comparison.md:212"
  },
  {
   "arquivo": "memory/requisitos/Crm/RUNBOOK-Cliente-drawer-760px.md",
   "linha": 23,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/clientes -> prototipo-ui/cowork/Wagner/legado/clientes",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 6 ocorrência(s) em 4 arquivo(s): memory/requisitos/Crm/RUNBOOK-Cliente-drawer-760px.md:23, memory/requisitos/Crm/_legado-fullpage/RUNBOOK-index-fullpage.md:116, memory/requisitos/Crm/cliente-drawer-760-visual-comparison.md:9, memory/requisitos/Crm/clientes-gap.md:5"
  },
  {
   "arquivo": "memory/requisitos/Crm/RUNBOOK-cliente-index.md",
   "linha": 14,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/clientes/cowork-app.jsx -> prototipo-ui/cowork/Wagner/legado/clientes/cowork-app.jsx",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 3 ocorrência(s) em 3 arquivo(s): memory/requisitos/Crm/RUNBOOK-cliente-index.md:14, memory/requisitos/Crm/_legado-fullpage/create-visual-comparison.md:21, memory/requisitos/Crm/cliente-index-visual-comparison.md:10"
  },
  {
   "arquivo": "memory/requisitos/Crm/cliente-drawer-760-visual-comparison.md",
   "linha": 95,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/clientes/clientes-icons.jsx -> prototipo-ui/cowork/Wagner/legado/clientes/clientes-icons.jsx",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/Crm/cliente-drawer-760-visual-comparison.md:95"
  },
  {
   "arquivo": "memory/requisitos/Crm/cliente-drawer-760-visual-comparison.md",
   "linha": 242,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/clientes/Oimpresso -> prototipo-ui/cowork/Wagner/legado/clientes/Oimpresso",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/Crm/cliente-drawer-760-visual-comparison.md:242"
  },
  {
   "arquivo": "memory/requisitos/Financeiro/RUNBOOK-cobranca.md",
   "linha": 135,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/payment-gateway-ui/critiques/REPORT.md -> prototipo-ui/cowork/Wagner/legado/payment-gateway-ui/critiques/REPORT.md",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/Financeiro/RUNBOOK-cobranca.md:135"
  },
  {
   "arquivo": "memory/requisitos/Financeiro/SPEC.md",
   "linha": 846,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/boletos/cowork-app.jsx -> prototipo-ui/cowork/Wagner/legado/boletos/cowork-app.jsx",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 4 ocorrência(s) em 2 arquivo(s): memory/requisitos/Financeiro/SPEC.md:846, memory/requisitos/Financeiro/boletos-visual-comparison.md:9"
  },
  {
   "arquivo": "memory/requisitos/Financeiro/fluxo-visual-comparison.md",
   "linha": 9,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/financeiro-fluxo/page.tsx -> prototipo-ui/cowork/Wagner/legado/financeiro-fluxo/page.tsx",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 3 ocorrência(s) em 1 arquivo(s): memory/requisitos/Financeiro/fluxo-visual-comparison.md:9"
  },
  {
   "arquivo": "memory/requisitos/Jana/AUDITORIA-reconciliacao-tripla-analise-por-setor-2026-06-22.md",
   "linha": 210,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/audit/reports/Produto__Index.design-report.json -> scripts/design/audit/reports/Produto__Index.design-report.json",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/Jana/AUDITORIA-reconciliacao-tripla-analise-por-setor-2026-06-22.md:210"
  },
  {
   "arquivo": "memory/requisitos/Jana/PARIDADE-area-jana-diagnostico-e-ondas.md",
   "linha": 206,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/cowork/_ds/office-impresso-design-system-019dd02f -> prototipo-ui/cowork/Wagner/_ds/office-impresso-design-system-019dd02f",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/Jana/PARIDADE-area-jana-diagnostico-e-ondas.md:206"
  },
  {
   "arquivo": "memory/requisitos/Jana/PARIDADE-area-jana-diagnostico-e-ondas.md",
   "linha": 231,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/cowork/_ds -> prototipo-ui/cowork/Wagner/_ds",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/Jana/PARIDADE-area-jana-diagnostico-e-ondas.md:231"
  },
  {
   "arquivo": "memory/requisitos/Jana/SPEC.md",
   "linha": 937,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/chat/cowork-app-v2.jsx -> prototipo-ui/cowork/Wagner/legado/chat/cowork-app-v2.jsx",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/Jana/SPEC.md:937"
  },
  {
   "arquivo": "memory/requisitos/KB/CAPTERRA-FICHA.md",
   "linha": 8,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/kb/Bench -> prototipo-ui/cowork/Wagner/legado/kb/Bench",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 3 ocorrência(s) em 1 arquivo(s): memory/requisitos/KB/CAPTERRA-FICHA.md:8"
  },
  {
   "arquivo": "memory/requisitos/KB/CHANGELOG.md",
   "linha": 141,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/kb -> prototipo-ui/cowork/Wagner/legado/kb",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/KB/CHANGELOG.md:141"
  },
  {
   "arquivo": "memory/requisitos/OficinaAuto/oficina-os-nova-prototipo-visual-comparison.md",
   "linha": 105,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/alvos/roles/OficinaAuto--ServiceOrders--Show.json -> governance/design/targets/roles/OficinaAuto--ServiceOrders--Show.json",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/OficinaAuto/oficina-os-nova-prototipo-visual-comparison.md:105"
  },
  {
   "arquivo": "memory/requisitos/OficinaAuto/producao-oficina-cacamba-visual-comparison.md",
   "linha": 9,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/producao-oficina/F1.html -> prototipo-ui/cowork/Wagner/legado/producao-oficina/F1.html",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 4 ocorrência(s) em 2 arquivo(s): memory/requisitos/OficinaAuto/producao-oficina-cacamba-visual-comparison.md:9, memory/requisitos/Repair/jobsheet-visual-comparison.md:14"
  },
  {
   "arquivo": "memory/requisitos/OficinaAuto/producao-oficina-cacamba-visual-comparison.md",
   "linha": 10,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/producao-oficina/visual-source.html -> prototipo-ui/cowork/Wagner/legado/producao-oficina/visual-source.html",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 3 ocorrência(s) em 1 arquivo(s): memory/requisitos/OficinaAuto/producao-oficina-cacamba-visual-comparison.md:10"
  },
  {
   "arquivo": "memory/requisitos/PaymentGateway/RUNBOOK-settings-gateways.md",
   "linha": 103,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/payment-gateway-ui/components/pg-payment-gateways-page.jsx -> prototipo-ui/cowork/Wagner/legado/payment-gateway-ui/components/pg-payment-gateways-page.jsx",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/PaymentGateway/RUNBOOK-settings-gateways.md:103"
  },
  {
   "arquivo": "memory/requisitos/Produto/BRIEFING.md",
   "linha": 134,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/cowork/prototipo-ui-patch/prototipos/produto -> prototipo-ui/cowork/Wagner/prototipo-ui-patch/prototipos/produto",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/Produto/BRIEFING.md:134"
  },
  {
   "arquivo": "memory/requisitos/Produto/RUNBOOK-unificado.md",
   "linha": 32,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/cowork/prototipo-ui-patch/prototipos/produto/produto-app.jsx -> prototipo-ui/cowork/Wagner/prototipo-ui-patch/prototipos/produto/produto-app.jsx",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/Produto/RUNBOOK-unificado.md:32"
  },
  {
   "arquivo": "memory/requisitos/Produto/_telas/RUNBOOK-produto-create.md",
   "linha": 19,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/produto-cockpit -> prototipo-ui/cowork/Wagner/legado/produto-cockpit",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 11 ocorrência(s) em 11 arquivo(s): memory/requisitos/Produto/_telas/RUNBOOK-produto-create.md:19, memory/requisitos/Produto/_telas/RUNBOOK-produto-index.md:19, memory/requisitos/Produto/_telas/RUNBOOK-produto-show.md:18, memory/requisitos/Produto/_telas/produto-bulk-edit-visual-comparison.md:15, memory/requisitos/Produto/_telas/produto-create-visual-comparison.md:14, memory/requisitos/Produto/_telas/produto-edit-visual-comparison.md:14"
  },
  {
   "arquivo": "memory/requisitos/Produto/_telas/RUNBOOK-produto-create.md",
   "linha": 19,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "memory/prototipo-ui/prototipos/produto-cockpit -> memory/prototipo-ui/cowork/Wagner/legado/produto-cockpit",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 5 ocorrência(s) em 4 arquivo(s): memory/requisitos/Produto/_telas/RUNBOOK-produto-create.md:19, memory/requisitos/Produto/_telas/RUNBOOK-produto-edit.md:17, memory/requisitos/Produto/_telas/RUNBOOK-produto-index.md:19, memory/requisitos/Produto/_telas/RUNBOOK-produto-show.md:18"
  },
  {
   "arquivo": "memory/requisitos/Produto/_telas/RUNBOOK-produto-index.md",
   "linha": 134,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "memory/prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx -> memory/prototipo-ui/cowork/Wagner/legado/produto-cockpit/produto-cockpit-page.jsx",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 2 ocorrência(s) em 2 arquivo(s): memory/requisitos/Produto/_telas/RUNBOOK-produto-index.md:134, memory/requisitos/Produto/_telas/produto-index-visual-comparison.md:24"
  },
  {
   "arquivo": "memory/requisitos/Produto/_telas/produto-bulk-edit-visual-comparison.md",
   "linha": 9,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx -> prototipo-ui/cowork/Wagner/legado/produto-cockpit/produto-cockpit-page.jsx",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 7 ocorrência(s) em 7 arquivo(s): memory/requisitos/Produto/_telas/produto-bulk-edit-visual-comparison.md:9, memory/requisitos/Produto/_telas/produto-create-visual-comparison.md:9, memory/requisitos/Produto/_telas/produto-edit-visual-comparison.md:9, memory/requisitos/Produto/_telas/produto-index-visual-comparison.md:9, memory/requisitos/Produto/_telas/produto-selling-prices-visual-comparison.md:9, memory/requisitos/Produto/_telas/produto-show-visual-comparison.md:9"
  },
  {
   "arquivo": "memory/requisitos/Produto/_telas/produto-index-setor-matrix.md",
   "linha": 25,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/produto -> prototipo-ui/cowork/Wagner/legado/produto",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/Produto/_telas/produto-index-setor-matrix.md:25"
  },
  {
   "arquivo": "memory/requisitos/Produto/adr/arq/0001-selling-price-multiplier.md",
   "linha": 12,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/produto-unificado -> prototipo-ui/cowork/Felipe/legado/produto-unificado",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 3 ocorrência(s) em 1 arquivo(s): memory/requisitos/Produto/adr/arq/0001-selling-price-multiplier.md:12"
  },
  {
   "arquivo": "memory/requisitos/RecurringBilling/Index-visual-comparison.md",
   "linha": 9,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/recurring/recurring-page.jsx -> prototipo-ui/cowork/Wagner/legado/recurring/recurring-page.jsx",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 3 ocorrência(s) em 2 arquivo(s): memory/requisitos/RecurringBilling/Index-visual-comparison.md:9, memory/requisitos/RecurringBilling/cobranca-recorrente-configuracoes-gap.md:26"
  },
  {
   "arquivo": "memory/requisitos/RecurringBilling/Index-visual-comparison.md",
   "linha": 9,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/recurring/recurring-data.jsx -> prototipo-ui/cowork/Wagner/legado/recurring/recurring-data.jsx",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/RecurringBilling/Index-visual-comparison.md:9"
  },
  {
   "arquivo": "memory/requisitos/RecurringBilling/Index-visual-comparison.md",
   "linha": 9,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/recurring/recurring-icons.jsx -> prototipo-ui/cowork/Wagner/legado/recurring/recurring-icons.jsx",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/RecurringBilling/Index-visual-comparison.md:9"
  },
  {
   "arquivo": "memory/requisitos/RecurringBilling/Index-visual-comparison.md",
   "linha": 133,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/recurring -> prototipo-ui/cowork/Wagner/legado/recurring",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 2 ocorrência(s) em 2 arquivo(s): memory/requisitos/RecurringBilling/Index-visual-comparison.md:133, memory/requisitos/RecurringBilling/cobranca-recorrente-configuracoes-gap.md:41"
  },
  {
   "arquivo": "memory/requisitos/Repair/RUNBOOK-jobsheet-create.md",
   "linha": 16,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/os/cowork-app.jsx -> prototipo-ui/cowork/Wagner/legado/os/cowork-app.jsx",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 8 ocorrência(s) em 8 arquivo(s): memory/requisitos/Repair/RUNBOOK-jobsheet-create.md:16, memory/requisitos/Repair/RUNBOOK-jobsheet-edit.md:16, memory/requisitos/Repair/RUNBOOK-jobsheet-index.md:18, memory/requisitos/Repair/RUNBOOK-jobsheet-show.md:18, memory/requisitos/Repair/RUNBOOK-repair-index.md:18, memory/requisitos/Repair/RUNBOOK-repair-show.md:16"
  },
  {
   "arquivo": "memory/requisitos/Sells/RUNBOOK-drafts.md",
   "linha": 12,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/vendas-cockpit -> prototipo-ui/cowork/Wagner/legado/vendas-cockpit",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 8 ocorrência(s) em 7 arquivo(s): memory/requisitos/Sells/RUNBOOK-drafts.md:12, memory/requisitos/Sells/RUNBOOK-edit.md:12, memory/requisitos/Sells/RUNBOOK-quotations.md:12, memory/requisitos/Sells/RUNBOOK-show.md:17, memory/requisitos/Sells/RUNBOOK-subscriptions.md:12, memory/requisitos/Sells/edit-visual-comparison.md:47"
  },
  {
   "arquivo": "memory/requisitos/Sells/index-r1-visual-comparison.md",
   "linha": 9,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/sells-index/vendas-page.jsx -> prototipo-ui/cowork/Wagner/legado/sells-index/vendas-page.jsx",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/Sells/index-r1-visual-comparison.md:9"
  },
  {
   "arquivo": "memory/requisitos/Sells/index-r1-visual-comparison.md",
   "linha": 13,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/sells-index/Oimpresso -> prototipo-ui/cowork/Wagner/legado/sells-index/Oimpresso",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 3 ocorrência(s) em 1 arquivo(s): memory/requisitos/Sells/index-r1-visual-comparison.md:13"
  },
  {
   "arquivo": "memory/requisitos/Sells/index-r1-visual-comparison.md",
   "linha": 57,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/sells-index -> prototipo-ui/cowork/Wagner/legado/sells-index",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 3 ocorrência(s) em 1 arquivo(s): memory/requisitos/Sells/index-r1-visual-comparison.md:57"
  },
  {
   "arquivo": "memory/requisitos/Sells/show-visual-comparison.md",
   "linha": 19,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/vendas-cockpit/Vendas -> prototipo-ui/cowork/Wagner/legado/vendas-cockpit/Vendas",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/Sells/show-visual-comparison.md:19"
  },
  {
   "arquivo": "memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md",
   "linha": 134,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/contrato/forja -> governance/design/contracts/forja",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md:134"
  },
  {
   "arquivo": "memory/requisitos/Whatsapp/CaixaUnificadaV4-visual-comparison.md",
   "linha": 11,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx -> prototipo-ui/cowork/Wagner/legado/caixa-unificada/inbox-page.jsx",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 5 ocorrência(s) em 2 arquivo(s): memory/requisitos/Whatsapp/CaixaUnificadaV4-visual-comparison.md:11, memory/requisitos/Whatsapp/SPEC.md:1789"
  },
  {
   "arquivo": "memory/requisitos/_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md",
   "linha": 38,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/financeiro- -> prototipo-ui/cowork/Wagner/legado/financeiro-",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 2 ocorrência(s) em 1 arquivo(s): memory/requisitos/_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md:38"
  },
  {
   "arquivo": "memory/requisitos/_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md",
   "linha": 61,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/sells-create -> prototipo-ui/cowork/Felipe/legado/sells-create",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 2 ocorrência(s) em 1 arquivo(s): memory/requisitos/_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md:61"
  },
  {
   "arquivo": "memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md",
   "linha": 21,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "memory/prototipo-ui/PROTOCOL.md -> memory/memory/reference/prototipo-ui/PROTOCOL.md",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 2 ocorrência(s) em 1 arquivo(s): memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md:21"
  },
  {
   "arquivo": "memory/requisitos/_DesignSystem/adr/ui/0015-padrao-cowork-default-forms.md",
   "linha": 18,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/clientes/clientes.css -> prototipo-ui/cowork/Wagner/legado/clientes/clientes.css",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/_DesignSystem/adr/ui/0015-padrao-cowork-default-forms.md:18"
  },
  {
   "arquivo": "memory/requisitos/_DesignSystem/adr/ui/0015-padrao-cowork-default-forms.md",
   "linha": 62,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/clientes/cockpit.css -> prototipo-ui/cowork/Wagner/legado/clientes/cockpit.css",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 1 ocorrência(s) em 1 arquivo(s): memory/requisitos/_DesignSystem/adr/ui/0015-padrao-cowork-default-forms.md:62"
  },
  {
   "arquivo": "memory/requisitos/_DesignSystem/adr/ui/0017-design-system-v3-reconciliacao-ui-v2.md",
   "linha": 19,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "memory/prototipo-ui/CODE_DESIGN_CONTRACT.md -> memory/memory/reference/prototipo-ui/CODE_DESIGN_CONTRACT.md",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 2 ocorrência(s) em 1 arquivo(s): memory/requisitos/_DesignSystem/adr/ui/0017-design-system-v3-reconciliacao-ui-v2.md:19"
  },
  {
   "arquivo": "memory/requisitos/_DesignSystem/pageheader-canon-v3-gap.md",
   "linha": 4,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/pageheader-canon-v3 -> prototipo-ui/cowork/Wagner/legado/pageheader-canon-v3",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 3 ocorrência(s) em 2 arquivo(s): memory/requisitos/_DesignSystem/pageheader-canon-v3-gap.md:4, memory/requisitos/_DesignSystem/templates/PageHeader-canon-v3-1.md:10"
  },
  {
   "arquivo": "memory/requisitos/_DesignSystem/sidebar-v3-unificado-gap.md",
   "linha": 6,
   "categoria": "B_path_fabricado_antigo_ja_morto",
   "item": "prototipo-ui/prototipos/sidebar-v3-unificado/visual-source.html -> prototipo-ui/cowork/Wagner/legado/sidebar-v3-unificado/visual-source.html",
   "evidencia": "path novo NÃO existe no HEAD (git ls-files) e o antigo já não existia em origin/main — substituição por prefixo sem oráculo git; 2 ocorrência(s) em 1 arquivo(s): memory/requisitos/_DesignSystem/sidebar-v3-unificado-gap.md:6"
  }
 ],
 "veredito": "reprovado",
 "evidencia": "memory/sessions/2026-09-11-refutacao-gt-g5-lote-7224-r1.md",
 "refutador": "claude-opus-5 (Anthropic) — subagente em contexto próprio, sessão fresca, instância nova (rodada 1)",
 "sessao_fresca": true
}
```
