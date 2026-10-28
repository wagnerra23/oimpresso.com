---
id: requisitos-fiscal-briefing
module: Fiscal
status: parcial
updated_at: "2026-10-28"
distilled_at: "2026-10-28"
distilled_by: "manual [F+C] — redestilação PARCIAL (2026-10-28, PR #10117): eventos no código e nos docs do módulo desde 2026-10-20 = #9629, #9659, #9727, #9738, #9718, #9851, #9861, #9873, #9901, #9909, #9915, #9828, #9847, #9862, #9985, #10021, #9786, #10081 e este PR (caso da venda de produto em que o cliente pede nota de serviço, só casos.md e débitos derivados). Só a Última mudança foi revista, pelos títulos dos PRs; Estado atual, Capacidades e Gaps seguem o retrato de 2026-10-20. Gatilho: distiller_freshness do sdd-scorecard acusou a porta >7d atrás do código."
---

# BRIEFING — Fiscal (verdade destilada)

## Estado atual
O módulo Fiscal atua como um cockpit unificado, consolidando nota fiscal eletrônica (NF-e) e nota fiscal de serviço eletrônica (NFS-e) em um único local. Atualmente em status parcial, a funcionalidade de NF-e está disponível ao público, enquanto o gerador do SPED ainda aguarda finalizações de integração e configuração. Recentemente, foram implementadas ações de risco para configuração fiscal.

## Capacidades
- Painel de controle com KPIs e alertas (inclui certificados vencidos).
- Listagem de NF-e/NFC-e com suporte ao drawer SEFAZ; NFS-e com filtros e busca, sem ações disponíveis.
- Manifesto DF-e e prazos de confirmação gerados automaticamente.
- Eventos registrados em timeline, com opção de exportação.
- Controle de acesso e permissões granular para gerenciamento de certificado e ambiente.
- Geração de SPED EFD-ICMS/IPI com prévias limitadas.
- Ações de cancelamento e correção de notas monitoradas.
- Cadastro do contador da empresa (nome, e-mail, CRC) em `/fiscal/config` › Envio de documentos, com o papel `Contador#{business}` (tributação, aceite, cockpit fiscal e SPED; sem venda nem financeiro) — UC-FCFG-08/09.

## Gaps
- O gerador do SPED apresenta hardcodes que impedem a exportação completa (GAP-FISCAL-003); depende de correções em configurações e permissões.
- O rollout para ROTA LIVRE (biz=4) está em pré-canary, aguardando testes manuais antes da promoção.
- Pré-voo da NFC-e automática (2026-10-26, [W] 2026-10-26): nenhuma empresa foi ligada. A biz=4 e a biz=164 emitiam pelo Delphi (WR Comercial/OfficeImpresso), que está sendo migrado — ligar aqui duplicaria a nota; virou cutover por empresa (US-NFE-074 do NfeBrasil). A biz=1 estava ligada, mas com o certificado A1 vencido em 2026-08-06 e sem credenciamento NFC-e na SEFAZ-SC (rejeição 781).

## Última mudança
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
