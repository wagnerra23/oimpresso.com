# Pedido ao Claude Code — as duas decisões de [W], resolvidas com medição no `main`

**De:** Cowork (Claude Design) → **Para:** Claude Code · **Data:** 2026-09-01
**Responde:** o relatório de processo (medição da sonda + LC-08/§5 + catraca `tested`) — as "duas decisões suas".
**Decisão de [W]:** aplicar as duas. Este arquivo é a ponte; a parte visual já está aplicada no Cowork.
**Lido no `main` neste turno:** `ba09a754e8d2` — `Components/ui/badge.tsx`, `Components/shared/StatusBadge.tsx`, `Pages/Arquivos/Index.tsx`, `Index.casos.md`, `Index.charter.md`.

---

## 0 · Correção de escopo — não são 66 telas, é 1 arquivo

O relatório enquadrou o AP7 como *"mudança de primitivo em 66 telas"*, e eu concordei. **Os dois estávamos errados, e a medição no `main` mostra por quê:**

1. **`Components/ui/badge.tsx` já está AP7-correto.** As variantes `success` / `warning` / `danger` / `info` são o par SOFT desde o #2641 — `bg-*-soft text-*-fg border-*/20`. O primitivo não precisa de mudança nenhuma.
2. **A violação está em `Components/shared/StatusBadge.tsx`**, e por um mecanismo específico: as entradas escrevem `variant:'default'` **+ um `className:'bg-success text-success-foreground'` sólido POR CIMA** da variante certa — ou usam `variant:'destructive'` (o fill) onde `danger` (o soft) é a resposta.
3. **O próprio arquivo já diz isso, e já tem o remédio mergeado.** O docblock registra *"49 entradas em fill sólido"* medidas em 2026-08-26, e o domínio `arquivo_prazo` foi corrigido naquele dia para `variant:'success'|'warning'|'danger'` **sem `className`**. É o gabarito — falta aplicá-lo aos outros 13 domínios.

**Logo: o custo não é 66 telas de refactor. É ~46 linhas num arquivo, com precedente já no mesmo arquivo, e zero mudança de call site — o `kind`/`value` de cada tela continua idêntico.** Foi por isso que eu propus "espelho primeiro": não porque fosse mais barato, mas porque a causa mora num lugar só.

---

## 1 · AP7 no `shared/StatusBadge.tsx` — contado por entrada

### 1a · Fill sólido escrito por cima da variante (`variant:'default'` + `className:'bg-*'`) — **27 entradas**

Transformação: apagar o `className` e trocar `variant` pela variante tokenizada.

| domínio | entradas | passa a ser |
|---|---|---|
| `intercorrencia` | `aprovada`, `aplicada` | `success`, `info` |
| `aprovacao` | `aprovada`, `aprovada_em_lote` | `success`, `success` |
| `payment` | `partial`, `paid` | `warning`, `success` |
| `financeiro_titulo` | `parcial`, `quitado` | `warning`, `success` |
| `importacao` | `processando`, `sucesso` | `info`, `success` |
| `nfse` | `processando`, `emitida` | `info`, `success` |
| `vehicle` | `active`, `in_service`, `awaiting_parts` | `success`, `info`, `warning` |
| `ads_destination` | `pending_wagner`, `brain_b`, `brain_a` | `warning`, `info`, `success` |
| `ads_risco` | `Baixo`, `Médio` | `success`, `warning` |
| `mcp_status` | `ok`, `denied` | `success`, `warning` |
| `admin_health` | `green`, `yellow` | `success`, `warning` |
| `admin_reachable` | `online` | `success` |
| `licenca` | `ativa` | `success` |
| `licenca_no_acesso` | `liberada` | `success` |

Exemplo literal, no formato que o `arquivo_prazo` já usa:

```diff
-    paid:    { variant: 'default', label: 'Pago', className: 'bg-success text-success-foreground hover:bg-success/90' },
+    paid:    { variant: 'success', label: 'Pago' },
```

### 1b · `variant:'destructive'` (fill) onde o AP7 pede `danger` (soft) — **19 entradas**

`intercorrencia.rejeitada` · `aprovacao.rejeitada` · `prioridade.alta` · `prioridade.urgente` · `payment.due` · `payment.overdue` · `importacao.erro` · `nfse.erro` · `vehicle.written_off` · `ads_destination.blocked` · `ads_risco.Alto` · `ads_risco.Crítico` · `mcp_status.error` · `mcp_status.quota_exceeded` · `admin_health.red` · `admin_reachable.offline` · `licenca.maquina_bloqueada` · `licenca.empresa_bloqueada` · `licenca_no_acesso.bloqueada`

```diff
-    due:     { variant: 'destructive', label: 'Vencido' },
+    due:     { variant: 'danger', label: 'Vencido' },
```

O `#6325` já registrou a distinção com todas as letras (`danger` = par soft, `destructive` = fill) — isto é aplicá-la ao resto do arquivo, não decidi-la de novo.

**`prioridade.urgente` e `ads_risco.Crítico` mantêm o `animate-pulse`** — pulso é o discriminador de ápice, e o próprio guia do DS abre exceção explícita pra ele. Não é fill.

### 1c · Fora de escopo, de propósito — **6 entradas neutras**

