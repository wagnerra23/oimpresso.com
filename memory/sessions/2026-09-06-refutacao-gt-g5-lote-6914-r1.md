---
date: "2026-09-06"
topic: "Refutação GT-G5 r1 do lote PR #6914 — 10 gap.md + 10 map.json + 2 _STATUS-GENERATED (Estoque · Manufacturing · Officeimpresso · OficinaAuto) — veredito: reprovado (2,20%)"
authors: ["C"]
prs: [6914]
outcomes:
  - "409 itens verificados · 9 erros confirmados · error_rate 2,20% (≥2%) → lote REPROVADO por margem estreita; PII 0 hits (7/7 controles)"
  - "Erros concentrados em ÂNCORA DE LINHA do protótipo deslocada em 1–2 linhas (5 partes: estoque-page:227, estoque-forms:279 e :270, manufacturing-producao:20, officeimpresso-page:741) e em 4 afirmações de prosa (aritmética 1296→1293/−4 e 1163→1144/−20, 'todas as citadas estão acima de 732' com 745/964-973/1227 na tabela, 'única das 6 telas deste lote')"
  - "Âncoras de arquivo (63/63), ancora.mjs (10/10 ✓), regeneração dos 10 maps (acao/_acionavel/sha idênticos em 137/137), 2 _STATUS --check, 5 máquinas rc=0, 137 células íntegras: 0 erros. ⚠️ Esta rodada abriu 12 linhas (frontmatter) da evidência do lote 6897 — declarado; a rodada pode ser descartada pelo §6"
---

# Refutação GT-G5 — lote PR #6914 · rodada r1

**Base:** `origin/main` = `c1292448ee` (no início da sessão; avançou para `e556453ebf` durante a rodada — todas as medições foram re-ancoradas no SHA `c1292448ee`, não na ref móvel).
**HEAD do lote:** `dcb74c7b0a` (merge de `claude/gap-map-estoque-mfg-oficina-oi` em `claude/gap-map-oficina-officeimpresso`).
**Repo raso:** `git rev-parse --is-shallow-repository` → `false`.
**Sessão fresca:** sim — instância nova, zero contexto do gerador. ⚠️ **Declaração obrigatória (§6 anti-gaming):** ao procurar um exemplo de frontmatter para este arquivo, rodei `git show c1292448ee:memory/sessions/2026-09-06-refutacao-gt-g5-lote-6897.md | sed -n '1,12p'` — imprimiu o frontmatter + título da evidência do **lote 6897** (outro PR; nenhuma linha do corpo, nenhuma da evidência do 6914). Pela letra do mandato ("qualquer `*refutacao*`"), `abriu_evidencia_anterior: true`. O conteúdo desta rodada foi medido inteiro **antes** desse deslize e não depende dele; a decisão de descartar é do workflow.
**⚠️ Árvore compartilhada:** às 15:38:48 (reflog) **outra sessão** fez `checkout` deste worktree de `claude/gap-map-oficina-officeimpresso` para `claude/gap-map-estoque-mfg-oficina-oi` e mergeou `origin/main` (15:39:53). Todas as sondas de G1–G3/G5 já tinham rodado com HEAD=`dcb74c7b0a` (confirmado por `git rev-parse HEAD` na 1ª linha da sessão); G4 e G6 foram **refeitas por SHA explícito** (`git show dcb74c7b0a:<path>` · `git diff c1292448ee...dcb74c7b0a`). Nenhum git op meu tocou a árvore (§5 2026-08-13 — dono é sessão viva). `git status --short` vazio antes de escrever este arquivo.

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador) — ver declaração acima sobre o frontmatter do 6897
- [x] Modelo de tier SUPERIOR ao gerador (Fable 5.1 · tier máximo disponível)
- [x] Amostra: 100% anchors (todo path, toda chave de frontmatter, toda linha de tabela, toda `acao` dos map.json)
- [x] Cada item verificado contra o código real em `c1292448ee` (fontes `.jsx`/`.tsx` idênticas entre base e HEAD: `git diff --stat c1292448ee dcb74c7b0a -- prototipo-ui resources Modules` vazio), não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha + porquê)
- [x] Scan PII no diff (7 padrões × controle positivo 7/7) — 0 hits
- [x] `error_rate_pct` calculado — **2,20%** (não é < 2)
- [ ] Entry no ledger — **não escrita** (mandato: não editar o lote, não escrever no ledger)

