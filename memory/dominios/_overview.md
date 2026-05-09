---
type: overview
authority: canonical
lifecycle: ativo
related_adrs: [0118, 0119]
---

# Overview — Migration Factory dominios

Conhecimento canônico de **sistemas externos legacy** que oimpresso ingere via Migration Factory ([ADR 0119](../decisions/0119-migration-factory-capacidade-institucional.md)). Estrutura formalizada por [ADR 0118](../decisions/0118-segregacao-dominios-externos-clientes-legacy.md).

## Visão estratégica

oimpresso vira a "saída fácil" de qualquer ERP legacy. Argumento de venda: *"traga seu banco, migramos tudo — 1 semana e você está rodando aqui sem perder cliente, fornecedor, NF, financeiro nem produção"*.

Concorrentes nicho gráfico (Zênite/Mubisys/Alfa Networks/Visua/Calcgraf/Calcme) viram **fonte de leads** ("clientes deles podem migrar pro oimpresso sem dor"). ERPs SaaS BR (Bling, Tiny, Conta Azul) abrem mercado de pequenas empresas.

## Estrutura `memory/dominios/`

```
memory/dominios/
├── _overview.md           ← este arquivo (taxonomia + roadmap)
├── _patterns/             ← 7 patterns reusáveis agnósticos de fonte
│   ├── README.md
│   ├── 01-strangler-fig-acl-brownfield-ai.md
│   ├── 02-bridge-tables-para-core.md
│   ├── 03-upsert-idempotente-multi-tenant.md
│   ├── 04-metadata-json-denormalized.md
│   ├── 05-schema-vivo-vs-reconstruido.md
│   ├── 06-pest-test-multi-tenant.md
│   └── 07-three-mode-importer.md
├── _template/             ← skeleton pra novo sistema externo
│   ├── README.md
│   └── (estrutura mínima)
└── <sistema>/             ← 1 pasta por sistema externo
    ├── README.md
    ├── ARQUITETURA.md
    ├── CONVENCOES.md
    └── modulos/<dom>/
        ├── tabelas/<TABELA>.md
        ├── _index.md
        └── MAPPING.md     ← ACL bilíngue (única lugar que mistura vocabulários)
```

## Catálogo de sistemas externos

### Em produção (1)

| Sistema | Tipo | Pasta | Status |
|---|---|---|---|
| **WR Comercial** | Desktop Delphi (Firebird) | [`wr-comercial/`](wr-comercial/README.md) | ✅ pipeline ponta-a-ponta validado (3 contas reais smoke biz=1, sessão 2026-05-09) |

### Previsto sob demanda (não atacar antecipadamente)

Categorizados por tipo de fonte:

#### ERPs SaaS BR (REST API)
| Sistema | URL | Estimativa LOE | Justificativa |
|---|---|---|---|
| **Bling** | bling.com.br | M (REST documentada) | Mercado grande pequenas empresas |
| **Tiny** | tiny.com.br | M | Concorrente direto Bling |
| **Conta Azul** | contaazul.com | M | Foco financeiro/contábil |
| **Omie** | omie.com.br | M | R$ [redacted Tier 0]Bi NFs/mês, segmento maior |

#### Concorrentes nicho gráfico (varia)
| Sistema | URL | Tipo provável |
|---|---|---|
| **Zênite** (NetCalc/GE) | zsl.com.br | Desktop legacy + cloud — investigar |
| **Mubisys** | mubisys.com | SaaS internacional vibe — REST esperado |
| **Alfa Networks** | alfanetworks.com.br | Desktop com cálculo m² |
| **Visua** | visua.com.br | Cunhou termo "FPV" — provável legacy |
| **Calcgraf** | calcgraf.com.br | 40 anos, 1000 sistemas — provável Delphi/desktop legacy |
| **Calcme** | calcme.com.br | Ecossistema (Calcme3D, Chatme, Calcpay) — REST possível |

#### ERPs enterprise (LOE alto)
| Sistema | Tipo | Justificativa |
|---|---|---|
| **Sankhya** | Cloud + on-premise | Demanda baixa, valor alto |
| **TOTVS Protheus** | On-premise | Idem |
| **Microsiga** | Idem | Idem |

