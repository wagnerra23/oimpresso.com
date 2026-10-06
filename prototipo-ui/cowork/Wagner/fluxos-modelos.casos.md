# Fluxos e regras — modelos por porte × atividade e casos de uso

> Tela: `fluxos-page.jsx` (rota `fluxos`, menu Sistema › Fluxos e regras) · dados: `fluxos-data.jsx` · motor de regras da compra: `fsm-regras.jsx`.
> Âncora de implementação: **ADR 0129** (FSM tabular `sale_processes` / `sale_process_stages` / `sale_stage_actions` + Spatie por transição) — a tela é a "UI admin /admin/fsm" que a ADR deixou para o futuro (clonar template). Os modelos abaixo são os templates.
> Pesquisa: `memory/research/2026-05-prospeccao/02-concorrentes-zenite-mubisys.md` e `33-grade-producao-concorrentes.md` (lidos no `main` em 06/10/2026) + páginas públicas da Holdprint (holdprint.net). É o que cada fornecedor **anuncia**; nada foi testado. Sem fonte = "não anunciado", não "não tem".

## Como funciona
1. **Perfil** — porte (Micro MEI e micro · até 5 pessoas · Pequena 6 a 20 pessoas · Média 21 a 100 pessoas · Grande mais de 100 pessoas) + atividade principal (Comunicação visual, Gráfica rápida e digital, Gráfica offset, Brindes e sublimação, Têxtil e DTF, Fachadas e instalação, Oficina mecânica).
2. **Encaixe** — porte igual vale 2, porte vizinho 1, atividade 2 (0–4). 4 = Recomendado, 3 = Serve, 1–2 = Parcial.
3. **Assistente de IA** — recebe perfil + descrição livre e devolve modelo, motivo, casos a ligar e a desligar e perfil sugerido. Sem IA, cai na regra de encaixe e diz isso na tela.
4. **Usar modelo** — grava o modelo da empresa e aplica a regra de custo da compra (`insumo.custo`) no motor de regras. Documentos em andamento ficam na etapa em que estão.
5. **Fluxos da empresa** — etapas e regras de cada processo; a Compra é editável (as mesmas opções de Compras › Regras das etapas).

## Casos de uso (27)
Formato da regra: **ao entrar em <etapa> → efeito**.

