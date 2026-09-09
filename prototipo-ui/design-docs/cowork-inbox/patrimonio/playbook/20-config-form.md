---
sessao: "20"
titulo: Configurações — formulário (prefixos e notificações)
dono: "[CL]"
base: f8e6e02876fc
prefixo: resources/js/Pages/Patrimonio/Configuracoes.tsx · Modules/AssetManagement/Http/Controllers/AssetSettingsController.php
nao_toca: Modules/AssetManagement/Config/retention.php
depende: "16 + D-FORMS"
bloqueio: "D-FORMS — NÃO EXECUTAR sem resposta [W]"
---
# 20 · Configurações — a menor das quatro, e a que mais depende da thread 15

> ⛔ **BLOQUEADA por `D-FORMS`.**

## A · IDENTIDADE (ancoragem dupla)
- **alvo (layout, read-only):** `patrimonio-page.jsx` (**58.265 B**) — aba Config (**831 nós** medidos em 04/09) + `patrimonio-forms.jsx`.
- **âncora (código):** `AssetSettingsController.php` :: `create()` **:150** (`view('assetmanagement::create')`) · `show()` **:228** (`::show`) · `edit()` **:239** (`::edit`); índice já React em **:92**.
- **Blade em jogo:** `settings/{index 2.698 B, notification_settings 5.206 B, prefix_settings 1.892 B}`.
- **receptor:** `Configuracoes.tsx` (**17.605 B**) + charter + casos.

## B · NÃO INVENTAR (quando destravar)
- **`retention.php` é `nao_toca`** — a retenção automática foi **descartada** em `memory/proibicoes.md` (2026-07-27) e corroborada pelo `_saida-04.md`. Não ressuscitar por "estava na tela de config".
- **Os 3 sítios deste controller são os mais suspeitos de código morto:** `::create`, `::show` e `::edit` **sem arquivo** na árvore, enquanto o Blade real de settings é `notification_settings`/`prefix_settings`, servidos por outro caminho. Se a thread `15` disser "inalcançável", esta thread deixa de ser migração e vira **remoção** — muda de tamanho e de risco.
- **Sem inglês na UI**, sem paleta inventada, tokens do DS.

## C · O QUE A DECISÃO PRECISA PESAR
1. **Ordem:** rodar a `15` antes economiza a thread inteira, no melhor caso.
2. **Custo:** ~160 linhas; retira até 3 Blades (~9,8 KB).
3. Config é a tela onde o resíduo §6 bate: prefixo de permissão `asset.*` × `assetmanagement.*` (item 2, já resolvido pelo SCOPE) e notificação de manutenção — não abrir escopo novo aqui.

## D · COMO VALIDAR (quando destravar)
1. `create`/`edit` na Page `Patrimonio/Configuracoes` (drawer ou seção — a tela é de formulário; drawer só se houver detalhe), índice sem diff (guarda).
2. Salvar prefixo e salvar notificação continuam gravando **os mesmos** campos — lista no `_saida-20.md`.
3. `retention.php` intacto (guarda) e nenhuma UI de retenção adicionada.
4. Se a `15` marcou os 3 sítios como inalcançáveis, o PR é de **remoção** e o `_saida` diz isso na primeira linha.
5. A11y: rótulo, `aria-live` no salvamento, foco após salvar.
6. PLACAR no corpo do PR.

## PRÉ / PÓS
- **antes:** 3 sítios de escrita apontando view sem arquivo; Blade de settings vivo ao lado da Page React.
- **depois:** escrita na Page **ou** sítios removidos — o que a `15` provar.
- **quebra:** `D-FORMS` sem resposta, ou `15` sem veredito ⇒ não execute.

## PROVA
`AssetSettingsController.php` sem `view('assetmanagement::create'|::show'|::edit')` · `retention.php` intacto (guarda) · `Configuracoes.charter.md` com o Non-Goal revogado e datado · `_saida-20.md`.
