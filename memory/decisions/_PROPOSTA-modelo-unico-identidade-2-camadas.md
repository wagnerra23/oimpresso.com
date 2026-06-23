# _PROPOSTA — MODELO ÚNICO DE IDENTIDADE VISUAL (2 camadas) · ratificado por [W] 2026-06-08

> ⚖️ **Soberania:** [W] ratificou ("só deve existir um modelo", 2026-06-08). [CC] redige a proposta; **[CL] numera/versiona como ADR sob OK de [W]** — [CC] não cunha número (L-09). Mexer aqui = reindexar identidade (só [W]).
> **Supersede formalmente:** o registry de accent-por-módulo (D-02 do `_PROPOSTA-ds-harmonizacao`, já lápide) e a regra de processo "cor só por `.<tela>-scope{--accent}`" onde ela implicava **chrome** por módulo.

## Decisão (a regra única)

**Existe UM modelo de governança de cor no ERP — duas camadas, nunca N abordagens.**

### Camada 1 — CHROME (identidade) = UMA cor
- **Roxo canônico `oklch(0.55 0.15 295)`** (ADR 0190/0235). Universal.
- Aplica em: botão, foco, link, estado ativo, primary das Index.
- **Fonte única de runtime:** `app.jsx` seta `--accent` inline via tweak `accentHue` (default 295). CSS = fallback.
- **Proibido:** redefinir `--accent` por módulo (`.<scope>{--accent: verde/azul/indigo}`). Não há mais exceção de chrome por tela.

### Camada 2 — SEMÂNTICA (significado) = N tokens governados
- **Origem** (`--origin-*`): de onde a tarefa veio (Attio-style wayfinding).
- **Etapa** (`--stage-*`): pipeline kanban.
- **Status** (`--pos`/`--warn`/`--neg`): saúde do dado.
- Multi-hue **de propósito** — não dilui identidade porque NÃO é chrome. **Regra:** só destes tokens; hue inventado fora deles = erro.

### O que morre / migra
- **Accent-por-módulo MORTO.** Vendas-verde 155 · CRM/Clientes-indigo 268 · Sells-azul 220 → **ou viram `--origin-*` (semântica), ou somem.** Nunca mais `--accent` de módulo.
- O **âmbar do Oficina** permanece — porque já é `--origin-MFG` (camada 2), não `--accent` redefinido. É o padrão correto, não exceção.

### Defesa (impede regressão)
- Redefinir `--accent` fora do canon = **erro de lint** (DS-GUARD estendido + D-05 "cor crua = erro").

## Consequência
Destrava o **programa de consolidação** (Mapa de Identidade ERP - CC.html, F1–F5). Próximo passo concreto sugerido: **F2 · Sells azul 220 → roxo** (chrome errado mais visível). Cada fase é PR gated; [W] aprova o gate.

## Referências (✓ lido @main/Cowork 2026-06-08)
`ds-v6/tokens.css` L23/L45 · `styles.css` L29 · `cockpit.css` L32 · `sells-cowork.css` L37 (azul stale) · `clientes-norte.css` L10 · `compras-page.css` L4-8 (aliased) · `_PROPOSTA-ds-harmonizacao.md` (lápide) · `PROCESSO_MEMORIA_CC.md` (regra a corrigir) · ADR 0190/0235.
