# Oficina Auto — Casos de Uso (dossiê fonte)

> Extraído por [CC] 2026-06-02 do bundle HTML enviado por [W] (`uploads/Casos de Uso - Oficina Auto (standalone).html`). Texto durável (markdown) — o HTML era só preview. Camada 4 · documento de validação da tela Oficina Auto. Fonte de UCs/lacunas; o contrato testável vive em `OficinaProducao.casos.md`.

---

Casos de Uso — Oficina Auto · Oimpresso ERP

 
 
 

 

 

O

Oimpresso ERP

 
Casos de uso · Oficina Auto

 

 

Objetivo e escopo

 

Método e fontes

 

Personas e papéis

 

Jornada macro (pátio → entrega)

 

Máquina de estados da OS

 

Casos de uso detalhados

 

Especificidades do contexto

 

Requisitos de usabilidade

 

Indicadores (KPIs)

 

Matriz de aderência da tela

 

Lacunas priorizadas

 

Referências

 

 

Tema

 

 

 

 
Camada 4 · Módulo Vertical · Documento de validação

 
Casos de Uso — Oficina Auto

 
Mapeamento minucioso do que acontece numa oficina mecânica/automotiva — do momento em que um carro ou caminhão entra no pátio até a entrega ao cliente — para servir de critério de validação da tela 
Oficina Auto
 do Oimpresso ERP.

 

 

Status:
 rascunho para revisão

 

Escopo:
 vertical oficina (carros + frota/caminhões)

 

Uso:
 validar a tela atual e priorizar a próxima iteração

 

Idioma:
 PT-BR

 

 

 

 

 

1
Objetivo e escopo

 
Estabelecer um vocabulário comum e uma lista verificável de necessidades reais da operação de uma oficina, antes de qualquer alteração na interface.

 
Este documento 
não propõe ainda nenhuma mudança de tela
. Ele descreve atores, fluxos, estados, regras de negócio e exceções do dia a dia de uma oficina, com base em práticas consolidadas do mercado (nacional e internacional) e no app mobile já desenhado do Oimpresso. A seção 10 (Matriz de aderência) confronta a tela atual com esses casos de uso e a seção 11 lista as lacunas priorizadas — esse é o insumo para a próxima rodada de design.

 
Dentro do escopo

 

 
Ciclo completo da Ordem de Serviço (OS): recepção → diagnóstico → orçamento → aprovação → peças → execução → qualidade → entrega → pós-venda.

 
Particularidades de 
frota e caminhões
 (placa + carretas, hodômetro, motorista, chassi/renavam).

 
Controle de pátio/box, vistoria digital (DVI), peças/estoque, apontamento de tempo, garantia legal e faturamento (NF-e peça + NFS-e mão de obra).

 

 
Fora do escopo (referenciado, não detalhado aqui)

 

 
Implementação fiscal (módulo Fiscal), conciliação financeira (módulo Financeiro), CRM/cobrança — apenas os pontos de integração são citados.

 
Agendamento online/portal do cliente — citado como fase F0, detalhado em documento próprio se priorizado.

 

 

 

 

 

2
Método e fontes

 
O levantamento cruzou três frentes:

 

 

Sistemas de gestão de oficina (intl.):
 Tekmetric, AutoVitals, Mitchell 1 / ProSpect (incl. caminhões classe 4–8), Shopmonkey, Torque360, Autoflow — referência de workflow, DVI e dispatch.

 

Sistemas e conteúdo brasileiros:
 fluxo de OS em swimlane (Texaco/Havoline), ERPs Actana, Certtus (linha de caminhões), NetSoft, Soften, Ultracar; guia de gestão Lean de oficina (Sults). Referência de etapas, papéis, garantia legal e fiscal local.

 

Ativos internos:
 protótipo mobile Oimpresso (telas 
screens-oficina*.jsx
, 
oficina-data.jsx
) e a tela ERP atual (
oficina-page.jsx
).

 

 
Princípio recorrente nas fontes: 
"defina o fluxo antes de automatizar"
. Digitalizar um processo malformado só acelera o caos. Por isso este documento precede a alteração da tela.

 

 

 

 

