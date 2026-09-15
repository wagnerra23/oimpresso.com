---
date: "2026-09-15"
time: "21:36 BRT"
slug: validador-schema-nao-sai-verde-sem-medir
tldr: "O validate-memory-schema.sh parou de afirmar sobre o que não mediu em 3 portas — falso 'campo ausente' por encoding, rodapé contando arquivo inexistente como validado, e tipo inválido saindo exit 0. 4 PRs mergeados (#7345 #7358 #7361 #7370), LC-33 aberta e 6 recibos de erros meus."
prs: [7345, 7358, 7361, 7370]
decided_by: [W]
related_adrs:
  - 0344-two-strikes-cobre-processo
  - 0130-handoff-append-only-mcp-first
next_steps:
  - "Nada aberto desta linha. A LC-33 nasceu com Gate: none e ALARMA de propósito — é o sinal honesto de que a classe repetiu sem defesa mecânica de classe."
---

## TL;DR

O `validate-memory-schema.sh` afirmava desfechos sobre o que **não tinha conseguido medir**, em três portas: campo presente acusado de ausente (encoding cp1252), arquivo inexistente contado como validado, e tipo inválido saindo `exit 0`. As três fechadas, com bite-test provado por mutação. **4 PRs mergeados**, **LC-33** aberta (classe nova, alarmando) e **6 recibos de erros meus** — porque cometi a mesma classe três vezes enquanto a consertava.

## Estado MCP no momento do fechamento

⚠️ **As tools `mcp__oimpresso__*` NÃO estavam disponíveis nesta sessão** (worktree; `ToolSearch` por `cycles-active`/`my-work` devolveu vazio, e o único servidor MCP configurado que reportou erro foi o `laravel-boost`). O snapshot abaixo veio da porta que existe aqui — `node .claude/hooks/brief-fetch-curl.mjs`, renovado no fechamento — e o resto é git/`gh` ao vivo. Fallback previsto em [`how-trabalhar.md`](../how-trabalhar.md) §Fallback.

```
Brief #645 · gerado há 95 min · endpoint mcp.oimpresso.com/api/mcp
  HITL pending [W]: 5     Brain B hoje: 0% (0/50)
  Em voo: 12 itens (Forja Triage ×3 · Produto G-06 e V0 · Infra Zod · Ponto lane required · Repair ×2)
  Decisões 24h: ADRs 5707 (aposentar rubrica module-grade) · 5710 (emenda 0314 — handoff integrity required)
  Commits 24h: 54   ·   ADS escalações: 0   ·   Incidentes: 0
  FLAGS: 🟠 US não atribuída 683 (524 sem dono) · 🟡 SDD composta 55,6 (Δ+0,1) · resto verde
```

Git ao vivo no fechamento: `origin/main` em `91622ae24c8`; os 4 squash-commits desta sessão são ancestrais dele (conferido por `merge-base --is-ancestor`, não por leitura de PR).

## O que aconteceu

Pedido de entrada: consertar um falso-positivo do `.github/scripts/validate-memory-schema.sh` que fazia o validador **local** mentir calado no Windows. O que saiu foi maior, porque o mesmo vício apareceu em **três portas** do mesmo script — e porque eu o cometi enquanto o consertava.

