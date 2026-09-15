---
sessao: "02"
titulo: query ambígua deixa de sortear charter (candidatos + exit 2)
dono: "[CL]"
base: 752041ac450d
prefixo: prototipo-ui/ancora.mjs
nao_toca: resources/js/Pages/** · .claude/hooks/** · scripts/**
depende: "01 (mesmo arquivo — remedir o sha antes de escrever)"
---
# 02 · o `✓` que é sorteio

## A · IDENTIDADE (ancoragem dupla)
- **âncora (código):** `prototipo-ui/ancora.mjs` :: `norm()` + o loop `for (const cf of charters)` dentro de `resolveAncora`, e o `return { ok:false, motivo:'sem charter pra essa tela …' }`.
- **oráculo:** os 24 charters de `resources/js/Pages/Ponto/**` (21 com `related_prototype`, medidos neste turno) — provam a ambiguidade sem precisar de leitura de conteúdo.

## B · NÃO INVENTAR
- **Zero regra de "adivinhar melhor".** Não pontuar, não ordenar por similaridade, não escolher "o mais provável". A resposta certa pra query ambígua é **a lista + erro**, não um palpite melhor.
- **`norm()` fica como está** (o strip de `/index` faz `Ponto/Espelho/Index` casar forte com `.../Espelho/Index.tsx`, que é correto). O que muda é o que se faz com **múltiplos fracos**.
- **Zero mudança nos consumidores.** `design-coverage`, `ancora-guard` e `integrity-check` consomem `--list`, não a query — declarar isso no PR.

## C · O DEFEITO MEDIDO
`norm('Ponto/Index')` → `"ponto/index"` → o `replace(/\/index$/i,'')` derruba o sufixo → **`q = "ponto"`**. Aí o loop aceita match fraco por `comp.includes(q)` e `relc.includes(q)`; **todos** os 24 charters do Ponto casam; `hit` é sobrescrito a cada volta e o **último na ordem de `walk`** vence. Nenhum `break` (nenhum match forte existe: não há `Pages/Ponto/Index.tsx`), nenhum aviso, exit **0**, selo `✓`.

O mesmo vale pra qualquer query de módulo (`Cliente`, `Fiscal`, `Financeiro`) e pra qualquer substring — `q` de 4 letras casa por `includes`.

**Por que dói:** o consumidor humano leu "âncora ✓" e abriu **um** arquivo. A ferramenta que existe pra impedir "escolher no olho" devolveu uma escolha no escuro com selo de medição. É a família LC-10 (artefato afirmando o próprio estado), aqui no eixo da **query**.

## D · COMO VALIDAR
1. Match **forte** (page igual, ou `comp.endsWith(q)`) → comportamento **idêntico** ao de hoje, exit 0. Nada de regressão em `/financeiro/unificado` nem em `Fixture/Index` (os dois já no selftest).
2. **Um** match fraco e nenhum forte → resolve como hoje, exit 0, mas a saída **diz** que foi match fraco e por qual critério.
3. **Dois ou mais** fracos e nenhum forte → **não escolhe**: imprime `query ambígua: N charters casam` + a lista de candidatos (`page` + caminho do charter, ordenada, teto de ~10 com "e mais N") + o que fazer (usar a rota ou o caminho `.tsx`) e sai **2** (uso), nunca 0.
4. Nenhum match → segue `ok:false` + exit 1, mensagem intacta ("NÃO invente âncora; registre ou pergunte").
5. A recuperação de query mangleada pelo MSYS continua funcionando (o selftest já tem o BITE) — a ambiguidade **não** pode transformar aquele caminho em erro.
6. Selftest: `BITE ambiguidade` (fixture com 3 charters do mesmo módulo, query = nome do módulo → ambíguo, sem escolha) + `CONTROLE ambiguidade` (query com match forte no MESMO fixture → escolhe, exit 0) + `CONTROLE ambiguidade: 1 fraco ainda resolve`.
7. `--selftest` verde. PLACAR no corpo do PR.

## 4-ter · EXECUÇÃO
- **ARQUIVOS A EDITAR:** `prototipo-ui/ancora.mjs` — **só este**.
- **REUSAR:** `norm`, `frontmatter`, `walk`, `raizesDePages`, a estrutura de retorno (`{ok, query, charter, telaViva, ancoras, repoRoot, aviso}`) — acrescentar `candidatos` e `forca` é aditivo.
- **CRIAR:** nada.
- **NÃO TOCAR:** charters, hooks, workflows, `--list`.
- **PASSO A PASSO:** 1) remedir sha · 2) trocar o `hit` único por coleta de `{charter, fm, forca}` · 3) forte único → como hoje · 4) fracos: 1 resolve com aviso, ≥2 não resolve e sai 2 com a lista · 5) selftest com bite + os dois controles · 6) `--selftest`.
- **DADO:** nenhum.
- **PARAR SE:** exit 2 na ambiguidade quebrar algum chamador que você encontre no repo (`git grep 'ancora.mjs'` antes de escrever — eu li 32 citações, quase todas em prompt/`.md`/workflow, mas **não** auditei cada uma) — aí **pare e reporte** qual, com o path e a linha.

## PRÉ / PÓS
- **antes:** `Ponto/Index` devolve um charter arbitrário com `✓`, exit 0.
- **depois:** devolve a lista dos candidatos e exit 2; `/financeiro/unificado` e `Fixture/Index` inalterados.
- **quebra:** se o loop já coleta candidatos, **não execute** — reporte e pare.

## PROVA
`prototipo-ui/ancora.mjs` contém `candidatos` + `BITE ambiguidade` + `CONTROLE ambiguidade` · `--selftest` verde · `_saida-02.md`.
