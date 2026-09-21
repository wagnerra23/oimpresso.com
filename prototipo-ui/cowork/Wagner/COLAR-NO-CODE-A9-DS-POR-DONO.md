# COLAR NO CODE — A9 · DS por DONO, convertido na APLICAÇÃO

> **Thread única, sessão limpa.** Norma e backlog ficam em `COLAR-NO-CODE-AUTOMACAO-DO-PROTOCOLO.md` (PR-A9); este arquivo é o **pedido** — auto-suficiente, teste do estranho. Quem executa não precisa do chat.
> **Decisão [W] 2026-09-17:** *"cada um fica com seu `_ds` próprio; quando for aplicar no Code, faz a conversão"*. 1 thread · 1 PR · prefixo `scripts/design-sync/` + `scripts/governance/`.
> **Proveniência das afirmações:** tudo abaixo foi **lido no `main`** (árvores `e286562ee818`/`5b3f52bab27b`) — `gerar-payload-partes.mjs` (14.239 B), `bundle-contract.mjs` (14.132 B), `aplicar-payload.mjs`, `aplicar-payload.test.mjs`, `cowork-mirror-freshness.mjs` (170.011 B), `payload-dependency-graph.mjs`, `Felipe/venda-v3/index.html`. **Não li:** `bundle-transaction.mjs` (o gate de ref literal de 17/09 — descrito pelo [CL], não medido por mim) e `importar-bundle.mjs::classificarParaSync` (li só o uso no gerador).

## A · PAPEL E DONO (§18 — papel, nunca classe)

```
PAPEL   · o pacote do PROTÓTIPO declara de que DS depende; o applier CONVERTE a referência
          na aterrissagem e VERIFICA a presença. Ninguém escreve no DS por esta porta.
DONO    · scripts/design-sync/gerar-payload-partes.mjs        (emitir dsRequires + transforms)
          scripts/design-sync/aplicar-payload.mjs             (converter + verificar)
          scripts/design-sync/bundle-contract.mjs             (shape-check, FORA da identidade)
          scripts/governance/cowork-mirror-freshness.mjs      (dsRuntimeRelPath + sha pós-transform)
          scripts/design-sync/bundle-transaction.mjs          (gate de ref literal: inverter papel)
ESCRITA · prototipo-ui/design-system/ segue SÓ pela rota do projeto DS (#7096). Inalterado.
```

## B · O PROBLEMA, EM NÚMEROS (não repetir a descoberta)

> **Quem mediu o quê:** os números do lado do design (10 `preview-cache`, sha e bytes das fontes, tokens, refs do host) foram **medidos por [CC] nesta sessão**. Os do repo marcados **[CL]** (251 arquivos em `design-system/`, 236 congelados, 702 do bundle ativo) vieram do relato dele registrado no `github.md` de 16–17/09 — **não remedidos por mim**. E `throw`/whitelist abaixo são conclusão de **leitura de código**, não de execução: nenhum `node` rodou deste lado.

1. **`_ds/` é recorte do bind de cada projeto, e as formas são duas.** Wagner: `_ds/<slug>/…` (slug `office-impresso-design-system-019dd02f-…`; **10** arquivos de papel `preview-cache` no manifesto do lote de 16/09, e em disco 8 entradas na raiz + as fontes em `assets/fonts/`). Felipe: **sem slug** — `prototipo-ui/cowork/Felipe/venda-v3/index.html:38,39,64` carrega `_ds/colors_and_type.css`, `_ds/styles.css`, `_ds/_ds_bundle.js`.
2. **Caminho sem slug LANÇA (por leitura da função, não por execução).** `dsRuntimeRelPath` remove `^_ds/[^/]+/`; `_ds/colors_and_type.css` não casa, cai na whitelist e dá `throw`. Coerente com a árvore: **não existe `_ds/` sob `Felipe/`** — as refs dele estão penduradas no espelho hoje.
3. **`styles.css` não tem destino de runtime.** A whitelist é `_ds_bundle.js | colors_and_type.css | cockpit_domains.css | assets/`. `styles.css` carrega os **componentes** do DS e o host do Felipe depende dele.
4. **Normalizar no lado do design cobra de cada dono.** O gate de ref literal (17/09) recusaria o lote do Felipe; e manter a indireção à mão custou, num dia, 4 ocorrências de ternário no host do Wagner, 1 pacote recusado (26), 2 reverts e uma máquina nova — para 3 linhas de `<link>`.
5. **Importar o `_ds/` na íntegra está reprovado por medição:** 10 arquivos sobre **251** [CL] = poda; as 4 `ibm-plex-sans-*.woff2` daqui são **byte-idênticas** (45.712 B, sha `e2291e84…`) contra **63.020 / 66.740 / 67.060 / 63.012 B** no repo; tokens daqui são pré-v1.2.0 ⇒ desfaria o #7456.

