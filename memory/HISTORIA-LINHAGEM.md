---
slug: historia-linhagem
title: "História & Linhagem — Delphi WR/OfficeImpresso → UltimatePOS → oimpresso"
type: history
authority: canonical
lifecycle: ativo
owner: W
last_updated: 2026-07-19
related:
  - 0001-estender-ultimatepos-opcao-c
  - 0002-nwidart-laravel-modules
  - 0113-integracao-delphi-laravel-ads-3-caminhos
  - 0118-segregacao-dominios-externos-clientes-legacy
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0203-legacy-migration-pipeline-firebird-oimpresso-w29
pii: false
---

# História & Linhagem — de onde o oimpresso veio

> **Pra que serve:** o ÚNICO doc que conta a linhagem **convergida** do sistema numa timeline: os 26 anos de Delphi (WR Sistemas / OfficeImpresso) → a decisão de **estender UltimatePOS** ([ADR 0001](decisions/0001-estender-ultimatepos-opcao-c.md)) → o oimpresso modular Laravel de hoje. Antes disto a linhagem estava **órfã, espalhada em 10+ nós** (ADRs, `09-modulos-ultimatepos`, `why-oimpresso`, o hub Delphi) — nenhum contava a história inteira.
>
> **O que este doc É:** timeline **datada + ponteiros**. História não muda → não apodrece (lei-mãe [ADR 0256](decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md): o estático-verdadeiro sobrevive).
> **O que este doc NÃO é:** um mapa vivo. **Zero número vivo** (contagem de clientes, receita, módulos instalados) — esses vivem nos sistemas/docs vivos e são **apontados, nunca restateados** (lápide §5 2026-07-17). Estado ATUAL dos módulos = `npm run screen-coverage:report` + os BRIEFINGs; contagem de clientes legacy = [`clientes-legacy/_index.md`](clientes-legacy/_index.md).

## As três eras (visão de 1 olhada)

```
 ERA I — Delphi (26 anos, pré-2026)        ERA II — a decisão (2026-04)      ERA III — oimpresso modular (2026+)
 WR Sistemas / OfficeImpresso              estender UltimatePOS (ADR 0001)    núcleo Laravel + Modules/<Vertical>
 Object Pascal + Firebird, setor gráfico   NÃO build-from-scratch, NÃO fork   Vestuario (prod) · ComVis · OficinaAuto
 ~clientes legacy rodando offline          nasce como Modules/PontoWr2        Delphi segue vivo, integração ADITIVA
        └─────────────────── a ponte: ADR 0113 (aditiva) + ADR 0203 (pipeline Firebird→oimpresso) ──────────────────┘
```

## Timeline datada (fato · fonte)

