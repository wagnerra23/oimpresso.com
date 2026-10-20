---
date: "2026-10-20"
topic: "Pesquisa de concorrentes para os casos de uso da consulta de vendas (Sells/Index), com as necessidades de comunicação visual"
authors: ["M", "C"]
outcomes:
  - "13 casos do núcleo que 3 ou mais ERPs brasileiros têm; 9 ainda faltam na tela"
  - "12 necessidades de comunicação visual para a lista de vendas; nenhuma atendida por inteiro hoje"
  - "Itens viraram [BACKLOG] sem id em resources/js/Pages/Sells/Index.casos.md"
---

# Pesquisa — casos de uso da consulta de vendas

## O que foi feito

Pedido da Maiara [M]: pesquisar nos concorrentes quais casos de uso a tela de consulta de vendas
(`/sells`, `resources/js/Pages/Sells/Index.tsx`) precisa ter, considerando as necessidades de empresas
de comunicação visual.

Duas pesquisas na web em paralelo:

- **ERPs brasileiros:** Bling, Tiny (Olist), Omie, Conta Azul, Nomus; TOTVS Protheus como referência de porte maior.
- **Gráfica e comunicação visual:** shopVOX, CoreBridge, Printavo, DecoNetwork, Ordant, PrintSmith Vision,
  Avanti Slingshot, Mubisys, Calcgraf.

O estado de hoje da tela foi medido no código (`Index.tsx`, `_components/SellsTabelaUnificada.tsx`,
`_components/SellsDateFilter.tsx`, `_components/FiscalSection.tsx`) em 2026-10-20.

## Premissas (traduzir a premissa, não copiar a solução)

1. **A mesma tela atende vestuário e comunicação visual.** O vestuário é quase todo o volume de vendas.
   O que é de comunicação visual entra na visão **Produção**, que já existe, e só aparece para empresa com
   o módulo Comunicação Visual (habilitação por pacote, nunca por `business_id` no código).
2. **Nos sistemas de gráfica, a produção tem quadro próprio.** A lista de vendas deve mostrar e filtrar o
   estado da produção, não virar o quadro de produção.

## Núcleo — o que 3 ou mais ERPs brasileiros têm

| Caso | Quem tem | Hoje na tela |
|---|---|---|
| Filtrar por situação | os 5 | ✅ pílulas |
| Filtrar por período escolhendo a data | Tiny, Omie, Nomus | ✅ 7 tipos de data (`SellsDateFilter`) |
| Filtrar por vendedor | Tiny, Omie, Conta Azul | ❌ |
| Emitir NF-e de uma venda | Bling, Tiny, Omie, Nomus | ✅ pelo painel da venda (`FiscalSection`) |
| Emitir NF-e em lote | Bling, Tiny, Omie, Nomus | ✅ |
| Duplicar a venda | Bling, Tiny, Omie, Conta Azul | ❌ |
| Enviar ao cliente por e-mail ou WhatsApp | Bling, Tiny, Omie, Conta Azul | ❌ na lista |
| Mudar situação ou etapa em lote | Bling, Tiny, Omie | ❌ |
| Exportar para planilha | Bling, Tiny, Omie | ❌ (botão "Em breve") |
| Gerar ordem de produção a partir de vendas | Bling, Tiny, Nomus | ❌ |
| Faturamento parcial | Tiny, Omie, Nomus | ❌ |
| Imprimir em lote | Bling, Tiny, Omie | ❌ |
| Totais do filtro | Conta Azul (único confirmado) | ⚠️ `totals` chega do servidor e não é exibido |

Diferenciais vistos em 1 ou 2: etiquetas com filtro e alteração em lote (Tiny); juntar pedidos do mesmo
cliente numa nota (Bling, Nomus); Kanban com "Faturar todos" (Omie); status de liberação e bloqueio
(Nomus, Protheus); devolução parcial ou total como status (Nomus); imprimir sem valores (Omie);
histórico, anexos e e-mails enviados (Omie).

## Comunicação visual — o que a lista precisa responder

