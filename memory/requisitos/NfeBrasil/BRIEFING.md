---
id: requisitos-nfe-brasil-briefing
module: NfeBrasil
status: producao
updated_at: "2026-09-06"
distilled_at: "2026-09-06"
distilled_by: jana:distill-module-truth
---

# BRIEFING — NfeBrasil (verdade destilada)

## Estado atual
Emissor fiscal integrado — NFC-e e NF-e (a emissão de NFS-e modelo 56 é stub, US-NFE-060; só o cancelamento NFS-e é real), com cancelamento, carta de correção, manifestação do destinatário e contingência. Em produção como código armado (biz=1/SC, ambiente de homologação, UF dinâmica; certificado A1 confirmado ativo em 2026-05-09, US-NFE-054 no SPEC — reconferir antes de usar), mas nenhuma nota real foi emitida ainda: os smokes de homologação e produção (US-NFE-054 e US-NFE-059) seguem pendentes.

## Capacidades
- Emissão NFC-e/NF-e com pipeline pronto para homologação SEFAZ (`NfeService`).
- Certificado A1 criptografado; trocar ambiente exige destino digitado + motivo, e substituir certificado exige o mesmo gate `fiscal.config.ambiente` (2026-09-04).
- Manifestação do destinatário (ciência, confirmação, desconhecimento, não realizada), sincronização de DF-e recebidos e manifestação em lote com falha parcial nomeada.
- Contingência SEFAZ (EPEC/off-line) — ativação por tenant, emissão e transmissão em contingência, saúde da SEFAZ por UF e retentativa; `tpEmis` persistido (EPEC no modelo 55, off-line no NFC-e 65). US-NFE-006 fases 1–6 (2026-09-02); o `_pendente_` do SPEC ficou atrás do código.
- Tributação ICMS/PIS/COFINS + IBS/CBS (reforma tributária, US-FISCAL-021, #3771/#3774/#3778) e importação de regras por CSV NCM; abas Config/DF-e (#4287) e charters das telas (#4142).
- Cancelamento NFC-e/NF-e com notificações (evento 110111) e Carta de Correção (evento 110110, US-FISCAL-013).
- Status da nota por polling após a autorização (US-NFE-002 fase 2C); broadcast Centrifugo/Reverb segue fora — ADR 0058/0062, Hostinger não roda daemon — e a integração via bridge HTTP é decisão [W] em aberto.

## Gaps
- MDF-e (modelo 58) e CT-e (modelo 57) ainda não suportados (o código ainda rotula CT-e como 67 em `NfeEmissao`/`NfeService`/migration — divergência doc×código não resolvida).
- SPED Fiscal/EFD: o gerador vive em `Modules/Fiscal` (`SpedIcmsIpiGeneratorService`, CU-FISC-15 do SDD do Fiscal), travado por feature flag; falta a Fase 2 do motor tributário (Strategy por regime).
- As mutações de tributação `destroy`, `toggleAutoEmission` e `aplicarTemplate` não checam permissão — `store`/`update` checam via `UpsertRegraTributariaRequest` (FormRequest); medido em 2026-07-28 (SDD §5.4.1).
- Import CSV de regras: o fluxo de 2 passos resolve o business duas vezes; trocar de negócio entre preview e aplicar grava no tenant errado (SDD §5.3 F8).
- Nenhuma nota real emitida — smokes US-NFE-054/059 pendentes. Os UC vermelhos do SDD (`SDD-emissao-fiscal-v1.0.md` §5.4.1 gate de `destroy`, §5.3 F8 tenant do import) nascem vermelhos por desenho; a correção é decisão [W]. Telas com `casos.md` ancorado no SDD: `Transactions/NfceStatus`, `Manifestacao/Index`, `Tributacao/{Index,ConfigDefault,RegraForm,ImportCsv}` — inventário vivo é o diretório `resources/js/Pages/NfeBrasil/`.

## Última mudança
2026-09-04 — manifestação DF-e em lote com falha parcial nomeada (#6740) e os defeitos empilhados que impediam a manifestação de chegar à SEFAZ (#6748); troca de ambiente passa a exigir destino digitado + motivo sob gate próprio (#6730/#6738). Antes: contingência US-NFE-006 (#6527→#6567, 2026-09-02) e o SDD de emissão fiscal (2026-07-28).

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
