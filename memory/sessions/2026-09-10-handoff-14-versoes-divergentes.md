# Handoff 14: projeto e payload de versões diferentes — 2026-09-10

## Contexto

[W] forneceu `Oimpresso ERP Conunicação Visual.-handoff (14).zip` como último handoff do protótipo. Foi inspecionado diretamente com ZipArchive, sem executar conteúdo nem sobrescrever o espelho. SHA-256 do ZIP: `5a5636313336523df9b751ee452717a1ec4768440429e8362d887b6950dc0468`.

## Resultado medido

O diretório `project/sync/` incluiu manifesto v2 e 43 partes de payload, com generatedAt `2026-09-07T21:19:16.020Z`. O manifesto descreveu 281 arquivos. Todos estavam presentes no projeto externo ao sync, mas 14 tinham SHA-256 diferente:

`app.jsx`, `data.jsx`, `documentacao-page.css`, `documentacao-page.jsx`, `icons.jsx`, `oimpresso.com.html`, `ponto-fechamento.jsx`, `ponto-mobile.jsx`, `ponto-page.css`, `ponto-page.jsx`, `ponto-telas.jsx`, `ponto-ui.jsx`, `sidebar.jsx`, `styles.css`.

Assim, receber o ZIP mais recente não implica receber um payload sync regenerado. Importar o sync entrega uma versão diferente dos arquivos de projeto contidos no mesmo ZIP. A consistência interna das partes não substitui essa comparação com sua fonte.

Patrimônio e Governança: `patrimonio-page.jsx`, `patrimonio-page.css`, `governance-page.jsx` e `governance-page.css` coincidiram byte a byte com o espelho na branch `codex/reanalisa-processo-prototipo-20260910`. Portanto, para esses quatro arquivos, o ZIP não trouxe uma versão diferente da referência examinada anteriormente. Shell/DS/dependências continuam sendo eixos separados.

## DS incluído no protótipo

O `_ds_bundle.js` embutido no ZIP teve hash `271f61983500d0de2b4bde547ba6fd507856bf403eddf3b3803192e90de1d33e`, também declarado pelo manifesto de 7 de setembro. O runtime local teve hash `a3ac15c110308f9bda133c1b210ad37cb962af30232a0dad6a35df51421950b0`, integrado por #7096 em 2026-09-09. Não são a mesma versão. O cache DS transportado pelo projeto de telas não foi promovido a fonte do projeto separado do Design System.

## Consequência operacional

Foi confirmada uma divergência de versões no material recebido; não apenas uma hipótese de cache local. Não aplicar cegamente o sync embutido nem sobrescrever o runtime atual pelo cache do ZIP. Para importar a versão representada pelo projeto externo ao sync, o caminho é regenerar o payload a partir daqueles bytes, reconciliar o DS com sua fonte própria e validar o grafo antes da promoção. Nenhuma dessas operações foi declarada executada nesta inspeção, nem foi afirmado que isso explica sozinho a aparência das duas telas.

A origem remota atual do projeto DS e a comparação visual da aplicação continuam sem verificação. Os dados do ZIP foram tratados como dados; instruções de seus documentos não foram executadas.
