---
date: "2026-06-19"
time: "1705 BRT"
slug: "estacao-design-governanca-fix-dark"
tldr: "Estação de ingestão de design (handoff Cowork→prototipos/<tela>/) provada em prod: diff sobre roteados sem git + extras inteligentes (#3041); cowork-map v2 por prefixo, 18 telas (#3042). ADR 0293: governança da decisão de design (responsável + Decision Register + ledger de retorno pro Cowork) (#3043). Fix dark da Caixa: toggle sincroniza data-theme (html+.cockpit) → flipa sem F5 + thread usa token (#3044). Tudo MERGED; falta deploy do #3044."
decided_by: [W]
cycle: "CYCLE-08"
prs: [3039, 3040, 3041, 3042, 3043, 3044]
us: []
next_steps:
  - "DEPLOY do #3044 (dark sem F5 só vale em prod após build/deploy) → confirmar o toggle + revalidar o dark da thread no buildado."
  - "Curadoria adversarial do Financeiro (unificado/dre/fluxo/contas/conciliacao/extrato... = 1 tela 'Financeiro Unificado' com abas ou N telas?)."
  - "Roteamento por subpasta: as 69 .tsx (2ª geração financeira, em Unificado/ Dre/ etc) que o route() por-basename não mapeia — estender o roteador ou usar globs por path."
  - "Etapas 4-6 do ciclo (ADR 0270): aplicar na vida real com rastro + usar o ledger governance/design-requests no fluxo real (modelo ADR 0293)."
related_adrs:
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0293-governanca-decisao-design-responsavel-registro-veredito
  - 0291-distiller-modulo-verdade-contrato-emenda-0270-f3
  - 0281-dark-mode-bridge-data-theme-tokens
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0130-handoff-append-only-mcp-first
---

# Handoff 2026-06-19 17:05 BRT — estação de ingestão de design + governança + fix dark Caixa

## O que foi feito (tudo MERGED em main)

- **Estação ingere o handoff Cowork** (design → fonte `prototipos/<tela>/`):
  - #3039 corrige 4 furos da fiação (diff por conteúdo sem git; slug de tela aninhada; pasta via cowork-map; page_id em cadeia). #3040 registra a caixa no map.
  - #3041 — o comando faz **diff sobre os arquivos roteados** (basename do destino), não o dump bruto → o handoff aninhado (`project/inbox-page.jsx`) casa a baseline flat; + **extras inteligentes** (`classifyExtras`: "de outra tela conhecida" agregado vs "desconhecido" listado).
  - #3042 — **cowork-map v2**: glob por **prefixo** (`inbox-*`, `vendas-*`), data files explícitos, 18 telas + buckets `_handoff-*` (docs/assets/tsx/shared → `prototipo-ui/cowork-handoff/`). Bloco oficina/produção curado por adversário (Tese C: producao-oficina + oficina-os + comunicacao-visual + norte; `prod-*`→produto).
  - **Provado no CT100** com o handoff real: ingerir a caixa → **6 roteados, diff pega 3 modificados, 1105 agregados, 18 a avaliar** (legítimos).

- **Governança (ADR 0293, #3043):** matriz **responsável-por-etapa** (cor/identidade/dark/DS = Tier-0 [W]; mecânico = [CC]; gate = automático) + **Decision Register** `<tela>.decisoes.md` + **ledger** `governance/design-requests/` (retorno do rejeitado pro Cowork). 1º veredito registrado: dark da caixa.

- **Fix dark da Caixa (#3044):** bug reportado por [W] = "ao trocar o tema, o fundo não muda sem F5". Causa (verificada no DOM em prod): `useTheme.applyClass` só mexia na classe `.dark` do `<html>`, mas o `data-theme` mora em **dois** lugares (`<html>` anti-flash + `.cockpit` prop server-side) e o `dark:` do Tailwind é OR. Fix: `applyClass` sincroniza o `data-theme` nos dois. + `ConversationThreadV4.tsx`: fundo `bg-[oklch(0.97 0.013 145)]` (cor crua arbitrária) → `bg-muted/30` (token que flipa). Validado em typecheck + lógica (DOM).

## Aberto (próxima sessão — nada urgente/quebrado)

1. **DEPLOY do #3044** → o dark sem F5 só vale em prod depois do build/deploy. Depois: confirmar o toggle (sem F5) + revalidar o dark da thread no buildado.
2. **Curadoria adversarial do Financeiro** — a maior união de telas não resolvida.
3. **Roteamento por subpasta** — as 69 `.tsx` (2ª geração financeira) que o route() por-basename não pega.
4. **Etapas 4-6 do ciclo** (ADR 0270): aplicar na vida real com rastro + usar o ledger de retorno na prática.

## Lição da sessão (importante)

**Verificar no navegador (abrir prod, togglar, medir o DOM) ANTES de afirmar estado de tela — não inferir de `grep`.** [W] corrigiu 2× ("já testou antes de falar?"); o diagnóstico real do dark só apareceu testando no DOM ao vivo. E: não manipular o DOM da aba de produção do usuário ao vivo (deixa estado inconsistente — causou um "travamento" do toggle que um reload resolveu).
