---
module: Fiscal
purpose: "Cara única (UI e sidebar) do domínio fiscal: agrega leitura de NfeBrasil e NFSe, delega toda ação SEFAZ aos Services deles, e é dono próprio do gerador SPED EFD-ICMS/IPI."
migracao_ui: "concluido — 0 Blade servido"
contains:
  - "AcoesController — thin delegate: cancelar (só a nota, ou a venda junto via FSM — R-NFE-044) · CC-e · inutilizar · retransmitir · devolução (DevolucaoService, R-NFE-039) · manifestar DF-e (4 ações, por linha e em lote)"
  - "CockpitController — sub-página 1 (KPIs + alertas + sparklines + quick links)"
  - "ConfigController — sub-página 6 (cert A1, ambiente SEFAZ, contingência, séries, SPED) + POST /config/contador; cert/ambiente/contingência postam para rotas do NfeBrasil"
  - "DataController"
  - "DfeController — sub-página 4 (Manifesto DF-e + prazo 90d)"
  - "EventosController — sub-página 5 (timeline append-only CC-e/Cancel/EPEC/Manifesto)"
  - "InstallController"
  - "NfeCockpitController — sub-página 2 (NF-e/NFC-e + drawer SEFAZ guiado)"
  - "NfseCockpitController — sub-página 3 (NFS-e modelo 56 nacional NT 2024-001)"
  - "PaletteSearchController — PR #7 ⌘K palette cross-fiscal (US-FISCAL-015 — busca global notas + DF-e)"
  - "SpedController — sub-página 7 + gerar() download EFD-ICMS/IPI (atrás de fiscal.sped_simples_only_lock) + POST /sped/trava (bypass só na sessão do superadmin)"
  - "DasController — apuração do DAS pelo Anexo, só leitura sobre o ApuracaoDasService (US-FISCAL-030), flag useV2FiscalDas"
  - "Services/SpedIcmsIpiGeneratorService — gerador TXT EFD-ICMS/IPI perfil A: os 10 blocos obrigatórios (B, D, G, K e 1 sem dados), Bloco E apuração, Bloco H esqueleto; sem PIS/COFINS"
  - "Services/Sped/EfdIcmsIpiLeiaute — ordem dos blocos (Guia Prático v3.2.3) e COD_VER por ano (NT 2025.001)"
  - "Services/SpedReferenciaArquivoService"
  - "Services/NotasUnifiedService — lista unificada de notas do Cockpit (substituiu o mock)"
  - "Services/SefazCstatService — mapa cstat → tom/rótulo"
  - "Http/Middleware/FiscalLiberado — exige o pacote fiscal_module da empresa em toda rota operacional (#10404)"
  - "Services/FiscalPacote — leitura do pacote fiscal_module usada pelo FiscalLiberado"
  - "Listeners/InvalidaCockpitCacheListener — invalida o cache de KPIs em NFeAutorizada/NFCeAutorizada"
  - "Console/Commands/CertHealthCheckCommand — fiscal:cert-health-check (alerta de vencimento do A1 em mcp_alertas_eventos)"
  - "Console/Commands/HabilitarBusinessCommand — fiscal:habilitar-business {biz}"
