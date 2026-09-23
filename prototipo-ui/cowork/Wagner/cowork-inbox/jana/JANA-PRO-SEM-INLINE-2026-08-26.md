# Jana/Pro — matar os 13 `style={{}}` inline (pedido zero-toque pro Code)

**Tela:** `/ia/pro` · `resources/js/Pages/Jana/Pro.tsx`
**Por que:** é a única das 4 telas da área Jana montada a partir de HTML: usa só `AppShellV2` + `PageHeader` do canon e resolve o resto com 13 blocos inline, todos com cor crua `oklch(...)` no `style` — o que faz o `ds/no-inline-raw-color` reclamar a cada PR que passa perto. `Pages/Arquivos/Index.tsx` (nascida do Padrão de Tela) usa 10 componentes do DS e tem **zero** inline. Verificado no `main` em 2026-08-26 (árvore `3ca7e27e85c3`).

**Fato importante:** os 13 inline estão TODOS dentro de um único bloco — o card de prova dark do hero (`{/* Hero direita — card de prova */}`). O resto do arquivo já é Tailwind + token. Então isto é um patch de **um bloco + um trecho de CSS**, não uma reescrita da tela.

## Rota escolhida: reusar o token que já existe, não criar paleta nova

A "ilha dark dentro de página clara" que o comentário do arquivo descreve **já tem token no sistema**: é a superfície da sidebar (`--sb-bg`, `--sb-hover`, `--sb-active`, `--sb-border`, `--sb-text-hi`, `--sb-text-dim`), dark-fixo nos dois modos (UI-0023). Usar ela:

- **não cria token novo** → sem loop VALOR:0, sem ADR de paleta, sem `ds-mirror-drift`;
- zera o `ds/no-inline-raw-color` desta tela (4 → 0) em vez de mover a dívida de lugar;
- muda o matiz da ilha de violeta-quente (hue 285) pro azul-frio da sidebar (hue 240). É a **única** diferença visual — declarada, não de contrabando. Vai gerar diff na baseline de `visreg` de `jana/pro` (que TEM baseline; o diff segue `targeted`).

Se [W] quiser preservar o hue 285 exato, aí é a rota B: 8 tokens novos `--jp-prova-*` em `cockpit.css` + entrada no DTCG — mais caro e é decisão de paleta, não de PR de tela.

## Patch

### 1. `resources/css/cockpit.css` — no fim do bloco `Sidebar — DARK FIXO`

```css
/* Ilha dark em página clara (Jana/Pro · card de prova). Herda a superfície da
   sidebar — mesma família, dark-fixo nos dois modos. Sem cor crua na tela. */
.ilha-dark{background:var(--sb-bg);color:var(--sb-text-hi)}
.ilha-dark-veu{background:radial-gradient(120% 80% at 100% 0%,color-mix(in oklch,var(--primary) 55%,transparent),transparent 60%);opacity:.35}
.ilha-dark-bolha{background:var(--sb-hover)}
.ilha-dark-bolha-eu{background:var(--sb-active)}
.ilha-dark-linha{border-color:var(--sb-border)}
.ilha-dark-mute{color:var(--sb-text-dim)}
.ilha-dark-marca{background:linear-gradient(135deg,var(--primary),color-mix(in oklch,var(--primary) 70%,white))}
.ilha-dark-pos{color:var(--success)}
.ilha-dark-pulso{background:var(--success);box-shadow:0 0 0 3px color-mix(in oklch,var(--success) 25%,transparent)}
```

### 2. `Pro.tsx` — apagar as 7 constantes de cor

Removem-se `PROOF_BG`, `PROOF_OVERLAY`, `PROOF_INK`, `PROOF_MUTE`, `BUB_THEM`, `BUB_JANA`, `NUM_POS` e o comentário `── Tokens canônicos do card de prova ──` que as justificava.

### 3. `Pro.tsx` — trocar o bloco do card de prova por este

```tsx
{/* Hero direita — card de prova (Jana lendo dados reais).
    Ilha dark = superfície da sidebar via classes `.ilha-dark*` (cockpit.css).
    Antes eram 13 `style={{}}` com oklch cru — a dívida que o ds/no-inline-raw-color
    cobrava nesta tela desde o F3. */}
<div className="ilha-dark relative flex flex-col gap-3 overflow-hidden rounded-lg p-5 shadow-md">
  <div aria-hidden className="ilha-dark-veu pointer-events-none absolute inset-0" />
  <div className="relative flex items-center gap-[9px]">
    <span className="ilha-dark-marca grid size-[26px] flex-none place-items-center rounded-full text-xs font-bold text-white">J</span>
    <b className="text-[13px]">Jana</b>
    <small className="ilha-dark-mute ml-auto flex items-center gap-[5px] text-[11px]">
      <span className="ilha-dark-pulso size-1.5 rounded-full" />
      lendo seu ERP
    </small>
  </div>

  <div className="ilha-dark-bolha relative max-w-[90%] self-end rounded-md rounded-br-[2px] px-[13px] py-2.5 text-[13px] leading-[1.5]">
    Jana, como foi meu faturamento esse mês?
  </div>

  <div className="ilha-dark-bolha-eu relative max-w-[90%] self-start rounded-md rounded-bl-[2px] px-[13px] py-2.5 text-[13px] leading-[1.5]">
    Maio fechou acima de abril. Veja pelos 3 ângulos:
    <div className="ilha-dark-linha mt-[9px] flex gap-3.5 border-t pt-[9px]">
      <div className="flex flex-col">
        <small className="ilha-dark-mute text-[9.5px] uppercase tracking-[0.08em]">Bruto</small>
        <b className="font-mono text-sm tabular-nums text-white">{fmtBRL(proof.bruto)}</b>
      </div>
      <div className="flex flex-col">
        <small className="ilha-dark-mute text-[9.5px] uppercase tracking-[0.08em]">Líquido</small>
        <b className="font-mono text-sm tabular-nums text-white">{fmtBRL(proof.liquido)}</b>
      </div>
      <div className="flex flex-col">
        <small className="ilha-dark-mute text-[9.5px] uppercase tracking-[0.08em]">Caixa</small>
        <b className="ilha-dark-pos font-mono text-sm tabular-nums">{fmtBRL(proof.caixa)}</b>
      </div>
    </div>
  </div>

  <div className="ilha-dark-mute relative mt-0.5 flex items-center gap-[7px] text-[11px]">
    <Check className="size-[13px] text-primary" strokeWidth={2} />
    Números reais das suas tabelas — sem planilha, sem integração.
  </div>
</div>
```

## Depois de aplicar

- `ds/no-inline-raw-color` em `Pro.tsx`: **4 → 0** (baixa o ratchet do CI em vez de segurar).
- `style={{}}` em `Pro.tsx`: **13 → 0**.
- Escopo do `ui-impact.mjs`: `targeted` (Pro.tsx + cockpit.css) — nenhum slot novo no `PageHeader`, nenhuma tela de outro módulo tocada.
- Regravar a baseline de `visreg` de `jana/pro` (mudança de hue da ilha, declarada acima).
- Nada de componente novo: só classe de shell em `cockpit.css`, na seção que já é dona do dark-fixo.

Se o `.ilha-dark*` for útil em outra ilha dark (e é: o mesmo motivo aparece no Cockpit Saúde), promover pra utilitário do DS é onda própria — não deste PR.
