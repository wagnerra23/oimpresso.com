<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Modelos;

/**
 * Casos de uso, fontes, portes e o resto de `prototipo-ui/cowork/Wagner/fluxos-data.jsx` (window.FLX)
 * que o CatalogoModelos não guardava: o que a aba Casos de uso da tela consome.
 *
 * Exportado do .jsx por máquina, não digitado. Não edite à mão: mude o protótipo e exporte de novo.
 * `CatalogoModelosFluxoTest` compara com o .jsx.
 */
final class CasosDoPrototipo
{
    /** id => [nome, url] */
    public const FONTES = [
        'mubisys' => ['Mubisys', 'https://mubisys.com/producao'],
        'holdprint' => ['Holdprint', 'https://www.holdprint.net/'],
        'zenite' => ['Zênite', 'https://www.zsl.com.br/'],
        'calcgraf' => ['Calcgraf', 'https://www.calcgraf.com.br/solucao/pcp/'],
        'visua' => ['Visua', 'https://www.visua.com.br/'],
        'calcme' => ['Calcme', 'https://www.calcme.com.br/funcionalidades/'],
        'shopvox' => ['shopVOX', 'https://shopvox.com/features/job-management/'],
        'corebridge' => ['Corebridge', 'https://www.corebridge.net/production-management'],
        'cyrious' => ['Cyrious', 'https://www.cyrious.com/products/control/'],
        'printiq' => ['printIQ', 'https://www.printiq.com/products/core/'],
        'odoo' => ['Odoo MRP', 'https://www.odoo.com/documentation/19.0/applications/inventory_and_mrp/manufacturing/basic_setup/mo_costs.html'],
        'katana' => ['Katana MRP', 'https://support.katanamrp.com/en/articles/5914362-manufacturing-costs-for-products'],
        'oimpresso' => ['oimpresso', ''],
    ];

    /** porte => [rótulo, descrição] */
    public const PORTES = [
        'micro' => ['Micro', 'MEI e micro · até 5 pessoas'],
        'pequena' => ['Pequena', '6 a 20 pessoas'],
        'media' => ['Média', '21 a 100 pessoas'],
        'grande' => ['Grande', 'mais de 100 pessoas'],
    ];

    /** estado do caso no oimpresso => [rótulo, tom] */
    public const ESTADOS = [
        'producao' => ['em produção', 'ok'],
        'prototipo' => ['no protótipo', 'accent'],
        'parcial' => ['parcial', 'warn'],
        'modulo' => ['em outro módulo', 'warn'],
        'planejado' => ['planejado', 'mute'],
        'sem' => ['sem US', 'danger'],
        'verificar' => ['não verificado', 'mute'],
    ];

