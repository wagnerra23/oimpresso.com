# Modules/ComunicacaoVisual

> Vertical gráfica rápida BR — CNAE 1813-0/01 (impressão de material para uso publicitário).
> Status: 🟡 em construção · Piloto previsto Q3/2026.
> ADR mãe: [0121](../../memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md) §P7.

## 1. Objetivo

ERP especializado para **gráficas rápidas e ateliês de comunicação visual** (lona, fachada, plotter, banner, adesivo, fachada ACM). Cobre o ciclo completo:

```
Orçamento (cálculo m²)
   └─ Aprovação cliente
       └─ Ordem de Produção (PCP gráfico via FSM canon)
           └─ Apontamento operador (drift m² produzido vs orçado)
               └─ Faturamento dual-doc (NFe55 mercadoria + NFSe56 serviço)
                   └─ Entrega + Instalação
```

## 2. Arquitetura

```
Modules/ComunicacaoVisual/
├── BRIEFING.md              ← estado consolidado (1 página)
├── CHANGELOG.md             ← histórico de releases
├── SCOPE.md                 ← contém/não-contém (frontmatter governance)
├── module.json              ← metadados nWidart
├── composer.json
├── Config/
│   ├── config.php           ← config geral módulo
│   └── retention.php        ← LGPD Art. 16 janelas de retenção
├── Entities/                ← Eloquent Models (todas com business_id global scope)
│   ├── Orcamento.php, OrcamentoItem.php
│   ├── Os.php, OrdemProducao.php
│   ├── Apontamento.php      ← APPEND-ONLY (sem SoftDeletes — registro legal)
│   ├── Material.php, Substrato.php, Acabamento.php
│   └── Instalacao.php, InstalacaoCatalogo.php
├── Http/
│   ├── Controllers/         ← OrcamentoController, ApontamentoController, DataController
│   └── Requests/            ← FormRequests com PT-BR messages
├── Services/                ← OrcamentoCalculator, ApontamentoTracker
├── Database/Migrations/     ← cv_* (Sprint 1 canon) + comvis_* (Sprint 0 legacy)
├── Database/Seeders/        ← FsmProcessoComunicacaoVisualSeeder + DemoSeed
├── Routes/                  ← web.php + api.php (prefixo /com-visual/*)
├── Resources/lang/          ← PT-BR strings
└── Tests/Feature/           ← Pest tests (biz=99 sempre)
```

## 3. Como o cliente usa (jornada típica)

**Persona:** Larissa-equivalente — dona/operadora de gráfica pequena (1-5 funcionários, ~R$ 30k/mês faturamento).

### 3.1 Atender pedido novo
1. Cliente liga: "Preciso de 2 lonas 3m × 1.5m com bastões"
2. Vendedor abre `/com-visual/orcamentos/novo`
3. Sistema calcula: 9m² × R$ 45/m² (lona 440g) + bastões R$ 30/un × 4 = **R$ 525**
4. Envia orçamento por WhatsApp/e-mail (PDF + link aprovação cliente)

### 3.2 Aprovação → produção
5. Cliente aprova online (1 clique)
6. Sistema gera **OS automática** + dispara FSM: stage `arte_aprovada`
7. Designer recebe notification → upload arte → muda stage `corte`
8. Plotter aponta: clica "iniciar produção" → trabalha → "finalizar" + informa m² real
9. Sistema calcula **drift**: orçado 9m² vs produzido 9.2m² = +2.2% (dentro tolerância)

### 3.3 Faturamento + entrega
10. Stage `faturamento` dispara NFe55 (mercadoria) + NFSe56 (serviço impressão) em paralelo
11. NFe-de-boleto-pago: emite NFe automática quando boleto Inter PJ paga
12. Stage `instalacao_agendada` → motorista recebe roteiro → cliente assina entrega

### 3.4 Visibilidade Wagner / Dono
- Dashboard ROI: m² produzido/mês × drift médio × margem média
- Kanban PCP: vê tudo em produção (consome shared `Modules/Repair` Kanban)
- IA Jana: "Quanto faturei essa semana em lona 440?" → resposta com dados reais

## 4. Multi-tenant Tier 0 (IRREVOGÁVEL — [ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))

- Toda Entity tem `business_id` global scope
- Job assíncrono SEMPRE recebe `$businessId` no constructor
- Pest cross-tenant biz=1 vs biz=99 em `Tier0GuardTest.php` — 100% verde
- Cliente A NÃO enxerga dado de cliente B mesmo com bug de query

## 5. LGPD (Art. 16 + 18)

- `Config/retention.php` define janelas: Apontamento/Orcamento/Os = 5 anos (CCom Art. 195)
- Entities core (`Orcamento`, `Os`, `Apontamento`) com `LogsActivity` (Spatie)
- Direito ao esquecimento: anonimiza `observacoes` + preserva ids fiscais
- `Tests/Feature/LgpdComplianceTest.php` valida automaticamente

## 6. Testes

```bash
# Local (Hostinger ou Herd)
php artisan test --filter ComunicacaoVisual

# Suite específica
php artisan test Modules/ComunicacaoVisual/Tests/Feature/CustomerJourneyTest.php
```

**Sempre biz=99** ([ADR 0101](../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)) — NUNCA biz=4 (ROTA LIVRE) ou biz real.

## 7. Concorrentes (referência)

| Concorrente | Cobre | Falta |
|-------------|-------|-------|
| Mubisys | Cálculo m² + base SP | IA, dual-doc fiscal, Tier 0 |
| Zênite | PCP visual | Cálculo m² auto, NFe-boleto |
| Calcgraf | Calculadora grátis | PCP, apontamento, FSM |

## 8. Próximos passos

Ver [BRIEFING.md §8](BRIEFING.md). TL;DR: aguardar piloto reportar dor (ADR 0105).

## 9. Comandos artisan

```bash
php artisan comvis:seed-demo --business=99 --reset    # popula demo idempotente
php artisan migrate --path=Modules/ComunicacaoVisual/Database/Migrations
```

## 10. Links relacionados

- SPEC completa: [memory/requisitos/ComunicacaoVisual/SPEC.md](../../memory/requisitos/ComunicacaoVisual/SPEC.md)
- Capterra ficha: [memory/requisitos/ComunicacaoVisual/CAPTERRA-FICHA.md](../../memory/requisitos/ComunicacaoVisual/CAPTERRA-FICHA.md) (se existir)
- ADR 0121 modular vertical · ADR 0143 FSM canon · ADR 0093 multi-tenant
