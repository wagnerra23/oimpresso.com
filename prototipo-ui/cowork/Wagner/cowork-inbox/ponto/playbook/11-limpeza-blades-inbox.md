---
sessao: "11"
titulo: Limpeza — 26 blades mortas + nav legado + inbox residual (+ /ponto/react se W8 = remover)
dono: "[CL]"
base: e86130722de1
prefixo: Modules/Ponto/Resources/views/** EXCETO reports/ · prototipo-ui/design-docs/cowork-inbox/ponto-dashboard/ · Modules/Ponto/Http/routes.php (SÓ a rota /react, SÓ se W8 = remover) · resources/js/Pages/Ponto/Welcome.* (idem)
nao_toca: Resources/views/reports/espelho-pdf.blade.php (VIVA — PDF do imprimir) · Resources/lang/ · resources/js/Pages/Ponto/** (fora do Welcome) · Tests/
depende: W8 só para a parte /react; o resto pode abrir agora — vaga 1
---
# 11 · Limpeza

## Medido nesta sha
- **27 blades** em `Modules/Ponto/Resources/views/` (aprovacoes 2 · banco-horas 2 · colaboradores 2 · configuracoes 2 · dashboard 1 · escalas 4 · espelho 2 · importacoes 3 · intercorrencias 5 · layouts 1 · relatorios 1 · reports 1). **0 `return view(`** nos 12 controllers; **21 `Inertia::render`**. Logo 26 são mortas; **`reports/espelho-pdf.blade.php` é a exceção** (PDF de `EspelhoController@imprimir` — **não verifiquei** o `loadView`; verificar antes de qualquer `rm`).
- `layouts/module.blade.php` = topnav legado de 10 itens (AdminLTE/FontAwesome) — nenhum controller o renderiza.
- `prototipo-ui/design-docs/cowork-inbox/ponto-dashboard/Index.casos.md` — cópia do que já vive em `Pages/Ponto/Dashboard/Index.casos.md` (27 KB, evoluído). Resíduo.
- `/ponto/react` → `Ponto/Welcome` (closure, charter `draft` desde 07/2026 com pendência "piloto fica ou sai?"; `WelcomeContratoTest` existe). **W8.**

## Passo a passo
1. `gh pr list` × `Modules/Ponto/Resources/views/`.
2. `git grep -n "pontowr2::" Modules/Ponto app resources` → cada view referenciada **fica** (esperado: só `reports.espelho-pdf`). Colar a saída no `_saida`.
3. Remover as 26 mortas + `layouts/module.blade.php`. Chaves de lang **não** — `Resources/lang/` é usado pelas Pages (`module_label`, `menu.*`).
4. Remover `cowork-inbox/ponto-dashboard/` (o casos vivo já está em Pages).
5. Se W8 = remover: tirar a rota `/react`, `Welcome.tsx/.charter/.casos` e `WelcomeContratoTest` **no mesmo PR**; se W8 = manter: nada, e o charter sai de `draft` em PR de [W].
6. `TelasNavegacaoTest` + lane `ponto-pest.yml` verdes. `_saida-11.md`.

## PARAR SE
- O grep achar view referenciada além de `reports/` → ela fica; listar.
- `espelho-pdf` não for encontrada no `loadView` → **não** apagar mesmo assim; reportar (pode ser referenciada por string composta).

## Prova (PLACAR confere)
- `layouts/module.blade.php` **ausente** · `dashboard/index.blade.php` **ausente** · `cowork-inbox/ponto-dashboard/Index.casos.md` **ausente** · `reports/espelho-pdf.blade.php` **presente** · `_saida-11.md`
