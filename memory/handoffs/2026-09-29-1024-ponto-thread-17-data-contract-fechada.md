---
date: "2026-09-29"
time: "1024 BRT"
slug: "ponto-thread-17-data-contract-fechada"
tldr: "Thread 17 do Ponto fechada e no main. 20 de 26 ids de data-contract gravados no vivo, 6 fora por regra. Os 2 ids feios foram renomeados nos dois lados (#8120), com o ponto-telas.jsx subido ao Cowork por opt-in [W] e a refutação GT-G5 aprovada na r2. Smoke em prod no deploy f37c125f35: 5 ids medidos no DOM; os 2 de BancoHoras/Show só no bundle, porque o business não tem colaborador no Ponto. Recibo _saida-17 no main e no Cowork."
decided_by: [W]
cycle: null
prs: [8114, 8119, 8120, 8135, 8146, 8147, 8149, 8155]
us: []
next_steps:
  - "Medir no DOM os 2 ids de BancoHoras/Show (bancohoras-historico-de-movimentos, bancohoras-ajuste-manual) quando houver um business de smoke com colaborador no Ponto; hoje só o bundle servido prova que o código carrega os ids"
  - "Decisão [W]: cobertura de pixel para Ponto/Escalas/Form (não está em tests/Browser/visreg-screens.json; o visual-regression advisory fica vermelho em todo PR que toca a tela). Pela ADR 0411, gerar a baseline é PR próprio com motivo declarado"
  - "Cowork: os 3 ids que o #8113 gravou no BancoHoras/Show sem par no protótipo (bancohoras-colaborador, bancohoras-kpis-do-extrato, bancohoras-legal) só viram âncora quando o protótipo ganhar o par"
  - "Passada de FORMA: o card marcado por escalaform-dados-da-escala e intercorrencias-dados-da-ocorrencia tem extensão diferente nos dois lados (no protótipo embrulha turnos/nota/rodapé; no vivo não). A medição de forma por região vai esbarrar nisso"
  - "Envio ao Cowork pendente de outra sessão: cowork-inbox/ponto/playbook/_DECISOES-W-2026-09-29.md (mergeado, não enviado)"
related_adrs: ["0411-snapshot-de-pixel-fora-do-passo-3-da-0409", "0315-design-sync-claude-design-vs-cowork-charter", "0412-retorno-canon-mergeado-ao-cowork-dispensa-opt-in-emenda-0315", "0418-ponto-listas-servidor-forma-prototipo-e-13-abas"]
---

# Handoff 2026-09-29 10:24 BRT — Ponto, thread 17: `data-contract` no `.tsx`, fechada

## TL;DR

