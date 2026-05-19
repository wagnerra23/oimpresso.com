---
name: Contrato Delphi WR Comercial inviolável — wire imutável (não vai recompilar)
description: Endpoints Connector + Officeimpresso + Subscription que Delphi WR Comercial consome. Decisão Wagner 2026-05-19 — não vai recompilar/redistribuir Delphi, então wire é IRREVOGÁVEL. Mudanças só ADITIVAS (campos novos no response OK; remover/renomear NÃO).
type: reference
trust_required: tier-0
last_updated: 2026-05-19
---

# Contrato Delphi WR Comercial — wire IRREVOGÁVEL

> **Decisão Wagner 2026-05-19:** "o contrato com o delphi não pode sofrer alteração pois não vai ser recompilado os delphi's."
>
> **Implicação Tier 0 IRREVOGÁVEL:** todo endpoint listado abaixo tem cliente Delphi vivo em produção (~6 builds catalogados: WR2 biz=1, EXTREMA LED biz=196, biz=169, biz=177, Vargas biz=164, Martinho biz=164). Build novo seria recompilação + redistribuição pra todos clientes — Wagner declarou inviável. Qualquer mudança no wire quebra clientes silenciosamente.
>
> Cross-ref: [ADR 0017](../decisions/0017-officeimpresso-restaurado-superadmin-exclusivo.md), [ADR 0018](../decisions/0018-log-acesso-desktop-fase-1-passivo.md), [ADR 0019](../decisions/0019-passport-v10-v13-auth-delphi.md), [ADR 0020](../decisions/0020-grupo-economico-matriz-filial.md), [ADR 0021](../decisions/0021-contrato-real-api-delphi-3-geracoes.md).

## 1. Regra de ouro

| Permitido | Proibido |
|---|---|
| ✅ Adicionar campos novos no response JSON (Delphi ignora chaves desconhecidas) | ❌ Renomear endpoint/path |
| ✅ Adicionar parâmetros opcionais no request (Delphi não envia, default funciona) | ❌ Renomear campo no response que Delphi parsa |
| ✅ Aceitar novos formatos de body (Delphi continua enviando o antigo) | ❌ Mudar HTTP status code de resposta |
| ✅ Acrescentar middleware passivo (log/OTel/throttle) | ❌ Mudar Content-Type do response |
| ✅ Adicionar enforcement server-side (bloqueio CNPJ/HD) que o Delphi já entende | ❌ Quebrar formato `S;msg` / `N;motivo` |
| ✅ Tornar campos antes obrigatórios em opcionais | ❌ Tornar campo opcional em obrigatório |
| ✅ Estender enum response (`autorizado: 'S'/'N'/'P'` = pendente) **se** Delphi tratar default como `N` | ❌ Quebrar dual-auth `X-API-Key`+`X-API-Secret`+`Bearer` |

## 2. Endpoints inviolávies (sob `/connector/api/*`)

Auth: Passport `auth:api` + throttle `120,1`. Middleware `log.delphi` + `timezone`. **Master user shared** — identidade tenant NUNCA vem do `user_id` (token pertence a 1 master usado por todos clients), sempre do BODY.

### 2.1 `POST /connector/api/oimpresso/registrar` (Gen 3 — atual prod)

**Source code Delphi:** `Services.OImpresso.Registro.pas`. **Backend:** [OImpressoRegistroController](../../Modules/Connector/Http/Controllers/Api/OImpressoRegistroController.php).

**Request body** (aceita 2 formatos):

```jsonc
// JSON flat (preferido):
{
  "cnpj": "12345678000190",
  "razao_social": "...",
  "hostname": "DESKTOP-XYZ",
  "serial_hd": "WD-WCC4M0...",
  "processador": "...",
  "memoria": "8GB",
  "sistema_operacional": "Windows 10",
  "ip_local": "192.168.0.50",
  "pasta_instalacao": "C:\\WR Comercial",
  "versao_exe": "1.0.1474",
  "versao_banco": "1.0.1474",
  "caminho_banco": "C:\\dados.fdb",
  "sistema": "WR Comercial",
  "paf": "S"
}
```

