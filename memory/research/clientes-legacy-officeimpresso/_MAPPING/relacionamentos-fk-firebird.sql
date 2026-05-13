-- =============================================================
-- RELACIONAMENTOS (FOREIGN KEYS) - Extraído de relacional.grc
-- Banco de dados: Firebird
-- Total de constraints: 76
-- =============================================================

-- -------------------------------------------------------------
-- Tabela: AGENDA
-- -------------------------------------------------------------
ALTER TABLE AGENDA
    ADD CONSTRAINT FK_AGENDA_LOTE
    FOREIGN KEY (CODLOTE)
    REFERENCES LOTE (CODIGO);


-- -------------------------------------------------------------
-- Tabela: AGENDA_LIDO
-- -------------------------------------------------------------
ALTER TABLE AGENDA_LIDO
    ADD CONSTRAINT FK_AGENDA_LIDO_USUARIO
    FOREIGN KEY (CODUSUARIO)
    REFERENCES USUARIO (CODIGO);


-- -------------------------------------------------------------
-- Tabela: AGENDA_SEGUIDOR
-- -------------------------------------------------------------
ALTER TABLE AGENDA_SEGUIDOR
    ADD CONSTRAINT FK_AGENDA_SEGUIDOR_AGENDA
    FOREIGN KEY (CODAGENDA)
    REFERENCES AGENDA (CODIGO);

ALTER TABLE AGENDA_SEGUIDOR
    ADD CONSTRAINT FK_AGENDA_SEGUIDOR_USUARIO
    FOREIGN KEY (CODUSUARIO)
    REFERENCES USUARIO (CODIGO);


-- -------------------------------------------------------------
-- Tabela: ANEXO
-- -------------------------------------------------------------
ALTER TABLE ANEXO
    ADD CONSTRAINT FK_ANEXO_EMPRESA
    FOREIGN KEY (CODEMPRESA)
    REFERENCES EMPRESA (CODIGO);

ALTER TABLE ANEXO
    ADD CONSTRAINT FK_ANEXO_USUARIO
    FOREIGN KEY (CODUSUARIO)
    REFERENCES USUARIO (CODIGO);


-- -------------------------------------------------------------
-- Tabela: BANCOS
-- -------------------------------------------------------------
ALTER TABLE BANCOS
    ADD CONSTRAINT FK_BANCOS_COOP
    FOREIGN KEY (CODBANCO_COOPERATIVA)
    REFERENCES BANCOS (CODIGO);


-- -------------------------------------------------------------
-- Tabela: CENTRO_TRABALHO
-- -------------------------------------------------------------
ALTER TABLE CENTRO_TRABALHO
    ADD CONSTRAINT FK_CENTRO_TRABALHO_TEMPO_TRAB
    FOREIGN KEY (CODTEMPO_TRABALHO)
    REFERENCES TEMPO_TRABALHO (CODIGO);


-- -------------------------------------------------------------
-- Tabela: CONFIGURACOES
-- -------------------------------------------------------------
ALTER TABLE CONFIGURACOES
    ADD CONSTRAINT FK_CONFIGURACOES_USUARIO_ALT
    FOREIGN KEY (CODUSUARIO_ALTERACAO)
    REFERENCES USUARIO (CODIGO);


-- -------------------------------------------------------------
-- Tabela: CONFIGURACOES_GRID
-- -------------------------------------------------------------
ALTER TABLE CONFIGURACOES_GRID
    ADD CONSTRAINT FK_CONFIGURACOES_GRID_USUARIO
    FOREIGN KEY (CODUSUARIO)
    REFERENCES USUARIO (CODIGO);


-- -------------------------------------------------------------
-- Tabela: CONTAS
-- -------------------------------------------------------------
ALTER TABLE CONTAS
    ADD CONSTRAINT FK_CONTAS_BANCOS_CONFIG
    FOREIGN KEY (CODBANCO_CONFIGURACAO)
    REFERENCES BANCOS (CODIGO);

ALTER TABLE CONTAS
    ADD CONSTRAINT FK_CONTAS_CONTA
    FOREIGN KEY (CODCONTA_TRANSFERENCIA_AUTO)
    REFERENCES CONTAS (CODIGO);

ALTER TABLE CONTAS
    ADD CONSTRAINT FK_CONTAS_EMAIL_MODELO
    FOREIGN KEY (CODEMAIL_MODELO)
    REFERENCES EMAIL_MODELO (CODIGO);

