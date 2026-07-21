---
proposal_id: resolvedor-reclamacao-cadeia
status: proposed
created: 2026-07-21
proposed_by: claude-code
decided_by: wagner
parent_adr: 0345-topicos-vivos-aprendizado-por-critica-revisada
related_adrs: [0345, 0256, 0093, 0314, 0275, 0264]
type: ferramenta-de-conhecimento
---

# Resolvedor reclamação → cadeia de responsabilidade

- **Status:** PROPOSTA (aguarda [W]). Um protótipo **read-only** já existe e roda; nada foi promovido a gate nem integrado com Jana/inbox.
- **Data:** 2026-07-21 · **Autor:** [CC].
- **Origem:** próximo passo aberto na [session tópicos vivos](../../sessions/2026-07-21-topicos-vivos-aprendizado-critica-revisada.md) e prometido na consequência #1 do [ADR 0345](../0345-topicos-vivos-aprendizado-por-critica-revisada.md): *"uma reclamação pode resolver módulo → tópico → tela/model/função/teste por âncoras estruturadas."* Esta proposta é a **ferramenta** que materializa essa frase.
- **NÃO é paralelo** (§5 proibicoes 2026-06-05): não cria índice/gate novo. É **leitor/compositor** sobre índices DERIVADOS que já existem (`catalog.json`, `SUPERFICIE.md`, `topicos/*.md`, `page-path.mjs`). Não computa cobertura/nota — consome as saídas de quem já computa (`screen-coverage`, `module-surface`).

## Problema

Uma reclamação de cliente ("o total da fatura veio errado") chega em linguagem natural. Hoje, achar o dono — módulo, depois arquivo, depois o teste que trava aquilo — é trabalho manual e depende de quem conhece o repo. O ADR 0345 estruturou a **matéria-prima** (tópicos com `anchors`, superfícies derivadas, grafo de módulos), mas faltava o **elo** que transforma a queixa na cadeia.

## Decisão proposta

Adotar [`scripts/governance/resolver-reclamacao.mjs`](../../../scripts/governance/resolver-reclamacao.mjs) como resolvedor read-only, com o desenho abaixo, e **medir com uso real antes de qualquer promoção**.

### O que é (e o que deliberadamente NÃO é)

- **É** um roteador + leitor-de-âncoras + camada de honestidade. Entrada: reclamação NL. Saída: `módulo → tópico → tela/rota/controller/função/model → teste`, quando resolvível.
- **NÃO é** um novo juiz/gate/índice. O **tópico já É a cadeia** (bloco `anchors`, ADR 0345); o resolvedor roteia até ele e lê. Sem tópico, deriva da SUPERFÍCIE com confiança menor **e diz isso**.
- **NÃO** toca dado de tenant. Lê só metadados git-canon.

### A camada de honestidade (o valor central)

O objetivo NÃO é acertar sempre — é ser **honesto sobre o que não resolve**. Cinco vereditos:

| Veredito | Quando | Ação implícita |
|---|---|---|
| ✅ `resolvido` | tópico casou + cadeia tem teste | dono + teste que trava a regressão |
| 🟡 `sem-cobertura` | tópico casou mas `anchors.tests` vazio | escrever teste é o próximo passo |
| 🟠 `parcial` | só o módulo casou (derivado da SUPERFÍCIE) | criar tópico afunila a cadeia |
| ⚠️ `ambiguo` | ≥2 módulos/tópicos dentro da margem | **não escolhe** — desambiguação humana |
| ❓ `incerto` | nada acima do piso | refinar com o cliente / ampliar léxico |

Isso operacionaliza o `incerto` do ADR 0345 ("ausência de contexto não vira bug inventado") no eixo do roteamento.

### v0 determinístico, v1 com crítico LLM (adiado)

- **v0 (este):** matcher **lexical determinístico** — transparente, testável (`--selftest` com controle-negativo pra piso E ambiguidade), custo zero, sem alucinação. Recusa abaixo do piso.
- **v1 (futuro, fora desta proposta):** crítico LLM **propõe** módulo/tópico com evidência → síntese central reconcilia → [W] aprova (o loop do ADR 0345). Só depois do v0 medido — senão é enfeite sem calibração.

## Evidência (protótipo roda — `--demo`)

4 demos + 2 extras cobrem os 5 vereditos. Destaques (saída literal no script/[session](../../sessions/2026-07-21-resolvedor-reclamacao-cadeia.md)):
- ✅ "total da fatura … desconto" → Produto/`calculo-total-fatura` → `ProductUtil::calculateInvoiceTotal` + `num_uf` + `TaxRate` + **3 testes** + ADR 0093.
- ⚠️ "boleto da cobrança" → RecurringBilling ⟂ Financeiro — refuta escolher.
- ❓ "sistema lento" → recusa rotear.

**Achado de brinde:** a demo pegou que `catalog.json` (usa `ProductCatalogue`) e `requisitos/` (usa `Produto`) **não unificam nome de módulo class-B** — corrigido no resolvedor por união de universo, mas é dívida real da base.

## Custos e riscos

- **Lexical ≠ semântico:** cobertura depende do léxico curado (pequeno, no arquivo, revisável no diff). Reclamação com jargão fora dele → `incerto` (falha honesta, não silenciosa).
- **Knobs empíricos** (piso 3.0, razão 0.6): calibram com uso; hoje enviesados pró-ambiguidade (over-flag > under-flag pra um resolvedor que não deve chutar).
- **Não vira gate por si** (ADR 0314/0275: required = só Tier-0). Se promover o `--selftest`, nasce **advisory**.

## Alternativas rejeitadas

- **Escrever um mapa reclamação→arquivo à mão:** apodrece (ADR 0256). O resolvedor deriva dos índices vivos.
- **Um novo índice de cobertura/teste por tema:** duplica `casos-gate`/`screen-coverage` (§5 proibicoes 2026-07-09). O resolvedor **lê** essas saídas, não as recomputa.
- **Já plugar em Jana/inbox:** a task pediu protótipo read-only validado ANTES de integrar. Integração toca dado de tenant (Tier 0) e é decisão [W] separada.

## Implantação proposta (se [W] aceitar)

1. Manter read-only + advisory; medir com reclamações reais (inbox/WhatsApp) por algumas semanas.
2. Só então decidir superfície de consumo (tool MCP `resolver-reclamacao`? passo do `ticket-triage`?) e o upgrade v1.
