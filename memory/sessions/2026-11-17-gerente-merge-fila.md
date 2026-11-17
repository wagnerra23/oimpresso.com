---
date: "2026-11-17"
topic: "Gerente de merge — fila de 28 PRs (lotes A–D)"
---

# Gerente de merge — 2026-11-17

Autorização [W]: "merge todos" (elegíveis: não-draft, required verde, sem conflito). Cada merge: `--squash`, espera do deploy, baseline `curl` de 9 rotas em prod antes e depois.

Baseline (estável a sessão inteira): `/` 200 · `/login` 200 · `/catalogue/1/1` 200 · `/nfe-brasil/tributacao`, `/product-catalogue/catalogue-qr`, `/fisio`, `/home`, `/ponto`, `/financeiro` 302 (sem sessão).

## Resultado

| Lote | Mergeados por esta sessão | Mergeados por sessão paralela | Fora |
|---|---|---|---|
| A docs/chore | #11020 #10982 #11060 | #11059 #11029 | — |
| B superadmin | #11064 #11056 #11051 #11050 #11049 #11057 | — | — |
| C NFSe/sells/produção | #10988 #11069 #11067 #11062 #11061 #11023 #10952 | — | — |
| D Fisio | — | #10460 (após eu resolver o conflito) | — |
| extra | #11070 (aberto por esta sessão) | — | — |

Todos os deploys `success`; baseline igual após cada um. **Zero reverts.**

Não mergeados: 8 drafts (#10953 #10998 #10999 #11000 #11001 #11002 #11003 #10491) — comentados; **#10888** (`public/.htaccess`, saiu de draft mas estava na lista de não-mergear) — comentado, decisão [W]; #11071 (novo, fora do snapshot).

## Achados

1. **Main vermelho na lane required `Sells · Pest (MySQL)`** desde `b3f7c20c3` (2026-11-16 19:23): `FsmCatalogoProcessosContratoTest` ancorava a frase do catálogo "marca cancelada sem olhar a resposta da Sefin", que o #10987 tornou falsa. Bloqueava #11029 e #10952. Consertado no **#11070** (catálogo + protótipo + assert), lane verde no próprio PR.
2. **Flake UC-NFTR-64** (`tests/js/nfe-natureza-lancamento.test.tsx`) derrubava o Vite build de PRs não relacionados (#11059, #11057). O #10988 é o conserto; mergeado primeiro do lote C.
3. **#10460**: conflito só no `SUPERFICIE.md` gerado; resolvido regenerando com `module-surface.mjs Fisio --write` (`--check` sem drift). Smoke em biz=4 pedido no enunciado **não** foi feito: R6 proíbe biz=4 em smoke e o Fisio não é módulo da Larissa; valeu o teste de isolamento 98×99 do PR + baseline `/fisio`.

## Erro meu (instrumento)

Parei um loop de merge em background e reiniciei; o processo parado já tinha disparado o merge do #10988 e continuou gravando no mesmo arquivo de baseline — o loop novo leu um arquivo intercalado e acusou "BASELINE MUDOU" (parada dura). Re-medido: idêntico. Corrigido dando nome único por execução (`pre-$n-$$.txt`). A parada dura funcionou como devia; o falso alarme veio do instrumento, não de prod.
