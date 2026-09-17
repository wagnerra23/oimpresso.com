# Handoff 24 — a correção do shell CHEGOU e está certa; o que recusa o lote são 4 PNGs

> **De:** Claude Code → **Para:** Cowork (o Claude do `claude.ai/design`) + [W] · **Data:** 2026-09-17
> **O que é:** devolutiva de uma importação que **não foi promovida**. Append-only.
> Insumo: `Oimpresso ERP Conunicação Visual.-handoff (24).zip` (780 entradas, CRC-32 conferido em
> todas), entregue por [W] no chat. Regra que decide o desfecho:
> [ADR 0406](../../decisions/0406-o-que-ultimo-importado-decide-emenda-0404.md) **D3** — *o achado
> vai para a origem*.

---

## 1. O que o pacote 24 traz, e está CERTO

O delta é pequeno e é exatamente a correção que eu tinha pedido no
[#7471](https://github.com/wagnerra23/oimpresso.com/pull/7471):

```
modified (2):  github.md · oimpresso.com.html
deleted (10):  _ds/<id>/**  (o cache inteiro do DS sai do espelho)
```

O shell passou a resolver a base do DS **em runtime, por ambiente**:

```js
window.__OI_DS_BASE__ = location.pathname.indexOf('/prototipo-ui/cowork/') > -1
  ? '../../design-system/'   // servido do REPO  → o DS versionado
  : '_ds/office-impresso-design-system-019dd02f…/';  // servido do COWORK → o bind
```

Isso resolve o problema 3 do #7471 (*o shell apontava pro `_ds/`, que é ignorado por design e não
existe no repo, então o preview local não carregava o DS*) **sem** duplicar o DS e **sem** precisar
que o repo materialize cache. É a solução certa, e o `deleted: 10` é consequência dela.

## 2. O que recusa o lote — e não é do pacote

```
BLOQUEADO: missing (4): inbox-photo-c1.png … c4.png
```

O `payloadDependencyGraph` fecha o grafo a partir do shell. Ele alcança `inbox-page.jsx`, que
declara as 4 fotos como dado de contato:

```js
{ id: "c1", …, photo: "inbox-photo-c1.png", name: "Renato Lopes", … }   // :166, :176, :185, :193
```

Os 4 arquivos **não podem existir no espelho**: o `.gitignore` raiz exclui
`prototipo-ui/cowork/**/*.png`. Então o grafo não fecha, o gerador emite `BLOQUEADO`, e o
consumidor recusa o lote inteiro — corretamente, porque o espelho **está** incoerente.

## 3. As duas datas que decidem de quem é o conserto

| fato | data | medido em |
|---|---|---|
| a regra `*.png` do espelho existe | **2026-06-23** ([#3259](https://github.com/wagnerra23/oimpresso.com/pull/3259), "SSOT Cowork") | `git log -S "*.png" -- prototipo-ui/cowork/.gitignore` |
| `inbox-page.jsx` passou a referenciar as 4 fotos | **2026-09-11** ([#7224](https://github.com/wagnerra23/oimpresso.com/pull/7224)) | `git log -S "inbox-photo-c1.png" -- …/inbox-page.jsx` |

A dependência é **3 meses mais nova que a proibição**. Ela nasceu morta do lado de cá: o render do
inbox está com 4 imagens quebradas desde 11/09, e nenhuma máquina viu — o `--css-refs` só varre
CSS, e o `ABSENT-LOCAL` só olha `link`/`script` do shell. Referência a imagem **dentro de JSX** não
tinha vigia; quem a expôs foi o grafo do bundle, e só depois que o
[#7470](https://github.com/wagnerra23/oimpresso.com/pull/7470) tirou os PNGs do estado-alvo.

> ⚠️ **Errata minha, e ela foi para dois PRs já mergeados.** No #7470 e no #7471 eu escrevi que o
> `.gitignore` *"ganhou `prototipo-ui/cowork/**/*.png` em 15/09 (#7314)"*. **Falso.** O #7314
> **moveu** a regra de `cowork/Wagner/.gitignore` para a raiz, para cobrir o `Felipe/` que ficara
> descoberto — o próprio corpo dele diz que as regras são *"byte-idênticas"* e que *"0 dos 677
> arquivos versionados sob cowork/ passa a casar"*. A regra é de **23/06**. O desfecho dos dois PRs
> não muda (o conserto está certo e o FP medido vale), mas a atribuição da data estava errada.

## 4. O que a origem precisa decidir

O conserto primário é do lado Cowork, porque foi lá que a dependência nasceu. Três formas, em
ordem de preferência minha — e a escolha é de vocês:

1. **Aplicar a mesma técnica do `__OI_DS_BASE__` às fotos.** Vocês acabaram de resolver
   exatamente esta classe de problema para o DS: path que só existe no Cowork, resolvido por
   ambiente. As fotos são o mesmo caso.
2. **Embutir como `data:` URI** no `inbox-page.jsx` (são avatares de mock, ~152 KB somados) — o
   grafo passa a não ter dep externa e o render funciona nos dois lados.
3. **Trocar por placeholder** gerado em CSS/SVG, se o realismo da foto não for o ponto.

## 5. O que NÃO fiz, e por quê

Medi a quarta saída — **abrir exceção no `.gitignore`** — e ela **funciona**:

```
com `!prototipo-ui/cowork/Wagner/inbox-photo-*.png` + os 4 arquivos:
  [5] REGERAR   DELTA: +4 ~2 -10 =699
  [6] VALIDAR   dry-run VALIDADO        (exit 0)
```

Não a apliquei porque ela **muda uma regra de política do repo** que vige desde junho e hoje não
tem nenhuma exceção (`grep -c '^!' .gitignore` no HEAD: 2, nenhuma sob `prototipo-ui/`). Isso é
soberania [W], não escolha de técnica — e a ADR 0406 D4 proíbe reconciliar à mão o que a regra
recusou. O experimento foi revertido inteiro: `.gitignore` restaurado por `git checkout`, os 4
arquivos removidos, `git status` limpo, `git check-ignore` de volta a `IGNORADO`.

## 6. Estado, para quem retomar

- **Nada foi promovido.** O bundle ativo segue `615462e0` (711 arquivos), do pacote 23.
- O pacote 24 fica pendente até (a) a origem tratar as 4 fotos, **ou** (b) [W] decidir a exceção.
- Quando destravar, a rota é a de sempre: `receber-handoff --zip <24> --conta w --apply`.
