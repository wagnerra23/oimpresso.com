---
id: requisitos-governance-protocolo-refutador-backfill
status: active
owner: "[W] Wagner"
module: Governance
updated_at: "2026-07-30"
---

# PROTOCOLO-REFUTADOR-BACKFILL — verificação adversarial de lotes IA (GT-G5)

> **Regra de ouro do backfill** ([plano-mãe SDD §1](../../sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md)): todo lote gerado por IA passa por refutação adversarial **ANTES** do merge. Sem entry no ledger, merge proibido.
> **Por quê:** com lotes IA preenchendo memória canônica em massa (anchors `Implementado em` nos 57 SPECs, BRIEFINGs destilados, filas de triage), um gerador que alucina envenena a fonte de verdade que todas as sessões futuras leem. O refutador é a vacina; o ledger é o registro auditável de que ela foi aplicada.

## 1. Escopo — o que é "PR-de-lote"

PR que **adiciona/modifica >10 arquivos em `memory/requisitos/**`** e cujo conteúdo foi gerado por IA em batch. Cobre os lotes das ondas SA-A5 (anchor-backfill), KL-E3 (BRIEFINGs destilados) e qualquer fila/triage gerada em massa. PRs pequenos (≤10 arquivos) ou 100% humanos não exigem entry — mas o scan PII (§4 item 5) é recomendado sempre.

## 2. Protocolo (passo a passo, na ordem)

1. **Gerador** (Haiku/Sonnet) produz o lote e abre PR **draft**.
2. **Refutador** sobe em **sessão FRESCA** — zero contexto do gerador (outra sessão/worktree; NUNCA a mesma conversa, nem "continuar de onde parou"). Contexto compartilhado = refutador contaminado = refutação inválida.
3. **Modelo do refutador de TIER SUPERIOR ao do gerador** (ordem: haiku < sonnet < opus < fable/mythos). Ex.: gerador Opus 4.8 → refutador Fable 5. Igualdade de tier só é aceita quando o gerador já é o tier máximo disponível (não existe superior). _Endurecido 2026-07-01 (antes: ≥) — achado da [avaliação adversarial SDD 2026-07-01](../../sessions/2026-07-01-sdd-avaliacao-adversarial.md): refutação por modelo idêntico tem correlação de erros — gerador e refutador alucinam igual._
4. **Prompt adversarial canônico:** "Prove que este anchor/claim/BRIEFING está **ERRADO**. Busque evidência no código real em origin/main (paths, git log, testes) — não no texto do PR. Para cada item: CONFIRMADO ou REFUTADO + evidência (path/linha/commit)."
5. **Amostragem:** anchors (paths, `Implementado em`, US-ids) = **100%** dos itens; prosa destilada (BRIEFINGs, resumos) = **≥30%** dos arquivos do lote, seleção aleatória com seed declarada na evidência.
6. **Critério de aceite:** `backfill_error_rate = erros_confirmados / itens_verificados < 2%`. Se ≥2% → lote **REPROVADO inteiro**: volta pro gerador, corrige, e o refutador re-verifica o lote todo de novo (não só os itens errados — erro sistemático de prompt costuma estar espalhado).
7. **Entry no ledger** `governance/sdd-verification-ledger.json` adicionada no MESMO PR do lote, antes do merge. Ledger é **append-only** (corrigir = nova entry, nunca editar a antiga).

## 3. Checklist do refutador (copiar pro artefato de evidência)

