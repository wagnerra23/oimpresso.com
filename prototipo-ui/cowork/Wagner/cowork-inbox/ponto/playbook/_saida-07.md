---
sessao: "07"
titulo: "Contratos do Ponto → required — saída da thread (pacote para o flip [W])"
autor: "[CL]"
criado: 2026-09-29
base: 5606344ca
thread: 07-contratos-required.md
veredito: "os 5 contratos existem, rodam e mordem; nenhum contrato novo e nenhum PR de código. O flip de branch protection NÃO foi feito (soberania [W]). Dois achados mudam o que o flip significa: (1) não existe 'required por contrato', o required é o job inteiro, que roda os 64 contratos do repo; (2) esse job carrega o step Omissão, que o próprio workflow declara advisory e que responde pelos 7 dos 10 vermelhos recentes do job. A regra de 3 verdes seguidos: 3 de 3 para os 4 contratos antigos, 1 de 3 para o ponto-rep-p."
---

# _saída 07 · Contratos do Ponto → required

> O índice diz "5/5" e a thread diz "4/4": a thread foi escrita antes da `ponto-conformidade`
> entrar como guarda (errata do índice §5). Os cinco foram tratados aqui. Não editei o índice.

## 1. Os 5 contratos existem, estão no `main` e passam

Medido em `5606344ca` (tip de `origin/main` na abertura), com `node scripts/contrato-de-tela.mjs --contract <c>`:

| contrato | tela | seções | rc | entrou por |
|---|---|---:|---:|---|
| `ponto-painel.contract.json` | `Ponto/Dashboard/Index` | 4 | 0 | (anterior) |
| `ponto-espelho.contract.json` | `Ponto/Espelho/Show` + `Index` | 5 | 0 | (anterior) |
| `ponto-conformidade.contract.json` | `Ponto/Conformidade` | 4 | 0 | #7994 (thread 05) |
| `ponto-fechamento.contract.json` | `Ponto/Fechamento/Index` | 4 | 0 | #7998 (thread 04) |
| `ponto-rep-p.contract.json` | `Ponto/Mobile/*` (3 arquivos) | 8 | 0 | #8192 (thread 06, recupera #8158/#8159) |

O job inteiro também foi rodado no mesmo tip: **64 contratos, 0 falhas** · `--map --check` 0 · `--anti-tautologia` 0 · `contrato-de-tela.test.mjs` 0.

## 2. Eles mordem: bite-test com fixture ruim

A cada contrato foi acrescentada uma copy impossível (`__MORDIDA_BITE_TEST__`) na 1ª seção. As fixtures ficaram no scratchpad da sessão e não entraram no repo.

| contrato | rc | o que o gate disse |
|---|---:|---|
| conformidade | **1** | `X copy ausente em "cabecalho"` |
| espelho | **1** | `X copy ausente em "espelho-dados-colaborador"` |
| fechamento | **1** | `X copy ausente em "fechamento-acoes"` |
| painel | **1** | `X copy ausente em "painel-nota-fechamento"` |
| rep-p | **1** | `X copy ausente em "repp-nota-regras"` |

O controle positivo é a tabela 1: os mesmos contratos, sem a mutação, saem com rc 0. O `rc=1` vem do assert de copy, não de erro de execução.

## 3. "Declarar no mesmo lugar" não tem lugar: a inclusão é por glob

A thread manda acrescentar os 2 novos "no mesmo lugar onde `painel`/`espelho` estão declarados como gate". **Esse lugar não existe.** O step `Contratos de tela ativos` de `.github/workflows/contrato-de-tela.yml` faz

```
git ls-files '*.contract.json' | grep -v EXEMPLO
```

e roda todos. Um contrato novo entra no gate no commit em que nasce, sem ninguém declará-lo. Logo não há PR de código a fazer para a inclusão, e não abri nenhum. Não criei um 2º padrão (PARAR SE da thread).

A consequência importa para o flip: **não existe "required por contrato"**. O que vira required é o check do job `Preflight + contratos ativos`, e ele cobre **os 64 contratos do repo**, não os 5 do Ponto.

## 4. Sempre roda em PR (always-run)

- O workflow declara `pull_request:` sem `paths:` e sem `types:`, então o default inclui `synchronize`. O filtro de arquivos fica **dentro** do job, com skip-as-pass (ADR 0261), e o check reporta status em todo PR.
- `node scripts/governance/required-always-run.mjs` no mesmo tip: `47 contexts required · 47 always-run · 0 FILTRADO · 0 SEM synchronize`. Os dois checks do workflow **não estão entre os 47**: a união `classic_protection ∪ rulesets` de `governance/required-checks-baseline.json`, lida em 2026-09-29, não tem nenhum context de contrato de tela.

## 5. A regra dos 3 verdes seguidos: só conta run em que o step EXECUTOU

Run com skip-as-pass sai verde sem rodar contrato nenhum e **não conta** (LC-13). Contei o step `Contratos de tela ativos` com `conclusion == success` e as linhas `== <contrato>` no log.

**Push em `main`, as 4 execuções reais mais recentes, seguidas e todas verdes:**

| run | sha | quando (UTC) | contratos | Ponto no run | falhas |
|---|---|---|---:|---|---:|
| 36589960134 | `bfa9c59d3` | 2026-09-29 15:24 | 63 | conformidade, espelho, fechamento, painel | 0 |
| 36601656475 | `46f1d4b39` | 2026-09-29 16:58 | 63 | idem | 0 |
| 36602897712 | `55533eae7` | 2026-09-29 17:09 | 63 | idem | 0 |
| 36606225831 | `a71c2f2d0` | 2026-09-29 17:37 | 64 | idem **+ rep-p** | 0 |

