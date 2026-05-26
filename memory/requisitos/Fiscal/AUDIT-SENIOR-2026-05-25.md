---
doc: AUDIT-SENIOR-FISCAL
modulo: Fiscal
status: dossier-sr-pre-onda
versao: 1.0.0
auditor: audit-senior-expert (Opus 4.7)
data: 2026-05-25
nota_atual: 66/100  # rubrica module-grade-v3 (ADR 0155)
nota_target_ondas: 92/100
escopo_codigo: D:/oimpresso.com/Modules/Fiscal/
escopo_doc: D:/oimpresso.com/memory/requisitos/Fiscal/
gaps_p0: 4
gaps_p1: 5
gaps_p2: 3
total_websearch: 11
total_webfetch: 0
piloto_proposto: biz=4 ROTA LIVRE (Larissa — vestuário SC, NCM 61/62, RS jurisdição) — habilitar cockpit Fiscal pra ela
prazo_regulatorio_critico: 2026-08-01 (highlight IBS/CBS obrigatório NFe — NT 2025.002)
prazo_full_validation: 2027-01-01 (validação integral IBS/CBS produção)
related_adrs:
  - 0089-capterra-driven-module-evolution
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0101-tests-business-id-1-nunca-cliente
  - 0103-eventos-fiscais-separados-por-modelo
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0106-recalibracao-velocidade-fator-10x-ia-pair
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0116-pivot-gold-manifestacao-destinatario-emenda-0115
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0178-restauracao-campos-fiscais-br-canon
  - 0186-chain-certificado-sefaz-consulta-cadastro
related_docs:
  - memory/requisitos/Fiscal/SPEC.md (v1.8.0)
  - memory/requisitos/Fiscal/BRIEFING.md
  - memory/requisitos/Fiscal/PLANO-TESTES-FISCAL.md (v1.0.0 — 7 ondas)
  - Modules/Fiscal/SCOPE.md (v1.8.0)
  - Modules/NfeBrasil/SCOPE.md
---

# Audit Sênior — Modules/Fiscal (2026-05-25)

> **Auditor:** `audit-senior-expert` (Opus 4.7). Sessão pré-onda 25 minutos, 11 WebSearches, 0 WebFetch (PLANO-TESTES já consolidou benchmark — não precisou).
> **Status:** Dossier blueprint pronto pra Wagner aprovar antes de disparar implementadores juniores Fase 3.

---

## TL;DR executável

**Módulo Fiscal é um agregador thin saudável (66/100)** sobre `NfeBrasil` (motor real). Score 66 NÃO reflete o problema correto da rubrica V3 — o problema **não é arquitetura interna** (a separação Fiscal/NfeBrasil é exemplar), e sim:

| Dim | Atual | Target | Caminho |
|---|---:|---:|---|
| D5 Cliente piloto | **0/15** | 13/15 | Habilitar cockpit pra Larissa biz=4 (já emite NFe via NfeBrasil) + smoke 7d |
| D6 Perf | 5/10 | 9/10 | Cache de KPIs (Redis 60s), índice palette LIKE, batch sparkline com índice composto |
| D4 Arquitetura | 12/20 | 17/20 | SPED tirar hardcodes (CST 102 / CFOP 5102 / NCM 00000000) → integrar `MotorTributarioService` real + 2 calculadoras |
| D2 Pest | 13/20 | 17/20 | Onda 3 (cStat fixtures top-10) + Onda 6 (IBS/CBS matriz) — herda PLANO-TESTES |
| **Restantes (D1, D3, D7, D8, D9)** | já 🟢 | mantém | Smoke não-regressão |

**Surpresa estratégica achada:** o **gerador SPED está com 6 hardcodes Tier-0** (NCM `00000000`, CST `102`, CFOP `5102`, ALIQ `0`, COD_MUN `UF+0000`, COD_PART `P-{cnpj}`). Pra biz=1 (Wagner WR2 sem ICMS própria, todas Simples Nacional CST 102) **funciona**. Pra **biz=4 (Larissa vestuário Simples Nacional RS) também funciona** porque CSOSN 102 é o caso comum vestuário Simples sem crédito ICMS. **Mas quando piloto migrar pra Lucro Presumido/Real** (e quando IBS/CBS exigir cClassTrib real em jan/2026 → ago/2026 obrigatório), **o gerador rejeita no PVA-EFD** porque os campos vêm vazios. Esse é o GAP P0 oculto pra Reforma.

**5 gaps P0 ordenados por dependência:**

1. **GAP-FISCAL-001 — Habilitar cockpit biz=4 (piloto Larissa)** — desbloqueia D5 (0→13)
2. **GAP-FISCAL-002 — Cache KPIs Cockpit (Redis 60s + busca palette indexada)** — D6 (5→9)
3. **GAP-FISCAL-003 — Integrar `MotorTributarioService` no SPED (eliminar 6 hardcodes)** — D4 (12→16) + pré-req da Onda 6 IBS/CBS
4. **GAP-FISCAL-004 — Onda 6 IBS/CBS (Reforma Tributária NT 2025.002)** — D2 (13→16) + prazo regulatório
5. **GAP-FISCAL-005 — Onda 3 fixtures cStat top-10 (Eliana/Larissa)** — D2 (16→18) + UX rejeição

**3 gaps P1:** automatização health-check cert A1 (cron), entradas DF-e manifestada → Bloco C inputs, EFD-Contribuições PIS/COFINS arquivo separado.

**3 gaps P2:** ⌘K palette com índices full-text, Bloco H integração Stock 31/12, batch tax preview no carrinho POS.

**Esforço total Ondas 1+2 (P0):** 7-10 dev-days IA-pair (fator 10x ADR 0106 aplicado).
**Sequência recomendada:** GAP-001 e GAP-002 em paralelo (1 dev-day cada) → GAP-003 sequencial 1 dia (pré-req) → GAP-004 paralelo com GAP-005 (3-5 dias) → smoke biz=4 (7d humano).
**Custo infra:** R$ [redacted Tier 0] extra (Redis já existe), R$ [redacted Tier 0] LLM (Reforma é determinística).

---

## Inventário (T0)

### Código