A thread 17 do playbook do Ponto (`17-data-contract-no-tsx.md`) está fechada e no `main`. A sessão original tinha entregue PR-1/3a/3b/4 (#8088, #8090, #8091, #8096); esta sessão fez o resto: PR-2 (Aprovações), PR-2b (âncoras do extrato de banco de horas), PR-5 (os 2 ids feios renomeados nos dois lados), a correção de uma linha velha de gap, o smoke em produção e o recibo. Placar: **20 de 26 ids no vivo, 6 fora por regra** (regiões "a nascer" e o `relatorios-gerar`, que sai do protótipo). Nenhum `.snap` foi gerado.

## Cronologia desta sessão

Horários em BRT, a partir do `mergedAt` do GitHub quando é merge.

| Quando | Evento |
|---|---|
| 2026-09-28 noite | Colisão medida (`whats-active` + PRs abertos). Escalas/Index sai do PR-2: a sessão da forma do protótipo grava `escalas-escalas-cadastradas` no #8115 dela, por combinação entre sessões. BancoHoras/Show espera o #8113 |
| 2026-09-28 noite | [#8114](https://github.com/wagnerra23/oimpresso.com/pull/8114) aberto: `aprovacoes-fila-de-aprovacoes` + âncora no map |
| 2026-09-29 manhã | [#8119](https://github.com/wagnerra23/oimpresso.com/pull/8119) (PR-2b): o #8113 já tinha gravado os 2 ids do extrato; o PR só declara as âncoras no map |
| 2026-09-29 manhã | [#8120](https://github.com/wagnerra23/oimpresso.com/pull/8120) (PR-5) aberto em rascunho; opt-in [W] ("sobe o ponto-telas.jsx pro design sync"); espelho subido ao Cowork e re-verificado no ledger |
| 07:31 | #8114 mergeado |
| 08:01 | #8119 mergeado |
| ~08:10 | Refutação GT-G5 do #8120: r1 reprovada (15/281, 5,34%, `gerado_em` velho nos 15 maps); corrigido; r2 aprovada (2/118, 1,69%) |
| 08:51 | #8120 e [#8135](https://github.com/wagnerra23/oimpresso.com/pull/8135) (linha 29 do gap de nova intercorrência) mergeados |
| ~09:25 | Deploy `f37c125f35` concluído com sucesso (os de `e1b5dd63ad`, `06a4e21091` e `971c2fd789` foram cancelados por pushes mais novos); smoke em prod |
| 09:39 | [#8146](https://github.com/wagnerra23/oimpresso.com/pull/8146) (recibo do smoke) mergeado e subido ao Cowork |
| 09:53 · 09:54 | [#8149](https://github.com/wagnerra23/oimpresso.com/pull/8149) (tabela de PRs do recibo) e [#8147](https://github.com/wagnerra23/oimpresso.com/pull/8147) (registro de envio) mergeados |
| 10:18 | [#8155](https://github.com/wagnerra23/oimpresso.com/pull/8155) (registro do envio do recibo atualizado) mergeado |

## Estado atual dos artefatos

### Entregue nesta sessão

| PR | O que entrou |
|---|---|
| #8114 | `data-contract="aprovacoes-fila-de-aprovacoes"` no card da fila (`Aprovacoes/Index.tsx:330`) + âncora no `aprovacoes-index.map.json` |
| #8119 | âncoras `bancohoras-historico-de-movimentos` e `bancohoras-ajuste-manual` no `banco-horas-show.map.json` (os ids já estavam no `.tsx` pelo #8113) |
| #8120 | `intercorrencias-card` → `intercorrencias-dados-da-ocorrencia` e `escalaform-card` → `escalaform-dados-da-escala` no `ponto-telas.jsx` e nos `.tsx` (`Create.tsx:241`, `Form.tsx:89`); região nomeada nos 2 gaps; `prototipo_sha` + `gerado_em` novos nos 15 maps do Ponto; ledger do espelho e registro de envio; evidências r1/r2 + entry no `governance/sdd-verification-ledger.json` |
| #8135 | linha "Nota de rascunho e append-only" do `intercorrencias-create-gap.md` passa a paridade (o #8076 já tinha trocado a copy); parte correspondente do map acompanha |
| #8146, #8149 | `_saida-17` com o smoke em prod e a tabela de PRs atualizada |
| #8147, #8155 | registros de envio do `_saida-17` em `scripts/design-sync/state/enviados-cowork.json` |

### Smoke em produção (deploy `f37c125f35`, business WR2 Sistemas)

| tela | id | resultado |
|---|---|---|
| `/ponto/aprovacoes` | `aprovacoes-fila-de-aprovacoes` | no DOM, card da fila |
| `/ponto/intercorrencias/create` | `intercorrencias-dados-da-ocorrencia` | no DOM, card "Dados da ocorrência" |
| `/ponto/escalas/create` | `escalaform-dados-da-escala` | no DOM, card "Dados da escala" |
| `/ponto/escalas` | `escalas-escalas-cadastradas` | no DOM |
| `/ponto/banco-horas` | `bancohoras-saldos-por-colaborador` | no DOM |
| BancoHoras/Show | 2 ids | **só no bundle** (`Show-WkseeyWP.js`); o business não tem colaborador no Ponto |

Cada tela lida duas vezes com 3 s de intervalo, leituras iguais. Método do bundle validado com controle positivo (1) e negativo (0).

## Decisões tomadas

- **Nomes dos ids novos:** padrão `<tela>-<título do card>`, com o título fixo do vivo, porque no protótipo o título é dinâmico. Decisão de técnica, registrada nos gaps.
- **Não gerar `.snap`** para `Ponto/Escalas/Form` (ADR 0411): o `visual-regression` advisory fica vermelho no PR, sem bloquear o merge (não está entre os contexts required).
- **`configuracoes-index.map.json` só com a data trocada** no #8120: o `gerar-map.mjs --atualizar` reverteria os 4 `_acionavel: false` que o #8096 re-mediu.
- **Recibo `_saida-17` sobe ao Cowork só depois do merge**: a isenção de opt-in (ADR 0412) exige arquivo igual ao `origin/main`.

## Bloqueios / pendências

Os cinco itens de `next_steps` no frontmatter. Nenhum bloqueia a thread; são continuação fora dela.

## Próximos passos (ordem)

1. Medir no DOM os 2 ids de BancoHoras/Show quando existir colaborador no business de smoke.
2. Decisão [W] sobre a baseline de pixel de `Ponto/Escalas/Form`.
3. Pares no protótipo para os 3 ids extras do BancoHoras/Show (Cowork).

## Notas para quem retomar

- **Sonda de DOM com filtro por ancestral de sidebar é cega nesta app:** o layout inteiro fica dentro de um ancestral que casa com esse filtro, e a 1ª versão deu 0 em Aprovações com o id presente. Filtre pelos ids `sb-*`, não por ancestral.
- **`gerado_em` de map acompanha o `prototipo_sha`:** trocar só o hash deixa a data falsa, e o refutador GT-G5 conta isso como erro em cada map (foi a r1 do #8120).
- **Auto-merge fecha o PR antes do próximo push chegar:** o registro de envio posto no branch do #8147 ficou fora do main e precisou do #8155. Não empurre para branch de PR com auto-merge ligado; abra outro.
- **"mergeei" nem sempre é estado:** duas vezes o PR seguia aberto (checks na fila, botão bloqueado). Confira `gh pr view N --json state,mergedAt` antes de agir sobre o merge.
- **Espelho × Cowork:** editar `prototipo-ui/cowork/Wagner/ponto-telas.jsx` arma o `cowork-mirror-freshness --unverified --check` (`MEXIDO-DEPOIS`) até subir e registrar no ledger (`--snapshot-from` + `--compare --check --ledger`).

## Estado MCP no momento do fechamento

Consultado em 2026-09-29 ~10:24 BRT:

- `cycles-active` (COPI): nenhum cycle ativo.
- `my-work` (@wr23): sem tasks ativas.
- `sessions-recent limit:3`: três sessões de estado-da-arte de 2026-08-22 (agentes de IA gerando UI, consistência com centenas de telas, fidelidade design → produção), indexadas em 2026-09-29. Nenhuma desta thread.
- ADRs desde o último handoff do Ponto (2026-09-28 18:40): 0416, 0417, 0418 (Ponto: listas no servidor com a forma do protótipo e 13 abas) e 0419 (Ponto: escopo do REP-P ratificado, W10). Nenhuma muda a thread 17.
- PRs da thread abertos: nenhum. `pendentes-cowork`: 1 não enviado, `_DECISOES-W-2026-09-29.md`, de outra sessão.

## Referências

- Recibo: `prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/_saida-17.md`
- Thread: `prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/17-data-contract-no-tsx.md`
- Evidências GT-G5: `memory/sessions/2026-09-29-refutacao-gt-g5-lote-8120-r1.md`, `-r2.md`
- Handoff irmão: [2026-09-28 18:40 — thread 27](2026-09-28-1840-ponto-thread-27-emendas-casos-guards.md)