## C · O QUE FAZER (5 passos, nesta ordem)

```
ARQUIVOS A EDITAR   : gerar-payload-partes.mjs · aplicar-payload.mjs · bundle-contract.mjs
                      cowork-mirror-freshness.mjs · bundle-transaction.mjs   (5, nada além)
REUSAR (não recriar): --exclude (glob JÁ existe no gerador) · payloadDependencyGraph ·
                      destinoDoBundle · dsRuntimeRelPath · rawHash · roleForPath
CRIAR               : campos `dsRequires` e `transforms` no manifesto + a verificação no applier
NÃO TOCAR           : a identidade do bundle (`identity` do createManifest) · o roteamento de
                      escrita do `_ds/**` · o host do Felipe · o host do Wagner (ver §E)
```

1. **Bytes do DS fora do lote, por flag existente.** Chamar o gerador com `--exclude '_ds/**'`. Nenhum `preview-cache` viaja. *(Sem código novo: li `casaExclude` e o docblock, que cita o teste com `--exclude '_ds/**/_ds_bundle.js'`; o arquivo de teste em si eu não abri.)*
2. **`dsRequires` por dono.** No gerador, **ler o `_ds/` do disco** e emitir `{ owner, slug|null, arquivos:[{path, sha256}] }` para os arquivos de runtime que o host referencia (+ as fontes que o CSS pede). ⚠️ **É leitura nova:** `classificarParaSync` trata `_ds/` como ruído e o gerador **não** o lê hoje. `slug: null` é a forma legítima do Felipe, não erro.
3. **`transforms` declarado.** `[{ path, regra:'ds-ref', de, para }]` + **o sha pós-transformação** de cada arquivo tocado, calculado pelo gerador. Regra única e determinística: `_ds/<slug>?/x` → caminho relativo a `prototipo-ui/design-system/x`.
4. **Applier: converter e verificar.** Aplicar `transforms` na aterrissagem; verificar `dsRequires` contra `prototipo-ui/design-system/` com **três desfechos e um só bloqueante**:
   - **ausente** (slug/arquivo exigido não existe) ⇒ **recusa o lote**, nomeando path e sha exigido;
   - **sha divergente** ⇒ **RELATO**, exit 0 — o espelho é o **dono** e pode estar **à frente** (caso real: as 4 fontes consertadas lá e velhas aqui), e hash **não diz direção**;
   - **igual** ⇒ silêncio. `dsRequires` ausente (pacote legado) ⇒ `NÃO MEDIDO`, nunca verde por omissão.
5. **Dois furos, no mesmo PR.** `styles.css` **entra** na whitelist do `dsRuntimeRelPath`; caminho **sem slug** passa a resolver em vez de lançar. E o **gate de ref literal inverte de papel**: deixa de recusar e passa a ser o **gatilho** da conversão — recusa só a ref `_ds/` que **não casa** a regra.

## D · A CONDIÇÃO QUE NÃO É DETALHE (por que `transforms` precisa do sha pós-transform)

Converter muda o conteúdo do host na aterrissagem, e **duas máquinas dependem de o arquivo do espelho ser byte-idêntico ao transportado**: o applier confere `bytes` declarado × real e recusa o lote na divergência; o `--compare-bundle` compara `rawHash(disco)` × `sha256` do manifesto — **é o único sinal que pega remendo à mão no espelho** (o caso de 13/08 que passou 4 dias). Conversão **não declarada** ⇒ host **STALE em 100% dos ciclos** e esse alarme morre. Com `transforms` + sha pós-transform: o bytes-check segue valendo no **payload** (pré-transform) e o frescor compara contra o sha **pós-transform**.

**E os campos ficam FORA da identidade.** `createManifest` hasheia `identity = {schema, source, entry, files, missing, mirrorScope?}` e `validateManifest` **recomputa esse conjunto** ⇒ campo novo dentro da identidade reprova por construção (foi a recusa do dry-run). Classificação: identidade = **o que pousa**; `dsRequires`/`transforms` = **pré-condição e regra de aterrissagem** ⇒ metadado ao lado de `mode`/`totals`/`changes`, com shape-check no validador.
❌ **Não** relaxar o validador para ignorar campo desconhecido na identidade (porta do drift silencioso). ❌ **Não** assinar os campos na identidade: muda o `bundleId` de **todo** bundle, quebra a cadeia de delta (`validatedPrevious.bundleId !== manifest.baseBundleId`) e força re-sync **snapshot** dos **702** arquivos do estado ativo.