```
Modules/Fiscal/
├── Http/Controllers/        11 controllers (1112 LoC total)
│   ├── AcoesController.php          369 — 5 mutações thin delegate
│   ├── CockpitController.php        178 — KPIs + sparklines + alertas
│   ├── ConfigController.php          53 — read-only cert + cfg
│   ├── DfeController.php             — manifestação
│   ├── EventosController.php        132 — timeline append-only
│   ├── NfeCockpitController.php     207 — sub-página 2 (CORE)
│   ├── NfseCockpitController.php     — sub-página 3
│   ├── PaletteSearchController.php  134 — ⌘K busca global
│   ├── SpedController.php           107 — sub-página 7 + download
│   ├── DataController.php            — bootstrap dados
│   └── InstallController.php         — install/uninstall módulo
├── Services/                 1 service (601 LoC)
│   └── SpedIcmsIpiGeneratorService.php  — gerador EFD-ICMS/IPI 23 registros
├── Tests/Feature/           12 arquivos, 1147 LoC, 10 testes documentados PLANO-TESTES
├── Routes/web.php           110 LoC — 7 GET sub-páginas + 6 POST ações + palette + sped
├── SCOPE.md                  86 LoC — v1.8.0
└── module.json              26 LoC — bucket `functional_horizontal`, fsm_n_a=true
```

### Doc (memory/requisitos/Fiscal/)

```
BRIEFING.md                    score Capterra 102/100 (cap acima)
PLANO-TESTES-FISCAL.md         v1.0.0 — 7 ondas, 12 classes erro, 60 testes
SPEC.md                        US-FISCAL-001..017 + R-FISCAL-001..003
RUNBOOK-cockpit.md             6 sub-páginas RUNBOOK + sped
fiscal-cockpit-visual-comparison.md  visual comparison Cowork
+ Charters .charter.md em resources/js/Pages/Fiscal/
```

### Cruzamento com NfeBrasil + NFSe

| Capacidade | Onde mora | Fiscal toca? |
|---|---|---|
| Emissão NFe XML + SEFAZ | NfeBrasil/Services/NfeService | ❌ (apenas chama via Service) |
| Cálculo tributário cascade 4 níveis | NfeBrasil/Services/MotorTributarioService | ❌ (não usa — usa hardcodes no SPED) |
| Cancelar NFe FSM cascade | app/Domain/Fsm/CancelarVendaCascade (ADR 0143) | ❌ (delega) |
| Cert A1 + chain SEFAZ | NfeBrasil/Services/CertificadoService (ADR 0186) | ❌ (lê NfeCertificado) |
| CC-e / Inutilização / Retransmissão | NfeBrasil/Services/Nfe{CartaCorrecao,Inutilizacao}Service + NfeService::retransmitir | ❌ (delega) |
| Geração SPED EFD-ICMS/IPI TXT | **Fiscal/Services/SpedIcmsIpiGeneratorService** | ✅ **ÚNICO Service próprio do módulo** |
| Manifestação DF-e (4 ações) | NfeBrasil/Services/Manifestacao/ManifestacaoService | ❌ (delega) |

**Verdict:** thin agregador exemplar — só 1 Service próprio + 11 Controllers thin. Não há acoplamento errado. **Fronteira saudável.**

---

## ANÁLISE D4 ARQUITETURA (12/20 → 17/20)

### O que está certo
- ✅ Separação NfeBrasil/Fiscal exemplar (pattern thin agregador validado em `Modules/Financeiro/Unificado`)
- ✅ AcoesController é thin puro — Request validate + delega Service + log + back()->with()
- ✅ HasBusinessScope global scope em todos Models lidos
- ✅ Cross-tenant guard explícito como defesa em profundidade em paths sensíveis (AcoesController, SpedService)
- ✅ Permissões Spatie granulares por sub-feature (`fiscal.nfe.acoes`, `fiscal.dfe.manage`, `fiscal.sped.export`, etc)
- ✅ Throttle 30/min em todas mutações que disparam SEFAZ (protege webservice)
- ✅ OTel span em pontos sensíveis (`fiscal.sped.gerar`)
- ✅ `Inertia::defer` em payloads caros (rows)
- ✅ Alertas determinísticos (sem LLM — receitas PHP) — anti-hook Charter

### Falhas estruturais (origem dos 8 pontos faltantes)

#### Falha 1 — Gerador SPED com 6 hardcodes Tier-0 (CRÍTICA)

`SpedIcmsIpiGeneratorService` gera TXT mas usa valores hardcoded em vez de chamar `MotorTributarioService`:

```php
// Linhas 375-376 (C170 — Item) — HARDCODES
'102',       // CST_ICMS (Simples Nacional default)
'5102',      // CFOP (venda mercadoria interna Simples)
'0,00',      // ALIQ_ICMS sempre zero
'0,00',      // VL_ICMS sempre zero

// Linha 300 (0200 — Item) — HARDCODE
(string) ($item['ncm'] ?? '00000000'),  // NCM placeholder

// Linha 583-585 (keyTotalizadorC190) — HARDCODE
return '102'; // CST simples nacional default

// Linha 116 (totalizador) — HARDCODE
$totalizadores[$key] ??= ['cst' => $key, 'cfop' => '5102', 'aliq' => 0, ...];

// Linha 376 extrairItens — HARDCODE
'ncm' => '00000000',  // pra TODO item
```

**Impacto:**
- Funciona pra biz=1 (Wagner WR2 sem ICMS própria, Simples Nacional CST 102)
- Funciona pra biz=4 (Larissa Simples Nacional vestuário NCM 61/62 CSOSN 102 CFOP 5102) — **conveniência feliz**
- **NÃO funciona** quando piloto migrar pra Lucro Presumido (CST 00/10/20) ou Lucro Real (CST 00..90 com substituição)
- **NÃO funciona** quando Reforma Tributária IBS/CBS exigir `cClassTrib` real (ago/2026 obrigatório highlight, jan/2027 validação integral)

**Solução técnica:** integrar `MotorTributarioService` (NfeBrasil) que já tem cascade 4 níveis + cache em memória + métricas OTel:
```php
// Em SpedIcmsIpiGeneratorService — gerarInterno()
$motor = app(MotorTributarioService::class);
foreach ($emissoes as $emissao) {
    $items = $this->resolverItensDaEmissao($emissao);  // ler dos metadata ou JOIN transactions_items
    foreach ($items as $item) {
        $contexto = ProdutoFiscalContext::fromItem($item);
        $tributo = $motor->calcular($contexto, $businessId, $ufOrigem, $ufDestino);
        // usa $tributo->cst / cfop / aliquota_icms / valor_icms — fim hardcode
    }
}
```

#### Falha 2 — Itens da NFe NÃO são lidos canonicamente

Hoje `extrairItens()` faz 1 item por NFe com `'PDV-' . $e->transaction_id`. NFe real tem N itens (linha de pedido). PVA-EFD valida quantidade vs valor — vai rejeitar quando NFe tiver 2+ produtos.

**Solução:** JOIN com `transactions_items` (ou parsear XML armazenado `xml_processado`) pra extrair items reais com `ncm`, `quantidade`, `valor_unitario`, `cfop` individual.

