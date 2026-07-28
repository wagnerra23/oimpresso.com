---
date: "2026-07-28"
time: "07:54 BRT"
slug: "sdd-faltantes-fila-derivada-10-merges"
tldr: "Pedido era 'faça todos os SDD faltantes'. Medi antes: dos 32 restantes só 7 são chip válido — 6 travados na Onda 0, 19 sem teste (o G-2 required reprova). A Onda 0 estava errada nos dois sentidos e nunca foi paga: 225 testes órfãos em 8 módulos, incl. Cliente (64) e OficinaAuto (44), que mergearam contrato com teste que nenhum job roda. Entreguei a fila derivada + errata + recuperei commit encalhado. [W] então autorizou merge: 10 PRs, nenhum de bandeja. Pendência: flip da protection do #4901."
decided_by: [W]
prs: [4903, 4898, 4900, 4838, 4705, 4901, 4904, 4905, 4906, 4910]
next_steps:
  - "[W] flip do `PHP / Pest (TeamMcp · MySQL)` na branch protection (#4901 mergeou a ADR 0354 mas NÃO ligou o gate) — nome tem `·` U+00B7, usar arquivo UTF-8 sem BOM + `gh api --input`, validar com protection-drift.mjs (RUNBOOK-branch-protection.md). NÃO é executável da sessão nuvem: sem `gh` e sem tool MCP de protection"
  - "Decisão [W]: pagar a Onda 0 (8 lanes, 225 testes órfãos) antes de novos chips? Cliente e OficinaAuto já têm SDD mergeado com teste decorativo"
  - "Decisão [W]: os 19 módulos com 0 testes — 3 saídas registradas no plano (Onda 0 estendida / chip de 2 tempos com UC [BACKLOG] / tirar do denominador o que não é módulo de negócio)"
  - "Decisão [W]: #4913 (SDD NfeBrasil) tem 🔴 toggleAutoEmission sem gate de permissão — liga emissão automática de documento fiscal; §9 do SDD lista as decisões"
  - "#4917 (VozDoCliente) segue draft com 3 decisões abertas de [W]"
related_adrs:
  - 0351-sdd-from-source
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0119-paralelismo-sessoes-whats-active-tier-1
  - 0314-poda-gates-onda-2-lei-fusoes
---

# Handoff 2026-07-28 07:54 — SDD faltantes: fila derivada + 10 merges

## Estado MCP no momento do fechamento

| Consulta | Resultado |
|---|---|
| `cycles-active` · `my-work` · `sessions-recent` · `decisions-search` | ⚠️ **NÃO EXECUTADAS — MCP indisponível nesta sessão.** O hook `brief-fetch` disparou em modo fallback no SessionStart (*"settings.local.json não encontrado — token MCP indisponível"*), e nenhuma tool MCP do oimpresso ficou acessível. |
| `whats-active` (detecção de sessão paralela) | Substituído por inspeção direta: `git for-each-ref` nas branches remotas + `list_pull_requests` via API do GitHub. **Detectou a sessão irmã** `claude/sdds-pendentes-c3a697` e, depois, os chips da Onda 4/5 nascendo durante a sessão. |

> Registro honesto: o protocolo de fechamento ([how-trabalhar.md](../how-trabalhar.md) §"Ao terminar uma sessão") exige o snapshot MCP como **prova, não promessa**. Sem MCP, a prova aqui é a inspeção de git/API acima — não um snapshot fabricado.

## O que entrou em main — 10 PRs

