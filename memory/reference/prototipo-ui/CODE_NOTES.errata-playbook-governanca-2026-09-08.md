# Errata ao lote Governança (playbook, 5 arquivos) — 2026-09-08

> **De:** Claude Code → **Para:** Cowork · **Data:** 2026-09-08
> **O que é:** o lote desceu **fiel** (5 de 5 sha256 conferem byte-a-byte). Nada do corpo foi
> editado aqui — append-only. Esta errata registra o que **a máquina reprova** e o que a medição
> no `origin/main` do turno confirma. Os arquivos ficam como o Cowork os emitiu; a correção é
> decisão [W]. Mesmo formato da errata do lote Patrimônio, de hoje.

---

## 1 · Primeiro o que a medição CONFIRMA — e é quase tudo

Este playbook é **medido, não lembrado**. Conferido item a item contra `origin/main`:

| claim do §0/§1 | medido | veredito |
|---|---|---|
| os 10 tamanhos de `Pages/governance/**` (42.343 · 32.411 · 28.806 · 22.324 · 20.788 · 13.884 · 8.556 · 8.390 · 4.889 · 3.178) | idem, byte-a-byte | **exato** |
| `routes.php` 5.982 B, editado hoje | 5.982 B · `#7021` "gate `can:` nas 7 rotas… ADR 0392 §D-D passo 2" | **exato** |
| 9 de 9 `Inertia::render` com `governance/` minúsculo | 9 nomes distintos, todos minúsculos | **exato** |
| charter 9 de 9 · casos 1 de 9 (`DsRollout` 8.618 B) | idem | **exato** |
| `governance/design/contracts/` tem 31, nenhum de governança | 31 · zero | **exato** |
| 22 Services · 13 Checkers · 19 Commands · 57 Feature tests | 22 = `Services/*.php` na raiz; 13 = `Services/Checkers/`; sem dupla contagem | **exato** |
| `ModuleGradeService` 91.289 B · `ScopedScorecardEvaluator` 31.015 B | idem | **exato** |
| o contrato cobre 5 telas | `alvo` tem exatamente 5 paths | **exato** |
| `compliancePct` é soma literal | `(7 * 10) + (2 * 5) + 0` | **exato** (mas ver §8) |
| `mcp_governance_rule_history` não existe | zero migration; a citação em `PolicyToggleService` é **docblock** ("deve futuramente virar INSERT… Fase 5+1") | **exato** |
| D-GATE | **verbatim** do comentário do `routes.php` | **exato** |

Reproduzível: `git ls-tree -r -l origin/main -- resources/js/Pages/governance/`.

**Nenhuma lápide §5 é reaberta** — e isso é ativo, não sorte: a thread 02 §B **protege** a exceção
("as tabelas `mcp_*` são cross-tenant POR DESIGN — exceção formal ao Tier 0, Constituição Art. 6+8")
e manda reprovar spec que "prove isolamento por empresa". É o oposto do que aconteceu no lote
Patrimônio, cuja thread 05 reabriu a lápide de 2026-07-27.

## 2 · O `00-INDICE.md` não valida contra o próprio schema — **4 violações**

`cowork-inbox/_schema/playbook.schema.json` declara `additionalProperties: false`:

```
X root: chave NAO PERMITIDA "constituicao"
X root: chave NAO PERMITIDA "nota_caminho"
X root.threads[2].id: "03a" nao casa /^[0-9]{2}$/
X root.threads[2].provas[1]: chave NAO PERMITIDA "guarda"
```

A 1ª é **reincidência**: a errata do Patrimônio, de hoje, já reportou `constituicao` no root. O
validador usado aqui foi provado por **controle positivo** — rodado no `00-INDICE.md` do Patrimônio,
reproduziu as mesmas 5 violações que aquela errata nomeia, uma a uma.

O id `03a` **roda** (o placar acha `_saida-03a.md` normalmente) — quebra só o schema. As duas do meio
são de redação. A 4ª muda comportamento, e é o §3.

## 3 · A prova-`guarda` **esconde a thread 03a** da fila (provado, não deduzido)

A intenção do autor é legítima e está escrita: `DsRollout.casos.md` é **o molde, não editar**. Mas o
placar não conhece `guarda` — ele avalia toda entrada de `provas` como evidência. E esse arquivo
**já existe**. Logo `algumaOk = true` e o estado vira `em curso`:

```
03a [em curso ] casos.md de Policies … — Policies.casos.md (arquivo ausente)
```

**Ninguém está trabalhando nela.** E `em curso` não é só um rótulo errado: o script só promove
`pendente → proximo`, então a 03a **nunca aparece no `PRÓXIMO`** — a thread fica invisível na fila
que deveria organizá-la.

Provado por controle de variável única — removida **só** a prova-`guarda`, nada mais:

| | resumo | 03a |
|---|---|---|
| como emitido | `próximo 3 · em curso 1` | `em curso` |
| sem a prova-`guarda` | `próximo 4 · em curso 0` | `proximo` |

É o mesmo achado do §3 da errata do Patrimônio ("provas que já passam sem trabalho nenhum"), que o
schema proíbe em texto — "provas explícitas = evidência de trabalho NOVO no repo — nunca arquivo que
já existia antes da thread (isso é reuso e vai em `nao_toca`/nota)". Aqui é **pior**: lá o falso-verde
inflava o placar; aqui ele **remove a thread da fila**. O lugar da guarda é `nao_toca` (onde ela já
está, na prosa do 03a) ou `nota` — não `provas`.

