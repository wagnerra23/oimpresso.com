# ADR TECH-0001 · Scoring de ADR pesa 1.2x no assistente

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude
- **Categoria**: tech

## Contexto

O `ChatAssistant` em modo offline faz keyword matching nos 5 tipos de conteúdo (README, ARCHITECTURE, SPEC, CHANGELOG, ADRs). Um peso uniforme entre eles trata "documentação geral" e "decisão consciente" como equivalentes.

Mas quando o usuário pergunta algo como "por que usamos X?" ou "por que não Y?", a resposta certa normalmente está num ADR — documento cuja natureza é explicar *por quê*. Se ARCHITECTURE.md menciona X casualmente e o ADR 0001 tem uma decisão formal, o ADR deveria ganhar.

## Decisão

Aplicar multiplicador de **1.2x** no score de ADRs no `ChatAssistant::retrieve()`:

```php
$hits[] = [
    'source' => "ADR {$adr['number']}",
    'score'  => $score * 1.2,   // <-- pesa decisão formal
    ...
];
```

Demais fontes (README, ARCHITECTURE, SPEC, CHANGELOG) ficam com peso 1.0x.

## Consequências

**Positivas:**
- Perguntas do tipo "por que" tendem a cair no ADR relevante (validado: "Por que usar MySQL?" traz ADR 0001 no topo com score 40.8).
- Incentiva documentar decisões em ADRs (vira mais descoberto pelo chat).

**Negativas:**
- Magic number. Valor empírico, não derivado de dataset.
- Pode falsamente priorizar ADR fraco sobre seção muito específica de ARCHITECTURE.

**Mitigação**: quando migrarmos pra embeddings (Fase 4), esse peso vira aprendido; por enquanto serve como prior razoável.

## Alternativas consideradas

- **Peso uniforme (1.0)**: piorou testes manuais — resposta vinha de seção casual do README.
- **Peso 2.0 no ADR**: overweight, empurrou ADRs irrelevantes pro topo.
- **Boost por keyword "por que" / "decisão"**: melhor precisão mas complicado de manter.
