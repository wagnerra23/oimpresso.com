---
sessao: "06"
titulo: "Estado das chaves MWART e FeatureFlag em produção — saída"
playbook: cutover-menu
thread: "06"
dono: "[CL]"
data: "2026-10-09"
base: "produção 463dc70bd543; leitura 2026-10-09T18:27:15Z"
---

# _saida-06 · O que o cliente vê hoje

Leitura via SSH e php artisan tinker no Hostinger. Foram lidos config('mwart') efetivo e FeatureFlagService::isOn com business_id, seguindo os atributos dos controllers. Não foram alterados env, banco, flags ou código. Não foram copiados SDK keys ou credenciais. Esta tabela foi um retrato da leitura, não afirmação permanente sobre produção.

## MWART (config efetiva)

Lista vazia com enabled=true significava todas as empresas; enabled=false significava desligada. A regra foi confirmada no ContactController. Todas as entradas de mwart retornadas pelo servidor foram registradas abaixo.

| Chave | Ligada na leitura? | business_id configurados / escopo |
|---|---|---|
| backup_index | não | nenhuma |
| repair_index | sim | 1 |
| repair_status_index | sim | 1 |
| repair_device_models_index | sim | 1 |
| repair_device_models_create | não | nenhuma |
| repair_device_models_edit | não | nenhuma |
| repair_dashboard_index | sim | 1 |
| repair_job_sheet_index | sim | 1 |
| repair_settings_index | não | nenhuma |
| repair_job_sheet_show | não | nenhuma |
| repair_job_sheet_edit | não | nenhuma |
| repair_job_sheet_create | não | nenhuma |
| repair_job_sheet_add_parts | não | nenhuma |
| repair_show | não | nenhuma |
| repair_show_fsm_panel | não | nenhuma |
| cliente_index | sim | todas |
| cliente_create | sim | todas |
| cliente_show | sim | todas |
| cliente_edit | sim | todas |
| cliente_import | sim | todas |
| cliente_ledger | sim | todas |
| cliente_map | sim | todas |
| vendas_pos_index | não | nenhuma |
| vendas_shipments_index | não | nenhuma |
| vendas_sell_return_index | não | nenhuma |
| vendas_discount_index | não | nenhuma |
| vendas_import_sales | não | nenhuma |
| vendas_sales_order_index | não | nenhuma |
| sistema_usuarios_index | não | nenhuma |

## FeatureFlagService (resultado efetivo)

Foram avaliadas as 93 empresas existentes, usando apenas seus ids; nenhum nome, e-mail ou outro dado de cliente foi copiado. A leitura respeitou cache/fallback do serviço, sem limpar cache. Resultado efetivo não comprova disponibilidade do GrowthBook nem mudança persistida no painel.

| Chave | Ligada na leitura? | Escopo avaliado |
|---|---|---|
| useV2SellsCreate | sim | todas as 93 empresas |
| useV2ConfiguracoesLocais | não | nenhuma das 93 empresas |
| useV2ConfiguracoesCodigoBarras | não | nenhuma das 93 empresas |
| useV2ConfiguracoesImpressoras | não | nenhuma das 93 empresas |
| useV2ConfiguracoesImpostos | não | nenhuma das 93 empresas |
| useV2ConfiguracoesTiposServico | não | nenhuma das 93 empresas |
| useV2ConfiguracoesEsquemasFatura | não | nenhuma das 93 empresas |
| useV2OfficeimpressoLogs | não | nenhuma das 93 empresas |

## Limites e sequência
- A entrega mediu configuração/avaliação das flags, não fez smoke visual de cada tela. Permissões, rota, query string e gates adicionais também podem decidir a resposta.
- Logs do Officeimpresso usam useV2OfficeimpressoLogs e ficaram desligados nessa leitura; já Cliente core ficou ligado em MWART.
- A decisão D-CFG-LIGAR da Sistema/13 mandou ligar as três telas de Configurações, mas as flags Locais, Impressoras e Código de barras foram medidas desligadas. A ativação com testes e smoke pertence àquela thread; não foi executada nesta auditoria.
- Não houve upload deste recibo ao Cowork/DesignSync nem marcador de enviado.
