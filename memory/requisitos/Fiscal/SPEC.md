---
module: Fiscal
status: ativo
version: "1.9.0"
last_updated: "2026-05-25"
piloto: oimpresso biz=1 (Wagner empresa) — depende de NfeBrasil já produção
last_review: "2026-05-25"
owners: [W]
parent_adr: 0094-constituicao-v2-7-camadas-8-principios
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0101-tests-business-id-1-nunca-cliente
  - 0104-processo-mwart-canonico-unico-caminho
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
implementacao_em_curso: PR Onda ESTABILIZAR — GAP-FISCAL-001 + GAP-FISCAL-002 (audit sênior 2026-05-25)
na_justified:
  D7.a: "Fiscal cockpit é leitura agregada — não emite. PII (CPF/CNPJ destinatário) vem de NfeBrasil via getActivitylogOptions excluindo PII (PII-LGPD-FISCAL.md). Cockpit apenas exibe via Inertia props já redacted no Service NfeBrasil."
---

# Especificação funcional — Fiscal (Cockpit unificado)

> Convenção do ID: `US-FISCAL-NNN` para user stories, `R-FISCAL-NNN` para regras Gherkin.
> Campo `Implementado em` linka com a página React.
>
> **Módulo thin agregador** — NÃO contém lógica fiscal própria (emissão, SEFAZ, cancelamento). Lê `Modules/NfeBrasil` + `Modules/NFSe` via Services. Pareado com [SCOPE.md](../../../Modules/Fiscal/SCOPE.md).

## 1. Glossário rápido

