---
date: "2026-09-15"
topic: "Refutação GT-G5 r3 do lote #7335 (55 arquivos memory/requisitos, ponteiros podres prototipo-ui/prototipos → anotação de remoção ou re-apontamento pra cowork/Wagner): 270 itens, 4 erros, 1,48% — aprovado com 4 refutados a consertar"
authors: ["C"]
prs: [7335]
outcomes:
  - "270 itens verificados contra origin/main (fdec77f6d3): 98 âncoras de path, 38 leitor-real/charter, 36 prosa×código, 70 integridade de célula/nota, 21 máquinas, 7 padrões PII — 4 REFUTADOS, error_rate 1,48% (< 2%), PII 0 hits com 7/7 controles positivos"
  - "Refutados: nota de remoção pendurada no bullet inteiro (Cliente/SDD:204, absolve o bundle_source vivo); nota apensada na célula AÇÃO do gap RB (map.json derivado diverge do gap ao regenerar); visual-source-fsm-v1.html omitido como vivo na mesma frase que anota Vendas Cockpit.html (Sells/show-vc:19); '(802 LOC)' re-ancorado em inbox-page.jsx que tem 1487 linhas (Whatsapp vc:243)"
  - "Observação sistêmica não contada: canon_reference com comentário YAML '# removido…' em 9 visual-comparison é YAML válido mas o leitor real (ancora.mjs::chaveAninhada) não strippa '#' e imprime o comentário dentro do valor — veredito não muda (ehCodigoDoRepo inalterado), precedente já em origin/main (Produto/Index.charter.md:6)"
---

# Session log 2026-09-15 — Refutação GT-G5 · lote #7335 · rodada r3

## TL;DR

**Veredito: aprovado por taxa** — 270 itens verificados contra `origin/main`, **4 erros confirmados**, error_rate **1,48%** (< 2%), **PII 0 hits** (7/7 controles positivos casaram). Os 4 refutados são reais e devem ser consertados antes do merge (2 notas no lugar errado, 1 omissão de arquivo morto na mesma frase, 1 número de LOC re-ancorado sem medir); nenhum path novo está morto e todos os 4 SHAs/datas de remoção citados conferem no histórico.

## Cabeçalho da rodada

