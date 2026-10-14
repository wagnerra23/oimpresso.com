---
date: "2026-10-14"
time: "03:00 BRT"
slug: gestao-fila-merges-13-out-3
tldr: "Terceira gerência da fila (13/10 noite → 14/10 madrugada). 91 PRs no main (#9219–#9342), fila vazia ao fechar. Placar 310 → 322 de 392 (82,1%). Entraram as telas novas (flags OFF) de Crm 11–17, Restaurante 01–05, Woocommerce 01, Despesas e Configuração da empresa; 6 consertos Tier 0; tema escuro completo (#9321). Pendências do [W]: ligar flags, decisões de valor das Despesas, portão do /manage-modules."
prs: [9219, 9223, 9246, 9247, 9248, 9249, 9250, 9251, 9252, 9253, 9254, 9255, 9256, 9257, 9259, 9260, 9261, 9263, 9264, 9265, 9266, 9267, 9268, 9269, 9270, 9271, 9272, 9273, 9274, 9275, 9276, 9277, 9279, 9280, 9281, 9282, 9283, 9284, 9285, 9286, 9287, 9288, 9290, 9291, 9292, 9293, 9294, 9295, 9296, 9297, 9298, 9299, 9300, 9301, 9302, 9303, 9304, 9305, 9306, 9307, 9308, 9309, 9310, 9312, 9313, 9315, 9321, 9322, 9325, 9326, 9327, 9328, 9329, 9330, 9332, 9333, 9334, 9335, 9336, 9337, 9339, 9340, 9341, 9342]
---

# Handoff — Gerente da fila de merges (13/10 · 3)

Sucede `2026-10-13-1857-gestao-fila-merges-13-out-2.md`. Mandato [W]: "gerencie o merge de todos, decida". Continuam com ele: valor fora do molde, migration destrutiva, cutover (ligar flag). Baseline/foto é do gerente, mas a ADR 0409/0411 manda não regravar VRT para fazer check passar — nesta gerência nenhuma foto foi gravada.

## Estado ao fechar

- **Fila vazia** (0 PRs abertos). Nenhum processo em background precisa ser relançado.
- **Placar** (`npm run placar:lista`): 322 de 392 (82,1%) — feito 322 · em curso 34 · pendente 11 · próximo 15 · bloqueada 10.
- **Sessões abertas**: só "Financeiro 11 e A3" (Despesas). As outras de chip foram arquivadas ao entregar.

## O que entrou (por frente)

