# Handoff 25 — IMPORTADO. E o critério de aceite tinha um item impossível

> **De:** Claude Code → **Para:** Cowork + [W] · **Data:** 2026-09-17 · Append-only.
> Sucede [CODE_NOTES.handoff-24-recusado-por-grafo-inbox-photo](CODE_NOTES.handoff-24-recusado-por-grafo-inbox-photo-2026-09-17.md).
> Insumo: `…-handoff (25).zip`. **Promovido:** bundle `1949443e` · 705 arquivos · 16 mudanças de
> transporte · 162 telas · 0 bloqueadas.

---

## 1. O 25 é byte-idêntico ao 24 no host

```
zip 24: 36106 B  sha=4e2614fde6f84413
zip 25: 36106 B  sha=4e2614fde6f84413
```

O conserto do host **já tinha descido no 24**, e o bundle regerado prova (mesmo id `2fd44e1f…`,
mesmo delta). Quando o relatório diz *"meu conserto desce no próximo export `--full-tree`"*, está
descrevendo o **espelho** — que de fato tinha o host velho de 35.008 B, porque eu havia recusado o
import. Não faltava export; faltava o import passar.

## 2. O critério de aceite: 2 de 3 itens atendidos, 1 impossível

| item pedido | veredito |
|---|---|
| host tem `window.__OI_DS_BASE__` | ✅ **4 ocorrências** |
| **nenhuma** ocorrência de `_ds/office-impresso` | ❌ **impossível** — ver abaixo |
| `inbox-photo-c1…c4.png` existem | ✅ 38.823 · 38.495 · 36.602 · 38.458 B |

A única ocorrência de `_ds/office-impresso` está na **L17**, e é o **fallback do próprio ternário**:

```js
window.__OI_DS_BASE__ = location.pathname.indexOf('/prototipo-ui/cowork/') > -1
  ? '../../design-system/'
  : '_ds/office-impresso-design-system-019dd02f…/';   // ← esta
```

Exigir zero quebraria o mecanismo **no Cowork**. O critério correto, e que o 25 cumpre, é
**zero refs `href`/`src` literais a `_ds/`** — medido: `0`.

> Sugestão para o próximo ciclo: quando o critério de aceite falar de *ref*, diga qual sonda o
> mede. "Ocorrência no texto" e "ref estática" são números diferentes no mesmo arquivo — aqui,
> `1` e `0`.

## 3. Os PNGs desceram — por decisão [W], não por conserto na origem

O 24/25 adicionou `<link rel="preload" as="image">` para as quatro (L135-138). Isso, com o
critério escrito, é a origem declarando que as fotos **devem** existir — então não haveria
conserto do lado de lá. [W] autorizou a exceção no `.gitignore` em 2026-09-17, **nomeada nos 4
arquivos**, não em glob:

```
!prototipo-ui/cowork/Wagner/inbox-photo-c1.png   … c4.png
```

Bite-test: os 4 ficam versionáveis; `outra.png`, `inbox-photo-c5.png`, `Felipe/x.png`,
`screenshots/a.png` e `foto.jpg` **seguem ignorados** (9/9).

Detalhe medido que corrige uma suposição minha: os `preload` **não** tornam os PNGs visíveis ao
`ABSENT-LOCAL` — o `parseShellDeps` filtra por extensão (`jsx|css|js`), vê 274 deps e **zero**
PNG. Quem os enxerga é só o grafo do bundle.

## 4. ⚠️ Dois defeitos do NOSSO lado que este pacote expôs — os dois consertados

### 4.1 O import apagou 10 arquivos do Design System

O 25 remove o cache `_ds/` do projeto de telas — legítimo **daquele** lado. Mas
`targetForLogical` mapeia `_ds/<id>/x` → `prototipo-ui/design-system/x`, e a transação aplicou o
**delete** no destino mapeado: sumiram `colors_and_type.css`, `cockpit_domains.css`,
`_ds_bundle.js` e as **7 fontes** — incluindo as 4 `ibm-plex-sans` distintas que custaram três
pacotes para chegar.

O passo [4] do `receber-handoff` já protegia a **escrita** por dono; faltava o **delete**. Agora
`preview-cache` nunca é apagado por export de telas — a regra vale por dono, não por direção.
Restaurado por `git checkout` (nada havia sido commitado) e provado por mutação: desligada a
proteção, o assert cai; religada, o DS sobrevive **e** a remoção de path de tela continua
efetivada (controle negativo).

### 4.2 O shell dinâmico criava 3 deps fantasma

`document.write('<link href="'+window.__OI_DS_BASE__+'colors_and_type.css"/>')` fazia o
`parseShellDeps` capturar o **miolo da expressão JS** como se fosse path. O `ABSENT-LOCAL` passou
a acusar 3 deps FALTANDO que não existem como arquivo nenhum — vermelho permanente por FP.

Filtro novo descarta ref com aspas, crase, `+` ou `${`. FP medido no shell real: **274 deps → 3
descartadas, 271 mantidas, e as 271 existem no espelho** (zero falso-negativo).

## 5. Estado

```
bundle ativo        1949443e · 705 arquivos
host do espelho     36106 B · sha 4e2614fd = o do zip
DS                  intacto (0 arquivos alterados)
ds-token-diff       divergências de VALOR: 0
ds-token-version    311 tokens · em dia
gates               ssot · sla · css-refs · unverified · absent-local ·
                    compare-bundle --check · protocolo --selftest · 7 suítes — todos rc=0
```
