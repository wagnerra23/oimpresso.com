# COLAR NO CODE — AUTOMAÇÃO DO PROTOCOLO DE EXPORT (PR-A1…A7)

> **Pedido de [W] 2026-09-03:** automatizar o ciclo `MAPA → ALVO → EXPORT → PR → PLACAR → pacote`, e valer **dos dois lados** (repo e Cowork).
> **Método:** `COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md`. **Ponte** — destino `prototipo-ui/` (root). Eu não commito.
> **Princípio:** nenhum dono novo. Cada PR abaixo **estende** máquina que já existe (`design-memory-gate.yml`, `cowork-ssot-guard`, `cowork-mirror-freshness`, `casos-gate`, `contrato-de-tela`, `design-spec-gen`, `prototipo-readiness`, `gerar-payload-partes`).
> **Risco:** 🟢 mecânico · 🟡 regra de domínio · 🔴 schema/CI crítico. 1 assunto por PR, ≤8 arquivos, ≤~350 linhas.

---

## Ordem (cada uma destrava a seguinte)

`A1 + A2` → `A5` → `A3` → `A4` → `A6` → `A7` → `A8`. A1 é a única que **não** depende de nada.

---

## PR-A1 · `ALVO` executável 🟢 — *primeiro da fila*

- **Cria:** `scripts/design-sync/alvo.mjs`.
- **Faz:** headless (Playwright já usado no repo? se não, `puppeteer-core` + Chrome do runner) abre o espelho servido, seta a rota, **espera `window.__oiLazyDone` E duas leituras iguais** de `document.querySelectorAll('*').length`, e roda a sonda: por seletor → nº de nós · nº de filhos · **ordem das classes dos filhos** · `getComputedStyle` dos campos pedidos · `scrollWidth × clientWidth` (truncamento) · retângulo (tamanho de alvo).
- **Modo `--mapa`:** colhe filhos diretos da raiz da view + classes repetidas ≥N e imprime no **stdout**. **Nunca grava arquivo** (mapa é comando — L-42 · ADR 0256).
- **Saída do modo alvo:** `prototipo-ui/contrato/<tela>.alvo.json` — *fonte de teste*, não retrato: é insumo do A3.
- **Aceite falsificável:** rodar 2× seguidas dá **byte-idêntico**; remover 1 filho no DOM via `--injetar-falha` faz o JSON mudar e o A3 reprovar (é o T5 do protocolo, embutido).
- **Aposenta:** eu medindo à mão e ditando números no chat.

## PR-A2 · sonda com caso de sanidade obrigatório 🟡

- **Faz:** todo cálculo derivado dentro do `alvo.mjs` (contraste, luminância, razão) roda antes um **caso de valor conhecido** e **aborta** se ele não bater.
- **Por que existe:** medido em 2026-09-03 — minha 1ª sonda leu `oklch(0.94 0.005 90)` com regex de `rgb()` e deu contraste **2,62** no `.fj-title`; a "correção" via `canvas.fillStyle` **não converte** oklch e repetiu o mesmo número, parecendo confirmação. Só a 3ª (OKLCH→OKLab→sRGB) vale: **10,84**, com sanidade branco-sobre-`--bg` = **15,52**.
- **Aceite:** sabotar a conversão faz o script **falhar**, não retornar número plausível.

## PR-A3 · `secao-check` no CI 🟢 (T2 · T3 · T6)

- **Cria:** `scripts/qa/secao-check.mjs`; **liga em** `.github/workflows/design-memory-gate.yml`.
- **Faz:** lê `<tela>.alvo.json` e compara com o render (preview/prod autenticada): contagem, **ordem**, tokens resolvidos. Reprova nomeando o ausente.
- **Vizinhança (T6):** roda também o alvo das seções **já fechadas** da mesma tela — regressão de vizinho vira falha de CI, não descoberta em review.
- **Aceite:** o PR que remove um slot do alvo fica **vermelho**; o que respeita fica verde 3× seguidas antes de virar required.

## PR-A4 · bateria A1–A12 de a11y, nos dois lados 🟡

- **Cria:** `scripts/qa/a11y-alvo.mjs` = **axe-core** + as sondas que o axe não faz: `DIV` clicável sem `role`/`tabindex` · `svg` em clicável sem `aria-hidden` nem nome · `aria-live` ausente onde há conteúdo dinâmico · overlay sem `role`/`aria-modal`/foco · `aria-selected|pressed` estático · contraste OKLCH (usa A2) · alvo <24×24.
- **Roda no protótipo E na tela.** Regra do protocolo: **o que falhar no protótipo corrige-se no build**, não vira pedido.
- **Baseline honesta (medida na Forja, 2026-09-03):** 23 `DIV` clicáveis · **66 de 66** svg anônimos · drawer sem foco · **0 de 6** abas com ARIA de estado · `aria-live` 0 · 4 falhas AA de contraste · 81 de 118 alvos <24px (⚪ decisão [W]).
- **Aceite:** número de violações **só pode cair** (ratchet, régua do `ds:report`).

