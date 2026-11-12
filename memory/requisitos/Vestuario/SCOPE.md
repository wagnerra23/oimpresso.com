---
module: Vestuario
purpose: "Vertical lojas de vestuário e moda BR (CNAE 4781-4/00): capacidades que o núcleo não tem por serem específicas do ramo — etiqueta TAG térmica (ZPL e PDF, tamanho x cor x coleção), curva de compra por tamanho e remarcação por coleção — mais os settings por business do vertical."
migracao_ui: "concluido — 0 Blade servido"
contains:
  - "EtiquetaTagController — geração lote etiquetas TAG (ZPL Argox/Zebra + PDF DomPDF) per business (US-VEST-020, RUNBOOK-etiqueta-tag.md)"
  - "EtiquetaTagService — ZPL 50×30mm + EAN-13 GS1 + QR Code opcional + settings configurable per business (Wave 27 + US-VEST-020)"
  - "GradeCurvaService — matriz tamanho × cor + proporção curva BR em configuração (Wave 27); sem consumidor fora dos testes"
  - "VestuarioSettingsResolver — settings JSON per business cache 5min (Sprint 2 ADR 0121)"
  - "DevolucaoService — devolução com crédito em vestuario_creditos_cliente; sem chamador em produção desde a decisão [W] 2026-10-26 (o crédito da US-VEST-021 é o Adiantamento do núcleo, ValeTrocaService); destino é decisão [W]"
  - "CurvaCompraController — sugestão de compra por tamanho pela curva de venda, só leitura (GET /vestuario/curva-compra, US-VEST-031 · UC-VCUR-01..03)"
  - "CurvaDeCompra — proporção vendida por variação nos últimos 90 dias (ou da categoria), dividida pelo maior resto; diferente do GradeCurvaService, que guarda proporção fixa em configuração (US-VEST-031)"
  - "RemarcacaoController — remarcação por coleção: tela, prévia, gravação, reversão e etiquetas do lote (/vestuario/remarcacao, atrás do setting remarcacao.enabled, US-VEST-032 · UC-VREM-05..07)"
  - "VestuarioLiberado — middleware de todas as rotas /vestuario/*: exige o pacote vestuario_module da empresa (UC-VPAC-01)"
  - "Remarcacao — prévia antes → depois por variação, gravação em lote com o antes guardado e reversão que não pisa em preço mexido depois (US-VEST-032 · UC-VREM-01..04)"
not_contains:
  - "Núcleo transactions/contacts → UltimatePOS core (compartilhado entre verticais)"
  - "NFe/NFC-e → Modules/NfeBrasil (backbone)"
  - "Kanban shared infra → Modules/Repair (consumido opcional via repair_settings JSON)"
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
trust_required: L2
owner: wagner
permission_prefix: vestuario.*
charter_adr: 0121
related_adrs:
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0066-format-date-shift-3h-preservado-legacy-clientes
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0093-multi-tenant-isolation-tier-0
url_prefixes:
  - /vestuario/*
drift_alerts: []
---

# Modules/Vestuario — vertical lojas de vestuário/moda BR

> ADR mãe: [0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) §P7
> SPEC: [memory/requisitos/Vestuario/SPEC.md](../../requisitos/Vestuario/SPEC.md)
> Cliente piloto: ROTA LIVRE biz=4 (Termas do Gravatal/SC) em prod desde 2024-Q1
> CNAE: 4781-4/00

## Estado em 2026-11-12

A pasta nasceu vazia em 2026-05 (ADR 0121 §P7): a ROTA LIVRE usa o produto há 2+ anos pelo núcleo UltimatePOS + `Modules/{Financeiro, NfeBrasil}`, com customizações pontuais ([ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md), `format_date` shift +3h). Desde então o módulo ganhou o que é só do ramo:

| Capacidade | US | Estado |
|---|---|---|
| Etiqueta TAG (ZPL e PDF) | US-VEST-020 | entregue; cutover da ROTA LIVRE não ocorreu |
| Curva de compra por tamanho | US-VEST-031 | entregue, com botão na nova compra |
| Remarcação por coleção | US-VEST-032 | entregue, atrás de `remarcacao.enabled` (desligado) |
| Acesso pelo pacote `vestuario_module` | — | middleware `VestuarioLiberado` em todas as rotas |

Tabelas do módulo: `vestuario_settings`, `vestuario_remarcacoes`, `vestuario_remarcacao_itens`, `vestuario_devolucoes` e `vestuario_creditos_cliente`, todas com `business_id` + FK. As duas últimas ficaram sem chamador em produção depois da decisão [W] de 2026-10-26 (crédito de devolução = Adiantamento do núcleo).

Os ids de US deste módulo são os do [SPEC](SPEC.md). Uma tabela antiga desta página usava US-VEST-001 a 004 para outras coisas e foi retirada para não colidir.

> **Política:** zero código novo sem sinal qualificado [ADR 0105]. Backlog em `memory/requisitos/Vestuario/SPEC.md` §4.

## Não-goals

- ❌ NÃO substitui núcleo UltimatePOS (continua compartilhado entre verticais)
- ❌ NÃO duplica Modules/NfeBrasil (vertical consome backbone)
- ❌ NÃO impõe Repair (kanban opcional via repair_settings JSON)