- **4 contratos antigos: 4 de 3 ✓**.
- **`ponto-rep-p`: 1 de 3.** Ele nasceu no `a71c2f2d0`. Nas 6 pushes seguintes em `main` (até `831461d6a`, 21:19 UTC) e nos 18 runs de PR abertos desde 17:37 UTC, o step saiu `skipped`, porque nenhum arquivo relevante mudou. O placar sobe sozinho no próximo PR que tocar um `.tsx` de `Pages/` ou um contrato. **Não fabriquei execução** para completar a conta.

## 6. O achado que muda o flip: o job carrega um step advisory

O job `Preflight + contratos ativos` roda 6 steps. Contratos, mapa e anti-tautologia estão verdes. Mas o step **`Omissão — símbolo/rota removido sem justificativa`** é declarado **advisory** pelo próprio comentário do workflow ("advisory significa não bloqueia o merge"), com FP medido de 9,5% e um falso-positivo confirmado na errata (`VariacoesTab`).

Nas **10 falhas mais recentes** do workflow (`status=failure`, 2026-09-10 a 2026-09-29), os steps que falharam foram:

| step que falhou | vezes |
|---|---:|
| Omissão | **7** |
| Preflight de base (higiene de base) | 1 |
| (sem step em falha: run cancelado) | 2 |
| **Contratos de tela ativos** | **0** |

Promover o check do job como está **transforma a Omissão em bloqueante** sem que ninguém tenha decidido isso, contra o texto do próprio workflow. É uma mudança de soberania escondida dentro de outra.

## 7. Pacote para o [W] flipar (não executado)

**Os dois caminhos:**

- **A · flipar o job como está.** Promove `Preflight + contratos ativos` (e, se quiser, `Self-test — o gate morde e libera certo`). Custo: a Omissão passa a bloquear. Pela amostra da §6, teria bloqueado 7 PRs em 20 dias.
- **B · separar antes, flipar depois (recomendado).** Um PR tira os steps de contrato (detecção, `Contratos de tela ativos`, `--map --check`, `--anti-tautologia`) para um job próprio e deixa a Omissão onde está, advisory. Só esse job novo vira required. A Omissão segue vermelha quando morde, mas não trava. Não abri esse PR: a thread proíbe criar estrutura nova sem reportar antes (PARAR SE), e mudar o nome do job que vai virar required é exatamente o vetor da §5 2026-08-08 (promover mexendo no nome do job). Com o sinal do [W], é 1 PR.

**Os passos do flip, em qualquer dos dois caminhos:**

1. Esperar o `ponto-rep-p` somar 3 execuções verdes (§5), ou aceitar 1 de 3 conscientemente.
2. Gerar o payload **a partir de** `governance/required-checks-baseline.json`, com o novo nome acrescentado, num arquivo UTF-8 **sem BOM**, e mandar por `gh api --input <arquivo>`. Nada de JSON inline no shell do Windows (§Ambiente, mojibake de 2026-07-02).
3. Atualizar o `governance/required-checks-baseline.json` no mesmo trabalho.
4. Validar: `node scripts/governance/protection-drift.mjs` (comparação string-exata; contar "N contexts" não prova nada) e `node scripts/governance/required-always-run.mjs`.
5. `gh pr update-branch` nos PRs abertos (RUNBOOK-branch-protection §Promoção de check a required).

**Contra a regra da ADR 0336 (promoção por mordida provada):** a mordida do step de contrato está provada **por fixture** (§2). Mordida **real em PR** do step `Contratos de tela ativos` não apareceu nas 10 falhas recentes. Esse gate não está no `design-gate-bites` (`--tally` lista só o `ancora-selftest`). Se o [W] exigir DR-2 com PR real, este pacote **não** cumpre, e é isso que o sustenta hoje: fixture, mais os runs verdes.

## Placar da thread (a "Prova" do 07-contratos-required.md)

| prova | estado |
|---|---|
| `ponto-fechamento.contract.json` e `ponto-rep-p.contract.json` existem | ✓ no `main` (#7998, #8192) |
| 4 exit 0 no `_saida-07.md` | ✓ **5** exit 0 (§1), mais o bite-test que morde nos 5 (§2) |
| required declarado no mesmo lugar dos 2 antigos | ✗ **não se aplica como escrito** (§3): não há declaração por contrato, e o required é branch protection, flip [W] (§7) |

**Entregue:** 2 de 3 provas. **Ausente:** o required, por soberania [W]. O pacote está pronto em §7.

## Desfecho — 2026-09-30

- **Caminho B, feito:** o #8223 separou o job. Agora `Contratos de tela (fidelidade + intenção)` roda os contratos, e `Preflight de base + omissão (advisory)` segue advisory.
- **3 de 3 para o `ponto-rep-p`:** push `a71c2f2d0` → PR #8223 (run 36703296537) → push `1159f1136` (run 36704447590), o step de contratos executado e verde nas três.
- **Flip aplicado pelo [W] ("faz o flip do required"):** `Contratos de tela (fidelidade + intenção)` entrou na branch protection de `main` (46 → 47 classic, 48 com o ruleset). Payload via `--input`, UTF-8 sem BOM. `protection-drift` 🟢 no bloco de proteção. Os 3 PRs abertos (#8224, #8197, #8134) receberam `update-branch`. O baseline foi reconciliado no PR deste desfecho.
- A prova "required declarado no mesmo lugar" fica atendida no único lugar onde o required existe: a branch protection mais o `governance/required-checks-baseline.json`.
