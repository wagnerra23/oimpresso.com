---
sessao: "03"
titulo: Regras de paralelismo — dono por prefixo, zero conflito
autor: "[CC]"
criado: 2026-08-23
regra: LER ANTES de abrir qualquer sessão. Sessão que escreve fora do seu prefixo está errada, mesmo que o conteúdo esteja certo.
---

# Máximo rendimento — 4 threads em paralelo, sem colisão

## Lei 1 — Um dono por prefixo. Sem exceção.

| Prefixo | Dono único | Ninguém mais escreve |
|---|---|---|
| `prototipo-ui/cowork/` | **S1** | S5, S9 |
| `prototipo-ui/contrato/` | **S5** | S1 cede após 1.11 |
| `resources/js/Pages/Ponto/**` | **S2** | S5, S9 |
| `resources/js/Pages/**` (não-Ponto) | **S9 / S10** | S2 · outra thread S10 do mesmo prefixo |
| `Modules/Whatsapp/**` | **S9b** | — |
| `cowork-inbox/` (curadoria) | **S3** | — |
| nada (read-only) | **S4** | — |
| `_saida-S<n>.md` | a própria sessão | todas as outras |

**Exceção resolvida:** S1 escreve 3 contratos *antes* de S5 existir. Depois de 1.11 mergeado, `contrato/` passa a ser de S5. Se as duas rodarem juntas: S1 fica com os 3 nomes dele, S5 não toca neles.

## Lei 2 — Estado não mora em arquivo compartilhado.

A coluna **Estado** da `01-LISTA-COMPLETA.md` é **somente-leitura** durante a execução. Cada sessão escreve só o seu `_saida-S<n>.md`. A consolidação é uma passada única, depois, por uma sessão só (S0-consolida).

> Motivo: 6 sessões editando a mesma tabela = 6 conflitos garantidos, todos triviais e todos caros.

## Lei 3 — Um PR por sessão. Dois PRs nunca no mesmo prefixo.

Eu não escrevo no git: o conflito real acontece na **sua colagem**. Dois PRs tocando `Pages/Ponto/` fazem merge sujo mesmo com sessões impecáveis. Ordem de colagem = ordem de conclusão, uma por vez.

## Lei 4 — Arquivos proibidos a todas as sessões

`github.md` · `memory/LICOES_CC.md` · `memory/INDEX.md` · `governance/required-checks-baseline.json` · `COWORK_NOTES.md` · `01-LISTA-COMPLETA.md`

Quem precisar mexer: **propõe no `_saida`**, não edita. São os arquivos que todo mundo quer tocar — por isso ninguém toca.

---

## O escalonamento de máximo rendimento

### Vaga 1 — hoje, 4 threads simultâneas (zero dependência entre elas)

| Thread | Sessão | Prefixo | Duração | Bloqueia? |
|---|---|---|---|---|
| T1 | **S1** build mecânico | `cowork/` + 3 contratos | curta | não |
| T2 | **S2** trio órfão Ponto | `Pages/Ponto/**` | longa (13 arquivos) | não |
| T3 | **S4** diff resíduo | read-only | média | não |
| T4 | **S3** curadoria | `cowork-inbox/` | precisa de [W] ao vivo | **destrava 3.25** |

**Por que estas quatro:** prefixos disjuntos, nenhuma lê a saída da outra. É o máximo de paralelismo que a estrutura permite sem inventar coordenação.

### Vaga 1b — **P1, entra assim que C.01/C.02 chegarem** (n threads, 1 por módulo)

`S10` é a lane do gargalo medido (29→54). Uma thread por módulo, prefixos disjuntos por construção. Não espera vaga: **fura a fila**, porque é a única métrica que se move sem decisão [W].
Regra: enquanto S2 estiver aberta, **nenhuma thread S10 toca `Pages/Ponto/**`**.

### Vaga 2 — após S1 e S2 mergeados, 3 threads

| Thread | Sessão | Prefixo |
|---|---|---|
| T1 | **S5** contratos | `contrato/` + 2 casos nomeados |
| T2 | **S9.02** Whatsapp/Atendimento (8 telas) | `Modules/Whatsapp/**` |
| T3 | **S9.03** Sells/PDV (5 telas) | `Pages/Sells/**` |

S9.01 (Ponto) **não entra aqui** — mesmo prefixo do S2. Entra na vaga 3.

### Vaga 3 — 3 threads

| Thread | Sessão |
|---|---|
| T1 | **S9.01** Ponto (9 telas) — prefixo liberado |
| T2 | **S9.07** governance (8) |
| T3 | **S9.08** Purchase + Stock* (8) + A.01 |

### Vaga 4 — série obrigatória
**S6** (implantação) → **S7** (pós-merge) → **S8** (encerramento). Não paralelizam: cada uma valida a anterior no ambiente vivo.

### Fora de vaga — quando [W] quiser
S9.04 Repair (13) · S9.05 Essentials (13) · S9.06 Forja (12) — dependem de 9.12 (a meta de cobertura).

---

## Rendimento estimado

| Vaga | Threads | Telas/arquivos movidos | Decisão [W] no caminho |
|---|---|---|---|
| 1 | 4 | 5 + 13 arquivos + 3 listas de diff + 25 vereditos | 3.25 |
| 2 | 3 | 2 casos + 13 telas | 5.04, 5.17 |
| 3 | 3 | 25 telas | 9.09 |
| 4 | 1 (série) | 11 telas implantadas | 7.08, 7.12 |

**Caminho crítico real:** não é volume, é **[W]**. Vaga 1 termina em horas de trabalho meu; 3.25 pode levar dias. Por isso T4 (curadoria) entra **junto** com as outras, não depois — é a thread mais lenta e tem que começar primeiro.

---

## Protocolo de abertura de thread (colar como 1ª mensagem)

```
Sessão fresca. Leia nesta ordem, do main, nunca de cópia local:
1. cowork-inbox/ponte/03-REGRAS-DE-PARALELISMO.md  ← seu prefixo e o que é proibido
2. cowork-inbox/ponte/<arquivo-da-sessão>.md        ← seu escopo
3. a read-order do main que o arquivo da sessão manda ler

Você escreve SOMENTE dentro do seu prefixo e SOMENTE seu _saida-S<n>.md.
Não edite a lista completa, o github.md, nem a memória.
Terminou: escreva _saida-S<n>.md e pare.
```

## Protocolo de fechamento (o que todo `_saida` tem)

1. **Feito** — arquivos por caminho completo
2. **Não feito e por quê** — sem eufemismo
3. **Pedido literal pro [CL]/[W]** — colável, sem edição
4. **Descobertas que mudam outra sessão** — a única coisa que atravessa threads
5. **Prefixo tocado** — para conferir que respeitou a Lei 1

---

## S0 · Consolidação (roda uma vez, no fim de cada vaga)

| # | Processo |
|---|---|
| 0.01 | Ler todos os `_saida-S<n>.md` da vaga |
| 0.02 | Atualizar a coluna Estado da `01-LISTA-COMPLETA.md` — **única sessão autorizada** |
| 0.03 | Colher os item-4 ("descobertas") e reescrever os arquivos de sessão afetados |
| 0.04 | Recalcular o caminho crítico (ele muda a cada vaga) |
| 0.05 | Declarar a próxima vaga aberta |
