---
doc: PLANO-TESTES-FISCAL
escopo: [Modules/Fiscal, Modules/NfeBrasil, Modules/NFSe]
status: draft
versao: 1.0.0
ondas: 7
testes_existentes: 60
classes_erro: 12
prazo_regulatorio: 2027-01-05  # NT 2025.002 IBS/CBS produção
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0101-tests-business-id-1-nunca-cliente
  - 0116-pivot-gold-manifestacao-destinatario-emenda-0115
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0186-chain-certificado-sefaz-consulta-cadastro
  - adr-nfebrasil-arq-0002-lib-sped-nfe-vs-acbr
  - adr-nfebrasil-tech-0002-contingencia-epec-fsda-retentativa-ordenada
  - adr-nfebrasil-ui-0002-monitor-cstat-sugestao-correcao
last_update: 2026-05-25
---

# Plano de Testes Fiscal — Ondas 1-7

> Doc mestre consolidado pro trio fiscal (`Modules/Fiscal` cockpit thin + `Modules/NfeBrasil` emissão real + `Modules/NFSe` LC 214/2025). Substitui inventário ad-hoc por taxonomia de risco × ondas × DoD.

## TL;DR

1. **Hoje:** 60 testes (41 NfeBrasil + 9 NFSe + 10 Fiscal) cobrem **caminho feliz + Tier 0 multi-tenant + append-only CONFAZ** com folga (Waves 23/25/26/27/28 saturação).
2. **Gap aberto crítico:** **Reforma Tributária IBS/CBS (NT 2025.002)** — produção 05/jan/2026, hoje 0 testes. Sem isso, emissão pós-jan/2027 quebra em massa.
3. **Gap aberto operacional:** **rejeição SEFAZ real (cStat)** — temos mock de Service, **não temos fixtures XML de resposta** dos top-10 motivos de rejeição.
4. **Ordem proposta:** auditar 1+2 (1d) → **Onda 6 IBS/CBS** → Onda 3 cStat → 4 cert/contingência → 5 SPED real → 7 chaos.

---

## Escopo dos módulos

