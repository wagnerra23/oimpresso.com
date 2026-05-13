---
name: Legacy Delphi WR Comercial + bancos Firebird — código fonte, registry, credenciais
description: Código-fonte Delphi cliente desktop (D:/Programas/WR Comercial, SVN monorepo); fluxo login→registro pro backend oimpresso; 50 bancos Firebird (.FDB) registrados em HKCU; ServidorWR2 = produção Wagner biz=1; credenciais canônicas SYSDBA/masterkey hardcoded em Principal.pas
type: reference
---

# Legacy Delphi WR Comercial + Firebird

Universo legacy do Wagner (WR Sistemas) — código Delphi cliente desktop (`WR Comercial`) + 50 bancos Firebird de clientes que ainda rodam em produção. Base pra migração progressiva pro `oimpresso` Laravel + sustentação dos clientes existentes.

## Código fonte Delphi

**Local:** `D:/Programas/WR Comercial/`

**VCS:** SVN (não Git). Repo em `http://servidor-crm:8777/svn/Programas`. Working copy em `D:/Programas/.svn/` (pai de WR Comercial — trabalha como **monorepo**). Pasta tem `.git` local mas histórico parcial/morto — **use SVN**.

### Inspeção sem `svn.exe` no PATH

TortoiseSVN em `C:/Program Files/TortoiseSVN/` só tem UI (TortoiseProc.exe, TortoiseMerge.exe), **sem svn.exe CLI**. Pra queries de histórico/checksum, ler direto o `wc.db` (SQLite) com Python stdlib:

```python
import sqlite3
c = sqlite3.connect('D:/Programas/.svn/wc.db')
# REPOSITORY: URL + UUID
# NODES: por arquivo — local_relpath, revision, changed_revision, changed_date(micro-unix), changed_author, checksum
# ACTUAL_NODE: flags de arquivos modificados localmente
# PRISTINE: base de cada checkout (texto comprimido em .svn/pristine/XX/XXXX.svn-base)
```

**Comparar working copy vs pristine (detectar mudanças locais não commitadas):**

```python
import hashlib
with open(path,'rb') as f:
    sha = hashlib.sha1(f.read()).hexdigest()
# comparar com checksum do NODES (formato '$sha1$<hex>')
```

Se `sha == pristine_checksum` → sem mudanças locais. Se difere → edit não commitado.

## Arquivos-chave do fluxo login → registro (oimpresso backend)

### Orquestração
- `Principal.pas` — `TFrmPrincipal.UserControlAfterLogin` (evento UserControl). Chama `GlobalVar_ControllerPrincipal.AfterLogin` (linha ~3670). DFM: `OnAfterLogin = UserControlAfterLogin` wired.
- `app/Controller/Controller.Principal.pas` — `TControllerPrincipal.AfterLogin` (linha ~112). Dispara `TServicesRegistroSistema.RegistrarSistema(True)` e `VerificarAtualizacao(True)`.

### Thread de registro (fluxo NOVO, array_tabelas)
- `app/Services/Services.RegistroSistema.pas` — `TServicesRegistroSistema` herda de `TOImpressoAPI` (TThread suspensa). `RegistrarSistema` cria Thread, seta `Endpoint:='/processa-dados-cliente'` + `RequestBody:=GerarJsonRegistro.ToJSON`, chama `.Start`. `GerarJsonRegistro` retorna `TJSONArray` com `NOME_TABELA=EMPRESA` (lendo `SQLEmpresa_PorCodigo(EmpresaAtiva)`) + `NOME_TABELA=LICENCIAMENTO` (hostname, HD via PCInfo, versões, etc.). Token via `Token_Registro` global, fallback `TServiceOImpressoToken.GetToken('WR23','<senha Vaultwarden>')`.

### Thread alternativa (fluxo legado, flat sem CNPJ)
- `app/Services/Services.LicencaThread.pas` — `TThreadLicenca`. Endpoint também `/processa-dados-cliente` mas body flat `{host, ip, serial_hd, sistema, versao}` — **sem CNPJ**. Backend faz lookup por HD em `licenca_computador` (update em TODAS linhas com aquele HD).

### Auth OAuth
- `app/Services/Services.OImpresso.Token.pas` — `TServiceOImpressoToken.GetToken(usuario, senha)` → `POST https://oimpresso.com/oauth/token` com `grant_type=password`, `client_id=39`, `client_secret=<segredo OAuth>`. Guarda `FAccessToken` + `FRefreshToken` (estático). TTL via `FExpiraEm`.

