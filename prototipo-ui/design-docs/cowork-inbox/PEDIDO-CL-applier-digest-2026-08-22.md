# Pedido pro [CL] — 3 correções no `aplicar-payload.mjs` + 1 pedido ao gerador do payload

> Autor: [CC] · data 2026-08-22 · lido no `main` NESTE turno: `scripts/design-sync/aplicar-payload.mjs`, `scripts/governance/cowork-mirror-freshness.mjs` (tree `6fbe59f32bd4`).
> Medido no `sync/payload.json` deste projeto Cowork (118 arquivos · 3.504.544 bytes · `schema cowork-payload/1` · `generatedAt 2026-08-17T11:48:27Z`).
> **O que eu NÃO fiz:** não rodei `node` (não tenho runtime aqui) e não commitei nada (tools de GitHub read-only). As medições abaixo são sobre o **payload real**; os bite-tests estão **especificados, não executados**.
> Ordem sugerida: **P0 (gerador) → C1 → C2 → C3**. Cada C num PR isolado com o bite-test do lado.

---

## P0 · O digest do payload não bate — e a etiqueta dele diz que deveria

**Medido (script sobre o payload real, este turno):**

| o que | resultado |
|---|---|
| `bytes` declarado == bytes reais | **118/118 ✓** |
| `fnv64` reproduzido | **0/118**, com **11 variantes** |
| digests únicos | 118/118 (derivam de conteúdo — só não do algoritmo anunciado) |
| envelope `hash` | `"fnv1a-64 (hex 16) sobre o conteudo UTF-8"` |

Variantes testadas: FNV-1a e FNV-1 × (bytes UTF-8 · `charCodeAt & 0xff` · code units 16 bits · UTF-16LE), prime de 32 bits dentro do estado de 64, offset 0, dois `fnv1a32` concatenados (fwd+rev e duplicado), `sha256`/`sha1` truncados em 16 hex (prefixo e sufixo), conteúdo com LF↔CRLF, `path+conteúdo`, `JSON.stringify(conteúdo)`.

**Consequência pro pedido:** a pergunta **não** é "é fnv1a-64?". O payload **já é autodescrito** e a descrição bate exatamente com o `fnv1a64()` que o applier implementa — e não confere. Então o pedido ao gerador é:

1. emitir o digest com **selftest de vetor conhecido** no próprio payload (ex.: `hashSelftest: { input: "abc", digest: "…" }`) — sem isso a etiqueta é afirmação sem prova;
2. hipótese mais barata pra investigar primeiro: **o hash é calculado antes da mutação do conteúdo** (`source.ancora` diz "query `?v=` removida") e os `bytes`, depois — o que explicaria bytes 118/118 e digest 0/118;
3. até (1) existir, o applier **continua não usando o digest como veredito** (está certo hoje) — mas ver C1.

---

## C1 · Digest: trocar silêncio por contradição medida

**Hoje:** o applier calcula `fnv1a64()` de todo arquivo e imprime 12 caracteres como enfeite (`${calc.slice(0,12)}`); a divergência com o `fnv64` declarado **nunca é dita**. O docblock diz "5 variantes, 0/118" — número de 17/08, hoje são 11 — e não menciona que o **próprio envelope declara o algoritmo implementado**.

**Pedido:** quando o envelope traz `hash` e o arquivo traz `fnv64`, comparar e, ao final do lote, imprimir **uma linha de contradição**: `⚠ gerador declara "<hash>" e o digest não bate em N/N arquivo(s) — bytes conferem em M/M; digest segue como referência, não veredito`. Não bloquear (o bloqueio dependeria de um digest que a gente não sabe calcular — é o raciocínio que já está no docblock e continua valendo). Atualizar o docblock para 11 variantes + a contradição do envelope.

**Bite-test:** payload fixture com `hash` declarado e `fnv64` errado em 2 de 3 arquivos → saída contém a linha de contradição com `2/3`, `exit 0`, e os 3 arquivos escritos.

---

## C2 · `bytes` ausente hoje passa calado — o único veredito real desaparece sem aviso

**Hoje:** `if (f.bytes != null && f.bytes !== bytesReais)`. Arquivo **sem** `bytes` é escrito **sem nenhuma verificação de integridade** e o log não distingue esse arquivo dos verificados. É a família já catalogada no repo (`veredictoFinal`/`LAST-PARTIAL`, LC-13): "não achei divergência" e "não procurei" saindo com o mesmo texto.

**Medido:** no payload real, **0 de 118 arquivos sem `bytes`** — o defeito é **latente**, não vivo. Vale consertar porque o applier é a rota principal e o gerador é de outra ponta: nada no formato obriga `bytes`.

**Pedido:** contar `semProvaDeBytes` e (a) somar ao rodapé (`N arquivo(s) escritos SEM prova de bytes`), (b) em `--require-complete-shell`, **recusar o lote** — modo que promete fechamento não pode escrever conteúdo não verificado.

**Bite-test:** fixture com 1 arquivo sem `bytes` → lote parcial escreve e reporta `1 sem prova de bytes`; com `--require-complete-shell`, `exit 1` e nada escrito.

---

## C3 · `missing` declarado é ignorado no lote parcial

**Hoje:** as três checagens de `missing` (`semDeclaracao`, `declarados`) vivem **dentro** do `if (requireCompleteShell)`. Um payload que declara `missing: ["app.jsx", …]` aplica em silêncio no modo lote parcial — quem serviu o payload avisou que faltava coisa e o applier não repassa.

**Medido:** no payload real, `missing: []` (0) — também latente.

**Pedido:** ler `missing` **sempre**; no lote parcial é **relato** (`ℹ️ o gerador declarou N ausente(s): …`, `exit 0`), em `--require-complete-shell` continua **bloqueio** (comportamento atual, sem mudança).

**Bite-test:** fixture com `missing: ["x.jsx"]` → lote parcial escreve, imprime o relato, `exit 0`; com `--require-complete-shell`, `exit 1`.

---

## Ressalva de cobertura (não é pedido — é honestidade)

O payload real que eu medi tem **0 `.md` e 0 `_ds/**`**. Ou seja: os dois roteamentos mais novos do applier — `.md` → `prototipo-ui/design-docs/` (decisão [W] 2026-08-21) e `_ds/**` → `mirror-snapshot/` via `dsRuntimeRelPath()` — **nunca foram exercitados por payload de produção**, só pelo selftest do próprio script. Se o gerador ainda não inclui `.md`, a decisão de 21/08 está escrita e **inerte**.
