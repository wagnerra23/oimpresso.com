# ADR TECH-0003 (Financeiro) · MVP do `CnabDirectStrategy` via `eduardokum/laravel-boleto` com envio mockado

- **Status**: accepted
- **Data**: 2026-04-25
- **Decisores**: Wagner
- **Categoria**: tech
- **Relacionado**: ARQ-0003 (Strategy Pattern Boleto), TECH-0001 (idempotência)

## Contexto

O ADR ARQ-0003 decidiu **3 strategies** (`GatewayStrategy`, `CnabDirectStrategy`, `HybridStrategy`). Falta decidir **qual implementar primeiro** e **como testar** sem depender de homologação bancária.

**Cliente único em produção (ROTA LIVRE)** usa **Sicoob (Bancoob)**. Wagner tem acesso a credenciais de **Inter, C6, Cora** (próprios), mas esses 3 são API REST moderna — fora do escopo da lib `eduardokum/laravel-boleto` (CNAB tradicional).

**Decisão do MVP precisa permitir:**
1. Validar geração de boleto pra Sicoob (cliente real) sem precisar certificado A1 nem banco real
2. Confiança alta no contract da `BoletoStrategy` interface antes de gastar tempo com integração real
3. Não bloquear ROTA LIVRE por homologação CNAB (que leva 2-4 semanas)

## Decisão

**MVP do `CnabDirectStrategy` usando `eduardokum/laravel-boleto` v0.11+ com envio CNAB mockado.**

Concretamente:

### Lib instalada via fork local (Laravel 13 não suportado upstream)

A lib upstream `eduardokum/laravel-boleto` v0.11.1 declara `laravel/framework: ^6-^11` no composer.json, conflitando com nosso Laravel 13. Composer caía em v0.1.0 (apenas 6 bancos, sem Sicoob).

**Solução:** fork local em `lib-custom/laravel-boleto/` (clone da tag 0.11.1) com patch único no `composer.json` adicionando `^13.0` ao constraint do framework. Configurado como path repository no `composer.json` raiz.

```json
"repositories": [
    {"type": "path", "url": "lib-custom/laravel-boleto", "options": {"symlink": false}}
]
```

A lib é geração offline pura (linha digitável + código de barras + render PDF/PNG via FPDF), sem features que dependam do framework — patch é seguro. Suite Pest valida que continua funcionando.

**21 bancos disponíveis na v0.11.1:**

| # | Banco | Wagner tem? |
|---|---|---|
| 1 | Ailos | |
| 2 | **Bancoob (Sicoob)** | cliente ROTA LIVRE ✅ |
| 3 | Banrisul | |
| 4 | Banco do Brasil | |
| 5 | BNB (Banco do Nordeste) | |
| 6 | Bradesco | |
| 7 | BTG | |
| 8 | **C6** | ✅ |
| 9 | Caixa Econômica Federal | |
| 10 | Cresol | |
| 11 | Delbank | |
| 12 | Fibra | |
| 13 | HSBC | |
| 14 | **Inter** | ✅ |
| 15 | Itaú | |
| 16 | Ourinvest | |
| 17 | Pine | |
| 18 | Rendimento | |
| 19 | Santander | |
| 20 | Sicredi | |
| 21 | Unicred | |

**Cora ainda fica fora** — não está na lib. Adapter próprio em onda futura se demanda surgir.

### Implementação

```php
namespace Modules\Financeiro\Strategies;

class CnabDirectStrategy implements BoletoStrategy
{
    public function emitir(Titulo $titulo): BoletoRemessa
    {
        // 1. resolve banco a partir de Titulo->contaBancaria->banco_codigo
        // 2. instancia $boleto = new Eduardokum\LaravelBoleto\Boleto\Banco\<Banco>([...])
        // 3. valida $boleto->isValid()
        // 4. persiste BoletoRemessa(status='gerado_mock', linha_digitavel, codigo_barras)
        // 5. retorna BoletoRemessa
    }

    public function cancelar(BoletoRemessa $r): void {
        // muda status='cancelado' (sem chamada banco)
    }

    public function statusAtual(BoletoRemessa $r): BoletoStatus {
        // retorna BoletoStatus baseado no status persistido (sem chamada banco)
    }
}
```

**Flag de runtime** (`config/financeiro.php`):
```php
'cnab_envio_modo' => env('FINANCEIRO_CNAB_ENVIO', 'mock'), // mock | sicoob | bb | itau | ...
```

Em modo `mock`, `BoletoRemessa::status` fica `gerado_mock` e nenhuma remessa CNAB é gerada nem enviada. Em modo `<banco>`, futuro: gera arquivo CNAB (240/400) e dispara envio real (SFTP/API).

### Contract test obrigatório