| Caso | O que faz | Processo | Ao entrar em | Efeito | Quem anuncia | A partir de | No oimpresso |
|---|---|---|---|---|---|---|---|
| UC-FLX-01 | Orçamento aprovado vira OS sem redigitar | Venda | Aprovado | Cria a OS com itens, medidas e arquivos do orçamento | Mubisys, shopVOX, printIQ, Calcme | Micro | planejado — US-COMVIS-003 |
| UC-FLX-02 | Cliente aprova a arte por link | Produção | Aprovação do cliente | Envia link da prova; aprovado avança para Impressão, recusado volta para Arte com o comentário | Holdprint, shopVOX, Corebridge, Cyrious, printIQ | Pequena | planejado — US-COMVIS-NEW-004 |
| UC-FLX-03 | Orçamento calcula custo, material e lucro sozinho | Venda | Orçamento | Ao salvar, recalcula o custo pela receita e mostra a margem | Mubisys, Holdprint | Micro | parcial — calculadora de m² |
| UC-FLX-04 | Termômetro de acesso da proposta | Venda | Enviado | Registra quando o cliente abre; sem abertura em 48 h cria tarefa de retorno | Mubisys | Pequena | sem US |
| UC-FLX-05 | Comissão do vendedor nasce no faturamento | Venda | Faturado | Lança a comissão do vendedor sobre o valor faturado | Mubisys | Pequena | sem US |
| UC-FLX-06 | Prazo de entrega calculado pela fila | Venda | Aprovado | Calcula a data de entrega pela capacidade livre de cada etapa | Holdprint, Calcgraf | Média | planejado — SPEC do PCP |
| UC-FLX-07 | Etapas de produção vêm do cadastro do produto | Produção | OS criada | Copia o roteiro de etapas do produto para a OS | shopVOX | Pequena | planejado — SPEC do PCP |
| UC-FLX-08 | Cada etapa tem setor e responsável | Produção | qualquer etapa | Atribui ao responsável do setor e conta o tempo parado | Mubisys, Corebridge, Calcme, Cyrious | Pequena | em outro módulo — Repair / Oficina |
| UC-FLX-09 | Apontamento pelo celular com QR Code | Produção | Iniciada / Concluída | A leitura do QR da OS inicia ou conclui a etapa e grava o tempo | Mubisys, printIQ, shopVOX, Cyrious | Pequena | parcial — US-COMVIS-004 |
| UC-FLX-10 | Alocador decide quem e quando começa | Produção | Liberada | Escolhe operador e horário para não parar máquina nem faltar material | Holdprint | Média | planejado — SPEC do PCP (parado, ADR 0152) |
| UC-FLX-11 | Dados da máquina entram sem digitação | Produção | Impressão | Fecha a etapa com metragem e tempo lidos do equipamento | Zênite, Calcgraf | Grande | planejado — US-COMVIS-004 |
| UC-FLX-12 | Romaneio de impressão peça a peça | Produção | Pré-impressão → Impressão | Gera a lista do que foi enviado ao setor de impressão e corte | Mubisys | Pequena | sem US |
| UC-FLX-13 | Simulador de aproveitamento de mídia | Produção | Pré-impressão | Agrupa os jobs na bobina para reduzir a perda de mídia | Holdprint, printIQ, Calcgraf | Média | sem US |
| UC-FLX-14 | Produção baixa o material consumido | Produção | OP concluída | Consome do estoque a quantidade real, não a orçada | Mubisys, Calcgraf, Cyrious | Micro | no protótipo — Fabricação |
| UC-FLX-15 | Previsto × realizado por OS | Produção | Entregue | Congela o custo real e compara com o orçado e o aprovado | Holdprint, Calcgraf, shopVOX, Corebridge, printIQ | Pequena | planejado — US-COMVIS-005 |
| UC-FLX-16 | Refugo e retrabalho com motivo | Produção | Reprovada no acabamento | Abre retrabalho na etapa de origem e soma o custo à OS | Calcgraf, Zênite | Pequena | planejado — SPEC do PCP |
| UC-FLX-17 | Compra recebida atualiza custo e propõe preço | Compra | Recebido | Recalcula o custo dos insumos (médio ou último) e propõe preço nos produtos que acompanham o custo | Mubisys, Holdprint, Calcgraf | Micro | no protótipo — Compras · regras das etapas |
| UC-FLX-18 | Estoque fracionado com rastreio da mídia | Compra | Recebido | Abre o rolo como lote e registra em que OS cada fração foi usada | Mubisys | Pequena | sem US |
| UC-FLX-19 | Estoque mínimo vira requisição de compra | Compra | Abaixo do mínimo | Cria a requisição com o fornecedor da última compra | Mubisys | Micro | planejado — Compras · requisições |
| UC-FLX-20 | Custeio RKW e ponto de equilíbrio | Financeiro | Fechamento do mês | Rateia despesas fixas por centro de custo e atualiza o custo-hora | Holdprint, Zênite | Média | sem US |
| UC-FLX-21 | Agenda da equipe de instalação | Entrega | Pronto | Agenda equipe e veículo; bloqueia se não houver janela | shopVOX, Corebridge, Visua, Calcme, Cyrious | Pequena | planejado — US-COMVIS-007 |
| UC-FLX-22 | Instalação com foto e assinatura | Entrega | Instalado | Só conclui com foto do serviço e assinatura do cliente | shopVOX, printIQ | Pequena | em outro módulo — Oficina (fotos) |
| UC-FLX-23 | Protocolo de entrega | Entrega | Entregue | Gera o protocolo com itens, volumes e quem recebeu | Mubisys, Corebridge, Cyrious | Micro | planejado — US-COMVIS-007 |
| UC-FLX-24 | Cliente acompanha o status | Entrega | cada transição | Avisa o cliente e atualiza o portal de acompanhamento | Calcgraf, Corebridge, shopVOX, printIQ | Pequena | em outro módulo — Repair (portal) |
| UC-FLX-25 | Pesquisa de satisfação depois da entrega | Entrega | Entregue + 2 dias | Envia a pesquisa; nota baixa abre atendimento | Mubisys | Pequena | sem US |
| UC-FLX-26 | Boleto pago emite a NF-e | Financeiro | Pago | Emite a NF-e sem ninguém clicar | oimpresso | Micro | em produção — US-RB-044 |
| UC-FLX-27 | Uma OS, duas notas (NF-e + NFS-e) | Financeiro | Faturado | Emite NF-e da mercadoria e NFS-e do serviço na mesma OS | oimpresso | Pequena | planejado — ADR 0129 · US-NFE-060 |

## Modelos (7)
### Balcão enxuto
Quem orça, imprime e entrega no mesmo dia, com poucas pessoas.
- **Porte:** Micro · **Atividades:** Comunicação visual, Gráfica rápida e digital, Brindes e sublimação, Têxtil e DTF · **Inspirado em:** Mubisys
- **Venda:** Orçamento → Aprovado → Pronto → Entregue → Pago
  - ao entrar em *Aprovado* → Baixa o material do estoque (UC-FLX-14)
  - ao entrar em *Pago* → Emite a NF-e sozinho (UC-FLX-26)
- **Compra:** Pedido → Recebido → Pago
  - ao entrar em *Recebido* → Custo = último preço; preço de venda não muda (UC-FLX-17)
- **Regra de custo da compra:** etapa `recebido` · método `ultimo` · preço `so_custo` · quem segue `produto` · aprovação `recebimento` · OPs abertas `congela`
- **Casos cobertos:** UC-FLX-01, UC-FLX-03, UC-FLX-14, UC-FLX-17, UC-FLX-19, UC-FLX-23, UC-FLX-26

