---
id: requisitos-sells-fluxos-por-tipo-e-porte
---

# Fluxos por tipo de empresa e por porte

> **O que é:** o desenho do fluxo de trabalho (o FSM do [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md))
> para cada tipo de empresa que o oimpresso atende e para cada porte, de 1 a mais de 26 funcionários.
> Cada célula da matriz é um **modelo de fluxo pronto**: os estágios ligados, quem faz cada etapa,
> quando o material sai do estoque, as travas e as alçadas.
>
> **Estado:** proposta de desenho, escrita em 2026-10-06 a pedido do time (sessão Claude Code).
> **Nada aqui está implementado.** O que existe hoje está marcado em cada seção, conferido no `main`.
>
> **Não duplica:** o pipeline de vendas e seus 7 gaps estão em [CASOS-USO-PIPELINE-VENDAS](CASOS-USO-PIPELINE-VENDAS.md);
> o fluxo canônico de comunicação visual está no [SPEC da Comunicação Visual §11](../ComunicacaoVisual/SPEC.md);
> o que 10 concorrentes anunciam na produção está na [grade de produção](../../research/2026-05-prospeccao/33-grade-producao-concorrentes.md).
> Este documento junta essas peças numa matriz **tipo × porte**, que nenhum deles tem.

## 1. Como ler

**Portes** (contagem de funcionários que usam o sistema):

| Porte | Pessoas | Como a empresa funciona |
|---|---|---|
| **Micro** | 1–3 | o dono faz quase tudo; uma pessoa acumula várias funções |
| **Pequena** | 4–10 | funções separadas, mas ainda acumuladas (quem atende também faz arte) |
| **Média** | 11–25 | setores com líder; aparece o PCP (programação da produção) |
| **Grande** | 26+ | várias equipes, turnos ou filiais; aprovação por alçada |

**Símbolos nas tabelas de estágio:** ✅ ligado · ◐ a empresa escolhe · — não existe nesse porte.

**Tipos de empresa (10):**

| # | Tipo | Módulo no oimpresso | Cliente real hoje |
|---|---|---|---|
| 4.1 | Comércio geral | núcleo (Sells) | — |
| 4.2 | Loja de vestuário | Vestuario | ROTA LIVRE (biz=4) |
| 4.3 | Comunicação visual — gráfica rápida / balcão | ComunicacaoVisual | pilotos OfficeImpresso |
| 4.4 | Comunicação visual — impressão digital sem instalação | ComunicacaoVisual | pilotos OfficeImpresso |
| 4.5 | Comunicação visual — com instalação / fachada | ComunicacaoVisual | pilotos OfficeImpresso |
| 4.6 | Comunicação visual — industrial com PCP | ComunicacaoVisual | Extreme (candidato) |
| 4.7 | Gráfica offset / comercial | **nenhum** | — |
| 4.8 | Oficina mecânica | OficinaAuto | Martinho (biz=164) |
| 4.9 | Assistência técnica / reparo | Repair | — |
| 4.10 | Fabricação sob receita | Manufacturing (Fabricação) | — |

## 2. O que muda com o porte (vale para todos os tipos)

Esta é a parte que não depende do ramo. Cada modelo da seção 4 aplica estas regras.

| Dimensão | Micro | Pequena | Média | Grande |
|---|---|---|---|---|
| **Quem faz o quê** | o dono acumula; os botões aparecem para ele | 2–4 funções por pessoa | um setor por pessoa, com líder | equipes por setor, turno e filial |
| **Travas** | **avisam, não bloqueiam** (bloqueio atrapalha quem faz tudo sozinho) | sinal obrigatório, com isenção do dono | todas as travas ligadas | todas, mais separação de funções |
| **Alçadas** (quem autoriza) | nenhuma | o dono autoriza desconto acima do limite e cancelamento | gerente do setor | por faixa de valor; cancelar nota emitida pede 2 aprovações |
| **Responsável** | não precisa (é o dono) | um por OS | um por etapa | um por etapa, fila por máquina ou equipe, e substituto |
| **Prazo** | prazo da OS | alerta de OS atrasada | prazo por etapa | capacidade por máquina e agenda |
| **Saída do material** | na venda ou no faturamento | ao concluir a produção | por etapa | por etapa, com apontamento real e refugo |
| **Apontamento de tempo** | — | ◐ | por etapa | por operador e máquina (QR ou celular) |
| **Visão do trabalho** | lista | um quadro (kanban) | quadro por setor | quadro por setor, agenda de máquina e painel gerencial |
| **Aviso ao cliente** | botão de WhatsApp manual | automático nos marcos (arte pronta, pronto para retirar) | automático + portal do cliente | portal + pesquisa de satisfação |
| **Indicadores** | faturamento | atrasos | produtividade por setor | custo real × orçado, refugo, ocupação de máquina |

