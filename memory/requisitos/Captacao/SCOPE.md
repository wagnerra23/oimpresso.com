---
module: Captacao
purpose: "Projetos de captação de recursos de ONGs e associações (primeira: Uniafibro-SC e associações-membro): fontes (edital, lei de incentivo, fundo, doação, parceria, voluntariado), projetos com proponente, aportes recebidos e comprovantes de despesa para prestação de contas. Decidido pela ADR 0452 em 2026-11-14."
migracao_ui: "n/a — módulo novo, nasce Inertia (sem Blade)"
contains:
  - "DataController"
  - "InstallController"
  - "CaptacaoAcesso + CaptacaoLiberado — regra única de acesso: pacote captacao_module e permissão captacao.access — ADR 0452 D1"
  - "Fontes de recurso (captacao_fontes) e projetos com proponente em texto (captacao_projetos) — US-CAPT-001"
  - "Aportes de dinheiro, bens, serviços ou horas de voluntário (captacao_aportes) e comprovantes de despesa (captacao_comprovantes), que não se apagam — US-CAPT-001"
not_contains:
  - "Título a receber e despesa no caixa → Modules/Financeiro (integração opcional na onda 5 da ADR 0452)"
  - "Cadastro da rede de associações e das associadas → Modules/Associacoes (sem FK: o proponente é texto)"
  - "Arquivo do comprovante → Modules/Arquivos (chamado, não duplicado)"
  - "Recibo de doação dedutível, emissão fiscal e cadastro de voluntário → fora (ADR 0452 §Fora desta decisão)"
depends_on:
  - Arquivos
owner: wagner
status: em construção
url_prefixes:
  - /captacao/install (instalação 1-clique; as telas chegam nas ondas 2 a 4 da ADR 0452)
---

# Modules/Captacao

## Por que existe

[W] pediu em 2026-11-14 a gestão dos projetos de captação da Uniafibro-SC e das associações-membro.
A [ADR 0452](../../decisions/0452-captacao-projetos-de-captacao-de-recursos.md) fixou as decisões D1–D8.

## Fronteira

O módulo é dono do ciclo do projeto: fonte → projeto → aporte → comprovante. Dinheiro no caixa é do
`Modules/Financeiro`; arquivo é do `Modules/Arquivos`; cadastro da rede é do `Modules/Associacoes`.

## Referências

- [SPEC](SPEC.md) · [BRIEFING](BRIEFING.md)
- [ADR 0452](../../decisions/0452-captacao-projetos-de-captacao-de-recursos.md) · [ADR 0093 — multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