### Comunicação visual · pequena
Produção por OS com arte, aprovação do cliente e instalação própria.
- **Porte:** Pequena · **Atividades:** Comunicação visual, Fachadas e instalação · **Inspirado em:** Mubisys, Holdprint, shopVOX
- **Venda:** Orçamento → Aprovado → Faturado → Entregue → Pago
  - ao entrar em *Aprovado* → Cria a OS com itens e arquivos (UC-FLX-01)
  - ao entrar em *Faturado* → NF-e da mercadoria + NFS-e do serviço (UC-FLX-27)
- **Produção:** Arte → Aprovação do cliente → Impressão → Acabamento → Instalação → Entregue
  - ao entrar em *Aprovação do cliente* → Link de aprovação; recusa volta para Arte (UC-FLX-02)
  - ao entrar em *Impressão* → Apontamento pelo celular (UC-FLX-09)
  - ao entrar em *Entregue* → Protocolo + pesquisa em 2 dias (UC-FLX-23)
- **Compra:** Pedido → Em trânsito → Recebido → Conferido → Pago
  - ao entrar em *Recebido* → Custo médio; propõe preço, aprova no recebimento (UC-FLX-17)
  - ao entrar em *Conferido* → Gera conta a pagar
- **Regra de custo da compra:** etapa `recebido` · método `medio` · preço `propor` · quem segue `produto` · aprovação `recebimento` · OPs abertas `congela`
- **Casos cobertos:** UC-FLX-01, UC-FLX-02, UC-FLX-03, UC-FLX-08, UC-FLX-09, UC-FLX-14, UC-FLX-15, UC-FLX-17, UC-FLX-21, UC-FLX-23, UC-FLX-25, UC-FLX-26, UC-FLX-27

### Comunicação visual · média com PCP
Vários setores e turnos; o gargalo é decidir o que entra na máquina.
- **Porte:** Média, Grande · **Atividades:** Comunicação visual, Fachadas e instalação, Gráfica offset · **Inspirado em:** Holdprint, printIQ, Corebridge
- **Venda:** Orçamento → Enviado → Aprovado → Faturado → Entregue → Pago
  - ao entrar em *Enviado* → Termômetro de acesso + retorno em 48 h (UC-FLX-04)
  - ao entrar em *Aprovado* → Prazo calculado pela fila (UC-FLX-06)
  - ao entrar em *Faturado* → Comissão do vendedor (UC-FLX-05)
- **Produção:** Pré-impressão → Aprovação do cliente → Liberada → Impressão → Acabamento → Expedição
  - ao entrar em *Pré-impressão* → Aproveitamento de mídia (UC-FLX-13)
  - ao entrar em *Liberada* → Alocador escolhe quem e quando (UC-FLX-10)
  - ao entrar em *Impressão* → Romaneio peça a peça (UC-FLX-12)
  - ao entrar em *Expedição* → Previsto × realizado (UC-FLX-15)
- **Compra:** Requisição → Pedido → Em trânsito → Recebido → Conferido → Pago
  - ao entrar em *Requisição* → Nasce do estoque mínimo (UC-FLX-19)
  - ao entrar em *Conferido* → Custo médio; propostas vão à fila da Fabricação (UC-FLX-17)
- **Regra de custo da compra:** etapa `conferido` · método `medio` · preço `propor` · quem segue `produto` · aprovação `fabricacao` · OPs abertas `congela`
- **Casos cobertos:** UC-FLX-01, UC-FLX-02, UC-FLX-04, UC-FLX-05, UC-FLX-06, UC-FLX-07, UC-FLX-08, UC-FLX-09, UC-FLX-10, UC-FLX-12, UC-FLX-13, UC-FLX-15, UC-FLX-16, UC-FLX-17, UC-FLX-19, UC-FLX-21, UC-FLX-22, UC-FLX-24, UC-FLX-27

### Gráfica digital e offset
Produção seriada; papel e tinta mudam de preço com frequência.
- **Porte:** Pequena, Média, Grande · **Atividades:** Gráfica offset, Gráfica rápida e digital · **Inspirado em:** Calcgraf, Zênite, printIQ
- **Venda:** Orçamento → Aprovado → Faturado → Entregue → Pago
  - ao entrar em *Aprovado* → Cria a OP com o roteiro do produto (UC-FLX-07)
- **Produção:** Pré-impressão → Prova → Impressão → Acabamento → Expedição
  - ao entrar em *Prova* → Aprovação online da prova (UC-FLX-02)
  - ao entrar em *Impressão* → Dados da máquina sem digitação (UC-FLX-11)
  - ao entrar em *Acabamento* → Refugo com motivo (UC-FLX-16)
- **Compra:** Pedido → Recebido → Conferido → Pago
  - ao entrar em *Recebido* → Custo médio; preço acompanha o papel automaticamente (UC-FLX-17)
- **Financeiro:** Emitido → Conferido → Conciliado → Liquidado
  - ao entrar em *Liquidado* → Custeio RKW no fechamento do mês (UC-FLX-20)
