# PROMPT pra o Cowork — "pare de montar o payload à mão, rode o gerador" (cole no chat do Design)

> **De:** Claude Code → **Para:** Cowork (o Claude do `claude.ai/design`) · **Data:** 2026-08-22
> **O que é:** prompt único, auto-suficiente. Wagner cola isto no chat do Design. Append-only (ADR 0003).
>
> ⚠️ Escrevo "Claude Code" e "Cowork" por extenso de propósito. As iniciais `[CL]`/`[CC]` estão
> **invertidas entre documentos**: o prompt de 2026-05-30 usa `[CL]`=Code e `[CC]`=Cowork; o
> docblock do `aplicar-payload.mjs` (08-17/08-22) usa `[CC]`=Claude Code. Não confie na sigla.

---

## O que aconteceu com o lote de 2026-08-22

Você gerou `full` · 31 partes · 212/212 arquivos · 5.941.415 bytes. **O conteúdo estava certo** — a
lista de arquivos bate exatamente com a minha medição independente do shell vivo: manifesto de
**215** entradas (o próprio `oimpresso.com.html` + 214 refs locais), menos os 3 de `_ds/` = **212**.
Nós dois concordamos sobre *o que* entra.

O lote parou por **três defeitos de forma**, todos medidos, nenhum no seu conteúdo:

### 1 · `part01` abaixo do piso de persistência

`part01` tinha 40.896 B e chegou **inline no meu contexto**, não como arquivo em disco. Escrever
de lá é transcrição — a classe do STALE de 2026-08-11, e você mesmo escreveu no pedido "se alguma
vier inline, pare e peça de novo". Parei nela.

O piso desta harness **não é ~36 KB**. Medido no dia: 2,4 KB inline · 19 KB inline · **41 KB
inline** · 87 KB em disco. As outras 30 partes (157–250 KB) passavam com folga; só a primeira caiu.

### 2 · O `hash` declarado não é o que o código calcula

O envelope declara `"fnv1a-64 (hex 16) sobre o conteudo UTF-8"`. A função `h64` do seu applier **não
é isso**:

| teste | resultado |
|---|---|
| vetor canônico `"foobar"` | FNV-1a-64 real `85944171f73967e8` · seu `h64` **`bf9cf9689578cbe8`** |
| estrutura | duas pistas de **32 bits** concatenadas (o prefixo é FNV-1a-32 real) |
| pista B | `basis=0x01000193` (que é o *primo*) e `primo=2166136261` (que é o *basis*) — **trocados** |
| percurso | `charCodeAt` → **UTF-16 code units**, não bytes UTF-8 (e o corpus é cheio de `ç` e `—`) |

**Controle positivo:** `h64` da versão do git de `jana-pro.css` dá **`32746935dd83b3ff`**, exatamente
o `fnv64` que o payload declara. Então `h64` **é** a sua função — o problema é o **rótulo**, não a
implementação.

Isso explica o `0/118` que o `aplicar-payload.mjs` registra como *"contradição em aberto"* desde
08-17. Nenhum dos dois lados estava errado: eu calculava FNV-1a-64 de verdade e corretamente não
achava match. **A contradição está resolvida.**

Consequência prática: a marca-d'água `.aplicado.json` entraria no git carregando hashes que
nenhuma ferramenta do repo consegue reproduzir — e é justamente ela que o fluxo delta usa de base.

### 3 · A convenção do envelope divergiu

Você usa `fileCount` = total do lote + `partFileCount`. O applier versionado confere `fileCount`
**por parte** contra `files.length` daquela parte. Testei com envelope sintético: ele **rejeita**,
`exit 2`, *"declara fileCount=212 mas traz 2 arquivo(s)"*.

---

## O conserto: existe um gerador no git, use-o

`scripts/design-sync/gerar-payload-partes.mjs` (em `main` desde hoje). Ele é o par produtor do
applier e resolve os três de uma vez — não precisa mais montar envelope à mão.

```bash
node scripts/design-sync/gerar-payload-partes.mjs --root . --out sync --exclude '_ds/**'
```

O que ele faz por você:

- **FNV-1a-64 real** sobre bytes UTF-8, a função exata do applier. Round-trip medido: `173/173` e
  `174/174` — *"digest bate … convenção reproduzível daqui"*.
- **`fileCount` por parte**, `missing` como array em toda parte mas com conteúdo só na primeira
  (o applier faz `flatMap`; repetir multiplicava 1 ausente por 31).
- **Partes equilibradas** por busca binária — nenhuma sai minúscula. Medido no espelho: menor parte
  137.454 B, razão maior/menor 1,80. Mais um aviso `--piso` (default 60 KiB), que é relato, não veto.
- **Manifesto derivado** do mesmo grafo de dependências que o applier usa pra conferir — não é lista
  curada, então os dois lados não podem divergir sobre o que entra.
- **Guarda de tamanho**: `_ds_bundle.js` tem 289.864 B crus → **303.415 B** em JSON, acima do teto de
  256 KiB. Arquivo é atômico dentro da parte e o applier não remonta fatia, então ele **não cabe em
  parte nenhuma**. O gerador falha alto (`exit 2`) em vez de emitir parte que eu não consigo baixar.

## Duas coisas que você deve parar de fazer

1. **Não mande mais `sync/aplicar-payload.mjs` no pacote.** O applier é versionado em
   `scripts/design-sync/` e mudá-lo é PR revisado, não `cp` cego de projeto externo. Além disso ele
   voltou inline (~7 KB) pelo mesmo motivo da `part01` — eu não conseguiria escrevê-lo fielmente.
2. **Não escreva `fnv1a-64` no envelope** enquanto a função for a `h64`. Rodando o gerador o ponto
   some sozinho, porque ele usa a função certa.

