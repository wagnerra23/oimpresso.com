---
date: "2026-10-14"
time: "1006 BRT"
slug: "contratos-thread01-e-subida-cowork"
tldr: "As threads 01 de telas-soltas e de modulos-faltantes já estavam entregues; faltava só o índice do Cowork apontar para os contratos certos (errata enviada, #9353). Os 14 recibos pendentes subiram ao projeto w e foram conferidos 14/14 (#9361). Placar só fecha as duas threads quando o Cowork corrigir os índices."
decided_by: [W]
cycle: null
prs: [9353, 9361]
us: []
next_steps:
  - "Esperar o Cowork corrigir as provas da thread 01 nos dois 00-INDICE.md e o próximo export trazer a versão nova; aí o placar mostra Telas soltas 7/7 e ModulosFaltantes 4/4"
  - "Não criar home.contract.json nem suporte.contract.json — seriam segundo contrato das mesmas telas"
related_adrs: ["0130-handoff-append-only-mcp-first", "0389-emenda-0374-escrita-do-espelho-quando-o-get-file-volta-inline", "0412-retorno-canon-mergeado-ao-cowork-dispensa-opt-in-emenda-0315"]
---

# Handoff 2026-10-14 10:06 BRT — contratos da thread 01 e subida ao Cowork

## TL;DR

O pedido era escrever os contratos que faltavam (`home`, `suporte`). Medido: não faltava nenhum. `Home/Index` já é
coberta por `dashboard-visao-geral.contract.json` e o Suporte por `suporte-log` + `suporte-empresas` + `suporte-visao`.
Quem está errado é a prova nos dois `00-INDICE.md` do Cowork; a errata foi pedida. Depois subi e conferi os 14 recibos
pendentes do projeto w.

## Cronologia desta sessão

| Quando (UTC) | Evento |
|---|---|
| ~10:30 | Varredura: 6 de 7 contratos de telas-soltas e os 3 de modulos-faltantes já em main (#8934, #9259, #9269, #9282, #9283); gate `contrato-de-tela --contract` rc=0 em todos |
| ~10:50 | `_ERRATA-INDICE-2026-10-14.md` nos dois playbooks, troca medida no placar numa cópia revertida → #9353 |
| 11:24 | #9353 mergeado (auto-merge); erratas sobem ao projeto w |
| ~11:40 | 7 recibos do CRM (`_saida-11..17`) sobem; depois Financeiro `_saida-13`, Fiscal `_saida-20`, Restaurante `_saida-03..05` |
| ~11:50 | `get_file` dos 14 + `pendentes-cowork --conferir` → 14/14 IGUAL → #9361 |
| ~12:40 | conflito no ledger com o #9352 (WooCommerce): mantidas as duas rodadas, `--unverified --check` limpo |
| 12:59 | #9361 mergeado por [W] |

## Estado atual dos artefatos

### Entregue nesta sessão

| Arquivo | Status | Notas |
|---|---|---|
| `prototipo-ui/cowork/Wagner/cowork-inbox/telas-soltas/playbook/_ERRATA-INDICE-2026-10-14.md` | ✅ main + projeto w | pede trocar a prova `home.contract.json` → `dashboard-visao-geral.contract.json` e a linha §1 "Visão geral" |
| `prototipo-ui/cowork/Wagner/cowork-inbox/modulos-faltantes/playbook/_ERRATA-INDICE-2026-10-14.md` | ✅ main + projeto w | pede trocar `suporte.contract.json` → `suporte-log.contract.json` |
| `scripts/design-sync/state/enviados-cowork.json` · `scripts/governance/.cowork-freshness-ledger.json` | ✅ main | 14 arquivos registrados como enviados e verificados (projeto w) |

### PRs

| PR | Status | Conteúdo |
|---|---|---|
| #9353 | merged | erratas dos índices telas-soltas e modulos-faltantes |
| #9361 | merged | conferir dos 14 recibos — 14/14 IGUAL |

## Decisões tomadas

| Pergunta | Decisão | Justificativa | Referência |
|---|---|---|---|
| Criar `home.contract.json` e `suporte.contract.json`? | Não | seriam segundo contrato das mesmas telas; já registrado nos `_saida-01*` das duas threads | `_saida-01b.md` das duas threads |
| Subir todos os pendentes do projeto w, não só os desta sessão | Sim ([W] pediu) | canal `cowork-inbox/`, blob igual a main, isento de opt-in | ADR 0412 |

## Bloqueios / pendências

- [ ] Cowork corrigir as provas da thread 01 nos dois `00-INDICE.md` — owner: Cowork/[W]. Placar hoje: Telas soltas 6/7, ModulosFaltantes 3/4; com a troca medida, 7/7 e 4/4.
- [ ] Projeto `copia` (Maiara): os mesmos 14 seguem pendentes lá; sobe da sessão dela.

## Próximos passos (ordem)

1. Quando o próximo export do Cowork chegar, rodar `node scripts/qa/placar.mjs --indice` e conferir as duas threads 01 como `feito`.
2. Se o Cowork não corrigir, a errata explica o que trocar; não escrever os contratos com nome antigo.

## Estado MCP no momento do fechamento

### cycles-active
```
Nenhum cycle ATIVO em COPI.
```

### my-work
```
Sem tasks ativas pra @wr23.
```

### sessions-recent limit:3
```
session-2026-08-22-arte-agentes-ia-ui-guardrails · session-2026-08-22-arte-escala-centenas-de-telas · session-2026-08-22-arte-fidelidade-prototipo-producao (indexed 2026-10-14)
```

### decisions-search "contrato de tela espelho cowork"
```
0324-frescor-espelho-cowork-dispatch-sla-limite-plataforma · 0405-espelhos-cowork-independentes-por-conta · 0255-contrato-view-deterministico-charter-design-spec — nenhuma nova desde o handoff anterior
```

### whats-active
```
N/A — não consultado; o dup-detector acusou o #9352 (WooCommerce) no ledger compartilhado, resolvido por merge da base.
```

## Referências

- Handoff anterior: [2026-10-14-0300-gestao-fila-merges-13-out-3.md](2026-10-14-0300-gestao-fila-merges-13-out-3.md)
- ADR 0130: [Handoff append-only + MCP-first](../decisions/0130-handoff-append-only-mcp-first.md)