| Campo | Valor |
|---|---|
| PR / rodada | #7335 · r3 |
| HEAD medido | `4199bf29ce130933d792335ed366a5aaf2cdb8f8` (branch `claude/ponteiros-podres-61`) |
| Base | `origin/main` = `fdec77f6d3db6f62266bf3149069d27f3b81ab03` (1 commit à frente do `a75f571ecb` citado no mandato; o commit extra só toca `memory/LICOES_CODE.md`, fora do lote) · merge-base `401344490d4` |
| Repo raso? | **false** (`git rev-parse --is-shallow-repository`) — datas de `git log` valem como recibo |
| Sessão fresca | sim — instância nova, sem contexto do gerador nem das rodadas r1/r2 (os arquivos `…-r1.md`/`…-r2.md` estão no diff do branch e **não foram abertos**; nenhum `memory/handoffs/` de hoje aberto; corpo do PR/commit não usado como evidência) |
| Tipo do lote | anchors → amostra **100%** (todo path, toda chave de frontmatter, toda nota, todo US/charter citado) |
| Modelo | Fable 5.1 (tier máximo disponível) |

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (Fable 5.1 = tier máximo; igualdade só admitida nesse caso)
- [x] Amostra: 100% anchors (lote é `anchors`; sem prosa destilada amostrada por seed)
- [x] Cada item verificado contra origin/main (`git ls-tree`, `git show`, `git log --diff-filter`, `git grep <ref>`), não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha/commit + porquê)
- [x] Scan PII no diff (7 padrões × controle positivo) — 0 hits
- [x] `error_rate_pct` calculado e < 2 (1,48)
- [ ] Entry no ledger — **não escrita por mim** (mandato: não escrever no ledger; o workflow faz)

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` → **55 arquivos**, todos `M`; `--stat`: 92 inserções / 90 deleções (diff `-U0`: 91 linhas `+`, 66 linhas `-`). Dois tipos de edição: (a) anotar path morto com `(removido em <data>, <sha>)` — 68 linhas `+` carregam a nota (70 ocorrências); (b) re-apontar pra `prototipo-ui/cowork/Wagner/{clientes,os,vendas,inbox,pg-payment-gateways}-page.jsx`. Fora de `memory/requisitos` o branch toca `governance/deadlink-baseline.json`, `governance/sdd-scorecard-baseline.json` e as evidências r1/r2.

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---|---|---|
| 1 · Âncora existe/não existe em origin/main (+ SHA/data da remoção) | 98 | 98 | 0 | `git ls-tree origin/main -- <path>` pros 6 paths novos (todos blob) e 34 paths/dirs velhos (todos vazios; controle negativo `prototipo-ui/nao-existe-xyz.jsx`=0, positivo `CLAUDE.md`=1); `git log origin/main --diff-filter=D/A --date=short --format=%h(a%ad/c%cd)` por path (author=committer em todos); dir-level `git ls-tree -r <sha>^` vs `<sha>` (ex.: `prototipos/clientes` 22→0 em 9da73296d34; `sells-index` 96→0 em 1070e3759b7; `pageheader-canon-v3` 7→0; `inventario-migracao` 3→0 em 4f51a9ec781); os 4 SHAs são ancestrais de origin/main |
| 2 · Não revogada + lida pelo leitor real | 38 | 38 | 0 | `node scripts/design/ancora.mjs <tela> --staging prototipo-ui/cowork/Wagner` em 10 telas ligadas (Cliente/Index, Financeiro/Fluxo/Index, Repair/JobSheet/Index, Repair/Show, Sells/Show, Sells/Edit, Sells/Index, RecurringBilling/Index, Produto/Index, Atendimento/CaixaUnificada) — nenhuma REVOGADA/MIS-ANCHOR; 28 chaves de frontmatter tocadas (9 `canon_reference`, 11 `blueprint_cowork`, `visual_source`, `prototype_source`, `visual_source_html`, `prototipo_nota`, 2 `canon_reference_v*`, 2 `fonte`) cruzadas com os leitores (`ancora.mjs::chaveAninhada`, `charter-blueprint-pointers.mjs`/`reconcile-triplet.mjs` — só varrem `*.charter.md`); js-yaml parseia os 48 frontmatters sem erro e o valor YAML não contém o comentário |
| 3 · Ação × veredito da prosa; afirmação sobre código | 36 | 34 | **2** | Charters em origin/main (Cliente/Index `related_prototype`/`bundle_source` ✓; Produto/Index `n/a (herda PT-01 Lista; segue o Padrão de Tela)` ✓; CaixaUnificada `visual_source: …/inbox-page.jsx` ✓); linhagem dos 5 `-page.jsx` (cabeçalho do novo == cabeçalho do antigo: `os-page.jsx`≡`prototipos/os/cowork-app.jsx`, `clientes-page.jsx`≡`prototipos/clientes/cowork-app.jsx`, `vendas-page.jsx`≡`vendas-cockpit/cowork-app.jsx` e `sells-index/vendas-page.jsx`, `inbox-page.jsx`≡`caixa-unificada/inbox-page.jsx`; `pg-payment-gateways-page.jsx` mesmo nome via `--follow` até cf73c71bfaa); `wc -l` do alvo; `git ls-tree -r origin/main` por nome |
| 4 · Célula/nota íntegra; map.json ≡ tabela | 70 | 68 | **2** | Referente de cada uma das 68 notas `(removido…)` lido no contexto da linha; contagem de pipes da linha 26 do gap RB (4 = header); `node scripts/design/gerar-map.mjs <gap.md>` (stdout) × `cobranca-recorrente-configuracoes.map.json` commitado, diff parte-a-parte por `id` |
| 5 · Máquina derivada | 21 | 21 | 0 | rc literal abaixo; drifts encontrados são pré-existentes em origin/main (provado por `git grep` do conteúdo drifado em main) |
| 6 · PII (7 padrões × controle positivo) | 7 | 7 | 0 | script sobre as 91 linhas `+`; 0 hits; 7/7 controles casaram |
| **Total** | **270** | **266** | **4** | **error_rate = 4/270 = 1,48%** |

## REFUTADOS

### R1 · `memory/requisitos/Cliente/SDD-cadastro-cliente-v1.0.md:204` — nota pendurada no bullet inteiro absolve o `bundle_source` vivo

- **Afirmação do lote (linha 204):** `- **Protótipo âncora:** \`bundle_source: clientes-page.jsx\` (declarado no \`Index.charter.md\`); handoff em \`prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md\`. _(removido em 2026-06-23, 9da73296d34)_`
- **O que origin/main diz:** o bullet carrega DOIS ponteiros. `prototipo-ui/cowork/Wagner/clientes-page.jsx` **existe** (`git ls-tree origin/main` → blob `bd2006c897d`) e é o `related_prototype` do charter (`resources/js/Pages/Cliente/Index.charter.md:5-6`); só o HANDOFF morreu (D em 9da73296d34).
- **Por que é erro do lote:** a nota está **fora da sentença** (depois do ponto final), então se prende ao bullet "Protótipo âncora" inteiro — pela doutrina do próprio repo (§5 2026-09-08: *o marcador vale pra sentença que o carrega, nunca pro parágrafo*) ela afirma que o protótipo-âncora foi removido, o que é falso. O mesmo lote sabe fazer certo: em `Cliente/SPEC.md:357` a nota foi posta **inline, antes do ponto**, colada só ao HANDOFF. Conserto: mover a nota pra dentro da sentença do HANDOFF.