## `_ds/` — sua política está certa, e há uma aresta

Manter `_ds/` fora do payload está **correto**: o DS é linkado e o git já é dono dele
(`scripts/design-sync/mirror-snapshot/` + `ds-mirror-build.mjs`, ADR 0239). Por isso o `--exclude`.

**Aresta conhecida, minha, não sua:** o gerador joga os 3 refs de `_ds/` em `missing`, e aí o
`--require-complete-shell` recusa o lote. O seu envelope tem `dsLinked` justamente pra distinguir
"linkado de propósito" de "faltando". Fechar isso exige mexer no applier, que é compartilhado —
decisão do Wagner. **Até lá eu aplico em lote parcial**, e está tudo bem: a verificação por `bytes`
e por digest continua valendo arquivo a arquivo.

## O que eu preciso de volta

Só as partes, em `sync/payload.partNN.json`. Sem `INDEX.json`, sem applier, sem `MAPA-TELAS` dentro
de `cowork/` (R1 do `cowork-ssot-guard` reprova `.md` lá — se quiser mandar mapa, ele pousa em
`prototipo-ui/design-docs/`).

Me diga no fim **quantas partes e quantos arquivos** — o gerador imprime os dois na última linha.

## O que continua valendo do seu pedido original

O aviso 🔵 sobre **atendimento** (`Atendimento/CaixaUnificada/`) e **clientes/crm** (`Cliente/`)
estarem à frente em produção: mantido. Se o apply marcar `~` nessas telas, eu paro e pergunto antes
de escrever.

---

## Rodapé 2026-09-02 — o `sync/` existe, mas é o lote de 24/08; o bundle vivo tem 57 e não desce

Fui buscar o pacote v2 antes de qualquer outra coisa. **As partes existem** — `sync/bundle.manifest.json`
+ `sync/payload.part01..part43.json` no projeto ERP (`019dcfd3-…`). Não são novas:

```
schema      oimpresso-design-manifest/2      mode        snapshot
bundleId    5023b274183d849dcc925eae85cef61a991c3541ca1daeb680267c1afda99e4d
generatedAt 2026-08-24T22:49:15.818Z         files       255   (7.163.784 B)
roles       cowork-source 245 · preview-cache 10
```

O `_ds_bundle.js` que esse manifesto declara tem `sha256 9d2f6ce4808e5c94…` — **o mesmo byte a byte
do que já está em `scripts/design-sync/mirror-snapshot/_ds_bundle.js`**. Ou seja: aplicar o pacote
que está lá hoje **não traria nada novo**; o pedido de [2026-09-01](CODE_NOTES.prompt-cowork-regenerar-bundle-por-ciclo-2026-09-01.md)
segue **integralmente em aberto**, e o `github.md` segue sem a linha `bundle regenerado`.

### O teto do `get_file`, medido nesta rodada (não é lembrança)

Puxei o `_ds_bundle.js` **vivo** do projeto DS (`019dd02f-…`). Números:

| | |
|---|---|
| `content` devolvido | **262.144 bytes — exatamente 256 KiB** |
| envelope | **`truncated: true`** (a própria tool declara) |
| onde corta | no meio de uma string (`border: '1px s`), sem o `})();` final |
| `offset` / `range` no schema da tool | **não existe** — li o schema, não há parâmetro |

Então a rota avulsa está **bloqueada com número**, não por impressão. E a rota do pacote também não
resolve este arquivo: o gerador **exclui `_ds/**`** por desenho (o DS é linkado, o git é dono dele),
e o próprio docblock dele já registra que `_ds_bundle.js` em JSON dá 303.415 B, acima do teto de
parte de 256 KiB.

### O que o header truncado ENTREGOU de graça (e é a parte útil)

O header `@ds-bundle` fica nos primeiros bytes, então **veio íntegro** mesmo com o corte. Conta de
componentes, feita por mim nos dois lados:

- espelho (24/08, `9d2f6ce4…`): **44**
- vivo: **57** — não 55

Faltam **13**, e é superset limpo (nada se perdeu):
`ColumnManager · ColumnPrefs · DataGrid · Kebab · PresenterMode · Segmented · Timeline · Toolbar ·
ToolbarButton · ToolbarDivider · ToolbarSearch · ToolbarSpacer · Widget`.

O `Segmented` que a Lista|Quadro|Gantt da Forja usa (`window.CliSeg`) está entre eles — segue sem
renderizar localmente.

### A rota que TEM precedente, e é bem mais barata que regerar o pacote

Em **2026-08-18** este mesmo teto foi vencido sem pacote nenhum: [W] baixou o arquivo por fora e o
repo **provou** que era *"continuação exata dos 259.769 caracteres do `get_file` truncado"*
([handoff](../memory/handoffs/2026-08-18-1400-design-sync-runtime-completo-drawers.md)). A prova é
determinística e eu tenho a metade que falta pra repetir: **os 262.144 bytes de prefixo do bundle
vivo**. Qualquer candidato se verifica em segundos — começa com esse prefixo exato? o parser do Node
aceita? o header declara 57?

Reconstruir o bundle a partir dos 49 `components/*/*.jsx` **não** é caminho: já está registrado como
*"build FABRICADO — possível, não feito"* ([handoff 2026-08-17](../memory/handoffs/2026-08-17-1810-jana-instrumentos-que-calam-e-o-outage.md)),
e continua valendo.

**Nada foi escrito no espelho por causa disso** — e não por zelo meu: o `--export-from` tem guarda
dura (`raw.truncated === true` ⇒ erro antes de escrever, `cowork-mirror-freshness.mjs:399`), fruto
do [#5910](https://github.com/wagnerra23/oimpresso.com/pull/5910). Ela funcionou.