**Regra para o micro:** a trava que bloqueia vira aviso. Exemplo concreto: na ROTA LIVRE, lançar
vendas do balcão em lote no fim do dia é fluxo normal ([SPEC do Vestuario](../Vestuario/SPEC.md), sensibilidade 2).
Um modelo que bloqueasse venda com data passada quebraria o cliente que hoje faz 99% do volume.

## 3. O que o motor precisa ter para isto funcionar

A matriz só funciona se o motor do FSM deixar cada empresa ligar, desligar e mudar partes do fluxo.
Estado conferido no `main` em 2026-10-06:

| # | Capacidade | Para quê | Hoje | Onde conferir |
|---|---|---|---|---|
| M1 | Ligar/desligar estágio por empresa | o micro pula a conferência; a média liga | ❌ a tabela não tem coluna para isso, e o seeder de CV ignora o `default_active => false` | `database/migrations/2026_05_11_120002_create_sale_process_stages_table.php` · `FsmProcessoComunicacaoVisualSeeder.php` |
| M2 | Modelo de fluxo por tipo de serviço ou produto | banner de balcão não passa pela instalação | ❌ um processo por chave e por empresa, escolhido pelo tipo de contato | `SaleProcess` (`default_for_contact_type`) |
| M3 | Versão do fluxo | a OS em andamento termina na regra em que nasceu | ❌ | — |
| M4 | Travas (condição antes de avançar) | sinal pago, arte com arquivo, NR-35 | ❌ o serviço só confere estágio, empresa e papel | `app/Domain/Fsm/Services/ExecuteStageActionService.php` |
| M5 | Funções do fluxo separadas do papel de acesso, mais de uma por pessoa | quem atende também faz arte | ❌ o cadastro de usuário aceita **um** papel; dar "operador" tira o papel de acesso | `app/Http/Controllers/ManageUserController.php` (`$user->roles->first()`) |
| M6 | Liberar o fluxo por permissão do módulo | sem isso, ninguém vê os botões até receber a função | ⚠️ só a OS da Oficina tem ([ADR 0265](../../decisions/0265-oficina-reparo-erradica-locacao.md)) | `app/Domain/Fsm/Policies/StageActionPolicy.php` (`SUBJECT_UPDATE_PERMISSION`) |
| M7 | Responsável por pessoa e por etapa | fila, carga, substituto | ❌ no motor (Repair e Oficina têm um responsável por OS) | — |
| M8 | Alçadas por valor | média e grande | ❌ | — |
| M9 | Efeitos escolhidos de uma lista fechada | o dono escolhe "baixar estoque aqui", não escreve código | ⚠️ cada ação já guarda o efeito, mas como nome de classe e sem tela | `sale_stage_actions.side_effect_class` |
| M10 | Prazo por estágio e alerta | média e grande | ❌ | — |
| M11 | Tela para editar o fluxo | o dono muda sem programador | ❌ as rotas só listam e executam ações | `SaleFsmActionController`, `ServiceOrderFsmActionController` |
| M12 | Histórico que sirva qualquer documento | OP, OS, venda | ⚠️ a coluna chama `transaction_id`; a OP grava o id dela ali | `sale_stage_history` |
| M13 | Saída do material ligada ao documento certo | baixa por OP e por etapa | ⚠️ **risco lido no código, sem teste**: `ConsumirEstoque` procura reservas por `transaction_id` igual ao id do documento; numa OP de CV esse id não é de venda | `app/Domain/Fsm/SideEffects/ConsumirEstoque.php` |

