---
id: requisitos-fiscal-briefing
module: Fiscal
status: parcial
updated_at: "2026-11-06"
distilled_at: "2026-11-06"
distilled_by: "manual [C] — 2026-11-06 (auditoria Fiscal, lote 4): Estado atual, Capacidades e Gaps revistos contra o código do origin/main 0eb214f534 e os lotes 0 e 1 da auditoria (#10397, #10399, #10405, #10404, #10416, #10439, #10462). Antes — redestilação PARCIAL (2026-11-05): eventos no código e nos docs do módulo desde 2026-10-28 = #10117, #10133, #10153, #10160, #10178, #10196, #10201, #10268, #10314 e #10378. Só a Última mudança foi revista, pelos títulos e corpos dos PRs; Estado atual, Capacidades e Gaps seguem o retrato de 2026-10-20. Gatilho: distiller_freshness do sdd-scorecard acusou a porta >7d atrás do código depois do #10378 (main mediu 12 contra a linha de base 11). Redestilação anterior: 2026-10-28, PR #10117."
---

# BRIEFING — Fiscal (verdade destilada)

## Estado atual
O Fiscal é a cara única do domínio fiscal: 8 telas sob `/fiscal` (Cockpit, NF-e, NFS-e, DF-e, Eventos, Config, SPED e DAS) que leem o NfeBrasil e o NFSe e delegam toda ida à SEFAZ aos Services deles. É dono próprio só do gerador do SPED EFD-ICMS/IPI. Toda rota operacional exige o pacote `fiscal_module` da empresa e o login liberado (desde 2026-11-05), e cada tela tem a sua permissão. O SPED segue travado por padrão (`fiscal.sped_simples_only_lock`) até a validação no PVA, e a tela do DAS segue atrás de flag por empresa. O estado de cada tela (casos, testes, notas) sai de `node scripts/governance/requisitos-status.mjs Fiscal` e de `npm run screen-coverage:report`, não deste texto.

## Capacidades
- Painel de controle com KPIs e alertas (inclui certificados vencidos).
- Listagem de NF-e/NFC-e com drawer SEFAZ; NFS-e com filtros e busca, sem ações disponíveis.
- Ações na NF-e: cancelar (só a nota, ou a venda junto pelo FSM — R-NFE-044), CC-e, inutilizar faixa, retransmitir sem apagar a nota antiga e NF-e de devolução (R-NFE-039). Erro da SEFAZ chega ao operador como mensagem genérica; o detalhe vai para o log.
- NF-e do regime normal (CRT 3) sai com o grupo IBS/CBS sem precisar ligar nada (ADR 0442); Simples segue igual.
- Manifesto DF-e com prazo de confirmação vindo da SEFAZ, manifestação por nota e em lote; o ambiente da manifestação e da distribuição é o da empresa, não o do servidor.
- Eventos em timeline, com export CSV dos filtros ativos.
- Config num lugar só: certificado A1, ambiente Homologação × Produção, contingência, séries e contador.
- Geração do SPED EFD-ICMS/IPI com os 10 blocos obrigatórios e o COD_VER do ano, atrás da trava.
- Prévia da apuração do DAS pelo Anexo (só leitura, flag por empresa `useV2FiscalDas`).
- Busca ⌘K de notas e DF-e, cada uma só para quem tem a permissão da tela dona.
- Alerta diário de certificado A1 a vencer (`fiscal:cert-health-check`).
- Cadastro do contador da empresa (nome, e-mail, CRC) em `/fiscal/config` › Envio de documentos, com o papel `Contador#{business}` (tributação, aceite, cockpit fiscal e SPED; sem venda nem financeiro) — UC-FCFG-08/09.

