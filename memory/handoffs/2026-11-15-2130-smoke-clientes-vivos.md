---
date: "2026-11-15"
time: "2130 BRT"
slug: "smoke-clientes-vivos"
tldr: "Smoke dos clientes vivos em 4 PRs (#10925 job pós-deploy, #10926 sinal diário, #10927 label, docs). Só vira verde depois que o [W] reprovisionar a conta biz=99 do smoke, cujo login falha em produção."
decided_by: [W]
cycle: null
prs: [10925, 10926, 10927]
us: []
next_steps:
  - "[W] reprovisionar a conta do smoke: screen-smoke:provision-tenant (dry-run, depois --force) + gh secret set SMOKE_PROD_USER/PASS"
  - "Depois do login, rodar gh workflow run screen-smoke-after-merge.yml -f rota=__CLIENTES__ e ver se a biz=99 abre o Ponto"
  - "Contar 2 semanas sem falso positivo antes de propor bloqueio (ADR 0336)"
related_adrs:
  - "0130-handoff-append-only-mcp-first"
  - "0164-screen-review-pdca-tela-smoke-pos-merge"
  - "0358-doutrina-de-teste-tenant-98-supersede-0101"
---

# Handoff 2026-11-15 21:30 BRT — Smoke dos clientes vivos

## TL;DR

O smoke da ROTA LIVRE e da WR2 está montado em 4 PRs abertos. Ele ainda não mede tela nenhuma
porque o login da conta fictícia biz=99 falha em produção; por isso sai vermelho com
`NAO-EXERCIDA`, que é o comportamento certo até o [W] trocar a credencial.

## Cronologia desta sessão

| Quando | Evento |
|---|---|
| 14/11 | Inventário: smoke pós-deploy já existe; 6/6 execuções vermelhas por `NÃO MEDIDO`; plano aprovado pelo [W] |
| 15/11 | #10925 job `smoke-clientes`; dispatch no branch pega bug do health gate e o login quebrado |
| 15/11 | [W]: biz=1 é a WR2 real. Medição: zero marcações do biz=1 em 365 dias |
| 15/11 | #10926 sinal diário (janela de 5 dias medida), #10927 label, PR de docs |

## Estado atual dos artefatos

### PRs

| PR | Status | Conteúdo |
|---|---|---|
| #10925 | aberto | job `smoke-clientes` (14 rotas, só leitura por máquina, ALERT por cliente) |
| #10926 | aberto | `sells:smoke-daily` ROTA LIVRE 5 dias; WR2 informativo; teste no tenant 98 |
| #10927 | aberto, empilhado sobre #10925 | label `affects/<cliente>` |
| docs | aberto | `memory/clientes/{wr2-sistemas,rota-livre}/SMOKE.md` + correção datada do `clientes-ativos.md` |

## Decisões tomadas

| Pergunta | Decisão Wagner | Justificativa | Referência |
|---|---|---|---|
| Workflow novo ou estender? | estender `screen-smoke-after-merge` + `sells:smoke-daily` | dono já existia | este handoff |
| Cruzar PRs | label, sem comentário | comentário polui | #10927 |
| Alerta | `ALERT` no log + job vermelho, sem canal externo | aguarda sessão de monitoramento | #10925 |
| biz=1 | conta real da WR2 em produção | [W] 2026-11-15 | `clientes-ativos.md` |

## Bloqueios / pendências

- [ ] Credencial `SMOKE_PROD_USER`/`SMOKE_PROD_PASS` inválida em produção — owner: W
- [ ] Conta biz=99 tem acesso ao Ponto? Não medível até o login voltar — owner: W
- [ ] Smoke que grava em biz=1 (MWART F4, RUNBOOK SEFAZ biz=1) agora toca dado de cliente pagante — owner: W decidir

## Próximos passos (ordem)

1. [W] reprovisiona a conta do smoke.
2. Dispatch `rota=__CLIENTES__` e leitura das 14 telas.
3. Se o Ponto der QUEBRADO por permissão, habilitar o módulo no pacote da biz=99.
4. Duas semanas sem falso positivo → decisão [W] sobre bloqueio (job pós-deploy não barra merge; o desenho possível é travar o próximo deploy).

## Estado MCP no momento do fechamento

### cycles-active
```
Nenhum cycle ATIVO em COPI. Use `cycles-list project:COPI` para ver todos.
```

### my-work
```
✨ Sem tasks ativas pra @wr23.
```

### sessions-recent limit:3
```
- session-2026-08-25-fluxo-design-playbook-detalhado (indexed 2026-11-15)
- session-2026-08-25-produto-unificado-zona-cinza-baseline (indexed 2026-11-15)
- session-2026-08-26-raio-gate-visual-5-suites (indexed 2026-11-15)
```

### decisions-search "smoke produção cliente"
```
0431-pcp-sai-do-desejo-modules-producao · 0448-fabricacao-cadastro-producao-execucao-product-bom-fonte-unica · 0293-governanca-decisao-design-responsavel-registro-veredito (nenhuma sobre smoke de cliente)
```

### whats-active
```
N/A — dup-detector --path rodado antes de cada PR; únicos toques em comum: #10823 (sells-pest.yml, ProductCatalogue) e #10924 (gates-registry.json, k6), temas diferentes
```

## Referências

- Session log: [2026-11-15-smoke-clientes-vivos.md](../sessions/2026-11-15-smoke-clientes-vivos.md)
- Handoff anterior: [2026-11-14-1900-runbook-deploy-producao.md](2026-11-14-1900-runbook-deploy-producao.md)
- ADR 0130: [Handoff append-only + MCP-first](../decisions/0130-handoff-append-only-mcp-first.md)