**Conferências que a tela de edição (M11) precisa fazer ao publicar:**
1. todo estágio não-final tem saída;
2. toda ação crítica tem quem pode executar;
3. a saída do material aparece **exatamente uma vez** em cada caminho do início ao fim;
4. não remove estágio que tem documento dentro (hoje, apagar um estágio deixa a OP de CV sem estágio, porque a ligação está como `nullOnDelete`).

## 4. Os fluxos

### 4.1 Comércio geral (núcleo)

| Estágio | Micro | Pequena | Média | Grande | Observação |
|---|---|---|---|---|---|
| Orçamento | ◐ | ✅ | ✅ | ✅ | |
| Venda / pedido | ✅ | ✅ | ✅ | ✅ | |
| Aguardando pagamento | ◐ | ✅ | ✅ | ✅ | pedido a prazo ou para entrega |
| Separação | — | ◐ | ✅ | ✅ | |
| Conferência e embalagem | — | — | ◐ | ✅ | |
| Nota emitida | ◐ | ◐ | ✅ | ✅ | venda sem nota é caminho feliz (pivô de 2026-05-10) |
| Entregue / retirado | ✅ | ✅ | ✅ | ✅ | |
| Pago | ✅ | ✅ | ✅ | ✅ | |
| Devolução / troca | ✅ | ✅ | ✅ | ✅ | |
| Em espera (com motivo) | — | ◐ | ✅ | ✅ | |
| Cancelada | ✅ | ✅ | ✅ | ✅ | |

- **Saída do material:** micro e pequena na finalização da venda; média e grande reservam no pedido e baixam na separação.
- **Alçadas:** desconto acima do limite (pequena: dono · média: gerente · grande: limite por vendedor); cancelar venda com nota (pequena e média: gerente · grande: 2 aprovações).
- **Hoje:** existem os processos `venda_sem_nota`, `venda_com_nota_manual`, `venda_com_nota_auto` (`SeedDefaultFsmProcesses`) e `venda_com_producao` (`FsmProcessoVendaComProducaoSeeder`). Separação e conferência não existem.

### 4.2 Loja de vestuário

| Estágio | Micro | Pequena | Média | Grande | Observação |
|---|---|---|---|---|---|
| Venda no balcão (PDV) | ✅ | ✅ | ✅ | ✅ | US-VEST-002 |
| Reserva no provador | ◐ | ✅ | ✅ | ✅ | US-VEST-027 (reserva de 24 h) |
| Condicional (leva para provar em casa) | ◐ | ◐ | ◐ | ◐ | prática comum no varejo de moda; **não está no SPEC**, confirmar com o cliente antes |
| Troca / devolução no prazo do CDC | ✅ | ✅ | ✅ | ✅ | US-VEST-021 |
| Crediário próprio | ◐ | ◐ | ◐ | ◐ | US-VEST-026 |
| Encomenda (tamanho ou cor em falta) | ◐ | ◐ | ✅ | ✅ | |
| Pedido online: aguardando pagamento → separação → embalado → enviado → entregue | — | ◐ | ✅ | ✅ | US-VEST-030 |
| Transferência entre lojas | — | — | ◐ | ✅ | |

- **Saída do material:** na venda. Reserva e condicional tiram a peça do **disponível** sem baixar; a baixa só acontece se o cliente ficar com a peça, e a peça devolvida volta ao disponível.
- **Funções:** micro = dono e caixa · pequena = vendedor e caixa (a ROTA LIVRE tem 3 usuários: Admin, Vendas, Caixa) · média = mais estoquista e gerente de loja · grande = várias lojas, comissão por meta.
- **Travas e alçadas:** troca fora do prazo e desconto acima da tabela pedem dono (pequena) ou gerente (média e grande). **Venda com data passada nunca bloqueia** (seção 2).
- **Hoje:** usa os processos padrão do núcleo. Não há processo de fluxo próprio do vestuário.

### 4.3 Comunicação visual — gráfica rápida / balcão

Cartão, panfleto, adesivo pequeno, impressão A3. Ciclo de horas, não de dias.