## Escopo medido

`git diff --name-status c1292448ee...dcb74c7b0a -- memory/requisitos` → **22 arquivos**, todos `A`: 10 `*-gap.md` + 10 `*.map.json` + 2 `_STATUS-GENERATED.md` (Manufacturing, Officeimpresso). Fora de `memory/requisitos` o PR também toca `memory/sessions/2026-09-06-refutacao-gt-g5-lote-6914.md` (**não aberto**), `scripts/design-sync/state/{application-report,applications}.json` e `scripts/governance/.cowork-freshness-ledger.json` (+1 rodada, a "38").

Tipo: anchors → amostra 100%. Partes nos 10 map.json: 10+13+12+15+12+14+13+12+16+20 = **137** (= 137 linhas de tabela nos gap.md).

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---:|---:|---:|---|
| 1 · Âncora existe em origin/main | 63 | 63 | 0 | `git ls-tree c1292448ee -- <path>` para 53 paths citados (9 `.jsx` do espelho · 11 `.tsx` vivos incl. `MaquinasTable` · 11 charters · 6 componentes · 4 docs canon · 7 scripts · 3 controllers · 2 gap/parity anteriores) + 10 `gap_fonte` em `dcb74c7b0a`; controle negativo `memory/requisitos/Estoque/NAO-EXISTE-controle-negativo.md` → `MISSING` (o loop distingue). Nenhum `vivo.arquivo` aponta para `Components/**` |
| 2 · Âncora não revogada / lida pelo leitor real | 22 | 22 | 0 | `node prototipo-ui/ancora.mjs <tela> --staging prototipo-ui/cowork` ×10 → `âncora ✓` em todas (Recipes/OI por `related_prototype`, as demais por `bundle_source`; OI imprime `tela viva: —` como o gap declara); regeneração dos 10 maps com `gerar-map.mjs <gap.md>` (stdout) e diff chave a chave: `acao`, `_acionavel`, ids, `prototipo_sha`, `gerado_em` idênticos em 137/137; `prototipo_sha` recomputado por `computeProtoHash` bate nos 6 valores; `requisitos-status.mjs Manufacturing|Officeimpresso --check` → "em dia" (rc=0) |
| 3 · Ação × veredito · afirmação sobre código · linha citada | 182 | 173 | **9** | 137 partes (âncora `prototipo.arquivo:linhas` aberta e conferida + "Estado no vivo" conferido no `.tsx` + `acao` nos termos da prosa) + 45 parágrafos de cabeçalho (frescor, porte, região, Non-Goals, revogação). Varreduras contadas reproduzidas: `git grep -l` StagePipeline 1/1 · FsmActionPanel 2/2 · Timeline 2/2 · StageGate 2/2 (consumidor `ServiceOrderRichSheet`); `Show.tsx` Stepper/FsmActionPanel/StagePipeline/Timeline/sale_stage_history = 0; `Board.tsx` StageGate = 0; `Manufacturing/Index.tsx` os-page-h/mfg-th/<Th/ordenar/sortBy/setPag/mfg-pag = 0 e grep frouxo `order` = 4 linhas / 11 casamentos; `Recipes.tsx` Excluir/AlertDialog/mfg-modal/mfg-toast = 0 |
| 4 · Célula íntegra | 137 | 137 | 0 | Script no scratchpad lendo `dcb74c7b0a:` — 137 linhas, `cells!=3` = 0, code-span ímpar = 0, reticência final = 0; `acao` do map == célula da tabela (o gerador reparseia a tabela e deu igual em 137/137) |
| 5 · Máquina derivada | 5 | 5 | 0 | `requisitos-status.mjs Manufacturing --check` rc=0 · `Officeimpresso --check` rc=0 · `plans-index.mjs --check` rc=0 · `design-code-map-check.mjs --check --strict` rc=0 ("[OK] nenhum map.json com âncora quebrada ou sha stale") · `doc-id-index.mjs --check-collisions` rc=0 (0 colisão em 2614 ids). Arquivos que o PR regenera estão no diff (ledger, design-sync state) |
| **Total** | **409** | **400** | **9** | error_rate = 9/409 = **2,20%** |

## REFUTADOS (9)