## Gaps
- SPED: os 6 valores fixos do GAP-FISCAL-003 viraram fallback com motor tributário (US-FISCAL-020), mas a UF de origem ainda cai em `'SP'` e o cabeçalho do emitente está em discussão; a trava fica ligada até o PVA real. Liberar é decisão [W] (proposta `decisions/proposals/2026-09-04-sped-identificacao-do-emitente-fonte-de-verdade.md` §7).
- Drawer "Enviar p/ contabilidade", status da SEFAZ e resumo de inadimplência do Cockpit seguem com dado de demonstração; a decisão [W] é marcar a procedência (SDD CU-FISC-16), ainda não feita.
- DAS: falta lançar a pagar e a segregação de receitas (US-FISCAL-030). IBS/CBS: falta a Saúde fiscal apontar a regra do regime normal sem CST (US-FISCAL-025) e o split UF/Município (US-FISCAL-024).
- O rollout para ROTA LIVRE (biz=4) está em pré-canary, aguardando testes manuais antes da promoção.
- Pré-voo da NFC-e automática (2026-10-26, [W] 2026-10-26): nenhuma empresa foi ligada. A biz=4 e a biz=164 emitiam pelo Delphi (WR Comercial/OfficeImpresso), que está sendo migrado — ligar aqui duplicaria a nota; virou cutover por empresa (US-NFE-074 do NfeBrasil). A biz=1 estava ligada, mas com o certificado A1 vencido em 2026-08-06 e sem credenciamento NFC-e na SEFAZ-SC (rejeição 781).

