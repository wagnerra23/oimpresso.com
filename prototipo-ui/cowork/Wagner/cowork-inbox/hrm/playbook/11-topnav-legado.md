---
sessao: "11"
titulo: Fim do topnav Blade + limpeza O8
dono: "[CL]"
base: 159e572dd448
prefixo: Modules/Essentials/Resources/views/layouts/nav_hrm.blade.php · layouts/partials/sidebar_hrm.blade.php · blades leave/* · leave_type/* · sales_targets/* · dashboard/hrm_dashboard.blade.php · chaves de lang mortas (16 idiomas)
nao_toca: attendance/* e payroll/* (destinos das threads 09 e 10 — não são desta limpeza) · Pages/** · Routes/web.php (rotas do resource saem em PR próprio, ver PARAR SE)
depende: 02 · 03 · 04 · 05 · 06 mergeadas E screenshot [W2] aprovado — vaga 3, sempre por último
---
# 11 · Fim do topnav Blade + limpeza

## Por quê e por que por último
O `nav_hrm.blade.php` é a navegação do HRM legado (10 itens, com `@can` por item). Enquanto **uma** tela do HRM ainda for blade, o topnav é o único caminho até ela. Só sai quando 02–06 estiverem em produção e o shell (AppShellV2) for a navegação — que é o que o protótipo já mostra (abas do módulo).

## Escopo fechado
1. Remover `nav_hrm.blade.php` e `sidebar_hrm.blade.php`; os itens Departamentos/Cargos (`TaxonomyController`) passam a ser entrada do shell (thread 01 já os tem no protótipo).
2. Remover as blades das telas que viraram Page: `leave/*` (5) · `leave_type/*` (3) · `sales_targets/*` · `dashboard/hrm_dashboard.blade.php`. **Não** tocar `attendance/*` nem `payroll/*`.
3. Remover as chaves de lang mortas nos 16 idiomas **só** das blades removidas.
4. `show()`/`edit()` dos resources que devolvem `essentials::show`/`essentials::edit` (views que não existem → 500): tirar da rota **em PR separado** — é `Routes/web.php`, prefixo compartilhado com a 09.

## PARAR SE
- Qualquer blade ainda referenciada por rota ativa (`git grep` do nome da view) → não remover.
- Rota do resource ainda sem Page equivalente → 500 vira 404; registrar, não "consertar" criando view.

## Prova
- `git grep -l "nav_hrm\|sidebar_hrm"` vazio · blades listadas ausentes · testes `EssentialsBladeT1InertiaSmoke` ajustados e verdes
- `_saida-11.md` · screenshot [W2] referenciado no PR
