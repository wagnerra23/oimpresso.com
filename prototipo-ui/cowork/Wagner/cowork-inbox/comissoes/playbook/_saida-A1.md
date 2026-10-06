---
sessao: "A1"
titulo: "ALVO comissoes — rota comissoes medida — saída da thread"
autor: "[CL]"
data: 2026-10-06
base: origin/main 1ab4ab51b1
thread: A1-alvo.md
veredito: "entregue — 1 alvo medido (7 seções, 0 ausentes), 3 runs byte-idênticos; secao-check conforme."
---

# _saida-A1 · Alvo de Comissões

## Feito

| tela | slug | rota no protótipo | seções | estado |
|---|---|---|---|---|
| Apuração de comissão | `comissoes` | `comissoes` → `window.ComissoesPage` (`app.jsx:808`, `comissoes-page.jsx`) | 7 · header · abas · kpis · filtros · aviso · tabela · nota | **medida** |

Arquivos:
- `governance/design/targets/comissoes.secoes.json` (entrada; seletores do `alvo:mapa` no DOM vivo)
- `governance/design/targets/comissoes.alvo.json` (saída do `alvo.mjs --alvo`, não editado à mão)

## Como foi medido

- Sanidade antes: `node scripts/design-sync/alvo.mjs --selftest --browser` → **25/25 ok**.
- Espelho `prototipo-ui/cowork/Wagner` servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`) na porta 5591.
- Sonda à parte, viewport 1280x900: `data-theme="dark"`, `window.__oiLazyDone === true`, `window.__route === "comissoes"`, contagem de nós **636 = 636** em duas leituras com 1,5 s de intervalo.
- `alvo:mapa --rota comissoes --raiz '.main-body > *'`: `div.oi-ph · nav.ds-tabbar.oi-rota-tabs · div.usr-kpis · div.usr-toolbar · div.cmi-aviso · div.os-table-wrap · p.cms-note`. Os 7 seletores usam essas classes, ancorados em `.main-body > .cms-page`.
- `alvo.mjs --alvo http://127.0.0.1:5591/ --tela comissoes --rota comissoes --secoes …/comissoes.secoes.json --quieto-ms 2000`: 2 runs com `--saida` e o 3º no destino. sha256 (16 primeiros) `a06390ed5f12624b` nos três. `nos_totais` 636, `ausentes: []`.
- `node scripts/qa/secao-check.mjs --tela comissoes --url http://127.0.0.1:5591/` → **conforme** (7 seções), exit 0.

## Não feito e por quê

- Lado produção: não pedido nesta thread (a Page React vem na thread 02). Nenhum token usado.
- `provas` da thread A1 no `00-INDICE.md` está vazio; não editei o índice (é do Cowork). Se quiser a prova mecânica: `json_com_chaves` em `governance/design/targets/comissoes.alvo.json`, chave `secoes`.

## Descobertas que mudam outra sessão

1. **Nenhum charter declara `comissoes-page.jsx` como âncora hoje.** A thread 02 precisa declarar `related_prototype` no charter da Page nova, senão o `ancora.mjs` não liga a tela a este alvo.
2. **A rota do protótipo é "Apuração de comissão", sob as abas de Usuários** (`OiRotaTabs`: Usuários · Funções e permissões · Comissionados · Apuração de comissão), não sob Relatórios. A thread 02 mira `Report/SalesRepresentative`: o encaixe de navegação diverge do protótipo e é decisão do Cowork/[W].
3. **O protótipo traz um aviso explícito** (`cmi-aviso`): "Nada disso existe no backend hoje" — agente na venda, regra por agente (fixa/faixa/margem), fechamento por período e título a pagar. Isso cruza com D-COM-1 (fica só no legado?): a seção `aviso` do alvo existe porque o protótipo a desenha, não porque a produção deva ter.
4. O toggle "Só vendas faturadas / Incluindo a receber" está no protótipo (seção `filtros`) e é exatamente a pergunta D-COM-2.

## Prefixo tocado

`governance/design/targets/comissoes.{secoes,alvo}.json` + este `_saida-A1.md`. Nada mais.