| Quando | Marco | Fonte canônica |
|---|---|---|
| **~2000 (26 anos)** | WR Sistemas opera no setor gráfico — Delphi (Object Pascal, RAD Studio pré-v13) + **Firebird 3.0.12** (1 `.FDB`/cliente, WIN1252). Produto = **WR Comercial / OfficeImpresso** (sistema offline). | [`legacy-delphi/_INDEX.md`](legacy-delphi/_INDEX.md) · [`why-oimpresso.md`](why-oimpresso.md) |
| _(pré-2026 — registro FINO, ver §Buracos)_ | Evolução do produto Delphi ao longo das versões; base instalada de clientes gráfica. | — (Wagner/Felipe são a fonte) |
| **2026-04-18** | **Decisão fundadora ([ADR 0001](decisions/0001-estender-ultimatepos-opcao-c.md), por Eliana):** estender **UltimatePOS v6** (Laravel, que o cliente WR2 já rodava em prod) como módulo — Opção C. Descarta build-from-scratch (A) e fork (B). Nasce como `Modules/PontoWr2/` (ponto eletrônico, Portaria MTP 671/2021). | [ADR 0001](decisions/0001-estender-ultimatepos-opcao-c.md) |
| **2026-04-18** | Sistema de módulos = **nWidart/laravel-modules** ([ADR 0002](decisions/0002-nwidart-laravel-modules.md)). | [ADR 0002](decisions/0002-nwidart-laravel-modules.md) |
| **2026-04-19** | Inventário da instância UltimatePOS capturado (base herdada: Crm, Manufacturing, Repair, Superadmin, Woocommerce, Connector, **Officeimpresso** — o módulo-ponte pro Delphi — etc.). | [`09-modulos-ultimatepos.md`](09-modulos-ultimatepos.md) |
| **2026-Q2+** | **Pivô multi-vertical** ([ADR 0121](decisions/0121-oimpresso-modular-especializado-por-vertical.md)): de "módulo de ponto" pra **ERP modular especializado por vertical** — núcleo comum + `Modules/<Vertical>` profundo. | [ADR 0121](decisions/0121-oimpresso-modular-especializado-por-vertical.md) |
| **2026+** | **A ponte com o legado** (Delphi NÃO é reescrito/recompilado — integração **aditiva**): 3 caminhos ([ADR 0113](decisions/0113-integracao-delphi-laravel-ads-3-caminhos.md)) + pipeline de migração **Firebird → oimpresso** ([ADR 0203](decisions/0203-legacy-migration-pipeline-firebird-oimpresso-w29.md)) + segregação dos domínios legacy no memory/ ([ADR 0118](decisions/0118-segregacao-dominios-externos-clientes-legacy.md)). | ADRs 0113 · 0118 · 0203 |
| **2026+ (estado vivo)** | Verticais: **Vestuario** em prod (ROTA LIVRE), **ComunicacaoVisual** em construção (candidatos OfficeImpresso saudáveis), **OficinaAuto** piloto. _(estado ATUAL = ponteiros vivos, não aqui.)_ | [`why-oimpresso.md`](why-oimpresso.md) · BRIEFINGs |

## Os dois lados hoje (e quem é dono)

- **Lado Delphi (legado vivo):** ~50 clientes gráfica ainda rodam WR Comercial/OfficeImpresso offline. Hub canônico = [`legacy-delphi/_INDEX.md`](legacy-delphi/_INDEX.md) (**owner Felipe**) → `MAPEAMENTO-DELPHI-LARAVEL.md`, `SCHEMA-FIREBIRD.md`, `PEGADINHAS.md`. Skills: `officeimpresso-source-analysis` (lê `.pas`), `officeimpresso-financial-snapshot` (Firebird read-only).
- **Lado oimpresso (próxima geração):** Laravel 13.6 + PHP 8.4 + Inertia/React, multi-tenant Tier 0, modular. Stack em [`what-oimpresso.md`](what-oimpresso.md); por-que em [`why-oimpresso.md`](why-oimpresso.md).
- **A ponte:** `Modules/Officeimpresso/` (integração 1-via Delphi→oimpresso) + o pipeline da [ADR 0203](decisions/0203-legacy-migration-pipeline-firebird-oimpresso-w29.md).

## Buracos no registro — o que só o Wagner/Felipe sabem (a preencher)

> Estes pontos o registro escrito NÃO cobre bem; foram **vividos**, não documentados. Marcados aqui pra virarem fato datado quando o dono preencher (não invento):

- **Timeline pré-2026 do Delphi:** anos-chave, versões do WR Comercial, marcos do produto gráfico ao longo dos 26 anos. Hoje resumido em 2 frases no `why-oimpresso`.
- **Por que UltimatePOS especificamente** (vs outro ERP Laravel base) — o contexto da escolha da base antes da ADR 0001.
- **Momento do pivô** de "PontoWr2 pra WR2" → "oimpresso multi-vertical" (o quê/quando disparou a virada estratégica).
- **OfficeImpresso legacy → nome comercial atual:** a relação exata entre a marca "OfficeImpresso" (Delphi) e o "oimpresso" (Laravel).

---
**Atualizado:** 2026-07-19 — doc criado agregando a linhagem que estava órfã/espalhada (pedido Wagner: "agregar o histórico de migração e evolução do UltimatePOS e Office do Delphi"). Timeline + ponteiros, zero número vivo. [CC]
**Mantenedor:** Wagner (fonte da história) + Felipe (lado Delphi) + Claude (agregação).
