---
sessao: "A1"
titulo: "ALVO officeimpresso--licencas--index + remedir Logs Index/Timeline — saída da thread"
autor: "[CL]"
data: 2026-10-01
base: origin/main 0754c7213
thread: 05-alvos.md §A1
veredito: "entregue PARCIAL — alvo de Licenças medido (4 seções, 0 ausentes, 2× byte-idêntico, secao-check conforme); remedir Logs Index/Timeline = NÃO MEDI, com a causa medida."
---

# _saida-A1 · Alvos do Officeimpresso

## Decisão usada
D2 ainda aparece `respondida: false` no índice, que é do Cowork e não foi reescrito. A resposta
está em `_DECISOES-W-2026-10-01.md` (no main): **tela nova no Officeimpresso**
(`Officeimpresso/Licencas`). Ela não é redirecionar/fundir/aposentar, então não houve paridade a
comparar. Rota do protótipo, portanto, `oi-licencas` (e não `suporte`).

## O que saiu

| tela | slug | rota no protótipo | seções |
|---|---|---|---|
| Licenças | `officeimpresso--licencas--index` | `oi-licencas` → `OfficeimpressoPage view=licencas` → `ViewLicencas` (`officeimpresso-page.jsx`, app.jsx:890) | 4 · header · toolbar · grade · rodape |

Arquivos: `governance/design/targets/officeimpresso--licencas--index.{secoes,alvo}.json`. O
`.alvo.json` é saída do `alvo:medir`, não editado à mão.

## Como foi medido
- Espelho servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`, raiz = `MIRROR_DIR`) na porta 5571.
- Seletores colhidos com `alvo.mjs --mapa --rota oi-licencas --raiz '.os-page.sa-page'` no DOM vivo: `div.sa-ph · div.sa-toolbar · div.os-table-wrap · div.sa-pag`. O protótipo não tem `data-contract` nessa tela; os seletores vão por classe.
- `node scripts/design-sync/alvo.mjs --alvo http://127.0.0.1:5571/ --tela officeimpresso--licencas--index --rota oi-licencas --secoes governance/design/targets/officeimpresso--licencas--index.secoes.json --quieto-ms 2000`, duas vezes. sha256 idêntico nas duas: `968d07079891a3a9…`. `nos_totais` 736, `ausentes: []`.
- Viewport 1280×900, tema dark (default do `alvo.mjs`, que espera `__oiLazyDone` e 2000 ms sem mudança no nº de nós).
- `node scripts/qa/secao-check.mjs --tela officeimpresso--licencas--index --servir-espelho`: **conforme** (4 seções).

## Remedir Logs Index/Timeline — NÃO MEDI

Não regravei `governance/design/targets/medidas/Officeimpresso--Logs--{Index,Timeline}/`. O que
segue é medido, não suposto:

1. **Contra que vista o `design.json` foi tirado (a pergunta do índice §1).** Os dois arquivos têm
   o mesmo blob `0369a411b2dd`, e a `assinatura` começa em *"Empresas licenciadas · 9 empresas"*:
   é a vista **`empresas`**, não a de log. A causa está no driver: `design-diff-lote.mjs --dry
   --tela Officeimpresso/Logs/Index --incluir-comparadas` deriva `shell route=officeimpresso`
   (app.jsx:889 → `view="empresas"`). A rota certa é `oi-log` (app.jsx:893), e ela renderiza a vista
   de log (`alvo --mapa --rota oi-log`: `sa-ph · sa-kpis--4 · oi-note · oi-periodo · sa-toolbar …`).
2. **O conserto do lado design fica fora do prefixo desta thread.** O driver só aceita a rota certa
   via override `governance/design/targets/roles/Officeimpresso--Logs--Index.json` com
   `{ "token": "oi-log" }`. O prefixo da A1 é `medidas/Officeimpresso--*`, e o driver ainda reescreve
   `medidas/RESUMO.md` (fora do prefixo). Gravar só o `design.json` à mão seria um instrumento paralelo
   ao dono (LC-19).
3. **O lado prod não é medível daqui.** O driver loga pela `/_visreg-login`: não há app local em
   `127.0.0.1:8000` (curl → 000), e no staging a rota respondeu 404 sem `VISREG_LOGIN_TOKEN`, que
   não está catalogado em `memory/_INDEX-SECRETS.md`. Remedir só o design e deixar o `prod.json` de
   2026-09-18 ao lado produziria um `resultado.json` de duas épocas.
4. **Timeline não tem vista própria no protótipo.** O cabeçalho do `officeimpresso-page.jsx:6` mapeia
   `licenca_log/index + timeline → view "log"`, e a rota viva é parametrizada
   (`licenca_log/timeline/{licenca_id}`, `Routes/web.php:53`). "Um `design.json` por tela", para a
   Timeline, hoje seria o mesmo da `oi-log` — ou a timeline do drawer da licença
   (`officeimpresso-page.jsx:717`), que é outro alvo (com `--clicar`). Isso é decisão do Cowork.

**Para quem pegar (thread 07 ou a sessão-mãe):** criar o override `roles/Officeimpresso--Logs--Index.json`
(`token: "oi-log"`, e `tableRow` em `__DD_ROLES` nos dois lados, como a ficha pede) e rodar o
`design-diff-lote --tela Officeimpresso/Logs/Index` com o app vivo (CI do `visual-regression` ou
staging com o token).

## Provas do json conferidas
- `governance/design/targets/officeimpresso--licencas--index.alvo.json` com a chave `secoes` — existe.

## Pendente (não inventado)
1. Remedir Logs Index/Timeline — acima.
2. Vista `computadores` (Por empresa), drawer da licença e BulkBar de Licenças não medidos (a vista padrão é a tabela).
3. A tabela "Alvos exportados" do `governance/design/targets/README.md` não foi atualizada (fora do prefixo).
4. Errata do índice (para o Cowork): D2 deve ir a `respondida: true` (`_DECISOES-W-2026-10-01.md`); o §1 "contra que vista não se sabe" agora tem resposta: vista `empresas`.

## Placar
entregue 1 de 1 alvo · 4 de 4 seções medidas · ausentes 0 · remedir 0 de 2.

## PR
O PR que adiciona este arquivo — branch `claude/officeimpresso-thread-A1`.