    /** Na ordem do protótipo: [id, título, processo, etapa, regra, fontes, porte, estado, US] */
    public const CASOS = [
        ['UC-FLX-01', 'Orçamento aprovado vira OS sem redigitar', 'venda', 'Aprovado', 'Cria a OS com itens, medidas e arquivos do orçamento', ['mubisys', 'shopvox', 'printiq', 'calcme'], 'micro', 'planejado', 'US-COMVIS-003'],
        ['UC-FLX-02', 'Cliente aprova a arte por link', 'producao', 'Aprovação do cliente', 'Envia link da prova; aprovado avança para Impressão, recusado volta para Arte com o comentário', ['holdprint', 'shopvox', 'corebridge', 'cyrious', 'printiq'], 'pequena', 'planejado', 'US-COMVIS-NEW-004'],
        ['UC-FLX-03', 'Orçamento calcula custo, material e lucro sozinho', 'venda', 'Orçamento', 'Ao salvar, recalcula o custo pela receita e mostra a margem', ['mubisys', 'holdprint'], 'micro', 'parcial', 'calculadora de m²'],
        ['UC-FLX-04', 'Termômetro de acesso da proposta', 'venda', 'Enviado', 'Registra quando o cliente abre; sem abertura em 48 h cria tarefa de retorno', ['mubisys'], 'pequena', 'sem', ''],
        ['UC-FLX-05', 'Comissão do vendedor nasce no faturamento', 'venda', 'Faturado', 'Lança a comissão do vendedor sobre o valor faturado', ['mubisys'], 'pequena', 'sem', ''],
        ['UC-FLX-06', 'Prazo de entrega calculado pela fila', 'venda', 'Aprovado', 'Calcula a data de entrega pela capacidade livre de cada etapa', ['holdprint', 'calcgraf'], 'media', 'planejado', 'SPEC do PCP'],
        ['UC-FLX-07', 'Etapas de produção vêm do cadastro do produto', 'producao', 'OS criada', 'Copia o roteiro de etapas do produto para a OS', ['shopvox'], 'pequena', 'planejado', 'SPEC do PCP'],
        ['UC-FLX-08', 'Cada etapa tem setor e responsável', 'producao', 'qualquer etapa', 'Atribui ao responsável do setor e conta o tempo parado', ['mubisys', 'corebridge', 'calcme', 'cyrious'], 'pequena', 'modulo', 'Repair / Oficina'],
        ['UC-FLX-09', 'Apontamento pelo celular com QR Code', 'producao', 'Iniciada / Concluída', 'A leitura do QR da OS inicia ou conclui a etapa e grava o tempo', ['mubisys', 'printiq', 'shopvox', 'cyrious'], 'pequena', 'parcial', 'US-COMVIS-004'],
        ['UC-FLX-10', 'Alocador decide quem e quando começa', 'producao', 'Liberada', 'Escolhe operador e horário para não parar máquina nem faltar material', ['holdprint'], 'media', 'planejado', 'SPEC do PCP (parado, ADR 0152)'],
        ['UC-FLX-11', 'Dados da máquina entram sem digitação', 'producao', 'Impressão', 'Fecha a etapa com metragem e tempo lidos do equipamento', ['zenite', 'calcgraf'], 'grande', 'planejado', 'US-COMVIS-004'],
        ['UC-FLX-12', 'Romaneio de impressão peça a peça', 'producao', 'Pré-impressão → Impressão', 'Gera a lista do que foi enviado ao setor de impressão e corte', ['mubisys'], 'pequena', 'sem', ''],
        ['UC-FLX-13', 'Simulador de aproveitamento de mídia', 'producao', 'Pré-impressão', 'Agrupa os jobs na bobina para reduzir a perda de mídia', ['holdprint', 'printiq', 'calcgraf'], 'media', 'sem', ''],
        ['UC-FLX-14', 'Produção baixa o material consumido', 'producao', 'OP concluída', 'Consome do estoque a quantidade real, não a orçada', ['mubisys', 'calcgraf', 'cyrious'], 'micro', 'prototipo', 'Fabricação'],
        ['UC-FLX-15', 'Previsto × realizado por OS', 'producao', 'Entregue', 'Congela o custo real e compara com o orçado e o aprovado', ['holdprint', 'calcgraf', 'shopvox', 'corebridge', 'printiq'], 'pequena', 'planejado', 'US-COMVIS-005'],
        ['UC-FLX-16', 'Refugo e retrabalho com motivo', 'producao', 'Reprovada no acabamento', 'Abre retrabalho na etapa de origem e soma o custo à OS', ['calcgraf', 'zenite'], 'pequena', 'planejado', 'SPEC do PCP'],
        ['UC-FLX-17', 'Compra recebida atualiza custo e propõe preço', 'compra', 'Recebido', 'Recalcula o custo dos insumos (médio ou último) e propõe preço nos produtos que acompanham o custo', ['mubisys', 'holdprint', 'calcgraf'], 'micro', 'prototipo', 'Compras · regras das etapas'],
        ['UC-FLX-18', 'Estoque fracionado com rastreio da mídia', 'compra', 'Recebido', 'Abre o rolo como lote e registra em que OS cada fração foi usada', ['mubisys'], 'pequena', 'sem', ''],
        ['UC-FLX-19', 'Estoque mínimo vira requisição de compra', 'compra', 'Abaixo do mínimo', 'Cria a requisição com o fornecedor da última compra', ['mubisys'], 'micro', 'planejado', 'Compras · requisições'],
        ['UC-FLX-20', 'Custeio RKW e ponto de equilíbrio', 'financeiro', 'Fechamento do mês', 'Rateia despesas fixas por centro de custo e atualiza o custo-hora', ['holdprint', 'zenite'], 'media', 'sem', ''],
        ['UC-FLX-21', 'Agenda da equipe de instalação', 'entrega', 'Pronto', 'Agenda equipe e veículo; bloqueia se não houver janela', ['shopvox', 'corebridge', 'visua', 'calcme', 'cyrious'], 'pequena', 'planejado', 'US-COMVIS-007'],
        ['UC-FLX-22', 'Instalação com foto e assinatura', 'entrega', 'Instalado', 'Só conclui com foto do serviço e assinatura do cliente', ['shopvox', 'printiq'], 'pequena', 'modulo', 'Oficina (fotos)'],
        ['UC-FLX-23', 'Protocolo de entrega', 'entrega', 'Entregue', 'Gera o protocolo com itens, volumes e quem recebeu', ['mubisys', 'corebridge', 'cyrious'], 'micro', 'planejado', 'US-COMVIS-007'],
        ['UC-FLX-24', 'Cliente acompanha o status', 'entrega', 'cada transição', 'Avisa o cliente e atualiza o portal de acompanhamento', ['calcgraf', 'corebridge', 'shopvox', 'printiq'], 'pequena', 'modulo', 'Repair (portal)'],
        ['UC-FLX-25', 'Pesquisa de satisfação depois da entrega', 'entrega', 'Entregue + 2 dias', 'Envia a pesquisa; nota baixa abre atendimento', ['mubisys'], 'pequena', 'sem', ''],
        ['UC-FLX-26', 'Boleto pago emite a NF-e', 'financeiro', 'Pago', 'Emite a NF-e sem ninguém clicar', ['oimpresso'], 'micro', 'producao', 'US-RB-044'],
        ['UC-FLX-28', 'Produção só libera com o sinal pago', 'producao', 'Aguardando liberação → Na fila', 'Guarda: sinal do orçamento pago; o retorno do banco libera a OS sozinho', ['oimpresso'], 'micro', 'verificar', ''],
        ['UC-FLX-29', 'Régua de cobrança do título vencido', 'financeiro', 'Vencido → Em cobrança', 'Lembretes em D+1, D+5 e D+15 e tarefa ao vendedor', ['oimpresso'], 'micro', 'verificar', ''],
        ['UC-FLX-30', 'ISS retido ajusta o valor do título', 'financeiro', 'NFS-e Autorizada', 'O título passa a cobrar o valor líquido das retenções', ['oimpresso'], 'pequena', 'verificar', ''],
        ['UC-FLX-31', 'Natureza fiscal do item decide a nota', 'financeiro', 'Faturado', 'Serviço gráfico sob encomenda → NFS-e · revenda → NF-e · instalação → NFS-e', ['oimpresso'], 'micro', 'verificar', 'Súmula 156 STJ'],
        ['UC-FLX-32', 'NF-e em contingência quando a SEFAZ cai', 'financeiro', 'Validada → Transmitida', 'Emite em SVC/EPEC e transmite quando a SEFAZ voltar', ['oimpresso'], 'pequena', 'verificar', ''],
        ['UC-FLX-27', 'Uma OS, duas notas (NF-e + NFS-e)', 'financeiro', 'Faturado', 'Emite NF-e da mercadoria e NFS-e do serviço na mesma OS', ['oimpresso'], 'pequena', 'planejado', 'ADR 0129 · US-NFE-060'],
        ['UC-FLX-33', 'Orçamento aprovado guarda o custo do dia', 'venda', 'Aprovado', 'Grava o custo previsto de cada item (material, tinta, hora de máquina, instalação) com a data; compra depois não muda esse valor', ['mubisys', 'shopvox', 'odoo'], 'micro', 'parcial', 'US-SELL-067 (Vendas) · US-COMVIS-019 (material da CV); falta hora de máquina, tinta e instalação'],
        ['UC-FLX-34', 'Orçamento aberto avisa que o custo mudou', 'venda', 'Orçamento / Enviado', 'Mostra o custo guardado ao lado do custo de hoje e a margem que sobra; só recalcula se alguém pedir', ['oimpresso'], 'micro', 'producao', 'US-SELL-068'],
        ['UC-FLX-35', 'Preço negociado abaixo da margem pede aprovação', 'venda', 'Aprovado', 'Compara o preço fechado com o custo guardado; abaixo da margem mínima só aprova quem tem permissão', ['oimpresso'], 'pequena', 'producao', 'US-SELL-069'],
        ['UC-FLX-36', 'OS em andamento avisa insumo que mudou de custo', 'producao', 'qualquer etapa antes de Concluída', 'Mostra o custo da abertura e o de hoje; a regra da compra decide se a OS mantém o custo ou acompanha', ['katana'], 'pequena', 'prototipo', 'Compras · regras das etapas'],
        ['UC-FLX-37', 'Material consumido leva o custo do dia da baixa', 'producao', 'Concluída', 'Congela as horas apontadas e o material pelo custo da data da baixa; compra depois não reescreve a OS', ['odoo', 'katana'], 'pequena', 'parcial', 'Produção · horas congeladas em pcp_ordens; material sem regra'],
        ['UC-FLX-38', 'Custo do insumo guarda o histórico por data', 'compra', 'Recebido', 'Cada recebimento grava o custo com a data; o custo novo vale daqui pra frente e o anterior nunca é apagado', ['katana', 'odoo'], 'micro', 'parcial', 'US-COM-024 · custo na data (médio e último); falta o método por insumo'],
    ];

