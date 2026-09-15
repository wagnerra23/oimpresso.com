---
sessao: "03"
titulo: --list prova o arquivo e mede o fallback component
dono: "[CL]"
base: 752041ac450d
prefixo: prototipo-ui/ancora.mjs
nao_toca: scripts/governance/** · resources/js/Pages/** · scripts/qa/**
depende: "02 (mesmo arquivo — remedir o sha antes de escrever)"
---
# 03 · `hasSource` diz que tem fonte sem nunca ter aberto arquivo

## A · IDENTIDADE (ancoragem dupla)
- **âncora (código):** `prototipo-ui/ancora.mjs` :: `listAll()` — a linha `const source = fm.related_prototype || doBundle || mockupJsx(fm.component) || null` e o `rows.push({ page, source, hasSource: !!source, charter, isNa, via })`.
- **consumidor declarado no próprio arquivo:** `design-coverage` (o comentário de 2026-08-26 diz que é **1 de 1** consumidor de `--list --json`). Confirmar no PR com `git grep`.

## B · NÃO INVENTAR
- **`hasSource` não muda de semântica e não sai do JSON.** É guarda: hoje significa "o charter DECLAROU a fonte" (inclusive `n/a` explícito) e o consumidor conta com isso. Os campos novos são **outra pergunta**.
- **Zero extrator novo.** O arquivo já declara a duplicação de 4 extratores (`render-proto-baseline::primeiroToken`, `anchor-content-check::anchorFile`, `::anchorRelPath`) e por que nenhum serve. Use `caminhoDaAncora` — o dono, neste mesmo arquivo.
- **Não medir frescor aqui.** `--list` é inventário de declaração; frescor tem dono (`cowork-mirror-freshness.mjs`).

## C · O DEFEITO MEDIDO
Duas coisas no mesmo `source`:

1. **Nada prova que o valor abre.** `hasSource:true` sai para qualquer string. Um `related_prototype` podre (path velho, arquivo renomeado, prosa sem arquivo) conta como coberto no `design-coverage` — e o comando de 1 tela, que **mede**, diria `⚠️ NÃO MEDIDO` ou `sem arquivo` pro mesmo charter. O `caminhoDaAncora` já classifica os 4 formatos do corpus (55 caminho limpo · 5 com parênteses · 4 com prosa antes · 11 que não nomeiam arquivo) e o `--list` não o chama.
2. **O 3º fallback é tautológico.** `mockupJsx(fm.component)` procura `-page.jsx` no campo que aponta a **própria tela viva** (`resources/js/Pages/.../Index.tsx`). Se casar, a linha declara que a fonte de design da tela é a tela — exatamente o que o charter `Repair/Settings/Index` recusa em prosa: *"ancorar aqui seria ancorar a tela nela mesma"*. **Não medi** quantas linhas saem hoje com `via:'component'`; por isso a remoção fica atrás de `D-COMPONENT` e esta thread entrega **o número**, não a poda.

## D · COMO VALIDAR
1. Cada linha do `--list --json` ganha, **aditivo**: `caminho` (o que `caminhoDaAncora` resolve, ou `null` quando o valor não nomeia arquivo) e `existe` (`true`/`false`/`null` — `null` = não há o que abrir, e **não** é o mesmo que `false`).
2. `hasSource`, `page`, `source`, `charter`, `isNa`, `via` **presentes e inalterados** (guarda; snapshot antes/depois do JSON com os campos antigos idênticos).
3. A saída de texto (`--list` sem `--json`) marca a linha cujo `existe === false` — sem mudar as colunas existentes de posição.
4. O PR **reporta o número**: quantas linhas com `via:'component'`, quantas com `existe:false`, quantas com `caminho:null`. Três contagens, no corpo do PR, não num arquivo novo.
5. `n/a` continua `isNa:true`, `caminho:null`, `existe:null` — declaração legítima nunca vira defeito (135 de 158 charters eram `n/a` em 2026-08-11; tratar como falha seria falso-positivo em massa).
6. Selftest: `BITE list: fonte que nao abre` (fixture com `related_prototype` apontando arquivo inexistente → `existe:false`, `hasSource` segue `true`) + `CONTROLE list: caminho real da o existe true` + `CONTROLE list: n/a nao vira existe false`.
7. `--selftest` verde; `design-coverage` roda e **não** muda de veredito só por causa dos campos novos (se mudar, é sinal de que alguém já consumia campo inexistente — reportar).
8. PLACAR no corpo do PR.

## 4-ter · EXECUÇÃO
- **ARQUIVOS A EDITAR:** `prototipo-ui/ancora.mjs` — **só este**.
- **REUSAR:** `caminhoDaAncora`, `desasparValor`, `ehDeclaracaoNa`, `ehArquivo`.
- **CRIAR:** nada. **Nem** relatório, **nem** json de retrato: as 3 contagens vão no corpo do PR (mapa é comando, não arquivo — ADR 0256).
- **NÃO TOCAR:** `design-coverage.mjs`, `ancora-guard.mjs`, `anchor-content-check` (gate required), nenhum charter.
- **PASSO A PASSO:** 1) remedir sha · 2) ler `listAll` inteiro · 3) somar `caminho`/`existe` sem mexer nos campos antigos · 4) contar `via:'component'` e reportar · 5) selftest bite + 2 controles · 6) `--selftest`.
- **DADO:** nenhum.
- **PARAR SE:** para fazer `existe` funcionar você precisar mudar a raiz de leitura de `--list` (ele roda contra `REPO_DEFAULT`; âncora de staging **não** é assunto desta thread) ou tocar o `anchor-content-check` — **pare e reporte**.

## PRÉ / PÓS
- **antes:** `hasSource` verdadeiro sem prova de arquivo; fallback `component` ativo e não medido.
- **depois:** `caminho`/`existe` no JSON, campos antigos idênticos, 3 contagens no PR, fallback ainda vivo e agora **quantificado** para `D-COMPONENT`.
- **quebra:** se o `--list` já emite `existe`, **não execute** — reporte e pare.

## PROVA
`prototipo-ui/ancora.mjs` contém `existe` + `BITE list: fonte que nao abre` + os 2 controles · `hasSource` intacto (guarda) · `--selftest` verde · `_saida-03.md`.