## PR-A5 · pacote regenerado por máquina 🟢 — *o que me destrava de vez*

- **Cria:** workflow `cowork-bundle.yml` — no push a `prototipo-ui/cowork/**`, roda
  `node scripts/design-sync/gerar-payload-partes.mjs --root prototipo-ui/cowork --out sync/ --previous sync/bundle.manifest.json`
  e commita `sync/`.
- **Por que:** o gerador exige os arquivos **em disco** e por isso não roda do meu lado (ADR 0374) — **o runner tem disco**. Sintoma que a justifica: `sync/bundle.manifest.json` já ficou congelado com 3 ciclos de design fechados fora dele.
- **Consequência medida da divisão 1-arquivo-por-tela:** os 17 `forja-*.jsx` estão **todos abaixo do piso de ~48 KB**, então a rota avulsa `get_file` não serve mais — o pacote deixou de ser conveniência e é **a** rota.
- **Aceite:** manifesto com `date` do commit e N arquivos igual ao `ls` do diretório; o `github.md` recebe a linha `bundle regenerado (<data> · N arquivos)` **pelo bot**, não por mim.

## PR-A6 · placar como bot de PR 🟢

- **Faz:** `scripts/qa/placar.mjs` compara `alvo.json` × render e **comenta no PR**: `entregue X de Y · ausentes <classe> por <motivo>`; motivos vêm de um `ausentes:` declarado no `alvo.json` (sem endpoint · campo inexistente · decisão [W]).
- **Deriva as 3 métricas** sem máquina nova: cobertura cumulativa (Σ entregue ÷ Σ alvo) · reincidência por motivo · retrabalho (seção reaberta).
- **Aceite:** PR sem placar **não** mergeia (o comentário é o gate) — hoje "esquecer o placar" é grátis, e é o que faz a omissão sumir.

## PR-A7 · pedido gerado + sessão limpa por bootstrap 🟡

- **Faz:** `scripts/design-sync/pedido.mjs --tela X --secao Y` monta os 4 blocos (A identidade com **ancoragem dupla** · B não inventar · C alvo do `alvo.json` · D DoD) lendo `alvo.json` + `<Tela>.design-spec.json` + charter; emite o `.md` da ponte.
- **Sessão limpa (§2-quater, obrigatório):** `.github/ISSUE_TEMPLATE/onda.yml` + `.claude/commands/onda.md` carregam o **read-order** na abertura — a sessão nasce lida, não "lembra de ler".
- **Aceite — teste do estranho:** um executor sem histórico abre o pedido e não precisa perguntar nada sobre alvo, âncora, dado ou aceite.

---

## PR-A8 · playbook por módulo + placar da LISTA 🟢 — pedido [W] 2026-09-05 (`SINCRONIZAR <Mod>`, §12 do protocolo)

- **Estende** `scripts/qa/placar.mjs` (A6) com `--indice`: **o script já está escrito e testado como ponte** em `cowork-inbox/_scripts/placar-indice.mjs` (zero deps) + `cowork-inbox/_schema/playbook.schema.json`. Lê `cowork-inbox/<mod>/playbook/playbook.json` (fonte: threads · prefixo · dependências · decisões [W] · `provas[]` de tipos `arquivo | ausente | contem | nao_contem | json_com_chaves`, com variáveis `${PAGES}` que só [W] preenche), confere no repo e deriva o estado (`feito · em curso · proximo · pendente · bloqueada`) — **ninguém escreve estado**. Comenta no PR **`entregue X de Y · ausentes <thread> por <arquivo/motivo>`** e imprime `PRÓXIMO:` (deps de thread feitas + decisões respondidas + nenhuma variável nula). `--todos 'cowork-inbox/*/playbook/playbook.json'` soma módulos = unificador. Hoje o placar é por tela; "o Code terminou a lista inteira?" não tem máquina.
- **Estende** `.claude/commands/onda.md` (A7) com `--thread NN`: a sessão limpa nasce com o `NN-*.md` + read-order carregados (bloco de abertura de `ponte/03`).
- **Regras herdadas, não novas:** 1 thread = 1 prefixo (Lei 1) · estado só em `_saida` (Lei 2) · 1 PR por thread (Lei 3) · arquivos proibidos (Lei 4). Índice vive em `cowork-inbox/`, nunca em `cowork/` (R1).
- **Aceite falsificável (T5 da lista):** remover uma `prova:` do repo faz X cair para X−1 **nomeando a thread**; `_saida` ausente reprova a thread mesmo com PR mergeado.
- **Depende de:** A6 (placar) e A7 (bootstrap). **Não automatiza:** qual seção entra (julgamento) e o T7.

