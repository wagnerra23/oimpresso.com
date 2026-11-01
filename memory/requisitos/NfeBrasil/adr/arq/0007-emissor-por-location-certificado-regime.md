---
id: requisitos-nfe-brasil-adr-arq-0007-emissor-por-location-certificado-regime
---

# ADR ARQ-0007 (NfeBrasil) · Local emissor com certificado e regime próprios (multi-CNPJ no mesmo business)

- **Status**: proposed
- **Data**: 2026-10-30
- **Decisores**: Felipe (proposta) · Wagner (aprovação)
- **Categoria**: arq
- **Relacionado**: ARQ-0003 (certificado A1 criptografado), TECH-0001 (numeração com lockForUpdate), R-NFE-031, R-NFE-032, US-NFE-074 (cutover Delphi → oimpresso), US-NFE-078, `ARCHITECTURE.md` (item aberto "Multi-CNPJ por business")

## Contexto

O cliente Sabor Brasil (migração do Delphi WR Comercial, banco Firebird v1525) opera **6 pessoas jurídicas** no mesmo banco, com raízes de CNPJ diferentes e regimes diferentes (uma no Simples Nacional, as demais no Regime Normal). No Delphi elas compartilham cadastro de pessoas, produtos e tabelas de preço; vendas, notas e financeiro são separados por `CODEMPRESA`. A regra do time para migração é **não fazer migração parcial**: as 6 entram.

O oimpresso já permite que um `business_locations` emita como estabelecimento próprio (`nfe_locais_emissores`, Playbook Fiscal thread 26): CNPJ, IE, UF, endereço e **série** do local. Isso foi desenhado para **filial** (mesma raiz de CNPJ). Para pessoas jurídicas diferentes faltam três coisas, medidas no código em 2026-10-30:

1. **Certificado é um por business.** `CertificadoService::carregarParaSefaz($businessId)` pega o único `nfe_certificados` ativo do business; é chamado em ~40 pontos (emissão, cancelamento, CC-e, inutilização, manifestação/DF-e, consulta cadastro). Certificado de uma raiz não assina nota de outra raiz. A própria R-NFE-031 registra: "o certificado continua o do business".
2. **Regime é um por business.** O CRT do `<emit>` vem de `$business->regime` e `nfe_business_configs` é único por business; o motor tributário escolhe CST × CSOSN pelo regime (17 arquivos citam regime).
3. **Numeração é única por business + modelo + série** (`nfe_emissoes_biz_seq_unique`) e a série é única dentro do business. Entre pessoas jurídicas diferentes a SEFAZ controla número por **CNPJ + modelo + série** — duas empresas podem legitimamente usar a série 1, e no cutover cada CNPJ tem de continuar exatamente do número em que o Delphi parou (US-NFE-074).

Alternativas avaliadas para o cliente:

| Opção | Pró | Contra |
|---|---|---|
| A — 1 business por CNPJ | Funciona hoje, zero código fiscal | Duplica pessoas, produtos e preços em 6 businesses; sem visão consolidada; diverge de como o cliente trabalha |
| B — 1 business com 6 locations, sem mudança | Igual ao Delphi | Só 1 CNPJ emite (1 certificado, 1 regime) |
| **C — B + local emissor com certificado e regime próprios** | Igual ao Delphi e todos emitem | Mudança no módulo fiscal em várias fases |

## Decisão (proposta)

**Opção C.** Um local emissor (`nfe_locais_emissores`) pode ter **certificado próprio** e **regime próprio**. Quando não tem, herda do business — comportamento de hoje, sem mudança para quem já emite.

Princípios:

1. **Só acrescenta (ADR 0093 G8).** Business sem local emissor, ou local sem certificado/regime próprios, emite exatamente como antes.
2. **Resolução única do emitente.** `resolverEmitente(business, location, modelo)` passa a devolver também o certificado e o regime a usar. Todo fluxo que fala com a SEFAZ (emissão, cancelamento, CC-e, inutilização, manifestação, DF-e, consulta cadastro) usa essa resolução — nenhum chama `carregarParaSefaz($businessId)` direto para nota de um local.
3. **Certificado amarrado ao CNPJ do local.** O certificado do local só é aceito se a raiz (8 primeiros dígitos) do `cnpj_titular` for a do CNPJ do local; senão a emissão recusa. `nfe_certificados` passa a aceitar um ativo por CNPJ dentro do business.
4. **Regime por local** alimenta o CRT do `<emit>` e o motor tributário (CST × CSOSN, Simples × normal).
5. **Numeração por CNPJ emitente.** A unicidade passa a ser business + CNPJ emitente + modelo + série + número; a série deixa de precisar ser única no business **entre CNPJs diferentes** (continua única entre matriz e filial da mesma raiz). Contador inicial por CNPJ/série configurável para o cutover.
6. **NSU de DF-e por CNPJ** (hoje por business).
7. **Tier 0 (ADR 0093):** local, certificado e numeração sempre filtrados por `business_id`.

## Fases (1 PR ≤ 300 linhas cada, revisão do Wagner)

1. Esta ADR + US-NFE-078.
2. Schema: `nfe_locais_emissores.certificado_id` e `regime`; ativo de `nfe_certificados` por CNPJ; unicidade de numeração com CNPJ emitente.
3. Certificado por local em emissão, cancelamento, CC-e e inutilização.
4. Regime por local no `<emit>` e no `MotorTributarioService`.
5. NSU de DF-e/manifestação por CNPJ.
6. Tela do local emissor (certificado + regime).
7. Homologação SEFAZ: 2 CNPJs de raízes e regimes diferentes no mesmo business.

## Consequências

- O cliente migra com um business só e os cadastros não duplicam.
- A migração de produção da Sabor Brasil fica bloqueada até a fase 7.
- Abre caminho para outros clientes legacy com grupo de empresas no mesmo banco Delphi.

## Perguntas para o Wagner

1. Unicidade da numeração com CNPJ emitente (princípio 5) — de acordo, ou prefere manter série única no business e mapear séries no cutover?
2. `nfe_business_configs` (regime, tributação default, reforma tributária, contingência): só o regime vai para o local, ou outros campos também?
3. Cobrança recorrente (`emitirParaInvoice`) entra nesta mudança ou segue emitindo pelo business?