ALTER TABLE CONTAS
    ADD CONSTRAINT FK_CONTAS_EMPRESA
    FOREIGN KEY (CODEMPRESA)
    REFERENCES EMPRESA (CODIGO);


-- -------------------------------------------------------------
-- Tabela: CONTRATO
-- -------------------------------------------------------------
ALTER TABLE CONTRATO
    ADD CONSTRAINT FK_CONTRATO_CLIENTE
    FOREIGN KEY (PESSOA_CLIENTE_CODIGO)
    REFERENCES PESSOAS (CODIGO);

ALTER TABLE CONTRATO
    ADD CONSTRAINT FK_CONTRATO_CONTA
    FOREIGN KEY (CODCONTA)
    REFERENCES CONTAS (CODIGO);

ALTER TABLE CONTRATO
    ADD CONSTRAINT FK_CONTRATO_CONTRATO_TIPO
    FOREIGN KEY (CODCONTRATO_TIPO)
    REFERENCES CONTRATO_TIPO (CODIGO);

ALTER TABLE CONTRATO
    ADD CONSTRAINT FK_CONTRATO_PC
    FOREIGN KEY (CODPLANOCONTAS)
    REFERENCES PLANOCONTAS (CODIGO);


-- -------------------------------------------------------------
-- Tabela: CONTRATO_TIPO
-- -------------------------------------------------------------
ALTER TABLE CONTRATO_TIPO
    ADD CONSTRAINT FK_CONTRATO_TIPO_CONTA
    FOREIGN KEY (CODCONTA)
    REFERENCES CONTAS (CODIGO);

ALTER TABLE CONTRATO_TIPO
    ADD CONSTRAINT FK_CONTRATO_TIPO_PC
    FOREIGN KEY (CODPLANOCONTAS)
    REFERENCES PLANOCONTAS (CODIGO);


-- -------------------------------------------------------------
-- Tabela: DICA_COMPONENTES
-- -------------------------------------------------------------
ALTER TABLE DICA_COMPONENTES
    ADD CONSTRAINT FK_DICA_COMPONENTES_1
    FOREIGN KEY (CODDICA)
    REFERENCES DICA (CODIGO);


-- -------------------------------------------------------------
-- Tabela: DICA_USUARIO
-- -------------------------------------------------------------
ALTER TABLE DICA_USUARIO
    ADD CONSTRAINT FK_DICA_USUARIO_DICA
    FOREIGN KEY (CODDICA)
    REFERENCES DICA (CODIGO);

ALTER TABLE DICA_USUARIO
    ADD CONSTRAINT FK_DICA_USUARIO_USUARIO
    FOREIGN KEY (CODUSUARIO)
    REFERENCES USUARIO (CODIGO);


-- -------------------------------------------------------------
-- Tabela: EMAIL_MASSA
-- -------------------------------------------------------------
ALTER TABLE EMAIL_MASSA
    ADD CONSTRAINT FK_EMAIL_MASSA_ANEXO_EMM
    FOREIGN KEY (CODEMAIL_MASSA_MENSAGEM)
    REFERENCES EMAIL_MASSA_MENSAGEM (CODIGO);


-- -------------------------------------------------------------
-- Tabela: EMAIL_MASSA_MENSAGEM
-- -------------------------------------------------------------
ALTER TABLE EMAIL_MASSA_MENSAGEM
    ADD CONSTRAINT FK_EMAIL_MASSA_MENSAGEM_EM
    FOREIGN KEY (CODEMAIL_MASSA)
    REFERENCES EMAIL_MASSA (CODIGO);

ALTER TABLE EMAIL_MASSA_MENSAGEM
    ADD CONSTRAINT FK_EMAIL_MASSA_MENSAGEM_PES
    FOREIGN KEY (CODPESSOA)
    REFERENCES PESSOAS (CODIGO);


-- -------------------------------------------------------------
-- Tabela: EQUIPAMENTO_ANTIFURTO_TIPO
-- -------------------------------------------------------------
ALTER TABLE EQUIPAMENTO_ANTIFURTO_TIPO
    ADD CONSTRAINT FK_EQUIPAMENTO_ANTIFURTO_TIPO_T
    FOREIGN KEY (CODANTIFURTO_TIPO)
    REFERENCES ANTIFURTO_TIPO (CODIGO);