### HTTP base
- `app/Controller/Controller.TOImpresso.pas` — `TOImpressoAPI.DoRequest` POSTA em `https://oimpresso.com/connector/api${FEndpoint}`, com header `Authorization: Bearer ${FToken}`. Duas variantes de Services.OImpresso.API.pas coexistem no disco mas só uma compila.

## Contrato `/connector/api/processa-dados-cliente` (backend aceita ambos)

1. **Array (RegistroSistema)** — 3KB típico:
   ```json
   [
     {"NOME_TABELA":"EMPRESA","CNPJCPF":"…","RAZAOSOCIAL":"…",…},
     {"NOME_TABELA":"LICENCIAMENTO","HD":"…","DESCRICAO":"<hostname>","VERSAO_EXE":"…",…}
   ]
   ```
2. **Flat (LicencaThread)** — 120B típico: `{host, ip, serial_hd, sistema, versao}` (sem CNPJ).

Resposta SEMPRE string `S;<msg>` ou `N;<motivo>` — não é JSON. **Quebra Delphi se backend retornar JSON aqui.**

## Como debugar quando Delphi NÃO chama backend

- **Breakpoint** em `Controller.Principal.pas:112` (`RegistrarSistema`). Se não atingido, investigar acima (linha 3670 de Principal.pas, wire do evento no DFM).
- **ShowMessage temporário** antes do `RegistrarSistema` — bom pra builds sem IDE.
- **Backend captura tudo**: middleware `log.delphi` em **todo** `/connector/api/*` (CRM + fieldforce), roda **antes de auth:api** → grava até 401. `php artisan officeimpresso:inspect-api --limit=20` mostra payload + headers das últimas chamadas em prod.

## Bug fixed 2026-04-24 — ordem token em `TServicesRegistroSistema.Execute`

**Sintoma:** Delphi autenticava em `/oauth/token` (200 OK, token gerado) mas `/connector/api/processa-dados-cliente` nunca chegava no backend. Causa silenciosa.

**Bug original** (Services.RegistroSistema.pas):
```pascal
Token := Token_Registro;   // ← seta FToken com valor ATUAL (pode ser '')
if Token_Registro = '' then
  Token_Registro := TServiceOImpressoToken.GetToken(...);  // busca novo
// DoRequest usa FToken que ficou vazio
```

Quando `Token_Registro` começava vazio, `FToken` era setado com '' antes do fetch. Fetch populava `Token_Registro` global mas **não atualizava `FToken`**. `DoRequest` enviava `Bearer ` (vazio) → 401 silencioso engolido pelo `except on E`.

**Fix:** mover `Token := Token_Registro` pra **depois** do bloco de fetch. `FToken` sempre reflete token populado.

### Regra aprendida
Se TThread copia estado global (Token, config, etc) pra campo de instância no início de `Execute`, ler/atualizar esse estado APÓS a cópia não reflete na cópia. **Sempre atribuir cópia depois de qualquer mutação do global.** Bug clássico de ordem.

## Observação prática (2026-04-24)
Wagner roda Delphi local conectando em DBs de clientes (ex: Vargas). Alguns clientes populam o grid (biz=196 EXTREMA LED, 169, 177) — têm build com `AfterLogin` chamando `RegistrarSistema`. Vargas (biz=164) e outros não populam — build anterior só autentica `/oauth/token`. Recompilar IDE Delphi + distribuir `.exe` resolve.

---

# Bancos Firebird WR2 / Office Comercial

Ferramenta: **Editor de Registros de Bancos de Dados — WR2 Sistemas v3.5**, Sistema: **Office Comercial**.

## Estrutura no registry (Windows)

Raiz: `HKCU:\Software\Rocha\Office Comercial\Banco`

| Subkey | Conteúdo |
|---|---|
| `Banco` (raiz) | Banco atual selecionado (`Banco`, `LOGIN`, `SENHA`, `CHARSET`, `Servidor`) |
| `Banco\Caminhos` | Lista de TODOS bancos registrados — propriedade = nome amigável, valor = path |
| `Banco\Caminhos\Senhas` | Senhas (propriedade = path completo, valor = senha — **placeholder**, ver creds canônicas abaixo) |

