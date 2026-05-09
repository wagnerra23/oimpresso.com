# FAQ — Objeções avançadas B2B

> 20 objeções reais que aparecem em ciclo de venda B2B PME brasileira.
> Resposta curta (≤3 frases) + escalação se aprofundar.
> Tom: confiante, sem defensivo, sem mentir. "Não sabemos" é resposta válida quando for verdade.

---

## Segurança / LGPD

### 1. "Vocês têm certificação LGPD / ISO 27001?"

ISO 27001 ainda não. **LGPD: temos `PiiRedactor` automático em logs/PR/commits, multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093), e DPO designado.** Auditável via ADRs públicas. Quer ler nossa Constituição v2? Mando link.

### 2. "Onde fica meu dado? Servidor americano?"

**Banco principal: Hostinger Brasil (servidor BR).** Servidor de IA / cache: Proxmox em SP (próprio). **Nada vai pros EUA exceto chamadas Anthropic/OpenAI já mascaradas pelo PiiRedactor.** Se quiser self-hosted on-premise, tier Enterprise considera caso a caso.

### 3. "O que acontece se vocês quebrarem?"

**Backup do banco é seu — export SQL completo a qualquer momento, sem pedir suporte.** Código-fonte ERP é Laravel + módulos próprios; em caso extremo, contrato Enterprise prevê escrow do código com 3º.

### 4. "Posso ver quem acessou meu dado?"

Sim — log de auditoria por user + ação + IP, retenção 12 meses. Tier Pro+. Tier Starter retém 30d.

---

## Migração

### 5. "Como migra meu Bling/Conta Azul/planilha?"

**Tier Pro inclui migração guiada** (você manda CSV/export, a gente importa). Cadastros (clientes, produtos, preços), histórico de vendas últimos 12m, e contas a receber abertas. Histórico anterior fica em arquivo de consulta.

### 6. "Quanto tempo a migração demora?"

**~2 semanas pra Pro** (kick-off → sandbox → validação → cutover). Enterprise: 4-8 semanas dependendo do volume. **Rollback garantido nos primeiros 30 dias** — se não rodar, voltamos pro seu sistema atual.

### 7. "E se a migração quebrar minha operação?"

Cutover é em fim de semana. Sandbox roda paralelo 7 dias antes — você opera no antigo, validamos no novo. Quando vira, você já viu funcionando. **ROTA LIVRE migrou sem perder dia de venda.**

---

## Dependência fornecedor

### 8. "E se vocês fecharem? Fico sem ERP?"

**Banco MySQL é seu — exporta a qualquer momento.** Código é Laravel padrão, qualquer dev sênior assume. **Tier Enterprise inclui escrow** do código-fonte com 3º (custódia legal). Risco real é mais baixo que parece.

### 9. "Vou ficar refém de uma empresa pequena?"

Comparado com Bling (refém de empresa média que pode te aumentar 30% sem aviso) ou Conta Azul (já mudou de dono), **ser PME tem vantagem:** acesso direto ao dono pra escutar problema. Time de 5 pessoas, todos atendem cliente Enterprise direto.

### 10. "Vocês vão ser comprados e vão acabar."

Possível. Por isso **escrow no Enterprise** e backup SQL livre em todos os tiers. **Se for comprado, contrato segue válido pelo período pago.** Você não fica preso.

---

## Integração contador / fiscal

### 11. "Meu contador usa Domínio/Sage/Alterdata. Vai integrar?"

**Export XML NFe + CFe + relatório SPED em PDF/Excel** o contador consome em qualquer software. Integração direta API só com **Domínio Sistemas** hoje (tier Pro+). Outros: pode pedir pro contador receber por e-mail automático.

### 12. "Meu contador faz minha NFe. Pra que automação?"

**Contador faz NFe de SAÍDA SERVIÇO (NFS-e municipal) no fim do mês.** O que automatizamos é **NFe/NFC-e de produto, na hora da venda**, que cliente final pediu agora. **São coisas diferentes.** Contador continua fazendo a parte dele.

### 13. "Aceita SPED Fiscal/Contribuições?"

Geração de SPED Fiscal: tier Pro+. SPED Contribuições: roadmap Q3 2026. Hoje seu contador gera com os XMLs que exportamos.

---

## Suporte

### 14. "Que horário tem suporte?"

Pro: chat e e-mail seg-sex 8-18h, resposta <24h. Enterprise: WhatsApp dedicado 8-20h, resposta <4h, fim de semana plantão pra crítico. Tier Starter: e-mail <48h.

### 15. "E quando dá ruim sexta 18h?"

Tier Enterprise tem plantão fim de semana incluso. Pro: incidente crítico (sistema fora) atendimento WhatsApp emergência incluso, **horário comercial estendido sábado 8-12h**. Não comercializamos 24/7 — sermos PME, fingir 24/7 é mentira.

### 16. "Tem onboarding presencial?"

Enterprise: 8h on-site se for SP capital ou ABC. Fora SP: videocall + 1 visita avaliada caso a caso. Pro: 100% remoto via Meet.

---

## Funcionalidade / vertical

### 17. "Vocês fazem orçamento por m² mesmo? Lonas tipo X com acabamento Y?"

Sim — produto cadastrado com unidade m² + variantes de acabamento (ilhós, bainha, corte) já é nativo. **Cliente piloto fatura 99% do volume em produto m².** Quer ver o cadastro na tela? 5 min.

### 18. "Tenho máquinas Roland + HP + Mimaki. Integra com elas?"

Hoje **não integramos via driver direto na máquina** (RIP). Repair gerencia o **fluxo de OS** (qual peça vai pra qual máquina, quando, prazo). Driver RIP não está no roadmap — geralmente é ZUND/Onyx/Caldera + nosso ERP convive.

### 19. "Quanto eu fatura você suporta? Tô com R$ X/mês."

**ROTA LIVRE faz 99% do volume com [XX faturamento — validar].** Stack roda confortável até **R$ [redacted Tier 0]M/mês por business** sem ajuste. Acima disso, conversamos sobre tier Enterprise + arquitetura dedicada.

### 20. "Tem app mobile?"

**Não app nativo ainda. Tela responsiva mobile (PWA)** já roda hoje — você abre no Chrome do celular e funciona pra consultar OS, financeiro, Jana chat. App nativo iOS/Android está no roadmap **Q4 2026**, sem promessa.

---

## Notas de uso

- **Quando não souber: "Boa pergunta — não tenho a resposta agora, te respondo até amanhã 18h."** E cumpra.
- **Não menta sobre roadmap.** Se a feature está em "Q4 2026 sem promessa", fala assim. Cliente B2B perdoa "não temos" — não perdoa "tinha mas mentiu".
- **Use ADRs como prova.** "Posso te mandar a ADR 0093 que documenta o multi-tenant Tier 0?" — prospect técnico se impressiona com governança transparente.
- **Quando a objeção for emocional ("não confio em IA"), não argumenta com lógica.** Reconhece: "entendo, IA tem peso de marketing furado. Por isso a Jana vem desligada — você liga quando confiar."
