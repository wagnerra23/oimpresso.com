# Sessão 2026-06-07 (b) — Prova viva Financeiro + inventário do arquivo + ponte pro [CL]

**Pedido [W]:** "essa seria a tela viva (Norte)?" → "quero o financeiro" → "ficou abaixo do 9.75, acabamentos fracos" → "densidade forte, identidade marcante, está opaco e [sem] frescor" → "tipografia também está ruim" → "faça o protocolo de ponte". + "inventário dos itens arquivados, classifique por reaproveitamento".

## O que foi feito
1. **Norte ≠ tela-piloto:** expliquei que `Oimpresso - Norte.html` é protótipo Cowork com CSS bespoke (`.ns-*`), o **oposto** dos primitivos — é o "antes", não a prova. Pilot real = `.tsx` no repo, 100% `Box/Stack/Inline/Grid/Text`.
2. **Prova viva Financeiro** (`Financeiro - Prova Viva (primitivos).html`) — tela composta 100% por primitivos (mocks fiéis ao contrato v2 + Tailwind via CDN configurado com os tokens REAIS do `@theme`). Evoluída em rodadas:
   - identidade roxa (hero cheio) + densidade ERP + frescor (canvas lavanda, acentos coloridos, acento de urgência nas linhas);
   - **drawer de domínio 9.75** (clique na linha): FSM stepper · conciliação OFX (±valor ±dias / sugestão IA) · fiscal (NF-e + chave + ISS/DAS) · cobrança (linha digitável + régua D+0/D+3/D+8 + 2ª via/PIX) — os 3 maiores gaps da rubrica (Cobrança 4,5 · Fiscal 5,0 · Conciliação 5,5);
   - **hierarquia em 3 camadas** (Hero → A receber/A pagar → Realizado faixa fina), matando a redundância do ageing;
   - **tipografia**: sistema `Num` (R$/centavos de-enfatizados, inteiro bold tabular, 5 tamanhos) + `Label` unificado.
3. **Bug real corrigido (verificador):** `transition-colors` em `bg-card` congela o fundo na troca de tema (CSS não re-dispara transição em mudança de var) → cards brancos no dark. Fix: `.no-trans` desliga transições durante o flip (2 frames) — cobre todos os elementos. Verificador ✅ nos 2 temas.
4. **Inventário do arquivo:** `_arquivo/INVENTARIO-REAPROVEITAMENTO.md` — ~60 itens classificados 🟢/🟡/🔴 + o que colher. Apontado pelo `INDEX.md`. TOP: **Método 9.75 Financeiro** (rubrica de domínio, composto 7,6→9,75), **Frescor verdict** (no Financeiro use vencimento+FSM, não pill), **ds-v5**, **shadcn `.tsx`** em `legado/uploads`.
5. **Ponte:** `prototipo-ui-patch/PROMPT_PARA_CODE_FINANCEIRO-PILOT-PRIMITIVOS.md` — URL do gabarito + instruções F3 (mapear primitivo vs `ui/`, gates, critério-de-pronto ADR 0253). Pré-req: refino v2 dos primitivos aplicado.

## Decisões / aprendizados
- **Frescor no Financeiro = vencimento + urgência, não pill de ageing** (veredito do arquivo confirma a v3).
- **Hierarquia > densidade pura:** denso sem 3 camadas vira ruído; o Hero precisa dominar e o "realizado" recuar.
- **Tipografia de dinheiro = sistema, não ad-hoc:** R$/centavos sempre de-enfatizados, inteiro tabular bold — consistência é o que dá acabamento.
- **L (lição):** `transition-colors` + token-var = congela no theme-flip. Padrão: desligar transição no flip (`.no-trans`), nunca transicionar a cor de fundo theme-driven.

## Residual / aberto
- A prova é mock fiel (Tailwind CDN); a prova **final** é o `.tsx` no repo nos gates ([CL], via ponte).
- `--font-mono` (Plex Mono) no `@theme` = Tier 0 [W].
- Gaps restantes da rubrica 9.75 (se [W] quiser subir mais): Caixa & Fluxo (projeção 30/60/90 + multi-conta), IA & DRE (auto-categorização + anomalia).

## Refs
- `Financeiro - Prova Viva (primitivos).html` · `_arquivo/INVENTARIO-REAPROVEITAMENTO.md` · `prototipo-ui-patch/PROMPT_PARA_CODE_FINANCEIRO-PILOT-PRIMITIVOS.md`
- Método 9.75 Financeiro (`_arquivo/exploracoes-2026-06-04/`) · ADR 0253 · `PROMPT_PARA_CODE_PRIMITIVOS-LAYOUT-V2.md`

## Próximo passo
[W] cola o PROMPT (pilot) no [CL] → [CL] faz F3 (tela `.tsx` 100% primitivos) + fecha critério-de-pronto ADR 0253. Cowork = git (D-06).
