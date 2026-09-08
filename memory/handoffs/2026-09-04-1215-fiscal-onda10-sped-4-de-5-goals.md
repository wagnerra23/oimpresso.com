---
id: handoffs-2026-09-04-1215-fiscal-onda10-sped-4-de-5-goals
date: "2026-09-04"
time: "12:15"
slug: fiscal-onda10-sped-4-de-5-goals
tldr: "Onda 10 do Fiscal fecha os 5 Goals do charter do Cowork em 4 PRs (3 de código empilhados + docs). A fonte estava VELHA num ponto e ERRADA em outro, e os dois viraram teste. [W] decidiu na sessão: bypass por opt-out (teste verde > charter) e prévia por arquivo de referência. Corrige um recibo da Onda 9: as 2 falhas da suíte são PermissionDoesNotExist, não users_username_unique."
decided_by: [W]
prs: [6723, 6728, 6737, 6741]
us: [US-FISCAL-010, US-FISCAL-016, US-FISCAL-017]
next_steps:
  - "Mergear na ordem: #6723 → #6728 → #6741 (empilhados). O #6737 (docs) é independente."
  - "Cada PR da pilha muda a tela: a baseline de pixel pede nova passada a cada merge (a do #6723 já foi disparada e autorizada por [W])."
  - "Smoke visual autenticado pós-merge — a tela precisa estar em produção."
  - "Seed da permission `superadmin` no CT 100: enquanto faltar, o UC-FSPED-09 e o 503 ponta-a-ponta do UC-FSF1-02 não executam em lane nenhuma."
related_adrs:
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0286-contrato-de-tela
---

# Handoff — Fiscal Onda 10 (SPED): os 5 Goals do charter do Cowork

Narrativa completa em
[`memory/sessions/2026-09-04-fiscal-onda10-sped-goals-cowork.md`](../sessions/2026-09-04-fiscal-onda10-sped-goals-cowork.md).

## Estado

