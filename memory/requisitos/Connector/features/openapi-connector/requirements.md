---
id: requisitos-connector-features-openapi-connector-requirements
feature: openapi-connector
module: Connector
us: ["US-CONN-013"]
parent_plan: connector-openapi
created: "2026-08-03"
---

# Requirements — OpenAPI 3.0 seguro para o Connector

> **US-mãe:** [US-CONN-013](../../SPEC.md) · **Sinal (ADR 0105):** gap P0 do
> [BRIEFING](../../BRIEFING.md): Felipe/Maiara precisam mergulhar no código para prestar suporte;
> clientes em migração dependem dos contratos do Connector e já pediram documentação.

## User story

**Como** Felipe ou Maiara prestando suporte às integrações externas
**Quero** consultar uma documentação OpenAPI 3.0 fiel aos endpoints do Connector
**Para** entender autenticação, request, response e erros sem ler controllers nem arriscar o
contrato Delphi em produção.

## Clarifications (fase Clarify — Spec Kit 2026)

- **2026-08-03** — P: a documentação será pública, autenticada ou apenas interna? → R:
  **PENDENTE [W]/[F]**. Default fail-closed: gerar apenas artefato controlado no CT 100; nenhuma
  rota/URL pública antes da decisão registrada na T-04.
- **2026-08-03** — P: o piloto inclui as rotas `oauth/*` hoje alcançadas pelo matcher largo do
  Scribe? → R: não. A US é do Connector; o inventário desta entrega é somente
  `/connector/api/*`. OAuth exige feature e revisão de segurança próprias.
- **2026-08-03** — P: Scribe pode fazer chamadas GET para obter exemplos ou ler o primeiro Model
  do banco? → R: não. Response calls e exemplos derivados de registros reais ficam desabilitados;
  exemplos são sintéticos/curados e nunca usam token real.
- **2026-08-03** — P: publicar em `/docs`? → R: não nesta fase. O path colide semanticamente com
  a rota do Officeimpresso e `/documentacao` foi separado de propósito; URL final depende da
  decisão de audiência.

## Acceptance criteria (EARS — ADR 0306)

- **AC-1** — QUANDO o OpenAPI for gerado, O SISTEMA DEVE representar o inventário de rotas
  `/connector/api/*` e não incluir `oauth/*`. _Prova: comparação determinística entre
  `route:list --path=connector/api` e os paths do artefato, com lista explícita de diferenças._
- **AC-2** — ENQUANTO a documentação for gerada, O SISTEMA NÃO DEVE executar response calls nem
  consultar registros reais para fabricar exemplos. _Prova: teste de contrato da configuração +
  geração no CT 100 com banco sentinela/observação de zero query de exemplo._
- **AC-3** — O ARTEFATO DEVE conter somente exemplos sintéticos/curados e jamais token, segredo,
  CPF/CNPJ, e-mail, telefone ou payload real de cliente. _Prova: scanner de segredos/PII sobre os
  arquivos gerados e revisão dos exemplos._
- **AC-4** — QUANDO docblocks/atributos forem curados, O SISTEMA DEVE preservar URLs, payloads,
  responses, content-types e a ordem `log.delphi → auth:api → timezone`. _Prova: suites de contrato
  Connector existentes + diff de rotas/middleware antes→depois._
- **AC-5** — ENQUANTO a audiência não estiver decidida por [W]/[F], A DOCUMENTAÇÃO DEVE permanecer
  sem rota pública e sem deploy no `public/docs`. _Prova: ausência de nova superfície HTTP no
  `route:list`/smoke e decisão registrada antes da task de publicação._
- **AC-6** — QUANDO Felipe ou Maiara consultar um endpoint legado e um endpoint JSON, A
  DOCUMENTAÇÃO DEVE permitir identificar autenticação, parâmetros, request, response e erros sem
  abrir o código-fonte. _Prova: smoke humano com roteiro e resultado literal registrado._
- **AC-7** — QUANDO o gerador for executado duas vezes no mesmo SHA, O ARTEFATO DEVE ser
  determinístico. _Prova: segunda geração produz diff vazio._

## Fora de escopo

- Alterar payload/response legado, controller, autenticação ou ordem de middleware.
- Documentar/publicar `oauth/*`; criar portal público; decidir sozinho a audiência.
- Rate limiting per-business, webhooks outbound ou SDK Delphi.
- Gerar exemplos a partir de banco de produção, staging compartilhado ou credenciais reais.

## Referências

- [SPEC.md](../../SPEC.md) (US-CONN-013) · [BRIEFING.md](../../BRIEFING.md) ·
  [CHARTER-rest-api-external.md](../../CHARTER-rest-api-external.md) ·
  [ADR 0093](../../../../decisions/0093-multi-tenant-isolation-tier-0.md) ·
  [ADR 0062](../../../../decisions/0062-separacao-runtime-hostinger-ct100.md)