| PR | O quê | O que travava |
|---|---|---|
| [#4903](https://github.com/wagnerra23/oimpresso.com/pull/4903) | fila derivada dos 32 + errata da Onda 0 + **commit encalhado recuperado** | draft |
| [#4898](https://github.com/wagnerra23/oimpresso.com/pull/4898) | schema dos RUNBOOKs + BRIEFING | — |
| [#4900](https://github.com/wagnerra23/oimpresso.com/pull/4900) | handoff sentinela US sem dono | `Casos-coverage · ratchet` **required vermelho** |
| [#4838](https://github.com/wagnerra23/oimpresso.com/pull/4838) | painel do sistema regenerado | required **"expected"** (nunca rodou) |
| [#4705](https://github.com/wagnerra23/oimpresso.com/pull/4705) | atribuição do relato SDD (Maiara) | 2 required "expected" |
| [#4901](https://github.com/wagnerra23/oimpresso.com/pull/4901) | ADR 0354 — teammcp-pest required | **conflito** |
| [#4904](https://github.com/wagnerra23/oimpresso.com/pull/4904) · [#4905](https://github.com/wagnerra23/oimpresso.com/pull/4905) · [#4906](https://github.com/wagnerra23/oimpresso.com/pull/4906) | SDD de KB · TeamMcp · Vestuario (Onda 4) | verificados antes |
| [#4910](https://github.com/wagnerra23/oimpresso.com/pull/4910) | Fiscal DF-e troca lastro de fachada por teste real | — |

**Padrão que vale reter:** o vermelho do #4900 e os "expected" do #4838/#4705 tinham a **mesma raiz** — base velha vs conjunto de required atual. Atualizar a branch com o `main` resolveu os três. Não era defeito de conteúdo.

**O conflito do #4901 era de line-ending, não de conteúdo.** `base` e branch em CRLF; `main` normalizado pra LF. Semanticamente `main` == base no `gates-registry.json` (115 workflows dos dois lados, zero entradas alteradas pelo main). Resolvi tomando a versão LF do main + aplicando **só** a entrada `teammcp-pest.yml`, depois de confirmar que `JSON.stringify(...,2)` faz round-trip byte-idêntico. Diff final: 3 linhas por 2.

## A dívida que o #4903 nomeia — 225 testes que nenhum job roda

| Módulo | Órfãos | Tem SDD? |
|---|---:|---|
| Cliente | 64 | ✅ chip mergeado **sem lane** |
| OficinaAuto | 44 | ✅ chip mergeado **sem lane** |
| RecurringBilling · Admin · Auditoria · Manufacturing · ProjectMgmt · ConsultaOs | 117 | — |

Cliente e OficinaAuto são o caso duro: contrato entregue, teste decorativo por construção — o defeito #7 do piloto reaparecendo *depois* de nomeado. As lanes existiam na branch da sessão irmã e não chegaram ao main. **Nenhuma máquina acusou: *"teste sem lane"* não é gate.**

E a Onda 0 estava errada nos dois sentidos: **Sells já tinha lane** (74 testes) e **Repair também** (por *matrix*, não path literal).

## Método — o que quase passou

Três erros de medição pegos antes de virarem afirmação (glob que subconta arquivo direto no diretório; medição de lane cega a *matrix*; espelho git stale no fim, do qual **recusei derivar contagem**). E um que quase passou: **`git fetch` falhou e o `|| echo "nenhum ✓"` imprimiu conformidade mesmo assim** — o anti-padrão do `crontab -l` já catalogado no §5 (2026-07-17). Descartei e refiz pela API. Não virou afirmação, mas foi por um fio.

No fechamento a mesma família reapareceu 2×: `exit=$?` depois de pipe com `||` reportando 0 pra comando que falhou, e o `validate.mjs` acusando `tldr > 500` — pego pela máquina antes do push, que é o desfecho certo.

## Para a próxima sessão

1. **O flip do #4901 é o item mais afiado** — o gate está documentado e registrado, mas não morde. Enquanto isso a lane `teammcp-pest` roda sem bloquear, que é estado honesto, não quebrado. **Não é executável da sessão nuvem**: sem `gh` e sem tool MCP de branch protection.
2. **Antes de abrir chip novo, decidir a Onda 0.** Abrir mais chips sem ela reproduz o defeito Cliente/OficinaAuto — e agora ele tem nome e número.
3. **A sessão irmã segue produzindo** (Onda 5 = NfeBrasil, #4913). Rodar `whats-active` — ou, sem MCP, inspecionar branches/PRs — antes de tocar `memory/requisitos/<Mod>/`.
4. **Contagem de SDD: re-rode, não copie.** `node scripts/governance/requisitos-status.mjs <Modulo>` — qualquer número aqui é recibo datado desta manhã.