ALTER TABLE EQUIPAMENTO_ANTIFURTO_TIPO
    ADD CONSTRAINT FK_EQUIPAMENTO_ANTIFURTO_TIPO_U
    FOREIGN KEY (CODUSUARIO)
    REFERENCES USUARIO (CODIGO);


-- -------------------------------------------------------------
-- Tabela: EQUIPAMENTO_SEMIREBOQUE
-- -------------------------------------------------------------
ALTER TABLE EQUIPAMENTO_SEMIREBOQUE
    ADD CONSTRAINT FK_EQUIPAMENTO_SEMIREBOQUE_US
    FOREIGN KEY (CODUSUARIO)
    REFERENCES USUARIO (CODIGO);


-- -------------------------------------------------------------
-- Tabela: FINANCEIRO
-- -------------------------------------------------------------
ALTER TABLE FINANCEIRO
    ADD CONSTRAINT FK_FINANCEIRO_USUARIOC
    FOREIGN KEY (CODUSUARIO_CONTA)
    REFERENCES USUARIO (CODIGO);


-- -------------------------------------------------------------
-- Tabela: FINANCEIRO_VINCULO
-- -------------------------------------------------------------
ALTER TABLE FINANCEIRO_VINCULO
    ADD CONSTRAINT FK_FINANCEIRO_VINCULO_DESTINO
    FOREIGN KEY (DESTINO_CODPEDIDO, DESTINO_CODIGO, DESTINO_CODEMPRESA)
    REFERENCES FINANCEIRO (CODPEDIDO, CODIGO, CODEMPRESA);

ALTER TABLE FINANCEIRO_VINCULO
    ADD CONSTRAINT FK_FINANCEIRO_VINCULO_ORIGEM
    FOREIGN KEY (ORIGEM_CODPEDIDO, ORIGEM_CODIGO, ORIGEM_CODEMPRESA)
    REFERENCES FINANCEIRO (CODPEDIDO, CODIGO, CODEMPRESA);

ALTER TABLE FINANCEIRO_VINCULO
    ADD CONSTRAINT FK_FINANCEIRO_VINCULO_USUARIO
    FOREIGN KEY (CODUSUARIO)
    REFERENCES USUARIO (CODIGO);


-- -------------------------------------------------------------
-- Tabela: NF_ENTRADA_CENTRO_TRABALHO
-- -------------------------------------------------------------
ALTER TABLE NF_ENTRADA_CENTRO_TRABALHO
    ADD CONSTRAINT FK_NF_ENTRADA_CENTRO_TRABALHO_1
    FOREIGN KEY (CODPRODUTO_CT_PRE_REQUISITO)
    REFERENCES NF_ENTRADA_CENTRO_TRABALHO (CODIGO);

ALTER TABLE NF_ENTRADA_CENTRO_TRABALHO
    ADD CONSTRAINT FK_NF_ENTRADA_CENTRO_TRABALHO_2
    FOREIGN KEY (CODNF_ENTRADA_PRODUTO, CODNF_ENTRADA)
    REFERENCES NF_ENTRADA_PRODUTOS (CODIGO, CODNF_ENTRADA);

ALTER TABLE NF_ENTRADA_CENTRO_TRABALHO
    ADD CONSTRAINT FK_NF_ENTRADA_CENTRO_TRABALHO_3
    FOREIGN KEY (CODCENTRO_TRABALHO)
    REFERENCES CENTRO_TRABALHO (CODIGO);


-- -------------------------------------------------------------
-- Tabela: NF_ENTRADA_PRODUTOS
-- -------------------------------------------------------------
ALTER TABLE NF_ENTRADA_PRODUTOS
    ADD CONSTRAINT FK_NF_ENTRADA_PRODUTOS_COMPOS2
    FOREIGN KEY (CODNF_ENTRADA_PRODUTOS, CODNF_ENTRADA)
    REFERENCES NF_ENTRADA_PRODUTOS (CODIGO, CODNF_ENTRADA);

ALTER TABLE NF_ENTRADA_PRODUTOS
    ADD CONSTRAINT FK_NF_ENTRADA_PRODUTOS_COMPOS3
    FOREIGN KEY (CODPRODUTO)
    REFERENCES PRODUTO (CODIGO);

