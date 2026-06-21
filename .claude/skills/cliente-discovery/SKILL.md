---
name: cliente-discovery
description: ATIVAR quando Wagner pedir /cliente-discovery, "entrevistar cliente X", "fazer discovery do cliente Y", "criar persona pra <pessoa>", "vou visitar cliente <nome> amanhã", "ligar pra cliente <nome>", OU antes de qualquer sessão presencial/call com cliente real do oimpresso. Skill que apresenta script canônico de entrevista combinando The Mom Test (Rob Fitzpatrick) + Job-to-be-done (Christensen) + Day-in-the-life observation (IDEO). 12 perguntas estruturadas + checklist de captura raw + template de saída em memory/clientes/<cliente>/discovery-YYYY-MM-DD.md + draft de persona YAML pronto pra refinar. Refs ADR UI-0016, ADR 0105 (cliente-como-sinal). NÃO substitui visita real — orquestra o que perguntar e como capturar.
tier: B
---

# cliente-discovery — Entrevista canônica de cliente real

Quando ativar (auto-trigger description matches):
- `/cliente-discovery` no input
- "vou visitar cliente <nome>"
- "vou ligar pra <cliente>"
- "preciso criar persona pra <funcionário>"
- "fazer discovery <cliente>"
- "entrevistar <pessoa>"

Pré-requisito: cliente real (paga ou piloto Wagner). Não use pra criar persona hipotética.

## Output esperado

Wagner sai da skill com 4 artefatos prontos:

1. **Script da entrevista** (15-30 min — Mom Test + JTBD + dia típico)
2. **Checklist de captura** (gravação áudio? screenshots durante uso? cronometrar tarefa?)
3. **Template raw** `memory/clientes/<cliente>/discovery-YYYY-MM-DD.md` pra preencher durante/após
4. **Draft YAML persona** baseado no que Wagner sabe HOJE — pra refinar com cliente

## Script canônico (12 perguntas Mom Test + JTBD)

### Aquecimento (não viciar respostas — Mom Test)

1. **"Me conta como foi seu dia ontem aqui no [empresa]?"** (passado concreto, não hipotético)
2. **"O que toma mais tempo no seu dia?"** (job principal aparece naturalmente)
3. **"Quando foi a última vez que algo deu errado no sistema? Conta como foi?"** (fricção real, não imaginada)

### Job-to-be-done core (Christensen)

4. **"Quando [evento que dispara uso do sistema], o que você FAZ exatamente?"** (sequência de ações observáveis)
5. **"Por que isso é importante pra você? E pra empresa?"** (motivação funcional + emocional + social)
6. **"Se sumisse essa parte do trabalho, o que aconteceria com a empresa?"** (criticidade real)

### Sistema atual + alternativas (Mom Test)

7. **"Você usa algum outro sistema/planilha/caderno pra isso hoje? Qual? Por quê?"** (concorrência implícita)
8. **"Quanto tempo isso te toma por dia/semana?"** (mensurar baseline atual)
9. **"O que você já tentou pra resolver isso antes do oimpresso?"** (alternativas consideradas)

### Comportamento real (não declarado)

10. **"Pode me mostrar como você faz isso no sistema agora? Quero ver."** (shadowing in-situ — captura friction real, não declarada)
11. **"Quem mais usa o sistema aqui no [empresa]? O que ELES fazem que é diferente de você?"** (outras personas potenciais)
12. **"Se eu pudesse mudar UMA coisa só pra te ajudar, o que seria?"** (priorização do próprio cliente)

## Anti-perguntas (NUNCA fazer — viciam resposta)

❌ "Você gostaria se a gente fizesse X?" → cliente sempre diz sim
❌ "Quanto você pagaria por Y?" → respostas hipotéticas mentem
❌ "Isso é importante?" → todo mundo diz sim
❌ "Você usaria essa feature?" → resposta imaginada vs uso real
❌ "Achou bonito?" → estética não é o ponto, fricção é

## Checklist de captura

- [ ] Áudio gravado (com permissão LGPD explícita)
- [ ] Screenshots durante uso (entrevistado fazendo tarefa real)
- [ ] Cronômetro: medir tempo da tarefa principal (ex "ela cadastrou cliente em 1m47s")
- [ ] Contagem de cliques tarefa golden path
- [ ] Citações textuais (transcrever literais — viram canon na persona)
- [ ] Fricções observadas (NÃO declaradas) — onde travou, hesitou, voltou
- [ ] Foto do ambiente (monitor, hardware, mesa, ruído contextual)

## Template raw output

Arquivo: `memory/clientes/<slug-cliente>/discovery-YYYY-MM-DD.md`

```markdown
---
date: 'YYYY-MM-DD'
topic: 'Discovery <Nome> @ <Empresa>'
authors: ['W']
cliente: <slug>
persona_alvo: <slug-funcionario>
duracao_min: <int>
canal: presencial | call | whatsapp
---

# Discovery <Nome> @ <Empresa> · YYYY-MM-DD

## Resumo executivo (1 parágrafo)

<insight principal — o que mudou minha compreensão>

## Respostas das 12 perguntas

### 1. Como foi o dia de ontem?
<resposta literal>

### 2. ...

## Citações marcantes
- "..." (contexto)
- "..." (contexto)

## Fricções observadas (não declaradas)
- ...

## Métricas baseline capturadas
- Tarefa "<X>" demora <tempo>
- Cliques pra completar: <N>
- Taxa de erro observada: <%>

## Outras personas identificadas (perguntar quem mais usa)
- ...

## Ações pós-discovery
- [ ] Atualizar persona YAML <link>
- [ ] Criar nova persona <slug> pra <pessoa identificada>
- [ ] Backlog: <item específico que destrava cliente>
```

## Princípios duros

- **Cliente paga ou piloto Wagner** ADR 0105 — não fazer pra prospect frio
- **LGPD explícito** — consentimento gravação áudio antes de começar
- **Append-only** — discovery raw NUNCA editado depois (gravado HISTÓRICO)
- **Citações literais** — não parafrasear nas citações (perde nuance)
- **Tempo total ≤ 30 min** — cliente PME não tem 2h pra dar
- **Shadowing > entrevista** — pergunta 10 (me mostra fazendo) vale 5 outras

## Relacionadas

- `design-deep-analysis` (usa persona criada por esta skill)
- ADR 0105 cliente-como-sinal
- ADR UI-0016 design contextualizado por persona
