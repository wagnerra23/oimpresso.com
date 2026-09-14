---
sessao: "17"
titulo: Bens — formulário (create/edit/show)
dono: "[CL]"
base: f8e6e02876fc
prefixo: resources/js/Pages/Patrimonio/Bens.tsx · Modules/AssetManagement/Http/Controllers/AssetController.php
nao_toca: resources/js/Pages/Patrimonio/_shared/**
depende: "16 + D-FORMS"
bloqueio: "D-FORMS — NÃO EXECUTAR sem resposta [W]"
---
# 17 · Bens — a escrita que os charters declaram como Non-Goal

> ⛔ **BLOQUEADA por `D-FORMS`.** Os 5 charters do Patrimônio declaram `create`/`edit`/`show` como **Non-Goal com motivo**. Abrir PR aqui antes da resposta de [W] é pedido **contra o charter** — e a errata da thread `08` já registra esse Non-Goal como decisão, não esquecimento.

## A · IDENTIDADE (ancoragem dupla)
- **alvo (layout, read-only):** `prototipo-ui/cowork/patrimonio-forms.jsx` (**18.810 B**) — os formulários do módulo no regime drawer PT-02.
- **âncora (código):** `AssetController.php` :: `create()` **:479** (`view('assetmanagement::asset.create')`) · `show()` **:520** (`view('assetmanagement::show')`) · `edit()` **:551** (`view('assetmanagement::asset.edit')`). O `index()` já é React (**:269**, `Inertia::render('Patrimonio/Bens')`).
- **receptor:** `resources/js/Pages/Patrimonio/Bens.tsx` (**21.047 B**) + charter + casos.
- **Blade em jogo:** `asset/create.blade.php` **9.867 B** · `asset/edit.blade.php` **10.070 B** — os dois maiores do módulo. O `asset/index.blade.php` (11.242 B) **não** entra: já foi substituído.

## B · NÃO INVENTAR (quando destravar)
- **Drawer, não modal full-screen** (PT-02; modal full-screen pra detalhe é proibição do sistema).
- **`show` é decisão separada de `create`/`edit`:** hoje `:520` aponta uma view que **pode não existir** — quem responde isso é a thread `15`. Sem o veredito dela, esta thread não sabe se `show` é migração ou remoção.
- **Zero campo novo.** O formulário React nasce com **os campos que o Blade já grava** — depreciação, baixa e transferência são resíduo §6 (itens 3, 6, 7, 8) e **não** entram por atalho de formulário.
- **`_shared` é `nao_toca`.**

## C · O QUE A DECISÃO PRECISA PESAR
1. **Custo:** ~260 linhas + retirar 2 Blades de ~10 KB. É a maior das 4 threads de escrita.
2. **Risco:** `create`/`edit` de bem tocam `asset_transactions` e o saldo que as threads `01`/`02` estão consertando — migrar a tela **antes** de `01`/`02` fecharem é pintar por cima de regra em obra.
3. **Alternativa honesta:** manter Blade e **escrever o Non-Goal no charter com prazo** (é o que já está lá, sem prazo). Paridade visual do índice sem paridade da escrita é um estado legítimo — desde que declarado, não esquecido.

## D · COMO VALIDAR (quando destravar)
1. `AssetController::create/edit` devolvem `Inertia::render` da **mesma** Page `Patrimonio/Bens` com o drawer aberto (a tela é uma; o formulário é estado dela) — **não** Page nova.
2. Índice sem diff visual quando o drawer está fechado (guarda).
3. Todo campo que o Blade gravava continua sendo gravado — lista campo a campo no `_saida-17.md`, do Blade para o formulário.
4. Validação do lado servidor **reusada** (FormRequest existente), não reescrita no cliente.
5. `SmokeRoutesTest` + o teste de feature do módulo verdes, run colado.
6. A11y do alvo antes de exportar: rótulo em todo campo, foco inicial no drawer, `aria-modal`, foco devolvido ao fechar.
7. PLACAR no corpo do PR.

## PRÉ / PÓS
- **antes:** `create`/`edit`/`show` em Blade; charter declarando Non-Goal.
- **depois:** drawer na Page de Bens; charter **atualizado** (o Non-Goal deixa de valer e isso se escreve).
- **quebra:** `D-FORMS` sem resposta ⇒ não execute.

## PROVA
`AssetController.php` contém `Inertia::render('Patrimonio/Bens'` nos métodos de escrita · `asset/create.blade.php` e `asset/edit.blade.php` sem chamador (`git grep` = 0) · `Bens.charter.md` com o Non-Goal revogado e datado · `_saida-17.md`.
