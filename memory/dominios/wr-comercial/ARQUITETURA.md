---
id: dominios-wr-comercial-arquitetura
---

# Arquitetura interna — Delphi WR Comercial

Stack do legado. Migrado da auto-mem `reference_delphi_wr_comercial.md` (2026-04-24) — agora canônico no git/MCP ([ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)).

## Local e VCS

- **Local:** `D:/Programas/WR Comercial/`
- **VCS:** **SVN** (não Git). Repo em `http://servidor-crm:8777/svn/Programas`
- Working copy em `D:/Programas/.svn/` (pai de WR Comercial — trabalha como monorepo)
- Pasta tem também `.git` local mas é histórico parcial/morto — **use SVN**

## Inspecionar sem `svn.exe` no PATH

TortoiseSVN está em `C:/Program Files/TortoiseSVN/` mas só tem UI (TortoiseProc.exe, TortoiseMerge.exe), **sem svn.exe CLI**. Pra queries de histórico/checksum, ler direto o `wc.db` (SQLite) com Python stdlib:

```python
import sqlite3
c = sqlite3.connect('D:/Programas/.svn/wc.db')
# REPOSITORY: URL + UUID
# NODES: por arquivo — local_relpath, revision, changed_revision, changed_date(micro-unix), changed_author, checksum
# ACTUAL_NODE: flags de arquivos modificados localmente
# PRISTINE: base de cada checkout (texto comprimido em .svn/pristine/XX/XXXX.svn-base)
```

**Comparar working copy vs pristine** (detectar mudanças locais não commitadas):
```python
import hashlib
with open(path, 'rb') as f:
    sha = hashlib.sha1(f.read()).hexdigest()
# comparar com checksum do NODES (formato '$sha1$<hex>')
```

Se `sha == pristine_checksum` → sem mudanças locais. Se difere → edit não commitado.

## Stack técnico

| Componente | Tecnologia | Notas |
|---|---|---|
| Linguagem | Object Pascal (Delphi) | Versão recente (não migrada pra v13) |
| Acesso a dados | **FireDAC** (`TFDQuery`, `TFDConnection`) | Migrado de IBX em momento desconhecido |
| Banco local (cache) | Firebird embedded (`fbclient.dll`) | Em `wr_memoria.pas:Cria_BancoFDB` cria banco vazio versão 1308 |
| HTTP client | Próprio `TOImpressoAPI` em `app/Controller/Controller.TOImpresso.pas` | POSTA em `https://oimpresso.com/connector/api/...` com Bearer token |
| OAuth | `TServiceOImpressoToken.GetToken('WR23', 'wscrct*2312')` | client_id=39, grant_type=password |
| Threading | `TThread` herdadas (`TServicesRegistroSistema`, `TThreadLicenca`) | Async com `TThread.Synchronize` pra UI |
| UI | DevExpress (cxGrid, dxSkins) + VCL nativo | Centenas de skins legacy |
| Resources | `WR2Resource.rc` → `RT_RCDATA` embedados no .exe | Inclui `UpdateSQL.txt`, `BancoLocal.sql`, planilhas Excel |

## Fluxo crítico — login → registro de licença

### Orquestração

- **`Principal.pas` — `TFrmPrincipal.UserControlAfterLogin`** (evento do UserControl). Chama `GlobalVar_ControllerPrincipal.AfterLogin` (linha ~3670). DFM: `OnAfterLogin = UserControlAfterLogin` wired.
- **`app/Controller/Controller.Principal.pas` — `TControllerPrincipal.AfterLogin`** (linha ~112). Dispara `TServicesRegistroSistema.RegistrarSistema(True)` e `VerificarAtualizacao(True)`.

### Thread de registro (fluxo NOVO — array_tabelas)

`app/Services/Services.RegistroSistema.pas` — `TServicesRegistroSistema` herda de `TOImpressoAPI` (TThread suspensa).

`RegistrarSistema` → cria Thread → seta `Endpoint:='/processa-dados-cliente'` + `RequestBody:=GerarJsonRegistro.ToJSON` → `.Start`.

`GerarJsonRegistro` retorna `TJSONArray` com:
- `NOME_TABELA=EMPRESA` (lendo `SQLEmpresa_PorCodigo(EmpresaAtiva)`)
- `NOME_TABELA=LICENCIAMENTO` (hostname, HD via PCInfo, versões, `VERSAO_BANCO` via `TConfig.ReadGlobalInteger('VERSAO_BANCO')`)

Token via `Token_Registro` global, fallback `TServiceOImpressoToken.GetToken('WR23', 'wscrct*2312')`.

### Thread alternativa (fluxo legado, flat sem CNPJ)

`app/Services/Services.LicencaThread.pas` — `TThreadLicenca`. Endpoint também `/processa-dados-cliente` mas body flat `{host, ip, serial_hd, sistema, versao}` — **sem CNPJ**. Backend faz lookup por HD em `licenca_computador` (update em TODAS as linhas com aquele HD).

### Auth OAuth

`app/Services/Services.OImpresso.Token.pas` — `TServiceOImpressoToken.GetToken(usuario, senha)` → `POST https://oimpresso.com/oauth/token` com `grant_type=password`, `client_id=39`, `client_secret=hwOlZy…`. Guarda `FAccessToken` + `FRefreshToken` (estático). TTL via `FExpiraEm`.

### HTTP base