#### Falha 3 — Sem Strategy por regime tributário

Hoje o Service trata Simples Nacional implícito (CSOSN 102). Quando Lucro Real/Presumido virar caso real, será preciso ramificar. **Strategy Pattern proposto:**

```php
interface SpedRegimeStrategyInterface {
    public function montarC170(NfeEmissao $e, $item, $tributo): string;
    public function montarC190(array $totalizadores): array;
    public function montarE110(float $debitos, float $creditos, float $saldoAnt): string;
}

class SimplesNacionalStrategy implements SpedRegimeStrategyInterface {...}  // CSOSN
class LucroPresumidoStrategy implements SpedRegimeStrategyInterface {...}   // CST 00/20/40
class LucroRealStrategy implements SpedRegimeStrategyInterface {...}        // CST completo + ICMS-ST
```

Resolver via `NfeBusinessConfig::regime` (`simples_nacional|lucro_presumido|lucro_real|mei`).

#### Falha 4 — Bloco H (inventário) sempre `IND_MOV=1` (esqueleto)

Backlog conhecido (RUNBOOK-sped §9). Quando empresa fechar exercício 31/12, vai precisar dados reais Stock. Hoje gera placeholder — PVA-EFD aceita mas Receita Federal espera dados reais em SPED de janeiro.

### Verdict D4

8 pontos faltantes vêm de:
- Falha 1 (6 hardcodes) — **-4 pts**
- Falha 2 (itens não reais) — **-2 pts**
- Falha 3 (sem Strategy por regime) — **-1 pt**
- Falha 4 (Bloco H esqueleto) — **-1 pt**

Solução das 4 falhas requer **integração com MotorTributarioService + ler items reais + 3 Strategies + dados Stock** — total ~3 dev-days IA-pair (fator 10x).

---

## ANÁLISE D6 PERF (5/10 → 9/10)

### Queries identificadas

#### Cockpit (CockpitController::computeKpis + computeSparklines + computeAlerts)
```
GET /fiscal — entrypoint
Queries executadas eager:
  1. NfeEmissao count (emitidas mês)        — index: business_id + emitido_em ✅
  2. NfeEmissao count autorizadas mês       — mesmo
  3. NfeEmissao count rejeitadas+denegadas  — mesmo
  4. NfeEmissao sum valor_total autorizadas — mesmo
  5. NfeDfeRecebido count pendente+ciencia  — index: business_id + status_manifestacao ✅
  6. NfeCertificado where ativo=true        — index: business_id + ativo ✅
  7. NfeEmissao GROUP BY DATE + status (sparkline 14d) — query única ✅
  8. NfeEmissao rejeitadas 7d limit 2 (alertas) — ✅
  9. NfeCertificado where ativo (cert vencendo alert) — repete query 6 ❌
  10. NfeDfeRecebido count pendente (alertas) — repete query 5 ❌
TOTAL: ~10 queries eager, 2 redundantes
```

**Solução P0:**
- Cache KPIs Redis 60s `cache_key = "fiscal:cockpit:kpis:biz:{businessId}"` — invalida em event `NFeAutorizada`/`NFCeAutorizada`
- Reusar `$cert` e `$dfeCount` no `computeAlerts` (passar via DI ou property privada após `computeKpis`)
- **Ganho:** 10 queries → 0 (cache hit) ou 6 queries (miss) — p95 cockpit ~150ms → ~40ms

#### Palette (PaletteSearchController::searchNotas + searchDfe)
```
GET /fiscal/palette/search?q={q}
Queries:
  1. NfeEmissao WHERE numero LIKE '%q%' OR chave_44 LIKE '%q%' OR motivo LIKE '%q%' — FULL SCAN
  2. NfeDfeRecebido WHERE chave_44 LIKE '%q%' OR nome_emitente LIKE '%q%' OR cnpj_emitente LIKE '%q%' — FULL SCAN
```

**Problema:** `LIKE '%q%'` (leading wildcard) ignora B-tree index. Em biz com 50k+ NFe vai degradar.

**Solução P1:**
- Adicionar coluna gerada virtual `chave_44_suffix` = `RIGHT(chave_44, 6)` com index
- OU MySQL FULLTEXT index em `motivo` + `nome_emitente` (já que biz=4 Larissa terá 99% do volume futuro)
- OU consultar diretamente quando query é só dígitos: `WHERE numero LIKE 'q%'` (prefix wildcard usa index)
- **Mínimo P0:** query com `<= 3` chars retornar empty (anti-DOS pra leading wildcard scan)

#### Cockpit Sparkline (GROUP BY DATE)
- Já está otimizado (1 query agregada)
- Mas se biz=4 tiver 10k+ NFe no mês, `GROUP BY DATE(emitido_em), status` pode ficar lento
- **Mitigação:** materialized view ou cache por business+day (refresh hourly cron)

### Verdict D6

5/10 → 9/10 com:
- Cache Redis 60s no cockpit (1 dia IA-pair)
- Anti-DOS palette (query >=3 chars + índice composto) (4h IA-pair)
- Reuse query cert/dfe entre KPI e alerts (2h IA-pair)

---

## ANÁLISE D5 CLIENTE PILOTO (0/15 → 13/15)

### Estado atual

**Cliente real ativo no oimpresso (`memory/reference/clientes-ativos.md`):**

| biz | Cliente | Vendas total | Usa NFe? | Usa Fiscal cockpit? |
|---|---|---:|---|---|
| 1 | WR2 Sistemas (Wagner) | 165 | Sim (homolog/dev) | Sim (piloto) |
| **4** | **ROTA LIVRE (Larissa)** | **17.251 (99% vol)** | **Sim** — emite NFe vestuário via NfeBrasil canônico, ADR 0186 chain cert + SEFAZ | **❌ NÃO** — usa Modules/NfeBrasil diretamente |
| 164 | Martinho Caçambas (em migração) | 44.018 (legacy WR2) | ⏸️ pending | ❌ |

**Larissa biz=4 é o piloto natural:**
- Já tem cert A1 ativo (ADR 0186 chain primário)
- Já emite NFe vestuário (NCM 61/62 — CSOSN 102 — CFOP 5102)
- Já consulta SEFAZ ConsultaCadastro pra IE (ADR 0186)
- **Falta apenas:** habilitar permissões `fiscal.*` pra user.id=10 (Larissa) + user.id=11 (rota.vendas-04)
- Convergência sortuda: Larissa é **Simples Nacional vestuário SC vendendo pra RS** — caso real que vai disparar Reforma IBS/CBS em jan/2026