| Goal | Onda 9 | Onda 10 | PR |
|---|---|---|---|
| 1. Barra com os 4 pré-requisitos | 🟡 no drawer | ✅ barra na página + data de encerramento | [#6723](https://github.com/wagnerra23/oimpresso.com/pull/6723) |
| 4. Cartão de validação externa | ❌ | ✅ medido do disco | #6723 |
| 5. Blocos com os registros | ❌ | ✅ medido do golden | #6723 |
| 2. Bypass de superadmin explícito | ❌ | ✅ ação nomeada com efeito no servidor | [#6728](https://github.com/wagnerra23/oimpresso.com/pull/6728) |
| 3. Prévia do TXT | ❌ | ✅ layout de um arquivo de referência | [#6741](https://github.com/wagnerra23/oimpresso.com/pull/6741) |

Docs (session log + este handoff): [#6737](https://github.com/wagnerra23/oimpresso.com/pull/6737).

Âncoras `data-contract` do protótipo presentes na tela: **1 → 5**. A que falta (`panorama-sped`) é
a tabela que a tela já tinha antes do F1.

**Os três PRs de código estão empilhados** — mergear `#6723 → #6728 → #6741`.

## Estado MCP no momento do fechamento

⚠️ **As tools MCP do oimpresso não estavam disponíveis nesta sessão** — o brief chegou pelo hook
`brief-fetch-curl` do SessionStart (Brief #605), e o **`whats-active` (LC-19) não pôde ser
chamado**. Registro como limitação da sessão, não como consulta feita. É a 3ª sessão seguida assim.

Substituto: `list_sessions` do host — **6 sessões Fiscal ativas**. Uma toca SPED (*"Investigar
emitente vazio no SPED (CNPJ/IE/UF fixa SP)"*), mas no `SpedIcmsIpiGeneratorService` e no registro
`0000`, que a lei desta onda proibia tocar. **Interseção de arquivos: vazia.**

## As duas decisões [W] da sessão

### Bypass de superadmin: opt-out, porque o teste verde venceu o charter

O `UC-FSF1-02` do Cowork quer a tela abrindo **bloqueada** mesmo para superadmin (opt-in). Mas
`SimplesOnlyGateTest::UC-FSPED-09 · superadmin bypassa flag` é **teste verde** e prova o contrário.
Precedência do projeto: *teste verde > casos > charter*.

[W] escolheu **opt-out**: o default preserva o comportamento provado, e a ação explícita
("Reativar trava nesta sessão") só consegue **restringir**. A trava global
`fiscal.sped_simples_only_lock` **não é tocada** — o que alterna é o bypass da sessão de quem
clica. Divergência registrada no charter e no casos.md, não escondida.

### Prévia do TXT: arquivo de referência, não encenação

A pergunta que a Onda 9 deixou estava **mal formulada** — a fonte mostra que a prévia nunca exigiu
rodar o gerador (o protótipo renderiza linhas fixas). O que restava era mais estreito: em produção,
*encenar* seria **fabricar**.

[W] inclinou para "linhas do golden como referência" pedindo mais informação. Medi: o golden é
saída **real** do gerador e a **primeira linha dele se identifica como fictícia**
(`|0000|…|CI TENANT 98 (FICTICIO)|…`). Com isso, [W] confirmou.

A tela mostra **uma linha por registro distinto** (as 12 primeiras cobririam só o Bloco 0), diz de
quem é o arquivo **lendo o nome do `0000`** — não de um rótulo escrito à mão —, e `previaTxt`
(a prévia do arquivo **do operador**) **continua `null`**, com a ausência declarada. A copy
"Não é a sua competência" está travada no `contrato-de-tela` para que as duas não se confundam.

## O que a fonte do Cowork tinha de errado, e virou teste

**Velha:** o cartão de validação do protótipo diz *"Golden file do TXT: não existe"*. O charter é
de 2026-08-24; o golden nasceu em **2026-09-03** (#6708) — **um dia depois**. Traduzir a copy
literal teria posto afirmação **falsa** numa tela fiscal. O `UC-FSF1-07` existe para impedir a
cópia voltar.

**Errada:** o motivo do mês em aberto, no protótipo, cita o campo `entrega` (prazo de entrega, dia
15 do mês seguinte) quando o que **destrava a geração** é o **encerramento** da competência. Quem
lesse a data errada esperaria duas semanas a mais. O `UC-FSF1-01` tem um assert
`not->toContain(prazo de entrega)` só para isso.

## Testes (CT 100, MySQL staging)

| Comando | Resultado |
|---|---|
| `--filter=SpedOnda10` | **11 → 15 passed (81 assertions)** com o Goal 3 |
| `--filter=SpedBypassSuperadmin` | **5 passed, 1 skipped (13 assertions)** — o **403 rodou** |
| `--filter='Sped\|SimplesOnly'` | **46 → 57 → 62 passed** (Onda 9 → PR1 → PR2) |

Todo delta bate com os casos adicionados — é o contador que prova execução, não o "0 failed".

⚠️ **As 2 falhas da suíte são pré-existentes, e eu medi em vez de deduzir:** restaurei o
`SpedController.php` do `origin/main` no container e rodei `SimplesOnlyGateTest` — `UC-FSPED-09 ·
superadmin bypassa` falhou **igual**, com `PermissionDoesNotExist: superadmin`.
**O handoff da Onda 9 atribuía essa falha a `users_username_unique` — é outra causa**, e o registro
fica corrigido aqui.

## Baseline visual — autorizada e disparada

O `visual-regression` do #6723 estava vermelho e **era meu**: `Fiscal/Sped` 1,2261%, dentro do raio.
Não deduzi que era intencional — baixei o artifact, confirmei que a baseline do HTML é
**byte-idêntica** ao `.snap` do repo e **olhei** o diff: barra nova no topo (com *"encerrou em
31/05/2026"*), tabela deslocando, card de blocos no rodapé; sidebar, header e subnav intactos.

[W] autorizou e disparei o `workflow_dispatch` do `visual-regression` no modo update, escopado
(`screens: ["Fiscal/Sped"]` — o update completo não cabe no timeout). **Cada PR da pilha muda a
tela, então cada merge pede nova passada.**

## Higiene do CT 100 (para quem for rodar teste lá)

O checkout do `oimpresso-staging` está em `c1abe9548`, com arquivos copiados à mão por sessões
anteriores. **O `Modules/Fiscal/Routes/web.php` de lá é mais velho que o `origin/main`** —
sobrescrevê-lo quebra rotas para as outras sessões. Apliquei só a inserção da rota nova, **com
backup antes**, e **restaurei ao fim** conferindo o sha (`59a5c5b4efe2fa54`).

Numa cópia anterior sobrescrevi o `SpedController.php` **sem** guardar o original — deslize sem
consequência (era reconstruível do `origin/main`), mas o backup passou a vir antes de qualquer
escrita. **Fica como regra: baixe e guarde antes de escrever no container.**

## Pendências herdadas que continuam abertas

- **Seed da permission `superadmin` no CT 100** — enquanto faltar, o `UC-FSPED-09` e o 503
  ponta-a-ponta do `UC-FSF1-02` não executam em lane nenhuma. Lacuna de ambiente, não de teste.
- **Emitente do registro `0000`** (CNPJ/IE vazios, UF fixa `SP`) — sessão paralela ativa. Agora a
  prévia do Goal 3 **expõe isso na tela**, o que é informação útil, não ruído.
- **Watchdog G6 vermelho** — conta OpenAI sem crédito (US-COPI-145), sessão paralela triando.
- **Smoke visual autenticado pós-merge** — pendente do merge.
