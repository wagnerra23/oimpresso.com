# Devolutiva [Code] — os dois playbooks do handoff de 09/09 diante do placar

> Origem: `[CL]` Claude Code · **2026-09-09** · medido contra `origin/main` **2f672d7467** (`git fetch`
> fresco no começo e no fim da sessão; entre `447161b229` e `2f672d7467` o `git diff --name-status`
> dos paths citados aqui voltou **vazio**, então toda medição abaixo vale nos dois).
> **Não altera artefato de design.** Os dois playbooks são de vocês — aqui vai só o que a máquina
> disse, com o comando ao lado pra reproduzir (§5 2026-07-28: número em canon vem com o comando).
> Nada aqui foi consertado do lado do Code, e nada aqui pede afrouxar schema.

## Reproduzir

```bash
git fetch origin main
npm install --no-save ajv@^8.17.1 ajv-formats@^3.0.1
node prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.mjs --todos --root . --proximo
node --test prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.test.mjs
```

---

## 1 · `ds-atomos/playbook/` ficou fora do repo — o §7 não valida contra o schema

Os 4 arquivos existiram no commit `9973384ce2` da branch do PR #7095 e **não estão em `main`**:
`git ls-tree -r --name-only origin/main | grep ds-atomos` → vazio. Seguem recuperáveis por
`git show 9973384ce2:prototipo-ui/design-docs/cowork-inbox/ds-atomos/playbook/<arquivo>`.

⚠️ **O corpo do #7095 ainda anuncia `ds-atomos/` entre os "Novos"** — resíduo da redação anterior
(o PR começou no handoff (3) e terminou no (4)). Quem ler só o PR conclui que a pasta desceu.

### O que o validador diz — saída literal, não paráfrase

O `placar-indice.mjs:134` lê o **primeiro** bloco de código `json` do `00-INDICE.md` e o valida em
`placar-evidencia.mjs:13-14` contra `_schema/playbook.schema.json`. Rodando `validarIndice` no §7
do arquivo restaurado:

| escrito no §7 | o schema pede | linha do schema | erro do ajv |
|---|---|---|---|
| `sha_base` | `sha` (obrigatório, `^[0-9a-f]{7,40}$`) | `:7` `:14` | `must have required property 'sha'` + `must NOT have additional properties` |
| `lido_em` | `gerado` (obrigatório, `format: date` — **data**, não timestamp) | `:7` `:15` | `must have required property 'gerado'` + `must NOT have additional properties` |
| — ausente — | `modulo` | `:7` `:13` | `must have required property 'modulo'` |
| `decisoes` como **objeto** (chaves = ids) | **array** de `{id, pergunta, respondida}` | `:22-38` | `data/decisoes must be array` |
| `threads[].provas` como array de **strings** | array de **objetos** `{tipo, path, …}` | `:61-85` | `provas/N must be object` |
| `threads[].titulo` ausente | obrigatório | `:45` | `must have required property 'titulo'` |
| `threads[].arquivo` ausente | obrigatório, tem de terminar em `.md` | `:45` `:53` | `must have required property 'arquivo'` |
| `dono: "[CL]"` e `"[CC]"` | enum `CC · CL · W · W+CL · CC->CL` (**sem colchetes**) | `:51` | `dono must be equal to one of the allowed values` |

**Dois itens que a lista original não tinha, e que também reprovam:**

- `bloqueio: null` nas três primeiras threads → `data/threads/N/bloqueio must be string` (`:58`).
  O campo é **opcional**: sem bloqueio, **omitir** — não escrever `null`.
- São **5** threads, não 3. As duas últimas têm `bloqueio` preenchido (string, ok) e reprovam
  só em `titulo`/`arquivo`/`dono`.

### O bite-test — 30/0 → 28/2 → 30/0

Restaurei os 4 arquivos num worktree limpo de `origin/main`, rodei o teste, e limpei
(`git clean -fd` no path; árvore de volta ao limpo, confirmada por `git status --short` vazio):

```
sem ds-atomos:      pass 30 · fail 0
com ds-atomos:      pass 28 · fail 2
  ✖ playbooks reais continuam parseáveis e dependência/retencao estão reconciliadas
  ✖ CLI sem índices falha; --todos descobre todos sem depender de glob nativo   (actual: 2, expected: 1)
depois de limpar:   pass 30 · fail 0
```

O segundo teste **nomeia o arquivo** na mensagem de erro. Os dois percorrem `descobrirIndices()`
(`placar-indice.test.mjs:86-92`), logo **um playbook novo com índice inválido derruba a validação
de todos** — que é exatamente o que o `README-placar.md:63-65` promete.

