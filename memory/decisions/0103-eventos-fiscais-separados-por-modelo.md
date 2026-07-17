---
slug: 0103-eventos-fiscais-separados-por-modelo
number: 103
title: "Eventos fiscais Laravel separados por modelo NFe (NFeAutorizada / NFCeAutorizada / ...)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-07"
module: nfebrasil
quarter: 2026-Q2
tags: [nfe, events, listeners, broadcasting, modelo-55, modelo-65, sped-nfe]
supersedes: []
supersedes_partially: []
superseded_by: []
related: [0093-multi-tenant-isolation-tier-0]
pii: false
review_triggers: ["Quando CT-e (modelo 67) for adicionado, decidir se cria CTeAutorizada ou se generaliza pra DocumentoFiscalAutorizado", "Se webhook outbound externos surgirem, validar que filter por event class é mais simples que filter por payload"]
---

# ADR 0103 — Eventos fiscais Laravel separados por modelo NFe

## Contexto

`Modules/NfeBrasil` emite múltiplos modelos de documento fiscal eletrônico via [sped-nfe](https://github.com/nfephp-org/sped-nfe):

- **Modelo 55** — NFe (Nota Fiscal Eletrônica) — B2B, recorrência (US-RB-044), invoice driven
- **Modelo 65** — NFC-e (NFe ao Consumidor) — B2C, venda balcão (US-NFE-002), transaction driven
- **Modelo 67** — CT-e (Conhecimento de Transporte Eletrônico) — futuro

Cada modelo tem semântica fiscal distinta:

| Modelo | Origem do destinatário | Caminho de email | Frequência | Webhook outbound |
|---|---|---|---|---|
| 55 NFe | `rb_invoices.contact_id` (recorrência) | resolve via `Invoice→Contact->email` | mensal/anual | sempre notifica cliente |
| 65 NFC-e | `transactions.contact_id` (POS) | resolve via `Transaction->contact->email` (anônimo é normal) | múltiplas/dia | opt-in (anônimo é caso comum) |
| 67 CT-e | shipping context | TBD | por entrega | TBD |

**Pergunta arquitetural:** ao implementar o pipeline NFC-e (US-NFE-002 fase 2B), criar **um único event** `DocumentoFiscalAutorizado(emissao)` polimórfico, OU **events separados por modelo** (`NFeAutorizada` / `NFCeAutorizada`)?

**Pattern de uso típico:**

```php
// Listener tem que resolver email do destinatário pra mandar DANFE
public function handle(NFeAutorizada $event): void {
    $emissao = $event->emissao;

    // Caminho A — single event polimórfico
    if ((int)$emissao->modelo === 55) {
        $invoice = Invoice::find($emissao->transaction_id);
        $email = $invoice?->contact?->email;
    } elseif ((int)$emissao->modelo === 65) {
        $tx = Transaction::find($emissao->transaction_id);
        $email = $tx?->contact?->email;
    }
    // ... mais ifs por modelo

    // Caminho B — events separados, listener específico
    // EnviarDanfePorEmail (modelo 55):  $invoice->contact->email
    // EnviarDanfeNFCePorEmail (modelo 65): $tx->contact->email
}
```

## Decisão

**Eventos separados por modelo.** `Modules/NfeBrasil/Events/`:

- `NFeAutorizada` — modelo 55 (B2B, recorrência)
- `NFCeAutorizada` — modelo 65 (B2C, POS)
- (futuro) `CTeAutorizada` — modelo 67

Cada event tem um único listener primário pra envio de DANFE/XML por email, com lógica de resolução de destinatário específica do modelo:

- `EnviarDanfePorEmail` (escuta `NFeAutorizada`) — resolve via `Invoice→Contact`
- `EnviarDanfeNFCePorEmail` (escuta `NFCeAutorizada`) — resolve via `Transaction→Contact` + cross-tenant guard + flag opt-in default false

Listeners adicionais (broadcast Centrifugo, webhooks outbound, dashboards) podem escutar event específico ou registrar pra ambos via Event Subscriber se querem agregação cross-modelo.

## Justificativa

### Por que NÃO event polimórfico

1. **Branching `if/elseif` em cada handler vira anti-pattern.** Cada novo handler que se importa com modelo precisa replicar a lógica de "se 55 vai aqui, se 65 vai ali". Manutenção O(handlers × modelos)
2. **Resolução de email é caminho radicalmente diferente.** NFe 55 busca em `rb_invoices`; NFC-e 65 busca em `transactions`. Não é variação de mesmo dado — é dado diferente. Forçar polimorfismo aqui é vazamento de abstração
3. **Flag config por modelo:** `email_danfe_on_autorizada` (NFe 55, default true) ≠ `email_danfe_nfce_on_autorizada` (NFC-e 65, default false). Configs distintas refletem semântica distinta — separar events deixa flag binding óbvio
4. **Cross-tenant security:** event/listener específico permite guard explícito por path (NFC-e tem guard cross-tenant `tx.business_id === emissao.business_id` que NFe 55 não precisa porque Invoice já garante)

### Por que separar em vez de subclassing

- `NFCeAutorizada extends NFeAutorizada` parece DRY mas Laravel `Event::listen(Class::class)` é exato — não dispara handlers de subclasses por padrão. Subclasses dão falsa sensação de polimorfismo
- Listeners encadeados (broadcast, webhook, etc) podem querer ESCUTAR ambos events sem herdar regra de email — `Event::listen([NFeAutorizada::class, NFCeAutorizada::class], BroadcastFiscalUpdate::class)` é syntactic clear

### Trade-off "duplicação" vs "abstração vazia"

Sim, há ~20 LoC duplicadas entre `EnviarDanfePorEmail` e `EnviarDanfeNFCePorEmail` (estrutura do handle, fila/tries/backoff). Aceito esse custo porque:

- Separação por modelo é **invariante de negócio** (sped-nfe não vai unificar modelo 55 e 65 algum dia)
- Future-proof: CT-e vai chegar com semântica completamente diferente (shipping, conhecimento de transporte) — abstração polimórfica seria refeita do zero
- Tests separados ficam mais legíveis (cada arquivo cobre um modelo)

## Implementação atual

```php
// Modules/NfeBrasil/Events/NFeAutorizada.php (PR #173)
final class NFeAutorizada {
    public function __construct(public readonly NfeEmissao $emissao) {}
}

// Modules/NfeBrasil/Events/NFCeAutorizada.php (PR #200)
final class NFCeAutorizada {
    public function __construct(public readonly NfeEmissao $emissao) {}
}

// NfeBrasilServiceProvider boot()
Event::listen(NFeAutorizada::class, EnviarDanfePorEmail::class);
Event::listen(NFCeAutorizada::class, EnviarDanfeNFCePorEmail::class);
```

Disparo em diferentes pontos:

- `EmitirNFeAoReceberPagamento` (NFe 55) — Listener de `InvoicePaid`, dispara `event(new NFeAutorizada($emissao))` quando `status='autorizada'`
- `EmitirNfceJob` (NFC-e 65, PR #201) — Job a partir de venda finalizada, dispara `event(new NFCeAutorizada($emissao))` quando `status='autorizada'`

Status NÃO terminais (`pendente`/`rejeitada`/`denegada`) **não disparam** eventos — ficam só no log + DB pra dashboard. Eventos `NFeRejeitada` / `NFCeRejeitada` ficam pra futuro (US de retry/correção).

## Tests anti-regressão

- `Modules/NfeBrasil/Tests/Feature/EmitirNfceJobTest.php` — 4 cases: status=autorizada → dispatch / status=rejeitada → NÃO dispatch / status=denegada → NÃO dispatch / status=pendente → NÃO dispatch
- `Modules/NfeBrasil/Tests/Feature/EnviarDanfeNFCePorEmailTest.php` — 11 cases: listener registrado / flag default false / modelo != 65 → skip / cross-tenant → skip / happy path / etc
- Pattern equivalente coberto em `EnviarDanfePorEmailTest.php` (NFe 55, PR #173)

## Consequências

### Positivo

- Listeners simples, single-responsibility, sem branching por modelo
- Flag config binding óbvio (`email_danfe_on_autorizada` vs `email_danfe_nfce_on_autorizada`)
- Cross-tenant guard explícito onde se aplica (NFC-e)
- Webhooks/dashboards futuros podem filtrar por classe sem inspecionar payload
- CT-e futuro adiciona event próprio sem refatorar 55/65

### Negativo

- Duplicação de boilerplate Listener (~20 LoC × 2 listeners)
- Quando regra cross-modelo emergir (ex: "todos eventos fiscais autorizados → log auditoria"), criar Event Subscriber agregador

### Custo de mudança futura

Se algum dia decidirmos unificar (improvável), refactor é mecânico: criar event `DocumentoFiscalAutorizado`, mover lógica dos 2 listeners pra um `EnviarDanfeFiscalPorEmail` com switch por modelo, deprecar events antigos. ~2h.

## Refs

- Auto-mem: `project_nfebrasil_estado_2026_05_07.md`
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 (informa cross-tenant guard NFC-e)
- PR [#173](https://github.com/wagnerra23/oimpresso.com/pull/173) — `NFeAutorizada` + `EnviarDanfePorEmail` (NFe 55)
- PR [#200](https://github.com/wagnerra23/oimpresso.com/pull/200) — `NFCeAutorizada` + `EnviarDanfeNFCePorEmail` (NFC-e 65)
- PR [#201](https://github.com/wagnerra23/oimpresso.com/pull/201) — Job dispatch event quando autorizada
