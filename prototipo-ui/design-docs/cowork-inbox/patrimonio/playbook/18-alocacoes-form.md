---
sessao: "18"
titulo: Alocações — formulário (alocar e revogar)
dono: "[CL]"
base: f8e6e02876fc
prefixo: resources/js/Pages/Patrimonio/Alocacoes.tsx · Modules/AssetManagement/Http/Controllers/AssetAllocationController.php
nao_toca: Modules/AssetManagement/Services/AssetAllocationService.php · resources/js/Pages/Patrimonio/_shared/**
depende: "16 + D-FORMS"
bloqueio: "D-FORMS — NÃO EXECUTAR sem resposta [W]"
---
# 18 · Alocações — a escrita, depois que a tela virou uma só

> ⛔ **BLOQUEADA por `D-FORMS`.** E, mesmo destravada, **atrás da `16`**: sem a fusão de tela, migrar formulário duplicaria drawer em duas telas de índice.

## A · IDENTIDADE (ancoragem dupla)
- **alvo (layout, read-only):** `patrimonio-forms.jsx` (**18.810 B**) — drawer de alocação e de revogação.
- **âncora (código):** `AssetAllocationController.php` :: `create()` **:320** · `show()` **:371** · `edit()` **:398**; índice já React em **:150**. Do lado da revogação: `RevokeAllocatedAssetController.php` :: `create()` **:131** · `show()` **:202** · `edit()` **:213**.
- **Blade em jogo:** `asset_allocation/{create 4.635 B, edit 5.328 B}` · `asset_revocation/create 3.599 B`. (`asset_revocation/edit.blade.php` **não existe** — ver thread `15`.)

## B · NÃO INVENTAR (quando destravar)
- **`AssetAllocationService` é `nao_toca`** — dono é a thread `02` (trava de saldo). O drawer **não** implementa regra de saldo no cliente: ele **mostra** o que o servidor devolve. Regra em dois lugares é a forma clássica de divergir.
- **Duas ações, um drawer com dois modos** — não dois componentes; a tela já é uma depois da `16`.
- **A rota continua não fundindo** (decisão [W] pendente na `09`): `POST asset/allocation` e `POST asset/revocation` seguem separados.

## C · O QUE A DECISÃO PRECISA PESAR
1. **Dependência dura:** `01` (vazamento Tier 0 na subconsulta de revoke) e `02` (trava de saldo) mexem na regra que este formulário submete. Ordem certa: `01` → `02` → `16` → `18`.
2. **Custo:** ~220 linhas; retira 3 Blades (~13,5 KB).
3. **`edit` de revogação não tem Blade** — pode ser rota morta. Se a `15` disser que é, esta thread **encolhe** e o `:213` vira remoção, não migração.

## D · COMO VALIDAR (quando destravar)
1. Os dois `create`/`edit` devolvem a Page `Patrimonio/Alocacoes` com o drawer no modo certo; índice sem diff com drawer fechado (guarda).
2. Erro de saldo vem do servidor e aparece no drawer como erro inline do campo — nunca como cálculo do cliente.
3. Multi-tenant: o teste `CrossTenantAssetTest` (dono da thread `01`) segue verde — guarda, não prova desta thread.
4. Campo a campo, Blade → formulário, no `_saida-18.md`.
5. A11y: rótulo, foco, `aria-modal`, foco devolvido; erro anunciado (`aria-live`).
6. PLACAR no corpo do PR.

## PRÉ / PÓS
- **antes:** alocar e revogar escrevem por Blade, em dois fluxos visuais.
- **depois:** um drawer, dois modos, uma tela; rotas intactas.
- **quebra:** `D-FORMS` sem resposta, ou `16` não fechada ⇒ não execute.

## PROVA
`AssetAllocationController.php` e `RevokeAllocatedAssetController.php` com `Inertia::render('Patrimonio/Alocacoes'` na escrita · os 3 Blades sem chamador · `CrossTenantAssetTest` verde (guarda) · `_saida-18.md`.
