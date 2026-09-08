---
id: requisitos-fiscal-briefing
module: Fiscal
status: parcial
updated_at: "2026-09-06"
distilled_at: "2026-09-06"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Fiscal (verdade destilada)

## Estado atual
Cockpit fiscal unificado — agregador fino que lê `Modules/NfeBrasil` e `Modules/NFSe`. Status parcial: piloto em biz=1, com `Modules/NfeBrasil` já LIVE como pré-condição (Wagner emite, Eliana confere e entrega o SPED; o dia 15 exibido no painel é heurística — o prazo legal da EFD é por UF); a exportação do SPED segue atrás da trava `fiscal.sped_simples_only_lock=true`. Contratos de tela (SDD + `casos.md`) desde 2026-07-27 (#4891); a configuração fiscal ganhou ações de risco em 2026-09-04. Multi-tenant Tier 0 (`HasBusinessScope` nos Models + Pest cross-tenant); permissões `fiscal.*` provisionadas pelo comando idempotente `fiscal:habilitar-business`, que não concede duas: `fiscal.sped.export` (bloqueada até GAP-FISCAL-003) e `fiscal.config.ambiente` (ato de [W]); o gerador do SPED (`SpedIcmsIpiGeneratorService`) tem guard cross-tenant explícito além do global scope; o `NotasUnifiedService` confia só no `HasBusinessScope` (os demais Services do módulo não leem dado de tenant). Contagem viva: `node scripts/governance/requisitos-status.mjs Fiscal`.

## Capacidades
- Cockpit com KPIs, sparklines e fila de alertas (inclui certificado vencido).
- NF-e/NFC-e: listagem, detalhe, drawer SEFAZ guiado e atalhos J/K. NFS-e (modelo 56 nacional, NT 2024-001): apenas listagem com filtros e busca — sem detalhe, sem ações, sem drawer nem J/K (`GET /fiscal/nfse` é a única rota; a tela se declara `em-implementacao`).
- Manifesto DF-e — notas emitidas contra o CNPJ, com pílula de prazo calculada de `prazo_confirmacao_em`, que o próprio importador (`DistribuicaoDfeService`) grava como `data_emissao + 180d` (NT 2014.002) — não é prazo devolvido pela SEFAZ; manifestação em lote com falha parcial nomeada.
- Eventos em timeline append-only, com export.
- Certificado A1, ambiente SEFAZ e regime + tributação default: leitura exige `superadmin` ou `fiscal.config.edit`; trocar ambiente e substituir certificado exigem `fiscal.config.ambiente` (ou `superadmin`) em `CertificadoController::garantirGateAmbiente` — gate próprio, independente de `fiscal.config.edit`, recusado no servidor.
- SPED & Livros: gerador EFD-ICMS/IPI. A prévia do TXT do período é ausência declarada (`previaTxt: null`, decisão [W] pendente); a tela mostra a prévia de um arquivo de referência medido.
- Saúde do certificado A1 por cron (`fiscal:cert-health-check`, US-FISCAL-022) alimentando `mcp_alertas_eventos`.
- Ações: cancelar (janela 24h NFC-e / 168h NF-e, CONFAZ SINIEF 07/2005 Art. 14 — `NfeCockpitController`), carta de correção (janela 720h — `AcoesController`), manifestar, inutilizar e retransmitir.
- Busca de notas pela palette ⌘K.

## Gaps
- SPED incompleto (GAP-FISCAL-003): o gap nasceu com seis hardcodes Tier-0 no gerador (a lista vive no cabeçalho do `SpedIcmsIpiGeneratorService`; a permissão que fica bloqueada até o gap fechar está em `HabilitarBusinessCommand.php`, constante `PERMS_BLOQUEADAS_ATE_GAP_003`); o resíduo hoje é o `COD_MUN` placeholder UF+0000 — e a trava global de exportação segue ligada até ele fechar.
- biz=4 (ROTA LIVRE) segue em pré-canary: falta briefing manual + canary de 7 dias — relógio humano, não integração (US-FISCAL-018 `_parcial_`; estado do canary em `config/governance/module_clients.yaml` — pós-canary, promover `piloto_reportando_dor` → `biz_4_rota_livre_prod`).

## Última mudança
Onda 10 do SPED (2026-09-04) — prévia do arquivo de referência, bypass de superadmin explícito e cartão de validação externa (#6723/#6728/#6741); manifestação DF-e em lote (#6740) e as ações de manifestar que não manifestavam (#6727).

## Proveniência (destilado de)

- audit `requisitos/Fiscal/AUDIT-SENIOR-2026-05-25.md` — AUDIT-SENIOR-2026-05-25.md
- audit `requisitos/Fiscal/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/Fiscal/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-09-04-fiscal-onda10-sped-goals-cowork.md` (2026-09-04) — 2026-09-04-fiscal-onda10-sped-goals-cowork.md
- handoff `handoffs/2026-09-04-1215-fiscal-onda10-sped-4-de-5-goals.md` (2026-09-04) — 2026-09-04-1215-fiscal-onda10-sped-4-de-5-goals.md
- session `sessions/2026-09-03-fiscal-onda9-sped-regua-golden.md` (2026-09-03) — 2026-09-03-fiscal-onda9-sped-regua-golden.md
- handoff `handoffs/2026-09-03-2040-fiscal-onda9-sped-regua-golden.md` (2026-09-03) — 2026-09-03-2040-fiscal-onda9-sped-regua-golden.md
- handoff `handoffs/2026-09-02-0804-fiscal-onda0-e-consertos-de-gates.md` (2026-09-02) — 2026-09-02-0804-fiscal-onda0-e-consertos-de-gates.md
- session `sessions/2026-09-01-fiscal-f0-screen-coverage.md` (2026-09-01) — 2026-09-01-fiscal-f0-screen-coverage.md
- handoff `handoffs/2026-08-28-1040-design-sync-recibos-fiscal.md` (2026-08-28) — 2026-08-28-1040-design-sync-recibos-fiscal.md
- session `sessions/2026-08-14-censo-redacao-brl-em-codigo.md` (2026-08-14) — 2026-08-14-censo-redacao-brl-em-codigo.md
- session `sessions/2026-08-10-mudos-eixo2-ligados-e-o-bug-que-o-skip-escondia.md` (2026-08-10) — 2026-08-10-mudos-eixo2-ligados-e-o-bug-que-o-skip-escondia.md
- session `sessions/2026-07-28-sdd-nfebrasil-emissao-fiscal.md` (2026-07-28) — 2026-07-28-sdd-nfebrasil-emissao-fiscal.md
- session `sessions/2026-07-27-sdd-fiscal-nfe.md` (2026-07-27) — 2026-07-27-sdd-fiscal-nfe.md
