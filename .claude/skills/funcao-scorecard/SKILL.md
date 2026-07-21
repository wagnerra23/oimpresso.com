---
name: funcao-scorecard
mission: "Substituir opinião solta da IA sobre código por um PARECER por-critério ancorado numa rubrica externa + citação extrativa — o screen-grade aplicado por função (concordo/não com evidência, nunca carimbo)."
description: ATIVAR quando [W] pedir "o que você acha dessas funções", "concorda com essa função?", "avalie as funções do <arquivo>", "parecer do ProductUtil", "/funcao-scorecard app/Utils/X.php" ou "scorecard de função". Aplica 8 critérios a funções relevantes por risco, com veredito concordo|discordo|incerto|n/a, evidência extrativa e intenção externa. NÃO edita código, NÃO cria task e NÃO força conclusão quando falta contexto.
type: process-skill
tier: B
status: active
version: 0.1.0
trust_level: L1
owner: wagner
created_at: 2026-07-21
updated_at: 2026-07-21
related_adrs: [0093, 0256, 0264, 0155, 0230]
parent_mission: "Toda skill substitui trabalho humano repetitivo com ROI provado, rumo ao ERP autônomo."
triggers_on:
  - "/funcao-scorecard"
  - "/funcao-scorecard {path}"
  - "concorda com essa função"
  - "parecer da função {X}"
  - "avalie as funções de {arquivo}"
---

# funcao-scorecard — parecer por função, ancorado em rubrica

> ⚠️ **PILOTO** ([proposal 2026-07-21](../../../memory/decisions/proposals/2026-07-21-funcao-scorecard-opiniao-ancorada-rubrica.md)). Só shippa YAML depois do bite-test (§5 do método).

## O que carrega (leia ANTES de opinar — não monte de cabeça)

**Método canônico:** [`memory/requisitos/_Governanca/FUNCAO-SCORECARD-METODO.md`](../../../memory/requisitos/_Governanca/FUNCAO-SCORECARD-METODO.md) — a rubrica dos 8 critérios (C1 multi-tenant · C2 valor/estoque · C3 dado-ausente · C4 atomicidade · C5 N+1 · C6 SQL cru · C7 tipos/falha · C8 cobertura contada), a regra de evidência, o formato YAML, e o bite-test.

## Protocolo (ordem load-bearing — §4 do método)

1. **Lê a rubrica primeiro** (`FUNCAO-SCORECARD-METODO.md`). Nunca julga de cabeça.
2. Lê a função-alvo + contexto no arquivo.
3. **Varreduras contadas** — `git grep -n "<fn>" -- '*.php'` **sem head_limit**; declara "N consumidores" e "N testes" como NÚMERO (C8).
4. Antes de persistir, filtra o escopo: valor/estoque, tenant, escrita DB, API pública, segurança/compliance ou alto fan-in. Função trivial fica fora salvo incidente concreto.
5. Por critério emite `concordo | discordo | incerto | n/a` + **citação extrativa** e **âncora de intenção externa**. Sem intenção suficiente ⇒ `incerto`; `n/a` honesto é esperado.
6. Materializa/atualiza `memory/governance/scorecards/funcoes/<path-slug>.yaml` (**sem nota agregada**, `totals` só conta).

## Proibições duras (§0/§6 do método)

- ⛔ **NÃO** é opinião livre — todo veredito é contra a rubrica + evidência. Opinião "eu acho boa" = carimbo (sicofância).
- ⛔ **NÃO** funde os 8 num número (PLUGAR NÃO FUNDIR).
- ⛔ Um `discordo` **NÃO** autoriza fix, **NÃO** cria task — é PARECER, candidato a US via [W]. Fix segue §5 2026-07-15 (varredura contada + âncora + teste vermelho) + REGRA MESTRE se valor/estoque.
- ⛔ Código sozinho não prova intenção. Se SPEC/charter/ADR/dono/golden/runtime não resolverem a dúvida, o único veredito válido é `incerto`.
- ⛔ **NUNCA** colar valor R$ de comentário/fixture na citação (regra BRL).

## Vocabulário
"parecer", não "achado". "candidato a US", não "bug a corrigir".
