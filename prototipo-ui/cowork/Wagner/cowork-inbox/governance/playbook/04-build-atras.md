---
sessao: "04"
titulo: Meu build está 4 telas atrás da produção — defeito do alvo, não pedido
dono: "[CC]"
base: 0d159eb84a10
constituicao: CONSTITUICAO-COWORK.md (C1–C12)
prefixo: nenhum arquivo do main. Eventual correção é no build do Cowork.
nao_toca: resources/js/** · Modules/**
depende: — (vaga 1). Termina em decisão [W], não em PR.
---
# 04 · O protótipo está atrás

## O achado
Meu build tem **5 vistas** de governança (`governance` · `gov-politicas` · `gov-auditoria` · `gov-drift` · `gov-notas`). A produção tem **9 telas**, todas com `Inertia::render`, todas com charter:

| tela só em produção | `.tsx` | o que é |
|---|---:|---|
| `Custos` | 13.884 B | custos de IA — recebida do `Modules/Jana` em 2026-08-05 (ADR 0366 §D-B) |
| `QualidadeIa` | 20.788 B | qualidade de IA — mesma migração de dono |
| `DsRollout` | 32.411 B | plano de portar o DS em ondas + **Ledger de Conformidade** |
| `ModuleGrades/Show` | 28.806 B | dossiê da nota de um módulo (o índice eu tenho; o detalhe não) |

Pela **C4**, produção à frente **não vira pedido** — vira correção do meu lado. E há uma ironia útil aqui: o `DsRollout` nasceu de um handoff `claude.ai/design` de 2026-06-12 (está escrito no `routes.php`). Ou seja: **saiu daqui, evoluiu lá, e não voltou.** É exatamente a defasagem espelho→git que o `cowork-mirror-freshness.mjs` mede na direção contrária.

## O que esta thread produz (não é código)
Uma **ficha do §13.2 por tela**, no `_saida-04.md`, e uma recomendação. Custo bruto de trazer as 4 = **96 KB de leitura** — RECUSA por teto se feito de uma vez. Portanto:

| opção | o que custa | quando faz sentido |
|---|---|---|
| **(a) não trazer** | 0 | se essas telas nunca vão ser alvo de onda de UI. O protótipo deixa de ser espelho completo, **e isso vai escrito** no índice |
| **(b) trazer 1** | 1 vista, ~1 sessão | `ModuleGrades/Show` é a mais provável de virar alvo (é o par do índice que já tenho) |
| **(c) trazer as 4** | 4 sessões, 96 KB | só se [W] quiser o protótipo como espelho fiel das 9 |

**Recomendação técnica, não decisão:** (a) para `Custos` e `QualidadeIa` — são telas de leitura vindas do Jana, com dono e permission próprios (`jana.*`), e nenhuma onda de UI as nomeia hoje. (b) para `ModuleGrades/Show`. `DsRollout` fica pendurado na pergunta que o [W] responde: o Ledger de Conformidade ainda é assunto vivo?

## O que NÃO fazer
- **Não** desenhar as 4 telas "de memória" a partir do nome. Tela nova no protótipo sem ler a produção é inventar divergência e depois pedir que a produção se ajuste a ela — o vício que a C4 existe para impedir.
- **Não** pedir ao Code que "sincronize o protótipo": o build do Cowork é meu, não dele.
- **Não** contar isto como pendência do Code no placar.

## Checklist de saída
1. ficha por tela (as 4) · 2. recomendação por tela com custo · 3. a pergunta do `DsRollout` formulada para [W] · 4. nenhum arquivo do `main` tocado · 5. registro de que o `DsRollout` saiu de um handoff daqui e não voltou