`app/Controller/Controller.TOImpresso.pas` — `TOImpressoAPI.DoRequest` POSTA em `https://oimpresso.com/connector/api${FEndpoint}`, com header `Authorization: Bearer ${FToken}`.

> ⚠️ Duas variantes de `Services.OImpresso.API.pas` coexistem no disco mas só uma compila (Delphi não aceita dois units com mesmo nome).

## Contrato `/connector/api/processa-dados-cliente` (backend aceita ambos)

1. **Array (fluxo RegistroSistema)** — 3KB típico:
   ```json
   [
     {"NOME_TABELA":"EMPRESA","CNPJCPF":"…","RAZAOSOCIAL":"…",…},
     {"NOME_TABELA":"LICENCIAMENTO","HD":"…","DESCRICAO":"<hostname>","VERSAO_EXE":"…",…}
   ]
   ```

2. **Flat (fluxo LicencaThread)** — 120B típico: `{host, ip, serial_hd, sistema, versao}` (sem CNPJ).

**Resposta SEMPRE string `S;<msg>` ou `N;<motivo>` — não é JSON.** Quebra Delphi se backend retornar JSON aqui ([ADR 0021](../../decisions/0021-officeimpresso-contrato-api-delphi.md) imutabilidade).

## Como debugar quando o Delphi NÃO chama o backend

- **Breakpoint** em `Controller.Principal.pas:112` (`RegistrarSistema`). Se não é atingido, investigar acima (linha 3670 de Principal.pas, wire do evento no DFM).
- **ShowMessage temporário** antes do `RegistrarSistema` — bom pra builds sem IDE.
- **Backend agora captura tudo**: middleware `log.delphi` aplicado a **todo** `/connector/api/*` (inclui groups CRM + fieldforce), e roda **antes de auth:api** → grava até 401. Rode `php artisan officeimpresso:inspect-api --limit=20` pra ver payload + formato + headers das últimas chamadas em prod.

## Onde fica a versão do schema do banco

```sql
SELECT VALOR FROM CONFIGURACOES WHERE CONFIG = 'VERSAO_BANCO'
```

Pascal ([Controller.Licenciamento.pas:76](D:/Programas/WR Comercial/app/Controller/Controller.Licenciamento.pas)):
```pascal
function ControllerLicenciamento_GetVersaoBanco: String;
begin
  Result := IntToStr(TConfig.ReadGlobalInteger('VERSAO_BANCO'));
end;
```

Banco zerado começa em `1308` (set em [`wr_memoria.pas:608`](D:/Programas/WR Comercial/wr_memoria.pas)).

Mais detalhes em [`UPDATESQL.md`](UPDATESQL.md).

## Bug histórico — ordem do token (fixed 2026-04-24)

**Sintoma:** Delphi autenticava em `/oauth/token` (200 OK, token gerado) mas `/connector/api/processa-dados-cliente` nunca chegava no backend. Causa silenciosa.

**Bug original:**
```pascal
Token := Token_Registro;   // ← seta FToken com valor ATUAL (pode ser '')
if Token_Registro = '' then
  Token_Registro := TServiceOImpressoToken.GetToken(...);  // busca novo
// DoRequest usa FToken que ficou vazio
```

Quando `Token_Registro` começava vazio, `FToken` era setado com '' antes do fetch. Fetch populava `Token_Registro` global mas **não atualizava `FToken`**. `DoRequest` enviava `Bearer ` (vazio) → provavelmente 401 silencioso ou SSL error engolido pelo `except on E`.

**Fix:** mover `Token := Token_Registro` pra **depois** do bloco de fetch. Agora `FToken` sempre reflete o token populado.

**Validação via curl** (confirma que o backend está correto):
- `POST /oauth/token` com `grant_type=password client_id=39 client_secret=hwOlZy… username=WR23 password=wscrct*2312` → 200 + access_token válido
- `POST /connector/api/processa-dados-cliente` com Bearer → 200 `S;Cliente e equipamento liberados`

`TThreadLicenca` não tem o mesmo bug — usa `_TokenRegistroLicenca` global direto no header, sem cópia pra field de instância.

**Regra aprendida:** se um TThread copia estado global (Token, config, etc) pra campo de instância no início de `Execute`, ler/atualizar esse estado APÓS a cópia não reflete na cópia. **Sempre atribuir a cópia depois de qualquer mutação do global.** Bug clássico de ordem.

## Observação prática

Wagner roda o Delphi local conectando em DBs de clientes (ex: Vargas). Alguns clientes populam o grid (biz=196 EXTREMA LED, 169, 177) — têm build com `AfterLogin` chamando `RegistrarSistema`. Vargas (biz=164) e outros não populam — provavelmente build anterior que só autentica em `/oauth/token`. Recompilar em IDE Delphi + distribuir `.exe` resolve.

## Não recompilar regra

Auto-mem [`feedback_delphi_contrato_imutavel`](../../claude/feedback_delphi_contrato_imutavel.md) e [ADR 0113](../../decisions/0113-integracao-delphi-laravel-ads-3-caminhos.md):

> "Delphi é código legado sem pipeline de build ativo. Mesmo se houvesse, demanda redistribuir binário pra N máquinas clientes = alto custo operacional."

Mudanças no contrato API quebram clientes em produção permanentemente. Qualquer integração nova entre Delphi↔Laravel **deve ser aditiva** (3 caminhos do ADR 0113).
