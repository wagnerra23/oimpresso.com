---
sessao: "13b"
titulo: Saída da thread 13 (2ª passada) — o smoke em prod da aba Auditoria, único item aberto da _saida-13
dono: "[CL]"
medido_em: 2026-09-30
base_medida: 553aeb36f (origin/main fresco)
arquivos_de_producao_tocados: 0
complementa: "_saida-13.md (2026-09-24), cujo checklist deixou aberto o smoke em prod depois do merge"
---

# 13b · Smoke em prod da aba Auditoria

## Por que é um arquivo novo e não um adendo na `_saida-13`
A `_saida-13.md` já estava verificada no espelho. Editá-la arma o check required "espelho — mexeu depois de verificar". Para `.md`, nada limpa esse check hoje além do próximo retorno do Cowork: o `--compare` do `cowork-mirror-freshness` só olha o universo `frescor` (jsx/html/css/js), e o `pendentes-cowork.mjs --conferir` imprime "1 verificado(s)" mas grava no ledger uma rodada sem nenhum arquivo verificado. Um recibo novo, nunca verificado, não bloqueia. É o mesmo desenho da `_saida-16b`.

## Resultado
Feito em `oimpresso.com`, biz=1 (WR2 Sistemas), usuário superadmin, com o `main` em `553aeb36f`.

- **A aba não aparece na faixa, e isso é por desenho.** O `PatrimonioSubNav` chama o `PageHeaderTabs` com `maxVisible={6}`. Com o ghost da Auditoria, o backend manda **7**, então a Auditoria cai no overflow "⋯" (`Mais 1 opções`), à direita de Configurações. Quem medir só as abas visíveis conclui, errado, que ela sumiu. Essa conclusão chegou a ser tirada nesta sessão e foi desfeita pela medição abaixo.
- **O backend manda o ghost certo.** Em `shell.menu`, a entry do módulo traz `{key: "auditoria", href: "/auditoria?subject_type=Modules%5CAssetManagement%5CEntities%5CAsset"}`. O item do overflow tem o mesmo `href`.
- **O clique abre a lista filtrada.** A URL vira `/auditoria?subject_type=…Asset` e o campo "Filtrar por tipo de entidade" vem com `Modules\AssetManagement\Entities\Asset`.
- **A lista vem vazia porque a empresa não tem bens.** A prop deferida `activities` foi pedida por partial reload. Sem filtro: 961 registros. `App\Contact`: 16. `App\Transaction`: 101. `Asset`: 0. E `bens` na mesma empresa: 0. O filtro funciona. A frase "o link mostra dados reais" (§Medições da `_saida-13`) continua **não verificada em prod**, porque lá não há bem cadastrado para gerar atividade.
- **Não medido, em lugar nenhum:** um usuário **não-superadmin** com `auditoria.view`. O smoke só cobre o ramo superadmin do gate (`isModuleInstalled`). O `MenuGhostsContratoTest` **também** usa usuário `superadmin` (`:69`), então o ramo por pacote (`hasThePermissionInSubscription` + `auditoria.view`) não tem teste nem smoke hoje.

## Checklist da `_saida-13`, item em aberto
- [x] Smoke em prod depois do merge (aba visível para um usuário com `auditoria.view`, e o clique abre a lista filtrada) — feito aqui, com as ressalvas acima
