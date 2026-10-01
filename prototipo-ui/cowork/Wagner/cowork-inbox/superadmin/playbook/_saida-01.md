---
sessao: "_saida-01"
thread: "01 · Casos: Usuario360 Index + Show"
dono: "[CL]"
data: 2026-09-30
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main 7201ce433
---
# _saida-01

## Entregue

- `Modules/Superadmin/Resources/js/Pages/superadmin/Usuario360/Index.casos.md` — 4 UC
  (`UC-SAUL-01..04`): contrato Inertia `users`/`filters`, admin barrado × superadmin passa,
  busca por username com os campos do charter + termo devolvido, e busca **cross-tenant
  intencional** (ADR 0093 §exceções). **Prova 1 do índice.**
- `Modules/Superadmin/Resources/js/Pages/superadmin/Usuario360/Show.casos.md` — 5 UC
  (`UC-SAUX-01..05`): os blocos 360 + `tabelas_ausentes`, admin barrado × superadmin passa,
  raio-X de usuário de **outro** business, trancar sem motivo não tranca (olha o efeito, não o
  302), e o ciclo trancar → destrancar **sem devolver o token MCP** (Non-Goal do charter).
  **Prova 2 do índice.**
- `Modules/Superadmin/Tests/Feature/Usuario360ContratoTest.php` — 1 teste por UC (9), tenant
  fictício 98 + segundo tenant fictício 99; nunca biz=4.
- `.github/workflows/verticais-pest.yml` — +1 linha no run-set. **Fora do prefixo, de propósito:**
  a lane lista ARQUIVO, e sem a linha o teste existiria sem rodar (§5 2026-08-02, "registrar no
  phpunit.xml não faz o teste rodar"). O trigger e o `paths-filter` já cobriam `Modules/Superadmin/**`.

UCs derivados dos dois charters + SPEC US-SUPER-010; nenhum do `.tsx`. `Show.tsx` não foi tocado.

## Provas do json

- `arquivo` `${MPAGES}/Usuario360/Index.casos.md` — existe.
- `arquivo` `${MPAGES}/Usuario360/Show.casos.md` — existe.
- `casos-coverage-guard` local: sem violação nova, débito −17 vs baseline. `uc-id-lint`: 0 fora do formato.

## Placar

entregue 1 de 1 (Superadmin/01). Veredito dos 9 UC vem da lane `verticais-pest` no PR — por isso
todos os Status ficam 🧪, nunca ✅.

## Errata do índice (não editei o índice)

- O `prefixo` da thread diz `tests/`, mas os testes do Superadmin vivem em
  `Modules/Superadmin/Tests/Feature/` (é onde estão os 5 contratos irmãos e o que o `phpunit.xml`
  e a lane MySQL alcançam). Um teste em `tests/` cairia na lane sqlite e pularia sem provar nada.

## Pendente (e por quê)

- **Log de acesso do superadmin ao raio-X** — aceite da US-SUPER-010 (LGPD Art. 7º), não
  implementado. Ficou como `[BACKLOG]` no `Show.casos.md`: UC sobre ele nasceria vermelho, e
  implementar é escopo de outra thread/decisão [W].
- Comportamentos só de front (debounce, skeleton, paginação client-side, AlertDialog no unlock,
  Tabs, sem polling) ficaram como `[BACKLOG]` — pedem E2E, não teste de contrato.
- Pest não rodado local nem no CT 100 (regra do projeto). A prova é a lane do PR.