- **Cockpit fiscal** — visão unificada agregando NF-e/NFC-e + NFS-e + DF-e + Eventos + Cert/Cfg + SPED
- **Sub-página** — uma das 7 telas do design KB-9.75 (Cockpit, NF-e, NFS-e, DF-e, Eventos, Cert, SPED)
- **SEFAZ pill** — badge colorido por tom (ok/warn/bad) com código cstat + label + hint
- **Pílula temporal** — chip mostrando prazo restante de ação legal (cancel 24h NFC-e / 168h NF-e, CC-e 30d)
- **Mapa "Jana sugere"** — receita determinística por cstat rejeitado (substitui IA real per R#2 KB-9.75)

Vocabulário completo NFe/NFSe em [NfeBrasil/GLOSSARY.md](../NfeBrasil/GLOSSARY.md).

## User stories

### US-FISCAL-001 · Cockpit NF-e · NFC-e (sub-página 2)

> **Área:** Fiscal/Nfe
> **Rota:** `GET /fiscal/nfe`
> **Controller/ação:** `NfeCockpitController@index`
> **Permissão Spatie:** `fiscal.nfe.view`
> **Status:** PR #1 #1183

**Como** contador (Eliana) ou operador fiscal (Wagner)
**Quero** ver lista consolidada de NF-e + NFC-e emitidas com status SEFAZ legível, janela legal de cancelamento, e drawer detalhado com mapa SEFAZ guiado
**Para** identificar rejeições, agir dentro da janela de 24h (NFC-e) / 168h (NF-e), e resolver bloqueios sem precisar abrir 4 telas separadas

**Implementado em:** [`resources/js/Pages/Fiscal/Nfe.tsx`](../../../resources/js/Pages/Fiscal/Nfe.tsx) · [`Modules/Fiscal/Http/Controllers/NfeCockpitController.php`](../../../Modules/Fiscal/Http/Controllers/NfeCockpitController.php)

**Definition of Done:**
- [x] Lista paginada `NfeEmissao` via `HasBusinessScope` global scope (ADR 0093 multi-tenant Tier 0)
- [x] Sub-tabs NFe(55) / NFCe(65) / Entrada (entrada = empty state com link Compras)
- [x] Filtros chip-row: Todas, Autorizadas, Rejeitadas, Janela 24h, Processando
- [x] Tabela com Número (mono) + Chave truncada + SEFAZ pill (tone ok/warn/bad) + Valor + Emissão
- [x] Pílula temporal "cancelar em Xh" inline na linha
- [x] Atalhos J/K (navega cursor) + Enter (abre drawer) + search box
- [x] `Inertia::defer` em rows (skill `inertia-defer-default`)
- [x] Drawer slide-in com SEFAZ pill expandida + dados destinatário + operação + mapa "Jana sugere" se cstat rejeitado
- [x] Pest test biz=1 vs biz=99 (ADR 0101 — global scope, isCancelavel, sefazCodes mapping)
- [x] Charter `Nfe.charter.md` com Goals + Non-Goals + Anti-hooks
- [x] CSS scoped `.fx-page` (não vaza tokens fiscais pra outras telas)

### US-FISCAL-002 · Cockpit (sub-página 1) — ✅ PR #2 Wave

> **Rota:** `GET /fiscal`
> **Controller:** `CockpitController@index`
> **Permissão:** `fiscal.access`
> **Page:** `resources/js/Pages/Fiscal/Cockpit.tsx`

**Implementado em:** [`resources/js/Pages/Fiscal/Cockpit.tsx`](../../../resources/js/Pages/Fiscal/Cockpit.tsx) · [`Modules/Fiscal/Http/Controllers/CockpitController.php`](../../../Modules/Fiscal/Http/Controllers/CockpitController.php) · verificado@176f9bc (2026-07-01)

**Como** contador (Eliana) ou operador (Wagner)
**Quero** visão consolidada do estado fiscal do mês em <3s (KPIs + sparklines + alertas + quick links)
**Para** identificar pendências sem precisar abrir 6 telas

**Definition of Done:**
- [x] 6 KPIs eager (emitidas/autorizadas/rejeitadas/faturamento/dfe/cert)
- [x] Sparklines SVG inline 14d (4 séries)
- [x] Alertas determinísticos PHP (3 níveis crit/warn/info — sem LLM)
- [x] 6 quick-link cards (4 ativos sub-pages 2/3/5 + 3 disabled)
- [x] HasBusinessScope nos 4 Models (NfeEmissao/NfseEmissao/NfeDfeRecebido/NfeCertificado)
- [x] Permissão `fiscal.access`
- [x] Pest biz=1 (`CockpitMultiTenantTest`)
- [x] Charter + RUNBOOK + visual-comparison

### US-FISCAL-003 · ⌘K palette cross-fiscal — **backlog PR #3**

**Implementado em:** _pendente_ — stub de backlog substituído por US-FISCAL-015 (⌘K palette entregue: `PaletteSearchController` + `CmdKPalette.tsx`). Consolidar/arquivar este stub.

Busca global em notas + DF-e + ações rápidas. Detalhe quando entregar.

### US-FISCAL-004 · Ações de mutação — **backlog PR #4**

**Implementado em:** _pendente_ — stub de backlog dividido/entregue em US-FISCAL-012 (Cancelar + Manifestar DF-e), US-FISCAL-013 (CC-e + Inutilizar) e US-FISCAL-014 (Retransmitir). Consolidar/arquivar este stub.

Cancelar / Retransmitir / CC-e / Inutilizar. Chama Services `Modules/NfeBrasil` existentes via Job. NOT habilitado neste PR #1 (botões disabled no drawer).

### US-FISCAL-005 · NFS-e (sub-página 3) — ✅ PR #2 Wave

> **Rota:** `GET /fiscal/nfse`
> **Controller:** `NfseCockpitController@index`
> **Permissão:** `fiscal.nfse.view`
> **Page:** `resources/js/Pages/Fiscal/Nfse.tsx`

**Implementado em:** [`resources/js/Pages/Fiscal/Nfse.tsx`](../../../resources/js/Pages/Fiscal/Nfse.tsx) · [`Modules/Fiscal/Http/Controllers/NfseCockpitController.php`](../../../Modules/Fiscal/Http/Controllers/NfseCockpitController.php) · verificado@176f9bc (2026-07-01)

**Como** contador
**Quero** lista de NFS-e (modelo 56 nacional NT 2024-001) com filtros status + competência + busca
**Para** conferir emissões do mês sem abrir o módulo NFSe legacy

**Definition of Done:**
- [x] Lista paginada `NfseEmissao` (HasBusinessScope) — modelo 56 nacional
- [x] 5 chips status + month picker + search (núm/cód.verificação/CPF/CNPJ tomador)
- [x] Inertia::defer em rows
- [x] Permissão `fiscal.nfse.view` (nova)
- [x] Pest biz=1 (`NfseCockpitMultiTenantTest`)
- [x] Charter + RUNBOOK + visual-comparison

### US-FISCAL-006 · Manifesto DF-e (sub-página 4) — **backlog PR #6**

**Implementado em:** _pendente_ — stub de backlog substituído por US-FISCAL-008 (sub-página DF-e entregue: `Dfe.tsx` + `DfeController`). Consolidar/arquivar este stub.

Lê `Modules/NfeBrasil/Models/NfeDfeRecebido`.

### US-FISCAL-007 · Eventos (sub-página 5) — ✅ PR #2 Wave

> **Rota:** `GET /fiscal/eventos`
> **Controller:** `EventosController@index`
> **Permissão:** `fiscal.access`
> **Page:** `resources/js/Pages/Fiscal/Eventos.tsx`

**Implementado em:** [`resources/js/Pages/Fiscal/Eventos.tsx`](../../../resources/js/Pages/Fiscal/Eventos.tsx) · [`Modules/Fiscal/Http/Controllers/EventosController.php`](../../../Modules/Fiscal/Http/Controllers/EventosController.php) · verificado@176f9bc (2026-07-01)

**Como** contador
**Quero** timeline de eventos SEFAZ aplicados (CC-e + Cancelamento + EPEC + Manifestação)
**Para** auditoria LGPD Art. 37 + revisão fiscal

**Definition of Done:**
- [x] Timeline append-only `NfeEvento` (HasBusinessScope — `UPDATED_AT = null`)
- [x] Filtros por tipo (todos/cce/cancel/epec/manifest) + período 7/30/90d
- [x] Eager `with('emissao')` pra link cross-página
- [x] Inertia::defer em rows
- [x] Permissão `fiscal.access` (gate único — eventos são audit)
- [x] Pest biz=1 (`EventosCockpitMultiTenantTest`)
- [x] Charter + RUNBOOK + visual-comparison

### US-FISCAL-008 · DF-e manifesto (sub-página 4) — ✅ PR #3 Wave

> **Rota:** `GET /fiscal/dfe` · **Permissão:** `fiscal.dfe.manage`

**Implementado em:** [`resources/js/Pages/Fiscal/Dfe.tsx`](../../../resources/js/Pages/Fiscal/Dfe.tsx) · [`Modules/Fiscal/Http/Controllers/DfeController.php`](../../../Modules/Fiscal/Http/Controllers/DfeController.php) · verificado@176f9bc (2026-07-01)

**Como** contador
**Quero** lista de NF-e emitidas CONTRA o CNPJ com filtros + pílula de prazo 90d
**Para** manifestar dentro do prazo legal (CONFAZ)

**DoD:**
- [x] Lista paginada NfeDfeRecebido (HasBusinessScope)
- [x] 5 chips status + busca chave/CNPJ/nome
- [x] Pílula temporal prazo (crit <7d / warn <30d / ok)
- [x] Pest biz=1 (`DfeControllerTest`) + Charter + RUNBOOK + visual-comparison

### US-FISCAL-009 · Cert/Cfg fiscal (sub-página 6) — ✅ PR #3 Wave

> **Rota:** `GET /fiscal/config` · **Permissão:** `fiscal.config.edit`

**Implementado em:** [`resources/js/Pages/Fiscal/Config.tsx`](../../../resources/js/Pages/Fiscal/Config.tsx) · [`Modules/Fiscal/Http/Controllers/ConfigController.php`](../../../Modules/Fiscal/Http/Controllers/ConfigController.php) · verificado@176f9bc (2026-07-01)

**Como** admin
**Quero** visão consolidada do cert A1 + regime + tributação default
**Para** confirmar status sem abrir múltiplas telas NfeBrasil

**DoD:**
- [x] Status cert A1 (NfeCertificado — `encrypted_password` $hidden)
- [x] Regime + auto_emission + tributacao_default
- [x] Tone urgência cert (bad ≤7d, warn ≤60d)
- [x] Read-only by design (link "Editar" → NfeBrasil canon)
- [x] Pest biz=1 (`ConfigControllerTest`) + Charter + RUNBOOK + visual-comparison

### US-FISCAL-010 · SPED & Livros (sub-página 7) — ✅ PR #3 Wave (placeholder)

> **Rota:** `GET /fiscal/sped` · **Permissão:** `fiscal.sped.export`

**Implementado em:** [`resources/js/Pages/Fiscal/Sped.tsx`](../../../resources/js/Pages/Fiscal/Sped.tsx) · [`Modules/Fiscal/Http/Controllers/SpedController.php`](../../../Modules/Fiscal/Http/Controllers/SpedController.php) · verificado@176f9bc (2026-07-01) — sub-página panorama (export real entregue depois em US-FISCAL-016/017)

**Como** contador
**Quero** panorama dos últimos 5 meses com status apuração + contagem agregada
**Para** ter referência cru enquanto gerador SPED canônico não existe

**DoD:**
- [x] Tabela 5 últimos meses (NfeEmissao autorizadas agregadas)
- [x] Status heurístico (aberto/pronto/entregue) + prazo entrega
- [x] Notice claro "em desenvolvimento"
- [x] Export buttons disabled (anti-hook charter)
- [x] Pest biz=1 (`SpedControllerTest` — anti-hook: gerador real NÃO existe)
- [x] Charter + RUNBOOK + visual-comparison

### US-FISCAL-013 · CC-e (Carta de Correção) + Inutilização faixa — ✅ PR #5 Wave

> **Rotas:**
> - `POST /fiscal/acoes/nfe/{emissao}/cce` (perm `fiscal.nfe.acoes`)
> - `POST /fiscal/acoes/nfe/inutilizar` (perm `fiscal.nfe.acoes`)
> **Controller:** `AcoesController@cartaCorrecao`, `AcoesController@inutilizar`
> **Services:** `NfeCartaCorrecaoService` (novo), `NfeInutilizacaoService` (já existia US-SELL-030)

**Implementado em:** [`Modules/Fiscal/Http/Controllers/AcoesController.php`](../../../Modules/Fiscal/Http/Controllers/AcoesController.php) · [`resources/js/Pages/Fiscal/_components/InutilizacaoModal.tsx`](../../../resources/js/Pages/Fiscal/_components/InutilizacaoModal.tsx) · [`resources/js/Pages/Fiscal/_components/NotaDrawer.tsx`](../../../resources/js/Pages/Fiscal/_components/NotaDrawer.tsx) · verificado@176f9bc (2026-07-01) — Services de emissão `NfeCartaCorrecaoService`/`NfeInutilizacaoService` vivem em `Modules/NfeBrasil`

**Como** contador/operador
**Quero** aplicar Carta de Correção em NF-e autorizada (sem alterar valores) e inutilizar faixa numérica de notas rejeitadas direto do cockpit Fiscal
**Para** corrigir erros textuais (endereço/info compl) sem cancelar+reemitir, e fechar buracos no sequencial fiscal sem multa anual

**DoD:**
- [x] `NfeCartaCorrecaoService::aplicar(biz, emissaoId, textoCorrecao, nSeqEvento)` — novo Service espelhado em `NfeInutilizacaoService` (não inflar `NfeService` 900 linhas)
- [x] Validação texto correção 15-1000 chars (CONFAZ SINIEF 07/2005 Art. 14)
- [x] Validação `n_seq_evento` 1-20 (regra SEFAZ — máx 20 CC-e por NFe)
- [x] Janela 720h (30d) da autorização (`emitido_em`)
- [x] Idempotência (emissao_id, n_seq_evento) — re-chamar mesma sequência retorna evento existente
- [x] Cross-tenant guard explícito + global scope (ADR 0093)
- [x] Persistência `NfeEvento(tipo='110110', justificativa=textoCorrecao, payload_json.n_seq_evento)`
- [x] OTel span `nfe.cce` + atributos (biz, emissao_id, n_seq_evento)
- [x] Inutilização delega `NfeInutilizacaoService::inutilizar` (Service já validado US-SELL-030)
- [x] Throttle 30/min anti-DOS (protege webservice SEFAZ)
- [x] UI: NotaDrawer botão CC-e habilitado pra `status='autorizada'` + modal texto correção
- [x] UI: Nfe.tsx header botão "Inutilizar faixa" + modal modelo/série/range/justificativa (componente extraído `_components/InutilizacaoModal.tsx`)
- [x] Pest `AcoesControllerTest` cobre validação CC-e + Inutilização + métodos
- [x] Pest `NfeCartaCorrecaoServiceTest` cobre Service contract (validarEntrada + cross-tenant)

**Non-Goals (futuro PR):**
- ❌ Retransmitir nota rejeitada (exige re-build payload — PR #6 dedicado)
- ❌ CC-e UI listar histórico de sequências aplicadas (next iter — drawer mostra só ação atual)
- ❌ Inutilização batch (faixa única por submit; ranges múltiplos = N submits)

### US-FISCAL-014 · Retransmitir NFe rejeitada/denegada — ✅ PR #6 Wave

> **Rota:** `POST /fiscal/acoes/nfe/{emissao}/retransmitir` (perm `fiscal.nfe.acoes`)
> **Controller:** `AcoesController@retransmitir` · **Service:** `NfeService::retransmitir` (novo método público)

**Implementado em:** [`Modules/Fiscal/Http/Controllers/AcoesController.php`](../../../Modules/Fiscal/Http/Controllers/AcoesController.php) · [`resources/js/Pages/Fiscal/_components/NotaDrawer.tsx`](../../../resources/js/Pages/Fiscal/_components/NotaDrawer.tsx) · verificado@176f9bc (2026-07-01) — a emissão `NfeService::retransmitir` vive em `Modules/NfeBrasil`

**Como** contador/operador
**Quero** retransmitir NFe rejeitada/denegada/erro_envio direto do cockpit
**Para** corrigir erros transientes (rede/timeout/duplicidade) ou pós-correção de cadastro sem refazer venda

**DoD:**
- [x] `NfeService::retransmitir(int $biz, int $emissaoId): NfeEmissao` método público novo
- [x] Whitelist status: `rejeitada` / `denegada` / `erro_envio`
- [x] Estratégia (Tier 0 CONFAZ Art. 14 preservation contract — Wave26/27 saturation): UPDATE antiga `status='inutilizada'` + `transaction_id=null` + metadata.original_transaction_id (libera UNIQUE biz+tx) + `emitirParaTransaction` novo número
- [x] NUNCA usa `->forceDelete()` — documento fiscal imutável CONFAZ Art. 14
- [x] Audit via Spatie LogsActivity D7
- [x] OTel span `nfe.retransmitir`
- [x] NotaDrawer botão Retransmitir habilitado pra rejeitada/denegada/erro_envio + modal confirm explicativo
- [x] Pest contracts + signature + route registered

**Non-Goals:**
- ❌ Inutilização SEFAZ formal do número antigo (cliente roda Inutilizar faixa PR #5)
- ❌ Retransmissão de emissões manuais (transaction_id null)
- ❌ Correção automática de cstat-causa-raiz (usuário corrige cadastro antes)

### US-FISCAL-015 · ⌘K palette cross-fiscal — ✅ PR #7 Wave

> **Rota:** `GET /fiscal/palette/search?q={query}` (perm `fiscal.access`)
> **Controller:** `PaletteSearchController@search` · **Component:** `CmdKPalette.tsx`

**Implementado em:** [`Modules/Fiscal/Http/Controllers/PaletteSearchController.php`](../../../Modules/Fiscal/Http/Controllers/PaletteSearchController.php) · [`resources/js/Pages/Fiscal/_components/CmdKPalette.tsx`](../../../resources/js/Pages/Fiscal/_components/CmdKPalette.tsx) · verificado@176f9bc (2026-07-01)

**Como** contador/operador
**Quero** buscar instantaneamente qualquer NF-e/NFC-e ou DF-e via Cmd+K (Mac) ou Ctrl+K (Win/Linux)
**Para** chegar em <2s na nota errada sem navegar entre sub-páginas

**DoD:**
- [x] Endpoint JSON validação 2-50 chars + permission gate + throttle 60/min
- [x] Multi-tenant via HasBusinessScope (ADR 0093)
- [x] 2 categorias top 5 (notas + dfe), LIKE em colunas indexáveis
- [x] `CmdKPalette.tsx` listener global Cmd/Ctrl+K + modal debounced 200ms
- [x] Atalhos ⌘K abre/fecha · Esc fecha · ↑↓ navega · Enter abre URL
- [x] FxShell mount global + botão Buscar habilitado
- [x] Pest contracts (validation + class + route)

**Non-Goals:** ações inline no palette, histórico de buscas, busca semântica/fuzzy.

### US-FISCAL-016 · Gerador SPED EFD-ICMS/IPI MVP — ✅ PR #8 Wave

> **Rota:** `GET /fiscal/sped/icms-ipi/{ano}/{mes}` (perm `fiscal.sped.export`)
> **Controller:** `SpedController@gerar` · **Service:** `Modules\Fiscal\Services\SpedIcmsIpiGeneratorService` (novo)

**Implementado em:** [`Modules/Fiscal/Http/Controllers/SpedController.php`](../../../Modules/Fiscal/Http/Controllers/SpedController.php) · [`Modules/Fiscal/Services/SpedIcmsIpiGeneratorService.php`](../../../Modules/Fiscal/Services/SpedIcmsIpiGeneratorService.php) · verificado@176f9bc (2026-07-01)

**Como** contador/Eliana
**Quero** baixar arquivo TXT EFD-ICMS/IPI direto do cockpit pra importar no PVA-EFD CONFAZ
**Para** entregar SPED Fiscal dentro do prazo dia 15

**DoD:**
- [x] Layout CONFAZ Guia Prático v3.1.1 perfil A (COD_VER=018)
- [x] 16 registros canônicos: Bloco 0 + Bloco C + Bloco 9
- [x] HasBusinessScope + cross-tenant guard (ADR 0093)
- [x] OTel span `fiscal.sped.gerar` + throttle 3/min
- [x] Sped.tsx botão Download habilitado quando notasAutorizadas > 0

**Non-Goals:** Bloco E, Bloco H, Bloco D, entradas DF-e, PIS/COFINS.

### US-FISCAL-017 · SPED EFD-ICMS/IPI Bloco E + Bloco H — ✅ PR #9 Wave

> **Service:** `SpedIcmsIpiGeneratorService` (expansão US-FISCAL-016)
> **Rota:** mesma `GET /fiscal/sped/icms-ipi/{ano}/{mes}` — arquivo agora inclui Bloco E + H

**Implementado em:** [`Modules/Fiscal/Services/SpedIcmsIpiGeneratorService.php`](../../../Modules/Fiscal/Services/SpedIcmsIpiGeneratorService.php) · verificado@176f9bc (2026-07-01) — expansão US-FISCAL-016 (registros `registroE110`/`registroH001`)

**Como** contador
**Quero** TXT SPED estruturalmente completo (Bloco E apuração + Bloco H esqueleto)
**Para** importar no PVA-EFD CONFAZ sem erro de "Bloco E ausente"

**DoD:**
- [x] Bloco E: `E001` + `E100` + `E110` (apuração consolidada via `array_sum` C190) + `E116` (condicional débitos > 0) + `E990`
- [x] Bloco H: `H001` (sempre IND_MOV=1 MVP) + `H990`
- [x] Bloco 9900 contadores incluem automaticamente os 7 tipos novos
- [x] Cobertura SPED: 23 registros canônicos

**Non-Goals:** saldo credor anterior real, Bloco H com dados Stock, ajustes E110 detalhados, EFD-Contribuições PIS/COFINS (backlog PR #10).

### US-FISCAL-011 · SPED Fiscal complete + PIS/COFINS — **backlog PR #10**

**Implementado em:** _pendente_ — backlog PR #10 (EFD-Contribuições PIS/COFINS + saldo credor real E110 + Bloco H com inventário real); Fase 2 GAP-7 de US-FISCAL-020. Nenhum código entregue ainda.

EFD-Contribuições (PIS/COFINS arquivo separado) + saldo credor real E110 + Bloco H com dados reais inventário 31/12.

### US-FISCAL-012 · Ações mutação NFe + DF-e — ✅ PR #4 Wave

> **Rotas:**
> - `POST /fiscal/acoes/nfe/{emissao}/cancelar` (perm `fiscal.nfe.acoes`)
> - `POST /fiscal/acoes/dfe/{recebido}/{acao}` (perm `fiscal.dfe.manage`, acao=cienciar|confirmar|desconhecer|nao_realizada)
> **Controller:** `AcoesController`

**Implementado em:** [`Modules/Fiscal/Http/Controllers/AcoesController.php`](../../../Modules/Fiscal/Http/Controllers/AcoesController.php) · [`resources/js/Pages/Fiscal/Dfe.tsx`](../../../resources/js/Pages/Fiscal/Dfe.tsx) · [`resources/js/Pages/Fiscal/_components/NotaDrawer.tsx`](../../../resources/js/Pages/Fiscal/_components/NotaDrawer.tsx) · verificado@176f9bc (2026-07-01) — Services `NfeService::cancelar`/`ManifestacaoService` vivem em `Modules/NfeBrasil`

**Como** contador/operador
**Quero** cancelar NFe autorizada (FSM cascade ADR 0143) e manifestar DF-e (4 ações SEFAZ) direto do cockpit Fiscal
**Para** não precisar abrir Modules/NfeBrasil canon pra ações operacionais

**DoD:**
- [x] AcoesController thin que delega NfeService::cancelar + ManifestacaoService
- [x] Validação motivo/justificativa ≥15 chars (regra CONFAZ SINIEF 07/2005)
- [x] Whitelist acao DF-e (4 valores) + guard 404
- [x] Throttle 30/min anti-DOS (protege SEFAZ webservice)
- [x] NotaDrawer botão Cancelar habilitado + modal motivo
- [x] Dfe.tsx coluna Ações com 4 botões + modal para desconhecer/nao_realizada
- [x] Pest `AcoesControllerTest` (validação + whitelist + métodos existentes)
- [x] back()->with('flash') — usuário fica na tela Fiscal

**Non-Goals (futuro PR):**
- ❌ Retransmitir nota rejeitada (exige re-build payload — Service NfeBrasil)
- ❌ Carta de Correção (CC-e) (exige texto correção 15-1000 + sequência)
- ❌ Inutilização faixa numérica (exige modal faixa início/fim + validação SEFAZ)

## 3. Regras Gherkin

### R-FISCAL-001 · Isolation multi-tenant Tier 0 (ADR 0093)

```
Given um usuário autenticado com business_id=1
And NfeEmissao tem registros pra business_id=1 e business_id=99
When ele acessa GET /fiscal/nfe
Then a lista deve conter SOMENTE notas business_id=1
And counts (rejeitadas, autorizadas) refletem somente business_id=1
```

Test: `Modules/Fiscal/Tests/Feature/NfeCockpitMultiTenantTest::it global scope HasBusinessScope esconde emissões cross-tenant na contagem do cockpit`.

### R-FISCAL-002 · Janela legal cancelamento (CONFAZ SINIEF 07/2005 Art. 14)

```
Given uma NF-e (modelo 55) autorizada emitida há 100h
When o cockpit calcula isCancelavel
Then deve retornar true (porque 100h < 168h prazo NF-e)

Given uma NFC-e (modelo 65) autorizada emitida há 30h
When o cockpit calcula isCancelavel
Then deve retornar false (porque 30h > 24h prazo NFC-e)
```

Test: `NfeCockpitMultiTenantTest::it isCancelavel respeita janela legal 24h NFC-e (modelo 65) vs 168h NF-e (modelo 55)`.

### R-FISCAL-003 · Permission gate por sub-feature

```
Given um usuário com permission fiscal.access mas NÃO fiscal.nfe.view
When ele acessa GET /fiscal/nfe
Then deve receber 403 Forbidden
```

(superadmin bypass via `auth()->user()->can('superadmin')` cobre todos gates fiscal.*).

## 4. Não-goals (PR #1)

- Ações de mutação (Cancelar/Retransmitir/CC-e/Inutilizar) — drawer mostra botões desabilitados com title="PR seguinte"
- Emissão nova (botão Emitir disabled)
- ⌘K palette completa
- JOIN com `transactions`/`contacts` pra dest_name correto (fallback `metadata->dest_name` neste PR)
- NFS-e, DF-e, SPED, Cert/Cfg, Eventos (sub-páginas separadas)

## Backlog ativo (Roadmap PRs)

| PR | Sub-página(s) | Esforço IA-pair | Score impact | Status |
|---|---|---|---|---|
| #1 #1183 | NF-e · NFC-e (cockpit + drawer) | 1 dia | base 0→60/100 | ✅ mergeado `8aef3d0fa` |
| #2 #1185 (Wave) | Cockpit (1) + NFS-e (3) + Eventos (5) | 1 dia | +20pp | ✅ mergeado `cabd29661` |
| #3 #1189 (Wave) | DF-e (4) + Cert/Cfg (6) + SPED (7) | 1 dia | +12pp | ✅ mergeado `e36e1e272` |
| #4 #1190 (Wave) | Cancelar NFe + Manifestar DF-e (4 ações) | 4h | +15pp (core) | ✅ mergeado `d10b117e1` |
| #5 #1249 (Wave) | CC-e (110110) + Inutilização faixa | 4h | +4pp | ✅ mergeado |
| #6 (Wave) | Retransmitir NFe rejeitada/denegada | 3h | +3pp | 🟡 em curso |
| #7 | ⌘K palette cross-fiscal | 6h | +8pp | 🔒 backlog |
| #8 | Gerador SPED real (EFD ICMS-IPI + PIS/COFINS) | 1+ semana | +10pp | 🔒 backlog |

**Meta:** Score Capterra Fiscal cockpit ≥ 88/100 pós-PR #6 (Retransmitir fecha gap workflow Bling). Wagner aprova nova meta.

## Histórico

- **v1.0.0** (2026-05-20) — SPEC.md inicial criado em PR #1183 (Fiscal cockpit NF-e). Módulo novo thin agregador.
- **v1.1.0** (2026-05-20) — PR #2 Wave consolidada: Cockpit + NFS-e + Eventos. 3 sub-páginas adicionadas (US-FISCAL-002, US-FISCAL-005, US-FISCAL-007). Permission `fiscal.nfse.view` nova. Roadmap reorganizado (5 PRs vs 7 originais).
- **v1.2.0** (2026-05-20) — PR #3 Wave final: DF-e + Cert/Cfg + SPED placeholder. **7 sub-páginas do design Cowork concluídas**. US-FISCAL-008/009/010 adicionadas + US-FISCAL-011 backlog (gerador SPED real). FxShell habilita todos 7 chips. Próximo PR foco em ações de mutação (cancelar/CC-e/etc).
- **v1.3.0** (2026-05-20) — PR #4 Wave Ações: AcoesController thin delegate pra NfeService::cancelar (FSM cascade ADR 0143) + ManifestacaoService (4 ações DF-e). NotaDrawer Cancelar habilitado + modal motivo. Dfe.tsx coluna Ações com 4 botões manifesto. US-FISCAL-012 adicionada. Roadmap reorganizado (Retransmitir+CCe+Inut viraram PR #5).
- **v1.4.0** (2026-05-20) — PR #5 Wave CCe + Inutilização: `NfeCartaCorrecaoService` novo (espelhado em `NfeInutilizacaoService` — não inflar NfeService 900 linhas). 2 rotas + 2 métodos no AcoesController. NotaDrawer botão CC-e habilitado + modal texto correção 15-1000. Nfe.tsx header "Inutilizar faixa" + `InutilizacaoModal.tsx` (componente extraído). US-FISCAL-013 adicionada. Retransmitir nota rejeitada permanece backlog PR #6 (re-build payload exige scope dedicado). Meta Capterra Fiscal ≥85/100.
- **v1.5.0** (2026-05-20) — PR #6 Wave Retransmitir: `NfeService::retransmitir` método novo (UPDATE antiga `status=inutilizada` + `transaction_id=null` preservation contract CONFAZ Art. 14 — NUNCA forceDelete + `emitirParaTransaction` novo número). AcoesController método retransmitir + rota POST. NotaDrawer botão Retransmitir habilitado + modal confirm explicativo. US-FISCAL-014 adicionada. Meta Capterra ≥88/100.
- **v1.6.0** (2026-05-20) — PR #7 Wave ⌘K palette: `PaletteSearchController` + `CmdKPalette.tsx` listener global Cmd/Ctrl+K + endpoint JSON 2 categorias (notas + dfe). FxShell mount global + botão Buscar habilitado. US-FISCAL-015 adicionada. Meta Capterra ≥96/100.
- **v1.7.0** (2026-05-20) — PR #8 Wave SPED MVP: `SpedIcmsIpiGeneratorService` novo + SpedController::gerar download TXT EFD-ICMS/IPI v3.1.1 perfil A + Sped.tsx botão habilitado. 16 registros canônicos (Blocos 0+C+9). US-FISCAL-016 adicionada. Meta Capterra **100/100**.
- **v1.8.0** (2026-05-20) — PR #9 Wave Bloco E + H: expande SpedIcmsIpiGeneratorService com 7 registros (E001+E100+E110+E116+E990+H001+H990). Apuração ICMS consolidada via array_sum C190. E116 condicional. Bloco H esqueleto. US-FISCAL-017 adicionada. Cobertura SPED: 23 registros canon (estrutura completa pra validação PVA-EFD CONFAZ).
- **v1.9.0** (2026-05-25) — **Onda ESTABILIZAR** (audit sênior GAP-FISCAL-001 + GAP-FISCAL-002): (a) Consolidação sidebar — `Modules/NfeBrasil/DataController.modifyAdminMenu` removeu 3 entries duplicadas (Notas/Manifestação/Certificado); `Modules/Fiscal/DataController.modifyAdminMenu` virou hub canon com 4 entries no grupo fiscal (Cockpit /fiscal · Notas /fiscal/nfe · Manifestação /fiscal/dfe · Certificado /fiscal/config). Wagner apontou 2026-05-25 "fiscal manifestação certificado tem telas competindo + cockpit não implementado". (b) Comando `fiscal:habilitar-business {biz}` idempotente provisiona 6 perms `fiscal.*` ao role Admin#{biz} (NÃO atribui fiscal.sped.export — GAP-FISCAL-003 hardcodes pendentes). (c) Cache Redis 60s KPIs Cockpit + listener invalidação em NFeAutorizada/NFCeAutorizada + reuse cert/dfeCount entre computeKpis/computeAlerts (2 queries redundantes eliminadas). (d) Palette anti-DOS min:3 chars (era min:2 — leading wildcard `LIKE %q%` pode full-scan tabelas com 50k+ NFe). (e) Feature flag `fiscal.sped_simples_only_lock=true` default produção bloqueia download SPED com 503 explicativo enquanto SpedIcmsIpiGeneratorService tem 6 hardcodes Tier-0 (audit sênior §"Surpresa estratégica" R1 multa fiscal interestadual). US-FISCAL-018 in_progress (provisionamento técnico ✅ · briefing Larissa pending), US-FISCAL-019 done. 19 Pest tests novos verdes (CockpitCacheTest 6 + SidebarConsolidacaoTest 10 + SimplesOnlyGateConfigTest 3 + HabilitarBusinessCommandTest 6 + SimplesOnlyGateTest 5 — alguns skipados em SQLite-only por exigirem MySQL ADR 0101).

## Referências

- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — Tests biz=1
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — MWART canônico
- [ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — Cowork loop
- [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM cancel cascade
- SCOPE: [`Modules/Fiscal/SCOPE.md`](../../../Modules/Fiscal/SCOPE.md)
- RUNBOOK: [`RUNBOOK-nfe.md`](RUNBOOK-nfe.md)
- Visual comparison: [`nfe-visual-comparison.md`](nfe-visual-comparison.md)

## Onda Audit Sênior 2026-05-25

> Origem: [`AUDIT-SENIOR-2026-05-25.md`](AUDIT-SENIOR-2026-05-25.md). Fiscal 66/100 Bom-baixo → projetado 80+ pós Onda ESTABILIZAR.
> Bypass MCP `tasks-create` (mcp_jira_projects ainda não tem entry "Fiscal") — webhook sincroniza no próximo push.

### US-FISCAL-018 · Habilitar cockpit Fiscal Larissa biz=4 + canary 7d smoke

**Implementado em:** _parcial_ · [`Modules/Fiscal/Console/Commands/HabilitarBusinessCommand.php`](../../../Modules/Fiscal/Console/Commands/HabilitarBusinessCommand.php) · [`config/fiscal.php`](../../../config/fiscal.php) · [`Modules/Fiscal/Http/Controllers/SpedController.php`](../../../Modules/Fiscal/Http/Controllers/SpedController.php) · verificado@176f9bc (2026-07-01) — falta rodar comando em prod + briefing Larissa + canary 7d + smoke (humano-limitado, backlog)

> owner: wagner · priority: p0 · estimate: 8h · type: story
> blocked_by: —

**Sintoma:** Larissa já emite NFe via NfeBrasil; falta apenas permissão Fiscal pra ela validar regras tributárias por NCM (vestuário NCM 61-63 Simples Nacional).

**Pré-req crítico:** Feature flag `fiscal.sped_simples_only_lock=true` provisionada ANTES de habilitar biz=4 — proteção R1 multa fiscal interestadual.

**Acceptance:**
- [x] Feature flag `fiscal.sped_simples_only_lock=true` em [config/fiscal.php](../../../config/fiscal.php) (default true em produção)
- [x] Gate HTTP em `SpedController::gerar` retorna 503 explicativo quando flag true (superadmin bypassa)
- [x] Comando `php artisan fiscal:habilitar-business {biz}` idempotente — provisiona 6 perms `fiscal.*` ao role Admin#{biz} + garante package_details.fiscal_module=1 ([HabilitarBusinessCommand.php](../../../Modules/Fiscal/Console/Commands/HabilitarBusinessCommand.php))
- [x] Comando NUNCA atribui `fiscal.sped.export` (audit sênior GAP-FISCAL-003 ainda pendente)
- [x] Pest `HabilitarBusinessCommandTest` cobre idempotência + cross-tenant scope (5 tests + 1 contract)
- [ ] Wagner roda `php artisan fiscal:habilitar-business 4` em prod via SSH (humano-limitado)
- [ ] Wagner sign-off briefing Larissa (30min humano-limitado)
- [ ] Canary 7d biz=4 com observação ativa Wagner
- [ ] Smoke browser MCP salvo

**Refs:** AUDIT-SENIOR-2026-05-25.md §GAP-FISCAL-001, [TaxRadar NCM Vestuário](https://taxradar.app/blog/ncm/ncm-texteis-vestuario-guia-completo-classificacao)

### US-FISCAL-019 · Cache Redis 60s KPIs + anti-DOS palette LIKE — ✅ Onda ESTABILIZAR

**Implementado em:** [`Modules/Fiscal/Http/Controllers/CockpitController.php`](../../../Modules/Fiscal/Http/Controllers/CockpitController.php) · [`Modules/Fiscal/Listeners/InvalidaCockpitCacheListener.php`](../../../Modules/Fiscal/Listeners/InvalidaCockpitCacheListener.php) · [`Modules/Fiscal/Http/Controllers/PaletteSearchController.php`](../../../Modules/Fiscal/Http/Controllers/PaletteSearchController.php) · verificado@176f9bc (2026-07-01)

> owner: wagner · priority: p1 · estimate: 8h · status: done · type: story
> blocked_by: —

**Sintoma:** KPIs do cockpit Fiscal sem cache (recalcula a cada request). Palette `LIKE %q%` sem índice = anti-DOS vulnerability.

**Acceptance:**
- [x] Cache Redis 60s nos KPIs via `Cache::remember('fiscal:cockpit:kpis:biz:'.$businessId, 60, ...)` em [CockpitController::index](../../../Modules/Fiscal/Http/Controllers/CockpitController.php)
- [x] Invalidação automática em `NFeAutorizada` + `NFCeAutorizada` events via [InvalidaCockpitCacheListener](../../../Modules/Fiscal/Listeners/InvalidaCockpitCacheListener.php) registrado em [FiscalServiceProvider](../../../Modules/Fiscal/Providers/FiscalServiceProvider.php)
- [x] Reuse de `$cert` + `$dfeCount` entre `computeKpis` e `computeAlerts` (2 queries redundantes eliminadas — audit sênior linha 248-249)
- [x] Palette `q` min 3 chars (era 2) — anti-DOS leading wildcard pra biz com 50k+ NFe ([PaletteSearchController](../../../Modules/Fiscal/Http/Controllers/PaletteSearchController.php))
- [x] Pest `CockpitCacheTest` (6 tests — cache key format, TTL, reuse contract, isolation multi-tenant, listener invalidação)
- [x] Pest `PaletteSearchControllerTest` atualizado pra validar min:3 (era min:2)

**Refs:** AUDIT-SENIOR-2026-05-25.md §GAP-FISCAL-002

### US-FISCAL-020 · Integrar MotorTributarioService NfeBrasil — elimina 6 hardcodes Tier-0 SPED — ✅ Onda CONSOLIDAR

**Implementado em:** [`Modules/Fiscal/Services/SpedIcmsIpiGeneratorService.php`](../../../Modules/Fiscal/Services/SpedIcmsIpiGeneratorService.php) · verificado@176f9bc (2026-07-01) — Fase 1 (fallback safe + CFOP interno/interestadual) entregue; Fase 2 Strategy Pattern por regime é escopo separado (GAP-7)

> owner: wagner · priority: p0 · estimate: 24h · status: done (Fase 1 — fallback safe entregue · Fase 2 Strategy Pattern por regime fica GAP-7) · type: story
> blocked_by: —

**Sintoma crítico:** `SpedIcmsIpiGeneratorService` tem **6 hardcodes Tier-0** que funcionam ACIDENTALMENTE pra Larissa porque coincidem com vestuário Simples Nacional:
1. NCM `00000000`
2. CST `102` (CSOSN Simples)
3. CFOP `5102` (operação dentro da UF)
4. ALIQ `0`
5. COD_MUN hardcoded
6. COD_PART hardcoded

**Quebra na primeira venda interestadual contribuinte** (CFOP 6102 com ICMS-ST). Não é refactor de qualidade — é pré-requisito não-óbvio da Onda 6 (Reforma Tributária IBS/CBS).

**Acceptance:**
- [x] Refactor `SpedIcmsIpiGeneratorService` chama `MotorTributarioService` via DI opcional ([SpedIcmsIpiGeneratorService.php](../../../Modules/Fiscal/Services/SpedIcmsIpiGeneratorService.php))
- [x] 6 hardcodes ESPALHADOS eliminados — centralizados em 6 constantes `private const FALLBACK_*` (NCM/CST/CFOP_INTERNO/CFOP_INTERESTADUAL/ALIQ/COD_GEN)
- [x] Fallback safe Simples Nacional quando motor lança `NcmObrigatorioException` ou `TributacaoNaoConfiguradaException` (caso atual Larissa — log INFO + emissão correta)
- [x] Diferenciação CFOP interno (5102) vs interestadual (6102) via UF origem/destino — **elimina R1 audit** (multa fiscal Larissa vendendo SC→RS)
- [x] `resolverTributoItem()` resolve via MotorTributarioService → TributoCalculado real (cst/cfop/aliq/vl_icms) quando configurado
- [x] `keyTotalizadorC190()` chave composta CST|CFOP|ALIQ (era hardcode `return '102'`)
- [x] `extrairNcmDaEmissao()` lê metadata real (era hardcode `'00000000'` SEMPRE)
- [x] Pest `SpedMotorTributarioIntegrationTest` (10 tests) — refactor source contract + DI opcional + fallback Simples interno/interestadual + motor configurado Lucro Presumido CFOP 6102 ALIQ 18% + exception handling
- [x] Tests existentes (`SpedIcmsIpiGeneratorServiceTest`) verde — back-compat 100%

**Fase 2 (futuro GAP-7 audit sênior — Strategy Pattern por regime):**
- [ ] Items reais via JOIN `transactions_sell_lines` (escopo separado — fora desta wave)
- [ ] Strategy `SimplesNacionalStrategy` / `LucroPresumidoStrategy` / `LucroRealStrategy` resolvidos via `NfeBusinessConfig.regime`
- [ ] COD_MUN IBGE municipio-level via lookup `business->city_id` (placeholder UF+0000 mantém esta wave)
- [ ] Pós Strategy entregue, feature flag `fiscal.sped_simples_only_lock` pode ser baixada pra `false` em prod

**Refs:** AUDIT-SENIOR-2026-05-25.md §GAP-FISCAL-003 + §"Surpresa estratégica" R1 multa fiscal interestadual
- [ ] Migration `nfe_fiscal_rules` 5 colunas (NCM/CFOP/UF/regime/aliq) provisionada

**Refs:** AUDIT-SENIOR-2026-05-25.md §GAP-FISCAL-003, ADR 0186

## Onda 6 — Reforma + refinamento (CAPTERRA-INVENTARIO 2026-07-03 · Passo 2)

### US-FISCAL-021 · IBS/CBS cálculo no MotorTributarioService (Onda 6 — sair do scaffold)

> owner: wagner · priority: p0 · estimate: 28h · status: todo · type: story
> blocked_by: —

**Origem:** CAPTERRA-INVENTARIO Fiscal 2026-07-03 (cap #7, GAP-FISCAL-004) — único P0 zerado da ficha (nota 75/100). Programa de Ondas Passo 2.

**Contexto:** schema já pronto (migration `2026_05_26_000001_add_ibs_cbs_to_nfe_fiscal_rules` tem colunas `c_class_trib`/`cst_ibs`/`cst_cbs`/`aliquota_ibs`/`aliquota_cbs`), mas `MotorTributarioService` tem **0 lógica de cálculo** IBS/CBS. Concorrentes ERP (Bling auto-fill, Tiny, Omie datado) e middleware (PlugNotas calculadora) já cobrem.

**Acceptance:**
- [ ] `MotorTributarioService::calcular()` retorna `cClassTrib` + CST IBS/CBS + alíquotas IBS/CBS a partir das colunas de `nfe_fiscal_rules` (cascade existente)
- [ ] `TributoCalculado` expõe campos IBS/CBS
- [ ] Preenche grupo UB (IBSUF/IBSMun/CBS) no XML da NF-e/NFC-e
- [ ] Valida NT 2025.002 (regras LA01-30/N12-110; rejeições 1106/960)
- [ ] Fallback safe Simples Nacional (biz=1/biz=4 não destacam até 2027-01)
- [ ] Pest ≥5 cenários (Simples, Regime Normal CRT=3, monofásica, crédito presumido, imune)
- [ ] Tests biz=1 (ADR 0101), NUNCA biz=4

**Dependência técnica:** `nfephp-org/sped-nfe` com IBS/CBS (hoje só `dev-master` + `TraitTagDetIBSCBS`; tag estável v5.1.34 SEM reforma — issue #1274 sem data). Avaliar pin `dev-master` vs aguardar release.

**Prazo regulatório:** homologação obrig. passou 01/07/2026; **produção obrigatória 03/08/2026** (CRT 3 Normal). Risco imediato contido (pilotos Simples) mas crítico se migrar regime.

**Refs:** CAPTERRA-FICHA Fiscal §7 · AUDIT-SENIOR-2026-05-25 GAP-FISCAL-004 · ADR ARQ-0004 (schema flexível CBS/IBS) · esta US é o **CÁLCULO** (o scaffold de schema já foi mergeado na migration `add_ibs_cbs_to_nfe_fiscal_rules`).

### US-FISCAL-022 · Health-check certificado A1 (cron alerta vencimento)

> owner: — · priority: p1 · estimate: 4h · status: todo · type: story
> blocked_by: —

**Origem:** CAPTERRA-INVENTARIO Fiscal 2026-07-03 (cap #13, 🟡 PARCIAL) — mercado (todos middlewares + Bling/Omie) alerta vencimento de cert; oimpresso só exibe validade estática.

**Acceptance:**
- [ ] Comando artisan `fiscal:cert-health-check` (registrado em `app/Console/Kernel.php`, schedule diário)
- [ ] Por business com cert A1: calcula dias-a-vencer
- [ ] dias-a-vencer ≤ 30 → cria/atualiza entry em `mcp_alertas` (dedup por business+cert)
- [ ] Multi-tenant scope (ADR 0093) — itera businesses com cert configurado
- [ ] Pest biz=1 (ADR 0101): cert vencendo em 15d gera alerta; cert válido 200d não gera
- [ ] Log estruturado

**Refs:** CAPTERRA-FICHA Fiscal §9 automation_targets `health-check-cert-a1` · ConfigController (fonte da validade).

> **Nota Passo 2:** cap #8/#14 (cache KPIs + índice palette) **NÃO** virou US — já entregue em **US-FISCAL-019** (✅ done, GAP-FISCAL-002 fechado). US-FISCAL-023 gerada por engano foi descartada antes de persistir (dedup falhou pq `tasks-list` default só lista ativas).
