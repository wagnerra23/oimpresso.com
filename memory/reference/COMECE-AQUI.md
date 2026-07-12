---
name: COMECE-AQUI — porta de entrada do oimpresso (onboarding gerado)
description: Front door pra uma IA nova (ou pro Wagner) entender o sistema. GERADO por system-map.mjs — só prompt estável + ponteiros pras fontes vivas, zero fato volátil à mão. NÃO apodrece.
type: guide
authority: generated
lifecycle: ativo
---

# 📖 COMECE AQUI — oimpresso

> ⚙️ **Gerado por `system-map.mjs`.** NÃO editar à mão (regenera). Contém ZERO fato volátil hardcoded — só o prompt estável + ponteiros pras fontes que se atualizam sozinhas. **Por isso não apodrece** (ao contrário de um doc escrito à mão).

## Pra uma IA nova entender tudo — cole numa sessão nova

```
Você vai trabalhar no oimpresso, meu ERP. Antes de qualquer coisa:
1. Rode a tool `brief-fetch` (estado consolidado do projeto). SE você NÃO tiver
   o servidor MCP conectado, PULE e leia em vez disso (fallback): o roadmap
   `memory/requisitos/_Governanca/roadmap/_ROADMAP.md` + o session log DATADO mais
   recente (arquivo `YYYY-MM-DD-*.md` em `memory/sessions/` — IGNORE README/_INDEX/
   _TEMPLATE, que um `ls -t` cru joga por cima). Nunca invente o retorno da tool.
2. As regras já carregaram via CLAUDE.md — respeite-as (multi-tenant, PT-BR,
   teste só no CT 100, aprovação humana antes de merge).
3. Leia `memory/reference/PAINEL-SISTEMA.md` — o índice GERADO do sistema
   inteiro (módulos + frescor, ADRs, ideias descartadas, o que está em aberto).
4. Pra o histórico do que já foi tentado e por que caiu, leia
   `memory/proibicoes.md` (seção "Ideias avaliadas e DESCARTADAS").

Agora, ANTES de começar, me diga em 5 bullets o que você entendeu: o que é,
como roda, quem é o cliente, o que está em voo, e uma regra que nunca pode
quebrar. Se algum bullet estiver vago, releia a fonte.
```

> O último passo força a IA a **provar** que entendeu, em vez de fingir.

## Pra auditar / revisar o sistema (2 comandos bastam)

- **`/sdd-avaliar`** — auditoria geral do processo (7 especialistas adversariais checam o estado REAL, nota 0-100 + riscos). Responde "o sistema está honesto?".
- **`/avaliar-modulo <X>`** — nota de um módulo em 9 dimensões + gaps. Responde "este módulo está bom?".
- _Mais fundo:_ `/audit-and-fix <tema>` · `capterra-senior` (vs mercado) · `design-arte` (UX) · `php artisan jana:health-check` (saúde diária).

## Estado vivo (não apodrece — é derivado)

- **36 módulos** · **342 ADRs** — detalhe + frescor no [PAINEL-SISTEMA.md](PAINEL-SISTEMA.md) (gerado junto deste).
- Estado consolidado agora: rode `brief-fetch`.
- Regras Tier 0 + o que já falhou: [proibicoes.md](../proibicoes.md).
- Como o sistema é construído: `CLAUDE.md` (carrega automático) + `memory/why-oimpresso.md` / `what-oimpresso.md` / `how-trabalhar.md`.
- Onde o CÓDIGO mora (pra mexer, não só entender): `Modules/<Vertical>` (features por vertical) · `app/Domain/Fsm` (máquina de estados de vendas/OS) · `resources/js/Pages/<Mod>/` (telas Inertia/React). Antes de criar/alterar, ABRA `Modules/Jana` · `Modules/Repair` e imite o padrão (ADR 0011). Pra criar módulo do zero, o passo-a-passo está em `memory/requisitos/Infra/RUNBOOK-criar-modulo.md`.
