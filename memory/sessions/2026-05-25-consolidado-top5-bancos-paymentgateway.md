---
data: 2026-05-25
tipo: consolidado-research
modulo: PaymentGateway
onda: 4f-4j (proposta)
aprovado_por: Wagner (pesquisa 2026-05-25; arquitetura dual-mode 2026-05-25)
adr_proposto: 0170-bancos-nativos-top5-dual-mode
status: aguardando-aprovacao-adr
---

# Consolidado pesquisa top-5 bancos PaymentGateway

> Origem: Wagner pediu "fazer pacote completo top-5 bancos" 2026-05-25. 5 dossiês paralelos via `audit-research-expert`:
> - [arte-banco-bradesco.md](2026-05-25-arte-banco-bradesco.md)
> - [arte-banco-itau.md](2026-05-25-arte-banco-itau.md)
> - [arte-banco-bb.md](2026-05-25-arte-banco-bb.md)
> - [arte-banco-santander.md](2026-05-25-arte-banco-santander.md)
> - [arte-banco-caixa.md](2026-05-25-arte-banco-caixa.md)

## 1. Matriz consolidada — viabilidade × esforço × calendário

| Banco | Verdict | Cód. REST (h) | Humano-limit | DX rank | Lib `eduardokum/laravel-boleto` |
|---|---|---|---|---|---|
| **Itaú** | ✅ VIÁVEL | 7-9h (reuso 80% Inter) | 2-3 sem | 🥇 | CNAB ✅ · API ❌ |
| **BB** | ✅ VIÁVEL c/ ressalva | 12-18h | 10-25 dias | 🥈 | CNAB ✅ · API ❌ |
| **Bradesco** | 🟡 PARCIAL | 24h (+80 LOC PKCS#7) | 14 dias irredutíveis | 🥉 | CNAB ✅ · API ❌ |
| **Santander** | 🟡 PARCIAL | 22h | 3-5 sem | 4º | CNAB ✅ · API ❌ |
| **Caixa** | ❌ INVIÁVEL REST | — (skip) | 30-90 dias se forçar | 5º | CNAB ✅ · API ❌ |

**Cobertura `eduardokum/laravel-boleto`:** API REST APENAS pro Inter (já incorporado). CNAB pra 11 bancos incluindo os top-5. Webhook só Inter.

## 2. Estratégia aprovada Wagner 2026-05-25 — Drivers separados por modalidade

> **v1 dual-mode (1 driver com `mode` REST|CNAB) rejeitado Wagner mesmo dia:** "prefiro driver diferente. não misture o gatway. configurações completamente diferentes"
>
> **v2 corrente:** cada combinação `banco+modalidade` é um `gateway_key` próprio com wizard, schema de credencial, contract e Pest próprios. Sem `mode` column.

| Banco | API REST | CNAB |
|---|---|---|
| Bradesco | `bradesco_api` (Onda 4i) | `bradesco_cnab` (Onda 4f.cnab) |
| Itaú | `itau_api` (Onda 4f) | `itau_cnab` (Onda 4f.cnab) |
| BB | `bb_api` (Onda 4g) | `bb_cnab` (Onda 4f.cnab) |
| Santander | `santander_api` (Onda 4j) | `santander_cnab` (Onda 4f.cnab) |
| Caixa | — (INVIÁVEL hoje) | `caixa_cnab` (Onda 4f.cnab) |

**Cliente experience:**
- Dia 1: cliente cadastra `{banco}_cnab` → preenche convênio + carteira + cedente + SFTP opcional → **emite boleto via arquivo remessa imediato**
- Dia N: quando banco homologar Open API → cliente cadastra `{banco}_api` ADICIONAL (não substitui CNAB) → migra cobranças novas pra REST gradual → eventualmente desativa CNAB
- Cliente pode ter 2 `payment_gateway_credentials` ativas pro mesmo banco — `for(Account)` resolver usa `gateway_key` no `EmitirCobrancaInput` ou fallback "última ativa"

## 3. Ordem proposta de execução (ondas)

### Onda 4f.0 — Fundação `CnabBoletoAdapter` (~6h, SEQUENCIAL obrigatório)

- Abstract `CnabBoletoAdapter` — bridge `EmitirCobrancaInput` ↔ `Eduardokum\LaravelBoleto\Boleto\Banco\{X}` (5 implementações finas herdam dele)
- Job `CnabRetornoProcessor` — recebe arquivo retorno upload (manual ou auto-SFTP), parse via lib, dispatch `CobrancaPaga`/`CobrancaVencida`
- UI `/payment-gateways/{id}/cnab-retorno` — upload manual + histórico processamento
- `payment_gateway_credentials.config_json` aceita schema CNAB (convênio, sequencial, carteira, cedente, SFTP) — **sem column nova**, `gateway_key` distingue modalidade
- Pest: `CnabBoletoAdapterContractTest` — contrato compartilhado pros 5 CnabDrivers

### Onda 4f.cnab — 5 drivers CNAB em PARALELO (~10h total, ~2h cada)

Spawn 5 `audit-implement-expert` em worktrees isolados (sem overlap entre bancos):
- `BradescoCnabDriver`, `ItauCnabDriver`, `BBCnabDriver`, `SantanderCnabDriver`, `CaixaCnabDriver`
- Cada um fino — herda `CnabBoletoAdapter`, configura layout (240 ou 400) + banco específico via lib
- Wizard CNAB próprio: convênio + carteira + agência + conta + cedente + SFTP host/user/key opcional

### Onda 4f — `ItauApiDriver` REST (~9h)

Razão #1 REST: **reuso 80% InterDriver** + Bolecode bônus. Mesmo OAuth2 + mTLS + payloads BACEN. Sandbox fechado mitigável (`Http::fake()` + fixtures).

### Onda 4g — `BBApiDriver` REST (~16h)

Razão #2 REST: **sandbox aberto sem KYC** (devs codam imediato). Gotcha gw-app-key dual-header documentado. Convênio CIP off-API = wizard pergunta 3 campos extras.

### Onda 4i — `BradescoApiDriver` REST (~26h)

Razão #3 REST: assinatura `X-Brad-Signature` PKCS#7 proprietária (+80 LOC vs Inter). Sandbox exige cliente PJ + cert A1 + gerente em CC. Subset MVP: boleto + pix_cob/cobv + cancelar/consultar/webhook.

### Onda 4j — `SantanderApiDriver` REST (~24h)

Razão #4 REST: calendário pior (3-5 sem humano + Gerente PJ libera API por tipo de operação + cert A1 ICP-Brasil cliente compra R$ 200-400/ano). Sandbox deprecado — flag `environment=Teste` no prod.

### Caixa REST — **NÃO entra** (INVIÁVEL hoje)

Portal `desenvolvedores.caixa.gov.br` HTTP 504 em 2026-05-25; convênio SIGCB presencial 30-90d; SOAP/XML B2B legado; ACBr classifica como "carece de suporte real". Reavaliar Q3-2026 se cliente-sinal qualificado (ADR 0105).

## 4. Esforço total recalibrado

| Item | Esforço |
|---|---|
| Onda 4f.0 (fundação CnabAdapter) | 6h |
| Onda 4f.cnab (5 CnabDrivers paralelos) | ~10h (paralelo) |
| Onda 4f Itaú REST | 9h |
| Onda 4g BB REST | 16h |
| Onda 4i Bradesco REST | 26h |
| Onda 4j Santander REST | 24h |
| Wizard CNAB + UI agrupar por banco | 4h |
| **Total código (IA-pair ADR 0106)** | **~95h ≈ 12-15 dias de trabalho ativo** |
| Humano-limitado (homologações REST paralelas — não soma, não bloqueia CNAB) | 14-45 dias calendário |

**Valor escalonado:**
- Após Onda 4f.0 + 4f.cnab (~16h código): cliente emite boleto via CNAB nos **5 bancos** dia 1
- Após Onda 4f Itaú REST (~25h cumulativo): primeiro banco com webhook real-time
- Após pacote completo (~95h): paridade Bling/Tiny/Omie em modalidades REST + CNAB

## 5. Valor entregue dia 1 (antes mesmo de API homologar)

- Cliente Bradesco/Itaú/BB/Santander/Caixa emite **boleto via CNAB no dia 1**
- Sem esperar homologação banco (que é 14-45 dias)
- Quando API liberar, **toggle no wizard** ativa modo REST (webhook real-time, sem mais upload manual de retorno)

## 6. Riscos Tier 0

| # | Risco | Mitigação |
|---|---|---|
| R1 | CNAB retorno requer **upload manual** ou auto-SFTP banco — UX inferior a webhook REST | UI dedicada `/cnab-retorno` + skill `cnab-retorno-upload` + cron alerta "não há retorno há X dias" |
| R2 | Cliente esquece de processar retorno → status `paga` defasado vs realidade banco | Alert WhatsApp `business.contact_financeiro` se sem upload há 3 dias úteis |
| R3 | Mode toggle REST→CNAB descrita perde webhook subscription configurado | Migration documenta — toggle reverso desativa webhook URL no banco |
| R4 | `CnabAdapter` precisa ser multi-tenant (business_id scope) | HasBusinessScope nos models `cnab_remessas` / `cnab_retornos` |
| R5 | Lib `eduardokum/laravel-boleto` upstream pode ter breaking changes | Lock versão composer; testar upgrade em PR isolado |
| R6 | Caixa REST entra "do nada" no futuro — driver vai precisar virar dual-mode também | CnabAdapter já é fundação; trocar pra dual fica trivial em 4h |

## 7. Próximo passo

ADR filho `0170-bancos-nativos-top5-dual-mode` em `memory/decisions/proposals/` — Wagner aceita formalmente antes de spawn `audit-implement-expert` paralelo (1 por banco, mas começando pela fundação Onda 4f.0 sequencial).

## 8. Refs

- [ADR 0170](../decisions/0170-paymentgateway-extracao-camada-cobranca.md) — PaymentGateway extração (parent)
- [ADR 0170-onda5-simplificada](../decisions/0170-onda5-simplificada.md) — dogfooding SaaS
- [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal
- [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — esforço IA-pair
- [feedback-pesquisa-paralela-antes-pacote-grande.md](../reference/feedback-pesquisa-paralela-antes-pacote-grande.md) — pattern desta sessão
- 5 dossiês individuais: `2026-05-25-arte-banco-{bradesco,itau,bb,santander,caixa}.md`
- [`lib-custom/laravel-boleto/src/Api/Banco/Inter.php`](../../../lib-custom/laravel-boleto/src/Api/Banco/Inter.php) — único API REST atual da lib
- [`lib-custom/laravel-boleto/src/Cnab/Retorno/Cnab240/Banco/`](../../../lib-custom/laravel-boleto/src/Cnab/Retorno/Cnab240/Banco/) — 11 bancos CNAB
