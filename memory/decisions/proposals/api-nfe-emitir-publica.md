# API pública de emissão de NFe/NFC-e — feature-wish — 2026-05-15

> **Status:** feature-wish (não é US ativa). Per [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md), backlog só recebe item se cliente paga + reporta. Aqui o sinal é hipótese ("cliente potencial pode querer") — fica registrado e vira US ativa quando houver compromisso comercial assinado.

## Contexto

Sessão 2026-05-15 com Wagner discutindo integração HyperCash (gateway pagamento BR — POST `/api/user/transactions` com Basic Auth). Wagner observou que **o mesmo prospect ou outro pode querer emitir NFe via API do oimpresso** — usar nosso backend fiscal (cert A1, motor tributário, transmissão SEFAZ) como serviço.

Esta feature-wish documenta esforço + riscos + modelo comercial pra Wagner decidir quando aparecer o 1º cliente disposto a pagar.

## Estado atual do código (snapshot 2026-05-15)

**Backend NFe — sólido em prod:**
- `Modules/NfeBrasil` em prod biz=4 (ROTA LIVRE) há 2+ anos emitindo NFC-e modelo 65
- `NfeService::emitirParaTransaction(transaction, modelo)` — emite 55 ou 65 com cert A1 + motor tributário aplicado
- `EmitirNfceJob` queued via listener `SellCreatedOrModified` (auto-emissão pós-venda)
- Idempotência interna por `transaction_id + modelo`
- `MotorTributarioService` aplica CFOP/CSOSN/CST por produto cadastrado
- `DanfeService` gera PDF, `ManifestacaoService` cobre destinatário, `NfeInutilizacaoService` cobre cancelamento
- `CertificadoService` armazena .pfx encrypt-at-rest por business

**API pública — não existe:**
- [Modules/NfeBrasil/Routes/api.php:18](../../../Modules/NfeBrasil/Routes/api.php) — só stub `Route::get('nfebrasil', fn(Request $r) => $r->user())`
- [NfeEmissaoController::emitir](../../../Modules/NfeBrasil/Http/Controllers/NfeEmissaoController.php:54) é rota **web** — lê `$request->session()->get('business.id')` (token Sanctum/Passport não popula sessão)
- Endpoint atual recebe só `{tx}` (Transaction já existente) — pressupõe venda criada via UI antes
- Não aceita payload NFe completo via JSON (cliente, items, CFOP, NCM, valores)

**Auth disponível pra reusar:** Passport OAuth2 já em uso em `Modules/Connector/Routes/api.php` (Delphi WR Comercial integrado desde 2026-04). Mesmo fluxo `/oauth/token` password grant + client credentials serve pra cliente externo.

## Modelo de uso — DECISÃO TRAVA TUDO

Antes de qualquer linha de código, Wagner precisa decidir **quem é o emissor da NFe**:

| Modelo | Como funciona | Risco | Recomendação |
|---|---|---|---|
| **A — Cliente vira business no oimpresso** | Cada cliente da API cadastra o próprio CNPJ + cert A1 + dados fiscais no oimpresso. Vira mais 1 `business_id`. NFe é emitida com CNPJ dele. | Baixo — modelo legal padrão. Cada cliente responde pelas próprias notas. | ✅ **Único viável.** |
| **B — Emite com CNPJ do oimpresso** | Cliente passa só items, oimpresso emite NFe com CNPJ próprio. | **Vermelho fiscal.** Configura interposição fraudulenta de pessoa (Lei 8.137/1990, Art. 1º) + responsabilidade tributária integral do oimpresso por nota emitida pra terceiro. | ❌ NÃO. |

Se Wagner aceitar Modelo A, o produto vira "**NFe-as-a-Service white-label**": cliente terceiro usa nosso backend fiscal mas continua sendo o emissor legal. Modelo de mercado canônico (igual NFe.io, eNotas, FocusNFe).

## Escopo MVP (Modelo A confirmado)