3
Personas e papéis

 
Numa oficina, a OS troca de mãos várias vezes. Cada handoff é um ponto onde a tela precisa deixar claro 
de quem é a vez
 e 
o que falta
.

 

 

Papel

Responsabilidade no fluxo

Precisa da tela

 

 

Consultor técnico / Recepção

Recebe o cliente, registra queixa, abre a OS, faz check-in do veículo (avarias, combustível, hodômetro, fotos de entrada).

Abertura rápida de OS, busca de cliente/veículo, checklist de recepção, captura de fotos.

 

Chefe de oficina

Coordena o pátio; tira a OS da fila e direciona ao mecânico/box conforme complexidade e carga.

Visão de pátio/capacidade, fila priorizada, atribuição de box e produtivo (drag-drop).

 

Mecânico / Produtivo

Diagnostica, executa, registra peças/serviços e tempo, aponta itens extras encontrados.

Vista da própria fila, checklist de serviço, apontamento de tempo, DVI com foto, adicionar item.

 

Orçamentista

Calcula peças + mão de obra, monta e envia o orçamento, negocia.

Composição de orçamento por seção, envio (WhatsApp), controle de não aprovados.

 

Estoquista / Comprador

Separa peças, identifica falta, cota, compra, recebe e baixa do estoque.

Status de peça (necessária→cotada→pedida→recebida), reserva, separação por OS.

 

Financeiro / Caixa

Fatura, recebe pagamento, emite NF, controla a receber.

Fechamento da OS → cobrança, forma de pagamento, NF-e/NFS-e.

 

Gestor / Dono

Acompanha ocupação, ticket médio, gargalos, margem.

KPIs, fila por etapa, OS paradas e o porquê.

 

Cliente / Motorista

Aprova ou recusa itens, acompanha status, retira o veículo.

(Externo) link de aprovação, notificações de status, comprovação por foto.

 

 

 
A tela atual não tem 
noção de papel/permissão
. Ações sensíveis (pausar execução, aprovar orçamento, dar desconto) deveriam respeitar autorização — o mobile já prevê "apenas Gerente de oficina ou Admin pausam/retomam".

 

 

 

 

4
Jornada macro — do pátio à entrega

 
O fluxo brasileiro consolidado tem 
cinco etapas centrais
 (recepção, diagnóstico, orçamento aprovado, execução, entrega), mas a operação real se decompõe em até 
onze fases
 com handoffs e SLA próprios. O kanban de 5 colunas da tela é uma 
compressão
 dessa jornada — importante ter consciência do que cada coluna esconde.

 

 

0

Agendamento / pré-cadastro

Recepção · opcional

Cliente liga/agenda; pré-cadastro de cliente e veículo. Reduz fila e fricção no balcão. Pode vir de retorno de garantia ou lembrete de revisão.

 

1

Recepção e check-in (chegada ao pátio)

Consultor técnico

Abordagem, escuta da queixa, identificação de cliente/veículo (placa, VIN/chassi, hodômetro de entrada, nível de combustível), 
vistoria de entrada
 com fotos walkaround e avarias, autorização de diagnóstico. Veículo entra na fila "aguardando serviço".

 

2

Triagem e alocação

Chefe de oficina

Prioriza pela carga/urgência, atribui mecânico e box/elevador. Aqui entra a visão de capacidade do pátio (posições livres/ocupadas).

 

3

Diagnóstico e vistoria digital (DVI)

Mecânico

Inspeção estruturada por sistema (motor, freios, suspensão…), leitura de falhas (OBD), classificação ok / atenção / crítico, foto/vídeo por item. Gera as recomendações que viram itens do orçamento.

 

4

Orçamento

Orçamentista

Compõe peças + mão de obra (por seção: suspensão, freios, motor…), tempos e preços. Mapeia cada achado da DVI a um item com preço.

 

5

Aprovação do cliente

Cliente

Envio por WhatsApp/link com fotos e justificativa item a item. Cliente 
aprova ou recusa cada item
; recusados ficam registrados (oportunidade futura). Registro formal da autorização (assinatura/WhatsApp).

 