OU string pipe (14 campos):
```
SERIAL|HOST|VERSAO|IP|CNPJ|RAZAO|PASTA|SO|PROC|MEM|VER_BANCO|CAM_BANCO|SISTEMA|PAF
```

**Response JSON** (formato fixo — campos podem ganhar irmãos novos, mas estes 7 são imutáveis):

```jsonc
{
  "success": true,           // boolean
  "autorizado": "S",         // "S" | "N" — Delphi parsa literal
  "message": "Autorizado",   // string
  "licenca_id": 123,         // int
  "business_id": 456,        // int
  "dias_restantes": 30,      // int
  "data_expiracao": "2026-12-31"  // string YYYY-MM-DD ou ""
}
```

**Resolução de identidade (servidor):**
1. Tenta `business_locations.cnpj = <cnpj>` (precedence ADR 0020 multi-CNPJ)
2. Fallback `business.cnpj = <cnpj>`
3. Se nada bate → `autorizado='N', message='Empresa nao cadastrada'`

**Side-effect:** cria/atualiza `licenca_computador` por `(hd + business_id + user_win)`. Máquina nova nasce `bloqueado=true` (admin libera manual).

### 2.2 `POST /connector/api/processa-dados-cliente` (Gen 1 — 3.7 restaurado)

**Body:** JSON array com `NOME_TABELA=EMPRESA` ou `NOME_TABELA=LICENCIAMENTO` (~3KB).
**Response:** **STRING** (não JSON) — `'S;Cliente e equipamento liberados'` ou `'N;<motivo>'`.

⚠️ **Critical:** mudar pra JSON quebra parsing Delphi. Response string é wire.

### 2.3 `POST /connector/api/salvar-cliente` (Gen 1)

Body: JSON business. Response: idêntico Gen 1 (`'S;'` / `'N;'`).

### 2.4 `POST /connector/api/salvar-equipamento/{business_id}` (Gen 1)

Body: JSON equipamento. Response: idêntico Gen 1.

### 2.5 `POST /connector/api/check-update` (Gen 3 — atual prod)

**Source:** `Services.RegistroSistema.pas` (VerificarAtualizacao).

**Body** text/plain: `"CNPJ;VersaoAtual"`
**Response** text/plain: `"VersaoNova;VersaoMinObrigatoria"` ou `"N;VersaoMinObrigatoria"`

Gerenciado por `business.versao_disponivel` + `business.versao_obrigatoria` (Superadmin manage).

### 2.6 `GET /connector/api/active-subscription`

