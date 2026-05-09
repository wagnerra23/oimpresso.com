---
name: Não anotar "cliente principal" em CLAUDE.md
description: Wagner não quer rastreio de clientes/personas no CLAUDE.md — é noise pra trabalho de programação
type: feedback
originSessionId: e1324d13-7148-4faa-9bee-1d5fbcc6286e
---
Não escrever em CLAUDE.md (ou em notas de contexto de programação) linhas tipo:
- "Cliente principal de produção: X (business_id=N, Pessoa)"
- "Cliente PontoWr2: Y (email)"
- "Empresa 4 / Larissa fora dos testes ativos"

**Why:** Wagner reagiu mal em 2026-04-30 quando adicionei essas linhas. CLAUDE.md é primer técnico pra programar, não CRM. Empresas/pessoas são clientes — não devem aparecer como filtro de prioridade ou "fora dos testes". Quando Wagner pede pra focar em X, ele quer código, não bureaucracia de quem está dentro/fora.

**How to apply:** Quando Wagner mudar foco de Cycle ("foco em MCP", "Jana em espera"), registrar o foco técnico em CURRENT.md (que é estado vivo do cycle) e, se for muito permanente, uma linha curta em CLAUDE.md sobre o foco — sem nomear clientes, sem "fora dos testes", sem listas de pessoas. Se precisar referenciar business_id pra teste, tudo bem citar o número, mas não pendurar nome de cliente como filtro.

Cliente principal real (quando relevante tecnicamente): WR Comercial e Wagner — mas mesmo isso não vai em CLAUDE.md sem motivo técnico claro.
