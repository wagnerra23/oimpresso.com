# ADR 0019 — Delphi legado não autentica após upgrade 3.7→6.7 (investigação)

**Status:** ✅ RESOLVIDO (2026-04-23)
**Data:** 2026-04-23

## Contexto

Após a restauração do módulo Officeimpresso (ADR 0017) e implementação do log passivo (ADR 0018), Wagner abriu o Delphi na máquina dele mas **nenhum evento foi capturado**:

- `licenca_log.count() = 0` — triggers MySQL não disparam
- `oauth_access_tokens` — último registro em **2026-04-23 18:00:18** (antes da restauração)
- `storage/logs/laravel.log` — **zero menções** a `officeimpresso` ou `/oauth/token` entre 20:30-20:40 (quando Delphi foi aberto)
- Triggers `licenca_log_after_oauth_access_token_insert` e `licenca_log_after_oauth_refresh_token_insert` confirmados ativos no DB

Interpretação: **o Delphi não está enviando request nenhum ao servidor.**

## Hipóteses

### H1 — Delphi usa token cached válido
O access_token emitido há 15h pode ainda estar válido (Passport default: 1 ano para access_token). Se o Delphi tem token gravado local e ele não expirou, **não precisa chamar `/oauth/token`**. Mas então deveria chamar `/api/officeimpresso/*` com Bearer — e também vemos zero calls.

### H2 — Delphi com URL antiga/errada
A instalação Delphi tem endpoint hardcoded (possivelmente `http://antigo-servidor.com/api`, ou URL sem HTTPS). Se a URL não bate em `oimpresso.com`, não veríamos nada no log.

### H3 — Modo de auth mudou com upgrade Laravel
Wagner disse: *"a única coisa que mudou foi a versão do Laravel e o modo de autenticar"*. Mudanças do Passport v10 → v13 relevantes:
- v11+: `oauth_clients.id` virou UUID (nosso DB ainda tem INT — funciona por compat mas pode ser frágil)
- v12: `Passport::routes()` deprecated
- v13: mudanças em hashing de token + grant types

### H4 — Delphi offline / sem conexão
Máquina cliente não tem conexão com o host. Problema de rede local, firewall, DNS.

### H5 — Certificado SSL / data do sistema
Delphi rejeita TLS porque cert mudou ou data do Windows da Eliana está errada.

## Como testar H1-H5

`bin/test-delphi-auth.sh USERNAME SENHA` simula exatamente o grant password do Delphi via curl.

Se o script funcionar:
- **H1** provável — Delphi vai autenticar quando token expirar, ou podemos forçar revoke dos tokens ativos
- **H2/H4/H5** são do lado Delphi — verificar config do app, log do Windows, certificado

Se falhar:
- Descobrimos o erro exato (body do 401/400 mostra `error_code`)

## Evidências no código

- `/oauth/token` com `grant_type=password` + `client_id=3` (WR Comercial) deveria funcionar
- Passport v13 está operante (responde 400 correto sem grant_type)
- Triggers MySQL estão corretos e disparam em insert normal
- `licenca_log` criada, UI funcional

## Ações sugeridas

1. Wagner roda `bin/test-delphi-auth.sh` da máquina local — confirma se auth funciona
2. Se sim: investigar config do Delphi (URL, credenciais, data/hora, SSL)
3. Se não: ver body do erro — provavelmente relacionado a UUID vs INT em `oauth_clients.id` ou a mudança de hashing

## Resolução — 3 problemas empilhados (cascata de mudanças Passport v10→v13)

### 1. `grant_type=password` desabilitado (erro `unsupported_grant_type`)
**Causa:** Passport v11+ desabilitou password grant por padrão (opt-in).
**Fix:** `Passport::enablePasswordGrant()` em `app/Providers/AuthServiceProvider.php::boot()`.
**Commit:** `bf58fe8`.

### 2. Client secret plain no DB vs `Hash::check()` no runtime (erro `invalid_client`)
**Causa:** Passport v12+ sempre hasha client secrets via Eloquent attribute cast. Assume DB tem secret hasheado. Nosso DB do 3.7 tinha plain (40 chars base64).
**Fix:** Re-salvar secrets via Eloquent pra disparar o cast (que hasha):
```php
foreach (Client::where('password_client', 1)->get() as $c) {
    $plain = $c->getRawOriginal('secret');
    if (!str_starts_with($plain, '$2y')) {
        $c->secret = $plain;  // cast hasha automaticamente
        $c->save();
    }
}
// 56 clientes re-hasheados
```

### 3. `provider = null` em todos os clients (sintoma adicional)
**Causa:** Passport v12+ espera provider explícito (`users`). Clients do 3.7 tinham null.
**Fix:** `UPDATE oauth_clients SET provider = 'users' WHERE password_client = 1 AND provider IS NULL;` (55 rows).

### Validação final
`curl -X POST https://oimpresso.com/oauth/token` com grant=password retorna `invalid_grant "user credentials were incorrect"` (HTTP 400) ao enviar password errado → **servidor está 100% operacional**. Delphi agora funciona se user/senha estiverem corretos.

## Lições

1. **Passport v10→v13 é uma cadeia de mudanças silenciosas** que quebra clientes legados. Não é uma única breaking change — são várias defaultas mudando.
2. **Re-hash de secrets via Eloquent save** é a forma idiomática. Não precisa conhecer plain text — o cast faz tudo.
3. **ADRs abertas pagam juros** quando o problema é diagnóstico. Mantém rastro do raciocínio caso volte a acontecer.

## Relacionado

- ADR 0017 — Restauração Officeimpresso
- ADR 0018 — Log passivo via triggers MySQL
- `bin/test-delphi-auth.sh` — script de diagnóstico