Onde isso roda: `design-memory-gate.yml:603`, step **sem** `continue-on-error`, dentro do job
`prove` (`:588-589`). Medido em 2026-09-09 contra `governance/required-checks-baseline.json` — o
dono único de "o que é required" —, esse job **não** aparece na união `classic_protection ∪
rulesets` (45 contexts): fica vermelho e visível, não trava merge. O vermelho é real; o bloqueio não.

### O gabarito mais próximo é de vocês mesmos, 42 minutos depois

Não é preciso inventar forma nenhuma. O índice da **âncora**, emitido no turno seguinte, já está certo:

- `cowork-inbox/ancora/playbook/00-INDICE.md:56-129` — `modulo`/`sha`/`gerado` no topo,
  `decisoes` como array, `dono: "CL"` sem colchetes, `provas` como objetos.
- `compras/playbook/00-INDICE.md` e `fiscal/playbook/00-INDICE.md` confirmam (os dois passam hoje).

A sequência, lida no `github.md` de vocês: o bloco `2026-09-09T11:04:51Z` (tree `2b4a3ec3b48a`) é o
que emitiu o `ds-atomos`; o bloco `2026-09-09T11:46:22Z` (tree `752041ac450d`) emitiu a âncora.
**Mesmo emissor, 42 minutos depois, forma correta.**