ALTER TABLE NF_ENTRADA_PRODUTOS
    ADD CONSTRAINT FK_NF_ENTRADA_PRODUTOS_COMPOS4
    FOREIGN KEY (CODPRODUTO_LOTE)
    REFERENCES PRODUTO_LOTE (CODIGO);

ALTER TABLE NF_ENTRADA_PRODUTOS
    ADD CONSTRAINT FK_NF_ENTRADA_PRODUTOS_COMPOS5
    FOREIGN KEY (CODFORNECEDOR)
    REFERENCES PESSOAS (CODIGO);

ALTER TABLE NF_ENTRADA_PRODUTOS
    ADD CONSTRAINT FK_NF_ENTRADA_PRODUTOS_COMPOS7
    FOREIGN KEY (CODFORMULA_PERFIL)
    REFERENCES FORMULA_PERFIL (CODIGO);


-- -------------------------------------------------------------
-- Tabela: NOTA_FISCAL_ENTRADA
-- -------------------------------------------------------------
ALTER TABLE NOTA_FISCAL_ENTRADA
    ADD CONSTRAINT FK_NOTA_FISCAL_ENTRADA_EMPRESA
    FOREIGN KEY (CODEMPRESA)
    REFERENCES EMPRESA (CODIGO);


-- -------------------------------------------------------------
-- Tabela: PRODUCAO_OS
-- -------------------------------------------------------------
ALTER TABLE PRODUCAO_OS
    ADD CONSTRAINT FK_PRODUCAO_OS_ACABAMENTO
    FOREIGN KEY (CODACABAMENTO)
    REFERENCES ACABAMENTO (CODIGO);

ALTER TABLE PRODUCAO_OS
    ADD CONSTRAINT FK_PRODUCAO_OS_PRE_REQUISITO
    FOREIGN KEY (CODPRODUCAO_OS_PRE_REQUISITO)
    REFERENCES PRODUCAO_OS (CODIGO);

ALTER TABLE PRODUCAO_OS
    ADD CONSTRAINT FK_PRODUCAO_OS_PRODUTO
    FOREIGN KEY (CODPRODUTO)
    REFERENCES PRODUTO (CODIGO);

ALTER TABLE PRODUCAO_OS
    ADD CONSTRAINT FK_PRODUCAO_OS_USUARIO
    FOREIGN KEY (CODUSUARIO)
    REFERENCES USUARIO (CODIGO);


-- -------------------------------------------------------------
-- Tabela: PRODUTO_CENTRO_TRABALHO
-- -------------------------------------------------------------
ALTER TABLE PRODUTO_CENTRO_TRABALHO
    ADD CONSTRAINT FK_PRODUTO_CENTRO_TRABALHO_CT
    FOREIGN KEY (CODCENTRO_TRABALHO)
    REFERENCES CENTRO_TRABALHO (CODIGO);

ALTER TABLE PRODUTO_CENTRO_TRABALHO
    ADD CONSTRAINT FK_PRODUTO_CENTRO_TRABALHO_PCT
    FOREIGN KEY (CODPRODUTO_CT_PRE_REQUISITO)
    REFERENCES PRODUTO_CENTRO_TRABALHO (CODIGO);


-- -------------------------------------------------------------
-- Tabela: RECURSO
-- -------------------------------------------------------------
ALTER TABLE RECURSO
    ADD CONSTRAINT FK_RECURSO_PESSOAS
    FOREIGN KEY (CODPESSOA)
    REFERENCES PESSOAS (CODIGO);

ALTER TABLE RECURSO
    ADD CONSTRAINT FK_RECURSO_PRODUTO
    FOREIGN KEY (CODPRODUTO)
    REFERENCES PRODUTO (CODIGO);

ALTER TABLE RECURSO
    ADD CONSTRAINT FK_RECURSO_TEMPO_TRABALHO
    FOREIGN KEY (CODTEMPO_TRABALHO)
    REFERENCES TEMPO_TRABALHO (CODIGO);


-- -------------------------------------------------------------
-- Tabela: RECURSO_AUSENCIA
-- -------------------------------------------------------------
ALTER TABLE RECURSO_AUSENCIA
    ADD CONSTRAINT FK_RECURSO_AUSENCIA_RECURSO
    FOREIGN KEY (CODRECURSO)
    REFERENCES RECURSO (CODIGO);