| Módulo | Papel | Lib base | Testes hoje |
|---|---|---|---|
| `Modules/Fiscal` | Cockpit thin (7 sub-páginas KB-9.75 + ⌘K palette + ações delegadas) | nenhuma | 10 |
| `Modules/NfeBrasil` | Emissão NF-e 55 + NFC-e 65 + DFe + SPED EFD-ICMS/IPI | [`eduardokum/sped-nfe`](https://github.com/nfephp-org/sped-nfe) (ADR ARQ-0002) | 41 |
| `Modules/NFSe` | NFS-e modelo 56 nacional (LC 214/2025) | adapters por município | 9 |

**Princípio thin:** Fiscal **não duplica** lógica fiscal — lê Models de NfeBrasil/NFSe via `HasBusinessScope`. Cancelamento cascade vive em `app/Domain/Fsm/CancelarVendaCascade` (ADR 0143). Plano de testes respeita essa fronteira.

---

## Taxonomia de erros (12 classes A-L)

| # | Classe | Origem | Severidade | Exemplo concreto |
|---|---|---|---|---|
| **A** | Schema/XSD | Lib `sped-nfe` + MOC 7.0 | 🟠 Alta | cStat 225, tag faltando, CNPJ com letra, ordem trocada |
| **B** | Duplicidade/idempotência | SEFAZ + nossa fila | 🔴 Crítica | cStat 539 retentativa após timeout silencioso |
| **C** | Cadastro/dados | SEFAZ ConsultaCadastro | 🟠 Alta | cStat 217 (homolog vs prod), IE inválida, denegação 207/302 |
| **D** | Certificado A1 | Hostinger storage + chain | 🔴 Crítica | Expirado, senha errada, chain incompleta, cert de outro CNPJ |
| **E** | Contingência SEFAZ | ADR TECH-0002 | 🟠 Alta | SEFAZ offline, EPEC, FS-DA, retentativa ordenada |
| **F** | Eventos pós-autorização | CONFAZ SINIEF 07/2005 Art. 14 | 🟡 Média | CC-e >720h, cancelamento >24h, inutilização sequencial |
| **G** | Manifestação DF-e | ADR 0116 (caso Gold) | 🟡 Média | Prazo 90d, 4 ações (ciência/confirmação/desconhecimento/não realizada) |
| **H** | SPED EFD-ICMS/IPI | PVA-EFD CONFAZ | 🟡 Média | Bloco fora de ordem, E110 saldo credor real, Bloco H inventário |
| **I** | Multi-tenant Tier 0 | ADR 0093 IRREVOGÁVEL | 🔴 Crítica | Cross-tenant leak NfeEmissao/NfeEvento/NfeCertificado |
| **J** | LGPD/append-only | CONFAZ Art. 14 + LGPD Art. 37 | 🔴 Crítica | `forceDelete` em NFe autorizada, PII em log, mutação evento |
| **K** | Reforma Tributária 2026 | NT 2025.002 | 🔴 Crítica | CST × C-ClassTrib inválido, alíquota IBS/CBS/IS errada |
| **L** | UX cockpit | Charters Fiscal | 🟢 Baixa | ⌘K palette, drawer SEFAZ guiado, sub-views 1-7 navegáveis |

---

## Inventário atual (60 testes mapeados por classe)

### `Modules/NfeBrasil/Tests/Feature/` — 41 testes

| Arquivo | Classes cobertas |
|---|---|
| `AbrasfV204CancelDriverTest.php` | F (cancelamento NFSe) |
| `BuscarDfesRecebidosJobTest.php` | G |
| `CertificadoControllerTest.php` | D, L |
| `CertificadoFallbackLegadoTest.php` | D |
| `CertificadoServiceTest.php` | D |
| `DanfeServicePrefersArquivosTest.php` | L |
| `DanfeServiceTest.php` | A (render XML), L |
| `DistribuicaoDfeServiceTest.php` | G |
| `EmitirNFSeJobTest.php` | A (caminho feliz) |
| `EmitirNFeAoReceberPagamentoTest.php` | A (caminho feliz) |
| `EmitirNfceAoFinalizarVendaTest.php` | A (caminho feliz) |
| `EmitirNfceJobTest.php` | A, B (job retry) |
| `EmitirParaInvoiceFallbackTest.php` | A (fallback) |
| `EnviarDanfeNFCePorEmailTest.php` | L |
| `EnviarDanfePorEmailTest.php` | L |
| `HasArquivosTraitTest.php` | I (scope) |
| `ImportRegrasCsvServiceTest.php` | A (validação NCM bulk) |
| `ManifestacaoControllerTest.php` | G, L |
| `ManifestacaoServiceTest.php` | G |
| `MotorTributarioServiceTest.php` | A (motor tributário) |
| `NfeBrasilMultiTenantIsolationTest.php` | **I** (Tier 0 contract) |
| `NfeCartaCorrecaoServiceTest.php` | F (CC-e) |
| `NfeDomainModelsTest.php` | A, I |
| `NfeEmissaoControllerSerializeUrlsTest.php` | L |
| `NfeEventoMultiTenantIsolationTest.php` | **I** (Tier 0 eventos) |
| `NfeInutilizacaoServiceTest.php` | F (inutilização) |
| `NfeServiceCancelarTest.php` | F (cancelamento <24h) |
| `NfeServiceDoubleWriteTest.php` | **B** (double-write idempotência) |
| `NfeServiceEmitirParaTransactionTest.php` | A (caminho feliz integrado) |
| `NfeServiceIdempotenciaRetryTest.php` | **B** (retry idempotente) |
| `NfeServiceRetransmitirTest.php` | A, B (retransmissão rejeitada/erro) |
| `NfeServiceTest.php` | A, contract Service |
| `NfeStatusControllerTest.php` | L (polling status) |
| `NfseCancelServiceTest.php` | F (cancelamento NFSe) |
| `ObservabilityTest.php` | ops (spans OTel) |
| `SyncFiscalRuleToTaxRateTest.php` | A (regra → tax_rate bridge) |
| `TributacaoControllerTest.php` | A, L |
| `TributacaoTemplateServiceTest.php` | A |
| `Wave23SaturationTest.php` | I + J + ops |
| `Wave25NfeSaturationTest.php` | I + J + ops |
| `Wave26SaturationTest.php` | I + J + ops |
| `Wave27NfeSaturationTest.php` | D (cert span) + ops |
| `Wave28NfePolishTest.php` | **J** (CONFAZ Art. 14 grep forceDelete) |

### `Modules/NFSe/Tests/Feature/` — 9 testes

| Arquivo | Classes cobertas |
|---|---|
| `MultiTenantIsolationTest.php` | **I** |
| `NfseCertificadoMultiTenantIsolationTest.php` | **I**, D |
| `ScaffoldTest.php` | smoke |
| `SmokeRoutesTest.php` | L (rotas) |
| `Wave23SaturationTest.php` | I + J |
| `Wave25SaturationTest.php` | I + J |
| `Wave26SaturationTest.php` | I + J |
| `Wave27PolishTest.php` | ops |
| `Wave28NfsePolishTest.php` | J |

### `Modules/Fiscal/Tests/Feature/` — 10 testes

| Arquivo | Classes cobertas |
|---|---|
| `AcoesControllerTest.php` | F, G, L (ações thin delegate) |
| `CockpitMultiTenantTest.php` | **I** |
| `ConfigControllerTest.php` | D, L |
| `DfeControllerTest.php` | G, L |
| `EventosCockpitMultiTenantTest.php` | **I**, F |
| `NfeCockpitMultiTenantTest.php` | **I** |
| `NfseCockpitMultiTenantTest.php` | **I** |
| `PaletteSearchControllerTest.php` | L (⌘K) |
| `SpedControllerTest.php` | H, L |
| `SpedIcmsIpiGeneratorServiceTest.php` | **H** (gerador SPED) |

### Coverage heat-map

| Classe | Hoje | Avaliação |
|---|---|---|
| A Schema/XSD | 12 testes | ✅ saturado (caminho feliz) |
| B Idempotência/duplicidade | 4 testes | 🟡 falta **cStat 539 simulado** |
| C Cadastro/IE/denegação | 0 testes diretos | 🔴 **gap** — só via ConsultaCadastro indireto |
| D Certificado | 6 testes | 🟡 falta cert expirado/wrong-CNPJ/chain |
| E Contingência | 0 testes | 🔴 **gap total** — ADR TECH-0002 sem cobertura |
| F Eventos pós-autorização | 7 testes | ✅ |
| G Manifestação DF-e | 4 testes | ✅ |
| H SPED | 2 testes | 🟡 falta validação PVA-EFD + Bloco E real + Bloco H |
| I Multi-tenant Tier 0 | 11 testes | ✅ saturado (Tier 0 IRREVOGÁVEL coberto) |
| J Append-only/LGPD | 5 testes | ✅ |
| **K Reforma Tributária IBS/CBS** | **0 testes** | 🔴🔴 **GAP CRÍTICO REGULATÓRIO** |
| L UX cockpit | 13 testes | ✅ |

---

## Ondas

Cada onda fecha um conjunto de riscos. Onda 1+2 já estão entregues — listadas pra auditoria. Ondas 3-7 são roadmap real de execução.

### 🟢 Onda 1 — Fundação caminho-feliz

**Risco fechado:** "código quebra em emissão simples NFC-e + NF-e + NFSe".
**Status:** ✅ ENTREGUE.
**Tipos:** Unit + Feature happy-path + Scaffold.
**Classes:** A (parcial), L.
**DoD verificação:**

```powershell
php artisan test Modules/NfeBrasil --filter=EmitirNfceAoFinalizarVendaTest
php artisan test Modules/NfeBrasil --filter=EmitirNFeAoReceberPagamentoTest
php artisan test Modules/NFSe --filter=ScaffoldTest
```

**Ação remanescente:** rodar `php artisan test Modules/{Fiscal,NfeBrasil,NFSe}` end-to-end no CI e confirmar verde.

---

### 🟢 Onda 2 — Tier 0 multi-tenant + LGPD append-only

**Risco fechado:** vazar dado fiscal entre tenants OU apagar NFe autorizada (Tier 0 IRREVOGÁVEL).
**Status:** ✅ ENTREGUE — ADR 0093 + 0101 + CONFAZ Art. 14 + LGPD Art. 37.
**Tipos:** Multi-tenant isolation + reflection-grep `forceDelete`.
**Classes:** I, J.
**Testes-âncora:**
- `NfeBrasilMultiTenantIsolationTest`, `NfeEventoMultiTenantIsolationTest`, `NfseCertificadoMultiTenantIsolationTest`
- Todos `*CockpitMultiTenantTest` em Fiscal (5 arquivos)
- `Wave28NfePolishTest::D2 NfeService NÃO contém forceDelete em código de cancelamento`

**DoD verificação:**

```powershell
php artisan test --filter=MultiTenant
php artisan test --filter=Wave28
```

**Gap pequeno a fechar:** 1 teste cross-tenant pra `SpedIcmsIpiGeneratorService` (gerador SPED enxerga só notas do business atual via `HasBusinessScope`).

---

### 🟡 Onda 3 — Rejeições SEFAZ reais (cStat top-10)

**Risco fechado:** "rejeitou em prod e o usuário não sabe o que fazer". Hoje sabemos parsear cStat (ADR UI-0002), mas **não temos fixtures XML reais** dos motivos comuns.
**Status:** 🔒 BACKLOG.
**Tipos:** Feature com fixture XML de resposta SEFAZ (mock driver da `sped-nfe`).
**Classes:** A, B, C.

**Testes propostos (criar):**

| Arquivo | cStat | Cenário | Classes |
|---|---|---|---|
| `RejeicaoCStat225SchemaTest.php` | 225 | XML mal formado, parser captura motivo, UI mostra sugestão | A |
| `RejeicaoCStat539DuplicidadeTest.php` | 539 | Segunda emissão da mesma chave bloqueada antes do submit | B |
| `RejeicaoCStat217NotaNaoConstaTest.php` | 217 | Homolog vs prod, UI explica ambiente | C |
| `RejeicaoCStat207DenegacaoIETest.php` | 207/302 | IE destinatário irregular, integra `ConsultaCadastroService` (ADR 0186) | C |
| `RejeicaoCStat204DuplicidadeNumeroTest.php` | 204 | Mesmo número+série já autorizado por outra chave | B |
| `RejeicaoCStat694CertificadoTest.php` | 694 | Cert expirado/revogado detectado pelo SEFAZ | D |

**Infraestrutura:**
- Criar `Modules/NfeBrasil/Tests/Fixtures/SefazResponses/cstat-{XXX}.xml` (golden files)
- Criar `Modules/NfeBrasil/Tests/Helpers/RejeicaoFixture.php` (factory)
- Mock driver via interface `SefazWebserviceClient` (extrair de `NfeService` se ainda não existir)

**DoD:**
- 6 fixtures XML reais com cStat documentado
- UI mostra `cstat_motivo` + sugestão de correção (já existe `NfeStatusController`)
- Métrica `nfe_rejeicao_cstat_total{cstat="..."}` populada

---

### 🟡 Onda 4 — Certificado + Contingência + Idempotência avançada

**Risco fechado:** SEFAZ cai numa sex-feira, cert expira no dia da emissão, ou retentativa duplica NFe.
**Status:** 🟡 PARCIAL (cert básico ✅ · contingência ❌).
**Tipos:** Feature + unit + integration.
**Classes:** D, E, B.

**Testes propostos:**

| Arquivo | Cenário | Classes |
|---|---|---|
| `CertificadoExpiradoTest.php` | A1 expira em D-7, UI alerta no cockpit `ConfigController` | D |
| `CertificadoWrongCnpjTest.php` | Cert de outro CNPJ não emite, motivo claro | D |
| `CertificadoChainQuebradaTest.php` | Chain ICP-Brasil incompleta, integra ADR 0186 | D |
| `ContingenciaEpecTest.php` | SEFAZ retorna 503, fluxo entra em EPEC, ordem cronológica preservada | E |
| `ContingenciaFsDaTest.php` | EPEC indisponível, fallback FS-DA, DANFE em papel-segurança | E |
| `RetentativaOrdenadaTest.php` | 3 emissões em paralelo + SEFAZ down + recovery → ordem preservada (ADR TECH-0002) | B, E |
| `SefazTimeoutSilenciosoTest.php` | Timeout >30s + autorização silenciosa SEFAZ + retry → captura cStat 539 esperado | B, E |

**DoD:**
- ContingenciaService (se ainda não existe) com testes verde
- Alerta cockpit "Cert expira em N dias" pop-up
- Métrica `nfe_contingencia_acionada_total{tipo="EPEC|FSDA"}` populada

---

### 🟡 Onda 5 — Eventos pós-autorização + SPED real

**Risco fechado:** CC-e fora de prazo aceita, SPED rejeita no PVA-EFD CONFAZ, Bloco H inventário inconsistente com `Modules/ProductCatalogue` Stock.
**Status:** 🟡 PARCIAL (eventos ✅ · SPED MVP ✅ · validação real ❌).
**Tipos:** Feature + golden-file validação PVA-EFD.
**Classes:** F, G, H.

**Testes propostos:**

| Arquivo | Cenário | Classes |
|---|---|---|
| `CartaCorrecaoPrazoTest.php` | CC-e >720h bloqueada, máximo 20 CC-e por NFe | F |
| `CancelamentoPrazo24hTest.php` | Cancelamento >24h bloqueado (vira evento de denúncia) | F |
| `InutilizacaoFaixaValidaTest.php` | Faixa sequencial, não pode pular números, ano-fiscal correto | F |
| `ManifestacaoPrazo90dTest.php` | Prazo decorrido, 4 ações distintas validadas | G |
| `SpedGoldenFilePvaEfdTest.php` | Output do `SpedIcmsIpiGeneratorService` casa com fixture PVA-EFD validada CONFAZ | H |
| `SpedBlocoE110SaldoCredorTest.php` | Bloco E110 com saldo credor real do mês anterior (não placeholder) | H |
| `SpedBlocoHInventarioRealTest.php` | Bloco H lê `Modules/ProductCatalogue` Stock 31/dez fechamento | H |

**DoD:**
- `SpedIcmsIpiGeneratorService` valida contra PVA-EFD CONFAZ (golden file 23 registros canônicos)
- Output TXT roda no PVA-EFD oficial sem erro estrutural
- Eliana (contadora L3) consegue gerar SPED mensal sem intervenção dev

---

### 🔴 Onda 6 — Reforma Tributária IBS/CBS 2026 ← **PRAZO REGULATÓRIO**

**Risco fechado:** NFe rejeitada em massa pós 05/jan/2026 por combinação CST × C-ClassTrib inválida (NT 2025.002).
**Status:** 🔴 BACKLOG ZERO COBERTURA. **Sem essa onda, emissão pós-jan/2027 quebra em produção.**
**Tipos:** Unit (matriz tributária) + Feature (emissão com novos grupos) + Migration.
**Classes:** K + A.

**Testes propostos:**

| Arquivo | Cenário | Classes |
|---|---|---|
| `IbsCbsCstClassTribMatrixTest.php` | Matriz completa CST × C-ClassTrib válida vs inválida (rejeição preventiva client-side) | K |
| `IbsAliquotaEstadualTest.php` | 27 UFs × alíquota IBS, validador local antes do submit | K |
| `CbsAliquotaFederalTest.php` | Alíquota CBS federal única, cálculo correto sobre base | K |
| `IsImpostoSeletivoTest.php` | Imposto Seletivo só em produtos específicos (lista CONFAZ) | K |
| `NfeBrasilNT2025002NovosGruposTest.php` | 7 novos grupos NT 2025.002 presentes no XML emitido | K, A |
| `MotorTributarioServiceIbsCbsTest.php` | Expansão de `MotorTributarioServiceTest` com IBS/CBS/IS | K |
| `MigrationNfeFiscalRulesIbsCbsTest.php` | Schema `nfe_fiscal_rules` ganha `c_class_trib`, `cst_ibs`, `cst_cbs`, `aliquota_ibs`, `aliquota_cbs`, `aliquota_is` | K |

**Migrations necessárias:**
- `add_ibs_cbs_columns_to_nfe_fiscal_rules` (additive — append-only)
- `add_ibs_cbs_columns_to_nfe_emissoes` (campos calculados gravados na emissão)

**Compatibilidade:**
- Período transitório 2026: emissões antes de 05/jan/2026 sem grupos IBS/CBS; depois com.
- Feature flag `nfe.ibs_cbs.enabled` per business (rollout escalonado).

**DoD:**
- Matriz completa testada (CST × C-ClassTrib)
- 1 cliente piloto biz emite NFe com IBS/CBS em ambiente homolog SEFAZ
- Métrica `nfe_ibs_cbs_aplicado_total` populada
- Comparativo Bling/Tiny pra confirmar paridade

**Dependência:** atualização da lib `eduardokum/sped-nfe` pra versão que suporte NT 2025.002 (validar antes de começar).

---

### 🔵 Onda 7 — Chaos + Contract + Saturation contínua

**Risco fechado:** SEFAZ muda contrato sem aviso, latência sobe em produção, fila empilha, espelho de homologação desatualiza.
**Status:** 🟡 PARCIAL (saturation ✅ · chaos/contract ❌).
**Tipos:** Saturation reflection + contract snapshot + chaos engineering.
**Classes:** E + ops.

**Testes propostos:**

| Arquivo | Cenário | Classes |
|---|---|---|
| `Wave29NfeSaturationTest.php` | Próxima saturation onda (mantém ritmo) | I, J, ops |
| `SefazContractSnapshotTest.php` | Snapshot dos XSDs por UF + diff CI alerta quando MOC atualiza | A |
| `SefazChaos500_503Test.php` | SEFAZ retorna 500/503/HTML/lentidão >30s → contingência aciona | E |
| `NfeFilaSobrecargaTest.php` | 1000 emissões enfileiradas, sem perda, sem duplicação, ordem preservada | B, E |
| `OtelSpansP95Test.php` | p95 emissão <8s, p95 cancelamento <4s, alertas Grafana | ops |

**DoD:**
- CI alerta quando XSD oficial SEFAZ muda (cron diário pull do schema CONFAZ)
- Dashboard Grafana com p50/p95/p99 emissão/cancelamento/manifestação
- Runbook de incidente "SEFAZ caiu" referenciado de `Modules/NfeBrasil/RUNBOOK-smoke-sefaz.md`

---

## Ordem de execução recomendada

| # | Onda | Justificativa | Esforço (IA-pair, ADR 0106) |
|---|---|---|---|
| 0 | Auditar 1+2 com `--coverage` | Confirmar verde antes de avançar | 1d humano |
| 1 | **Onda 6 IBS/CBS** | Prazo regulatório real (05/jan/2026 produção) — sem ela emissão pós-jan/2027 quebra | 3-5d IA-pair |
| 2 | Onda 3 cStat | Eliana/Larissa sentem direto quando rejeita | 2-3d IA-pair |
| 3 | Onda 4 cert/contingência | Quando SEFAZ cair em sex/feriado vira incidente | 2-3d IA-pair |
| 4 | Onda 5 SPED real | Pré-fechamento mensal Eliana (contadora) | 3-4d IA-pair |
| 5 | Onda 7 chaos/contract | Defensiva — depois que volume justifique | 2d IA-pair |

**Total:** ~12-17 dias IA-pair (ADR 0106 fator 10x aplicado).

---

## Apêndice A — Top-10 cStat (fixtures Onda 3)

| cStat | Significado | Frequência | Solução guiada |
|---|---|---|---|
| **225** | Falha no Schema XML do lote | 🔴 muito alta | Validador local antes de submit |
| **539** | Duplicidade de NF-e com chave divergente | 🔴 muito alta | Idempotência fila + chave determinística |
| **204** | Duplicidade de NF-e (mesmo número+série) | 🟠 alta | Pular pra próximo número |
| **217** | NF-e não consta na base SEFAZ | 🟠 alta | Verificar ambiente (homolog vs prod) |
| **207** | Uso denegado: irregularidade fiscal destinatário | 🟡 média | ConsultaCadastro pré-submit (ADR 0186) |
| **301/302** | Denegação emitente/destinatário | 🟡 média | Bloqueio CONFAZ — não retenta |
| **694** | Certificado expirado/revogado | 🟠 alta | Alerta pré-vencimento |
| **656** | Excesso de consultas em curto intervalo | 🟢 baixa | Rate limit fila |
| **108/109** | SEFAZ ambiente indisponível | 🟠 alta | Contingência EPEC (Onda 4) |
| **999** | Erro genérico (parsear motivo) | 🟢 baixa | Log + escalação manual |

Fonte: [Tabela cStat oficial sped-nfe](https://github.com/nfephp-org/sped-nfe/blob/master/docs/cStat.md) + [Xpertus 2026](https://xpertus.com.br/blog/nfe-rejeicoes-mais-comuns-como-resolver-guia-2026).

---

## Apêndice B — NT 2025.002 IBS/CBS grupos novos (Onda 6)

| Grupo XML | Campo | Descrição |
|---|---|---|
| `IBSCBS` | `CST` | CST específico IBS/CBS (substitui parcialmente CST ICMS) |
| `IBSCBS` | `cClassTrib` | Código de Classificação Tributária (NOVO) |
| `gIBSCBS` | `vBC` | Base de cálculo unificada |
| `gIBSUFDest` | `pIBSUF` | Alíquota IBS estadual (variável por UF) |
| `gCBS` | `pCBS` | Alíquota CBS federal (única) |
| `gIS` | `pIS` | Alíquota Imposto Seletivo (lista CONFAZ) |
| `total/ICMSTot` | `vIBS`, `vCBS`, `vIS` | Totalizadores no grupo |

**Prazo:** produção a partir de **05/jan/2026**. Período transitório com ICMS+IPI+PIS+COFINS coexistindo até 2027-2028 (regime dual).

Fonte: [TecnoSpeed NT 2025.002](https://blog.tecnospeed.com.br/nota-tecnica-reforma-tributaria-nfe-nfce/) + [Contábeis IBS/CBS 2026](https://www.contabeis.com.br/noticias/74328/ibs-e-cbs-2026-erros-de-classificacao-que-podem-causar-rejeicao-de-notas-fiscais/).

---

## Apêndice C — Comandos artisan úteis

```powershell
# Suite completa fiscal
php artisan test Modules/Fiscal Modules/NfeBrasil Modules/NFSe

# Por onda
php artisan test --filter=MultiTenant                   # Onda 2
php artisan test --filter=Rejeicao                      # Onda 3 (após criar)
php artisan test --filter=Contingencia                  # Onda 4 (após criar)
php artisan test --filter=Sped                          # Onda 5
php artisan test --filter=IbsCbs                        # Onda 6 (após criar)
php artisan test --filter=Wave2                         # Saturation acumulada

# Coverage por módulo
php artisan test Modules/NfeBrasil --coverage --min=70

# Smoke SEFAZ homolog (manual, não CI)
php artisan nfebrasil:smoke-sefaz biz=1 ambiente=homolog
```

---

## Referências canônicas

- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — testes usam biz=1, NUNCA biz=4 (Larissa real)
- [ADR 0116](../../decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md) — pivot Gold DFe
- [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM cancelamento cascade
- [ADR 0186](../../decisions/0186-chain-certificado-sefaz-consulta-cadastro.md) — chain A1 + ConsultaCadastro
- [ADR ARQ-0002 NfeBrasil](../NfeBrasil/adr/) — lib `eduardokum/sped-nfe`
- [ADR TECH-0002 NfeBrasil](../NfeBrasil/adr/) — contingência EPEC/FS-DA
- [ADR UI-0002 NfeBrasil](../NfeBrasil/adr/) — monitor cStat → sugestão
- [SCOPE.md Fiscal](../../../Modules/Fiscal/SCOPE.md) — cockpit thin agregador
- [SCOPE.md NfeBrasil](../../../Modules/NfeBrasil/SCOPE.md) — emissão real
- [PII-LGPD-FISCAL.md](../NfeBrasil/PII-LGPD-FISCAL.md) — append-only contract
- [RUNBOOK-smoke-sefaz.md](../NfeBrasil/RUNBOOK-smoke-sefaz.md) — smoke biz=1 ambiente homolog

---

**v1.0.0** (2026-05-25) — Plano inicial consolidado. 7 ondas, 12 classes de erro, 60 testes mapeados, gap regulatório IBS/CBS identificado como prioridade Onda 6.