- [ ] Sessão fresca (sem nenhum contexto do gerador)
- [ ] Modelo de tier SUPERIOR ao gerador (haiku < sonnet < opus < fable/mythos; igualdade só no tier máximo)
- [ ] Amostra: 100% anchors / ≥30% prosa (seed da seleção aleatória declarada)
- [ ] Cada item verificado contra o código real em origin/main, não contra o diff
- [ ] Cada REFUTADO anotado com evidência (path + linha/commit + porquê)
- [ ] **Scan PII no diff** — repo é PÚBLICO: CPF (`\d{3}\.\d{3}\.\d{3}-\d{2}` e 11 dígitos crus), CNPJ (`\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}`), nomes de cliente do CRM, telefones, e-mails de cliente. O gitleaks do umbrella (`governance-gate-umbrella.yml` job `secret-scan`) cobre tokens/segredos, **NÃO cobre PII brasileira** — este item é grep manual obrigatório. Hits = 0 ou lote reprovado.
- [ ] `error_rate_pct` calculado e < 2
- [ ] Entry no ledger no mesmo PR, veredito + evidência preenchidos

## 4. Ledger — schema da entry

| Campo | Tipo | Regra |
|---|---|---|
| `pr` | integer | número do PR do lote |
| `lote_id` | string | ex.: `SA-A5-financeiro-01`, `KL-E3-briefings-02` |
| `data` | string | `"YYYY-MM-DD"` da refutação |
| `tipo` | enum | `anchors` \| `prosa` |
| `gerador` / `refutador` | string | contém `haiku`/`sonnet`/`opus`/`fable`/`mythos`; refutador de tier SUPERIOR ao gerador (igualdade só no tier máximo). **Gerador não-Anthropic:** ver §4.1 |
| `sessao_fresca` | boolean | tem que ser `true` |
| `amostra_pct` | number | `anchors` → 100; `prosa` → ≥30 |
| `itens_verificados` / `erros_confirmados` | integer | base do error_rate |
| `error_rate_pct` | number | aceite < 2 |
| `pii_scan` / `pii_hits` | boolean / integer | `true` / `0` obrigatórios |
| `evidencia` | string | path do artefato de refutação (session log ou comment do PR) |
| `veredito` | enum | `aprovado` \| `reprovado` |

### 4.1 Gerador NÃO-Anthropic (emenda 2026-07-30)

A escala de tiers do §2.3 só conhece modelos Anthropic. Um lote gerado por modelo de outro
vendor (Codex/GPT, Gemini, Llama…) caía em `rank(gerador) === 0` e era **reprovado
independentemente do veredito** — gate sem caminho honesto de abertura, cujo único "jeito
prático" era falsear o campo `gerador`. Regra que fabrica incentivo pra mentir no registro
é pior que regra ausente.

**O que a regra de tier protege é DECORRELAÇÃO**, não hierarquia por si: gerador e
refutador do mesmo modelo alucinam igual (avaliação 2026-07-01). **Vendor cruzado satisfaz
isso por construção** — treinos, tokenizadores e modos de falha distintos decorrelacionam
mais que `opus`-refuta-`opus`, que a regra já aceita no tier máximo.

Portanto:

| Gerador | Refutador exigido |
|---|---|
| Anthropic | tier SUPERIOR (igualdade só no tier máximo) — §2.3, inalterado |
| **Não-Anthropic reconhecido** (`codex`/`gpt-N`/`gemini`/`llama`/`mistral`/`grok`/`deepseek`/`qwen`) | **Anthropic de tier ALTO (≥ `opus`)** |
| String não reconhecida (nem Anthropic, nem vendor externo) | **reprova** — o campo não é terra de ninguém |

O refutador continua tendo que ser Anthropic de tier conhecido: não sabemos ranquear um
refutador externo, e afrouxar os dois lados de uma vez esvaziaria o campo.

Enforcement: `ledger-check.mjs` (`isExternal` + `MIN_RANK_VS_EXTERNAL`), com bite-test
pareado em [`ledger-check-external.test.mjs`](../../../scripts/governance/ledger-check-external.test.mjs)
— 10 casos, incluindo os controles negativos de que a regra Anthropic×Anthropic **não** foi
afrouxada e de que veredito/`error_rate` seguem barrando. Rodado no CI por
`governance-script-tests.yml`.