-- -------------------------------------------------------------
-- Tabela: SINTEGRA
-- -------------------------------------------------------------
ALTER TABLE SINTEGRA
    ADD CONSTRAINT FK_SINTEGRA_EMPRESA
    FOREIGN KEY (CODEMPRESA)
    REFERENCES EMPRESA (CODIGO);


-- -------------------------------------------------------------
-- Tabela: SINTEGRA_R10
-- -------------------------------------------------------------
ALTER TABLE SINTEGRA_R10
    ADD CONSTRAINT FK_SINTEGRA_R10_SINTEGRA
    FOREIGN KEY (CODSINTEGRA)
    REFERENCES SINTEGRA (CODIGO);


-- -------------------------------------------------------------
-- Tabela: SINTEGRA_R11
-- -------------------------------------------------------------
ALTER TABLE SINTEGRA_R11
    ADD CONSTRAINT FK_SINTEGRA_R11_SINTEGRA
    FOREIGN KEY (CODSINTEGRA)
    REFERENCES SINTEGRA (CODIGO);


-- -------------------------------------------------------------
-- Tabela: SINTEGRA_R50
-- -------------------------------------------------------------
ALTER TABLE SINTEGRA_R50
    ADD CONSTRAINT FK_SINTEGRA_R50_SINTEGRA
    FOREIGN KEY (CODSINTEGRA)
    REFERENCES SINTEGRA (CODIGO);


-- -------------------------------------------------------------
-- Tabela: SINTEGRA_R51
-- -------------------------------------------------------------
ALTER TABLE SINTEGRA_R51
    ADD CONSTRAINT FK_SINTEGRA_R51_SINTEGRA
    FOREIGN KEY (CODSINTEGRA)
    REFERENCES SINTEGRA (CODIGO);


-- -------------------------------------------------------------
-- Tabela: SINTEGRA_R53
-- -------------------------------------------------------------
ALTER TABLE SINTEGRA_R53
    ADD CONSTRAINT FK_SINTEGRA_R53_SINTEGRA
    FOREIGN KEY (CODSINTEGRA)
    REFERENCES SINTEGRA (CODIGO);


-- -------------------------------------------------------------
-- Tabela: SINTEGRA_R54
-- -------------------------------------------------------------
ALTER TABLE SINTEGRA_R54
    ADD CONSTRAINT FK_SINTEGRA_R54_SINTEGRA
    FOREIGN KEY (CODSINTEGRA)
    REFERENCES SINTEGRA (CODIGO);


-- -------------------------------------------------------------
-- Tabela: SINTEGRA_R60A
-- -------------------------------------------------------------
ALTER TABLE SINTEGRA_R60A
    ADD CONSTRAINT FK_SINTEGRA_R60A_SINTEGRA
    FOREIGN KEY (CODSINTEGRA)
    REFERENCES SINTEGRA (CODIGO);


-- -------------------------------------------------------------
-- Tabela: SINTEGRA_R60M
-- -------------------------------------------------------------
ALTER TABLE SINTEGRA_R60M
    ADD CONSTRAINT FK_SINTEGRA_R60M_SINTEGRA
    FOREIGN KEY (CODSINTEGRA)
    REFERENCES SINTEGRA (CODIGO);


-- -------------------------------------------------------------
-- Tabela: SINTEGRA_R61
-- -------------------------------------------------------------
ALTER TABLE SINTEGRA_R61
    ADD CONSTRAINT FK_SINTEGRA_R61_1
    FOREIGN KEY (CODSINTEGRA)
    REFERENCES SINTEGRA (CODIGO);


-- -------------------------------------------------------------
-- Tabela: SINTEGRA_R70
-- -------------------------------------------------------------
ALTER TABLE SINTEGRA_R70
    ADD CONSTRAINT FK_SINTEGRA_R70_SINTEGRA
    FOREIGN KEY (CODSINTEGRA)
    REFERENCES SINTEGRA (CODIGO);


-- -------------------------------------------------------------
-- Tabela: SINTEGRA_R74
-- -------------------------------------------------------------
ALTER TABLE SINTEGRA_R74
    ADD CONSTRAINT FK_SINTEGRA_R74_SINTEGRA
    FOREIGN KEY (CODSINTEGRA)
    REFERENCES SINTEGRA (CODIGO);


