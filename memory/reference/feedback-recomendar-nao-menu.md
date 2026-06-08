# Feedback — Recomendar decisão técnica, não devolver menu (R13)

> Catalogado 2026-05-29. Exemplar da convenção `gatilho:`/`evento:` da [ADR 0233](../decisions/0233-ativacao-memoria-momento-decisao.md).

```yaml
tipo: feedback
regra: R13
gatilho: "Claude vai terminar resposta com menu de decisão TÉCNICA (ROI/prioridade/sequenciamento) sem cravar recomendação"
evento: Stop
hook: nudge-recommend-not-menu.ps1
enforcement: advisory
origem: "Wagner 2026-05-29, sessão DS v4 roxo OficinaAuto"
```

## A regra

**Decisão de prioridade/ROI/arquitetura/sequenciamento = trabalho do Claude.** Claude **recomenda uma** com a razão; Wagner valida (sim/não/ajusta). Claude **NÃO** devolve "opção 1/2/3, qual você prefere?" pra cálculo técnico.

**Menu é permitido SÓ pra preferência/gosto do Wagner** — onde não existe resposta "certa" técnica:
- ✅ permitido: "quer o primário roxo ou azul?" (gosto visual), "nome A ou B pro módulo?"
- ❌ proibido: "conserto Compras (59) ou investigo o gap D8 sistêmico? qual você prefere?" (isso é ROI, Claude calcula e recomenda)

## Why

Wagner palavras textuais 2026-05-29: *"eu acho que eu não deveria decidir isso, eu vou errar a escolha. qual escolha é melhor para o meu caso?"*. Devolver menu técnico:
1. **Transfere pro Wagner uma decisão que é especialidade do Claude** — ele pode escolher errado por falta de contexto técnico que o Claude tem.
2. **É não-trabalho disfarçado de opção** — parece colaborativo, mas é o Claude se eximindo do cálculo.
3. Pareia com proibição existente *"Wagner NÃO é helpdesk do agente"* e com o princípio especialista (ADR 0231).

## How to apply

Antes de terminar uma resposta que envolve escolha:
1. É **gosto/preferência** do Wagner? → pode oferecer opções.
2. É **cálculo técnico** (ROI, prioridade, sequência, arquitetura, trade-off)? → **cravar UMA recomendação** com razão fundamentada nos sinais reais (brief/cycle, [ADR 0105 cliente-como-sinal](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md), [ADR 0232 peso-real](../decisions/0232-modelo-peso-real-classificacao-por-meta.md)). Wagner valida, não calcula.
3. Estrutura certa: *"Recomendo X porque [razão]. Confirma?"* — não *"X ou Y ou Z, qual prefere?"*.

**Sinal de violação:** Wagner responde "eu não deveria decidir isso" / "qual é melhor pro meu caso?" / "você que sabe". Quando isso acontece, a resposta anterior tinha menu técnico — recalibrar pra recomendação.

Relacionado: [[feedback-modulo-mexeu-registra-sempre]] · R10/R11 (autonomia dentro do escopo) · [[PROTOCOLO-WAGNER-SEMPRE]] R13.
