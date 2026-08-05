---
date: "2026-08-03"
time: "1031"
slug: "redactor-cnpj-lid-familia-de-regex"
tldr: "O fix do redactor (#5169) nunca alcançou o indexador do RAG — são 2 cópias, e o sync usava a não-corrigida. Corrigido (#5193), e ao medir a FAMÍLIA de regex apareceu a mesma colisão no CNPJ (LID do WhatsApp) e uma cascata no PHONE (#5211). Índice de produção limpo: 189 → 39 redactions, e as 39 que restam são todas legítimas."
decided_by: [W]
cycle: null
prs: [5193, 5211]
us:  []
next_steps:
  - "CEP segue sem defesa e NÃO MEDIDO — não tem dígito verificador, então a regra de formação não se aplica. Medir exige os inputs reais dos consumidores do PiiRedactor (formulário, log, audit), não o corpus de docs."
  - "Se aparecer 2ª ocorrência da classe LC-18 (fix na cópia que o consumidor não usa), promover a defesa mecânica — hoje está com Ocorrências: 1 e gate = o teste no consumidor."
related_adrs: ["0130-handoff-append-only-mcp-first", "0053-mcp-server-governanca-como-produto"]
---

# Handoff 2026-08-03 10:31 BRT — redactor: o fix que não alcançou o consumidor, e a família de regex

## TL;DR

