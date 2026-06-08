---
slug: 0246-tipo-outros-default-migracoes-legacy
number: 246
title: 'Tipo "Outros" como categoria default pra cadastros legacy em migrações'
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by:
  - W
decided_at: '2026-06-03'
module: officeimpresso
quarter: 2026-Q2
tags:
  - migracao-legacy
  - contacts
  - canon-pattern
related:
  - '0021-officeimpresso-contrato-api-delphi'
  - '0197-extend-contacts-absorcao-pessoas-legacy'
  - '0203-legacy-migration-pipeline-firebird-oimpresso-w29'
pii: false
---

# ADR 0246 — Tipo "Outros" como categoria default em migrações legacy

## Contexto

WR Comercial Delphi (e provavelmente outros sistemas legacy de clientes WR Sistemas) usa **35 tipos dinâmicos** de pessoa armazenados como flags `IS_<TIPO>` em uma tabela mestre `PESSOAS`. No banco de produção da WR2:

- 12.233 dos 13.703 registros têm `TIPO='O'` (Outros — pré-venda, leads de feira antiga, pessoa genérica, cancelados, etc)
- Apenas ~700 registros têm papel comercial claro (cliente/fornecedor/equipe/representante)
- 35 papéis dinâmicos coexistem (`IS_CLI`, `IS_FOR`, `IS_FUN`, `IS_REP`, `IS_PDV`, `IS_FSS` Feira Sign 2014, `IS_F15` Feira Sign 2015, `IS_FF6` Feira Fespa 2016, etc)

Próximas migrações (Vargas, Extreme, Gold, Martinho complementar, demais 33 clientes legacy) provavelmente terão papéis customizados específicos ao negócio de cada cliente que **não cabem nos 4 tipos canônicos do oimpresso** (Cliente, Fornecedor, Equipe, Representante).

Sem decisão canônica, cada migração inventaria categoria nova → bagunça multi-tenant + UI inconsistente.

## Decisão

**Adicionar 5º tipo "Outros" no cadastro de contatos do oimpresso como categoria default pra TODOS os registros legacy que não se encaixem nos 4 tipos comerciais existentes.**

### Regras do tipo "Outros"

1. **Validação relaxada:** CPF/CNPJ aparece no formulário mas **não é obrigatório** preencher
2. **Aba no menu:** "Outros" aparece como aba dedicada (após "Repr.") com botão `+ Novo outros`
3. **Aba "Todos":** registros de "Outros" aparecem misturados com os demais tipos
4. **Conversão via chips ADR 0188 (não botão dedicado):** "conversão" entre tipos é nativa via chips de papel da aba Classificação (`ClassificacaoTab.tsx` PATCH `/cliente/{id}/papeis`). Pessoa pode acumular `is_other=1 AND is_customer=1` simultaneamente. Ao adicionar/remover papel, validação CPF/CNPJ é feita server-side conforme tipos ativos (ex: ativar `is_customer=1` sem documento → 422)
5. **Múltiplos papéis preservados:** pessoa pode ter `is_other=true` + `is_customer=true` simultaneamente (refletindo realidade Delphi onde `IS_CLI='S' AND IS_PDV='S'` é possível)

### Regra de mapping pra migrações legacy (canon)

Quando importar de qualquer sistema legacy WR Sistemas (Delphi WR Comercial principalmente) → cadastro de pessoa:

```
SE pessoa tem flag de papel canônico (cliente/fornecedor/equipe/representante)
   ENTÃO usa o tipo correspondente no oimpresso
   E se tem múltiplos papéis, marca todos os tipos simultaneamente

SE pessoa NÃO tem nenhum flag de papel canônico
   OU se tem flag de papel não-canônico (pré-venda, lead de feira, pessoa genérica, cancelado-antigo, etc)
   ENTÃO migra como "Outros"
   E preserva os flags legacy em metadata (json) pra rastreabilidade
```

### Metadata legacy preservada

Todo cadastro migrado guarda em `contacts.metadata` (JSON) os flags legacy originais:

```json
{
  "legacy_source": "wr-comercial-delphi",
  "legacy_business": "wr2-servidor-crm",
  "legacy_pessoa_codigo": "12345",
  "legacy_flags": ["IS_CLI", "IS_PDV", "IS_FSS"],
  "legacy_tipo": "J",
  "legacy_data_cadastro": "2014-03-15"
}
```

Isso permite:
- Reverter migração ou re-importar de forma idempotente
- Auditoria LGPD ("de onde veio esse dado?")
- Análise pós-migração (quantos do tipo "Outros" eram prospects de feira)

## Consequências

### Positivas

- **Migrações futuras simplificadas** — Vargas/Gold/Extreme/Martinho não vão precisar criar tipos novos pra cobrir papéis customizados deles
- **Conversão sem perda** — cliente que começou como prospect ("Outros") vira "Cliente" com clique único, histórico preservado
- **UI consistente** — todas as migrações apresentam o mesmo menu (Todos · Clientes · Fornec. · Equipe · Repr. · Outros)
- **LGPD-friendly** — metadata legacy permite rastrear origem dos dados pra resposta a SVI (Solicitação de Verificação Individual) Art. 18 LGPD
- **Idempotência** — pattern reutilizável em todos importers Wave 30+ via flag `default_to_outros_when_unknown=true`

### Negativas / Custos

