# Patrimônio — ponteiro (o pedido vive no playbook)

> **Este arquivo virou ponteiro em 2026-09-08.** O conteúdo foi para `cowork-inbox/patrimonio/playbook/` — mesmo padrão de HRM e Ponto. Anti-scatter: **não abrir doc novo pro Patrimônio**; a pasta do playbook é a unidade que desce.

- **Pedido:** `cowork-inbox/patrimonio/playbook/00-INDICE.md` (+ 6 threads `NN-*.md`)
- **Leis:** `CONSTITUICAO-COWORK.md` (C1–C12), citadas — não copiadas
- **Como se gera:** `COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md` §13 · **por que se sabe:** `DOSSIE-PROTOCOLO-COWORK.md`

## O essencial, em 6 linhas (medido em `cb475c0ca2f4`, 2026-09-08)
1. O módulo é **`Modules/AssetManagement`** — nunca procurar por `Modules/Patrimonio`.
2. **100% Blade:** 6 `Route::resource` sob o prefixo `asset`, **0 `Inertia::render`**, **0 de 794** arquivos em `Pages/` batendo `patrimonio|asset`.
3. **20 arquivos começam hoje** (threads 01–05) · **46 travados** numa pergunta só: D-ENDERECO (módulo próprio × seção do Estoque).
4. **Achado novo de 08/09:** `AssetAllocationService.php:112` — subconsulta de revoke **sem `business_id`**. Vazamento Tier 0 em produção. É a thread 01.
5. **Um pedido de 04/09 morreu:** o `&&` da permissão (D1) não se reconfirmou; virou medição (thread 04), não PR.
6. As **11 decisões de [W]** estão no §6 do índice e no `playbook.json` do §7.
