# Arquivos — 10 refinos aplicáveis em produção

**Tela:** `resources/js/Pages/Arquivos/Index.tsx` (4 vistas: Acervo · Retenção · Cofre · Trilha)
**Base:** 4 screenshots do vivo (`oimpresso.com/arquivos`, WR2 Sistemas, 2 arquivos) + leitura do `Index.tsx` no `main` neste turno (2026-08-26, árvore `6fa1d053f32c`).
**Natureza:** tudo é copy/format/estado — nenhum refino escreve, apaga ou dispara job; a tela segue leitura pura. Nenhum depende da onda 3.

---

## A1 — Ícone da busca em cima do placeholder (P1, e não é só desta tela)

Screenshot do Acervo: o campo lê **"🔍scar por nome, dono ou contexto…"** — a lupa está desenhada sobre a primeira letra. O campo é do `Components/shared/DataTable` (`searchPlaceholder`), então o defeito aparece em **toda** tela que usa DataTable com busca (Clientes, Produtos, Financeiro…). Conferir lá: o `<Input>` precisa de `pl-9` (ou `pl-10` no tamanho maior) quando o ícone é absoluto. Corrigir no DataTable, não nesta tela.

## A2 — Datas em ISO na tela (P1)

`vence_em` e `quando` são renderizados crus:

```tsx
<span className="tabular-nums">{a.vence_em}</span>      // → 2026-09-07
<span className="tabular-nums text-xs">{row.original.quando}</span>  // → 2026-06-09 18:08
```

Canon do DS: **dd/mm/aaaa** operacional, hora `HH:MM` 24h. Formatar na tela e guardar o ISO no `title` (mesmo tratamento que bucket/disco já recebem):

```diff
+/** ISO do servidor → dd/mm/aaaa (canon DS). O ISO fica no `title`, pra depurar. */
+function dataBR(iso: string): string {
+  const [d] = iso.split(' ')
+  const [a, m, dia] = d.split('-')
+  return dia && m && a ? `${dia}/${m}/${a}` : iso
+}
+function dataHoraBR(iso: string): string {
+  const [d, h] = iso.split(' ')
+  return h ? `${dataBR(d)} ${h.slice(0, 5)}` : dataBR(d)
+}
-            <span className="tabular-nums">{a.vence_em}</span>
+            <span className="tabular-nums" title={a.vence_em}>{dataBR(a.vence_em)}</span>
```

(idem na coluna `quando` da Trilha, com `dataHoraBR`.)

## A3 — Inglês na UI cliente-facing: "grace period" e `hard_delete` (P1 — proibição)

Na Retenção aparecem, como **rótulo e como número-herói**: `No grace period`, `Grace period`, `Estratégia: hard_delete`. A proibição é dura (sem inglês em UI cliente-facing) e a própria tela já defende a regra em três lugares ("negócio na tela, técnico no `title`"). Aqui escapou porque o valor vem do config.

```diff
-        <KpiRetencao valor={kpis.no_grace} rotulo="No grace period" nota={`${retencao.grace_dias} dias pra restaurar`} />
+        <KpiRetencao valor={kpis.no_grace} rotulo="Na janela de restauro" nota={`${retencao.grace_dias} dias pra restaurar`} />
...
-            <span className="text-xs text-muted-foreground">Grace period</span>
+            <span className="text-xs text-muted-foreground">Janela de restauro</span>
...
-            <span className="text-lg font-medium">{retencao.estrategia}</span>
+            {/* Enum do config na tela seria jargão: PT-BR na tela, valor no `title`. */}
+            <span className="text-lg font-medium" title={retencao.estrategia}>
+              {retencao.estrategia === 'hard_delete' ? 'Apagar de verdade' : 'Anonimizar'}
+            </span>
```

A nota do card ("apagar de verdade; `anonymize` disponível por arquivo") passa a repetir o título — reescrever pra "o arquivo sai do disco; anonimizar é a alternativa por arquivo".

## A4 — "1 anos" (P2)

`ticket-anexo` tem 365 dias e a tabela imprime **"1 anos"**.

```diff
-                  {p.dias >= 365 ? `${Math.round(p.dias / 365)} anos` : `${p.dias} dias`}
+                  {p.dias >= 365
+                    ? `${Math.round(p.dias / 365)} ${Math.round(p.dias / 365) === 1 ? 'ano' : 'anos'}`
+                    : `${p.dias} dias`}
```

## A5 — Coluna "Disco" mostra `arquivos` — a correção do Cofre não chegou na tabela (P1)

O `rotuloDisco()` existe e traz a justificativa escrita ("quem lê a tela quer saber se está no cofre cifrado ou no disco comum — o nome técnico do disco é detalhe de infra"), mas a **coluna do Acervo** ficou com o valor cru: na screenshot a célula diz `arquivos`, que é o nome do disco Laravel do CT, não uma informação de negócio.

```diff
     {
       id: 'disco',
       header: 'Disco',
       cell: ({ row }) =>
         row.original.encrypted ? (
           <span className="text-xs font-medium">vault · cifrado</span>
         ) : (
-          <span className="text-xs text-muted-foreground">{row.original.disk ?? '—'}</span>
+          <span className="text-xs text-muted-foreground" title={row.original.disk ?? undefined}>
+            {row.original.disk ? rotuloDisco(row.original.disk).titulo : '—'}
+          </span>
         ),
     },
```

`vault · cifrado` também é enum+jargão na mesma coluna → "Cofre · cifrado".

## A6 — A sub-linha do arquivo é feita de duas ausências (P2)

Na screenshot as duas linhas dizem **"sem contexto · sem classificação humana"** — nenhuma informação, duas vezes, no caso mais comum do acervo. E `sem contexto` está dentro de `<code>`, que é para valor técnico, não para prosa. O mapa `CONTEXTO_PT` já existe e é usado **só** na Retenção; usar aqui alinha as duas abas:

