---
title: "Fase Debate (AGREE/CHALLENGE/CONNECT/SURFACE) — plugar nos workflows adversariais"
owner: W
status: ativo
last_validated: "2026-07-13"
preconditions:
  - "Workflow adversarial que hoje faz refutador/ataque → juiz DIRETO (sem debate cruzado)"
  - "Achados de cada cético identificáveis por { id único, autor, texto }"
steps:
  - "Copiar o bloco BEGIN/END TEMPLATE de .claude/workflows/debate-adversarial.js"
  - "Chamar faseDebate(achados, opts) ENTRE a fase de refutação e a de juiz"
  - "Passar debatido + surfaces ao juiz (não os achados crus)"
  - "Rodar 1× pra validar que a fase produz reações tipadas"
---

# RUNBOOK — Fase Debate adversarial (roubada do Open Code Review)

> **Origem:** pesquisa OSS 2026-07-13 (`deep-research` wf_53aa5343 —
> [session log](../../sessions/2026-07-13-arte-oss-comparavel-ia-os.md)). Das peças roubáveis
> do mercado, o veredito honesto foi: **só esta vale roubar, e é barata** — o resto (ratchet
> Betterer, policy-as-code OPA, rastreabilidade Sphinx-Needs) já temos equivalente funcional,
> migrar = retrabalho. Fonte: **Open Code Review** (spencermarx/open-code-review, Apache-2.0,
> ~299★) + paper OpenReview *"Adversarial Review: Cooperative Code Review through Structured
> Disagreement"*. **Roubo = adaptar o padrão, NÃO instalar a ferramenta** (o OCR é
> code-review-only; a gente quer o protocolo genérico no nosso harness `Workflow`).

## O problema que resolve

Hoje os workflows adversariais (`sdd-avaliador-processo.js`, `scripts/adr-0296-adversarial-*.js`)
fazem **refutador → juiz DIRETO**: cada cético julga o seu slice isolado, o juiz agrega. Falta a
camada de **debate cruzado** entre os céticos. Isso é a **falácia de composição** — julgar
slice-a-slice fabrica um "0 acima" / "0 bug" falso, porque a falha pode estar **na costura entre os
slices**, que nenhum revisor sozinho olha. Foi exatamente o que o adversário da grade de réguas
pegou em 2026-07-10, e virou a **regra dura #7** da skill `reguas-do-sistema` (hoje aplicada só à
Fase `Integração` daquela grade, à mão). O protocolo AGREE/CHALLENGE/CONNECT/SURFACE é o **remédio
genérico** disso — mata a falácia **por construção**, não por regra manual.

## O padrão OCR

```
… → [refutar/atacar em paralelo] → [DEBATE cruzado] → [juiz/síntese] → veredito
                                        ↑ a camada nova
```

Entre a revisão paralela e a síntese, o OCR mete uma **fase de discourse**: cada revisor vê os
achados **de TODOS os outros** (não só o próprio) e emite reações tipadas. "Pega falso-positivo que
revisor único perderia, fortalece achado válido, e revela o que ninguém veria sozinho." Os 4 modos:

| Modo | Significado | Alvo/campos |
|---|---|---|
| **AGREE** | Concordo e **reforço com evidência NOVA** (segundo caminho/prova) | `alvo` = id do achado; `razao` |
| **CHALLENGE** | Refuto/duvido da premissa ou do raciocínio (default cético) | `alvo` = id do achado; `razao` |
| **CONNECT** | Ligo dois achados; digo que padrão emerge da ligação | `alvo` + `alvo_secundario`; `razao` |
| **SURFACE** | Levanto o que **ninguém viu** — esp. a falha do **TODO integrado** | `alvo="TODO"`; `novo_achado` |

**O `SURFACE` com `alvo="TODO"` é o coração anti-falácia**: obriga cada cético a olhar os achados
JUNTOS e perguntar "qual falha do conjunto integrado nenhum slice pegou?". É o "e o TODO?" da regra
dura #7, agora **forçado pelo schema**, não confiado à disciplina manual.

## Como plugar (3 passos)

1. **Copie** o bloco entre `BEGIN TEMPLATE` e `END TEMPLATE` de
   [`.claude/workflows/debate-adversarial.js`](../../../.claude/workflows/debate-adversarial.js)
   pro seu script adversarial. Ele traz `REACAO_SCHEMA` + a função `faseDebate(achados, opts)` e
   depende só dos globais do harness (`agent`/`parallel`/`log`) — **nada a importar** (o harness
   `Workflow` roda scripts self-contained; por isso o template é **colável**, não um módulo).
2. **Chame entre a refutação e o juiz.** Normalize os achados dos seus céticos pra `{ id, autor,
   texto }` (id curto e único; autor = quem achou):
   ```js
   const { debatido, surfaces } = await faseDebate(achados, { foco: 'a avaliação do stream X', effort: 'high' })
   ```
3. **Alimente o juiz com o `debatido` + `surfaces`, NÃO com os achados crus.** Cada `debatido[i]`
   ganha `agree/challenge/connect` + um `sinal` (`CONTESTADO` se desafios > concordâncias,
   `REFORCADO` se houve agree, senão `NEUTRO`). O juiz deve: derrubar `CONTESTADO` sem defesa,
   promover `REFORCADO`, e **incorporar cada `SURFACE` do TODO** (é a falha de composição).

## Contrato

- **Entrada:** `achados` = `Array<{ id: string, autor: string, texto: string }>` (ids únicos).
- **Saída:** `{ achados, reacoes, debatido, surfaces }`.
  - `reacoes` = todas as reações tipadas (com `por` = quem reagiu).
  - `debatido` = achados enriquecidos com reações + `sinal`.
  - `surfaces` = os `SURFACE` (incl. `alvo="TODO"`) + reações órfãs (alvo inexistente).
- **Degenerescência:** `< 2` achados → retorna sem debate (não há o que cruzar).
- **Debatedores:** 1 por autor distinto (fallback: 1 por achado se não houver autores).

## Onde plugar primeiro (candidatos)

| Workflow | Fase antes → depois | Ganho |
|---|---|---|
| `sdd-avaliador-processo.js` | entre `Avaliar` (7 streams) e `Síntese` | hoje 7 streams isolados → agrega; o SURFACE pega risco sistêmico **entre** streams |
| `reguas-do-sistema.js` | a Fase `Integração` já é um debate manual da falácia; migrar pro protocolo tipado unifica | menos prompt ad-hoc, mesmo efeito |
| `scripts/adr-0296-adversarial-*.js` | entre atacantes e juiz | idem — cruzar ataques antes de julgar |

> **Não reescrever todos de uma vez** (commit-discipline). Um exemplar + template; migrar cada
> workflow é um chip próprio quando doer. O `debate-adversarial.js` é o exemplar **runnable**:
> `Workflow({ scriptPath: ".claude/workflows/debate-adversarial.js" })` roda o demo barato
> (3 lentes → debate → juiz) e prova que a fase emite reações tipadas.

## Anti-padrões

- ❌ Passar achados **crus** ao juiz "porque o debate já rodou" — o valor está no `debatido`+`surfaces`.
- ❌ Cada debatedor reagir **só ao próprio slice** — o prompt manda priorizar os achados dos OUTROS; sem isso não há debate, é refutação renomeada.
- ❌ Descartar `SURFACE alvo="TODO"` no juiz — é justamente a falha de composição; derrubá-lo re-abre o buraco da regra dura #7.
- ❌ Instalar/depender do OCR (é code-review-only). Roubamos o **padrão**, não a ferramenta.
