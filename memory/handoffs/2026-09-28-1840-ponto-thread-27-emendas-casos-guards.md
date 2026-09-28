---
date: "2026-09-28"
time: "1840 BRT"
slug: "ponto-thread-27-emendas-casos-guards"
tldr: "Thread 27 do Ponto em 6 PRs. As 8 emendas de charter e os 2 casos de BACKLOG estão no main. Os 2 guards (#8094) e a citação do guard no charter (#8104) estão dentro do #8093, que segue aberto com conflito só no _STATUS-GENERATED.md. O merge do #8093 é da gerente (sessão \"Oimpresso ERP visual communication import\")."
decided_by: [W]
cycle: null
prs: [8087, 8093, 8094, 8095, 8097, 8104]
us: []
next_steps:
  - "Merge do #8093 no main (dono: gerente): merge do main + requisitos-status.mjs Ponto --write para o conflito do _STATUS-GENERATED.md; ele leva junto o #8094 (guards) e o #8104 (citação no charter)"
  - "Depois do merge, conferir no main que o casos-gate não acusa uc-orphan do UC-INTIDX-04 e que o loop-fechar-check.test.mjs passa"
  - "Construir as capacidades que ficaram em [BACKLOG]: filtro Sem PIS (Colaboradores/Index), amostra de erros (Importacoes/Show), anexo de comprovante (Intercorrencias/Create); cada uma vira UC no PR que a construir"
  - "D-CFG-IA-CAMINHO: construir o caminho de pacote/permissão para as flags de IA (hoje env global); até lá o bloco IA do Ponto fica só leitura"
related_adrs: ["0358-doutrina-de-teste-tenant-98-supersede-0101"]
---

# Handoff 2026-09-28 18:40 BRT — Ponto, thread 27: emendas de charter, casos e guards

## TL;DR

