---
date: "2026-11-14"
topic: "Captação: ADR 0452 e esqueleto do Modules/Captacao (US-CAPT-001)"
authors: ["W", "C"]
prs: [10895]
outcomes:
  - "ADR 0452 escrita (proposto): módulo próprio para projetos de captação, D1–D8"
  - "Modules/Captacao com 4 tabelas captacao_* (business_id + FK + escopo) e teste 98×99"
  - "Proponente como nome+CNPJ no projeto e voluntariado só como aporte, por decisão de [W]"
---

# Captação: ADR 0452 e esqueleto do módulo

## O que foi feito

[W] pediu um módulo para os projetos de captação de recursos da Uniafibro-SC e das associações-membro: edital, lei de incentivo, fundo, doação, parceria e voluntariado. A ADR 0451 (Associações) deixou dinheiro de fora; Captação ficou como módulo próprio.

**Busca prévia:** zero ADR, task, commit ou tabela de captação. A 0451 só existe no PR #10862, então a 0452 estava livre.

**Achado que mudou o desenho:** a Uniafibro ainda não tem CNPJ, e Rouanet, FIA e FUMPROAD exigem CNPJ do proponente. Por isso o proponente é texto no projeto, e quem inscreve hoje é a associação-membro.

**Entregue no PR #10895:**
- a ADR 0452;
- o módulo, modelado no esqueleto das Associações (#10878): pacote, permissões, middleware, instalação e 4 tabelas;
- o teste de contrato;
- SCOPE, SPEC e BRIEFING.

O teste entrou na lane `verticais-pest`, igual às Associações.

## Tropeços

- Ao editar `verticais-pest.yml`, a âncora de 6 espaços também casava a linha de 14 espaços (LC-16). O assert de contagem segurou antes de gravar.
- O par de barra invertida colapsou no transporte do comando (LC-26). Troquei por `chr(92)`.
- Os dois foram pegos antes do commit. Não chegaram ao repo e não geram ocorrência nova no ledger.
