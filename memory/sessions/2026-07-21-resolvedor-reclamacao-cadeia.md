---
date: "2026-07-21"
hour: "14:10 BRT"
topic: "Resolvedor reclamação → módulo → tópico → cadeia (tela/rota/controller/função/model/teste)"
authors: [C]
outcomes:
  - "Protótipo read-only scripts/governance/resolver-reclamacao.mjs: roteia reclamação em linguagem natural pra cadeia de artefatos responsáveis, expondo ambiguidade/lacuna em vez de chutar."
  - "Insight central: o TÓPICO já É a cadeia (anchors do ADR 0345). O resolvedor é roteador + leitor de âncoras + camada de honestidade sobre índices derivados — NÃO um índice/juiz novo."
  - "5 vereditos honestos: resolvido / sem-cobertura / parcial / ambiguo / incerto. Validado em 4 demos + 2 casos extras."
  - "Bug de correção pego pela própria demo: módulo class-B (Produto) não é nó do catalog.json (usa ProductCatalogue) → união de universo de módulos consertou o roteamento do tópico piloto."
prs: []
us: []
related_adrs:
  - 0345-topicos-vivos-aprendizado-por-critica-revisada
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0093-multi-tenant-isolation-tier-0
  - 0314-poda-gates-onda-2-lei-fusoes
---

# Session log 2026-07-21 — resolvedor reclamação → cadeia de responsabilidade

## TL;DR

O próximo passo aberto pela [session dos tópicos vivos](2026-07-21-topicos-vivos-aprendizado-critica-revisada.md) era: *"construir resolvedor reclamação → módulo → tópico → tela/rota/controller/função/model/teste, expondo ambiguidades"*. Construí o protótipo **read-only** [`scripts/governance/resolver-reclamacao.mjs`](../../scripts/governance/resolver-reclamacao.mjs). Ele pega uma reclamação em linguagem natural e devolve a cadeia de artefatos responsáveis — **e, quando não sabe, diz que não sabe** (ambiguidade/lacuna explícitas). Zero integração com Jana/inbox nesta fase, por desenho.

## O insight que dispensou metade do trabalho

O ADR 0345 + `topico.schema.json` já definem que um tópico carrega um bloco `anchors` com `screens/routes/controllers/functions/models/tables/tests/adrs`. Ou seja: **um tópico JÁ É a cadeia**. Então o resolvedor não precisa *derivar* a cadeia toda — precisa (1) **rotear** a reclamação pro módulo/tópico certo e (2) **ler as âncoras**. Quando não há tópico, cai pro derivado da SUPERFÍCIE, com confiança menor e dizendo isso.

Isso mantém o resolvedor como **leitor/compositor** sobre índices que já existem (ADR 0256: derivado sobrevive) e evita o §5 "duplicar régua consolidada" — ele NÃO computa cobertura/nota/gate; consome as saídas de `catalog.json`, `SUPERFICIE.md`, `topicos/*.md`, `page-path.mjs`.

## Arquitetura (5 estágios, todos puros/testáveis)

1. **Normalizar** — tokeniza PT-BR (strip acento, remove stopword, ≥3 chars).
2. **Índice derivado** — `catalog.json` (módulo→tabela/componente/api/purpose) ∪ módulos de `requisitos/*/topicos` e `SUPERFICIE.md` (necessário p/ class-B) + parse dos tópicos.
3. **Pontuar módulos** — overlap ponderado: tabela (3.0) · prefixo (2.5) · título-de-tópico (3.0) · claim-de-tópico (1.5) · purpose (1.0) · léxico de domínio curado (2.5). A evidência (`token→onde`) sai junto — transparência obrigatória.
4. **Classificar roteamento** — `incerto` (abaixo do piso 3.0) · `ambiguo` (2º ≥ 0.6× do 1º) · `ok`. **É o núcleo da honestidade.**
5. **Montar cadeia + veredito final** — tópico casou+tem teste = `resolvido`; tópico sem teste = `sem-cobertura`; só módulo = `parcial`; empate = `ambiguo`; nada = `incerto`.

O matcher v0 é **lexical determinístico**: transparente, testável, custo zero, sem alucinação. Ele NÃO "entende" a reclamação — casa tokens contra bag-of-words derivada e **recusa** abaixo do piso. O upgrade LLM (crítico PROPÕE módulo/tópico com evidência → síntese central → aprovação humana, ADR 0345) fica adiado de propósito; v0 valida a espinha.

## Prova — 4 demos + 2 extras (`--demo`, saída literal no script)

