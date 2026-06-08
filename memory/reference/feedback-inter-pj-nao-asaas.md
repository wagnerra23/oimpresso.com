---
name: Inter PJ é o caminho de cliente — Asaas fica como blueprint de referência
description: Cliente cobra via Inter PJ. Asaas fica no repo como referência educacional (driver/webhook bem documentado), mas Claude não propõe Asaas pra cliente — só Inter.
type: feedback
---
Quando cliente pedir **cobrança / boleto / PIX / extrato / receber pagamento**, default é **Inter PJ Banking** (Modules/RecurringBilling integrado via Inter API). Não propor Asaas como caminho. Asaas fica no repo como **blueprint reusável** pros próximos drivers (Bradesco, Itaú, Santander) — "todas as APIs de boleto/PIX são iguais e o Asaas é bem documentado", então ter o código Asaas ajuda quem implementa o próximo banco.

**Why:** 2026-05-14, durante pivot CYCLE-05 → CYCLE-06. Wagner explícito: *"Financeiro é o Meu do inter, podemos fazer agora · Asaas é insistência sua não minha · não gosto do Asaas · ninguém me pede · o Inter sim"*. Refinement 2026-05-15: *"deixe o Asaas parado vamos fazer, mas esperar o cliente pedir, muito bom fazer pelo Asaas porque todas são iguais e o Asaas é bem documentado então ter o Asaas ajuda a desenvolver os outros"*. Cliente piloto (ROTA LIVRE biz=4, Larissa) usa Inter. Asaas é POC histórico — fica como referência arquitetural, não como caminho ativo.

**How to apply:**
- Cliente novo pedindo cobrança → **Inter PJ** (default), não Asaas
- FIN-4 ROTA LIVRE = "atualizar cobrança ROTA LIVRE" → Inter, não Asaas
- Em retros/handoffs/SPECs, **não citar Asaas como alternativa** pra cliente. Citar Asaas como blueprint quando estiver criando novo driver banco (ex: "espelhar shape `Modules/RecurringBilling/Services/Boleto/Drivers/AsaasDriver.php` pra implementar Bradesco")
- **NÃO remover código Asaas** — fica como referência. Cliente novo pedir Asaas explicitamente é o único caso de ativar
- Inter PJ estava bloqueado lado banco com "Aplicações não existe" — destravado 2026-05-14 (Wagner com credenciais prontas)

**Não confundir com:**
- ADR canônica formal de gateway único — isso ainda não existe; é preferência forte Wagner. Se virar ADR algum dia, supersedes essa feedback.
- Remover código Asaas — explicitamente **NÃO fazer**, é blueprint educacional valioso.
