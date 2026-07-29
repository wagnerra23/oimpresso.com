---
date: "2026-07-29"
hour: "10:30"
topic: "Catálogo de vistas publicadas — as 21 agrupadas pelo título foram abertas e lidas; 3 estavam no tema errado, 1 dúvida resolvida por prova literal"
authors: ["C", "W"]
outcomes:
  - "24/24 vistas lidas por dentro (eram 3)"
  - "3 vistas movidas de tema — todas por título enganoso"
  - "trilogia Mapa/Guia/Manual confirmada pelo rodapé literal"
  - "série temporal das grades confirmada + 2 correções de ordem/data"
prs: [5022]
related_adrs:
  - "0256-knowledge-survival-meia-vida-catraca-sentinela"
  - "0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento"
---

# Vistas publicadas: a dívida declarada era real, e o título mentia em todos os casos

**TL;DR** — O catálogo [`VISTAS-PUBLICADAS.md`](../reference/VISTAS-PUBLICADAS.md) declarava no próprio
corpo que **21 das 24 vistas estavam agrupadas pelo título, não pela leitura**. Todas foram abertas.
O agrupamento por título errou em **3 vistas de tema**, **2 de natureza** (plano ≠ retrato), **1 de
ordem** e **1 de dúvida em aberto** — e acertou onde tinha anotado dúvida menor. Nada foi apagado:
as 24 URLs saíram idênticas, conferidas por `diff`.

## O que motivou

A dívida estava escrita e datada no arquivo — não foi descoberta, foi **declarada**. Isso é o modelo
funcionando: o autor anterior escreveu *"é hipótese, não medição — pode haver surpresa ao abrir"* em
vez de apresentar o agrupamento como fato.

## O que a leitura achou

### 3 vistas no tema errado — o título soava como um tema que já existia

| Vista | Estava | É |
|---|---|---|
| **Máquina de entrada** | `viva` em *conhecimento e memória* | ingestão de sinal externo (reclamação, manual de concorrente, roteamento por dicionário de domínio) |
| **Memória do processo** | mesmo tema | auditoria adversarial de enforcement — 57 agentes, 13 categorias, 10 rejeitados que viraram §5 |
| **Arquitetura oimpresso** | *sistema inteiro* | manual de arquitetura **de código** (Blade×React, request, rotas, 3 camadas de permissão) |

A **Máquina de entrada** era a mais cara das três: marcada `viva` no tema errado, ela fazia as três
vistas legítimas de organização do conhecimento parecerem **históricas dela**. Uma leitura futura
concluiria que o manual de 07-23 estava superado por um documento que fala de outro assunto.

### Plano não é retrato — e não envelhece igual

Dois planos (*Plano do sistema inteiro* e *Plano de padronização de UI*, ambos de 07-20) estavam
espalhados: um marcado `histórica` no tema mapa, outro em "trabalho pontual". Viraram tema próprio,
ambos `viva`. O raciocínio: **mapa é superado por mapa mais novo; plano só é superado por execução ou
por outro plano.** Nenhum dos dois foi.

### A dúvida do manual × história: resolvida por prova, não por julgamento

O catálogo anotava: *"Ficam ambos vivos até alguém ler e decidir se são de fato temas separados."*

O rodapé do próprio manual responde, literal: **"o 3º da família (Mapa = o quê · Guia = como chegou ·
Manual = como operar)"** — e as três se linkam entre si no cabeçalho. São **temas distintos escritos
como trilogia** em 2026-07-12. A terceira perna é a `mapa-sistema-oimpresso`, que estava catalogada
noutro tema sem o elo registrado.

### Grades: a série está certa, a ordem não estava

**Confirmado:** nenhuma grade supersede outra. É o mesmo instrumento medido em momentos diferentes —
o nº de dimensões cresceu (6 → 11) quando as ADRs 0333/0334 somaram os eixos *rodar-e-observar* e
*servir-o-negócio*.

Duas correções:

- **As duas de 07-17 estavam invertidas.** A `memoria-conhecimento` é a **posterior** — ela mesma diz
  *"▲ era 7,0 · grade da manhã"* e se declara *"rodada parcial · 1 de 11 dimensões"*. Não é grade
  paralela: é **re-medição de uma linha** da completa da manhã.
- **A de 07-18 estava datada pela publicação** (07-19), contra o próprio padrão da vista, que manda
  registrar a data de **medição**.

E dois escopos distintos ficaram marcados por linha: `memoria-conhecimento` é parcial *do mesmo
instrumento*; `Guardrails de integridade` usa o mesmo **método** sobre um **recorte próprio** (3
classes de erro da base de conhecimento) que não são dimensões da grade do IA OS.

### Uma sequência que não estava registrada

As 3 vistas dark eram itens soltos em "trabalho pontual". São **etapas encadeadas** do mesmo trabalho
— PRs #3981 → #3982 → #3983 — e cada uma é um **pedido de aprovação visual** antes de tirar o PR do
draft. Nenhuma supersede a anterior: a seguinte continua de onde a aprovada parou.

## O achado que vale além do agrupamento

No tema IA a linhagem estava **certa** (a de 07-28 sucede a outra de 07-28, mesmo tema, mesma data).
Mas a leitura mostrou que a **histórica guarda o que a viva não tem**: as **seis plantas Mermaid
navegáveis**. As duas cobrem os mesmos seis fluxos — a viva com `arquivo:linha` e grade, a histórica
com o desenho. Quem quiser *ver* a topologia vai na histórica.

Isso é argumento concreto a favor do modelo append-only do catálogo: **descartar a superada teria
perdido informação que a sucessora não carrega.**

## O que NÃO foi feito

- ⛔ Nada apagado, nada despublicado — o modelo append-only fica.
- ⛔ O hook `vista-publicada-padrao.mjs` e o padrão da vista não foram tocados.
- ⚠️ **Ler por dentro corrigiu o agrupamento, não re-verificou o conteúdo** das vistas contra o repo
  de hoje. Vários números dentro delas envelheceram — seguem sendo retratos datados. Isso está escrito
  na seção de confiabilidade do próprio catálogo, não só aqui.

## Nota de método — dois tropeços meus, registrados

1. **Inventei um UUID.** Ao montar uma URL de `WebFetch` escrevi `68fb943c-3c11-4b95-...` quando o
   real era `68fb943c-03c3-4f2b-...`. A ferramenta devolveu *"artifact not found"* — falhou alto, não
   silencioso. Corrigi extraindo o UUID **do próprio arquivo** (`grep -o 'artifact/[0-9a-f-]*'`) em vez
   de reler no olho. É a classe LC-08 em miniatura: transcrever de memória quando a fonte está à mão.
2. **A branch de trabalho estava suja.** 38 commits atrás de `main` e **7 à frente** — e `git cherry`
   provou que os 7 **não** estão em `main` (trabalho de outra sessão). Commitar ali teria misturado
   dois assuntos num PR. Abri branch limpa de `origin/main` com 1 arquivo.

## Estado ao fechar

- PR [#5022](https://github.com/wagnerra23/oimpresso.com/pull/5022) aberto, CI rodando.
- Verificações locais antes de propor merge: **24 URLs idênticas** ao original (diff de UUIDs) e
  **16/16 links relativos resolvem** (`deadlink-gate` é required, e adicionei 8 links novos).
