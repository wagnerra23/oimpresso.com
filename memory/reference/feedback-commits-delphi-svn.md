---
name: SVN auto-commit por tarefa em D:\Programas (Delphi WR Comercial)
description: Regra Wagner 2026-05-27 — após cada tarefa concluída em D:\Programas\ (working copy SVN do código Delphi WR Comercial/Office Comercial/OficinaAuto + componentes ACBr/DUnit/etc), Claude commita SVN automaticamente em PT-BR pra criar histórico granular. svn.exe via SlikSvn 1.14.2 em C:\Program Files\SlikSvn\bin\. Atenção: commit SVN é centralizado (vira "push" imediato pro servidor servidor-crm:8777) — trata como publication-policy.
type: feedback
---

# Auto-commit SVN no Delphi (D:\Programas)

## Regra (Wagner 2026-05-27)

Após terminar **cada tarefa** em `D:\Programas\` (working copy SVN do código Delphi legacy WR Sistemas), **Claude commita** automaticamente com mensagem descritiva PT-BR. Cria histórico granular paralelo à disciplina git do oimpresso (1 PR = 1 intent).

## Why

- Histórico SVN do Delphi tinha gaps grandes — último commit `Administrador` em rev 10815 (18/abr/2026, +1 mês atrás). Pequenos fixes Wagner faz dia-a-dia ficam working copy sujo e perdem rastreabilidade.
- Quando bug aparecer em prod num cliente Delphi, `svn blame <arquivo.pas>` revela QUANDO/POR QUE editou. Equivalente ao `git log` do oimpresso.
- Wagner já entendeu o fluxo SVN (centralizado, sem push separado) e quer formalizar a disciplina.

## How to apply

### Trigger
Após cada tarefa em D:\Programas\ ser concluída — Wagner confirma "pronto/done", ou tarefa Plan/TaskCreate finaliza. **NÃO commitar mid-task** (estado intermediário inconsistente vai pro servidor central na hora).

### Ferramenta
- **CLI:** `C:\Program Files\SlikSvn\bin\svn.exe` (instalada 2026-05-27 via `winget install Slik.Subversion`)
- **NÃO** está no PATH automático em todos shells — usar caminho completo OU adicionar ao `$env:PATH` na sessão:
  ```powershell
  $svn = 'C:\Program Files\SlikSvn\bin\svn.exe'
  ```

### Workflow canônico por tarefa

```powershell
$svn = 'C:\Program Files\SlikSvn\bin\svn.exe'
$wc  = 'D:\Programas'

# 1. Ver o que mudou
& $svn status $wc | Where-Object { $_ -notmatch '^\?' }   # ignora untracked por enquanto
& $svn diff $wc --depth=infinity | Select-Object -First 200

# 2. Adicionar arquivos novos relevantes (revisar manualmente — não cair em ?)
& $svn add <arquivo-novo>

# 3. Commit com mensagem PT-BR descritiva
& $svn commit $wc -m "<tipo>: <descricao curta>

<corpo opcional explicando WHY>

