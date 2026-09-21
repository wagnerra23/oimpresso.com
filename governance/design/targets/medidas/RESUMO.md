# Medidas design × vivo — RESUMO (DERIVADO)

> Gerado por `scripts/design/design-diff-lote.mjs` em 2026-09-21T14:27:15.184Z. **Não edite à mão** — re-rode o comando.
> Base viva: `https://staging.oimpresso.com` · bundle: `6788f6796a6da4d7bcf4153ba2c9fc03404c839e6b6a71896b8d3ca7e27196f3` · telas: 68.
> Isto NÃO é gate. `IGUAL` só aparece quando o dono (`design-diff --check`) provou a proveniência do espelho; `MEDIDO · frescor não provado` = 0 bug contra uma cópia de frescor desconhecido.

| Tela | Fonte | Rota shell | Veredito | bugs | shell | D2 | D4 | D6 | D8 | D9 | SHELL | Frescor da âncora | Motivo |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Forja/Aprovacoes/Index | forja-aprova.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Forja/Roadmap/Gantt | forja-page.jsx | projects | IGUAL | 0 | 0 | ok | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Forja/Trabalho/Index | forja-page.jsx | projects | IGUAL | 0 | 0 | ok | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| team-mcp/Forja/Cockpit | forja-page.jsx | projects | IGUAL | 0 | 0 | ok | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Atendimento/Channels/Index | inbox-page.jsx | inbox | NÃO MEDI | — | — | — | — | — | — | — | — | — | charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não |
| Atendimento/Macros/Index | inbox-page.jsx | inbox | NÃO MEDI | — | — | — | — | — | — | — | — | — | charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não |
| Cliente/Create | cliente-form.jsx | cli-novo | IGUAL | 0 | 0 | ok | sem-dado | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Cliente/Edit | cliente-form.jsx | cli-novo | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota parametrizada (/contacts/{id}/edit) — passe --tela X --url /rota/concreta |
| Cliente/Import | cliente-import.jsx | cli-import | DIVERGE (bug) | 2 | 0 | ok | 1 bug | 1 bug | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Cliente/Ledger | cliente-extrato.jsx | cli-extrato | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Cliente/Map | cliente-mapa.jsx | cli-mapa | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| ComunicacaoVisual/Index | comunicacao-visual-page.jsx | cv | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Essentials/Metas | hrm-extras.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Financeiro/Conciliacao/Index | financeiro-telas-extras.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Financeiro/Configuracoes/Contador | configuracoes-page.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | — | charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não; rota do shell: só prefixo cfg-* (window.ConfiguracoesPage) — declare o token no override |
| Financeiro/Dre/Index | financeiro-telas-extras.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Financeiro/Fluxo/Index | financeiro-telas-extras.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Financeiro/Impostos/Index | financeiro-telas-extras.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Fiscal/Cockpit | fiscal-actions.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Jana/Acoes | jana-telas-novas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Jana/Alertas | jana-telas-novas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Jana/Chat | jana-merge.jsx | chat | IGUAL | 0 | 0 | ok | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Jana/Index | jana-merge.jsx | chat | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | ⛔ NÃO MEDI — identidade da view não provada: assimetria estrutural: 5 x 1 copies — um lado so casou copy de shell Nenhuma copy pinada do contrato apareceu no render, ou a sonda é anterior ao D0. Isto NÃO é divergência de |
| Jana/Memoria | jana-merge.jsx | chat | IGUAL | 0 | 0 | ok | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Jana/Plataforma | jana-telas-novas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Jana/Pro | jana-pro.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | — | charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não; rota do shell: sem rota derivável |
| Manufacturing/Insumos | manufacturing-insumos.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Manufacturing/Report | manufacturing-producao.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Manufacturing/Settings | manufacturing-producao.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Modules/Index | Index.tsx | modulos | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | ⛔ NÃO MEDI — identidade da view não provada: um dos lados nao casou NENHUMA copy do contrato Nenhuma copy pinada do contrato apareceu no render, ou a sonda é anterior ao D0. Isto NÃO é divergência de fidelidade: é sinal  |
| OficinaAuto/AprovacaoPublica | oficina-forms.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | — | rota parametrizada (/aprovar-os/{token}) — passe --tela X --url /rota/concreta; charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não; rota do shell: sem rota derivável |
| OficinaAuto/ServiceOrders/Create | oficina-forms.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| OficinaAuto/ServiceOrders/Edit | oficina-forms.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota parametrizada (/oficina-auto/ordens-servico/{id}/edit) — passe --tela X --url /rota/concreta; rota do shell: sem rota derivável |
| OficinaAuto/Vehicles/Create | oficina-forms.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | — | charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não; rota do shell: sem rota derivável |
| OficinaAuto/Vehicles/Edit | oficina-forms.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | — | rota parametrizada (/oficina-auto/veiculos/{id}/edit) — passe --tela X --url /rota/concreta; charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não; rota do shell: sem rota derivável |
| Patrimonio/Alocacoes | patrimonio-page.jsx | assets | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | ⛔ NÃO MEDI — identidade da view não provada: assimetria estrutural: 9 x 2 copies — um lado so casou copy de shell Nenhuma copy pinada do contrato apareceu no render, ou a sonda é anterior ao D0. Isto NÃO é divergência de |
| Patrimonio/Bens | patrimonio-page.jsx | assets | DIVERGE (bug) | 2 | 0 | 1 bug | sem-dado | ok | 1 bug | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Patrimonio/Configuracoes | patrimonio-page.jsx | assets | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | ⛔ NÃO MEDI — identidade da view não provada: assimetria estrutural: 7 x 1 copies — um lado so casou copy de shell Nenhuma copy pinada do contrato apareceu no render, ou a sonda é anterior ao D0. Isto NÃO é divergência de |
| Patrimonio/Index | patrimonio-page.jsx | assets | DIVERGE (bug) | 1 | 0 | 1 bug | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Patrimonio/Manutencoes | patrimonio-page.jsx | assets | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | ⛔ NÃO MEDI — identidade da view não provada: assimetria estrutural: 5 x 2 copies — um lado so casou copy de shell Nenhuma copy pinada do contrato apareceu no render, ou a sonda é anterior ao D0. Isto NÃO é divergência de |
| Ponto/Aprovacoes/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/BancoHoras/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/BancoHoras/Show | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota parametrizada (/ponto/banco-horas/{colaborador}) — passe --tela X --url /rota/concreta; rota do shell: sem rota derivável |
| Ponto/Colaboradores/Edit | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota parametrizada (/ponto/colaboradores/{id}/editar) — passe --tela X --url /rota/concreta; rota do shell: sem rota derivável |
| Ponto/Colaboradores/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/Configuracoes/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/Configuracoes/Reps | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/Escalas/Form | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/Escalas/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/Importacoes/Create | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/Importacoes/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/Importacoes/Show | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota parametrizada (/ponto/importacoes/{id}) — passe --tela X --url /rota/concreta; rota do shell: sem rota derivável |
| Ponto/Intercorrencias/Create | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/Intercorrencias/Edit | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota parametrizada (/ponto/intercorrencias/{id}/edit) — passe --tela X --url /rota/concreta; rota do shell: sem rota derivável |
| Ponto/Intercorrencias/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Ponto/Intercorrencias/Show | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota parametrizada (/ponto/intercorrencias/{id}) — passe --tela X --url /rota/concreta; rota do shell: sem rota derivável |
| Ponto/Relatorios/Index | ponto-telas.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Produto/Index | produtos-page.jsx | produtos | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Produto/Unificado/Index | produtos-page.jsx | produtos | DIVERGE (bug) | 3 | 0 | 2 bug | ok | 2 div | 1 bug | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Purchase/Index | compras-page.jsx | compras | DIVERGE (bug) | 3 | 0 | 1 bug | 1 bug | ok | 1 bug | ok | sem-dado | verificado 2026-09-17 |  |
| Purchase/Show | compras-page.jsx | compras | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota parametrizada (/purchases/{id}) — passe --tela X --url /rota/concreta |
| RecurringBilling/Planos/Create | cobranca-recorrente-page.jsx | recurring | NÃO MEDI | — | — | — | — | — | — | — | — | — | charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não |
| Sells/Caixa/Index | vendas-extras.jsx | vendas | IGUAL | 0 | 0 | ok | ok | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |
| Sells/Create | vendas-create-page.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota do shell: sem rota derivável |
| Sells/CreateV3 | sells-create.jsx | — | NÃO MEDI | — | — | — | — | — | — | — | — | fora do espelho | rota do shell: sem rota derivável |
| Suporte/Visao | suporte-page.jsx | suporte | NÃO MEDI | — | — | — | — | — | — | — | — | verificado 2026-09-17 | rota parametrizada (/suporte/empresas/{business}) — passe --tela X --url /rota/concreta |
| User/Perfil | perfil-page.jsx | perfil | IGUAL | 0 | 0 | ok | sem-dado | ok | ok | ok | sem-dado | verificado 2026-09-17 |  |
| Vestuario/Etiquetas/Index | vestuario-page.jsx | vestuario | DIVERGE (bug) | 1 | 0 | ok | 1 bug | ok | ok | sem-dado | sem-dado | verificado 2026-09-17 |  |

## Contagem por veredito

- DIVERGE (bug): 10
- IGUAL: 8
- NÃO MEDI: 50
