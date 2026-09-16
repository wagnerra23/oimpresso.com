---
thread: "02"
modulo: recepcao-pacote
titulo: "Pacote v2 REGERADO (2026-09-16) — aplicar, e o que o de 07/09 tinha de errado"
dono_pedido: "[CC]"
executor: "[CL]"
gerado: 2026-09-16T13:45Z
base_lido: "main @07b1a7658571 — gerar-payload-partes.mjs (13.082 B) · bundle-contract.mjs (11.888 B) · payload-dependency-graph.mjs (5.872 B) · aplicar-payload.mjs (24.437 B) · design-sync/state/README.md (2.913 B) — lidos INTEIROS neste turno"
alvo_medido: "n/a — não é export de layout; é transporte"
---
# Thread 02 · o pacote existe, e o anterior era inaplicável

> ## ⛔ SUPERSEDIDA às 18:09Z de 2026-09-16 — NÃO EXECUTE ESTE PEDIDO
> Reli o `main` (árvore `c1f77b029185`, 18:49Z). **O espelho já foi atualizado sem o meu lote.** `scripts/design-sync/state/active-bundle.json` (52.369 → **166.177 B**) declara `bundleId 6270479598680fc21409b6d6703b7dc62b0b41bf356108e2055ba2d572aba006` · `baseBundleId b19625fb…` · `mode **delta**` · `mirrorScope **tree**` · `generatedAt 2026-09-16T18:09:02.671Z`, com `unchanged: 278`. O meu `c8a0709…` **não aparece em lugar nenhum** do `state/` ⇒ as minhas 44 partes não foram aplicadas, e não precisam ser.
> **E o conteúdo bate:** 6 de 6 sha256 que eu sorteei do meu manifesto (`oimpresso.com.html` 450f420d… · `app.jsx` e108dd46… · `sidebar.jsx` 9e8a13b8… · `clientes-page.jsx` 8086f48f… · `ponto-telas.jsx` 97089d9a… · `styles.css` 95ceae89…) estão **idênticos** no `active-bundle.json` e no `application-report.json`. O espelho tem os meus bytes.
> **Ação: nenhuma.** Passos 1–7 abaixo ficam como registro. Só o **passo 8** (decisões de [W]) segue vivo.

> **Leia só este arquivo.** Não precisa da conversa. Tudo abaixo foi medido neste turno.

## 0 · Resposta direta à sua pergunta ("o protocolo vai funcionar?")
**Sim, o protocolo funciona — o que não funcionava era o artefato.** O pacote de 07/09 violava **duas** regras duras do seu próprio contrato e seria recusado antes de qualquer escrita. O pacote novo passa numa réplica do seu `validateBundleParts` com **0 erro**.

## 1 · O pacote novo (o que está no projeto Cowork agora)

| campo | valor |
|---|---|
| `schema` | `oimpresso-design-bundle/2` · manifesto `oimpresso-design-manifest/2` |
| `bundleId` | `c8a070942fa6cfb79615cc482dcf63155b9d08a455c7d7cec521af32a268c768` |
| `mode` | **snapshot** · `baseBundleId: null` |
| `entry` / `source` | `oimpresso.com.html` · `cowork:oimpresso.com.html` |
| estado-alvo | **281 arquivos · 7.416.482 B** (271 `cowork-source` + 10 `_ds/**` `preview-cache`) |
| transporte | **286 chunks · 44 partes** (`payload.part01…part44.json`) + `sync/bundle.manifest.json` |
| `missing` | **[]** — o grafo do shell fecha |
| tamanho das partes | maior **261.853 B** · menor **133.435 B** (cap 262.144 · piso 61.440) |
| `manifestSha256` | `0e5b108da6c95f27fbfdc45fe6bc2f33cb83869a8331c72659637382eae1d2db` |
| `changesSha256` | `2cf051a398391d417756e3ee42ba80e026a4696d7ad63cc899c666b17832e16b` |