Outras keys soltas em `HKCU:\Software\Rocha`:
- `Login`, `LoginWR` → último usuário logado (`ADMINISTRADOR`)
- `RegistroBD\Sistema` → "Office Comercial"
- `Agenda Monitor`, `Visualizador de Log` → outros utilitários WR2

## Banco do Wagner (produção própria)

- **`ServidorWR2`** → `servidor-crm:Banco` (instância raiz Firebird no host LAN `servidor-crm`) = **produção Wagner, biz=1 (TechPress / WR2 SC)**
- Confirmado por Wagner 2026-05-09. **NÃO é o "TechPressLocal"** que aparece como Banco Atual em screenshots — esse é só o que estava conectado na hora da captura.
- Demais entradas (incluindo TechPress, TechPressLocal, Display Parana, etc) = **bancos de clientes Delphi WR Comercial**.

## Credenciais canônicas (hardcoded no Delphi)

```
User     : SYSDBA
Password : masterkey
Charset  : WIN1252
Port     : 3050 (default Firebird)
```

**Origem:** `backup\backup2\Principal.pas:3446-3449` e `backup\ArqINI\Principal.pas:3428-3431`:

```pascal
{$IFDEF WR2}
  CONECTAR.DatabaseName := 'Servidor-CRM:Banco';
  CONECTAR.Params.Values['password']:='masterkey';
  CONECTAR.Params.Values['user_name']:= 'SYSDBA';
{$ELSE}
  CONECTAR.DatabaseName:=Reg.ReadString('BANCO');
```

Quando o app é compilado com `{$DEFINE WR2}`, ignora o registry e usa SYSDBA/masterkey direto. **Senha de 1 caractere no `HKCU\Software\Rocha\Office Comercial\Banco\Caminhos\Senhas\<path>` é placeholder, não credencial real.**

Funciona pro banco do Wagner (`servidor-crm:Banco`, biz=1) e provavelmente pros 49 bancos de cliente também (Wagner é o desenvolvedor original).

NOTA: SYSDBA/masterkey é credencial Firebird default global em todos os clientes WR2 — informação já hardcoded no source público do projeto WR2. Por isso fica documentada aqui em git canônico (caso especial vs feedback-nunca-publicar-credenciais.md).

### Validação 2026-05-09

POC 2 (`scripts/legacy-migration/poc2-firebird-connect.py`) conectou em `servidor-crm:Banco` com SYSDBA/masterkey:
- `VERSAO_BANCO = 1466` (schema atual)
- 441 tabelas usuárias + 1 view
- POC 1 mostra `UpdateSQL.txt` em v1468 → 2 updates pendentes (normal)

### Comandos úteis

```powershell
# Conexão direta via Python
$env:FIREBIRD_PASSWORD="masterkey"
python scripts/legacy-migration/poc2-firebird-connect.py --alias ServidorWR2

# Cliente Firebird CLI (se Wagner instalou isql.exe)
isql -user SYSDBA -password masterkey servidor-crm:Banco
```

### Não commitar

- `.env` real (já gitignored)
- `.env.example` (não incluir password)
- Comentários inline em scripts (apontar pra `Principal.pas` é OK; literal não)

Outros devs (Felipe/Maiara) que precisarem rodar importer recebem credencial via Vaultwarden (`vault.oimpresso.com`), não git.

## Bancos registrados (50 entradas — 2026-05-09)

