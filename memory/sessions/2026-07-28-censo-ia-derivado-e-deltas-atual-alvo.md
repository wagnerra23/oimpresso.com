---
date: "2026-07-28"
hour: "18:40 BRT"
duration: "5h"
topic: "Censo da camada de IA derivado no system-map, a reincidência que a revisão adversarial pegou, e os 6 deltas atual→alvo"
authors: [W, C]
outcomes:
  - "Censo da camada de IA derivado por CONTRATO (implements), não por pasta — PR #4973 mergeado"
  - "A 1ª entrega reincidiu na própria classe: contou pasta de 1 módulo (39) para descrever registro de 3 (44)"
  - "Self-test do ORÁCULO, não só do classificador — era a lacuna que deixou o bug passar (19 asserts)"
  - "Achado de bug: no chat streaming os tokens são gravados na mensagem do turno anterior"
  - "Doutrina da Jana e as 5 decisões em aberto sumiram do repo com a geração do ARCHITECTURE — resgatadas em proposal"
  - "5 sessões paralelas abertas com áreas de arquivo isoladas"
prs: [4973, 4976]
us:  []
related_adrs:
  - "0035-stack-ai-canonica-wagner-2026-04-26"
  - "0053-mcp-server-governanca-como-produto"
  - "0256-knowledge-survival-meia-vida-catraca-sentinela"
---

# Session log 2026-07-28 — o censo que se conta sozinho, e o que ele pegou

## TL;DR

[W] pediu um mapa técnico da IA. Ao montar, os números que escrevi à mão estavam **errados** — 14
agentes eram 22, 16 provedores eram 15. A correção certa não foi corrigir os números: foi tirar a mão
do meio. O `system-map.mjs` passou a derivar o censo da árvore ([PR #4973](https://github.com/wagnerra23/oimpresso.com/pull/4973), mergeado).

Aí veio a parte instrutiva: **a primeira entrega do contador reincidiu na classe que ela existia para
matar.** Publicou `Tools MCP expostas: 39` medindo a pasta de um módulo, quando o servidor registra
44 em três. Passou `--check` verde, self-test verde, deadlink verde. Número errado com selo de
"derivado" é pior que número errado à mão — o leitor perde o direito de duvidar.

Quem pegou foi revisão adversarial de dois céticos independentes. Nenhum gate pegaria: o self-test
cobria o **classificador** e não o **oráculo** (a função que escolhe a fonte não era exportada, logo
era intestável por construção).

## O que ficou de máquina

- Censo por **contrato** (`implements Agent`), com a varredura por pasta como contra-medida; a
  divergência entre as duas vira alarme que **nomeia o arquivo**.
- Tools por **registro** (`$tools` do servidor), arquivos como contra-medida.
- Rótulo `registradas`, nunca `expostas` — exposição é runtime (`MCP_TOOLS_EXPOSED`); no Hostinger é
  zero. Dizer "expostas" a partir de arquivo é presence-gate.
- Sem `git grep`, emite **"não medido"**, nunca `0`.
- 19 asserts, com bite-test do erro real (`caching.embeddings` não é provider) e controles negativos.
- Gatilho do workflow ampliado para acordar quando alguém mexe em IA — com o **resíduo declarado** em
  comentário: agente fora da convenção só é pego pelo cron.

## Achados que viraram trabalho

| Achado | Como apareceu |
|---|---|
| Tokens do chat gravados na mensagem do **turno anterior** | cético adversarial; confirmado por leitura própria — o comentário do controlador documenta a dependência herdada |
| 5 tabelas órfãs + 1 só-seeder + 1 referenciada **sem migração** | varredura das 58 tabelas |
| Só **2** tabelas têm imutabilidade real; outras prometem em comentário | `grep` dos triggers |
| 4 flags lidas fora de `config/` — uma **desliga a redação de PII** | inventário das 46 chaves |
| Erro de infra indistinguível de "não achei" (2 módulos) | leitura dos caminhos de falha |

Cinco viraram sessão paralela, com áreas de arquivo isoladas declaradas em cada prompt.

## A perda que quase passou

O [#4975](https://github.com/wagnerra23/oimpresso.com/pull/4975) — sessão paralela, mesmo dia — fez
`Jana/ARCHITECTURE.md` virar **gerado**. Direção certa. Mas o anterior era **curado**, e duas peças
não-deriváveis sumiram do repositório inteiro:

```bash
git grep -il "não é BI tradicional" origin/main -- memory/   # → 0
git grep -il "trajetória projetada"  origin/main -- memory/  # → 0
```

A doutrina de posicionamento e as cinco decisões em aberto — as duas que a auditoria adversarial do
mesmo dia tinha marcado como "preservar". Resgatadas na
[proposal](../decisions/proposals/2026-07-28-camada-ia-atual-x-alvo-e-doutrina-resgatada.md)
([PR #4976](https://github.com/wagnerra23/oimpresso.com/pull/4976)), junto dos 6 deltas atual→alvo.

**Lição:** quando um documento curado vira gerado, a prosa não migra sozinha — e some sem alarme,
porque nenhum gate mede ausência de doutrina.

## Erros meus, registrados

- **LC-08 ocorrência 24** — censo contado à mão (14 agentes, 16 provedores, 2 pipelines de RAG).
  Corolário: *quando o número que você vai escrever pode ser contado, a correção certa é o contador,
  não o número.*
- **Emenda no mesmo dia** — a 1ª entrega do contador reincidiu. Corolário adicional: *fixture do
  classificador nunca pega erro de oráculo; teste também a escolha da fonte.*
- Publiquei os dois artefatos visuais **sem ver renderizado** — validei sintaxe e integridade das
  referências, não o resultado. O primeiro tinha nós ilegíveis; [W] apontou por print.

## Limite honesto

Tudo veio de leitura de código. **Nada foi medido contra banco ou runtime.** Onde se lê "roda", leia
"o código declara que roda". As contagens do dia valem para `origin/main` de 2026-07-28.
