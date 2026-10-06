# App oimpresso — possíveis implementações

06/10/2026 · @Felipe

## Contexto

São 12 implementações possíveis para o app: 5 rápidas, que seguem o padrão atual, 5 que precisam de rota nova no ERP e 2 da comunicação visual. Das 4 regras do app, câmera e biometria já foram liberadas pelo Wagner (falta atualizar o README); offline e o nome REP-P seguem para decidir.

A lista sai da comparação entre os documentos *Estrutura do ERP — Árvore de Módulos e Funcionalidades* e *Casos de Uso do ERP por Segmento e Porte* (Felipe, 06/10/2026) e as 35 telas do app (arquivos em `src/telas/`) `wagnerra23/oimpresso-app` no commit `0099bc6` (PR #81). As referências §N e UC-XX-NN apontam para esses dois documentos.

Nada aqui está aprovado: cada item tem uma coluna **Decisão** para preencher na conversa.

## O que o app já cobre

O app já entrega boa parte do que os documentos chamam de App do Gestor e App do Colaborador (§22). A série recente de PRs da oficina bate com UC-OF-01 e UC-OF-04.

| Área | Já existe no app | Onde está no documento |
| --- | --- | --- |
| Painel | Início, Dashboard e Relatórios só de leitura | §12, UC-PT-01 |
| Ponto | Bater ponto, espelho, justificar e validar fora da área | §7, §22 App do Colaborador |
| Oficina — OS | Lista, detalhe, Nova OS, avançar etapa, cancelar, recusar orçamento, acionar garantia | §17, §19, §21 |
| Oficina — veículos | Cadastro, consulta de placa, km, histórico de OS, peças trocadas, lembrete por km | §19, UC-OF-01, UC-OF-04 |
| Oficina — agenda | Agenda, novo agendamento, cancelar com motivo e abrir OS a partir do agendamento | §19, UC-OF-01 |
| Pessoas | Lista, ficha, nova pessoa com CEP e consentimento LGPD, WhatsApp e ligação | §2 |
| Produtos e estoque | Lista, novo produto sem preço, saldo e movimentações só de leitura | §1, §9 |
| Venda | Venda rápida com carrinho e venda idempotente | §4 (PDV) |
| Financeiro e fiscal | Saldo, contas a receber e a pagar, notas emitidas e rejeitadas só de leitura; links de cobrança (Asaas) | §5, §6 |
| Apoio | Tarefas, notificações, equipe, assistente Jana, Meu menu (até 3 módulos na barra) | §15 (perfis de interface) |

## Ganhos rápidos (itens 1 a 5)

Os cinco itens seguem o padrão que o app já usa e nenhum precisa de permissão nova. Só o item 4 pode mexer em estoque ou cobrança, conforme a etapa, e por isso passa pela regra mestre. Os itens 2 e 3 pedem só campos novos em rotas que já existem.

| # | O quê | Onde está no documento | O que precisa | Decisão |
| --- | --- | --- | --- | --- |
| 1 | Botão de WhatsApp também na OS, no pedido e no orçamento (hoje só em Pessoas; Pedidos só tem "Ligar") | §22 | Só o app: o telefone já vem da API |  |
| 2 | Início com "OS prontas sem retirada", "a receber amanhã" e "orçamentos que vencem em 7 dias" | UC-PT-01, §4 | ERP manda os 3 campos em `/api/app/inicio` |  |
| 3 | Serviços recusados no cartão do veículo, com lista para retomar | UC-OF-02, UC-OF-03, §19 | ERP guarda o recusado por item e devolve no veículo |  |
| 4 | Avançar etapa na Produção (etapas da venda), como já se faz na OS | §17 | Ação da FSM da venda exposta para o app |  |
| 5 | Leitor de código de barras Bluetooth/USB na Venda rápida e em Produtos | UC-OF-07, §22 | Só o app: o leitor digita como teclado, sem câmera. Reverte o "fora de propósito" registrado em `VendaRapida.tsx` |  |

## Ganhos médios (itens 6 a 10)

Os itens 6 a 10 cobrem quem trabalha no pátio e na rua, que os documentos marcam como [App] [MVP]. Cada um precisa de rota nova no ERP antes da tela.

| # | O quê | Onde está no documento | O que precisa | Decisão |
| --- | --- | --- | --- | --- |
| 6 | Fila de aprovações do gestor: desconto, compra e pagamento | UC-PT-05, §4, §10, §22 App do Gestor | Alçadas no ERP; a tela reaproveita o modelo de Validar ponto (`FilaGestor.tsx`) |  |
| 7 | Check-in do veículo: km, combustível, avarias, objetos no carro e assinatura do cliente na tela | UC-OF-01, §19 Recepção | Rota de check-in ligada à OS; fotos liberadas (câmera aprovada pelo Wagner) |  |
| 8 | Quadro do mecânico: Meus serviços com relógio para iniciar, pausar e concluir | UC-OF-06, §19, §22 App do Mecânico | Apontamento de horas por serviço no ERP; base para produtividade e comissão |  |
| 9 | Orçamento com link de aprovação por WhatsApp e status Visualizado ou Aprovado | UC-CV-01, UC-OF-01, §4 | O link de aprovação da OS já existe no ERP web (o app diz "fica no computador"): expor no app e estender ao orçamento da comunicação visual; pode se juntar aos links de pagamento |  |
| 10 | App de campo: agenda do dia, status Em deslocamento, Em andamento e Concluído com hora e GPS, e assinatura do cliente | UC-CV-07, UC-OF-12, UC-OF-13, §20 | Status de agendamento de campo no ERP; parte da Agenda da oficina que já existe; o app já tem permissão de localização |  |

## Comunicação visual (itens 11 e 12)

Os itens 11 e 12 são os mais pedidos pela comunicação visual, e hoje o app tem pouco desse lado. Pela regra Tier 0, o celular não calcula preço: a calculadora tem que mandar as medidas e mostrar o valor que o ERP devolver.

| # | O quê | Onde está no documento | O que precisa | Decisão |
| --- | --- | --- | --- | --- |
| 11 | Calculadora de m²: produto-modelo, largura × altura × quantidade e acabamentos, devolvendo área, preço e prazo | UC-CV-01, §18 Configurador | Rota de cálculo no ERP com fórmula e acabamentos cadastrados (§1 Precificação) |  |
| 12 | Prova de arte: ver a versão, mandar ao cliente e ver Aprovada ou Reprovada, registrando quem aprovou, data e hora | UC-CV-04, §18 Provas de arte | Provas versionadas no ERP e bloqueio da produção sem prova aprovada |  |

## Regras para o Wagner decidir

Câmera e biometria já foram liberadas pelo Wagner: a ADR 0383 vale só para o ponto. Falta corrigir o README do app, que ainda diz "nunca adicionar permissão de câmera". Offline e o nome REP-P seguem para decidir.

| Regra | O que os documentos pedem | Regra hoje no app | Opção possível | Decisão |
| --- | --- | --- | --- | --- |
| Câmera | Fotos de avarias, inspeção, campo e despesas; leitura de código pela câmera (§22) | "Nunca adicionar permissão de câmera" (README e AndroidManifest) | Câmera só na OS e no campo, nunca no ponto. (A permissão de galeria já está declarada no iOS, mas nenhuma tela usa: a OS mostra só a contagem de fotos) | Liberada pelo Wagner (QR e foto); falta atualizar o README e o AndroidManifest |
| Biometria | Login com biometria (§22) | Sem biometria (ADR 0383) | Biometria só para destravar o app, nunca para o ponto | Liberada pelo Wagner no login; nunca para o ponto |
| Offline | Offline com sincronização (§22) | Nada é gravado sem conexão; marcação só com NSR do servidor | Offline só para consulta, sem gravar nada |  |
| Nome REP-P | §7 e UC-PT-06 falam em "ponto REP-P" | O app não usa o termo até o registro no INPI e o certificado ICP-Brasil (ERP #8417) | Corrigir os documentos antes de usar em material comercial |  |

## O que continua no computador

Estes módulos dos documentos não valem uma tela no celular: são configuração, rotina de escritório ou têm um cliente só.

- Emissão e configuração fiscal, SPED e livros (§5)
- CNAB, borderôs e conciliação bancária (§6)
- Folha, eSocial e FGTS Digital (§7)
- Construtor de painéis de BI e designer de relatórios .fr3 (§12, §14)
- MRP, plano mestre e Gantt de produção (§3)
- Módulo Associação: o próprio documento dá prioridade baixa, com um cliente só (§11)

## Próximos passos

- [ ] Preencher a coluna Decisão dos itens 1 a 12 com o Wagner
- [ ] Atualizar o README do app (câmera e biometria liberadas, ADR 0383 só para o ponto)
- [ ] Decidir as 2 regras restantes: offline e nome REP-P
- [ ] Para cada item aprovado, abrir uma issue no `oimpresso-app` e, quando precisar, a rota correspondente no ERP
- [ ] Terminar antes a issue #75 (teste da tela de Pedidos), que é a tarefa ativa do Luiz
