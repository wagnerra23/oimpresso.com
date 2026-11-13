---
module: Associacoes
purpose: "Rede de associações (primeira: Uniafibro-SC, associações de pessoas com fibromialgia de SC): cadastro das associações-membro e da documentação delas, das associadas com consentimento LGPD por finalidade, e visão consolidada da rede. A rede inteira é uma empresa. Decidido pela ADR 0451 em 2026-11-13."
migracao_ui: "n/a — módulo novo, nasce Inertia (sem Blade)"
contains:
  - "DataController"
  - "InstallController"
  - "AssociacoesAcesso + AssociacoesLiberado — regra única de acesso: pacote, associacoes.access e alcance por associação (rede inteira com associacoes.rede; senão só onde é representante em assoc_representantes) — ADR 0451 D4"
  - "Associações-membro (assoc_associacoes) e documentos delas que não se apagam (assoc_documentos) — US-ASSOC-001"
  - "Associadas como contacts do núcleo, sem CPF (assoc_associadas) e consentimento LGPD por finalidade que o banco não deixa apagar (assoc_consentimentos) — US-ASSOC-001"
  - "PainelDaRedeController + PendenciasDaRede — painel da rede em /associacoes: números por situação, cidades, associadas ativas e pendências de documentação, no alcance D4 — US-ASSOC-004"
not_contains:
  - "Prontuário, laudo e programa de exercícios → Modules/Fisio (outra empresa: a clínica; sem ligação entre empresas)"
  - "Site público uniafibro.com.br → resources/uniafibro + routes/web.php (HTML estático, sem banco)"
  - "Arquivo do documento (estatuto, ata, certidão) → Modules/Arquivos (chamado, não duplicado)"
  - "Mensalidade, repasse de dinheiro, eleição e votação → fora (ADR 0451 §Fora desta decisão)"
depends_on:
  - Arquivos
owner: wagner
status: em construção
url_prefixes:
  - /associacoes/install (instalação 1-clique; as telas chegam nas ondas 3 a 5 da ADR 0451)
  - /associacoes (painel da rede — onda 5, US-ASSOC-004)
---

# Modules/Associacoes

## Por que existe

[W] pediu em 2026-11-12 a área de membros da Uniafibro-SC: associações-membro, associadas e a
visão da rede. A [ADR 0451](../../decisions/0451-associacoes-rede-uniafibro-area-de-membros.md)
fixou o modelo (uma empresa só) e as decisões D1–D7.

## Fronteira

O módulo é dono do cadastro da rede. Arquivo pertence ao `Modules/Arquivos`; dado clínico fica
no `Modules/Fisio`, em outra empresa, e não se cruza com este módulo.

## Referências

- [SPEC](SPEC.md) · [BRIEFING](BRIEFING.md)
- [ADR 0451](../../decisions/0451-associacoes-rede-uniafibro-area-de-membros.md) · [ADR 0093 — multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