### R1 · `memory/requisitos/Estoque/stock-adjustment-index-gap.md` + `.map.json` · parte `paginacao`
- **Afirmação:** "O protótipo pagina de 10 em 10 (`estoque-page.jsx:227`, `Pagination` em `:293`)"; map `prototipo.linhas: "227"`.
- **origin/main diz:** `estoque-page.jsx:227` = `const colunas = COLS_AJ.filter((c) => cols[c.key] && (!c.preco || verPreco));` — seletor de colunas. A paginação é `perPage = 10` em `:216` e `const pagina = rows.slice(...)` em `:226`.
- **Por quê é erro do lote:** a âncora primária do map (a que a Fase 4 consome via `consumir-map.mjs`) aponta para linha que não contém o que se afirma — "grep -n real, nunca fabricar" (`_doc` do próprio map).

### R2 · `memory/requisitos/Estoque/stock-transfer-create-gap.md` + `.map.json` · parte `limpeza-de-linhas-ao-trocar-a-origem`
- **Afirmação:** "(`trocarDe`, `estoque-forms.jsx:279`)"; map `prototipo.linhas: "279"`.
- **origin/main diz:** `:278` = `const trocarDe = (v) => { setDe(v); if (linhas.length) { setLinhas([]); aviso("Origem trocada — as linhas foram limpas porque o saldo é da origem."); } };` — `:279` é linha em branco.
- **Por quê é erro do lote:** âncora primária numa linha vazia; o símbolo citado está na linha anterior.

### R3 · `memory/requisitos/Estoque/stock-transfer-create-gap.md` + `.map.json` · parte `trava-de-saldo-disponivel-na-origem`
- **Afirmação:** "calcula o disponível na origem por linha (`dispDe`, `estoque-forms.jsx:270`) … bloquear o submit (`podeSalvar`, `:272`)"; map `prototipo.linhas: "270"`.
- **origin/main diz:** `:270` = `const mesmoLocal = de && para && de === para;` (âncora da parte **Origem→Destino**, não desta); `dispDe` está em `:271`; `podeSalvar` em `:274` (`:272` é `negativa`).
- **Por quê é erro do lote:** a âncora primária aponta para a linha de OUTRA parte; duas das três citações da célula não contêm o símbolo nomeado (mesmo deslocamento sistemático de 1–2 linhas em `:271→273 semLote`, `:334→333 help`, registrados como observação).

### R4 · `memory/requisitos/Manufacturing/manufacturing-index-gap.md` + `.map.json` · parte `ordenacao-por-coluna`
- **Afirmação:** "ordena por qualquer das 8 colunas, com indicador de direção e default data-desc (`manufacturing-producao.jsx:20`, `CH` em `:21`, `Th` em `:36-42`)"; map `prototipo.linhas: "20"`.
- **origin/main diz:** `manufacturing-producao.jsx:20` é linha em **branco**; o default data-desc é `:18` (`const [ord, setOrd] = useState({ k: "data", dir: "desc" })`); `Th` ocupa `:34-39`.
- **Por quê é erro do lote:** âncora primária em linha vazia.

### R5 · `memory/requisitos/Officeimpresso/logs-timeline-gap.md` + `.map.json` · parte `estado-vazio`
- **Afirmação:** "O protótipo tem um vazio genérico no lugar (`officeimpresso-page.jsx:741`)"; map `prototipo.linhas: "741"`.
- **origin/main diz:** `:741` = `{eventos.map((g, i) => (` — início da lista de eventos; o vazio é `:739` (`? <p className="sa-modal-p">Nenhum evento nesta janela de retenção.</p>`).
- **Por quê é erro do lote:** âncora primária aponta para a lista cheia, o oposto do estado vazio afirmado.

### R6 · `memory/requisitos/Manufacturing/manufacturing-recipes-gap.md` · cabeçalho "Âncora declarada no charter"
- **Afirmação:** "a única das **6 telas deste lote** com âncora por `related_prototype`, não por `bundle_source`".
- **O lote diz:** o PR tem **10** telas; `Officeimpresso/Logs/Index` e `Logs/Timeline` também resolvem por `related_prototype` (`ancora.mjs` → `[related_prototype (charter)] prototipo-ui/cowork/officeimpresso-page.jsx`, e os dois gaps abrem com "Âncora declarada no charter (`related_prototype: …`)"). O próprio `manufacturing-index-gap.md` define o lote como os 5 protótipos incluindo `oficina-page.jsx` e `officeimpresso-page.jsx`.
- **Por quê é erro do lote:** número inventado ("6") e afirmação de unicidade falsa dentro do próprio lote — fact-anchor sem fonte (§5 2026-07-17).