```php
// tests/Feature/Modules/Financeiro/CnabDirectStrategyContractTest.php

dataset('bancos_eduardokum', [
    'bancoob'   => [BancoCodigo::BANCOOB,   'Bancoob (Sicoob)'],
    'bb'        => [BancoCodigo::BB,        'Banco do Brasil'],
    'bnb'       => [BancoCodigo::BNB,       'Banco do Nordeste'],
    'banrisul'  => [BancoCodigo::BANRISUL,  'Banrisul'],
    'bradesco'  => [BancoCodigo::BRADESCO,  'Bradesco'],
    'caixa'     => [BancoCodigo::CAIXA,     'Caixa'],
    'hsbc'      => [BancoCodigo::HSBC,      'HSBC'],
    'itau'      => [BancoCodigo::ITAU,      'Itaú'],
    'santander' => [BancoCodigo::SANTANDER, 'Santander'],
    'sicredi'   => [BancoCodigo::SICREDI,   'Sicredi'],
]);

it('gera boleto valido por banco', function (string $codigo, string $nome) {
    $titulo = Titulo::factory()->paraBanco($codigo)->create();
    $remessa = app(CnabDirectStrategy::class)->emitir($titulo);

    expect($remessa->status)->toBe('gerado_mock');
    expect($remessa->linha_digitavel)->toMatch('/^\d{47,48}$/'); // 47 ou 48 dígitos
    expect($remessa->codigo_barras)->toMatch('/^\d{44}$/');      // 44 dígitos
    expect($remessa->valor_total)->toEqual($titulo->valor_total);
    expect($remessa->vencimento)->toEqual($titulo->vencimento);
})->with('bancos_eduardokum');

it('e idempotente por titulo_id', function (string $codigo) {
    $titulo = Titulo::factory()->paraBanco($codigo)->create();
    $r1 = app(CnabDirectStrategy::class)->emitir($titulo);
    $r2 = app(CnabDirectStrategy::class)->emitir($titulo);
    expect($r1->id)->toBe($r2->id);
})->with('bancos_eduardokum');
```

## Consequências

### Positivas

- **Sicoob coberto sem certificado** — Larissa pode validar tela de boleto no MVP usando dados próprios da ROTA LIVRE (agência/conta) sem homologação. Boleto físico é apenas mock pré-impressão.
- **10 bancos validados em uma suite Pest** — qualquer regressão na interface `BoletoStrategy` quebra rapidamente.
- **Sem dependência de rede em CI** — testes 100% offline, determinísticos.
- **Caminho de upgrade claro** — flag `cnab_envio_modo` permite ligar Sicoob real em produção sem refactor de tela ou modelo.

### Negativas

- **Inter / C6 / Cora ficam pra depois** — esses 3 são API REST e não cabem em `eduardokum/laravel-boleto`. Implementação real entra em **onda seguinte** via `GatewayStrategy` ou via adapters próprios (decisão futura).
- **CNAB real não validado no MVP** — geração de remessa CNAB 240/400 e parser de retorno ficam pra próxima onda; risco de surpresa quando ligar produção.
- **Mock não exercita SFTP/upload** — fluxo de envio fica sem cobertura. Mitigação: tarefa explícita pra Onda 2 + teste de integração com SFTP fake.

### Neutras

- Mantém compatibilidade total com ADR ARQ-0003 (essa decisão é dentro do `CnabDirectStrategy`, não substitui a arquitetura).
- `GatewayStrategy` (Asaas/Iugu) e `HybridStrategy` ficam para Onda 2-3 sem alteração de contrato.

## Alternativas consideradas

- **Implementar Asaas como driver MVP** — rejeitado: cliente atual (Sicoob) não usa Asaas; criaria fricção operacional pra Larissa.
- **Implementar Sicoob real direto via API SISCOOB + certificado A1** — rejeitado: bloqueia MVP em homologação que pode levar semanas.
- **Implementar 3 strategies em paralelo (Cnab + Gateway + Hybrid)** — rejeitado: explosão de superfície sem necessidade comprovada; MVP foca em 1 caminho.
- **Pular a lib eduardokum, fazer geração de boleto à mão** — rejeitado: 10 bancos × módulo bancário próprio = 6 meses de trabalho que a lib já entrega.

## Pendências (não bloqueantes)

1. Decidir adapter para Inter/C6/Cora (API REST) — Onda 2 ou 3
2. Implementar parser CNAB 240 retorno — Onda 2
3. Tela `/financeiro/boletos` consumindo `BoletoRemessa` — Onda 2

## Referências

- ADR ARQ-0003: `adr/arq/0003-strategy-pattern-boleto-cnab-vs-gateway.md`
- ADR TECH-0001: `adr/tech/0001-idempotencia-em-toda-mutacao-financeira.md`
- Lib: https://github.com/eduardokum/laravel-boleto
- Docs: https://laravel-boleto.readthedocs.io/
- Auto-memória: `memory/cliente_rotalivre.md` (sensibilidades ROTA LIVRE / Larissa)
