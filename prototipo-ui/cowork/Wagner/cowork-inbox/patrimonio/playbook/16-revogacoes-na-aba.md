---
sessao: "16"
titulo: revogações entram na aba de Alocações (fusão de TELA, não de rota)
dono: "[CL]"
base: f8e6e02876fc
prefixo: Modules/AssetManagement/Http/Controllers/RevokeAllocatedAssetController.php · resources/js/Pages/Patrimonio/Alocacoes.tsx · Modules/AssetManagement/Tests/Feature/SmokeRoutesTest.php
nao_toca: Modules/AssetManagement/Services/AssetAllocationService.php · resources/js/Pages/Patrimonio/_shared/**
depende: "15"
---
# 16 · a fusão da thread 09 ficou pela metade

## A · IDENTIDADE (ancoragem dupla)
- **alvo (layout, read-only):** `prototipo-ui/cowork/patrimonio-page.jsx` (**58.265 B**) — a aba **Alocações**, onde alocar e revogar são **uma** tela.
- **âncora (código):** `RevokeAllocatedAssetController.php:108` — `return view('assetmanagement::asset_revocation.index');`. Do outro lado, `AssetAllocationController.php:150` já devolve `Inertia::render('Patrimonio/Alocacoes')`.
- **receptor que já existe:** `resources/js/Pages/Patrimonio/Alocacoes.tsx` (**14.994 B**) + `Alocacoes.charter.md` + `Alocacoes.casos.md`.
- **Blade que sai de cena:** `Resources/views/asset_revocation/index.blade.php` (**3.540 B**). O `create.blade.php` (3.599 B) da mesma pasta **fica** — é escrita, e escrita é `D-FORMS`.

## B · NÃO INVENTAR
- **A rota NÃO funde.** `GET asset/revocation` continua existindo e continua sendo a rota da revogação — fundir rota é decisão [W], escrita na `nota_provas` da thread `09`. O que funde é a **tela**: o `index()` passa a devolver a **mesma Page** com a visão de revogação.
- **Zero prop nova no `_shared`.** O `PatrimonioSubNav` deriva as abas de `shell.menu` (`DataController::modifyAdminMenu`) — **6 ghosts vivos, não as 7 do protótipo**. Ele é `nao_toca`.
- **Zero mudança no `AssetAllocationService`.** As threads `01`/`02` são donas dele; encostar aqui é colisão de prefixo.
- **Copy em PT-BR**, sem inglês na UI, sem emoji no app.

## C · O DEFEITO MEDIDO
O protótipo tem **uma** aba; a produção tem **duas** telas de índice para o mesmo assunto — uma React (`Alocacoes.tsx`) e uma Blade (`asset_revocation/index.blade.php`). Quem entra por `asset/revocation` cai no Blade, com sub-nav e densidade de outro regime. É a lacuna 2 do levantamento de 09/09, e ela **não** aparece no placar porque a thread `09` deu por fechada com a Page criada.

**Por que agora e não junto com a 09:** a 09 já fechou com `_saida-06-alocacoes.md`; reabrir thread fechada é pior que abrir a seguinte com o resíduo nomeado.

## D · COMO VALIDAR
1. `RevokeAllocatedAssetController::index` devolve `Inertia::render('Patrimonio/Alocacoes', …)` com um sinal explícito de visão (ex.: `visao: 'revogacao'`) — **não** uma Page nova, **não** um componente duplicado.
2. `Alocacoes.tsx` recebe a visão como prop **opcional**: sem ela, a tela renderiza **exatamente** como hoje (guarda — a rota `asset/allocation` não pode mudar de pixel).
3. `asset_revocation.index` não é mais chamado em nenhum lugar (`git grep` = 0). O `.blade.php` **pode ficar no disco** nesta thread — retirar arquivo é onda de limpeza, com o `create` ainda vivo ao lado.
4. Permissões: a guarda que hoje protege `revocation` continua valendo no novo caminho (medir com o teste, não no olho).
5. `SmokeRoutesTest.php`: caso novo para `GET asset/revocation` — status igual ao baseline e Page `Patrimonio/Alocacoes`. Colar o resumo do run no `_saida-16.md`.
6. **A11y do que você está mexendo** (§5-bis, e o alvo não é sagrado): se a visão de revogação trouxer tabela do protótipo, `th` com `scope` — é o 4º módulo com esse achado e nesta thread ele **não** se propaga.
7. PLACAR no corpo do PR.

## 4-ter · EXECUÇÃO
- **ARQUIVOS A EDITAR:** `RevokeAllocatedAssetController.php` · `Alocacoes.tsx` · `SmokeRoutesTest.php` — **só estes 3**.
- **REUSAR:** o `Inertia::render` que `AssetAllocationController:150` já monta (mesmo shape de dado, mesmo nome de Page); os átomos do DS que a Page já importa.
- **CRIAR:** nada de Page, nada de componente, nada de CSS.
- **NÃO TOCAR:** `_shared/`, `AssetAllocationService.php`, os `create.blade.php`.
- **PASSO A PASSO:** 1) ler os dois controllers e a Page inteira · 2) mapear o dado que o Blade de revogação usa hoje contra o que a Page já recebe · 3) render com a visão · 4) prop opcional na Page · 5) teste · 6) `_saida-16.md`.
- **DADO:** o mesmo `asset_transactions`/alocação que a tela já consome. **Se o Blade de revogação usar campo que a Page não recebe, declare no `_saida` e pare no que falta** — não invente fonte.
- **PARAR SE:** encaixar exigir fundir a rota, mexer no `_shared` ou tocar o Service — **pare e reporte**.

## PRÉ / PÓS
- **antes:** `asset/revocation` renderiza Blade; `Alocacoes.tsx` só serve `asset/allocation`.
- **depois:** as duas rotas caem na mesma Page; `asset/allocation` sem diff visual (guarda).
- **quebra:** se o controller já faz `Inertia::render`, **não execute** — reporte e pare.

## PROVA
`RevokeAllocatedAssetController.php` contém `Inertia::render('Patrimonio/Alocacoes'` e **não** contém `asset_revocation.index` · `Alocacoes.tsx` presente e sem diff na visão default (guarda) · `SmokeRoutesTest` verde com o run colado · `_saida-16.md`.
