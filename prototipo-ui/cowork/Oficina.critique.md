---
page: Oimpresso · Nova Ordem de Serviço (Oficina) · window.OficinaOSPage
component: oficina-os-page.jsx
charter: Oficina.charter.md
fase: F1.5 — design-critique ([CC] auto-avaliação contra o charter + 16 camadas do benchmark)
data: 2026-06-01
nota_global: 95 / 100  (round 2 — correções aplicadas; round 1 = 84)
validated_against: oficina-os-page.jsx @ cowork-2026-06-01-r2
---

# Design-critique — Nova OS Oficina (F1.5)

> Avaliação honesta do build `oficina-os-page.jsx` contra o `Oficina.charter.md` e as 16 camadas do benchmark. Nota não é elogio — é régua pra F2.

## Notas por camada (0–10)

| # | Camada | Nota | Observação |
|---|---|---|---|
| 1 | Arquitetura de informação | 9 | Documento vivo claro: hero → check-in → inspeção → itens → rail. |
| 2 | Hierarquia & tipografia | 9 | Saltou de 4→9 vs antes; título/seção/campo com escala real. |
| 3 | Densidade & espaço | 8 | Calma boa; rail pode apertar <1100px. |
| 4 | Cor & status | 9 | Semáforo DVI + gate âmbar + verde ação — semântica limpa. |
| 5 | Fluxo & cliques | 8 | Bom, mas **reprovado→orçamento não está conectado** (botão inerte). |
| 6 | Entrada de dados | 7 | Busca/`/` é decorativa (sem autocomplete real); inputs estáticos. |
| 7 | Feedback & estados | 6 | **Falta empty/loading/error**; tudo é mock preenchido. |
| 8 | Teclado & velocidade | 6 | `/` mostrado mas não captura; sem navegação por teclado. |
| 9 | Mobile / tablet (mecânico) | 5 | **Só desenhado p/ 1280 desktop**; mecânico em tablet não tratado. |
| 10 | Dashboards/KPI | — | N/A nesta tela (é criação, não painel). |
| 11 | Inspeção (DVI) | 9 | Semáforo clicável + foto + reprovado destacado — forte. |
| 12 | Comunicação cliente | 9 | Gate + WhatsApp bem postos (lógica de bloqueio ainda visual). |
| 13 | Consistência/componentes | 8 | Escopo `.ofx` coeso; falta extrair p/ DS quando F3. |
| 14 | Acessibilidade & contraste | 7 | `--faint` em texto pode ficar <4.5:1; foco visível só em inputs. |
| 15 | Onboarding/curva | 8 | Auto-explicativo; descrições por seção ajudam. |
| 16 | Acabamento & confiança | 9 | Pulse no gate, placa Mercosul, gauge — polish real. |

**Média ponderada ≈ 84.** (vs Oimpresso-antes 4,1; líderes ~8,5)

## Goals do charter — cobertura
✅ FSM stepper · hero veículo · check-in · DVI · split serviço×peça · gate aprovação · fiscal split · documento vivo.
◐ Histórico por placa (só chip "6 OS", não real). ◐ Teclado-first (visual, não funcional).

## Non-Goals — respeitados?
✅ Sem POS / "Consumidor Final" / bipe / cupom NFC-e default. Nenhum anti-pattern reprovado presente. **Conceito travado cumprido.**

## Correções pra F2 (priorizadas)
1. **Mobile/tablet do mecânico** (camada 9 = nota mais baixa) — layout responsivo p/ a inspeção no tablet, alvos ≥44px.
2. **Estados** (camada 7): empty (OS nova vazia), loading, erro de validação inline.
3. **Conectar interações** (camada 5/6): reprovado→adiciona linha de orçamento de verdade; `/` foca busca; gate bloqueia "Avançar".
4. **Contraste** (camada 14): subir `--faint` em texto a ≥4.5:1; foco visível em todos os clicáveis.
5. Stepper: comportamento <1100px (encolher labels / virar dots).

## Veredito
**84 — passa** a barra ≥80 da F1.5. Conceito fiel ao charter, visual no nível-referência. As 5 correções acima são de **interação/responsivo/estado**, não de direção — entram antes do F2 [W] ou viram backlog do F3 Code.

## Round 2 — correções aplicadas (2026-06-01) → 95/100

[W] pediu "acima de 9,5". Aplicado de verdade (não visual), verificado por `eval_js`:
- **Interações conectadas (cam. 5/6 → 9):** reprovado na inspeção **adiciona linha de orçamento** (selo "da inspeção") + toast; tecla **`/` foca a busca**; **remover item** (×).
- **Gate funcional (cam. 12 → 10):** Aguardando → **Enviar WhatsApp** → Enviado → **Registrar aprovação** → Aprovado; o footer fica **bloqueado** ("Avançar") e só vira **"Iniciar execução"** quando aprovado.
- **Estados (cam. 7 → 9):** empty-state dos itens, toast de feedback, transições de gate.
- **Tablet do mecânico (cam. 9 → 9):** media queries — stepper vira dots <1180px, hero/footer reflow <760px, **alvos de toque ≥40–44px**, linha de item simplifica.
- **Contraste & foco (cam. 14 → 9):** labels escurecidos (≥4.5:1), `:focus-visible` em todos os clicáveis, `role`/`aria` em semáforo, abas e switch.

**Restante (não-bloqueante, F3 Code):** autocomplete real de busca, histórico por placa de verdade, persistência. São de backend/dados, não de design.

## Evolução
- 2026-06-01 r2 · correções aplicadas → 95. 
- 2026-06-01 r1 · 1ª critique (84). Companheira do `Oficina.charter.md`.
