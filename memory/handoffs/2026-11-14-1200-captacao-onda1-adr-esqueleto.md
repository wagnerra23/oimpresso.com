---
date: "2026-11-14"
time: "1200 BRT"
slug: "captacao-onda1-adr-esqueleto"
tldr: "Modules/Captacao nasceu: ADR 0452 (proposto) + esqueleto + 4 tabelas captacao_* + teste de isolamento 98×99, tudo no PR #10895 por decisão de [W]. Merge do PR ratifica a ADR. Próximo: onda 2 (fontes e projetos)."
decided_by: [W]
cycle: null
prs: [10895]
us: ["US-CAPT-001"]
next_steps:
  - "Conferir a lane verticais-pest do #10895 com assertions > 0 no CaptacaoEsquemaContratoTest antes do merge"
  - "[W] mergeia o #10895 (= ratifica a ADR 0452) e liga o pacote captacao_module para a empresa da Uniafibro"
  - "Onda 2: CRUD de fontes e projetos (US-CAPT-002), tela PT-01 via criar-tela.mjs"
  - "Primeiro caso real: edital municipal de Capivari de Baixo (SC), confirmado por [W]; pedir número, prazo e proponente"
related_adrs: ["0452-captacao-projetos-de-captacao-de-recursos", "0451-associacoes-rede-uniafibro-area-de-membros", "0093-multi-tenant-isolation-tier-0"]
---

# Handoff 2026-11-14 12:00 BRT — Captação: ADR 0452 e esqueleto do módulo

## TL;DR

`Modules/Captacao` existe no PR #10895, que traz a ADR 0452 (`proposto`), o esqueleto, as 4 tabelas `captacao_*` e o teste de isolamento. O merge do PR ratifica a ADR. Nenhuma tela ainda.

## Cronologia desta sessão

| Quando | Evento |
|---|---|
| início | Busca prévia: nenhuma ADR, task, commit ou tabela de captação no repo; 0452 livre (0451 só no PR #10862) |
| plano | [W] aprovou: tudo num PR, proponente como nome+CNPJ no projeto, voluntariado só como aporte |
| execução | ADR + 25 arquivos; lint PHP, schema, anchor-lint, module-surface, catalog e test-lane verdes local |
| fim | PR #10895 aberto, aguardando a lane verticais-pest |

## Estado atual dos artefatos

### Entregue nesta sessão

| Arquivo | Status | Notas |
|---|---|---|
| `memory/decisions/0452-captacao-projetos-de-captacao-de-recursos.md` | ✅ pronto | D1–D8, fora, 5 ondas |
| `Modules/Captacao/**` | ✅ pronto | modelado no esqueleto das Associações (#10878) |
| `Modules/Captacao/Tests/Feature/CaptacaoEsquemaContratoTest.php` | 🟡 aguarda CI | lane verticais-pest (MySQL) |
| `memory/requisitos/Captacao/{SCOPE,SPEC,BRIEFING,SUPERFICIE}.md` | ✅ pronto | US-CAPT-001 a 005 |

### PRs

| PR | Status | Conteúdo |
|---|---|---|
| #10895 | aberto | ADR 0452 + esqueleto + camada de dados |

## Decisões tomadas

| Pergunta | Decisão Wagner | Justificativa | Referência |
|---|---|---|---|
| ADR e esqueleto em PRs separados? | Tudo num PR só | escolha de [W] | ADR 0452 §Ondas |
| Quem é o proponente? | Nome e CNPJ no projeto, sem FK | Uniafibro sem CNPJ; Captação não depende de Associações | ADR 0452 D3 |
| Voluntário tem cadastro? | Não, só aporte de horas | LGPD só se paga quando houver termo de adesão | ADR 0452 D8 |

## Bloqueios / pendências

- [ ] Merge do #10895 (ratifica a ADR 0452) — owner: W
- [ ] Ligar o pacote `captacao_module` para a empresa da Uniafibro no superadmin — owner: W
- [ ] O deploy cria 4 tabelas vazias em produção no merge (`migrate --force`). Só acrescenta, sem risco a dado existente.

## Próximos passos (ordem)

1. Ver a lane verticais-pest do #10895 com `CaptacaoEsquemaContratoTest` executado (assertions > 0).
2. Depois do merge, smoke: `curl -sv https://oimpresso.com/captacao/install` deve dar `302` sem sessão.
3. Onda 2 (US-CAPT-002): fontes e projetos, com charter e casos.md.
4. Primeiro caso real, confirmado por [W] em 2026-11-14: **edital municipal de Capivari de Baixo (SC)**. Pedir nome, número, prazo e associação proponente para cadastrar como a primeira fonte.

## Estado MCP no momento do fechamento

- `brief-fetch`: brief #864; nada de Captação no estado.
- `cycles-active`: nenhum cycle ativo em COPI.
- `tasks-list module:Captacao`: nenhuma task.
- `sessions-recent limit:3`: três logs de estado da arte de design (2026-08-22), sem relação com Captação.
- `decisions-search "captação captacao edital projeto fundraising doação"`: nenhuma ADR sobre o tema.
- `whats-active hours:6`: 10 sessões; nenhuma tocando `Modules/Captacao`. As sessões das Associações (ondas 3 a 6) estão em `Modules/Associacoes`.
