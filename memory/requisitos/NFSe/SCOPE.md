---
module: NFSe
purpose: "Emite, consulta e cancela NFS-e pelo Sistema Nacional (Sefin Nacional) com certificado A1 e ambiente por empresa; serve as telas /nfse e a emissão a partir da venda. Divide a tabela nfse_emissoes com o Modules/NfeBrasil, que também a cria e altera. A decisão [W] de 2026-10-13 de consolidar a NFS-e no NfeBrasil (D-NFSE-CONSOLIDA) ainda não tem ADR."
migracao_ui: "concluido — 0 Blade servido"
contains:
  - "NfseController — telas Index/Emitir/Show, cancelar, pdf e POST /nfse/transactions/{tx}/emitir (emissão a partir da venda)"
  - "DataController — menu e permissões nfse.view/emit/cancel"
  - "InstallController"
  - "NfseEmissaoService — idempotência, retry e cancelamento"
  - "SnNfseAdapter (NfseProviderInterface) — chamada HTTP à Sefin Nacional; a DPS é serializada e assinada pela DpsXml do NfeBrasil"
  - "EmitirNfseJob (fila nfse)"
  - "TransactionNfseObserver — cria rascunho a partir da venda recorrente"
  - "NotaDaVendaNfse — diz à venda se ela tem NFS-e emitida (trava de edição, US-SELL-072)"
  - "Comandos nfse:health e nfse:importar-cert"
db_tables_owned:
  - nfse_provider_configs
  - nfse_emissoes (COMPARTILHADA — o NfeBrasil também a cria (migration idempotente) e a altera em 2026-10-13 e 2026-10-20; os dois módulos têm model próprio para ela e vocabulários de status diferentes. Dono final pendente de ADR da D-NFSE-CONSOLIDA)
db_tables_consumed:
  - nfe_certificados (dono NfeBrasil; a migration do NFSe também a cria de forma idempotente)
not_contains:
  - "Serialização e assinatura da DPS, leitura do certificado A1 e lista de serviços nacional → Modules/NfeBrasil"
  - "Cancelamento por evento assinado (nfse_eventos_cancelamento, CancelarNfseJob) → Modules/NfeBrasil"
  - "Cockpit fiscal de leitura (/fiscal/nfse) → Modules/Fiscal"
  - "Retenção da NFS-e como baixa no título → Modules/Financeiro"
trust_required: L3
owner: wagner
permission_prefix: nfse.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /nfse/*
drift_alerts: []
---

# Modules/NFSe

## Missão

Emitir NFS-e pelo Sistema Nacional (LC 214/2025) para a empresa da sessão: tela própria (`/nfse`), emissão a partir da venda e rascunho a partir da venda recorrente.

## Fronteira com o NfeBrasil (estado em 2026-11-16)

O NFSe e o NfeBrasil dividem `nfse_emissoes`, e a fronteira hoje é esta:

- As duas migrations criam a tabela (`Modules/NFSe/Database/Migrations/2026_05_01_000003_*` e `Modules/NfeBrasil/Database/Migrations/2026_05_11_150001_*`, idempotente). As colunas novas de 2026-10 (retenções federais, cTribNac, ISS retido) estão em migrations do **NfeBrasil**.
- Cada módulo tem seu model da tabela: `Modules\NFSe\Models\NfseEmissao` (status `rascunho|processando|emitida|cancelada|erro`) e `Modules\NfeBrasil\Models\NfseEmissao` (status `pending|sent|authorized|rejected|cancelled`). Fiscal, Financeiro e Fisio consomem o model do NFSe.
- A emissão do NFSe já usa código do NfeBrasil (`DpsXml`, `CertificadoService`, `ListaServicoNacional`).
- Há dois caminhos de cancelamento: o do NFSe (`SnNfseAdapter::cancelar`) e o do NfeBrasil (`NfseCancelService` + `CancelarNfseJob`, que grava em `nfse_eventos_cancelamento`).

[W] decidiu em 2026-10-13 que a NFS-e consolida no NfeBrasil (D-NFSE-CONSOLIDA, citada no SPEC do NfeBrasil e em docblocks). A decisão não tem ADR, e este SCOPE **não** escolhe o dono final: registra os dois.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../governance/ARCHITECTURE.md).

---

- **v1.1.0** (2026-11-16) — Estado real: tabela `nfse_emissoes` compartilhada com o NfeBrasil, tabelas declaradas, `contains` com o que o módulo tem, `not_contains` com os vizinhos reais (NfeBrasil, Fiscal, Financeiro) no lugar do texto de modelo. A v1.0.0 dizia "Dono do schema nfse_emissoes em produção" e "Spec-ready, ativar com NfeBrasil". Auditoria NFSe, lote 5.2.
- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
