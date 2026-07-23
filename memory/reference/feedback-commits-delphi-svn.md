---
id: reference-feedback-commits-delphi-svn
name: SVN read-only em D:\Programas — Claude NÃO comita Delphi
description: Regra Wagner 2026-05-27 — D:\Programas (working copy SVN compartilhada pelo time em servidor-crm:8777, Delphi WR Comercial/Office Comercial/OficinaAuto + componentes) é READ-ONLY pro Claude. Comitar source Delphi é regra sem ROI (princípio 4 Constituição v2 Loop fechado por métrica) porque Wagner não recompila/redistribui .exe (ADR 0113 + contrato-delphi-inviolavel.md). svn.exe (SlikSvn) instalado só pra LEITURA (info/log/status/diff/blame). Wagner comita manual quando decidir recompilar+distribuir.
type: feedback
---

# SVN read-only em D:\Programas — Claude NÃO comita Delphi

## Regra (Wagner 2026-05-27)

`D:\Programas\` é working copy SVN compartilhada pelo time inteiro (Wagner + Felipe + Maiara + Eliana + Luiz), apontando pro servidor central `http://wr2.com.br:8777/svn/Programas` (hostname canônico — split-DNS LAN escritório resolve pro `.55:8777` interno; working copy original do Wagner usa hostname legacy `servidor-crm` apontando pro mesmo repo). Cobre o código Delphi WR Comercial / Office Comercial / OficinaAuto + componentes ACBr/DUnit/Imagens/Aprocat/Clinica/etc.

