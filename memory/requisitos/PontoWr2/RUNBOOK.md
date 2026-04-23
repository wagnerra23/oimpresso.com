# Runbook · PontoWr2

## Problema: `/ponto` retorna 404 ou redireciona pro Blade antigo

**Sintoma**: Usuário clica no menu Ponto e cai em tela Blade legada (ou 404).

**Causa**: PontoWr2 desativado ou `/ponto/*` não está em `inertiaPrefixes`.

**Correção**:
```bash
# 1. Verifique modules_statuses.json
cat modules_statuses.json | grep PontoWr2
# Se "false", rode:
php artisan module:enable PontoWr2

# 2. Verifique LegacyMenuAdapter
grep -A 5 "inertiaPrefixes" app/Services/LegacyMenuAdapter.php
# Deve conter '/ponto/*' conforme CLAUDE.md §5
```

## Problema: Importação AFD falha com "PIS não cadastrado"

**Sintoma**: `ponto:importacoes-afd` ou upload via UI joga erro após N linhas.

**Causa**: AFD tem colaboradores com PIS que não existem em `ponto_colaborador_config`.

**Correção**:
```bash
# Inspecionar antes de importar:
php artisan ponto:afd-inspecionar --arquivo=/caminho/arquivo.txt --business=1

# Output mostra quais PIS estão faltando. Cadastre via UI ou seed:
# /ponto/colaboradores/create
```

## Problema: Marcação "não pode ser alterada" (erro 403 ou exception)

**Sintoma**: User tenta editar/deletar uma marcação de dias passados.

**Causa**: ADR ARQ-0001 — `ponto_marcacoes` é append-only por lei (Portaria 671/2021).

**Correção**:
- Não é bug, é regra legal.
- Para "corrigir" uma marcação errada, registre uma **anulação**: `Marcacao::anular($id, $motivo)`.
- Isso cria novo registro tipo `anulacao` preservando o original.
- Espelho mostra ambas linhas — original riscada + anulação.

## Problema: Trailer AFD não bate ("contagem esperada X, encontrada Y")

**Sintoma**: Import aborta no final com mismatch.

**Causa**: Arquivo corrompido, foi editado manualmente, ou export do relógio REP foi interrompido.

**Correção**:
- **Nunca** ignore esse erro — aceitar import parcial pode fabricar marcações inexistentes.
- Peça ao cliente pra re-exportar o AFD do relógio REP original.
- Se persistir, contate suporte do fabricante do REP.

## Problema: Cliente reclama de hora extra "errada" no espelho

**Sintoma**: Colaborador aponta que 8h05 trabalhadas virou 0h05 de HE.

**Causa possível**: Tolerância configurada = 0min. CLT aceita 10min/dia.

**Correção**:
```bash
# Veja configuração atual:
php artisan tinker
>>> \Modules\PontoWr2\Entities\Configuracao::where('business_id', 1)->first()
# Ajustar tolerancia_entrada / tolerancia_saida pra 5 (min cada)

# Ou via UI: /ponto/configuracoes
```

## Problema: eSocial não aceita hash do REP-P

**Sintoma**: Integração eSocial rejeita envio com erro de assinatura.

**Causa**: Hash SHA256 do registro foi gerado com algoritmo/seed errado.

**Correção**:
- Verificar `Modules/PontoWr2/Services/HashService.php` — usa concatenação `PIS|data|hora|hash_anterior`.
- ART registrada com chave diferente da usada no código = rejeição.
- Revalidar com `php artisan ponto:validar-cadeia-hash --business=1`.

## Comandos úteis

```bash
# Inspecionar AFD antes de importar
php artisan ponto:afd-inspecionar --arquivo=/path --business=1

# Importar AFD de uma importação já persistida
php artisan ponto:importacoes-afd --importacao=2

# Recalcular espelho de um colaborador/mês
# (quando regra mudou e precisa re-processar histórico)
# TODO: php artisan ponto:recalcular-espelho --colaborador=X --mes=YYYY-MM

# Re-sincronizar docs_pages após adicionar @docvault
php artisan docvault:sync-pages

# Auditar qualidade da documentação
php artisan docvault:audit-module PontoWr2 --save
```

## Contatos

- **Cliente piloto**: WR2 Sistemas — Eliana (eliana@wr2.com.br)
- **Decisor técnico**: Wagner (wagnerra@gmail.com)
- **Documentação base**: `CLAUDE.md` + `memory/REQUISITOS_FUNCIONAIS_PONTO.md`