### R2 · `memory/requisitos/RecurringBilling/cobranca-recorrente-configuracoes-gap.md:26` — nota apensada na célula **Ação**; o `map.json` derivado passa a divergir do gap

- **Afirmação do lote:** linha "Linguagem visual" da tabela de partes ganha `_(removido em 2026-05-20, 1070e3759b7)_` **no fim da 3ª célula (Ação)**: `…não deste gap _(removido em 2026-05-20, 1070e3759b7)_ |`. O path morto (`prototipo-ui/prototipos/recurring/recurring-page.jsx`) está na 2ª célula (Achado), não na Ação.
- **O que origin/main diz:** a tabela de partes do gap é a **fonte** do `memory/requisitos/RecurringBilling/cobranca-recorrente-configuracoes.map.json` (`scripts/design/gerar-map.mjs:2` "deriva o ESQUELETO do map.json a partir do gap.md"; `:15` "a tabela de PARTES do gap.md é a MESMA fonte que o contrato consome"). Regenerado com `node scripts/design/gerar-map.mjs memory/requisitos/RecurringBilling/cobranca-recorrente-configuracoes-gap.md` (rc=0, 8 partes): `partes[linguagem-visual].acao` do gerado = texto do commitado **+ ` _(removido em 2026-05-20, 1070…`**; o `map.json` commitado (idêntico em origin/main — não está no diff) não tem a nota. Os outros 7 `acao` batem.
- **Por que é erro do lote:** a coluna Ação é o veredito acionável da parte; a nota de remoção pertence ao Achado (ou à linha de evidência `:41`, onde o lote já a pôs corretamente). Resultado: map.json e gap discordam num campo que o gerador copia literalmente, e `design-code-map-check --check --strict` **não vê** (ele só confere `prototipo_sha` e `vivo.arquivo`). Conserto: mover a nota pra célula Achado (ou removê-la — a `:41` já registra) e não tocar Ação.

### R3 · `memory/requisitos/Sells/show-visual-comparison.md:19` — anota `Vendas Cockpit.html` e deixa `visual-source-fsm-v1.html` como se existisse (OMITE)

