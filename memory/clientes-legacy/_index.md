# Clientes legacy — índice

Cross-cutting por cliente. Cada `<alias>.md` consolida quirks que afetam múltiplos módulos do mesmo cliente. Pasta criada por [ADR 0118](../decisions/0118-segregacao-dominios-externos-clientes-legacy.md).

## Matriz cliente × versão Delphi × business_id

> Versões Delphi observadas no Editor de Registros de Bancos de Dados v3.5 (snapshot 2026-05-09). Versão real atual de cada cliente vem do banco: `SELECT VALOR FROM CONFIGURACOES WHERE CONFIG='VERSAO_BANCO'`. Algumas versões no editor podem estar desatualizadas (cliente sobe versão sem o editor saber).

| Alias (registry) | Versão Delphi | business_id oimpresso | Status migração | Notas |
|---|---|---|---|---|
| **ServidorWR2** | 1466 | 1 (Wagner) | 🚧 piloto | banco do Wagner; smoke test fase 6 |
| **rota-livre** ⭐ | ? | 4 | ⏳ não iniciada | 99% volume; ver [../clientes/rota-livre/operacao.md](../clientes/rota-livre/operacao.md) |
| TechPressLocal | 1468 | — | ⏳ | banco "TechPress" local (não tem certeza se é cliente real ou ambiente teste Wagner) |
| Display Parana | ? | ? | ⏳ | |
| Destak | ? | ? | ⏳ | |
| DMB | ? | ? | ⏳ | |
| Medeiros Produtos Limpeza | 1417 | ? | ⏳ | |
| HexiPrint | 1407 | ? | ⏳ | |
| CyberStudio | ? | ? | ⏳ | |
| Golbal (Global Pneus) | 1418 | ? | ⏳ | |
| Multimage | 1412 | ? | ⏳ | |
| Vargas | 1468 | 164 | ⏳ | observado em [ARQUITETURA.md](../dominios/wr-comercial/ARQUITETURA.md) — cliente Delphi não chama backend (build antigo só com OAuth) |
| Vargas Acessorios | ? | ? | ⏳ | (path em "Jardel acessorios" — nome inconsistente) |
| Guia Decor | 1416 | ? | ⏳ | |
| Bangalo | ? | ? | ⏳ | |
| Mecanica Lebrinha | ? | ? | ⏳ | duplicado com "Lebrinha" abaixo (mesmo path no registry) |
| Midia OFF | 1472 | ? | ⏳ | "Banco de Dados muito antigo, poucas alt. restantes" — possível licença expirando |
| Midia e CIA | 1472 | ? | ⏳ | mesmo aviso |
| MoveisSul | 1472 | ? | ⏳ | mesmo aviso |
| Max | 1453 | ? | ⏳ | |
| Zoom | 1474 | ? | ⏳ | versão MAIS NOVA observada |
| Wow | 1453 | ? | ⏳ | |
| CiaDosMoveis | ? | ? | ⏳ | |
| Camargo (Sabor Brasil) | ? | ? | ⏳ | nome registry não bate com path |
| Mhundo | 1429 | ? | ⏳ | |
| MilLetras | 1417 | ? | ⏳ | |
| ECopias | ? | ? | ⏳ | |
| Estilo | 1413 | ? | ⏳ | |
| Extreme | 1472 | 196 | ⏳ | "EXTREMA LED" provável; chama backend (build novo) |
| CopyLanLocal | ? | ? | ⏳ | |
| Art Laser | ? | ? | ⏳ | |
| ASULBRAT (Assulbrat) | ? | ? | ⏳ | path com sufixo "BANCO - ASULBRAT.FDB" — cliente custom |
| Metalurgica SF | 1410 | ? | ⏳ | |
| Personalise | 1408 | ? | ⏳ | "Banco de Dados muito antigo" |
| Produart | 1472 | ? | ⏳ | "Banco de Dados muito antigo" |
| Studium Vinil | 1412 | ? | ⏳ | |
| CubaInox | ? | ? | ⏳ | |
| SCMola | 1413 | ? | ⏳ | |
| Safety | 1421 | ? | ⏳ | |
| CiaSul | ? | ? | ⏳ | |
| Casagrande | ? | ? | ⏳ | path "Mecânica Casagrande" |
| GPSinalizacao | 1446 | ? | ⏳ | |
| Lebrinha | 1434 | ? | ⏳ | |
| GSX | 1418 | ? | ⏳ | path no banco da GPSinalizacao mas com sufixo GSX — cliente compartilha instância |
| GoldenPrint | 571 | ? | ⏳ | versão MAIS ANTIGA observada (gap de ~900 updates) |
| Gold | 1466 | ? | ⏳ | |
| Fixar | 1421 | ? | ⏳ | |
| Fluxo | 1444 | ? | ⏳ | |
| NewPrintFoz | 1413 | ? | ⏳ | |
| MartinhoServidor | 1453 | ? | ⏳ | |
| RG Comunicacao | 1472 | ? | ⏳ | |
| Martinho | ? | ? | ⏳ | path local (sem servidor-crm) |

> **49 clientes Delphi** + 1 piloto Wagner (ServidorWR2) = 50 entradas no registry. Algumas duplicações intencionais (ex: Lebrinha + Mecanica Lebrinha mesmo path).

## Status flags

- ⏳ não iniciada — sem mapeamento, sem business_id, sem decisão
- 🟡 mapeada — cliente tem `<alias>.md` doc + business_id atribuído mas import ainda não rodou
- 🚧 piloto — em smoke
- ✅ migrada — import rodou ok, dados visíveis no oimpresso, cliente notificado
- 🔒 retired — Delphi desativado pro cliente (oimpresso 100% in)

## Como adicionar cliente novo

1. Criar `clientes-legacy/<alias-kebab>.md` com perfil: razão social, biz_id, versão Delphi atual, quirks
2. Atualizar este `_index.md` com a linha
3. Cruzar com auto-mem do dev (Wagner ROTA LIVRE legacy) — migrar conhecimento que ainda esteja em auto-mem privada
4. PII reais (CPF/CNPJ cliente, telefones) marcar como `[REDACTED]` ou usar últimos 2 dígitos só pra rastreabilidade ([proibicoes.md](../proibicoes.md))
