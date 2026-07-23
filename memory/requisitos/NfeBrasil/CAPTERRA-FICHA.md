---
id: requisitos-nfe-brasil-capterra-ficha
---

# CAPTERRA-FICHA — NfeBrasil

> Ficha canônica de benchmark do módulo NfeBrasil. Fonte da skill `comparativo-do-modulo`.
> Reaproveita matriz e scores de [`COMPARATIVO_CONCORRENCIA.md`](COMPARATIVO_CONCORRENCIA.md) (2026-04-25, próx. revisão 2026-07-25).
> ADR de governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md).

---

## Identidade do módulo

- **Nome interno**: `NfeBrasil`
- **Domínio de negócio**: emissão fiscal eletrônica BR — NF-e (mod. 55) + NFC-e (mod. 65) + NFS-e + cancelamento + inutilização + DANFE
- **Cliente principal alvo**: ROTA LIVRE / Larissa (NFC-e por venda) + qualquer business UltimatePOS que vende com nota fiscal
- **Concorrentes-alvo direto** (4 + UPos integrado é o diferencial):
  - **TecnoSpeed** — tecnospeed.com.br — robusto, R$ [redacted Tier 0]-0,40/nota, foco em médios+
  - **PlugNotas** — plugnotas.com.br — multi-CNPJ, R$ [redacted Tier 0]-0,35/nota
  - **FocusNFE** — focusnfe.com.br — API-first, R$ [redacted Tier 0]/nota, dev-friendly
  - **NFE.io / Conta Azul** — nfe.io — bundle financeiro
  - **eduardokum/sped-nfe** — lib OSS PHP que vamos usar como engine interna (não concorrente, é dependency)

## Comparativos de referência

- [`COMPARATIVO_CONCORRENCIA.md`](COMPARATIVO_CONCORRENCIA.md) — matriz Capterra-style 2026-04-25
- `memory/comparativos/` — sem dedicado a NFe BR ainda (criar quando atualizar)

## Capacidades baseline com score

