# Medidas design × vivo — RESUMO (DERIVADO)

> Gerado por `scripts/design/design-diff-lote.mjs` em 2026-09-28T07:51:02.797Z. **Não edite à mão** — re-rode o comando.
> Base viva: `https://staging.oimpresso.com` · bundle: `1c3cc84c8139e41d67aa05c9cc45054062103a4d81091c5be48720c49a07ae5e` · telas: 89.
> Isto NÃO é gate. `IGUAL` só aparece quando o dono (`design-diff --check`) provou a proveniência do espelho; `MEDIDO · frescor não provado` = 0 bug contra uma cópia de frescor desconhecido.

| Tela | Fonte | Rota shell | Veredito | bugs | shell | D2 | D4 | D6 | D8 | D9 | SHELL | Frescor da âncora | Motivo |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin/Content/Index | cms-page.jsx | site | NÃO MEDI | — | — | — | — | — | — | — | — | — | charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não |
| Forja/Aprovacoes/Index | forja-aprova.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Forja/Roadmap/Gantt | forja-page.jsx | projects | IGUAL | 0 | 0 | ok | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Forja/Trabalho/Index | forja-page.jsx | projects | IGUAL | 0 | 0 | ok | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-25 |  |
| team-mcp/Forja/Cockpit | forja-page.jsx | projects | IGUAL | 0 | 0 | ok | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Atendimento/CaixaUnificada/Index | inbox-page.jsx | inbox | IGUAL | 0 | 0 | ok | sem-dado | ok | ok | ok | sem-dado | verificado 2026-09-25 |  |
| Atendimento/Channels/Index | inbox-page.jsx | inbox | NÃO MEDI | — | — | — | — | — | — | — | — | — | charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não |
| Atendimento/Macros/Index | inbox-page.jsx | inbox | NÃO MEDI | — | — | — | — | — | — | — | — | — | charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não |
| Cliente/Create | cliente-form.jsx | cli-novo | IGUAL | 0 | 0 | ok | sem-dado | ok | ok | ok | sem-dado | verificado 2026-09-25 |  |
| Cliente/Edit | cliente-form.jsx | cli-novo | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota parametrizada (/contacts/{id}/edit) — passe --tela X --url /rota/concreta |
| Cliente/Import | cliente-import.jsx | cli-import | DIVERGE (bug) | 2 | 0 | ok | 1 bug | 1 bug | ok | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Cliente/Index | clientes-page.jsx | clientes | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-25 |  |
| Cliente/Ledger | cliente-extrato.jsx | cli-extrato | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-25 |  |
| Cliente/Map | cliente-mapa.jsx | cli-mapa | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Compras/Index | compras-page.jsx | compras | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | sem-dado | sem-dado | verificado 2026-09-25 |  |
| ComunicacaoVisual/Index | comunicacao-visual-page.jsx | cv | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Documentacao/Index | documentacao-page.jsx | documentacao | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-25 |  |
| Essentials/Holidays/Index | hrm-page.jsx | hrm | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Essentials/Metas | hrm-extras.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Essentials/Painel | hrm-page.jsx | hrm | IGUAL | 0 | 0 | ok | sem-dado | ok | ok | ok | sem-dado | verificado 2026-09-25 |  |
| Essentials/Settings/Index | hrm-page.jsx | hrm | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Essentials/Tipos | hrm-page.jsx | hrm | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Financeiro/Cobranca/Index | pg-cobranca-page.jsx | cobranca | DIVERGE (bug) | 2 | 0 | 1 bug | sem-dado | ok | 1 bug | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Financeiro/Conciliacao/Index | financeiro-telas-extras.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Financeiro/Configuracoes/Contador | configuracoes-page.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | — | charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não; rota do shell: só prefixo cfg-* (window.ConfiguracoesPage) — declare o token no override |
| Financeiro/Dre/Index | financeiro-telas-extras.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Financeiro/Fluxo/Index | financeiro-telas-extras.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Financeiro/Impostos/Index | financeiro-telas-extras.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Financeiro/PlanoContas/Index | financeiro-telas-extras.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Financeiro/Unificado/Index | financeiro-page.jsx | financeiro | DIVERGE (bug) | 2 | 0 | 1 bug | ok | 2 div | 1 bug | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Fiscal/Cockpit | fiscal-actions.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Home/Index | dash-legacy-page.jsx | dash-legacy | IGUAL | 0 | 0 | ok | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Jana/Acoes | jana-telas-novas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Jana/Alertas | jana-telas-novas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Jana/Chat | jana-merge.jsx | chat | DIVERGE (bug) | 2 | 0 | 1 bug | sem-dado | ok | 1 bug | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Jana/Index | jana-merge.jsx | chat | DIVERGE (bug) | 2 | 0 | 1 bug | sem-dado | ok | 1 bug | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Jana/Memoria | jana-merge.jsx | chat | DIVERGE (bug) | 2 | 0 | 1 bug | sem-dado | ok | 1 bug | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Jana/Plataforma | jana-telas-novas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Jana/Pro | jana-pro.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | — | charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não; rota do shell: sem rota derivável |
| Manufacturing/Index | manufacturing-page.jsx | mfg-producao | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Manufacturing/Index | manufacturing-producao.jsx | mfg-producao | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Manufacturing/Insumos | manufacturing-insumos.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Manufacturing/Recipes | manufacturing-page.jsx | manufacturing | IGUAL | 0 | 0 | ok | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Manufacturing/Report | manufacturing-producao.jsx | mfg-relatorio | IGUAL | 0 | 0 | ok | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Manufacturing/Settings | manufacturing-producao.jsx | mfg-config | IGUAL | 0 | 0 | ok | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Modules/Index | Index.tsx | modulos | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | ⛔ NÃO MEDI — identidade da view não provada: um dos lados nao casou NENHUMA copy do contrato Nenhuma copy pinada do contrato apareceu no render, ou a sonda é anterior ao D0. Isto NÃO é divergência de fidelidade: é sinal  |
| OficinaAuto/AprovacaoPublica | oficina-forms.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | — | rota parametrizada (/aprovar-os/{token}) — passe --tela X --url /rota/concreta; charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não; rota do shell: sem rota derivável |
| OficinaAuto/ServiceOrders/Create | oficina-forms.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| OficinaAuto/ServiceOrders/Edit | oficina-forms.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota parametrizada (/oficina-auto/ordens-servico/{id}/edit) — passe --tela X --url /rota/concreta; rota do shell: sem rota derivável |
| OficinaAuto/Vehicles/Create | oficina-forms.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | — | charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não; rota do shell: sem rota derivável |
| OficinaAuto/Vehicles/Edit | oficina-forms.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | — | rota parametrizada (/oficina-auto/veiculos/{id}/edit) — passe --tela X --url /rota/concreta; charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não; rota do shell: sem rota derivável |
| Patrimonio/Alocacoes | patrimonio-page.jsx | assets | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | ⛔ NÃO MEDI — identidade da view não provada: assimetria estrutural: 9 x 2 copies — um lado so casou copy de shell Nenhuma copy pinada do contrato apareceu no render, ou a sonda é anterior ao D0. Isto NÃO é divergência de |
| Patrimonio/Bens | patrimonio-page.jsx | assets | DIVERGE (bug) | 2 | 0 | 1 bug | sem-dado | ok | 1 bug | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Patrimonio/Configuracoes | patrimonio-page.jsx | assets | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | ⛔ NÃO MEDI — identidade da view não provada: assimetria estrutural: 7 x 1 copies — um lado so casou copy de shell Nenhuma copy pinada do contrato apareceu no render, ou a sonda é anterior ao D0. Isto NÃO é divergência de |
| Patrimonio/Index | patrimonio-page.jsx | assets | DIVERGE (bug) | 1 | 0 | 1 bug | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Patrimonio/Manutencoes | patrimonio-page.jsx | assets | DIVERGE (bug) | 2 | 0 | 1 bug | sem-dado | ok | 1 bug | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Ponto/Aprovacoes/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Ponto/BancoHoras/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Ponto/BancoHoras/Show | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota parametrizada (/ponto/banco-horas/{colaborador}) — passe --tela X --url /rota/concreta; rota do shell: sem rota derivável |
| Ponto/Colaboradores/Edit | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota parametrizada (/ponto/colaboradores/{id}/editar) — passe --tela X --url /rota/concreta; rota do shell: sem rota derivável |
| Ponto/Colaboradores/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Ponto/Configuracoes/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Ponto/Configuracoes/Reps | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Ponto/Dashboard/Index | ponto-page.jsx | ponto | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Ponto/Escalas/Form | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Ponto/Escalas/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Ponto/Espelho/Index | ponto-page.jsx | ponto | DIVERGE (bug) | 6 | 0 | 2 div | 1 bug | 4 bug | 1 bug | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Ponto/Espelho/Show | ponto-page.jsx | ponto | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota parametrizada (/ponto/espelho/{colaborador}) — passe --tela X --url /rota/concreta |
| Ponto/Importacoes/Create | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Ponto/Importacoes/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Ponto/Importacoes/Show | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota parametrizada (/ponto/importacoes/{id}) — passe --tela X --url /rota/concreta; rota do shell: sem rota derivável |
| Ponto/Intercorrencias/Create | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Ponto/Intercorrencias/Edit | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota parametrizada (/ponto/intercorrencias/{id}/edit) — passe --tela X --url /rota/concreta; rota do shell: sem rota derivável |
| Ponto/Intercorrencias/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Ponto/Intercorrencias/Show | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota parametrizada (/ponto/intercorrencias/{id}) — passe --tela X --url /rota/concreta; rota do shell: sem rota derivável |
| Ponto/Relatorios/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Produto/Create | produto-blade-forms.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Produto/Edit | produto-blade-forms.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota parametrizada (/products/{id}/edit) — passe --tela X --url /rota/concreta; rota do shell: sem rota derivável |
| Produto/Index | produtos-page.jsx | produtos | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-25 |  |
| Produto/Unificado/Index | produtos-page.jsx | produtos | DIVERGE (bug) | 3 | 0 | 2 bug | ok | 2 div | 1 bug | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Purchase/Index | compras-page.jsx | compras | DIVERGE (bug) | 3 | 0 | 1 bug | 1 bug | ok | 1 bug | ok | sem-dado | verificado 2026-09-25 |  |
| Purchase/Show | compras-page.jsx | compras | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota parametrizada (/purchases/{id}) — passe --tela X --url /rota/concreta |
| RecurringBilling/Planos/Create | cobranca-recorrente-page.jsx | recurring | NÃO MEDI | — | — | — | — | — | — | — | — | — | charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não |
| Sells/Caixa/Index | vendas-extras.jsx | vendas | IGUAL | 0 | 0 | ok | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-25 |  |
| Sells/Create | vendas-create-page.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota do shell: sem rota derivável |
| Sells/CreateV3 | venda-v3.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | fora do espelho | rota do shell: sem rota derivável |
| Suporte/Visao | suporte-page.jsx | suporte | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-25 | rota parametrizada (/suporte/empresas/{business}) — passe --tela X --url /rota/concreta |
| User/Perfil | perfil-page.jsx | perfil | IGUAL | 0 | 0 | ok | sem-dado | ok | ok | ok | sem-dado | verificado 2026-09-25 |  |
| Vestuario/Etiquetas/Index | vestuario-page.jsx | vestuario | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | sem-dado | sem-dado | verificado 2026-09-25 |  |

## Contagem por veredito

- DIVERGE (bug): 26
- IGUAL: 12
- NÃO MEDI: 51
