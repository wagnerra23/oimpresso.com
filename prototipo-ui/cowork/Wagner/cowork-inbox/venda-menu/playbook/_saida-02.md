---
sessao: "02"
titulo: "Remessas → Sells/Shipments/Index — saída da thread"
autor: "[CL]"
criado: 2026-10-01
base: 55cdb3dcf
thread: 01-telas-legadas.md §01–07 (item 02)
veredito: "entregue em 2 PRs (backend #8491 → tela #8493) — Page PT-01 + drawer PT-02, nenhuma rota nova; 1 PARAR SE disparou (PR > 300 linhas) e foi tratado partindo em backend → tela; veredito dos testes pendente da lane sells-pest."
---

# _saída 02 · Remessas → `Sells/Shipments/Index`

## O que saiu

| PR | arquivos | o quê |
|---|---|---|
| [#8491](https://github.com/wagnerra23/oimpresso.com/pull/8491) backend | `app/Http/Controllers/SellController.php` (`editShipping`) · `tests/Feature/Sells/SellsShipmentsContratoTest.php` · `.github/workflows/sells-pest.yml` · `memory/requisitos/Sells/RUNBOOK-shipments.md` | `editShipping` responde JSON quando o cliente pede JSON · UC-REM-01/02/05/06 (metade drawer) |
| [#8493](https://github.com/wagnerra23/oimpresso.com/pull/8493) tela | `SellController@shipments` (branch Inertia, golden `getDrafts`, Blade como fallback) · `resources/js/Pages/Sells/Shipments/Index.tsx` · `_components/EditarRemessaSheet.tsx` · `Index.charter.md` · `Index.casos.md` · UC-REM-03/04/06/07 no mesmo teste · `memory/requisitos/Sells/SUPERFICIE.md` (regerado) | lista PT-01 + drawer PT-02 760px |

Prova da thread: `SellController.php` contém `Inertia::render('Sells/Shipments/Index'` — entra no #8493, junto com a page, porque o `OrphanRenderGateTest` (required) reprova render sem page existente. Na 1ª versão do #8491 o render ia no backend; a divisão foi refeita antes do veredito do gate.

## Como a tela fala com o backend (sem rota nova)

- **Lista:** `GET /sells?only_shipments=true` com `Accept: application/json` + `X-Requested-With` (padrão Drafts) — o mesmo DataTables do Blade, com paginação/ordem/busca no servidor (`start`/`length`/`order`/`search`). Permissões de remessa (`access_own_shipping`, `access_commission_agent_shipping`, `access_pending_shipments_only`, locais permitidos) continuam aplicadas lá, sem cópia.
- **Drawer:** lê `GET /sells/edit-shipping/{id}` (agora JSON quando pedido) e grava `PUT /sells/update-shipping/{id}`. O modal Blade segue recebendo HTML.
- **Romaneio:** `printSaleReceipt({ mode: 'packing_slip' })` → `/sells/{id}/print?package_slip=true`, só com `print_invoice`.

## O que vale saber

1. **O JSON legado não traz `id`.** `index()` faz `removeColumn('id')`; a tela tira o id do link `edit-shipping/{id}` que o próprio JSON devolve (coluna de ação/status). O teste usa a mesma extração.
2. **Status e pagamento chegam como HTML.** O texto sai por `DOMParser` (não executa script); a chave do status volta pelo rótulo de `shipping_statuses()`, a do pagamento pelo `data-orig-value`.
3. **"Rastreio" do protótipo não existe no legado.** A coluna da tela é "Detalhes de envio" (`shipping_details`), onde o Blade guarda o dado de envio; o UC-REM-03 foi escrito assim, com a nota. "Documentos da remessa" e "Atendente" do protótipo ficaram fora.
4. **A tela só é alcançada por navegação Inertia.** O sidebar usa `<a href>` comum, então `/shipments` pelo menu segue no Blade até o cutover F5 (humano), como Drafts.
5. **Alcance (`alcance:` no charter) não foi declarado:** a rota `/shipments` não tem `->name()` e dar nome seria mexer em `routes/web.php`, fora do prefixo.

## Fora desta thread (declarado no charter)

Upload/lista de documentos da remessa (`shipping_document`) e o histórico de atividades do modal; filtro de garçom (`service_staffs`, só com o módulo).

## PARAR SE

- **PR > 300 linhas — disparou.** Tela + drawer ≈ 430 linhas de TSX. Tratado como manda o §PARAR SE: partido em backend (#8491) → tela (#8493). O PR da tela ainda passa de 300 linhas sozinho (lista + drawer formam uma unidade); declarado no PR.
- Rota nova — não disparou. Tela de valor — não se aplica (remessa não mexe em total).

## Provas locais

`tsc` 0 erro nos arquivos novos (baseline 302 ≤ 333) · `eslint` limpo · `ds-guard` limpo · `memory-schemas/validate` OK · `casos-coverage-guard` sem violação nova · `screen-coverage --check` OK · `anchor-content-check` sem âncora podre · `module-surface --all --check` sem drift. Pest não roda local: veredito na lane `PHP / Pest (Sells · MySQL)`.

## Placar

entregue 1 de 1 tela · 7 UCs (5 de comportamento, 2 estruturais) · rotas novas 0.