| Estágio | Micro | Pequena | Média | Grande | Observação |
|---|---|---|---|---|---|
| Atendimento e orçamento rápido | ✅ | ✅ | ✅ | ✅ | |
| Arte (criação e aprovação no mesmo atendimento) | ◐ | ✅ | ✅ | ✅ | aprovação por link a partir da média |
| Pagamento ou sinal | ◐ | ✅ | ✅ | ✅ | no micro, à vista no balcão |
| Fila de produção | ✅ | ✅ | ✅ | ✅ | lista no micro, quadro a partir da pequena |
| Impressão | ✅ | ✅ | ✅ | ✅ | |
| Acabamento | ✅ | ✅ | ✅ | ✅ | |
| Conferência | — | — | ◐ | ✅ | |
| Pronto para retirada | ✅ | ✅ | ✅ | ✅ | aviso automático a partir da pequena |
| Entregue | ✅ | ✅ | ✅ | ✅ | |
| Faturado / pago | ✅ | ✅ | ✅ | ✅ | |

- **Saída do material:** micro na venda, como no varejo; pequena ao concluir a impressão; média e grande por etapa (papel na impressão, laminação no acabamento).

### 4.4 Comunicação visual — impressão digital sem instalação

Banner, lona, adesivo, placa. O cliente busca ou recebe em casa.

| Estágio | Micro | Pequena | Média | Grande | Observação |
|---|---|---|---|---|---|
| Orçamento por m² | ✅ | ✅ | ✅ | ✅ | existe (`quote_draft`) |
| Orçamento enviado, com lembrete | ✅ | ✅ | ✅ | ✅ | lembrete automático a partir da pequena |
| Aprovado | ✅ | ✅ | ✅ | ✅ | existe |
| Aguardando sinal | ◐ | ✅ | ✅ | ✅ | **novo** |
| Arte em criação | ◐ | ◐ | ✅ | ✅ | **novo**; hoje fica dentro de "aprovado" |
| Arte em aprovação, com versões | ✅ | ✅ | ✅ | ✅ | existe sem versões; aprovação por link a partir da pequena |
| Pré-impressão (fechar arquivo, aproveitar mídia) | — | ◐ | ✅ | ✅ | **novo** |
| Aguardando material | — | ◐ | ✅ | ✅ | **novo** |
| Fila de máquina | — | — | ◐ | ✅ | existe (`aguardando_maquina`), mas não dá para desligar (M1) |
| Impressão | ✅ | ✅ | ✅ | ✅ | existe |
| Acabamento | ✅ | ✅ | ✅ | ✅ | existe; sub-etapas (refile, ilhós, solda, bastão) a partir da média |
| Conferência | — | ◐ | ✅ | ✅ | **novo** |
| Pronto para retirada / em rota | ✅ | ✅ | ✅ | ✅ | **novo** (hoje pula direto para entregue) |
| Entregue | ✅ | ✅ | ✅ | ✅ | existe |
| Faturado / pago | ✅ | ✅ | ✅ | ✅ | **novo** no fluxo de CV |
| Em espera (com motivo) | ◐ | ✅ | ✅ | ✅ | **novo** no fluxo de CV |
| Refação (com motivo e custo) | ◐ | ✅ | ✅ | ✅ | existe como ação, sem motivo nem custo |
| Garantia | ✅ | ✅ | ✅ | ✅ | existe, sem efeito |

- **Saída do material:** reserva na aprovação (já existe); baixa ao concluir a impressão (já existe) na pequena, por etapa a partir da média. Refação baixa o consumo extra e alerta a margem.
- **Travas:** sinal pago (isenção pelo dono) · arte aprovada com arquivo e versão antes de imprimir · material disponível antes da fila.

### 4.5 Comunicação visual — com instalação / fachada

ACM, letra-caixa, luminoso, adesivação de frota e vitrine. Inclui tudo da 4.4, mais:

