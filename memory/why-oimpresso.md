# Por que o oimpresso existe

ERP brasileiro com **arquitetura modular especializada por vertical** ([ADR 0121](decisions/0121-oimpresso-modular-especializado-por-vertical.md)). Núcleo comum (multi-tenant + Jana IA + Financeiro + NFe) atende qualquer pequena empresa BR. Módulos `Modules/<Vertical>` adicionam profundidade setorial onde precisa.

Construído sobre UltimatePOS v6 com módulos próprios em `Modules/` (Jana IA, Financeiro, SRS ex-MemCofre, NfeBrasil, RecurringBilling, Repair).

## Origem
Originalmente nasceu como módulo Ponto WR2 (controle eletrônico Portaria MTP 671/2021) e evoluiu pra plataforma multi-vertical com 26 anos de experiência majoritariamente no setor gráfico (WR Sistemas / OfficeImpresso legacy Delphi).

## Cliente piloto (Modules/Vestuario)
**ROTA LIVRE** (`business_id=4`, Larissa) — `LARISSA COMERCIO DE ARTIGOS DO VESTUARIO LTDA - ME`, **vestuário em Termas do Gravatal/SC** (não gráfica em SP). 99% do volume de vendas do oimpresso novo (Laravel). Monitor 1280px. Customizações ativas: `format_date` shift +3h ([ADR 0066](decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md) — preservado intencionalmente).

> ⚠️ ROTA LIVRE não é exceção — é o **caso piloto Modules/Vestuario** validado em produção há 2+ anos. Outros módulos verticais: ComunicacaoVisual ainda em construção; OficinaAuto em **piloto LIVE** (Martinho, biz=164).

## Módulos verticais — estado

| Módulo | CNAE | Status | Cliente piloto |
|--------|------|--------|-----------------|
| **Modules/Vestuario** | 4781-4/00 | ✅ em produção | ROTA LIVRE |
| **Modules/ComunicacaoVisual** | 1813-0/01 | 🟡 em construção (schema multi-vertical) | candidatos: 6 saudáveis OfficeImpresso (Vargas, Extreme, Gold, Zoom, Fixar, Mhundo, Produart) |
| **Modules/OficinaAuto** | 4520-0/01 | 🟡 piloto (LIVE prod biz=164) | Martinho (mecânica pesada de caminhão basculante; ~91 veículos reais importados) |
| Outros | — | 🔒 backlog ADR feature-wish (ADR 0105) | — |

## Posicionamento
- **Núcleo** vs Bling, Tiny, Conta Azul, Omie (horizontais raso) — ganha em multi-tenant Tier 0, Jana IA com memória persistente, governança formal (Constituição v2)
- **Modules/ComunicacaoVisual** vs Mubisys, Zênite, Calcgraf — ganha em stack moderna (Laravel 13.6 + React 19), NFe-de-boleto-pago automática (US-RB-044), IA conversacional
- **Modules/Vestuario** vs Linx Microvix, ProMoz, Vendizap — em desenvolvimento; ROTA LIVRE valida fundamentos
- **Modules/OficinaAuto** vs Mecânico, Auto Manager, Lokoz — em piloto LIVE (Martinho biz=164, ~91 veículos reais importados; Discovery mai/2026)

## Meta financeira
**R$ [redacted Tier 0] milhões/ano** ([ADR 0022](decisions/0022-meta-5mi-ano-financeira.md)). Crescimento via diversificação modular (~1M+ empresas BR endereçáveis) + aprofundamento por vertical onde tem cliente real.

## Cliente externo separado
`Eliana(WR2)` (cliente externa, eliana@wr2.com.br) ≠ `Eliana[E]` (esposa Wagner, time interno, advogada+financeiro). Sempre desambiguar em commits/notas.

## Cliente principal Jana
**ROTA LIVRE** — Larissa pergunta sobre faturamento/metas/produtos vestuário e recebe resposta correta usando dados reais (CYCLE-01 goal validado em prod).
