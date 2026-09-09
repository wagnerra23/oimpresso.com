---
sessao: "19"
titulo: Manutenções — formulário (e o D1 que continua vivo ao lado)
dono: "[CL]"
base: f8e6e02876fc
prefixo: resources/js/Pages/Patrimonio/Manutencoes.tsx · Modules/AssetManagement/Http/Controllers/AssetMaitenanceController.php
nao_toca: resources/js/Pages/Patrimonio/_shared/**
depende: "16 + D-FORMS"
bloqueio: "D-FORMS — NÃO EXECUTAR sem resposta [W]"
---
# 19 · Manutenções — escrita no controller que carrega o D1

> ⛔ **BLOQUEADA por `D-FORMS`.**

## A · IDENTIDADE (ancoragem dupla)
- **alvo (layout, read-only):** `patrimonio-forms.jsx` (**18.810 B**) — drawer de manutenção. O protótipo mediu a aba Manutenções em **916 nós** (04/09).
- **âncora (código):** `AssetMaitenanceController.php` :: `create()` **:322** · `show()` **:373** · `edit()` **:412**; índice já React em **:262**.
- **Blade em jogo:** `asset_maintenance/{create 4.681 B, edit 4.641 B}`.
- **receptor:** `Manutencoes.tsx` (**19.025 B**) + charter + casos.

## B · NÃO INVENTAR (quando destravar)
- **NÃO corrigir o D1 aqui.** O `&&` onde deveria ser `||` vive em **6 sítios deste controller** (`:63`, `:208`, `:243`, `:286`, `:322` + o medido na thread `04`), e a errata de 08/09 provou que ele **não caiu**: quem tem só `view_own_maintenance` — o técnico — é barrado das próprias manutenções. Corrigir permissão dentro de PR de UI mistura dois riscos num diff. **Registrar no `_saida`**, e o conserto é PR próprio.
- **Persona:** o técnico Repair usa tablet/celular — alvo de toque **≥44px** no drawer, não a densidade de escritório.
- **`_shared` é `nao_toca`.**

## C · O QUE A DECISÃO PRECISA PESAR
1. Migrar a tela **não** melhora o acesso do técnico: com o D1 vivo, ele continua barrado — a UI nova fica bonita e inacessível pra quem mais usa. Isso empurra o D1 pra **antes** da 19, ou pra o mesmo dia.
2. **Custo:** ~220 linhas; retira 2 Blades (~9,3 KB).
3. `show()` **:373** aponta `assetmanagement::show` — mesmo caso de view fantasma da thread `15`.

## D · COMO VALIDAR (quando destravar)
1. `create`/`edit` na Page `Patrimonio/Manutencoes` com drawer; índice sem diff fechado (guarda).
2. Toque ≥44px nos controles do drawer, medido, não estimado.
3. Campo a campo Blade → formulário no `_saida-19.md`, com o custo de manutenção declarado como **fora** (não há coluna — resíduo §6 item 3).
4. O D1 aparece no `_saida` com os 6 sítios e a frase "não corrigido aqui, por desenho".
5. A11y: rótulo, foco, `aria-modal`, `aria-live` no erro.
6. PLACAR no corpo do PR.

## PRÉ / PÓS
- **antes:** escrita em Blade; D1 vivo em 6 sítios.
- **depois:** drawer na Page; D1 **ainda vivo e nomeado** no recibo.
- **quebra:** `D-FORMS` sem resposta ⇒ não execute.

## PROVA
`AssetMaitenanceController.php` com `Inertia::render('Patrimonio/Manutencoes'` na escrita · 2 Blades sem chamador · `_saida-19.md` citando os 6 sítios do D1 · PLACAR.
