# PONTE → CODE · Atualizar CLAUDE.md + docs de estrutura no git (estado Cowork 2026-07-10)

**Origem:** [CC] · 2026-07-10 · [W] "gera pro Code a nova estrutura". **Natureza:** Tier 0 (toca `CLAUDE.md` canônico) → abre PR, **aguarda [W]** pro merge.
**Regra:** valide contra o `main` FRESCO antes (L-42 — não confie no que este doc diz do git; confirme). Só o que MUDOU Cowork-side está aqui; o protocolo (6 papéis × 7 fases etc.) **não muda**.

## O que mudou no protótipo Cowork nesta sessão (fatos que eu executei aqui)
1. **DS agora é LINKADO AO VIVO, não copiado.** `oimpresso.com.html` tem `<html class="cockpit">` e carrega o DS bound direto:
   - `_ds/office-impresso-design-system-019dd0…/colors_and_type.css` (fundações: neutros/radius/type/status/accent)
   - `_ds/…/cockpit_domains.css` (companion do PR #4097 — domínios origin/stage/sla/canal/kind/kpi/vip, canon verbatim do SSOT)
2. **`ds-v6/tokens.css` foi APOSENTADO.** Era o snapshot congelado que dava drift (282 vs 240). Os neutros passaram a vir do DS vivo; os ~110 nomes restantes (aliases + escalares z/s/focus + accent com tweak) foram **dobrados no fim do `styles.css`**; o arquivo `ds-v6/tokens.css` foi **deletado**. A pasta `ds-v6/` (components/showcase/backup) foi arquivada em `_arquivo/`.
3. **Origins alinhadas ao canon** (SSOT `semantic.tokens.json`, lido no turno): CRM hue 220 (não 245), chroma fg 0.10. "Canon vence" ([W]).
4. **3 mapas de handoff** na raiz, ligados ao README "🤖 COMECE AQUI":
   - `MAPA_TELAS.md` (rota→arquivo→destino repo) e `MAPA_TOKENS.md` (inventário) = **GERADOS** por `scripts/gerar-mapas-handoff.js` (não editar à mão).
   - `MAPA_COMPONENTES.md` (markup bespoke → componente DS) = curado.
   - Frescor cobrado por `memory-health.js` CHECK 9.
5. **Raiz enxuta:** arquivadas em `_arquivo/` ~59 HTML de exploração, 16 pastas espelho-`Pages/Financeiro/*` (.tsx bundler-only + charters) + `_shared`/`prototipos`, 18 jsx/css órfãos (protótipo "Norte"), 11 docs legados. Raiz = `oimpresso.com.html` + espinha `.md` + `*-page.jsx/css` + infra (`_ds`, `memory`, `scripts`, `prototipo-ui-patch`, `uploads`, `_arquivo`).
6. **Convenção retirada:** `Painel de Controle.html` — [W] "não uso" → arquivado; [CC] não refresca mais painel ao fim de sessão.
7. **Lições novas** (em `memory/LICOES_CC.md`): **L-42** (ponte de token = diff contra SSOT, nunca ausência presumida de espelho local) · **L-43** (artefato derivado = gerado por máquina + frescor, nunca cópia à mão).

## Pedido ao [CL]
Atualizar no `main`, validando contra o repo:
- **`CLAUDE.md`** (e docs de estrutura equivalentes): a seção que diz *"FONTE ÚNICA de tokens = `ds-v6/tokens.css`"* / *"`ds-v6/` é a pasta do DS"* está **stale** → o DS é o bound vivo (`_ds/…` colors_and_type + cockpit_domains); ds-v6 aposentado/dobrado. A menção a *"`.html` legados na raiz = histórico"* → agora estão em `_arquivo/`.
- Registrar o **índice de handoff** = `MAPA_TELAS/COMPONENTES/TOKENS` (via README "COMECE AQUI").
- (Opcional) refletir a retirada da convenção do Painel.

**NÃO** commitar afirmando — abre PR, aguarda [W]. Retorno em `CODE_NOTES.md`.
