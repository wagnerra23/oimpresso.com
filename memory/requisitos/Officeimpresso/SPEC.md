---
modulo: Officeimpresso
status: bridge-legacy
related_adrs: [0136, 0137, 0153, 0154]
na_justified:
  D3.b: "Officeimpresso é bridge legacy Delphi WR Sistemas → oimpresso Laravel. Não é módulo de produto novo, é ponte de migração. BRIEFING canônico vive no projeto principal (Connector + Officeimpresso são pareados). ADR 0136+0137 documentam migração legacy."
  D6.b: "Bridge legacy Firebird → Laravel via Connector REST. p99 OTel <500ms ainda não exportado (instrumentação pendente infra OTel project-wide). Performance é dominada pelo Firebird remoto do cliente — fora do controle do oimpresso. ADR 0136+0137."
  D8.b: "Officeimpresso bridge é backend-only (endpoints REST consumidos pelo Connector/Delphi). Sem views Blade legacy próprias — autenticação via Passport tokens, CSRF N/A em rotas API stateless."
---

# SPEC — Modules/Officeimpresso

## Visão

Bridge legacy entre o ERP Delphi histórico **WR Comercial / WR Sistemas (OfficeImpresso)** e o oimpresso Laravel moderno. Não é módulo de produto novo — é **ponte de migração** que expõe dados Firebird legacy via endpoints autenticados para o app Laravel consumir durante a transição de clientes gráficos.

## Arquitetura atual

- 7 Controllers expondo recursos do legacy (clientes, vendas, NFe, financeiro, licenças, etc) pra polling/sync
- 2 entidades Eloquent próprias: `Licenca_Computador` + `LicencaLog` (controle de licenças WR Comercial Desktop)
- 3 tests Pest cobertura básica (Wave A 2026-05-12 — smoke endpoints + isolation business_id + license guard)
- Pareado com **`Modules/Connector`** — Connector consome Officeimpresso pra migrar cliente legacy → Laravel passo a passo
- Schema bridge consultado em `OFFICEIMPRESSO-FIREBIRD-SCHEMA.md` (skill `officeimpresso-source-analysis`)

## Roadmap

- Migração efetiva por cliente é decidida no projeto principal (Wagner approva por business_id elegível)
- Módulo **será descomissionado** quando o último cliente Delphi sair do legacy (sem ETA — depende de sinal qualificado por cliente, ADR 0105)

## N/A justificado

- **D3.b BRIEFING.md** — Briefing canônico de capacidade vive no projeto principal (não dentro do módulo bridge). Officeimpresso é meio, não fim. ADR 0136 + 0137 documentam estratégia de migração legacy e substituem BRIEFING.md tradicional pra este módulo.

## Referências

- ADR 0136 — Officeimpresso bridge legacy WR Comercial
- ADR 0137 — Connector + Officeimpresso pareados pra migração
- ADR 0153 — Module grade rubric v1
- ADR 0154 — Module grade v2 N/A justificado
- Skill `officeimpresso-source-analysis` — leitura código fonte Delphi
- Skill `officeimpresso-financial-snapshot` — extração receita Firebird