**Porta 1 — o FP de encoding (#7345).** `extract_frontmatter_field` imprimia o valor com `print()`; no Windows (stdout cp1252) um `→`/`≥`/`↔` estourava `UnicodeEncodeError`, o `2>/dev/null` comia o traceback, o `|| true` zerava o rc e o campo **presente** virava `campo obrigatório ausente`. **2ª ocorrência** — a 1ª foi em 2026-07-29, registrada como chip com o mesmo diagnóstico e o mesmo candidato de fix, adiada por two-strikes ([ADR 0344](../decisions/0344-two-strikes-cobre-processo.md)). Conserto: escrita por `sys.stdout.buffer` + contrato de exit (`0` existe · `1` ausente · `3` não-consegui-ler) nos 8 call sites, mais `add_violation` decodificando stdin por `buffer.decode`.

**Porta 2 — o rodapé (#7361).** `[[ ! -f ]] → continue` sem incrementar `SKIPPED`, e o rodapé faz `TOTAL - SKIPPED`: path que o validador nunca abriu entrava na conta de **validados**, com `exit 0`.

**Porta 3 — o tipo inválido (#7361, 2º commit).** O `TYPE` era validado só dentro do laço, então tipo torto **sem arquivos** caía no `[OK] nada a validar` e saía verde — `validate-memory-schema.sh --selftest` devolvia `exit 0` sem medir nada.

**O ledger (#7358) e a errata (#7370).** Registro do ciclo com lápide §5 + classe nova; e, duas horas depois, a errata de uma frase minha que o próprio merge tornou falsa.

## Artefatos gerados

| PR | arquivos | o que |
|---|---|---|
| [#7345](https://github.com/wagnerra23/oimpresso.com/pull/7345) `2ccc480d080` | script + bite-test + RUNBOOK (+184/−41) | contrato de exit 0/1/3 · `sys.stdout.buffer` · PERNA 4 forçando `cp1252` |
| [#7358](https://github.com/wagnerra23/oimpresso.com/pull/7358) `0259ea21a25` | `LICOES_CODE.md` · `licoes-rejeitadas.md` · `proibicoes.md` (+53) | lápide §5 + **LC-33** nova + 5 recibos |
| [#7361](https://github.com/wagnerra23/oimpresso.com/pull/7361) `4f614f80e50` | script + bite-test + RUNBOOK (+108/−6) | rodapé com 3 baldes · `MISSING` · TYPE na entrada · PERNAS 5 e 6 |
| [#7370](https://github.com/wagnerra23/oimpresso.com/pull/7370) `91622ae24c8` | `LICOES_CODE.md` · `licoes-rejeitadas.md` (+3/−1) | errata datada + recibo LC-10 (8× → 9×) |

**LC-33 — `vermelho-por-nao-medicao`** (classe nova): instrumento colapsa "não consegui medir" numa **acusação**, não num verde. É o espelho da LC-13 — lá esconde defeito, aqui **fabrica** defeito e manda consertar o alvo errado. `base:1` + 1 rec = 2 ocorrências, `Gate: none`, **alarmando**.

## Persistência

- **git canônico:** os 4 squash-commits em `origin/main`, conferidos por ancestralidade e por **controle de conteúdo no blob** (`## LC-33` e a string `inexistentes:` presentes).
- **MCP:** webhook GitHub→`mcp.oimpresso.com` propaga `memory/*` em ~2min.
- **BRIEFING:** não se aplica — nenhum `Modules/<X>` foi tocado (só `.github/scripts/`, `scripts/tests/`, `memory/`).
- **Deploy:** o run de `4f614f80e50` falhou por **corrida** (`cannot lock ref refs/remotes/origin/main` — o `main` andou durante o `git fetch` no Hostinger); o run seguinte (`84af54af35b`) saiu **success** e carrega os dois commits. Nenhum arquivo é de runtime.

## Próximos passos pra retomar

Nada aberto desta linha. Para retomar o tema:

```bash
node scripts/tests/memory-schema-detect.test.mjs     # 29 asserts, 6 pernas
```

## Lições catalogadas

Seis recibos, **todos de erros meus nesta sessão** — o arco tem simetria: o defeito era um instrumento afirmando sobre o que não mediu, e eu cometi a classe três vezes ao consertá-lo.

| classe | o que foi |
|---|---|
| **LC-33** (nova) | o defeito do validador, 2ª ocorrência |
| **LC-08** ×3 | li o `erros: 0` da **NOTA do próprio script** e publiquei número falso · medi sob `PYTHONIOENCODING` estrito e escrevi a conclusão errada **dentro do código** · `git rev-parse <ref>:<path>` mangleado pelo MSYS virou "DIVERGE" publicado |
| **LC-13** | comparei duas versões sobre 50 arquivos que **nenhuma** abriu (CRLF nos paths) e publiquei `erros: 0` dos dois lados |
| **LC-26** | par de barras colapsou num heredoc Python |
| **LC-10** | declarei em **presente** que um achado "NÃO está consertado" — e o consertei 2h depois |

**A que vale mais**, e é o incremento da lápide: *contornar um defeito no CHAMADOR não é consertá-lo — é cegar quem o acusaria*. O bite-test que pegaria o bug nasceu em 2026-09-10 **já carregando** `PYTHONIOENCODING: 'utf-8'`, com um comentário que descrevia o defeito corretamente.

**Processo:** o `ciclo-adversary` deu **REJECT** na 1ª redação do ledger com 5 itens; os 5 conferiram sob medição independente e 4 foram aplicados tal qual — o 5º (denominador) com número **meu** (549, não os 552 dele, que não aplicava o guard de YAML). O achado que mais doeu: minha justificativa de classe (*"a lápide-mãe declara LC-13"*) era **falsa** — ela só cita por analogia, e eu derivei de um hit de `grep`.

Três hooks morderam corretamente e os três me pouparam: `block-destructive` barrou um `rm -rf` e um `git push --force`; `memory-schema-preflight` barrou o frontmatter deste handoff (slug de ADR com ponto); `block-memory-drift` barrou um Edit neste arquivo mesmo — não tem como distinguir handoff novo de antigo, e recriei pelo caminho limpo em vez de usar o override.

## Pointers detalhados

- Session log desta sessão: [`memory/sessions/2026-09-15-validador-schema-tres-portas.md`](../sessions/2026-09-15-validador-schema-tres-portas.md)
- Lápide §5 (fonte): `memory/licoes-rejeitadas.md` → `### 2026-09-15 — EMENDA da lápide 2026-07-29 … eixo ESPELHADO`
- Classe: `memory/LICOES_CODE.md` → `## LC-33`
- Chip da 1ª ocorrência: [handoff 2026-07-29 20:05](2026-07-29-2005-srs-rodada5-aprovada-smoke-verde.md)
- RUNBOOK do gate: [`memory/requisitos/Infra/RUNBOOK-memory-schema-gate-extended.md`](../requisitos/Infra/RUNBOOK-memory-schema-gate-extended.md)
- ADR 0224 (hooks block × advisory) — citada na lápide; fora do `related_adrs` porque o slug tem ponto e o schema exige `^[0-9]{4}-[a-z0-9-]+$`
