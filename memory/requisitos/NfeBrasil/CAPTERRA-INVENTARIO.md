---
id: requisitos-nfe-brasil-capterra-inventario
---

# CAPTERRA-INVENTÁRIO — NfeBrasil

> Gerado por skill `comparativo-do-modulo` em **2026-05-06**.
> Fontes: `CAPTERRA-FICHA.md` (16 capacidades) + `SPEC.md` (11 US planejadas) + `Modules/NfeBrasil/` + `resources/js/Pages/NfeBrasil/` + `composer.json`.
> ADR de governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md).

## Resumo

| Bucket | Quantidade | % |
|---|---|---|
| ✅ APROVADO | 0 | 0% |
| 🟡 PARCIAL | 1 | 6% |
| ❌ AUSENTE | 15 | 94% |
| **Total** | 16 | 100% |

**Por score:**

| Score | ✅ | 🟡 | ❌ | Total |
|---|---|---|---|---|
| **P0** (bloqueador) | 0 | 1 | 8 | 9 |
| **P1** | 0 | 0 | 5 | 5 |
| **P2** | 0 | 0 | 2 | 2 |

**Diagnóstico em 1 linha:** módulo é **scaffold puro** — só tem `DataController`+`InstallController`+`Routes` boilerplate de nWidart + 1 listener stub (US-RB-044). Zero domínio fiscal implementado.

**O que existe:** apenas o ponto de integração com cobrança recorrente (listener `EmitirNFeAoReceberPagamento` registrado mas flag-disabled, lança LogicException quando habilitado).

## Inventário detalhado

| # | Capacidade | Score | Status | Evidência | Falta |
|---|---|---|---|---|---|
| 1 | Configurar certificado A1 (.pfx) encrypted | P0 | ❌ | Sem `CertificadoService.php`, sem migration `nfe_certificados`, sem UI | Service + storage encrypted + UI configuração + teste |
| 2 | Emitir NFC-e modelo 65 a partir de venda UPos | P0 | ❌ | Sem `EmitirNfceJob.php`, sem listener `TransactionCompleted`, sem integração `/sells/create` | Listener + job + tributação + idempotência + DANFE 80mm |
| 3 | Emitir NFe modelo 55 (B2B) | P0 | 🟡 | `EmitirNFeAoReceberPagamento.php` existe (stub US-RB-044) — **listener registrado, lança LogicException se habilitado** | `NfeService::emitirModelo55` real + integração sped-nfe + cert loaded + autorização SEFAZ ≥1 prod |
| 4 | Cancelar NFe/NFC-e dentro do prazo legal | P0 | ❌ | Sem `CancelamentoService.php`, sem evento NfeCancelada | Service + validação prazo (24h NFC-e, 168h NFe) + estorno Financeiro + cStat=135 |
| 5 | Inutilização de numeração | P0 | ❌ | Sem `InutilizacaoService.php`, sem migration `nfe_inutilizacoes` | Service + endpoint admin + log audit |
| 6 | Tributação automática (CFOP/NCM/CST/CSOSN) | P0 | ❌ | Sem `MotorTributarioService.php`, sem matriz Simples/Regime Normal | Service + matriz por regime + cobertura ≥5 cenários teste |
| 7 | Multi-tenant (cert + emissões isoladas por business_id) | P0 | ❌ | Sem domínio, sem `BusinessScope`, sem tabelas nfe_* | Models + Migrations com business_id + global scope + teste isolamento |
| 8 | DANFE PDF + DANFE NFC-e (cupom 80mm) | P0 | ❌ | Sem render, sem `nfephp-org/sped-da` em composer | Render via sped-da + storage por chave + reimpressão (US-NFE-003) |
| 9 | Integração nativa na tela de venda UPos (**diferencial**) | P0 | ❌ | Sem listener em `App\Events\TransactionCompleted` | Listener core + UI inline em `/sells/create` finalizar + status broadcast realtime |
| 10 | Visualizar e reimprimir emissão pela chave | P1 | ❌ | Sem `EmissaoController@show`, sem `Pages/NfeBrasil/Emissoes/Show.tsx` | Controller + tela + audit re-download |
| 11 | Carta de Correção Eletrônica (CCe) | P1 | ❌ | Sem `CCeService.php`, sem evento tipo 110110 | Service + UI + persistência nfe_eventos |
| 12 | Contingência SEFAZ (modo offline EPEC/SVC) | P1 | ❌ | Sem `ContingenciaService.php`, sem detecção de timeout | Service + retry job + teste com mock SEFAZ down |
| 13 | API REST pública (Sanctum + rate limit) | P1 | ❌ | `Routes/api.php` é só placeholder | Endpoints autenticados + Sanctum + rate limit + OpenAPI |
| 14 | Emitir NFS-e (mod. serviço) | P1 | ❌ | Sem adapter por município | NfseService + adapters ≥3 cidades (SP/Sorocaba/Campinas) |
| 15 | MDF-e | P2 | ❌ | — | MdfeService + UI + integração SEFAZ MDF-e |
| 16 | Webhook callback pra eventos NFe | P2 | ❌ | — | WebhookDispatcher + HMAC + retry + log |

