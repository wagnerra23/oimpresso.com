---
status: active
owner: "[W] Wagner"
module: Governance
updated_at: "2026-07-01"
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
| `gerador` / `refutador` | string | contém `haiku`/`sonnet`/`opus`/`fable`/`mythos`; refutador de tier SUPERIOR ao gerador (igualdade só no tier máximo) |
| `sessao_fresca` | boolean | tem que ser `true` |
| `amostra_pct` | number | `anchors` → 100; `prosa` → ≥30 |
| `itens_verificados` / `erros_confirmados` | integer | base do error_rate |
| `error_rate_pct` | number | aceite < 2 |
| `pii_scan` / `pii_hits` | boolean / integer | `true` / `0` obrigatórios |
| `evidencia` | string | path do artefato de refutação (session log ou comment do PR) |
| `veredito` | enum | `aprovado` \| `reprovado` |

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