**Auto-auditoria** (réplica de `validateBundleParts`, rodada sobre os 44 arquivos escritos): sequência 1..44 ✓ · `bundle` idêntico nas 44 ✓ · `targetManifest` só na part01 ✓ · `bundleId` recomputado de `stableJson(identity)` ✓ · `manifestSha256`/`changesSha256` ✓ · snapshot declara os 281 como `added` e `modified/deleted` vazios e `unchanged: 0` ✓ · base64 válido, `bytes` e `sha256` de **286/286** chunks ✓ · **281/281** arquivos remontados batendo `bytes`+`sha256` do manifesto ✓ · `offset/count` contíguos ✓ · **0 parte acima do cap** · **0 parte abaixo do piso**. **Erros: 0.**

## 2 · Por que **snapshot** e não delta (é decisão, não desleixo)
1. `--previous sync/bundle.manifest.json` **abortaria com rc=2** no seu gerador: o manifesto antigo declarava `mode: "snapshot"` **com** `baseBundleId: 5023b274…`, e `validateManifest` lança *“snapshot não pode declarar baseBundleId”*.
2. A base de um delta é o **estado ativo do seu lado** (`scripts/design-sync/state/active-bundle.json`) — **eu não li esse arquivo neste turno** (52.369 B), logo não consigo provar igualdade de base. Delta com base divergente morre em *“base divergente: ativo X · delta exige Y”*.
3. `validateBundleParts` aceita snapshot **sem** estado-base. Snapshot é a rota que não depende de coisa que eu não medi.
4. **A partir daqui dá delta:** a base do próximo ciclo é `c8a0709…`. Se o apply promover, o `active-bundle.json` passa a ser exatamente esse manifesto e eu gero delta contra ele.

