---
name: Conversa Claude (mobile) — Implementar documentos fiscais no UltimatePOS
description: Plano detalhado de módulo NfeBrasil pra emissão NF-e/NFC-e/MDF-e/CT-e + SPED + reforma tributária CBS/IBS. Estrutura de repositório de spec, MVP em 7 fases, ~5-6 semanas. Origem: chat mobile Wagner com Claude.
type: evidencia
origin_url: https://claude.ai/chat/b782e544-d84d-4e00-8d0c-a40e2215ebd3
origin_title: "Implementar documentos fiscais no UltimatePOS"
extracted_at: 2026-04-24
originSessionId: 6cbda521-1ac7-4ff2-9419-9acdb42822ac
---
# Conversa #1 — Documentos fiscais no UltimatePOS

**URL origem:** https://claude.ai/chat/b782e544-d84d-4e00-8d0c-a40e2215ebd3
**Título:** "Implementar documentos fiscais no UltimatePOS"
**Quando:** chat mobile recente
**Por que importa:** Wagner disse que vai criar módulos novos. Este é candidato direto — emissão fiscal sempre falta no oimpresso e ROTA LIVRE precisa.

## Decisão arquitetural-chave

**Repositório de SPEC antes de código.** Wagner não vai jogar chat longo no Cursor — IA aluciona. Em vez disso, **gerar pacote de spec** que a IA lê antes de codar.

Estrutura recomendada (`nfe-brasil-spec/`):

```
nfe-brasil-spec/
├── README.md
├── 00-contexto/
│   ├── projeto.md                 # objetivo, escopo, não-escopo
│   ├── ultimatepos-info.md        # versão, Laravel, PHP, estrutura
│   ├── glossario.md               # NFe, NFCe, MDFe, CFOP, CST, etc.
│   └── decisoes-arquiteturais.md  # ADRs: por que sped-nfe e não ACBr
├── 01-requisitos/
│   ├── funcionais.md
│   ├── nao-funcionais.md          # performance, segurança
│   ├── restricoes-legais.md       # 5 anos retenção, LGPD
│   └── fora-de-escopo.md
├── 02-arquitetura/
│   ├── visao-geral.md             # diagrama + fluxo principal
│   ├── modulos.md                 # NfeBrasil, TaxEngine, Printing
│   ├── integracao-ultimatepos.md  # pontos de injeção exatos
│   └── fluxo-nfce.md              # sequence diagram emissão
├── 03-banco-de-dados/             # schemas
├── 08-integracao-ui/
│   ├── onde-editar.md             # arquivos exatos UltimatePOS
│   ├── pontos-de-injecao.md       # @stack, @push, hooks
│   ├── menu-admin.md
│   └── permissoes-ui.md
├── 09-sped-e-obrigacoes/
│   ├── sped-fiscal-efd.md
│   ├── sped-contribuicoes.md
│   └── livros-fiscais.md
├── 10-codigos-sefaz/
│   ├── cstat-catalogo.csv         # todos os códigos traduzidos
│   ├── acoes-sugeridas.md
│   └── rejeicoes-comuns.md
├── 11-testes/
│   ├── estrategia-testes.md
│   ├── casos-teste-motor.md       # matriz completa
│   ├── mocks-sefaz.md
│   └── fixtures/xmls-homologacao/
├── 12-implementacao/
│   ├── fase-1-mvp-nfce.md         # passo a passo
│   ├── fase-2-nfe.md
│   ├── fase-3-cancelamento.md
│   ├── fase-4-contingencia.md
│   ├── fase-5-motor-completo.md
│   ├── fase-6-mdfe-cte.md
│   └── fase-7-sped.md
└── 13-prompts-ia/
    ├── prompt-inicial.md          # contexto que a IA lê primeiro
    ├── prompt-criar-modulo.md
    ├── prompt-implementar-service.md
    └── prompt-implementar-calculator.md
```

## Stack-alvo definida

- UltimatePOS v6.x (CodeCanyon)
- Laravel 10.x → atualizar pra **L13.6** (já feito no oimpresso)
- PHP 8.1+ → **8.4** (Herd local + Hostinger)
- MySQL 8.0 / MariaDB 10.6+
- nwidart/laravel-modules v10+
- Extensões PHP requeridas: `soap, curl, openssl, dom, xsl, zip, gd, bcmath`

## MVP — Fase 1 NFC-e (Simples Nacional, CSOSN 102, SP)

**Objetivo:** emitir NFC-e autorizada em homologação SP, regime Simples Nacional, produto sem ST.

**Pré-requisitos:**
- UltimatePOS instalado e funcionando
- Certificado A1 de homologação
- CSC de homologação SP cadastrado
- Extensões PHP listadas

**Entregas:**
1. Módulo `NfeBrasil` via `php artisan module:make NfeBrasil`
2. Migrations: `fiscal_certificates`, `fiscal_configurations`, `fiscal_documents`
3. Campos adicionais em:
   - `products`: ncm, cest, origem
   - `business`: cnpj, regime
   - `business_locations`: ie, im
4. `CertificateStorageService` (upload, validação, criptografia)
5. `NfceBuilder` (monta XML)
6. `NfceTransmitter` (assina + envia + persiste)
7. `EmitFiscalDocumentJob` (queue)
8. Controllers admin: upload de certificado, config fiscal
9. Botão **"Emitir NFC-e"** no modal de finalização de venda
10. Testes Pest: upload certificado, emissão happy path, rejeição por NCM inválido