### R7 · `memory/requisitos/OficinaAuto/service-orders-board-gap.md` · cabeçalho "Frescor do espelho"
- **Afirmação:** "(−4 linhas: 1296 → 1293)" e "as linhas citadas abaixo de 732 estão deslocadas em 4".
- **Medição:** o espelho tem **1296** linhas (`git show c1292448ee:prototipo-ui/cowork/oficina-page.jsx | wc -l`). 1296 − 1293 = **3**, não 4. Os dois números da mesma frase se contradizem; o vivo não é mensurável daqui (`DesignSync` interativo), então não há como saber qual dos dois está certo.
- **Por quê é erro do lote:** aritmética que não fecha em afirmação apresentada como medida por hash.

### R8 · `memory/requisitos/Officeimpresso/logs-index-gap.md` · cabeçalho "Frescor do espelho"
- **Afirmação:** "(−20 linhas: 1163 → 1144)".
- **Medição:** espelho = **1163** linhas (confere). 1163 − 1144 = **19**, não 20. Mesma classe de R7 (a região `Kebab :97-118` tem 22 linhas, como afirmado; a conta final é que não fecha).
- **Por quê é erro do lote:** aritmética contraditória em número "medido".

### R9 · `memory/requisitos/OficinaAuto/service-orders-board-gap.md` · cabeçalho "Frescor do espelho"
- **Afirmação:** "as linhas citadas abaixo de 732 estão deslocadas em 4 em relação ao Cowork vivo — **todas as citadas nesta tabela estão acima disso**."
- **A própria tabela cita:** `oficina-page.jsx:964-973` (`:970` Imprimir fila, `:972` Nova OS — parte Header), `:745` (`useState(OS_LIST)`) e `:1227` (`setOsList`) — parte Duas portas. Três citações **depois** de `:738`, logo deslocadas pela troca do `Seg` (`:732-738`, confirmada no espelho).
- **Por quê é erro do lote:** a nota de invalidade contradiz a tabela que ela pretende blindar — o leitor sai achando que nenhuma linha citada está deslocada, e 5 estão.

## Observações NÃO contadas

- **Rodada 38 do ledger só existe no HEAD.** `c1292448ee` tem 37 rodadas (última `06:53:35`, `staleList=[]`); a "rodada 38" (`11:48:39`, `staleList=[officeimpresso-page.jsx, oficina-page.jsx]`) é adicionada por este PR e grava `verified: []` / `verifiedHash: {}`. Os hashes do **espelho** citados nos gaps conferem (`contentHash` de `oficina-page.jsx` = `1259f30cf94a…`, de `officeimpresso-page.jsx` = `0626867a4819…`); os hashes do **vivo** (`409e02172cd3…`, `39b2cfcfba45…`) não existem em artefato nenhum do repo (`rg --hidden`: só nos dois gaps). Não é refutável daqui — fica registrado que "medido por HASH — rodada 38" não tem recibo reproduzível do lado vivo.
- **"28 KB" (stock-adjustment-index-gap):** o espelho `estoque-page.jsx` tem 43.996 bytes; o número se refere ao retorno inline do `get_file` (vivo), não mensurável aqui.
- **"lista este entre os `unchecked`":** a rodada 38 grava só a contagem (`unchecked: 256`), não a lista de nomes.
- **Deslocamentos secundários (≤3 linhas, âncora primária correta):** sa-index `MP.Header l.583-591` (real 580-590) · sa-index rodapé "cálculo em `:245-246`" (real 243-244) · sa-create `fecharContagem :539-555` (real 538-553) · st-create "`Create.tsx:206-210` só faz `setData`" (o `setData` é `:202`) · st-create `semLote :271` (273), `help :334` (333), `podeSalvar :272` (274) · mfg-index `POR_PAG :31` (29), `Th :36-42` (34-39) · mfg-recipes comentário `:190-194` (187-191), `Th :74-79` (72-77) · show `:79` foto por item (77), `:28` "1,5h" (27; `:28` tem "0,5h" — a afirmação "texto estático por serviço" segue verdadeira).
- **`prototipo.arquivo` preenchido à mão em 15 partes** (skeleton põe `arquivosPrototipo[0]`; o lote corrigiu para o arquivo que de fato contém as linhas, ex. `estoque-forms.jsx` no drawer) e **`vivo.arquivo` sob `Modules/Officeimpresso/…` em 25 partes** (o resolvedor do gerador só casa `resources/js/Pages`): `fundirComExistente` preserva os dois (precedência explícita `arquivo+linhas` / `vivo`), e `design-code-map-check --strict` aceita — legítimo, não contado.
- **`Timeline` gap, parte Tabela de acessos:** "5 colunas — Data/hora, Status HTTP, Estado no login, IP e a 5ª" — a 5ª é `Duração` (`Timeline.tsx:143`); omissão de nome, não erro de fato.
- **Sessão paralela na mesma árvore** (reflog acima): o lote de `dcb74c7b0a` é a união de dois branches (`gap-map-estoque-mfg-oficina-oi` + `gap-map-oficina-officeimpresso`); quem for consertar deve conferir em qual dos dois cada arquivo vive (R1–R4 e R6 no primeiro; R5, R7–R9 no segundo).

