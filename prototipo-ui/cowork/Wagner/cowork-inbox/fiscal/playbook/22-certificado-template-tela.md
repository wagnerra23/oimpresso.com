---
sessao: "22"
titulo: Configurar pelo certificado — tela (4 passos em drawer)
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 11d035d368ea (2026-10-06)
prefixo: resources/js/Pages/NfeBrasil/Tributacao/Index.tsx (+ `_components/` se for o padrão) · Index.casos.md · e2e/nfe-tributacao-onboarding.spec.ts
nao_toca: Modules/ (backend é da 21)
depende: 21 · 12 (alvo medido — D-ANCORA)
decisao: _DECISOES-W-2026-10-06.md (D-SUPORTE · configuração inicial)
implementa: UC-NFTR-18
us: US-NFE-TPL-001 · protótipo `fiscal-tributacao.jsx` · `TrOnboarding`
---
# 22 · Configurar pelo certificado (tela)

Drawer lateral (PT-02, não modal de tela cheia), com 4 passos: **Certificado → Dados lidos (cada um com a fonte; divergência como escolha) → Template (sugerido + NCM padrão obrigatório) → Confirmar (resumo do que muda + aviso se já há config)**. O botão "Aplicar" só aparece no passo 4. Alvo de forma: `TrOnboarding` do protótipo, **depois** de [W] responder D-ANCORA.

## Casos de uso que esta thread implementa
| UC | Título | Prioridade | Destino |
|---|---|---|---|
| UC-NFTR-18 | Sem regime escolhido ou sem NCM válido, a tela não avança e não aplica | `must` `[fiscal]` | `Index.casos.md` |

### UC-NFTR-18 · Sem regime escolhido ou sem NCM válido, a tela não avança e não aplica · `must` `[fiscal]`
- **Destino:** `Index.casos.md`
- **Persona:** Larissa configurando sozinha
- **Aceite:** Dado regime divergente não escolhido · Então "Continuar" fica desabilitado no passo 2 · Dado NCM vazio ou `00000000` · Então desabilitado no passo 3 · Quando completo · Então o passo 4 mostra regime, template, valores e NCM, e só ali existe "Aplicar". Controle positivo: fechar o drawer em qualquer passo não grava nada.
- **Teste:** e2e `nfe-tributacao-onboarding.spec.ts` — `UC-NFTR-18 · onboarding não avança sem regime e NCM`
- **Contrato:** UC-NFTR-14..17 · `Index.charter.md` (sem modal pra detalhe; confirmação explícita)
- **Regressão que defende:** tela que "ajuda" pulando a decisão.

## Prova
e2e verde · UC-NFTR-18 no `Index.casos.md` · `design-diff` contra o alvo da 12 sem DIVERGE.

Terminou: `_saida-22.md`. Pare.