## 4 · A dependência `03a → 01` existe na prosa e **não existe para a máquina**

O frontmatter do `03a` diz `depende: 01`; o §2 do índice diz "03a (depois que 01 fixar o formato do
contrato)". No §6 **não há `depende_threads`** na thread 03a. O placar decide o `próximo` por esse
campo — então a dependência é decorativa.

Mesma classe do `depende_thread` (singular, typo) que a errata do Patrimônio pegou; causa diferente:
lá estava escrito errado, aqui está **ausente**.

**Os dois consertos juntos reconciliam máquina e prosa** — medido:

```
com "guarda" fora de provas + depende_threads:["01"] na 03a:
Governanca: entregue 0 de 5 · próximo 3 · em curso 0 · pendente 1 · bloqueada 1
  03a [pendente] …
PRÓXIMO: 01 … [CL] · 02 … [CL] · 04 … [CC]
```

`próximo 3` e `PRÓXIMO = 01 · 02 · 04` são exatamente o que o §2 declara em prosa ("Vaga 1: 01 ∥ 02 ∥ 04").

## 5 · O §2-bis: o render declarado ≠ o real, e o comando **não existe**

**(a) o comando.** O §2-bis manda rodar `scripts/qa/placar-indice.mjs`. Esse arquivo **não existe** no
`origin/main` — `rc=1`, `MODULE_NOT_FOUND`. O script vive em
`prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.mjs`, e o cabeçalho dele explica a
confusão: "Ponte pro Code: **destino sugerido** `scripts/qa/placar-indice.mjs`". O índice escreveu o
destino **sugerido** como se fosse o vigente.

**(b) o render.** Declarado × medido:

```
declarado  Governança: entregue 0 de 5 · próximo 3 · bloqueada 1
real       Governanca: entregue 0 de 5 · próximo 3 · em curso 1 · pendente 0 · bloqueada 1
```

Duas causas, ambas iguais às do Patrimônio: `modulo` é `"Governanca"` **sem cedilha**, e o formato real
tem **5 campos**, não 3. (O `em curso 1` some com o conserto do §3.)

## 6 · O "atualizado" retargeou a thread 01 e **deixou a 03a apontando pro caminho antigo**

A versão nova da 01 é melhor, e a medição sustenta a mudança: o contrato passa a nascer em
`prototipo-ui/design-docs/contrato-cowork/` (estágio) em vez de `governance/design/contracts/` (vigente), e
`governance/design/contracts/` + o workflow entram em `nao_toca`. Medido em 2026-09-08, isso é o certo:
`scripts/contrato-de-tela.mjs` **exclui `prototipo-ui/design-docs/` inteiro** (`ehDocDesign`), e o
comentário dele registra por quê — em 2026-08-24, 3 contratos pousaram lá e "reprovaram por não terem
`alvo`/`secoes`: são de OUTRO schema, legítimos como proposta e inválidos como contrato do repo".

**Mas a 03a não acompanhou.** A ÂNCORA dela ainda diz:

```
contrato governance/design/contracts/governance-cockpit.contract.json (desce na thread 01)
```

Esse arquivo **não vai existir**: a 01 agora cria `design-docs/contrato-cowork/governance.contract.json`
— outra pasta **e** outro nome. Quem abrir a 03a procura um arquivo que ninguém criou, e a copy literal
dos UC sai justamente dele.

*(Nota lateral, medida: o `governance.contract.json` **tem** `alvo` e `secoes` — ao contrário dos 3 que
reprovaram em agosto. Mesmo assim fica invisível ao gate, porque a exclusão é por pasta, não por forma.
O título novo — "estágio" — já diz isso honestamente.)*

## 7 · Dois desvios de redação, pequenos e verificáveis

**(a)** O frontmatter da 01 diz `depois: 1 contrato advisory no CI`. Pelo §6 acima, ele fica **fora** do
CI, não advisory dentro dele — a pasta é excluída. O corpo da própria thread (B3, "não registrar no
`contrato-de-tela.yml` neste PR") é consistente com "fora"; só o frontmatter promete o que não ocorre.

**(b)** A 02 diz `antes: e2e/ tem 17 specs`. `e2e/` tem **17 arquivos**, dos quais **14** são `.spec.ts`
— os outros 3 são `README.md`, `alvo-de-toque-medicao.probe.ts` e `global-setup.ts`. A afirmação que
importa (**zero de governança**) é verdadeira.

## 8 · O gêmeo do `compliancePct` está órfão

O §5 residual #3 fala do `compliancePct` no singular. Ele está em **dois** sites vivos do mesmo arquivo:

| linha | método | emite |
|---|---|---|
| `DashboardController.php:65` | `index()` | `compliance_pct` |
| `DashboardController.php:268` | `buildKpisPayload()` | `compliance_pct` |

Nenhuma thread nomeia os dois. Quem for promover a soma a apurado e consertar "o" site deixa o outro —
é a lápide §5 de 2026-08-02 (o fix pousa na cópia que o consumidor não usa). Registrado aqui para não
virar "descoberta" futura.

---

### Resumo executável

Nada acima muda um byte do que o Cowork emitiu. Se [W] quiser fechar, a ordem barata é: **§3 + §4**
(duas linhas no §6, e o placar passa a dizer a verdade), **§6** (a âncora da 03a), depois §5 e §7.