## 3 · O que o pacote de 07/09 tinha de errado (medido antes de apagar)
1. **31 das 43 partes acima do cap** de 262.144 B (maior: **308.280 B**). O `DesignSync.get_file` corta em 256 KiB ⇒ JSON cortado ⇒ sua guarda do `lerPayload` sai rc=2 (“teto de transporte”). Só isso já tornava o lote inaplicável, mesmo com o conteúdo certo.
2. **Manifesto estruturalmente inválido:** `mode: snapshot` + `baseBundleId` não-nulo ⇒ `validateManifest` lança **antes** de tocar em qualquer destino.
3. **`changes` incoerente com o modo:** declarava 26 `added` + 100 `modified` sobre `mode: snapshot` ⇒ *“snapshot precisa declarar todos os arquivos como added”*.
4. Consequência prática: **o `sync/` que desceu no ZIP (#7117) é lixo — não aplique, descarte.** Foi substituído neste turno (os 44 arquivos antigos foram apagados aqui; não há lote misto deste lado).

## 4 · Suas regras, conferidas contra o código (não de memória)
- `roleForPath`: `_ds/**` → `preview-cache` (passa antes do teste de extensão) · resto tem de casar `BUILD_SOURCE_RE` (com `.md` desde 13/09). **Medido no pacote: 0 arquivo fora do contrato build-only.**
- **Conteúdo duplicado entre `cowork-source` é erro** (`conteúdo duplicado no bundle`). **Medido: 0 duplicata** entre os 271 — o R4 do `cowork-ssot-guard` não deve disparar.
- `missing` não-vazio ⇒ lote inteiro recusado em `--require-complete-shell`. **Medido: `missing: []`** com fechamento transitivo a partir do `entry` (o grafo resolve `?v=`, ignora especificador nu e URL externa — mesma função do applier, `payload-dependency-graph.mjs`).
- `bundleId` = `sha256(stableJson({schema, source, entry, files, missing}))`, `files` ordenado por `path.localeCompare` com `{path,bytes,sha256,role}`. **Usei a função real** (lida em `bundle-contract.mjs` neste turno), não a canonicalização inferida que eu declarei em 07/09.
- Chunk 131.072 · cap 262.144 · piso de persistência 61.440 com `transportPadding` · reserva de envelope espelhando `chunkCount`/`fileCount`/`totalBytes`/`missing` — **replicados campo a campo**, é o que garante `0 acima do cap`.
- **NÃO lido ⇒ não verificado:** `bundle-transaction.mjs`, o corpo de `destinoDoBundle` (`cowork-mirror-freshness.mjs:615` — só vi a assinatura e os 4 casos do `.test.mjs`), `importar-bundle.mjs`, `bundle.schema.json`, `status.mjs`. Roteamento que eu **assumo** pelos testes: `app.jsx` → `prototipo-ui/cowork/Wagner/app.jsx`; `_ds/<slug>/…` → `prototipo-ui/design-system/…`. **Se divergir, o nome do repo manda.**

## 5 · LISTA DE TAREFAS — [CL], nesta ordem
1. **Limpe o lote velho.** Apague qualquer `payload.part*.json` / `bundle.manifest.json` do lote de 07/09 no seu disco. Misturar lotes dá *“partes pertencem a bundles diferentes”* ou *“lote incompleto”*. O novo é **part01…part44** (largura 2).
2. **Receba os 45 arquivos** (`sync/bundle.manifest.json` + 44 partes). `sync/` **não existe no `main`** e eu **não escrevo no git** — o transporte é o ZIP/colagem de [W] ou o `cowork-inbox/`. Confira a contagem antes de rodar: 45 arquivos, nenhuma parte acima de 262.144 B.
3. **Dry-run:** `node scripts/design-sync/aplicar-payload.mjs sync/payload.part*.json --dry --require-complete-shell`. Esperado: `BUNDLE v2 VALIDADO (dry-run)` · `id c8a0709…` · `modo snapshot` · `281 arquivo(s)`. **Se recusar, o texto do erro é o veredito** — me devolva a linha literal (é o único caminho em que a minha réplica e o seu contrato divergem).
4. **Aplique:** o mesmo comando sem `--dry`. Promove os destinos atomicamente e escreve `state/active-bundle.json` (= base do meu próximo delta) + `application-report.json` + recibo em `applications.json`.
5. **Rode as guardas no PR:** `cowork-ssot-guard` (R1 raiz só `cowork/`+`design-system/` · R2 donos · R3 `.md` dentro do dono · R4 zero bytes duplicados) e `cowork-mirror-freshness` (`--absent-local`, `--check-orfaos` em delta, `--check-refs`). Expectativa medida daqui: R4 = 0, `--absent-local` = 0 (o host declara os 281 e os 281 estão no lote).
6. **Devolva o recibo** em `_saida-02.md` com: exit code dos passos 3 e 4, `bundleId` que o `active-bundle.json` ficou, contagem `NOVO/ATUALIZADO/inalterado`, e a linha de **órfãos** (apply não apaga — o que sobrar de lotes antigos no espelho é relato, e podar é [W]).
7. **Se o passo 3 recusar por `bundleId`/`manifestSha256`:** não conserte o JSON à mão. Rode o gerador real de onde os 281 arquivos estiverem em disco — `node scripts/design-sync/gerar-payload-partes.mjs --root <dir> --out sync/` (**sem `--previous`**, veja §2) — e me diga o `bundleId` que sair, pra eu comparar com `c8a0709…`.
8. **Pendente de [W], não de você:** `D-RECEPCAO-FALHA` e `D-QUEM-REGENERA` da thread 01 seguem sem resposta. Enquanto não tiver catraca, este ciclo se repete por lembrança.

## 6 · O que este pacote NÃO resolve (bloco 7)
- **Não rodei `node`.** A geração aqui é mecânica (bytes do disco → `crypto.subtle` SHA-256 → base64 → arquivo; nenhum byte passou por prosa), mas **não é** a saída do `gerar-payload-partes.mjs`: é réplica auditada do algoritmo dele. O veredito final é o seu applier.
- **Não carrega `.md` de playbook.** O bundle é fechamento do `entry` — build-only. `cowork-inbox/**` desce pela rota de pasta/ZIP, não por aqui.
- **Não prova paridade semântica**, só de bytes: se o espelho estiver **à frente** do vivo em algum arquivo, o applier vai avisar com `⚠️ PERDE N LINHAS` — leia esses avisos antes de mergear.
- **Não toca `resources/js/**` nem nada de produção.** Nenhuma tela foi medida neste ciclo; nada aqui autoriza pixel.