- **Afirmação do lote:** `` `Vendas Cockpit.html` (removido em 2026-05-20, 1070e3759b7 — era em `prototipo-ui/prototipos/vendas-cockpit/`) + `visual-source-fsm-v1.html` — pattern detail view com: ``
- **O que origin/main diz:** `visual-source-fsm-v1.html` morava no **mesmo diretório** e morreu no **mesmo commit**: `git show 1070e3759b7 -M --name-status` → `R100 prototipo-ui/prototipos/vendas-cockpit/visual-source-fsm-v1.html → prototipo-ui/_BACKUP-NAO-USAR/…`; o backup foi apagado em ce0c0684fc5 (2026-06-18). Em origin/main: `git ls-tree -r origin/main --name-only | grep -c visual-source-fsm-v1` = **0**; `git grep -l visual-source-fsm-v1 origin/main` = 1 arquivo, o próprio `show-visual-comparison.md`.
- **Por que é erro do lote:** a frase reescrita anota um dos dois arquivos mortos e deixa o irmão sem nota, induzindo o leitor a achar que `visual-source-fsm-v1.html` sobreviveu — exatamente a classe "ponteiro podre" que o lote se propõe a fechar, na mesma linha que ele editou. Conserto: anotar os dois (mesma data/SHA).

### R4 · `memory/requisitos/Whatsapp/CaixaUnificadaV4-visual-comparison.md:243` — "(802 LOC)" re-ancorado num arquivo de 1487 linhas

- **Afirmação do lote:** `- [prototipo-ui/cowork/Wagner/inbox-page.jsx](../../../prototipo-ui/cowork/Wagner/inbox-page.jsx) — fonte visual canônica (802 LOC)`
- **O que origin/main diz:** `git show origin/main:prototipo-ui/cowork/Wagner/inbox-page.jsx | wc -l` = **1487**. (O alvo antigo, `prototipos/caixa-unificada/inbox-page.jsx` em `9da73296d34^`, já tinha 1141 — o número era stale antes; a linhagem é a mesma, cabeçalho idêntico.)
- **Por que é erro do lote:** o lote reescreveu a linha e re-ancorou uma afirmação quantitativa sobre o código num alvo novo **sem medi-lo** — afirmação sobre código que o código em origin/main contradiz (grupo 3). Conserto: remover o número ou medir (`1487` em fdec77f6d3, datado).

## Observações não contadas

