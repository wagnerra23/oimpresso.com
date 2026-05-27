---
slug: 0195-tabs-autosave-mount-sempre-hidden
number: 195
title: "Tabs com autosave/state user-editável ficam mount-sempre (hidden via CSS) — render-condicional só pra tabs read-only"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-27"
module: core
quarter: 2026-Q2
tags: [drawer-760, tabs, autosave, react-state, ux, debounce, persona-larissa, ADR-0179-cliente-drawer, ADR-0185-drawer-canon]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - "0179-cliente-drawer-760px-substitui-show-fullpage"
  - "0185-drawer-760-canon-entidades-cadastrais"
  - "0094-constituicao-v2-7-camadas-8-principios"
pii: false
review_triggers:
  - "Custo de render inicial (5 tabs mount simultâneos) virar gargalo perceptível em monitor médio → considerar lazy-on-first-visit + keep-mounted"
  - "Algum tab cadastral disparar fetch pesado no mount (BrasilAPI/SEFAZ/IA) → mover fetch pra on-visible em vez de on-mount"
  - "Tab read-only ganhar state user-editável → promover pra mount-sempre"
---

# ADR 0195 — Tabs com autosave/state user-editável ficam mount-sempre (hidden via CSS)

## Contexto

[ADR 0179](0179-cliente-drawer-760px-substitui-show-fullpage.md) + [ADR 0185](0185-drawer-760-canon-entidades-cadastrais.md) estabelecem o **Drawer 760** como pattern canônico pra entidades cadastrais (Cliente piloto, escalando pra Fornecedor/Produto/Veículo/Ordem de Serviço/Plano/Equipamento). Cada drawer tem múltiplas tabs (Cliente: 8 — Identificação · Contato · Endereço · Comercial · Classificação · OSs · IA · Auditoria), com **autosave on blur** (debounce 800ms) nas tabs cadastrais.

Implementação inicial em `Pages/Cliente/Index.tsx` (ClienteSheet) usava **render-condicional**:

```tsx
{activeTab === 'endereco' && <EnderecoTab contact={contact} />}
```

Wagner 2026-05-27 reportou bug em prod (biz=4 Larissa @ ROTA LIVRE): **"fui testar e ainda perde informações quando troca de aba"**. Reprodução:
1. User digita CEP/número/email num campo
2. Troca de aba antes do debounce 800ms disparar
3. Componente da aba antiga **desmonta** (render-condicional false) → state local perdido + cleanup `useEffect` faz `clearTimeout` em todos os debounces pendentes
4. User volta pra aba → componente remonta → `useState` inicial pega `contact.*` antigo do payload → valor digitado **sumiu silenciosamente sem nunca ter chegado ao servidor**

Mesmo após [fix payload completo no `rows`](../../app/Http/Controllers/ContactController.php) (commit 2026-05-27 desta sessão), o problema persistia porque o mount/unmount do componente é o que perde o state, não o payload.

Alternativas avaliadas:

| Opção | Trade-off |
|---|---|
| **A**: Render-condicional + flush síncrono no cleanup | Refactor por tab — tem que rastrear `currentValue` de cada field em `useRef` pra disparar `performSave` no unmount. Race condition fetch-async vs unmount. |
| **B**: State elevado pro pai (ClienteSheet via Context/useReducer) | Refactor pesado em 5 tabs + 30+ campos. Quebra encapsulamento. |
| **C**: `useImperativeHandle` + `tabRef.current.flush()` chamado pelo pai antes de mudar aba | Funciona mas adiciona acoplamento pai↔filho. |
| **D**: Manter tabs mounted sempre, esconder via `hidden` quando inativas | 1 mudança no pai. State preservado naturalmente. Blur natural do input dispara autosave síncrono ao trocar aba (input perde foco antes do `setActiveTab` re-render). |

## Decisão

**Tabs com autosave ou state user-editável: SEMPRE montadas, escondidas via `hidden={activeTab !== 'X'}`.**

**Tabs read-only (consulta, sem state editável): render-condicional OK** (desmontar economiza memória sem custo de UX).

Implementação padrão (referência canônica: [Pages/Cliente/Index.tsx:1903-1942](../../resources/js/Pages/Cliente/Index.tsx)):

```tsx
{contact && (
  <>
    {/* Tabs cadastrais com autosave — MOUNTED SEMPRE */}
    <div hidden={activeTab !== 'identificacao'}>
      <IdentificacaoTab contact={contact} ... />
    </div>
    <div hidden={activeTab !== 'contato'}>
      <ContatoTab contact={contact} />
    </div>
    <div hidden={activeTab !== 'endereco'}>
      <EnderecoTab contact={contact} />
    </div>
    {/* ... outras tabs cadastrais ... */}

    {/* Tabs read-only — render-condicional OK */}
    {activeTab === 'oss' && <OssTab contact={...} />}
    {activeTab === 'ia' && <IATab contact={...} />}
    {activeTab === 'auditoria' && <AuditoriaTab contact={...} />}
  </>
)}
```