- **Esforço inicial:** migration nova em `contacts` (relaxar validação documento + adicionar metadata legacy) + UI nova (aba + botão + tela de conversão)
- **Tipo "Outros" pode virar lata de lixo** — sem disciplina de revisão pós-migração, prospects antigos ficam apodrecendo. Mitigação: alerta dashboard "X% dos seus contatos são 'Outros' há > 1 ano sem interação — quer arquivar?"
- **Conversão entre tipos exige cuidado:** validação na promoção (Outros→Cliente exige CPF/CNPJ) precisa ser feita server-side, não só na UI

### Aplicação retroativa

ADR aplica a TODAS as migrações legacy daqui pra frente. Migrações Wave 29-1 já mergeadas (Martinho biz=164) **não** retroagem — Martinho continua com schema atual sem "Outros". Próxima Wave (30 — WR2 biz=1) é a primeira a implementar.

## Implementação

### Etapa 1 — Ajustes no oimpresso (ANTES da migração)

Pré-existente: ADR 0188 (24/maio/2026) entregou flags aditivas + aba Classificação com chips multi-papel + PATCH `/cliente/{id}/papeis`. Esta ADR só estende.

1. Migration `contacts`:
   - Adicionar coluna `is_other` boolean default false após `is_representative`
   - Adicionar índice composto `idx_contacts_biz_other` (Tier 0 multi-tenant ADR 0093)
   - Sem coluna `metadata` nova — `legacy_raw` JSON catch-all (ADR 0199) já cobre

2. UI (mudanças mínimas, sem tela nova):
   - 6ª aba "Outros" no `SLOT2_TABS` de `Pages/Cliente/Index.tsx` (filtro `?type=other`)
   - Entrada `other` em `ROLE_TITLE` map → botão `+ Novo outros` automaticamente
   - 5º chip "Outros" no array `PAPEL_OPTIONS` em `_drawer/ClassificacaoTab.tsx`
   - **SEM tela nova de conversão** — chips da aba Classificação fazem o trabalho

3. Backend:
   - `ContactController::index` aceita `?type=other` no filtro
   - Endpoint existente PATCH `/cliente/{id}/papeis` (ADR 0188) aceita `is_other` no whitelist
   - Invariante "≥1 papel ativo" continua valendo (com 5 papéis agora)
   - `StoreContactRequest` / `UpdateContactRequest`: CPF/CNPJ condicional — `required_unless:is_other,1`

4. Tests Pest:
   - Cadastro só com `is_other=1` (sem CPF/CNPJ) salva 200 OK
   - PATCH `/papeis` com `is_customer=1` sem CNPJ retorna 422
   - Filtro `?type=other` retorna apenas contatos com `is_other=1`
   - Multi-tenant: biz=1 não enxerga `is_other=1` de biz=99 (ADR 0093 Tier 0)
   - Invariante: desativar último papel ativo bloqueia 422

### Etapa 2 — Importer Wave 30 (DEPOIS da Etapa 1)

Em `scripts/legacy-migration/import-pessoas-from-firebird.py` (ou `import-contacts-wr2.py` novo), adicionar fallback:

```python
def determinar_tipos_oimpresso(pessoa_delphi):
    tipos = set()
    if pessoa_delphi.IS_CLI == 'S' or pessoa_delphi.IS_MEN == 'S' or pessoa_delphi.IS_WEB == 'S':
        tipos.add('customer')
    if pessoa_delphi.IS_FOR == 'S':
        tipos.add('supplier')
    if pessoa_delphi.IS_FUN == 'S':
        tipos.add('team')
    if pessoa_delphi.IS_REP == 'S':
        tipos.add('representative')
    # Fallback canônico ADR 0246
    if not tipos:
        tipos.add('other')
    return tipos
```

### Etapa 3 — Pattern documentation

Atualizar `memory/reference/migracao-officeimpresso-pattern.md` adicionando seção "§3-bis Fallback Outros canon (ADR 0246)" com a regra de mapping acima.

## Riscos

| # | Risco | Mitigação |
|---|---|---|
| R1 | Tipo "Outros" recebe lixo eterno sem revisão | Dashboard alerta `% de Outros há > 1 ano` + sugestão arquivar |
| R2 | Conversão Outros→Cliente sem documento via API REST burla validação | Validation policy server-side independente do client |
| R3 | LGPD — migrar prospects antigos sem consentimento | Metadata `legacy_data_cadastro` permite identificar pessoas > 5 anos pra revisão LGPD; flag `is_archived` automática pra > 10 anos |
| R4 | Múltiplos `is_<tipo>=true` na mesma row complica queries existentes | Backward compat: tipo "primário" em `type` enum preservado (regra: o primeiro tipo da lista determinada) |

## Refs

- [ADR 0021 — Contrato API Delphi](0021-officeimpresso-contrato-api-delphi.md)
- [ADR 0197 — Bucket A+B schema PESSOAS→contacts](0197-extend-contacts-absorcao-pessoas-legacy.md)
- [ADR 0203 — Legacy migration pipeline Wave 29-1](0203-legacy-migration-pipeline-firebird-oimpresso-w29.md)
- [_MAPPING/TELA-PESSOAS.md](../research/clientes-legacy-officeimpresso/_MAPPING/TELA-PESSOAS.md) — mapping canônico Delphi→Laravel
- [memory/research/clientes-legacy-officeimpresso/01-wr-sistemas/02-schema-real-2026-06-03.md](../research/clientes-legacy-officeimpresso/01-wr-sistemas/02-schema-real-2026-06-03.md) — profile WR2 que motivou esta ADR
