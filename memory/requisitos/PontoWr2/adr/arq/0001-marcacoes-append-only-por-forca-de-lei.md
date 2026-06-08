# ADR ARQ-0001 (PontoWr2) · Marcações append-only por força de lei

- **Status**: accepted
- **Data**: 2026-04-22 (decisão original ~2024, formalizada aqui)
- **Decisores**: Wagner
- **Categoria**: arq
- **Relacionado**: `memory/decisions/0003-marcacoes-append-only.md` (legado)

## Contexto

Portaria MTP 671/2021 exige que marcações eletrônicas de ponto sejam **imutáveis** (sem UPDATE/DELETE) e auditáveis. Se um registro for alterado, o sistema original tem que provar integridade via hash/assinatura.

## Decisão

Tabela `ponto_marcacoes` é **append-only**:

- Nenhum Eloquent permite `update()` ou `delete()` direto (overrides que lançam exceção).
- Correção usa método `Marcacao::anular()` que cria **novo registro** com `tipo='anulacao'` apontando pro original.
- Triggers MySQL garantem no nível do banco (não pode ser bypassed via raw query).
- Hashes SHA256 do registro concatenado com o anterior formam cadeia auditável.

## Consequências

**Positivas:**
- Conformidade legal garantida por arquitetura, não por disciplina.
- Auditor pode validar integridade sem acessar código.
- Queries históricas nunca mentem (registro original sempre presente).

**Negativas:**
- Tabela cresce sem shrink — mitigar com particionamento por ano quando passar 10M linhas.
- Correções parecem "volumosas" pro usuário (várias linhas por ajuste).

## Alternativas consideradas

- **Soft deletes**: rejeitado — não atende Portaria 671 (exige imutabilidade física).
- **Audit log paralelo**: rejeitado — duplica dado sem garantir que o original ficou intacto.