1. **`canon_reference` com comentário YAML `# removido em …` (9 `*-visual-comparison.md`: 7 Produto, Financeiro/fluxo, Financeiro/boletos).** js-yaml lê o valor **sem** o comentário (YAML válido). Mas o leitor real do `canon_reference` é `scripts/design/ancora.mjs::chaveAninhada` (L166-176: `desasparValor(t.slice(prefixo.length))` — só trim + desaspa, **não** strippa `#`). Medido com `declaracoesNaoAncora` importado do próprio script: o `valor` em HEAD = path + `  # removido em 2026-05-20, 1070e3759b7`; `node scripts/design/ancora.mjs Produto/Index` e `Financeiro/Fluxo/Index` imprimem o comentário dentro do valor. **Não conta como erro** porque (a) o veredito da máquina não muda — é declaração não-âncora, e `ehCodigoDoRepo` (L152-155, `includes('.blade.php')`/`resources/js/Pages`+`.tsx`) dá o mesmo resultado com ou sem o sufixo; (b) origin/main já carrega o mesmo idioma numa chave mais forte: `resources/js/Pages/Produto/Index.charter.md:6` `bundle_source: produtos-page.jsx  # 2026-09-09 [C]: …` — ali o resolvedor tolera porque `mockupJsx` (L55-59) extrai `[\w.-]*-page\.jsx` por regex. Risco declarado: qualquer consumidor futuro que resolva `canon_reference` como path (`existsSync`) verá um path com prosa. Boletos não tem charter que o leia (`related_visual_comparison` → 0 em origin/main).
2. **Literal truncado do charter Produto** — os 4 RUNBOOKs de Produto citam `related_prototype: n/a (herda PT-01 Lista)`; o charter diz `n/a (herda PT-01 Lista; segue o Padrão de Tela)`. Semanticamente igual; `ehDeclaracaoNa` (`/^n\/a\b/i`) reconhece ambos. Não contado.
3. **"removido" = rename para `_BACKUP-NAO-USAR/`** em 1070e3759b7 (R100 pra todos os paths desse commit, incl. `clientes/cowork-app.jsx`, `kb/Bench KB*.html`, `producao-oficina/*`, `produto-cockpit/*`, `recurring/*`, `sells-index/vendas-page.jsx`, `vendas-cockpit/*`); o backup morreu em ce0c0684fc5 (2026-06-18). O **path citado** deixou de existir na data anotada — redação aceita.
4. **`requisitos-status --check` vermelho em Jana/Repair/Sells** (e `_STATUS-GENERATED.md` ausente em PaymentGateway/Whatsapp/_DesignSystem): o drift é de UCs/US que o lote não toca (`UC-JPAIN-21..23`, `UC-DMIDX-07/08`, `UC-RDSH-05`, `US-SELL-060..063` — todos já em origin/main; o lote não altera `Sells/SPEC.md` nem `.casos.md`). Pré-existente, não do lote.
5. **`design-code-map-check --check --strict` rc=1** — 17 problemas, todos Ponto (`STALE prototipo_sha`) e governance/ModuleGrades (`vivo.arquivo não existe`); **nenhum** em RecurringBilling. Herdado. Ele não detecta o R2 (não compara `acao` com o gap).
6. **`sdd-scorecard --ratchet` rc=1** por `full_suite_pass_rate: carried_over — fonte ausente no checkout` (órfã `governance/nightly-floor` não buscada neste worktree) — ambiente, não lote. `--json` em HEAD: `distiller_freshness = 8` = baseline (8). O lado origin/main ("6 herdadas") **não foi re-medido** (exigiria worktree de main).
7. **Repair**: os 8 docs chamam `os-page.jsx` de blueprint canônico/pattern reuse; os charters Repair declaram `bundle_source: repair-page.jsx` ou `n/a` e `Repair/Settings/Index.charter.md:4` registra que `repair-page.jsx` é porte reverso. O re-apontamento é de **linhagem correta** (`os-page.jsx` ≡ antigo `prototipos/os/cowork-app.jsx`, cabeçalho idêntico "Listagem + Detalhe de Ordens de Serviço"); o conflito doc×charter pré-existe e nenhuma data foi carimbada. `OsDetailPanel`/`NewOsModal` citados: **0** ocorrências no novo **e** no antigo — stale herdado.
8. **Deadlink**: o lote converte links markdown mortos em code-spans; `deadlink-gate --check` OK (778/778 grandfathered, nenhum arquivo vivo piorou); `governance/deadlink-baseline.json` ajustado no branch (−14/+11 linhas).
9. Nenhuma linha `+` do lote carrega `2026-09-15`; nenhuma chave `date:`/`updated:`/`gerado_em:` foi alterada — o lote não carimbou data de hoje em prosa stale.

## Scan PII (linhas `+` de `git diff origin/main...HEAD -- memory/requisitos`, 91 linhas)

| Padrão | Hits | Controle positivo casou? |
|---|---|---|
| CPF pontuado (`ddd.ddd.ddd-dd`) | 0 | sim |
| CPF cru (11 dígitos isolados) | 0 | sim |
| CNPJ (`dd.ddd.ddd/dddd-dd`) | 0 | sim |
| Telefone BR formatado (`(dd) 9dddd-dddd`) | 0 | sim |
| Telefone cru (10–11 dígitos isolados) | 0 | sim |
| E-mail | 0 | sim |
| Valor em reais (símbolo de reais seguido de dígito — padrão não reproduzido aqui de propósito) | 0 | sim |
| **Total** | **0** | **7/7** |