- **Regra de custo da compra:** etapa `recebido` · método `medio` · preço `aplicar` · quem segue `produto` · aprovação `recebimento` · OPs abertas `congela`
- **Casos cobertos:** UC-FLX-01, UC-FLX-02, UC-FLX-07, UC-FLX-11, UC-FLX-13, UC-FLX-15, UC-FLX-16, UC-FLX-17, UC-FLX-20

### Brindes, sublimação e DTF
Catálogo com variação de cor e tamanho; compra por grade.
- **Porte:** Micro, Pequena · **Atividades:** Brindes e sublimação, Têxtil e DTF · **Inspirado em:** Mubisys, shopVOX
- **Venda:** Orçamento → Aprovado → Produção → Entregue → Pago
  - ao entrar em *Produção* → Baixa a peça e o insumo pela grade (UC-FLX-14)
  - ao entrar em *Entregue* → Cliente avisado por mensagem (UC-FLX-24)
- **Compra:** Pedido → Recebido → Pago
  - ao entrar em *Recebido* → Último preço; aplica o preço sozinho (UC-FLX-17)
- **Regra de custo da compra:** etapa `recebido` · método `ultimo` · preço `aplicar` · quem segue `categoria` · aprovação `recebimento` · OPs abertas `vivo`
- **Casos cobertos:** UC-FLX-01, UC-FLX-03, UC-FLX-14, UC-FLX-17, UC-FLX-19, UC-FLX-24, UC-FLX-26

### Fachadas e instalação sob encomenda
Cada job é um projeto: visita, projeto, produção e equipe em campo.
- **Porte:** Pequena, Média · **Atividades:** Fachadas e instalação, Comunicação visual · **Inspirado em:** Holdprint, Visua, shopVOX
- **Venda:** Visita técnica → Projeto → Orçamento → Aprovado → Faturado → Pago
  - ao entrar em *Aprovado* → Prazo pela fila de produção (UC-FLX-06)
  - ao entrar em *Faturado* → NF-e + NFS-e (UC-FLX-27)
- **Entrega:** Pronto → Agendado → Em campo → Instalado → Entregue
  - ao entrar em *Agendado* → Agenda equipe e veículo (UC-FLX-21)
  - ao entrar em *Instalado* → Exige foto e assinatura (UC-FLX-22)
  - ao entrar em *Entregue* → Pesquisa de satisfação (UC-FLX-25)
- **Compra:** Pedido → Recebido → Conferido → Pago
  - ao entrar em *Recebido* → Custo médio; propõe preço, aprova no recebimento (UC-FLX-17)
- **Regra de custo da compra:** etapa `recebido` · método `medio` · preço `propor` · quem segue `produto` · aprovação `recebimento` · OPs abertas `congela`
- **Casos cobertos:** UC-FLX-02, UC-FLX-06, UC-FLX-15, UC-FLX-17, UC-FLX-21, UC-FLX-22, UC-FLX-23, UC-FLX-25, UC-FLX-27

### Oficina mecânica
OS de veículo; a peça comprada destrava o serviço.
- **Porte:** Micro, Pequena, Média · **Atividades:** Oficina mecânica · **Inspirado em:** Corebridge
- **Venda:** Diagnóstico → Aguardando peça → Em serviço → Pronto → Entregue
  - ao entrar em *Pronto* → Cliente avisado (UC-FLX-24)
  - ao entrar em *Entregue* → Fotos do serviço (UC-FLX-22)
- **Compra:** Pedido → Recebido → Pago
  - ao entrar em *Recebido* → Libera as OS em Aguardando peça; custo médio (UC-FLX-17)
- **Regra de custo da compra:** etapa `recebido` · método `medio` · preço `so_custo` · quem segue `produto` · aprovação `recebimento` · OPs abertas `congela`
- **Casos cobertos:** UC-FLX-17, UC-FLX-22, UC-FLX-24, UC-FLX-27

## Processos com fluxo (14) — aba Processos (`fluxos-processos.jsx`)
Cada processo = estados + transições com **guarda** (condição), **efeito** e **quem pode** (RBAC por transição, ADR 0129).

### Orçamento e venda · Comercial
Do primeiro preço até a venda faturada. Decide quando nasce a OS, a nota e a cobrança.
- **Estados:** Orçamento (início) · Enviado · Aprovado · Faturado · Entregue (fim) · Perdido (fim · exceção) · Cancelado (fim · exceção)
- Orçamento → Enviado · **Enviar ao cliente** · só se: Cliente com contato válido · efeito: Registra o envio; mede abertura (termômetro) · Vendedor
- Enviado → Aprovado · **Cliente aprova** · só se: Validade do orçamento não venceu · efeito: Cria a OS por item · gera as parcelas (sinal + saldo) · Vendedor · cliente pelo link
- Enviado → Perdido · **Marcar perdido** · só se: Motivo obrigatório · efeito: Alimenta o funil (motivo de perda) · Vendedor
- Aprovado → Faturado · **Faturar** · só se: Itens com natureza fiscal definida · efeito: Emite NF-e e/ou NFS-e conforme o item · Financeiro
- Faturado → Entregue · **Concluir** · só se: OS pronta ou entregue · efeito: Abre pesquisa de satisfação em 2 dias · Expedição
- Aprovado → Cancelado · **Cancelar** · só se: Nenhuma nota autorizada · ou notas canceladas antes · efeito: Libera reserva de estoque · estorna sinal · Gerente
- Conversa com: OS e produção por item, NF-e · mercadoria (modelo 55), NFS-e · serviço (padrão nacional), Cobrança · título a receber

