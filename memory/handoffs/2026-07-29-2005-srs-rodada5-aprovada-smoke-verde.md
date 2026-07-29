---
date: "2026-07-29"
time: "20:05 UTC"
slug: srs-rodada5-aprovada-smoke-verde
tldr: "A rodada 5 do refutador GT-G5 aprovou o lote do SRS com 0 erros em 56 itens, o #5036 mergeou as 19:42 e o deploy fechou as 19:50. Smoke em producao FEITO: 6/6 rotas /memcofre/* em 301 e 0 tabelas docs_* restantes, com controle positivo. O que destravou nao foi mais esforco: as 4 rodadas anteriores mediram estados que os proprios commits de correcao ja tinham superado."
prs: [5036, 5043]
decided_by: [W]
next_steps:
  - "anti-ghost segue vermelho no main (advisory, NAO required em nenhum dos dois donos). Saida sancionada = fazer knowledge-drift --check honrar `excluded` classe C. NAO fiz: mudar maquina de governanca pra apagar o proprio vermelho, no PR que ela avalia, e conflito de interesse"
  - "Chip novo desta sessao: validate-memory-schema.sh da FALSO 'campo obrigatorio ausente' no Windows quando o valor do frontmatter tem char fora do cp1252 (a seta U+2192 quebrou o print e o `|| true` virou campo vazio). Par candidato = PYTHONIOENCODING=utf-8 no extractor. Nao armei: 1a ocorrencia, two-strikes ADR 0344"
  - "Chip nao tratado: jana-gold-set.json:57 ensina que 'MemCofre e o cofre de senhas' — falso; o eval premia a resposta errada"
  - "Chip nao tratado: ~15 docs do NfeBrasil com path stale do .pfx (disk real = nfe_certs)"
related_adrs:
  - 0357-deprecar-srs-sucessor-kb-jana-governance
  - 0130-handoff-append-only-mcp-first
---

# A rodada 5 não foi teimosia — foi a primeira medição do HEAD

## TL;DR

