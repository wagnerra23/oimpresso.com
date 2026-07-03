# BRIEFING — Modules/Fiscal

> **Última atualização:** 2026-07-03 (US-FISCAL-022 mergeada — health-check proativo cert A1 · PR #3775)
> **Owner:** Wagner | **Status produção:** 🟢 piloto biz=1 (Wagner empresa) — depende de Modules/NfeBrasil já LIVE
> **Score Capterra Fiscal cockpit:** **102/100** (acima cap — top-3 gaps Bling/Tiny fechados)

## O que é

**Cockpit fiscal unificado** — agregador thin sobre `Modules/NfeBrasil` + `Modules/NFSe` (sem duplicação de backend). 7 sub-páginas conforme design Cowork KB-9.75:

1. **Cockpit** — KPIs do mês + alertas determinísticos + sparklines + quick links
2. **NF-e/NFC-e** — lista consolidada modelos 55+65 + drawer SEFAZ guiado + atalhos J/K
3. **NFS-e** — lista modelo 56 nacional NT 2024-001 + filtros status/competência
4. **DF-e (manifesto)** — NF-e emitidas contra CNPJ + pílula prazo 90d (NT 2014.002)
5. **Eventos** — timeline append-only CC-e + Cancelamento + EPEC + Manifestação
6. **Cert/Cfg** — Certificado A1 + regime + tributação default (read-only)
7. **SPED & Livros** — gerador EFD-ICMS/IPI MVP (download .txt CONFAZ v3.1.1)

Tudo + **5 ações fiscais SEFAZ** (Cancelar, Manifestar, CC-e, Inutilizar, Retransmitir) + **⌘K palette cross-fiscal** (busca global notas + DF-e).

## Cliente piloto em produção

**biz=1 (Wagner WR2 / Office Impresso)** — operador fiscal. Persona dual:
- **Wagner** — emissão, cancelamento, retransmissão (operações live)
- **Eliana (contadora)** — leitura, conferência, SPED Fiscal mensal entrega dia 15

**Larissa ROTA LIVRE biz=4 em pre-canary** (US-FISCAL-018 Onda ESTABILIZAR 2026-05-25 — audit sênior §GAP-FISCAL-001): provisionamento técnico ok (`php artisan fiscal:habilitar-business 4` idempotente — 6 perms `fiscal.*` ao role Admin#4 EXCETO `fiscal.sped.export` que fica bloqueada por feature flag `fiscal.sped_simples_only_lock=true` enquanto GAP-FISCAL-003 não eliminar 6 hardcodes Tier-0 em SpedIcmsIpiGeneratorService). Briefing manual 30min + canary 7d humano-limitado pending Wagner. Post-canary Wagner promove `module_clients.yaml` Fiscal de `piloto_reportando_dor` -> `biz_4_rota_livre_prod`. **Tier 0 IRREVOGÁVEL:** tests SEMPRE biz=1, NUNCA biz=4 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)).

## Capacidades canônicas

| Capacidade | Controller / Service | Status |
|---|---|---|
| Cockpit raiz (KPIs + alertas + sparklines) | `CockpitController` | ✅ ativo |
| Lista NF-e/NFC-e + drawer SEFAZ guiado | `NfeCockpitController` | ✅ ativo |
| Lista NFS-e (modelo 56 nacional) | `NfseCockpitController` | ✅ ativo |
| Manifesto DF-e + prazo 90d | `DfeController` | ✅ ativo |
| Eventos timeline append-only | `EventosController` | ✅ ativo |
| Cert/Cfg fiscal read-only | `ConfigController` | ✅ ativo |
| SPED & Livros + download EFD-ICMS/IPI | `SpedController` + `SpedIcmsIpiGeneratorService` | ✅ ativo (MVP saídas + Bloco E + H esqueleto) |
| Cancelar NFe (FSM cascade ADR 0143) | `AcoesController::cancelarNfe` → `NfeService::cancelar` | ✅ ativo |
| Manifestar DF-e (4 ações) | `AcoesController::manifestarDfe` → `ManifestacaoService` | ✅ ativo |
| Carta de Correção 110110 | `AcoesController::cartaCorrecao` → `NfeCartaCorrecaoService` | ✅ ativo |
| Inutilização faixa numérica | `AcoesController::inutilizar` → `NfeInutilizacaoService` | ✅ ativo |
| Retransmitir rejeitada/denegada/erro_envio | `AcoesController::retransmitir` → `NfeService::retransmitir` | ✅ ativo |
| ⌘K palette cross-fiscal | `PaletteSearchController` + `CmdKPalette.tsx` | ✅ ativo |
| Bloco E SPED apuração ICMS | `SpedIcmsIpiGeneratorService` (E001+E100+E110+E116+E990) | ✅ ativo |
| Health-check proativo cert A1 (cron alerta vencimento ≤30d) | `CertHealthCheckCommand` (`fiscal:cert-health-check`) | ✅ ativo (US-FISCAL-022 — cron 06:30 BRT → `mcp_alertas_eventos`) |

## Stack técnica

- **11 Controllers** em `Modules/Fiscal/Http/Controllers/`: AcoesController, CockpitController, ConfigController, DataController, DfeController, EventosController, InstallController, NfeCockpitController, NfseCockpitController, PaletteSearchController, SpedController
- **1 Service novo do módulo** em `Modules/Fiscal/Services/`: `SpedIcmsIpiGeneratorService` (gerador TXT 23 registros canon)
- **2 Console Commands** em `Modules/Fiscal/Console/Commands/`: `HabilitarBusinessCommand` (`fiscal:habilitar-business`), `CertHealthCheckCommand` (`fiscal:cert-health-check` — cron diário 06:30 BRT, US-FISCAL-022)
- **Pages Inertia (React)** em `resources/js/Pages/Fiscal/`: Cockpit, Nfe, Nfse, Dfe, Eventos, Config, Sped + 3 components (`FxShell`, `NotaDrawer`, `InutilizacaoModal`, `CmdKPalette`)
- **7 RUNBOOK.md** em `memory/requisitos/Fiscal/`: cockpit, nfe, nfse, dfe, eventos, config, sped
- **7 visual-comparison.md** (1 por sub-página)
- **Tests Pest:** 10+ feature tests em `Modules/Fiscal/Tests/Feature/` (incl. `CertHealthCheckCommandTest` biz=1 — ADR 0101) + 2 em `Modules/NfeBrasil/Tests/Feature/` (NfeCartaCorrecao + NfeServiceRetransmitir)
- **Routes:** `Modules/Fiscal/Routes/web.php` — 7 GET sub-páginas + 6 POST ações + 1 GET palette search + 1 GET sped download

## Multi-tenant (ADR 0093)

- **HasBusinessScope automático** em todos os Models lidos: NfeEmissao, NfseEmissao, NfeDfeRecebido, NfeEvento, NfeCertificado, NfeInutilizacao
- **Cross-tenant guard explícito** nos Services novos (defesa em profundidade — Service também valida `session.user.business_id` vs param)
- **Isolamento testado** em Pest biz=1 vs biz=99 (NfeCockpitMultiTenantTest, CockpitMultiTenantTest, etc)

## Permissões Spatie (`fiscal.*`)

- `fiscal.access` — gate cockpit + palette + acesso geral
- `fiscal.nfe.view` — lista NF-e/NFC-e
- `fiscal.nfse.view` — lista NFS-e
- `fiscal.nfe.acoes` — Cancelar/CC-e/Inutilizar/Retransmitir
- `fiscal.dfe.manage` — Manifestar DF-e
- `fiscal.config.edit` — editar Cert/Cfg
- `fiscal.sped.export` — gerar SPED TXT

## Pílulas temporais (UI feedback)

- **Cancelar:** 24h NFC-e (modelo 65) / 168h NF-e (modelo 55) — CONFAZ SINIEF 07/2005 Art. 14
- **CC-e:** 720h (30 dias) da autorização
- **Manifestar DF-e:** 90 dias (NT 2014.002)
- **CC-e sequência:** 1-20 por NFe (mesma chave de acesso)

## Integrações com Modules/NfeBrasil

Fiscal **lê** Models e **chama** Services de NfeBrasil — não duplica backend:

| Função | Service NfeBrasil chamado |
|---|---|
| Cancelar NFe | `NfeService::cancelar` (FSM cascade ADR 0143 — refund + notif cliente em biz=1 live) |
| Manifestar DF-e | `ManifestacaoService::{cienciar,confirmar,desconhecer,naoRealizada}` |
| Carta Correção | `NfeCartaCorrecaoService::aplicar` (novo PR #1249) |
| Inutilização faixa | `NfeInutilizacaoService::inutilizar` (US-SELL-030 existente) |
| Retransmitir | `NfeService::retransmitir` (novo PR #1253 — UPDATE preservation contract Art. 14) |

## Próximos passos (backlog)

- **PR #10** (1+ semana) — EFD-Contribuições PIS/COFINS arquivo separado + saldo credor real em E110 + Bloco H com dados reais Stock (declaração 31/12)
- **Smoke biz=1 prod** — validar TXT EFD-ICMS/IPI no PVA-EFD homologação CONFAZ (Pest browser MCP)
- **Entradas via DF-e manifestada** (Bloco C0 inputs) — exige reconciliação cadastro fornecedor (Modules/Crm)
- **Consolidar detecção de vencimento de cert (1 detector, N sinks)** — a lógica dias-a-vencer ≤30d hoje vive em 4 lugares: `fiscal:cert-health-check` (cron → `mcp_alertas_eventos`, único sink de alerta ao usuário), `nfe:health` (log ops, não-agendado — docblock "06:05 BRT" é claim stale), `nfse:health` (check status) e `ConfigController` (UI estática). US-FISCAL-022 fechou o gap do **alerta proativo** (cap #13); um refactor futuro pode centralizar a detecção num único helper e deixar os health-commands consumirem dele. Não-urgente — decisão Wagner 2026-07-03 foi manter como está.

## Referências

- SPEC: [`memory/requisitos/Fiscal/SPEC.md`](SPEC.md) — US-FISCAL-001 até US-FISCAL-017
- SCOPE: [`Modules/Fiscal/SCOPE.md`](../../../Modules/Fiscal/SCOPE.md)
- RUNBOOKs: `RUNBOOK-{cockpit,nfe,nfse,dfe,eventos,config,sped}.md`
- ADRs canônicas: [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) (multi-tenant), [0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) (Constituição v2), [0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) (tests biz=1), [0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) (MWART), [0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) (Cowork loop), [0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) (FSM cancel cascade)
- Layout SPED: CONFAZ Guia Prático EFD-ICMS/IPI v3.1.1 (Ajuste SINIEF 02/2009)