6

Peças

Estoquista / Comprador

Ciclo próprio: 
necessária
 → 
cotada
 → 
pedida
 → 
recebida
. Reserva de estoque, separação por OS, compra quando falta. Execução pode ficar bloqueada aqui ("aguardando peça").

 

7

Execução

Mecânico

Serviço na baia, com 
apontamento de tempo
 (início/pausa/fim), checklist de roteiro, registro de itens extras (que voltam à aprovação). Pausa com motivo quando falta peça/aprovação.

 

8

Qualidade (revisão final)

Chefe de oficina

Conferência do que foi autorizado × executado, test-drive, limpeza, sem sujeira/avaria nova. Só passa para entrega quando aprovado.

 

9

Entrega e faturamento

Recepção + Financeiro

Checklist de saída assinado, devolução das peças trocadas, orientações, hodômetro de saída, 
garantia (mín. 90 dias por lei)
, pagamento e emissão de NF-e (peça) + NFS-e (mão de obra). Baixa de estoque vinculada à OS.

 

10

Pós-venda

CRM / Recepção

NPS/pesquisa, lembrete de próxima revisão/troca de óleo por km/data, controle de retorno em garantia (sem custo) e reativação de itens recusados.

 

 
A jornada é 
não-linear
: itens extras descobertos na execução voltam para aprovação; falta de peça empurra de volta para "aguardando peça"; reprovação na qualidade devolve para execução. A tela precisa suportar idas e voltas, não só avanço.

 

 

 

 

5
Máquina de estados da OS

 
Reconciliando as fontes BR (Actana/Certtus) com o kanban atual, este é o conjunto canônico de estados da OS proposto para validação:

 

 
Aberta / Recepção

→

 
Diagnóstico

→

 
Orçamento enviado

→

 
Aprovada

→

 
Aguardando peça

→

 
Em execução

→

 
Qualidade

→

 
Pronto / aguardando retirada

→

 
Entregue / faturada

→

 
Encerrada

 

 
Transições e estados paralelos

 

 

Recusada / parcial:
 cliente recusa todo ou parte do orçamento → registra itens recusados, pode encerrar sem serviço ou seguir com o aprovado.

 

Pausada:
 estado paralelo a "Em execução" com motivo (peça, aprovação, mecânico, box, expediente) e autorização de gerente.

 

Retorno de garantia:
 reabre OS vinculada à anterior, sem custo, dentro do prazo legal.

 

Abandono:
 veículo não retirado após X dias — alerta e procedimento.

 

 
O kanban atual tem 
5 colunas
 (Recepção, Diagnóstico, Aguardando peças, Execução, Pronto). Faltam estados explícitos de 
Orçamento/Aprovação
 e 
Qualidade
, e os estados paralelos (Pausada, Recusada, Retorno de garantia). Decidir se viram colunas, sub-status ou filtros é uma questão de design a validar.

 

 

 

 

6
Casos de uso detalhados

 
Formato: ator · pré-condição · gatilho · fluxo principal · exceções · o que a tela precisa. Numerados por fase.

 

 

UC-01

Abrir OS e fazer check-in do veículo

Consultor técnico

 

 
Pré-condição

Cliente presente (espontâneo, guincho ou agendado).

 
Gatilho

Veículo chega ao pátio.

 
Fluxo principal

Busca/cadastra cliente e veículo (placa → puxa dados; VIN/chassi, modelo, ano, cor).

Registra hodômetro de entrada e nível de combustível.

Anota a queixa do cliente (relato), com áudio/foto se preciso.

Vistoria de entrada: walkaround com fotos das 4 faces + avarias preexistentes.

Coleta autorização de diagnóstico; OS entra como "Aberta/Recepção".

 
Exceções

Cliente novo sem cadastro; veículo de frota (vincular à frota e motorista); guincho sem cliente presente.

 

 

A tela precisa:
 ação "Nova OS" funcional com formulário de check-in, busca por placa, campos de chassi/renavam/hodômetro/combustível, captura de fotos de entrada e relato separado do diagnóstico.

 

 

 

