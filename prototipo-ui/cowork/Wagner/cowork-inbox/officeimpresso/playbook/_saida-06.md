---
sessao: "06"
titulo: "Tela de licenças — PR-a (índice + KPI-filtros) + PR-b (drawer + bloquear com motivo) — saída da thread"
autor: "[CL]"
data: 2026-10-01
base: origin/main 133e1fe00
thread: 06-licencas.md
veredito: "entregue PR-a (#8372) e PR-b — índice + KPI-filtros + drawer PT-02 (ficha + histórico) + liberar/bloquear com motivo, tudo atrás da flag useV2OfficeimpressoLicencas (OFF), com charter + casos (UC-OILIC-01..15) + contrato + teste."
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
1. ~~PR-b da thread 06~~ — entregue, ver a seção PR-b abaixo.
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

## PR-b (2026-10-01) — drawer + liberar/bloquear com motivo
Base: origin/main `81141329b` (com o #8372 dentro). Branch `claude/officeimpresso-thread-06b`.

- **Drawer PT-02 (760px)** — `Licencas/_components/LicencaDrawer.tsx`, aberto ao clicar no nome da
  máquina. Ficha (empresa, usuário do Windows, HD, sistema, IP, processador, memória, pasta, versões,
  datas, mensagem que o desktop recebe) + histórico de acessos e bloqueios (`licenca_log`, 50 últimos).
  Vem por `Inertia::optional('detalhe')` (`only: ['detalhe']`, `?licenca=`), com a MESMA regra de
  visão da lista: fora do escopo devolve `null`.
- **Senha nunca sai**: a ficha é DTO explícito; além de `senha`/`contra_senha`/`serial`/`token`,
  também ficam de fora `usuario` e `conexao` (par de credencial do banco do desktop).
- **Liberar/bloquear com motivo** — reusa a rota POST `/officeimpresso/licenca_computador/{id}/toggle-block`
  e o `LicencaService::alternarBloqueio` (#8367). O drawer manda a intenção (`bloquear`) + `motivo`
  (5 a 500, como o `ConfirmMotivo` do protótipo); sem `bloquear` (Blade e tela de Logs) o toggle
  segue igual, sem motivo. Intenção já cumprida (clique repetido) dá erro e não inverte.
- **Motivo sem coluna nova**: vai para `licenca_log` com `source = admin_action` — o mecanismo que o
  próprio `LicencaLog` já documenta para *block/unblock* e que tem retenção declarada de 2555 dias
  (`licenca_log_admin_actions` no `module.json`). `business_id` = o do **equipamento**, gravado explícito; NÃO usei
  `Util::activityLog`, que pega o negócio da sessão (o superadmin age em outro negócio). Texto passa
  pelo `PiiRedactor`. Sem migration.
- **Regra do bloqueio intacta**: `licenca_computador.motivo` — a mensagem `N;<motivo>` que a API
  devolve ao desktop — NÃO é reescrito. Teste UC-OILIC-14 trava isso.
- Testes: UC-OILIC-10..15 em `LicencasIndexContratoTest.php` (mesma lane `officeimpresso-pest`).
  Cross-tenant: UC-11 (ficha de outro negócio = null) e UC-14 (superadmin bloqueia máquina de outro
  negócio → log no negócio do equipamento). Flag segue desligada.
- Local: `contrato-de-tela --contract` limpo · `--map --check` limpo · `--anti-tautologia` 0
  reprovados · `casos-coverage-guard` sem violação nova · `integrity-check` ok · charter conforme
  schema · `SUPERFICIE.md` regenerado. Pest/PHPStan/tsc: só no CI do PR.

### Pendente do PR-b (decisão [W], não inventado)
5. **O motivo do operador deve virar a mensagem que o desktop mostra?** Hoje não vira (pedido: a
   regra não muda). Se sim, é uma linha em `bloquearComMotivo()` gravando `licenca_computador.motivo`
   — mas o texto passa a aparecer para o cliente final.
6. **Alvo do bloqueio**: `toggle-block` age em máquina de qualquer negócio para quem tem
   `officeimpresso.licencas.gerenciar` (por desenho: a WR2 atende os clientes — ver
   `LicencaLogController::podeVerTodasEmpresas`). O drawer só oferece o botão para máquina que a
   tela mostra, mas o endpoint não foi restringido, para não quebrar a tela de Logs.
7. Revogar licença e bloqueio em lote (estão no protótipo) ficaram fora — não são da ficha 06.

## Placar
entregue 2 de 2 PRs da thread (PR-a #8372 + PR-b) · UCs 15 de 15 com teste · contrato 4 de 4 seções.

## PR
PR-a: #8372 (branch `claude/officeimpresso-thread-06`). PR-b: o PR que traz esta seção — branch
`claude/officeimpresso-thread-06b`.
