---
id: requisitos-captacao-briefing
module: Captacao
status: em-construcao
status_nota: "ADR 0452 proposta em 2026-11-14; onda 1 = ADR + esqueleto + camada de dados (US-CAPT-001), sem rota de negócio nem tela."
updated_at: "2026-11-14"
---

# BRIEFING — Captação

## O que é

Gestão de projetos de captação de recursos de ONGs e associações: fontes (edital, lei de incentivo,
fundo, doação, parceria, voluntariado), projetos com proponente, aportes recebidos e comprovantes de
despesa para a prestação de contas. A primeira empresa a usar é a Uniafibro-SC.

## Estado

- **Decisão:** [ADR 0452](../../decisions/0452-captacao-projetos-de-captacao-de-recursos.md), D1–D8.
- **Código:** `Modules/Captacao` com instalação 1-clique, pacote `captacao_module` (desligado por
  padrão) e as quatro tabelas `captacao_*` (US-CAPT-001). Sem tela ainda.
- **Próximas ondas:** fontes e projetos (US-CAPT-002) → aportes (003) → comprovantes e prestação de
  contas (004) → Financeiro opcional (005). Ordem e escopo no [SPEC](SPEC.md).

## O que depende de [W]

- Dados do primeiro caso real: um **edital municipal de Capivari de Baixo (SC)**, confirmado por [W] em 2026-11-14. Faltam nome, número, órgão, prazo de inscrição e qual associação será a proponente (o edital provavelmente exige CNPJ). Entra como fonte do tipo `edital` quando a tela da onda 2 existir.
- Ligar o pacote `captacao_module` para a empresa da Uniafibro-SC no superadmin.

## Regras que não mudam

- Tudo com `business_id` e escopo global (ADR 0093).
- Proponente é nome e CNPJ no projeto, sem FK para o módulo Associações.
- Aporte e comprovante não se apagam. Voluntário não tem cadastro.
- Dado pessoal e valor de doação nunca em log, commit ou PR.
