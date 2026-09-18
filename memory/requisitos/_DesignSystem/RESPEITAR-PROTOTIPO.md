---
id: requisitos-design-system-respeitar-prototipo
---

# Como respeitar o protótipo — a norma (fonte da verdade da tela)

> **O protótipo Cowork aprovado é a fonte da verdade da tela.** O código a segue.
> ~~Toda divergência ou é **DECLARADA** (autorizada) ou é **PEGA** (drift não-declarado).~~
> ⛔ **REVOGADO por [W] em 2026-09-18** (*"eu revogo tudo, de todos. a regra mudou, agora é o
> Protótipo quem manda, e a paridade deve ser o objetivo"*). **Não existe divergência autorizada
> no eixo FORMA** — existe **dívida a fechar**. Declarar um desvio agora **registra** a dívida;
> não a absolve. A frase riscada fica como registro do que valeu de 2026-07-12 a 2026-09-18;
> **não instrui mais nada**.
>
> A máquina que pega o drift é a **M1** (`scripts/governance/detect-ui-drift.mjs`), e ela segue
> valendo pelo que sempre fez de útil: **saber que a tela mudou**. O que mudou é o *significado*
> da declaração, não a detecção.
>
> Origem: Wagner 2026-07-11/12 — *"cada customização não é pega como alteração da máquina; parece que não sabe que alterou."* Esta norma + a M1 fazem a máquina **saber que alterou**.

---

## A cadeia da verdade

```
CHARTER            →  PROTÓTIPO (.jsx aprovado)  →  CÓDIGO (.tsx)  →  SCREENSHOT
declara a fonte       o visual, fonte da verdade    tem que seguir     Wagner aprova
(related_prototype)   da tela                       o protótipo        = juiz FINAL da estética
```

- A **camada superior herda da inferior e nunca a contradiz** (Constituição UI v2, [ADR UI-0013](adr/ui/0013-constituicao-ui-v2-camadas.md)).
- Se a tela **não tem protótipo bespoke**, a fonte é o **Padrão de Tela** que ela herda (PT-01..05) — também declarado no charter. "Fonte da verdade = protótipo bespoke SE existe, senão o Padrão de Tela." A M1 não distingue os dois: ela só exige que **toda mudança de UI seja justificada**.
- **Estética não é decidida por máquina.** Bonito/feio = teu screenshot (gate visual F1.5/F3, [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)/[0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)) + a comparação **medida** `design-diff.mjs` ([ADR 0299](../../decisions/0299-figma-nao-e-fonte-de-design.md)). A M1 só sabe se a mudança foi **autorizada**, não se ficou boa.

---

## Como se pede uma customização (SEM virar drift)

⛔ **Esta seção mudou de sentido em 2026-09-18.** Ela descrevia **duas** formas legítimas de o
`.tsx` divergir; hoje só a segunda é legítima no eixo FORMA. A primeira **não desapareceu** — ela
deixou de **absolver** e passou a **registrar dívida**.

| Você quer... | O que fazer (e o que a M1 aceita) |
|---|---|
| **Que a tela fique diferente do protótipo** | O caminho é **mudar o protótipo** no Cowork vivo e descer — não desviar dele no código. O protótipo é soberano na FORMA ([UI-0029](adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)); quem discorda dele discute com ele, não contorna. |
| **Aplicar/seguir o design** (fazer o código convergir pro protótipo aprovado) | (a) mude o `related_prototype` do charter pra apontar pro protótipo real, **OU** (b) registre a aplicação no `memory/reference/prototipo-ui/SYNC_LOG.md` citando a tela (o registro que o loop Cowork↔Code já usa). |
| **Não consegue fechar agora** (custo, raio, dependência) | `divergence_from_blueprint: "<razão real>"` no charter irmão, **neste PR** — mas leia o que ele significa hoje: **DÍVIDA A FECHAR registrada**, com a razão de ainda não ter fechado. Não é autorização, não prescreve, e não protege a tela numa leitura futura. |

> ~~**Desviar do protótipo** (um ajuste pontual autorizado) — `divergence_from_blueprint: "<razão
> real>"`. Ex: `"cliente pediu densidade maior na lista"`.~~ ⛔ **REVOGADO por [W] em 2026-09-18.**
> Fica como registro da redação de 2026-07-12; **não instrui mais nada**.

> ✅ **Ponta solta FECHADA em 2026-09-18 — a M1 já fala o vocabulário novo.**
> ~~`detect-ui-drift.mjs` classifica um `divergence_from_blueprint` com razão real como
> `CLEARED — desvio declarado`~~ — agora ele devolve um estado **próprio**, `DIVIDA`, e imprime
> **`◐`**, fora do balde de "limpas". A contagem do relatório passou a ser
> `limpas · dívida registrada · flags`.
>
> **O predicado NÃO mudou, e isso é deliberado:** a M1 mede **declaração**, nunca paridade — ela
> segue respondendo *"mudou sem declarar?"*, e declarar segue não sendo 🚩, porque é melhor que
> mudar em silêncio. O que mudou é que o caminho do desvio **deixou de contar como limpo**. Os
> outros dois caminhos (`related_prototype` fresco · `SYNC_LOG`) seguem **`✓` limpos** e a
> revogação não os toca: eles dizem *"estou seguindo o design"*, não *"estou desviando"*.
> Promovê-la a bloqueante continua sendo ato [W] via `gates-registry.json` `promote_by`.
>
> Provado **rodando a máquina**, não revisando o texto ([LC-22](../../LICOES_CODE.md)): CLI de
> fora em 3 casos — razão real ⇒ `dívida 1 / limpas 0` · sem sinal ⇒ `flags 1` · placeholder
> `"none"` ⇒ `flags 1` (não vira dívida). Mais mutação: revertido o estado para `CLEARED`, **2
> asserts caem**; restaurado, verde. **O campo tem adoção real** — 37 charters o declaram, 33 com
> razão escrita (medido 2026-09-18) —, então nada disso apaga o que já está declarado: aquelas
> telas passam a aparecer como **dívida aberta**, que é o retrato correto.

> ✅ **Segunda ponta FECHADA no mesmo dia — `reconcile-triplet.mjs`.** O estado interno
> ~~`DIVERGENCIA_DECLARADA`~~ virou **`DIVIDA_REGISTRADA`**, o badge ~~`~ DIVERGÊNCIA DECLARADA`~~
> virou **`◐ DÍVIDA REGISTRADA`**, e a legenda deixou de dizer *"desvio consciente"* — agora diz
> que **registra a dívida, não a autoriza**. Provado rodando o comando do CI (`--all`): o badge
> novo aparece em **26** slots, contra 1.149 `✓ CONFORME` e 107 `✗ DIVERGÊNCIA MUDA`. O
> `reconcile-triplet.test.mjs` foi atualizado com **controle** — se o estado voltar a se chamar
> `DIVERGENCIA_DECLARADA` ou `CONFORME`, o assert cai.
>
> ⚠️ **O derivado `produto-index-setor-matrix.md` ainda mostra o texto ANTIGO, e é de propósito.**
> O gerador já está certo; o arquivo só muda quando alguém rodar `--write`. Não o regerei aqui
> porque **descobri, rodando, que o regen APAGA o `id:` do frontmatter** — campo que o
> `doc-id-stamp.mjs` carimba e que o `doc-id-index` trata como `STAMPED; sobrevive a move de
> path`. São **dois produtores do mesmo arquivo**, e decidir qual manda é escopo próprio, não
> carona de um PR de vocabulário. (O regen também traria `gerado_em` novo e os slots do protótipo
> saindo de `AUSENTE` para valores reais — mudanças verdadeiras, mas de outro assunto.)
> ⚠️ Nota de contexto medida no mesmo dia: `doc-id-index --check` **já falha em `origin/main`
> limpo** (conferido em worktree separado) — drift pré-existente, de outro dono.

> ⚠️ **Editar o código direto, sem declarar, é o drift** — a M1 pega. Não é "a máquina te barrando": é a máquina **sabendo que você alterou** e pedindo o porquê. Advisory (não bloqueia) — é aviso, não muro.

**Placeholders não contam** (L-24 *presença ≠ correção*, [proibicoes §5](../../proibicoes.md)): `divergence_from_blueprint: "none"` ou `related_prototype: n/a (herda PT-01)` **não** limpam o flag — a M1 mede o **valor semântico**, não "a linha apareceu no diff". E o sinal tem que ser **fresco** (tocado neste PR): uma linha velha de desvio não cega a tela pra sempre.

---

## As máquinas (detecção + documentação — NUNCA auto-editam a tela)

| Máquina | Pergunta que responde | Arquivo | Estado |
|---|---|---|---|
| **M1 — autorização** | "essa `.tsx` mudou **sem declaração**?" | `scripts/governance/detect-ui-drift.mjs` + `.github/workflows/detect-ui-drift.yml` | advisory, visível (`::warning::` + job summary) |
| **M2 — verdade visual** | "a tela viva **bate** com o `.jsx` aprovado?" (medido, não no olho) | `scripts/design/design-diff.mjs` ([ADR 0299](../../decisions/0299-figma-nao-e-fonte-de-design.md)) + skill `comparar-design-prod` | existente; teu olho é o juiz |
| design-spec-gate | "o **QUE** mudou estruturalmente?" (ortogonal à M1) | `scripts/design-spec-gen.mjs` ([ADR 0255](../../decisions/0255-contrato-view-deterministico-charter-design-spec.md)) | por-tela |

**Como aplicar um protótipo** (o *como*, não a norma): [`RUNBOOK-replicar-prototipo-cowork.md`](RUNBOOK-replicar-prototipo-cowork.md) + skill `aplicar-prototipo`.

---

## Rodar a M1

```bash
npm run ui-drift:check          # roda contra o diff base...HEAD (default base origin/main)
npm run ui-drift:selftest       # prova que morde e libera (13 casos)
node scripts/governance/detect-ui-drift.mjs --base=<ref> --json
```

No CI: `detect-ui-drift.yml` roda em todo PR que toca `Pages/**/*.tsx|*.charter.md` ou `SYNC_LOG.md`, emite `::warning::` por tela em drift + resumo no job. **Advisory** por [ADR 0314](../../decisions/0314-poda-gates-onda-2-lei-fusoes.md) (autorização de UI é *quality*, não Tier-0). Promoção a `--strict` (bloqueante honesto) decidida por Wagner via `gates-registry.json` `promote_by` — a asserção "mudou sem declaração" é verdadeira-ou-falsa, nunca teatro.

---

**Escopo v1 (honesto):** a M1 cobre telas com **charter irmão** (`<Tela>.charter.md`). Um `.tsx` sem charter irmão (ex: `_components/`) vira **nota** advisory, não flag — vetor de drift real, marcado como gap conhecido de v1 (fechar em v2 atribuindo componente ao charter-tela ancestral).

**Última atualização:** 2026-09-18 — **[W] revogou a "divergência DECLARADA (autorizada)"**: no eixo
FORMA não há desvio aceito, há **dívida a fechar**, e a paridade com o protótipo é o objetivo
([UI-0029](adr/ui/0029-prototipo-soberano-sobre-adr-ui.md), ratificada em 2026-08-31). O texto antigo
fica riscado, não apagado. **As duas máquinas foram alinhadas no mesmo dia:** a M1
(`detect-ui-drift`) passou a devolver o estado **`DIVIDA`** (`◐`, fora das "limpas") no caminho do
desvio, e o `reconcile-triplet` deixou de chamar a divergência de "desvio consciente" — as duas
provadas rodando, não revisando. **Fora do eixo, intactos:** **visibilidade** (permissão/pacote/módulo), **dado** e
**comportamento** seguem do código, pela regra de precedência de [proibicoes.md](../../proibicoes.md)
— esta revogação é só sobre FORMA.

**2026-07-12** — norma criada + M1 (detector de mudança de UI não-declarada). Reusa `fmScalar` (reconcile-triplet), forma diff-aware (design-return-gate), SYNC_LOG (loop Cowork↔Code) — zero vocabulário novo.