## E · ORDEM DE APLICAÇÃO (para não matar o lote de ninguém)

Enquanto o gate de ref literal estiver vigente, o **host do Wagner mantém** `__OI_DS_BASE__` — medido no arquivo-fonte: 4 ocorrências, `(href|src)="_ds/` = **0**. Quando este PR entrar, a indireção sai numa **única** edição declarada do lado do design, e o **host do Felipe não é tocado por ninguém**. Fora dessa ordem, alguém morre no meio do caminho.

## E-bis · PRÉ E PÓS-CONDIÇÃO (§13.5 — o `depois` é o `antes` do próximo)

```
antes : manifesto SEM `dsRequires`/`transforms` · dsRuntimeRelPath sem `styles.css` e lançando em
        caminho sem slug · gate de ref literal RECUSANDO · `_ds/` sob `Felipe/` AUSENTE no espelho
depois: manifesto COM os 2 campos (fora da identidade, shape-checados) · whitelist com `styles.css`
        e slug opcional · gate CONVERTENDO · os 7 itens do §F verdes nos dois espelhos
quebra: se o "antes" já não valer (campos existirem, gate já invertido), NÃO executar — reportar
```

## F · ACEITE — nos DOIS espelhos, senão não conta

1. **Wagner (com slug):** lote cujo host referencia `_ds/<slug>/colors_and_type.css` pousa com a ref **convertida** e **0 byte** de `_ds/**` escrito.
2. **Felipe (sem slug):** lote com `_ds/colors_and_type.css` + `_ds/styles.css` **pousa igual** — hoje isso dá `throw` pela leitura do `dsRuntimeRelPath` (confirme com um controle positivo antes de consertar). É o bite que prova "funciona nos dois".
3. **BITE ausência:** apagar `prototipo-ui/design-system/_ds_bundle.js` ⇒ lote **recusado** citando path e sha exigido.
4. **CONTROLE positivo:** o mesmo lote com o DS completo **passa** (sem ele, "recusa sempre" ficaria verde no item 3).
5. **BITE divergência:** mutar 1 byte do `colors_and_type.css` do espelho ⇒ **RELATO**, exit 0, texto dizendo que o espelho pode estar à frente.
6. **BITE frescor pós-transform:** `--compare-bundle` depois da aplicação ⇒ **SYNC**; remendar 1 byte do host no espelho à mão ⇒ **STALE**.
7. **T5:** remover uma entrada de `dsRequires` faz a contagem cair **nomeando** o arquivo; remover a de `transforms` faz a ref chegar **não convertida** e o item 6 reprovar.

## G · PARAR SE

- A leitura de `bundle-transaction.mjs` mostrar que o gate de ref literal **não** é o descrito aqui (eu **não** li esse arquivo) ⇒ reportar e parar antes de invertê-lo.
- `classificarParaSync` já tiver dono/ramo para `_ds/` que eu não vi ⇒ estender aquele ramo, não criar leitura paralela.
- Qualquer passo exigir **escrever** em `prototipo-ui/design-system/` ⇒ parar: é a rota do projeto DS (#7096), e o caso 9 do `aplicar-payload.test.mjs:241` afirma que `_ds/` **não** pousa sob o dono.

## H · O QUE ESTE PR **NÃO** RESOLVE (decisão [W], não máquina)

**Cadência da rota do DS.** A verificação torna DS defasado **visível**; não atualiza nada. **236 de 251** arquivos de `design-system/` congelados desde 2026-09-09 (desceu inteiro 1× no #7096), `ds-mirror-drift` medindo **1 de 251**, e o cache do bind do lado do design ainda com os 8 tokens pré-v1.2.0. Falta **dono + gatilho** (a cada ciclo de token? a cada refresh de binding?). Enquanto não houver, o veredito honesto do import é *"o DS do espelho é o de 09/09"*.

## I · RECIBO (no PR)

`_saida` com: passo a passo feito por caminho · o que não foi feito e por quê · placar dos 7 itens do §F (`entregue X de 7 · ausentes <item> por <motivo>`) · confirmação de que **0 byte** de `_ds/**` foi escrito · e a linha `bundle regenerado (<data> · N arquivos)` no `github.md` quando o primeiro lote pós-A9 descer.