| Estágio | Micro | Pequena | Média | Grande | Observação |
|---|---|---|---|---|---|
| Visita técnica / medição | ◐ | ✅ | ✅ | ✅ | **novo**; no micro, o dono mede na hora |
| Projeto técnico (estrutura, elétrica) | — | ◐ | ✅ | ✅ | **novo** |
| Licença da prefeitura | ◐ | ◐ | ◐ | ◐ | **novo**; só onde a cidade exige licença de publicidade |
| Terceirizado (serralheria, corte a laser, letreiro) | ◐ | ✅ | ✅ | ✅ | **novo**; sai e volta com prazo do fornecedor |
| Pré-montagem | — | ◐ | ✅ | ✅ | **novo** |
| Instalação agendada | ✅ | ✅ | ✅ | ✅ | existe; agenda por equipe a partir da média |
| Em instalação (foto antes e depois, localização) | ✅ | ✅ | ✅ | ✅ | existe, sem fotos |
| Aceite do cliente com assinatura | ✅ | ✅ | ✅ | ✅ | existe; no micro, foto e WhatsApp bastam |
| Nota de material (NF-e) e de serviço (NFS-e) | ✅ | ✅ | ✅ | ✅ | a ação existe e **não emite nada** |
| Manutenção preventiva (luminoso) | — | — | ◐ | ◐ | **novo** |

- **Travas:** acima de 2 m de altura, instalador com NR-35 válida e ART emitida antes de iniciar · medidas confirmadas antes de aprovar o orçamento · fotos e assinatura para concluir.

### 4.6 Comunicação visual — industrial com PCP

Várias máquinas, turnos e setores. Não existe em micro nem em pequena.

| Estágio | Micro | Pequena | Média | Grande | Observação |
|---|---|---|---|---|---|
| Orçamento com custo calculado | — | — | ✅ | ✅ | |
| Aprovado, sinal, arte, aprovação | — | — | ✅ | ✅ | igual à 4.4 |
| Programação do PCP (agrupar ordens por material e máquina) | — | — | ◐ | ✅ | **novo** |
| Requisição de material ao almoxarifado | — | — | ✅ | ✅ | **novo** |
| Fila por máquina com capacidade | — | — | ✅ | ✅ | |
| Impressão com apontamento por operador | — | — | ✅ | ✅ | API de apontamento existe, sem tela |
| Acabamento por setor com apontamento | — | — | ✅ | ✅ | |
| Qualidade, com refugo e motivo | — | — | ✅ | ✅ | **novo** |
| Expedição com romaneio | — | — | ✅ | ✅ | **novo** |
| Instalação | — | — | ◐ | ◐ | usa a 4.5 |
| Fechamento: custo real × orçado | — | — | ◐ | ✅ | **novo** |

- **Modelo:** uma OS gera uma ordem de produção por item, e cada item anda no próprio ritmo. A OS mostra o estado somado ("2 de 3 itens prontos").
- **Saída do material:** por etapa, pelo apontamento real; o refugo baixa à parte, com motivo.

### 4.7 Gráfica offset / comercial

Livro, revista, embalagem, impresso em tiragem. **Não há módulo nem processo hoje.**

| Estágio | Micro | Pequena | Média | Grande | Observação |
|---|---|---|---|---|---|
| Orçamento (tiragem, papel, cores, acabamento) | ✅ | ✅ | ✅ | ✅ | |
| Aprovado e sinal | ✅ | ✅ | ✅ | ✅ | |
| Recebimento do arquivo | ✅ | ✅ | ✅ | ✅ | |
| Pré-impressão (verificação e imposição) | ◐ | ✅ | ✅ | ✅ | |
| Prova e aprovação da prova, com versão | ✅ | ✅ | ✅ | ✅ | |
| Aguardando papel | ◐ | ✅ | ✅ | ✅ | |
| Gravação de chapa | ◐ | ✅ | ✅ | ✅ | o micro costuma terceirizar |
| Impressão | ✅ | ✅ | ✅ | ✅ | fila por máquina a partir da média |
| Secagem | — | — | ◐ | ◐ | |
| Acabamento (corte, dobra, vinco, laminação, encadernação) | ✅ | ✅ | ✅ | ✅ | sub-etapas a partir da média |
| Terceirizado (hot stamping, faca especial) | ◐ | ◐ | ◐ | ◐ | |
| Contagem e conferência | — | ◐ | ✅ | ✅ | |
| Expedição | — | ◐ | ✅ | ✅ | |
| Entregue e faturado | ✅ | ✅ | ✅ | ✅ | |

- **Saída do material:** papel na impressão, **incluindo a perda planejada de acerto de máquina**; chapa na gravação; tinta por estimativa, e pelo apontamento real a partir da média.

