# CARTA DE DESIGN — [CC] · subordinada à constituição do git

> **Esta carta NÃO é lei.** A lei é, no repo `wagnerra23/oimpresso.com@main`:
> **ADR 0094 (Constituição Oimpresso V2 · 8 princípios)** + **ADR UI-0013 (Constituição UI v2)** +
> `prototipo-ui/PROTOCOL.md` + `prototipo-ui/CLAUDE_DESIGN_BRIEFING.md` + os ADRs em `memory/decisions/`.
> Esta carta só descreve **como [CC] obedece**. Onde divergir do git, **o git vence**.
> Ratificação: rebaixa a antiga "Constituição" (overstep) — ver §7. Registrada em ADR 0237 (proposta no Cowork como 0201; renumerada por colisão).

## §0 — Hierarquia (quem manda)
0. **Constituição suprema:** **ADR 0094 (Oimpresso V2)** + **ADR UI-0013 (UI v2)** — já existem no git, acima de tudo.
1. **Memória e processo:** manda o **git**. ADRs Nygard, numeração monotônica (ADR 0028), append-only.
   [CC] **lê e obedece**; **não legisla** memória.
2. **Design (F1):** cuida [CC], **dentro** dos tokens e proibições do `CLAUDE_DESIGN_BRIEFING.md`.
3. **Decisão/merge:** manda **[W] Wagner**.

## §1 — O que [CC] entrega (formato canônico)
Conforme PROTOCOL.md §4 e BRIEFING §8:
- `prototipos/<tela>/page.tsx` — React + Tailwind, mock ok. (HTML standalone = só rascunho pra [W] ver/decidir, **não** é a entrega.)
- `prototipos/<tela>/COMPARISON.md` — 15 dimensões (≥6 obrigatórias).
- `prototipos/<tela>/critique-score.json` — F1.5, score ≥80.
- [CC] **não escreve Inertia** (isso é [CL], F3).

## §2 — Tokens (não inventar paleta · BRIEFING §4/§7)
- Cores: shadcn semântico (`bg-background/primary/muted/accent/destructive/card`) + escala **warm** de status (`emerald/amber/rose/sky-50/700`). Sem opacity-color (`/10`).
- Type/space/shadow/radius/foco/animação: exatamente a tabela do BRIEFING §4. `rounded-md` default, `shadow-sm`, foco `ring-2`. Nada de `rounded-xl+`, `shadow-lg+`.
- Esqueleto: **Cockpit V2 — ADR 0110** (sidebar + header sticky + body cards + footer sticky + Sheet drawer lateral).

## §3 — Método grade (copiado do git · BRIEFING §6 + KB-9.75)
A cada tela, sempre **comparar, avaliar, buscar o melhor**:
1. **Bench** em 15 dimensões (A. Estrutura 1–8 · B. Estado da arte 9–15).
2. **Benchmark externo:** form→Stripe · list→Linear · dashboard→Vercel · inbox→Front.
3. **`critique-score.json`** com `score`, `strengths`, `weaknesses_priority` (severity+fix), `benchmark_comparable`, e o teste **`"parece feita pela Vercel?: SIM"`**.
4. Gate: **≥80 passa** · 70–79 = 1 round de refator · <70 = discussão (PROTOCOL §1.5).
5. **Evoluir é a meta** — toda tela visada mira subir a nota (KB-9.75: de 8,27 → 9,75).

## §4 — Como evoluir SEM quebrar o layout
1. **Só a partir de tokens + componentes shared** (BRIEFING §6 dim 8). Mudança vive no token → propaga segura.
2. **Aditivo, nunca redeclara.** Versão de DS é monotônica e só soma.
3. **Gate de comparação visual F1.5 — ADR 0107.** Antes/depois é obrigatório; pega regressão de layout antes do merge.
4. **Dim 5 (estados) sempre coberta:** default/hover/focus/active/disabled/loading/empty/error.
5. **Responsivo por container-query**, não por largura fixa (cura colisões tipo Financeiro <1100px).
6. **Cockpit V2 é o esqueleto fixo** (ADR 0110) — só o conteúdo do body muda.

## §5 — Ciclo auto-auditável (PROTOCOL §6)
O loop se audita sozinho via `php artisan jana:health-check`:
- `design_loop_stuck` — tela em F3 > 7 dias.
- `design_critique_skipped` — protótipo sem `critique-score.json`.
- `design_a11y_skipped` — merge sem `a11y-report.md`.
Mais: `SYNC_LOG.md` append-only + `HANDOFF.md` por fase. **Nada avança sem deixar rastro auditável.**

## §6 — Memória (git manda · ADR 0010/0027/0028)
[CC] no início **lê** STATUS (espelho local) + ADRs do git; no fim **registra** decisão como ADR e
gera a **ponte zero-toque** pro [CL] commitar. [CC] **nunca afirma** que escreveu no git.

## §7 — Correções desta sessão (auto-integração honesta)
- ❌→📝 **"Constituição acima dos ADRs"** — overstep. **Retirada.** A lei é o git; isto é carta subordinada.
- 📝 **Identidade oklch por tela** (verde/roxo/navy/indigo) — toca *"não invente paleta"* (BRIEFING §7).
  Vira **proposta F0** em `COWORK_NOTES.md`; só é lei se virar ADR aprovado por [W]. Até lá, **tokens canônicos**.
- 📝 **Cadastro = página inteira (PT-03)** — toca proibição *"modal/full-screen pra detalhe → Sheet"*.
  É **proposta** (cadastro ≠ detalhe), mas entra **pelo loop**, não por decreto. Pendente ADR.
- ✅ **Espinha/índice temático** — úteis, mas **subordinados** ao `memory/` do git, que é a fonte.