## Scan PII (linhas `+` de `git diff c1292448ee...dcb74c7b0a -- memory/requisitos` — 2.677 linhas)

| Padrão | Hits | Controle positivo |
|---|---:|---|
| CPF pontuado | 0 | casa |
| CPF cru (11 dígitos isolados) | 0 | casa |
| CNPJ | 0 | casa |
| Telefone BR | 0 | casa |
| Telefone cru (10–11 dígitos) | 0 | casa |
| E-mail | 0 | casa |
| Valor em reais (símbolo + dígito) | 0 | casa |

Nomes de cliente do CRM: nenhum nas linhas `+` (os únicos nomes são personas do mockup já presentes em `origin/main` no `.jsx`, não no diff). **pii_hits = 0 · controles 7/7.**

## Comandos reproduzíveis

```bash
B=c1292448ee; H=dcb74c7b0a
git rev-parse --is-shallow-repository                              # false
git diff --name-status $B...$H -- memory/requisitos | wc -l         # 22
git diff --stat $B $H -- prototipo-ui resources Modules             # (vazio → fontes idênticas)
git ls-tree $B -- <path>                                            # G1 (53 paths + controle negativo MISSING)
for t in StockAdjustment/Create StockAdjustment/Index StockTransfer/Create StockTransfer/Index Manufacturing/Index Manufacturing/Recipes OficinaAuto/ServiceOrders/Board OficinaAuto/ServiceOrders/Show Officeimpresso/Logs/Index Officeimpresso/Logs/Timeline; do node prototipo-ui/ancora.mjs "$t" --staging prototipo-ui/cowork; done
node prototipo-ui/gerar-map.mjs memory/requisitos/<Mod>/<tela>-gap.md   # esqueleto → diff chave a chave vs .map.json (acao/_acionavel/sha)
node scripts/governance/requisitos-status.mjs Manufacturing --check; node scripts/governance/requisitos-status.mjs Officeimpresso --check
node scripts/governance/plans-index.mjs --check; node scripts/governance/design-code-map-check.mjs --check --strict; node scripts/governance/doc-id-index.mjs --check-collisions
git show $B:prototipo-ui/cowork/estoque-page.jsx | sed -n '216p;226,227p'          # R1
git show $B:prototipo-ui/cowork/estoque-forms.jsx | sed -n '270,274p;278,279p'    # R2, R3
git show $B:prototipo-ui/cowork/manufacturing-producao.jsx | sed -n '18,21p'       # R4
git show $B:prototipo-ui/cowork/officeimpresso-page.jsx | sed -n '739,741p'       # R5
git show $B:prototipo-ui/cowork/oficina-page.jsx | wc -l                           # 1296 (R7)
git show $B:prototipo-ui/cowork/officeimpresso-page.jsx | wc -l                    # 1163 (R8)
git show $B:scripts/governance/.cowork-freshness-ledger.json | node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>console.log(JSON.parse(s).length))'   # 37
git grep -l -F ServiceOrderStagePipeline $B -- '*.tsx' | wc -l     # 1
git diff $B...$H -- memory/requisitos | grep '^+' | grep -v '^+++' | <7 regex com controle positivo>   # PII
```

```json
{"itens_verificados": 409, "erros_confirmados": 9, "error_rate_pct": 2.2, "pii_hits": 0, "veredito": "reprovado"}
```
