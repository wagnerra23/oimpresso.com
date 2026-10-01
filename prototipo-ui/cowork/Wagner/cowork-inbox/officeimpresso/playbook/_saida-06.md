---
sessao: "06"
titulo: "Tela de licenças — PR-a (índice + KPI-filtros) — saída da thread"
autor: "[CL]"
data: 2026-10-01
base: origin/main 133e1fe00
thread: 06-licencas.md
veredito: "entregue PR-a — índice + KPI-filtros em Inertia atrás da flag useV2OfficeimpressoLicencas (OFF), com charter + casos + contrato + teste. PR-b (drawer + liberar/bloquear com motivo) pendente."
---

# _saida-06 · Licenças (PR-a)

## Decisão usada
D2 ainda aparece `respondida: false` no índice (é do Cowork e não foi reescrito). A resposta está em
`_DECISOES-W-2026-10-01.md`: **tela nova no Officeimpresso** (`Officeimpresso/Licencas`). Não é
redirecionar/fundir/aposentar — não houve paridade a comparar. A dependência A1 está `em curso` no
placar só por causa do mesmo D2; o alvo dela (`officeimpresso--licencas--index.alvo.json`) está no main.
D5 (cobrança fora desta tela) respeitado: a tela não mostra `valor` nem `gera_mensalidade`.

## O que saiu
- `LicencaComputadorController::index()` — dual pela flag `useV2OfficeimpressoLicencas` (default OFF:
  produção segue no Blade, que é a rota de fuga do RUNBOOK-licencas §F2). Flag ligada →
  `Inertia::render('Officeimpresso/Licencas/Index')`, `permissions` eager, `licencas` em `Inertia::defer`.
- `buildLicencasPayload()` — DTO explícito por máquina; **não seleciona** `senha`, `contra_senha`,
  `serial` nem `token` (as colunas seguem no banco; D4/thread 03 é quem as dropa).
- `Pages/Officeimpresso/Licencas/Index.tsx` + `Index.charter.md` + `Index.casos.md` (UC-OILIC-01..09).
- `governance/design/contracts/officeimpresso-licencas.contract.json` — 4 seções do alvo A1 (header ·
  toolbar · grade · rodape), copy só com literais do protótipo `ViewLicencas`.
- `Modules/Officeimpresso/Tests/Feature/LicencasIndexContratoTest.php` + linha na allowlist da lane
  `officeimpresso-pest` (sem ela o teste não roda — fora do prefixo, declarado).
- `memory/requisitos/Officeimpresso/SUPERFICIE.md` regenerado (`module-surface --write`, derivado).

## Multi-tenant
Só `superadmin` vê máquinas de todos os negócios; `officeimpresso.access` sem superadmin vê o negócio
da sessão, que era a regra do `index()` — não ampliei. O aviso de HD compartilhado (L4) é contado
sobre as linhas já escopadas: quem não vê os outros negócios não fica sabendo deles.

## Provas do json conferidas
- `LicencaComputadorController.php` contém `Inertia::render(` — sim.
- `governance/design/contracts/officeimpresso-licencas.contract.json` existe — sim.
- Local: `contrato-de-tela --contract` limpo (4/4 seções, ordem ok) · `--map --check` limpo ·
  `--anti-tautologia` 0 reprovados · `casos-coverage-guard` sem violação nova · `integrity-check` ok ·
  charter conforme `charter.schema.json`. Pest/PHPStan/tsc: só no CI do PR (não rodo local).

## Pendente (não inventado)
1. **PR-b da thread 06**: drawer PT-02 (ficha + histórico) e liberar/bloquear com motivo obrigatório.
2. **Decisão [W] — escopo do suporte nesta tela.** O `LicencaLogController::podeVerTodasEmpresas()`
   dá visão de todos os negócios a `officeimpresso.access` (relato do Luiz, 29/07), e a proposta de
   licenças diz que a tela é "do suporte". Aqui segui a regra do `index()` (só a sessão) porque o
   pedido foi não ampliar. Se o suporte precisar ver o cliente nesta tela, é uma linha em
   `podeVerTodasEmpresas()` do `LicencaComputadorController`.
3. **Ligar a flag** `useV2OfficeimpressoLicencas` e o cutover (F5) — [W]; até lá a tela React não
   aparece em produção, e o gate visual só entra no F5 (RUNBOOK-licencas §F5 item 12).
4. Rótulo de situação: segui o protótipo ("Ativa" / "Bloqueada"), o que resolve o D2 do
   `licencas-parity.md` pela forma (UI-0029).

## Errata para o Cowork
- Índice: D2 deve ir a `respondida: true` (`_DECISOES-W-2026-10-01.md`).
- `licencas-parity.md` cita `UC-LIC-*`, que colidem com os `UC-LIC-*` do Ponto
  (`LicencaAbonaDiaContratoTest`). Os casos desta tela usam `UC-OILIC-*`.

## Placar
entregue 1 de 2 PRs da thread (PR-a) · UCs 9 de 9 com teste · contrato 4 de 4 seções.

## PR
O PR que adiciona este arquivo — branch `claude/officeimpresso-thread-06`.
