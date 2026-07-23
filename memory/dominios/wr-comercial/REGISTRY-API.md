---
id: dominios-wr-comercial-registry-api
---

# Windows Registry API — `HKCU\Software\Rocha`

Como o app Delphi **WR2 Sistemas Office Comercial** persiste configuração de bancos no registry. O **Editor de Registros de Bancos de Dados v3.5** é a UI que manipula essa árvore — qualquer importer/migrador precisa ler dela.

## Estrutura completa

```
HKCU\Software\Rocha\
├── Agenda Monitor                 (utilitário separado)
├── Login                          (último username, ex: "ADMINISTRADOR")
├── LoginWR                        (último username — outro app)
├── Office Comercial               ← APP PRINCIPAL (WR Comercial)
│   ├── Agenda                     (preferências de UI)
│   ├── Banco                      ← BANCO ATUAL (selecionado)
│   │   ├── (default values)
│   │   │   ├── Banco              = path do banco atual (ex: D:\DadosClientes\Techpress\BANCO.FDB)
│   │   │   ├── LOGIN              = usuário Firebird (geralmente vazio = SYSDBA hardcoded)
│   │   │   ├── SENHA              = senha Firebird (geralmente vazia = "masterkey" hardcoded)
│   │   │   ├── CHARSET            = charset (vazio = default WIN1252)
│   │   │   └── Servidor           = flag protocolo (0 = local/network compartilhado)
│   │   └── Caminhos               ← LISTA DE TODOS OS BANCOS REGISTRADOS
│   │       ├── (props)            ← cada propriedade = 1 alias
│   │       │   ├── ServidorWR2    = "servidor-crm:Banco"          ← Wagner produção
│   │       │   ├── TechPressLocal = "D:\DadosClientes\Techpress\BANCO.FDB"
│   │       │   ├── Display Parana = "servidor-crm:D:\DadosClientes\Display Parana\Dados\BANCO.FDB"
│   │       │   ├── Destak         = "servidor-crm:D:\DadosClientes\Destak\Dados\BANCO.FDB"
│   │       │   └── (... 49 outros bancos de cliente)
│   │       └── Senhas             ← SENHAS por path completo
│   │           ├── (props)
│   │           │   ├── "servidor-crm:Banco"                                = (placeholder 1 char; senha real é "masterkey" hardcoded)
│   │           │   ├── "D:\DadosClientes\Techpress\BANCO.FDB"               = ...
│   │           │   └── (... uma por path único)
│   ├── ConsultaPessoas            (preferências de UI)
│   ├── (... ~150 outras subkeys de UI/grid layout)
│   └── Registrado                 (flag "app registrado")
├── RegistroBD\Sistema             = "Office Comercial" (último sistema usado pelo Editor)
└── Visualizador de Log            (utilitário separado)
```

## ApplicationTitle

A subkey `Office Comercial` corresponde ao `ApplicationTitle` do app Delphi (acessível via `Application.Title` no Object Pascal). Confirmado em [`backup\backup2\Principal.pas:3482`](D:/Programas/WR Comercial/backup/backup2/Principal.pas):

```pascal
if Reg.OpenKey('\Software\Rocha\' + ApplicationTitle + '\Banco\Caminhos', False) then
  Reg.GetValueNames(AListaCaminhos);
```

## Padrão Pascal (TRegistry)

```pascal
uses Registry;

var
  Reg: TRegistry;
  AListaCaminhos: TStringList;
  ABanco: string;
begin
  AListaCaminhos := TStringList.Create;
  Reg := TRegistry.Create;
  try
    Reg.RootKey := HKEY_CURRENT_USER;
    if Reg.OpenKey('\Software\Rocha\' + ApplicationTitle + '\Banco\Caminhos', False) then
      Reg.GetValueNames(AListaCaminhos);
    AListaCaminhos.Sort;
    ABanco := Reg.ReadString(AListaCaminhos[TMenuItem(Sender).Tag]);
    if Reg.OpenKey('\Software\Rocha\' + ApplicationTitle + '\Banco', True) then
      Reg.WriteString('BANCO', ABanco);
  finally
    AListaCaminhos.Free;
    Reg.Free;
  end;
end;
```

