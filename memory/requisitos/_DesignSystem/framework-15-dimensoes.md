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
"Pago R$ 200/mês por isso" vs "isso parece DIY do estagiário". **Mede em**: tokens consistentes + microcopy profissional PT-BR + dark mode + erro states.

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

| Dimensão | Larissa (balconista) | Daniela (operacional) | Martinho (dono) | Contador (futuro) |
|---|---|---|---|---|
| 1. Density | 2 | 3 | 1 | 3 |
| 2. Discoverability | 3 | 2 | 2 | 1 |
| 3. Speed-to-task | 3 | 2 | 1 | 1 |
| 4. Error recovery | 3 | 2 | 1 | 2 |
| 5. Cognitive load | 3 | 2 | 1 | 1 |
| 6. Aesthetic-usability | 2 | 1 | 3 | 1 |
| 7. Affordance | 3 | 2 | 1 | 1 |
| 8. Brand confidence | 1 | 1 | 3 | 2 |
| 9. Mobile fit | 2 | 1 | 2 | 1 |
| 10. A11y WCAG | 2 | 2 | 1 | 2 |
| 11. i18n PT-BR | 3 | 3 | 2 | 3 |
| 12. Performance perceived | 2 | 2 | 1 | 1 |
| 13. Information hierarchy | 1 | 3 | 3 | 3 |
| 14. Microcopy | 3 | 2 | 1 | 1 |
| 15. Internal consistency | 1 | 2 | 2 | 1 |
| **Total ponderado max** | **34** | **30** | **25** | **24** |

### Como ler a tabela

- **Larissa** (não-técnica, balcão): peso 3 em Speed/Cognitive/Affordance/Microcopy/Discoverability — ela odeia "achar onde clicar"
- **Daniela** (operacional oficina): peso 3 em Density/Information hierarchy/i18n — quer ver tudo de uma vez, frota + saldo + última OS
- **Martinho** (dono): peso 3 em Brand confidence/Information hierarchy — paga pra "parecer empresa séria"

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
