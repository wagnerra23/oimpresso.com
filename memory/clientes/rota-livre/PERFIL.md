# ROTA LIVRE — perfil canônico do cliente (`business_id = 4`)

> Porta-mãe do corpus do cliente ROTA LIVRE (camada `business-knowledge`, [ADR 0334](../../decisions/0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md)).
> Autoridade de identidade e estado. Dado estruturado em [`perfil.yml`](perfil.yml); persona operadora em [`personas/larissa.yml`](personas/larissa.yml); detalhe operacional e histórico de incidentes em [`operacao.md`](operacao.md).

## Identidade (autoritativa)

- **Razão social:** LARISSA COMERCIO DE ARTIGOS DO VESTUARIO LTDA - ME
- **Nome fantasia:** ROTA LIVRE (loja BL0001)
- **CNPJ:** `[REDACTED]` *(PII redactada — [`proibicoes.md`](../../proibicoes.md); consultar via `business_id=4` no banco)*
- **Ramo:** vestuário (CNAE `4781-4/00`) — cliente piloto do `Modules/Vestuario`
- **Localização:** Termas do Gravatal, **Gravatal/SC**, CEP `88735-XXX` *(redactado)*
- **Timezone:** `America/Sao_Paulo` (confirmado operacionalmente 2026-04-24)
- **business_id:** 4 · **Cadastro original:** 2021-02-01

> ⚠️ **Correção de dado 2026-07-22:** o `perfil.yml` trazia localização `SP / São Paulo` e razão social `ROTA LIVRE COMERCIO DE VESTUARIO LTDA` — **ambas erradas**. O canon [`why-oimpresso.md`](../../why-oimpresso.md) crava "vestuário em Termas do Gravatal/SC (não gráfica em SP)" e a razão social real acima. `perfil.yml` foi corrigido no mesmo PR desta porta.

## Estado

- **Status:** piloto Wagner — **cliente ativo com volume real** (não demo).
- **Volume:** 17.251+ vendas (~99% do total do sistema oimpresso). Primeira venda 2021-05-13; venda diária.
- **Sinal (ADR 0105):** não paga formalmente ainda; reporta fricção semanalmente. É o **caso piloto canônico** de qualquer mudança de UX/UI/migração.
- **Migração legacy:** ROTA LIVRE **já está no oimpresso** (biz=4) desde o começo — **não há banco Firebird WR Comercial fonte** pra importar. Está no corpus legacy apenas porque os quirks operacionais guiam decisões de arquitetura.

## Sensibilidades — NÃO MEXER SEM AVISAR

Detalhe completo (com commits e datas) em [`operacao.md`](operacao.md). Resumo dos invariantes:

1. **`format_date` shift +3h é preservado de propósito** ([ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md)) — Larissa decorou os horários com o shift. Corrigir o datetime = regressão percebida.
2. **`transaction_date` retroativo é fluxo normal** (lançamento em lote no fim do dia), não bug — não "corrigir" por algoritmo.
3. **Monitor ~1280px** — telas largas precisam caber; colunas default demais quebram a operação.
4. **`/sells/create` depende de `default_location`** — role `Vendas#4` precisa de `location.4`.

## Como retomar contexto

Ao receber qualquer pedido citando ROTA LIVRE / Larissa / Gravatal / biz=4:

1. Ler esta porta + [`operacao.md`](operacao.md).
2. `grep rotalivre` em `memory/sessions/` pra notas recentes.
3. Bug de produção → avaliar **impacto visual** antes de fix sistêmico (operadora decorou estado anterior).
4. **Comunicar o Wagner antes de mudanças** — ele é o canal com o cliente.
