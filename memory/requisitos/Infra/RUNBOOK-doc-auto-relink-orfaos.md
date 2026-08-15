# RUNBOOK — `doc-auto-relink --orfaos` (pagar dívida de link morto)

> **O que é:** o modo que fecha o elo *detectar → consertar* do `deadlink-gate`. O gate (required)
> impede **piorar**; este modo **paga** a dívida já congelada no baseline.
> **Landeado:** [PR #5818](https://github.com/wagnerra23/oimpresso.com/pull/5818) (2026-08-15) —
> piloto pagou 265 links (1.076 → 811).
> **Dono:** `scripts/governance/doc-auto-relink.mjs`. Não abra máquina paralela — estenda esta.

---

## 1. Quando usar (e quando NÃO)

| Situação | Usar? |
|---|---|
| Um doc mudou de pasta e os links não seguiram | ✅ sim |
| `deadlink-gate` acusa dívida grande num módulo que você vai mexer | ✅ sim, com `--escopo` |
| Proposta virou ADR aceita (`decisions/proposals/` → `decisions/`) | ✅ sim — é o caso mais comum |
| Você quer "limpar tudo de uma vez" | ❌ **não** — big-bang de legado morre no CI (§5 2026-07-12) |
| O link aponta pra doc que foi **deletado** de verdade | ❌ não há conserto automático |
| O alvo é `memory/decisions/**` ou `memory/handoffs/**` | ❌ append-only — exige label `adr-body-edit-W`, decisão [W] |

**Diferença do `--detect`:** o `--detect` só enxerga doc que moveu **e** tem `id` STAMPED no índice.
O `--orfaos` pega o **resíduo** — o link que não abre, sem depender de índice nenhum.

---

## 2. Uso — os 4 comandos que importam

```bash
# 1. VER o que dá pra religar (dry-run, não escreve nada) — comece SEMPRE por aqui
npm run docs:relink:orfaos
#    ou: node scripts/governance/doc-auto-relink.mjs --orfaos

# 2. RECORTAR por área (o jeito recomendado: pague um módulo por vez)
node scripts/governance/doc-auto-relink.mjs --orfaos --escopo memory/requisitos/Financeiro/

# 3. APLICAR com teto (nunca sem --max na primeira vez)
node scripts/governance/doc-auto-relink.mjs --orfaos --escopo memory/requisitos/Financeiro/ --apply --max 20

# 4. TRAVAR o ganho no ratchet (senão a dívida pode voltar)
node scripts/governance/deadlink-gate.mjs --check          # confirma que não piorou
node scripts/governance/deadlink-gate.mjs --write-baseline # regrava o baseline menor
node scripts/governance/baseline-tamper-guard.mjs          # prova que ENCOLHEU, não afrouxou
```

### Como ler a saída

```
[orfaos] link morto RELIGÁVEL (basename com alvo único, referrer mutável): 633
[orfaos] não propostos — ambíguo: 51 · sem alvo: 136 · referrer append-only/gate-guarded: 1681
```

A segunda linha é o **disclosure honesto** — o denominador do que ele *não* fez, e por quê.
Instrumento que só mostra o que achou esconde o que ignorou.

---

## 3. As 6 travas (por que é seguro)

| # | Trava | O que impede |
|---|---|---|
| 1 | referrer append-only / gate-guarded | editar ADR, handoff, session (Tier 0) |
| 2 | alvo ambíguo (N basenames) | adivinhar destino — o guard sintático que o §5 mata 4× |
| 3 | alvo inexistente | "consertar" link pra doc deletado |
| 4 | reescrita ancorada (`replaceExact`) | comer conteúdo vizinho (§5 2026-08-02) |
| 5 | `--max N` | big-bang de legado (§5 2026-07-12) |
| 6 | link root-relative válido | trocar a convenção do autor — **15,8% de FP medido** |

**A trava 6 é a mais sutil:** um link markdown cujo alvo é o caminho **a partir da raiz do repo**
(algo como `memory/decisions/0093-multi-tenant-...` escrito direto, sem `../`), dentro de
`.claude/agents/*.md`, **não está quebrado** — é root-relative de propósito. Só não resolve como relativo. Reescrever seria vandalismo
educado. Foi pega medindo 633 propostas antes de aplicar; 100 eram isso.

---

## 4. Fluxo recomendado (o que fiz no piloto, e deu certo)

```bash
# a) medir onde dói
node scripts/governance/deadlink-gate.mjs --scan | grep VIVO

# b) escolher UM escopo e ver o plano
node scripts/governance/doc-auto-relink.mjs --orfaos --escopo memory/dominios/

# c) aplicar
node scripts/governance/doc-auto-relink.mjs --orfaos --escopo memory/dominios/ --apply

# d) TESTE DE IDENTIDADE — o passo que ninguém pode pular
git diff --numstat | awk '$1!=1 || $2!=1 {print "ANOMALIA:",$0}'
#    vazio = todo arquivo mudou exatamente 1 linha (1+/1-). Qualquer saída = PARE e investigue.

# e) travar o ganho
node scripts/governance/deadlink-gate.mjs --write-baseline
node scripts/governance/baseline-tamper-guard.mjs
```

> ⚠️ **O passo (d) não é opcional.** O §5 tem duas lápides de codemod que comeu conteúdo vizinho
> (2026-08-02 reescrita sem delimitar o alvo · 2026-08-12 rename com 4 formas de referência).
> "Rodou sem erro" e até "teste verde" são compatíveis com dano intacto. **Diff idêntico é prova.**

---

## 5. Casos de uso cobertos por teste

`npm run docs:relink:selftest` → **27/27**. O que cada grupo prova:

| Caso de uso | Assert |
|---|---|
| Doc moveu, link não seguiu | `MORDE: link morto com alvo único vira proposta` |
| Dois arquivos com mesmo nome | `SOLTA: alvo ambíguo não vira proposta` |
| Link pra doc deletado | `SOLTA: alvo inexistente não vira proposta` |
| Link que já funciona | `SOLTA: link VIVO não vira proposta` (controle negativo) |
| Link dentro de ADR | `SOLTA: referrer append-only nunca vira proposta` + `ADR byte-a-byte intacta` |
| Link root-relative | `SOLTA: link ROOT-RELATIVE válido não vira proposta` |
| Link com `#âncora` | `USO: fragmento #ancora é PRESERVADO` |
| Mesmo link morto 2× no arquivo | `USO: vira UMA proposta` + `as DUAS ocorrências religadas` |
| Path dentro de `` `code-span` `` | `USO: code-span fica INTACTO (só markdown-link é alvo)` |
| Recorte por pasta | `USO: --escopo restringe` + `contabiliza o que ficou de fora` |
| Teto de leva | `--max N corta a leva` |
| Rodar duas vezes | `USO: re-rodar após o apply é NO-OP (idempotente)` |
| Não estragar o arquivo | `IDENTIDADE: só a linha do link mudou` + `nenhuma linha extra` |

---

## 6. Onde ele roda sozinho

- **CI, todo PR:** `governance-script-tests.yml` roda `--selftest` e `--orfaos` em **dry-run**
  (nunca `--apply` em CI — aplicar é ato humano, com o teste de identidade no meio).
- **Não é gate:** não avermelha PR nenhum. É relatório.

---

## 7. Resíduo conhecido (medido 2026-08-15, pós-piloto)

| Classe | Qtd | Caminho |
|---|---|---|
| `memory/decisions/` append-only | ~298 | decisão [W] + label `adr-body-edit-W` |
| Sem alvo real (doc deletado) | ~136 | manual: apontar pro sucessor ou remover o link |
| Ambíguo (N basenames) | ~51 | manual: só quem escreveu sabe qual era |

Esses **não** têm conserto automático por construção. Pagar é decisão, não automação.

---

_Refs: [PR #5818](https://github.com/wagnerra23/oimpresso.com/pull/5818) · `deadlink-gate` (required, ADR 0347) · ADR 0256 · `memory/proibicoes.md` §5 (2026-08-02, 2026-07-12) · `memory/reguas/fraquezas.json#spec-auto-remediacao`_