### 4.8 Oficina mecânica

**Hoje** (`oficina_mecanica_os`, `OficinaAutoFsmSeeder.php`): recepção → diagnóstico → aguardando aprovação → aguardando peças → em execução → pronto para retirar → entregue, mais cancelado e garantia.

| Estágio | Micro | Pequena | Média | Grande | Observação |
|---|---|---|---|---|---|
| Agendamento | ◐ | ✅ | ✅ | ✅ | **novo** |
| Recepção com checklist, fotos e quilometragem | ◐ | ✅ | ✅ | ✅ | existe a recepção; o checklist é novo |
| Diagnóstico | ✅ | ✅ | ✅ | ✅ | existe |
| Orçamento aprovado pelo cliente (link) | ◐ | ✅ | ✅ | ✅ | existe a espera; o link é novo |
| Compra e cotação de peças | ◐ | ◐ | ✅ | ✅ | |
| Aguardando peças | ✅ | ✅ | ✅ | ✅ | existe |
| Execução por mecânico ou box | ✅ | ✅ | ✅ | ✅ | apontamento a partir da média |
| Serviço terceirizado (retífica, torno) | ◐ | ◐ | ◐ | ◐ | **novo** |
| Teste e inspeção final | — | ◐ | ✅ | ✅ | **novo** |
| Pronto para retirar, com aviso | ✅ | ✅ | ✅ | ✅ | existe; aviso automático é novo |
| Entregue com assinatura | ✅ | ✅ | ✅ | ✅ | |
| Nota de peças (NF-e) e de serviço (NFS-e) | ✅ | ✅ | ✅ | ✅ | |
| Garantia | ✅ | ✅ | ✅ | ✅ | existe |

- **Saída do material:** a peça é reservada na aprovação e baixada ao ser aplicada (média e grande, por item). No micro, na entrega ou no faturamento.

### 4.9 Assistência técnica / reparo

**Hoje** (`os_reparo_padrao`, `FsmProcessoOsReparoPadraoSeeder.php`): recebido → em diagnóstico → aguardando aprovação → aprovado ou rejeitado → aguardando peças → peças chegaram → em execução → pausado → aguardando retirada → entregue, mais cancelado e garantia. É o fluxo mais completo do sistema hoje.

| Estágio | Micro | Pequena | Média | Grande | Observação |
|---|---|---|---|---|---|
| Recebimento com checklist e fotos | ◐ | ✅ | ✅ | ✅ | |
| Laudo técnico | — | ◐ | ✅ | ✅ | **novo** |
| Orçamento aprovado por link | ◐ | ✅ | ✅ | ✅ | |
| Envio ao fabricante (garantia de fábrica) | ◐ | ◐ | ✅ | ✅ | **novo** |
| Teste final | — | ◐ | ✅ | ✅ | **novo** |
| Aviso de aparelho não retirado | ◐ | ✅ | ✅ | ✅ | **novo**; só aviso, sem descarte automático |
| Garantia do serviço | ✅ | ✅ | ✅ | ✅ | existe; prazo legal de 90 dias para bem durável (CDC art. 26, II) |

### 4.10 Fabricação sob receita

**Hoje:** o módulo Fabricação (receitas e ordens de produção) tem status próprio, **fora do FSM**. Onde ele baixa os insumos não foi conferido para este documento.

| Estágio | Micro | Pequena | Média | Grande | Observação |
|---|---|---|---|---|---|
| Planejamento (por pedido ou estoque mínimo) | — | ◐ | ✅ | ✅ | |
| Ordem de produção aberta | ✅ | ✅ | ✅ | ✅ | |
| Separação e reserva dos insumos | ◐ | ✅ | ✅ | ✅ | |
| Em produção | ✅ | ✅ | ✅ | ✅ | por etapa a partir da média |
| Qualidade | — | ◐ | ✅ | ✅ | |
| Apontamento de quantidade produzida e perda | ◐ | ✅ | ✅ | ✅ | |
| Lote e validade (rastreabilidade) | — | ◐ | ◐ | ✅ | |
| Entrada do produto pronto no estoque | ✅ | ✅ | ✅ | ✅ | |
| Expedição | ◐ | ◐ | ✅ | ✅ | |