Nomes de cliente do CRM: nenhum nas linhas `+` (só personas internas já presentes em origin/main: Larissa/ROTA LIVRE, Wagner).

## Comandos reproduzíveis

```bash
# base / raso / lote
git rev-parse HEAD origin/main; git rev-parse --is-shallow-repository
git diff --name-status origin/main...HEAD -- memory/requisitos | wc -l      # 55
# grupo 1 — existência + controle
git ls-tree origin/main -- prototipo-ui/cowork/Wagner/os-page.jsx           # blob
git ls-tree origin/main -- prototipo-ui/prototipos                          # vazio
git ls-tree origin/main -- prototipo-ui/nao-existe-xyz.jsx | wc -l          # 0 (controle negativo)
git log origin/main --diff-filter=D --date=short --format='%h %ad %cd' -- 'prototipo-ui/prototipos/vendas-cockpit/visual-source-fsm-v1.html'
git ls-tree -r 1070e3759b7^ -- prototipo-ui/prototipos/sells-index | wc -l; git ls-tree -r 1070e3759b7 -- prototipo-ui/prototipos/sells-index | wc -l
git show 1070e3759b7 -M --name-status --format= | grep 'vendas-cockpit'
# grupo 2 — leitor real
node scripts/design/ancora.mjs Produto/Index --staging prototipo-ui/cowork/Wagner
node scripts/design/ancora.mjs Financeiro/Fluxo/Index --staging prototipo-ui/cowork/Wagner
git show origin/main:resources/js/Pages/Cliente/Index.charter.md | sed -n '5,6p'
# grupo 3
git show origin/main:prototipo-ui/cowork/Wagner/inbox-page.jsx | wc -l       # 1487
git show 9da73296d34^:prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx | wc -l   # 1141
git ls-tree -r origin/main --name-only | grep -c visual-source-fsm-v1       # 0
# grupo 4
node scripts/design/gerar-map.mjs memory/requisitos/RecurringBilling/cobranca-recorrente-configuracoes-gap.md > /tmp-gerado.json   # comparar partes[linguagem-visual].acao com o map.json commitado
# grupo 5 (rc literal desta rodada)
node scripts/governance/plans-index.mjs --check                 # rc=0
node scripts/governance/doc-id-index.mjs --check-collisions     # rc=0 (0 colisão em 2745 ids)
node scripts/governance/design-code-map-check.mjs --check --strict   # rc=1 (17 problemas, nenhum do lote)
node scripts/governance/deadlink-gate.mjs --check               # rc=0
for m in Cliente Crm Financeiro Jana KB OficinaAuto PaymentGateway Produto RecurringBilling Repair Sells Whatsapp _DesignSystem; do node scripts/governance/requisitos-status.mjs $m --check; done
#   rc=0: Cliente Crm Financeiro KB OficinaAuto Produto RecurringBilling · rc=1 (drift pré-existente): Jana Repair Sells · rc=1 (arquivo inexistente): PaymentGateway Whatsapp _DesignSystem
bash .github/scripts/validate-memory-schema.sh spec memory/requisitos/{Cliente,Financeiro,Jana,Whatsapp}/SPEC.md   # rc=0, erros 0
node scripts/governance/sdd-scorecard.mjs --json | jq .metrics.distiller_freshness.value   # 8 (baseline 8)
node scripts/governance/sdd-scorecard.mjs --ratchet             # rc=1 (full_suite_pass_rate carried_over — órfã não buscada; ambiente)
```

Árvore deixada limpa: `git status --short` vazio antes desta evidência; nenhum arquivo do lote editado; nada commitado; ledger não tocado.

```json
{"itens_verificados": 270, "erros_confirmados": 4, "error_rate_pct": 1.48, "pii_hits": 0, "veredito": "aprovado"}
```