**Ordem sugerida:**
1. Migrations e Models
2. CertificateStorageService + tela admin
3. FiscalConfiguration + tela admin
4. NumberSequenceService (com lockForUpdate)
5. NfceBuilder (use fixture de venda em `tests/fixtures/`)
6. NfceTransmitter

## Funcionalidades fiscais ausentes hoje

| # | Item | Status |
|---|---|---|
| 1.1 | Emissão NFC-e/NF-e | ❌ ausente |
| 1.2 | Impressão DANFCe layout 58mm/80mm + QR Code | ❌ (sped-da pronto, só config) |
| 1.3 | Manifestação do Destinatário (DistribuicaoDFe) | ❌ ausente |
| 1.4 | SPED Fiscal e EFD (blocos C100/C170, exportação contábil) | ❌ ausente |
| 1.5 | Livros fiscais eletrônicos + auditoria (log SEFAZ obrigatório, backup XML 5 anos CF/88 art. 195 §3º) | ❌ ausente |

## Reforma Tributária CBS/IBS — atenção

A CBS/IBS começa a valer com **alíquota teste de 1% em 2026** e sobe gradualmente até 2033. Motor precisa estar preparado:

- Adicionar `cbs_cst`, `cbs_aliquota`, `ibs_cst`, `ibs_aliquota` em `fiscal_rules`
- Novos campos no XML da NFe (layout 4.00 → layout novo sendo definido pela SEFAZ)
- Período de transição (2026-2032) onde coexistem PIS/COFINS/ICMS/ISS com CBS/IBS

Schema de `fiscal_rules` deve já considerar isso — campos `cbs_*` e `ibs_*` podem entrar nulos e serem preenchidos quando a legislação consolidar.

## Estimativa de implementação

Tabela parcialmente vista (do screenshot final):

| # | Item | Tempo | Validação |
|---|---|---|---|
| 5 | ICMS-ST + MVA + Protocolos | 1 semana | Bebidas, combustíveis |
| 6 | DIFAL + FCP | 3 dias | Vendas Interestaduais OK |
| 7 | Cache + performance | 2 dias | Sub-100ms por linha |
| 8 | UI de CRUD de regras + simulador | 1 semana | Contador autônomo |
| 9 | Fallback + alertas admin | 2 dias | Segurança |
| 10 | Testes com casos reais | 1 semana | Validação contábil |

**Total: ~5-6 semanas de desenvolvimento dedicado.**

## Tabelas oficiais (sources)

- **TIPI (NCM)**: https://www.gov.br/receitafederal → CSV
- **CEST**: site CONFAZ (Convênio 142/2018)
- **Protocolos ICMS de ST**: site CONFAZ por setor
- **Tabela de CFOPs**: RICMS ou Portal NFe
- **Códigos cStat**: anexo do Manual de Orientação NFe

## Diagramas

Use **Mermaid em markdown** — IA entende perfeitamente. Exemplo do fluxo NFC-e:

```mermaid
sequenceDiagram
    participant POS
    participant Observer
    participant Job
    participant Builder
    participant Transmitter
    participant SEFAZ
    participant DB

    POS->>Observer: TransactionPaid
    Observer->>Job: dispatch(EmitFiscalDocumentJob)
    Job->>Builder: build(transaction)
    Builder-->>Job: XML
    ...
```

## Sugestão final da conversa

Wagner pode pedir pra Claude (a versão mobile) gerar agora o conteúdo completo de:
1. `README.md` geral do projeto
2. `prompt-inicial.md` para a IA
3. `fase-1-mvp-nfce.md` passo a passo
4. `cstat-catalogo.csv` com os 50 erros mais comuns
5. Exemplo `caso-cerveja-sp-intra-sn.md` com cálculo resolvido

OU pedir um template vazio de todos os documentos pra preencher.

## Como aplicar no oimpresso

Memory diz Fiscal/Boleto perdidos na migração 3.7→6.7 (`preference_modulos_prioridade.md`). Quando Wagner decidir começar **NfeBrasil**:

1. Criar `Modules/NfeBrasil/` via `php artisan memcofre:new-module NfeBrasil` (comando ainda não criado, pendente)
2. Pasta `memory/requisitos/NfeBrasil/` com README/SPEC/ARCHITECTURE/CHANGELOG/adr/ — formato MemCofre
3. Migration de spec do repositório `nfe-brasil-spec/` da conversa pra `memory/requisitos/NfeBrasil/`
4. ADR 0001: por que `sped-nfe` (eduardokum) e não ACBr
5. Começar Fase 1 MVP NFC-e SP Simples Nacional

## Referências externas mencionadas

- `eduardokum/sped-nfe` (lib PHP composer) — implícito como escolha provável
- `sped-da` (DANFE) — Wagner mencionou que tem pronto, "só precisa config"
- ACBr (alternativa rejeitada — provavelmente por ser desktop/COM)

## Ideias adjacentes

- **Sistema de prompts pra IA implementar** (pasta `13-prompts-ia/`) — meta-pattern que reusa em outros módulos
- **Repositório de spec separado do código** — mantém spec versionada, permite refactor sem perder requisitos
