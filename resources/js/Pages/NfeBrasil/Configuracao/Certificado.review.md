---
review_round: W31-R1
tela: /nfe-brasil/configuracao/certificado
component: resources/js/Pages/NfeBrasil/Configuracao/Certificado.tsx
charter: PRESENTE (live 2026-05-10)
reviewer: claude (W31 bulk static)
review_date: 2026-05-17
modulo: NfeBrasil
status: live
loc: 564
---

# Review estático — NfeBrasil/Configuracao/Certificado

## Cabeçalho
- US: US-NFE-041 + US-NFE-061 (charter)
- ADRs: 0029, 0093, 0094, satélite arq/0003 cert storage encrypted
- Charter `Certificado.charter.md` — 10 Non-Goals + 8 Anti-hooks + 10 Pest GUARD listados

## Pontos fortes
- Cumpre charter exemplarmente: upload + status + smoke SEFAZ + painel fiscal + toggle ambiente, tudo inline
- StatusBadge tri-estado (ok/proximo_vencimento/vencido) emerald/amber/red — alinhado com charter
- Smoke SEFAZ via `fetch` (não Inertia) com timeout — evita reload pesado
- `aria-live="polite"` no resultado smoke — accessibility
- File input limpa após upload (`fileRef.current.value = ''`) — comportamento canon charter
- Senha `autoComplete="off"` + nunca exibida após upload — Anti-hook charter §4
- Toast em todas mutações (sonner) — UX canon
- Fallback `cnpj_titular_fallback` (business.cnpj) bem sinalizado com title hover
- Toggle radio 1/2 com botão "Salvar ambiente" disabled se sem mudança
- Validação `disabled={form.processing || !form.data.certificado || !form.data.senha}`

## Riscos / gaps
1. Smoke `fetch` sem `signal: AbortController` — se user sair da tela, request continua. P3
2. `props.ambiente ?? 2` default Homologação ok, mas charter diz "toggle 1↔2" — sem case onde ambiente vem `null` real (validar Controller). P3
3. Sem ack visual após sucesso toggle ambiente — apenas toast (charter ok). P3
4. Upload `accept=".pfx,.p12"` mas user pode forçar outros — backend valida. Sem feedback client de tamanho >100KB. P2
5. Form `aplicarDefaultsRegime` não aplicável aqui (esse é tributação) — Certificado OK.
6. **Cert CNPJ titular** ausente do cert (fallback) é só warning visual — pode mascarar cert com CNPJ errado real. Charter cita "validado contra business.cnpj antes da gravação" — confirmar backend faz. P1 (não verificável estático)
7. Sem indicador de quem fez upload + quando (audit log mencionado em US-NFE-062 P1, ainda pendente). P2

## Multi-tenant
- Charter exige Pest GUARD `isolates certs by business_id (cross-tenant 404)` — checar se existe `CertificadoCharterTest.php`. Não verificado aqui.

## Recomendação
1. Adicionar `AbortController` no smoke fetch (P3)
2. Implementar US-NFE-062 (audit visual) (P2)
3. Validar tamanho do `.pfx` client-side antes do submit (P2)