A thread 27 do playbook do Ponto (`27-emendas-e-guards.md`) virou 6 PRs. Quatro estão no `main`. Os outros dois (#8094 guards, #8104 citação no charter) já foram mergeados dentro do branch do #8093, que ainda está aberto. O #8093 tem conflito só no arquivo derivado `memory/requisitos/Ponto/_STATUS-GENERATED.md`, e o merge dele é da sessão gerente. Nenhum `.tsx` foi tocado; os `data-contract` são do chip irmão Ponto/17.

## Cronologia desta sessão

| Quando (BRT) | Evento |
|---|---|
| ~17:00 | Leitura da thread 27, da ata 2026-09-14 (bloco 4 + R1) e dos charters no `main`. #8072 mergeado; #8073 e #8078 abertos |
| ~17:35 | [#8087](https://github.com/wagnerra23/oimpresso.com/pull/8087) aberto: emendas E3, E3-bis, E4, E6, E7, E8 |
| ~17:50 | Guards rodados e mutados no CT 100 (tenant 98, cópias em `/tmp`, checkout de lá intocado) |
| ~17:55 | [#8093](https://github.com/wagnerra23/oimpresso.com/pull/8093) (casos) e [#8094](https://github.com/wagnerra23/oimpresso.com/pull/8094) (guards, empilhado) abertos |
| ~18:00 | #8073 e #8078 mergeados; [#8095](https://github.com/wagnerra23/oimpresso.com/pull/8095) (E1, E2, E5) e [#8097](https://github.com/wagnerra23/oimpresso.com/pull/8097) (casos) abertos |
| ~18:10 | Ponto/17 corrige: os ids citados são de parte do map, não strings de `data-contract`. Corpos do #8087 e do #8095 atualizados |
| ~18:30 | [#8104](https://github.com/wagnerra23/oimpresso.com/pull/8104): charter de Intercorrências/Index cita o `UC-INTIDX-04`, empilhado no #8093 |
| 18:37 | Gerente mergeia o #8104 dentro do #8093; o `main` avança e o #8093 volta a ter conflito no `_STATUS-GENERATED.md` |

## Estado atual dos artefatos

### Entregue nesta sessão

| Arquivo | Status | Notas |
|---|---|---|
| `Pages/Ponto/BancoHoras/{Index,Show}.charter.md` | ✅ main (#8087) | E3/E3-bis. KPIs "a construir"; pendência de expiração estava no Index, não no Show |
| `Pages/Ponto/Intercorrencias/Create.charter.md` | ✅ main (#8087) | E4: Goal do anexo + Non-Goal de PII literal (LGPD Art. 11) |
| `Pages/Ponto/Intercorrencias/Index.charter.md` | ✅ main (#8087) · 🟡 citação no #8093 (#8104) | E7: atribuição `D-INTERC-ACOES` |
| `Pages/Ponto/Escalas/Index.charter.md` | ✅ main (#8087) | E8: já era catch-up; fechou só a pendência + diálogo do DS |
| `Pages/Ponto/Espelho/Show.charter.md` | ✅ main (#8087) | E6: exceção de tinta de papel; o vivo já tem `data-contract="espelho-folha-impressao"` |
| `Pages/Ponto/{Colaboradores/Index,Importacoes/Show,Configuracoes/Index}.charter.md` | ✅ main (#8095) | E1, E2, E5 + `D-CFG-IA-CAMINHO` medido |
| `Pages/Ponto/{Colaboradores/Index,Importacoes/Show}.casos.md` | ✅ main (#8097) | `[BACKLOG]` Sem PIS e amostra de erros; `last_run` do Colaboradores revalidado |
| `Pages/Ponto/Intercorrencias/{Index,Create}.casos.md` | 🟡 #8093 aberto | `UC-INTIDX-04` + `[BACKLOG]` do anexo; `last_run` do Create revalidado |
| `Modules/Ponto/Tests/Feature/IntercorrenciaContratoTest.php` | 🟡 #8093 aberto (via #8094) | guard `UC-INTIDX-04`, lê o fonte da `Index.tsx` |
| `Modules/Ponto/Tests/Feature/EscalaRemocaoContratoTest.php` | 🟡 #8093 aberto (via #8094) | 2 casos HTTP do `UC-ESCIDX-04`, tenant 98, transação revertida |

### PRs

| PR | Status | Conteúdo |
|---|---|---|
| #8087 | merged | charters BH, Intercorrências, Escalas, Espelho |
| #8093 | **aberto, DIRTY** (só `_STATUS-GENERATED.md`) | casos Intercorrências + os dois de baixo |
| #8094 | merged no branch do #8093 | os 2 Pest GUARDs |
| #8095 | merged | charters Colaboradores, Importações, Configurações |
| #8097 | merged | casos Colaboradores e Importações (BACKLOG) |
| #8104 | merged no branch do #8093 | charter de Intercorrências/Index cita o guard |

## Decisões tomadas

Nenhuma decisão nova de [W] nesta sessão. Tudo veio da `ATA-DECISOES-2026-09-14.md`, com a linha citada em cada emenda. As divergências abaixo foram declaradas nos PRs, não resolvidas caladas:

| Pergunta | Decisão | Justificativa | Referência |
|---|---|---|---|
| E8: guard afirma 403? | Não. Afirma o redirect com flash `error` e a escala intacta | É o que o `EscalaController@destroy` faz e o `UC-ESCIDX-04` defende; 403 seria decisão nova | #8094 |
| E7: guard via prop `acoes_da_linha`? | Não. Lê o fonte da `Index.tsx` | A prop não existe; o payload não carrega ação, então um teste HTTP ficaria verde com os botões de volta | #8094 |
| UCs de E1, E2, E4 viram UC? | Não, `[BACKLOG]` com aceite e próximo id | As capacidades não existem no vivo; um teste seria vermelho por construção | #8093, #8097 |
| Ids dos UCs | Convenção do arquivo (`UC-COLIDX-04`, `UC-IMPSH-06`, `UC-INTCRE-04`), citando o id da thread | A thread usava `UC-PONT-*` | #8093, #8097 |

## Bloqueios / pendências

- [ ] #8093 com conflito no `_STATUS-GENERATED.md` depois do #8101 — owner: gerente (pediu que ninguém mexa no branch)
- [ ] Anexo de comprovante, filtro Sem PIS e amostra de erros ainda não existem no vivo — owner: W (priorizar)
- [ ] `D-CFG-IA-CAMINHO`: medido em 2026-09-28, o caminho não existe (flags `AI_*` por env, globais; o `DataController` só declara `ponto_module` e 7 permissões `ponto.*`, nenhuma de IA) — owner: W
- [ ] Regiões sem âncora estável (`faixa-de-kpi`, `kpis-do-extrato`, `anexo-de-comprovante`, `acao-por-linha`, `remover-escala`, `barra-de-busca-e-filtros`): o protótipo não tem `contrato=` nelas; âncora exige id novo nos dois lados, nascido no Cowork — owner: CC

## Próximos passos (ordem)

1. Gerente: merge do `main` no #8093 + `node scripts/governance/requisitos-status.mjs Ponto --write`, depois merge no `main`.
2. Conferir no `main`: `node scripts/casos-coverage-guard.mjs` sem `uc-orphan` e `node .claude/hooks/loop-fechar-check.test.mjs` verde.
3. Quem construir anexo, filtro Sem PIS ou amostra de erros: promover o `[BACKLOG]` correspondente a UC no mesmo PR, com o teste.

## Notas para quem retomar

- **Guards provados no CT 100.** Base do `EscalaRemocaoContratoTest`: 4/4 verdes, 14 assertions, 0 skipped. Mutações: link "Editar" e `router.post` na lista (vermelho no assert certo); `destroy` sem trava e recusando sempre (cada um derruba o caso oposto). Restauração conferida por `sha1sum`; 0 escalas residuais. O checkout do CT 100 era `main@e57b78bf5` (2026-09-21), com 14 arquivos sujos de outra sessão, e não foi alterado: os testes rodaram a partir de cópias em `/tmp/t27/`.
- **Falha que não é desta thread:** no CT 100, `UC-INTCRE-03` falha porque o `Create.tsx` de lá é anterior ao #8076.
- **As 2 advisory do #8093** (`governance script tests`, `hooks selftest`) falham pela mesma causa do casos-gate: a medição E1 do `loop-fechar-check` roda o casos-guard, que sai 1 com o `uc-orphan`. No branch do #8094 as duas passam.
- **Sandbox:** o Bash desta sessão nega escrita no diretório git da worktree; `git add/commit/push` só funcionaram fora do sandbox.
- **Um erro meu, pego antes do commit:** escrevi que o Espelho vivo não tinha folha de impressão e que o `log` da importação não era exposto. Os dois estavam errados; conferi o `.tsx` e o controller e corrigi antes de publicar.

## Estado MCP no momento do fechamento

### cycles-active
```
Nenhum cycle ATIVO em COPI. Use `cycles-list project:COPI` para ver todos.
```

### my-work
```
✨ Sem tasks ativas pra @wr23. Use `tasks-list owner:wr23 status:done` pra ver fechadas.
```

### sessions-recent limit:3
```
- session-2026-08-22-arte-agentes-ia-ui-guardrails (indexed 2026-09-28)
- session-2026-08-22-arte-escala-centenas-de-telas (indexed 2026-09-28)
- session-2026-08-22-arte-fidelidade-prototipo-producao (indexed 2026-09-28)
Nenhuma toca a thread 27.
```

### decisions-search "ponto"
```
0293-governanca-decisao-design-responsavel-registro-veredito
adr-jana-arq-0003-administracao-roi-governance
0392-fronteira-governance-audiencia-enforcement-na-concessao
Nenhuma ADR nova sobre as emendas; a fonte é a ata 2026-09-14.
```

### whats-active (sessões paralelas)
```
15 sessões locais ativas no início. Relevantes: Ponto/17 (data-contract nos .tsx; PRs #8088, #8090,
#8091, #8096) e a gerente "Oimpresso ERP visual communication import" (dona dos merges).
#8079 e #8077 tocam .casos.md de Aprovações, Escalas e BancoHoras/Show; nenhum PR desta thread os toca.
```

## Referências

- Thread: `prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/27-emendas-e-guards.md`
- Ata: `prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/ATA-DECISOES-2026-09-14.md` (bloco 2, bloco 4, R1)
- Handoff anterior: [2026-09-28-2041-doc-id-index-dono-do-frescor.md](2026-09-28-2041-doc-id-index-dono-do-frescor.md)
- ADR 0130: [Handoff append-only + MCP-first](../decisions/0130-handoff-append-only-mcp-first.md)
- ADR 0358: [Doutrina de teste — tenant 98](../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
