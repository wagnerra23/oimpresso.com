---
sessao: "04"
titulo: Metas de venda — PUXAR (produção à frente · #6869)
dono: "[CC] read-only → build"
base: 45e63465d2e4
prefixo: prototipo-ui/cowork/hrm-extras.jsx (`Metas`) — só se houver gap a puxar
nao_toca: resources/js/Pages/Essentials/Metas.tsx · SalesTargetController.php · prototipo-ui/contrato/essentials-metas.contract.json
depende: RESÍDUO 5 (o que fazer com as 5 colunas de apuração do protótipo)
---
# 04 · Metas — puxar o vivo (a rev.1 desta thread mandava reconstruir uma tela em produção)

## Estado (lido no `main` 45e63465)
`/hrm/sales-target` **já é Inertia**: `SalesTargetController.php:80` → `Inertia::render('Essentials/Metas')`. O #6869 (mergeado 2026-09-05 18:50, **32 s antes** da base que a rev.1 declarou) entregou o pacote inteiro: `resources/js/Pages/Essentials/Metas.tsx` (330 ln) · `Metas.charter.md` (`page: /hrm/sales-target`, `related_prototype: prototipo-ui/cowork/hrm-extras.jsx (Metas)`) · `Metas.casos.md` · `prototipo-ui/contrato/essentials-metas.contract.json` · `Tests/Feature/HrmMetasTest.php` · `e2e/essentials-metas.spec.ts` · `RUNBOOK-metas.md` · lane em `essentials-pest.yml`. Frescor 🔵 — **não repintar, não reescrever charter.**

## O que a produção decidiu e o protótipo ainda não sabe (lido no charter)
- **Non-Goal explícito:** as **5 colunas de apuração** do protótipo (`Mês anterior · Mês atual · Faixa atingida · Progresso na faixa · Comissão em R$`) ficaram **fora** — único produtor é `DashboardController::getUserSalesTargets` (admin-only, DataTables) e trazê-lo é caminho de VALOR (dupla prova, `proibicoes.md`). Meu protótipo mostra as 5 como se fossem desta tela → **divergência a declarar no build**, não pedido pro Code.
- **Não envia float** ao `/hrm/save-sales-target` (texto pt-BR de 2 casas — `Util::num_uf` lê `1.234` como mil e duzentos e trinta e quatro). Se o form do protótipo (`hrm-forms.jsx`) mostra máscara decimal com ponto, puxar a máscara vírgula.
- Colaborador sem faixa = `Badge` "sem meta" + travessão, nunca zero.
- Contrato gerado com `copy: []` em todas as seções (`_pendente_w`): a copy literal é decisão [W] — o protótipo é a fonte natural dela.

## O que esta thread faz
1. Ler `Metas.tsx` + `Metas.charter.md` + `essentials-metas.contract.json` no `main` (no turno; sha no `_saida`).
2. Medir o protótipo (`hrm-extras.jsx` `Metas`, 896 nós · `os-table` 7 col) — T1 (duas leituras iguais; o módulo tem skeleton).
3. Diff nos dois sentidos: **produção → protótipo** (átomos, `aria-*`, `data-testid`, `data-contract`, estado "sem meta", máscara pt-BR) entra no build; **protótipo → produção** só se for comportamento sem VALOR (busca `?q=`, ordenação) — e aí é pedido de 1 arquivo com UC do `Metas.casos.md`.
4. Propor a **copy literal por seção** (`cabecalho · filtros · lista`) para preencher o `_pendente_w` do contrato — no `_saida`, para [W] aprovar; **não editar o contrato**.
5. Aplicar RESÍDUO 5 quando respondido: remover as 5 colunas de apuração do protótipo **ou** selar "fora desta onda" (Tweak, nunca arquivo novo).
6. `_saida-04.md`.

## PARAR SE
- RESÍDUO 5 não respondido → fazer 1–4, deixar 5 registrado.
- Qualquer item do diff exigir número de apuração → é VALOR: não entra nem no protótipo como dado "real".

## Prova (PLACAR confere)
- `_saida-04.md` com o diff nos dois sentidos, a sha lida e a proposta de copy por seção.
- Se entrou no build: `hrm-extras.jsx` no espelho `prototipo-ui/cowork/` com os átomos puxados e sem as colunas de VALOR (ou com o selo).
