---
id: requisitos-nfe-brasil-briefing
module: NfeBrasil
status: producao
updated_at: "2026-10-29"
distilled_at: "2026-10-29"
distilled_by: "agente (2026-10-29: re-leitura manual restrita ao que o Sabor Brasil pediu — regime próprio do local emissor e regra fiscal por regime, P-155, e ICMS-ST dentro do estado para contribuinte, P-153 — NfeService::resolverEmitente/montarItensNfe/internaDoSt, MotorTributarioService::calcularComRegimeDoLocal; 2026-10-21: re-leitura manual restrita à emissão da venda separada, US-NFE-069 PR-2 — NfeService, NfeEmissaoController e o NfseController do módulo NFSe; 2026-10-21 (outra): re-leitura manual restrita à contingência — ModoContingencia, NfeService::resolverTpEmis/registrarEpec, ContingenciaService::ativar, US-NFE-071; 2026-10-13: re-leitura manual restrita ao RetencoesNfseService da thread 23 do playbook Fiscal; 2026-10-01: re-leitura manual restrita ao que o lote 4 de install fora de GET mudou — InstallController e rotas install/*; antes, 2026-09-06: jana:distill-module-truth)"
---

# BRIEFING — NfeBrasil (verdade destilada)

## Estado atual
Emissor fiscal integrado — NFC-e e NF-e (a emissão de NFS-e modelo 56 é stub, US-NFE-060; só o cancelamento NFS-e é real), com cancelamento, carta de correção, manifestação do destinatário e contingência. Em produção como código armado (biz=1/SC, ambiente de homologação, UF dinâmica; certificado A1 confirmado ativo em 2026-05-09, US-NFE-054 no SPEC — reconferir antes de usar), mas nenhuma nota real foi emitida ainda: os smokes de homologação e produção (US-NFE-054 e US-NFE-059) seguem pendentes.

