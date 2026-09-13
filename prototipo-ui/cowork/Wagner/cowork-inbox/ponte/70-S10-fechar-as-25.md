---
sessao: S10
titulo: Fechar as 25 — de 29 para 54 telas prontas
autor: "[CC]"
criado: 2026-08-23
base: medição [CL] 2026-08-23 (readiness 29/54)
regra: sessão FRESCA — não herda contexto; lê a read-order do main e só depois age
---

# S10 · A lane do gargalo medido

## Contrato de paralelismo (Lei 1 — `03-REGRAS-DE-PARALELISMO.md`)

| | |
|---|---|
| **Sessão** | S10 (uma thread por MÓDULO — nunca duas no mesmo prefixo) |
| **Escreve em** | `resources/js/Pages/<Modulo>/**` do módulo da onda + `Modules/<Mod>/Resources/js/Pages/**` |
| **NÃO toca** | `Pages/Ponto/**` enquanto S2 estiver aberta · `contrato/` (é do S5) |
| **Estado** | só em `_saida-S10-<modulo>.md` |

## Read-order obrigatória (do `main`, nunca de cópia local)
1. `CLAUDE.md` · `prototipo-ui/PROTOCOL.md` · `prototipo-ui/PRE-FLIGHT-TELA.md`
2. `memory/INDEX.md` + `memory/proibicoes.md` + `memory/LICOES_CC.md`
3. `scripts/qa/prototipo-readiness.mjs` — **o critério exato de ✅**, não a minha descrição dele
4. `scripts/governance/charter-live-signal.mjs` — o oráculo de tela-em-produção (é **required**)
5. O charter de cada tela da onda
6. `cowork-inbox/ponte/06-CORRECAO-MEDIDA.md` — por que esta lane é P1

## Pré-requisitos (sem estes dois, a sessão só lê)
| # | O que | Dono | Estado |
|---|---|---|---|
| C.01 | **Lista nominal das 25** — saída completa do `prototipo-readiness.mjs` | [CL] | ⬜ |
| C.02 | **Critério do scorecard** — formato, campos, onde mora, como é validado | [CL] | ⬜ |

> Se C.01/C.02 não chegaram: **não invente**. Rode o passo 1 (abaixo) e pare. Escrever casos contra critério não-lido é o erro que já cometi neste projeto — nota datada tratada como estado.

## Processo por onda (um módulo por thread)
1. **Ler o critério** (passos 3 e 4 da read-order) e escrever, no `_saida`, o critério de ✅ **em uma frase verificável**. Se não couber numa frase, é porque não foi entendido.
2. Recortar a onda: as telas das 25 que pertencem a **um** prefixo.
3. Por tela: ler o charter → escrever `casos.md` no padrão do `main` (frontmatter · Rastreabilidade · UC com Dado/Quando/Então · `[BACKLOG]`).
4. UC de tenant leva `[T0]` + ADR 0093 + "biz=1 vs fictício, **nunca biz=4**" (ADR 0101).
5. **Nenhum UC ✅ sem lane executada** — nasce `⬜`; `last_run_ci` diz a verdade.
6. Scorecard conforme C.02.
7. Reconferir readiness: a onda **só fecha** se a contagem subir. Se não subiu, o artefato não atendeu o critério — reportar o que faltou, não insistir.
8. `_saida-S10-<modulo>.md`.

## Critério de pronto da onda
- [ ] critério de ✅ escrito em uma frase, do código
- [ ] casos.md de todas as telas da onda, padrão do `main`
- [ ] scorecard de todas
- [ ] readiness **subiu** — com o número antes e depois
- [ ] nenhum UC ✅ sem veredito
- [ ] `_saida` escrito

## Não fazer
❌ Não escrever `prototipo-ui/PRODUCAO.md` — o `charter-live-signal.mjs` já é o dono, e é required. Segundo dono = drift.
❌ Não mexer em `status:` de charter — virar `live` é ato de [W].
❌ Não alterar copy nem ordem de contrato.
❌ Não tocar `route-hits.json` — é C.06, dono [W]/[CL].
❌ Não abrir duas threads S10 no mesmo prefixo.

## Métrica única desta lane
```
readiness: 29 / 54   →   objetivo 54 / 54
```
Toda onda reporta o par antes→depois. É o único número que diz se esta lane funcionou.