### Por que D5 = 0/15

Rubrica V3 (ADR 0155): cliente piloto pagante usando módulo = 15. Wagner (biz=1) **operador interno** não conta — desenvolveu o módulo. BRIEFING.md confirma: *"Não tem cliente biz=4 (Larissa ROTA LIVRE) tocando esse módulo ainda."*

### Caminho 0 → 13

1. **Habilitar `fiscal.access` + `fiscal.nfe.view` + `fiscal.nfe.acoes` pra user.id=10 e 11** (4h IA-pair: migration assignment + smoke)
2. **Habilitar sub-página NF-e do cockpit pra biz=4** (já funciona — só permissão bloqueia)
3. **Smoke biz=4 7 dias** — Larissa usa cockpit pra dia-a-dia + reporta UX gaps
4. **Iterar 2 ciclos UX** (drawer SEFAZ guiado, ⌘K, cancelamento <24h)
5. **Habilitar SPED export biz=4** somente após GAP-FISCAL-003 (eliminar hardcodes — porque biz=4 também é Simples mas pode mudar regime e quebrar)

**Esforço:** 1 dev-day implementação + 7 dias canary (humano-limitado, NÃO IA-pair — ADR 0106 mantém relógio).

**Risco P1:** se Larissa fizer venda interestadual contribuinte (vestuário pra revenda CFOP 6102 com ICMS-ST), o gerador SPED hardcoded **gera CST/CFOP errado**. Mitigação: SPED export só após GAP-FISCAL-003 ou flag `feature.fiscal.sped.simples_only=true` per business.

---

## ANÁLISE D2 PEST (13/20 → 17/20)

### Estado atual

PLANO-TESTES-FISCAL v1.0.0 já consolida 60 testes em 7 ondas. **Reusar esse plano** — não duplica. Resumo:

- **Onda 1 + 2:** ✅ entregues (caminho feliz + Tier 0 multi-tenant)
- **Onda 3:** 🔴 fixtures cStat top-10 SEFAZ (6 testes propostos, esforço 2-3d IA-pair)
- **Onda 4:** 🟡 cert + contingência (7 testes propostos, 2-3d IA-pair)
- **Onda 5:** 🟡 SPED real + eventos prazo (7 testes propostos, 3-4d IA-pair)
- **Onda 6:** 🔴🔴 **IBS/CBS Reforma (PRAZO REGULATÓRIO 05/jan/2026)** — 7 testes, 3-5d IA-pair
- **Onda 7:** 🔵 chaos + contract snapshot

**Coverage gap heat-map crítico:**

| Classe erro | Testes hoje | Gap |
|---|---|---|
| K Reforma Tributária IBS/CBS | **0** | 🔴🔴 — sem isso, jan/2027 quebra |
| C Cadastro/IE/denegação | 0 diretos | 🔴 |
| E Contingência EPEC/FSDA | 0 | 🔴 |
| B Idempotência cStat 539 | 4 (genérico) | 🟡 |
| D Cert expirado/wrong-CNPJ | 6 | 🟡 |
| H SPED validação PVA-EFD | 2 (Service) | 🟡 |

### Caminho 13 → 17

Foco P0 nesta onda: **GAP-FISCAL-005 = Onda 3 fixtures cStat** (3 dev-days IA-pair).

Razão: rejeição é o que machuca cliente. Eliana (contadora) vê "rejeitou cStat 539" e sem fixture+sugestão fica perdida. Onda 6 (IBS/CBS) tem prazo regulatório jan/2026 mas hoje (mai/2026) ainda está em janela de aviso — alvo de **Onda 2 do roadmap deste audit** (não desta wave inicial).

Onda 4 (cert/contingência) e Onda 7 (chaos) ficam pra próximas auditorias.

---

## ANÁLISE D1, D3, D7, D8, D9 (já 🟢 — manter)

| Dim | Atual | Manter |
|---|---:|---|
| D1 Multi-Tenant | 25/30 | Saturação Wave 23/25/26/27/28 já cobre. Pequeno gap: 1 teste cross-tenant `SpedIcmsIpiGeneratorService` faltante (PLANO-TESTES §Onda 2 gap pequeno) |
| D3 Doc | 13/15 | SCOPE + SPEC + BRIEFING + 7 RUNBOOK + PLANO-TESTES — excelente. Falta apenas ADR formal de "Fiscal = thin agregador" pra cristalizar pattern (1h) |
| D7 LGPD | 9/10 | PII redactor enabled, `pii_fields_tracked: []` (sem PII direto — vem redacted de NfeBrasil), `activity_log_enabled: false`. Falta CC-e justificativa não-redacted no log (não-PII mas merece auditoria explícita) |
| D8 Sec | 5/8 | Permissões granulares OK, throttle OK, cross-tenant guard OK. **Gap:** sem rate-limit por business (só global 30/min) — biz=4 emite 1000 NFe/mês pode bater limite global. **+2:** rate-limit per-biz + auth headers SEFAZ não logados ainda (rotacionar) |
| D9 Obs | 6/7 | OTel span `fiscal.sped.gerar` + métricas Wave 26 cobertas. **Gap:** sem dashboard Grafana específico Fiscal (vive misturado com NfeBrasil) — 1 painel novo basta |

---

## Estado-da-arte 2026 (benchmark concorrentes)

### Concorrentes diretos BR

| ERP | Estratégia tributária | Vantagem | Desvantagem |
|---|---|---|---|
| **Bling** | Regras de Operação por NCM + UF destino | Cadastro guiado, e-commerce nativo | Não exibe `cSit` no cadastro cliente (oimpresso supera via ADR 0186) |
| **Tiny** | Configuração CFOP + CSOSN por produto | Back-office mais robusto | Sem alertas antecipados rejeição |
| **Omie** | 3 níveis (CFOP / NCM / produto) + DIFAL automatizado | Cobertura completa Reforma + Simples | Caro escalado |
| **Sankhya** | EIP — pipeline tributário customizado | Lucro Real, indústria | Pesado pra PME |
| **Avalara** | API tax engine multi-país | Internacional + Reforma | Foco US/EU, BR fraco |

**oimpresso position:** thin agregador exemplar + ADR 0186 (warning antecipado cSit) → **bate Bling/Tiny em UX cadastro fiscal**, mas **falta o motor real** (MotorTributarioService já existe mas SPED não usa).

### Bibliotecas BR confirmadas em uso

| Lib | Uso oimpresso | Status NT 2025.002 IBS/CBS |
|---|---|---|
| `nfephp-org/sped-nfe` | ✅ canon `Modules/NfeBrasil` | ⚠️ issue #1274 GitHub solicitando suporte IBS/CBS — versão estável pendente |
| `nfephp-org/sped-efd-icms-ipi` | ❌ NÃO usado | Fiscal gera TXT manual via `linha()` helper |

