---
name: Adaptação rápida — leia primeiro pra não ficar deslocado
description: Cartão de bootstrap pra um agente (Claude/Cursor) entender o projeto oimpresso e se orientar em <2min, sem ficar deslocado. O que é o projeto + como se orientar + as regras que mais pegam + sinais de que você está perdido. Síntese acionável das lições 2026-05-28.
type: reference
authority: canonical
---

# 🧭 Adaptação rápida — leia ISTO primeiro

> **Pra quem chega novo (agente ou pessoa) e não quer ficar deslocado.** 2 minutos de leitura te orientam. Existe porque, em 2026-05-28, um agente entrou sem se orientar e errou a sessão inteira (ver [lições](../sessions/2026-05-28-licoes-memoria-governanca-confiabilidade.md)).

## 1. O que é o projeto (4 linhas)

- **oimpresso** = ERP modular vertical com IA, em cima do UltimatePOS. Laravel 13.6 + PHP 8.4 + Inertia/React.
- **Multi-tenant por `business_id`** — vazar dado entre tenants é o pior bug possível (Tier 0 IRREVOGÁVEL).
- **Cliente real único em produção:** Larissa, loja de roupa **ROTA LIVRE** (Gravatal/SC), **biz=4**. Ela testa e reporta.
- **Meta:** R$5M/ano. Time pequeno (Wagner + Felipe/Maiara/Eliana/Luiz entrando via MCP).

## 2. Como se orientar em 60s (faça nesta ordem)

1. **`brief-fetch`** (tool MCP) → estado vivo: cycle ativo, tasks, HITL, decisões recentes. **Sempre primeiro.**
2. **`my-work`** → suas tasks.
3. **Buscar** o que precisar com `decisions-search` / `memoria-search` — **NÃO** `grep`/`glob`/navegar pasta. A memória é indexada; pasta é só depósito. Ver [COMO-FUNCIONA-MEMORIA-RETRIEVAL](COMO-FUNCIONA-MEMORIA-RETRIEVAL.md).

## 3. As 6 regras que mais pegam (decore)

1. **Verifique antes de afirmar.** Não diga "é X" sem ter checado. (O maior erro de 2026-05-28 foi afirmar "estrago de 14k arquivos" sem ver que o repo era shallow — eram 2 arquivos.)
2. **Peça antes de commit/push/merge/PR.** Aprovação humana é obrigatória (R10). Não saia abrindo PR.
3. **Decisão ≠ memória.** ADR não decai por tempo — vale até ser substituída. Memória (sessão/fato) decai. Nunca aplique prazo a decisão.
4. **Nunca mova/delete/reorganize arquivos de memória.** Append-only. "Se aconteceu, aconteceu."
5. **Multi-tenant Tier 0:** toda query de negócio respeita `business_id`. Testes usam `biz=1` (nunca `biz=4`, que é cliente real).
6. **Pedido vago → pergunte antes de implementar.** Não adivinhe.

## 4. 🚩 Sinais de que você está DESLOCADO — pare

- Você ia afirmar um fato sem ter verificado → **pare, verifique.**
- Você ia fazer `grep` na pasta `memory/` pra "achar" algo → **use a busca MCP.**
- Você ia deletar/mover/reorganizar memória → **não. Append-only.**
- Você ia abrir PR / commitar / mergear sem o Wagner pedir → **pare, peça.**
- Você ia aplicar tempo/prazo a uma decisão (ADR) → **errado, decisão não decai.**

## 5. Onde está a verdade (fonte canônica)

| Quer… | Vá em |
|---|---|
| Estado atual | `brief-fetch` (tool MCP) |
| Regras invioláveis (Tier 0) | `memory/proibicoes.md` (REGRA ZERO + R1-R12) |
| Decisões | `decisions-search` (não navegue `decisions/`) |
| Como a memória funciona | [COMO-FUNCIONA-MEMORIA-RETRIEVAL.md](COMO-FUNCIONA-MEMORIA-RETRIEVAL.md) |
| Por que confiabilidade vem de processo | [lições 2026-05-28](../sessions/2026-05-28-licoes-memoria-governanca-confiabilidade.md) |
| Primer do projeto | `CLAUDE.md` (raiz) |

## 6. A regra de ouro da confiabilidade

**Confiabilidade vem de processo, não de inteligência.** Toda vez que um agente verifica antes de afirmar e pede antes de agir, acerta. Toda vez que age no reflexo, erra. Se está com pressa de afirmar/agir — é exatamente aí que você deve desacelerar e verificar.