UC-02

Triar a fila e alocar mecânico + box

Chefe de oficina

 

 
Pré-condição

OS na fila "aguardando serviço".

 
Gatilho

Box/elevador ou mecânico fica livre.

 
Fluxo principal

Vê a fila priorizada (urgência, prazo, chegada).

Confere capacidade do pátio (posições livres/ocupadas).

Atribui mecânico e box arrastando o card; status vira "Diagnóstico".

 
Exceções

Pátio cheio (fila de espera); serviço exige elevador/box específico; remanejar mecânico sobrecarregado.

 

 

A tela precisa:
 visão de ocupação do pátio, fila ordenável, e atribuição por arraste (já existe drag-drop por box/mecânico — falta a visão de capacidade).

 

 

 

UC-03

Diagnosticar e registrar vistoria digital (DVI)

Mecânico

 

 
Pré-condição

OS atribuída ao mecânico, veículo no box.

 
Gatilho

Início do diagnóstico.

 
Fluxo principal

Percorre checklist por sistema (motor, freios, suspensão, elétrica, pneus…).

Classifica cada item: ok / atenção / crítico; anexa foto ou vídeo curto.

Lê falhas (OBD) e registra diagnóstico técnico.

Cada achado vira uma recomendação mapeada a um item de orçamento.

 
Exceções

Item "não aplicável"; recomendação futura (não urgente); necessidade de test-drive.

 

 

A tela precisa:
 DVI por item com foto/vídeo e mapeamento achado→item de orçamento (hoje a DVI é estática/ilustrativa, sem captura nem vínculo).

 

 

 

UC-04

Compor e enviar o orçamento

Orçamentista

 

 
Pré-condição

Diagnóstico/DVI concluídos.

 
Gatilho

Recomendações prontas para precificar.

 
Fluxo principal

Adiciona peças e serviços (catálogo, por seção), com qtd, preço e tempo.

Calcula total; define forma de pagamento e prazo de entrega.

Envia ao cliente por WhatsApp/link com fotos e justificativa item a item.

OS → "Orçamento enviado".

 
Exceções

Desconto (exige autorização/limite); peça sem preço atualizado; orçamento revisado após novos achados.

 

 

A tela precisa:
 estado explícito "Orçamento enviado"; agrupar itens por seção; envio com registro. (Adicionar item + total já existem.)

 

 

 

UC-05

Aprovar / recusar itens do orçamento

Cliente
 (via consultor)

 

 
Pré-condição

Orçamento enviado.

 
Gatilho

Resposta do cliente.

 
Fluxo principal

Cliente aprova ou recusa 
cada item
 (ou tudo).

Itens aprovados liberam execução; recusados ficam registrados.

Autorização fica registrada (WhatsApp/assinatura). OS → "Aprovada".

 
Exceções

Aprovação parcial; cliente não responde (cobrar OK); recusa total (encerrar sem serviço, manter itens recusados para retomada).

 

 

A tela precisa:
 aprovação item a item já existe no fluxo de itens; falta 
registro de recusados
, aprovação parcial formal e o estado da OS refletindo "Aprovada".

 

 

 

UC-06

Gerir peças: cotar, comprar, receber, reservar

Estoquista / Comprador

 

 
Pré-condição

Itens de peça aprovados.

 
Gatilho

Peça aprovada sem estoque suficiente.

 
Fluxo principal

Verifica estoque; reserva o que há.

Para o que falta: 
cotada
 → 
pedida
 → 
recebida
.

Separa por OS; ao aplicar, baixa do estoque.

 
Exceções

Peça atrasa (mantém OS "aguardando peça"); peça errada/devolução; peça de terceiro/cliente.

 

 

A tela precisa:
 ciclo de peça (necessária→cotada→pedida→recebida), reserva e baixa de estoque vinculada à OS — hoje o item só tem aguardando/aprovado/aplicado, sem a cadeia de compra.

 

 

 

UC-07

Executar o serviço e apontar tempo

Mecânico

 

 
Pré-condição

OS aprovada e peças disponíveis.

 
Gatilho