Tarefa: <ref opcional — ex US-OFICINA-002, oimpresso-task-id, bug N>"
```

### Convenção de mensagem (PT-BR, similar ao conventional commits adaptado)

| Prefixo | Quando usar | Exemplo |
|---|---|---|
| `fix:` | Correção bug Delphi | `fix: ordem token RegistroSistema.Execute (Bearer vazio)` |
| `feat:` | Feature/funcionalidade nova | `feat: AfterLogin chama RegistrarSistema na inicializacao` |
| `refactor:` | Limpeza sem mudar comportamento | `refactor: Controller.Principal extrai metodo VerificarAtualizacao` |
| `chore:` | Componentes/build/instalador | `chore: bump ACBr componente para revisao trunk` |
| `docs:` | Comentários/README internos | `docs: Principal.pas anota fluxo {$IFDEF WR2}` |

NÃO usar inglês (codebase Delphi inteiro é PT-BR — RAZAOSOCIAL, CNPJCPF, EMPRESA, etc — manter consistência).

### O que NUNCA fazer

- ❌ `svn commit` sem `svn status` + `svn diff` review antes — commit SVN = visível IMEDIATAMENTE no servidor central (`http://servidor-crm:8777/svn/Programas`), sem janela de revert local
- ❌ Commit com working copy contendo arquivos não-relacionados de outra tarefa (1 tarefa = 1 commit; se misturou, separar via `svn revert` parcial OU mudar TODOS pra um commit grande com mensagem honesta `chore: cleanup multiplas tarefas pendentes`)
- ❌ Commit envolvendo credenciais ou .env-style (SYSDBA/masterkey hardcoded em Principal.pas `{$IFDEF WR2}` é caso especial documentado em [legacy-delphi-firebird.md](legacy-delphi-firebird.md) — qualquer outra credencial é violação)
- ❌ Commit de `bin/` `dcu/` `__history/` (artefatos build Delphi) — verificar `.svnignore`/`svn:ignore` props antes
- ❌ Mudar branch/tag SVN sem aprovação Wagner (SVN branch = pasta no servidor, custosa, raramente justificável)

### Pré-flight check (1× por sessão)

```powershell
$svn = 'C:\Program Files\SlikSvn\bin\svn.exe'
& $svn info 'D:\Programas' | Select-String 'URL|Revisão|Working'
# Deve mostrar URL http://servidor-crm:8777/svn/Programas/Trunk e revisão > 10815
& $svn status 'D:\Programas' --depth=immediates 2>&1 | Select-Object -First 5
# Se já tem mudanças locais ALHEIAS à tarefa atual: alertar Wagner ANTES de editar
```

Se servidor SVN inacessível (LAN ausente, host `servidor-crm` não resolve): `svn commit` vai falhar com `E170013` ou `E215004`. Caso isso aconteça, Claude **não tenta retry** — avisa Wagner ("servidor SVN offline, mudanças continuam locais, commit fica pra quando voltar online"). Diferente de git, SVN NÃO permite commit local pendente — só working copy stash informal.

## Relação com publication-policy

Cada `svn commit` é equivalente a um `git push` no oimpresso → trata como publicação. Aplicar mesma matriz:

- ✅ Executar direto: fix/feat/refactor de pequeno escopo dentro da tarefa que Wagner aprovou
- ⚠️ Confirmar antes: commit que cruza módulos Delphi (`WR Comercial` + `Componentes/ACBr` simultaneamente — pode indicar tarefa mal separada)
- ❌ Escalar pra Wagner: mudança em `app/Services/Services.OImpresso.Token.pas` (auth OAuth — Tier 0 contrato Delphi conforme [contrato-delphi-inviolavel.md](contrato-delphi-inviolavel.md))

## Relação com contrato-delphi-inviolavel.md

Tier 0 IRREVOGÁVEL: o **wire de comunicação Delphi ↔ oimpresso backend** (endpoints `/oauth/token`, `/connector/api/processa-dados-cliente`, `/connector/api/officeimpresso/*`) não muda porque builds antigos rodando em clientes saudáveis nunca recompilam. Edit em `Services.OImpresso.Token.pas` / `Controller.TOImpresso.pas` / `Services.RegistroSistema.pas` requer aprovação Wagner explícita ANTES do commit, e mensagem deve referenciar [contrato-delphi-inviolavel.md](contrato-delphi-inviolavel.md) no body.

## Ver também

- [legacy-delphi-firebird.md](legacy-delphi-firebird.md) — código fonte Delphi + bancos Firebird + credenciais canônicas + fluxo login→registro (seção "Inspeção via svn.exe" atualizada 2026-05-27)
- [contrato-delphi-inviolavel.md](contrato-delphi-inviolavel.md) — Tier 0 endpoints wire Delphi (NÃO recompila)
- [feedback-nunca-publicar-credenciais.md](feedback-nunca-publicar-credenciais.md) — nunca ecoar credencial literal (aplica aos commit messages SVN também)
- [commit-discipline](../../.claude/skills/commit-discipline) — disciplina git oimpresso (princípio análogo)