### OS e produção por item · Produção
A OS anda por item: cada item tem o próprio roteiro. A OS só fica pronta quando todos os itens terminam.
- **Estados:** Aguardando liberação (início) · Na fila · Em execução · Pausada (espera) · Retrabalho · Concluída (fim) · Cancelada (fim · exceção)
- Aguardando liberação → Na fila · **Liberar** · só se: Arte aprovada · sinal pago (se exigido) · material disponível ou reservado · efeito: Reserva o material · calcula prazo pela fila · PCP / sistema
- Na fila → Em execução · **Iniciar etapa** · só se: Operador do setor da etapa · efeito: Começa a contar o tempo (QR ou tela) · Operador
- Em execução → Pausada · **Pausar** · só se: Motivo: falta material · máquina · aguardando cliente · efeito: Para o relógio; alerta se passar do prazo · Operador
- Pausada → Em execução · **Retomar** · efeito: Volta a contar o tempo · Operador
- Em execução → (mesmo estado) · **Concluir etapa** · só se: Etapa seguinte do roteiro existe · efeito: Passa para a próxima etapa (ou para as paralelas) · Operador
- Em execução → Retrabalho · **Reprovar no controle** · só se: Motivo + etapa de origem · efeito: Soma custo de refugo à OS · Acabamento / qualidade
- Retrabalho → Em execução · **Refazer** · efeito: Reabre a etapa de origem · PCP
- Em execução → Concluída · **Concluir item** · só se: Última etapa do roteiro feita · efeito: Baixa o material real · congela custo · se todos os itens: OS pronta · Operador
- Aguardando liberação → Cancelada · **Cancelar item** · só se: Nada consumido · ou consumo lançado como perda · efeito: Libera reserva · Gerente
- Conversa com: Orçamento e venda, Requisição de material, Entrega e instalação, Cobrança · título a receber

### NF-e · mercadoria (modelo 55) · Fiscal
Documenta circulação de mercadoria (ICMS/IPI). Autorizada pela SEFAZ do estado.
- **Estados:** Rascunho (início) · Validada · Transmitida (espera) · Autorizada (fim) · Rejeitada · Denegada (fim · exceção) · Cancelada (fim · exceção) · Inutilizada (fim · exceção)
- Rascunho → Validada · **Validar** · só se: Schema + regras de negócio + certificado A1 válido · Sistema
- Validada → Transmitida · **Transmitir** · só se: SEFAZ no ar — senão, contingência (SVC/EPEC) · efeito: Reserva o número da série · Sistema
- Transmitida → Autorizada · **Retorno 100** · efeito: Envia XML + DANFE ao cliente · baixa estoque (se for a regra) · liga ao título · SEFAZ
- Transmitida → Rejeitada · **Retorno de rejeição** · efeito: Mostra o código e o campo; volta para correção · SEFAZ
- Rejeitada → Validada · **Corrigir e revalidar** · Fiscal
- Transmitida → Denegada · **Retorno de denegação** · efeito: Número queimado; avisa o fiscal — irregularidade cadastral · SEFAZ
- Autorizada → (mesmo estado) · **Carta de correção (CC-e)** · só se: Não corrige valor, imposto, destinatário nem data · efeito: Evento vinculado à nota · Fiscal
- Autorizada → Cancelada · **Cancelar** · só se: Dentro do prazo (em regra 24 h, varia por UF) · mercadoria não circulou · efeito: Estorna estoque · cancela o título se não pago · Fiscal
- Rascunho → Inutilizada · **Inutilizar número** · só se: Número pulado na sequência · efeito: Comunica a faixa à SEFAZ · Fiscal
- Conversa com: Orçamento e venda, Cobrança · título a receber, Devolução, troca e garantia

### NFS-e · serviço (padrão nacional) · Fiscal
Documenta prestação de serviço (ISS). Desde 1º/01/2026 todo município segue o padrão nacional (LC 214/2025).
- **Estados:** Rascunho (início) · Validada · Enviada (espera) · Autorizada (fim) · Rejeitada · Cancelada (fim · exceção) · Substituída (fim · exceção)
- Rascunho → Validada · **Validar** · só se: Código do serviço (LC 116) + município de incidência + retenções · efeito: Calcula ISS e retenções · Sistema
- Validada → Enviada · **Enviar DPS** · só se: Emissor nacional ou do município (integrado ao ADN) · Sistema
- Enviada → Autorizada · **Retorno de autorização** · efeito: Envia ao tomador · ajusta o título pelo ISS retido · Prefeitura / ADN
- Enviada → Rejeitada · **Retorno de rejeição** · efeito: Volta para correção · Prefeitura / ADN
- Rejeitada → Validada · **Corrigir e revalidar** · Fiscal
- Autorizada → Substituída · **Substituir** · só se: Nova NFS-e emitida no lugar desta · efeito: Vincula a substituta · recalcula o título · Fiscal
- Autorizada → Cancelada · **Cancelar** · só se: Regra e prazo do emissor/município; fora do prazo pode exigir análise · efeito: Cancela o título se não pago · Fiscal
- Conversa com: Orçamento e venda, Cobrança · título a receber

