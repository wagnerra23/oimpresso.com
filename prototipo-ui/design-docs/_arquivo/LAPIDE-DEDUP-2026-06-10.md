# LÁPIDE — Dedup 2026-06-10 (mandato [W]: "retirado o custo de duplicatas")

> [W] 2026-06-10: estrutura "espalhada, fora de padrão, custo alto para reler… retirado o custo de duplicatas."
> Deleção AUTORIZADA de cópias puras. O canônico de cada item vive no git `wagnerra23/oimpresso.com@main`
> (histórico completo por commit) ou no próprio projeto (1 cópia viva). Nada único foi perdido.

## Árvores removidas (e onde vive o canônico)

| Removido | O que era | Canônico |
|---|---|---|
| `_arquivo/legado/uploads/Oimpresso-handoff(1)/…` | CÓPIA COMPLETA do projeto aninhada (handoff antigo re-importado: memory/, prototipo-ui-patch/, uploads/Design System/, telas) | git @main + este projeto vivo |
| `_arquivo/legado/backups/2026-05-14-pre-handoff/` | snapshot integral do projeto em 05-14 | git @main (commits da época) |
| `_arquivo/legado/backups/2026-05-14-vendas-pre-aplus/` | snapshot integral pré-Vendas-A+ | git @main + `_arquivo/telas/` |
| `resources/` (raiz do Cowork) | espelhos locais de `resources/css|js` do repo — **fonte da classe de erro mais recorrente** (Regra 6: "fotocópia que envelhece"; reincidência tripla 06-08) | git @main — SEMPRE ler lá |
| `prototipo-ui-patch/integracao-vendas-oficina/` | snapshot integral de ponte JÁ PROCESSADA | git @main (mergeada) |
| `prototipo-ui-patch/vendas-financeiro-completo/` | idem | git @main |
| `prototipo-ui-patch/vendas-refino-kb-9.75/` | idem | git @main |
| `uploads/Oficina Auto - Cockpit (1).html` · `uploads/oficina-auto (1).jsx` · `uploads/Casos de Uso - Oficina Auto (standalone) (2).html` | duplicatas "(1)/(2)" de uploads | original sem sufixo em `uploads/` / `_arquivo/` |

## Regra que isto grava (anti-recidiva)
1. **Espelho local de arquivo do repo = PROIBIDO existir.** Estado do repo só por `github_read_file @main` no turno (Regra 6). Sem cache em disco.
2. **Ponte processada não guarda snapshot integral** — o prompt `.md` fica em `prototipo-ui-patch/_processados/`; o conteúdo vive no git mergeado.
3. **Backup integral de projeto = nunca dentro do projeto.** O git É o backup.
4. Upload duplicado "(N)" = deletar no recebimento.

Asserção que cobre: futura IT8 no `memory-health.js` (árvore proibida: `resources/` na raiz, `*(1).*` em uploads, snapshot integral em ponte). Registrada como residual da sessão `2026-06-10-reestruturacao-dedup.md`.