```yaml
capacidades:
  - nome: "Configurar certificado A1 (.pfx) com senha encrypted"
    score: P0
    descricao: "Upload + validação OpenSSL + storage encrypted-at-rest por business; sem cert, nada funciona"
    quem_tem: ["TecnoSpeed", "PlugNotas", "FocusNFE", "NFE.io"]
    evidencia_de_pronto: "CertificadoService::validar() implementado + storage encrypted + UI configuração + cert vencido bloqueia emissão + teste"

  - nome: "Emitir NFC-e modelo 65 a partir de venda UPos"
    score: P0
    descricao: "Listener TransactionCompleted → job assíncrono → SEFAZ → DANFE PDF em <5s sem fricção no balcão"
    quem_tem: ["TecnoSpeed", "FocusNFE"]
    referencias: ["SPEC US-NFE-002"]
    evidencia_de_pronto: "EmitirNfceJob + tributação automática + idempotência (business_id, transaction_id) + status broadcast + DANFE salvo + ≥1 NFC-e autorizada em prod"

  - nome: "Emitir NFe modelo 55 (B2B)"
    score: P0
    descricao: "Emissão fiscal padrão pra venda B2B; usa nfephp-org/sped-nfe como engine"
    quem_tem: ["TecnoSpeed", "PlugNotas", "FocusNFE", "NFE.io"]
    evidencia_de_pronto: "NfeService::emitirModelo55 + autorização SEFAZ ≥1 prod + DANFE renderizado + listener InvoicePaid (US-RB-044) consume"

  - nome: "Cancelar NFe/NFC-e dentro do prazo legal"
    score: P0
    descricao: "NFC-e ≤24h, NFe ≤168h; valida prazo + envia evento SEFAZ tipo 110111 + persiste em nfe_eventos"
    quem_tem: ["TecnoSpeed", "PlugNotas", "FocusNFE", "NFE.io"]
    referencias: ["SPEC US-NFE-004"]
    evidencia_de_pronto: "CancelamentoService + validação prazo + cStat=135 OK + estorno Financeiro via evento NfeCancelada + teste cobrindo prazo expirado"

  - nome: "Inutilização de numeração"
    score: P0
    descricao: "Pula range de números não usados (obrigação fiscal pra evitar lacuna na sequência)"
    quem_tem: ["TecnoSpeed", "PlugNotas", "FocusNFE", "NFE.io"]
    evidencia_de_pronto: "InutilizacaoService + endpoint admin + log auditável + teste"

  - nome: "Tributação automática (CFOP/NCM/CST/CSOSN)"
    score: P0
    descricao: "MotorTributarioService calcula impostos a partir de produto.NCM + business.regime — sem isso operador precisa decorar tabela ICMS/PIS/COFINS"
    quem_tem: ["TecnoSpeed", "PlugNotas", "FocusNFE"]
    evidencia_de_pronto: "MotorTributarioService::calcular(produto, business) + cobertura matriz Simples/Regime Normal/MEI + teste com 5+ cenários reais"

  - nome: "Multi-tenant (cert + emissões isoladas por business_id)"
    score: P0
    descricao: "Wagner pode operar 56+ businesses; cert vazado entre tenants = catástrofe legal"
    quem_tem: ["PlugNotas (multi-CNPJ é diferencial)", "TecnoSpeed", "FocusNFE"]
    evidencia_de_pronto: "BusinessScope global + cert path includes business_id + teste de isolamento (skill multi-tenant-patterns)"

  - nome: "DANFE PDF + DANFE NFC-e (cupom)"
    score: P0
    descricao: "PDF imprimível obrigatório; NFC-e usa formato cupom 80mm térmico"
    quem_tem: ["TecnoSpeed", "PlugNotas", "FocusNFE", "NFE.io"]
    evidencia_de_pronto: "Renderização via eduardokum/sped-da + storage por chave de acesso + reimpressão (US-NFE-003)"

  - nome: "Visualizar e reimprimir emissão pela chave"
    score: P1
    descricao: "Read da NFe + DANFE + XML pelo número/chave; histórico de eventos"
    quem_tem: ["TecnoSpeed", "PlugNotas", "FocusNFE", "NFE.io"]
    referencias: ["SPEC US-NFE-003"]
    evidencia_de_pronto: "EmissaoController@show + tela /nfe-brasil/emissoes/{chave} + audit re-download"

  - nome: "Carta de Correção Eletrônica (CCe)"
    score: P1
    descricao: "Corrige campos da nota emitida sem cancelar (limites SEFAZ); evento tipo 110110"
    quem_tem: ["TecnoSpeed", "PlugNotas", "FocusNFE", "NFE.io"]
    evidencia_de_pronto: "CCeService + UI + persistência em nfe_eventos + teste"

  - nome: "Contingência SEFAZ (modo offline)"
    score: P1
    descricao: "Quando SEFAZ está fora, emite em contingência (EPEC/SVC) e re-envia depois quando voltar; obrigatório por lei pra varejo"
    quem_tem: ["TecnoSpeed (forte)", "PlugNotas", "FocusNFE"]
    evidencia_de_pronto: "ContingenciaService + detecção automática de timeout/erro SEFAZ + fila retry + teste com mock SEFAZ down"

  - nome: "Integração nativa na tela de venda UPos (diferencial)"
    score: P0
    descricao: "Emissão SEM sair da tela /sells/create — valor de venda da plataforma; concorrentes obrigam alt-tab pra portal externo"
    quem_tem: ["—"]  # diferencial único oimpresso
    evidencia_de_pronto: "Listener em TransactionCompleted core + UI inline na finalização de venda + status realtime + reimpressão sem deixar a tela"

  - nome: "API REST pública pra integração externa"
    score: P1
    descricao: "Endpoints autenticados (Sanctum) pra terceiros emitirem; modelo SaaS"
    quem_tem: ["FocusNFE (killer feature)", "PlugNotas", "TecnoSpeed"]
    evidencia_de_pronto: "Routes/api.php com endpoints documentados + Sanctum + rate limit + OpenAPI schema + teste E2E"

  - nome: "Emitir NFS-e (mod. serviço — varia por município)"
    score: P1
    descricao: "Padrão municipal; cada cidade tem provider próprio (Abrasf, ISSNet, etc.). Larissa não emite ainda mas precisa em breve"
    quem_tem: ["TecnoSpeed", "PlugNotas", "FocusNFE", "NFE.io"]
    evidencia_de_pronto: "NfseService com adapters por município (≥3 cidades cobertas: SP, Sorocaba, Campinas) + teste"

  - nome: "MDF-e (Manifesto de Documento Fiscal)"
    score: P2
    descricao: "Obrigatório quando há transporte; Larissa não tem caminhão próprio mas mercado tem"
    quem_tem: ["TecnoSpeed", "PlugNotas (parcial)"]
    evidencia_de_pronto: "MdfeService + UI + integração SEFAZ MDF-e"

  - nome: "Webhook callback pra eventos de NFe (autorizada/rejeitada/cancelada)"
    score: P2
    descricao: "Notifica sistemas externos do tenant quando estado muda — útil pra integrações"
    quem_tem: ["FocusNFE", "PlugNotas"]
    evidencia_de_pronto: "WebhookDispatcher + assinatura HMAC + retry com backoff + log auditável"
```

