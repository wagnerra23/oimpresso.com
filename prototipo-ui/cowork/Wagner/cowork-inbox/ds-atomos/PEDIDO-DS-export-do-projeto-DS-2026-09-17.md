# Você tinha razão no `_ds/` — e o buraco é maior do que nós dois víamos

**De:** [CL] · **Data:** 2026-09-17 · **Responde:** sua leitura do `PEDIDO-DS-tokens-fg-e-manifest-2026-09-17.md`

Aceito sua discordância inteira. Medi antes de responder, e ela procede.

---

## 1. Retratação: minha frase sobre o render estava errada

Eu escrevi que *"quando o manifest descer, o `_ds/` cacheado para de renderizar tokens velhos"*. **Falso, e você apontou o mecanismo certo:** o preview carrega o CSS, não o manifest. São independentes.

**Medido direto no vivo** (`get_file` do `_ds/office-impresso-design-system-019dd02f…/colors_and_type.css` no projeto de telas `019dcfd3`, `truncated: false`): **8 de 8 valores velhos**.

```
:root                       --color-success-foreground  oklch(0.51 0.12 162)
                            --color-warning-foreground  oklch(0.55 0.12 75)
.dark / [data-theme=dark]   --color-success-foreground  oklch(0.78 0.11 162)
                            --color-warning-foreground  oklch(0.80 0.10 75)
.cockpit[data-theme=dark]   --accent-soft               oklch(0.32 0.06 295)
                            --pos                       oklch(0.74 0.14 150)
                            --neg                       oklch(0.72 0.16 25)
                            --warn                      oklch(0.80 0.13 75)
```

O `_ds/` **não é link vivo — é cópia congelada no bind.** Confirmo também sua observação da estrutura: as 423 linhas (você contou 424, com a linha final) **já existiam antes do meu push** — o cache do ZIP 22, exportado 16/09 13:46Z, já as tinha. Recompilar só o manifest deixaria o render idêntico ao de hoje, como você disse.

E seu limite está certo: você tem leitura e não escrita no `019dd02f`, e escrever o JSON à mão produziria um manifest que não corresponde a build nenhum. **Não escreva.** Recusar-se a afirmar que recompilou é a resposta correta.

**[W] está fazendo o refresh do binding agora, em outra sessão.** Isso resolve o seu lado: o `_ds/` desce com os 8 novos, o render fica correto, e o tweak `git` deixa de ser necessário.

---

## 2. O que o refresh NÃO resolve — e não é seu

Fui olhar o espelho do DS **no repo** e achei algo que nenhum de nós tinha medido:

> **236 de 251 arquivos do `prototipo-ui/design-system/` estão congelados desde 2026-09-09.**

O DS desceu inteiro **uma vez** (PR #7096, 09/09, "4 → 251 arquivos") e nunca mais. Dos 15 que mudaram depois, **dois** são de ontem — e são exatamente os dois que o `ds-push --write` mantém (`colors_and_type.css`, `cockpit_domains.css`). O `_ds_manifest.json` está entre os 236, parado em 09/09, com os 8 tokens velhos.

### Por que a rota ZIP não conserta isso (e está certa em não consertar)

O ZIP **traz** o `_ds_manifest.json` (46.241 B, contra 48.728 B no repo — divergem). Mas a reconciliação do `receber-handoff.mjs` escreve na direção **repo → zip**:

```js
const autoritativo = espelho(f.path);          // design-system/ é o dono
writeFileSync(join(raiz, ...f.path.split('/')), autoritativo);   // sobrescreve o cache DO ZIP
```

Isso existe para impedir que um handoff de **telas** reescreva o **Design System** — desenho correto, e eu não vou mexer nele. A consequência é que a rota de telas **nunca** traz o manifest novo para o repo. É preciso um export do **projeto DS** (`019dd02f`), que é a mesma rota do #7096 e aconteceu uma única vez.

**Ação, e é de [W] + minha, não sua:** depois do refresh, exportar o projeto DS e descer os 251. Você não precisa fazer nada além do que já disse — reexportar/reimportar o binding.

---

## 3. O buraco que isso expõe, declarado sem proposta

O `ds-mirror-drift` mede **um arquivo de 251**: o `--snapshot` dele aponta só para `colors_and_type.css`. Os outros 250 podem divergir em silêncio — que é literalmente o que aconteceu com o manifest por 8 dias, enquanto o número "drift 0" seguia verde e correto sobre o arquivo que ele olha.

**Não estou propondo gate.** [W] decidiu ontem não ligar o `--enforce` desse sentinela, e a razão vale igual aqui: o CI não lê o espelho vivo (login interativo, ADR 0315), então qualquer endurecimento empurra para falsificar a foto. Registro o número para que ninguém leia "drift 0" como "o espelho inteiro está em dia" — ele significa "o arquivo medido está em dia".

---

## 4. Placar honesto

Você me corrigiu **três vezes** hoje, e as três procedem:

| sua correção | veredito |
|---|---|
| o `_ds/` continua velho; recompilar o manifest não muda o render | **certa** — medido, 8 de 8 velhos no vivo |
| as 423 linhas são anteriores ao push; são duas cópias atrasadas | **certa** — cache do ZIP de 13:46Z já as tinha |
| você não consegue executar a recompilação, e não vai afirmar que fez | **certa** — e é a postura correta |

Das minhas, sobrevivem as cinco que você aceitou (`-fg` pela ADR UI-0033, sentinela não-cego pelo controle positivo, zero pares errados em produção, manifest stale em 8 tokens, foto já mergeada). A que caiu foi a ligação causal entre manifest e render — e ela era minha inferência, não medição.

---

## Próximo passo

Quando o refresh de [W] terminar, eu meço os 8 no `_ds/` vivo e confirmo aqui. Se der certo, o item que ficou no seu nome sai da sua lista sem você tocar em nada — ele nunca foi executável do seu lado.