### Endpoints novos

| Método | Path | Função | Esforço |
|---|---|---|---|
| `POST /v1/nfe/emissoes` | Emite NFe/NFC-e (modelo 55 ou 65) com payload completo (destinatário, items, valores) | core — orquestra `NfeService` | 1d |
| `GET /v1/nfe/emissoes/{id}` | Consulta status (autorizada / rejeitada / pendente / cancelada) + cStat SEFAZ | leitura | 0,3d |
| `POST /v1/nfe/emissoes/{id}/cancelar` | Cancela NFe autorizada (até 24h, evento 110111) | reusa `NfseCancelService` | 0,5d |
| `GET /v1/nfe/emissoes/{id}/danfe` | Download DANFE PDF | reusa `DanfeService` | 0,2d |
| Webhook OUT `nfe.autorizada` / `nfe.rejeitada` | SEFAZ é assíncrono — notifica receiver do cliente quando muda status | retry policy + HMAC saída | 1d |

### Plumbing

- FormRequest validando payload completo (items array, CPF/CNPJ destinatário, NCM por item, CFOP)
- Transformação payload→Transaction interna (`business_id` do token Passport)
- `Idempotency-Key` header (UUID por emissão — evita double-emit em retry de rede)
- Pest cross-tenant biz=1 vs biz=99 ([ADR 0101](../0101-tests-business-id-1-nunca-cliente.md))
- Doc Scribe (anotações `@bodyParam`, `@response`, `@authenticated`) — publicada em `oimpresso.com/docs`
- Sandbox SEFAZ (URL homologação já configurada no service — só expor flag por business)

### Total: 4–5 dias dev (1 pessoa) + 1d Eliana[E] revisar ToS

## Faseamento sugerido

### Fase 1 — Backend API (3d)
- Endpoint POST emissão + FormRequest + idempotência
- Endpoint GET status + GET DANFE + POST cancelar
- Pest cross-tenant + sandbox SEFAZ por flag

### Fase 2 — Webhook OUT + observabilidade (1,5d)
- `NfeWebhookOutDispatcher` com retry exponencial (3 tentativas: 30s, 5min, 30min)
- Assinatura HMAC saída (`X-Oimpresso-Signature: sha256=...`)
- `audit_log` table cobrindo cada chamada API + cada webhook out
- Métrica Prometheus: `nfe_api_emissoes_total{biz,status,modelo}`

### Fase 3 — Doc + comercial (1,5d)
- Scribe anotações + publicação `oimpresso.com/docs` (extensão do MVP Mubisys já planejado)
- Página landing `oimpresso.com/api/nfe`
- ToS + LGPD (Eliana[E] revisa)
- 2 exemplos de integração (curl + PHP) na doc

**Total: ~6 dias dev. Marca de validação:** 1 cliente real emite 1 NFe homologação SEFAZ via API → autorizada → DANFE baixado.

## Modelo comercial — opções

(Wagner decide na conversa com cliente. Benchmark mercado BR 2025-2026:)

| Modelo | Concorrência | Vantagem | Desvantagem |
|---|---|---|---|
| **Setup + mensal fixo** | NFe.io: R$ 0 setup + R$ 89-499/m por faixa de notas | Previsível pro cliente | Não escala com sucesso dele |
| **Setup + por NFe emitida** | eNotas: R$ 0 setup + R$ 0,89-1,49 por NFe | Cliente paga proporcional ao uso | Variável — cliente sazonal pode reclamar |
| **Setup + mensal + overage** | FocusNFe: R$ 0 setup + R$ 99/m até 100 NFe + R$ 0,79 excedente | Híbrido — entry baixa + escala | Mais complexo de explicar |

**Proposta inicial sugerida (a confirmar com Wagner):**
- **Setup R$ 2.500** (cobre 6d de dev — payback no 1º cliente)
- **Mensalidade R$ 199/m** com até 200 NFe/m incluídas
- **R$ 0,79 por NFe excedente**
- Sandbox grátis ilimitado (homologação SEFAZ)
- **Mínimo 12 meses** primeiro cliente (ROI dev + diluir risco fiscal)