[SuperadminController::getActiveSubscription](../../Modules/Connector/Http/Controllers/Api/SuperadminController.php#L65). Devolve `Superadmin::Subscription::active_subscription($business_id)` + `getResourceCount`.

⚠️ Schema do response é definido em scribe/docs. Apps mobile + Delphi consomem. Tratar como wire imutável — adições só.

### 2.7 `GET /connector/api/packages`

[SuperadminController::getPackages](../../Modules/Connector/Http/Controllers/Api/SuperadminController.php#L214). Lista `Superadmin::Package` com `custom_permissions` por módulo.

### 2.8 `POST /oauth/token` (Laravel Passport)

**Não está em /connector/api** mas é wire crítico Delphi.

- `grant_type=password` habilitado via `Passport::enablePasswordGrant()` (ADR 0019)
- `provider='users'` obrigatório no oauth_client desde Passport v11+
- `oauth_clients.secret` hashed (Eloquent cast desde v11)
- Bloqueio: [`User::validateForPassportPasswordGrant`](../../app/User.php) override rejeita quando `business.officeimpresso_bloqueado=1`
  - Response: HTTP 400 `invalid_grant` (igual "não autenticou" — Delphi não distingue)
  - **Esse é o mecanismo canônico de bloqueio cliente inadimplente**

### 2.9 `GET /api/officeimpresso` (Bearer)

Devolve user autenticado. Aditivo pós-3.7. Delphi futuro pode consumir.

### 2.10 `POST /api/officeimpresso/audit` (Bearer)

Endpoint opt-in pra Delphi postar eventos (audit log). Aditivo.

## 3. Identidade e enforcement — mestre absoluto

### Master user shared (armadilha)

Delphi compartilha **1 oauth client + 1 user** entre TODOS clientes em produção (history UltimatePOS).
Consequência:

- `$request->user()->business_id` em endpoints Connector dá SEMPRE o mesmo (master) — **errado** pra identificar cliente real
- `auth:api` autoriza tráfego mas NÃO identifica cliente

### Identidade canônica em handler novo

```php
// ✅ CERTO — derivar do body
$cnpj = $request->input('cnpj');
[$businessId, $locationId] = $this->resolveBusinessByCnpj($cnpj);

// ❌ ERRADO — assume token identifica cliente
$businessId = $request->user()->business_id;  // sempre master
```

Pattern reference: [OImpressoRegistroController::resolveBusiness](../../Modules/Connector/Http/Controllers/Api/OImpressoRegistroController.php) — `business_locations.cnpj` precede `business.cnpj`.

### Enforcement de bloqueio — 3 níveis ortogonais

| Nível | Onde | Granularidade | Effect |
|---|---|---|---|
| **Empresa** | `business.officeimpresso_bloqueado` | 1 bit por empresa | `/oauth/token` retorna 400 invalid_grant; `/oimpresso/registrar` retorna `autorizado='N', message='Empresa bloqueada'` |
| **Máquina** | `licenca_computador.bloqueado` | 1 bit por máquina (hd + biz + user_win) | `/oimpresso/registrar` retorna `autorizado='N', message=<motivo>` |
| **Validade** | `licenca_computador.dt_validade` | Date por máquina | `dias_restantes < 0` — Delphi UI mostra alerta vencimento (não bloqueia ainda — vide ADR 0021 §roadmap) |

**Esses 3 bits são o substrato Tier 0 do dogfooding SaaS.** Onda 5 PaymentGateway integra com eles via listener — NÃO substitui.

## 4. Builds Delphi em produção (catalogados 2026-04-24)

| Cliente | biz | Build estado | Envia `/processa-dados-cliente`? | Envia `/oimpresso/registrar`? |
|---|---|---|---|---|
| WR2 (master) | 1 | — | — | — |
| EXTREMA LED | 196 | atual | ✅ | ✅ |
| (cliente bizj=169) | 169 | atual | ✅ | ✅ |
| (cliente biz=177) | 177 | atual | ✅ | ✅ |
| Vargas | (legacy) | anterior | ❌ — só `/oauth/token` | ❌ |
| Martinho | 164 | anterior | ❌ — só `/oauth/token` | ❌ |
| Gold | (catalogado) | anterior | ❌ | ❌ |
| Zoom | (catalogado) | anterior | ❌ | ❌ |
| Fixar | (catalogado) | anterior | ❌ | ❌ |
| Mhundo | (catalogado) | anterior | ❌ | ❌ |
| Produart | (catalogado) | anterior | ❌ | ❌ |

**Implicação:** servidor TEM que aceitar build anterior (só `/oauth/token`) E build atual (Gen 1 + Gen 3) simultaneamente. Nunca remover Gen 1 antes de validar que TODOS migraram pra Gen 3.

## 5. O que JÁ existe e NÃO precisa reimplementar

| Capacidade | Path | Status |
|---|---|---|
| Listener `LogPassportAccessToken` (event listener `AccessTokenCreated`) | [Modules/Officeimpresso/Listeners/](../../Modules/Officeimpresso/Listeners/) | ✅ Live |
| Middleware `LogDelphiAccess` (`/connector/api/{processa-dados-cliente,salvar-cliente,salvar-equipamento/{id}}`) | [Modules/Officeimpresso/Http/Middleware/](../../Modules/Officeimpresso/Http/Middleware/) | ✅ Live |
| Middleware `LogDesktopAccess` (`/api/officeimpresso/*`) | idem | ✅ Live |
| `User::validateForPassportPasswordGrant` override (rejeita bloqueado) | [app/User.php](../../app/User.php) | ✅ Live |
| Tabela `licenca_log` + UI machine-centric | `/officeimpresso/licenca_log` | ✅ Live |
| Pest `DelphiOImpressoContractTest` (9 regression guards) | [tests/Feature/Connector/](../../tests/Feature/Connector/DelphiOImpressoContractTest.php) | ✅ Live |
| Comando `php artisan officeimpresso:inspect-api` (audit body completo) | [Modules/Officeimpresso/Console/Commands/](../../Modules/Officeimpresso/Console/Commands/) | ✅ Live |
| OTel span `connector.delphi.oimpresso.registrar` (D9.a Wave 16) | wrap em `OtelHelper::spanBiz` | ✅ Live |

## 6. Wagner regra prática (skill `como-integrar`)

Antes de tocar QUALQUER controller/middleware/listener listado acima:

1. Ler este arquivo + [project-officeimpresso-modulo.md](project-officeimpresso-modulo.md) integral
2. Conferir `tests/Feature/Connector/DelphiOImpressoContractTest.php` — qualquer mudança que quebre os 9 guards é Tier 0 PROIBIDA sem ADR nova explicitando trade-off
3. Adição-only (não substituição) — ver matriz §1
4. Smoke real obrigatório pós-deploy:
   ```bash
   # validar Delphi anterior ainda autentica
   curl -sv -X POST https://oimpresso.com/oauth/token -d "grant_type=password&username=USER&password=PASS&client_id=CID&client_secret=SECRET"
   # validar Delphi atual registra
   curl -sv -X POST https://oimpresso.com/connector/api/oimpresso/registrar -H "Content-Type: application/json" -H "Authorization: Bearer TOKEN" -d '{"cnpj":"...","serial_hd":"..."}'
   ```

## 7. Refs canônicas

- [project-officeimpresso-modulo.md](project-officeimpresso-modulo.md) — módulo Laravel licença desktop completo (diff 3.7 vs 6.7)
- [legacy-delphi-firebird.md](legacy-delphi-firebird.md) — source code Delphi + 50 bancos Firebird + creds SYSDBA
- [matriz-conhecimento-clientes-legacy.md](matriz-conhecimento-clientes-legacy.md) — clientes × build × status × VERSAO_BANCO
- [migracao-officeimpresso-pattern.md](migracao-officeimpresso-pattern.md) — pattern Firebird→MySQL idempotente
- [ADR 0017](../decisions/0017-officeimpresso-restaurado-superadmin-exclusivo.md) — Officeimpresso restaurado
- [ADR 0018](../decisions/0018-log-acesso-desktop-fase-1-passivo.md) — Log acesso desktop event listener+middleware
- [ADR 0019](../decisions/0019-passport-v10-v13-auth-delphi.md) — Passport v13 auth Delphi
- [ADR 0020](../decisions/0020-grupo-economico-matriz-filial.md) — Grupo econômico matriz/filial
- [ADR 0021](../decisions/0021-contrato-real-api-delphi-3-geracoes.md) — Contrato API Delphi 3 gerações
- [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0170](../decisions/0170-paymentgateway-extracao-camada-cobranca.md) — PaymentGateway extração

---

**Princípio mestre:** Delphi é hardware fóssil. O servidor evolui em volta dele, nunca contra ele.
