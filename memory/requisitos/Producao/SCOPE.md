---
module: Producao
purpose: "Camada única de PCP: apontamento de produção por operação, pessoa e tempo, postos com capacidade e custo-hora por empresa, programação e gargalo. Nasceu em 2026-10-15 como esqueleto (ADR 0431); o fundo veio na thread 02 e os cadastros na 03."
migracao_ui: "n/a — módulo novo, nasce Inertia (sem Blade)"
contains:
  - "DataController — 3 hooks UltimatePOS (pacote superadmin, permissões; sem item de sidebar enquanto não houver tela)"
  - "InstallController — instalação 1-clique (ADR 0024)"
  - "RoteirosController — roteiros por família, até 2 trilhas com junção nomeada (UC-PCV-13, thread 03), e os produtos ligados ao roteiro (US-PCP-026, UC-FLX-07)"
  - "PostosController — postos, máquinas e veículos, com os motivos de pausa e de refugo do posto (UC-PCV-33, E03)"
  - "FerramentasController — ferramentas com código QR (E03)"
  - "ColaboradorCustoController — custo-hora da pessoa, só com producao.desempenho.ver (E03)"
  - "ApontamentoController — celular do operador (PWA, thread 04): /producao/apontamento, escritas em JSON para a fila offline; a regra mora no ApontamentoOperadorService"
  - "ConsumoMaterialService — material apontado na etapa da OS por item (US-PCP-021 · UC-FLX-37): baixa o estoque do local da venda por uma production_sell por apontamento, custo do lote que saiu, em pcp_consumos; o CustoRealService soma no congelado"
  - "ReservaDeInsumoDaOs — reserva o insumo na venda, baixa real na produção (US-PCP-031 · UC-FLX-14): a OS do item sob encomenda reserva o insumo da receita (contrato Fabricacao/InsumosDaReceita do núcleo) em stock_reservations; o apontamento abate, a OS encerrada e a venda cancelada liberam; chave producao_reserva_insumo em common_settings, desligada por padrão"
  - "LoteRastreioController + RastreioDoRolo — rastreio do rolo: em que OS cada fração do lote foi usada e o saldo, JSON só com purchase.view (US-COM-025 · UC-FLX-18); a baixa da OS aceita o rolo escolhido (lote_id)"
  - "OrdemCustoController + PrevisaoCustoDaOrdem — custo da OS aberta: abertura × hoje dos insumos apontados e a previsão pela regra ops da compra (congela | vivo), JSON só com producao.desempenho.ver (US-PCP-022 · UC-FLX-36)"
  - "AlocacaoController + AlocacaoDaEtapa — alocador decide quem e quando começa cada etapa pendente (US-PCP-029 · UC-FLX-10): troca responsável e início previsto em pcp_programacoes com histórico em pcp_eventos, e a fila do posto pela ordem do início; JSON só com producao.etapa.mover"
  - "PrazoDaVendaController + PrazoPelaFila — prazo de entrega da venda/orçamento pela fila dos postos (Σ programações abertas + etapas do roteiro ÷ horas por dia, em dias úteis pelo CalendarioDeProducao: turno da produção, senão expediente do RH; folgas do expediente e feriados do RH da empresa e da loja, pelo contrato CalendarioDaEmpresa do Rh), JSON só leitura com sell.view ou producao.access (US-PCP-028 · UC-FLX-06)"
  - "PrometerPrazoDaVenda — na aprovação (CriarOsDaVendaJob), grava a estimativa em transactions.due_date só quando a venda não tem data e copia a data para pcp_ordens.prazo_prometido (US-PCP-028 · UC-FLX-06)"
  - "HorasDosPostosService — implementa app/Contracts/Producao/HorasDosPostos.php: horas fechadas por posto no mês e troca do custo-hora, para o custeio RKW do Financeiro (US-FIN-070)"
  - "AproveitamentoMidiaController + AproveitamentoDeMidia — simulador de aproveitamento de mídia na bobina (US-PCP-024 · UC-FLX-13): pcp_bobinas (largura útil, margem, giro por mídia), arranjo em faixas com metros, perda e o uma a uma; aceitar grava pcp_arranjos + pcp_arranjo_os (consumo previsto por OS, rateio pela área) e gera o romaneio de cada OS; simular com producao.access, aceitar com o papel producao.liberar, JSON"
  - "RomaneioController + RomaneioDeImpressao — romaneio de impressão peça a peça da OS de comunicação visual (US-PCP-023 · UC-FLX-12): pcp_romaneios + pcp_romaneio_linhas, cópia imutável dos itens do orçamento por versão; gerar com o papel producao.liberar, ler com producao.access, JSON"
  - "FecharTurnoCommand — producao:fechar-turno a cada 15 min, fecha o cronômetro esquecido no fim do turno ou na saída do ponto"
  - "Api/AppApontamentoController — apontamento do app das lojas com token (/api/app/producao/*, P8 tela 02, inclui motivos-pausa), sobre o ApontamentoOperadorService"
  - "EtiquetaOsController — PDF A4 da OS com o QR da etiqueta (E05 modelo a4_os; PR 07 propostas-app)"
  - "AprovacaoArteController + AprovacaoArteService — cliente aprova a arte por link (US-COMVIS-NEW-004 · UC-FLX-02): o operador gera o link da prova da OS por item (token, só o sha256 em pcp_aprovacoes_arte, 7 dias, uso único); a página pública /arte/{token} (Blade, sem login, throttle) aprova — conclui a etapa pelo motor — ou recusa com comentário, reabrindo a arte"
  - "Ordem (pcp_ordens) — OS por item que nasce do pedido nos modelos de fluxo; situação no motor FSM pelo ProcessoDaOrdem (ADR 0436)"
  - "SinalDaOrdem — regra 'produção só libera com o sinal pago' (US-PCP-025 · UC-FLX-28): o Liberar recusa abaixo do % mínimo da empresa e o pagamento gravado na venda libera a OS sozinho (liberar_pelo_sinal); só LÊ valor"
