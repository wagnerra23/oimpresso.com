# Sessão 2026-06-10 (d) — Mandato [W]: reestruturação + dedup + programa ≥9

**Pedido [W]:** "estrutura espalhada, fora de padrão, custo alto pra reler, nasceu bagunçada. Vai ter que ser refeita. Retirado o custo de duplicatas. Re-analisado todo projeto com Identidade única. Não é aceitável qualidade abaixo de 9."

## O que foi feito (Fase 0 — executada e provada)
1. **Dedup: 575 arquivos de duplicata pura DELETADOS** (autorização explícita de [W]): cópia completa do projeto aninhada em `_arquivo/legado/uploads` (358) · backups integrais 05-14 (112) · **`resources/` espelhos do repo (61) — a fonte da classe de erro Regra-6** · 3 pontes processadas com snapshot integral (41) · uploads "(N)" (3). Lápide: `_arquivo/LAPIDE-DEDUP-2026-06-10.md`. Host verificado limpo (nenhuma referência viva às árvores).
2. **IT8 `arvore_proibida` no memory-health.js** (§8.2 loop erro→asserção, sem pedir): duplicata estrutural não renasce — checa `resources/`, uploads "(N)", árvores legado.
3. **Plano + programa entregues:** `Reestruturacao - Identidade Unica e Qualidade 9.html` — §2 estrutura-alvo (a lei do que pode existir) · §3 Fase 1 espinha (STATUS 48KB → núcleo ~150 linhas, log → digest) · §4 ondas W1–W4 de re-análise com régua (identidade roxo canon + probe 0 🔴 + 15-dim + estados; <9 refaz no ato).

## Decisões
- [W] decidiu: estrutura refeita · zero duplicata · identidade única · piso 9. Execução contínua SEM novo pedido (REGRA-0); 1 onda por sessão.
- Espelhos de repo locais = proibidos PARA SEMPRE (estado do repo só @main no turno).

## Erros + correção
- Nenhum novo nesta sessão; censo via run_script estourou timeout (walk ingênuo) → troquei por list_files dirigido; coberto pelo procedimento (medir com a ferramenta certa), não-mecanizável além do IT8 já criado.

## Residual (fila de execução, sem precisar de [W])
- **Fase 1 (próxima sessão):** STATUS núcleo enxuto + log→digest + MEMORY_INDEX absorve 0253/0254. Critério: espinha = 1 leitura.
- **W1:** Vendas + Oficina (probe + baseline G5 medida + estados + nota).
- **W2:** Financeiro (8.3→≥9: US-FIN-029 + fiscal) + Compras. **W3:** Clientes (indigo→roxo canon) + Produtos/Boletos. **W4:** demais rotas.
- Painel de Controle.html refrescar na Fase 1.

## Refs
`_arquivo/LAPIDE-DEDUP-2026-06-10.md` · `Reestruturacao - Identidade Unica e Qualidade 9.html` · `memory-health.js` IT8 · sessões 06-10 (a)(b)(c)