## Última mudança
Em 2026-11-05/06 (auditoria do Fiscal, lotes 0 e 1): o ambiente da SEFAZ da manifestação, da distribuição DF-e e da inutilização passou a vir da configuração da empresa (#10405); as rotas passaram a exigir o pacote `fiscal_module` e o login liberado também na URL direta (#10404); cada item do menu checa a permissão da tela (#10416); a busca e os eventos respeitam a permissão de notas e de DF-e (#10439); e o erro técnico deixou de chegar à tela (#10462). A lane MySQL do NfeBrasil passou a rodar quando muda código do Fiscal (#10397).
Em 2026-11-05 (PR #10378), o Manifesto DF-e (`/fiscal/dfe`) ganhou os casos UC-FDFE-14..16 com teste: o prazo de confirmação pinta crítico, atenção ou tranquilo nas bordas de 7 e 30 dias; cada chip de estado devolve só as suas notas, e "pendentes" inclui a nota com ciência; a busca acha por trecho da chave, por CNPJ com pontuação e pelo nome do emitente. O teste da busca por nome achou um defeito: a busca tirava os não-dígitos do texto, sobrava vazio e a condição da chave casava todas as notas. Agora a chave e o CNPJ só entram na busca quando o texto tem dígito. Entre 2026-10-28 e 2026-11-05, pelos títulos dos PRs: a venda bonificada sai como NF-e de bonificação, CFOP 5910/6910 e tPag 90 (#10178), e tributa pela regra da operação dela (#10196); a apuração do DAS ganhou tela atrás da flag por empresa `useV2FiscalDas` (US-FISCAL-030, fatia 2, #10268); o CEST e o GTIN do cadastro do produto vão ao XML da NF-e (#10314); e os casos do módulo ganharam os pedidos do Sabor Brasil para a migração (#10160) e perguntas para os casos que dependiam do [W] (#10201).
Em 2026-10-28 (PR #10117), a tela de NFS-e passou a mostrar, no bloco de decisões pendentes, o caso da Fixar: venda lançada com itens de produto em que o cliente pede nota de serviço. A natureza fiscal segue sendo do produto e decidida pelo contador (D-NATUREZA); se quem fatura poderá trocá-la só no item da venda é decisão [W]. Nenhuma regra de emissão mudou. Entre 2026-10-20 e 2026-10-28, pelos títulos dos PRs: a venda passou a se separar em NF-e da mercadoria e NFS-e do serviço pela natureza do item (#9659, #9727); a NF-e ganhou contingência SVC-AN/SVC-RS, EPEC e retransmissão agendada (#9629, #9718), frete e desconto corrigidos no XML (#9738), base e alíquotas em percentual no regime normal (#9861), IBS/CBS automático no regime normal (#9901), CSC da empresa nas chaves da SEFAZ (#9915) e NF-e interestadual com DIFAL e ICMS-ST (#9828); a NF-e de devolução ganhou rota com permissão (#9851); a busca de DF-e usa o CNPJ da empresa e a falha vira alarme no cockpit (#9873); o XML do mês vai ao contador uma vez por competência (#9847); o SPED EFD-ICMS/IPI saiu com os 10 blocos e o COD_VER do leiaute (#9985); e a apuração do DAS pelo Anexo entrou, só como cálculo (#10021). As lacunas da frente Fiscal viraram as US-FISCAL-025..031 (#9862).
Em 2026-10-26 (R-NFE-044, decisão [W]), cancelar uma NF-e pelo drawer de `/fiscal/nfe` passou a perguntar: **só a nota** (padrão, para reemitir) ou **a venda junto** — esta roda a ação FSM `cancelar_venda`; com a US-SELL-077 (#9911) a venda é cancelada, o estoque volta e o título sem pagamento é cancelado. Antes de cancelar a nota a tela confere se a venda pode ser cancelada: venda com dinheiro recebido recusa e **nada** é cancelado, nem a nota. O texto do modal que dizia "aciona FSM cascade" saiu: o cancelamento sozinho nunca disparou FSM. Em 2026-10-20 (PR #9632), o `Nfe.casos.md` ganhou os casos UC-FNFE-21..23 da NF-e emitida quando a fatura da cobrança recorrente é paga (US-RB-044 · UC-FLX-26); nenhum código de tela mudou, os testes existentes do listener passaram a citar os casos, e o `_lib/debitos-conhecidos.ts` foi regerado pelo `fiscal-debitos-derive`. Em 2026-10-07 (playbook Fiscal thread 15c, PR #9018), o card **Envio de documentos** de `/fiscal/config` deixou de declarar "ainda não existe campo": a empresa cadastra o contador (tabela `nfe_contadores`, do NfeBrasil) e salvar cria o papel "Contador". O drawer "Enviar p/ contabilidade" do cockpit segue mockado (thread 28). Em 2026-09-09, as 7 abas do cockpit voltaram aos rótulos e à ordem do protótipo; antes disso, em 2026-09-08, os 8 inventários de paridade do Fiscal + NfeBrasil foram vinculados e as 2 telas silenciosas fechadas, e em 2026-09-06 a lane MySQL passou a EXECUTAR os 3 testes da tela Dfe. Em 2026-09-04 tinham saído as melhorias da onda 10 do SPED — prévia do arquivo de referência, correções das ações de manifestação e ajuste das permissões de operação administrativa.

## Proveniência (destilado de)

- audit `requisitos/Fiscal/AUDIT-SENIOR-2026-05-25.md` — AUDIT-SENIOR-2026-05-25.md
- audit `requisitos/Fiscal/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/Fiscal/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- handoff `handoffs/2026-09-24-1150-fiscal-thread02-recibo-e-indice-patrimonio.md` (2026-09-24) — 2026-09-24-1150-fiscal-thread02-recibo-e-indice-patrimonio.md
- session `sessions/2026-09-14-refutacao-gt-g5-lote-7262-r2.md` (2026-09-14) — 2026-09-14-refutacao-gt-g5-lote-7262-r2.md
- session `sessions/2026-09-13-fiscal-e2e-rede-thread01.md` (2026-09-13) — 2026-09-13-fiscal-e2e-rede-thread01.md
- session `sessions/2026-09-04-fiscal-onda10-sped-goals-cowork.md` (2026-09-04) — 2026-09-04-fiscal-onda10-sped-goals-cowork.md
- handoff `handoffs/2026-09-04-1215-fiscal-onda10-sped-4-de-5-goals.md` (2026-09-04) — 2026-09-04-1215-fiscal-onda10-sped-4-de-5-goals.md
- session `sessions/2026-09-03-fiscal-onda9-sped-regua-golden.md` (2026-09-03) — 2026-09-03-fiscal-onda9-sped-regua-golden.md
- handoff `handoffs/2026-09-03-2040-fiscal-onda9-sped-regua-golden.md` (2026-09-03) — 2026-09-03-2040-fiscal-onda9-sped-regua-golden.md
- handoff `handoffs/2026-09-02-0804-fiscal-onda0-e-consertos-de-gates.md` (2026-09-02) — 2026-09-02-0804-fiscal-onda0-e-consertos-de-gates.md
- session `sessions/2026-09-01-fiscal-f0-screen-coverage.md` (2026-09-01) — 2026-09-01-fiscal-f0-screen-coverage.md
- handoff `handoffs/2026-08-28-1040-design-sync-recibos-fiscal.md` (2026-08-28) — 2026-08-28-1040-design-sync-recibos-fiscal.md
