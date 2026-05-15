---
name: feedback-brave-mcp-primeiro-sempre
description: Wagner SEMPRE prefere Claude controlar Brave/Chrome MCP pra olhar UI ao vivo em vez de pedir validação humana ou deploy precoce. Não perguntar mais.
type: feedback
---

# Wagner sempre escolhe Brave MCP — não perguntar mais

**Regra:** quando precisar validar UI/visual de uma tela, **SEMPRE** usar Chrome/Brave MCP (`mcp__Claude_in_Chrome__*`) pra navegar + screenshot direto. **NUNCA** propor como alternativa "você valida manual" ou "deploy primeiro e me manda print".

**Why:** Wagner palavras textuais 2026-05-15: *"controle o brave e olhe você isso é muito mais efetivo"* — e em seguida *"a sempre, não pergunte mais"*. Loop "Claude pede pro Wagner validar → Wagner abre browser → volta com feedback" desperdiça contexto e tempo dele. Claude olhar diretamente fecha o loop dentro da mesma sessão.

**How to apply:**

1. **UI mexida em qualquer tela** (`.tsx`/`.blade.php`/CSS) → próximo passo é Chrome MCP, não confirmação Wagner
2. **Se nenhum browser conectado:** instruir conexão da extensão (1 vez) — `list_connected_browsers` retorna vazio = pedir Wagner clicar Connect na extensão Claude in Chrome no Brave
3. **Localhost dev vs Hostinger prod:**
   - Se mudança ainda não commitada/mergeada → assumir `npm run dev` rodando em localhost (porta 5173 Vite + 8000 PHP) e navegar lá
   - Se mudança já em prod → navegar `https://oimpresso.com/...`
4. **Screenshot OBRIGATÓRIO** após cada visita — `mcp__Claude_in_Chrome__navigate` + screenshot/snapshot. Reportar o que vi (não pedir Wagner pra confirmar visualmente)
5. **NÃO oferecer "(a) Brave MCP / (b) deploy primeiro"** como dilema — sempre vai pra (a)

**Não confundir com:**
- Confirmação de DEPLOY em prod ainda exige aprovação Wagner (Tier 0 publication-policy)
- O que mudou foi: validação VISUAL agora é Claude faz, não Wagner faz

**Histórico:**
- 2026-05-15 — Wagner instalou regra após sessão Caixa Unificada V4 onde Claude pediu print 3× em vez de olhar direto.
