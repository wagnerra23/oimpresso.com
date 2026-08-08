---
date: "2026-08-02"
time: "21:00 BRT"
slug: "b7-cobertura-travas-de-prova"
tldr: "Ratchet 191→145 e 1ª tela do B7 fechada (Conciliação, UC-FCC-01..13). O valor não foi a tela: foram as 2 travas achadas — a quarentena da lane classificava por GREP que casou a NEGAÇÃO (10 testes fora de lane required por motivo inexistente) e o manifesto G-7 só lê UC de título de it() (135 de 383 UCs improváveis por construção). Ambas caíram aqui; o resto é forward-only. O ledger LICOES_CODE.md estava corrompido desde 07-31 e foi restaurado."
decided_by: [W]
cycle: null
prs: [5172, 5174, 5175, 5177, 5178, 5180, 5183]
us: []
next_steps:
  - "Amanhã ≥07:30 BRT: conferir se o cron casos-results-publish carimbou UC-FCC-01..13 no manifesto. Se NÃO subir, o loop não fechou e é sinal a investigar — não dar por certo."
  - "Converter docblock→it() é oportunístico (132 UCs presos): fazer só quando a tela já for tocada por trabalho real. NÃO varrer em lote (§5 2026-07-12)."
  - "29 arquivos seguem quarentenados no Financeiro (bucket C, falha real) — cada um é investigação própria, não conserto em lote."
  - "133 telas ainda sem casos.md; os módulos grandes (Essentials 13, Repair 13, Jana 10, Forja 9, Atendimento 8) NÃO têm SDD — lá não dá pra derivar UC sem inventar."
  - "[W]: decidir se o censo do teto vira linha advisory no casos-coverage-guard --report (extensão do dono do tema, não gate novo). Não fiz por conta própria — é script de gate required."
related_adrs: ["0365-trio-de-tela-fica-colocado-reverte-eixo-0364", "0264-governanca-executavel-trio-dominio-e2e", "0344-two-strikes-cobre-processo"]
---

# Handoff 2026-08-02 21:00 BRT — B7-cobertura e as travas de prova

## TL;DR

O B7 andou (ratchet 191→145, 1ª tela fechada), mas o que importa é o que a tela **revelou**:
os UCs dela nasceram com **zero capacidade de virar ✅**, e não por falta de teste. Duas travas
independentes — uma quarentena classificada por grep que casou a negação, e um manifesto que só
enxerga UC em título de `it()`. As duas caíram. A segunda tem alcance de projeto: **135 de 383 UCs
são improváveis por construção**.

## Cronologia desta sessão

| Quando | Evento |
|---|---|
| 17:00 | Medido o estado real: 134 telas sem casos (a prosa dizia ~136) e baseline 45 acima da dívida |
| 17:20 | #5172 — ratchet aterrissado 191→146 |
| 18:00 | #5174 — `Conciliacao/Index.casos.md` com UC-FCC-01..13, derivado do SDD §6.2 |
| 18:40 | Cobrança do [W] (*"qual o verdadeiro status"*) → 3 correções minhas; #5175 errata |
| 19:30 | #5177 — conversão pra `it()`; JUnit prova o UC no `name` |
| 20:00 | #5178 — quarentena: medido que 10 de 11 não usam RefreshDatabase; 4 saem, fixture consertada |
| 20:30 | #5180 — `UploadDedupe` convertido; censo do teto medido |
| 21:00 | #5183 — `LICOES_CODE.md` restaurado (24 → 17 entradas) |

## Estado atual dos artefatos

### Entregue

| Arquivo | Status | Notas |
|---|---|---|
| `resources/js/Pages/Financeiro/Conciliacao/Index.casos.md` | ✅ | 13 UCs, todos com id no título de teste |
| `resources/js/Pages/Financeiro/Conciliacao/Index.charter.md` | ✅ v2 | corrigido: dizia *"score fixo 0.85 no MVP"* contra o código correto |
| `Modules/Financeiro/Tests/Feature/Conciliacao{LeExtratoApi,UploadDedupe}Test.php` | ✅ | convertidos pra Pest `it()`; asserções verbatim |
| `Modules/Financeiro/Tests/Feature/ConciliacaoAuditReabrirTest.php` | ✅ | fixture consertada: 1 failed → 5/0/0 |
| `.github/financeiro-pest-quarantine.list` | ✅ | 37→33; bucket B reclassificado com o motivo medido |
| `memory/LICOES_CODE.md` | ✅ | restaurado; LC-08 39→40, LC-11 5→6 |
| `scripts/casos-coverage-baseline.json` | ✅ | 191→145 |

### PRs

Todos **mergeados**: #5172 · #5174 · #5175 · #5177 · #5178 · #5180 · #5183.

## Estado MCP no momento do fechamento

Consultado agora (prova, não promessa):

- **`cycles-active`** → `Nenhum cycle ATIVO em COPI`
- **`my-work`** → 8 tasks, **todas em REVIEW**: US-COPI-123 `p0` · US-TR-309/310/305/306 `p1` ·
  US-PG-008 `p1` · US-PROD-027 `p1` · US-INFRA-023 `p1`
- **`decisions-search "trio de tela casos charter localizacao 0364 0365"`** → **ADR 0365
  `status: aceito`**, ratificada por [W] hoje (#5179). O **B0 caiu durante esta sessão** — o trio
  colocado é canon *de jure*. B7 nunca dependeu dele (a proposal já dizia, e o guard required sempre
  resolveu por path-irmão).
- Nenhuma task foi criada/alterada por mim nesta sessão — o trabalho todo foi B7, que vive na
  proposal, não em US.

## O que o próximo agente precisa saber

1. **Não confie no rótulo de uma quarentena / bucket / classificação sem medir.** O bucket
   *"B) RefreshDatabase"* tinha 11 arquivos e **10 não usam o trait** — a string casou comentários
   dizendo o oposto. Isso manteve 10 testes fora de uma lane **required** por meses, um deles com
   `[must]` `[T0]` cross-tenant.
2. **UC em docblock não existe pro manifesto.** Só título de `it()`/`test()` chega ao `name` do
   `<testcase>`. Se for escrever `casos.md`, o teste precisa citar o UC **no título** — senão nasce
   com teto zero.
3. **O `exec_backed_pct` tem teto estrutural.** 236 de 383 é o máximo alcançável hoje. Ler o número
   como "falta rodar teste" é erro.
4. **Verde ≠ veredito.** Três vezes nesta sessão o CI estava verde sem provar o que eu precisava.
   Baixar o artifact (`gh run download`) foi o que fechou. E cuidado com **auto-merge ligado**: o
   #5175 mergeou no head do momento e deixou meu commit seguinte de fora, enquanto a API do PR
   ainda devolvia o head antigo (`git ls-remote` desmentiu).
5. **O ledger é do agente.** Consertou erro de uma classe? Incrementa `Ocorrências` no
   `LICOES_CODE.md`. E ele já esteve corrompido — se o banner de SessionStart listar a mesma classe
   duas vezes, é sintoma disso.

## Riscos abertos

- **Nenhum UC desta tela está ✅ ainda.** Dependem do cron das 07:30. Se amanhã não subirem, o loop
  não fechou — investigar, não presumir.
- O `LICOES_CODE.md` **não tem `merge=union`** no `.gitattributes` (o irmão
  `financeiro-pest-quarantine.list` tem, justamente pra sobreviver a sessões concorrentes). Foi 1ª
  ocorrência da corrupção → consertei, não codifiquei gate (regra do próprio arquivo). **Se
  repetir, é o candidato óbvio.**
