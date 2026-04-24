# ADR 0021 — Contrato real da API consumida pelo Delphi

**Status:** Descoberto (2026-04-24) — implementação backend pendente
**Data:** 2026-04-24

## Contexto

Até então eu assumia que o Delphi autenticava via `POST /oauth/token` (Passport password grant) e depois chamava `/api/officeimpresso/*` com Bearer token. Baseei todo o log architecture (listener + middleware) nessa hipótese.

Wagner compartilhou o código Delphi em `D:/Programas/WR Comercial/app/Services/Services.OImpresso.*.pas`. A análise revelou que o Delphi tem **DUAS gerações de código**:

### Geração LEGADA (Services.OImpresso.API + Services.OImpresso.Registro)
- Auth via headers custom `X-API-Key` + `X-API-Secret`
- Base URL: `https://oimpresso.com/connector/api`
- Endpoints esperados:
  - `POST /oimpresso/registrar` — JSON com `{cnpj, razao_social, hostname, serial_hd, processador, memoria, sistema_operacional, ip_local, pasta_instalacao, versao_exe, versao_banco, caminho_banco, sistema, paf}`
  - `POST /oimpresso/registrar` alt — body string pipe-separated: `SERIAL|HOST|VERSAO|IP|CNPJ|RAZAO|PASTA|SO|PROC|MEM|VER_BANCO|CAM_BANCO|SISTEMA|PAF`
  - `POST /oimpresso/verificar-licenca` — body string com `serial_hd`
  - `POST /oimpresso/verificar-atualizacao` — body string com `versao_atual`
  - `GET /oimpresso/info` — retorna dados do cliente autenticado
- Response esperado: `{success: bool, autorizado: "S"|"N", message, licenca_id, business_id, data_expiracao, dias_restantes}` ou `{update_available, versao_nova, url_download, changelog, obrigatorio}`

### Geração NOVA (Services.OImpresso.APIRegistro + Services.OImpresso.TokenRegistro)
- Auth **dupla stack**:
  1. Headers: `X-API-Key: 107` + `X-API-Secret: 9hjMG9HXTuoCq2culzsZKsdlm2vvTuS5UQgAnO4k`
  2. Chama `POST https://oimpresso.com/oauth/token` com `grant_type=password`, `client_id=39`, `client_secret=hwOlZy...`, `username`, `password` → obtém access_token
  3. Usa `Authorization: Bearer <access_token>` em chamadas de dados
- Base URL: `https://oimpresso.com/api`
- Mesmos endpoints (`/oimpresso/*`) mas com prefixo diferente

### Realidade confusa
Os dois arquivos `Services.OImpresso.Token.pas` e `Services.OImpresso.TokenRegistro.pas` declaram `unit Services.OImpresso.Token` — **conflito de unit**. Só um compila/linka. A versão "Registro" é mais nova (inclui os dois headers + OAuth).

## Decisão

Implementar no backend os endpoints **dos dois prefixos** pra compatibilidade com qualquer versão do Delphi em campo:

### Fase 1 — Endpoints mínimos (implementar)

| Endpoint | Auth aceita | Body | Response |
|---|---|---|---|
| `POST /api/oimpresso/registrar` | Bearer OR X-API-Key+Secret | JSON ou string pipe | `{success, autorizado, message, licenca_id, business_id, data_expiracao, dias_restantes}` |
| `POST /api/oimpresso/verificar-licenca` | Bearer OR X-API-Key+Secret | string `serial_hd` | mesmo que `/registrar` |
| `POST /api/oimpresso/verificar-atualizacao` | Bearer OR X-API-Key+Secret | string `versao_atual` | `{update_available, versao_nova, url_download, changelog, obrigatorio}` |
| `GET /api/oimpresso/info` | Bearer OR X-API-Key+Secret | - | `{client_id, client_name, user_id, business_id, ip}` |

### Fase 2 — Endpoints espelho em `/connector/api/oimpresso/*`
Alias pros mesmos handlers, pra compat com Geração Legada que usa esse prefixo.

### Middleware de auth híbrida
Accept EITHER:
- `Authorization: Bearer <token>` (Passport) → resolve via `auth:api`
- `X-API-Key: <client_id>` + `X-API-Secret: <client_secret>` → match em `oauth_clients`, resolve user via `oauth_clients.user_id`

### Extração de `serial_hd`
No corpo do `/registrar` ou `/verificar-licenca`. Match em `licenca_computador.hd` → identifica máquina exata. **AQUI é onde `hd` vem**, não no `/oauth/token` como eu tinha assumido.

### Fluxo de "registrar" (comportamento esperado pelo Delphi)
1. Delphi coleta dados do SO (hostname, serial HD, processador, memória, IP local, etc.)
2. Envia JSON pro backend
3. Backend valida:
   - Empresa existe? (`business.cnpj`)
   - Business está bloqueado? (`officeimpresso_bloqueado`)
   - Machine existe com esse `serial_hd`? (sim → update, não → insert respeitando limite do pacote)
   - Versão está dentro do mínimo obrigatório?
4. Retorna `autorizado: 'S'` (pode rodar) ou `'N'` (recusa com `message` explicando)