## Padrão Python (winreg)

```python
import winreg

APP_TITLE = "Office Comercial"

def read_alias(alias: str) -> tuple[str, str]:
    """Retorna (path, password) pra um alias."""
    caminhos_key = rf"Software\Rocha\{APP_TITLE}\Banco\Caminhos"
    senhas_key = rf"Software\Rocha\{APP_TITLE}\Banco\Caminhos\Senhas"

    with winreg.OpenKey(winreg.HKEY_CURRENT_USER, caminhos_key) as k:
        path, _ = winreg.QueryValueEx(k, alias)

    with winreg.OpenKey(winreg.HKEY_CURRENT_USER, senhas_key) as k:
        try:
            password, _ = winreg.QueryValueEx(k, path)
        except FileNotFoundError:
            password = ""

    return path, password

def list_aliases() -> list[str]:
    """Lista todos aliases registrados."""
    caminhos_key = rf"Software\Rocha\{APP_TITLE}\Banco\Caminhos"
    aliases = []
    with winreg.OpenKey(winreg.HKEY_CURRENT_USER, caminhos_key) as k:
        i = 0
        while True:
            try:
                name, _, _ = winreg.EnumValue(k, i)
                aliases.append(name)
                i += 1
            except OSError:
                break
    return aliases
```

Implementação canônica em [`scripts/legacy-migration/poc2-firebird-connect.py`](../../../scripts/legacy-migration/poc2-firebird-connect.py).

## Formatos de path observados

| Prefixo | Significado | Exemplo |
|---|---|---|
| `<host>:<path-windows>` | Firebird remoto (TCP/IP) | `servidor-crm:D:\DadosClientes\Vargas\Dados\BANCO.FDB` |
| `<host>:<alias-firebird>` | Instância Firebird com alias configurado no servidor | `servidor-crm:Banco` |
| `<path-windows>` (sem `<host>:`) | Firebird **local** (embedded ou serviço local) | `D:\DadosClientes\Techpress\BANCO.FDB` |

`firebird-driver` Python aceita os 3 formatos diretamente.

## Valor da senha — placeholder vs real

A propriedade em `Caminhos\Senhas\<path>` armazena **placeholder de 1 caractere** quando o app Delphi compila com `{$DEFINE WR2}` — porque a credencial real (`SYSDBA` / `masterkey`) está **hardcoded** no Pascal:

```pascal
{$IFDEF WR2}
  CONECTAR.DatabaseName := 'Servidor-CRM:Banco';
  CONECTAR.Params.Values['password'] := 'masterkey';
  CONECTAR.Params.Values['user_name'] := 'SYSDBA';
{$ELSE}
  CONECTAR.DatabaseName := Reg.ReadString('BANCO');
  // ... lê LOGIN/SENHA do registry
```

(Veja [`backup\backup2\Principal.pas:3446-3449`](D:/Programas/WR Comercial/backup/backup2/Principal.pas) e [`backup\ArqINI\Principal.pas:3428-3431`](D:/Programas/WR Comercial/backup/ArqINI/Principal.pas).)

Em build sem `{$DEFINE WR2}`, o valor seria a senha real. Pra todos os bancos validados em 2026-05-09, **senha = `masterkey`**, user = `SYSDBA`. Detalhes operacionais em auto-mem `reference_firebird_wr_comercial_creds.md` (local máquina Wagner — não commitar credencial).

## Outros apps na mesma árvore

A pasta `Software\Rocha` é compartilhada por vários apps Delphi da WR2 Sistemas:

- `Office Comercial` (este — WR Comercial)
- `Agenda Monitor` (utilitário de agenda standalone)
- `Visualizador de Log` (utilitário de log)
- `LoginWR` (auth shared)
- `RegistroBD` (Editor de Registros de Bancos — armazena qual sistema está sendo editado)

Pra outros apps, a estrutura `Banco\Caminhos[+Senhas]` se repete em sub-árvore análoga (`Software\Rocha\<App-Name>\Banco\...`).
