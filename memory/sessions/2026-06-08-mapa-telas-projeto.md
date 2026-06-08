# Mapa de Telas — projeto oimpresso (panorâmico)

> Gerado 2026-06-08 por `scripts/gen-mapa-telas.py`. Fonte: filesystem (`resources/js/Pages/**/*.tsx`) + charters ao lado.

## Resumo

| Métrica | Valor |
|---|---:|
| **Telas** (páginas Inertia) | **232** |
| Componentes de apoio (`_components/`, atoms, helpers) | 216 |
| Total de arquivos `.tsx` em Pages | 448 |
| Áreas/módulos | 39 |
| Telas com charter (contrato do que deveria ter) | 132 (56%) |
| Charter `live` (aprovado por Wagner) | 59 |
| **Telas sem charter** (gap: contrato indefinido) | 100 (43%) |

**Legenda da coluna _Deveria ter_:** se há charter, mostra a Mission (contrato). Se vazio (`—`) numa tela com charter, a Mission ainda não foi escrita; `❌` = tela **sem contrato definido** — primeiro gap a fechar.

> Componentes de apoio não são listados tela a tela (são pedaços internos de uma tela). Cada módulo mostra `+N componentes` ao final.

## Índice por módulo

| Módulo | Telas | Com charter | Comp. | Briefing |
|---|---:|---:|---:|---|
| [Ponto](#ponto) | 20 | 0/20 | 2 | [BRIEFING](memory/requisitos/Ponto/BRIEFING.md) |
| [ads](#ads) | 19 | 0/19 | 0 | [BRIEFING](memory/requisitos/ADS/BRIEFING.md) |
| [Financeiro](#financeiro) | 19 | 12/19 | 37 | [BRIEFING](memory/requisitos/Financeiro/BRIEFING.md) |
| [Essentials](#essentials) | 13 | 3/13 | 0 | [BRIEFING](memory/requisitos/Essentials/BRIEFING.md) |
| [Repair](#repair) | 13 | 12/13 | 0 | [BRIEFING](memory/requisitos/Repair/BRIEFING.md) |
| [Jana](#jana) | 12 | 8/12 | 5 | [BRIEFING](memory/requisitos/Jana/BRIEFING.md) |
| [OficinaAuto](#oficinaauto) | 11 | 11/11 | 22 | [BRIEFING](memory/requisitos/OficinaAuto/BRIEFING.md) |
| [Atendimento](#atendimento) | 9 | 6/9 | 7 | — |
| [ProjectMgmt](#projectmgmt) | 9 | 3/9 | 0 | [BRIEFING](memory/requisitos/ProjectMgmt/BRIEFING.md) |
| [Admin](#admin) | 8 | 8/8 | 25 | [BRIEFING](memory/requisitos/Admin/BRIEFING.md) |
| [Produto](#produto) | 8 | 8/8 | 0 | — |
| [Sells](#sells) | 8 | 8/8 | 34 | [BRIEFING](memory/requisitos/Sells/BRIEFING.md) |
| [Cliente](#cliente) | 7 | 7/7 | 27 | — |
| [Fiscal](#fiscal) | 7 | 7/7 | 13 | [BRIEFING](memory/requisitos/Fiscal/BRIEFING.md) |
| [Site](#site) | 7 | 0/7 | 0 | — |
| [governance](#governance) | 6 | 6/6 | 0 | [BRIEFING](memory/requisitos/Governance/BRIEFING.md) |
| [MemCofre](#memcofre) | 6 | 0/6 | 0 | — |
| [NfeBrasil](#nfebrasil) | 6 | 4/6 | 3 | [BRIEFING](memory/requisitos/NfeBrasil/BRIEFING.md) |
| [RecurringBilling](#recurringbilling) | 6 | 6/6 | 7 | [BRIEFING](memory/requisitos/RecurringBilling/BRIEFING.md) |
| [Purchase](#purchase) | 4 | 2/4 | 0 | — |
| [kb](#kb) | 3 | 3/3 | 13 | [BRIEFING](memory/requisitos/KB/BRIEFING.md) |
| [Nfse](#nfse) | 3 | 0/3 | 0 | [BRIEFING](memory/requisitos/NFSe/BRIEFING.md) |
| [team-mcp](#team-mcp) | 3 | 1/3 | 0 | — |
| [TransactionPayment](#transactionpayment) | 3 | 3/3 | 0 | — |
| [Auditoria](#auditoria) | 2 | 1/2 | 0 | [BRIEFING](memory/requisitos/Auditoria/BRIEFING.md) |
| [Settings](#settings) | 2 | 2/2 | 5 | — |
| [StockAdjustment](#stockadjustment) | 2 | 2/2 | 0 | — |
| [StockTransfer](#stocktransfer) | 2 | 2/2 | 0 | — |
| [superadmin](#superadmin) | 2 | 2/2 | 0 | [BRIEFING](memory/requisitos/Superadmin/BRIEFING.md) |
| [Whatsapp](#whatsapp) | 2 | 1/2 | 12 | [BRIEFING](memory/requisitos/Whatsapp/BRIEFING.md) |
| [_Showcase](#showcase) | 2 | 0/2 | 0 | — |
| [Compras](#compras) | 1 | 1/1 | 4 | [BRIEFING](memory/requisitos/Compras/BRIEFING.md) |
| [ComunicacaoVisual](#comunicacaovisual) | 1 | 1/1 | 0 | — |
| [ConsultaOs](#consultaos) | 1 | 0/1 | 0 | — |
| [Home](#home) | 1 | 1/1 | 0 | — |
| [Manufacturing](#manufacturing) | 1 | 1/1 | 0 | [BRIEFING](memory/requisitos/Manufacturing/BRIEFING.md) |
| [Modules](#modules) | 1 | 0/1 | 0 | — |
| [Tarefas](#tarefas) | 1 | 0/1 | 0 | — |
| [Vestuario](#vestuario) | 1 | 0/1 | 0 | [BRIEFING](memory/requisitos/Vestuario/BRIEFING.md) |

## Ponto

📋 Estado do módulo: [memory/requisitos/Ponto/BRIEFING.md](memory/requisitos/Ponto/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Aprovacoes/Index](resources/js/Pages/Ponto/Aprovacoes/Index.tsx) | ❌ | — |
| [BancoHoras/Index](resources/js/Pages/Ponto/BancoHoras/Index.tsx) | ❌ | — |
| [BancoHoras/Show](resources/js/Pages/Ponto/BancoHoras/Show.tsx) | ❌ | — |
| [Colaboradores/Edit](resources/js/Pages/Ponto/Colaboradores/Edit.tsx) | ❌ | — |
| [Colaboradores/Index](resources/js/Pages/Ponto/Colaboradores/Index.tsx) | ❌ | — |
| [Configuracoes/Index](resources/js/Pages/Ponto/Configuracoes/Index.tsx) | ❌ | — |
| [Configuracoes/Reps](resources/js/Pages/Ponto/Configuracoes/Reps.tsx) | ❌ | — |
| [Dashboard/Index](resources/js/Pages/Ponto/Dashboard/Index.tsx) | ❌ | — |
| [Escalas/Form](resources/js/Pages/Ponto/Escalas/Form.tsx) | ❌ | — |
| [Escalas/Index](resources/js/Pages/Ponto/Escalas/Index.tsx) | ❌ | — |
| [Espelho/Index](resources/js/Pages/Ponto/Espelho/Index.tsx) | ❌ | — |
| [Espelho/Show](resources/js/Pages/Ponto/Espelho/Show.tsx) | ❌ | — |
| [Importacoes/Create](resources/js/Pages/Ponto/Importacoes/Create.tsx) | ❌ | — |
| [Importacoes/Index](resources/js/Pages/Ponto/Importacoes/Index.tsx) | ❌ | — |
| [Importacoes/Show](resources/js/Pages/Ponto/Importacoes/Show.tsx) | ❌ | — |
| [Intercorrencias/Create](resources/js/Pages/Ponto/Intercorrencias/Create.tsx) | ❌ | — |
| [Intercorrencias/Index](resources/js/Pages/Ponto/Intercorrencias/Index.tsx) | ❌ | — |
| [Intercorrencias/Show](resources/js/Pages/Ponto/Intercorrencias/Show.tsx) | ❌ | — |
| [Relatorios/Index](resources/js/Pages/Ponto/Relatorios/Index.tsx) | ❌ | — |
| [Welcome](resources/js/Pages/Ponto/Welcome.tsx) | ❌ | — |

_+2 componentes de apoio (não listados)._

## ads

📋 Estado do módulo: [memory/requisitos/ADS/BRIEFING.md](memory/requisitos/ADS/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Admin/Confidence](resources/js/Pages/ads/Admin/Confidence.tsx) | ❌ | — |
| [Admin/Conflicts](resources/js/Pages/ads/Admin/Conflicts.tsx) | ❌ | — |
| [Admin/DecisaoShow](resources/js/Pages/ads/Admin/DecisaoShow.tsx) | ❌ | — |
| [Admin/Decisoes](resources/js/Pages/ads/Admin/Decisoes.tsx) | ❌ | — |
| [Admin/Graph](resources/js/Pages/ads/Admin/Graph.tsx) | ❌ | — |
| [Admin/Learning](resources/js/Pages/ads/Admin/Learning.tsx) | ❌ | — |
| [Admin/MetaSkills](resources/js/Pages/ads/Admin/MetaSkills.tsx) | ❌ | — |
| [Admin/Metricas](resources/js/Pages/ads/Admin/Metricas.tsx) | ❌ | — |
| [Admin/Patterns](resources/js/Pages/ads/Admin/Patterns.tsx) | ❌ | — |
| [Admin/Policy](resources/js/Pages/ads/Admin/Policy.tsx) | ❌ | — |
| [Admin/ProjectShow](resources/js/Pages/ads/Admin/ProjectShow.tsx) | ❌ | — |
| [Admin/Projects](resources/js/Pages/ads/Admin/Projects.tsx) | ❌ | — |
| [Admin/Skills/Edit](resources/js/Pages/ads/Admin/Skills/Edit.tsx) | ❌ | — |
| [Admin/Skills/Index](resources/js/Pages/ads/Admin/Skills/Index.tsx) | ❌ | — |
| [Admin/Skills/Review](resources/js/Pages/ads/Admin/Skills/Review.tsx) | ❌ | — |
| [Admin/Skills/Show](resources/js/Pages/ads/Admin/Skills/Show.tsx) | ❌ | — |
| [Admin/Skills/Test](resources/js/Pages/ads/Admin/Skills/Test.tsx) | ❌ | — |
| [Admin/TeamScopes](resources/js/Pages/ads/Admin/TeamScopes.tsx) | ❌ | — |
| [Admin/Tools](resources/js/Pages/ads/Admin/Tools.tsx) | ❌ | — |

## Financeiro

📋 Estado do módulo: [memory/requisitos/Financeiro/BRIEFING.md](memory/requisitos/Financeiro/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Advisor/Dashboard](resources/js/Pages/Financeiro/Advisor/Dashboard.tsx) | 📝 | Dar ao contador parceiro (eliana) uma visão consolidada e **somente leitura** dos clientes que lhe concederam acesso, com entrada rápida pras telas financeiras de cada cliente (... |
| [Advisor/Login](resources/js/Pages/Financeiro/Advisor/Login.tsx) | 📝 | Dar ao contador (persona Eliana) uma porta de entrada confiável e de marca consistente para o portal financeiro isolado, onde ele acessa os dados dos clientes dele. |
| [AssinaturaAtualizar](resources/js/Pages/Financeiro/AssinaturaAtualizar.tsx) | 📝 | Permitir que admin/operador altere com segurança o **valor**, **ciclo** ou **forma de pagamento** de uma assinatura recorrente ativa, com **preview do impacto** (diff antigo → n... |
| [Caixa/Index](resources/js/Pages/Financeiro/Caixa/Index.tsx) | ✅ | Permitir que Larissa @ ROTA LIVRE encontre o **histórico de fechamentos de caixa** (turnos do vendedor) navegando pelo menu Financeiro, sem precisar abrir a tela POS — diferenci... |
| [Categorias/Index](resources/js/Pages/Financeiro/Categorias/Index.tsx) | ❌ | — |
| [Cobranca/Index](resources/js/Pages/Financeiro/Cobranca/Index.tsx) | ✅ | Eliana acompanha **toda a cobrança gerada (boleto · pix · pix automático · cartão) em uma view única** com funil 5 etapas + 4 KPIs (3 fixos + 1 contextual) + tabela rica chip co... |
| [Conciliacao/Index](resources/js/Pages/Financeiro/Conciliacao/Index.tsx) | ✅ | Reunir num só lugar todas as linhas de extrato bancário pendentes — de **upload OFX** e de **sync API do banco** — e deixar o usuário conciliá-las com títulos abertos. |
| [Configuracoes/Contador](resources/js/Pages/Financeiro/Configuracoes/Contador.tsx) | 📝 | Permitir que o **dono do negócio** conceda ao contador parceiro acesso **somente-leitura** ao Financeiro (Visão Unificada + Relatórios DRE/Fluxo) num portal próprio do contador ... |
| [ContasBancarias/Index](resources/js/Pages/Financeiro/ContasBancarias/Index.tsx) | ✅ | Listar contas bancárias do business + permitir configurar dados pra emissão de boleto (banco, agência, carteira, beneficiário) por conta. |
| [ContasPagar/Index](resources/js/Pages/Financeiro/ContasPagar/Index.tsx) | ❌ | — |
| [ContasReceber/Index](resources/js/Pages/Financeiro/ContasReceber/Index.tsx) | ❌ | — |
| [Dashboard/Index](resources/js/Pages/Financeiro/Dashboard/Index.tsx) | ❌ | — |
| [Dre/Index](resources/js/Pages/Financeiro/Dre/Index.tsx) | ✅ | Wagner (dono) e Eliana (financeiro) respondem **"deu lucro este mês?"** em <60s, com leitura hierárquica clássica de DRE (Receita bruta → Deduções → Receita líquida → Custos → L... |
| [Extrato/Index](resources/js/Pages/Financeiro/Extrato/Index.tsx) | ✅ | Mostrar extrato (saldo + lançamentos + totais) de uma conta bancária do business atual, filtrável por período. |
| [Fluxo/Index](resources/js/Pages/Financeiro/Fluxo/Index.tsx) | ✅ | Mostrar **fluxo de caixa em duas óticas** (Projetado dia-a-dia próximos 35d + Realizado mês-a-mês últimos 12m) numa view única com tabs — sem Eliana precisar abrir Contas a Rece... |
| [PlanoContas/Index](resources/js/Pages/Financeiro/PlanoContas/Index.tsx) | ❌ | — |
| [Relatorios/Index](resources/js/Pages/Financeiro/Relatorios/Index.tsx) | ❌ | — |
| [Unificado/Index](resources/js/Pages/Financeiro/Unificado/Index.tsx) | ✅ | Tela única de **fluxo financeiro do mês** que mistura **Pagar / Pagas / Receber / Recebidas** em uma view só, evitando que Eliana abra 4 menus diferentes pra responder "quanto e... |
| [Unificado/Novo](resources/js/Pages/Financeiro/Unificado/Novo.tsx) | ❌ | — |

_+37 componentes de apoio (não listados)._

## Essentials

📋 Estado do módulo: [memory/requisitos/Essentials/BRIEFING.md](memory/requisitos/Essentials/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Documents/Index](resources/js/Pages/Essentials/Documents/Index.tsx) | ❌ | — |
| [Holidays/Index](resources/js/Pages/Essentials/Holidays/Index.tsx) | ✅ | Cadastro de **feriados do business** opcionalmente escopados por `business_location` (matriz/filial). |
| [Knowledge/Create](resources/js/Pages/Essentials/Knowledge/Create.tsx) | ❌ | — |
| [Knowledge/Edit](resources/js/Pages/Essentials/Knowledge/Edit.tsx) | ❌ | — |
| [Knowledge/Index](resources/js/Pages/Essentials/Knowledge/Index.tsx) | ✅ | Organizar **manuais, procedimentos e artigos internos** em estrutura hierárquica de 3 níveis (Livro → Seção → Artigo). |
| [Knowledge/Show](resources/js/Pages/Essentials/Knowledge/Show.tsx) | ❌ | — |
| [Messages/Index](resources/js/Pages/Essentials/Messages/Index.tsx) | ❌ | — |
| [Reminders/Index](resources/js/Pages/Essentials/Reminders/Index.tsx) | ✅ | Permitir que cada usuário cadastre **avisos pessoais** (data + hora + repetição) sem poluir agenda de terceiros. |
| [Settings/Index](resources/js/Pages/Essentials/Settings/Index.tsx) | ❌ | — |
| [Todo/Create](resources/js/Pages/Essentials/Todo/Create.tsx) | ❌ | — |
| [Todo/Edit](resources/js/Pages/Essentials/Todo/Edit.tsx) | ❌ | — |
| [Todo/Index](resources/js/Pages/Essentials/Todo/Index.tsx) | ❌ | — |
| [Todo/Show](resources/js/Pages/Essentials/Todo/Show.tsx) | ❌ | — |

## Repair

📋 Estado do módulo: [memory/requisitos/Repair/BRIEFING.md](memory/requisitos/Repair/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Dashboard/Index](resources/js/Pages/Repair/Dashboard/Index.tsx) | ✅ | Visão consolidada de OS Repair (KPIs + status + equipe + tendências) num único panorama, **sem ações de mutação**. |
| [DeviceModels/Create](resources/js/Pages/Repair/DeviceModels/Create.tsx) | 📝 | Criar novo modelo de aparelho no catálogo Repair (compartilhado entre verticais). |
| [DeviceModels/Edit](resources/js/Pages/Repair/DeviceModels/Edit.tsx) | 📝 | Editar modelo existente do catálogo Repair. |
| [DeviceModels/Index](resources/js/Pages/Repair/DeviceModels/Index.tsx) | 📝 | Listar catálogo de modelos de aparelhos atendidos por oficina (compartilhado entre verticais Repair). |
| [Index](resources/js/Pages/Repair/Index.tsx) | ❌ | — |
| [JobSheet/AddParts](resources/js/Pages/Repair/JobSheet/AddParts.tsx) | 📝 | Adicionar peças (variations) à OS pra cobrar do cliente. |
| [JobSheet/Create](resources/js/Pages/Repair/JobSheet/Create.tsx) | 📝 | Criar nova OS preservando ergonomia do Blade legado (cliente + aparelho + defeitos + checklist) com fluxo "Salvar e adicionar peças" / "Salvar e upload docs" mantido. |
| [JobSheet/Edit](resources/js/Pages/Repair/JobSheet/Edit.tsx) | 📝 | Editar dados de OS preservando coexistência FSM/legacy: campos principais (cliente, aparelho, defeitos, status legacy, checklist) editáveis. |
| [JobSheet/Index](resources/js/Pages/Repair/JobSheet/Index.tsx) | ✅ | Listar e filtrar Ordens de Serviço por status, cliente, equipe e local — ponto de entrada pra ações de OS. |
| [JobSheet/Show](resources/js/Pages/Repair/JobSheet/Show.tsx) | 📝 | Detalhe completo de OS com FSM panel pra executar transições canônicas (ADR 0143 quando pipeline iniciado) OU mostrar empty state "Iniciar pipeline FSM" quando legacy. |
| [ProducaoOficina/Index](resources/js/Pages/Repair/ProducaoOficina/Index.tsx) | • | Visão de produção em **kanban de 5 colunas** (Recepção → Diagnóstico → Aguardando peças → Em execução → Pronto) pra operadores enxergarem o fluxo do dia em monitor 1280px **sem ... |
| [Show](resources/js/Pages/Repair/Show.tsx) | 📝 | Detalhe da VENDA-de-reparo (Transaction sub_type='repair'). |
| [Status/Index](resources/js/Pages/Repair/Status/Index.tsx) | ✅ | Configurar os status que ordens de serviço (Repair) podem assumir — CRUD simples administrativo. |

## Jana

📋 Estado do módulo: [memory/requisitos/Jana/BRIEFING.md](memory/requisitos/Jana/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Admin/Custos/Index](resources/js/Pages/Jana/Admin/Custos/Index.tsx) | ❌ | — |
| [Admin/Governanca/Index](resources/js/Pages/Jana/Admin/Governanca/Index.tsx) | ✅ | Cockpit de **governança do MCP server** ([ADR 0053](../../../../../memory/decisions/0053-mcp-server-governanca-como-produto.md)) — único ERP BR com MCP exposto como produto. |
| [Admin/Qualidade/Index](resources/js/Pages/Jana/Admin/Qualidade/Index.tsx) | ❌ | — |
| [Admin/Roadmap](resources/js/Pages/Jana/Admin/Roadmap.tsx) | ✅ | Visualizar cronologicamente os **cycles ativos** + **tasks (US-*)** do MCP como Gantt interativo, agrupado por módulo, com filtros por cycle/owner/priority/module e dependency a... |
| [Brief/Index](resources/js/Pages/Jana/Brief/Index.tsx) | ❌ | — |
| [Chat](resources/js/Pages/Jana/Chat.tsx) | ✅ | Conversar com a Jana (IA assistente do oimpresso) pra **consultar dados** (vendas, OS, financeiro, estoque) e **pedir ações cross-módulo** (criar OS, registrar pagamento, listar... |
| [Cockpit](resources/js/Pages/Jana/Cockpit.tsx) | • | Cockpit do **Analista IA (Jana)** — entrega brief diário, monitora KPIs, detecta anomalias, sugere ações HITL e responde via chat single-thread. |
| [Dashboard](resources/js/Pages/Jana/Dashboard.tsx) | ✅ | Visão consolidada das **metas ativas do business** com farol verde/amarelo/vermelho, série temporal últimas 12 janelas e projeção linear. |
| [Memoria](resources/js/Pages/Jana/Memoria.tsx) | ✅ | Tela LGPD-first onde dono/gestor **vê, edita e apaga fatos** que a Jana lembrou sobre o business (`copiloto_memoria_facts`). |
| [Painel](resources/js/Pages/Jana/Painel.tsx) | ✅ | Dar ao Wagner (e Larissa/Eliana) **visão executiva diária do negócio em 1 tela**: o que aconteceu, o que está crítico, o que a IA sugere fazer HOJE. |
| [Pro](resources/js/Pages/Jana/Pro.tsx) | ✅ | Converter o usuário do plano **Grátis** pro **Jana Pro** numa única tela de decisão (estilo checkout Stripe): mostrar o valor com **prova ao vivo** (a Jana lendo dados reais do ... |
| [Regras/Index](resources/js/Pages/Jana/Regras/Index.tsx) | ❌ | — |

_+5 componentes de apoio (não listados)._

## OficinaAuto

📋 Estado do módulo: [memory/requisitos/OficinaAuto/BRIEFING.md](memory/requisitos/OficinaAuto/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [AprovacaoPublica](resources/js/Pages/OficinaAuto/AprovacaoPublica.tsx) | ✅ | Cliente final (não-User) aprova ou rejeita um orçamento de OS da oficina automotiva em menos de 30s, sem precisar criar conta, com nível de segurança suficiente pra ter validade... |
| [ProducaoOficina/Index](resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx) | ✅ | Dar à oficina um painel Kanban tempo-real pra o gerente movimentar OS entre estágios via drag-drop (confirmação em transições críticas, [ADR 0143](../../../../../memory/decision... |
| [ServiceOrders/Board](resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx) | ✅ | Dar ao operador da oficina (Martinho · mecânica pesada de caminhão) uma visão de fluxo de trabalho das Ordens de Serviço — da recepção do veículo à retirada pelo cliente — onde ... |
| [ServiceOrders/Create](resources/js/Pages/OficinaAuto/ServiceOrders/Create.tsx) | ✅ | Permitir abertura rápida de OS pelo atendente em ≤ 30s — escolher vehicle (existente ou criar inline), order_type, dados mínimos do trabalho, e abrir em status `aberta` ou já av... |
| [ServiceOrders/Edit](resources/js/Pages/OficinaAuto/ServiceOrders/Edit.tsx) | ✅ | Editar Ordem de Servico existente em **modo FOCO** (sem SubNav, Wave 5 PR #1631 skill `pageheader-canon` Fase 4-bis) — usuario foca em 9 campos basicos do form (veiculo, status,... |
| [ServiceOrders/Index](resources/js/Pages/OficinaAuto/ServiceOrders/Index.tsx) | ✅ | Dashboard operacional pra atendente/gerente da oficina decidir próxima ação em cada OS — visão consolidada de OS abertas, em serviço, aguardando aprovação, atrasadas (locação ov... |
| [ServiceOrders/Show](resources/js/Pages/OficinaAuto/ServiceOrders/Show.tsx) | ✅ | Tela única-fonte-da-verdade sobre 1 OS — mecânico/atendente acompanha estado FSM, próximas ações disponíveis, histórico append-only de transições, valor acumulado (locação ativa). |
| [Vehicles/Create](resources/js/Pages/OficinaAuto/Vehicles/Create.tsx) | ✅ | Cadastrar novo veiculo (scaffold V0 US-OFICINA-001) em form simples modo FOCO (sem SubNav) — atendente registra identificacao (placa Mercosul principal + secundaria, chassi prin... |
| [Vehicles/Edit](resources/js/Pages/OficinaAuto/Vehicles/Edit.tsx) | ✅ | Editar dados de veiculo cadastrado (scaffold V0 US-OFICINA-001) em form simples modo FOCO (sem SubNav) — usuario atualiza identificacao (placa Mercosul principal + secundaria, c... |
| [Vehicles/Index](resources/js/Pages/OficinaAuto/Vehicles/Index.tsx) | ✅ | Cadastro-fonte único de veículos pra o módulo — atendente busca por placa antes de abrir OS, gerente vê histórico de OS por vehicle, importer Firebird popula tabela em massa pra... |
| [Vehicles/Show](resources/js/Pages/OficinaAuto/Vehicles/Show.tsx) | 📝 | Dar ao mecânico/atendente uma visão única do veículo: dados de identificação + situação atual (`current_status` via badge canon) + histórico de ordens de serviço, com caminho di... |

_+22 componentes de apoio (não listados)._

## Atendimento

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [CaixaUnificada/Index](resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx) | ✅ | Tela única que centraliza todas as conversas omnichannel do business num **3-col visual Cowork** (chips canal em cima · lista esquerda · thread central · contexto direita), com ... |
| [Channels/Index](resources/js/Pages/Atendimento/Channels/Index.tsx) | ✅ | Tela onde gerente cadastra/parea/desativa os canais do business (Whatsapp Baileys / Meta Cloud / Z-API hoje · IG/FB/Email preview-only). |
| [Channels/Show](resources/js/Pages/Atendimento/Channels/Show.tsx) | ❌ | — |
| [Csat/Index](resources/js/Pages/Atendimento/Csat/Index.tsx) | ❌ | — |
| [Inbox/Index](resources/js/Pages/Atendimento/Inbox/Index.tsx) | ✅ | Tela única que centraliza todas as conversas omnichannel do business (Whatsapp Baileys hoje · Whatsapp Meta Cloud · Whatsapp Z-API · futuramente Instagram DM, Messenger, Email, ... |
| [JanaTemplates](resources/js/Pages/Atendimento/JanaTemplates.tsx) | ✅ | Permitir que o admin do business configure 2 coisas: (1) ligar/desligar o bot Jana global do business e (2) nomear os templates HSM aprovados pra disparos automáticos de repair/... |
| [Macros/Index](resources/js/Pages/Atendimento/Macros/Index.tsx) | ✅ | CRUD das macros do business — respostas pré-formatadas que atendente dispara via slash `/<atalho>` ou botão no composer da Inbox. |
| [Macros/Variants](resources/js/Pages/Atendimento/Macros/Variants.tsx) | ❌ | — |
| [Metricas/Index](resources/js/Pages/Atendimento/Metricas/Index.tsx) | ✅ | Dashboard executivo do líder de atendimento — responde "quanto tô gastando em WhatsApp?", "Jana tá resolvendo quanto sozinha?", "estou batendo SLA?". |

_+7 componentes de apoio (não listados)._

## ProjectMgmt

📋 Estado do módulo: [memory/requisitos/ProjectMgmt/BRIEFING.md](memory/requisitos/ProjectMgmt/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Activity/Index](resources/js/Pages/ProjectMgmt/Activity/Index.tsx) | ❌ | — |
| [Backlog/Index](resources/js/Pages/ProjectMgmt/Backlog/Index.tsx) | ❌ | — |
| [Board/DetailSheet](resources/js/Pages/ProjectMgmt/Board/DetailSheet.tsx) | ❌ | — |
| [Board/Index](resources/js/Pages/ProjectMgmt/Board/Index.tsx) | ✅ | Kanban board Jira-style pra gerenciar tarefas/épicos do oimpresso por ciclos: navegação rápida via J/K, ações E/A pra mover status, drawer DetailSheet ao clicar card mostra desc... |
| [Burndown/Index](resources/js/Pages/ProjectMgmt/Burndown/Index.tsx) | ❌ | — |
| [Inbox/Index](resources/js/Pages/ProjectMgmt/Inbox/Index.tsx) | 📝 | Caixa de entrada dedicada por-pessoa: mostrar as notificações do usuário autenticado (`mcp_inbox_notifications`) agrupadas por tipo, permitir marcar-lido (individual + todas) e ... |
| [MyWork/Index](resources/js/Pages/ProjectMgmt/MyWork/Index.tsx) | ❌ | — |
| [Roadmap/Index](resources/js/Pages/ProjectMgmt/Roadmap/Index.tsx) | ❌ | — |
| [Triage/Index](resources/js/Pages/ProjectMgmt/Triage/Index.tsx) | 📝 | Dar ao time não-técnico (e futuros clientes B2B) uma tela dedicada pra **triar** tasks órfãs — atribuir dono + prioridade (+ cycle/epic opcional) inline, sem abrir cada task. |

## Admin

📋 Estado do módulo: [memory/requisitos/Admin/BRIEFING.md](memory/requisitos/Admin/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [FeatureFlags/Index](resources/js/Pages/Admin/FeatureFlags/Index.tsx) | 📝 | Painel read-mostly Wagner-only pra inspecionar o estado das feature flags GrowthBook self-hosted (por environment + rules biz-{N}) e o audit log recente das mudanças. |
| [FeatureFlags/Show](resources/js/Pages/Admin/FeatureFlags/Show.tsx) | 📝 | — |
| [GovernanceV4](resources/js/Pages/Admin/GovernanceV4.tsx) | 📝 | Command center governance v4 — visualizar 28 waves consecutivas (W11-W28) + 34 módulos × 4 buckets canônicos + drift detection + Initiatives (Cortex-style) + AI suggestions READ... |
| [GovernanceV4Dashboard](resources/js/Pages/Admin/GovernanceV4Dashboard.tsx) | 📝 | Dashboard Wagner-only que mostra ranking intra-bucket dos módulos sob rubricas Scoped Scorecards (ADR 0160). |
| [Index](resources/js/Pages/Admin/Index.tsx) | 📝 | Centro de Operações Wagner-only que agrega visão única de toda a infra/governance da empresa em **read-mostly aggregator**: brief diário, health checks 5 SQL, cycles ativos com ... |
| [RagQualityDashboard](resources/js/Pages/Admin/RagQualityDashboard.tsx) | 📝 | Dashboard Wagner-only de observability do pipeline RAG (KB + Jana): latência p99 por estágio, qualidade de retrieval (nDCG@5/recall@5), queries mais lentas e saúde do reranker B... |
| [ScreenReview](resources/js/Pages/Admin/ScreenReview.tsx) | 📝 | Command center Wagner pra fechar loop PDCA visual: ver 200+ telas Inertia React do projeto, status review consolidado, click pra abrir reader com screenshots 1440+1280 lado-a-la... |
| [ScreenReviewDashboard](resources/js/Pages/Admin/ScreenReviewDashboard.tsx) | ✅ | Visão executiva (read-only) do estado PDCA das 201+ telas Inertia/React do oimpresso. |

_+25 componentes de apoio (não listados)._

## Produto

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [BulkEdit](resources/js/Pages/Produto/BulkEdit.tsx) | 📝 | Editar atributos comuns (Category/Sub/Brand/Tax/Locations + preços variations) em N produtos selecionados simultaneamente. |
| [Create](resources/js/Pages/Produto/Create.tsx) | 📝 | Cadastrar produto novo (single/variable/combo) com validação cliente-side TypeScript + server-side Laravel. |
| [Edit](resources/js/Pages/Produto/Edit.tsx) | 📝 | Editar produto existente reusando estrutura Create.tsx 100% — diferença é `useForm` inicializado com `product` props recebidos do controller. |
| [Index](resources/js/Pages/Produto/Index.tsx) | 📝 | Catálogo simples de produtos em grid view com tabs de categoria, busca e cards visuais — variante "lite" do `/produto/unificado` pra usuários que querem visão rápida sem complex... |
| [SellingPrices](resources/js/Pages/Produto/SellingPrices.tsx) | 📝 | Configurar preços de variations por price_group (matriz N×M). |
| [Show](resources/js/Pages/Produto/Show.tsx) | 📝 | Mostrar detalhe completo do produto com Hero KPIs + tabs (Resumo · Composição · Variações · Preços · Movimento · Fiscal). |
| [StockHistory](resources/js/Pages/Produto/StockHistory.tsx) | 📝 | Auditoria de movimentos de estoque por variation × location. |
| [Unificado/Index](resources/js/Pages/Produto/Unificado/Index.tsx) | 📝 | Catálogo unificado: numa tela única alterna entre 5 sub-views (Produtos / Categorias / Insumos·BOM / Tabelas de preço / Histórico de uso) com KPI strip persistente + drawer deta... |

## Sells

📋 Estado do módulo: [memory/requisitos/Sells/BRIEFING.md](memory/requisitos/Sells/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Caixa/Index](resources/js/Pages/Sells/Caixa/Index.tsx) | • | Caixa do dia — resumo financeiro por forma de pagamento + **por origem** (Balcão / Oficina / Online · A1 KB-9.75) + ações de fechamento. |
| [Create](resources/js/Pages/Sells/Create.tsx) | ✅ | Cadastrar venda completa (cliente + produtos + pagamento + frete + impostos) numa tela única longa, com navegação rápida via filter pills entre seções e ações sempre acessíveis ... |
| [Drafts](resources/js/Pages/Sells/Drafts.tsx) | • | Listar rascunhos de venda (status=draft, sub_status=NULL) — Larissa retoma pra finalizar depois. |
| [Edit](resources/js/Pages/Sells/Edit.tsx) | • | Editar venda existente — produtos, descontos, pagamento, frete — preservando bloqueios de negócio legacy (`canBeEdited`, `isReturnExist`) e FSM safety (trait `GuardsFsmTransitio... |
| [Index](resources/js/Pages/Sells/Index.tsx) | ✅ | Tela cockpit central de operação comercial — lista vendas (pedidos · faturamento · NF-e/NFS-e) do business com 4 KPIs operacionais (Faturado hoje / Ticket médio / A receber / 4º... |
| [Quotations](resources/js/Pages/Sells/Quotations.tsx) | • | Listar cotações (status=draft + sub_status=quotation) — propostas formais enviadas pro cliente. |
| [Show](resources/js/Pages/Sells/Show.tsx) | 📝 | Mostrar detalhe completo de uma venda — linhas, pagamentos, frete, atividades — em página full-page Inertia com pattern visual derivado de SaleSheet drawer (Index canon). |
| [Subscriptions](resources/js/Pages/Sells/Subscriptions.tsx) | • | Listar vendas recorrentes (status=final + is_recurring=1) — cobranças mensais/anuais. |

_+34 componentes de apoio (não listados)._

## Cliente

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Create](resources/js/Pages/Cliente/Create.tsx) | 📝 | Formulário de cadastro de novo cliente/fornecedor — substitui Blade `contact.create.blade.php` com layout limpo single-column 3xl, seções colapsáveis lógicas e validação Inertia... |
| [Edit](resources/js/Pages/Cliente/Edit.tsx) | 📝 | Form de edição de cliente existente, pré-preenchido. |
| [Import](resources/js/Pages/Cliente/Import.tsx) | 📝 | Wizard simples 2-step pra importar clientes em massa via XLSX/CSV. |
| [Index](resources/js/Pages/Cliente/Index.tsx) | ✅ | Listagem densa de clientes com drawer lateral 760px abrindo ao clicar em qualquer linha. |
| [Ledger](resources/js/Pages/Cliente/Ledger.tsx) | 📝 | Extrato financeiro detalhado do cliente com filtros de data/formato/local + tabela com débito/crédito/saldo + export PDF/Excel. |
| [Map](resources/js/Pages/Cliente/Map.tsx) | 📝 | Visualização geográfica dos clientes com lista lateral pesquisável + mapa embed Google Maps. |
| [Show](resources/js/Pages/Cliente/Show.tsx) | • | Tela completa de detalhe do cliente com paridade funcional ao Blade legacy (`resources/views/contact/show.blade.php`). |

_+27 componentes de apoio (não listados)._

## Fiscal

📋 Estado do módulo: [memory/requisitos/Fiscal/BRIEFING.md](memory/requisitos/Fiscal/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Cockpit](resources/js/Pages/Fiscal/Cockpit.tsx) | 📝 | Dar à pessoa fiscal (Eliana contadora + Wagner operador) **visão consolidada do estado fiscal do mês** em até **3 segundos** — KPIs de emissão (NF-e/NFC-e/NFS-e/faturamento), al... |
| [Config](resources/js/Pages/Fiscal/Config.tsx) | 📝 | Visão consolidada do estado **read-only** de cert A1 + regime tributário + tributação default. |
| [Dfe](resources/js/Pages/Fiscal/Dfe.tsx) | 📝 | Lista de NF-e emitidas **CONTRA o CNPJ Oimpresso** (manifesto destinatário SEFAZ), com filtros por status + pílula temporal do prazo legal 90d. |
| [Eventos](resources/js/Pages/Fiscal/Eventos.tsx) | 📝 | **Timeline append-only** de eventos SEFAZ aplicados a notas — CC-e + Cancelamento + EPEC + Manifestação destinatário. |
| [Nfe](resources/js/Pages/Fiscal/Nfe.tsx) | 📝 | Dar à pessoa fiscal (Eliana contadora + Wagner operador) a **lista navegável de NF-e/NFC-e emitidas** com **status SEFAZ legível**, **janela legal de cancelamento visível**, e *... |
| [Nfse](resources/js/Pages/Fiscal/Nfse.tsx) | 📝 | Lista navegável de **NFS-e emitidas** (Sistema Nacional NT 2024-001 — substitui emissores municipais legacy) com filtros por status + competência + busca, agregada no cockpit Fi... |
| [Sped](resources/js/Pages/Fiscal/Sped.tsx) | 📝 | **Placeholder no PR #3** — gerador SPED Fiscal (EFD ICMS/IPI + EFD-Contribuições) é integração complexa que merece PR dedicado. |

_+13 componentes de apoio (não listados)._

## Site

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [BlogPost](resources/js/Pages/Site/BlogPost.tsx) | ❌ | — |
| [Blogs](resources/js/Pages/Site/Blogs.tsx) | ❌ | — |
| [Home](resources/js/Pages/Site/Home.tsx) | ❌ | — |
| [Login](resources/js/Pages/Site/Login.tsx) | ❌ | — |
| [Page](resources/js/Pages/Site/Page.tsx) | ❌ | — |
| [Pricing](resources/js/Pages/Site/Pricing.tsx) | ❌ | — |
| [Register](resources/js/Pages/Site/Register.tsx) | ❌ | — |

## governance

📋 Estado do módulo: [memory/requisitos/Governance/BRIEFING.md](memory/requisitos/Governance/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Audit](resources/js/Pages/governance/Audit.tsx) | ✅ | Permitir a Wagner (operador sênior) investigar atividade do MCP server `mcp.oimpresso.com` em janela de tempo: quem chamou qual endpoint, com qual tool/resource, status (ok/erro... |
| [Dashboard](resources/js/Pages/governance/Dashboard.tsx) | ✅ | Dashboard executivo de governança E saúde do ecossistema: visão consolidada de checks Constituição (multi-tenant isolation, brief uptime, custo brain B, PII leak, profile distil... |
| [DriftAlerts](resources/js/Pages/governance/DriftAlerts.tsx) | ✅ | Mostrar a Wagner divergência entre intenção declarada (SCOPE.md de cada módulo) e realidade do filesystem (`Modules/<X>/Http/Controllers/`). |
| [ModuleGrades/Index](resources/js/Pages/governance/ModuleGrades/Index.tsx) | ✅ | Permitir que Wagner (e time MCP futuro) abram **uma tela** e vejam a maturidade do projeto inteiro — 34 Modules com nota 0-100 normalizada + bucket de cor + breakdown das 9 dime... |
| [ModuleGrades/Show](resources/js/Pages/governance/ModuleGrades/Show.tsx) | ✅ | Drill-down de **um módulo específico** — mostrar nota grande, breakdown das 9 dimensões (ADR 0155 v3) com evidências, top 10 gaps ordenados por perda de pontos, e botão **"Evolu... |
| [Policies](resources/js/Pages/governance/Policies.tsx) | ✅ | Permitir a Wagner editar policies de governança (rules do `ActionGate`) pelo painel `/governance/policies` sem precisar tocar em código. |

## MemCofre

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Chat](resources/js/Pages/MemCofre/Chat.tsx) | ❌ | — |
| [Dashboard](resources/js/Pages/MemCofre/Dashboard.tsx) | ❌ | — |
| [Inbox](resources/js/Pages/MemCofre/Inbox.tsx) | ❌ | — |
| [Ingest](resources/js/Pages/MemCofre/Ingest.tsx) | ❌ | — |
| [Memoria](resources/js/Pages/MemCofre/Memoria.tsx) | ❌ | — |
| [Modulo](resources/js/Pages/MemCofre/Modulo.tsx) | ❌ | — |

## NfeBrasil

📋 Estado do módulo: [memory/requisitos/NfeBrasil/BRIEFING.md](memory/requisitos/NfeBrasil/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Manifestacao/Index](resources/js/Pages/NfeBrasil/Manifestacao/Index.tsx) | ✅ | Manifestar (cienciar / confirmar / desconhecer / não realizada) **NFes recebidas** que o destinatário precisa responder — única tela onde o usuário vê DFes pendentes pelo prazo ... |
| [Transactions/NfceStatus](resources/js/Pages/NfeBrasil/Transactions/NfceStatus.tsx) | 📝 | Acompanhar o **status fiscal pós-venda NFC-e** de uma `Transaction` individual — única tela onde o operador POS (Larissa-caixa) consulta o resultado da emissão SEFAZ após clicar... |
| [Tributacao/ConfigDefault](resources/js/Pages/NfeBrasil/Tributacao/ConfigDefault.tsx) | 📝 | Configurar os **defaults tributários por business** (regime fiscal, CSOSN/CST, ICMS, PIS, COFINS, IPI) que ficam no Nível 4 da cascata de defaults do motor tributário (business ... |
| [Tributacao/ImportCsv](resources/js/Pages/NfeBrasil/Tributacao/ImportCsv.tsx) | ❌ | — |
| [Tributacao/Index](resources/js/Pages/NfeBrasil/Tributacao/Index.tsx) | ✅ | Configurar **tributação default + regras NCM específicas** do business — única tela onde o usuário aplica template setor (1-clique MEI / Simples / Lucro Presumido / Lucro Real),... |
| [Tributacao/RegraForm](resources/js/Pages/NfeBrasil/Tributacao/RegraForm.tsx) | ❌ | — |

_+3 componentes de apoio (não listados)._

## RecurringBilling

📋 Estado do módulo: [memory/requisitos/RecurringBilling/BRIEFING.md](memory/requisitos/RecurringBilling/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Configuracoes/Index](resources/js/Pages/RecurringBilling/Configuracoes/Index.tsx) | ✅ | Centralizar tudo que afeta como o módulo Cobrança Recorrente cobra clientes do business — gateways de boleto/pix cadastrados, régua de dunning (cobrança escalada), emissão autom... |
| [Faturas/Index](resources/js/Pages/RecurringBilling/Faturas/Index.tsx) | ✅ | Listar **faturas individuais** (`rb_invoices`) com filtros por status (open/paid/overdue/canceled/refunded) · gateway (inter/c6/asaas) · período (mês atual / próximo mês / atras... |
| [Index](resources/js/Pages/RecurringBilling/Index.tsx) | ✅ | Listar assinaturas recorrentes (plano + cliente + próxima cobrança + status pagamento) com filtros por status/vencimento/plano, drawer lateral mostrando detalhe completo (KV gri... |
| [Planos/Create](resources/js/Pages/RecurringBilling/Planos/Create.tsx) | ✅ | Formulário pra cadastrar novo plano de assinatura (nome / slug / valor / ciclo / trial / fiscal) — submete POST `/recurring-billing/planos` e redireciona pra Index com flash de ... |
| [Planos/Edit](resources/js/Pages/RecurringBilling/Planos/Edit.tsx) | ✅ | Formulário pra editar plano existente — submete PUT `/recurring-billing/planos/{id}` e redireciona pra Index com flash de sucesso. |
| [Planos/Index](resources/js/Pages/RecurringBilling/Planos/Index.tsx) | ✅ | Listar planos de assinatura recorrente do business, com criação/edição/exclusão protegida (planos com assinatura ativa não podem ser deletados) — sub-rota da família Cobrança Re... |

_+7 componentes de apoio (não listados)._

## Purchase

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Create](resources/js/Pages/Purchase/Create.tsx) | • | — |
| [Edit](resources/js/Pages/Purchase/Edit.tsx) | • | — |
| [Index](resources/js/Pages/Purchase/Index.tsx) | ❌ | — |
| [Show](resources/js/Pages/Purchase/Show.tsx) | ❌ | — |

## kb

📋 Estado do módulo: [memory/requisitos/KB/BRIEFING.md](memory/requisitos/KB/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Graph](resources/js/Pages/kb/Graph.tsx) | 📝 | A tela `/kb/graph` é o **coração visual do KB Unificado** — onde Wagner vê **as conexões entre os 143 ADRs + ~500 sessions + ~30 charters + ~50 runbooks + ~10 briefings** como u... |
| [Index](resources/js/Pages/kb/Index.tsx) | 📝 | A tela `/kb` é o **portal de entrada do KB Unificado** — onde Wagner navega, busca, lê e cruza os 143 ADRs + sessions + charters + runbooks + briefings da governança do oimpresso. |
| [Index.v2](resources/js/Pages/kb/Index.v2.tsx) | 📝 | Port do protótipo Cowork `prototipo-ui/prototipos/kb/kb-page.jsx` pra Inertia React 19 + TS estrito + AppShellV2 + tokens OKLCH hue 240. |

_+13 componentes de apoio (não listados)._

## Nfse

📋 Estado do módulo: [memory/requisitos/NFSe/BRIEFING.md](memory/requisitos/NFSe/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Emitir](resources/js/Pages/Nfse/Emitir.tsx) | ❌ | — |
| [Index](resources/js/Pages/Nfse/Index.tsx) | ❌ | — |
| [Show](resources/js/Pages/Nfse/Show.tsx) | ❌ | — |

## team-mcp

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [CcSessions/Index](resources/js/Pages/team-mcp/CcSessions/Index.tsx) | ❌ | — |
| [Tasks/Index](resources/js/Pages/team-mcp/Tasks/Index.tsx) | ❌ | — |
| [Team/Index](resources/js/Pages/team-mcp/Team/Index.tsx) | 📝 | Console superadmin Wagner-only de **governança de credenciais MCP Tier 0**: emitir/revogar tokens por dev, auditar IP+last_used por credencial individual, controlar quotas BRL (... |

## TransactionPayment

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Edit](resources/js/Pages/TransactionPayment/Edit.tsx) | • | — |
| [Index](resources/js/Pages/TransactionPayment/Index.tsx) | • | — |
| [Show](resources/js/Pages/TransactionPayment/Show.tsx) | • | — |

## Auditoria

📋 Estado do módulo: [memory/requisitos/Auditoria/BRIEFING.md](memory/requisitos/Auditoria/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Detail](resources/js/Pages/Auditoria/Detail.tsx) | ❌ | — |
| [Index](resources/js/Pages/Auditoria/Index.tsx) | • | — |

## Settings

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [PaymentGateways/CnabRetorno](resources/js/Pages/Settings/PaymentGateways/CnabRetorno.tsx) | 📝 | Permitir que o operador financeiro envie arquivos de retorno bancário (CNAB .ret/.txt) de um gateway e acompanhe a conciliação — registros baixados, valor total e erros por arqu... |
| [PaymentGateways/Index](resources/js/Pages/Settings/PaymentGateways/Index.tsx) | ✅ | Wagner gerencia credenciais dos 5 drivers (Inter + C6 + Asaas + BCB Pix + PesaPal) — config inicial, ativar/desativar com confirmação Trust L3, health check real on-demand, link... |

_+5 componentes de apoio (não listados)._

## StockAdjustment

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Create](resources/js/Pages/StockAdjustment/Create.tsx) | • | — |
| [Index](resources/js/Pages/StockAdjustment/Index.tsx) | • | — |

## StockTransfer

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Create](resources/js/Pages/StockTransfer/Create.tsx) | • | — |
| [Index](resources/js/Pages/StockTransfer/Index.tsx) | • | — |

## superadmin

📋 Estado do módulo: [memory/requisitos/Superadmin/BRIEFING.md](memory/requisitos/Superadmin/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Usuario360/Index](resources/js/Pages/superadmin/Usuario360/Index.tsx) | 📝 | Permitir ao superadmin localizar qualquer usuário (cross-business) por nome/email/empresa e saltar pro perfil 360 em ≤2 cliques, com busca instantânea. |
| [Usuario360/Show](resources/js/Pages/superadmin/Usuario360/Show.tsx) | 📝 | — |

## Whatsapp

📋 Estado do módulo: [memory/requisitos/Whatsapp/BRIEFING.md](memory/requisitos/Whatsapp/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Settings](resources/js/Pages/Whatsapp/Settings.tsx) | ✅ | Permitir admin de business **conectar/reconectar** número WhatsApp Business via Meta Cloud em 5-15 min usando popup OAuth oficial Facebook for Business v4 (Embedded Signup). |
| [Templates/Index](resources/js/Pages/Whatsapp/Templates/Index.tsx) | ❌ | — |

_+12 componentes de apoio (não listados)._

## _Showcase

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Components](resources/js/Pages/_Showcase/Components.tsx) | ❌ | — |
| [OndaF](resources/js/Pages/_Showcase/OndaF.tsx) | ❌ | — |

## Compras

📋 Estado do módulo: [memory/requisitos/Compras/BRIEFING.md](memory/requisitos/Compras/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Index](resources/js/Pages/Compras/Index.tsx) | • | Servir como **cockpit operacional de Compras** entregando 4 KPIs (a pagar / em trânsito / volume mês / fornecedores) + lista paginada filtrável por estágio FSM + drawer detalhe,... |

_+4 componentes de apoio (não listados)._

## ComunicacaoVisual

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Index](resources/js/Pages/ComunicacaoVisual/Index.tsx) | • | — |

## ConsultaOs

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Index](resources/js/Pages/ConsultaOs/Index.tsx) | ❌ | — |

## Home

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Index](resources/js/Pages/Home/Index.tsx) | ✅ | Servir como **landing pós-login** do oimpresso entregando 8 KPIs de operação em 2 grupos (Vendas / Compras & Custos) em ≤800ms numa shell Inertia React, com fallback discreto pr... |

## Manufacturing

📋 Estado do módulo: [memory/requisitos/Manufacturing/BRIEFING.md](memory/requisitos/Manufacturing/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Index](resources/js/Pages/Manufacturing/Index.tsx) | 📝 | Listar ordens de produção (production_purchase) do business ativo em UX Inertia/React, em coexistência com Blade legacy `/manufacturing/production` durante migração MWART. |

## Modules

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Index](resources/js/Pages/Modules/Index.tsx) | ❌ | — |

## Tarefas

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Index](resources/js/Pages/Tarefas/Index.tsx) | ❌ | — |

## Vestuario

📋 Estado do módulo: [memory/requisitos/Vestuario/BRIEFING.md](memory/requisitos/Vestuario/BRIEFING.md)

| Tela | Charter | Deveria ter (Mission do charter) |
|---|:--:|---|
| [Etiquetas/Index](resources/js/Pages/Vestuario/Etiquetas/Index.tsx) | ❌ | — |

---
**Como ler:** Charter ✅=live (aprovado) · 📝=draft · ❌=sem charter (gap de contrato). A coluna _Deveria ter_ é o resumo do contrato; ausência = primeiro gap a documentar.