Convenção: prefixo `servidor-crm:` = Firebird remoto na máquina `servidor-crm` (LAN); sem prefixo = path local em `D:\DadosClientes\`.

### Locais (sem servidor-crm)
| Nome | Caminho |
|---|---|
| TechPressLocal | `D:\DadosClientes\Techpress\BANCO.FDB` |
| Martinho | `D:\DadosClientes\MartinhoCacamba\BANCO.FDB` |

### Remotos (servidor-crm)
| Nome | Path no servidor-crm |
|---|---|
| Art Laser | `D:\DadosClientes\Art Laser\Dados\BANCO.FDB` |
| ASULBRAT | `D:\DadosClientes\Assulbrat\Dados\BANCO - ASULBRAT.FDB` |
| Bangalo | `D:\DadosClientes\Bangalo Servidor\Dados\BANCO.FDB` |
| Camargo | `D:\DadosClientes\Sabor Brasil\Dados\BANCO.FDB` |
| Casagrande | `D:\DadosClientes\Mecânica Casagrande\Dados\BANCO.FDB` |
| CiaDosMoveis | `D:\DadosClientes\Cia dos Moveis\Dados\BANCO.FDB` |
| CiaSul | `D:\DadosClientes\Ciasul\Dados\BANCO.FDB` |
| CopyLanLocal | `D:\DadosClientes\Copylan\Dados\BANCO.FDB` |
| CubaInox | `D:\DadosClientes\CubaInox\Dados\BANCO.FDB` |
| CyberStudio | `D:\DadosClientes\CyberStudio\Dados\BANCO.FDB` |
| Destak | `D:\DadosClientes\Destak\Dados\BANCO.FDB` |
| Display Parana | `D:\DadosClientes\Display Parana\Dados\BANCO.FDB` |
| DMB | `D:\DadosClientes\DMB\Dados\BANCO.FDB` |
| ECopias | `D:\DadosClientes\ECOPIAS\Dados\BANCO.FDB` |
| Estilo | `D:\DadosClientes\ESTILO\Dados\BANCO.FDB` |
| Extreme | `D:\DadosClientes\Extreme\Dados\BANCO.FDB` |
| Fixar | `D:\DadosClientes\Fixar\Dados\BANCO.FDB` |
| Fluxo | `D:\DadosClientes\FLUXO\Dados\BANCO.FDB` |
| Golbal | `D:\DadosClientes\Global Pneus\Dados\BANCO.FDB` |
| Gold | `D:\DadosClientes\Gold\Dados\BANCO.FDB` |
| GoldenPrint | `D:\DadosClientes\Golden Print\Dados\BANCO.FDB` |
| GPSinalizacao | `D:\DadosClientes\GPSinalizacao\Dados\BANCO.FDB` |
| GSX | `D:\DadosClientes\GPSinalizacao\Dados\BANCO - GSX.FDB` |
| Guia Decor | `D:\DadosClientes\Guia Decor\Dados\BANCO.FDB` |
| HexiPrint | `D:\DadosClientes\HexiPrint\Dados\BANCO.FDB` |
| Lebrinha | `D:\DadosClientes\Mecanica Lebrinha\Dados\BANCO.FDB` |
| MartinhoServidor | `D:\DadosClientes\MartinhoCacamba\Dados\BANCO.FDB` |
| Max | `D:\DadosClientes\Max Comunicação\Dados\BANCO.FDB` |
| Mecanica Lebrinha | `D:\DadosClientes\Mecanica Lebrinha\Dados\BANCO.FDB` (duplicada com Lebrinha) |
| Medeiros Produtos Limpeza | `D:\DadosClientes\Medeiros Produtos Limpeza\Dados\BANCO.FDB` |
| Metalurgica SF | `D:\DadosClientes\Metalurgica SF\BANCO.FDB` |
| Mhundo | `D:\DadosClientes\Mhundo\Dados\BANCO.FDB` |
| Midia e CIA | `D:\DadosClientes\MIDIA E CIA\Dados\BANCO.FDB` |
| Midia OFF | `D:\DadosClientes\MIDIA OFF\Dados\BANCO.FDB` |
| MilLetras | `D:\DadosClientes\MIL LETRAS\Dados\BANCO.FDB` |
| MoveisSul | `D:\DadosClientes\Movesul\Dados\BANCO.FDB` |
| Multimage | `D:\DadosClientes\Multimage\Dados\BANCO.FDB` |
| NewPrintFoz | `D:\DadosClientes\NewPrintFoz\Dados\BANCO.FDB` |
| Personalise | `D:\DadosClientes\Personalize\Dados\BANCO.FDB` |
| Produart | `D:\DadosClientes\Produart\Dados\BANCO.FDB` |
| RG Comunicacao | `D:\DadosClientes\RG Comunicação\Dados\BANCO.FDB` |
| Safety | `D:\DadosClientes\Safety\Dados\BANCO.FDB` |
| SCMola | `D:\DadosClientes\SCMolas\Dados\BANCO.FDB` |
| ServidorWR2 | `Banco` (instância raiz `servidor-crm:Banco`) — **PRODUÇÃO WAGNER** |
| Studium Vinil | `D:\DadosClientes\Studium Vinil\Dados\BANCO.FDB` |
| TechPress | `D:\DadosClientes\Techpress\Dados\BANCO.FDB` |
| Vargas | `D:\DadosClientes\Vargas\Dados\BANCO.FDB` |
| Vargas Acessorios | `D:\DadosClientes\Jardel acessorios\Dados\BANCO.FDB` |
| Wow | `D:\DadosClientes\WOWComunicacao\Dados\BANCO.FDB` |
| Zoom | `D:\DadosClientes\Zoom\Dados\BANCO.FDB` |

## Recall: como acessar

Pra um banco específico (ex: TechPress local):

```powershell
# Path
(Get-ItemProperty 'HKCU:\Software\Rocha\Office Comercial\Banco\Caminhos').'TechPressLocal'

