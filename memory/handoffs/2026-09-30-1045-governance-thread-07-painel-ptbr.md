---
date: "2026-09-30"
time: "10:45 BRT"
slug: governance-thread-07-painel-ptbr
tldr: "Governança thread 07: itens 2-4 medidos como inexecutáveis como escritos (contrato dá 92 falhas; charter/casos do prefixo sem tela no main); #7138 refeito; Painel sem texto de tela em inglês; header migrado pro canon; acordos de estado aprovados [W]; 2 chips abertos."
prs: [8203, 8222, 8233, 8236, 8243, 8250]
decided_by: [W]
related_adrs: [0409-zero-baseline-de-tolerancia-conformidade-absoluta, 0399-aposentar-rubrica-module-grade-gate-e-baseline]
next_steps:
  - "Rodar o chip 'Ancorar as 4 telas de Governança ao contrato' (task_d5bb6a83), começando por Policies"
  - "Cowork: criar entrada no 00-INDICE da governança pra thread de ancoragem e ajustar a prova da 07 (contrato por tela, não governance.contract.json único)"
  - "Cowork: levar verdict aprovado dos 5 acordos + tirar dimensao-nao-avaliada do governance.contract.json"
---

# Governança thread 07 · Painel em PT-BR

## Estado MCP no momento
- `cycles-active`: nenhum cycle ativo em COPI.
- `my-work`: sem tasks ativas pra @wr23.
- Chip `task_780f7be2` (regerar baseline multi-tenant) foi iniciado por [W] e terminou em sessão separada — resultado não verificado aqui.

## O que aconteceu
1. `/onda governance --thread 07`. Medido: o contrato do espelho sem a vista Notas dá **92 falhas** no `contrato-de-tela.mjs` (0 âncoras `data-contract` nas 7 telas; metade da copy fora de produção; verdicts inválidos). `Pages/governance/Index.*` não tem `.tsx`; os charters vivos já não citam a vista. Nada disso foi criado. Recibo: `_saida-07.md` (§A–§E).
2. #7138 refeito no #8203: sub-nav Políticas/Auditoria/Drift. Depois #8233 ("Gerenciar políticas"), #8236 (links do Painel + migração obrigatória pro `PageHeader` canon pela ADR 0409 + drift do `SUPERFICIE.md` do Ponto que travava o required em todo PR), #8243 (ícone do header quebrava linha — visto na imagem do `visual-regression`), #8250 (rótulos/descrições dos KPIs e abas MCP do contrato do protótipo, conferidas contra as consultas do controller).
3. [W] aprovou os acordos de estado: 5 aprovados, `dimensao-nao-avaliada` sai com a vista (#8222, §E do `_saida-07`). Medido: só `ok`/`error` existem nos dois lados.
4. 4 `_saida` subidos ao Cowork depois do `/design-login` (`written: 4`); registro duplicado com outra sessão, #8230 fechado.

## Artefatos gerados
- `Modules/Governance/Http/Controllers/DataController.php` (rótulos sub-nav + primary)
- `resources/js/Pages/governance/Dashboard.tsx` + `.charter.md` (header canon, textos PT-BR)
- `tests/Feature/Design/CockpitPatternConformanceTest.php` (aceita PageHeader canon) · `e2e/governance-dashboard.spec.ts` (rótulo Conformidade)
- `config/pageheader-shared-baseline.json` 63→62 · `memory/requisitos/Ponto/SUPERFICIE.md` regenerado
- `prototipo-ui/cowork/Wagner/cowork-inbox/governance/playbook/_saida-07.md`

## Persistência
- git: 6 PRs mergeados (lista no frontmatter). Smoke prod com screenshot após cada deploy (último `0e872ca78`).
- Cowork: `_saida-07` + 3 recibos de outras threads no projeto w.
- BRIEFING Governança não tocado (só copy; sem capacidade nova).

## Próximos passos pra retomar
Clicar no chip `task_d5bb6a83` (ancoragem, Policies primeiro). O prompt dele já carrega a decisão dos acordos.

## Lições catalogadas
- `visual-regression` advisory: abrir a imagem de diferença pegou um bug real (ícone quebrando linha) que o "é a mudança intencional" teria escondido. Não se regenera baseline no PR (ADR 0409).
- Sonda de texto em página com `text-transform: uppercase`: `innerText` devolve maiúsculas; comparar sem caixa.
- O vigia de crons segue vermelho em todo PR por `multi-tenant-scope-baseline.json` com 61d, até o chip do baseline mergear.

## Pointers detalhados
- `_saida-07.md` §B (medições) e §C (erratas do índice) · PR bodies do #8236 e #8250.
