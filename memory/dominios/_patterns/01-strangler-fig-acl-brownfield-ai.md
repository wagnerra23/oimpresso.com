# Pattern 01 — Strangler Fig + Anticorruption Layer + Brownfield AI

**Status**: canônico desde 2026-05-09 (validado em migração WR Comercial)
**Fonte canon**: [ADR 0118](../../decisions/0118-segregacao-dominios-externos-clientes-legacy.md)

## Contexto

Cliente vem de ERP velho (Delphi WR Comercial, Bling, Tiny, Sankhya, etc) e quer migrar pro oimpresso sem perder histórico. Reescrever sistema antigo é inviável; importar dados é o caminho.

## Problema

Como integrar 2 mundos com vocabulários, schemas e ciclos de vida diferentes **sem contaminação cruzada** que viole §5 SoC da [Constituição v2](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)?

## Solução — 3 camadas DDD canônicas + IA

1. **Strangler Fig Application** (Martin Fowler, 2004) — substitui legacy gradualmente, módulo a módulo. Não big-bang.

2. **Anticorruption Layer** (Eric Evans, *DDD* 2003 cap. 14) — `MAPPING.md` por módulo é a ACL **documentada**. Único arquivo bilíngue por design (vocabulário legacy + Laravel). Tudo mais isolado.

3. **Brownfield AI** ([artigo abr/2026](https://tianpan.co/blog/2026-04-12-brownfield-ai-integrating-llm-features-into-legacy-codebases)) — Claude Opus 4.7 atua como ACL agent: lê schema legacy, propõe mapping campo-a-campo, audita equivalência semântica.

## Estrutura física canônica

```
memory/
├── decisions/                              ADRs Laravel
├── requisitos/<Modulo>/                    Vocabulário Laravel
└── dominios/<sistema>/                     Vocabulário sistema externo
    ├── README.md
    ├── ARQUITETURA.md
    ├── CONVENCOES.md                       (específico desse sistema)
    └── modulos/<dom>/
        ├── tabelas/<TABELA>.md
        ├── _index.md
        └── MAPPING.md                      ← ACL bilíngue, único arquivo
```

`memory/clientes/<alias>/PERFIL.md` cross-cutting captura quirks por cliente.

## Exemplo concreto

WR Comercial → oimpresso, módulo financeiro/contas-bancarias:
- `dominios/wr-comercial/modulos/financeiro/` (vocabulário Delphi)
- `requisitos/Financeiro/SPEC.md` (vocabulário Laravel)
- `MAPPING.md` na fronteira — ex: `CONTAS Delphi → accounts core + fin_contas_bancarias`

Importer Python (`scripts/legacy-migration/import-contas-bancarias.py`) implementa a ACL em código.

## Quando NÃO usar

- Big-bang rewrite (cliente OK em perder histórico) — não precisa de ACL, é greenfield
- Sistema legacy já tem REST API estável e cliente quer **integração permanente** (não migração one-shot) — usar pattern de [ADR 0113](../../decisions/0113-integracao-delphi-laravel-ads-3-caminhos.md) (3 caminhos aditivos via Connector)

## Referências

- Eric Evans, *Domain-Driven Design* (2003) — Bounded Context, ACL, Context Map
- Martin Fowler, [*StranglerFigApplication*](https://martinfowler.com/bliki/StranglerFigApplication.html) (2004)
- Vaughn Vernon, *DDD Distilled* (2016)
- Sam Newman, *Monolith to Microservices* (2019) caps 1-3