Pedido de entrada era operacional ("reindexar os `casos.md` para limpar redactions indevidas"). O reindex **não podia funcionar**: o `mcp:sync-memory` não usa o `PiiRedactor` que o [#5169](https://github.com/wagnerra23/oimpresso.com/pull/5169) corrigiu — o `IndexarMemoryGitParaDb` tem cópia própria de `PII_PATTERNS`. Dois PRs depois, o índice de produção está limpo e a família inteira de padrões foi medida.

## Cronologia desta sessão

| Quando | Evento |
|---|---|
| ~00:30 | Verificado que `c33d291` (#5169) estava deployado em prod. Rodado `--only=casos`: `0 atualizados, 33 redactions` — no-op |
| ~01:00 | Causa medida: `PII_PATTERNS` em PHP existe **só** no indexador (sweep contado no repo inteiro); zero referência ao `PiiRedactor` |
| ~03:29 | [#5193](https://github.com/wagnerra23/oimpresso.com/pull/5193) mergeado (squash `c936bf7aa57`). Índice: `casos` 9 docs/33 → 1 doc/1 |
| ~04:00 | [W] autorizou o resíduo. Medição do CNPJ no corpus real: 67 falso-positivos em 22 docs |
| ~04:40 | Teste da emenda pegou **cascata**: liberar o CNPJ fez o PHONE comer pedaço de número maior |
| ~09:00 | [#5211](https://github.com/wagnerra23/oimpresso.com/pull/5211) mergeado (prod `76a3e61de`) |
| ~10:00 | Sync por tipo em prod; `adr` tinha 52 docs ainda não reprocessados |

## Estado atual dos artefatos

### Entregue nesta sessão

| Arquivo | Status | Notas |
|---|---|---|
| `Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php` | ✅ | `deveRedigir` + DV de CPF e CNPJ (paridade deliberada com o `PiiRedactor`) |
| `Modules/Jana/Services/Privacy/PiiRedactor.php` | ✅ | DV de CNPJ + fronteira `(?<!\d)`/`(?!\d)` no PHONE |
| `Modules/Jana/Tests/Unit/IndexarMemoryRedactorRunIdTest.php` | ✅ | teste no **consumidor real**; 8 casos |
| `Modules/Jana/Tests/Unit/PiiRedactorNumeroCruTest.php` | ✅ | + CNPJ/LID/cascata; metade é controle de NÃO-afrouxamento |
| `.github/workflows/jana-logica-pura-pest.yml` | ✅ | lane ligada nos **dois** pontos (`paths:` + linha de execução), incl. o arquivo sob teste |
| `memory/LICOES_CODE.md` | ✅ | **LC-18** `fix-na-copia-que-o-consumidor-nao-usa` |
| `memory/proibicoes.md` | ✅ | 2 lápides §5 (a duplicata; a família de regex + cascata) |

### PRs

| PR | Status | Conteúdo |
|---|---|---|
| [#5193](https://github.com/wagnerra23/oimpresso.com/pull/5193) | merged | porta a semântica ratificada do #5169 pro indexador |
| [#5211](https://github.com/wagnerra23/oimpresso.com/pull/5211) | merged | DV de CNPJ nos dois redactors + fronteira do PHONE |
| [#5190](https://github.com/wagnerra23/oimpresso.com/pull/5190) | closed | substituído pelo #5193 (rebase exigiria force-push, barrado por hook) |

## Resultado medido em produção

| | início | final |
|---|---|---|
| docs com redaction | 79 | **22** (só ativos) |
| redactions | 189 | **39** |

As 39 restantes classificadas exercitando a **regra deployada** (reflection no serviço real, não palpite): **25 formatadas** (declaração) + **11 cruas com DV válido** (PII real). Zero falso-positivo por regra. Recibo do comportamento em prod:

```
remoteJid 14628809617558@lid  -> count=0   articles/21830391097367 -> count=0
emitente 11222333000181       -> count=1   run 30366164436         -> count=0
```

⚠️ O `79 / 189` inicial foi medido **sem filtrar `deleted_at`** — mesma impureza dos dois lados, então o delta vale, mas o número de partida não é estritamente "ativos".

## O que o próximo agente precisa saber

1. **São DOIS redactors, e é intencional** — `PiiRedactor` (input/log/audit) e `IndexarMemoryGitParaDb` (corpus `memory/`). Estão em **paridade deliberada**, declarada no docblock dos dois: mexeu num, mexe no outro **no mesmo PR**. Divergir é o LC-18.
2. **A cascata é real** — os padrões rodam em sequência sobre o mesmo texto. Liberar um faz o número sobrar pro seguinte. O PHONE só não quebrou no #5169 **por sorte** (o run id começava com `30`, DDD inexistente). Ao mexer num padrão, rodar a **lista inteira** no caso liberado.
3. **`--only=<type>` não roda soft-delete** (documentado no comando). Sync parcial nunca reconcilia remoção; e o sync do deploy **não alcança tudo** — o `adr` tinha 52 docs stale quando rodei tipo a tipo.
4. **CEP não tem dígito verificador** — a regra de formação não se aplica lá, e o tamanho do problema segue **não medido**. Não leia a ausência de número como "está tudo bem".

## Estado MCP no momento do fechamento

- `cycles-active`: **nenhum cycle ATIVO em COPI**
- `my-work` (@wagner): **8 tasks**, todas em REVIEW — `US-COPI-123` (p0, mock no /ia/dashboard), `US-TR-309/310/305/306`, `US-PG-008`, `US-PROD-027`, `US-INFRA-023`
- `decisions-search "PII redactor ... indexação memória"`: 4 ADRs, nenhuma governa a redação em si (as mais próximas são de governança de memória — 0059, 0236, 0243, 0293). **Não abri ADR**: a mudança é emenda de semântica já ratificada em PR, não decisão arquitetural nova
- Handoff anterior relacionado: [`2026-08-02-1916-e5-ads-b3-rag-redactor-ciclo-fechado.md`](2026-08-02-1916-e5-ads-b3-rag-redactor-ciclo-fechado.md) (B3, a indexação que expôs o defeito)

## Caveats honestos

- **Não fui eu quem mergeou** nenhum dos dois PRs — apareceram `MERGED` enquanto eu aguardava fila de runner. Tinha autorização, mas o ato não foi meu.
- **Dois desvios de processo meus**, ambos contornados sem pedir bypass: o `block-destructive` barrou force-push e `reset --hard` (troquei de branch em vez de insistir); e `git branch` não troca de branch, então um commit foi parar na branch local errada (corrigido com push fast-forward).
- **Três erros de medição meus**, pegos antes de virar conclusão publicada — todos LC-08: `grep` de `[REDACTED]` quando o token é `XXX.XXX.XXX-NN`; `grep` de `(?<!\d)` quebrando no escape de shell e reportando "não deployado"; e classificar "48 crus" sem aplicar o DV. Registro porque é a classe que este trabalho todo cataloga.
- **Vermelhos de CI que não eram meus**: `casos-gate` e `baseline-tamper-guard` acusaram baseline "crescido" — eu não toquei o arquivo; main o encolheu no [#5191](https://github.com/wagnerra23/oimpresso.com/pull/5191) e minha cópia velha virou superset (§5 2026-07-28). Resolvido com merge do main.
