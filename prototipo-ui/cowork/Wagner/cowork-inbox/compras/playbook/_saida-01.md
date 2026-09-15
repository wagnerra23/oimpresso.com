---
sessao: "_saida-01"
thread: "01 · Rede: 2 specs E2E do módulo Compras"
dono: "[CL]"
data: 2026-09-13
prefixo_tocado: e2e/compras-cockpit.spec.ts · e2e/purchase-create.spec.ts
base_lida: wagnerra23/oimpresso.com@main (PR #7249)
natureza: RECIBO DE RECONCILIAÇÃO — ver §0
---
# _saida-01

## 0 · Por que este recibo não foi escrito pela sessão executora

A thread rodou em 2026-09-13, quando o `cowork-inbox/` **não existia no repo**: a #7224 o havia
apagado junto com `design-docs/`, e a `R3` do `cowork-ssot-guard` ainda recusava `.md` aninhado
sob `cowork/<dono>/`. Não havia onde gravar `_saida`. O executor deixou o recibo no **corpo do
PR**, que era o caminho disponível. A ADR 0398 devolveu a pasta; este arquivo traz aquele recibo
para o lugar canônico e **diz de onde veio** — nenhuma afirmação aqui é nova.

Fonte: [PR #7249](https://github.com/wagnerra23/oimpresso.com/pull/7249) (mergeado).
Esta thread é a única das 8 sem session log próprio em `memory/sessions/`.

## 1 · Feito

`e2e/compras-cockpit.spec.ts` e `e2e/purchase-create.spec.ts` criados, imitando as specs vizinhas
de `e2e/` (o playbook proíbe inventar um segundo jeito de escrever E2E).

## 2 · Provas — medidas em 2026-09-14 contra `origin/main`

As **5 provas de arquivo** do §7 do índice passam. Medição reproduzível:

```
node prototipo-ui/cowork/Wagner/cowork-inbox/_scripts/placar-indice.mjs --indice \
  prototipo-ui/cowork/Wagner/cowork-inbox/compras/playbook/00-INDICE.md --root .
```

⚠️ O placar só carimba `feito` com este arquivo presente — era exatamente o que faltava.