**Decisão técnica P1:** considerar migrar `SpedIcmsIpiGeneratorService` pra usar `nfephp-org/sped-efd-icms-ipi` quando lib suportar v3.1.1 + IBS/CBS (próxima auditoria revisita).

### Reforma Tributária — Datas críticas

| Data | Marco | Impacto Fiscal |
|---|---|---|
| **2026-01-01** | Highlight CBS 0.9% + IBS 0.1% NF-e (opcional, sem multa) | Já passou — biz=1 oimpresso já podia testar |
| **2026-04-01** | Validação fields IBS/CBS pela Receita Federal | Hoje 25/mai — Onda 6 ainda tem ~3 meses pra primeira validação |
| **2026-08-01** | **Highlight CBS+IBS obrigatório NFe** (CRT 3 — Lucro Real/Presumido) | **Hard deadline pra biz=4 se mudar regime** |
| **2027-01-01** | CBS substitui PIS+COFINS integral; IBS começa fase transição ICMS/ISS | Crítico — sistema precisa estar 100% |
| **2029-2032** | Transição IBS suplanta ICMS+ISS gradualmente | Roadmap longo |
| **2033** | Sistema dual termina — CBS+IBS+IS apenas | Final game |

### Simples Nacional (Larissa biz=4)

- **2026:** Simples sem mudança — não destaca IBS/CBS no DAS
- **2027+:** Simples começa a destacar IBS/CBS — **prazo Larissa 2027-Q1**

---

## Gap Analysis vs Benchmark (12 gaps)

| # | Gap | Prio | Score impact | Dim | Esforço (IA-pair) |
|---|---|---|---|---|---|
| **1** | **Habilitar cockpit biz=4 (Larissa piloto)** | P0 | +13 pts | D5 | 1d |
| **2** | **Cache Redis KPIs Cockpit + palette anti-DOS** | P0 | +4 pts | D6 | 1d |
| **3** | **Integrar MotorTributarioService no SPED (elim 6 hardcodes)** | P0 | +4 pts | D4 | 2-3d |
| **4** | **Onda 6 IBS/CBS Reforma Tributária NT 2025.002** | P0 | +3 pts | D2+regulatório | 3-5d |
| **5** | **Onda 3 fixtures cStat top-10 SEFAZ** | P0 | +3 pts | D2 | 2-3d |
| 6 | Cron health-check cert A1 daily (alerta ≤30d) | P1 | +1 pt | D9 | 4h |
| 7 | Strategy pattern por regime (Simples/Presumido/Real) | P1 | +2 pts | D4 | 2d |
| 8 | EFD-Contribuições PIS/COFINS arquivo separado | P1 | +2 pts | funcional | 1+sem |
| 9 | Items NFe reais (JOIN transactions_items ou parse XML) | P1 | +2 pts | D4 | 2d |
| 10 | Bloco H integração Stock 31/12 real | P1 | +1 pt | D4 | 2d |
| 11 | Dashboard Grafana Fiscal específico | P2 | +1 pt | D9 | 4h |
| 12 | ⌘K palette full-text index (volume escala) | P2 | +1 pt | D6 | 4h |

**Sub-total:**
- **P0 (5 gaps):** +27 pts (mas teto rubrica = +26 do gap atual 66 → 92)
- **P1 (5 gaps):** +8 pts
- **P2 (2 gaps):** +2 pts

---

## Roadmap 3 ondas (alinhado com aprendizados Onda 1-3 + maturity-gap pattern)

### 🟢 ESTABILIZAR (Onda 0 → Onda 1) — 66 → 80

**Goal:** trazer cliente pagante + lock D5 + perf básica.

| # | Gap | Áreas isoladas (paths) | Pré-req | Esforço |
|---|---|---|---|---|
| 1 | Habilitar biz=4 + permissões | `Modules/UltimatePos/Database/Seeders/PermissionsTableSeeder.php` + admin UI Spatie | Larissa briefing 30min | 1d |
| 2 | Cache KPIs + palette anti-DOS | `Modules/Fiscal/Http/Controllers/CockpitController.php` + `Modules/Fiscal/Http/Controllers/PaletteSearchController.php` + event listeners NFeAutorizada/NFCeAutorizada | nenhum | 1d |
| 5 | Onda 3 fixtures cStat (6 testes propostos) | `Modules/NfeBrasil/Tests/Feature/Rejeicao*Test.php` + `Modules/NfeBrasil/Tests/Fixtures/SefazResponses/cstat-*.xml` + `Modules/NfeBrasil/Tests/Helpers/RejeicaoFixture.php` | Wagner aprovar 6 fixtures XML | 2-3d |

**Score projetado:** 66 → **80** (D5 0→13, D6 5→9, D2 13→16). Bucket: **Bom Alto** → **Excelente** (limiar 80).

**Pré-flight checks Onda 1:**
- [ ] Wagner sign-off Larissa briefing (1 reunião 30min — humano-limitado)
- [ ] Deploy CT 100 verde (não-regressão)
- [ ] PLANO-TESTES Onda 1+2 verde no CI (`php artisan test --filter=MultiTenant`)
- [ ] Charter `Cockpit.charter.md` revisado pra mencionar cache

### 🟡 CONSOLIDAR (Onda 1 → Onda 2) — 80 → 88

**Goal:** eliminar hardcodes SPED + IBS/CBS Reforma prep + Strategy regime.

| # | Gap | Áreas isoladas (paths) | Pré-req | Esforço |
|---|---|---|---|---|
| 3 | MotorTributario integrado SPED | `Modules/Fiscal/Services/SpedIcmsIpiGeneratorService.php` (refactor) + `Modules/NfeBrasil/Services/Tributacao/ProdutoFiscalContext.php` (factory `fromTransactionItem`) | GAP-1 (biz=4 live) | 2-3d |
| 9 | Items NFe reais via XML parsed | `Modules/Fiscal/Services/SpedIcmsIpiGeneratorService.php::extrairItens` + JOIN `transactions_items` ou `parseXmlNfe` helper novo | GAP-3 | 2d |
| 4 | Onda 6 IBS/CBS (7 testes + migration) | `Modules/NfeBrasil/Database/Migrations/2026_XX_XX_add_ibs_cbs_to_nfe_fiscal_rules.php` + `Modules/NfeBrasil/Services/MotorTributarioService.php` (expansão) + `Modules/NfeBrasil/Tests/Feature/IbsCbs*Test.php` | sped-nfe issue #1274 resolvido OU patch local | 3-5d |
| 7 | Strategy pattern por regime | Novo dir `Modules/Fiscal/Services/Sped/Strategies/` + 3 classes (`SimplesNacionalStrategy.php`, `LucroPresumidoStrategy.php`, `LucroRealStrategy.php`) | GAP-3 | 2d |