| Caso | Por que | Quem tem | Hoje na tela |
|---|---|---|---|
| Etapa da produção na linha | "onde está meu pedido?" é a pergunta mais comum | shopVOX, CoreBridge, Printavo, DecoNetwork, Ordant | ◐ Pipeline mostra o fluxo da venda |
| Atrasadas pela entrega, com dias de atraso | decidir o que entra primeiro na máquina | shopVOX, CoreBridge, DecoNetwork, Calcgraf | ◐ "Atrasadas" olha o pagamento |
| Situação da arte (com data e quem aprovou) | gargalo que mais trava; prova se o cliente reclamar | 6 dos 9 | ❌ |
| Sinal pago e saldo | só produz depois do sinal, só instala com saldo | Printavo, Calcgraf | ◐ Pago/A receber na visão Financeira |
| Instalação: data, confirmada, equipe | agenda e deslocamento | shopVOX, CoreBridge | ❌ |
| Duas datas: prometida e fim da produção | a instalação consome os últimos dias | shopVOX | ◐ só a prometida (`due_date`) |
| Urgência | furar a fila conscientemente | Printavo, Calcgraf | ❌ |
| Parada com motivo | diferente de cancelada e de atrasada | shopVOX | ❌ |
| Status de cada nota (NF-e e NFS-e) | a venda pode estar meio faturada | Calcgraf | ◐ conferir a coluna Fiscal |
| Recompra com a arte aprovada | frota e rede de lojas repetem | shopVOX | ❌ |
| Retrabalho e cortesia marcados | custo sem receita distorce margem | nenhum confirmado | ❌ |
| Totais da operação | é como o dono acompanha o dia | Calcgraf, Printavo | ◐ cards de hoje são de caixa |

Etapa, arte, sinal, instalação e parada dependem do fluxo de comunicação visual registrar a informação —
ver [FLUXOS-POR-TIPO-E-PORTE §4.3–4.6](../requisitos/Sells/FLUXOS-POR-TIPO-E-PORTE.md), onde várias dessas
etapas ainda estão marcadas como novas.

## Limites

- A central de ajuda do Bling, a do Printavo, a da DecoNetwork e um artigo do Nomus recusaram acesso
  (403 ou erro SSL). O que está atribuído a eles veio do resumo do buscador.
- Mubisys e Calcgraf só têm site institucional, sem manual da tela de lista. O site oficial do Zênite não foi
  encontrado.
- Não confirmado em nenhum sistema de gráfica: ações em lote e retrabalho como recurso próprio.
- Sankhya não foi pesquisado.

## Fontes

ERPs brasileiros:
- Bling: https://ajuda.bling.com.br/hc/pt-br/articles/360036457114 · /360039070654 · /360036450634 · /7581499239447 · /1500007483382 · /360040976893 · /360051479033 · /12419106189719
- Tiny (Olist): https://ajuda.olist.com/pedidos/pedidos-de-venda-como-utilizar
- Omie: https://ajuda.omie.com.br/pt-BR/articles/15234883 · /4693269 · /12702141 · /498879 · /498882 · /6903821 · /3623439
- Conta Azul: https://ajuda.contaazul.com/hc/pt-br/articles/11069896624269 · /11070640115597 · /19484867417741 · /7656679164557
- Nomus: https://ajuda.nomus.com.br/support/solutions/articles/27000057502-pedido · https://news.nomus.com.br/conheca-a-nova-pesquisa-da-tela-de-pedidos-de-venda/
- TOTVS Protheus: https://centraldeatendimento.totvs.com/hc/pt-br/articles/1500002610761

Gráfica e comunicação visual:
- shopVOX: https://help.shopvox.com/article/zfx5focz1s-managing-job-statuses-and-deadlines · https://shopvox-pro.helpdocs.io/article/ryx40qddgr-installation-job-board-shop-vox-pro · https://shopvox-pro.helpdocs.io/article/4zz9xyb5u3-sales-order-states · https://docs.shopvox.com/article/31j2ay0e5e-existing-approved-proofs-used-for-re-orders
- CoreBridge: https://supportdesk.corebridge.net/support/solutions/articles/19000158597-statuses-overview · /19000159711-orders-overview · /19000168379-scheduling-overview
- Printavo: https://support.printavo.com/hc/en-us/articles/53448714297499 · /46723939955099 · /46723941260699
- DecoNetwork: https://help.deconetwork.com/hc/en-us/articles/225983468
- Avanti: https://blog.avantisystems.com/3-steps-to-more-efficient-soft-proofing
- Mubisys: https://www.mubisys.com/comercial · https://www.mubisys.com/producao
- Calcgraf: https://www.calcgraf.com.br/solucao/pcp/ · /solucao/pedido/ · /solucao/painel-de-controle/