## Capacidades
- Emissão NFC-e/NF-e com pipeline pronto para homologação SEFAZ (`NfeService`).
- Certificado A1 criptografado; trocar ambiente exige destino digitado + motivo, e substituir certificado exige o mesmo gate `fiscal.config.ambiente` (2026-09-04).
- Manifestação do destinatário (ciência, confirmação, desconhecimento, não realizada), sincronização de DF-e recebidos e manifestação em lote com falha parcial nomeada.
- Contingência SEFAZ — ativação por tenant com escolha de modo para a NF-e (SVC-AN `tpEmis` 6 e SVC-RS 7 transmitem na hora para a SEFAZ Virtual da UF; EPEC 4 guarda a nota e envia o evento 110140 ao Ambiente Nacional, US-NFE-071 2026-10-21), NFC-e off-line (9), saúde da SEFAZ por UF e retentativa. A marcação de contingência no DANFE e o smoke em homologação seguem pendentes. US-NFE-006 fases 1–6 (2026-09-02); o `_pendente_` do SPEC ficou atrás do código.
- Tributação ICMS/PIS/COFINS + IBS/CBS (reforma tributária, US-FISCAL-021, #3771/#3774/#3778) e importação de regras por CSV NCM; abas Config/DF-e (#4287) e charters das telas (#4142).
- Cálculo das retenções federais PIS/COFINS/CSLL da NFS-e (`RetencoesNfseService`, R-NFSE-001/002, Lei 10.833/2003 arts. 30–32): regime normal retém 4,65% com dispensa no limite do § 3º; Simples/MEI não retém e avisa o contador. Ligado à emissão no mesmo dia (R-NFSE-003): marcação na tela Emitir NFS-e, valores gravados em `nfse_emissoes` e grupo `tribFed` na DPS (NT 007).
- Venda com mercadoria e serviço sai em duas notas (US-NFE-069 · UC-FLX-27): `SeparacaoNotasDaVenda` divide a venda pela natureza fiscal do produto e o botão "Emitir NF-e + NFS-e" do drawer de Vendas emite a NF-e só com a parte mercadoria e a NFS-e só com a parte serviço, com o código LC 116 do contador. Rateio decidido por [W] em 2026-10-21: desconto/acréscimo proporcional ao bruto, frete inteiro na NF-e. Natureza pendente bloqueia; sem o botão, a NF-e segue recusando serviço (R-NFE-034).
- Cancelamento NFC-e/NF-e com notificações (evento 110111) e Carta de Correção (evento 110110, US-FISCAL-013).
- Status da nota por polling após a autorização (US-NFE-002 fase 2C); broadcast Centrifugo/Reverb segue fora — ADR 0058/0062, Hostinger não roda daemon — e a integração via bridge HTTP é decisão [W] em aberto.
- ICMS-ST dentro do estado para contribuinte (P-153, 2026-10-29): a NF-e e o ST da tela de venda leem a interna da própria UF quando o destinatário é contribuinte (`NfeService::internaDoSt`); consumidor final da mesma UF segue sem ST, e outra UF como antes. Sem a interna da UF cadastrada, sem ST.

## Gaps
- MDF-e (modelo 58) e CT-e (modelo 57) ainda não suportados (o código ainda rotula CT-e como 67 em `NfeEmissao`/`NfeService`/migration — divergência doc×código não resolvida).
- SPED Fiscal/EFD: o gerador vive em `Modules/Fiscal` (`SpedIcmsIpiGeneratorService`, CU-FISC-15 do SDD do Fiscal), travado por feature flag; falta a Fase 2 do motor tributário (Strategy por regime).
- As mutações de tributação `destroy`, `toggleAutoEmission` e `aplicarTemplate` não checam permissão — `store`/`update` checam via `UpsertRegraTributariaRequest` (FormRequest); medido em 2026-07-28 (SDD §5.4.1).
- Import CSV de regras: o fluxo de 2 passos resolve o business duas vezes; trocar de negócio entre preview e aplicar grava no tenant errado (SDD §5.3 F8).
- Nenhuma nota real emitida — smokes US-NFE-054/059 pendentes. Os UC vermelhos do SDD (`SDD-emissao-fiscal-v1.0.md` §5.4.1 gate de `destroy`, §5.3 F8 tenant do import) nascem vermelhos por desenho; a correção é decisão [W]. Telas com `casos.md` ancorado no SDD: `Transactions/NfceStatus`, `Manifestacao/Index`, `Tributacao/{Index,ConfigDefault,RegraForm,ImportCsv}` — inventário vivo é o diretório `resources/js/Pages/NfeBrasil/`.

## Última mudança
2026-10-21 — emissão da venda separada em NF-e + NFS-e pelo drawer de Vendas (US-NFE-069 PR-2, rateio decidido por [W]); o smoke em homologação segue pendente. Também no mesmo dia, contingência ganha os modos SVC-AN/SVC-RS e o evento EPEC 110140, escolhidos na seção Contingência SEFAZ de `/fiscal/config` (US-NFE-071, UC-FLX-32). Antes: 2026-10-13 — retenção federal ligada à emissão da NFS-e (marcação na nota, decisão [W]); antes, no mesmo dia, cálculo das retenções federais da NFS-e no NfeBrasil (thread 23, D-NFSE-CONSOLIDA), sem chamador. Antes: 2026-10-01 — instalar/desinstalar/atualizar o módulo deixam de executar por GET: o GET de `nfebrasil/install*` só mostra uma confirmação, e a ação roda no POST + CSRF (trait `InstalacaoSoPorPost`, lote 4 da série #8387). Nada mudou na emissão. Antes: 2026-09-04 — manifestação DF-e em lote com falha parcial nomeada (#6740) e os defeitos empilhados que impediam a manifestação de chegar à SEFAZ (#6748); troca de ambiente passa a exigir destino digitado + motivo sob gate próprio (#6730/#6738). Antes: contingência US-NFE-006 (#6527→#6567, 2026-09-02) e o SDD de emissão fiscal (2026-07-28).

## Proveniência (destilado de)

- audit `requisitos/NfeBrasil/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/NfeBrasil/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- handoff `handoffs/2026-08-24-1711-workflow-migracao-layout-em-ondas.md` (2026-08-24) — 2026-08-24-1711-workflow-migracao-layout-em-ondas.md
- session `sessions/2026-08-20-backup-migracao-ondas-0-a-3.md` (2026-08-20) — 2026-08-20-backup-migracao-ondas-0-a-3.md
- session `sessions/2026-08-14-censo-redacao-brl-em-codigo.md` (2026-08-14) — 2026-08-14-censo-redacao-brl-em-codigo.md
- handoff `handoffs/2026-08-12-1509-triagem-componentes-orfaos-e-o-inventario-que-nao-consultei.md` (2026-08-12) — 2026-08-12-1509-triagem-componentes-orfaos-e-o-inventario-que-nao-consultei.md
- session `sessions/2026-07-30-pr5069-refutacao-r1.md` (2026-07-30) — 2026-07-30-pr5069-refutacao-r1.md
- session `sessions/2026-07-29-srs-e5-e6-e-quatro-refutacoes.md` (2026-07-29) — 2026-07-29-srs-e5-e6-e-quatro-refutacoes.md
- handoff `handoffs/2026-07-29-2005-srs-rodada5-aprovada-smoke-verde.md` (2026-07-29) — 2026-07-29-2005-srs-rodada5-aprovada-smoke-verde.md
- session `sessions/2026-07-28-sdd-nfebrasil-emissao-fiscal.md` (2026-07-28) — 2026-07-28-sdd-nfebrasil-emissao-fiscal.md
- session `sessions/2026-07-28-sdd-recurringbilling-cobranca-recorrente.md` (2026-07-28) — 2026-07-28-sdd-recurringbilling-cobranca-recorrente.md
