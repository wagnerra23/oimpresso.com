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

## User stories

### US-ASSOC-001 · Camada de dados da rede — **P0**

**Implementado em:** `Modules/Associacoes/Database/Migrations/2026_11_13_000001_create_assoc_tables.php` · `Modules/Associacoes/Entities/` · `Modules/Associacoes/Services/AssociacoesAcesso.php` · `Modules/Associacoes/Tests/Feature/AssociacoesEsquemaContratoTest.php`

**Testado em:** `Modules/Associacoes/Tests/Feature/AssociacoesEsquemaContratoTest.php` · `Modules/Associacoes/Tests/Feature/AssociacoesAcessoContratoTest.php`

**DoD:** as cinco tabelas têm business_id com FK; a regra de acesso (`AssociacoesAcesso`) libera só com o pacote e `associacoes.access`, e o representante vê só a associação dele, a diretoria (`associacoes.rede`) vê a rede; os models filtram por empresa (98 não vê 99); a mesma pessoa não entra duas vezes na rede; consentimento não se apaga (banco e Model) e se revoga com `revoked_at`; documento não se apaga pelo Model.

### US-ASSOC-002 · Associações e documentos, com a pendência de 30 dias — **P0**

**Implementado em:** `Modules/Associacoes/Http/Controllers/AssociacoesController.php` · `Modules/Associacoes/Http/Controllers/DocumentosAssociacaoController.php` · `Modules/Associacoes/Services/PendenciaDiretoria.php` · `Modules/Associacoes/Services/DocumentosDaAssociacao.php` · `Modules/Associacoes/Resources/js/Pages/Associacoes/Index.tsx`

**Testado em:** `Modules/Associacoes/Tests/Feature/AssociacoesTelaContratoTest.php`

Cadastro e lista das associações-membro (nome, cidade, região, CNPJ, representante, situação) e dos documentos (estatuto, ata da diretoria, certidão). Mudança de diretoria abre pendência; sem ata nova em 30 dias, ela aparece como atrasada. O representante vê só a associação dele (D4).

**DoD:** a lista e a ficha passam por `AssociacoesAcesso::restringir()` (diretoria vê a rede, representante só a dele, 98 não vê 99); gravar exige `associacoes.editar`, e criar associação e trocar representantes exige também `associacoes.rede`; documento com arquivo no `Modules/Arquivos`, sai da lista com motivo e nunca se apaga; mudança de diretoria abre pendência, ata da diretoria enviada fecha, e passado `associacoes.prazo_ata_diretoria_dias` ela fica atrasada (cálculo único em `PendenciaDiretoria`, que o painel da onda 5 reusa). Casos: `Modules/Associacoes/Resources/js/Pages/Associacoes/Index.casos.md` (UC-ASSOC-00..07).

### US-ASSOC-003 · Associadas com consentimento — **P0**

**Implementado em:** _pendente_ — onda 4 da ADR 0451.

Cadastro da associada pelo contato do núcleo (nome, celular, cidade; sem CPF), com consentimento explícito por finalidade e celular mascarado nas listas.

### US-ASSOC-004 · Painel da rede — **P1**

**Implementado em:** _pendente_ — onda 5 da ADR 0451.

Quantas associações, cidades e associadas ativas, e as pendências de documentação.

### US-ASSOC-005 · Área da associada no app — **P1**

**Implementado em:** _pendente_ — onda 6 da ADR 0451.

Link + PIN no padrão do portal do Fisio, no domínio que o app da Play já abre. A associada vê o próprio cadastro e os consentimentos, e pode revogar.

## Histórico

- 2026-11-13 · v1.0 — criado com a onda 2 da ADR 0451; US-ASSOC-001 entregue (camada de dados), 002 a 005 planejadas.

## Referências

- [ADR 0451 — área de membros da rede Uniafibro](../../decisions/0451-associacoes-rede-uniafibro-area-de-membros.md)
- [ADR 0093 — multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0358 — doutrina de teste tenant 98](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
- [SCOPE](SCOPE.md) · [BRIEFING](BRIEFING.md)
