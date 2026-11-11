---
id: requisitos-comunicacao-visual-pii-lgpd
---

# PII handling ComunicacaoVisual — LGPD compliance

> Wave 26 (2026-05-17) — D7.a evidence pra rubrica governance. **Revisto em 2026-11-11** contra o código de
> `origin/main`: a seção 2 dizia que três classes usavam `PiiRedactor` e não usam, e faltavam as superfícies
> que entraram em outubro (aceite público, arquivos, aviso por WhatsApp).
> Doc canon de PII/LGPD pra Modules/ComunicacaoVisual.
> Pattern equivalente ao Modules/Vestuario/PII-LGPD.md (vertical-thin delega core).

## 1. Estratégia

Modules/ComunicacaoVisual é **vertical especializado fino** sobre núcleo UltimatePOS. PII real (CPF/CNPJ/email/telefone cliente final) mora em `App\Contact` (núcleo) — o módulo apenas referencia via `contato_id` FK em `comvis_orcamentos` / `comvis_os`.

Portanto:
- **PII direto no módulo:** ZERO colunas `email`, `cpf`, `cnpj`, `telefone` em entities ComVis
- **PII free-text potencial:** `observacoes` (texto livre do vendedor) e o `comentario` do aceite público (texto livre do cliente). Só `observacoes` é anonimizável via `right_to_be_forgotten` (retention.php §`anonymize_fields`)
- **Redactor canônico:** delega `App\Support\Privacy\PiiRedactor` (canon ADR 0094 §Princípio 6 — utilitário de privacidade do NÚCLEO, disponível a todo o monolito. Morou em `Modules/Jana/Services/Privacy/` até 2026-08-12, quando saiu do módulo justamente porque 23 módulos dependiam da Jana só pra cumprir LGPD)

## 2. Onde PiiRedactor é usado

Medido com `git grep -l PiiRedactor -- Modules/ComunicacaoVisual` (2026-11-11), fora dos testes:

| Classe | Uso |
|---|---|
| `Services/OrcamentoCalculator.php` | redige `observacoes` antes do span OTel e do log do cálculo |
| `Http/Requests/RecusarOrcamentoRequest.php` | só o docblock cita; a classe não tem consumidor (nenhuma rota a usa) |

```php
// Modules/ComunicacaoVisual/Services/OrcamentoCalculator.php
use App\Support\Privacy\PiiRedactor;

$observacoesRedacted = app(PiiRedactor::class)->redact($observacoesBruto);
```

PiiRedactor é instance method (não estático) — resolvido via container `app(PiiRedactor::class)`.

Até 2026-11-11 esta seção afirmava que `OrcamentoController`, `ApontamentoController` e `DemoSeedCommand` também usavam o redactor. Não usam: nenhum dos três importa a classe.

## 3. Spatie ActivityLog whitelist (AuditTrailIntegrityTest §Wave 25)

Entities core (`Orcamento`, `Os`, `Apontamento`) declaram `getActivitylogOptions()` com `logOnly([...])` que NÃO inclui:
- `contato_id` (FK pra App\Contact — PII indireta)
- `observacoes` (texto livre potencialmente PII)
- `operador_id` (FK pra User — anonimização requerida em LGPD-18)

Garantia automática: `Modules/ComunicacaoVisual/Tests/Feature/AuditTrailIntegrityTest.php` quebra se PR adicionar PII na whitelist.

## 4. Superfícies de PII que entraram em outubro de 2026

### 4.1 Aceite público do orçamento (`/orcamento-cv/{token}`, US-COMVIS-023)

- **O que a página mostra:** nome da empresa, número, datas, itens (descrição, medidas, quantidade, subtotal) e totais. Nunca custo, margem ou dado do contato (`AceiteDoOrcamento::paraCliente`).
- **O que o cliente escreve:** o `comentario` do pedido de ajuste, até 2000 caracteres, gravado em `comvis_orcamento_aceites.comentario`. Não passa por `PiiRedactor` e não é logado. Não está em `retention.php`.
- **Proteção do link:** só o sha256 do token fica no banco; resposta com `X-Robots-Tag: noindex, nofollow` e `Referrer-Policy: no-referrer`; throttle 30/min por IP; token desconhecido devolve 404 sem dizer por quê.