## Tasks propostas (aguardando aprovação Wagner)

> Recomendação cirúrgica: este módulo é **caminho longo** (16 capacidades, ~120-150h estimadas). Aprovar tudo de uma vez gera backlog imenso bloqueado. Sugestão: aprovar **Onda 1 P0 essenciais (5 tasks)** que destravam emissão básica funcional. Demais P0/P1 entram na Onda 2 após validar.

### Onda 1 — P0 essenciais (5 tasks, ~50h, foundation + emissão básica)

1. **[P0] [Epic] Foundation domínio NFe** — migrations `nfe_certificados`/`nfe_emissoes`/`nfe_eventos`/`nfe_inutilizacoes` + Models + composer require `nfephp-org/sped-nfe` + `nfephp-org/sped-da`. ~16h. **Bloqueia tudo.**
2. **[P0] CertificadoService + storage encrypted + tela admin** — upload .pfx + senha + validação OpenSSL + storage criptografado por business + UI. ~12h. **Sem cert, nada emite.**
3. **[P0] NfeService base + integração sped-nfe** — `emitirModelo55()` real (sem mock); atualiza listener US-RB-044 pra usar service real (remove LogicException). ~12h. **Destrava US-RB-044 fim-a-fim.**
4. **[P0] MotorTributarioService — matriz Simples/Regime Normal** — calcula CFOP/NCM/CST/CSOSN a partir de produto+business; cobertura ≥5 cenários. ~8h.
5. **[P0] DANFE PDF render** — sped-da wired + storage por chave + endpoint download. ~6h. **Sem DANFE não fecha o ciclo.**

### Onda 2 — P0 restantes (4 tasks, ~30h) — após Onda 1 OK

6. [P0] EmitirNfceJob + listener `TransactionCompleted` UPos (diferencial vertical). ~10h
7. [P0] CancelamentoService + validação prazo + estorno Financeiro. ~8h
8. [P0] InutilizacaoService + endpoint admin. ~4h
9. [P0] Tests de isolamento multi-tenant (skill multi-tenant-patterns). ~8h

### Onda 3 — P1 (5 tasks, ~40h) — quando Larissa pedir

10. [P1] EmissaoController@show + tela `/nfe-brasil/emissoes/{chave}`. ~6h
11. [P1] CCeService + endpoint + UI. ~8h
12. [P1] ContingenciaService + retry job (CRÍTICO regulatório). ~10h
13. [P1] API REST pública + Sanctum + OpenAPI. ~10h
14. [P1] NfseService base (3 cidades). ~12h (estimativa otimista — cada cidade tem provider próprio)

### Onda 4 — P2 (2 tasks)

15. [P2] MdfeService
16. [P2] Webhook callback dispatcher

## Próxima reauditoria sugerida

**2026-08-06** (trimestral) ou após mergear ≥3 das P0 da Onda 1.

## Observações estratégicas

- **NFe é regulatório (lei federal)** — qualidade > velocidade. Emissão errada gera multa e pode bloquear tenant fiscalmente. **Não fazer atalhos.**
- **MotorTributarioService é o coração** — sem matriz Simples/Regime Normal correta, emissão erra imposto e contador não aceita. Vale investir nos 5+ cenários teste.
- **Diferencial competitivo está no #9 (integração UPos venda)** — gateway de boletos era commodity em RecurringBilling, mas *aqui* concorrentes (TecnoSpeed/Plug/FocusNFE) **não têm** integração POS nativa. Forte vantagem.
- **Contingência SEFAZ é P1 mas regulatório** — varejo (NFC-e) por lei tem que continuar emitindo mesmo SEFAZ down. Subir antes de Larissa começar usar em volume.
- **Listener US-RB-044 já existe esperando** — assim que Onda 1 fechar (com NfeService real), basta habilitar config flag pra cobrança recorrente disparar NFe automática. Diferencial vertical destravado de graça.
