# PROMPT PARA CLAUDE CODE — FINANCEIRO: O QUE FALTA APLICAR (ondas FA-1..FA-4)

> **[CC] → [CL]** · 2026-06-11 · proposta sob PROTOCOL §10.4: **valide tudo contra `origin/main` FRESCO antes** (memória do Cowork ≠ git); não cunhe número de ADR; se algo já estiver no main, não refaça — anote e siga.
> **Origem:** [W] pediu "no financeiro compare e verifique o que falta aplicar, divida em ondas". [CC] releu `@main 8f5d24b4` NESTE turno (Unificado/Index.tsx · Impostos/Index.tsx · foundations.css · cockpit.css · inertia.css · conformance-gate.mjs · os 6 css do Financeiro) e mediu os gaps. Números abaixo são MEDIDOS, não estimados.
> **URLs dos gabaritos** válidas ~1h — se expirarem, [W] pede "regenera URLs das ondas Financeiro" no Cowork.

## ✅ JÁ LANDOU no main — NÃO refazer (✓lido 2026-06-11)

| Item do pacote F2 | Evidência @main |
|---|---|
| PR-1 · US-FIN-029 3 lentes | `Unificado/Index.tsx`: `FIN_LENTES`, `LENTE_SETS`, `?lente=` clamp caixa, KPI-click→`applyLente`, chips filtrados por lente |
| PR-2 · Impostos & obrigações | `Financeiro/Impostos/Index.tsx` + charter + casos |
| PR-3 · Drawer 3 camadas | hero Camada 1 FIXA fora do scroll (L1711) · Conciliação como lente discreta (L1956) · LenteFiscal ISS/DAS (L1985) |
| PR-4 (½) · Type RAMP | `foundations.css` com `--fs-1..9` + `fontRampCheck` ratchet no `conformance-gate.mjs` (`.fontramp-baseline.json`) |
| Exportar | já existe "Exportar XLSX/PDF" no Unificado (melhor que o CSV do protótipo) |
| Novo título | `Novo.tsx` + `TituloCreateSheet.tsx` |

## ❌ O QUE FALTA — 4 ondas (1 PR cada, em ordem)

### Onda FA-1 — Completar o PR-4: §TEMPERO na fundação (a metade que NÃO landou)
`foundations.css@main` tem SÓ o ramp; `cockpit.css` e `inertia.css` não têm nenhum token de tempero (✓lido — grep `--sh-1|--atmo|--ease` = 0 nos três). A autorização [W] de 2026-06-10 ("vai" + "sim sim é isso que eu quero") cobria os DOIS blocos.
- **Adicionar em `foundations.css`** (já está no TOKEN_DEF_ALLOW do foundation-guard — sem mexer em allowlist): `--sh-1`/`--sh-2` (1 fonte de luz, par light+dark) · `--ease: cubic-bezier(.22,1,.36,1)` + `--t-1: .15s`/`--t-2: .3s` · `--atmo` (radial roxo 5% light / ~25% dark). Valores canônicos no §TEMPERO do gabarito `ds-v6/tokens.css` (URL abaixo, linhas ~85–95 light · ~141–145 dark). Par dark no seletor de tema REAL do repo (validar qual é: `.dark`/`[data-theme="dark"]`).
- **Aplicar a atmosfera no shell**: `background-image: var(--atmo)` no container do cockpit (referência: body do protótipo). Superfícies de tela NÃO podem ser sólidas opacas em cima (foi o bug `.fin-body` do protótipo — transparent).
- Registrar no PR: fundação autorizada [W] 2026-06-10 (mesma autorização do ramp).

### Onda FA-2 — Snap tipográfico do Financeiro live (314 decls medidas)
Adoção do ramp nos css do Financeiro é **zero** (`var(--fs-` = 0 nos 6 arquivos). Fora do ramp, medido @main:
| arquivo | font-size px fora do ramp |
|---|---|
| `cowork-canon-financeiro-bundle.css` | **208** (14 tamanhos distintos) |
| `fin-output.css` | 57 |
| `fin-curadoria.css` | 18 |
| `fin-ia.css` | 18 |
| `fin-cowork.css` | 12 |
| `fin-mobile.css` | 1 |
- Snap pro degrau mais próximo (`--fs-1..9`); o gabarito de COMO fica é o `financeiro.css` do protótipo (131 `var(--fs-)`, 0 fora — URL abaixo). Exceção consciente só com comentário no local.
- Ao fim: `npm run conformance:baseline:write` pra **baixar** o `.fontramp-baseline.json` (ratchet only-down — o gate já existe, esta onda materializa a descida).
- Screenshots @1280/@1440 light+dark no PR (tamanho muda layout — conferir tabela densa e KPIs).