### 4.2 Arquivos do orçamento e da OS (US-COMVIS-020)

- Arte e foto do local vão para a tabela `media` do core, no disco público (`public/uploads/media`), e a OS aponta para o mesmo arquivo. Quem tem a URL baixa, sem login: não há rota de download por empresa.
- Foto de fachada pode mostrar pessoa, placa ou endereço. Os arquivos não estão em `retention.php`.
- Formatos aceitos: `OrcamentoArquivoController::EXTENSOES`. Em 2026-11-11 a lista inclui `svg`; a remoção está em PR aberto do lote 0.

### 4.3 Aviso de instalação por WhatsApp (US-COMVIS-007)

- Quem envia é o `Modules/Whatsapp` (`NotifyInstalacaoCvCustomer`), ouvindo `InstalacaoAgendada`. O evento carrega só `businessId` e `instalacaoId`.
- A mensagem leva o primeiro nome do cliente, o número da OS, o dia e a hora; não leva quem instala nem o veículo. O log da falta de pré-condição leva só ids.
- **Consentimento, como está:** vale o opt-out por contato. `Contact::canReceiveWhatsappNotification()` trata `null` como permitido e bloqueia só o opt-out explícito. O charter do módulo promete opt-in por OS; qual dos dois vale é decisão [W] aberta.

### 4.4 API de apontamento (spool)

Em 2026-11-11, `GET /comunicacao-visual/api/apontamentos` carrega a relação `operador` inteira (registro de `users`). A correção (permissão e resposta sem PII) está em PR aberto do lote 0.

## 5. Retention LGPD Art. 16

- Apontamento/Orcamento/Os: **5 anos** (CCom Art. 195 — guarda fiscal)
- Telemetria operacional: **12 meses**
- Direito ao esquecimento (LGPD Art. 18 VI): anonimiza `observacoes` + preserva IDs fiscais
- Fora de `retention.php`: `comvis_orcamento_aceites` (comentário do cliente) e os arquivos em `media`
- O comentário de `retention.php` prevê um job `comvis:purge-expired` que apaga de vez. Ele não existe. Aposentar esse TODO é decisão [W]: a lápide de 2026-07-27 em [`proibicoes.md`](../../proibicoes.md) registra que num ERP não se apaga PII por TTL

Detalhes: [`Modules/ComunicacaoVisual/Config/retention.php`](../../../Modules/ComunicacaoVisual/Config/retention.php) (canon) + shim `config/retention.comunicacaovisual.php` (rubrica path).

## 6. PiiRedactor canon (referência)

Implementação canônica em `app/Support/Privacy/PiiRedactor.php` — exporta utilitário PT-BR pra todo o monolito (CPF/CNPJ/email/telefone/CEP BR → `[REDACTED:TIPO]`).

ComVis NÃO reimplementa — sempre `use App\Support\Privacy\PiiRedactor;` no código que loga free-text.

## 7. Compliance checklist

- [x] `Config/retention.php` declara janelas legais (5y entities, 12m telemetry)
- [x] `right_to_be_forgotten` enabled + `preserve_fiscal_ids=true`
- [x] Entities core com `LogsActivity` whitelist sem PII (AuditTrailIntegrityTest)
- [x] `PiiRedactor` (core) no log free-text do cálculo
- [x] Pest cross-tenant (Tier 0 ADR 0093)
- [x] Página pública do aceite sem custo, sem dado do contato, sem indexação
- [ ] Comentário do aceite e arquivos com janela de retenção
- [ ] Arquivos com download por empresa
- [ ] Consentimento do aviso de instalação decidido (opt-out × opt-in por OS)

## 8. ADRs

- [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0 IRREVOGÁVEL
- [0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) Constituição v2 §Princípio 6
- [0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) §P7 vertical thin delega core
- [0160](../../decisions/0160-governance-v4-scoped-scorecards-buckets.md) Scoped scorecards bucket