| Reclamação | Veredito | Cadeia |
|---|---|---|
| "quando dou desconto o total da fatura vem inflado" | ✅ **resolvido** | Produto → tópico `calculo-total-fatura` → `ProductUtil::calculateInvoiceTotal` + `Util::num_uf` + `TaxRate` + `tax_rates` + **3 testes** + ADR 0093 |
| "não consigo emitir o boleto da cobrança" | ⚠️ **ambiguo** | RecurringBilling (6.0) ⟂ Financeiro (5.0) — não escolhe, lista os dois, sinaliza que faltam tópicos |
| "a ordem de serviço da oficina não salva o veículo" | 🟠 **parcial** | OficinaAuto (11.0, claro) mas sem tópico → cadeia derivada da SUPERFÍCIE, confiança baixa |
| "o sistema está lento hoje" | ❓ **incerto** | abaixo do piso → recusa rotear |
| "não consigo dar baixa na conta a receber do inadimplente" | 🟠 **parcial** | Financeiro claro, sem tópico |
| "a nota fiscal nfe não foi transmitida pra sefaz" | ⚠️ **ambiguo** | NfeBrasil (13.5) ⟂ Fiscal (8.5) — emissão vs imposto, legítimo |

O caso ✅ é o alvo: reclamação → tópico curado → cadeia inteira **incluindo os 3 testes que travam a regressão**. O caso ⚠️ é o valor de verdade: o resolvedor **não chuta** quando a reclamação cruza dois donos.

## O bug que a própria demo pegou (honestidade aplicada a mim mesmo)

Na 1ª rodada, o tópico piloto (`calculo-total-fatura`, módulo **Produto**) NÃO roteava — Produto só entrava pelo léxico e empatava com Sells em 7.5. Causa: **Produto é class-B** (núcleo UltimatePOS) e **não é nó do `catalog.json`** (o catálogo tem `ProductCatalogue`). O universo de módulos vinha só do catálogo → o sinal de título-de-tópico do módulo class-B era perdido. Fix: **união** do universo (catálogo ∪ módulos com `topicos/`/`SUPERFICIE.md`). Depois: Produto 16.5, tie quebrado pela evidência do tópico, veredito vira `resolvido`. É um achado real sobre a base: **catálogo e requisitos não unificam nome de módulo** (class-B).

## Honestidade / limites (o que NÃO resolve — de propósito)

- **Lexical ≠ semântico.** Reclamação com vocabulário fora do léxico/índices → `incerto`. Correto (recusa > chute), mas cobertura depende do léxico curado (pequeno, no arquivo, revisável no diff — igual `CORE_APP_MODULES`).
- **Bias pró-ambiguidade.** Piso 3.0 e razão 0.6 erram pro lado de **flagar** ambiguidade. Pra um resolvedor cujo valor é "não chutar", over-flag > under-flag. Knobs em `KNOBS`, tuneáveis.
- **Cadeia derivada não linka teste.** No `parcial`, `testes: []` — a SUPERFÍCIE não ancora teste-por-tópico. O caminho de fechar é **criar o tópico** (afunila a cadeia + ancora o teste), não inchar o resolvedor.
- **Multi-tenant Tier 0:** este protótipo lê **só metadados git-canon** (catalog/SUPERFICIE/tópicos) — **zero dado de tenant**. Quando/se integrar com inbox (reclamação real de cliente), ESSA borda toca dado com `business_id` e aplica o scope global (ADR 0093); o núcleo do resolvedor segue metadata-only.

## Próximos passos (decisão [W])

- [ ] Medir com reclamações reais (inbox/WhatsApp) antes de qualquer promoção — o léxico e os knobs calibram com uso.
- [ ] Se virar produto: promover o `--selftest` a job advisory (ADR 0275, nasce advisory) e decidir a superfície de consumo (tool MCP? passo do triagem de ticket?).
- [ ] Upgrade v1: crítico LLM propõe módulo/tópico com evidência, reconciliado por síntese central + [W] (loop do ADR 0345) — só depois do v0 medido.
- [ ] Distinguir "módulo sem teste nenhum" de "módulo com teste não-ancorado" no veredito `parcial` (sinal barato, adiado).

## Referências

- Protótipo: [`scripts/governance/resolver-reclamacao.mjs`](../../scripts/governance/resolver-reclamacao.mjs) (`--demo` · `--selftest` · `--json`)
- ADR-proposta: [`memory/decisions/proposals/2026-07-21-resolvedor-reclamacao-cadeia.md`](../decisions/proposals/2026-07-21-resolvedor-reclamacao-cadeia.md)
- Origem: [session tópicos vivos 2026-07-21](2026-07-21-topicos-vivos-aprendizado-critica-revisada.md) · [ADR 0345](../decisions/0345-topicos-vivos-aprendizado-por-critica-revisada.md)
