<?php

Route::group(['middleware' => ['web',  'SetSessionData','auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'], 'prefix' => 'connector', 'namespace' => 'Modules\Connector\Http\Controllers'], function () 
{
    Route::get('/api', 'ConnectorController@index');
    Route::resource('/client', 'ClientController');
    Route::get('/regenerate', 'ClientController@regenerate');

	Route::get('install', 'InstallController@index');
    Route::post('install', 'InstallController@install');
    Route::get('install/uninstall', 'InstallController@uninstall');
    Route::get('install/update', 'InstallController@update');
});

Route::group(['middleware' => ['timezone'], 'prefix' => 'connector/api', 'namespace' => 'Modules\Connector\Http\Controllers\Api'], function()
{
    // Rota do Dify sem autenticação
    Route::post('dify/receive', 'DifyController@receive');
});

Route::group(['middleware' => ['auth:api', 'timezone'], 'prefix' => 'connector/api', 'namespace' => 'Modules\Connector\Http\Controllers\Api'], function()
{
    // Cadastro de Empresa
	Route::resource('business', 'BusinessController', ['only' => ['index', 'show', 'store', 'update']]);
	Route::post('/processa-dados-cliente', 'LicencaComputadorController@ProcessaDadosCliente');
	Route::post('/salvar-cliente', 'BusinessController@saveBusiness');
	Route::post('/salvar-equipamento/{business_id}', 'LicencaComputadorController@saveEquipamento');
	Route::resource('business-location', 'BusinessLocationController', ['only' => ['index', 'show', 'store', 'update']]);
	Route::get('packages', 'SuperadminController@getPackages');
	Route::get('active-subscription', 'SuperadminController@getActiveSubscription');
	Route::get('business-details', 'CommonResourceController@getBusinessDetails');
	Route::post('empresa/sync-post', 'EmpresaController@syncEmpresa');
	Route::get('empresa/sync-get', 'EmpresaController@getSyncEmpresaUntilDate');
	
	Route::post('licenciamento/sync-post', 'LicenciamentoController@syncLicenciamento');
	Route::get('licenciamento/sync-get', 'LicenciamentoController@getSyncLicenciamentoUntilDate');
	Route::post('licenciamento_historico/sync-post', 'LicenciamentoHistoricoController@syncLicenciamentoHistorico');
	Route::get('licenciamento_historico/sync-get', 'LicenciamentoHistoricoController@getSyncLicenciamentoHistoricoUntilDate');
	

	// Usuários/Permissões
    Route::get('usuario/sync-get', 'UserController@syncGet');
    Route::post('usuario/sync-post', 'UserController@syncPost');	
	Route::get('usuario/user', 'UserController@getUserByBusinessCNPJ');
    Route::get('user/loggedin', 'UserController@loggedin');
	Route::resource('user', 'UserController', ['only' => ['index', 'show']]);
	Route::get('get-attendance/{user_id}', 'AttendanceController@getAttendance');
	Route::post('update-password', 'UserController@updatePassword');
	Route::post('usuario_extra/sync-post', 'UsuarioExtraController@syncUsuarioExtra');
	Route::get('usuario_extra/sync-get', 'UsuarioExtraController@getSyncUsuarioExtraUntilDate');
	Route::post('usuario_extraex/sync-post', 'UsuarioExtraexController@syncUsuarioExtraex');
	Route::get('usuario_extraex/sync-get', 'UsuarioExtraexController@getSyncUsuarioExtraexUntilDate');
	Route::post('usuario_log/sync-post', 'UsuarioLogController@syncUsuarioLog');
	Route::get('usuario_log/sync-get', 'UsuarioLogController@getSyncUsuarioLogUntilDate');
	Route::post('usuario_logado/sync-post', 'UsuarioLogadoController@syncUsuarioLogado');
	Route::get('usuario_logado/sync-get', 'UsuarioLogadoController@getSyncUsuarioLogadoUntilDate');
	Route::post('usuario_menssagem/sync-post', 'UsuarioMenssagemController@syncUsuarioMenssagem');
	Route::get('usuario_menssagem/sync-get', 'UsuarioMenssagemController@getSyncUsuarioMenssagemUntilDate');
	

	// Chat
	Route::get('notifications', 'CommonResourceController@getNotifications');
	Route::post('mensagem/sync-post', 'MensagemController@syncMensagem');
	Route::get('mensagem/sync-get', 'MensagemController@getSyncMensagemUntilDate');
	Route::post('mensagem_assunto/sync-post', 'MensagemAssuntoController@syncMensagemAssunto');
	Route::get('mensagem_assunto/sync-get', 'MensagemAssuntoController@getSyncMensagemAssuntoUntilDate');
	Route::post('mensagem_contato/sync-post', 'MensagemContatoController@syncMensagemContato');
	Route::get('mensagem_contato/sync-get', 'MensagemContatoController@getSyncMensagemContatoUntilDate');
	Route::post('mensagem_lido/sync-post', 'MensagemLidoController@syncMensagemLido');
	Route::get('mensagem_lido/sync-get', 'MensagemLidoController@getSyncMensagemLidoUntilDate');
	

    //Pessoas
	Route::post('pessoas_grupo/sync-post', 'PessoasGrupoController@syncPessoasGrupo');
	Route::get('pessoas_grupo/sync-get', 'PessoasGrupoController@getSyncPessoasGrupoUntilDate');
	Route::post('cidades/sync-post', 'CidadesController@syncCidades');
	Route::get('cidades/sync-get', 'CidadesController@getCidadesUntilDate');
	Route::resource('contactapi', 'ContactController', ['only' => ['index', 'show', 'store', 'update']]);
	Route::post('contactapi-payment', 'ContactController@contactPay');
//	Route::resource('taxonomy', 'CategoryController', ['only' => ['index', 'show']]);
	Route::post('pessoas_tipo/sync-post', 'PessoasTipoController@syncPessoasTipo');
	Route::get('pessoas_tipo/sync-get', 'PessoasTipoController@getSyncPessoasTipoUntilDate');
	Route::post('pais/sync-post', 'PaisController@syncPais');
	Route::get('pais/sync-get', 'PaisController@getSyncPaisUntilDate');
	Route::post('pessoas/sync-post', 'PessoasController@syncPessoas');
	Route::get('pessoas/sync-get', 'PessoasController@getSyncPessoasUntilDate');
	Route::post('pessoas_cheques_autorizados/sync-post', 'PessoasChequesAutorizadosController@syncPessoasChequesAutorizados');
	Route::get('pessoas_cheques_autorizados/sync-get', 'PessoasChequesAutorizadosController@getSyncPessoasChequesAutorizadosUntilDate');
	Route::post('pessoas_contato/sync-post', 'PessoasContatoController@syncPessoasContato');
	Route::get('pessoas_contato/sync-get', 'PessoasContatoController@getSyncPessoasContatoUntilDate');
	Route::post('pessoas_credito/sync-post', 'PessoasCreditoController@syncPessoasCredito');
	Route::get('pessoas_credito/sync-get', 'PessoasCreditoController@getSyncPessoasCreditoUntilDate');
	Route::post('pessoas_entrega/sync-post', 'PessoasEntregaController@syncPessoasEntrega');
	Route::get('pessoas_entrega/sync-get', 'PessoasEntregaController@getSyncPessoasEntregaUntilDate');
	Route::post('pessoas_produto/sync-post', 'PessoasProdutoController@syncPessoasProduto');
	Route::get('pessoas_produto/sync-get', 'PessoasProdutoController@getSyncPessoasProdutoUntilDate');
	Route::post('clientes_produto/sync-post', 'ClientesProdutoController@syncClientesProduto');
	Route::get('clientes_produto/sync-get', 'ClientesProdutoController@getSyncClientesProdutoUntilDate');
	Route::post('pessoas_representante/sync-post', 'PessoasRepresentanteController@syncPessoasRepresentante');
	Route::get('pessoas_representante/sync-get', 'PessoasRepresentanteController@getSyncPessoasRepresentanteUntilDate');
		
	// Produtos
	Route::get('produto/sync-get', 'ProductController@getUntilDate');
	Route::post('produto/sync-post', 'ProductController@sync');
	Route::get('produto_marca/sync-get', 'BrandController@getUntilDate');
	Route::post('produto_marca/sync-post', 'BrandController@sync');
	Route::get('produto_categoria/sync-get', 'ProdutoCategoriaController@getUntilDate');
	Route::post('produto_categoria/sync-post', 'ProdutoCategoriaController@sync');
	Route::get('unidade/sync-get', 'UnitController@getUnitsUntilDate');
	Route::post('unidade/sync-post', 'UnitController@syncUnits');
	//Route::resource('brand', 'BrandController', ['only' => ['index', 'show', 'create','update']]);
	Route::resource('unit', 'UnitController', ['only' => ['index', 'show']]);
	Route::resource('product', 'ProductController', ['only' => ['index', 'show']]);
	Route::get('variation/{id?}', 'ProductController@listVariations');
	Route::resource('types-of-service', 'TypesOfServiceController', ['only' => ['index', 'show']]);
	Route::post('produto_baixa_automatica/sync-post', 'ProdutoBaixaAutomaticaController@syncProdutoBaixaAutomatica');
	Route::get('produto_baixa_automatica/sync-get', 'ProdutoBaixaAutomaticaController@getSyncProdutoBaixaAutomaticaUntilDate');
	Route::post('produto_barras/sync-post', 'ProdutoBarrasController@syncProdutoBarras');
	Route::get('produto_barras/sync-get', 'ProdutoBarrasController@getSyncProdutoBarrasUntilDate');
	Route::post('produto_tipo/sync-post', 'ProdutoTipoController@syncProdutoTipo');
	Route::get('produto_tipo/sync-get', 'ProdutoTipoController@getSyncProdutoTipoUntilDate');
	Route::post('precificacao/sync-post', 'PrecificacaoController@syncPrecificacao');   // parece ser na venda
	Route::get('precificacao/sync-get', 'PrecificacaoController@getSyncPrecificacaoUntilDate');
	Route::post('produto_markup/sync-post', 'ProdutoMarkupController@syncProdutoMarkup');
	Route::get('produto_markup/sync-get', 'ProdutoMarkupController@getSyncProdutoMarkupUntilDate');
	Route::post('produto_preco/sync-post', 'ProdutoPrecoController@syncProdutoPreco');
	Route::get('produto_preco/sync-get', 'ProdutoPrecoController@getSyncProdutoPrecoUntilDate');
	Route::post('produto_tabela/sync-post', 'ProdutoTabelaController@syncProdutoTabela');
	Route::get('produto_tabela/sync-get', 'ProdutoTabelaController@getSyncProdutoTabelaUntilDate');
	Route::post('produto_tabela_preco/sync-post', 'ProdutoTabelaPrecoController@syncProdutoTabelaPreco');
	Route::get('produto_tabela_preco/sync-get', 'ProdutoTabelaPrecoController@getSyncProdutoTabelaPrecoUntilDate');
	Route::post('produto_centro_trabalho/sync-post', 'ProdutoCentroTrabalhoController@syncProdutoCentroTrabalho');
	Route::get('produto_centro_trabalho/sync-get', 'ProdutoCentroTrabalhoController@getSyncProdutoCentroTrabalhoUntilDate');
	Route::post('produto_composicao/sync-post', 'ProdutoComposicaoController@syncProdutoComposicao');
	Route::get('produto_composicao/sync-get', 'ProdutoComposicaoController@getSyncProdutoComposicaoUntilDate');
	Route::post('produto_custo_adicional/sync-post', 'ProdutoCustoAdicionalController@syncProdutoCustoAdicional');
	Route::get('produto_custo_adicional/sync-get', 'ProdutoCustoAdicionalController@getSyncProdutoCustoAdicionalUntilDate');
	Route::post('produto_equipamento/sync-post', 'ProdutoEquipamentoController@syncProdutoEquipamento');
	Route::get('produto_equipamento/sync-get', 'ProdutoEquipamentoController@getSyncProdutoEquipamentoUntilDate');
	Route::post('produto_etapa/sync-post', 'ProdutoEtapaController@syncProdutoEtapa');
	Route::get('produto_etapa/sync-get', 'ProdutoEtapaController@getSyncProdutoEtapaUntilDate');
	Route::post('produto_fabrica/sync-post', 'ProdutoFabricaController@syncProdutoFabrica');
	Route::get('produto_fabrica/sync-get', 'ProdutoFabricaController@getSyncProdutoFabricaUntilDate');
	Route::post('produto_formato_corte/sync-post', 'ProdutoFormatoCorteController@syncProdutoFormatoCorte');
	Route::get('produto_formato_corte/sync-get', 'ProdutoFormatoCorteController@getSyncProdutoFormatoCorteUntilDate');
	Route::post('produto_fornecedor/sync-post', 'ProdutoFornecedorController@syncProdutoFornecedor');
	Route::get('produto_fornecedor/sync-get', 'ProdutoFornecedorController@getSyncProdutoFornecedorUntilDate');
	Route::post('produto_grade_modelo/sync-post', 'ProdutoGradeModeloController@syncProdutoGradeModelo');
	Route::get('produto_grade_modelo/sync-get', 'ProdutoGradeModeloController@getSyncProdutoGradeModeloUntilDate');
	Route::post('produto_grade_modelo_item/sync-post', 'ProdutoGradeModeloItemController@syncProdutoGradeModeloItem');
	Route::get('produto_grade_modelo_item/sync-get', 'ProdutoGradeModeloItemController@getSyncProdutoGradeModeloItemUntilDate');
	Route::post('produto_grupo/sync-post', 'ProdutoGrupoController@sync');
	Route::get('produto_grupo/sync-get', 'ProdutoGrupoController@getUntilDate');
	Route::post('produto_grupo_impostouf/sync-post', 'ProdutoGrupoImpostoUFController@syncProdutoGrupoImpostoUF');
	Route::get('produto_grupo_impostouf/sync-get', 'ProdutoGrupoImpostoUFController@getSyncProdutoGrupoImpostoUFUntilDate');
	Route::post('produto_imposto/sync-post', 'ProdutoImpostoController@syncProdutoImposto');
	Route::get('produto_imposto/sync-get', 'ProdutoImpostoController@getSyncProdutoImpostoUntilDate');
	Route::post('produto_imposto_estado/sync-post', 'ProdutoImpostoEstadoController@syncProdutoImpostoEstado');
	Route::get('produto_imposto_estado/sync-get', 'ProdutoImpostoEstadoController@getSyncProdutoImpostoEstadoUntilDate');
	Route::post('produto_subunidade/sync-post', 'ProdutoSubunidadeController@syncProdutoSubunidade');
	Route::get('produto_subunidade/sync-get', 'ProdutoSubunidadeController@getSyncProdutoSubunidadeUntilDate');
	Route::post('unidade_subunidade/sync-post', 'UnidadeSubunidadeController@syncUnidadeSubunidade');
	Route::get('unidade_subunidade/sync-get', 'UnidadeSubunidadeController@getSyncUnidadeSubunidadeUntilDate');
	Route::post('produto_wizard/sync-post', 'ProdutoWizardController@syncProdutoWizard');
	Route::get('produto_wizard/sync-get', 'ProdutoWizardController@getSyncProdutoWizardUntilDate');
	Route::post('produto_wizard_condicao/sync-post', 'ProdutoWizardCondicaoController@syncProdutoWizardCondicao');
	Route::get('produto_wizard_condicao/sync-get', 'ProdutoWizardCondicaoController@getSyncProdutoWizardCondicaoUntilDate');
	Route::post('produto_wizard_materia_prima/sync-post', 'ProdutoWizardMateriaPrimaController@syncProdutoWizardMateriaPrima');
	Route::get('produto_wizard_materia_prima/sync-get', 'ProdutoWizardMateriaPrimaController@getSyncProdutoWizardMateriaPrimaUntilDate');
	

    // Financeiro
    Route::post('condicaopagto/sync-post', 'CondicaopagtoController@syncCondicaopagto');
    Route::get('condicaopagto/sync-get', 'CondicaopagtoController@getCondicaopagtoUntilDate');	
	Route::get('payment-accounts', 'CommonResourceController@getPaymentAccounts');
	Route::get('payment-methods', 'CommonResourceController@getPaymentMethods');
	Route::resource('cash-register', 'CashRegisterController', ['only' => ['index', 'store', 'show', 'update']]);
	Route::resource('expense', 'ExpenseController', ['only' => ['index', 'store', 'show', 'update']]);
	Route::get('expense-refund', 'ExpenseController@listExpenseRefund');
	Route::get('profit-loss-report', 'CommonResourceController@getProfitLoss');

	Route::post('bancos/sync-post', 'BancosController@syncBancos');
	Route::get('bancos/sync-get', 'BancosController@getSyncBancosUntilDate');
	Route::post('bancos_conciliacao_bancaria/sync-post', 'BancosConciliacaoBancariaController@syncBancosConciliacaoBancaria');
	Route::get('bancos_conciliacao_bancaria/sync-get', 'BancosConciliacaoBancariaController@getSyncBancosConciliacaoBancariaUntilDate');
	Route::post('boletos/sync-post', 'BoletosController@syncBoletos');
	Route::get('boletos/sync-get', 'BoletosController@getSyncBoletosUntilDate');
	Route::post('caixa/sync-post', 'CaixaController@syncCaixa');
	Route::get('caixa/sync-get', 'CaixaController@getSyncCaixaUntilDate');
	Route::post('caixa_configuracao/sync-post', 'CaixaConfiguracaoController@syncCaixaConfiguracao');
	Route::get('caixa_configuracao/sync-get', 'CaixaConfiguracaoController@getSyncCaixaConfiguracaoUntilDate');
	Route::post('caixa_historico/sync-post', 'CaixaHistoricoController@syncCaixaHistorico');
	Route::get('caixa_historico/sync-get', 'CaixaHistoricoController@getSyncCaixaHistoricoUntilDate');
	Route::post('centro_custo/sync-post', 'CentroCustoController@syncCentroCusto');
	Route::get('centro_custo/sync-get', 'CentroCustoController@getSyncCentroCustoUntilDate');
	Route::post('centro_de_custo_rateio/sync-post', 'CentroDeCustoRateioController@syncCentroDeCustoRateio');
	Route::get('centro_de_custo_rateio/sync-get', 'CentroDeCustoRateioController@getSyncCentroDeCustoRateioUntilDate');
	Route::post('comissao/sync-post', 'ComissaoController@syncComissao');
	Route::get('comissao/sync-get', 'ComissaoController@getSyncComissaoUntilDate');
	Route::post('comissao_financeiro/sync-post', 'ComissaoFinanceiroController@syncComissaoFinanceiro');
	Route::get('comissao_financeiro/sync-get', 'ComissaoFinanceiroController@getSyncComissaoFinanceiroUntilDate');
	Route::post('comissao_meta/sync-post', 'ComissaoMetaController@syncComissaoMeta');
	Route::get('comissao_meta/sync-get', 'ComissaoMetaController@getSyncComissaoMetaUntilDate');
	Route::post('comissao_pessoa/sync-post', 'ComissaoPessoaController@syncComissaoPessoa');
	Route::get('comissao_pessoa/sync-get', 'ComissaoPessoaController@getSyncComissaoPessoaUntilDate');
	Route::post('comissao_produto/sync-post', 'ComissaoProdutoController@syncComissaoProduto');
	Route::get('comissao_produto/sync-get', 'ComissaoProdutoController@getSyncComissaoProdutoUntilDate');
	Route::post('competencia/sync-post', 'CompetenciaController@syncCompetencia');
	Route::get('competencia/sync-get', 'CompetenciaController@getSyncCompetenciaUntilDate');
	Route::post('conciliacao_bancaria/sync-post', 'ConciliacaoBancariaController@syncConciliacaoBancaria');
	Route::get('conciliacao_bancaria/sync-get', 'ConciliacaoBancariaController@getSyncConciliacaoBancariaUntilDate');
	Route::post('conciliacao_bancaria_financeiro/sync-post', 'ConciliacaoBancariaFinanceiroController@syncConciliacaoBancariaFinanceiro');
	Route::get('conciliacao_bancaria_financeiro/sync-get', 'ConciliacaoBancariaFinanceiroController@getSyncConciliacaoBancariaFinanceiroUntilDate');
	Route::post('contas/sync-post', 'ContasController@syncContas');
	Route::get('contas/sync-get', 'ContasController@getSyncContasUntilDate');

	Route::post('contrato/sync-post', 'ContratoController@syncContrato');
	Route::get('contrato/sync-get', 'ContratoController@getSyncContratoUntilDate');
	Route::post('contrato_produto/sync-post', 'ContratoProdutoController@syncContratoProduto');
	Route::get('contrato_produto/sync-get', 'ContratoProdutoController@getSyncContratoProdutoUntilDate');
	Route::post('contrato_tipo/sync-post', 'ContratoTipoController@syncContratoTipo');
	Route::get('contrato_tipo/sync-get', 'ContratoTipoController@getSyncContratoTipoUntilDate');
	Route::post('mensalidade/sync-post', 'MensalidadeController@syncMensalidade');
	Route::get('mensalidade/sync-get', 'MensalidadeController@getSyncMensalidadeUntilDate');
	Route::post('mensalidade_financeiro/sync-post', 'MensalidadeFinanceiroController@syncMensalidadeFinanceiro');
	Route::get('mensalidade_financeiro/sync-get', 'MensalidadeFinanceiroController@getSyncMensalidadeFinanceiroUntilDate');
	

	Route::post('dre/sync-post', 'DREController@syncDRE');
	Route::get('dre/sync-get', 'DREController@getSyncDREUntilDate');
	Route::post('dre_centro_custo/sync-post', 'DreCentroCustoController@syncDreCentroCusto');
	Route::get('dre_centro_custo/sync-get', 'DreCentroCustoController@getSyncDreCentroCustoUntilDate');
	Route::post('dre_classificacao/sync-post', 'DreClassificacaoController@syncDreClassificacao');
	Route::get('dre_classificacao/sync-get', 'DreClassificacaoController@getSyncDreClassificacaoUntilDate');
	Route::post('dre_classificacao_planocontas/sync-post', 'DreClassificacaoPlanoContasController@syncDreClassificacaoPlanoContas');
	Route::get('dre_classificacao_planocontas/sync-get', 'DreClassificacaoPlanoContasController@getSyncDreClassificacaoPlanoContasUntilDate');
	Route::post('dre_planocontas/sync-post', 'DrePlanoContasController@syncDrePlanoContas');
	Route::get('dre_planocontas/sync-get', 'DrePlanoContasController@getSyncDrePlanoContasUntilDate');
	Route::post('dre_planocontas_centro_custo/sync-post', 'DrePlanoContasCentroCustoController@syncDrePlanoContasCentroCusto');
	Route::get('dre_planocontas_centro_custo/sync-get', 'DrePlanoContasCentroCustoController@getSyncDrePlanoContasCentroCustoUntilDate');
	

	Route::post('financeiro/sync-post', 'FinanceiroController@syncFinanceiro');
	Route::get('financeiro/sync-get', 'FinanceiroController@getSyncFinanceiroUntilDate');
	Route::post('financeiro_boleto/sync-post', 'FinanceiroBoletoController@syncFinanceiroBoleto');
	Route::get('financeiro_boleto/sync-get', 'FinanceiroBoletoController@getSyncFinanceiroBoletoUntilDate');
	Route::post('financeiro_boleto_historico/sync-post', 'FinanceiroBoletoHistoricoController@syncFinanceiroBoletoHistorico');
	Route::get('financeiro_boleto_historico/sync-get', 'FinanceiroBoletoHistoricoController@getSyncFinanceiroBoletoHistoricoUntilDate');
	Route::post('financeiro_centro_custo/sync-post', 'FinanceiroCentroCustoController@syncFinanceiroCentroCusto');
	Route::get('financeiro_centro_custo/sync-get', 'FinanceiroCentroCustoController@getSyncFinanceiroCentroCustoUntilDate');
	Route::post('financeiro_cheque/sync-post', 'FinanceiroChequeController@syncFinanceiroCheque');
	Route::get('financeiro_cheque/sync-get', 'FinanceiroChequeController@getSyncFinanceiroChequeUntilDate');
	Route::post('financeiro_historico/sync-post', 'FinanceiroHistoricoController@syncFinanceiroHistorico');
	Route::get('financeiro_historico/sync-get', 'FinanceiroHistoricoController@getSyncFinanceiroHistoricoUntilDate');
	Route::post('financeiro_hist_agrupamento/sync-post', 'FinanceiroHistAgrupamentoController@syncFinanceiroHistAgrupamento');
	Route::get('financeiro_hist_agrupamento/sync-get', 'FinanceiroHistAgrupamentoController@getSyncFinanceiroHistAgrupamentoUntilDate');
	Route::post('financeiro_motivo/sync-post', 'FinanceiroMotivoController@syncFinanceiroMotivo');
	Route::get('financeiro_motivo/sync-get', 'FinanceiroMotivoController@getSyncFinanceiroMotivoUntilDate');
	Route::post('financeiro_setor/sync-post', 'FinanceiroSetorController@syncFinanceiroSetor');
	Route::get('financeiro_setor/sync-get', 'FinanceiroSetorController@getSyncFinanceiroSetorUntilDate');
	Route::post('financeiro_vinculo/sync-post', 'FinanceiroVinculoController@syncFinanceiroVinculo');
	Route::get('financeiro_vinculo/sync-get', 'FinanceiroVinculoController@getSyncFinanceiroVinculoUntilDate');
	Route::post('planocontas/sync-post', 'PlanoContasController@syncPlanoContas');
	Route::get('planocontas/sync-get', 'PlanoContasController@getSyncPlanoContasUntilDate');
	Route::post('planocontas_centro_custo/sync-post', 'PlanoContasCentroCustoController@syncPlanoContasCentroCusto');
	Route::get('planocontas_centro_custo/sync-get', 'PlanoContasCentroCustoController@getSyncPlanoContasCentroCustoUntilDate');
	Route::post('tipo_pagamento/sync-post', 'TipoPagamentoController@syncTipoPagamento');
	Route::get('tipo_pagamento/sync-get', 'TipoPagamentoController@getSyncTipoPagamentoUntilDate');
	

	// Compras
	Route::post('nf_entrada/sync-post', 'NfEntradaController@syncNfEntrada');
	Route::get('nf_entrada/sync-get', 'NfEntradaController@getSyncNfEntradaUntilDate');
	Route::post('nf_entrada_centro_trabalho/sync-post', 'NfEntradaCentroTrabalhoController@syncNfEntradaCentroTrabalho');
	Route::get('nf_entrada_centro_trabalho/sync-get', 'NfEntradaCentroTrabalhoController@getSyncNfEntradaCentroTrabalhoUntilDate');
	Route::post('nf_entrada_despesa/sync-post', 'NfEntradaDespesaController@syncNfEntradaDespesa');
	Route::get('nf_entrada_despesa/sync-get', 'NfEntradaDespesaController@getSyncNfEntradaDespesaUntilDate');
	Route::post('nf_entrada_manifesto/sync-post', 'NfEntradaManifestoController@syncNfEntradaManifesto');
	Route::get('nf_entrada_manifesto/sync-get', 'NfEntradaManifestoController@getSyncNfEntradaManifestoUntilDate');
	Route::post('nf_entrada_parcelas/sync-post', 'NfEntradaParcelasController@syncNfEntradaParcelas');
	Route::get('nf_entrada_parcelas/sync-get', 'NfEntradaParcelasController@getSyncNfEntradaParcelasUntilDate');
	Route::post('nf_entrada_produtos/sync-post', 'NfEntradaProdutosController@syncNfEntradaProdutos');
	Route::get('nf_entrada_produtos/sync-get', 'NfEntradaProdutosController@getSyncNfEntradaProdutosUntilDate');
	Route::post('nf_entrada_produtos_afetados/sync-post', 'NfEntradaProdutosAfetadosController@syncNfEntradaProdutosAfetados');
	Route::get('nf_entrada_produtos_afetados/sync-get', 'NfEntradaProdutosAfetadosController@getSyncNfEntradaProdutosAfetadosUntilDate');
	Route::post('nf_entrada_produtos_composicao/sync-post', 'NfEntradaProdutosComposicaoController@syncNfEntradaProdutosComposicao');
	Route::get('nf_entrada_produtos_composicao/sync-get', 'NfEntradaProdutosComposicaoController@getSyncNfEntradaProdutosComposicaoUntilDate');
	Route::post('nf_entrada_produtos_custo_ad/sync-post', 'NfEntradaProdutosCustoAdController@syncNfEntradaProdutosCustoAd');
	Route::get('nf_entrada_produtos_custo_ad/sync-get', 'NfEntradaProdutosCustoAdController@getSyncNfEntradaProdutosCustoAdUntilDate');
	Route::post('nf_entrada_tabela_preco/sync-post', 'NfEntradaTabelaPrecoController@syncNfEntradaTabelaPreco');
	Route::get('nf_entrada_tabela_preco/sync-get', 'NfEntradaTabelaPrecoController@getSyncNfEntradaTabelaPrecoUntilDate');
	Route::post('nf_entrada_tipo/sync-post', 'NfEntradaTipoController@syncNfEntradaTipo');
	Route::get('nf_entrada_tipo/sync-get', 'NfEntradaTipoController@getSyncNfEntradaTipoUntilDate');
	Route::post('nf_entrada_vinculos/sync-post', 'NfEntradaVinculosController@syncNfEntradaVinculos');
	Route::get('nf_entrada_vinculos/sync-get', 'NfEntradaVinculosController@getSyncNfEntradaVinculosUntilDate');

	// Produção
	Route::post('producao/sync-post', 'ProducaoController@syncProducao');
	Route::get('producao/sync-get', 'ProducaoController@getSyncProducaoUntilDate');
	Route::post('producao_acao/sync-post', 'ProducaoAcaoController@syncProducaoAcao');
	Route::get('producao_acao/sync-get', 'ProducaoAcaoController@getSyncProducaoAcaoUntilDate');
	Route::post('producao_centro_trabalho/sync-post', 'ProducaoCentroTrabalhoController@syncProducaoCentroTrabalho');
	Route::get('producao_centro_trabalho/sync-get', 'ProducaoCentroTrabalhoController@getSyncProducaoCentroTrabalhoUntilDate');
	Route::post('producao_custo_adicional/sync-post', 'ProducaoCustoAdicionalController@syncProducaoCustoAdicional');
	Route::get('producao_custo_adicional/sync-get', 'ProducaoCustoAdicionalController@getSyncProducaoCustoAdicionalUntilDate');
	Route::post('producao_estagio/sync-post', 'ProducaoEstagioController@syncProducaoEstagio');
	Route::get('producao_estagio/sync-get', 'ProducaoEstagioController@getSyncProducaoEstagioUntilDate');
	Route::post('producao_etapas/sync-post', 'ProducaoEtapasController@syncProducaoEtapas');
	Route::get('producao_etapas/sync-get', 'ProducaoEtapasController@getSyncProducaoEtapasUntilDate');
	Route::post('producao_marcador/sync-post', 'ProducaoMarcadorController@syncProducaoMarcador');
	Route::get('producao_marcador/sync-get', 'ProducaoMarcadorController@getSyncProducaoMarcadorUntilDate');
	Route::post('producao_motivo/sync-post', 'ProducaoMotivoController@syncProducaoMotivo');
	Route::get('producao_motivo/sync-get', 'ProducaoMotivoController@getSyncProducaoMotivoUntilDate');
	Route::post('producao_movimento/sync-post', 'ProducaoMovimentoController@syncProducaoMovimento');
	Route::get('producao_movimento/sync-get', 'ProducaoMovimentoController@getSyncProducaoMovimentoUntilDate');
	Route::post('producao_nao_lido/sync-post', 'ProducaoNaoLidoController@syncProducaoNaoLido');
	Route::get('producao_nao_lido/sync-get', 'ProducaoNaoLidoController@getSyncProducaoNaoLidoUntilDate');
	Route::post('producao_os/sync-post', 'ProducaoOsController@syncProducaoOs');
	Route::get('producao_os/sync-get', 'ProducaoOsController@getSyncProducaoOsUntilDate');
	Route::post('producao_produto/sync-post', 'ProducaoProdutoController@syncProducaoProduto');
	Route::get('producao_produto/sync-get', 'ProducaoProdutoController@getSyncProducaoProdutoUntilDate');
	Route::post('producao_situacao/sync-post', 'ProducaoSituacaoController@syncProducaoSituacao');
	Route::get('producao_situacao/sync-get', 'ProducaoSituacaoController@getSyncProducaoSituacaoUntilDate');
	Route::post('producao_template/sync-post', 'ProducaoTemplateController@syncProducaoTemplate');
	Route::get('producao_template/sync-get', 'ProducaoTemplateController@getSyncProducaoTemplateUntilDate');
	Route::post('producao_tempo/sync-post', 'ProducaoTempoController@syncProducaoTempo');
	Route::get('producao_tempo/sync-get', 'ProducaoTempoController@getSyncProducaoTempoUntilDate');

	// Outras Entidades
	Route::post('tipo_impressao/sync-post', 'TipoImpressaoController@syncTipoImpressao');
	Route::get('tipo_impressao/sync-get', 'TipoImpressaoController@getSyncTipoImpressaoUntilDate');
	Route::post('producao_roteiro/sync-post', 'ProducaoRoteiroController@syncProducaoRoteiro');
	Route::get('producao_roteiro/sync-get', 'ProducaoRoteiroController@getSyncProducaoRoteiroUntilDate');
	Route::post('producao_roteiro_organograma/sync-post', 'ProducaoRoteiroOrganogramaController@syncProducaoRoteiroOrganograma');
	Route::get('producao_roteiro_organograma/sync-get', 'ProducaoRoteiroOrganogramaController@getSyncProducaoRoteiroOrganogramaUntilDate');
	Route::post('producao_roteiro_pergunta/sync-post', 'ProducaoRoteiroPerguntaController@syncProducaoRoteiroPergunta');
	Route::get('producao_roteiro_pergunta/sync-get', 'ProducaoRoteiroPerguntaController@getSyncProducaoRoteiroPerguntaUntilDate');
	Route::post('produto_prerequisito/sync-post', 'ProdutoPrerequisitoController@syncProdutoPrerequisito');
	Route::get('produto_prerequisito/sync-get', 'ProdutoPrerequisitoController@getSyncProdutoPrerequisitoUntilDate');
	Route::post('acabamento/sync-post', 'AcabamentoController@syncAcabamento');
	Route::get('acabamento/sync-get', 'AcabamentoController@getSyncAcabamentoUntilDate');
	Route::post('centro_trabalho/sync-post', 'CentroTrabalhoController@syncCentroTrabalho');
	Route::get('centro_trabalho/sync-get', 'CentroTrabalhoController@getSyncCentroTrabalhoUntilDate');
	Route::post('centro_trabalho_ausencia/sync-post', 'CentroTrabalhoAusenciaController@syncCentroTrabalhoAusencia');
	Route::get('centro_trabalho_ausencia/sync-get', 'CentroTrabalhoAusenciaController@getSyncCentroTrabalhoAusenciaUntilDate');
	Route::post('centro_trabalho_estagio/sync-post', 'CentroTrabalhoEstagioController@syncCentroTrabalhoEstagio');
	Route::get('centro_trabalho_estagio/sync-get', 'CentroTrabalhoEstagioController@getSyncCentroTrabalhoEstagioUntilDate');
	Route::post('centro_trabalho_planocontas/sync-post', 'CentroTrabalhoPlanoContasController@syncCentroTrabalhoPlanoContas');
	Route::get('centro_trabalho_planocontas/sync-get', 'CentroTrabalhoPlanoContasController@getSyncCentroTrabalhoPlanoContasUntilDate');
	Route::post('centro_trabalho_recurso/sync-post', 'CentroTrabalhoRecursoController@syncCentroTrabalhoRecurso');
	Route::get('centro_trabalho_recurso/sync-get', 'CentroTrabalhoRecursoController@getSyncCentroTrabalhoRecursoUntilDate');
	Route::post('local/sync-post', 'LocalController@syncLocal');
	Route::get('local/sync-get', 'LocalController@getSyncLocalUntilDate');
	Route::post('projeto/sync-post', 'ProjetoController@syncProjeto');
	Route::get('projeto/sync-get', 'ProjetoController@getSyncProjetoUntilDate');
	Route::post('recurso/sync-post', 'RecursoController@syncRecurso');
	Route::get('recurso/sync-get', 'RecursoController@getSyncRecursoUntilDate');
	Route::post('recurso_ausencia/sync-post', 'RecursoAusenciaController@syncRecursoAusencia');
	Route::get('recurso_ausencia/sync-get', 'RecursoAusenciaController@getSyncRecursoAusenciaUntilDate');


	//Estoque
	Route::get('product-stock-report', 'CommonResourceController@getProductStock');
	Route::post('balanco/sync-post', 'BalancoController@syncBalanco');
	Route::get('balanco/sync-get', 'BalancoController@getSyncBalancoUntilDate');
	Route::post('balanco_patr_classificacao/sync-post', 'BalancoPatrClassificacaoController@syncBalancoPatrClassificacao');
	Route::get('balanco_patr_classificacao/sync-get', 'BalancoPatrClassificacaoController@getSyncBalancoPatrClassificacaoUntilDate');
	Route::post('balanco_produto/sync-post', 'BalancoProdutoController@syncBalancoProduto');
	Route::get('balanco_produto/sync-get', 'BalancoProdutoController@getSyncBalancoProdutoUntilDate');
	Route::post('balanco_produtos/sync-post', 'BalancoProdutosController@syncBalancoProdutos');
	Route::get('balanco_produtos/sync-get', 'BalancoProdutosController@getSyncBalancoProdutosUntilDate');
	Route::post('balanco_titulo/sync-post', 'BalancoTituloController@syncBalancoTitulo');
	Route::get('balanco_titulo/sync-get', 'BalancoTituloController@getSyncBalancoTituloUntilDate');
	Route::post('produto_estoque/sync-post', 'ProdutoEstoqueController@syncProdutoEstoque');
	Route::get('produto_estoque/sync-get', 'ProdutoEstoqueController@getSyncProdutoEstoqueUntilDate');
	Route::post('produto_estoque_local/sync-post', 'ProdutoEstoqueLocalController@syncProdutoEstoqueLocal');
	Route::get('produto_estoque_local/sync-get', 'ProdutoEstoqueLocalController@getSyncProdutoEstoqueLocalUntilDate');
	Route::post('produto_estoque_reserva/sync-post', 'ProdutoEstoqueReservaController@syncProdutoEstoqueReserva');
	Route::get('produto_estoque_reserva/sync-get', 'ProdutoEstoqueReservaController@getSyncProdutoEstoqueReservaUntilDate');
	Route::post('produto_movimento/sync-post', 'ProdutoMovimentoController@syncProdutoMovimento');
	Route::get('produto_movimento/sync-get', 'ProdutoMovimentoController@getSyncProdutoMovimentoUntilDate');
	Route::post('produto_requisicao/sync-post', 'ProdutoRequisicaoController@syncProdutoRequisicao');
	Route::get('produto_requisicao/sync-get', 'ProdutoRequisicaoController@getSyncProdutoRequisicaoUntilDate');
	Route::post('produto_requisicao_motivo/sync-post', 'ProdutoRequisicaoMotivoController@syncProdutoRequisicaoMotivo');
	Route::get('produto_requisicao_motivo/sync-get', 'ProdutoRequisicaoMotivoController@getSyncProdutoRequisicaoMotivoUntilDate');
	Route::post('lote/sync-post', 'LoteController@syncLote');
	Route::get('lote/sync-get', 'LoteController@getSyncLoteUntilDate');
	Route::post('produto_estoque_lote/sync-post', 'ProdutoEstoqueLoteController@syncProdutoEstoqueLote');
	Route::get('produto_estoque_lote/sync-get', 'ProdutoEstoqueLoteController@getSyncProdutoEstoqueLoteUntilDate');
	Route::post('produto_lote/sync-post', 'ProdutoLoteController@syncProdutoLote');
	Route::get('produto_lote/sync-get', 'ProdutoLoteController@getSyncProdutoLoteUntilDate');
	Route::post('produto_serial/sync-post', 'ProdutoSerialController@syncProdutoSerial');
	Route::get('produto_serial/sync-get', 'ProdutoSerialController@getSyncProdutoSerialUntilDate');


	// Equipamentos da Empresa
	Route::post('equipamento/sync-post', 'EquipamentoController@syncEquipamento');
	Route::get('equipamento/sync-get', 'EquipamentoController@getSyncEquipamentoUntilDate');
	Route::post('equipamento_computador/sync-post', 'EquipamentoComputadorController@syncEquipamentoComputador');
	Route::get('equipamento_computador/sync-get', 'EquipamentoComputadorController@getSyncEquipamentoComputadorUntilDate');
	Route::post('equipamento_eletrodomestico/sync-post', 'EquipamentoEletrodomesticoController@syncEquipamentoEletrodomestico');
	Route::get('equipamento_eletrodomestico/sync-get', 'EquipamentoEletrodomesticoController@getSyncEquipamentoEletrodomesticoUntilDate');
	Route::post('equipamento_impressora/sync-post', 'EquipamentoImpressoraController@syncEquipamentoImpressora');
	Route::get('equipamento_impressora/sync-get', 'EquipamentoImpressoraController@getSyncEquipamentoImpressoraUntilDate');


	// Fiscal
	Route::resource('tax', 'TaxController', ['only' => ['index', 'show']]);
	Route::post('nf_cest/sync-post', 'NfCestController@syncNfCest');
	Route::get('nf_cest/sync-get', 'NfCestController@getSyncNfCestUntilDate');
	Route::post('nf_cfop/sync-post', 'NfCfopController@syncNfCfop');
	Route::get('nf_cfop/sync-get', 'NfCfopController@getSyncNfCfopUntilDate');
	Route::post('nf_cnae/sync-post', 'NfCnaeController@syncNfCnae');
	Route::get('nf_cnae/sync-get', 'NfCnaeController@getSyncNfCnaeUntilDate');
	Route::post('nf_cst/sync-post', 'NfCstController@syncNfCst');
	Route::get('nf_cst/sync-get', 'NfCstController@getSyncNfCstUntilDate');
	Route::post('nf_dadosadicionais/sync-post', 'NfDadosAdicionaisController@syncNfDadosAdicionais');
	Route::get('nf_dadosadicionais/sync-get', 'NfDadosAdicionaisController@getSyncNfDadosAdicionaisUntilDate');
	Route::post('nf_icms/sync-post', 'NfIcmsController@syncNfIcms');
	Route::get('nf_icms/sync-get', 'NfIcmsController@getSyncNfIcmsUntilDate');
	Route::post('nf_natureza_operacao/sync-post', 'NfNaturezaOperacaoController@sync');
	Route::get('nf_natureza_operacao/sync-get', 'NfNaturezaOperacaoController@getUntilDate');
	Route::post('nf_natureza_operacao_prodgrupo/sync-post', 'NfNaturezaOperacaoProdGrupoController@syncNfNaturezaOperacaoProdGrupo');
	Route::get('nf_natureza_operacao_prodgrupo/sync-get', 'NfNaturezaOperacaoProdGrupoController@getSyncNfNaturezaOperacaoProdGrupoUntilDate');
	Route::post('nf_ncm/sync-post', 'NfNcmController@syncNfNcm');
	Route::get('nf_ncm/sync-get', 'NfNcmController@getSyncNfNcmUntilDate');
	Route::post('nf_provedor/sync-post', 'NfProvedorController@syncNfProvedor');
	Route::get('nf_provedor/sync-get', 'NfProvedorController@getSyncNfProvedorUntilDate');
	Route::post('nf_regime_especial_tributacao/sync-post', 'NfRegimeEspecialTributacaoController@syncNfRegimeEspecialTributacao');
	Route::get('nf_regime_especial_tributacao/sync-get', 'NfRegimeEspecialTributacaoController@getSyncNfRegimeEspecialTributacaoUntilDate');
	Route::post('nota_fiscal/sync-post', 'NotaFiscalController@syncNotaFiscal');
	Route::get('nota_fiscal/sync-get', 'NotaFiscalController@getSyncNotaFiscalUntilDate');
	Route::post('nota_fiscal_entrada/sync-post', 'NotaFiscalEntradaController@syncNotaFiscalEntrada');
	Route::get('nota_fiscal_entrada/sync-get', 'NotaFiscalEntradaController@getSyncNotaFiscalEntradaUntilDate');
	Route::post('nota_fiscal_eventos/sync-post', 'NotaFiscalEventosController@syncNotaFiscalEventos');
	Route::get('nota_fiscal_eventos/sync-get', 'NotaFiscalEventosController@getSyncNotaFiscalEventosUntilDate');
	Route::post('nota_fiscal_produto/sync-post', 'NotaFiscalProdutoController@syncNotaFiscalProduto');
	Route::get('nota_fiscal_produto/sync-get', 'NotaFiscalProdutoController@getSyncNotaFiscalProdutoUntilDate');
	

	Route::post('req_manifesto/sync-post', 'ReqManifestoController@syncReqManifesto');
	Route::get('req_manifesto/sync-get', 'ReqManifestoController@getSyncReqManifestoUntilDate');
	Route::post('empresa_xml_autoriza/sync-post', 'EmpresaXmlAutorizaController@syncEmpresaXmlAutoriza');
	Route::get('empresa_xml_autoriza/sync-get', 'EmpresaXmlAutorizaController@getSyncEmpresaXmlAutorizaUntilDate');
	Route::post('nf_erros/sync-post', 'NfErrosController@syncNfErros');
	Route::get('nf_erros/sync-get', 'NfErrosController@getSyncNfErrosUntilDate');
	

	//RH
	Route::post('clock-in', 'AttendanceController@clockin');
	Route::post('clock-out', 'AttendanceController@clockout');
	Route::get('holidays', 'AttendanceController@getHolidays');
	Route::post('folha_pagamento/sync-post', 'FolhaPagamentoController@syncFolhaPagamento');
	Route::get('folha_pagamento/sync-get', 'FolhaPagamentoController@getSyncFolhaPagamentoUntilDate');
	Route::post('folha_pagamento_financeiro/sync-post', 'FolhaPagamentoFinanceiroController@syncFolhaPagamentoFinanceiro');
	Route::get('folha_pagamento_financeiro/sync-get', 'FolhaPagamentoFinanceiroController@getSyncFolhaPagamentoFinanceiroUntilDate');
	Route::post('folha_pagamento_grupo/sync-post', 'FolhaPagamentoGrupoController@syncFolhaPagamentoGrupo');
	Route::get('folha_pagamento_grupo/sync-get', 'FolhaPagamentoGrupoController@getSyncFolhaPagamentoGrupoUntilDate');
	Route::post('folha_pagamento_salario/sync-post', 'FolhaPagamentoSalarioController@syncFolhaPagamentoSalario');
	Route::get('folha_pagamento_salario/sync-get', 'FolhaPagamentoSalarioController@getSyncFolhaPagamentoSalarioUntilDate');
	

	Route::post('funcionario_anotacoes/sync-post', 'FuncionarioAnotacoesController@syncFuncionarioAnotacoes');
	Route::get('funcionario_anotacoes/sync-get', 'FuncionarioAnotacoesController@getSyncFuncionarioAnotacoesUntilDate');
	Route::post('funcionario_beneficiario/sync-post', 'FuncionarioBeneficiarioController@syncFuncionarioBeneficiario');
	Route::get('funcionario_beneficiario/sync-get', 'FuncionarioBeneficiarioController@getSyncFuncionarioBeneficiarioUntilDate');
	Route::post('funcionario_demissao/sync-post', 'FuncionarioDemissaoController@syncFuncionarioDemissao');
	Route::get('funcionario_demissao/sync-get', 'FuncionarioDemissaoController@getSyncFuncionarioDemissaoUntilDate');
	Route::post('funcionario_ferias/sync-post', 'FuncionarioFeriasController@syncFuncionarioFerias');
	Route::get('funcionario_ferias/sync-get', 'FuncionarioFeriasController@getSyncFuncionarioFeriasUntilDate');
	Route::post('funcionario_funcao/sync-post', 'FuncionarioFuncaoController@syncFuncionarioFuncao');
	Route::get('funcionario_funcao/sync-get', 'FuncionarioFuncaoController@getSyncFuncionarioFuncaoUntilDate');
	Route::post('funcionario_horario/sync-post', 'FuncionarioHorarioController@syncFuncionarioHorario');
	Route::get('funcionario_horario/sync-get', 'FuncionarioHorarioController@getSyncFuncionarioHorarioUntilDate');
	Route::post('funcionario_pensao/sync-post', 'FuncionarioPensaoController@syncFuncionarioPensao');
	Route::get('funcionario_pensao/sync-get', 'FuncionarioPensaoController@getSyncFuncionarioPensaoUntilDate');
	Route::post('funcionario_ponto/sync-post', 'FuncionarioPontoController@syncFuncionarioPonto');
	Route::get('funcionario_ponto/sync-get', 'FuncionarioPontoController@getSyncFuncionarioPontoUntilDate');
	Route::post('funcionario_ponto_arquivo/sync-post', 'FuncionarioPontoArquivoController@syncFuncionarioPontoArquivo');
	Route::get('funcionario_ponto_arquivo/sync-get', 'FuncionarioPontoArquivoController@getSyncFuncionarioPontoArquivoUntilDate');
	Route::post('funcionario_salario/sync-post', 'FuncionarioSalarioController@syncFuncionarioSalario');
	Route::get('funcionario_salario/sync-get', 'FuncionarioSalarioController@getSyncFuncionarioSalarioUntilDate');
	
	Route::post('planilha_tempo/sync-post', 'PlanilhaTempoController@syncPlanilhaTempo');
	Route::get('planilha_tempo/sync-get', 'PlanilhaTempoController@getSyncPlanilhaTempoUntilDate');
	Route::post('tempo_trabalho/sync-post', 'TempoTrabalhoController@syncTempoTrabalho');
	Route::get('tempo_trabalho/sync-get', 'TempoTrabalhoController@getSyncTempoTrabalhoUntilDate');
	Route::post('tempo_trabalho_horario/sync-post', 'TempoTrabalhoHorarioController@syncTempoTrabalhoHorario');
	Route::get('tempo_trabalho_horario/sync-get', 'TempoTrabalhoHorarioController@getSyncTempoTrabalhoHorarioUntilDate');
	
	Route::post('setor/sync-post', 'SetorController@syncSetor');
	Route::get('setor/sync-get', 'SetorController@getSyncSetorUntilDate');
	Route::post('setor_funcionario/sync-post', 'SetorFuncionarioController@syncSetorFuncionario');
	Route::get('setor_funcionario/sync-get', 'SetorFuncionarioController@getSyncSetorFuncionarioUntilDate');
	Route::post('setor_status/sync-post', 'SetorStatusController@syncSetorStatus');
	Route::get('setor_status/sync-get', 'SetorStatusController@getSyncSetorStatusUntilDate');
	


	//CRM
	Route::post('agenda/sync-post', 'AgendaController@syncAgenda');
	Route::get('agenda/sync-get', 'AgendaController@getSyncAgendaUntilDate');
	Route::post('agenda_bloqueio/sync-post', 'AgendaBloqueioController@syncAgendaBloqueio');
	Route::get('agenda_bloqueio/sync-get', 'AgendaBloqueioController@getSyncAgendaBloqueioUntilDate');
	Route::post('agenda_bloqueio_historico/sync-post', 'AgendaBloqueioHistoricoController@syncAgendaBloqueioHistorico');
	Route::get('agenda_bloqueio_historico/sync-get', 'AgendaBloqueioHistoricoController@getSyncAgendaBloqueioHistoricoUntilDate');
	Route::post('agenda_faq/sync-post', 'AgendaFaqController@syncAgendaFaq');
	Route::get('agenda_faq/sync-get', 'AgendaFaqController@getSyncAgendaFaqUntilDate');
	Route::post('agenda_filtro/sync-post', 'AgendaFiltroController@syncAgendaFiltro');
	Route::get('agenda_filtro/sync-get', 'AgendaFiltroController@getSyncAgendaFiltroUntilDate');
	Route::post('agenda_historico/sync-post', 'AgendaHistoricoController@syncAgendaHistorico');
	Route::get('agenda_historico/sync-get', 'AgendaHistoricoController@getSyncAgendaHistoricoUntilDate');
	Route::post('agenda_lido/sync-post', 'AgendaLidoController@syncAgendaLido');
	Route::get('agenda_lido/sync-get', 'AgendaLidoController@getSyncAgendaLidoUntilDate');
	Route::post('agenda_mensagem/sync-post', 'AgendaMensagemController@syncAgendaMensagem');
	Route::get('agenda_mensagem/sync-get', 'AgendaMensagemController@getSyncAgendaMensagemUntilDate');
	Route::post('agenda_seguidor/sync-post', 'AgendaSeguidorController@syncAgendaSeguidor');
	Route::get('agenda_seguidor/sync-get', 'AgendaSeguidorController@getSyncAgendaSeguidorUntilDate');
	Route::post('agenda_tarefas/sync-post', 'AgendaTarefasController@syncAgendaTarefas');
	Route::get('agenda_tarefas/sync-get', 'AgendaTarefasController@getSyncAgendaTarefasUntilDate');
	Route::post('agenda_titulo/sync-post', 'AgendaTituloController@syncAgendaTitulo');
	Route::get('agenda_titulo/sync-get', 'AgendaTituloController@getSyncAgendaTituloUntilDate');
	Route::post('agenda_titulo_workflow/sync-post', 'AgendaTituloWorkflowController@syncAgendaTituloWorkflow');
	Route::get('agenda_titulo_workflow/sync-get', 'AgendaTituloWorkflowController@getSyncAgendaTituloWorkflowUntilDate');
	

	Route::post('email/sync-post', 'EmailController@syncEmail');
	Route::get('email/sync-get', 'EmailController@getSyncEmailUntilDate');
	Route::post('email_anexo/sync-post', 'EmailAnexoController@syncEmailAnexo');
	Route::get('email_anexo/sync-get', 'EmailAnexoController@getSyncEmailAnexoUntilDate');
	Route::post('email_caixa/sync-post', 'EmailCaixaController@syncEmailCaixa');
	Route::get('email_caixa/sync-get', 'EmailCaixaController@getSyncEmailCaixaUntilDate');
	Route::post('email_conta/sync-post', 'EmailContaController@syncEmailConta');
	Route::get('email_conta/sync-get', 'EmailContaController@getSyncEmailContaUntilDate');
	Route::post('email_log/sync-post', 'EmailLogController@syncEmailLog');
	Route::get('email_log/sync-get', 'EmailLogController@getSyncEmailLogUntilDate');
	Route::post('email_massa/sync-post', 'EmailMassaController@syncEmailMassa');
	Route::get('email_massa/sync-get', 'EmailMassaController@getSyncEmailMassaUntilDate');
	Route::post('email_massa_mensagem/sync-post', 'EmailMassaMensagemController@syncEmailMassaMensagem');
	Route::get('email_massa_mensagem/sync-get', 'EmailMassaMensagemController@getSyncEmailMassaMensagemUntilDate');
	Route::post('email_massa_mensagem_anexo/sync-post', 'EmailMassaMensagemAnexoController@syncEmailMassaMensagemAnexo');
	Route::get('email_massa_mensagem_anexo/sync-get', 'EmailMassaMensagemAnexoController@getSyncEmailMassaMensagemAnexoUntilDate');
	Route::post('email_modelo/sync-post', 'EmailModeloController@syncEmailModelo');
	Route::get('email_modelo/sync-get', 'EmailModeloController@getSyncEmailModeloUntilDate');
	Route::post('email_preconfig/sync-post', 'EmailPreconfigController@syncEmailPreconfig');
	Route::get('email_preconfig/sync-get', 'EmailPreconfigController@getSyncEmailPreconfigUntilDate');


	//Vendas
	Route::resource('sell', 'SellController', ['only' => ['index', 'store', 'show', 'update', 'destroy']]);
	Route::post('sell-return', 'SellController@addSellReturn');
	Route::get('list-sell-return', 'SellController@listSellReturn');	
	Route::get('selling-price-group', 'ProductController@getSellingPriceGroup');
	Route::post('update-shipping-status', 'SellController@updateSellShippingStatus');
	Route::post('meta/sync-post', 'MetaController@syncMeta');
	Route::get('meta/sync-get', 'MetaController@getSyncMetaUntilDate');
	Route::post('meta_detalhe/sync-post', 'MetaDetalheController@syncMetaDetalhe');
	Route::get('meta_detalhe/sync-get', 'MetaDetalheController@getSyncMetaDetalheUntilDate');
	
	Route::post('status/sync-post', 'StatusController@syncStatus');
	Route::get('status/sync-get', 'StatusController@getSyncStatusUntilDate');
	Route::post('venda/sync-post', 'VendaController@syncVenda');
	Route::get('venda/sync-get', 'VendaController@getSyncVendaUntilDate');
	Route::post('venda_audit/sync-post', 'VendaAuditController@syncVendaAudit');
	Route::get('venda_audit/sync-get', 'VendaAuditController@getSyncVendaAuditUntilDate');
	Route::post('venda_composicao/sync-post', 'VendaComposicaoController@syncVendaComposicao');
	Route::get('venda_composicao/sync-get', 'VendaComposicaoController@getSyncVendaComposicaoUntilDate');
	Route::post('venda_endereco_entrega/sync-post', 'VendaEnderecoEntregaController@syncVendaEnderecoEntrega');
	Route::get('venda_endereco_entrega/sync-get', 'VendaEnderecoEntregaController@getSyncVendaEnderecoEntregaUntilDate');
	Route::post('venda_estagio/sync-post', 'VendaEstagioController@syncVendaEstagio');
	Route::get('venda_estagio/sync-get', 'VendaEstagioController@getSyncVendaEstagioUntilDate');
	Route::post('venda_financeiro/sync-post', 'VendaFinanceiroController@syncVendaFinanceiro');
	Route::get('venda_financeiro/sync-get', 'VendaFinanceiroController@getSyncVendaFinanceiroUntilDate');
	Route::post('venda_financeiro_tef/sync-post', 'VendaFinanceiroTefController@syncVendaFinanceiroTef');
	Route::get('venda_financeiro_tef/sync-get', 'VendaFinanceiroTefController@getSyncVendaFinanceiroTefUntilDate');
	Route::post('venda_obra/sync-post', 'VendaObraController@syncVendaObra');
	Route::get('venda_obra/sync-get', 'VendaObraController@getSyncVendaObraUntilDate');
	Route::post('venda_produto/sync-post', 'VendaProdutoController@syncVendaProduto');
	Route::get('venda_produto/sync-get', 'VendaProdutoController@getSyncVendaProdutoUntilDate');
	Route::post('venda_produto_baixa_automatica/sync-post', 'VendaProdutoBaixaAutomaticaController@syncVendaProdutoBaixaAutomatica');
	Route::get('venda_produto_baixa_automatica/sync-get', 'VendaProdutoBaixaAutomaticaController@getSyncVendaProdutoBaixaAutomaticaUntilDate');
	Route::post('venda_produto_centro_trabalho/sync-post', 'VendaProdutoCentroTrabalhoController@syncVendaProdutoCentroTrabalho');
	Route::get('venda_produto_centro_trabalho/sync-get', 'VendaProdutoCentroTrabalhoController@getSyncVendaProdutoCentroTrabalhoUntilDate');
	Route::post('venda_produto_custo_adicional/sync-post', 'VendaProdutoCustoAdicionalController@syncVendaProdutoCustoAdicional');
	Route::get('venda_produto_custo_adicional/sync-get', 'VendaProdutoCustoAdicionalController@getSyncVendaProdutoCustoAdicionalUntilDate');
	Route::post('venda_produto_etapa/sync-post', 'VendaProdutoEtapaController@syncVendaProdutoEtapa');
	Route::get('venda_produto_etapa/sync-get', 'VendaProdutoEtapaController@getSyncVendaProdutoEtapaUntilDate');
	Route::post('venda_produto_fornecedor/sync-post', 'VendaProdutoFornecedorController@syncVendaProdutoFornecedor');
	Route::get('venda_produto_fornecedor/sync-get', 'VendaProdutoFornecedorController@getSyncVendaProdutoFornecedorUntilDate');
	Route::post('venda_produto_prerequisito/sync-post', 'VendaProdutoPrerequisitoController@syncVendaProdutoPrerequisito');
	Route::get('venda_produto_prerequisito/sync-get', 'VendaProdutoPrerequisitoController@getSyncVendaProdutoPrerequisitoUntilDate');
	Route::post('venda_situacao/sync-post', 'VendaSituacaoController@syncVendaSituacao');
	Route::get('venda_situacao/sync-get', 'VendaSituacaoController@getSyncVendaSituacaoUntilDate');
	Route::post('venda_tipo/sync-post', 'VendaTipoController@syncVendaTipo');
	Route::get('venda_tipo/sync-get', 'VendaTipoController@getSyncVendaTipoUntilDate');
	
	Route::post('ecf/sync-post', 'EcfController@syncEcf');
	Route::get('ecf/sync-get', 'EcfController@getSyncEcfUntilDate');
	Route::post('ecf_atualizacao/sync-post', 'EcfAtualizacaoController@syncEcfAtualizacao');
	Route::get('ecf_atualizacao/sync-get', 'EcfAtualizacaoController@getSyncEcfAtualizacaoUntilDate');
	Route::post('ecf_atualizacao_produtos/sync-post', 'EcfAtualizacaoProdutosController@syncEcfAtualizacaoProdutos');
	Route::get('ecf_atualizacao_produtos/sync-get', 'EcfAtualizacaoProdutosController@getSyncEcfAtualizacaoProdutosUntilDate');
	Route::post('ecf_forma_pagamento/sync-post', 'EcfFormaPagamentoController@syncEcfFormaPagamento');
	Route::get('ecf_forma_pagamento/sync-get', 'EcfFormaPagamentoController@getSyncEcfFormaPagamentoUntilDate');
	
	Route::post('anexo/sync-post', 'AnexoController@syncAnexo');
	Route::get('anexo/sync-get', 'AnexoController@getSyncAnexoUntilDate');
	Route::post('arquivos/sync-post', 'ArquivosController@syncArquivos');
	Route::get('arquivos/sync-get', 'ArquivosController@getSyncArquivosUntilDate');
	
	Route::post('arquivos_relatorio/sync-post', 'ArquivosRelatorioController@syncArquivosRelatorio');
	Route::get('arquivos_relatorio/sync-get', 'ArquivosRelatorioController@getSyncArquivosRelatorioUntilDate');
	
	Route::post('bi_acoes/sync-post', 'BiAcoesController@syncBiAcoes');
	Route::get('bi_acoes/sync-get', 'BiAcoesController@getSyncBiAcoesUntilDate');
	Route::post('bi_acoes_condicao/sync-post', 'BiAcoesCondicaoController@syncBiAcoesCondicao');
	Route::get('bi_acoes_condicao/sync-get', 'BiAcoesCondicaoController@getSyncBiAcoesCondicaoUntilDate');
	Route::post('bi_acoes_execucao/sync-post', 'BiAcoesExecucaoController@syncBiAcoesExecucao');
	Route::get('bi_acoes_execucao/sync-get', 'BiAcoesExecucaoController@getSyncBiAcoesExecucaoUntilDate');
	Route::post('bi_kpi/sync-post', 'BiKpiController@syncBiKpi');
	Route::get('bi_kpi/sync-get', 'BiKpiController@getSyncBiKpiUntilDate');
	
	Route::post('kpi/sync-post', 'KpiController@syncKpi');
	Route::get('kpi/sync-get', 'KpiController@getSyncKpiUntilDate');
	Route::post('kpi_ano/sync-post', 'KpiAnoController@syncKpiAno');
	Route::get('kpi_ano/sync-get', 'KpiAnoController@getSyncKpiAnoUntilDate');
	Route::post('kpi_dia/sync-post', 'KpiDiaController@syncKpiDia');
	Route::get('kpi_dia/sync-get', 'KpiDiaController@getSyncKpiDiaUntilDate');
	Route::post('kpi_menu/sync-post', 'KpiMenuController@syncKpiMenu');
	Route::get('kpi_menu/sync-get', 'KpiMenuController@getSyncKpiMenuUntilDate');
	Route::post('kpi_mes/sync-post', 'KpiMesController@syncKpiMes');
	Route::get('kpi_mes/sync-get', 'KpiMesController@getSyncKpiMesUntilDate');
	

	// Associação
	Route::post('antifurto_tipo/sync-post', 'AntifurtoTipoController@syncAntifurtoTipo');
	Route::get('antifurto_tipo/sync-get', 'AntifurtoTipoController@getSyncAntifurtoTipoUntilDate');
	Route::post('equipamento_antifurto_tipo/sync-post', 'EquipamentoAntifurtoTipoController@syncEquipamentoAntifurtoTipo');
	Route::get('equipamento_antifurto_tipo/sync-get', 'EquipamentoAntifurtoTipoController@getSyncEquipamentoAntifurtoTipoUntilDate');
	Route::post('equipamento_rateio/sync-post', 'EquipamentoRateioController@syncEquipamentoRateio');
	Route::get('equipamento_rateio/sync-get', 'EquipamentoRateioController@getSyncEquipamentoRateioUntilDate');
	Route::post('equipamento_rateio_financeiro/sync-post', 'EquipamentoRateioFinanceiroController@syncEquipamentoRateioFinanceiro');
	Route::get('equipamento_rateio_financeiro/sync-get', 'EquipamentoRateioFinanceiroController@getSyncEquipamentoRateioFinanceiroUntilDate');
	Route::post('equipamento_semireboque/sync-post', 'EquipamentoSemireboqueController@syncEquipamentoSemireboque');
	Route::get('equipamento_semireboque/sync-get', 'EquipamentoSemireboqueController@getSyncEquipamentoSemireboqueUntilDate');
	Route::post('equipamento_veiculo/sync-post', 'EquipamentoVeiculoController@syncEquipamentoVeiculo');
	Route::get('equipamento_veiculo/sync-get', 'EquipamentoVeiculoController@getSyncEquipamentoVeiculoUntilDate');
	Route::post('rateio_antifurto_planocontas/sync-post', 'RateioAntifurtoPlanoContasController@syncRateioAntifurtoPlanoContas');
	Route::get('rateio_antifurto_planocontas/sync-get', 'RateioAntifurtoPlanoContasController@getSyncRateioAntifurtoPlanoContasUntilDate');
	Route::post('rateio_financeiro/sync-post', 'RateioFinanceiroController@syncRateioFinanceiro');
	Route::get('rateio_financeiro/sync-get', 'RateioFinanceiroController@getSyncRateioFinanceiroUntilDate');
	Route::post('tabfipe_anomodelo/sync-post', 'TabFipeAnoModeloController@syncTabFipeAnoModelo');
	Route::get('tabfipe_anomodelo/sync-get', 'TabFipeAnoModeloController@getSyncTabFipeAnoModeloUntilDate');
	Route::post('tabfipe_marca/sync-post', 'TabFipeMarcaController@syncTabFipeMarca');
	Route::get('tabfipe_marca/sync-get', 'TabFipeMarcaController@getSyncTabFipeMarcaUntilDate');
	Route::post('tabfipe_veiculo/sync-post', 'TabFipeVeiculoController@syncTabFipeVeiculo');
	Route::get('tabfipe_veiculo/sync-get', 'TabFipeVeiculoController@getSyncTabFipeVeiculoUntilDate');

	// Venda Película
	Route::post('carro/sync-post', 'CarroController@syncCarro');
	Route::get('carro/sync-get', 'CarroController@getSyncCarroUntilDate');
	Route::post('carrointeiro/sync-post', 'CarroInteiroController@syncCarroInteiro');
	Route::get('carrointeiro/sync-get', 'CarroInteiroController@getSyncCarroInteiroUntilDate');
	Route::post('carrointeirotemp/sync-post', 'CarroInteiroTempController@syncCarroInteiroTemp');
	Route::get('carrointeirotemp/sync-get', 'CarroInteiroTempController@getSyncCarroInteiroTempUntilDate');
	Route::post('carrointeirovalor/sync-post', 'CarroInteiroValorController@syncCarroInteiroValor');
	Route::get('carrointeirovalor/sync-get', 'CarroInteiroValorController@getSyncCarroInteiroValorUntilDate');
	Route::post('carrotemp/sync-post', 'CarroTempController@syncCarroTemp');
	Route::get('carrotemp/sync-get', 'CarroTempController@getSyncCarroTempUntilDate');
	Route::post('tipofilme/sync-post', 'TipoFilmeController@syncTipoFilme');
	Route::get('tipofilme/sync-get', 'TipoFilmeController@getSyncTipoFilmeUntilDate');

	// Módulos do sistema
	Route::post('app/sync-post', 'AppController@syncApp');
	Route::get('app/sync-get', 'AppController@getSyncAppUntilDate');


	// Configurações
	Route::post('configuracao_acoes/sync-post', 'ConfiguracaoAcoesController@syncConfiguracaoAcoes');
	Route::get('configuracao_acoes/sync-get', 'ConfiguracaoAcoesController@getSyncConfiguracaoAcoesUntilDate');
	Route::post('configuracao_acoes_regra/sync-post', 'ConfiguracaoAcoesRegraController@syncConfiguracaoAcoesRegra');
	Route::get('configuracao_acoes_regra/sync-get', 'ConfiguracaoAcoesRegraController@getSyncConfiguracaoAcoesRegraUntilDate');
	Route::post('configuracao_agrupamento/sync-post', 'ConfiguracaoAgrupamentoController@syncConfiguracaoAgrupamento');
	Route::get('configuracao_agrupamento/sync-get', 'ConfiguracaoAgrupamentoController@getSyncConfiguracaoAgrupamentoUntilDate');
	Route::post('configuracao_componente/sync-post', 'ConfiguracaoComponenteController@syncConfiguracaoComponente');
	Route::get('configuracao_componente/sync-get', 'ConfiguracaoComponenteController@getSyncConfiguracaoComponenteUntilDate');
	Route::post('configuracao_componente_css/sync-post', 'ConfiguracaoComponenteCssController@syncConfiguracaoComponenteCss');
	Route::get('configuracao_componente_css/sync-get', 'ConfiguracaoComponenteCssController@getSyncConfiguracaoComponenteCssUntilDate');
	Route::post('configuracao_cronjob/sync-post', 'ConfiguracaoCronjobController@syncConfiguracaoCronjob');
	Route::get('configuracao_cronjob/sync-get', 'ConfiguracaoCronjobController@getSyncConfiguracaoCronjobUntilDate');
	Route::post('configuracao_filtro/sync-post', 'ConfiguracaoFiltroController@syncConfiguracaoFiltro');
	Route::get('configuracao_filtro/sync-get', 'ConfiguracaoFiltroController@getSyncConfiguracaoFiltroUntilDate');
	Route::post('configuracao_form/sync-post', 'ConfiguracaoFormController@syncConfiguracaoForm');
	Route::get('configuracao_form/sync-get', 'ConfiguracaoFormController@getSyncConfiguracaoFormUntilDate');
	Route::post('configuracao_regra/sync-post', 'ConfiguracaoRegraController@syncConfiguracaoRegra');
	Route::get('configuracao_regra/sync-get', 'ConfiguracaoRegraController@getSyncConfiguracaoRegraUntilDate');
	Route::post('configuracoes/sync-post', 'ConfiguracoesController@syncConfiguracoes');
	Route::get('configuracoes/sync-get', 'ConfiguracoesController@getSyncConfiguracoesUntilDate');
	Route::post('configuracoes_grid/sync-post', 'ConfiguracoesGridController@syncConfiguracoesGrid');
	Route::get('configuracoes_grid/sync-get', 'ConfiguracoesGridController@getSyncConfiguracoesGridUntilDate');

	// Histórico
	Route::post('historico/sync-post', 'HistoricoController@syncHistorico');
	Route::get('historico/sync-get', 'HistoricoController@getSyncHistoricoUntilDate');
	Route::post('historico_adiciona_seguidor/sync-post', 'HistoricoAdicionaSeguidorController@syncHistoricoAdicionaSeguidor');
	Route::get('historico_adiciona_seguidor/sync-get', 'HistoricoAdicionaSeguidorController@getSyncHistoricoAdicionaSeguidorUntilDate');
	Route::post('historico_chamadas/sync-post', 'HistoricoChamadasController@syncHistoricoChamadas');
	Route::get('historico_chamadas/sync-get', 'HistoricoChamadasController@getSyncHistoricoChamadasUntilDate');
	Route::post('historico_editando/sync-post', 'HistoricoEditandoController@syncHistoricoEditando');
	Route::get('historico_editando/sync-get', 'HistoricoEditandoController@getSyncHistoricoEditandoUntilDate');
	Route::post('historico_impressoes/sync-post', 'HistoricoImpressoesController@syncHistoricoImpressoes');
	Route::get('historico_impressoes/sync-get', 'HistoricoImpressoesController@getSyncHistoricoImpressoesUntilDate');
	Route::post('historico_notificacao/sync-post', 'HistoricoNotificacaoController@syncHistoricoNotificacao');
	Route::get('historico_notificacao/sync-get', 'HistoricoNotificacaoController@getSyncHistoricoNotificacaoUntilDate');
	Route::post('historico_seguidor/sync-post', 'HistoricoSeguidorController@syncHistoricoSeguidor');
	Route::get('historico_seguidor/sync-get', 'HistoricoSeguidorController@getSyncHistoricoSeguidorUntilDate');
	Route::post('historico_sem_assunto/sync-post', 'HistoricoSemAssuntoController@syncHistoricoSemAssunto');
	Route::get('historico_sem_assunto/sync-get', 'HistoricoSemAssuntoController@getSyncHistoricoSemAssuntoUntilDate');
	Route::post('historico_sla/sync-post', 'HistoricoSlaController@syncHistoricoSla');
	Route::get('historico_sla/sync-get', 'HistoricoSlaController@getSyncHistoricoSlaUntilDate');

	// Layout
	Route::post('layout_form/sync-post', 'LayoutFormController@syncLayoutForm');
	Route::get('layout_form/sync-get', 'LayoutFormController@getSyncLayoutFormUntilDate');
	Route::post('layout_perfil/sync-post', 'LayoutPerfilController@syncLayoutPerfil');
	Route::get('layout_perfil/sync-get', 'LayoutPerfilController@getSyncLayoutPerfilUntilDate');

	// Log do sistema
	Route::post('log_sistema/sync-post', 'LogSistemaController@syncLogSistema');
	Route::get('log_sistema/sync-get', 'LogSistemaController@getSyncLogSistemaUntilDate');

	// Parâmetros
	Route::post('parametros/sync-post', 'ParametrosController@syncParametros');
	Route::get('parametros/sync-get', 'ParametrosController@getSyncParametrosUntilDate');
	Route::post('registro_atividade/sync-post', 'RegistroAtividadeController@syncRegistroAtividade');
	Route::get('registro_atividade/sync-get', 'RegistroAtividadeController@getSyncRegistroAtividadeUntilDate');

	// WR Módulos
	Route::post('wr_agrupador/sync-post', 'WrAgrupadorController@syncWrAgrupador');
	Route::get('wr_agrupador/sync-get', 'WrAgrupadorController@getSyncWrAgrupadorUntilDate');
	Route::post('wr_app/sync-post', 'WrAppController@syncWrApp');
	Route::get('wr_app/sync-get', 'WrAppController@getSyncWrAppUntilDate');
	Route::post('wr_componente/sync-post', 'WrComponenteController@syncWrComponente');
	Route::get('wr_componente/sync-get', 'WrComponenteController@getSyncWrComponenteUntilDate');
	Route::post('wr_condicao/sync-post', 'WrCondicaoController@syncWrCondicao');
	Route::get('wr_condicao/sync-get', 'WrCondicaoController@getSyncWrCondicaoUntilDate');
	Route::post('wr_config/sync-post', 'WrConfigController@syncWrConfig');
	Route::get('wr_config/sync-get', 'WrConfigController@getSyncWrConfigUntilDate');
	Route::post('wr_controle/sync-post', 'WrControleController@syncWrControle');
	Route::get('wr_controle/sync-get', 'WrControleController@getSyncWrControleUntilDate');
	Route::post('wr_controle_parent/sync-post', 'WrControleParentController@syncWrControleParent');
	Route::get('wr_controle_parent/sync-get', 'WrControleParentController@getSyncWrControleParentUntilDate');
	Route::post('wr_controle_wr_form/sync-post', 'WrControleWrFormController@syncWrControleWrForm');
	Route::get('wr_controle_wr_form/sync-get', 'WrControleWrFormController@getSyncWrControleWrFormUntilDate');
	Route::post('wr_filtro/sync-post', 'WrFiltroController@syncWrFiltro');
	Route::get('wr_filtro/sync-get', 'WrFiltroController@getSyncWrFiltroUntilDate');
	Route::post('wr_form/sync-post', 'WrFormController@syncWrForm');
	Route::get('wr_form/sync-get', 'WrFormController@getSyncWrFormUntilDate');
	Route::post('wr_kanban/sync-post', 'WrKanbanController@syncWrKanban');
	Route::get('wr_kanban/sync-get', 'WrKanbanController@getSyncWrKanbanUntilDate');
	Route::post('wr_modulo/sync-post', 'WrModuloController@syncWrModulo');
	Route::get('wr_modulo/sync-get', 'WrModuloController@getSyncWrModuloUntilDate');
	Route::post('wr_obrigatorio/sync-post', 'WrObrigatorioController@syncWrObrigatorio');
	Route::get('wr_obrigatorio/sync-get', 'WrObrigatorioController@getSyncWrObrigatorioUntilDate');
	Route::post('wr_valor_inicial/sync-post', 'WrValorInicialController@syncWrValorInicial');
	Route::get('wr_valor_inicial/sync-get', 'WrValorInicialController@getSyncWrValorInicialUntilDate');


    // Restourante mesas, não tenho isso ainda
	Route::resource('table', 'TableController', ['only' => ['index', 'show']]);

});
