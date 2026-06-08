---
module: NfeBrasil
alias: nfe-brasil
status: ativo
migration_target: react
migration_priority: alta
risk: alto
problem: "oimpresso v6.7 não emite documento fiscal nenhum. ROTA LIVRE (única cliente ativa) precisa de NFC-e a cada venda por exigência fiscal SP. Sem NfeBrasil, sistema é ferramenta interna, não ERP completo."
persona: "Operador POS (Larissa-caixa) + Gestor (config) + Contador terceiro (SPED) + Auditor SEFAZ"
positioning: "Vender com nota fiscal sem virar contador. NFC-e em 1 clique no caixa, NF-e B2B sem fricção, SPED pronto no fim do mês — direto do ERP que já roda o caixa."
estimated_effort: "5-6 semanas dev sênior (7 fases)"
revenue_tier: 1B
revenue_pricing:
  starter: "R$ 99/mês — NFC-e Simples Nacional, 1 UF, até 500 docs/mês"
  pro: "R$ 299/mês — NF-e + NFC-e completas, multi-UF, ICMS-ST, contingência, até 5.000 docs/mês"
  enterprise: "R$ 599/mês — + MDF-e + CT-e + SPED Fiscal/EFD + cert digital gerenciado, ilimitado"
revenue_take_rate: "n/a (subscription puro — emissão fiscal não tem GMV)"
references:
  - https://claude.ai/chat/b782e544-d84d-4e00-8d0c-a40e2215ebd3
  - eduardokum/sped-nfe
  - eduardokum/sped-da
  - reference_ultimatepos_integracao.md
  - feedback_carbon_timezone_bug.md
related_modules:
  - Financeiro
  - PontoWr2
  - Officeimpresso
last_generated: 2026-04-24
last_updated: 2026-04-24
---

# NfeBrasil

> **Pitch para o tenant:** _Vender com nota fiscal sem virar contador._ NFC-e em 1 clique no caixa, NF-e B2B sem fricção, SPED pronto no fim do mês — direto do ERP que já roda o caixa.

## Propósito

Tornar oimpresso emissor fiscal completo brasileiro:

- **NFC-e** (modelo 65, B2C ponto-de-venda) — emissão em background no clique "Finalizar venda" do POS
- **NF-e** (modelo 55, B2B) — para vendas a CNPJ com cálculo tributário completo (ICMS, ICMS-ST, IPI, PIS, COFINS, DIFAL, FCP)
- **MDF-e + CT-e** — logística (transporte de mercadorias)
- **SPED Fiscal / EFD ICMS-IPI** — obrigação contábil mensal
- **Contingência** — modos offline / EPEC / FS-DA quando SEFAZ está fora
- **Cancelamento + Carta de Correção** — fluxo completo
- **Schema flexível CBS/IBS** — preparado para Reforma Tributária 2026-2033

Compliance crítico: legislação BR exige guarda de XML por 5 anos (CF/88 art. 195 §3º). Falha de emissão = não pode vender.

## Posicionamento de mercado (revenue thesis)

NfeBrasil é **compliance-forced**: tenant que opera no varejo BR PRECISA emitir nota. Não tem "free tier" do produto (não dá pra vender meia nota). Pricing assume tenant paga premium pela tranquilidade jurídica.

| Plano | Preço/mês | Limite | Margem (estimada) |
|---|---|---|---:|
| **Starter** | R$ 99 | 500 NFC-e/mês, 1 UF, Simples Nacional | 75% |
| **Pro** | R$ 299 | 5.000 docs/mês, multi-UF, todos regimes, contingência | 80% |
| **Enterprise** | R$ 599 | Ilimitado + MDF-e + CT-e + SPED + cert gerenciado | 85% |
| **Cert digital A1 anual gerenciado** | R$ 199/ano (add-on) | Renovação automática + storage | 60% |

**Subscription puro** (não há take rate): emissão fiscal não move dinheiro do cliente, oimpresso não intermedia → cobrança previsível, fácil de explicar.

**Lock-in muito alto**: cliente que tem 6 meses de XMLs no oimpresso não migra. CAC pago em 2-3 meses.

## Índice

- **[SPEC.md](SPEC.md)** — user stories US-NFE-NNN + regras Gherkin R-NFE-NNN
- **[ARCHITECTURE.md](ARCHITECTURE.md)** — camadas, modelos, integração com core e SEFAZ
- **[GLOSSARY.md](GLOSSARY.md)** — vocabulário fiscal BR (CST/CSOSN, NCM, CFOP, CSC, etc.)
- **[CHANGELOG.md](CHANGELOG.md)** — versão a versão
- **[adr/](adr/)** — decisões numeradas (`arq/`, `tech/`, `ui/`)
- **[RUNBOOK.md](RUNBOOK.md)** — _stub_ — operações: rotacionar cert A1, restaurar contingência, etc.

## Áreas funcionais