```diff
-              <code>{a.sub_destination ?? 'sem contexto'}</code>
-              {lei ? <> · {lei}</> : null}
-              {a.classified_by && a.classified_by !== 'no_rule_matched' ? (
-                <> · classificado por {a.classified_by}</>
-              ) : (
-                <> · sem classificação humana</>
-              )}
+              {/* Mesmo vocabulário da Retenção: rótulo PT-BR na tela, slug no `title`. */}
+              <span title={a.sub_destination ?? undefined}>
+                {a.sub_destination ? (CONTEXTO_PT[a.sub_destination] ?? a.sub_destination) : 'Sem contexto mapeado'}
+              </span>
+              {lei ? <> · {lei}</> : null}
+              {/* "Sem classificação humana" só informa quando HÁ contexto: junto de "sem
+                  contexto" são duas ausências dizendo a mesma coisa. */}
+              {a.classified_by && a.classified_by !== 'no_rule_matched' ? (
+                <> · classificado por {a.classified_by}</>
+              ) : a.sub_destination ? (
+                <> · sem classificação humana</>
+              ) : null}
```

Ganho medido na screenshot: as duas linhas passam a dizer "Sem contexto mapeado · LGPD Art. 15-16 — eliminação tempestiva" — a lei que a Retenção já mostra, na linha onde ela decide o prazo.

## A7 — Trilha: `size=207560` como "Detalhe" (P2)

A coluna Detalhe imprime o `detalhe` cru do log: `size=207560`. `key=value` + bytes é formato de log, não de tela — e a função `tamanho()` já está no arquivo:

```diff
+/** `size=207560` → `203 KB`. Pares desconhecidos passam como vieram (o dono do vocabulário
+ *  é a coluna, não este mapa) — o cru fica no `title`. */
+function detalheBR(detalhe: string): string {
+  const m = detalhe.match(/^size=(\d+)$/)
+  return m ? tamanho(Number(m[1])) : detalhe
+}
-      <span className="text-xs text-muted-foreground">{row.original.detalhe ?? '—'}</span>
+      <span className="text-xs text-muted-foreground" title={row.original.detalhe ?? undefined}>
+        {row.original.detalhe ? detalheBR(row.original.detalhe) : '—'}
+      </span>
```

## A8 — Badges de estado ainda em fill sólido: "Vencendo" e "Envio" (P1 — AP7)

Nas screenshots, `Vencendo` sai âmbar cheio com texto claro (Acervo) e `Envio` azul cheio (Trilha). AP7 pede tint 5–10% + borda 22% + dot; e o próprio arquivo registra o #6268, que trocou 9 badges de **estado** para o par SOFT — os tons `info`/`warning` ficaram fora. Conferir `Components/ui/badge.tsx` + `StatusBadge kind="arquivo_prazo"` e aplicar o mesmo remédio do #6268 aos dois tons. É correção no primitivo, não nesta tela.

## A9 — Cofre saudável gasta a dobra explicando problemas que não existem (P2)

Screenshot: `0 acima do cap`, `0 órfãos`, `0 grupos com MD5 repetido` — e três parágrafos técnicos (OOM do `VaultEncryptionService`, `attach()`, caminho derivado do hash) ocupando a tela inteira pra dizer que **nada** foi achado. A explicação é boa e deve ficar; o que muda é o peso quando o total é zero:

```diff
+  const semAchados = acima.total === 0 && orfaos.total === 0 && duplicados.grupos === 0
...
+          {semAchados ? (
+            <Stack gap={2}>
+              <span className="text-sm font-medium text-foreground">Nada a apontar</span>
+              <p className="max-w-[72ch] text-xs leading-relaxed text-muted-foreground">
+                Medimos os três sinais do <code>arquivos:health-check</code> que esta tela cobre — arquivo acima
+                do cap de {cofre.cap_mb} MB, órfão sem vínculo e MD5 repetido — e nenhum apareceu. Quando um
+                aparecer, ele abre aqui com a amostra e o que significa.
+              </p>
+            </Stack>
+          ) : (
+            <>{/* os três <Achado> como já estão */}</>
+          )}
```

Achado com total 0 continua listado quando **algum** outro apareceu (o contraste é a informação); o texto longo só desaparece quando não há nada.

## A10 — "servido por `Storage::url`" (P2)

É a nota do card "Disco comum" — mesmo card cuja justificativa escrita é "o nome técnico vai no `title`". `Storage::url` é código.

```diff
-    : { titulo: 'Disco comum', nota: 'servido por Storage::url' }
+    : { titulo: 'Disco comum', nota: 'servido direto pelo navegador, sem link temporário' }
```

---

## Bônus barato

Na tabela da Retenção, 8 dos 9 contextos estão em 0 e o único com arquivo (`default`, 2) é a **última** linha. Ordenar por arquivos desc (mantendo a política inteira visível) põe o que existe no topo sem esconder nada: `[...politica].sort((a, b) => (retencao.por_contexto[b.sub] ?? 0) - (retencao.por_contexto[a.sub] ?? 0))`.

## Gates

`npm run lint` (inclui `layout-primitives-guard` e `ds/no-db-jargon-in-ui`) · `npm run typecheck` · `php artisan test --filter=Arquivos` · contrato de tela (`prototipo-ui/contrato/arquivos.contract.json` — **A3/A5/A6 mudam copy literal**: atualizar o contrato no mesmo PR, senão o gate reprova) · visreg das 4 abas.
A1 e A8 são no primitivo compartilhado: rodar o visreg das telas que usam DataTable/Badge, não só Arquivos.
