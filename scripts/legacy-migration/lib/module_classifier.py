"""
Classifica tabelas Firebird WR Comercial em módulos funcionais.

Heurística inicial por prefixo. Ambíguos vão pra _outros/.
Wagner refina manualmente editando este arquivo (cresce o catálogo).
"""

from __future__ import annotations

import re

# Ordem importa — primeira regex que casa vence. Regras mais específicas primeiro.
PREFIX_RULES: list[tuple[str, str]] = [
    # ========================================================================
    # Financeiro
    # ========================================================================
    (r"^FINANCEIRO", "financeiro"),
    (r"^BOLETO", "financeiro"),
    (r"^CHEQUE", "financeiro"),
    (r"^CAIXA", "financeiro"),
    (r"^CONCILIACAO", "financeiro"),
    (r"^COMISSAO", "financeiro"),
    (r"^CONTAS$", "financeiro"),
    (r"^CONTA_", "financeiro"),
    (r"^BANCOS?$", "financeiro"),
    (r"^BANCO_", "financeiro"),
    (r"^BANCOS_", "financeiro"),
    (r"^DRE", "financeiro"),
    (r"^PLANOCONTAS", "financeiro"),
    (r"^PLANO_CONTAS", "financeiro"),
    (r"^MENSALIDADE", "financeiro"),
    (r"^FATURA", "financeiro"),
    (r"^RATEIO", "financeiro"),
    (r"^TIPO_PAGAMENTO", "financeiro"),
    (r"^CONDICAOPAGTO", "financeiro"),
    (r"^CUSTO", "financeiro"),
    (r"^PRECIFICACAO", "financeiro"),
    # ========================================================================
    # Vendas (incluindo orçamento, pedido, PDV)
    # ========================================================================
    (r"^VENDA", "vendas"),
    (r"^ORCAMENTO", "vendas"),
    (r"^PEDIDO", "vendas"),
    (r"^PDV", "vendas"),
    (r"^TABELA_VALOR", "vendas"),
    (r"^CONTRATO", "vendas"),
    (r"^ECF$", "vendas"),
    (r"^CARRO", "vendas"),  # carrinho de compras / carrinho intermediário
    # ========================================================================
    # NFe (entrada, saída, manifestação)
    # ========================================================================
    (r"^NF_", "nfe"),
    (r"^NFE", "nfe"),
    (r"^NFCE", "nfe"),
    (r"^NOTA_FISCAL", "nfe"),
    (r"^MANIFESTAC", "nfe"),
    (r"^SINTEGRA", "nfe"),
    (r"^SPED", "nfe"),
    # ========================================================================
    # Estoque (produtos, lotes, movimentos)
    # ========================================================================
    (r"^PRODUTO", "estoque"),
    (r"^ESTOQUE", "estoque"),
    (r"^MOVIMENTO_ESTOQUE", "estoque"),
    (r"^REQUISICAO", "estoque"),
    (r"^FORMULA", "estoque"),  # cálculos de produto
    (r"^FAMILIA$", "estoque"),
    (r"^LOTE$", "estoque"),
    (r"^UNIDADE", "estoque"),
    (r"^TIPOFILME$", "estoque"),
    # ========================================================================
    # Produção (orçamento gráfico, kanban, ordens)
    # ========================================================================
    (r"^PRODUCAO", "producao"),
    (r"^KANBAN", "producao"),
    (r"^CENTRO_TRABALHO", "producao"),
    (r"^CENTRO_CUSTO", "producao"),
    (r"^CENTRO_DE_CUSTO", "producao"),
    (r"^MARCADOR", "producao"),
    (r"^ETAPA", "producao"),
    (r"^PROCESSO", "producao"),
    (r"^TAREFA", "producao"),
    (r"^ROTEIRO", "producao"),
    (r"^PROJETO", "producao"),
    (r"^ACABAMENTO", "producao"),
    (r"^COR$", "producao"),
    # ========================================================================
    # Cadastros (pessoas, fornecedores, funcionários, agências, etc)
    # ========================================================================
    (r"^PESSOAS?$", "cadastros"),
    (r"^PESSOA_", "cadastros"),
    (r"^PESSOAS_", "cadastros"),
    (r"^FORNECEDOR", "cadastros"),
    (r"^FUNCIONARIO", "cadastros"),
    (r"^CLIENTE", "cadastros"),
    (r"^EMPRESA", "cadastros"),
    (r"^AGENCIA", "cadastros"),
    (r"^REPRESENTANTE", "cadastros"),
    (r"^ASSOCIADO", "cadastros"),
    (r"^USUARIO", "cadastros"),
    (r"^TELEFONE", "cadastros"),
    (r"^CIDADE", "cadastros"),
    (r"^PAIS$", "cadastros"),
    (r"^LOCAL$", "cadastros"),
    (r"^SETOR", "cadastros"),
    # ========================================================================
    # Recursos humanos (folha, ponto, banco de horas)
    # ========================================================================
    (r"^FOLHA", "rh"),
    (r"^PONTO", "rh"),
    (r"^MARCAC", "rh"),
    (r"^FICHA_PONTO", "rh"),
    (r"^BANCO_HORAS", "rh"),
    (r"^INTERCORRENCIA", "rh"),
    (r"^FERIA", "rh"),
    (r"^TEMPO_TRABALHO", "rh"),
    (r"^RECURSO", "rh"),
    # ========================================================================
    # Agenda (eventos, kanban de tarefas, comunicação)
    # ========================================================================
    (r"^AGENDA", "agenda"),
    (r"^EMAIL", "agenda"),
    (r"^MENSAGEM", "agenda"),
    (r"^MSG", "agenda"),
    (r"^OCORRENCIA", "agenda"),
    (r"^TIPOOCORRENCIA$", "agenda"),
    (r"^TIPO_OCORRENCIA", "agenda"),
    (r"^SLA", "agenda"),
    (r"^SOLICITACAO", "agenda"),
    (r"^REGISTRO_ATIVIDADE", "agenda"),
    # ========================================================================
    # Equipamentos (relacionado a Repair Laravel)
    # ========================================================================
    (r"^EQUIPAMENTO", "equipamento"),
    (r"^VEICULO", "equipamento"),
    (r"^IMPRESSORA", "equipamento"),
    (r"^COMPUTADOR", "equipamento"),
    (r"^ELETRODOMESTICO", "equipamento"),
    (r"^TABELAFIPE", "equipamento"),
    (r"^TABELA_FIPE", "equipamento"),
    (r"^TABFIPE", "equipamento"),
    (r"^OS$", "equipamento"),
    (r"^ORDEM_SERVICO", "equipamento"),
    (r"^TIPOEQUIPAMENTOS", "equipamento"),
    (r"^ANTIFURTO", "equipamento"),
    # ========================================================================
    # WR_* — metadados de configuração do app (telas, KPIs, filtros)
    # ========================================================================
    (r"^WR_", "wr_metadata"),
    (r"^TAG_", "wr_metadata"),
    (r"^FORM_", "wr_metadata"),
    (r"^GRID_", "wr_metadata"),
    (r"^FILTRO", "wr_metadata"),
    (r"^STATUS$", "wr_metadata"),
    (r"^SITUACAO$", "wr_metadata"),
    (r"^HISTORICO", "wr_metadata"),  # tabelas genéricas de histórico de UI
    # ========================================================================
    # UI metadata (dicas, planilhas, layouts)
    # ========================================================================
    (r"^DICA", "ui_metadata"),
    (r"^SPREADSHEET", "ui_metadata"),
    (r"^LAYOUT", "ui_metadata"),
    (r"^IMPRESSAO", "ui_metadata"),
    (r"^TIPO_IMPRESSAO", "ui_metadata"),
    (r"^ARQUIVOS_RELATORIO", "ui_metadata"),
    (r"^ARQUIVOS$", "ui_metadata"),
    (r"^ANEXO$", "ui_metadata"),
    (r"^ATALHO_RAPIDO", "ui_metadata"),
    (r"^CONFIGURACOES_GRID", "ui_metadata"),
    (r"^PLANILHA_TEMPO", "ui_metadata"),
    (r"^RELATORIO", "ui_metadata"),
    # ========================================================================
    # BI / KPIs
    # ========================================================================
    (r"^KPI", "bi"),
    (r"^DASHBOARD", "bi"),
    (r"^BALANCO", "bi"),
    (r"^BI_", "bi"),
    (r"^META$", "bi"),
    (r"^META_", "bi"),
    # ========================================================================
    # Configuração / sistema
    # ========================================================================
    (r"^CONFIGURACOES?$", "configuracao"),
    (r"^CONFIGURACAO_", "configuracao"),
    (r"^CONFIG_", "configuracao"),
    (r"^PARAMETROS?$", "configuracao"),
    (r"^LOGSISTEMA", "configuracao"),
    (r"^LOG_", "configuracao"),
    (r"^LICENCIAMENTO", "configuracao"),
    (r"^LICENCA", "configuracao"),
    (r"^COMPETENCIA", "configuracao"),
    (r"^HISTORICOM$", "configuracao"),
    (r"^APP$", "configuracao"),
    (r"^ATUALIZACAO$", "configuracao"),
    # ========================================================================
    # Tributário (regimes, tabelas fiscais)
    # ========================================================================
    (r"^REGIME", "tributario"),
    (r"^REGRA", "tributario"),
    (r"^CFOP", "tributario"),
    (r"^CST$", "tributario"),
    (r"^CSOSN", "tributario"),
    (r"^NCM", "tributario"),
    (r"^CNAE", "tributario"),
    (r"^GRUPO_REGRA", "tributario"),
    # ========================================================================
    # API / integração
    # ========================================================================
    (r"^API_", "api"),
    (r"^OIMPRESSO", "api"),
    (r"^WEB_SERVICE", "api"),
]


def classify_table(name: str) -> str:
    """Retorna nome do módulo (slug) pra uma tabela. '_outros' se não casa."""
    upper = name.upper()
    for pattern, module in PREFIX_RULES:
        if re.match(pattern, upper):
            return module
    return "_outros"


def classify_all(table_names: list[str]) -> dict[str, list[str]]:
    """Retorna {modulo: [tabelas]} ordenado por nome de tabela."""
    result: dict[str, list[str]] = {}
    for name in sorted(table_names):
        module = classify_table(name)
        result.setdefault(module, []).append(name)
    return result