O ciclo E1→E6 do `Modules/SRS` fechou: [#5036](https://github.com/wagnerra23/oimpresso.com/pull/5036) mergeou às 19:42, deploy verde às 19:50, **smoke em produção feito** (6/6 redirects 301 · 0 tabelas `docs_*`). A rodada 5 do refutador GT-G5 devolveu **`aprovado`, 0 erros em 56 itens** — e o que destravou não foi mais esforço: foi descobrir que **as 4 rodadas anteriores mediram estados que os próprios commits de correção já tinham superado**. As duas "decisões [W]" que o handoff das 18:47 deixou na mesa **caíram por medição** — nenhuma era decisão.

Continuação direta de [`2026-07-29-1847`](2026-07-29-1847-srs-deprecado-e-4-refutacoes.md).

## Smoke em produção — os dois recibos

Deploy `30485607906` → `success` 19:50:11. Warm-up antes (`/login` = 200), requisições espaçadas (mais cedo na sessão uma rajada minha causou 503 de ~2 min).

```
/memcofre/memoria       301 -> https://oimpresso.com/ia/memoria
/memcofre/chat          301 -> https://oimpresso.com/ia
/memcofre/inbox         301 -> https://oimpresso.com/kb
/memcofre/ingest        301 -> https://oimpresso.com/kb
/memcofre/modulos/Jana  301 -> https://oimpresso.com/governance/module-grades/Jana
/memcofre               301 -> https://oimpresso.com/governance
```

E no banco de produção (`u906587222_oimpresso`), com **controle positivo** provando que era o banco vivo:

```
tabelas docs_* restantes: 0
business=82 · transactions=75254
migration registrada: 2026_07_29_160000_drop_docs_tables_srs_deprecacao
```

Os mesmos `business=82` e `transactions=75254` da sonda que decidiu a deprecação — mesma base, antes e depois.

## As duas "decisões" que não eram decisão

### 1. `anti-ghost` nunca bloqueou nada

Os required de `main` vêm de **dois donos**, e o handoff anterior olhou só um:

| origem | contexts | tem `anti-ghost`? |
|---|---|---|
| protection clássica | 34 | ❌ |
| ruleset "Governance Gate — main" | 1 | ❌ |

O único required que faltava era o `Governance Gate`, e ele vem do **ruleset** — por isso o grep na protection clássica não o achava. Corolário perene: **"o gate X é required?" tem dois donos; consultar um só responde errado.**

### 2. O split era pior que a doença

O `ledger-check` conta `--diff-filter=ACMR` sob `memory/requisitos/` = **13**. Composição:

| metade | arquivos | passa do limiar 10? |
|---|---|---|
| núcleo SRS + MemCofre | 4 | não → gate **mudo** |
| colaterais (4 charters + 3 SPECs + backlog) | 9 | não → gate **mudo** |

**Os dois lados caem abaixo do limiar.** Splitar não reduziria o lote — apagaria a revisão adversarial nas duas metades, e a de 9 é exatamente onde moraram os erros das rodadas 1–3. Medi também se os 9 seriam descartáveis: **não são** — corrigem 4 charters que atribuíam "cofre de senhas / cert A1" a um módulo deletado (dono real: `Modules/NfeBrasil` + `nfe_certificados` + disk `nfe_certs`), cancelam a `US-ARQ-026` e consertam um `Testado em:` apontando para caminho sob o módulo removido.

## O achado que destravou

**Cada commit de correção gravava a entry da rodada que ele mesmo corrigia, no mesmo commit.**

```
fa95e04e19  fix(srs): restaura por BLOCO  →  ledger +18 linhas  =  entry r4 (reprovado) E o fix da r4
```

Logo o `reprovado 22.2` descrevia o estado **anterior ao próprio HEAD**. Ninguém tinha verificado `fa95e04e19`. A rodada 5 não era "mais uma tentativa" — era a **primeira medição do que estava de fato lá**.

E a frase que eu escrevi no handoff anterior — *"as taxas não convergem (38,9 → 11,5 → 18,4 → 22,2)"* — **não se sustenta**: são razões sobre denominadores diferentes (18 → 26 → 38 → 45 claims), porque cada correção adiciona prosa e prosa nova é claim nova. Não é série; são quatro populações. Chamar aquilo de tendência foi LC-08 de novo.

## Rodada 5 — `aprovado`, 0/56

Refutador `fable-5`, sessão fresca, tier superior ao gerador (`opus-5`), amostra 100%, `pii_hits: 0`.

O que mais pesa não é a contagem — é a **prova mecânica**: ele regenerou os três artefatos gerados dentro da árvore da branch e obteve **diff ZERO** nos três.

| artefato | valor reproduzido |
|---|---|
| `deadlink-gate` | 1098 vivos, exit 0 |
| `tasks-index-generate` | 858 US abertas |
| `catalog-graph` | 39 módulos · 622 nós · 947 arestas |

**Conferi por conta própria** as quatro provas mais duras antes de escrever a entry — relatório de agente é hipótese, não veredito:

| claim | medido |
|---|---|
| 63 arquivos deletados de `Modules/SRS` | **63** ✓ |
| nada sobrou no módulo | **0** ✓ |
| `charter_adr: 0080` em 24 de 37 | **24 / 37** ✓ |
| `DEPRECATION-PLAN` restaurado 47 → 47 | **47 → 47** ✓ |

O último fecha o ciclo: é o número que na rodada 4 eu **afirmei sem contar** e que me reprovou.

## Achado de instrumento (chip novo, não armado)

O `validate-memory-schema.sh` deu **falso `campo obrigatório ausente`** para o `tldr` do rascunho deste handoff. O campo existia e o YAML parseava; o que estourou foi o `print(val)` do extractor — a seta `→` (U+2192) do "E1→E6" **não existe no cp1252**, encoding do console no Windows. O `|| true` do script transformou o crash em string vazia, e string vazia virou "ausente".

Confirmado rodando o mesmo script com `PYTHONIOENCODING=utf-8` (que é o que o CI Linux tem): `erros: 0`.

É a família *"instrumento que responde uma pergunta parecida com a feita e devolve um número"* — aqui devolveu **ausência** quando a verdade era **falha de encoding**, o mesmo vício de `cmd || echo "(não tem X)"` já catalogado no §5. **Não armei defesa:** 1ª ocorrência, e por two-strikes ([ADR 0344](../decisions/0344-two-strikes-cobre-processo.md)) a 1ª conserta, não codifica. O par candidato é `PYTHONIOENCODING=utf-8` no `extract_frontmatter_field`.

## Método — o que esta sessão cobra da próxima

1. **Um job com N comandos é N gates.** Rodei os **15** do `Governance Gate` antes de empurrar. Nas rodadas anteriores declarei verde 3× tendo rodado subconjunto.
2. **"É required?" tem dois donos** — protection clássica **e** rulesets.
3. **Relatório de agente é hipótese a testar.** Os 4 spot-checks custaram um comando.
4. **Não mexi na máquina que me avaliava.** O `anti-ghost` tem correção sancionada e conhecida; aplicá-la no PR que ela julga seria conflito de interesse, mesmo sendo a correção certa.
5. **Saída vazia de instrumento ≠ ausência do fato.** Antes de acreditar num "não tem", checar se o instrumento conseguiu medir.

## Estado MCP no momento do fechamento

- `cycles-active` → **servidor MCP indisponível** (`Server Oimpresso MCP — Wagner unavailable`; desconectou no meio da sessão). Fallback filesystem usado, conforme [`how-trabalhar.md`](../how-trabalhar.md) §Fallback.
- `my-work` → idem, indisponível.
- Handoffs irmãos do dia (`ls -t`): `1847-srs-deprecado-e-4-refutacoes` · `1700-kb-flaky-403-reduzido-nao-fechado` · `1615-kb-leitor-fecha-loop-aprendizado` · `1435-kb-v2-leitor-do-corpo-live`.
- ADRs novas hoje: nenhuma criada. A **0357** já estava `status: aceito` em `main` (ratificada no #5039) — conferido lendo o arquivo de `origin/main`, não do working tree.
- Nenhuma task MCP criada nesta sessão — o servidor não respondeu.
