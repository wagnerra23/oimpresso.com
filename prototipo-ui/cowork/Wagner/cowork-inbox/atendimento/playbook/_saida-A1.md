---
sessao: "A1"
titulo: "ALVO atendimento--caixa-unificada--index — saída da thread"
autor: "[CL]"
data: 2026-10-06
base: origin/main 1ab4ab51b1
thread: A1-alvo-contrato.md
veredito: "entregue — alvo da rota inbox medido (8 seções, 0 ausentes), 3× byte-idêntico; secao-check conforme."
---

# _saida-A1 · Alvo da Caixa Unificada

## Medida

| tela | slug | rota no protótipo | âncora (`ancora.mjs`) | seções | estado |
|---|---|---|---|---|---|
| Caixa Unificada | `atendimento--caixa-unificada--index` | `inbox` → `window.InboxPage` (`app.jsx:876`) | `Atendimento/CaixaUnificada/Index` → `inbox-page.jsx` (bundle · visual_source) | 8 · header · lista_cabecalho · busca · saude · lista · thread_cabecalho · mensagens · composer | **medida** |

Arquivos: `governance/design/targets/atendimento--caixa-unificada--index.{secoes,alvo}.json`. O `.alvo.json` sai do `alvo.mjs --alvo`, não foi editado à mão.

Só o lado protótipo. O lado produção logado não entrou nesta thread; nenhum token foi usado.

## Como foi medido

- Sanidade antes: `node scripts/design-sync/alvo.mjs --selftest --browser` → **25/25 ok**.
- Espelho servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`, raiz = `MIRROR_DIR`) na porta 5583.
- Sonda à parte na rota `inbox`: `data-theme="dark"`, `window.__oiLazyDone === true`, `window.__route === "inbox"`, 640 nós.
- Seletores colhidos com `alvo:mapa --rota inbox` nas raízes `.main-body > *`, `.om-shell`, `.om-list-c` e `.om-thread-c`, no DOM vivo.
- `alvo.mjs --alvo http://127.0.0.1:5583/ --tela atendimento--caixa-unificada--index --rota inbox --secoes …/atendimento--caixa-unificada--index.secoes.json --quieto-ms 2000` · viewport 1280×900. Duas vezes com `--saida` e uma no destino: as três com sha256 `db29a9c7deba1516…`.
- `base.assinatura` contém "Templates Filas Canais Broadcast Guia" — é o build **depois** da thread 00 (antes era "Troubleshooters Trilhas").
- `node scripts/qa/secao-check.mjs --tela atendimento--caixa-unificada--index --url http://127.0.0.1:5583/`: **conforme**, 8 seções.

## O `design.json` não é compartilhado, mas está velho

A ficha pede conferir que `design.json` é da rota `inbox`. `governance/design/targets/medidas/Atendimento--CaixaUnificada--Index/design.json` é da Caixa (a assinatura é a do inbox, não de outra tela). Só que ela ainda traz "Troubleshooters Trilhas" no topo, ou seja, foi medida **antes** da thread 00. Não regravei: está fora do prefixo `atendimento--caixa-unificada--index.*` desta thread.

## Ressalvas medidas

1. **Abas móveis fora do alvo.** `nav.om-mobile-tabs` existe no DOM mas tem `display: none` em 1280 px. Não entrou como seção; medir em viewport móvel é outra medida.
2. **Contexto fechado.** A 1280 px a casca é `.om-shell.no-ctx`: o painel de contexto não está aberto (a `_saida-00` registra que ele abre sozinho só em ≥1440). O alvo não tem seção de contexto.
3. **Dois `<main>` no documento** na rota `inbox` (`querySelectorAll('main').length === 2`): o do shell e o `main.om-thread-c`. Vai contra C3 / AP9 ("um `<main>` por documento"). Correção é no build do Cowork (C12), não pedido para produção.
4. **Banner de saúde é dado do mock.** A seção `saude` mede o `.om-health-banner.warn` que o protótipo mostra com o canal degradado. Na produção ele só aparece quando um canal está degradado; a thread 05 decide se entra no contrato.

## Provas do json conferidas

`json_com_chaves` (`secoes`) em `governance/design/targets/atendimento--caixa-unificada--index.alvo.json`: existe, 8 seções.

## Fora do escopo

- `00-INDICE.md` não foi editado. As ressalvas acima são para ele e para a thread 05.
- A tabela "Alvos exportados" do `governance/design/targets/README.md` não foi tocada.
- Prefixo tocado: `governance/design/targets/atendimento--caixa-unificada--index.*` e este `_saida-A1.md`.
