---
sessao: "29"
titulo: Natureza ICMS × ISS por produto e DIFAL do Simples por UF
dono: "[CL]"
base: wagnerra23/oimpresso.com@main (2026-10-06)
prefixo: Modules/NfeBrasil/Database/Migrations/<natureza_fiscal + decidido_por em produto · difal_simples em tabela UF> · Tests
depende: 07 (versões) · 15 (aceite) · D-NATUREZA e D-DIFAL-UF respondidas [W] 2026-10-06
decisao: _DECISOES-W-2026-10-06.md (respondidas: D-NATUREZA · D-DIFAL-UF · D-FECHAMENTO · D-REJEITADA)
---
# 29 · Natureza ICMS × ISS por produto e DIFAL do Simples por UF

Dois casos difíceis em que a lei não dá resposta única, e o sistema **não decide sozinho**:
1. **Banner, placa, adesivo personalizado: ICMS ou ISS?** A SEFAZ-SP trata como industrialização (ICMS); a Súmula 156 do STJ aponta ISS para composição gráfica personalizada. O produto ganha `natureza_fiscal` (mercadoria | serviço | pendente) decidida pelo contador, com fonte e data. Pendente: emite pelo padrão da UF e entra na Saúde fiscal.
2. **DIFAL do Simples para consumidor de outra UF:** por padrão não se recolhe (STF ADI 5.464); a UF que cobra por lei própria (STF Tema 1284) é marcada pelo contador na tabela por estado. Marcada → a nota gera o DIFAL.

## Fontes (lidas pelo [CC] em 2026-10-06; o Code cita a lei literal no docblock)
- SEFAZ-SP, Consulta 10406/2016: placas, banners e totens por encomenda = industrialização, ICMS.
- STJ Súmula 156: composição gráfica personalizada sob encomenda = só ISS.
- SEFAZ-SP, Consulta 32359/2025: Simples sem obrigação de DIFAL a não contribuinte de outra UF.
- STF Tema 1284: cobrança possível se prevista em lei estadual (fonte secundária: jettax, 2026).

## Casos de uso
**Números provisórios:** confirmar o próximo livre no turno.

### R-NFE-034 · Natureza decidida pelo contador vale para os produtos iguais · `must` `[fiscal]`
- **Destino:** `Index.casos.md` (NfeBrasil/Tributacao) ou SPEC do módulo
- **Aceite:** Dado banner com natureza pendente · Então emite pelo padrão da UF e a Saúde fiscal mostra "natureza a decidir". Quando o contador decide "serviço 24.01" · Então o produto passa a NFS-e e a decisão fica com autor, data e fonte. A Jana nunca grava natureza. Controle positivo: produto já decidido não volta pra fila.
- **Teste:** `NaturezaFiscalTest` — `R-NFE-034`
- **Contrato:** Súmula STJ 156 · SEFAZ-SP Consulta 10406/2016
- **Regressão que defende:** a IA escolhendo imposto em caso controverso.

### R-NFE-035 · DIFAL do Simples só nas UFs marcadas · `must` `[fiscal]`
- **Destino:** `Index.casos.md` (NfeBrasil/Tributacao) ou SPEC do módulo
- **Aceite:** Dado Simples vendendo a consumidor no RJ · Quando RJ não está marcado · Então sem DIFAL e aviso citando ADI 5.464 (bateria C17). Quando o contador marca RJ · Então a nota gera DIFAL e guia. Controle positivo: venda a contribuinte nunca gera DIFAL do remetente.
- **Teste:** `MotorTributarioServiceTest` — `R-NFE-035`
- **Contrato:** STF ADI 5.464 · STF Tema 1284 · LC 190/2022
- **Regressão que defende:** Simples pagando DIFAL que não deve, ou deixando de pagar onde a UF cobra.

## Prova
Testes verdes com controle positivo · biz de teste conforme ADR 0358.

Terminou: `_saida-29.md`. Pare.