-- -------------------------------------------------------------
-- Tabela: SINTEGRA_R75
-- -------------------------------------------------------------
ALTER TABLE SINTEGRA_R75
    ADD CONSTRAINT FK_SINTEGRA_R75_SINTEGRA
    FOREIGN KEY (CODSINTEGRA)
    REFERENCES SINTEGRA (CODIGO);


-- -------------------------------------------------------------
-- Tabela: TEMPO_TRABALHO_HORARIO
-- -------------------------------------------------------------
ALTER TABLE TEMPO_TRABALHO_HORARIO
    ADD CONSTRAINT FK_TEMPO_TRABALHO_HORARIO_1
    FOREIGN KEY (CODTEMPO_TRABALHO)
    REFERENCES TEMPO_TRABALHO (CODIGO);


-- -------------------------------------------------------------
-- Tabela: TIPO_PAGAMENTO
-- -------------------------------------------------------------
ALTER TABLE TIPO_PAGAMENTO
    ADD CONSTRAINT FK_TIPO_PAGAMENTO_CONTA
    FOREIGN KEY (CODCONTA_PADRAO)
    REFERENCES CONTAS (CODIGO);


-- -------------------------------------------------------------
-- Tabela: VENDA
-- -------------------------------------------------------------
ALTER TABLE VENDA
    ADD CONSTRAINT FK_VENDA_NATUREZA_OPERACAO
    FOREIGN KEY (NF_CODNATUREZA_OPERACAO)
    REFERENCES NF_NATUREZA_OPERACAO (CODIGO);

ALTER TABLE VENDA
    ADD CONSTRAINT FK_VENDA_PROJETO
    FOREIGN KEY (CODPROJETO)
    REFERENCES PROJETO (CODIGO);

ALTER TABLE VENDA
    ADD CONSTRAINT FK_VENDA_USUARIO_EXCLUSAO
    FOREIGN KEY (CODUSUARIO_EXCLUSAO)
    REFERENCES USUARIO (CODIGO);


-- -------------------------------------------------------------
-- Tabela: VENDA_COMPOSICAO
-- -------------------------------------------------------------
ALTER TABLE VENDA_COMPOSICAO
    ADD CONSTRAINT FK_VENDA_COMPOSICAO_BASE
    FOREIGN KEY (CODVENDA_COMPOSICAO_BASE, CODVENDA, CODVENDA_PRODUTO)
    REFERENCES VENDA_COMPOSICAO (CODIGO, CODVENDA, CODVENDA_PRODUTO);

ALTER TABLE VENDA_COMPOSICAO
    ADD CONSTRAINT FK_VENDA_COMPOSICAO_FP
    FOREIGN KEY (CODFORMULA_PERFIL)
    REFERENCES FORMULA_PERFIL (CODIGO);


-- -------------------------------------------------------------
-- Tabela: VENDA_PRODUTO
-- -------------------------------------------------------------
ALTER TABLE VENDA_PRODUTO
    ADD CONSTRAINT FK_VENDA_PRODUTO_CENTRO_TRAB2
    FOREIGN KEY (CODCENTRO_TRABALHO)
    REFERENCES CENTRO_TRABALHO (CODIGO);

ALTER TABLE VENDA_PRODUTO
    ADD CONSTRAINT FK_VENDA_PRODUTO_CENTRO_TRABALH
    FOREIGN KEY (CODVENDA_PRODUTO_CT_PRE_REQ)
    REFERENCES VENDA_PRODUTO_CENTRO_TRABALHO (CODIGO);

ALTER TABLE VENDA_PRODUTO
    ADD CONSTRAINT FK_VENDA_PRODUTO_VENDA_ORIGINAL
    FOREIGN KEY (CODVENDA_PRODUTO_ORIGINAL, CODVENDA_ORIGINAL)
    REFERENCES VENDA_PRODUTO (CODIGO, CODVENDA);


-- -------------------------------------------------------------
-- Tabela: VENDA_TIPO
-- -------------------------------------------------------------
ALTER TABLE VENDA_TIPO
    ADD CONSTRAINT FK_VENDA_TIPO_NAT_OP
    FOREIGN KEY (CODNF_NATUREZA_OPERACAO_PADRAO)
    REFERENCES NF_NATUREZA_OPERACAO (CODIGO);