**Claude NÃO executa `svn commit`/`svn add`/`svn rm`/`svn delete`/`svn move`/`svn cp` em `D:\Programas\`** — nenhuma operação que modifique o repositório central.

`svn.exe` (SlikSvn 1.14.2 em `C:\Program Files\SlikSvn\bin\svn.exe`) instalado nesta sessão fica útil **só pra LEITURA**: `info`, `log`, `status`, `diff`, `blame`, `cat`, `list`, `propget`.

## Why — princípio 4 Constituição v2

> **"Loop fechado por métrica — toda regra tem dashboard provando ROI. Sem métrica = regra não existe."** ([ADR 0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md))

Comitar source Delphi no SVN **não tem loop fechado**:

1. Wagner declarou **não recompila/redistribui** os `.exe` Delphi ([ADR 0113](../decisions/0113-integracao-delphi-laravel-ads-3-caminhos.md), [contrato-delphi-inviolavel.md](contrato-delphi-inviolavel.md)). Code change que vai pro SVN **não chega em prod** — não vira binário rodando em cliente.
2. Mudança no `.pas` que altera contrato API (`Services.OImpresso.Token.pas` / `Controller.TOImpresso.pas` / `Services.RegistroSistema.pas`) **quebra cliente permanentemente** se algum dia recompilar — Tier 0 [contrato-delphi-inviolavel.md §1](contrato-delphi-inviolavel.md) (regra de ouro: aditivo only).
3. Time inteiro acessa o mesmo `wr2.com.br:8777` (split-DNS, antes `servidor-crm:8777`) — commit acidental do Claude poluiu histórico compartilhado SEM aprovação humana, e versão central não dá pra "git reset --hard" (SVN é centralizado).
4. **Princípio mestre [contrato-delphi-inviolavel.md:233](contrato-delphi-inviolavel.md)**: *"Delphi é hardware fóssil. O servidor evolui em volta dele, nunca contra ele."*

A estratégia oimpresso vs Delphi é evolução **server-side aditiva** ([ADR 0113](../decisions/0113-integracao-delphi-laravel-ads-3-caminhos.md) — 3 caminhos: endpoints novos Connector / hooks Listener server-side / fila polling `ads_delphi_commands`). Tudo no Laravel, nada no `.pas`.

## How to apply

### O que Claude PODE fazer em D:\Programas (READ-ONLY)

| Comando | Uso |
|---|---|
| `& 'C:\Program Files\SlikSvn\bin\svn.exe' info <path>` | URL/UUID/revisão da working copy |
| `& 'C:\...\svn.exe' log <path> -l 30 -v` | Últimos 30 commits + arquivos tocados |
| `& 'C:\...\svn.exe' status <path>` | Ver edits locais (NÃO comitar) |
| `& 'C:\...\svn.exe' diff <path>` | Ver diff vs base (debugar) |
| `& 'C:\...\svn.exe' blame <arquivo.pas>` | Autor linha-a-linha pra entender bug histórico |
| `& 'C:\...\svn.exe' cat <arquivo>@<rev>` | Conteúdo de versão antiga |
| Read tool em `D:\Programas\**\*.pas` / `.dfm` / `.dpr` | Leitura passiva — documentação/análise/cross-ref com SPEC oimpresso |
| Fallback `wc.db` (SQLite) | Se CLI quebrar — script Python `sqlite3.connect('D:/Programas/.svn/wc.db')` |

### O que Claude NÃO faz (HARD STOP)

- ❌ `svn commit` — qualquer escopo, mesmo "1 arquivo só"
- ❌ `svn add` / `svn rm` / `svn delete` / `svn move` / `svn cp` — qualquer operação que altere árvore
- ❌ `svn merge` / `svn switch` — modificações estruturais
- ❌ `svn propset` / `svn propedit` — propriedades versionadas
- ❌ Edit/Write tools em `.pas`/`.dfm`/`.dpr`/`.inc`/`.rc` dentro de `D:\Programas\WR Comercial\app\` (cobre [PEGADINHAS.md:182](../legacy-delphi/PEGADINHAS.md) READ-ONLY hard rule)
- ❌ Sugerir "vou comitar isso pra Wagner" / "vou criar branch SVN pra testar" / "vou propset ignore"

### Se descobrir bug REAL no Delphi durante análise

Fluxo aprovado:
1. Documenta o bug em [memory/legacy-delphi/PEGADINHAS.md](../legacy-delphi/PEGADINHAS.md) (apend-only, Felipe owner) ou ARQUITETURA.md `dominios/wr-comercial/` se for arquitetural
2. **Não edita o `.pas`** — passa o achado pro Wagner com referência (`arquivo.pas:LL`, snippet, hipótese de fix)
3. Se fix for crítico Tier 0 (contrato `/connector/api/*` quebrado): cria ADR proposal em `memory/decisions/proposals/`
4. Wagner decide: (a) recompilar+distribuir manualmente IDE, (b) implementar workaround Laravel server-side aditivo per [ADR 0113](../decisions/0113-integracao-delphi-laravel-ads-3-caminhos.md), (c) postergar

### Quando esta regra muda

Cria ADR nova `supersedes: [esta]` quando:
- Wagner formaliza pipeline build Delphi automatizado (CI compila + assina + push pra cliente via Connector aditivo) — habilita commit automático SVN com loop fechado de prod
- Time decide migrar `D:\Programas\` de SVN pra git (raro)
- Refactor planejado de módulo Delphi morto (ex: `_Ideias/`, `drafts/`) — escopo limitado com ADR

Até lá: **READ-ONLY**.

## Setup técnico desta sessão

- `winget install Slik.Subversion` → `svn 1.14.2-SlikSvn` em `C:\Program Files\SlikSvn\bin\svn.exe` (não no `$env:PATH` automático)
- Working copy `D:\Programas` do Wagner confirmada: rev 10815 (18/abr/2026), URL legacy `http://servidor-crm:8777/svn/Programas/Trunk`. URL canônica nova (Felipe etc): `http://wr2.com.br:8777/svn/Programas/Trunk` — mesmo repo (UUID match).
- TortoiseSVN GUI continua disponível pra Wagner comitar manual via Explorer

## Ver também

- [ADR 0094 Constituição v2 — Loop fechado por métrica](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — princípio 4 (ROI obrigatório)
- [ADR 0113 — Integração Delphi↔Laravel 3 caminhos aditivos](../decisions/0113-integracao-delphi-laravel-ads-3-caminhos.md) — "não recompilar regra"
- [contrato-delphi-inviolavel.md](contrato-delphi-inviolavel.md) — Tier 0 wire IRREVOGÁVEL + princípio mestre "hardware fóssil"
- [memory/legacy-delphi/PEGADINHAS.md:182](../legacy-delphi/PEGADINHAS.md) — `D:\Programas\WR Comercial\app\` READ-ONLY hard rule (Felipe owner)
- [legacy-delphi-firebird.md](legacy-delphi-firebird.md) — código fonte Delphi + bancos Firebird + creds Vaultwarden
- [dominios/wr-comercial/ARQUITETURA.md](../dominios/wr-comercial/ARQUITETURA.md) — stack interno Delphi (FireDAC, OAuth, threading)