### Onda FA-3 — Tempero APLICADO no Financeiro live (depende da FA-1)
Espelho da sessão Cowork 2026-06-10 "tempero no financeiro". Medido @main:
- **box-shadow ad-hoc → tokens**: bundle 44 · fin-output 8 · fin-cowork 1 → `var(--sh-1)` (card assentado) / `var(--sh-2)` (drawer/modal/popover/cmdk). Dark-overrides de sombra duplicados DELETAM (o par dark vive no token).
- **transition ad-hoc → tokens**: bundle 40 · fin-output 12 · fin-cowork 5 · fin-curadoria 1 → `var(--t-1)/var(--t-2)` + `var(--ease)`.
- **Medida de linha**: títulos `text-wrap: balance`; prosa/disclaimer ≤60ch `text-wrap: pretty` (ex.: disclaimer do Impostos).
- Nota L-37 do protótipo: `shadow-[var(--sh-1)]` arbitrária falhava só no Tailwind CDN do Cowork; no build real Tailwind 4 compila — mas em css de tela prefira a propriedade direta.

### Onda FA-4 — Fechamento: cor crua residual + consistência + costura
- **hex residual**: bundle 69 · fin-cowork 3 → tokens (`--pos/--neg`/semânticos do `@theme`); rodar conformance baseline write pra descer o ratchet. (oklch cru numérico no bundle é dívida congelada maior — descer o que a onda tocar, sem sweep cego.)
- **Breadcrumb consistente**: protótipo achou "voltar" VERDE cru fora da identidade + telas Fluxo/Conciliação sem o padrão de breadcrumb do módulo — conferir se o live tem o mesmo padrão de furo e unificar (token, não cor própria).
- **Costura venda→título (⚠ não-verifiquei — domínio backend)**: no protótipo, criar venda gera título no caixa via pipeline único de status. Confirmar no live que venda faturada aparece no Unificado com vínculo navegável; se não, anotar gap em CODE_NOTES (não implementar às cegas nesta onda).

## 📸 Achados no screenshot do LIVE (06-11, [W] colou o print do /financeiro/unificado dark) — entram na FA-4 (ou hotfix antes, a critério)

| # | Achado | Conserto proposto |
|---|---|---|
| FX-1 | Segmented de lente renderiza COLADO no FinSubNav → lê-se "**Caixa** · A receber · A pagar · **Caixa** · Conciliação…" — dois "Caixa" adjacentes, ambíguo | Separação visual real entre lente (filtro) e subnav (navegação): alinhar o segmented à direita do header (intenção original do `os-page-h-r`) ou gap+divisor. NÃO renomear a lente (nome é charter v14 [W]) |
| FX-2 | Hero diz "SALDO PREVISTO · **MAIO**" com a página em "**Junho** 2026" | ⚠ verificar se é filtro de período ativo; se for label stale, período do hero tem que vir da MESMA fonte do subtítulo (fonte única) |
| FX-3 | KPI A pagar: "próx. **5 jun**" — data já vencida (hoje 11/06) apresentada como "próxima" | "próx." = próxima obrigação FUTURA; vencida vira "vencida há Nd" (tom destructive) |
| FX-4 | Linha com valor "**−0,00**" (FELIPE — COMISSÃO) | Zero nunca leva sinal; formatar `brl(0)` sem sinal + investigar título zerado na origem |
| FX-5 | DeltaBadge "↓-100.0%" no PAGO R$ 0,00 (e "↑+505.8%" gigantes) | Suprimir delta quando valor=0 ou sem base comparável — ruído, não informação |

O print também CONFIRMA visualmente os gaps das ondas: fundo chapado sem `--atmo` (FA-1/FA-3) e tamanhos de letra fora do ramp (FA-2).

## URLs dos gabaritos (curl -L)

| Artefato | Serve de gabarito pra | URL |
|---|---|---|
| `ds-v6/tokens.css` | FA-1 (§TEMPERO light ~L85 · dark ~L141) | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/ds-v6/tokens.css?t=a5b8c481d5efb0dfc20348cab117a26aa87609c1dd26c99eb779a93f808b6160.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781204949.fp&direct=1 |
| `financeiro.css` (protótipo) | FA-2 + FA-3 (100% ramp · 36/36 transições em --t/--ease · sombras em --sh) | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/financeiro.css?t=22dfeba74409cf3a2c864a30329dcdc5a2055bb08c82f590fb814a465ee08510.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781204948.fp&direct=1 |
| `fin-boletos.css` (protótipo) | FA-2 (sub-telas boletos 100% ramp) | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/fin-boletos.css?t=7bfd8e2191ecd0389ca86cb2dafcb9f0b274ceea8c32102207133681cbcecf4d.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781204950.fp&direct=1 |

## Execução
```bash
git checkout main && git pull
# FA-1: branch feat/foundations-tempero        (fundação — autorização [W] 2026-06-10 no corpo do PR)
# FA-2: branch feat/fin-type-ramp-snap         (314 decls → var(--fs-N) + baseline write)
# FA-3: branch feat/fin-tempero-aplicado       (sombras/transições/medida → tokens; depende FA-1)
# FA-4: branch feat/fin-fechamento-cor-costura (hex residual + breadcrumb + verificação costura)
# Em cada: fetch gabaritos → implementar → ui:lint, stylelint, conformance (cor+ramp), pest → screenshots light+dark → PR
# CI verde + não-Tier-0 → merge autônomo. FA-1 = fundação já autorizada; citar no PR.
# Atualizar SYNC_LOG.md + CODE_NOTES.md ao fim de cada merge.
```
**Ordem:** FA-1 → FA-2 → FA-3 → FA-4.