---

## PR-A9 · DS **por dono**, convertido na APLICAÇÃO 🟢 — decisão [W] 2026-09-17

> **O problema que isto encerra:** o `_ds/` é recorte do **bind de cada projeto** — o do Wagner tem slug (`_ds/office-impresso-…019dd02f/`, 10 arquivos `preview-cache`), o do Felipe é **sem slug** (`_ds/colors_and_type.css`, `_ds/styles.css`, `_ds/_ds_bundle.js` em `venda-v3/index.html`). Normalizar a forma no lado do DESIGN cobra de cada dono, a cada refresh de binding. [W]: *"cada um fica com seu `_ds` próprio; quando for aplicar no Code, faz a conversão"*. A conversão tem endereço natural no applier, que **já é o dono do roteamento** (`destinoDoBundle` · `dsRuntimeRelPath`).
>
> **Custo medido de fazer diferente (1 dia):** 3 refs de `<link>` custaram 4 ocorrências de `__OI_DS_BASE__` escritas à mão, 1 pacote recusado (26), 1 revert dele, 1 revert errado meu e uma máquina nova (+21/+7/+10/+4/+30 linhas em `bundle-transaction.mjs`/`.test.mjs`). E não terminava: o host do Felipe teria de ser migrado por outro dono, e todo refresh recriaria o caso. **Uma regra, dois donos, zero conserto por pacote** é o objetivo deste PR.

> **Pedido pronto para colar no Code:** `COLAR-NO-CODE-A9-DS-POR-DONO.md` (thread única, auto-suficiente). Este bloco é a norma/backlog; o executor lê o pedido, não este arquivo (§13).

### Desenho (5 peças, nenhuma nova do zero)
1. **Bytes do DS saem do lote por FLAG que já existe:** `--exclude '_ds/**'` no `gerar-payload-partes.mjs` (glob suportado; o próprio docblock cita o teste com `--exclude '_ds/**/_ds_bundle.js'`). Nada de `preview-cache` pousa — a regra de dono do [CL] fica **intacta, e sem código novo**.
2. **`dsRequires` POR DONO, com o slug de cada um:** `{ owner, slug|null, arquivos:[{path, sha256}] }`, lido do `_ds/` em disco (⚠️ leitura **nova** — `classificarParaSync` trata `_ds/` como ruído e o gerador **não** o lê hoje; premissa minha corrigida por [CL]). Slug `null` é a forma legítima do Felipe, não erro.
3. **`transforms` declarado no manifesto:** `[{path, regra:'ds-ref', de, para}]` + o **sha pós-transformação** de cada arquivo tocado, calculado pelo gerador. Regra única e determinística: `_ds/<slug>?/x` → caminho relativo a `prototipo-ui/design-system/x`.
4. **Applier converte na aterrissagem** com essa regra, e **verifica** o DS exigido contra `prototipo-ui/design-system/`: **ausente ⇒ recusa o lote** nomeando arquivo e sha · **sha divergente ⇒ RELATO**, exit 0 (o espelho é o dono e pode estar à frente — foi o caso das 4 fontes hoje; e hash **não diz direção**) · igual ⇒ silêncio. `dsRequires` ausente (pacote legado) ⇒ `NÃO MEDIDO`, nunca verde por omissão.
5. **O gate de ref literal INVERTE de papel:** deixa de recusar e passa a ser o **gatilho** da conversão. Recusa só a ref `_ds/` que **não casa** a regra (ex.: `_ds/` apontando para arquivo fora da whitelist de runtime).

### Por que a transformação tem de ser DECLARADA (a condição, não um detalhe)
Converter muda o conteúdo do host na aterrissagem, e **duas máquinas dependem de o arquivo do espelho ser byte-idêntico ao transportado**: o applier confere `bytes` declarado × real e recusa o lote na divergência; o `--compare-bundle` compara `rawHash(disco)` × `sha256` do manifesto — **é o único sinal que pega remendo à mão no espelho** (caso de 13/08, 4 dias sem ninguém ver). Conversão não declarada ⇒ host **STALE em 100% dos ciclos** e esse alarme morre. Com `transforms` + sha pós-transform: bytes-check segue valendo no payload (pré-transform) e o frescor compara contra o sha pós-transform. **Retrabalho visível trocado por cegueira é o pior negócio possível** — daí a condição.

