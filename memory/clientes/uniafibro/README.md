---
slug: cliente-uniafibro
status: proposto
authority: canonical
decided_by: [W]
decided_at: "2026-11-17"
tags: [uniafibro, rede, fibromialgia, sc, cliente, associacoes]
related:
  - 0451-associacoes-rede-uniafibro-area-de-membros
---

# Rede Uniafibro — mapa das associações-membro (17/11/2026)

> **Fonte primária:** pasta `C:\Users\wagne\Documents\Uniafibro\` (Wagner pessoal, local; não vive no git por conter PII — CPFs, dados de saúde).
> **Este README** é o artefato público do projeto com o que pode ser versionado: CNPJ (público), cidade, nome fantasia, status.
> **Atualiza** a ADR 0451 (que só mencionava AGFESC + AFIBROMAR) com o mapa real da rede.

## A rede em 1 linha

**Uniafibro-SC** = União das Associações de Fibromiálgicos de Santa Catarina. Em formação (sem CNPJ próprio). Agrega **6 associações formalizadas** + grupos/núcleos locais.

## Associações formalizadas (6)

| # | Sigla | Nome | Cidade/UF | CNPJ | Status | Presidente |
|---|---|---|---|---|---|---|
| 1 | **AGFESC** (Girassóis) | Associação Girassóis Fibromiálgicos do Extremo Sul Catarinense | Criciúma/SC | *pendente registro* | Formalizada (grupo iniciado ago/2020) | Giselli Cunha |
| 2 | **AFIBROMAR** | Associação dos Fibromiálgicos de Maravilha | Maravilha/SC | *pendente registro* | Formalizada | — |
| 3 | **AREFI** | Associação dos Fibromiálgicos de Indaial e Região | Indaial/SC | [REDACTED-CNPJ] | Formalizada 22/09/2023 (RCPJ Indaial 11/10/2023, livro A-034) | Clair Maria Santos Nascimento |
| 4 | **AFICOR** | Associação dos Fibromiálgicos de Concórdia | Concórdia/SC | *pendente registro* | Formalizada | — |
| 5 | **ACPFA** | Associação dos Fibromiálgicos de Joinville | Joinville/SC | *pendente registro* | Formalizada | — |
| 6 | **ASCAF** | Associação dos Fibromiálgicos de Cocal do Sul | Cocal do Sul/SC | *pendente registro* | Formalizada | — |

## Núcleos/grupos em formação (sem CNPJ)

- **Capivari de Baixo/SC** — grupo liderado por Cirlei (projetos: Ateliê Mãos que Florescem, Cuidar em Rede, Proposta Univinte, Projeto Saúde Capivari)
- **Tubarão/SC** — núcleos fisioterapia + massoterapia (propostas: Unisul Ambulatório Amurel, Fundos municipais idosos+PcD)

## Projetos / parcerias ativos (destaque)

- **AREFI × FURB** (Fundação Universidade Regional de Blumenau) — Acordo de Cooperação 017/2024 · Programa Universidade Gratuita (bolsas vinculadas a Lei Complementar SC 831/2023) · Projetos pesquisa-ação e extensão
- **AREFI × Uniasselvi** — proposta Plano Indaial e Médio Vale (ERE da FURB e Uniasselvi)
- **AGFESC × Unesc** (Criciúma) — base do AMASF ambulatório fibromialgia (desde 01/10/2020)
- **AGFESC — Tubarão** — proposta à Unisul ambulatório Amurel
- **Projetos Capivari (via AGFESC):** Ateliê Mãos que Florescem, Cuidar em Rede

## Captação (planos em andamento)

- **Pacote Fibromialgia SC** — emenda de R$ [redacted Tier 0] (fonte local, não commitada)
- **Fundos municipais de Tubarão** (idosos e PcD)
- **Plano de Captação Associação Fibromialgia** (rede AGFESC)
- **AREFI Kit Sede** — cessão de imóvel, IPTU e custeio (versão Indaial)

## Base legal (resumo — ver pesquisas .md na pasta local)

- **Federal:** Lei 14.705/2023 (atendimento SUS fibromialgia/dor crônica/SDRC), Lei 15.176/2025 (equiparação PcD via biopsicossocial), LBI 13.146/2015, LC 142/2013, BPC/LOAS
- **SC estadual:** Lei 18.862/2024 (Maio Roxo, mês fibromialgia), Lei 18.928/2024 (CIPF — Carteira Catarinense PcF emitida pela FCEE)
- **Criciúma municipal:** Lei 7.764/2020 (atendimento preferencial + estacionamento + ônibus), Lei 8.394/2023 (PcD municipal, ampliada pra esclerose múltipla) — envolveu Giselli Cunha em Tribuna Livre 30/05/2023

## Estrutura técnica no sistema

- **1 business no superadmin oimpresso** = Uniafibro-SC (per ADR 0451 D1 — não 1 empresa por associação)
- Associações-membro = registros dentro de `associacoes` table (ADR 0451 D3)
- `cnpj` nullable (6 associações têm, Capivari não)
- `situacao` enum: `em_adesao` / `ativa` / `desligada` (ADR 0451 D3 — não criar `em_formacao` novo, usar CNPJ null)

## Dados sensíveis / LGPD

- **Nomes de dirigentes** (presidente, vice, conselho fiscal): públicos em atas registradas em cartório ou em registros de câmaras municipais. OK incluir aqui.
- **CPFs individuais, RG, endereços residenciais** dos dirigentes: **NUNCA no git**. Ficam só na pasta local Wagner.
- **Dados de associadas** (membros comuns): nome, celular, cidade, consentimento (ADR 0451 D5). **Sem CPF.** Sem diagnóstico/laudo/CID neste módulo.

## Impacto nos agentes em curso (17 sessões em paralelo 2026-11-15/17)

- **Associações merge-train** — Onda 2 seed opcional pode popular as 6 associações (não a Capivari, que fica como grupo em adesão). Precisa emenda ADR 0451 D3 explicitando enum (feita no PR da Onda 1).
- **Captação de Recursos** — enum `tipo_projeto` deve cobrir: emenda_parlamentar (R$ [redacted Tier 0] SC), fundo_municipal (Tubarão idosos/PcD), cooperacao_universidade (FURB, Unisul, Univinte, Uniasselvi), acordo_cooperacao (artigo 14.133/21), convenio_sus, voluntariado, cessao_imovel_iptu (AREFI Kit Sede), plano_captacao_rede.
- **Uniafibro direitos** — Pode pular pesquisa; usar como base as 4 `pesquisa-fibro-*.md` da pasta local Wagner (`09 Pesquisas (leis, direitos, atendimento SC)`). Os conteúdos já estão validados com fonte + ano.
- **Data readiness Uniafibro** — Quando empresa for criada no superadmin, usar AREFI como template de estrutura (estatuto completo disponível). Representante institucional da rede = Giselli Cunha (AGFESC).

## Pendências pra Wagner

1. Confirmar grafias oficiais das siglas (AFIBROMAR/AREFI/AFICOR/ACPFA/ASCAF conferem com pasta)
2. CNPJs das outras 5 associações (AGFESC, AFIBROMAR, AFICOR, ACPFA, ASCAF) — se existem, trazer pra atualizar esta tabela
3. Validar nome completo + email institucional Giselli Cunha (se quiser expor contato público no site)
4. Autorizar criação de arquivos .md individuais por associação em `memory/clientes/uniafibro/<sigla>-<cidade>.md` com estatuto resumido (sem PII)

---

**Última atualização:** 2026-11-17 (Cowork orchestrator, a partir de leitura do PDF AREFI e exploração da pasta `C:\Users\wagne\Documents\Uniafibro\`).
