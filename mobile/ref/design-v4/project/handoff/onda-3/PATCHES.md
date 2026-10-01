# Onda 3 — ajustes por arquivo (hex fixo e emoji)

O adaptador `erp-ui.tsx` já resolve formulários, selects, modais, toasts, cards e tabelas de **todas** as telas que o importam. O que sobra são cores fixas e emoji **dentro** das telas. Cada item abaixo é um trecho exato do `main` → o que entra no lugar.

Em toda tela que usar `palette`: `const { palette } = useOiTheme();` (`@/lib/oi-theme-context`). Status: `import { OiStatus } from "@/components/oi"` + o mapa de `@/lib/status-tones`.

## orcamentos.tsx
- L748–752 `STATUS` com `{ bg: "#…", fg: "#…", label }` → apagar; usar `statusOf(ORCAMENTO_STATUS, o.status)` e renderizar `<OiStatus label={t.label} variant={t.variant} />`.
- L493/497 `backgroundColor: "#EAF3DE"` / `color: "#27500A"` → `<OiStatus label="Aprovado" variant="ok" />`.
- L506/510 `"#FCEBEB"` / `"#791F1F"` → `<OiStatus label="Rejeitado" variant="danger" />`.
- L485 `"📄 Gerar PDF"` → `<OiBtn size="sm" leftIcon="file" label="Gerar PDF" … />`.
- L578 `× Remover` → `<OiBtn size="sm" variant="ghost" leftIcon="trash" label="Remover" … />`.
- L676 `🧠 Sugerir preços (IA)` → `<OiBtn leftIcon="zap" label="Sugerir preços com IA" … />`.
- L874, L910 `×` → `<OiIcon name="x" size={18} color={palette.textDim} />` dentro de Pressable 36×36 com `hitSlop={8}`.
- L907 `🧠 Análise IA — Precificação` → `<OiIcon name="zap" color={palette.accent} />` + texto "Análise de preço (IA)"; card externo → `<OiCard variant="hi">`.

## estoque.tsx
- L258–260 `colorForStock` (`#791F1F` / `#9A6A05` / `#27500A`) → `estoqueTone(q, min)` + `<OiStatus>`; para texto use `palette[tone === "danger" ? "danger" : tone === "warn" ? "warn" : "ok"]`.
- L285 `⚠ {lowStockCount}` → `<OiIcon name="alert" size={16} color={palette.warn} />` + texto; faixa inteira pode virar o `Toast` adaptado com `tipo="erro"` ou um OiCard.
- L382 `📊 Movimentações` → `<OiBtn size="sm" leftIcon="chart" label="Movimentações" … />`.
- L407, L593 `×` → `OiIcon name="trash"` (remover) / `name="x"` (limpar busca).

## estoque/[id].tsx
- L30–33 `entrada/saida/perda/ajuste` com hex → `MOVIMENTO_ESTOQUE` (`sign` mantido) + `<OiStatus>`.

## fiscal.tsx
- L250–255 mapa `rascunho…rejeitado` → `statusOf(FISCAL_STATUS, doc.status)` + `<OiStatus>`.
- L295 `📄 PDF` → `<OiBtn size="sm" leftIcon="file" label="DANFE" />`.
- L300 `🔄 Atualizar` → `<OiBtn size="sm" leftIcon="refresh" label="Consultar SEFAZ" />`.
- L305 `× Cancelar` → `<OiBtn size="sm" variant="ghost" label="Cancelar NF-e" />` com texto em `palette.danger`.

## pagamentos.tsx
- L114–120 mapa `pendente…falhou` → `statusOf(PAGAMENTO_STATUS, s)` + `<OiStatus>`.
- L192 `🔗 Copiar link` → `<OiBtn size="sm" leftIcon="paperclip" label="Copiar link" />`.
- L197 `🔄 Atualizar` → `<OiBtn size="sm" variant="primary" leftIcon="refresh" label="Consultar Asaas" />`.
- L202 `× Cancelar` → `<OiBtn size="sm" variant="ghost" label="Cancelar" />`.
- Referência por UUID colado: trocar o FormInput por `OiSelectField` com as OS/pedidos abertos (`useServiceOrders`, pedidos) — proposta da tela 15 do espelho.

## relatorios.tsx
- L69 `color = "#3b82f6"` → `color = palette.accent`.
- L128 `"#10b981"` → `palette.ok`; L146 `"#ef4444"` → `palette.danger`; L204 `"#0f766e"` → `palette.accent`; L498 `"#dc2626"` → `palette.danger`.
- `MetricCard` já sai como `OiKpi` pelo adaptador.

## chat.tsx
- L140 `placeholderTextColor="#9BA1A6"` → `palette.textMute`.

## mais.tsx
- L65–66 `"#8e6bd1"` / `"#d0892b"` → `palette.accent2` / `palette.warn`.

## empresas.tsx
- Imports de erp-ui e screen-container continuam funcionando pelos adaptadores. Opcional: `Alert.alert` → OiSheet de confirmação.

## Fora (de propósito)
- `login.tsx` L233 `#ffffff` — botão branco sobre a marca, correto.
- `venda-rapida.tsx` L562 `#111315` — fundo do leitor de câmera, correto. Os `×` dali são "1× a 12×", texto.
- `manutencao.tsx` L347 `✓` — trocar por `OiIcon name="check"` quando passar por lá.

## Conferir no fim
```
grep -rnE "#[0-9A-Fa-f]{6}" mobile/app | grep -v "login.tsx\|venda-rapida.tsx"   # → vazio
grep -rn "📄\|🧠\|📊\|⚠\|🔄\|🔗" mobile/app                                  # → vazio
```
