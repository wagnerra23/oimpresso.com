---
date: "2026-07-27"
time: "19:01 BRT"
slug: sentinela-us-sem-dono-ligada
tldr: "A sentinela mcp:tasks:unassigned existia com Pest e ZERO invocadores por 33 dias — ligada em #4862 (MERGED): cron 06:45 BRT + flag no brief 6x/dia. O acervo real medido em prod é 680 nao atribuidas / 537 sem dono (o triage dizia >=50 porque eu tinha truncado a consulta). --strict fica DESLIGADO de proposito: com 680 seria parede, nao catraca."
prs: [4862]
decided_by: [W]
related_adrs:
  - 0070-jira-style-task-management-current-md-removed
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
next_steps:
  - "Conferir a 1a flag no brief das 20h BRT (depende do deploy fechar)"
  - "Conferir o 1o cron amanha 06:45 BRT via `php artisan schedule:list | grep tasks:unassigned` no Hostinger"
  - "Decidir se a flag de 680 vira paisagem — se sim, considerar reportar FLUXO (nascidas sem dono desde ontem) em vez do acervo"
---

# Handoff — sentinela de US sem dono ligada

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` (@wagner) → **10 tasks, todas em REVIEW**. Duas são a contraparte humana deste trabalho: `US-TR-309` (tela Triage lista órfãs) e `US-TR-310` (atribuir owner inline)
- Handoffs irmãos de hoje: [11:35 produto-3-achados](2026-07-27-1135-produto-3-achados-tier0-fechados.md) · [14:45 órfãos-ligados](2026-07-27-1445-orfaos-ligados-elo-hitl.md) · [09:05 sdd-produto](2026-07-27-0905-sdd-produto-fechado-cadeia-requisitos.md)
- Session logs de hoje: 5 antes deste (conferido por `ls` antes de criar — sem duplicação)

## O que aconteceu

[W] enunciou a doutrina *"uma única entrada, session log, e sempre um ticket de backlog para um dono"* e perguntou se era caso de pesquisar fundo e modificar módulo. Medindo, **não era**: duas pernas já eram canon e a terceira tinha máquina pronta e **parada há 33 dias** — `mcp:tasks:unassigned` (US-INFRA-043, #3302), com Pest e com `--json` escrito *"pro Daily Brief"*, e `git grep` contado dando **14 ocorrências / 7 arquivos / 0 invocadores**.

Ligada em [#4862](https://github.com/wagnerra23/oimpresso.com/pull/4862), **MERGED 21:01 UTC** (auto-merge squash — [W] disse "merge" com 93 checks pendentes; não usei `--admin` porque os pendentes incluíam os Tier 0).

## Artefatos gerados

| Arquivo | O quê |
|---|---|
| `Modules/Jana/Services/TasksSemDonoBriefLineService.php` (182) | Flag no brief; fonte-única (chama `detectarNaoAtribuidas()`, não reimplementa a regra) |
| `Modules/Jana/Tests/.../TasksSemDonoBriefLineServiceTest.php` (132) | Núcleos puros, zero DB; **10/10 PASS** no CI executado por nome |
| `app/Console/Kernel.php` (+31) | Cron 06:45 BRT, `environments(['live'])` |
| `memory/governance/AUTOMATIONS.md` · `memory/requisitos/Infra/SPEC.md` | Registro + correção: a US estava `done` com acceptance #2 nunca entregue |

## Persistência

- **git:** #4862 merged; este handoff + [session log](../sessions/2026-07-27-sentinela-us-sem-dono-ligada.md) neste commit
- **MCP:** propaga via webhook (~2min pós-push)
- **BRIEFING:** não tocado — o PR não muda capacidade de módulo, liga infra de governança

## Números medidos (prod, não estimados)

`680` não atribuídas · `537` sem dono · mais antiga `US-ACCO-001` (**87 dias**). O `triage` dizia ≥50 porque **eu** passei `limit:50` — sempre sinalizei o teto, mas carreguei o número menor por vários turnos. Só corrigiu quando rodei o comando contra prod.

## Próximos passos pra retomar

```bash
gh pr view 4862 --json state,mergedAt && ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 'cd domains/oimpresso.com/public_html; php artisan schedule:list | grep tasks:unassigned'
```

⚠️ **Deploy ainda não fechou** quando fechei este handoff: backlog de runner do GitHub (99 queued / 1 in_progress numa janela de 100; GitHub Actions sem incidente, repo público então não é limite de gasto). Merged ≠ live. A 1ª flag só aparece na geração do brief **posterior ao deploy**.

## Lições catalogadas

1. **Procurar a máquina parada antes de propor máquina nova** — o pedido cheirava a construir; a resposta era um `git grep` contado.
2. **Output truncado de tool é recorte, não medida** (`triage limit:50` → carreguei "≥50" por turnos; o real era 537).
3. **Conflito em derivado se resolve pelo gerador** — `SUPERFICIE.md` conflitou 3×; as 3 vezes re-rodei `module-surface.mjs Jana --write`, nunca escolhi lado.
4. **Gate vermelho pode acusar o que o nome não diz** — o `baseline-tamper-guard` viu "afrouxamento" que era **staleness** (o main encolheu o baseline no #4879 e eu carregava a versão anterior). Não usei o trailer `BASELINE-GROW` que ele oferecia: registraria uma intenção que não era a minha.
5. **`block-destructive` mordeu certo** — barrou `--force-with-lease` numa branch já publicada; refiz como merge.

## Pointers detalhados

[Session log](../sessions/2026-07-27-sentinela-us-sem-dono-ligada.md) (narrativa + a dúvida honesta sobre a flag de 680 virar paisagem) · [handoff irmão 14:45](2026-07-27-1445-orfaos-ligados-elo-hitl.md) (13 órfãos ligados no mesmo dia — este é o 14º, em população diferente).