| Área | Controller(s) principais | Por que existe |
|---|---|---|
| **Configuracao** | `ConfiguracaoFiscalController`, `CertificadoController` | Setup inicial: cert A1 + CSC + regime + ambiente (homologação/prod) |
| **Emissao** | `NfeController`, `NfceController` | Emitir NF-e e NFC-e (transação principal) |
| **Cancelamento** | `CancelamentoController` | Cancelar NFe (até 24h NFC-e / 168h NF-e) |
| **Correcao** | `CartaCorrecaoController` | Carta de Correção Eletrônica (CCe) — corrige erros não-monetários |
| **Contingencia** | `ContingenciaController` | Modo offline / EPEC / FS-DA quando SEFAZ está fora |
| **Manifestacao** | `ManifestacaoController` | Manifestar NF-e recebida (operação destinada) |
| **Logistica** | `MdfeController`, `CteController` | MDF-e + CT-e (transporte) — Onda 6 |
| **Tributacao** | `TributacaoController`, `MotorTributarioService` | Cálculo automático: ICMS, ICMS-ST, MVA, DIFAL, FCP, IPI, PIS, COFINS, CBS, IBS |
| **Sped** | `SpedController` | Geração SPED Fiscal/EFD ICMS-IPI mensal |
| **Monitor** | `MonitorController` | Dashboard rejeições + status SEFAZ + filas |

## Quem ganha o que

| Persona | Job (concretos) | Tela atende |
|---|---|---|
| **Larissa-caixa** (operador POS) | "Finalizar venda + emitir NFC-e em 1 clique" | `/sells/create` (botão "Emitir NFC-e" — integração POS↔NfeBrasil) |
| | "Reimprimir DANFE de venda de ontem" | `/nfe-brasil/emissoes/{numero}` |
| **Gestor RH/Admin** | "Subir cert A1 novo + testar emissão homologação" | `/nfe-brasil/configuracao/certificado` |
| | "Por que essa nota foi rejeitada?" | `/nfe-brasil/monitor` (rejeições com cStat + correção sugerida) |
| **Contador (terceiro)** | "Baixar SPED Fiscal mês corrente" | `/nfe-brasil/sped?mes=2026-04` (link compartilhável read-only) |
| **Auditor SEFAZ** (eventual) | "XMLs de 2024 da empresa X" | API `/nfe-brasil/api/xmls?periodo=...` (token assinado) |

## Status atual (2026-04-24)

- ✅ **Spec promovida** de `_Ideias/NfeBrasil/` para `requisitos/NfeBrasil/` (`spec-ready`)
- ⏳ **Fase 1 (MVP):** NFC-e Simples Nacional CSOSN 102 SP — 1 semana
- ⏳ **Fase 2:** NF-e Lucro Presumido + Real CST tradicional — 1 semana
- ⏳ **Fase 3:** Cancelamento + Carta de Correção — 3 dias
- ⏳ **Fase 4:** Contingência (EPEC + FS-DA) — 3 dias
- ⏳ **Fase 5:** Motor tributário ICMS-ST + MVA + DIFAL + FCP — 1-2 semanas
- ⏳ **Fase 6:** MDF-e + CT-e — 1 semana
- ⏳ **Fase 7:** SPED Fiscal/EFD — 1 semana

## Onde se conecta

- **Core UltimatePOS** — observers em `Transaction` (sells/purchases) → sob demanda emite NF-e/NFC-e. Não-emissão NÃO bloqueia venda (assíncrono via queue) mas exibe alerta.
- **Financeiro** — DRE consome NF-e emitida (receita bruta + impostos retidos). Evento `NfeAutorizada` carrega `chave_acesso` e atualiza `fin_titulos.metadata.nfe_chave`.
- **RecurringBilling** — fatura recorrente paga emite NFS-e (modelo análogo a NF-e, mas serviço). Estende NfeBrasil com `NfseProvider`.
- **Officeimpresso** — Superadmin licencia NfeBrasil por business; cert A1 é "1 cert por business" (não pode compartilhar cross-tenant).
- **MemCofre** — telas finais terão `// @memcofre US-NFE-NNN` declarando stories cobertas.

## Próximos passos imediatos

1. **Validar com ROTA LIVRE** (biz=4, SP, Simples Nacional): qual CSOSN aplica? Qual CSC já tem cadastrado na SEFAZ-SP?
2. **Decidir lib**: `eduardokum/sped-nfe` vs alternativa? Ver ADR ARQ-0002 (decisão tomada)
3. **Scaffold módulo** + hooks DataController + permissões Spatie no boot
4. **Migrations + seeders** — tabelas NCM/CEST/CFOP/CSOSN/CST pré-seeded (datasets públicos Receita Federal/CONFAZ)
5. **Fase 1 MVP**: certificado upload + 1 NFC-e happy path SP + DANFE PDF + 3 testes Pest (auth, isolamento, validação)
