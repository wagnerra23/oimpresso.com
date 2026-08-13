---
date: "2026-08-13"
hour: "20:56 BRT"
topic: "Espelho Cowork — o medidor que media a si mesmo, o caminho banido que eu reconstruí, e 13 duplicatas com 7 defasadas na raiz de prototipo-ui/"
authors: [C]
prs: [5754, 5757, 5758]
related_adrs:
  - 0374-emenda-0315-espelho-cowork-e-rota-prevista
  - 0324-frescor-espelho-cowork-dispatch-sla-limite-plataforma
  - 0325-import-prototipo-designsync-pull-direto
  - 0315-design-sync-claude-design-vs-cowork-charter
outcomes:
  - "tautologia do --export-from exposta: ledger passa a gravar stalePreExport"
  - "--snapshot-from separa MEDIR de CONSERTAR; ciclo provado contra o vivo com hash real"
  - "13 duplicatas removidas da raiz (7 defasadas) + 28 ponteiros vivos reapontados"
  - "LC-19 n+2 e LC-08 n+4 catalogados; lapide sec5 sobre construir no lugar banido"
---

# Espelho Cowork — separar MEDIR de CONSERTAR, e a limpeza que virou achado de âncora podre

## TL;DR

Três PRs mergeados. O fio condutor: **um medidor que media a si mesmo**, e o preço de medir
muito sem medir a coisa certa.

1. **#5754** — adversário achou 7 defeitos no medidor de frescor que eu tinha acabado de
   estender. O pior: o `--export-from … --emit-snapshot` **escreve o espelho antes de medir**,
   então o `--compare` seguinte dava SYNC por construção. Eu tinha publicado *"11 medidos, 0
   stale"* como notícia boa — era eco do meu próprio export; **2 arquivos ESTAVAM stale**
   (`styles.css`, `inbox-page.jsx`) e foram consertados antes da medição.
2. **#5757** — [W] perguntou *"se eu alterar algo no protótipo vai enxergar?"*. Para responder
   com honestidade, precisei **separar medir de consertar** (`--snapshot-from`). No caminho,
   construí `--snapshot-from-tree` lendo do `~/Downloads/_cowork-handoff-staging` — **lugar que
   [W] baniu em 07-01** e que eu tinha citado na mesma sessão. Revertido, e o protocolo
   reconciliado ([W]: *"não existe mais zip, é direto o protocolo"*).
3. **#5758** — organizando `prototipo-ui/`, descobri que **13 dos 15 arquivos soltos na raiz
   eram duplicata** do espelho e **7 estavam defasadas**. E que o charter do Sells tinha **dois
   ponteiros discordando**: `related_prototype` certo, `visual_source` apontando pra cópia velha.

## O que aconteceu, em ordem

### 1 · A tautologia (#5754)