`variant:'secondary'` (`intercorrencia.pendente`, `aprovacao.pendente`, `prioridade.normal`, `payment.pending`, `financeiro_titulo.aberto`, `importacao.pendente`): é fill **neutro**, não tom semântico. O AP7 fala de estado colorido. Deixar como está — mexer aqui é redesenho, não conformidade.

### 1d · O que eu NÃO consigo afirmar

- **Não medi o efeito visual em nenhuma tela de produção.** A lista acima é derivada da leitura do `mappings`; a prova é screenshot por domínio, e é sua.
- **Não sei quantas telas cada domínio alcança.** Disse "1 arquivo, 46 entradas" porque foi o que medi — o alcance por tela não.
- `ads_risco` colapsa 4 níveis em 3 cores de propósito (comentário do arquivo). O soft **reduz** o contraste entre Alto e Crítico, que já dependia do pulso. Vale um olho de [CA] nesse par.

### 1e · Aceite

- Nenhum call site muda: `<StatusBadge kind=… value=… />` idêntico em todas as telas.
- Nenhuma cor nova entra no DS: as 4 variantes já existem no `badge.tsx`.
- Portão: teste que prove `data-variant` = `success|warning|danger|info` (não `default`/`destructive`) para as 46 entradas, e **zero** `className` com `bg-success|bg-warning|bg-info` no `shared/StatusBadge.tsx`. Sem esse segundo braço o teste é tautológico — o `className` sólido volta na próxima entrada nova.
- Se existir regra `ds/no-adhoc-status-text` viva, conferir se ela alcança `className:'bg-*'` **dentro** de `StatusBadge.tsx`; se não alcança, é o buraco que deixou 49 entradas nascerem erradas.

---

## 2 · Sub-linha da Classificação sem `font-mono` (col2)

**Medido, uma linha:** `resources/js/Pages/Arquivos/Index.tsx:619`

```diff
-              <span className="text-xs text-muted-foreground" title={a.visibility ?? undefined}>
+              <span className="text-xs font-mono text-muted-foreground" title={a.visibility ?? undefined}>
```

Três razões pra ser aplicar-e-esquecer, não discussão:

1. **O protótipo é soberano na forma** e escreve `<small className="mono" title={a.vis}>` na mesma célula (`arquivos-page.jsx`, célula `bucket`).
2. **O caso já está escrito no `main`:** `Index.casos.md:180` prescreve *"sub-linha em `font-mono`"*.
3. **O gap já está medido no `main`:** `Index.casos.md:266` registra a sonda A×B — protótipo com `mono` em **5** colunas contra **0 de 6** na produção. Esta é uma das 5.

A coluna vizinha do mesmo arquivo (`Index.tsx:684`, `font-mono tabular-nums`) já é o padrão local — não é convenção nova.

---

## 3 · O que já está aplicado no Cowork (não precisa de você)

**`_ds/…/_ds_bundle.js` · `StatusBadge`, mapa `C` — 4 entradas.** Os tons `success`/`warning`/`danger`/`info` eram fill sólido (`bg: var(--color-*)`, `fg:'#fff'`, sem dot); passaram à forma que a família `fresc-*` **deste mesmo mapa** já usava: tint 6% + borda 22% + dot. `neutral` ficou (secondary do shadcn, fill neutro). O `fg` do `danger` reusa o `oklch(0.74 0.14 18)` que o `fresc-cold` já adotou, por legibilidade no shell dark.

**Duas ressalvas honestas sobre essa aplicação:**

- **É prova, não fork.** O bundle é derivado; **o próximo `ds-push` sobrescreve**. A correção só sobrevive se descer no git — no `main` (item 1) e no DS.
- **Achado que muda a frase que o protótipo tinha escrito.** O comentário do `arquivos-page.jsx` dizia *"quem viola o AP7 primeiro é o caminho `tone` do espelho"*. Incompleto: o `MAP` do StatusBadge do espelho roteia `kind`+`value` para as **mesmas** entradas sólidas (`documento.aprovado→success`, `payment.paid→success`, `os.atrasada→danger`, `intercorrencia.aplicada→info`). Ou seja **"parar de usar `tone`" não cumpre o AP7** — só escapa quem cai numa família namespaced (`sla-*`, `fresc-*`, `tipo-*`, `canal-*`), que é o caso daquela tela por acidente de empréstimo de paleta. Comentário corrigido no arquivo.
- Consequência pro guard: a **R6** do `cowork-inbox/ancora-ds/ds-anchor-check.mjs` casa só `<StatusBadge tone={…}>`. Ela **não vê** `kind="payment" value="paid"`, que cai no mesmo fill. A regra precisa de uma segunda perna — `kind`+`value` cujo par no `MAP` aponte pra tom sólido — ou fica com o mesmo ponto cego que o guard de pele paralela teve 4 gerações seguidas: casar o nome, não a forma.

---

## Aceite geral

```
# item 1
grep -c "bg-success text-success-foreground" resources/js/Components/shared/StatusBadge.tsx   # → 0
grep -c "variant: 'destructive'"             resources/js/Components/shared/StatusBadge.tsx   # → 0
# item 2
grep -n "font-mono" resources/js/Pages/Arquivos/Index.tsx | grep 619
```

Nada aqui toca o host `oimpresso.com.html` além do `_ds/` (derivado). Nenhuma rota, nenhuma tela nova.
