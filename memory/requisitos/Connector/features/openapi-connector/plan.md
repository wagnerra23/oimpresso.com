---
id: requisitos-connector-features-openapi-connector-plan
feature: openapi-connector
module: Connector
---

# Plan — OpenAPI 3.0 seguro para o Connector

## Status vivo

- **status:** ativo
- **owner:** W/F
- **criado:** 2026-08-03 · **reviewed_at:** 2026-08-03 · **próxima-revisão:** 2026-09-02
- **cycle:** off-cycle · **execução:** `parent_plan=connector-openapi` — tasks MCP ainda não criadas
- **gate-de-saída (DoD):** AC-1..7 provados, smoke de suporte concluído e US-CONN-013 ancorada
- **kill-condition:** se a geração não puder operar sem response calls/dados reais, não publicar;
  preservar charter/testes como fonte e reabrir a abordagem
- **verdade-viva:** este documento

## Decisões técnicas

| # | Decisão | Por quê (1 linha) | Âncora |
|---|---|---|---|
| D1 | Reusar `knuckleswtf/scribe ^5.0`; não instalar Swagger/gerador paralelo | pacote e configuração já existem; outro gerador criaria dual-source | `composer.json` · `config/scribe.php` |
| D2 | Restringir o matcher desta feature a `connector/api/*`; excluir `oauth/*` | menor superfície e aderência à US-CONN-013 | requirements AC-1 |
| D3 | Desabilitar response calls e fontes de exemplo que leem/criam Models | documentação não pode executar negócio nem incorporar dado real | `config/scribe.php` `response_calls`/`models_source` · AC-2/3 |
| D4 | Curar exemplos sintéticos e contratos a partir do charter/testes; não inferir por chamada real | contrato externo congelado e reprodutível | `CHARTER-rest-api-external.md` · AC-3/4 |
| D5 | Gerar primeiro como artefato controlado no CT 100; publicação é gate humano separado | audiência/URL estão pendentes e `/docs` colide semanticamente | AC-5 · ADR 0062 |
| D6 | Publicação, se autorizada, deve ser fail-closed e não mudar as rotas do Connector | documentação não pode ampliar acesso à API | decisão [W]/[F] na T-04 |

## Plug-points (comparar e NÃO duplicar)

| Onde | O que já existe | Como esta feature encaixa |
|---|---|---|
| `config/scribe.php` | matcher `oauth/*` + `connector/*`, response calls GET, saída `public/docs` | estreitar e tornar a geração segura; não criar segunda config sem necessidade provada |
| `composer.json` | `knuckleswtf/scribe ^5.0` em `require-dev` | reusar o comando do pacote no CT 100 |
| `Modules/Connector/Routes/api.php` | inventário vivo `/connector/api/*` e middleware | fonte da cobertura de paths; não alterar o contrato |
| `CHARTER-rest-api-external.md` | requests/responses imutáveis dos endpoints Delphi | fonte curada dos exemplos e content-types |
| `Modules/Connector/Tests/Feature/` | auth, multi-tenant, rotas e contratos | adicionar safety/coverage test na lane já registrada no `phpunit.xml` |
| `routes/web.php` + `Modules/Officeimpresso/Routes/web.php` | `/documentacao` separado e `/docs` do Officeimpresso | não ocupar URL antes da decisão de audiência |

## Design

- **(a) Dados tocados:** nenhum dado de negócio pode ser lido ou escrito durante a geração. A
  implementação futura poderá alterar apenas configuração, metadados/docblocks, testes e artefato
  documental aprovado.
- **(b) Contratos:** entrada = inventário `route:list --path=connector/api` + charter + FormRequests;
  saída = OpenAPI 3.0 e documentação humana geradas pelo Scribe. Nenhuma rota runtime do Connector
  muda.
- **(c) Interação novo↔existente:** `Scribe (sem response calls) → rotas/metadata curada → artefato
  controlado → scan PII/segredo + comparação de inventário → decisão humana → eventual publicação`.

## Riscos Tier-0

- [x] **Multi-tenant (ADR 0093):** geração não autentica nem consulta tenant; se algum teste precisar
  de contexto, usar somente biz=1 vs biz=99 e fakes, nunca dado de cliente.
- [x] **REGRA MESTRE valor/estoque:** N/A — nenhum cálculo ou escrita. Qualquer response call que
  pudesse executar endpoint fica proibido por D3.
- [x] **PII/LGPD:** risco principal; exemplos sintéticos, token placeholder e scan do artefato são
  DoD. `databaseFirst`, `factoryCreate` e credencial real não entram.
- [x] **Tela (ADR 0264):** N/A — sem `resources/js/Pages/**`; a eventual página gerada não é tela
  de domínio Inertia e sua exposição depende da T-04.
- [x] **Runtime (ADR 0062):** geração/testes somente no CT 100. Hostinger poderá servir apenas
  artefato previamente aprovado, sem daemon ou geração em runtime.

## Alternativas descartadas

- Instalar Swagger/OpenAPI generator novo — duplica Scribe já presente e cria drift.
- Manter matcher largo `oauth/*` + `connector/*` — amplia a auditoria e pode documentar autenticação
  fora do escopo.
- Usar response calls GET ou `databaseFirst` para “exemplos realistas” — risco de PII, tenant leak e
  efeitos colaterais; transação não torna leitura/exposição segura.
- Publicar direto em `public/docs` ou ocupar `/docs` — audiência não decidida e colisão existente.
- Escrever `openapi.yaml` manualmente — fotografia que apodrece; geração deve derivar do contrato vivo.