Início da execução.

 
Fluxo principal

Inicia apontamento de tempo (start/pause/stop) por item ou OS.

Segue o checklist de roteiro, marcando passos concluídos.

Registra peças aplicadas; atualiza progresso.

 
Exceções

Item extra
 descoberto → cria item "aguardando aprovação" e (opcional) pausa; pausa com motivo; troca de mecânico.

 

 

A tela precisa:
 apontamento de tempo, checklist de roteiro e pausa com motivo. Adicionar item extra já existe; falta o vínculo com pausa/tempo.

 

 

 

UC-08

Controle de qualidade antes da entrega

Chefe de oficina

 

 
Pré-condição

Execução concluída.

 
Gatilho

Mecânico devolve a OS.

 
Fluxo principal

Confere autorizado × executado; test-drive.

Checa limpeza/sem avaria nova; valida garantia.

Aprova → "Pronto"; reprova → volta para execução.

 

 

A tela precisa:
 etapa/checklist de qualidade entre execução e pronto (ausente hoje).

 

 

 

UC-09

Entregar o veículo e faturar

Recepção + Financeiro

 

 
Pré-condição

OS "Pronto".

 
Gatilho

Cliente retira o veículo.

 
Fluxo principal

Checklist de saída assinado; hodômetro de saída; devolve peças trocadas.

Recebe pagamento (forma/parcelas); emite NF-e (peça) + NFS-e (mão de obra).

Registra 
garantia (mín. 90 dias)
; orienta o cliente. OS → "Entregue/Encerrada".

 
Exceções

Pagamento parcial/fiado (política); cliente não retira (abandono); nota só de serviço.

 

 

A tela precisa:
 fluxo de entrega real (checklist de saída, pagamento, NF, garantia) — hoje "Entregar" apenas mostra um aviso.

 

 

 

UC-10

Pós-venda: garantia, NPS e lembretes

CRM / Recepção

 

 
Pré-condição

OS encerrada.

 
Gatilho

Tempo decorrido / retorno do cliente.

 
Fluxo principal

Coleta NPS após a entrega.

Agenda lembrete de próxima revisão/troca de óleo (por km ou data).

Trata retorno em garantia (reabre OS vinculada, sem custo).

 

 

A tela precisa:
 histórico do veículo (passagens anteriores), vínculo de retorno de garantia e gatilho de lembrete — ausentes.

 

 

 

 

 

7
Especificidades do contexto

 
Frota e caminhões

 

 

Conjunto placa + carretas:
 uma OS de caminhão pode envolver o cavalo e até várias carretas; rastrear qual carreta passou e quando.

 

Dados de frota:
 vínculo à frota do cliente, motorista, hodômetro, chassi, renavam — o detalhe mobile já contempla; o ERP só mostra KM.

 

Serviços por seção e por modelo:
 listas pré-configuradas (suspensão, direção, lubrificação…) e tabela de preço por modelo aceleram o orçamento.

 

Aprovação por frota:
 gestor de frota aprova por centro de custo, não o motorista.

 

 
Garantia legal e fiscal (Brasil)

 

 

Garantia mínima de 90 dias
 em serviços automotivos (CDC) — registrar o que é coberto e por quanto tempo.

 

Dois documentos fiscais
: NF-e (modelo 55) para a peça e NFS-e para a mão de obra na mesma OS.

 

Baixa de estoque vinculada à OS
 e markup auditável por serviço; evita o "estoque que some no fim do mês".

 

 
Peças e estoque

 

 
Curva ABC, ponto de reposição e alerta de mínimo; cotação a partir da demanda de OS aprovadas (sem imobilizar capital).

 
Código de barras na entrada/separação; controle de peça entregue ao produtivo e devolução do não aplicado.

 

 
Comercial / financeiro

 

 

Controle de não aprovados
 para reabrir negociação; 
política anti-fiado
 (entrada/parcelas formalizadas na OS).

 
Histórico de OS por veículo = ativo de upsell (chegando na km de troca) e defesa em reclamação.

 

 

 

 

 

8
Requisitos de usabilidade

 

 

