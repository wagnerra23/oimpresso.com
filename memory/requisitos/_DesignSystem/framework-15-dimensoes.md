# Framework 15 dimensões UX — oimpresso canon

> Canon do processo `design-deep-analysis`. Score 0-100 por dimensão + ponderação 1-3× por persona = decisão objetiva por tela.
>
> Refs: [ADR UI-0016](adr/ui/0016-design-contextualizado-por-persona.md), [Constituição UI v2](adr/ui/0013-constituicao-ui-v2-camadas.md).

## As 15 dimensões

### 1. Density (densidade de informação)
Quantos campos/registros cabem na viewport. Linear/Vercel = alta. iOS Settings = baixa. **Mede em**: items visíveis sem scroll @1280×800.

### 2. Discoverability (acha onde clicar)
Affordance visual + microcopy óbvia + posição esperada. **Mede em**: tempo de 1ª descoberta de ação primária (≤3s = bom).

### 3. Speed-to-task (cliques pra completar)
Mínimo de cliques pra completar tarefa típica. **Mede em**: contagem de cliques no golden path (sem erro).

### 4. Error recovery (volta atrás fácil)
Cancelar / desfazer / corrigir input inválido. **Mede em**: Undo disponível? Validação inline? "Cancelar" visível?

### 5. Cognitive load (sobrecarga mental)
Quantidade de decisões simultâneas. Miller's law (7±2). **Mede em**: campos visíveis + opções por dropdown + alertas simultâneos.

### 6. Aesthetic-usability effect (parece sério)
Visual polido = percebido como mais usável. Princípio Norman/Nielsen. **Mede em**: consistência tipográfica + spacing + ring focus + dark mode.

### 7. Affordance (sabe o que cada coisa faz)
Botão parece botão. Input parece input. Link parece link. **Mede em**: ambíguos vs claros (mouse hover muda cursor previsível).

### 8. Brand confidence (parece produto sério)
"Pago R$ [redacted Tier 0]/mês por isso" vs "isso parece DIY do estagiário". **Mede em**: tokens consistentes + microcopy profissional PT-BR + dark mode + erro states.

### 9. Mobile fit (cabe em 360×640)
Larissa às vezes abre no celular do balcão. **Mede em**: tap targets ≥44px, sem scroll horizontal, ações primárias acessíveis sem zoom.

### 10. A11y WCAG 2.5 (acessibilidade)
Contraste 4.5:1, focus visível, ARIA labels, navegação teclado. **Mede em**: Lighthouse audit + manual tab-through.

### 11. i18n PT-BR (português Brasil)
CNPJ/CPF/IE/UF/CEP formatos. Datas dd/mm/aaaa. R$ vírgula. **Mede em**: máscara correta + validação BR + mensagens nativas.

### 12. Performance perceived (parece rápido)
Skeleton em ≤200ms. Optimistic UI. Inertia partial reload. **Mede em**: TTFB + LCP + CLS.

### 13. Information hierarchy (hierarquia visual)
Item primário se destaca de secundário. Daniela acha valor a receber em 1 olhar. **Mede em**: contraste de peso/tamanho/cor entre níveis.

### 14. Microcopy (qualidade do texto)
Label curto sem "(opcional)" verboso. Erro com solução. Botão verbo no infinitivo. **Mede em**: skill `design:ux-copy`.

### 15. Internal consistency (consistência interna)
Mesmo padrão em telas equivalentes. Filtro de Sells = filtro de Compras = filtro de Cliente. **Mede em**: skill `design:design-system`.

## Tabela de ponderação por persona

Peso 1× = baseline. Peso 2× = importante. Peso 3× = crítico.

| Dimensão | Larissa (balconista) | Daniela (operacional) | Jair (dono Martinho) | Kamila (admin/fiscal) |
|---|---|---|---|---|
| 1. Density | 2 | 3 | 1 | 3 |
| 2. Discoverability | 3 | 2 | 2 | 2 |
| 3. Speed-to-task | 3 | 2 | 1 | 2 |
| 4. Error recovery | 3 | 2 | 1 | 3 |
| 5. Cognitive load | 3 | 2 | 1 | 2 |
| 6. Aesthetic-usability | 2 | 1 | 3 | 1 |
| 7. Affordance | 3 | 2 | 1 | 2 |
| 8. Brand confidence | 1 | 1 | 3 | 1 |
| 9. Mobile fit | 2 | 1 | 2 | 1 |
| 10. A11y WCAG | 2 | 2 | 1 | 2 |
| 11. i18n PT-BR | 3 | 3 | 2 | 3 |
| 12. Performance perceived | 2 | 2 | 1 | 1 |
| 13. Information hierarchy | 1 | 3 | 3 | 3 |
| 14. Microcopy | 3 | 2 | 1 | 2 |
| 15. Internal consistency | 1 | 2 | 2 | 2 |
| **Total ponderado max** | **34** | **30** | **25** | **29** |

### Como ler a tabela

- **Larissa** (não-técnica, balcão Rota Livre): peso 3 em Speed/Cognitive/Affordance/Microcopy/Discoverability — ela odeia "achar onde clicar"
- **Daniela** (gerente operacional Martinho): peso 3 em Density/Information hierarchy/i18n — quer ver tudo de uma vez, frota + saldo + última OS
- **Jair** (dono Martinho Caçambas): peso 3 em Brand confidence/Information hierarchy/Aesthetic-usability — paga pra "parecer empresa séria"
- **Kamila** (admin/financeiro Martinho — esposa do Jair): peso 3 em Density/Error recovery/i18n PT-BR/Information hierarchy — emite NF-e, cobra cliente, fluxo caixa — erro fiscal é caro