    /** modelo => [inspirado em, regra de custo da compra] */
    public const MODELOS = [
        'balcao' => [['mubisys'], ['etapa' => 'recebido', 'metodo' => 'ultimo', 'preco' => 'so_custo', 'regraEm' => 'produto', 'aprovacao' => 'recebimento', 'ops' => 'congela']],
        'cv-pequena' => [['mubisys', 'holdprint', 'shopvox'], ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'propor', 'regraEm' => 'produto', 'aprovacao' => 'recebimento', 'ops' => 'congela']],
        'cv-pcp' => [['holdprint', 'printiq', 'corebridge'], ['etapa' => 'conferido', 'metodo' => 'medio', 'preco' => 'propor', 'regraEm' => 'produto', 'aprovacao' => 'fabricacao', 'ops' => 'congela']],
        'grafica' => [['calcgraf', 'zenite', 'printiq'], ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'aplicar', 'regraEm' => 'produto', 'aprovacao' => 'recebimento', 'ops' => 'congela']],
        'brindes' => [['mubisys', 'shopvox'], ['etapa' => 'recebido', 'metodo' => 'ultimo', 'preco' => 'aplicar', 'regraEm' => 'categoria', 'aprovacao' => 'recebimento', 'ops' => 'vivo']],
        'instalacao' => [['holdprint', 'visua', 'shopvox'], ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'propor', 'regraEm' => 'produto', 'aprovacao' => 'recebimento', 'ops' => 'congela']],
        'oficina' => [['corebridge'], ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'so_custo', 'regraEm' => 'produto', 'aprovacao' => 'recebimento', 'ops' => 'congela']],
    ];
}
