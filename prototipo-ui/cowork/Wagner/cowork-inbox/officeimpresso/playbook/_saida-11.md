---
sessao: "_saida-11"
thread: "11 · Computadores + Empresas (LicencaComputadorController computadores/businessall) → Inertia"
dono: "[CL]"
data: 2026-10-13
tipo: entregue (duas telas React atrás de flag OFF; cutover é do [W])
---
# _saida-11

O código desta thread entrou em dois PRs de outra sessão ("Placar e playbooks"), que não
escreveu o recibo. Este arquivo é só o recibo: nada de código mudou aqui. Conferido no `main`
`d0e6c8b476`.

## O que entregou
- **Empresas** — [#9145](https://github.com/wagnerra23/oimpresso.com/pull/9145), mergeado em 2026-10-12.
  `businessall()` ganhou o caminho React pela flag `useV2OfficeimpressoEmpresas`. Desligada,
  a Blade `licenca_computador.businessall` segue servindo. Ligada, a rota responde
  `Officeimpresso/Empresas/Index`, com `empresas` (DTO de 8 campos) e `kpis` adiados.
- **Computadores** — [#9153](https://github.com/wagnerra23/oimpresso.com/pull/9153), mergeado em 2026-10-13.
  `computadores()` e `viewLicencas($id)` ganharam o caminho React pela flag
  `useV2OfficeimpressoEmpresa`. Ligada, as duas rotas respondem a ficha
  `Officeimpresso/Empresa/Show`. `?legacy=1` volta à Blade, porque as ações da ficha (editar,
  bloquear empresa, bloquear máquina) ainda não estão na tela React.
- Trio das duas Pages (`.tsx` + `.charter.md` + `.casos.md`), `RUNBOOK-empresas.md`,
  `RUNBOOK-empresa.md`, `empresas-parity.md` e `empresa-parity.md` em
  `memory/requisitos/Officeimpresso/`.

## Divergência da prova do índice
O índice pede `Inertia::render('Officeimpresso/Computadores` e marcou o nome como proposto.
A Page saiu como `Officeimpresso/Empresa/Show`, porque a tela é a ficha de uma empresa. As
duas rotas antigas (`/computadores` e `/licenca_computador/{id}`) caem nela. Com a prova como
está, o placar mantém a thread em "em curso". Corrigir a prova no índice é trabalho do Cowork:
o Code não edita o `00-INDICE.md`.

| prova do índice | no `main` |
|---|---|
| `Inertia::render('Officeimpresso/Empresas` | ✅ `LicencaComputadorController.php:337` |
| `Inertia::render('Officeimpresso/Computadores` | ❌ — o nome real é `Officeimpresso/Empresa/Show` (`:365`) |

## Escopo (Tier 0)
- A lista de empresas é global por desenho: só a operadora passa em `authorizeAccess()`
  (UC-EMPS-11).
- `/computadores` mostra a empresa da sessão (UC-EMP-19). A operadora abre a ficha de outra
  empresa pelo id da URL, decisão D1 [W] 2026-10-12, e id inexistente dá 404 (UC-EMP-20).
- A máquina sai como DTO de 9 campos: senha, serial e token nunca vão para o payload
  (UC-EMP-21).

## Prova
Lane `officeimpresso-pest.yml` (MySQL, tenant 98 × 99), com os dois testes na lista:
- `ComputadoresEmpresasIndexContratoTest` — UC-EMPS-01, 02, 04, 05, 10, 11 e 12. No run do
  #9145 (job `114905738309`) a lane saiu com `103 passed (490 assertions)`, com este arquivo em
  PASS.
- `ComputadoresEmpresaShowContratoTest` — UC-EMP-01, 04, 15, 19, 20 e 21. PASS no run do #9153
  (job `115063575506`).

Nos dois PRs, `visual-regression` e o watchdog de crons ficaram vermelhos. Os dois são
advisory.

## Fica para o [W]
- Ligar `useV2OfficeimpressoEmpresas` e `useV2OfficeimpressoEmpresa` em produção.
- Depois, apagar as Blades `businessall` e `computadores` e as flags (§F5 dos dois RUNBOOKs).
- As ações da ficha em React ficam para um PR próprio. Até lá, `?legacy=1` é a rota de fuga.