## Riscos

| Risco | Probabilidade | Mitigação |
|---|---|---|
| **Cliente terceiro manda NCM/CFOP errado → SEFAZ rejeita** | Alta | API retorna cStat + xMotivo da rejeição. ToS deixa claro: cliente responde por dados; oimpresso só transmite. Doc + exemplos. |
| **Cert A1 do cliente vence sem aviso** | Alta | Cron já existente (`nfe:check-cert-expiration`) + webhook OUT `cert.expirando` 30/15/7d antes |
| **Webhook OUT do oimpresso falha — cliente perde notificação** | Média | Retry 3× + endpoint `GET /emissoes` pra cliente reconciliar manualmente |
| **Cliente esquece `Idempotency-Key` em retry → emite 2 NFe** | Média | Doc destaca como **obrigatório**, não opcional. Header ausente = HTTP 400 |
| **Volume alto satura queue de emissão** | Baixa hoje, alta com 5+ clientes | Queue dedicada `nfe-api` + Horizon supervisor separado |
| **Responsabilidade tributária derrapada** | Baixa se ToS sólido | **Eliana[E] revisa ToS antes de assinar 1º contrato.** Modelo A protege juridicamente. |
| **SEFAZ cai (rede ou contingência)** | Média | API retorna 503 + retry-after header. Cliente programa retry lado dele. |

## Sinal qualificado pendente (gate ADR 0105)

Esta feature-wish vira US ativa quando **um** dos abaixo:

- [ ] Cliente potencial assina contrato/proposta com cláusula API NFe
- [ ] Cliente legacy OfficeImpresso migrado pede explicitamente integração via API (não UI)
- [ ] 2+ prospects na mesma semana citam "API NFe" como bloqueador (sinal de mercado)
- [ ] Wagner decide investir como diferencial competitivo independente de cliente confirmado (vira ADR aceito + cycle goal — não feature-wish)

Enquanto nenhum dos 4 gatilhos: este doc fica em `proposals/`. Não cria US no MCP, não consome dev.

## Próximos passos (se aprovar)

1. Wagner conversa com cliente — confirma Modelo A + faixa de preço aceita
2. Eliana[E] rascunha ToS (modelo white-label NFe — referência: ToS NFe.io/eNotas público)
3. Criar US no MCP `tasks-create module:NfeBrasil title:"API pública NFe emissão MVP"` apontando pra este doc
4. Promover este doc pra `memory/decisions/0NNN-api-nfe-emissao-publica.md` (status `aceito`)
5. Adicionar capacidade ao `CAPTERRA-FICHA.md` do NfeBrasil (selling point novo: "API REST pública pra terceiros emitirem com seu CNPJ")

## Refs

- [ADR 0105 — Cliente como sinal](../0105-cliente-como-sinal-guiar-sem-mandar.md) — gate pra virar US ativa
- [ADR 0093 — Multi-tenant Tier 0](../0093-multi-tenant-isolation-tier-0.md) — `business_id` scope obrigatório no token
- [ADR 0101 — Tests biz=1 nunca cliente](../0101-tests-business-id-1-nunca-cliente.md)
- [proposals/api-docs-mvp-mubisys.md](api-docs-mvp-mubisys.md) — Scribe doc framework reusável
- [memory/requisitos/NfeBrasil/SPEC.md](../../requisitos/NfeBrasil/SPEC.md) — US-NFE-001..NNN
- [memory/requisitos/NfeBrasil/ARCHITECTURE.md](../../requisitos/NfeBrasil/ARCHITECTURE.md)
- Lei 8.137/1990 Art. 1º (interposição fraudulenta — fundamenta rejeição Modelo B)
- CONFAZ SINIEF 07/2005 Art. 14 (cancelamento NFe — janela 24h)
