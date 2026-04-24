---
status: researching
priority: alta
problem: "oimpresso não emite documentos fiscais (NF-e, NFC-e, MDF-e, CT-e). Cliente real (ROTA LIVRE) não consegue regularizar venda fiscal."
persona: "Operadora de loja (Larissa-caixa) emite venda final + Contador (terceiro) gera SPED mensal"
estimated_effort: "5-6 semanas dedicadas"
references:
  - https://claude.ai/chat/b782e544-d84d-4e00-8d0c-a40e2215ebd3
  - memory/requisitos/_DesignSystem/adr/ui/0006-padrao-tela-operacional.md
  - reference_ultimatepos_integracao.md (auto-memória)
  - feedback_carbon_timezone_bug.md (auto-memória)
related_modules:
  - PontoWr2 (cliente comum: ROTA LIVRE)
  - Officeimpresso (controle de licença, padrão similar)
---

# Ideia: NfeBrasil — emissão fiscal completa

## Problema

oimpresso v6.7 não emite documento fiscal nenhum. ROTA LIVRE (única cliente ativa real, biz=4) opera vendas mas precisa de NFC-e a cada transação por exigência fiscal SP. Hoje contorna fora do sistema. Sem isso, sistema é "ferramenta interna" não "ERP completo".

Conversa Claude mobile (referência principal — ver `evidencias/`) detalhou plano em **7 fases / ~5-6 semanas** e propôs estrutura de **repositório de spec** que a IA lê antes de codar — anti-alucinação.

## Persona

| Persona | Job |
|---|---|
| **Operador POS** (Larissa-caixa) | Click "Finalizar venda" → NFC-e emitida em background + DANFE imprimível |
| **Gestor RH/Admin** | Configura uma vez (cert A1, regime, CSC), monitora rejeições, manifesta NFes recebidas |
| **Contador (terceiro)** | Baixa SPED Fiscal mensal (blocos C100/C170) + livros eletrônicos pra ECF |
| **Auditor SEFAZ** (eventual) | Logs imutáveis 5 anos, XMLs auditáveis, conformidade Portaria/CF |

## Status

`researching` — conversa Claude mobile detalhada existe (extraída 2026-04-24). Spec em formato MemCofre ainda não criada. Aguarda decisão de Wagner pra promover de "ideia" → "requisito".

## Estimativa

**5-6 semanas dedicadas**, dividida em 7 fases:

| # | Fase | Tempo | Entrega |
|---|---|---|---|
| 1 | MVP NFC-e (Simples Nacional, CSOSN 102, SP) | 1 semana | Emissão happy path SP |
| 2 | NF-e completa (Lucro Presumido + Real, CST tradicional) | 1 semana | Vendas B2B |
| 3 | Cancelamento + Carta de Correção | 3 dias | Auditoria fiscal completa |
| 4 | Contingência (offline, EPEC, FS-DA) | 3 dias | Resiliência SEFAZ down |
| 5 | Motor tributário completo (ICMS-ST + MVA + DIFAL + FCP) | 1-2 semanas | Vendas interestaduais |
| 6 | MDF-e + CT-e | 1 semana | Logística |
| 7 | SPED Fiscal/EFD | 1 semana | Obrigação contábil mensal |

## Decisões iniciais (pré-spec)

Ver `decisoes-iniciais.md` quando criada. Pendentes:

- **Lib base:** `eduardokum/sped-nfe` (recomendado pela conversa) vs ACBr (rejeitado — desktop/COM)
- **DANFE:** `sped-da` mencionado como pronto, "só precisa config"
- **Reforma tributária CBS/IBS:** schema já considera `cbs_*` e `ibs_*` em `fiscal_rules` (campos nulos hoje, preenchidos quando legislação consolidar 2026-2033)
- **Storage cert A1:** `CertificateStorageService` com upload + criptografia + validação. Pasta segura fora do `public/`.
- **Numeração:** `NumberSequenceService` com `lockForUpdate` (sequencial garantido).

## Referências externas mencionadas

- TIPI/NCM: https://www.gov.br/receitafederal → CSV
- CEST: site CONFAZ (Convênio 142/2018)
- Protocolos ICMS de ST: site CONFAZ por setor
- Tabela CFOPs: RICMS ou Portal NFe
- Códigos cStat: anexo do Manual de Orientação NFe

## Próximos passos sugeridos

1. **Validar com ROTA LIVRE**: confirmar que NFC-e é o documento prioritário (vs NF-e completo)
2. **Spec leve:** copiar conteúdo de `evidencias/conversa-claude-2026-04-mobile.md` pra estrutura formal `requisitos/NfeBrasil/{README,SPEC,ARCHITECTURE}.md`
3. **Promover:** quando spec leve estiver, mover de `_Ideias/NfeBrasil/` pra `requisitos/NfeBrasil/`
4. **Scaffold:** `memcofre:new-module NfeBrasil` (comando ainda não criado)
5. **Fase 1 MVP:** começar com migrations + CertificateStorageService + tela admin

## Riscos

- **Volume de spec** pode atrasar: tem que escolher entre "spec gigante upfront" (conversa propôs 13 pastas) vs "spec mínima viável" (só Fase 1).
- **Reforma tributária** em curso: legislação muda em 2026-2033. Schema flexível é obrigatório.
- **Multi-tenant + cert A1**: cada business tem cert próprio. Storage tem que ser tenant-isolated.
- **Compliance**: backup XML 5 anos (CF/88 art. 195 §3º) é não-negociável. Storage precisa retenção garantida.

## Conexões

- **MemCofre**: tem `docs_pages` que pode rastrear telas fiscais por @memcofre comments.
- **PontoWr2**: cliente ROTA LIVRE é comum aos dois. Lições aprendidas (timezone, label, persona) aplicam.
- **Design System**: telas (config fiscal, upload cert, lista de NFes) seguem ADR UI-0006 (PageHeader + KpiGrid + DataTable + EmptyState).
