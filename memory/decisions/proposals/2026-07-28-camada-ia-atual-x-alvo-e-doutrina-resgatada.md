---
status: proposal
title: "Camada de IA: 6 deltas atual→alvo, e o resgate da doutrina que a geração do ARCHITECTURE apagou"
proposed_by: Claude — pedido [W] 2026-07-28 "senti muita falta de como o sistema está e como deveria ficar o fluxo dele"
proposed_at: 2026-07-28
relates_to:
  - 0035-stack-ai-canonica-wagner-2026-04-26
  - 0053-mcp-server-governanca-como-produto
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
---

# PROPOSAL — o que a camada de IA é hoje, o que deveria ser, e o que se perdeu no caminho

## Por que esta proposta existe

Duas coisas aconteceram no mesmo dia, em sessões paralelas, e a segunda apagou parte da primeira.

O [PR #4975](https://github.com/wagnerra23/oimpresso.com/pull/4975) fez `memory/requisitos/Jana/ARCHITECTURE.md`
virar **artefato gerado** por `system-map.mjs` — topologia, inventário de agentes, registro de
ferramentas, stacks. Isso é bom e é a direção certa: contagem derivada não apodrece.

Mas o documento anterior era **curado**, e nem tudo nele era derivável. Ao ser substituído, sumiram do
repositório inteiro duas peças que uma auditoria adversarial, no mesmo dia, tinha marcado
explicitamente como **"preservar"**:

```bash
git grep -il "não é BI tradicional" origin/main -- memory/    # → nenhum resultado
git grep -il "trajetória projetada"  origin/main -- memory/   # → nenhum resultado
```

Não é crítica ao #4975 — a consolidação estava certa no que fez. É a lição de que **gerar o derivável
não dispensa dar casa ao não-derivável**, e que o custo de esquecer isso é silencioso: ninguém percebe
que a doutrina sumiu até alguém propor o que ela proibia.

O próprio arquivo gerado admite a lacuna, na seção *"O que ainda é humano"*: explicar **por que** as
camadas existem, decidir se serviço em standby entra ou sai, registrar mudança em ADR.

## Parte 1 — resgate consolidado

A casa foi definida na integração de 2026-07-28:

- doutrina de posicionamento e decisões ainda abertas vivem em
  [`memory/requisitos/Jana/BRIEFING.md`](../../requisitos/Jana/BRIEFING.md#doutrina-do-produto-e-decisões-abertas);
- topologia e inventário derivável vivem em `Jana/ARCHITECTURE.md` e `PAINEL-SISTEMA.md`;
- execução do ciclo observar→aprender vive em `Jana/OBSERVABILITY.md`;
- esta proposta preserva a proveniência e os seis deltas, sem repetir os conteúdos donos.

Quando uma decisão aberta for tomada por [W], ela sai do BRIEFING e ganha ADR. A proposta não mantém
uma segunda cópia da doutrina.

## Parte 2 — os seis deltas: como está × como deveria ficar

Levantado por leitura de código em 2026-07-28 (quatro frentes + revisão adversarial). **Nada foi
medido contra banco ou runtime** — onde se lê "roda", leia "o código declara que roda".

O alvo é **proposta**, não decisão. E a distinção entre as classes importa: `defeito` é código que não
faz o que promete; `escolha` é comportamento deliberado que talvez mereça revisão, mas foi decidido.

### D1 · Tokens do chat vão para a mensagem errada — `defeito`

**Hoje:** no caminho com streaming, `LaravelAiSdkDriver::responderChatStream` grava o consumo em
"a última resposta da conversa", de dentro do gerador. O controlador cria a resposta deste turno
**depois** que o gerador termina (`ChatController::sendStream`). O número do turno atual cai na
resposta anterior; no primeiro turno, em lugar nenhum. O comentário do próprio controlador documenta
a dependência herdada do driver bloqueante.

**Alvo:** o driver **devolve** o consumo; o controlador, que já tem a mensagem criada, grava nela.
Verificar o caminho bloqueante à parte — a ordem lá é outra e pode estar correta.

**Estado:** sessão aberta.

### D2 · O trace do streaming é parcial — `lacuna`

> **Errata 2026-07-28:** a redação inicial dizia que o streaming “não emite nada”. Isso estava vencido:
> `LangfuseAgentTelemetryListener` já assina `StreamingAgent`/`AgentStreamed` e cria trace + generation.

**Hoje:** a chamada ao modelo do streaming possui emissor Langfuse no código. O residual é outro:
cache hit, clarificação, persistência, erro parcial e término SSE não compartilham um trace raiz; a
emissão OTel explícita continua restrita ao caminho bloqueante; runtime precisa ser provado pelo
destino.

**Alvo:** trace raiz generator-aware, com a generation existente como filha e sem duplicá-la. O
diagnóstico arquitetural fica neste delta; ações, testes, aceite e rollback vivem apenas em
[`Jana/OBSERVABILITY.md` Etapas 1–2](../../requisitos/Jana/OBSERVABILITY.md#etapa-1--criar-o-trace-raiz-ponta-a-ponta-do-chat-sse).

**Estado:** proposto; a execução deve coordenar com D1 porque toca o mesmo fluxo.

### D3 · Falha de infraestrutura é indistinguível de "não achei" — `defeito`

**Hoje:** em `kb-answer` (`McpMemoryDocument::buscarHybrid`) três falhas distintas — exceção HTTP,
resposta inválida e zero resultados — colapsam no mesmo retorno vazio. No KB Unificado
(`KbRagService::ask`), índice fora do ar e ausência real viram a mesma resposta **200**. Quem lê
conclui que o conteúdo não existe.

Agrava: o mesmo serviço alimenta o avaliador de qualidade. Índice fora do ar durante uma avaliação
derruba a nota e o diagnóstico aponta "a IA piorou" quando o problema era rede.

**Alvo:** separar degradação de ausência **sem** tornar fatal — a escolha por disponibilidade é boa e
fica. O resultado carrega o sinal; quem consome decide o que mostrar.

**Estado:** duas sessões abertas (uma por módulo).

### D4 · Sete tabelas sem uso, uma sem existir — `dívida`

**Hoje:** cinco tabelas criadas e nunca lidas nem escritas; uma escrita só pelo semeador; e
`mcp_handoff_drafts` **referenciada pelo código sem migração** — o custo de gerar rascunho de handoff
nunca é persistido (está em try/catch, não quebra).

**Alvo:** cada uma resolvida explicitamente — criar, remover ou documentar como reservada. Estado
intermediário é promessa quebrada com aparência de funcionalidade.

**Estado:** sessão aberta.

### D5 · Append-only prometido em comentário — `risco`

**Hoje:** só `mcp_audit_log` e `mcp_task_events` têm gatilho real de imutabilidade no MySQL. Outras
tabelas se declaram append-only **em comentário** — nada no banco impede `UPDATE` ou `DELETE`.

**Alvo:** por tabela, promover a gatilho **ou** rebaixar a redação para "convenção, sem garantia".
Promessa em comentário é pior que ausência: quem lê acha que é garantia.

**Estado:** sessão aberta.

### D6 · Configuração em três lugares — `higiene`

**Hoje:** chaves na configuração do módulo, chaves fixas no código sem escape de ambiente, e quatro
lidas direto do ambiente **fora de `config/`** — invisíveis a qualquer inventário. Uma delas desliga
a redação de PII.

**Alvo:** tudo na configuração, com o padrão atual preservado. **Verificar antes** se há cache de
configuração ativo em produção: se houver, as chaves soltas já podem estar retornando nulo — e aí
não é higiene, é bug latente. Esse é o achado mais valioso possível neste item.

**Estado:** sessão aberta.

## O que esta proposta pede a [W]

1. **Quais dos seis deltas viram trabalho** e em que ordem. D1–D2 já apontam para o plano de
   observabilidade; D3–D6 continuam como proposta.
2. A casa documental deixou de ser pergunta: BRIEFING = intenção; ARCHITECTURE/PAINEL = derivável;
   OBSERVABILITY = execução.
3. Os alvos de produto e as decisões abertas continuam dependendo de [W].

## Lição de método (vale além deste caso)

Quando um documento curado vira gerado, **a prosa não migra sozinha** — e some sem alarme, porque
nenhum gate mede ausência de doutrina. A checagem barata, antes de consolidar: rodar
`git grep` das frases que a versão anterior tinha de único e confirmar que ainda existem em algum
lugar. Aqui, duas não existiam mais, e nada avisou.

Complementa a [ADR 0256](../0256-knowledge-survival-meia-vida-catraca-sentinela.md): derivado sobrevive
porque se regenera; **curado sobrevive porque alguém lhe deu casa** — e a hora de dar é antes de
apagar a antiga.
