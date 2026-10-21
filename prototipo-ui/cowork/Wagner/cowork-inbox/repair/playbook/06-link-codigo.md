---
sessao: "06"
titulo: Link de acompanhamento com código
dono: "[CL]"
base: 93ff8a960b74
---
# 06 · Link de acompanhamento com código

Casos: `cowork-inbox/repair/ConsultaOs-link.casos.md` (UC-COS-12..22, 25, 26). Forma: `repair-portal.jsx` (aba Portal do Repair: prévia "Abriu o link", "Digitou o código", "Link de OS pronta", "Link expirado", "Código errado"; 390px e tela cheia).

- **Âncoras lidas @93ff8a960b74:** `Modules/ConsultaOs/Routes/web.php` (prefixo `consulta-os`, `throttle:30,1`) · `ConsultaOsController@index/buscar` · `RepairConsultaOsRepository` (whitelist UC-COS-04) · `Pages/ConsultaOs/Index.tsx` + `OsLookupForm` + `OsResultCard`.
- **Código:** coluna nova em `repair_job_sheets`, única global, 8 caracteres de `23456789ABCDEFGHJKMNPQRSTUVWXYZ`, gerado por CSPRNG (`random_int`), nunca derivado do nº/id. Backfill das OS existentes dentro da validade.
- **Rota:** `GET /os/{codigo}` pública, mesma pilha e throttle do `consulta-os`. Renderiza a `ConsultaOs/Index` já aberta na OS (uma tela, duas entradas). Headers do UC-COS-25.
- **Busca:** `tipo=codigo` entra na lista fechada do `ConsultaPublicaRequest`; normaliza hífen, espaço e caixa.
- **Recibo/etiqueta:** QR + código legível no lugar do texto "nº + série" (UC-COS-20). Tag `{link_acompanhamento}` nos templates de status (UC-COS-21), sem disparar nada ao abrir o link.
- **Não faz:** login, cadastro, campo novo além do D-LOJA, mudança no UC-COS-01..11 (o UC-COS-09 só ganha a nota de que o caminho do código não cruza empresas).