### Dois furos de máquina que a mesma medição expôs (entram no PR)
- **`styles.css` NÃO está na whitelist de runtime** do `dsRuntimeRelPath` (`_ds_bundle.js | colors_and_type.css | cockpit_domains.css | assets/`) — e é o que carrega os **componentes** do DS, do qual o host do Felipe depende. Sem isso o DS não tem como pousar componente para ninguém.
- **Caminho sem slug LANÇA:** `_ds/colors_and_type.css` não casa `^_ds/[^/]+/`, cai na whitelist e dá `throw`. Coerente com a árvore: **não existe `_ds/` sob `Felipe/`** — as refs dele estão penduradas no espelho hoje.

### `dsRequires`/`transforms` ficam FORA da identidade do bundle
`createManifest` hasheia `identity = {schema, source, entry, files, missing, mirrorScope?}` e `validateManifest` **recomputa esse conjunto** — campo novo dentro da identidade reprova por construção (foi a recusa do dry-run do [CL]). E a classificação é essa mesma: identidade = **o que pousa**; `dsRequires`/`transforms` = **pré-condição e regra de aterrissagem**. Viajam como metadado (ao lado de `mode`/`totals`/`changes`), com **shape-check** no validador e enforcement no applier. ❌ **Não** relaxar o validador para ignorar campo desconhecido na identidade (porta do drift silencioso) e ❌ **não** assinar os campos na identidade (muda o id de **todo** bundle, quebra a cadeia de delta e força re-sync snapshot dos **702** arquivos do estado ativo).

### Aceite — nos DOIS espelhos, senão não conta
1. **Wagner (com slug):** lote com host referenciando `_ds/<slug>/colors_and_type.css` pousa com a ref convertida e **0 byte** de `_ds/**` escrito.
2. **Felipe (sem slug):** mesmo lote com `_ds/colors_and_type.css` + `_ds/styles.css` **pousa igual** — hoje isso dá `throw`. É o bite que prova "funciona nos dois".
3. **BITE ausência:** apagar `prototipo-ui/design-system/_ds_bundle.js` ⇒ lote **recusado** citando path e sha exigido.
4. **CONTROLE positivo:** o mesmo lote com o DS completo **passa** (sem ele, "recusa sempre" ficaria verde no item 3).
5. **BITE divergência:** mutar 1 byte do `colors_and_type.css` do espelho ⇒ **RELATO**, exit 0, com o texto dizendo que o espelho pode estar à frente.
6. **BITE frescor pós-transform:** rodar `--compare-bundle` depois da aplicação ⇒ **SYNC** (é o que prova que a conversão não cegou o alarme); e remendar 1 byte do host no espelho à mão ⇒ **STALE**.
7. **T5:** remover uma entrada de `dsRequires` faz a contagem cair **nomeando** o arquivo; remover a entrada de `transforms` faz a ref chegar **não convertida** e o item 6 reprovar.

### Simulação das 6 possibilidades (feita ANTES de pedir — não por pacote)
Testes que hoje decidem se um lote vive: **(a)** gate de ref literal · **(b)** `dsRuntimeRelPath` (slug + whitelist) · **(c)** escrita no DS (dono) · **(d)** bytes-check / `--compare-bundle` · **(e)** R4 de bytes duplicados (as 4 fontes) · **(f)** funciona para o Felipe.

| opção | falha em | por quê, medido | veredito |
|---|---|---|---|
| **O1** indireção `__OI_DS_BASE__` no design (status quo) | **f** | host do Felipe tem 3 refs literais **sem slug** ⇒ lote recusado e `dsRuntimeRelPath` lança | cobra cada dono a cada refresh de bind |
| **O2** conversão na aplicação (`dsRequires`+`transforms`) | — | passa nos 6 **se** o `transforms` declarar o sha pós-transform; bytes saem por `--exclude '_ds/**'`, que já existe | ✅ **escolhida** |
| **O3** importar `_ds/` na íntegra | **c · e · d** | 10 arquivos sobre **251** = poda; 4 fontes idênticas (45.712 B) reprovam por dupe; tokens/fontes daqui são **mais velhos** ⇒ desfaz o #7456 | recusa por pacote, para sempre |
| **O4** só refresh do binding | **a · b · f** | com `_ds/` batendo, não há o que escrever (c/e passam por acidente), mas o Felipe segue sem rota | complemento necessário, não solução |
| **O5** DS por URL absoluta / `<base href>` | **c · d** | DS sai do grafo e do espelho versionado; `<base>` reescreveria **todas** as ~270 refs relativas do app | descartada |
| **O6** um `_ds/` por dono dentro do espelho | **e** | R4 hasheia tudo em `prototipo-ui/` **inclusive cache ignorado** ⇒ 2 cópias = vermelho garantido; e o caso 9 do teste afirma que `_ds/` **não** pousa sob o dono | descartada |

