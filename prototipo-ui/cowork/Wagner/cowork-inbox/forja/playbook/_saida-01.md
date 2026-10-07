---
sessao: "_saida-01"
thread: "01 · casos.md das 3 telas team-mcp sem trio (CcSessions · Tasks · Team)"
dono: "[CL]"
data: 2026-10-07
tipo: recibo
entregue_em: "#8928 (CcSessions) · #8936 (Tasks) · este PR (Team)"
base_lida: wagnerra23/oimpresso.com@main 8b5963261b47
---
# _saida-01

## Entregue

Um PR por tela, como a thread pede (G-2). Nenhuma das 3 tinha `casos.md` no `main` na hora em que comecei cada uma: conferi com `git ls-tree origin/main` antes de criar o branch de cada tela.

| tela | PR | UC | teste novo | marcadores |
|---|---|---|---|---|
| `team-mcp/CcSessions/Index` | #8928 | 7 (`UC-CCS-01..07`) | `Modules/Forja/Tests/Feature/CcSessionsContratoTest.php` | 3 `[T0]` |
| `team-mcp/Tasks/Index` | #8936 | 6 (`UC-TSK-01..06`) | `Modules/Forja/Tests/Feature/TasksAdminContratoTest.php` | 1 `[T0]` |
| `team-mcp/Team/Index` | este PR | 6 (`UC-EQP-01..06`) | `Modules/Forja/Tests/Feature/TeamEquipeContratoTest.php` | 4 `[T0]` |

Os UC vêm do charter de cada tela, do `*-visual-comparison.md` aprovado, do SDD do hub (`memory/requisitos/TeamMcp/SDD-tela-hub-team-mcp-v1.0.md`) e das SPECs (`TeamMcp/SPEC.md`, `Jana/SPEC-cc-sessions.md`). Não vêm do `.tsx`. As três telas nasceram Inertia, então não há Blade nem Delphi para comparar (SDD §0.2).

Cada teste novo entra nas duas lanes:

- `.github/ci-sqlite-pest.list`: `PHP / Pest (Unit)`, **required**. Ali rodam só as pernas de registro (rota, middleware, verbo, escopo de query).
- `forja-pest.yml`: `PHP / Pest (Forja · MySQL)`, **advisory**. Ali rodam as pernas de request. Entraram **failing-first**, porque o checkout do CT 100 está atrás do `main` e não tem o helper `usuarioComPermissoes`.

Todos os UC estão `🧪`. O `✅` sai do manifesto do CI.

O charter de cada tela ganhou uma linha apontando para o casos e o teste. Nos charters de Tasks e Team também corrigi o path do backend, que apontava para `Modules/TeamMcp/…`, módulo que não existe mais.

## Provas (medidas antes de cada PR)

1. `node scripts/qa/screen-coverage-map.mjs --screen team-mcp/<Tela>/Index` → `trio completo: ✓` e `sem … UC órfão` nas 3.
2. `node scripts/casos-coverage-guard.mjs` → `✅ Sem violações novas DESTE PR`.
3. `php -l` de cada teste no container do CT 100 → `No syntax errors detected`.
4. **Pest não rodou fora do CI.** O veredito de cada UC é o das lanes do próprio PR.

## Em aberto, para [W]

- **CcSessions:** `cc.read.team` deveria mostrar o time ou só as próprias sessões? A SPEC-cc-sessions diz *time*. O charter e o código dizem *só as próprias*. O casos segue o charter (precedência).
- **Tasks:** uma transição proibida pelo FSM (ex.: `todo → done`) responde **404** ("task não encontrada") em vez de 422.
- **Team `[T0]`:** `DELETE /team/token/{token}` (legacy), `gerarToken`, `gerarDxt` e `atualizarQuota` não conferem o business do `userId`/`tokenId` da URL. A rota legacy contradiz um Anti-hook escrito no charter. Não virou UC, porque nasceria `❌` e a correção é decisão [W].
- **Conflito textual previsto:** os 3 PRs desta thread e os da thread 02 (#8925, #8927, #8932, #8933) acrescentam linhas no mesmo trecho do `forja-pest.yml`, que não é `merge=union`. Quem mergear depois resolve somando as linhas.
