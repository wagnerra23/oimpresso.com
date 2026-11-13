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

**Implementado em:** _pendente_ — onda 3 da ADR 0451.

Cadastro e lista das associações-membro (nome, cidade, região, CNPJ, representante, situação) e dos documentos (estatuto, ata da diretoria, certidão). Mudança de diretoria abre pendência; sem ata nova em 30 dias, ela aparece como atrasada. O representante vê só a associação dele (D4).

### US-ASSOC-003 · Associadas com consentimento — **P0**

**Implementado em:** _pendente_ — onda 4 da ADR 0451.

Cadastro da associada pelo contato do núcleo (nome, celular, cidade; sem CPF), com consentimento explícito por finalidade e celular mascarado nas listas.

### US-ASSOC-004 · Painel da rede — **P1**

**Implementado em:** _pendente_ — onda 5 da ADR 0451.

Quantas associações, cidades e associadas ativas, e as pendências de documentação.

### US-ASSOC-005 · Área da associada no app — **P1**

**Implementado em:** `Modules/Associacoes/Services/PortalAssociadaService.php`, `Modules/Associacoes/Http/Controllers/PortalAssociadaController.php`, `Modules/Associacoes/Http/Controllers/PortalLinkController.php`, `Modules/Associacoes/Resources/js/Pages/Associacoes/Public/Membro.tsx`

Link + PIN no padrão do portal do Fisio, no domínio que o app da Play já abre. A associada vê o próprio cadastro e os consentimentos, e pode revogar.

- Rota pública `/associacoes/m/{token}` (sem login, throttle): PIN → "Meus dados" (nome, associação, situação, data de entrada) → autorizações com "Retirar". Retirar grava `revoked_at`; `associada` só a associação encerra.
- Gerar/revogar: `POST|DELETE /associacoes/associadas/{id}/portal` (`associacoes.editar` + alcance D4). O botão entra na tela de associadas da onda 4.
- Token HMAC com o business assinado, PIN de 4 dígitos com trava de 5 erros / 30 min, só hashes em `assoc_portal_tokens`. "Lembrar este aparelho" ainda não existe.
- Abre dentro do app só depois de o TWA da Play aceitar `/associacoes/m/` no intent-filter (hoje só `/fisio/p/`); até lá abre no navegador.
- Casos: `Modules/Associacoes/Resources/js/Pages/Associacoes/Public/Membro.casos.md` (UC-AMB-01..06) · Teste: `Modules/Associacoes/Tests/Feature/AssociacoesPortalMembroContratoTest.php`.

## Histórico

- 2026-11-13 · v1.0 — criado com a onda 2 da ADR 0451; US-ASSOC-001 entregue (camada de dados), 002 a 005 planejadas.

## Referências

- [ADR 0451 — área de membros da rede Uniafibro](../../decisions/0451-associacoes-rede-uniafibro-area-de-membros.md)
- [ADR 0093 — multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0358 — doutrina de teste tenant 98](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
- [SCOPE](SCOPE.md) · [BRIEFING](BRIEFING.md)
