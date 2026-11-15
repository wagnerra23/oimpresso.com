---
module: NfeBrasil
purpose: "Motor headless de comunicação com a SEFAZ: emite NF-e/NFC-e/NFS-e, guarda e opera o certificado A1, aplica eventos (cancelamento, CC-e, inutilização, manifestação DF-e) e calcula tributos. Não tem tela própria no sidebar — a UI é do Modules/Fiscal."
migracao_ui: "concluido — 0 Blade servido (o resource do scaffold saiu em 2026-10-27)"
contains:
  - "DataController"
  - "InstallController"
  - "CertificadoController"
  - "TributacaoController — CRUD regras NCM (US-NFE-010 fase 2)"
  - "ConfigDefaultController — Nível 4 cascade (defaults business)"
  - "ImportRegrasController — import CSV bulk (US-NFE-010 fase 3)"
  - "NfeStatusController — endpoint JSON polling + Page Inertia (US-NFE-002 fase 2C)"
  - "DanfePublicaController — DANFE em PDF por link assinado (7 dias) para o cliente, GET /nfe-brasil/danfe/{business}/{emissao}; sem assinatura válida = 403 (US-WA-321, aviso por WhatsApp)"
  - "NfeEmissaoController — emissão fiscal manual + reenvio DANFE email + download PDF (US-NFE-MANUAL, PR #262)"
  - "ManifestacaoController — Manifestação do Destinatário (US-NFE-052, PR #317)"
  - "NfeInutilizacaoController — UI admin pra inutilizar faixa NFe via SEFAZ (US-SELL-030)"
  - "ContingenciaController — liga/desliga contingencia SEFAZ por tenant (US-NFE-006, ADR TECH-0002)"
  - "EmpresaFiscalLookupController — Configurar pelo certificado, leitura (playbook Fiscal thread 21, #8829)"
  - "SugestaoFiscalController — sugestões da Jana na tributação (playbook Fiscal thread 10, #8997)"
  - "RevisaoContadorController — revisão do contador por versão de regra (playbook Fiscal thread 15a, #9003)"
  - "RevisaoContadorLinkController — link de revisão do contador com código por e-mail (playbook Fiscal thread 15b, #9015)"
  - "Middleware PermissaoNfe — permissão por rota nas ações fiscais que só exigiam login (lote 3.2 da auditoria, #10728)"
  - "Comandos nfe:* — health, retentar-contingencia, enviar-xml-contador, importar-regras-csv, migrate-cert-business, mover-arquivos-fiscais (#10692), puxar DF-e"
  - "Jobs EmitirNfceJob (ShouldBeUnique, #10725), CancelarNfeJob (cancelamento pela venda, #10697), BuscarDfesRecebidosJob, RetentarContingenciaJob, EmitirNFSeJob, CancelarNfseJob"
db_tables_owned:
  - nfe_certificados
  - nfe_emissoes
  - nfe_eventos
  - nfe_inutilizacoes
  - nfe_fiscal_rules
  - nfe_operacoes_fiscais
  - nfe_icms_uf
  - nfe_sugestoes_fiscais
  - nfe_revisoes_contador
  - nfe_contador_links
  - nfe_contadores
  - nfe_business_configs
  - nfe_fiscal_rule_tax_rate_links (bridge ADR ARQ-0005)
  - nfe_sefaz_status (saude do autorizador por UF — global por desenho da ADR TECH-0002; sem business_id)
  - nfe_dfe_recebidos
  - nfe_dfe_itens
  - nfe_dfe_eventos
  - nfe_dfe_nsu_state (cursor do DF-e; proxima_consulta_em desde #10729)
  - nfe_envios_contador
  - nfe_locais_emissores
  - nfe_naturezas_produto
  - nfse_eventos_cancelamento
not_contains:
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/Forja"
  - "MCP server admin → Modules/Forja"
  - "IA da sugestão de tributação (SugestaoFiscalAgent + ProdutoFiscalTool, playbook Fiscal thread 10, #8997) → Modules/Jana"
  - "Regra do pacote fiscal_module nas rotas /nfe-brasil/* (middleware FiscalLiberado → FiscalPacote, lote 3.3 da auditoria, decisão [W] B-03 2026-11-10) → Modules/Fiscal"
  - "Schema nfse_emissoes → Modules/NFSe (dono em produção); a migration daqui tem guard hasTable"
  - "SPED Fiscal/EFD → Modules/Fiscal"
trust_required: L3
owner: wagner
permission_prefix: nfebrasil.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /nfe-brasil/* com login e pacote fiscal_module (#10732) — configuracao · contingencia · tributacao · api · transactions · inutilizacoes · manifestacao + raiz (emitir, reenviar e-mail, DANFE)
  - /nfe-brasil/contador/* SEM login — página do contador por URL assinada (14 dias) + código de 6 dígitos por e-mail
  - /nfe-brasil/danfe/{business}/{emissao} SEM login — DANFE por link assinado (anexo do aviso por WhatsApp)
  - /nfebrasil/* (só o install do módulo; o resource do scaffold saiu em 2026-10-27)
  - /api/v1/nfebrasil (scaffold nWidart · auth:sanctum · devolve o usuário logado, sem uso de negócio)
drift_alerts: []
---

# Modules/NfeBrasil

## Missão

Motor de comunicação com a SEFAZ: NF-e e NFC-e (emissão, cancelamento, CC-e, inutilização, contingência),
manifestação e leitura de DF-e, certificado A1 e motor tributário. A UI de operação fica no Modules/Fiscal;
aqui ficam as telas de Tributação, Manifestação e o status da NFC-e da venda. SPED é do Modules/Fiscal.

## Permissões

Dois prefixos convivem no `DataController`: `nfebrasil.*` (access, emit, consult, sped) e `nfe.*`
(`nfe.tributacao.manage`, `nfe.configuracao.manage`, `nfe.manifestacao.*`). O `permission_prefix` do
frontmatter guarda só o primeiro, porque é lido por máquina como um prefixo único.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
- **v1.1.0** (2026-11-11) — auditoria NfeBrasil, lote 5.3: `url_prefixes` com as duas rotas públicas e o
  pacote `fiscal_module`; o resource do scaffold saiu da lista; 8 tabelas que as migrations do módulo criam
  entram em `db_tables_owned` (`nfse_emissoes` fica com o NFSe); os dois prefixos de permissão; missão sem SPED.