### Matriz de classificação

| Tipo de tab | Padrão | Por quê |
|---|---|---|
| Form com autosave (debounce on change + blur) | **mount-sempre** | State local + debounce timers morrem no unmount |
| Form sem autosave (botão Salvar explícito) | **mount-sempre** | Form dirty perdido no unmount = re-trabalho do user |
| Lista/leitura com filtros locais (search, sort) | **mount-sempre** | Filtros aplicados perdidos no unmount |
| Lista/leitura pura (sem state user) | **render-condicional OK** | Re-fetch do servidor é fonte da verdade |
| Tab pesada (gráficos AI, timeline ledger) | **render-condicional OK** | Custo de render alto, sem state a preservar |

## Justificativa

- **UX**: Padrão Notion/Linear/Figma — trocar de aba **nunca** descarta trabalho do usuário. Persona Larissa @ biz=4 (não-técnica, 1280×1024) não tem como suspeitar que trocar de aba apaga rascunho.
- **Simplicidade**: 1 linha de mudança (`{x && <Y/>}` → `<div hidden>...`) cobre o caso 95% sem refactor de cada tab.
- **Blur natural funciona**: input ativo perde foco quando user clica na nova aba — `handleBlur` dispara `clearTimeout(debounce)` + `performSave` síncrono ANTES do `setActiveTab` re-render. Autosave on blur já estava implementado em cada tab; só faltava o componente não desmontar.
- **Custo render aceitável pros 5 tabs cadastrais**: forms são leves (sem queries pesadas no mount). IA/OSs/Auditoria continuam lazy.

## Consequências

**Positivas:**
- State local + debounces pendentes preservados ao trocar aba — zero perda silenciosa
- Blur natural cobre 100% dos casos sem refactor por tab
- Pattern aplicável a outras telas com drawer multi-tab (Fornecedor/Produto/Veículo/OS/Plano/Equipamento conforme ADR 0185)

**Negativas / Trade-offs:**
- Mount inicial do drawer renderiza N tabs simultâneas (custo 1× ao abrir). Mitigação: tabs cadastrais são forms leves; revisitar se a abertura virar perceptivelmente lenta.
- Bundle inicial do drawer carrega código de todas as tabs cadastrais mesmo se user só visitar 1. Aceitável — não vale code-splitting pra esse volume.
- Se um tab cadastral disparar fetch no mount (ex: BrasilAPI autocomplete on-mount), agora dispara mesmo se a aba nunca for visitada. Mitigar movendo fetch pra on-visible (`useEffect` com `activeTab === 'X'` gate) ou on-blur do campo gatilho.

**Riscos mitigados:**
- Usuária Larissa perdendo cadastro silenciosamente (regressão de confiança no produto)
- Tickets de suporte tipo "salvei e sumiu" sem reprodução fácil pra time

## Quando aplicar

Em **qualquer tela** do oimpresso que tenha:
1. Componente container com múltiplas tabs/seções (Drawer 760, Sheet, Modal multi-step)
2. Alguma tab/seção com input user-editável que persiste via autosave (debounce) OU botão Salvar explícito
3. Render-condicional desmontando ao trocar de tab/seção

**Telas atuais elegíveis** (a auditar conforme ADR 0185 escala):
- `Pages/Cliente/Index.tsx` — **piloto** (aplicado 2026-05-27)
- `Pages/Fornecedores/*` (quando entrar em drawer 760)
- `Pages/Produto/*` (idem)
- `Pages/OficinaAuto/Vehicles/*`
- `Pages/OficinaAuto/ServiceOrders/*`
- `Pages/RecurringBilling/Planos/*`
- `Pages/Repair/DeviceModels/*`
- `Pages/Sells/Create.tsx` — pills de seção fazem `scrollIntoView` (não desmontam), não aplica
- Qualquer Sheet/Drawer multi-tab futuro

## Não aplicar quando

- Tab é **read-only puro** (consulta sem state editável) — render-condicional economiza memória
- Tab dispara query pesada no mount (gráficos, timeline ledger longo, AI brief) — render-condicional + skeleton de loading é melhor
- Tela é **wizard sequencial** (step 1 → 2 → 3 linear, sem volta) — neste caso re-mount é semanticamente correto

## Referências

- [ADR 0179](0179-cliente-drawer-760px-substitui-show-fullpage.md) — Drawer 760 substitui Show.tsx full-page (Cliente piloto)
- [ADR 0185](0185-drawer-760-canon-entidades-cadastrais.md) — Drawer 760 escala pra entidades cadastrais do projeto
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Princípio "Loop fechado por métrica" + "Confiabilidade com fallback"
- Implementação canônica: [Pages/Cliente/Index.tsx:1903-1942](../../resources/js/Pages/Cliente/Index.tsx)
- Sessão de origem: `frosty-greider-83ab2f` 2026-05-27 — bugs CNPJ-apaga-endereço + CEP-fecha-tela + troca-de-aba-perde-dados
