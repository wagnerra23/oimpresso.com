---
sessao: "A1"
titulo: "ALVO modulos--index — saída da thread"
autor: "[CL]"
data: 2026-10-06
base: origin/main bab78f4764
thread: A1-alvo.md
veredito: "entregue — alvo da rota modulos medido (5 seções, 0 ausentes), 3× byte-idêntico; secao-check conforme."
---

# _saida-A1 · Alvo do Gerenciador de Módulos

## Medida

| tela | slug | rota no protótipo | âncora (`ancora.mjs`) | seções | estado |
|---|---|---|---|---|---|
| Gerenciador de Módulos | `modulos--index` | `modulos` → `window.ModulosPage` (`app.jsx:812`) | `Modules/Index` → `modulos-page.jsx` (related_prototype do charter) | 5 · header · kpis · busca · filtros · tabela | **medida** |

Arquivos: `governance/design/targets/modulos--index.{secoes,alvo}.json`. O `.alvo.json` sai do `alvo.mjs --alvo`, não foi editado à mão.

Só o lado protótipo. O lado produção logado não entrou nesta thread; nenhum token foi usado.

## Como foi medido

- Placar antes: `placar.mjs --indice …/modulos/playbook/00-INDICE.md --thread A1` → `proximo`.
- Sanidade antes: `node scripts/design-sync/alvo.mjs --selftest --browser` → **25/25 ok**. `protocolo.config.mjs --selftest` → OK.
- Espelho servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`, raiz = `MIRROR_DIR`) na porta 5591.
- Sonda à parte na rota `modulos`: `data-theme="dark"`, `window.__oiLazyDone === true`, `window.__route === "modulos"`, 1493 = 1493 nós em duas leituras com 1,5 s entre elas, 1 `<main>`, 32 linhas na tabela. Antes do `__oiLazyDone` a contagem ainda subia (1381 → 1417): por isso a espera pelo sinal, não por tempo.
- Seletores colhidos com `alvo:mapa --rota modulos` nas raízes `.mod-page` e `.mod-toolbar`, no DOM vivo. Todos por classe própria, nenhum posicional.
- `alvo.mjs --alvo http://127.0.0.1:5591/ --tela modulos--index --rota modulos --secoes …/modulos--index.secoes.json --quieto-ms 2000` · viewport 1280×900. Duas vezes com `--saida` e uma no destino: as três com sha256 `e6ace284c6cb9e03…`. `ausentes: []`.
- `node scripts/qa/secao-check.mjs --tela modulos--index --url http://127.0.0.1:5591/`: **conforme**, 5 seções.

## Ressalvas medidas

1. **Cabeçalho é o `PageHeader` do DS (`.oi-ph`), não o `os-page-h`.** O `modulos-page.jsx:275` escolhe `.oi-ph` quando `window.PH` existe e cai no `<header class="os-page-h">` só como reserva. No espelho servido o `PH` existe; o alvo mede o `.oi-ph`.
2. **Mock com 32 ativos, 0 inativos, 0 com erro.** O KPI "Com erro" está no alvo com valor 0 — o estado aceso da thread 01 (P2) não aparece nesta medida. Medir o card aceso exigiria outro mock, não outra sonda.
3. **Drawer fora do alvo.** `os-drawer.mod-drawer` só monta após clique numa linha. Se uma onda precisar dele, é outra medida com `--clicar`.
4. **`medidas/Modules--Index/resultado.json` segue `NÃO MEDI`** (2026-09-18, identidade da view não provada). Está dentro de `governance/design/targets/`, mas é a comparação prod×proto de outro mecanismo, não o alvo; não regravei.

## Provas do json conferidas

`arquivo` `governance/design/targets/modulos--index.alvo.json`: existe, 5 seções.

## Fora do escopo

- `00-INDICE.md` não foi editado.
- A tabela "Alvos exportados" do `governance/design/targets/README.md` não foi tocada.
- Prefixo tocado: `governance/design/targets/modulos--index.*` e este `_saida-A1.md`.