### Cálculo do score

```
score_ponderado_persona(tela) = Σ (score_dim × peso_dim_persona) / total_max_persona × 100
```

Tela com score < 60 ponderado por persona = refator necessário.
Tela com score 60-80 = aceitável, room pra melhoria.
Tela com score > 80 = ótimo.

## Como aplicar (resumo)

1. `/design-deep <persona>` + print da tela
2. Skill carrega persona YAML + roda design:* skills em paralelo
3. Cada dimensão recebe score 0-100 (estimado pelo critique/system/ux-copy/a11y)
4. Score ponderado total por persona
5. Top 3 dimensões críticas viram ações priorizadas
6. 3 alternativas A/B/C com diff de código preparado
7. Wagner decide → aplica → smoke prod

Detalhes operacionais no [RUNBOOK-design-deep.md](RUNBOOK-design-deep.md).

---

## Frameworks complementares canon (adicionados 2026-05-27)

O **15D** cobre análise tática de tela. Pra cobrir outros ângulos do design o canon do oimpresso agora incorpora 2 frameworks consolidados:

### A. JTBD Forces (Bob Moesta — Re-Wired Group)

Modelo de **DECISÃO de adoção/abandono** do usuário. Quatro forças competem:

| Força | Definição | Pergunta canon |
|---|---|---|
| **Push (do velho)** | Frustração com sistema atual | "O que dói no que ele usa hoje?" |
| **Pull (do novo)** | Atração pelo oimpresso | "O que o oimpresso resolve que ele queria?" |
| **Anxiety (do novo)** | Medo de trocar (vai dar certo?) | "O que ele tem MEDO se trocar?" |
| **Habit (do velho)** | Apego ao familiar | "O que ele vai sentir falta do velho?" |

**Trocar acontece quando**: `Push + Pull > Anxiety + Habit`.

Aplicação no oimpresso: análise de **conversão piloto→pago** (Larissa, Daniela) e **prevenção de churn** (oimpresso vs Bling/Tiny/Omie). Quando designar tela que afeta decisão de continuar usando, escorar via JTBD Forces — NÃO 15D.

Exemplo concreto Sells/Create:
- **Push velho**: Excel + caderno fiado, perde controle saldo cliente
- **Pull novo**: vê saldo no balcão em 1 clique
- **Anxiety**: "se quebrar o sistema, perco vendas"
- **Habit**: "Excel eu sei onde tudo está"

Decisão design Sells/Create deve **MAXIMIZAR Pull (saldo evidente, NF-e auto)** + **REDUZIR Anxiety (recovery rápido, offline mode)** + **REDUZIR Habit (atalhos espelham caderno mental)**.

### B. Nielsen Heuristics 10 (NN/g 1994)

Checklist universal de sanity. **Pre-merge UI review** — Claude valida tela contra os 10 antes de aprovar PR:

| # | Heurística | Pergunta |
|---|---|---|
| H1 | Visibility of system status | Usuário sabe o que está acontecendo agora? (loading, saved, etc) |
| H2 | Match between system and real world | Termos = linguagem do usuário (PT-BR PME, não tech) |
| H3 | User control and freedom | Saída de emergência sempre disponível (Cancelar, Voltar) |
| H4 | Consistency and standards | Mesmo padrão em telas equivalentes |
| H5 | Error prevention | Validação antes do erro (mascara CNPJ, confirma antes deletar) |
| H6 | Recognition over recall | Mostra opções (dropdown) > exige lembrar (input livre) |
| H7 | Flexibility and efficiency | Atalhos pra power user, sem complicar pra novato |
| H8 | Aesthetic and minimalist design | Cada elemento na tela compete por atenção — corte o desnecessário |
| H9 | Help users recognize, diagnose, recover from errors | Mensagem de erro explica + sugere ação corretiva |
| H10 | Help and documentation | Onde necessário, ajuda contextual em ≤1 clique |

Aplicação no oimpresso: skill `design-deep-analysis` roda Nielsen 10 como **quick sanity check** ANTES de pontuar 15D detalhado. Se reprovar em ≥3 heurísticas, sinaliza "tela tem problema fundamental, refator profundo".

### Como combinar

```
┌─ Pergunta tipo "tela X tá ruim" ─────────────────────────────────────┐
│                                                                       │
│  1. Quick check Nielsen 10 (5 min) — algum vermelho?                  │
│     SIM → refator profundo (15D + 3 alternativas)                     │
│     NÃO → continua                                                    │
│                                                                       │
│  2. 15D score ponderado por persona — score < 60? refator             │
│                                                                       │
│  3. Se tela é "porta de entrada" (Sells/Create, Cliente landing):     │
│     rodar JTBD Forces — Push/Pull/Anxiety/Habit balanceado?           │
│                                                                       │
└───────────────────────────────────────────────────────────────────────┘
```

### Refs

- Bob Moesta — "Demand-Side Sales" + "Forces of Progress" framework
- Clayton Christensen — "Competing Against Luck" (livro)
- Jakob Nielsen — "10 Usability Heuristics" (NN/g, 1994)
- Whitney Quesenbery — "5Es of Usability" (cabíveis em futuras expansões)
