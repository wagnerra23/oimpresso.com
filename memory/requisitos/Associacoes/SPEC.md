---
id: requisitos-associacoes-spec
module: Associacoes
status: ativo
version: "1.0"
last_updated: "2026-11-13"
lifecycle: em-construcao
related_adrs:
  - "0451-associacoes-rede-uniafibro-area-de-membros"
  - "0093-multi-tenant-isolation-tier-0"
  - "0358-doutrina-de-teste-tenant-98-supersede-0101"
owner: [W]
---

# Especificação funcional — Associações

> Convenção do ID: `US-ASSOC-NNN`. A ordem segue as ondas da [ADR 0451](../../decisions/0451-associacoes-rede-uniafibro-area-de-membros.md).

### US-ASSOC-001 · Camada de dados da rede — **P0**

**Implementado em:** `Modules/Associacoes/Database/Migrations/2026_11_13_000001_create_assoc_tables.php` · `Modules/Associacoes/Entities/` · `Modules/Associacoes/Tests/Feature/AssociacoesEsquemaContratoTest.php`

**Testado em:** `Modules/Associacoes/Tests/Feature/AssociacoesEsquemaContratoTest.php`

**DoD:** as quatro tabelas têm business_id com FK; os models filtram por empresa (98 não vê 99); a mesma pessoa não entra duas vezes na rede; consentimento não se apaga (banco e Model) e se revoga com `revoked_at`; documento não se apaga pelo Model.

### US-ASSOC-002 · Associações e documentos, com a pendência de 30 dias — **P0**

**Implementado em:** _pendente_ — onda 3 da ADR 0451.

Cadastro e lista das associações-membro (nome, cidade, região, CNPJ, representante, situação) e dos documentos (estatuto, ata da diretoria, certidão). Mudança de diretoria abre pendência; sem ata nova em 30 dias, ela aparece como atrasada. O representante vê só a associação dele (D4).

### US-ASSOC-003 · Associadas com consentimento — **P0**

**Implementado em:** _pendente_ — onda 4 da ADR 0451.

Cadastro da associada pelo contato do núcleo (nome, celular, cidade; sem CPF), com consentimento explícito por finalidade e celular mascarado nas listas.

### US-ASSOC-004 · Painel da rede — **P1**

**Implementado em:** _pendente_ — onda 5 da ADR 0451.

Quantas associações, cidades e associadas ativas, e as pendências de documentação.

### US-ASSOC-005 · Área da associada no app — **P1**

**Implementado em:** _pendente_ — onda 6 da ADR 0451.

Link + PIN no padrão do portal do Fisio, no domínio que o app da Play já abre. A associada vê o próprio cadastro e os consentimentos, e pode revogar.
