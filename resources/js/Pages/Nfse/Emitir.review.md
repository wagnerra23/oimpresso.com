---
review_round: W31-R1
tela: /nfse/emitir
component: resources/js/Pages/NFSe/Emitir.tsx
charter: AUSENTE
reviewer: claude (W31 bulk static)
review_date: 2026-05-17
modulo: NFSe
status: live
loc: 401
---

# Review estático — NFSe/Emitir

## Cabeçalho
- US: US-NFSE-009
- Permissão: `nfse.emit`
- Stack: Inertia `useForm` + AppShellV2 + shadcn UI + lucide-react
- Smoke real: rota `POST /nfse/emitir` direto (sem mock visível)

## Pontos fortes
- Tomador opcional CNPJ/CPF + email (PF/PJ flex)
- Resumo financeiro live (valor serviços / ISS / líquido) re-calcula on change — ótimo feedback
- Toggle ISS retido na fonte
- Hint LC 116/2003 com exemplos práticos (1.05/1.07)
- Hidden `transaction_id` quando vínculo de venda → pre-fill correto
- Painel aside "Venda vinculada" — UX comprovada (padrão Show)
- Botão Submit disabled quando `semConfig` ou `certAlerta` — fail-safe
- Banner amarelo claro pra cert ausente/expirado com instrução `php artisan nfse:importar-cert`

## Riscos / gaps
1. **CHARTER AUSENTE** — viola MWART F3 (ADR 0104). Hook `block-mwart-violation.ps1` deveria bloquear; tela já live, então provavelmente foi adicionada antes do hook. P1
2. CPF/CNPJ sem máscara client-side — usuário digita raw, validação backend (FormRequest). UX inferior a Show (formatCnpj). P2
3. `parseFloat(data.valor_servicos)` sem locale BR (vírgula→ponto) — pode quebrar se Larissa colar `1.234,56`. P1
4. Alíquota ISS aceita decimal puro (0.05), mas user pode digitar `5` por engano → ISS 500% silencioso. Sem validação `< 0.20`. P1
5. `competencia` recebe `transaction_date` (formato YYYY-MM-DD) mas input é `type=month` (YYYY-MM) — possível mismatch. P2
6. Sem `defer` em config/venda — Controller assumido eager-load. Se `montarPainelFiscal` for caro, p95 sofre. P2 (não detectável estático)
7. Sem confirm dialog antes de Submit em produção — emissão NFSe é IRREVERSÍVEL (gera número fiscal). P0
8. `flash` exibido como banner inline + sem auto-dismiss; padrão canon = `toast` sonner. P2

## Multi-tenant
- Render assume backend já scopou `config.business_id` (não detectável aqui — checar Controller). Aside venda só renderiza se backend resolver `Venda` cross-tenant safe.

## Recomendação
1. Criar charter `Emitir.charter.md` (P1)
2. Adicionar confirm dialog antes de Submit em ambiente=producao (P0)
3. Validar alíquota ISS no client (max 0.20) (P1)
4. Padronizar flash → toast (P2)