#### Gateways financeiros (já parcialmente integrados)
| Sistema | Status atual |
|---|---|
| **Asaas** | Aliado/integrado (não migra — co-existe) |
| **Iugu** | Aliado/integrado |
| **Pagar.me** | Sob demanda |
| **Stripe** | Sob demanda |

## Roadmap 12-18 meses

| Marco | Quando | Critério de prontidão |
|---|---|---|
| ✅ Migração WR Comercial — Wagner biz=1 (smoke) | 2026-05-09 | 3 contas reais importadas com idempotência |
| ⏳ Migração WR Comercial — Wagner biz=1 prod completo | Próximas 2 semanas | BOLETOS + FINANCEIRO_BOLETO_HISTORICO + decisões pendentes MAPPING resolvidas |
| ⏳ Migração WR Comercial — 2º cliente real (qualquer alias do registry) | Mês 1-2 | Generaliza engine sem refatoração maior; valida `business_id` resolution per-cliente |
| ⏳ Migração WR Comercial — todos 49 clientes legacy | Mês 3-4 | Self-service ou batch; cleanup pasta dominios/wr-comercial completa |
| ⏳ **Primeira fonte concorrente** (Bling/Tiny escolhido sob demanda) | Mês 4-6 | DriverInterface abstraído + ACL pra REST API — gatilho pra `Modules/MigrationFactory/` Laravel formal |
| ⏳ 3-5 fontes concorrentes integradas | Mês 6-12 | Catálogo público + comercial; SLA pra Migration as a Service |
| ⏳ Self-service guiado por IA | Mês 12+ | UI + automação Claude Opus pair |

**Princípio**: cada marco depende de **demanda real** (cliente paga e reporta) ou **métrica detecta drift** ([ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)). Não atacar especulativamente.

## Como adicionar sistema novo

1. **Criar pasta** `memory/dominios/<sistema>/` copiando de [`_template/`](_template/)
2. **Documentar** ARQUITETURA, CONVENCOES (específicas do sistema), README com identidade
3. **Aplicar Pattern 01** ([Strangler Fig + ACL + Brownfield AI](_patterns/01-strangler-fig-acl-brownfield-ai.md))
4. **Atacar 1 entidade por vez** começando por contas bancárias OU clientes (depende de demanda)
5. **Reutilizar Patterns 02-07** (bridge tables, UPSERT idempotente, metadata JSON, schema vivo, Pest test, three-mode)
6. **Documentar lacunas** em `MAPPING.md` por entidade — Wagner valida decisões pendentes
7. **Atualizar este overview** adicionando linha no catálogo

## Quem opera

- **Wagner** — superadmin, executa importers contra prod com `--confirm`
- **Time interno (Felipe/Maiara)** — opera dev/local, valida output via Pest, propõe mapping novo
- **Cliente externo** — não toca factory direto. UI futura `/migration-factory/` ficará restrita `can('superadmin')`

## Modelo de receita (a validar)

| Modelo | Quem faz | Quem paga | Quando ativar |
|---|---|---|---|
| Migration as a Service | Wagner+time | Cliente paga setup R$ X | Após 3+ clientes reais migrados sem incidente |
| Self-service guiado | Cliente + Claude pair | Cliente paga só licença mensal | Após UI Migration Factory + automação Brain B |
| Pacote "Onboarding Premium" | Wagner+time | Anual com migração inclusa | Após Migration as a Service consolidado |

## Referências

- [ADR 0118 — Segregação dominios externos](../decisions/0118-segregacao-dominios-externos-clientes-legacy.md)
- [ADR 0119 — Migration Factory institucional](../decisions/0119-migration-factory-capacidade-institucional.md)
- [Patterns reusáveis](_patterns/README.md)
- [Sessão 2026-05-09 — pipeline completo](../sessions/2026-05-09-pipeline-legacy-migration-completo.md)
- Auto-mem `reference_concorrentes_com_visual` — research detalhado dos 6 concorrentes nicho gráfico (referência pra positioning, não pra migração técnica)