### Cobrança · título a receber · Financeiro
Cada parcela tem vida própria: sinal, saldo, renegociação. O pagamento pode destravar produção e nota.
- **Estados:** Previsto (início) · Emitido · Vencido · Em cobrança (espera) · Pago parcial · Pago · Conciliado (fim) · Renegociado (fim · exceção) · Cancelado (fim · exceção) · Baixado por perda (fim · exceção)
- Previsto → Emitido · **Registrar boleto/PIX** · só se: Conta de cobrança configurada · efeito: Envia ao cliente pelo canal preferido · Financeiro / sistema
- Emitido → Vencido · **Vencimento passou** · só se: D+1 sem pagamento · Sistema
- Vencido → Em cobrança · **Iniciar régua** · efeito: Lembretes em D+1, D+5 e D+15 · tarefa ao vendedor · Sistema
- Emitido → Pago · **Retorno do banco** · só se: Valor recebido ≥ valor do título · efeito: Se for sinal: libera produção · se a regra for nota no pagamento: emite a nota · comissão · Banco
- Emitido → Pago parcial · **Retorno do banco** · só se: Valor recebido < valor do título · efeito: Gera saldo remanescente · Banco
- Pago → Conciliado · **Conciliar extrato** · só se: Lançamento do extrato casado · efeito: Fecha no caixa · Financeiro
- Em cobrança → Renegociado · **Renegociar** · só se: Alçada do gerente · efeito: Cancela este e gera novas parcelas · Gerente
- Em cobrança → Baixado por perda · **Baixar** · só se: Alçada da diretoria · motivo · efeito: Lança perda; bloqueia novo crédito do cliente · Diretoria
- Conversa com: Orçamento e venda, OS e produção por item, NF-e · mercadoria (modelo 55), NFS-e · serviço (padrão nacional)

### Conta a pagar · Financeiro
Nasce da compra conferida ou de despesa avulsa; aprovação por alçada antes de pagar.
- **Estados:** Lançado (início) · Aprovado · Agendado · Pago · Conciliado (fim) · Cancelado (fim · exceção)
- Lançado → Aprovado · **Aprovar** · só se: Valor dentro da alçada do aprovador · Gerente / diretoria
- Aprovado → Agendado · **Agendar no banco** · só se: Saldo projetado suficiente · efeito: Entra no fluxo de caixa · Financeiro
- Agendado → Pago · **Retorno do banco** · efeito: Baixa a compra (estágio Pago) · Banco
- Pago → Conciliado · **Conciliar** · Financeiro
- Conversa com: Compra

### Compra · Suprimentos
Já configurável em Compras › Regras das etapas: recebimento atualiza custo e propõe preço.
- **Estados:** Requisição (início) · Pedido · Em trânsito (espera) · Recebido · Conferido · Pago (fim) · Cancelado (fim · exceção)
- Requisição → Pedido · **Aprovar requisição** · só se: Alçada · efeito: Envia o pedido ao fornecedor · Comprador
- Em trânsito → Recebido · **Receber** · só se: Quantidade conferida · efeito: Entrada no estoque · custo médio · propõe preço · Almoxarifado
- Recebido → Conferido · **Conferir NF-e de entrada** · só se: Divergência dentro da tolerância · efeito: Gera conta a pagar · manifesta a nota · Fiscal
- Conversa com: Conta a pagar, Requisição de material

### Requisição de material · Suprimentos
A produção pede o material da OS; o almoxarifado separa e entrega. Rastreia onde cada rolo foi usado.
- **Estados:** Solicitada (início) · Em separação · Entregue (fim) · Em falta (espera) · Cancelada (fim · exceção)
- Solicitada → Em separação · **Separar** · só se: Saldo disponível · efeito: Reserva a fração do rolo/lote · Almoxarifado
- Solicitada → Em falta · **Sem saldo** · efeito: Abre requisição de compra · pausa a etapa da OS · Sistema
- Em separação → Entregue · **Entregar** · efeito: Baixa do estoque para a OS · Almoxarifado
- Conversa com: OS e produção por item, Compra

### Contagem de inventário · Suprimentos
Contar sem parar a operação; a diferença só ajusta o estoque depois de aprovada.
- **Estados:** Aberta (início) · Contando · Em revisão · Ajuste aprovado (fim) · Cancelada (fim · exceção)
- Aberta → Contando · **Iniciar** · só se: Locais escolhidos · efeito: Congela o saldo de referência · Almoxarifado
- Contando → Em revisão · **Fechar contagem** · só se: Todos os itens contados · efeito: Calcula divergência por item · Almoxarifado
- Em revisão → Ajuste aprovado · **Aprovar ajuste** · só se: Divergência acima do limite exige gerente · efeito: Lança o ajuste e o custo da perda · Gerente