**Score projetado:** 80 → **88** (D4 12→17, D2 16→18). Bucket: **Excelente** sólido.

**Pré-flight checks Onda 2:**
- [ ] sped-nfe lib release com suporte IBS/CBS confirmado (issue #1274) — fallback: patch local + ADR
- [ ] Wagner approvalde mudança schema `nfe_fiscal_rules` (5 colunas novas: `c_class_trib`, `cst_ibs`, `cst_cbs`, `aliquota_ibs`, `aliquota_cbs`)
- [ ] Larissa biz=4 confirmar regime tributário atual + se for Lucro Presumido em 2026 acionar Strategy nova

### 🔵 EVOLUIR (Onda 2 → Onda 3) — 88 → 92+

**Goal:** EFD-Contribuições + Bloco H real + chaos engineering.

| # | Gap | Áreas isoladas | Esforço |
|---|---|---|---|
| 8 | EFD-Contribuições PIS/COFINS arquivo separado | Novo `Modules/Fiscal/Services/EfdContribuicoesGeneratorService.php` (mesma estrutura SPED ICMS-IPI mas Bloco M) + nova rota `/fiscal/sped/contribuicoes/{ano}/{mes}` | 1+sem |
| 10 | Bloco H integração Stock 31/12 | `Modules/Fiscal/Services/SpedIcmsIpiGeneratorService.php::registroH010` novo + JOIN `Modules/ProductCatalogue` Stock | 2d |
| 6 | Cron cert health-check daily | Novo `Modules/Fiscal/Console/Commands/CertHealthCheckCommand.php` + schedule em `Kernel.php` daily 6h | 4h |
| 11 | Dashboard Grafana | `infra/grafana/dashboards/fiscal.json` novo painel | 4h |
| 12 | Palette full-text index | Migration MySQL `ALTER TABLE nfe_emissoes ADD FULLTEXT(motivo)` + refactor query | 4h |

**Score projetado:** 88 → **92+** com folga pra novas pressões regulatórias (Reforma 2027+).

---

## Sequência recomendada (paralelo vs sequencial)

```
┌─── Onda 1 ESTABILIZAR (paralelo, 3 dev-days corridos) ──────────┐
│ implementer-A: GAP-1 (biz=4 habilitar) ── 1d  ─┐                │
│ implementer-B: GAP-2 (cache + palette)  ── 1d  ├─ smoke 7d     │
│ implementer-C: GAP-5 (Onda 3 fixtures)  ── 3d ─┘  biz=4        │
└─────────────────────────────────────────────────────────────────┘
                    ↓ pré-req: biz=4 LIVE
┌─── Onda 2 CONSOLIDAR (sequencial, 6-8 dev-days) ────────────────┐
│ STEP 1: GAP-3 (MotorTributario integrado) ── 2-3d              │
│   ↓                                                              │
│ STEP 2: GAP-9 (items reais) ── 2d                               │
│   ↓                                                              │
│ STEP 3 paralelo: GAP-4 (IBS/CBS) + GAP-7 (Strategy) ── 3-5d    │
└─────────────────────────────────────────────────────────────────┘
                    ↓
┌─── Onda 3 EVOLUIR (paralelo, 4-7 dev-days) ─────────────────────┐
│ paralelo total — gaps independentes                              │
└─────────────────────────────────────────────────────────────────┘
```

**Dependências críticas:**
- GAP-3 deve preceder GAP-9 (items reais dependem do contexto MotorTributario)
- GAP-3 deve preceder GAP-4 (IBS/CBS injetado no mesmo pipeline)
- GAP-1 deve preceder Onda 2 (sem biz=4 LIVE, refactor SPED não tem validação real)
- GAP-2 (cache) pode rodar em paralelo com tudo (independent)
- GAP-5 (Onda 3 cStat) pode rodar em paralelo (fixtures XML não tocam código produção)

---

## Custo total projetado

### Dev-days IA-pair (fator 10x ADR 0106 aplicado)

| Onda | Esforço | Wall-clock típico |
|---|---|---|
| ESTABILIZAR | 3d IA-pair + 7d smoke humano | ~2 semanas |
| CONSOLIDAR | 7-10d IA-pair + 1 dia Wagner approve schema | ~3 semanas |
| EVOLUIR | 4-7d IA-pair + lib sped-efd-icms-ipi avaliação | ~2 semanas |
| **TOTAL** | **14-20d IA-pair** | **~7 semanas wall-clock** |

### Infra & runtime

| Item | Custo extra |
|---|---|
| Redis cache (CT 100) | R$ [redacted Tier 0] — já existe |
| MySQL FULLTEXT index | R$ [redacted Tier 0] — schema change apenas |
| OTel span volume | +5% — negligível |
| Grafana painel | R$ [redacted Tier 0] |

### LLM custo

| Item | Custo extra |
|---|---|
| Auditoria SPED via IA (sugestão correção cStat) | **R$ [redacted Tier 0]** — receitas determinísticas PHP (anti-hook Charter) |
| Classificação NCM-assistida (futuro) | Backlog Onda 4+ — fora deste escopo |

**Custo total ondas 1-3: R$ [redacted Tier 0] infra + R$ [redacted Tier 0] LLM + ~14-20 dev-days IA-pair.**

---

## Surpresa estratégica

**Achado oculto:** o `SpedIcmsIpiGeneratorService` **funciona acidentalmente** pra Larissa biz=4 (Simples Nacional vestuário NCM 61/62 CSOSN 102 CFOP 5102) porque os 6 hardcodes coincidem com o caso mais comum vestuário Simples Nacional sem crédito ICMS. **Mas isso é coincidência feliz** — não decisão arquitetural.

**Consequências:**
1. **Habilitar SPED export pra biz=4 hoje** seria seguro tecnicamente (TXT válido pra Simples Nacional revenda interna), MAS quebra na primeira venda interestadual contribuinte (CFOP 6102 com ICMS-ST vestuário RS→SP) — comum vestuário atacado.
2. **Quando IBS/CBS virar obrigatório highlight em ago/2026**, mesmo Simples Nacional vai precisar `cClassTrib` real — hardcode `'00000000'` NCM rejeita.
3. **A integração com `MotorTributarioService` (GAP-3) é pré-requisito não-óbvio da Onda 6 IBS/CBS**, não só refactor de qualidade — é blocker regulatório.

**Recomendação:** **NÃO habilitar SPED export biz=4 nesta wave inicial.** Feature flag `feature.fiscal.sped.simples_only=true` per business até Onda 2 completar GAP-3. Habilitar visualização (`/fiscal/sped` página) ✅ — bloquear download (`POST /fiscal/sped/icms-ipi/{ano}/{mes}`) ❌ até GAP-3.

---

## Risk register

| # | Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|---|
| R1 | NFe Larissa biz=4 rejeitada por hardcode CFOP/CST quando ela mudar pra venda interestadual contribuinte | 🟡 média | 🔴 alta (multa fiscal + cliente perdido) | Feature flag `feature.fiscal.sped.simples_only` até GAP-3 + alerta UI quando NFe tiver CFOP 6xxx |
| R2 | Reforma Tributária IBS/CBS jan/2027 quebra emissão em massa | 🔴 alta (regulatório certo) | 🔴 crítica (sistema inoperável BR) | Onda 6 (GAP-4) precisa estar verde antes ago/2026 (4 meses de margem hoje) |
| R3 | Multas por SPED rejeitado no PVA-EFD CONFAZ | 🟡 média | 🟡 média (advertência primeira vez) | GAP-9 + GAP-10 + smoke biz=1 PVA-EFD homologação |
| R4 | Larissa habilitada mas UX ruim cockpit Fiscal vs NfeBrasil canônico (já familiar) | 🟡 média | 🟢 baixa (rollback feature flag) | Smoke 7d com Larissa + iterar 2 ciclos UX antes lock |
| R5 | Cert A1 oimpresso institucional vence (ADR 0186) e fallback de Larissa também — emissão para | 🟢 baixa | 🔴 crítica | GAP-6 (cron daily health-check) + ADR 0186 invariante #5 review_trigger |
| R6 | Lib `nfephp-org/sped-nfe` não publicar versão IBS/CBS antes ago/2026 obrigatório | 🟡 média | 🔴 alta | Monitorar issue #1274 GitHub mensal + patch local pronto + ADR feature wish |
| R7 | Palette `LIKE %q%` degrada quando biz=4 acumular 50k+ NFe (chegou em ~1 ano hoje volume Larissa) | 🟡 média | 🟢 baixa (UX só) | GAP-12 (full-text index) Onda 3 |

---

## Tasks pré-formatadas pra `tasks-create` MCP

> Formato sugerido pra Wagner aprovar e disparar via MCP. NÃO criadas (escopo dossier).

```yaml
# Onda ESTABILIZAR
- module: fiscal
  priority: P0
  title: "GAP-FISCAL-001 — Habilitar cockpit Fiscal para biz=4 Larissa (piloto)"
  description: |
    Atribuir permissões fiscal.* (fiscal.access, fiscal.nfe.view, fiscal.nfe.acoes,
    fiscal.dfe.manage) pra user.id=10 (Larissa) e user.id=11 (rota.vendas-04) em
    biz=4. Smoke 7d com Larissa reportando UX gaps. NÃO habilitar fiscal.sped.export
    nesta wave (vide R1 + GAP-3 pré-req).
  related_adrs: [0105, 0186]
  estimate: 1d IA-pair + 7d smoke humano

- module: fiscal
  priority: P0
  title: "GAP-FISCAL-002 — Cache Redis KPIs Cockpit (60s) + anti-DOS palette"
  description: |
    Cache Redis 60s `fiscal:cockpit:kpis:biz:{businessId}` invalidado em
    NFeAutorizada/NFCeAutorizada events. Reusar query cert/dfe entre KPI e alerts.
    Anti-DOS palette: query >=3 chars + planejar índice FULLTEXT futuro.
  estimate: 1d IA-pair

- module: nfebrasil  # nasce em NfeBrasil (não Fiscal)
  priority: P0
  title: "GAP-FISCAL-005 — Onda 3 fixtures cStat top-10 SEFAZ + sugestão UI"
  description: |
    Criar 6 fixtures XML SEFAZ (cStat 225/539/204/217/207/694) +
    RejeicaoFixture helper + 6 Pest tests. Integrar com NfeStatusController
    sugestões UI (ADR UI-0002).
  related_adrs: [0186]
  estimate: 2-3d IA-pair

# Onda CONSOLIDAR
- module: fiscal
  priority: P0
  title: "GAP-FISCAL-003 — Integrar MotorTributarioService no SpedIcmsIpiGeneratorService"
  description: |
    Eliminar 6 hardcodes (NCM 00000000, CST 102, CFOP 5102, ALIQ 0, COD_MUN,
    COD_PART). Usar MotorTributarioService::calcular() pra cada item da NFe.
    Pré-req: GAP-1 (biz=4 live pra validação real).
  related_adrs: [0094]
  estimate: 2-3d IA-pair

- module: fiscal
  priority: P0
  title: "GAP-FISCAL-009 — Items NFe reais via JOIN transactions_items ou parse XML"
  description: |
    Hoje extrairItens() faz 1 item por NFe ('PDV-X'). PVA-EFD valida N itens por
    NFe. JOIN transactions_items OR parse xml_processado.
  estimate: 2d IA-pair

- module: nfebrasil
  priority: P0
  title: "GAP-FISCAL-004 — Onda 6 IBS/CBS Reforma NT 2025.002 — PRAZO ago/2026"
  description: |
    Migrations append-only nfe_fiscal_rules (5 colunas IBS/CBS) + 7 Pest tests
    (matriz CST × cClassTrib, alíquotas 27 UFs IBS, CBS federal, IS,
    7 grupos XML, MotorTributario expansão). Pré-req: lib sped-nfe versão
    IBS/CBS (issue #1274 GitHub).
  related_adrs: [0094]
  estimate: 3-5d IA-pair

- module: fiscal
  priority: P1
  title: "GAP-FISCAL-007 — Strategy pattern por regime (Simples/Presumido/Real)"
  description: |
    3 classes em Modules/Fiscal/Services/Sped/Strategies/ resolvidas via
    NfeBusinessConfig.regime. Sem isso, SPED hardcoded Simples quebra
    quando piloto migrar regime.
  estimate: 2d IA-pair
```

---

## Pré-flight checks ANTES de disparar implementadores juniores (Fase 3)

- [ ] **Wagner aprova este dossier integral** (ler TL;DR + Surpresa estratégica + Roadmap)
- [ ] **Wagner sign-off Larissa briefing** (30min reunião — explicar piloto Fiscal cockpit, expectativa 7d smoke)
- [ ] **Verificar deploy CT 100 verde** (`php artisan jana:health-check` 5 verdes)
- [ ] **PLANO-TESTES Onda 1+2 verde** (`php artisan test --filter=MultiTenant` + `--filter=Wave28`)
- [ ] **Issue GitHub `nfephp-org/sped-nfe#1274` checked** (status lib IBS/CBS — feature flag se ainda não released)
- [ ] **R7: confirm Redis CT 100 health** (cache layer da GAP-2 depende)
- [ ] **Feature flag `feature.fiscal.sped.simples_only` provisionada** (true por default — proteção GAP-3 não-completa)

---

## Resumo decisões arquiteturais sênior (TL;DR pra implementadores)

| Decisão | Por quê | Alternativa rejeitada |
|---|---|---|
| **Cache KPIs Redis 60s** | Cockpit visita ~10×/dia × 56 biz = 560 hits/dia × 10 queries = 5600 queries economizadas | Cache per-user (over-engineering — KPIs são by-biz globais) |
| **MotorTributarioService reuso** | Já existe, cascade 4 níveis testado, cache em memória, métricas OTel — duplicar = dívida | Mini-calculadora própria Fiscal (rejeitada — viola "thin agregador") |
| **Strategy Pattern por regime** | 3 regimes BR (Simples/Presumido/Real) têm semântica SPED radicalmente diferente — `switch` em Service vira anti-pattern | Subclassing SpedGenerator (Laravel Event::listen exact-match — perde dispatch automático) |
| **biz=4 piloto Larissa** | Único cliente real com volume + já tem cert + já emite NFe — convergência sortuda | biz=164 Martinho (em migração ainda, sem cert), biz=1 Wagner (operador interno, não conta D5) |
| **Feature flag SPED simples_only** | Hardcodes funcionam acidentalmente pra Simples — habilitar SPED export biz=4 sem GAP-3 é risco multa quando fizer venda contribuinte | Liberar tudo (rejeitada — R1 multa fiscal) |
| **Onda 6 IBS/CBS pré-req Onda 2 e não Onda 1** | Prazo regulatório agosto/2026 — 4 meses margem hoje (~25mai/2026). Permite ondas 1+2 primeiro com escala graduada | Onda 6 primeiro (rejeitada — sem cache + piloto, IBS/CBS não tem onde aterrar) |

---

## Referências

### Documentos canônicos oimpresso (reusados, NÃO duplicados)
- [memory/requisitos/Fiscal/SPEC.md](SPEC.md) — US-FISCAL-001..017
- [memory/requisitos/Fiscal/BRIEFING.md](BRIEFING.md) — capacidades + integrações NfeBrasil
- [memory/requisitos/Fiscal/PLANO-TESTES-FISCAL.md](PLANO-TESTES-FISCAL.md) — 7 ondas testes (este dossier reusa Onda 3+6)
- [Modules/Fiscal/SCOPE.md](../../../Modules/Fiscal/SCOPE.md) — frontmatter v1.8.0
- [Modules/NfeBrasil/SCOPE.md](../../../Modules/NfeBrasil/SCOPE.md) — motor real
- [memory/reference/clientes-ativos.md](../../reference/clientes-ativos.md) — biz=4 Larissa 99% volume

### ADRs canônicas relevantes
- [ADR 0089](../../decisions/0089-capterra-driven-module-evolution.md) — Capterra-driven (skill + 3 artefatos)
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (princípios duros)
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — Tests biz=1, NUNCA biz=4
- [ADR 0103](../../decisions/0103-eventos-fiscais-separados-por-modelo.md) — Events fiscais por modelo (informa cache invalidation)
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal qualificado
- [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — Recalibração fator 10x IA-pair
- [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM cancel cascade
- [ADR 0178](../../decisions/0178-restauracao-campos-fiscais-br-canon.md) — Restauração campos fiscais BR
- [ADR 0186](../../decisions/0186-chain-certificado-sefaz-consulta-cadastro.md) — Chain cert A1 + SEFAZ (IRREVOGÁVEL)

### Pesquisas externas 2026 (WebSearch — 11 totais)

1. [Cronograma Reforma Tributária 2026-2033](https://planning.com.br/reforma-tributaria/cronograma-reforma-tributaria-2026-2033/)
2. [Receita Federal Orientações 2026](https://www.gov.br/receitafederal/pt-br/acesso-a-informacao/acoes-e-programas/programas-e-atividades/reforma-consumo/orientacoes-2026)
3. [Bling — Regras de Operação NCM + UF](https://ajuda.bling.com.br/hc/pt-br/articles/360034982693)
4. [Omie — Regra Geral Tributação 3 níveis](https://ajuda.omie.com.br/pt-BR/articles/10947561)
5. [TaxCloud — Best Sales Tax APIs 2026](https://taxcloud.com/blog/sales-tax-apis/)
6. [Tax Thomson Reuters — Agentic AI 2026](https://tax.thomsonreuters.com/blog/an-evolution-of-tax-tools-and-how-agentic-ai-will-shape-2026/)
7. [SEFAZ-AM — IBS/CBS NFe obrigatórios jan/2026](https://www.sefaz.am.gov.br/noticias/31893)
8. [TecnoSpeed — NT 2025.002 IBS/CBS grupos](https://blog.tecnospeed.com.br/nota-tecnica-reforma-tributaria-nfe-nfce/)
9. [GitHub nfephp-org/sped-nfe issue #1274 IBS/CBS](https://github.com/nfephp-org/sped-nfe/issues/1274)
10. [TaxRadar — NCM Têxteis e Vestuário 2026](https://taxradar.app/blog/ncm/ncm-texteis-vestuario-guia-completo-classificacao)
11. [SEFAZ Disponibilidade NF-e](https://www.nfe.fazenda.gov.br/portal/disponibilidade.aspx)
12. [Refactoring.guru — Strategy Pattern PHP](https://refactoring.guru/design-patterns/strategy/php/example)

### Bibliotecas BR confirmadas

- [`nfephp-org/sped-nfe`](https://github.com/nfephp-org/sped-nfe) — emissão NFe (canon `Modules/NfeBrasil`, ADR ARQ-0002)
- [`nfephp-org/sped-efd-icms-ipi`](https://packagist.org/packages/nfephp-org/sped-efd-icms-ipi) — **NÃO usado hoje** (gerador manual), avaliar migração quando suportar IBS/CBS

---

**Fim do dossier.** Auditor sênior: Opus 4.7 sessão 2026-05-25 25min. Próximo passo: Wagner aprova → spawn `audit-implement-expert` em paralelo pros 3 gaps Onda 1 (GAP-001, GAP-002, GAP-005).