Origem: refutação GT-G5 rodada 2 do PR #5069 — o lote do Codex ficou preso por este defeito
de mecanismo, não por defeito do lote. A emenda **não** aprova aquele lote (ele segue
`reprovado` por 3 erros próprios); só devolve ao gate um caminho honesto de abertura.

### 4.2 Teto de política — "máximo disponível" é o que o projeto USA (emenda 2026-08-12)

O §2.3 sempre disse *"igualdade só quando o gerador já é o tier máximo **disponível**"*. O
`ledger-check` lia "disponível" como o `Math.max` da tabela — **fable**. Só que fable não
está disponível na prática: [W] o vetou por custo (2026-08-12, PR #5680). Com quase todo
lote nascendo de opus (23 das 87 entries deste ledger), a regra virava **insatisfazível**.

É o mesmo defeito de mecanismo que a §4.1 consertou pro gerador externo — gate sem
caminho honesto de abertura, cujo único "jeito prático" é falsear o campo `gerador`. E
vale repetir o que aquela emenda cravou: **regra que fabrica incentivo pra mentir no
registro é pior que regra ausente.**

Portanto o teto passa a ser **`opus`**: gerador opus + refutador opus é aceito.

**O que isto NÃO afrouxa** — e há controle negativo pra cada um em
[`ledger-check-external.test.mjs`](../../../scripts/governance/ledger-check-external.test.mjs)
(17 casos, todos verdes):

| caso | veredito |
|---|---|
| opus gera + opus refuta | **passa** (teto de política) |
| sonnet gera + sonnet refuta | reprova — igualdade abaixo do teto não vale |
| haiku gera + haiku refuta | reprova |
| opus gera + sonnet refuta | reprova — refutador abaixo do gerador |
| `sessao_fresca: false` | reprova — vale para opus×opus também |
| `veredito: reprovado` / `error_rate >= 2` | reprovam, como antes |

A linha que sustenta a emenda é a da própria §4.1: **o que a regra protege é
DECORRELAÇÃO, não hierarquia por si.** O tier é uma das metades; a outra é
`sessao_fresca` — refutador em contexto próprio, sem herdar nada do gerador — e essa
continua exigida e enforçada. Quando fable voltar a ser viável para uso rotineiro, o teto
sobe de volta com uma linha (`MAX_RANK`).

**Origem:** PR #5680. A refutação opus×opus rodada lá **encontrou defeito real** — 6
fósseis de `memory/modulos/` que o `guardaPerdaDeBranch()` protege e que um script de
remoção havia apagado. O lote foi reprovado, corrigido, e reaprovado numa segunda rodada
que ainda achou um conflito de merge em índice derivado. Ou seja: a decorrelação por
sessão fresca **funcionou na prática** no exato caso que motivou a emenda.

## 5. Enforcement — ledger-check.mjs

`node scripts/governance/ledger-check.mjs --pr <N> [--base origin/main] [--head HEAD] [--enforce] [--json]`

- Detecta PR-de-lote (>10 arquivos em `memory/requisitos/**` no diff base...head) e exige entry válida no ledger pro PR.
- Valida TODAS as regras do §4 (modelo, amostra, error_rate, PII, sessão fresca, veredito).
- **Nasce ADVISORY** (sem `--enforce` sempre sai 0, só imprime aviso) — regra "gates novos nascem advisory". Fase 2 do plano-mãe pluga no workflow do scorecard; promoção a required segue o calendário de promoções (máx 1/semana).
- `--files-from <txt>` e `--ledger <path>` existem pra simulação/selftest (GT-G6) sem tocar git nem o ledger canônico.

## 6. Anti-gaming

- Refutador na mesma sessão do gerador = entry inválida (auto-atestado não vale).
- Amostra "aleatória" sem seed declarada = refazer.
- Editar entry antiga pra mudar veredito = violação append-only (diff do ledger é revisado como qualquer código).
- O ledger registra REPROVADOS também — taxa de reprovação é insumo da métrica `backfill_error_rate` do scorecard SDD (GT-G2).
