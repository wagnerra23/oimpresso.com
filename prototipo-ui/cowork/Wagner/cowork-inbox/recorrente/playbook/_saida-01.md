---
sessao: "01"
titulo: "Contratos dos 4 Index da Cobrança Recorrente — saída da thread"
autor: "[CL]"
data: 2026-10-13
base: origin/main 8fe939db25
thread: 01-contratos.md
veredito: "entregue — 4 contratos derivados dos alvos da A1; 17 âncoras postas só como atributo; mordida provada por mutação nas 4."
---
# _saida-01 · Contratos dos 4 Index

## Entregue
Cada contrato parte do `.alvo.json` da A1 (`governance/design/targets/recorrente--<tela>--index.alvo.json`), não do
`.tsx`. Entram só as seções do alvo que têm contraparte na Page, e só a copy que aparece literalmente nos dois lados:
cada string foi conferida por máquina na `base.assinatura` do alvo, no protótipo (`cobranca-recorrente-page.jsx`) e
no código da Page sem comentário, dentro da faixa de linhas da seção.

| contrato | Page | seções (âncora, em ordem) | copy |
|---|---|---|---|
| `recorrente-assinaturas` | `RecurringBilling/Index` | `cabecalho` · `kpis` · `filtros` · `lista` | 2 · 5 · 12 · 0 |
| `recorrente-planos` | `RecurringBilling/Planos/Index` | `cabecalho` · `kpis` · `ciclos` · `lista` | 1 · 5 · 0 · 10 |
| `recorrente-faturas` | `RecurringBilling/Faturas/Index` | `cabecalho` · `kpis` · `filtros` · `lista` | 0 · 7 · 10 · 9 |
| `recorrente-configuracoes` | `RecurringBilling/Configuracoes/Index` | `cabecalho` · `gateways` · `dunning` · `nfe` · `webhooks` | 1 · 2 · 2 · 4 · 2 |

## Fora, e por quê (detalhe no `_nota` de cada contrato)
- **`subnav`** (Planos, Faturas, Configurações): o protótipo mostra a sub-navegação do Financeiro (`ph-nav`); as
  Pages não têm. Decisão [W], não do contrato.
- **`periodo`** (Assinaturas): a Page não tem a barra `fin-periodbar`; o filtro "Próxima cobrança" fica na coluna
  de filtros.
- **Seções sem copy**: `lista` de Assinaturas (só dado), `ciclos` de Planos ("Distribuição ciclos" na Page ×
  "Distribuição por ciclo" no protótipo) e `cabecalho` de Faturas (h1 e "Nova fatura" só na Page). A âncora fica; a
  copy só entraria escolhendo um dos lados.
- **Planos Create/Edit**: a A1 não os mediu como estado do Index, então não entram.

## Pages: só âncoras (`nao_toca` relaxado, precedente #9185/#9225)
O job "Contratos de tela" é required e exige `data-contract` na Page. As 4 tinham zero âncora. Entraram 17
atributos, sem mudar handler, dado, copy nem classe. Em Configurações o atributo vai no `<SectionCard>`, que ganhou
a prop opcional `data-contract` repassada ao `<section>` que ele já renderizava — é o único lugar onde uma linha de
tipo mudou.

## RUNBOOK criado (exigência do hook, fora do prefixo)
`memory/requisitos/RecurringBilling/RUNBOOK-recurring-billing-index.md`: um RUNBOOK para as 4 telas (o hook
`block-mwart-violation` aceita `RUNBOOK-<modulo>-index.md` para `<Mod>/<Sub>/Index.tsx`). Estrutura, âncoras,
contratos e o que as telas não fazem.

## Provas (rodadas localmente, árvore com o PR)
- `contrato-de-tela --contract` nos 4: rc=0, todas as seções "âncora + copy presentes", ordem coerente.
- Modos do job: os 93 contratos ativos (sem EXEMPLO) rc=0; `--map --check` rc=0 (as 4 telas `🟢 portado`);
  `--anti-tautologia` rc=0, nenhum aviso para os 4 contratos; `--preflight origin/main` rc=0;
  `contrato-de-tela.test.mjs` e `contrato-de-tela-relevante.test.mjs` rc=0; intenção de fluxo (1 `.intent.json`)
  rc=0.
- **Mordida por mutação em cópia isolada (`--root`):** cópia intacta rc=0 nas 4; tirando uma âncora de cada
  (`lista`, `ciclos`, `filtros`, `nfe`), rc=1 com `seção "<id>" sem âncora data-contract no alvo`.
- Typecheck: nenhum erro em `Configuracoes/Index.tsx`; os 2 avisos do módulo (`printSubDetail`, `_Unused`) são
  anteriores e estão em linhas não tocadas.

## Placar
entregue 4 de 4 contratos · 17 seções · 4 Pages com só atributo · 1 RUNBOOK.
