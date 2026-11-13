---
id: requisitos-associacoes-briefing
module: Associacoes
status: em-construcao
status_nota: "ADR 0451 proposta em 2026-11-13; onda 2 = esqueleto + camada de dados (US-ASSOC-001), sem rota de negócio nem tela."
updated_at: "2026-11-13"
---

# BRIEFING — Associações

## O que é

Cadastro de uma rede de associações: as associações-membro e os documentos delas, as associadas
com consentimento LGPD por finalidade e a visão consolidada da rede. A primeira rede é a
Uniafibro-SC, em formação. A rede inteira é uma empresa no oimpresso.

## Estado

- **Decisão:** [ADR 0451](../../decisions/0451-associacoes-rede-uniafibro-area-de-membros.md), D1–D7.
- **Código:** `Modules/Associacoes` com instalação 1-clique, pacote `associacoes_module` (desligado
  por padrão) e as quatro tabelas `assoc_*` (US-ASSOC-001). Sem tela ainda.
- **Próximas ondas:** associações e documentos (US-ASSOC-002) → associadas (003) → painel (004) →
  área no app (005). Ordem e escopo no [SPEC](SPEC.md).

## O que depende de [W]

- Criar a empresa da Uniafibro-SC no superadmin (pode ser sem CNPJ enquanto o registro não sai) e
  ligar o pacote para ela.

## Regras que não mudam

- Tudo com `business_id` e escopo global (ADR 0093); nada cruza com a empresa da clínica (Fisio).
- Associada sem CPF e sem dado clínico; dado pessoal nunca em log, commit ou PR.
- Consentimento e documento não se apagam.