**Escolha: O2 + O4** — O2 é a única que passa nos 6 e a única que **para de cobrar dos donos**; O4 é o passo separado (refresh do bind) que torna o eixo de bytes inócuo.

### Ordem de aplicação (para não modificar o import de novo)
Enquanto o gate atual (recusa por ref literal) estiver vigente, o **host do Wagner mantém** `__OI_DS_BASE__` — ele passa nesse gate e está aplicado no 25. **Quando o A9 entrar**, a indireção sai numa **única** edição declarada e o host volta a refs literais, que a conversão resolve; o host do Felipe **não precisa ser tocado por ninguém**. Fora dessa ordem, o lote de alguém morre no meio do caminho.

### O que este PR **NÃO** resolve (decisão [W], não máquina)
**Cadência da rota do DS.** A verificação torna DS defasado visível; não atualiza nada. Quem escreve `design-system/` é o export do projeto DS (#7096), que hoje roda **por evento, sem periodicidade** — **236 de 251** arquivos congelados desde 2026-09-09, `ds-mirror-drift` medindo **1 de 251**, e o cache do bind deste lado ainda com os 8 tokens pré-v1.2.0 e as 4 Sans byte-idênticas (45.712 B) contra o git já consertado (**63.020 / 66.740 / 67.060 / 63.012 B**). Falta **dono + gatilho**.

### Lição de classe (vale além deste PR)
**"Não escrever" foi implementado como "não olhar".** A exclusão do `_ds/**` era de **escrita** (dono), mas desceu ao `classificarParaSync` como exclusão de **conhecimento** — e por isso o gerador não tinha de onde tirar a exigência. Mesma forma do meu erro do mesmo dia (concluir que o DS precisava de ref literal porque a máquina de frescor não o media): **ausência de medição virando ausência de existência**. Regra: ao barrar a escrita de algo, declarar **quem passa a ler** — senão a barreira apaga o dado junto com a permissão.

---

## O que NÃO se automatiza (e não deve)

- **Decisões [W]:** alocação/label no sidebar · motor do gantt (`@svar-ui/react-gantt` × `.fj-g-*`: 163 dependências viram setas) · alvo de toque em ERP denso · quais capacidades entram.
- **Merge de `.tsx`** — humano por ADR 0283.
- **Qual seção entra na onda** — é julgamento; automatizar julgamento é o erro que o resto do protocolo evita.
- **Dizer "está igual"** — continua sendo `design-diff --compare --check` nos dois renders (T7), que o A3 **alimenta** mas não substitui.

## Bloqueio herdado que o A3 depende

O `--compare` desta área é **medição órfã**: aborta com *"exige um snapshot.json existente"*. O A3 precisa de um job que **gere e versione o snapshot** por seção — sem isso, T7 segue não-afirmável por ninguém.


## Anexos do PR-A8 — REMOVIDOS em 2026-09-09 (eram cópia de máquina envelhecendo)

Este arquivo carregava o `playbook.schema.json` e o `placar-indice.mjs` **inteiros, inline** (A8.1 e A8.2, geradas de cópias locais em 2026-09-06). As duas eram **máquina do repo em cache** — e envelheceram: o `main` de hoje (lido 2026-09-09) já tem `constituicao`/`nota_caminho` no topo do schema, `custo`/`afeta` em `decisoes` e `descobrirIndices` no placar (#7063 + #7071). Descer estes anexos **desfaria os dois** — foi o defeito que recusou o handoff (3) e o bundle v2, e o playbook `patrimonio` reprovando contra a versão velha foi o controle positivo.

**A fonte é o `main`, e só ele:**
- `prototipo-ui/design-docs/cowork-inbox/_schema/playbook.schema.json`
- `prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.mjs`

O PR-A8 continua igual: **estende** `scripts/qa/placar.mjs` com `--indice` reusando o que já está lá. Nada a colar daqui — quem executa lê os dois arquivos no `main` no turno. Ver §6-bis do `COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md` (lote fecha por inclusão; máquina do repo invalida o lote).