# Senha (placeholder no registry — ver creds canônicas SYSDBA/masterkey acima)
(Get-ItemProperty 'HKCU:\Software\Rocha\Office Comercial\Banco\Caminhos\Senhas').'D:\DadosClientes\Techpress\BANCO.FDB'
```

Pra conectar de fora do app Delphi:
- **Firebird client** (`fbclient.dll` / `gbak`/`isql`) precisa estar instalado
- Remoto: `servidor-crm/3050:<path>` (porta padrão Firebird 3050) — host `servidor-crm` resolvido só na LAN
- User típico Firebird: `SYSDBA` / `masterkey`
- Charset: `WIN1252` ou `ISO8859_1` (Delphi BR legacy)

Bibliotecas pra consultar via script:
- Python: `fdb` ou `firebird-driver`
- Node: `node-firebird`
- PHP: `pdo_firebird` (extensão raramente compilada — usar isql CLI ou `gbak` pra dump)

## Notas

- **ServidorWR2 (`servidor-crm:Banco`) = produção Wagner, biz=1** — instância raiz Firebird na LAN
- **Demais 49 entradas = clientes Delphi WR Comercial** — universo de migração pro `oimpresso`
- Coluna "Mensagem" do app mostra "Banco de Dados muito antigo, poucas alt. restantes" em vários (Extreme, Personalise, Produart, Lebrinha, Midia OFF, Midia e CIA, MoveisSul) — pode indicar licenças expirando
- **Não exportar passwords** pra git/MCP — credencial canônica vive em Vaultwarden + hardcoded no Delphi `{$IFDEF WR2}`
- Cruzar com project-officeimpresso-modulo.md (módulo Laravel atual) e clientes-ativos.md (clientes ativos no oimpresso Laravel — só 7/56 com vendas)

### Atualizar essa lista

```powershell
Get-ItemProperty 'HKCU:\Software\Rocha\Office Comercial\Banco\Caminhos' |
  Select-Object * -ExcludeProperty PS* |
  Format-List