Baixa fricção no balcão:
 abrir OS em segundos; busca por placa preenche o veículo. Intake lento atrasa toda a oficina.

 

Tempo real e status visível para todos:
 quem liga perguntando "e o meu carro?" deve ser respondido sem caçar o mecânico.

 

Kanban que reflete o pátio:
 mover OS entre etapas por arraste; identificar gargalos e prioridades. (Já implementado.)

 

Mobilidade na baia:
 o produtivo opera por tablet/celular — DVI, fotos e apontamento de tempo precisam funcionar em telas pequenas.

 

Comunicação anexada à OS:
 mensagens com o cliente e entre equipe ligadas ao card, não em apps soltos.

 

Atalhos de teclado
 (J/K navegar, N nova OS, / buscar) para o balcão de alto volume — previstos no handoff desktop.

 

Adoção:
 se a tela exigir digitação dupla, a equipe contorna. Cada passo deve "puxar" o próximo sem retrabalho.

 

 

 

 

 

9
Indicadores (KPIs) a expor

 

 

Indicador

Por que importa

Na tela hoje?

 

 

Ticket médio / ARO

Valor médio por OS; DVI bem feita aumenta o ARO.

parcial
 (valor em curso)

 

Taxa de aprovação

% de itens orçados que viram serviço aprovado.

não

 

MTTR / tempo por etapa

Tempo médio de reparo; check-in→orçamento, aprovação→peça, etc.

não

 

Ocupação de box/mecânico

Capacidade usada × ociosa.

parcial
 (contagem por box)

 

% de veículos inspecionados (DVI)

Consistência da vistoria; meta >80%.

não

 

OS atrasadas / urgentes

Risco de prazo.

sim
 (urgentes)

 

 

 

 

 

 

10
Matriz de aderência da tela atual

 
Confronto preliminar entre a tela 
Oficina Auto
 atual e os casos de uso. Critério de validação para a próxima iteração.

 

 

Necessidade (UC)

Status

Observação crítica

 

 

Abrir OS + check-in (UC-01)

ausente

"Nova OS" não abre formulário; sem vistoria de entrada, fotos walkaround, combustível, autorização.

 

Relato do cliente × diagnóstico técnico (UC-01/03)

parcial

Há só um campo "sintoma". O mobile separa relato e diagnóstico.

 

Triagem + alocação por arraste (UC-02)

atende

Drag-drop por etapa/box/mecânico implementado.

 

Visão de capacidade do pátio (UC-02)

parcial

Filtro por box existe; falta a vista de ocupação (livre/cheio) do mobile (Pátio).

 

DVI por item com foto/vídeo + vínculo ao orçamento (UC-03)

parcial

DVI é ilustrativa e fixa; não captura mídia nem gera item de orçamento.

 

Estado "Orçamento enviado / Aprovada" (UC-04/05)

ausente

Kanban não tem essas colunas/estados; aprovação vive só no nível de item.

 

Aprovar/recusar item + registrar recusados (UC-05)

parcial

Aprovação item a item OK; falta registro de recusados e aprovação parcial formal.

 

Ciclo de peça (cotada→pedida→recebida) + estoque (UC-06)

ausente

Item só tem aguardando/aprovado/aplicado; sem compra, reserva ou baixa de estoque.

 

Apontamento de tempo + checklist de roteiro (UC-07)

ausente

Sem start/stop de tempo nem checklist de serviço marcável.

 

Item extra na execução (UC-07)

atende

Adicionar peça/serviço com aprovação já existe.

 

Pausar/retomar com motivo + permissão (UC-07)

ausente

Mobile prevê; ERP não.

 

Qualidade / revisão final (UC-08)

ausente

Não há etapa nem checklist de QC.

 

Entrega: checklist saída, pagamento, NF, garantia (UC-09)

ausente

"Entregar" só emite um aviso; sem faturamento/garantia.

 

Pós-venda: histórico, garantia, lembrete (UC-10)

ausente

Sem histórico do veículo nem gatilhos.

 

Frota/caminhão: carretas, motorista, chassi/renavam (§7)

parcial