## Como auditar este módulo (etapa específica)

> Lida pela skill no passo 2.5.

**Locais a inspecionar (paths esperados):**
- Services: `Modules/NfeBrasil/Services/{Nfe,Certificado,MotorTributario,Cancelamento,Inutilizacao,Cce,Contingencia,Nfse,Mdfe}Service.php`
- Models: `Modules/NfeBrasil/Models/{NfeEmissao, NfeCertificado, NfeEvento, NfeInutilizacao}.php`
- Listeners: `Modules/NfeBrasil/Listeners/{EmitirNFeAoReceberPagamento, OnTransactionCompleted}.php`
- Jobs: `Modules/NfeBrasil/Jobs/{EmitirNfceJob, EmitirNfeJob, CancelarJob, CCeJob, ContingenciaRetryJob}.php`
- Migrations: `Modules/NfeBrasil/Database/Migrations/*` — espera `nfe_certificados`, `nfe_emissoes`, `nfe_eventos`, `nfe_inutilizacoes`
- Tests: `Modules/NfeBrasil/Tests/Feature/` (apenas EmitirNFeAoReceberPagamentoTest.php hoje)
- UI Inertia: `resources/js/Pages/NfeBrasil/{Configuracao, Emissao, Emissoes, Cancelamento}/*.tsx`
- Tabela de impostos: seeder com matriz Simples/Regime Normal por NCM (depende de tabela federal)
- Composer: `nfephp-org/sped-nfe` deve estar em `composer.json` requires (engine de XML+envio); `nfephp-org/sped-da` pra DANFE

**Critérios customizados de classificação:**

| Capacidade | ✅ APROVADO requer | 🟡 PARCIAL aceita |
|---|---|---|
| Certificado A1 | Service + storage encrypted + UI + teste | Service existe sem UI |
| NFC-e | Job + listener + idempotência + DANFE + ≥1 NFC-e autorizada em prod | Service existe sem prod-evidence |
| NFe55 | NfeService::emitirModelo55 + autorização ≥1 prod + listener InvoicePaid funcional | Stub (US-RB-044 atual) |
| Cancelamento | Validação prazo + cStat=135 + estorno Financeiro + teste | Service sem estorno |
| Tributação | MotorTributario com matriz ≥3 regimes + 5+ cenários teste | Hardcoded sem matriz |
| Multi-tenant | BusinessScope + cert path por biz + teste isolamento | Sem teste de isolamento |

**Métricas de prod relevantes** (quando módulo estiver vivo):
- Taxa autorização SEFAZ — meta `>99%` p95 — query: `SELECT cstat, COUNT(*) FROM nfe_emissoes WHERE created_at > NOW()-INTERVAL 30 DAY GROUP BY cstat`
- Latência média emissão — meta `<3s p95`
- Notas emitidas/mês por business — métrica de adoção

## Métricas de adoção

- **Última auditoria**: 2026-05-06 (1ª via skill /comparativo)
- **Capacidades P0 cobertas**: 0/9 (módulo é scaffold puro + listener stub US-RB-044)
- **Próxima reauditoria sugerida**: após mergear ≥3 P0 (provavelmente 2026-Q3)

## Histórico de revisão da ficha

- `2026-05-06` — Wagner — criação via skill /comparativo, baseada em COMPARATIVO_CONCORRENCIA.md de 2026-04-25

---

## UX heuristics (Capterra v2 — eixo Usabilidade)

> Capterra v2 ([ADR 0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md) §3 eixos): além de medir features, mede **como** o concorrente entrega — cliques, tempo, recuperação de erro.
> ⚠️ **TODO Wagner pesquisar/curate** — placeholder vazio até inventariar 3-5 heurísticas P0 do módulo.

```yaml
ux_heuristics: []
  # - id: example-clicks
  #   nome: "Cliques pra ação X"
  #   score: P0
  #   benchmark: "Concorrente A: 1 clique. Concorrente B: 5."
  #   target: "<= 2 cliques"
  #   metrica: "navegacao_steps_X"
```

## Automation targets (Capterra v2 — eixo Automação)

> O que mercado faz **sem humano**? Listener? Cron? Job? Webhook?
> ⚠️ **TODO Wagner pesquisar/curate** — placeholder vazio até inventariar 3-5 automações P0 do módulo.

```yaml
automation_targets: []
  # - id: example-auto-action
  #   nome: "Auto-disparar X quando Y"
  #   score: P0
  #   benchmark: "Concorrente A SIM, B SIM, C PARCIAL"
  #   target: "Listener event Y → JobDoX, p95 < 30s"
  #   metrica: "auto_X_p95_seconds"
```
