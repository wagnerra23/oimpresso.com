---
sessao: "05"
titulo: shared/StatusBadge.tsx — kinds sla · frescor · atendimento + props rel e tone (tokens que JÁ existem)
dono: "[CL]"
base: 07036a68a049
prefixo: resources/js/Components/shared/StatusBadge.tsx · tests/js/statusbadge-kinds.test.tsx (criar)
nao_toca: Components/ui/badge.tsx · FiscalStatusBadge (e seus 5 consumidores) · resources/js/Pages/** · tokens (resources/css/**)
depende: 08 (AP7 — feito) · D-SB-KINDS respondida ([W] 2026-09-24)
---
# 05 · StatusBadge — os 3 kinds que só o DS tinha

## A · IDENTIDADE (ancoragem dupla)
- **alvo (vocabulário, read-only):** `StatusBadge` do bundle do DS — `_ds/…/_ds_bundle.js` (mapa `MAP`), lido pelo [CC] em 2026-09-24. Chaves e rótulos abaixo são **literais de lá**.
- **âncora (código):** `resources/js/Components/shared/StatusBadge.tsx` — **15.840 B** @`07036a68a049`, lido inteiro. `Variant` é **derivado** de `badgeVariants` (success/warning/danger/info/neutral/outline…) — é daí que saem os tons; nenhum token novo.
- **NÃO ler:** `Pages/**`.

## B · NÃO INVENTAR — escopo fechado pela D-SB-KINDS
- **ENTRAM:** `sla` · `frescor` · `atendimento` + props **`rel`** e **`tone`**.
- **FICAM FORA:** `fiscal` (fonte única = `FiscalStatusBadge`, 5 consumidores — `REGISTRY_DS_COMPONENTES`) · `tipo` PJ/PF (exigiria `--color-tipo-pj/-pf`, **0** no repo).
- **Sem token NOVO — mas com as cores próprias.** Emenda [W] 2026-09-24 à D-SB-KINDS: *"gostei das cores próprias, e pode ter cores diferentes atrasado e vencido"*. Os tokens **já existem no `main`** (lido @`07036a68a049`): `resources/css/tokens/semantic.tokens.json:198–223` → `_generated-cockpit-{light,dark}.css`:
  - SLA: `--sla-{fresh,aging,late,expired}` + `-soft` + `-dot` (light e dark)
  - canal: `--canal-{email,ig,fb,ml}-{tint,fg}` (light e dark)
  Logo **não** se cria token, e **não** se toca `resources/css/**`: o badge só **consome** esses. Forma AP7 continua: fundo tintado + texto + dot, **nunca** fill sólido (`--canal-*-bg` é o chip sólido — **não usar**).
- Kinds existentes: **0 diff**.

## C · MAPA (chave e rótulo = DS literal · cor = token cockpit que já existe)
Como o `Variant` do `ui/badge` não tem esses tons, a entrada usa `variant: 'outline'` (sem fill) + `className` com os tokens — o mesmo mecanismo que o arquivo já usa para `animate-pulse`. Sem cor crua: só `var(--…)`.

| kind | chave | rótulo | classe (fundo · texto · borda) |
|---|---|---|---|
| `sla` | `fresh` | No prazo | `bg-[var(--sla-fresh-soft)] text-[var(--sla-fresh)] border-transparent` |
| | `aging` | Vencendo | `bg-[var(--sla-aging-soft)] text-[var(--sla-aging)] border-transparent` |
| | `late` | Atrasado | `bg-[var(--sla-late-soft)] text-[var(--sla-late)] border-transparent` — hue **30** |
| | `expired` | Vencido | `bg-[var(--sla-expired-soft)] text-[var(--sla-expired)] border-transparent` — hue **25**, mais saturado: **cor diferente de Atrasado**, como [W] pediu |
| `frescor` | `recente` | recente | `variant: 'success'` (o DS deriva de `--color-success` a 16% — é o par soft que já existe) |
| | `fresc` | fresc | `variant: 'warning'` |
| | `frio` | frio | `variant: 'danger'` |
| | `distante` | distante | `variant: 'danger'` |
| `atendimento` | `email` | E-mail | `bg-[var(--canal-email-tint)] text-[var(--canal-email-fg)] border-transparent` |
| | `instagram` | Instagram | `bg-[var(--canal-ig-tint)] text-[var(--canal-ig-fg)] border-transparent` |
| | `facebook` | Facebook | `bg-[var(--canal-fb-tint)] text-[var(--canal-fb-fg)] border-transparent` |
| | `mercadolivre` | Mercado Livre | `bg-[var(--canal-ml-tint)] text-[var(--canal-ml-fg)] border-transparent` |
| | `whatsapp` | WhatsApp | `bg-[var(--sla-fresh-soft)] text-[var(--sla-fresh)] border-transparent` — **o DS usa o verde do SLA** (`sla-fresh`), sem token de canal próprio. É pílula de status soft, não CTA: não fere "sem WhatsApp loud" |

**Dot:** o `Badge` já desenha o dot com `currentColor` (`dot` ligado neste componente). Se o `main` tiver `--sla-*-dot` e o dot sair com a cor do texto, isso **basta** — não criar prop de cor de dot.

**Fora do `.cockpit`:** os tokens vivem em `_generated-cockpit-*`. Toda Page em `AppShellV2` está dentro do `.cockpit`; **medir** que o badge renderiza com cor (não transparente) numa Page real antes de fechar. Se alguma tela consumidora ficar fora do escopo, **reportar**, não duplicar token.

## D · PROPS NOVAS (opcionais, default = comportamento de hoje)
- **`rel?: string`** — sufixo de tempo relativo, renderizado **depois** do rótulo com separador `·` (ex.: `<StatusBadge kind="frescor" value="recente" rel="há 1 sem" />` → "recente · há 1 sem"). Em qualquer kind. Ausente = nada muda.
- **`tone?: Variant`** — sobrescreve a `variant` do mapa (e do fallback). Tipado pelo `Variant` derivado — string fora do DS **não compila**. Ausente = nada muda.

## E · COMO VALIDAR
1. Cada uma das 13 chaves do bloco C renderiza o rótulo literal com a classe/variant da tabela (teste parametrizado).
1b. **Atrasado ≠ Vencido:** `getComputedStyle(color)` das duas pílulas **difere**, em light e em dark.
1c. As 4 pílulas de canal têm cores **distintas entre si**.
2. **Guarda:** todos os kinds que já existem (`intercorrencia`… `transferencia_estoque`) renderizam **igual** — snapshot de 1 valor por kind antes/depois.
3. `rel` aparece após o rótulo; sem `rel`, o texto é idêntico ao de hoje.
4. `tone` troca a variante; valor fora do `Variant` = erro de tipo (`// @ts-expect-error` no teste).
5. **Ausências:** o arquivo **não** contém `fiscal:` nem `tipo-pj` nem `canal-email-bg` (chip sólido) · `ui/badge.tsx` e `resources/css/**` sem diff.
6. **Bite-test:** trocar a classe de `expired` pela de `late` deixa o teste 1b vermelho.
7. `npm run test -- statusbadge-kinds` verde · PLACAR no corpo do PR.

## 4-ter · EXECUÇÃO
- **ARQUIVOS A EDITAR:** `shared/StatusBadge.tsx` (3 entradas no `mappings` + 2 props) · **criar** `tests/js/statusbadge-kinds.test.tsx`.
- **REUSAR:** `Badge`/`badgeVariants` de `ui/badge` · o tipo `Variant` já derivado · `cn`.
- **NÃO TOCAR:** `ui/badge.tsx` · `FiscalStatusBadge` · tokens.
- **PASSO A PASSO:** 1) acrescentar os 3 kinds **no fim** do `mappings`, com docblock citando D-SB-KINDS e o bundle do DS como fonte das chaves · 2) `rel` e `tone` na `Props` e no render (os dois ramos: mapeado e fallback) · 3) testes + bite.
- **DADO:** nenhum.
- **PARAR SE:** algum token do bloco C **não** existir no `main` no turno (aí seria token novo = fora; reporte qual); ou aparecer vontade de trazer `fiscal`/`tipo`.

## PRÉ / PÓS
- **antes:** `StatusBadge.tsx` sem `sla:`/`frescor:`/`atendimento:` (conferir: 0 ocorrências de cada).
- **depois:** os 3 kinds + `rel` + `tone` · kinds antigos sem diff · `ui/badge.tsx` sem diff.
- **quebra:** se algum dos 3 kinds já existir, **não execute** — reporte e pare.

## PROVA
`StatusBadge.tsx` contém `frescor:` · não contém `tipo-pj` · teste novo existe · `_saida-05.md`.
