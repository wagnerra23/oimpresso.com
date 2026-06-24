---
name: Escala de design = carteira Delphi (~150 clientes) migrando pro online, NÃO 1 cliente
description: A escala de DESIGN do oimpresso é a carteira legacy (~150 clientes Delphi WR Comercial em migração pro ERP online), não o 1 cliente online atual (ROTA LIVRE). Corrige a premissa "cliente único" que análises de design usaram pra justificar não investir em robustez. Drive de risco Tier 0: fragilidade escala por cliente.
type: reference
---

# Escala real de design: ~150 clientes Delphi → online (não 1)

> **Origem:** Wagner, 2026-06-23 — *"cliente único? são 150 clientes do delphi indo para online. isso vai estar no meu mcp?"* Correção de premissa + registro canônico (não estava no MCP).

## O fato

- **Online HOJE:** ~1 cliente pagante ativo no ERP web (ROTA LIVRE / Larissa) — `clientes-ativos.md:27` ("apenas 1 cliente ROTA LIVRE"). Daqui saiu o "cliente único".
- **Escala-ALVO (o que se está construindo PARA):** **~150 clientes Delphi (WR Comercial)** em migração pro ERP online. A **carteira legacy é a escala de design**, não o 1 cliente atual. Bate com o CYCLE-08 ("monetizar a carteira legacy — primeiros clientes migrados e pagando").
- A memória canônica só catalogava **~6 builds Delphi** (`contrato-delphi-inviolavel.md:13`: WR2 biz=1, Extrema biz=196, Vargas/Martinho biz=164…) — **sub-representava a escala em ~25×**. Este doc corrige.

## Por que importa pro design (Tier 0)

Análises de design usaram "cliente único" pra justificar NÃO investir em robustez (ex: workflow da âncora 2026-06-23, [`ancora-improvada-design-final.md`](../sessions/2026-06-23-ancora-improvada-design-final.md) §4, marcou rename-proof/verde-por-método/sentinela-SEFAZ como "over-engineering pro perfil"). **Premissa errada.** Com ~150 clientes fiscais multi-tenant:

- Falha silenciosa (bug fiscal passando como "testado verde"; âncora fraca; vazamento Tier 0) atinge **~150 empresas pagando imposto**, não 1.
- Custo de **robustez é fixo** (escreve a defesa 1×); custo de **fragilidade escala por cliente** (× ~150 tenants NF-e). O cost/benefit que rejeitava a robustez **inverte**.
- Toda decisão "vale a pena endurecer?" deve usar a **escala-alvo (~150)**, não o estado atual (1).

## Detalhe em aberto (a confirmar com Wagner)

Número exato e granularidade: ~150 é a base instalada WR Comercial inteira? Quantos já têm data de migração vs só addressable? Registrado como **~150 (asserção Wagner 2026-06-23)** até confirmar a fonte.

## Cross-ref

- `clientes-ativos.md` (online atual = 1 · 56 businesses, 7 com vendas) · `contrato-delphi-inviolavel.md` (wire Delphi imutável, 6 builds) · `migracao-officeimpresso-pattern.md` · `matriz-conhecimento-clientes-legacy.md` · `cliente-martinho-cacambas.md` (migração WR2→online concluída, exemplo)
- `memory/sessions/2026-06-23-ancora-improvada-design-final.md` §4 — a tabela "over-engineering" que usou a premissa errada → **reabrir robustez sob ~150 clientes**.