```

## Tabelas-fonte do `Banco` Wagner (WR Sistemas) — migráveis pro oimpresso

Schema vivo do `servidor-crm:Banco` tem **441+ tabelas usuárias**. As que importam pra migração legacy → oimpresso, catalogadas + validadas em 2026-05-11:

| Tabela Delphi | Cols | Rows típicos | Mapeia pra | Status migração | Importer |
|---|---|---|---|---|---|
| **CONTAS** | 91 | 22 (19 ativas) | `accounts` + `fin_contas_bancarias` | migrado biz=1 | `import-contas-bancarias.py` |
| **EMPRESA** | 113 | 4 entidades Wagner | `contacts` (type=both) | migrado biz=1 | `import-empresas.py` |
| **BANCOS** | 7 | 16 instituições (FEBRABAN+) | lookup interno (não migrar — usar `banco_codigo` string) | n/a | n/a |
| PESSOAS / CLIENTES / FORNECEDOR | ? | milhares | `contacts` (customer/supplier) | pendente | criar importer |
| PRODUTOS | ? | milhares | `products` | pendente | criar importer |
| FINANCEIRO (contas a pagar/receber) | ? | milhões | `transactions` + `transaction_payments` | pendente | criar importer |
| FINANCEIRO_BOLETO + _HISTORICO | ? | dezenas de milhares | `rb_boletos` + histórico | decisão Wagner | tabela bridge |
| BANCOS_CONCILIACAO_BANCARIA | ? | ? | `fin_extrato_lancamentos` | decisão Wagner | — |

### Schema CONTAS Delphi (91 cols, fonte das contas bancárias)

Core (mapeáveis 1:1):
- `CODIGO` (PK), `DESCRICAO` (nome amigável Wagner — usar como `accounts.name`), `TIPO` (BANCO/CAIXA/CARTA), `ATIVO` (S/N), `CODEMPRESA` (FK→EMPRESA)
- `CNPJCPF`, `AGENCIA`, `CONTA`, `CODBANCO` (FEBRABAN 3 dígitos), `DIGITO_AG`, `DIGITO_CC`, `CARTEIRA`, `VARIACAO`, `CONVENIO`
- `NOME_CEDENTE`, `CODIGO_CEDENTE`, `CODIGO_TRANSMISSAO`

Boleto/email/cooperativa/PIX/WS (mapear pra `fin_contas_bancarias.metadata` JSON):
- `LAYOUT_ARQUIVO`, `LOCAL_DE_PAGAMENTO`, `MENSAGEM_PROTESTO/MULTA/JUROS/DESCONTO`, `ESPECIE`
- `COOPERATIVA`, `AGENCIA_COOPERATIVA`, `CONTA_COOPERATIVA`, etc (subgrupo)
- `EMAIL_ASSUNTO`, `EMAIL_EXIBIR_*`, `CODEMAIL_MODELO`
- `PIX`, `INDICADORPIX`
- `TEM_WS`, `WS_SCOPO`, `ENDERECO`, `VERSAO_ARQUIVO`, `VERSAO_LAYOUT`

**NUNCA migrar** (segredos):
- `CLIENTID`, `CLIENTSECRET`, `KEYFILE`, `CERTFILE`, `APPKEY` — credenciais API banking (Inter, etc.). Decisão Vaultwarden integration pendente ADR.

### Schema EMPRESA Delphi (113 cols, fonte das pessoas jurídicas)

Core (mapeáveis):
- `CODIGO` (PK), `CNPJCPF`, `INSCIDENT`, `RAZAOSOCIAL`, `FANTASIA`, `TIPO` (J/F), `ATIVO`
- `ENDERECO`, `NUMERO`, `BAIRRO`, `CEP`, `CIDADE`, `UF`, `PAIS`, `CODIGO_MUNICIPIO`
- `FONE1`, `FONE2`, `EMAIL`, `CONTATO`
- `REGIME` (Simples Nacional / Lucro Real / etc), `CRT`, `CNAE`, `IM`, `IEST`, `SUFRAMA`
- `CONTADOR_*` (nome/CPF/CNPJ/CRC/email/endereço completo)

Fiscal (mapear pra `Modules/NfeBrasil/*` futuramente):
- `EMITE_NFE`, `EMITE_NFCE`, `EMITE_NFSE`, `EMITE_SAT`, `EMITE_TEF`
- `NFE_DADOS_SIMPLES_NACIONAL`, `NFE_DANFE`, `NFE_PATH`, `NFE_SERIE`
- `NFSE_*` (vários campos), `NF_EMAIL_*`

**NUNCA migrar** (segredos LGPD/banking):
- `CERTIFICADO` (PKCS#12 base64), `CERTIFICADO_SENHA`, `TEM_CERTIFICADO`
- `WEB_SERVICE_LOGIN`, `WEB_SERVICE_SENHA`
- `NFSE_USUARIO`, `NFSE_SENHA`, `NFSE_WSCHAVEAUTORIZ`
- `NFCE_PRODUCAO_ID`, `NFCE_PRODUCAO_CSC`, `NFCE_HOMOLOGACAO_ID`, `NFCE_HOMOLOGACAO_CSC`
- `APP_SENHA`, `NFE_NUMSERIE`

### Resultado migração 2026-05-11 (biz=1 Wagner em prod)

- **19 accounts ativas** (ids 7-26) + 19 `fin_contas_bancarias` + 19 bridge `accounts_legacy_map` + 2 placeholders 2021 soft-deleted
- **4 contacts** (CNPJs WR Comercial, Wagner PF, EL Tecnologia, WR2 Desenvolvimento) — type=both
- **3 account_types** biz=1: Banco (id=1), Caixa (id=2), Cartão de Crédito (id=10 — pulou IDs por auto_increment global da tabela)
- Bridge `accounts_legacy_map` (business_id, legacy_source='wr-comercial-delphi', legacy_id=CODIGO) torna UPSERT idempotente

Ver detalhes em feedback-legacy-migration-importer.md + PR [#593](https://github.com/wagnerra23/oimpresso.com/pull/593) + [#596](https://github.com/wagnerra23/oimpresso.com/pull/596) (fix Eloquent cast).