not_contains:
  - "Emissão fiscal (XML + SEFAZ) → Modules/NfeBrasil (lê via Service); o ambiente SEFAZ (tpAmb) de manifestação, distribuição e inutilização vem de business.ambiente (Modules/NfeBrasil/Support/AmbienteDoEmitente.php, #10405)"
  - "Cálculo do DAS → Modules/NfeBrasil (ApuracaoDasService); o Fiscal só mostra"
  - "NFSe federal LC 214/2025 → Modules/NFSe (a sub-página 3 /fiscal/nfse só lê)"
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/Forja"
trust_required: L3
owner: wagner
permission_prefix: fiscal.*
charter_adr: 0094
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
  - 0104-processo-mwart-canonico-unico-caminho
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
url_prefixes:
  - /fiscal/*
drift_alerts: []
---

# Modules/Fiscal

## Missão

Cockpit fiscal unificado: agrega visão consolidada de NF-e/NFC-e (modelos 55 + 65), NFS-e (LC 214/2025), Manifesto DF-e, Eventos (CC-e / cancelamento / inutilização), Certificado A1 + Config, e SPED — substituindo telas fragmentadas de `Modules/NfeBrasil` e `Modules/NFSe`.

**Padrão:** thin agregador (espelho de `Modules/Financeiro/Unificado`). Controllers leem Models de NfeBrasil/NFSe via `HasBusinessScope` global scope — **não duplica backend**.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../governance/TRUST-TIERS.md). Persona dupla: Eliana (contadora — leitura, conferência, SPED) + Wagner (operador fiscal — emissão, cancelamento, retransmissão).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida:

- **Lógica de emissão XML + SEFAZ webservice** → vive em `Modules/NfeBrasil` (sped-nfe lib + `NfeEmissaoService`). Fiscal só consome via Service e exibe.
- **Cancelamento cascade** (NFe SEFAZ + Asaas/Inter refund + Whatsapp/email cliente) → orquestrado por `app/Domain/Fsm/CancelarVendaCascade` (ADR 0143). O botão "Cancelar" do drawer, por padrão, cancela **só a nota** (`NfeService::cancelar`, sem FSM); o FSM (`cancelar_venda` via `ExecuteStageActionService`) só entra quando o operador escolhe "a venda junto" (R-NFE-044). *(Até 2026-11-06 esta linha dizia "apenas dispatch action FSM", o que nunca valeu para o caminho padrão.)*
- **NFS-e endpoint SEFIN** → vive em `Modules/NFSe`. Fiscal sub-página 3 lê `NfseEmissao`.

## Acesso (2026-11-05)

Todas as rotas operacionais (`Modules/Fiscal/Routes/web.php`) passam por `CheckUserLogin` e `FiscalLiberado` (pacote `fiscal_module` na assinatura — antes só o menu e a API exigiam, #10404). A permissão fina fica no controller: `fiscal.access` (Cockpit, palette, DAS), `fiscal.nfe.view` (NF-e, eventos e export CSV), `fiscal.nfse.view`, `fiscal.dfe.manage`, `fiscal.config.edit` (inclui o contador), `fiscal.sped.export`, `fiscal.nfe.acoes` (ações em nota) e `superadmin` (trava do SPED). Cada item de menu checa a permissão do controller da rota (#10416), e a busca ⌘K só devolve notas a quem tem `fiscal.nfe.view` e DF-e a quem tem `fiscal.dfe.manage` (#10439). O inventário de arquivos é do [`SUPERFICIE.md`](SUPERFICIE.md) (`node scripts/governance/module-surface.mjs Fiscal --check`).

## Roadmap

| Wave | Entrega | Score impact | Status | PR |
|---|---|---|---|---|
| 1 | NF-e · NFC-e cockpit + drawer SEFAZ guiado | base 0→60 | ✅ mergeado | `8aef3d0fa` (#1183) |
| 2 | Cockpit (#1) + NFS-e (#3) + Eventos (#5) | +20pp | ✅ mergeado | `cabd29661` (#1185) |
| 3 | DF-e (#4) + Cert/Cfg (#6) + SPED (#7 placeholder) | +12pp | ✅ mergeado | `e36e1e272` (#1189) |
| **🎯 7 sub-páginas do design KB-9.75 entregues após Wave 3** | | | | |
| 4 | AcoesController — Cancelar NFe + Manifestar DF-e (4 ações SEFAZ) | +15pp | ✅ mergeado | `d10b117e1` (#1190) |
| 5 | CC-e (110110) + Inutilização faixa numérica | +4pp | ✅ mergeado | #1249 |
| 6 | Retransmitir NFe rejeitada/denegada/erro_envio (preservation contract CONFAZ Art. 14) | +3pp | ✅ mergeado | #1253 |
| 7 | ⌘K palette cross-fiscal (PaletteSearchController + CmdKPalette) | +8pp | ✅ mergeado | #1257 |
| 8 | Gerador SPED EFD-ICMS/IPI MVP saídas (SpedIcmsIpiGeneratorService + 16 registros) | +4pp | ✅ mergeado | #1259 |
| 9 | SPED Bloco E (apuração ICMS) + Bloco H (esqueleto) — 23 registros total | +2pp | ✅ mergeado | #1261 |
| **🎯 Score Capterra Fiscal cockpit ≥ 100/100 pós-Wave 9 (meta do dia — a ficha mediu 75/100 em 2026-07-03, `CAPTERRA-FICHA.md`)** | | | | |
| 10 | EFD-Contribuições PIS/COFINS + saldo credor real E110 + Bloco H dados reais Stock | +3pp | 🔒 backlog | — |

---

- **v1.0.0** (2026-05-20) — SCOPE.md inicial. Módulo Fiscal criado em PR #1183 como thin agregador (sub-página NF-e · NFC-e).
- **v1.1.0** (2026-05-20) — PR #2 Wave consolidada: + CockpitController, NfseCockpitController, EventosController. Roadmap reorganizado (5 PRs vs 7).
- **v1.2.0** (2026-05-20) — PR #3 Wave final: + DfeController, ConfigController, SpedController. **7 sub-páginas do design concluídas**. Próximos PRs: mutações + ⌘K + SPED real.
- **v1.3.0** (2026-05-20) — PR #4 Wave Ações: + AcoesController thin (cancelar NFe + manifestar DF-e 4 ações). Delega Services NfeBrasil existentes.
- **v1.4.0** (2026-05-20) — PR #5 Wave CC-e + Inutilização: + NfeCartaCorrecaoService (Modules/NfeBrasil) + AcoesController.cartaCorrecao + AcoesController.inutilizar (delega NfeInutilizacaoService US-SELL-030). Score Capterra 80→85.
- **v1.5.0** (2026-05-20) — PR #6 Wave Retransmitir: + NfeService.retransmitir (UPDATE preservation contract CONFAZ Art. 14 — NUNCA forceDelete). AcoesController.retransmitir. Score 85→88.
- **v1.6.0** (2026-05-20) — PR #7 Wave ⌘K palette: + PaletteSearchController + CmdKPalette.tsx listener global Cmd/Ctrl+K + FxShell mount. Score 88→96.
- **v1.7.0** (2026-05-20) — PR #8 Wave SPED MVP: + Services/SpedIcmsIpiGeneratorService (gerador EFD-ICMS/IPI v3.1.1 perfil A — 16 registros Blocos 0+C+9) + SpedController.gerar download TXT. Score 96→100.
- **v1.8.0** (2026-05-20) — PR #9 Wave Bloco E + H: expansão SpedIcmsIpiGeneratorService com 7 registros (Bloco E apuração ICMS + Bloco H esqueleto). 23 registros canônicos total — estrutura completa pra validação PVA-EFD CONFAZ. Score 100→102 (acima cap).
- **v1.9.0** (2026-11-06) — Auditoria Fiscal lote 4: `contains` re-alinhado com `git ls-files Modules/Fiscal` (Services, Middleware, Listener e Commands que faltavam); seção Acesso (gate de pacote + permissões, lote 1); ambiente SEFAZ da empresa (lote 0); cancelamento corrigido (só a nota por padrão, FSM só com a venda junto).
