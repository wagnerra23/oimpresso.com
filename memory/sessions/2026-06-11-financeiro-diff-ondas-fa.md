# Sessão 2026-06-11 — Financeiro: diff protótipo×main + ondas FA-1..FA-4

## Pedido ([W])
"no financeiro compare e verifique o que falta aplicar, divida em ondas"

## O que foi feito
- Releitura @main `8f5d24b4` NESTE turno (Regra 6 / Portão 1): `Unificado/Index.tsx` (126KB, via import temporário + grep), `Impostos/Index.tsx`, `foundations.css`, `cockpit.css`, `inertia.css`, `conformance-gate.mjs`, e censo mecânico dos 6 css do Financeiro (`cowork-canon-financeiro-bundle` · `fin-cowork` · `fin-curadoria` · `fin-ia` · `fin-mobile` · `fin-output`). Espelhos importados DELETADOS no mesmo turno (IT8 árvore proibida).
- **Diff completo F2 vs main.** Ponte: `prototipo-ui-patch/PROMPT_PARA_CODE_ONDAS-FINANCEIRO-APLICAR.md` (URLs ~1h embutidas).

## Achados (✓lido @main 2026-06-11)
**JÁ LANDOU (pacote F2 inteiro de telas):** PR-1 lentes US-FIN-029 (FIN_LENTES/LENTE_SETS/?lente=/KPI-click) · PR-2 Impostos (Index+charter+casos) · PR-3 drawer 3 camadas (hero fixo L1711 · Conciliação-lente L1956 · LenteFiscal L1985) · PR-4 ½: ramp `--fs-1..9` em `foundations.css` (CRIADO — corrige memória "foundations.css não existe") + `fontRampCheck` ratchet no conformance-gate (`.fontramp-baseline.json`) · Exportar XLSX/PDF · TituloCreateSheet.

**FALTA (medido, não estimado):**
1. **§TEMPERO não landou** — `--sh-1/2 · --ease · --t-1/2 · --atmo` ausentes em foundations/cockpit/inertia (grep=0 nos 3). PR-4 aplicou só metade da autorização [W] 06-10.
2. **Ramp sem adoção no fin live**: `var(--fs-` = 0 nos 6 css; fora do ramp = bundle **208** · output 57 · curadoria 18 · ia 18 · cowork 12 · mobile 1 = **314**. Protótipo (gabarito) = 131 var(--fs-), 0 fora.
3. **Tempero não aplicado**: sombras ad-hoc 44+8+1 · transições ad-hoc 40+12+5+1 · sem atmosfera/medida.
4. **Residual**: 69+3 hex no fin css · breadcrumb fluxo/concil · costura venda→título (⚠ backend, não-verifiquei).

## Decisões
Nenhuma nova — ondas derivam de autorizações existentes ([W] 06-10 "vai" pro ramp+tempero; F2 "aprovado"). Onda FA-1 cita a autorização no PR.

## Erros + correção
- Memória local dizia "foundations.css ainda NÃO existe" (STATUS, censo 06-04) — STALE: existe desde o pacote F2/Q9. Corrigido lendo @main antes de afirmar (Regra 6 funcionou).

## Residual / Próximo passo
- **Adendo (mesmo dia):** [W] colou print do live `/financeiro/unificado` (dark) — confirma F2 no ar + gaps FA visíveis (sem --atmo, tipo fora do ramp). **5 achados novos FX-1..5 anexados ao prompt** (seção 📸): Caixa duplicado lente×subnav · hero "MAIO" vs página "Junho" (⚠ verificar filtro) · "próx. 5 jun" vencida · "−0,00" com sinal · DeltaBadge -100% em valor zero.
- [W] cola `PROMPT_PARA_CODE_ONDAS-FINANCEIRO-APLICAR.md` 1× no Code → FA-1→FA-4 (4 PRs, merge autônomo, FA-1 fundação autorizada).
- URLs expiram ~1h — regenerar sob pedido.
- oklch cru numérico do bundle (509) = dívida congelada maior; só desce no que as ondas tocarem (sem sweep cego).

## Refs
- Ponte: `prototipo-ui-patch/PROMPT_PARA_CODE_ONDAS-FINANCEIRO-APLICAR.md`
- Gabaritos: `financeiro.css` · `fin-boletos.css` · `ds-v6/tokens.css §TEMPERO`
- Sessões-mãe: `2026-06-10-tempero-no-financeiro.md` · `2026-06-10-type-ramp-ancora.md` · `2026-06-10-onda-w2-financeiro.md` · `2026-06-10-polimento-drawer-financeiro.md`
