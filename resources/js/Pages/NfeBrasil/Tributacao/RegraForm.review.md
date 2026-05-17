---
review_round: W31-R1
tela: /nfe-brasil/tributacao/regras/(create|edit)
component: resources/js/Pages/NfeBrasil/Tributacao/RegraForm.tsx
charter: AUSENTE
reviewer: claude (W31 bulk static)
review_date: 2026-05-17
modulo: NfeBrasil
status: live
loc: 285
---

# Review estático — NfeBrasil/Tributacao/RegraForm

## Cabeçalho
- US: US-NFE-010 fase 2 (Form criar/editar regra NCM)
- Dual-mode: create/edit via `regra: Regra | null`

## Pontos fortes
- `FieldDecimal` helper extrai pattern repetitivo (ICMS/PIS/COFINS/IPI + MVA/FCP)
- UF select com 27 estados constante (`UFS`)
- "UF Destino vazio = todas (Nível 3)" hint explícito
- CSOSN/CST toggle exclusive (mesma pattern ConfigDefault)
- NCM/CFOP máscara live `replace(/\D/g, '').slice(0, 8|4)`
- MVA/FCP opcionais sinalizados `(opcional)`
- Edit vs Create UI text dinâmico

## Riscos / gaps
1. **CHARTER AUSENTE** — P1
2. `form.setData('cst', '')` antes do `form[method]()` — mesma RACE do ConfigDefault (Inertia setData async). P1
3. `regra?.uf_destino ?? ''` no useForm + Select value `?? ''` — string vazia ≠ "TODAS" sentinel. Map `v === 'TODAS' ? '' : v` ok mas display inicial vazio sem placeholder se cadastrado como NULL. P2
4. Sem validação client NCM existe (8 digitos válidos NCM SH) — backend valida. P2
5. CFOP 5102 default em create — assume venda intraestadual mercadoria. Sem hint sobre CFOPs 5/6/7 (interestadual/exterior). P2
6. Sem detecção de duplicata client antes de submit — se já existe regra (NCM+UF orig+UF dest), erro vem só do backend. P2 UX
7. Sem botão "Clonar regra existente" no create — user que quer 49019900 SP→SP e SP→RJ digita tudo 2x. P3
8. Alíquotas IPI sem `*` mas Label diz `aliquota_ipi && '*'` — bug: `'aliquota_ipi' && '*'` é truthy string, sempre mostra `*`. P3
9. `tipoCodigoTributario` state local mas `useForm` data carrega ambos `csosn` e `cst` — pode submeter os dois se user toggle e ?não? trigger limpeza. Setado no submit mas race se backend rapido. P2

## Multi-tenant
- POST/PUT scoped backend. Sem cross-tenant visível.

## Recomendação
1. Fix `form.setData` race usando `form.transform(data => { ...data, csosn|cst: '' })` (P1)
2. Criar charter (P1)
3. Hint sobre CFOPs interestaduais (P2)
4. Bug `aliquota_ipi && '*'` — usar `field !== 'aliquota_ipi'` (já feito na linha 242 ConfigDefault, replicar) (P3)
