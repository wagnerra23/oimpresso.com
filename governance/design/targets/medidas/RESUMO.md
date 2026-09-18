# Medidas design × vivo — RESUMO (DERIVADO)

> Gerado por `scripts/design/design-diff-lote.mjs` em 2026-09-18T12:45:18.980Z. **Não edite à mão** — re-rode o comando.
> Base viva: `https://staging.oimpresso.com` · bundle: `6788f6796a6da4d7bcf4153ba2c9fc03404c839e6b6a71896b8d3ca7e27196f3` · telas: 134.
> Isto NÃO é gate. `IGUAL` só aparece quando o dono (`design-diff --check`) provou a proveniência do espelho; `MEDIDO · frescor não provado` = 0 bug contra uma cópia de frescor desconhecido.

| Tela | Fonte | Rota shell | Veredito | bugs | shell | D2 | D4 | D6 | D8 | D9 | SHELL | Frescor da âncora | Motivo |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Forja/Aprovacoes/Index | forja-aprova.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Forja/Roadmap/Gantt | forja-page.jsx | projects | IGUAL | 0 | 0 | ok | ok | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Forja/Trabalho/Index | forja-page.jsx | projects | IGUAL | 0 | 0 | ok | ok | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| team-mcp/Forja/Cockpit | forja-page.jsx | projects | IGUAL | 0 | 0 | ok | ok | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Officeimpresso/Logs/Index | officeimpresso-page.jsx | officeimpresso | DIVERGE (bug) | 9 | 0 | 2 bug | 2 bug | 3 bug | 2 bug | ok | sem-dado | verificado 2026-09-17 |  |
| Officeimpresso/Logs/Timeline | officeimpresso-page.jsx | officeimpresso | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Settings/PaymentGateways/Index | pg-payment-gateways-page.jsx | payment-gateways | DIVERGE (bug) | 2 | 0 | 1 bug | sem-dado | 1 div | 1 bug | ok | sem-dado | verificado 2026-09-17 |  |
| superadmin/Assinaturas/Index | superadmin-page.jsx | superadmin | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| superadmin/Dashboard/Index | superadmin-page.jsx | superadmin | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| superadmin/Negocios/Index | superadmin-page.jsx | superadmin | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| superadmin/Pacotes/Index | superadmin-page.jsx | superadmin | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Atendimento/CaixaUnificada/Index | inbox-page.jsx | inbox | IGUAL | 0 | 0 | ok | sem-dado | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Atendimento/Channels/Index | inbox-page.jsx | inbox | NÃO MEDI | — | — | — | — | — | — | — | — | — | charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não |
| Atendimento/Macros/Index | inbox-page.jsx | inbox | NÃO MEDI | — | — | — | — | — | — | — | — | — | charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não |
| Arquivos/Index | arquivos-page.jsx | arquivos | DIVERGE (bug) | 5 | 0 | 4 div | 2 bug | 2 bug | 1 bug | ok | sem-dado | verificado 2026-09-17 |  |
| Backup/Index | backup-page.jsx | backup | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | ⛔ NÃO MEDI — identidade da view não provada: um dos lados nao casou NENHUMA copy do contrato Nenhuma copy pinada do contrato apareceu no render, ou a sonda é anterior ao D0. Isto NÃO é divergência de fidelidade: é sinal  |
| Cliente/Create | cliente-form.jsx | cli-novo | IGUAL | 0 | 0 | ok | sem-dado | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Cliente/Edit | cliente-form.jsx | cli-novo | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota parametrizada (/contacts/{id}/edit) — passe --tela X --url /rota/concreta |
| Cliente/Import | cliente-import.jsx | cli-import | DIVERGE (bug) | 2 | 0 | ok | 1 bug | 1 bug | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Cliente/Index | clientes-page.jsx | clientes | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Cliente/Ledger | cliente-extrato.jsx | cli-extrato | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Cliente/Map | cliente-mapa.jsx | cli-mapa | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Compras/Index | compras-page.jsx | compras | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| ComunicacaoVisual/Index | comunicacao-visual-page.jsx | cv | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Essentials/Documents/Index | essenciais-page.jsx | essenciais | IGUAL | 0 | 0 | ok | sem-dado | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Essentials/Holidays/Index | hrm-page.jsx | hrm | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Essentials/Knowledge/Index | essenciais-extras.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Essentials/Knowledge/Index | essenciais-page.jsx | essenciais | IGUAL | 0 | 0 | ok | sem-dado | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Essentials/Messages/Index | essenciais-page.jsx | essenciais | IGUAL | 0 | 0 | ok | sem-dado | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Essentials/Metas | hrm-extras.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Essentials/Reminders/Index | essenciais-page.jsx | essenciais | IGUAL | 0 | 0 | ok | sem-dado | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Essentials/Settings/Index | hrm-page.jsx | hrm | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Essentials/Tipos | hrm-page.jsx | hrm | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | ⛔ NÃO MEDI — identidade da view não provada: um dos lados nao casou NENHUMA copy do contrato Nenhuma copy pinada do contrato apareceu no render, ou a sonda é anterior ao D0. Isto NÃO é divergência de fidelidade: é sinal  |
| Essentials/Todo/Index | essenciais-page.jsx | essenciais | IGUAL | 0 | 0 | ok | sem-dado | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Financeiro/Cobranca/Index | pg-cobranca-page.jsx | cobranca | DIVERGE (bug) | 2 | 0 | 1 bug | sem-dado | ok | 1 bug | ok | sem-dado | verificado 2026-09-17 |  |
| Financeiro/Conciliacao/Index | financeiro-telas-extras.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Financeiro/Configuracoes/Contador | configuracoes-page.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | — | charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não; rota do shell: só prefixo cfg-* (window.ConfiguracoesPage) — declare o token no override |
| Financeiro/Dre/Index | financeiro-telas-extras.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Financeiro/Fluxo/Index | financeiro-telas-extras.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Financeiro/Impostos/Index | financeiro-telas-extras.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Financeiro/Unificado/Index | financeiro-page.jsx | financeiro | DIVERGE (bug) | 4 | 0 | 4 div | 3 bug | 1 div | 1 bug | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Fiscal/Cockpit | fiscal-actions.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Fiscal/Cockpit | fiscal-page.jsx | fiscal | DIVERGE (bug) | 4 | 0 | 1 div | 3 bug | 1 bug | 1 div | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Fiscal/Config | fiscal-page.jsx | fiscal-config | IGUAL | 0 | 0 | ok | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Fiscal/Config | fiscal-subpages.jsx | fiscal-config | IGUAL | 0 | 0 | ok | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Fiscal/Dfe | fiscal-page.jsx | fiscal-dfe | IGUAL | 0 | 0 | ok | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Fiscal/Dfe | fiscal-subpages.jsx | fiscal-dfe | IGUAL | 0 | 0 | ok | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Fiscal/Eventos | fiscal-page.jsx | fiscal-eventos | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | ⛔ NÃO MEDI — identidade da view não provada: um dos lados nao casou NENHUMA copy do contrato Nenhuma copy pinada do contrato apareceu no render, ou a sonda é anterior ao D0. Isto NÃO é divergência de fidelidade: é sinal  |
| Fiscal/Eventos | fiscal-subpages.jsx | fiscal-eventos | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | ⛔ NÃO MEDI — identidade da view não provada: um dos lados nao casou NENHUMA copy do contrato Nenhuma copy pinada do contrato apareceu no render, ou a sonda é anterior ao D0. Isto NÃO é divergência de fidelidade: é sinal  |
| Fiscal/Nfe | fiscal-page.jsx | fiscal-nfe | DIVERGE (bug) | 1 | 0 | ok | ok | 1 bug | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Fiscal/Nfse | fiscal-page.jsx | fiscal-nfse | DIVERGE (bug) | 11 | 0 | 6 div | 3 bug | 4 bug | 4 bug | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Fiscal/Sped | fiscal-page.jsx | fiscal-sped | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | ⛔ NÃO MEDI — identidade da view não provada: assimetria estrutural: 7 x 1 copies — um lado so casou copy de shell Nenhuma copy pinada do contrato apareceu no render, ou a sonda é anterior ao D0. Isto NÃO é divergência de |
| Fiscal/Sped | fiscal-subpages.jsx | fiscal-sped | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | ⛔ NÃO MEDI — identidade da view não provada: assimetria estrutural: 7 x 1 copies — um lado so casou copy de shell Nenhuma copy pinada do contrato apareceu no render, ou a sonda é anterior ao D0. Isto NÃO é divergência de |
| governance/Audit | governance-page.jsx | governance | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| governance/Dashboard | governance-page.jsx | governance | DIVERGE (bug) | 3 | 0 | 1 bug | 1 bug | ok | 1 bug | sem-dado | sem-dado | verificado 2026-09-17 |  |
| governance/DriftAlerts | governance-page.jsx | governance | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| governance/Policies | governance-page.jsx | governance | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Home/Index | dash-legacy-page.jsx | dash-legacy | IGUAL | 0 | 0 | ok | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Jana/Acoes | jana-telas-novas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Jana/Alertas | jana-telas-novas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Jana/Chat | jana-merge.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Jana/Index | jana-merge.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Jana/Memoria | jana-merge.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Jana/Plataforma | jana-telas-novas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Jana/Pro | jana-pro.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | — | charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não; rota do shell: sem rota derivável |
| kb/Index | kb-page.jsx | kb | DIVERGE (bug) | 3 | 0 | 1 bug | 2 bug | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| kb/Index.v2 | kb-page.jsx | kb | DIVERGE (bug) | 3 | 0 | 1 bug | 1 bug | ok | 1 bug | ok | sem-dado | verificado 2026-09-17 |  |
| Manufacturing/Index | manufacturing-page.jsx | manufacturing | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Manufacturing/Insumos | manufacturing-insumos.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Manufacturing/Recipes | manufacturing-page.jsx | manufacturing | IGUAL | 0 | 0 | ok | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Manufacturing/Report | manufacturing-producao.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Manufacturing/Settings | manufacturing-producao.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Modules/Index | Index.tsx | modulos | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | ⛔ NÃO MEDI — identidade da view não provada: um dos lados nao casou NENHUMA copy do contrato Nenhuma copy pinada do contrato apareceu no render, ou a sonda é anterior ao D0. Isto NÃO é divergência de fidelidade: é sinal  |
| Modules/Index | modulos-page.jsx | modulos | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | ⛔ NÃO MEDI — identidade da view não provada: um dos lados nao casou NENHUMA copy do contrato Nenhuma copy pinada do contrato apareceu no render, ou a sonda é anterior ao D0. Isto NÃO é divergência de fidelidade: é sinal  |
| OficinaAuto/AprovacaoPublica | oficina-forms.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | — | rota parametrizada (/aprovar-os/{token}) — passe --tela X --url /rota/concreta; charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não; rota do shell: sem rota derivável |
| OficinaAuto/ServiceOrders/Board | oficina-page.jsx | oficinaauto | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| OficinaAuto/ServiceOrders/Create | oficina-forms.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| OficinaAuto/ServiceOrders/Edit | oficina-forms.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota parametrizada (/oficina-auto/ordens-servico/{id}/edit) — passe --tela X --url /rota/concreta; rota do shell: sem rota derivável |
| OficinaAuto/ServiceOrders/Show | oficina-os-page.jsx | oficina-os | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota parametrizada (/oficina-auto/service-orders/{id}) — passe --tela X --url /rota/concreta |
| OficinaAuto/Vehicles/Create | oficina-forms.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | — | charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não; rota do shell: sem rota derivável |
| OficinaAuto/Vehicles/Edit | oficina-forms.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | — | rota parametrizada (/oficina-auto/veiculos/{id}/edit) — passe --tela X --url /rota/concreta; charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não; rota do shell: sem rota derivável |
| Patrimonio/Alocacoes | patrimonio-page.jsx | assets | DIVERGE (bug) | 3 | 0 | 1 bug | 1 bug | ok | 1 bug | ok | sem-dado | verificado 2026-09-17 |  |
| Patrimonio/Bens | patrimonio-page.jsx | assets | DIVERGE (bug) | 3 | 0 | 1 bug | 1 bug | ok | 1 bug | ok | sem-dado | verificado 2026-09-17 |  |
| Patrimonio/Configuracoes | patrimonio-page.jsx | assets | DIVERGE (bug) | 3 | 0 | 1 bug | 1 bug | ok | 1 bug | ok | sem-dado | verificado 2026-09-17 |  |
| Patrimonio/Index | patrimonio-page.jsx | assets | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | ⛔ NÃO MEDI — identidade da view não provada: contrato sem copy pinada Nenhuma copy pinada do contrato apareceu no render, ou a sonda é anterior ao D0. Isto NÃO é divergência de fidelidade: é sinal de que o render pode se |
| Patrimonio/Manutencoes | patrimonio-page.jsx | assets | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | ⛔ NÃO MEDI — identidade da view não provada: um dos lados nao casou NENHUMA copy do contrato Nenhuma copy pinada do contrato apareceu no render, ou a sonda é anterior ao D0. Isto NÃO é divergência de fidelidade: é sinal  |
| Ponto/Aprovacoes/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/BancoHoras/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/BancoHoras/Show | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota parametrizada (/ponto/banco-horas/{colaborador}) — passe --tela X --url /rota/concreta; rota do shell: sem rota derivável |
| Ponto/Colaboradores/Edit | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota parametrizada (/ponto/colaboradores/{id}/editar) — passe --tela X --url /rota/concreta; rota do shell: sem rota derivável |
| Ponto/Colaboradores/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/Configuracoes/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/Configuracoes/Reps | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/Dashboard/Index | ponto-page.jsx | ponto | DIVERGE (bug) | 3 | 0 | ok | 1 bug | ok | ok | 2 bug | sem-dado | verificado 2026-09-17 |  |
| Ponto/Escalas/Form | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/Escalas/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/Espelho/Index | ponto-page.jsx | ponto | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Ponto/Espelho/Show | ponto-page.jsx | ponto | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota parametrizada (/ponto/espelho/{colaborador}) — passe --tela X --url /rota/concreta |
| Ponto/Importacoes/Create | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/Importacoes/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/Importacoes/Show | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota parametrizada (/ponto/importacoes/{id}) — passe --tela X --url /rota/concreta; rota do shell: sem rota derivável |
| Ponto/Intercorrencias/Create | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/Intercorrencias/Edit | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota parametrizada (/ponto/intercorrencias/{id}/edit) — passe --tela X --url /rota/concreta; rota do shell: sem rota derivável |
| Ponto/Intercorrencias/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/Intercorrencias/Show | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota parametrizada (/ponto/intercorrencias/{id}) — passe --tela X --url /rota/concreta; rota do shell: sem rota derivável |
| Ponto/Relatorios/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Produto/Index | produtos-page.jsx | produtos | DIVERGE (bug) | 3 | 0 | 2 bug | 1 bug | 1 div | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Produto/Unificado/Index | produtos-page.jsx | produtos | DIVERGE (bug) | 3 | 0 | 2 bug | ok | 2 div | 1 bug | ok | sem-dado | verificado 2026-09-17 |  |
| Purchase/Create | compras-grade-matrix.jsx | cmp-grade | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | ⛔ NÃO MEDI — identidade da view não provada: um dos lados nao casou NENHUMA copy do contrato Nenhuma copy pinada do contrato apareceu no render, ou a sonda é anterior ao D0. Isto NÃO é divergência de fidelidade: é sinal  |
| Purchase/Index | compras-page.jsx | compras | DIVERGE (bug) | 5 | 0 | 2 bug | 1 bug | ok | 2 bug | ok | sem-dado | verificado 2026-09-17 |  |
| Purchase/Show | compras-page.jsx | compras | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota parametrizada (/purchases/{id}) — passe --tela X --url /rota/concreta |
| RecurringBilling/Configuracoes/Index | cobranca-recorrente-page.jsx | recurring | DIVERGE (bug) | 3 | 0 | 1 bug | 1 bug | ok | 1 bug | ok | sem-dado | verificado 2026-09-17 |  |
| RecurringBilling/Faturas/Index | cobranca-recorrente-page.jsx | recurring | DIVERGE (bug) | 3 | 0 | 1 bug | 1 bug | ok | 1 bug | ok | sem-dado | verificado 2026-09-17 |  |
| RecurringBilling/Index | cobranca-recorrente-page.jsx | recurring | DIVERGE (bug) | 3 | 0 | 1 bug | 1 bug | ok | 1 bug | ok | sem-dado | verificado 2026-09-17 |  |
| RecurringBilling/Planos/Create | cobranca-recorrente-page.jsx | recurring | NÃO MEDI | — | — | — | — | — | — | — | — | — | charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não |
| RecurringBilling/Planos/Index | cobranca-recorrente-page.jsx | recurring | DIVERGE (bug) | 3 | 0 | 1 bug | 1 bug | ok | 1 bug | ok | sem-dado | verificado 2026-09-17 |  |
| Repair/Dashboard/Index | repair-page.jsx | repair | DIVERGE (bug) | 3 | 0 | 1 bug | 1 bug | ok | 1 bug | ok | sem-dado | verificado 2026-09-17 |  |
| Repair/DeviceModels/Index | repair-page.jsx | repair | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Repair/Index | repair-page.jsx | repair | DIVERGE (bug) | 3 | 0 | 1 bug | 1 bug | ok | 1 bug | ok | sem-dado | verificado 2026-09-17 |  |
| Repair/JobSheet/Index | repair-page.jsx | repair | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Repair/ProducaoOficina/Index | repair-page.jsx | repair | IGUAL | 0 | 0 | ok | sem-dado | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Repair/Settings/Index | repair-page.jsx | repair | NÃO MEDI | — | — | — | — | — | — | — | — | — | charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não |
| Repair/Status/Index | repair-page.jsx | repair | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Sells/Caixa/Index | vendas-extras.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Sells/Create | vendas-create-page.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Sells/CreateV3 | sells-create.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | fora do espelho | rota do shell: sem rota derivável |
| Sells/Index | vendas-page.jsx | vendas | DIVERGE (bug) | 1 | 0 | 1 bug | ok | 1 div | 1 div | sem-dado | sem-dado | verificado 2026-09-17 |  |
| StockAdjustment/Create | estoque-page.jsx | estoque | DIVERGE (bug) | 3 | 0 | 1 bug | 1 bug | ok | 1 bug | ok | sem-dado | verificado 2026-09-17 |  |
| StockAdjustment/Index | estoque-page.jsx | estoque | DIVERGE (bug) | 3 | 0 | 1 bug | 1 bug | ok | 1 bug | ok | sem-dado | verificado 2026-09-17 |  |
| StockTransfer/Create | estoque-page.jsx | estoque | DIVERGE (bug) | 3 | 0 | 1 bug | 1 bug | ok | 1 bug | ok | sem-dado | verificado 2026-09-17 |  |
| StockTransfer/Index | estoque-page.jsx | estoque | DIVERGE (bug) | 3 | 0 | 1 bug | 1 bug | ok | 1 bug | ok | sem-dado | verificado 2026-09-17 |  |
| Suporte/Visao | suporte-page.jsx | suporte | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota parametrizada (/suporte/empresas/{business}) — passe --tela X --url /rota/concreta |
| User/Perfil | perfil-page.jsx | perfil | IGUAL | 0 | 0 | ok | sem-dado | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Vestuario/Etiquetas/Index | vestuario-page.jsx | vestuario | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |

## Contagem por veredito

- DIVERGE (bug): 52
- IGUAL: 18
- NÃO MEDI: 64