### Entrega e instalação · Pós-venda
Do pronto até o cliente assinar. Instalação em altura ou fachada exige agenda e registro de campo.
- **Estados:** Pronto (início) · Agendado · Em campo · Instalado · Entregue (fim) · Reagendado (espera)
- Pronto → Agendado · **Agendar** · só se: Equipe e veículo livres na janela · efeito: Avisa o cliente com data e hora · Expedição
- Agendado → Reagendado · **Reagendar** · só se: Motivo (chuva, acesso, cliente ausente) · efeito: Novo aviso ao cliente · Expedição
- Em campo → Instalado · **Concluir instalação** · só se: Foto do serviço + assinatura do cliente · efeito: Relatório de instalação na OS · Instalador
- Instalado → Entregue · **Fechar** · efeito: Protocolo de entrega · pesquisa em 2 dias · Sistema
- Conversa com: OS e produção por item, Orçamento e venda

### Devolução, troca e garantia · Pós-venda
Defeito, erro de arte ou troca. Decide se refaz, devolve dinheiro ou emite nota de devolução.
- **Estados:** Aberta (início) · Em análise · Refazer · Devolver valor · Encerrada (fim) · Negada (fim · exceção)
- Aberta → Em análise · **Analisar** · só se: Fotos ou peça recebida · Atendimento
- Em análise → Refazer · **Aprovar refação** · só se: Dentro da garantia · efeito: Cria OS de retrabalho sem cobrança · Gerente
- Em análise → Devolver valor · **Aprovar devolução** · efeito: NF-e de devolução (se houve NF-e) · estorno do título · Gerente
- Conversa com: OS e produção por item, NF-e · mercadoria (modelo 55), Cobrança · título a receber

### Atendimento · Comercial
Conversa que entra pelos canais e precisa de dono e prazo (SLA).
- **Estados:** Nova (início) · Em atendimento · Aguardando cliente (espera) · Resolvida (fim) · Arquivada (fim · exceção)
- Nova → Em atendimento · **Assumir** · efeito: Começa o SLA de resposta · Atendente
- Em atendimento → Aguardando cliente · **Pedir retorno** · efeito: Pausa o SLA · Atendente
- Em atendimento → Resolvida · **Resolver** · só se: Assunto ligado (orçamento, OS ou cobrança) · efeito: Pesquisa rápida de atendimento · Atendente
- Conversa com: Orçamento e venda

### Contrato recorrente · Financeiro
Gera cobrança e nota todo mês até acabar ou ser cancelado.
- **Estados:** Rascunho (início) · Ativo · Suspenso (espera) · Encerrado (fim) · Cancelado (fim · exceção)
- Rascunho → Ativo · **Ativar** · só se: Cliente assinou · efeito: Agenda as cobranças do período · Comercial
- Ativo → (mesmo estado) · **Dia do ciclo** · efeito: Emite a cobrança e a nota do mês · Sistema
- Ativo → Suspenso · **Suspender** · só se: 2 parcelas vencidas · efeito: Para de gerar cobranças · avisa o cliente · Sistema
- Conversa com: Cobrança · título a receber, NFS-e · serviço (padrão nacional)

### Intercorrência de ponto · Pessoas
Marcação esquecida ou errada vira pedido com aprovação e trilha (Portaria MTP 671/2021).
- **Estados:** Aberta (início) · Em análise · Aprovada (fim) · Recusada (fim · exceção)
- Aberta → Em análise · **Enviar** · só se: Justificativa preenchida · efeito: Notifica o gestor · Colaborador
- Em análise → Aprovada · **Aprovar** · só se: Gestor do colaborador · efeito: Ajusta o espelho; marcação original preservada · Gestor
- Em análise → Recusada · **Recusar** · só se: Motivo obrigatório · efeito: Avisa o colaborador · Gestor

## NF-e × NFS-e
| | NF-e (modelo 55) | NFS-e (padrão nacional) |
|---|---|---|
| Documenta | Circulação de mercadoria | Prestação de serviço |
| Imposto | ICMS (e IPI, se industrializa) | ISS |
| Quem autoriza | SEFAZ do estado | Emissor nacional ou do município, sempre integrado ao ADN (padrão nacional desde 1º/01/2026) |
| Corrigir depois | Carta de correção (CC-e) — sem mexer em valor, imposto, destinatário ou data | Substituição: emite-se uma nova NFS-e no lugar da anterior |
| Cancelar | Prazo curto (em regra 24 h, varia por UF) e só se a mercadoria não circulou | Regra e prazo do emissor/município |
| Estado só dela | Denegada · Inutilizada · contingência (SVC/EPEC) | Substituída |
| Retenções | Raras (ICMS-ST é outra coisa) | ISS retido pelo tomador e, para PJ, retenções federais — o título recebe o valor líquido |
| Onde se recolhe | UF de origem / destino (DIFAL) | Em regra no município do prestador; algumas atividades no local da execução (LC 116, art. 3º) |
| Reforma tributária | Ganha campos de IBS/CBS (transição 2026–2032) | Ganha campos de IBS/CBS (transição 2026–2032) |

