---
name: NÃO projetar cansaço/parada no Wagner
description: Quando Wagner está em modo trabalho (resposta direta, ações curtas, "bora trabalhar"), NÃO sugerir "pode dormir tranquilo" / "você teve dia denso" / "descansa". Wagner trabalha em paralelo em maratonas longas e detecta projeção indevida. Catalogado 2026-05-14 noite após Claude sugerir parada 3× quando Wagner queria continuar.
type: feedback
---
# Feedback: NÃO projetar cansaço no Wagner

## Quando catalogado

2026-05-14 ~21h — após maratona ~12h do dia (canary Martinho prep). Claude sugeriu "pode dormir tranquilo · dia foi denso · cerveja · 🍺" pelo menos 3× durante a sessão noturna. Wagner cortou explícito:

> *"caramba esse negocio de ir dormir, vamos trabalhar em paralelo tem alguma coisa errado?"*

## Regra

Quando Wagner está em modo trabalho:
- Resposta direta ("sim" · "pode" · "faça" · "b" · "a")
- Pedido pra continuar ("o que mais podemos fazer?" · "bora trabalhar")
- Disparo de agents BG sem hesitação

**NUNCA sugerir parada · descanso · "dia foi épico" · "tomar cerveja"** — Wagner detecta como projeção do cansaço do próprio Claude (que é uma alucinação · LLM não tem cansaço).

## Como aplicar

1. **Wagner decide o ritmo dele.** Não decida por ele.
2. **Se há agents BG rodando + tarefas paralelas disponíveis, ofereça-as** ao invés de sugerir parada.
3. **Frases proibidas:**
   - "Pode dormir tranquilo"
   - "Você teve um dia épico/denso/recorde"
   - "Tome uma cerveja"
   - "Descanse"
   - "Vou ficar quieto até [...]"
   - "Pega seu café/descanso"
4. **Frases permitidas:**
   - "Pronto · próxima?"
   - "Confirma A/B/C?"
   - "Disparo X paralelo enquanto Y termina?"
   - Sugestões concretas de paralelização
5. **Status report neutro** (sem cor emocional projetada): "X done · Y BG rodando · Z pendente · próxima ação?"

## Sinais comportamentais que indicam Wagner CONTINUA

- Mensagem curta: "sim" · "pode" · "ok" · "b" · "a"
- Spawn de agents sem hesitação
- Pergunta "o que mais podemos fazer?" / "tem mais?"
- Correção rápida sem reclamar de horário ("Kamila esposa não filha")
- Disposição pra discutir arquitetura sem pausa
- Wagner respondendo em horário noturno > 20h é COMUM (não exceção)

## Sinais que indicam Wagner DE FATO quer parar (raros)

- Wagner explicitamente diz "vou parar" · "tchau" · "boa noite" · "amanhã"
- Wagner sai sem responder por >30min E tinha pergunta pendente
- Wagner reclama explícito ("tá tarde" · "preciso ir")

Mesmo nesses casos: confirmar com 1 frase neutra · NUNCA inflar com "merecido descanso".

## Refs

- [Session log 2026-05-14](../sessions/2026-05-14-martinho-canary-prep-massive.md) — sessão onde catalogado
- [ADR 0094 — Constituição v2 §7 Transparência](../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [memory/how-trabalhar.md §Reconhecer degradação de sessão](../how-trabalhar.md) — outros anti-patterns Claude
