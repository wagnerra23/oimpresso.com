---
date: "2026-09-28"
time: "12:22 BRT"
slug: fabricacao-threads-e-conciliacao-felipe
tldr: "Playbook manufacturing: 3 de 4 threads feitas (01 #8016, 02 #7984, 04 #7989). Thread 03 suspensa por decisao [W]: a versao do Felipe (#7991) e a principal e a pasta cowork/Felipe/manufacturing-* fica. Porte Wagner+onda B descartado sem subir ao Cowork. D-RET-01 decidida: Wagner."
prs: [7984, 8003, 8016, 7991]
decided_by: [W]
next_steps:
  - "Felipe aplicar a D-RET-01 no #7991 (comentario 5868436186)"
  - "Cowork marcar a thread 03 como bloqueada/descartada no proximo pacote (o placar ainda a lista como proximo)"
---
# Fabricação — threads do playbook e conciliação com o Felipe

## Estado MCP no momento
- `cycles-active`: nenhum cycle ativo em COPI.
- `my-work`: sem tasks ativas.
- Placar (`node scripts/qa/placar.mjs --indice prototipo-ui/cowork/Wagner/cowork-inbox/manufacturing/playbook/00-INDICE.md`): **entregue 3 de 4** — 01, 02, 04 feitas; 03 aparece como `proximo`, mas está suspensa por [W].

## O que aconteceu
- **Thread 02** ([#7984](https://github.com/wagnerra23/oimpresso.com/pull/7984), mergeado): os 5 charters de `resources/js/Pages/Manufacturing/` apontam `related_prototype` para `cowork/Wagner`. Recibo `_saida-02.md` enviado ao Cowork e registrado no [#8003](https://github.com/wagnerra23/oimpresso.com/pull/8003). [W] confirmou em 28/09: **os charters ficam no Wagner**.
- **Thread 03** (aposentar `cowork/Felipe/manufacturing-*`): não executada. A comparação por hash mostrou que só `manufacturing-data.jsx` tem cópia idêntica no Wagner. Em seguida a sessão gerente avisou que o Felipe edita esses arquivos no [#7991](https://github.com/wagnerra23/oimpresso.com/pull/7991), e [W] decidiu que **a versão do Felipe é a principal** e que a pasta fica.
- **Porte Wagner + onda B do Felipe:** preparado por merge de 3 vias (ancestral: Wagner@`4f51a9ec7`) e testado no host, mas **descartado**. O #7991 já cobre tudo o que ele trazia e ainda acerta um ponto que o porte errava (a linha da lista não abre detalhe, como no vivo). Nada foi escrito no Cowork.
- **D-RET-01 decidida por [W] em 26/09: Wagner.** Título "Produção" na aba Ordens e "Manufacturing" nas demais; a frase "custo recalculado…" fica no subtítulo das abas que não são Ordens. Registrada no [comentário do #7991](https://github.com/wagnerra23/oimpresso.com/pull/7991#issuecomment-5868436186).
- **Thread 01:** feita por outra sessão ([#8016](https://github.com/wagnerra23/oimpresso.com/pull/8016), mergeado). A duplicata #8022 foi fechada. O `_saida-01` foi enviado no #8027.

## Achados que valem além desta sessão
- **`visual-regression` vermelho em PR que toca `Pages/Manufacturing/Index.tsx` não é regressão:** o gate para em fail-closed porque `Manufacturing` não está em `tests/Browser/visreg-screens.json` (#7989 e #8016, mesmo motivo). O #8016 foi conferido pelo código: só atributos `data-contract` e um `<div>` em volta do `PageHeader`, que não é sticky e fica num pai `space-y-6` — sem efeito visual. Cobrir a tela é decisão [W] (o pedido declarou "nenhuma thread de pixel"; a ADR 0409 aposentou a regeneração de baseline).
- **Colisão `.mfg-grid` × `mockup-pages.css`** no host do Wagner: a folha global define `.mfg-grid{display:grid;grid-template-columns:1.5fr 1fr}`. O Felipe também achou e resolveu no #7991.
- **Preview do host do Wagner:** sirva a **raiz do repo**, não `prototipo-ui/`. O host só usa `../../design-system/` quando o caminho contém `/prototipo-ui/cowork/`; fora disso cai no `_ds/` local, que não é versionado, e o DS some.

## Lições catalogadas
- Rodei `whats-active` no início, mas **não repeti a checagem de PR aberto antes de começar o porte**. O #7991 teria aparecido. É a emenda §5 2026-09-05 (re-checar antes de publicar/produzir). Na thread 01 a checagem prévia foi feita e evitou um terceiro PR duplicado.
- Afirmei no chat que o merge tinha mantido o `CliTabs`, sem conferir; o merge tinha trazido o `TabBar` do DS. Corrigido no chat antes de qualquer envio.

## Próximos passos pra retomar
```
node scripts/qa/placar.mjs --indice prototipo-ui/cowork/Wagner/cowork-inbox/manufacturing/playbook/00-INDICE.md
```
Nada pendente do lado do Code na Fabricação. Pendências de outros: Felipe aplica a D-RET-01 no #7991; o Cowork marca a thread 03 como bloqueada no índice (editar o índice no espelho quebra um check obrigatório).

## Pointers
- Playbook: `prototipo-ui/cowork/Wagner/cowork-inbox/manufacturing/playbook/` (`_saida-01`, `_saida-02`, `_saida-04`)
- Retorno do Felipe: `_saida-felipe-retorno.md` no #7991