not_contains:
  - "Presença e saída do ponto (o apontamento só LÊ, D-PONTO) → Modules/Ponto"
  - "Parcelas de sinal e saldo do orçamento e o pagamento vindo do retorno do banco → Vendas/Financeiro e PaymentGateway (a Produção só lê o pago da venda)"
excludes:
  - "Dono da OS de Repair, ComunicacaoVisual e OficinaAuto: continua nesses módulos (D-RELACAO, ADR 0431); a OS por item do pedido é da Produção desde a ADR 0436"
  - "Receita e lista de materiais: Manufacturing segue fonte de material"
  - "Encaixe de mídia por algoritmo (versão 2)"
  - "Análise de gargalo por IA (depois da regra simples da V1)"
depends_on: []
owner: wagner
status: em construção
url_prefixes:
  - /producao/* (install 1-clique, os cadastros da thread 03: roteiros, postos, ferramentas, custo-hora, e o PDF da OS com QR em os/{id}/etiqueta, o romaneio de impressão em os/{id}/romaneio e romaneios/{id}, a alocação etapas/{id}/alocacao e a fila postos/{id}/fila, a bobina da mídia em bobinas/{mídia} e o aproveitamento em aproveitamento e aproveitamento/simular — Routes/web.php)
  - /producao/apontamento/* (celular do operador, thread 04)
  - /arte/{token} (página pública da prova para o cliente aprovar, sem login — UC-FLX-02)
  - /producao/lotes/{id}/rastreio (rastreio do rolo, US-COM-025)
---

# Modules/Producao

## Por que existe

O PCP ficou como feature-wish na [ADR 0152](../../decisions/0152-modules-pcp-feature-wish.md)
por falta de piloto. Em 2026-10-15 [W] autorizou a construção e escolheu o nome
([ADR 0431](../../decisions/0431-pcp-sai-do-desejo-modules-producao.md)).

## Fronteira

Produção registra **quem fez qual etapa, em que posto, quando e por quanto tempo**, e usa isso
para programar e medir. Não é dona da OS: Comunicação Visual, Repair e OficinaAuto continuam
criando e fechando a OS; a Produção lê as etapas delas e grava os apontamentos.

## Referências

- [SPEC](SPEC.md) · [ROADMAP](ROADMAP.md) · [proposta aceita](ADR-PROPOSAL.md)
- [ADR 0093 — multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