O adversário nomeou 7. O que importa é o mecanismo: `--export-from` grava o conteúdo do vivo e
**só então** emite o snapshot que o `--compare` consome. Verde garantido. É a mesma doença do
drift-sentinel tautológico (§5 2026-07-17: *"se todos os pontos são idênticos, o MEDIDOR é o
problema"*), e a ADR 0324 trata o ledger como evidência de aceite.

Conserto: o número que faltava já existia no processo (`tally.ATUALIZADO`). Agora viaja no
snapshot como `_stalePreExport`, o `--compare` o repassa e o ledger grava — a rodada distingue
*"estava em dia"* de *"acabei de arrumar"*. Bite: fixture velha→nova ⇒ `stalePreExport: 1`.

Outros 6, resumidos: `_ds/` inflava o denominador (124→122, reproduzido); `conferirIdsNoRepo`
tinha 3 vetores de fuga por `continue` silencioso; **2 dos 5 alvos de ID eram FP por construção**
— e a razão veio de [W]: `venda-v3.css` e `Sells/CreateV3.tsx` são do **[L]/[M], de outra conta
de design**, e estão corretos (virou `FORA_DESTA_CONTA` no protocolo); duas justificativas que
eu tinha **inventado** foram reescritas com número medido; `--check-novos` era órfão e foi ligado
no chokepoint real (o próprio `--export-from`); LC-10 no cabeçalho.

### 2 · Medir ≠ consertar, e o caminho banido (#5757)

A pergunta de [W] era irrespondível: `--emit-snapshot` só existia acoplado ao `--export-from`.
`--snapshot-from` mede sem tocar. **Provado contra o vivo, com hash real:**

```
baixei styles.css do Cowork  →  "igual"            →  --check exit 0
alterei o espelho            →  "STALE styles.css" →  --check exit 1   ← ENXERGOU
reverti                      →                     →  --check exit 0
```

**O teto que apareceu ao medir:** `get_file` acima de ~64KB o harness persiste em disco (o script
lê, ninguém transcreve); abaixo volta **inline**, e aí só chega ao disco passando pelo agente —
transcrever, proibido pela ADR 0374. Medido: **18 de 191** passam de 64KB. Os 15 que desceram no
#5743 eram **todos pequenos**.

**O erro:** concluí que a saída era ler o projeto exportado do disco, e apontei pro
`~/Downloads/_cowork-handoff-staging`. O `ancora-guard` lista esse path em `PROIBIDOS` desde
2026-07-01 ([W]: *"não pode trocar de lugar nunca"*) — e **eu tinha citado esse guard na mesma
sessão**. Medi o repo inteiro e não medi a decisão. Lápide no §5.

### 3 · A organização que virou achado (#5758)

[W]: *"os 3 vai precisar movelos sim"*. Medindo antes de mover:

| | |
|---|---|
| soltos na raiz de `prototipo-ui/` | 15 |
| **duplicata do espelho** | **13** |
| **divergentes (raiz atrás)** | **7** |
| `oficina-page.jsx` raiz × espelho | 46.145 × 72.619 bytes — **−26KB** |

Provado por hash contra o vivo: espelho `1259f30cf94a` == VIVO; raiz `d556575b2340` **defasada**.

**O achado que dói:** `Sells/Index.charter.md` declarava `related_prototype:
prototipo-ui/cowork/vendas-page.jsx` (certo) **e** `visual_source: prototipo-ui/vendas-page.jsx`
(a cópia velha). Idem no Caixa. E `visual_source` **não é decorativo** — é lido por `ancora.mjs`,
`detectar-telas.mjs`, `charter-blueprint-pointers.mjs` e 3 workflows.

Feito: 13 duplicatas removidas (−17,5k linhas), 28 ponteiros vivos reapontados com padrão
ancorado (`(?<!cowork/)`) e teste de identidade por arquivo; registros datados **não** tocados.

**Teste pós-merge (browser, não olho):** os 13 removidos seguem carregando **200 OK** (o shell os
serve do espelho), **0 imports quebrados**, **0 erros JS**, app monta com 885 elementos, sidebar
`oklch(0.21 0.025 295)` dark-fixo canon, `window.JanaPage` existe — a fiação que abriu a sessão
está curada.

## Erros meus, catalogados

- **LC-19 (n+2)** — construí a ferramenta que lê do lugar banido, com a proibição no meu contexto.
  Lápide §5 2026-08-13.
- **LC-08 (n+4)** — afirmei *"3 arquivos são âncora de charter"* medindo o **nome**, não o path;
  o grep casava `prototipo-ui/cowork/…` também. Os `related_prototype` já estavam certos. A
  afirmação errada quase virou razão pra **não** fazer o trabalho certo.
- **Hipótese derrubada pela própria medição** (registrada porque funcionou): supus que os 71
  `.tsx` do espelho inflavam o denominador do frescor. Medi: entram **0** no manifesto (o walk só
  lê `.jsx/.css/.js/.html`). São peso morto que confunde, **não** defeito de máquina — o que
  rebaixou a urgência de "conserto" para "clareza", e a decisão para [W].

## Guard que NÃO nasceu (FP medido antes)

O `cowork-ssot-guard` promete *"não existe dupla-fonte fora dela"* e passou **verde** em tudo
acima — varre só dentro de `prototipo-ui/`. Fora dali há 11 arquivos de design em
`resources/js/Pages/Financeiro/_cowork-bundle/`. A regra sintática óbvia dá **24 hits**, com ~5
FP **por construção** (fixtures de teste precisam da cópia) e 19 cópias **declaradas**
(`_BACKUP-NAO-USAR-…`, bundle com README). Não é decidível pelo path — família das 4 lápides de
guard sintático. O limite ficou **escrito no cabeçalho do guard, com o número**.

## Aberto (chips criados)

1. **113 UNCHECKED** do espelho — medir contra o vivo, começando pelos >64KB
2. **101 arquivos** que o Cowork já arquivou (69 `.tsx` + 32 sob `_arquivo/`) — decisão [W]
3. **Skill `aplicar-prototipo`** ainda manda importar por ZIP
4. **2 ponteiros podres** pré-existentes (`financeiro-app.jsx`, `fiscal-page.css`)
5. **Conflito da ADR 0374** — rota canônica incumprível em 91% do acervo; decisão [W]

E o `related_us` do `Sells/Caixa`: o `charter-us-lint` (advisory) ficou vermelho porque **meu
toque acordou** dívida grandfathered. **Não inventei a US** — ela não existe no SPEC, e a tela
está em rascunho aguardando aprovação de [W].
