---
date: "2026-09-08"
hour: "11:45 BRT"
topic: "Rodada pontual do --export-from apagava a devolutiva de outra rodada — o universo da rodada lido como universo global"
authors: ["C"]
outcomes:
  - "PR #6992: preserva o retrato + `--limpar-devolutiva` explícito, com bite-test no dono"
  - "A 1ª forma sugerida (cobertura do universo) medida e provada INSATISFAZÍVEL por construção"
  - "2º achado: o bite nascia MUDO — a lane não tinha os arquivos sob teste no `paths:`"
  - "Near-miss meu: baseline lido de um processo que nunca executou (rc=0 + zero output)"
---

# A devolutiva que sumia, e a medição que quase mentiu

## O pedido

Consertar o bloco do `--export-from` em [`cowork-mirror-freshness.mjs`](../../scripts/governance/cowork-mirror-freshness.mjs)
que regenera/remove `prototipo-ui/CODE_NOTES.recusados-canon.md`. Uma rodada pontual (1 JSON)
executava `rmSync` e apagava o retrato de 54 recusados de 07/09.

## O que se mediu, na ordem

**1. Reproduzir antes de tocar.** Sandbox com 2 rodadas: uma com recusa (escreve o retrato),
outra pontual com 1 `.md` avulso e zero canon de tela. A 2ª imprimiu *"removido — nenhuma recusa
nesta rodada"* e apagou. Defeito confirmado, não deduzido.

**2. A causa não é o `rmSync` — é a premissa.** O comentário do autor declarava *"o arquivo é
derivado e regenerado; quando não há recusa nenhuma, ele some"*. Verdadeiro para rodada COMPLETA,
falso para PONTUAL — e [`protocolo.config.mjs`](../../prototipo-ui/protocolo.config.mjs) classifica
o `--export-from` como **a rota do caso pontual** (*"ARQUIVO AVULSO (1-3) → `--export-from`"*).
A premissa era falsa justamente no uso principal do comando.

**3. A 1ª forma sugerida é insatisfazível — e isso se prova, não se opina.** *"Só remover quando a
rodada cobre o universo do retrato"*: o retrato só contém paths que passaram por
`RE_CANON_DE_TELA` (o `recusados.push` está **dentro** daquele `if`), teste determinístico no path.
Incluir um path do retrato na rodada **o recusa de novo** ⇒ o ramo de zero-recusa nunca roda.
*"Cobriu o universo"* e *"zero recusa"* são **mutuamente exclusivos por construção**. Implementar
aquilo seria um ramo de remoção que nunca dispara — o espelho do gate que nunca fica vermelho.

**4. O limiar também caiu.** *"N arquivos = rodada completa"* é denominador que decisão nenhuma
estabeleceu (§5 2026-07-27) — o mesmo erro com um número na frente.

## O que ficou

A rodada que não pode **provar** que o retrato acabou o preserva, diz de quando ele é (data +
contagem lidas do próprio cabeçalho, via `lerRetratoDevolutiva`) e oferece o descarte explícito
`--limpar-devolutiva`. Declaração do operador — mesmo desenho do `--origem agente` do bloco
vizinho, e nomeada pelo **efeito** (apaga), não por uma propriedade que o script não checa (LC-10).

## O 2º achado, que não estava no pedido

Instalado o bite-test, medi se a lane que o roda **enxerga o arquivo mudar**. Não enxergava:
`cowork-mirror-freshness.{mjs,test.mjs}` não estavam no `paths:` do `design-memory-gate.yml`, e
`gh pr checks 6992 | grep -ci design-memory` deu **0**. O bite nascia mudo — a classe que o
comentário **2 linhas acima no mesmo `paths:`** já advertia, e que o `cowork-pele-paralela` já
tinha resolvido do mesmo jeito. Commit 2 corrige (paths 29 → 31).

Ressalva honesta que fica registrada: o `on:` dessa lane é `types: [opened, reopened,
ready_for_review]` — **sem `synchronize`**. O gatilho novo vale para PRs abertos daqui em diante;
ele não se re-dispara por push num PR já aberto. Provado por `workflow_dispatch` na branch.

## O erro que eu quase publiquei (LC-08)

Pro baseline dos modos de CI, copiei o script para `scripts/governance/__base-tmp.mjs` e rodei.
Saiu **rc=0 com ZERO output**. Ia ler isso como *"baseline limpo, sem divergência"* — e cheguei a
escrever uma comparação inteira em cima disso, com 6 modos dando `base=0`. Todos os seis eram
**não-execuções**: o guard de entry-point do script é
`process.argv[1].endsWith('cowork-mirror-freshness.mjs')`, e `__base-tmp.mjs` não casa, então
`main()` nunca rodou.

O que expôs foi um único modo discordando (`--sla`: base=0, novo=1). Se o defeito fosse uniforme,
os 7 teriam concordado e eu teria publicado uma comparação de nada. Refeito com nome que satisfaz
o guard (`base-cowork-mirror-freshness.mjs`) **+ controle positivo** (a saída tem de ser não-vazia
antes de eu confiar nela): os 7 modos saem **byte-idênticos**, e o `--sla` rc=1 é **pré-existente**
(`LAST-PARTIAL` do ledger), reproduzido nos dois lados.

É §5 2026-07-29 na veia — *vazio com rc=0 é "não mediu", não "mediu limpo"* — e §5 2026-08-01,
que já prescrevia o controle positivo. As duas lápides existiam; o que faltou foi aplicá-las
**antes** de montar a tabela, não depois.

## Provas

| o quê | como |
|---|---|
| bite morde | mutação: `rmSync` incondicional reposto ⇒ suíte `rc=1`, 3 asserts vermelhos |
| sem regressão | teste ANTIGO (`origin/main`) contra script NOVO: 250 OK · 0 FAIL |
| contador | 250 → **257 OK · 0 FAIL** (+7 = os 7 asserts novos) |
| roda no CI | `workflow_dispatch`: os 7 asserts `[OK]` no log do Linux, **0 `[FAIL]`** |
| modos do CI | 7/7 byte-idênticos vs `origin/main`, rc incluso |
| YAML íntegro | reparseado após a edição: 4 jobs · 31 paths (§5 2026-08-05) |

Li a **saída do teste** no log, não o campo `conclusion` — o step tem `continue-on-error`, e ali
o campo não distingue.

## O que NÃO se fez, e por quê

- **Sem lápide §5 nova para o defeito consertado.** A classe já está catalogada duas vezes
  (§5 2026-08-10 *catraca cujo universo vem do lado mutável* · §5 2026-08-04 *isenção que casa com
  a saída-padrão do produtor*). Re-registrá-la duplicaria régua consolidada (§5 2026-07-09). Este
  trabalho é a **execução** que a lápide de 2026-08-02 pedia — *"recuar à mão em vez de virar REGRA
  do mecanismo; o próximo run repete"* —, não uma classe nova.
- **Recibo LC-08 sim**, pelo near-miss acima: a classe é minha e o ledger é meu.
- **Sem gate novo** para "premissa de universo": o predicado é semântico, e a família sintática já
  tem lápides medidas.

## Pointers

- PR: [#6992](https://github.com/wagnerra23/oimpresso.com/pull/6992) — 2 commits, 3 arquivos
- Origem da devolutiva: #6945 · descoberta do defeito: #6990
- Dono do teste: [`cowork-mirror-freshness.test.mjs`](../../scripts/governance/cowork-mirror-freshness.test.mjs) §13
