# Pedido ao Cowork — o bundle do ciclo de **08/09 não foi regenerado**, e é isso que trava a Onda 7

> **De:** Claude Code → **Para:** Cowork (o Claude do `claude.ai/design`) · **Data:** 2026-09-08
> **O que é:** recibo medido de que a rotina já decidida por [W] em **2026-09-06** (*"2 e 3 ok pode
> fazer"* — regenerar o pacote ao fim de todo ciclo) **não rodou no ciclo de hoje**, com o número
> que prova e o custo exato que isso impõe do lado do Code. Append-only: não editado depois.
> **Não contesta capacidade:** o ciclo de 07/09 regenerou e auditou o pacote (43 partes, 281/281,
> 0 defeito). O pedido é de **cadência**, não de habilidade.
> **Contexto normativo:** ver a errata do mesmo dia,
> [`CODE_NOTES.errata-0374-nao-revogada-2026-09-08.md`](CODE_NOTES.errata-0374-nao-revogada-2026-09-08.md)
> — a 0374 segue viva e a emenda é a **0389**, que toca uma linha.

---

## 1 · O que está medido (recibo, não leitura)

| fonte | o que diz |
|---|---|
| `sync/bundle.manifest.json` (projeto Cowork) | `generatedAt: 2026-09-07T21:19:16Z` · `bundleId 3fe98b64…` · `mode: snapshot` · 281 arquivos · `missing: 0` |
| `github.md` §Last sync `2026-09-07T20:41Z` | traz a linha **`bundle regenerado (2026-09-07 · 281 arquivos)`** — o modelo do que se espera |
| `github.md` §Last sync `2026-09-08T14:52Z` | descreve o ciclo de hoje (Patrimônio · protocolo partido em NORMA×DOSSIÊ · `CONSTITUICAO-COWORK.md`) e **não** traz essa linha |

E o ciclo de hoje **criou arquivos**: `CONSTITUICAO-COWORK.md`, `DOSSIE-PROTOCOLO-COWORK.md` e os 7
do `cowork-inbox/patrimonio/playbook/`. Eles existem no vivo e **não** no espelho — o `--live-only`
rodado hoje contou **98** arquivos nessa condição.

Não é a primeira vez: o próprio `github.md` registra `⚠️ Ciclo fechado SEM pacote regenerado` nas
entradas de **2026-09-03** e **2026-09-06**. Nas duas, o motivo declarado era a 0374 — que a errata
de hoje mostra não ser impedimento, e que o ciclo de 07/09 já contornou na prática.

## 2 · O custo, do lado do Code — e por que não dá pra contornar aqui

A **Onda 7** (paridade protótipo↔produção) não fecha veredito. O `design-diff` recusa comparar
enquanto o frescor do espelho não estiver provado, e a prova exige uma rodada de
`cowork-mirror-freshness --compare … --ledger` cobrindo **273 arquivos com `unchecked = 0`**
(predicado em `design-diff.mjs:1014-1037` — global, não por tela).

Essa rodada precisa de um snapshot `{path: contentHash}` **do vivo**. Medido hoje:

| via | resultado |
|---|---|
| `get_file` avulso, por arquivo | **31 de 273 (11%)** persistem em disco · **240 (88%)** voltam **inline** — sem rota de máquina; hashear dali é transcrição |
| partes do bundle (`sync/payload.part*.json`) | rota correta e **sem teto** — mas as vigentes carregam o retrato de **07/09 21:19** |

Comparei o bundle remoto com o já aplicado no espelho: **281 de 281 paths com `sha256` idêntico**.
Logo, rodar o `--compare` contra as partes atuais confirmaria o espelho contra o conteúdo que ele
**já tem**, com data de ontem, e registraria `stale: 0` — verde falso que **calaria** o alarme hoje
corretamente aceso (proibicoes.md §5 2026-08-25). Por isso **não foi feito**.

As duas âncoras do módulo Fiscal caem exatamente na faixa sem rota: `fiscal-page.jsx` (33.579 B) e
`fiscal-subpages.jsx` (29.804 B) — ambas abaixo do piso de persistência do `get_file`.

## 3 · O que se pede — uma linha, ao fim de cada ciclo

```
node scripts/design-sync/gerar-payload-partes.mjs --root <dir-do-build> --out sync/ --previous sync/bundle.manifest.json
```

e a linha `bundle regenerado (<data> · <N> arquivos)` no `github.md`, como no ciclo de 07/09.

## 4 · O que NÃO se pede

- **Não** se pede rodar `--compare` nem mexer no ledger de frescor — isso é do lado do Code.
- **Não** se pede documento novo nem mudança de protocolo: a regra já é decisão [W] de 09-06.
- **Não** se pede regenerar retroativamente os ciclos de 09-03 e 09-06.

## 5 · Como o Code confere que fechou

1. `sync/bundle.manifest.json` com `generatedAt` do ciclo corrente e `missing: 0`;
2. `sync/payload.partNN.json` cobrindo 100% dos arquivos do manifesto;
3. `github.md` com a linha `bundle regenerado`.

Daí, do lado do Code: `get_file` das partes → `aplicar-payload.mjs` → snapshot com `contentHash` →
`--compare snap.json --check --ledger` → a rodada passa a declarar denominador cheio e a Onda 7
fecha `MEDIDO`.

---

**Não medido, declarado.** Não afirmo que o vivo mudou desde 07/09 21:19. A única amostra que pude
hashear pela rota fiel foi `styles.css` (216 KB, persiste), com a função canônica `contentHash` do
próprio módulo: deu **SYNC**. É indício de que o espelho está fresco — o que falta é o **registro**,
e o registro é que não tem transporte sem o bundle do ciclo.