Só KM no drawer; faltam campos de frota/caminhão.

 

Avançar etapa (fluxo)

atende

Implementado, com pipeline visual.

 

Linha do tempo / histórico da OS

atende

Timeline existe (derivada; idealmente eventos reais).

 

Anexar fotos

atende

Anexo real implementado; falta marcação/anotação na imagem.

 

Papéis e permissões

ausente

Sem controle de quem pode aprovar/pausar/descontar.

 

 

 

 

 

 

11
Lacunas priorizadas (insumo para o design)

 
Recomendação de ordem para a próxima iteração — a confirmar com o Wagner antes de aplicar à tela.

 

 

#

Lacuna

Impacto

Esforço

 

 

P1

Abertura de OS + check-in
 (UC-01): formulário, busca por placa, vistoria de entrada, fotos, autorização.

Alto — início de todo o fluxo, hoje inexistente.

Médio

 

P1

Fluxo de entrega + faturamento
 (UC-09): checklist de saída, pagamento, NF-e/NFS-e, garantia.

Alto — fecha o ciclo e gera receita.

Médio

 

P1

Estados de Orçamento/Aprovação
 (UC-04/05) + registro de recusados.

Alto — etapa central do negócio.

Médio

 

P2

Ciclo de peças + estoque
 (UC-06): cotada→pedida→recebida, reserva, baixa.

Alto — gargalo recorrente e margem.

Alto

 

P2

DVI real
 (UC-03): item por sistema, foto/vídeo, achado→item.

Alto — eleva ticket e confiança.

Alto

 

P2

Apontamento de tempo + checklist de roteiro + pausa
 (UC-07).

Médio — produtividade e MTTR.

Médio

 

P3

Etapa de Qualidade
 (UC-08).

Médio — reduz retorno/retrabalho.

Baixo

 

P3

Campos de frota/caminhão
 + histórico do veículo (§7, UC-10).

Médio — diferencial na vertical.

Médio

 

P3

Visão de pátio
 (ocupação) + KPIs (ARO, aprovação, MTTR, %DVI).

Médio — gestão.

Médio

 

P4

Papéis e permissões
; pós-venda (NPS, lembretes, retorno de garantia).

Médio — governança e retenção.

Médio

 

 

 
Próximo passo sugerido: você prioriza/edita esta lista; eu transformo os itens escolhidos em alterações de tela — mantendo a disposição e o sistema de cor já validados, e seguindo o protocolo (CHANGELOG + HANDOFF) ao fechar.

 

 

 

 

12
Referências

 

 
Tekmetric — Repair shop workflow & Digital Vehicle Inspection Guide (estágios de peça needed/quoted/ordered/received; ARO com DVI).

 
AutoVitals — Workflow management & DVI best practices (estados de dispatch; marcação de foto; metas de inspeção).

 
Mitchell 1 / ProSpect — inspeção e check-in para automóveis e caminhões classe 4–8.

 
UTI / Vehicle Service Pros — etapas do processo de reparo (check-in → inspeção → aprovação → reparo → conclusão → follow-up).

 
AutoSoftWay — otimização de workflow (pontos de tempo por etapa; "defina o fluxo antes de automatizar").

 
Tire Review / Torque360 / Autoflow — boas práticas de DVI (20–25 fotos, walkaround; achado→serviço; vídeo).

 
Texaco/Havoline — fluxo de OS em swimlane e papéis (consultor técnico, chefe de oficina, orçamentista, mecânico).

 
Actana, Certtus (linha caminhões), NetSoft, Soften, Ultracar — estados da OS, garantia 90 dias, NF-e+NFS-e, baixa de estoque, placa+carretas, kanban.

 
Sults — guia de gestão Lean de oficina (5 etapas + checklist por handoff + SLA; aprovação formal por WhatsApp).

 
Ativos internos Oimpresso — protótipo mobile (telas de oficina, OS, locais) e tela ERP atual.

 

 

 Documento de casos de uso · Oficina Auto · Oimpresso ERP — rascunho para revisão. Nenhuma alteração de tela foi feita com base neste documento ainda.