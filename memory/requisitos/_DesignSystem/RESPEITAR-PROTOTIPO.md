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

> ⚠️ **Ponta solta declarada, NÃO consertada neste PR — a M1 ainda fala o vocabulário revogado.**
> `detect-ui-drift.mjs` classifica um `divergence_from_blueprint` com razão real como
> `CLEARED — desvio declarado` (L129-131), e o docblock dela abre com *"Duas formas legítimas…
> 1. DESVIO DECLARADO"* (L15-18). **O campo tem adoção real:** 37 charters o declaram, **33 com
> razão escrita** (medido 2026-09-18, `rg -g '*.charter.md'` em `resources/` + `Modules/`) — logo
> não é letra morta que se apague de passagem. A detecção segue correta; o que envelheceu é a
> palavra `CLEARED` e a moldura de "legítimo". Reescrever isso é **PR próprio**: mexe em máquina
> que roda no CI, e vale a regra de sempre — [LC-22](../../LICOES_CODE.md), doc que a máquina lê
> se valida **rodando a máquina**, não revisando o texto.

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
fica riscado, não apagado. **Ponta solta nomeada:** a M1 ainda emite `CLEARED — desvio declarado`
(PR próprio). **Fora do eixo, intactos:** **visibilidade** (permissão/pacote/módulo), **dado** e
**comportamento** seguem do código, pela regra de precedência de [proibicoes.md](../../proibicoes.md)
— esta revogação é só sobre FORMA.

**2026-07-12** — norma criada + M1 (detector de mudança de UI não-declarada). Reusa `fmScalar` (reconcile-triplet), forma diff-aware (design-return-gate), SYNC_LOG (loop Cowork↔Code) — zero vocabulário novo.
