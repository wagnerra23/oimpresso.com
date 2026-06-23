# APRENDER-COM-ERRO — Loop de graduação de lição (camada 5 ativa)

> **Status:** proposta de [CC] · soberania [W].
> **O que é:** o mecanismo que faz erro virar **imunidade**, não só registro. Fecha as 5 camadas (Intake → Frescor → Crítica/Auditoria → Humano → **Lição ativa**).
> **De onde vem:** o princípio JÁ existe na sua memória — **L-16** ("quem cobra falta/stale = a MÁQUINA, nunca [W]") e **L-17** (melhoria = proposta + soberania). Esta regra só torna a graduação **obrigatória e periódica**.
> **A verdade que ela encara:** somos stateless. "Aprender" não é lembrar — é **mudar o ambiente**: ou o erro vira um **check que barra**, ou vira uma **regra num arquivo que eu SEMPRE leio**. Lição que só vive num log que ninguém relê **não foi aprendida — foi arquivada**.

---

## Princípio

> **Toda lição tem que graduar: virar CHECK (mecânico) ou virar REGRA-CARREGADA (julgamento). Log que só cresce é cemitério.**
> Transporte (zero-toque) entrega o arquivo; **graduação** é que transfere conhecimento. O conhecimento só persiste em (a) check que sempre roda, (b) arquivo sempre-lido no momento certo. Não há terceiro lugar.

---

## O loop — 3 estágios

### 1. Registrar (já existe)
Erro → entrada `L-NN` em `LICOES_CC.md` no formato **Erro · Sintoma · Regra · Ref**. (Não muda.)

### 2. Triar + Graduar (NOVO — obrigatório por entrada)
Toda `L-NN` ganha um campo final **`Graduação:`** com classe + destino + status:

- **MECANIZÁVEL** → `→ CHECK: <nome/gate>` · vira lint/Pest/ratchet/CI-gate. O erro **morre** (impossível de mergear). Se o check não existe → abre ponte pro [CL] construir (tooling = Tier 0).
- **JULGAMENTO** → `→ REGRA-CARREGADA: <arquivo sempre-lido · onde>` · entra no conjunto que eu leio no momento certo (ritual CLAUDE.md, Regra de Ouro, Ficha de Contexto, charter da tela). Não num log à parte.
- **`status: graduada | pendente`**

**Classificador (qual é qual):**
| Mecanizável (vira check) | Julgamento (vira regra-carregada) |
|---|---|
| detectável por grep / AST / diff / CI **sem entender intenção** | exige ler intenção / gosto / contexto |
| ex.: cor fora de token · arquivo novo na raiz · doc `stale` · ADR duplicado · delete sem lápide · PR toca lei sem flag | ex.: "venda ≠ POS" · "proposta ≠ firme" · "não afirmar commit" · "reler a lei antes" |

### 3. Colher (NOVO — periódico, no `sync now`)
Varrer `L-NN` com `status: pendente`:
- mecanizável sem check ainda → vira **proposta de check** pro [CL] (ou entrada na Regra de Ouro, se for higiene Cowork-only — ver matriz abaixo);
- graduada → **comprimir** a entrada (1 linha + ponteiro pro check) pra o log não inchar.
→ Um log colhido fica **curto e vivo**; um log append-only puro morre de tamanho.

---

## ONDE a graduação aterrissa (não bridge por reflexo — L-20)

| Onde o erro pode ocorrer | Destino da graduação | Vai pro git? |
|---|---|---|
| Artefato **git** (cor, charter, ADR, doc stale, lei) | **CI check** no repo (lint/Pest/ratchet) | Sim — ponte pro [CL] (Tier 0, espera [W]) |
| **Julgamento** meu (escopo, gosto, fidelidade) | **Regra-carregada** em arquivo sempre-lido | Charter/PROTOCOL = sim; ritual local = não |
| Higiene do **Cowork** (html na raiz, canal novo, lápide) | **Regra de Ouro pre-flight** (sem CI aqui) + health-check local | Não — é Cowork-only (L-13/L-20) |

> Regra anti-reflexo: **nem toda lição vira check no git.** Higiene Cowork fica no pre-flight; só o que é repo-bound transporta.

---

## GATE

- ❌ **`L-NN` sem `Graduação:` = incompleta** (igual dossiê sem commit). Lição que só registra e não gradua **vai repetir**.
- ❌ Mecanizável que **dá** pra checar e não tem check → fica `pendente` e entra na próxima colheita; não "confio que vou lembrar".
- ✅ Override de alta-confiança (L-17 §4) é **logado** — o log de override alimenta a triagem (override errado reincidente → vira check).

---

## Por que isto responde [W] ("zero-toque não é transferência")

- **Transferência real** = lição graduada: ou o `charter_stale`/`ui:lint` barra o [CL] sozinho, ou a regra está no `CLAUDE.md`/`PROTOCOL` que ele lê ao abrir o repo.
- **O prompt zero-toque** só entrega o **código do check** — a transferência é o check existir e rodar, não o prompt.
- **"Não errar mais"** = o erro mecânico fica **impossível** (check) e o de julgamento fica **na minha frente** na hora certa (regra-carregada). Não depende de memória.

Ver a **colheita retroativa das 22 lições** no rodapé de `LICOES_CC.md` (§ Graduação 2026-06-01).
