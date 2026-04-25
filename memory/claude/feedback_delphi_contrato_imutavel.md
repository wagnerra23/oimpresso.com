---
name: Contrato da API com Delphi é IMUTÁVEL
description: Delphi não é recompilado — qualquer mudança no contrato de request/response das APIs quebra o cliente em produção permanentemente
type: feedback
originSessionId: 0922b4af-6c32-45e6-ae30-5d09580ae4ca
---
**Regra:** O cliente Delphi (desktop em produção há 3 anos) **NÃO será recompilado**. Wagner confirmou em 2026-04-24. Qualquer mudança no contrato de request/response das APIs consumidas pelo Delphi **quebra o cliente real**.

**Why:** Delphi é código legado sem pipeline de build ativo. Mesmo se houvesse, demanda redistribuir binário pra N máquinas clientes = alto custo operacional. Preservar contrato é requisito não-funcional crítico.

**How to apply:**

Endpoints que o Delphi consome (contratos fixos — NÃO alterar):

1. **`POST /oauth/token`** (Passport v13 password grant)
   - **Request in:** `{grant_type:"password", client_id, client_secret, username, password}` JSON
   - **Response OK (200):** `{access_token, refresh_token, expires_in, token_type:"Bearer"}`
   - **Response erro (400/401):** `{error, error_description}` padrão OAuth. Delphi só distingue HTTP != 200 como falha.
   - Headers custom aceitos: `X-API-Key` + `X-API-Secret` (ignorados pelo Passport mas enviados pelo Delphi novo)

2. **`POST /connector/api/processa-dados-cliente`** (auth Bearer)
   - **Request in:** array JSON `[{NOME_TABELA:"EMPRESA",...}, {NOME_TABELA:"LICENCIAMENTO", HD, ...}]`
   - **Response:** **STRING simples** `'S;Cliente e equipamento liberados'` ou `'N;<motivo>'`. **NÃO retornar JSON** — Delphi parseia texto.

3. **`POST /connector/api/salvar-cliente`** (auth Bearer)
   - Request: JSON com CNPJCPF + RAZAOSOCIAL obrigatórios
   - Response: objeto Business criado (JSON)

4. **`POST /connector/api/salvar-equipamento/{business_id}`** (auth Bearer)
   - Match por `hd + business_id + user_win` — mudar essa tupla quebra identificação
   - Response: objeto Licenca_Computador salvo

5. **`GET /connector/api/{tabela}/sync-get?date=YYYY-MM-DD+HH:MM:SS`** + **`POST /connector/api/{tabela}/sync-post`** (sync genérico — ADR 0021 Geração 2)
   - Response sync-get: `{status:"success", data:[...], pagination:{total_pages}}`
   - Response sync-post: `{status:"completed"|"validation_error", data:[...], message}`

**Mudanças ADITIVAS permitidas:**
- Adicionar campos **opcionais** no body (Delphi ignora desconhecidos)
- Adicionar endpoints **novos** (Delphi atual não chama, sem impacto)
- Adicionar enforcement via rejeição (return false em validateForPassportPasswordGrant) — Delphi já trata "não 200" como falha

**Mudanças PROIBIDAS:**
- Renomear campos de request OU response
- Mudar tipo (string `"S"`/`"N"` → boolean, data `YYYY-MM-DD` → timestamp, etc.)
- Remover campos opcionais que o Delphi espera ler
- Trocar string pura `'S;msg'` por JSON em endpoints que historicamente retornavam string
- Alterar formato de auth (ex: mudar grant_type aceito)

**Validação com testes:** `tests/Feature/Connector/DelphiOImpressoContractTest.php` tem regression guards (regex no source) que pegam remoção de `->where('hd', ...)`, sumiço de método `ProcessaDadosCliente`, etc. Manter esses testes verdes é obrigatório.

**Consulta:** ADR 0021 em `memory/decisions/0021-officeimpresso-contrato-api-delphi.md` tem o contrato detalhado com 3 gerações de endpoints Delphi.
