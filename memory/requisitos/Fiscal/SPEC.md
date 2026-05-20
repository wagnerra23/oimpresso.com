---
module: Fiscal
status: em-implementacao (PR #1 NF-e cockpit)
version: 1.0.0
last_updated: 2026-05-20
piloto: oimpresso biz=1 (Wagner empresa) — depende de NfeBrasil já produção
last_review: 2026-05-20
owner: wagner
parent_adr: 0094
related_adrs: [0093, 0101, 0104, 0114, 0143]
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

Busca global em notas + DF-e + ações rápidas. Detalhe quando entregar.

### US-FISCAL-004 · Ações de mutação — **backlog PR #4**

Cancelar / Retransmitir / CC-e / Inutilizar. Chama Services `Modules/NfeBrasil` existentes via Job. NOT habilitado neste PR #1 (botões disabled no drawer).

### US-FISCAL-005 · NFS-e (sub-página 3) — ✅ PR #2 Wave

> **Rota:** `GET /fiscal/nfse`
> **Controller:** `NfseCockpitController@index`
> **Permissão:** `fiscal.nfse.view`
> **Page:** `resources/js/Pages/Fiscal/Nfse.tsx`

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

Lê `Modules/NfeBrasil/Models/NfeDfeRecebido`.

### US-FISCAL-007 · Eventos (sub-página 5) — ✅ PR #2 Wave

> **Rota:** `GET /fiscal/eventos`
> **Controller:** `EventosController@index`
> **Permissão:** `fiscal.access`
> **Page:** `resources/js/Pages/Fiscal/Eventos.tsx`

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

EFD-Contribuições (PIS/COFINS arquivo separado) + saldo credor real E110 + Bloco H com dados reais inventário 31/12.

### US-FISCAL-012 · Ações mutação NFe + DF-e — ✅ PR #4 Wave

> **Rotas:**
> - `POST /fiscal/acoes/nfe/{emissao}/cancelar` (perm `fiscal.nfe.acoes`)
> - `POST /fiscal/acoes/dfe/{recebido}/{acao}` (perm `fiscal.dfe.manage`, acao=cienciar|confirmar|desconhecer|nao_realizada)
> **Controller:** `AcoesController`

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