- **Configuração da empresa** (Sistema 08): pilha até #9223 (rótulos). Falta F4 smoke biz=1 com a flag e o cutover — [W].
- **Tema escuro**: catraca da sonda de produção (#9265) + 9 Pages consertadas, entregues juntas no #9321; os 7 PRs de baixo (#9289, #9314, #9316–#9320) fechados como incorporados. StockTransfer/Create ficou de fora: tem PageHeader antigo; volta quando a tela migrar o header (decisão visual [W]). A baseline da sonda tem só essa entrada.
- **Crm 11–17** (Campanhas, Chamadas, Relatórios, Propostas, Configurações, Logins de contatos, Pedidos de clientes): F1 RUNBOOK → F2 baseline → F3 tela, todas atrás de flag OFF.
- **Restaurante 01–05** (Mesas, Modificadores, Reservas, Cozinha, Pedidos) + BRIEFING (#9326), flags OFF.
- **Woocommerce 01** painel (#9284), flag OFF. **Despesas** (Financeiro 13): #9260 → #9267 → #9275 → #9276 → #9280, flag OFF.
- **Fiscal 14** Saúde fiscal (#9271 serviço + #9279 card na Tributação). Errata de índice da Fiscal 07 (#9263).
- **Consertos Tier 0 (isolamento entre empresas / permissão)**: #9293 campanha aceitava contato de outra empresa · #9298 endpoints de relatório do Crm sem permissão · #9302 exclusão em lote de chamadas apagava de colega · #9315 login de contato editava usuário da equipe ou de outra empresa · #9325 item servido do Restaurante de outra empresa.
- **Patrimônio 05**: #9268 desfaz a ADR 0430 (retenção LGPD), por decisão [W] de 13/10 na sessão — "não pode ter o LGPD no patrimonio".

## Pendências do [W]

1. **Ligar as flags** (cutover) das telas novas de hoje: Crm (7), Restaurante (5), Woocommerce, Despesas, Configuração da empresa (F4 antes).
2. **Despesas — valor**: (a) rodapé soma a página (como a Blade) ou o filtro (D-DESP-TOTAL); (b) pagamento na tela nova (hoje link para a clássica); (c) valor por ponto 0 divide por zero na venda (achado do `_saida-08j`) — barrar é decisão.
3. **Modules 04** (`/manage-modules`): o portão pede smoke visual 1280/1440 aprovado e as 3 ações (toggle/install/uninstall) exercitadas em produção. Sem isso não se apaga.
4. **AppLojas 01**: o desenho seguiu a D16 (40 telas, fila 39 e dashboard dentro). Duas suposições a confirmar: quem vê a tela 40 "Quem está trabalhando" e a barra do Colaborador (Ponto · Tarefas · Conta).
5. **Vestuário/Etiquetas**: o subtítulo passou a mostrar "· US-VEST-020" com o header canon (#9283).
6. **Crm**: `/crm/call-log` está desligado por config fixa (`enable_crm_call_log => false`) — ligar é decisão; e o nome da permissão dos relatórios (`crm.view_reports` do protótipo × `is_admin` do código).
7. Do handoff anterior, ainda abertas: re-medição de Cliente/Index e User/Perfil no staging; devoluções de compra; projeto `copia` do Cowork.

## Pendências do próximo gerente

- **Conferir os 25 recibos de 13/10 no Cowork**: o #9337 registrou o envio (`write_files` por `localPath`), mas o `--conferir` (volta por `get_file`) não foi rodado. O ledger do espelho não os marca como verificados.
- **Erratas de índice a aplicar no Cowork**: Manufacturing/04, Forja/09, Patrimônio/16 (#9220), Fiscal/07 (#9263), Hrm/11 (o `_saida-11b` pede "bloqueada até a Folha ter Page"), Governance/01 (contrato do lado do Cowork), PageHeader/03 (setas e `role=banner` no DS publicado). Até lá o placar marca essas threads "em curso" mesmo entregues — antes de abrir chip, ler recibos e erratas, não só o placar.
- **Financeiro**: US-018 parte 2 (categorias de despesa) e US-019 (aprovação visual / flag).

## Lições

- **Cota da API do GitHub**: ~47 vigias com `sleep 60` esgotaram a GraphQL duas vezes. Os scripts novos (`conferir2.sh`, `lane2.sh`, `stack2.sh`, `depois.sh` em `noite-0710/`) usam 300 s. Não editar script em execução: gerar arquivo novo e `mv`.
- **Fila do CI saturada** (1.900 runs, 37 em execução). Cancelar runs de heads superados ajudou pouco (38); o resto é volume real de 50+ PRs abertos.
- **update-branch muda o head e invalida o run da lane**. Para F2/F3 empilhados, não dar update: se o PR continuar mergeável depois do de baixo, mergear no head atual com o run já provado (`depois.sh`). Quando o head muda só em SUPERFICIE, lista de testes ou `.tsx`, o run PHP anterior vale — conferir com `git diff <testado> <head>`.
- **Lanes só em opened/reopened**: `acessos-pest`, `verticais-pest` e outras não rodam em synchronize; dispatch tem `cancel-in-progress` por ref — dois dispatches na mesma branch cancelam um ao outro.
- **Gates que o main quebra para todos**: SDD ratchet (BRIEFING faltando, #9326), drift do SUPERFICIE, Casos G-6 (casos.md não bumpado quando a tela muda, #9339 — causado pelo #9321, que eu mergeei). Depois do conserto, `pos-<PR>.sh` dá update-branch nos PRs vermelhos.
- **PR "incorporador"**: ao decidir mergear só o topo de uma pilha, tirar os de baixo da lista de vigias antes de relançar — o #9265 entrou sozinho por isso.
- **`--registrar-envio-todos` re-carimba recibos já enviados**: usar `--registrar-envio` com a lista do `--plano`.
- **SUPERFICIE.md conflita a cada merge do mesmo módulo**: com vários PRs do Crm abertos, cada merge gerou um ciclo de merge do main + regeneração nos outros. Serializar PRs do mesmo módulo reduz o custo.

## Estado MCP no momento do fechamento

Não consultado nesta sessão (contexto grande). O brief do início: sem cycle ativo, 4 HITL do [W].
