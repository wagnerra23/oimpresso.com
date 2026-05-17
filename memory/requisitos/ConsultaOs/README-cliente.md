# README — Como o cliente externo usa o portal ConsultaOs

> **Audiência:** vendedores oimpresso, operadores que entregam OS a clientes finais.
> **Wave 18 RETRY (2026-05-16) — D5 cliente final canônico.**

## Em 1 frase

Cliente externo (sem login) acompanha o pipeline da OS via link público compartilhado pelo vendedor, sem expor preço, custo, margem ou dados de outras empresas (multi-tenant Tier 0).

## Fluxo cliente (jornada E2E coberta por Pest)

1. **Vendedor entrega o número da OS** ao cliente (ex: "Sua OS é 4821 — acompanhe em https://oimpresso.com/consulta-os").
2. **Cliente acessa o portal** — pagina Inertia/React (`/consulta-os`), zero auth necessária.
3. **Cliente digita o número** no campo busca (alfanumérico ≤20 chars, validado por FormRequest).
4. **API retorna payload público:**
   - `client` (nome cliente)
   - `stage` (`orcado` / `aprovacao` / `producao` / `acabamento` / `expedicao` / `entregue`)
   - `items[]` (descrição itens — sem preço)
5. **Cliente pode filtrar por estágio** — útil pra confirmar se OS já saiu pra entrega.

## O que o cliente NUNCA vê (privacidade)

- `business_id` — vazaria identidade interna da empresa atendente (ADR 0093)
- `total_final` / `valor_pago` — financeiro é interno
- `lucro` / `margem` / `custo_*` — competitividade
- CPF/CNPJ outras OS — PII (LGPD Art. 5º)
- Outras OS do mesmo cliente ou de outros clientes — isolation total

## Defesas Tier 0 ativas

- **Throttle** `throttle:30,1` (30 reqs/min/IP) — anti-DDoS + anti-enumeration
- **FormRequest** `alpha_num + max:20` — bloqueia SQL injection / XSS / path traversal
- **404 limpo** — número inexistente NÃO retorna lista de sugestões nem dica de range válido
- **PiiRedactor** no log de auditoria — IP truncado /24 (IPv4) ou /48 (IPv6), número da OS redacted via regex BR (CPF/CNPJ/email/telefone caso cliente cole no campo errado)
- **Retenção logs** 365 dias (`Config/retention.php` chave `consulta_os_logs`) — LGPD Art. 5º §II

## Status atual

- **Mock-only** até US-CONSULTA-001 substituir `MockConsultaOsRepository` por query real `Modules/Repair`
- **Numeros mock disponíveis:** 4821 (aprovacao), 4819 (orcado), 4817 (producao), 4815 (entregue)
- **Quando real entrar:** Repository resolve `business_id` via lookup do protocolo + canary 7d ROTA LIVRE antes de demais tenants

## Spans OTel

- `consultaos.busca_publica` — atributos `estagio` (sem `business_id` propositalmente — rota pública)

## Referências

- SPEC: `memory/requisitos/ConsultaOs/SPEC.md` (US-CONSULTA-001)
- ADR 0093 multi-tenant Tier 0
- ADR 0155 module-grade v3 (D5 customer journey)
- `Modules/Repair/Routes/web.php` `/repair-status` (padrão a imitar quando query real entrar)