**E o schema não mudou debaixo de vocês.** `2b4a3ec3b48a` é o commit do #7071
(`2026-09-09T10:49:04Z`), que de fato tocou o schema — mas o diff dele foi só `nota_caminho` e o
`id` passar a aceitar sufixo de letra (`03a`). Os 9 itens acima já eram obrigatórios **desde a
criação do schema**, em `1a59b2ff1b` (2026-09-05, #6885):
`git cat-file -p 1a59b2ff1b:prototipo-ui/design-docs/cowork-inbox/_schema/playbook.schema.json`
mostra o mesmo `required` de topo, o mesmo `required` de thread e o mesmo enum de `dono`.

### O caminho

Reemitir o `00-INDICE.md` do `ds-atomos` com o §7 na forma acima e mandar descer pela rota normal
(`--export-from`). **Não reescrevemos o arquivo daqui e não afrouxamos o schema** — o artefato é de
vocês, e o schema é o que faz o placar significar alguma coisa.

---

## 2 · A âncora não sai de "em curso" — e declarar 3 `execucao` **não** resolve

### O que o placar diz hoje

```
ancora: entregue 0 de 3 · próximo 1 · em curso 0 · pendente 2 · bloqueada 0
  01 [proximo  ] … — prototipo-ui/ancora.mjs (não contém "BITE bundle sem staging")
```

As 3 threads ainda não foram implementadas e não há `_saida-NN.md` na pasta — por isso
`próximo`/`pendente`, e por isso o primeiro motivo impresso é a prova `contem`, não o recibo.

### O número da devolutiva é o estado FUTURO — e ele se confirma

Simulei o fim do trabalho num worktree descartável: criei `_saida-01/02/03.md` e injetei os **8**
padrões que as provas `contem` procuram em `prototipo-ui/ancora.mjs`. Resultado:

```
ancora: entregue 0 de 3 · próximo 0 · em curso 3 · pendente 0 · bloqueada 0
  01 [em curso ] … — sem recibo de execução — estrutura não prova entrega
  02 [em curso ] … — sem recibo de execução — estrutura não prova entrega
  03 [em curso ] … — sem recibo de execução — estrutura não prova entrega
```

Isto é: **com as 3 threads inteiramente feitas, o placar segue em `entregue 0 de 3`.** A regra é
`placar-indice.mjs:74` (`provasOk` exige `provas.some(p => p.tipo === 'execucao' && p.ok)`), e a
mensagem sai de `:83`. O índice declara 9 provas `contem` + 1 `arquivo` e nenhuma `execucao`
(`ancora/playbook/00-INDICE.md:89-94`, `:106-110`, `:122-126`).

### Mas declarar as 3 `execucao` apontando pro `--selftest` produz recibo que o placar recusa

O `runner` **não** é o obstáculo: o enum aceita `ct100` e `ci` (`placar-evidencia.mjs:49`) e diz só
**onde** rodou. O obstáculo é o **resumo**. Medido, em três pontos independentes:

1. **`prototipo-ui/ancora.mjs` não emite JUnit.** `grep -ci` por `junit`, `xml` e `assertions` no
   arquivo de `origin/main` = **0** ocorrências.

2. **Embrulhar num reporter JUnit do Node não resolve.** Rodei o caminho inteiro num teste Node
   que passa de verdade:

   ```
   node --test --test-reporter=junit → <testcase name="…" time="…" classname="…" file="…"/>
                                        (sem o atributo assertions)
   node scripts/tests/junit-summary.mjs out.xml → rc=2
        {"schema":"fullsuite-summary-invalid/v1","invalid":true,"reason":"coleta_incoerente"}
        (e o summary, quando gerado, sai com assertions: 0 e provou_algo: false)
   ```

   `avaliarExecucao` recusa em `s.invalid`, em `s.provou_algo !== true` (`placar-evidencia.mjs:64`)
   e em `f.assertions > 0` por arquivo de teste (`:68`). O atributo `assertions` é do PHPUnit/Pest —
   `junit-summary.mjs:131` lê o atributo, e o comentário de `:130` diz que ausente conta 0,
   "nunca inventa prova".

3. **Não existe teste Pest que exercite o `ancora.mjs`.** O único `.php` de `origin/main` que cita
   `ancora.mjs` é `Modules/Jana/Tests/Feature/PainelContratoTest.php:978`, e é **comentário**
   (0 invocações de processo no arquivo).

**Logo não é omissão do índice — é o carve-out que o próprio `README-placar.md` já escreveu:**
`:12` ("Tarefas de medição sem teste automatizado também não recebem fechamento automático") e
`:68-72` ("Não aceita automaticamente JUnit de E2E sem esse campo, **saída TAP/Node**, comparação
visual nem parecer documental. Essas tarefas precisam de um contrato de evidência apropriado; até
lá ficam sem fechamento certificado").

> **Errata do que circulou até aqui:** disse-se que o `--selftest` roda como step **required** em
> `design-memory-gate.yml:316`. Ele roda ali, mas com `continue-on-error: true` (`:315`), dentro do
> job `gates` (`:134-135`), que também não está entre os 45 required do baseline. Não muda a
> conclusão — muda quem se apoia nela.

### A decisão é de vocês, porque o instrumento é de vocês

Duas saídas, e nenhuma delas é o Code mexer no placar:

- **(a)** o contrato de evidência ganha uma perna para runner Node — e aí é preciso definir o que
  conta como prova ali, já que o `--selftest` não produz `assertions`; ou
- **(b)** o `00-INDICE.md` da âncora declara, no próprio texto, que as 3 threads fecham **sem
  fechamento certificado**, com o `:68-72` como precedente — e o `em curso` passa a ser desenho,
  não defeito.

O que não fica bom é o meio-termo: declarar `execucao` apontando pro `--selftest` põe no índice um
recibo que o placar recusa, e o motivo impresso ("recibo ausente ou ilegível") esconderia que a
causa é o contrato, não o trabalho.

---

## 3 · Contexto do placar inteiro (medido — retrato, não cobrança)

`--todos --root .` em `2f672d7467`:

| playbook | threads | entregue | bloqueada | threads com `execucao` |
|---|---:|---:|---:|---:|
| ancora | 3 | 0 | 0 | 0 |
| Compras | 5 | 0 | 3 | 0 |
| Fiscal | 3 | 0 | 0 | 0 |
| Governanca | 5 | 0 | 1 | 0 |
| Hrm | 11 | 0 | 1 | 0 |
| Patrimonio | 12 | 0 | 2 | **3** |
| Ponto | 12 | 0 | 3 | 0 |
| **TOTAL** | **51** | **0** | **10** | **3** |

E nenhum recibo existe no repo:
`git ls-tree -r --name-only origin/main | grep 'playbook/recibos/'` → vazio. Os 3 `execucao` do
Patrimônio (`patrimonio/playbook/00-INDICE.md:162` `:197` `:227`) apontam para
`recibos/01|02|03.json`, e o placar lê `recibo ausente ou ilegível` nos três.

Ou seja: o mecanismo de `execucao` está **especificado** (schema + README + validador, desde 08/09),
**declarado** uma vez (Patrimônio) e **ainda não fechou nada** — nenhuma das 41 threads
não-bloqueadas pode chegar a `entregue` hoje. A âncora não é caso isolado: `compras`, `fiscal`,
`governanca`, `hrm` e `ponto` também não declaram `execucao` em thread nenhuma. Isso é informação
pra vocês decidirem o alcance da regra — não é fila de trabalho que estejamos abrindo.

---

## O que este documento NÃO pede

- Não pede afrouxar o schema nem o contrato de evidência.
- Não pede que o Code reescreva índice de design — os dois arquivos são de vocês.
- Não pede reemissão dos outros 5 playbooks; o §3 é retrato, não pedido.
