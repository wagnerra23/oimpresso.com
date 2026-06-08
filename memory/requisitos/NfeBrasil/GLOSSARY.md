# Glossário — NfeBrasil

> Vocabulário fiscal brasileiro contextualizado pelo módulo.

## Modelos de documento fiscal

- **NF-e** (modelo 55) — Nota Fiscal Eletrônica B2B. Vendas a CNPJ; tributação completa.
- **NFC-e** (modelo 65) — Nota Fiscal de Consumidor Eletrônica. Ponto-de-venda B2C; substitui cupom fiscal.
- **MDF-e** (modelo 58) — Manifesto Eletrônico de Documentos Fiscais. Logística — relaciona NFes em transporte.
- **CT-e** (modelo 57) — Conhecimento de Transporte Eletrônico. Emissor é transportadora.
- **NFS-e** — Nota Fiscal de Serviço Eletrônica. Municipal hoje, federal a partir Lei Complementar 214/2025.

## Componentes da emissão

- **Chave de acesso** — string de 44 dígitos identificadora única da NFe (UF + AAMM + CNPJ + modelo + série + número + tipo + cód + DV).
- **Protocolo** — número retornado pela SEFAZ após autorização.
- **DANFE** — Documento Auxiliar da NFe. PDF imprimível derivado do XML.
- **DANFE-CCe** — DANFE auxiliar da Carta de Correção.
- **CSC** — Código de Segurança do Contribuinte (NFC-e). Usado para gerar QR-code de consulta.
- **Certificado A1** — certificado digital em arquivo `.pfx`. Validade 1 ano. Padrão para SaaS (vs A3 hardware token).

## Status SEFAZ (cStat — códigos comuns)

- **100** — Autorizado o uso da NFe ✓
- **101** — Cancelamento autorizado ✓
- **135** — Evento registrado e vinculado ✓ (cancelamento, CCe)
- **204** — Duplicidade de NFe (já tem essa chave)
- **217** — NFe não consta na base SEFAZ (provavelmente falha rede)
- **224** — Falta atributo (campo obrigatório vazio)
- **539** — IE inválida do destinatário
- **729** — CFOP inválido para CSOSN do emitente
- **754** — Município destinatário inválido

(Lookup table `nfe_cstat_correcoes` mapeia cada um pra sugestão de correção — ver UI-0002)

## Códigos tributários

- **NCM** — Nomenclatura Comum do Mercosul. 8 dígitos. Classifica produto. Usado em ICMS-ST + IPI + PIS/COFINS.
- **CEST** — Código Especificador da Substituição Tributária. 7 dígitos. Para ICMS-ST.
- **CFOP** — Código Fiscal de Operações e Prestações. 4 dígitos. Tipo de operação (5102 = venda mercadoria intra-UF, 6102 = inter-UF, etc.).
- **CST** — Código Situação Tributária. 3 dígitos. Para regimes não-Simples (Lucro Presumido / Real).
- **CSOSN** — Código Situação Operação Simples Nacional. 3 dígitos. Para Simples Nacional (CSOSN 102 = isento sem ST).
- **MVA** — Margem de Valor Agregado. % usada em cálculo de ICMS-ST.

## Impostos brasileiros

- **ICMS** — Imposto sobre Circulação de Mercadorias e Serviços. Estadual.
- **ICMS-ST** — Substituição Tributária. Vendedor recolhe ICMS de toda a cadeia.
- **DIFAL** — Diferencial de Alíquota. Vendas interestaduais B2C.
- **FCP** — Fundo de Combate à Pobreza. Adicional ao ICMS em alguns produtos/UFs.
- **IPI** — Imposto sobre Produtos Industrializados. Federal.
- **PIS** + **COFINS** — Contribuições federais sobre faturamento.
- **CBS** — Contribuição sobre Bens e Serviços. Reforma 2026+.
- **IBS** — Imposto sobre Bens e Serviços. Reforma 2026+.

## Regimes tributários

- **MEI** — Microempreendedor Individual. Limite R$ 81k/ano. Usa CSOSN 102/300.
- **Simples Nacional** — PME até R$ 4,8M/ano. Usa CSOSN.
- **Lucro Presumido** — Empresas até R$ 78M/ano. Usa CST.
- **Lucro Real** — Empresas > R$ 78M ou setores específicos. Usa CST.

## Modos de operação

- **Ambiente homologação** (`tpAmb=2`) — testes; NFes emitidas não têm valor fiscal.
- **Ambiente produção** (`tpAmb=1`) — NFes reais.
- **tpEmis=1** — emissão normal online.
- **tpEmis=4** — EPEC (Evento Prévio Emissão em Contingência) NF-e.
- **tpEmis=9** — FS-DA / NFC-e offline contingência.

## SEFAZ webservices

- **NFeAutorizacao** — envia lote de NFes; recebe protocolo.
- **NFeRetAutorizacao** — consulta resultado de envio assíncrono.
- **NFeStatusServico** — health-check da SEFAZ.
- **NFeConsultaProtocolo** — consulta status de NFe específica.
- **NFeRecepcaoEvento** — cancelamento / CCe / manifestação.
- **NFeDistribuicaoDFe** — recebe NFes endereçadas ao CNPJ (manifestação).

## SPED / EFD

- **SPED Fiscal / EFD ICMS-IPI** — escrituração mensal obrigatória. Bloco C100 (notas próprias) + C170 (itens) + C500 (notas terceiros).
- **SPED Contribuições / EFD-Contribuições** — escrituração PIS/COFINS.
- **EFD-Reinf** — eventos retenção (substituirá DCTF-Web).
- **PVA** — Programa Validador e Assinador. Valida arquivo SPED contra layout.

## Eventos da NFe

- **Cancelamento** (110111) — anula NFe. Prazo legal: 24h NFC-e, 168h NF-e.
- **CCe** (110110) — Carta de Correção Eletrônica. Corrige campos não-monetários.
- **Manifestação** (210200/210210/210220/210240) — destinatário declara: Confirmação, Ciência, Desconhecimento, Operação não realizada.
- **EPEC** (110140) — autorização prévia em contingência NF-e.

## UltimatePOS específico

- **business_id** — tenant (multi-tenant scope).
- **Transaction** core — venda/compra; observers do NfeBrasil escutam para emitir NFe.
- **transaction_payment** — pagamento; NfeBrasil não toca, só Financeiro.
- **session('business_timezone')** — timezone do business (NÃO `session('business.time_zone')`).

## Acrônimos

- **A1 / A3** — tipos de certificado digital (A1 = arquivo, A3 = hardware token)
- **CCe** — Carta de Correção Eletrônica
- **CFe** — Cupom Fiscal Eletrônico (modelo 59 — SP-only — NFC-e substituiu)
- **DAS** — Documento de Arrecadação do Simples
- **DCASP** — Plano de Contas Aplicado ao Setor Público
- **EFD** — Escrituração Fiscal Digital
- **EPEC** — Evento Prévio de Emissão em Contingência
- **FCP** — Fundo de Combate à Pobreza
- **FEBRABAN** — Federação Brasileira de Bancos
- **FS-DA** — Formulário de Segurança - DANFE Auxiliar
- **IBGE** — Instituto Brasileiro de Geografia e Estatística (códigos de município)
- **ICMS-ST** — ICMS Substituição Tributária
- **MVA** — Margem de Valor Agregado
- **PVA** — Programa Validador e Assinador
- **SVAN/SVRS** — SEFAZ Virtual Ambiente Nacional / Rio Grande do Sul (contingência)
