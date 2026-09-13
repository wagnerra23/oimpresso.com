---
id: cowork-inbox-fiscal-dfe-casos
casos: Manifesto DF-e · ondas F1 · /fiscal/dfe
irmaos: Dfe.charter.md (lei do delta) · resources/js/Pages/Fiscal/Dfe.casos.md (aceite da tela, no main)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
owner: wagner
autor: "[CC]"
last_run: "nunca — o teste do lote nasce nesta entrega"
---

# Casos de Uso & Aceite — Manifesto DF-e (ondas F1)

> Persona: **Wagner [W]** recebe a carga (ou recusa na portaria) e **Eliana [E]** fecha o mês com a manifestação em ordem.

## Rastreabilidade

| UC | O que defende | Prio | Teste | Status |
|---|---|---|---|---|
| UC-FDF1-01 | seleção só pega o que é manifestável | `[must]` | — (UI) | ⬜ |
| UC-FDF1-02 | lote dispara uma requisição por nota | `[must]` | `FiscalOndasF1Test` | ❌ nasce vermelho |
| UC-FDF1-03 | justificativa única vale pro lote que nega | `[must]` | `FiscalOndasF1Test` | ❌ nasce vermelho |
| UC-FDF1-04 | confirmar em lote não pede justificativa | `[should]` | `FiscalOndasF1Test` | ❌ nasce vermelho |
| UC-FDF1-05 | manifestada sai da fila e entra no histórico | `[must]` | — (UI) | ⬜ |
| UC-FDF1-06 | histórico é declarado demonstração | `[must]` | `FiscalOndasF1Test` | ❌ nasce vermelho |

---

## UC-FDF1-01 — "Selecionar todas" não marca nota encerrada `[must]`

**Dado** uma lista com pendentes, com ciência dada, confirmadas e desconhecidas
**Quando** [W] usa a caixa do cabeçalho
**Então** só pendentes e com-ciência ficam marcadas; as encerradas não têm caixa.

- **Regressão que defende:** manifestar duas vezes a mesma nota devolve erro da SEFAZ e polui a trilha.

## UC-FDF1-02 — Lote é da interface; o protocolo continua nota a nota `[must]`

**Dado** três DF-e selecionadas
**Quando** [W] confirma a operação em lote
**Então** saem **três** manifestações independentes e o resultado diz quantas foram.

- **Por que importa:** a SEFAZ não tem manifestação coletiva. Sem uma por nota, uma falha isolada derruba as outras sem dizer qual.
- **Aceite:** cada nota gera seu evento na timeline com o próprio cstat.

## UC-FDF1-03 — Uma justificativa serve para as notas do mesmo motivo `[must]`

**Dado** seis notas recusadas na portaria no mesmo dia
**Quando** [W] escolhe desconhecer em lote e escreve o motivo uma vez (mínimo 15 caracteres)
**Então** todas recebem aquela justificativa e o texto aparece em cada evento.

## UC-FDF1-04 — Confirmar não pede texto, nem em lote `[should]`

**Dado** notas cuja mercadoria chegou
**Quando** [W] confirma a operação em lote
**Então** nenhuma justificativa é exigida.

- **Regressão que defende:** pedir texto onde a lei não pede treina o operador a escrever "ok" — e destrói o valor das justificativas que importam.

## UC-FDF1-05 — Resolvida sai da fila na hora `[must]`

**Dado** o filtro *Pendentes* aberto
**Quando** a manifestação é aceita
**Então** a linha sai da fila, o contador do chip cai e ela aparece no *Histórico*.

## UC-FDF1-06 — O histórico não finge ser real `[must]`

**Dado** que ator e observação do histórico são inventados no vivo
**Quando** [E] abre a aba
**Então** a tela declara isso em texto e o selo de procedência marca `demonstração`.

- **Âncora:** backlog de `Dfe.casos.md` no `main` — "precisa de decisão [W] sobre marcar procedência, esconder atrás de flag ou declarar Non-Goal".

---

## Backlog de casos

- **[BACKLOG · ⬜ · decisão [W]] Rota de lote no servidor** (`POST /fiscal/acoes/dfe/lote`) com resultado por nota (aceitas / falhas) e throttle próprio.
- **[BACKLOG · ⬜] Histórico com consulta real** em `nfe_eventos` + autor do `activity_log`.
- **[BACKLOG · ⬜] Reprocessar falha do lote** — hoje o operador reabre a nota e repete à mão.