## Consequências

### Preciso reconsiderar arquitetura do log
- Listener `LogPassportAccessToken` em `/oauth/token` **continua valendo** pra versão NOVA do Delphi
- Mas pra versão LEGADA (sem OAuth), preciso middleware em `/api/oimpresso/*` + `/connector/api/oimpresso/*` que:
  1. Valida X-API-Key+Secret via `oauth_clients`
  2. Se `/registrar` ou `/verificar-licenca`: extrai `serial_hd` do body
  3. Grava `licenca_log` com `licenca_id` resolvido

### Reinterpretar "registra só com hd"
O filtro atual (listener `return if ! $hd`) faz sentido pra `/oauth/token` (nunca teria `hd`) mas precisa ser REMOVIDO quando implementar `/api/oimpresso/registrar` — aí o `hd` vai estar no body, sempre presente.

### Client_id 39 vs 107 no Delphi
Delphi envia **AMBOS**:
- `client_id=39` no OAuth body (pra pegar token)
- `X-API-Key=107` nos headers (pra identificar app)

Ambos precisam estar em `oauth_clients` com `password_client=1, provider='users', secret hashed`. Já confirmados em produção.

## Riscos

- **Se implementar errado, quebra Delphi em produção.** Todos os endpoints precisam seguir EXATAMENTE o contrato: response com `autorizado: "S"|"N"` (string, não bool), `data_expiracao: YYYY-MM-DD`, etc.
- **Authorização "S"/"N"** é um detalhe fácil de errar (Delphi faz `if autorizado = 'S' then Result.Autorizado := True`).

## Próximos passos

1. Criar `Modules/Officeimpresso/Http/Controllers/Api/RegistroController.php` com os 4 endpoints
2. Criar `Modules/Officeimpresso/Http/Middleware/AuthOImpresso.php` (aceita X-API-Key+Secret ou Bearer)
3. Rotas em `Modules/Officeimpresso/Routes/api.php` sob `/oimpresso/*` + alias em `/connector/api/oimpresso/*`
4. Testes com curl simulando o Delphi legado (X-API-Key) e o novo (Bearer)

## ATUALIZAÇÃO 2026-04-24 — 3 gerações de código Delphi convivendo

Após ler `Controller.TOImpresso.pas` (a classe base de sync) — são **3 gerações** de endpoints:

### Geração 1 — `/connector/api/processa-dados-cliente` (LEGADA — 3.7, funcionando)
Rota em `Modules/Connector/Http/routes.php:`
```php
Route::post('/processa-dados-cliente', 'LicencaComputadorController@ProcessaDadosCliente');
Route::post('/salvar-equipamento/{business_id}', 'LicencaComputadorController@saveEquipamento');
Route::post('/salvar-cliente', 'BusinessController@saveBusiness');
```
Delphi manda JSON com duas tabelas (`EMPRESA` + `LICENCIAMENTO`). Backend responde `S;msg` ou `N;motivo`. Match por `hd + business_id + user_win`.

### Geração 2 — `/connector/api/{tabela}/sync-post` + sync-get (Generic sync, 3.7)
`Controller.TOImpresso.pas` implementa padrão de sync genérico:
- `GET /connector/api/{tabela}/sync-get?date=<ultima>` → baixa atualizações
- `POST /connector/api/{tabela}/sync-post` → envia chunks de 100 registros com `OIMPRESSO_SINCRONIZADO IS NULL`
- Exemplo em 3.7: `equipamento_impressora/sync-post`, `historico_impressoes/sync-post`
- Resposta esperada: `{status: "completed"|"validation_error", data: [...], message}`

### Geração 3 — `/oimpresso/registrar` etc. (NOVO, em desenvolvimento, "FASE 1")
Em `Services.OImpresso.Registro.pas`. Usa base `https://oimpresso.com/api/oimpresso/*`. Comentário "FASE 1: Registro e Migração" sugere que é redesign do autor. **Não está em produção ainda**.

## Conclusão atualizada

**Prioridade: restaurar Geração 1 + 2 do Connector do 3.7**

`git ls-tree origin/3.7-com-nfe Modules/Connector/` mostra **147 arquivos** faltando no 6.7 — incluindo `LicencaComputadorController`, `BusinessController`, `EquipamentoImpressoraController`, `HistoricoImpressoesController` etc. É uma restauração enorme mas é o que estava operante em 3.7.

Plano sugerido (próxima sessão):
1. Restaurar `Modules/Connector/Http/routes.php` completo do 3.7
2. Restaurar controllers da API (namespace `Modules\Connector\Http\Controllers\Api\*`)
3. Gerar nomes únicos nas rotas pra não colidir com route:cache (padrão já aplicado no Officeimpresso)
4. Testar endpoint `processa-dados-cliente` com curl simulando Delphi
5. Validar: Delphi abre, se conecta, computador aparece em `licenca_log` automaticamente

## Relacionado

- ADR 0017 — Restauração Officeimpresso 3.7
- ADR 0018 — Log acesso via listener+middleware
- ADR 0019 — Passport v13 auth Delphi (RESOLVIDO)
- ADR 0020 — Grupo econômico (matriz + filial)