**Na OS de comunicação visual:** impresso personalizado e sob encomenda, em regra, é só ISS (Súmula 156 do STJ), mesmo levando material; revenda sem personalização é NF-e; instalação é NFS-e. Há exceções (impresso que vira embalagem/insumo de indústria). Decisão do contador → cada item carrega **natureza fiscal**; o faturamento escolhe NF-e, NFS-e ou as duas (ADR 0129: 1 OS = N documentos). Fontes: STJ Súmula 156 e REsp 1.092.206 (repetitivo); LC 214/2025 (NFS-e padrão nacional desde 1º/01/2026).

## Casos acrescentados em 06/10 (UC-FLX-28 a 32)
Proposta do oimpresso, estado **não verificado** no `main`: sinal pago libera produção · régua de cobrança · ISS retido ajusta título · natureza fiscal do item decide a nota · NF-e em contingência.

## Integração nas telas (06/10) — `fsm-etapa.jsx` (`window.OiEtapaPainel`)
As 4 peças comuns: **etapa atual** (trilha do processo) · **ações da etapa** (só as que saem do estado atual; desabilitadas com o motivo) · **prévia do que o sistema vai fazer** (modal antes de confirmar) · **histórico da transição**. Link "Regras desta etapa" abre Fluxos e regras › Processos no processo certo.
- `modo="acao"` — o painel executa a transição: **Compras** (substitui a trilha e os botões fixos do rodapé; "Receber" abre o recebimento em 4 passos) · **OS** (produção do item: Liberar bloqueado por arte não aprovada / sinal não pago) · **Cobrança › boleto** · **Entregas e instalação** (rota nova `entregas`; "Concluir instalação" exige foto + assinatura).
- `modo="leitura"` — a tela mantém os próprios botões; o painel mostra etapa e o que pode acontecer: **Vendas** (diz qual nota sai ao faturar) · **Fiscal NF-e/NFS-e** (Cancelar bloqueado fora do prazo) · **Estoque › contagem** · **Ponto › intercorrência**.
- **Fabricação › receita**: campos **Política de preço** (fixo / acompanha o custo / margem alvo) e **Natureza fiscal** (serviço gráfico NFS-e / mercadoria NF-e / misto). O recebimento da compra passa a ler a política da receita.
- **Atendimento** (painel de contexto da conversa) e **Cobrança recorrente** (drawer da assinatura) em `modo="leitura"` — Resolver bloqueado sem vínculo a orçamento/OS/cobrança; Reativar bloqueado com cobranças falhadas.
- **Cobrança › detalhe da cobrança** (`pg-cobranca-page.jsx`, rota `cobranca`) em `modo="acao"`.
- **Refino 06/10 (partes 1–4):** (1) quando a tela muda a etapa pelos próprios botões, o painel registra no histórico ("Mudança feita na tela") — sem desfazer transição feita pelo painel; (2) bloqueio traz a **ação de correção** ao lado do motivo (OS: "Abrir aprovação de arte" / "Registrar sinal recebido"; Entregas: sugerir equipe livre); (3) o painel mostra as **regras do modelo em uso** para aquele processo, com a da etapa atual em destaque; (4) em **Fluxos da empresa** cada regra do modelo liga/desliga e cada processo mostra **onde aparece** (atalho para a tela).
- **Para o Code:** no `main` o painel lê `sale_stage_actions` permitidas pelo `StageActionPolicy` e chama `ExecuteStageActionService`; o histórico é `sale_stage_history`. As telas deixam de decidir botões por `if (stage === …)`.

## Fontes
- Mubisys: https://mubisys.com/producao
- Holdprint: https://www.holdprint.net/
- Zênite: https://www.zsl.com.br/
- Calcgraf: https://www.calcgraf.com.br/solucao/pcp/
- Visua: https://www.visua.com.br/
- Calcme: https://www.calcme.com.br/funcionalidades/
- shopVOX: https://shopvox.com/features/job-management/
- Corebridge: https://www.corebridge.net/production-management
- Cyrious: https://www.cyrious.com/products/control/
- printIQ: https://www.printiq.com/products/core/

## O que a ancoragem NÃO resolve (para o Code)
- Persistência: hoje modelo e regras ficam no navegador. No `main` vão para `sale_processes` (por `business_id`, ADR 0093) — o modelo vira seed clonável.
- Só a Compra tem regras editáveis; Venda, Produção, Entrega e Financeiro mostram as regras do modelo em leitura até cada módulo ligar o `ExecuteStageActionService` (ADR 0129, fase 2).
- Política de preço por produto (fixo / acompanha o custo / margem alvo) ainda é dado de exemplo — falta o campo no cadastro.
- A chamada de IA do protótipo é ilustrativa; no produto vai pela stack da Jana (ADR 0035).