- **Saída do insumo:** é aqui que a escolha do momento mais aparece. As três opções são ao abrir a ordem, ao iniciar ou ao concluir. O modelo micro baixa ao concluir; o médio e o grande, por etapa e pelo apontamento real da perda.

## 5. Como a empresa troca de porte

A empresa não reinstala nada: troca de modelo. A troca é uma **nova versão do fluxo** (M3):

1. escolhe o modelo novo, inteiro ou só uma parte (por exemplo, subir só a instalação para "média");
2. o sistema mostra o que liga (conferência, fila de máquina), o que muda (a saída do material passa de "concluir impressão" para "por etapa") e o que passa a exigir aprovação;
3. **prévia de impacto** nas ordens em andamento, com a tabela antes→depois (regra mestre de valor e estoque);
4. atribui as funções novas às pessoas. Sem isso, os botões novos não aparecem para ninguém (M5, M6);
5. as ordens em andamento terminam na versão em que nasceram; só as novas usam o modelo novo.

**O caso perigoso:** adiantar a saída do material, por exemplo de "concluir" para "iniciar".
Sem versão, as ordens que já passaram do novo ponto e ainda não chegaram ao antigo **nunca baixam**.
Atrasar a saída é seguro, porque o consumo ignora reserva já consumida e nada baixa duas vezes.

## 6. Ordem de construção sugerida

1. **Motor:** M1 (liga/desliga), M3 (versão), M5 e M6 (funções separadas e liberação por permissão), M4 (travas). Sem isso, os modelos viram processos fixos que ninguém consegue adaptar.
2. **Teste do risco M13**, antes de ligar qualquer fluxo de CV em produção.
3. **Modelos dos tipos com cliente real:** vestuário (ROTA LIVRE), oficina (Martinho) e comunicação visual (pilotos OfficeImpresso).
4. **Tela de configuração** (M11), com as 4 conferências da seção 3.
5. **Alçadas, prazo por etapa e responsável por pessoa** (M7, M8, M10).
6. **Tipos sem cliente hoje** (offset, fabricação sob receita): o desenho fica pronto aqui e a construção espera o primeiro cliente.

Tudo que toca saída de material, cobrança de sinal ou faturamento passa pela **regra mestre de valor e estoque**
([proibicoes.md](../../proibicoes.md)): prova por dois caminhos e tabela antes→depois aprovada antes do merge.

## 7. Fontes e limites

**Do repositório** (conferido no `main` em 2026-10-06): os seeders citados em cada seção,
`app/Domain/Fsm/`, `ManageUserController.php`, os SPECs de [Comunicação Visual](../ComunicacaoVisual/SPEC.md)
e [Vestuario](../Vestuario/SPEC.md), a [grade de produção de concorrentes](../../research/2026-05-prospeccao/33-grade-producao-concorrentes.md)
e o [perfil de Zênite e Mubisys](../../research/2026-05-prospeccao/02-concorrentes-zenite-mubisys.md).

**De fora:** [shopVOX — modelos de fluxo](https://shopvox-pro.helpdocs.io/article/0vnwpogzpx-workflow-templates-explained)
(estágio → etapas, modelo ligado ao produto e escolhido por item, máquina e pessoa por etapa) ·
[Printavo — guia de status](https://printavo.com/blog/status-guide) (status com aviso automático) ·
[Corebridge](https://www.softwareadvice.com.au/software/489064/CoreBridge) (quadro de produção e avisos por marco) ·
[SignAgent](https://support.signagent.com/knowledge/surveying-in-signagent) e
[GoCanvas](https://www.gocanvas.com/mobile-forms-apps/6872-Sign-Installation-Report) (vistoria e relatório de instalação em campo).

**Limites:**
- as faixas de porte são uma proposta, não medição dos clientes;
- os estágios de offset, fabricação sob receita e assistência técnica vêm de prática de mercado, sem cliente do oimpresso para confirmar;
- o "condicional" do vestuário e a licença de publicidade da prefeitura precisam de confirmação com o cliente antes de virar requisito;
- M13 é leitura de código, não foi provado com teste.
