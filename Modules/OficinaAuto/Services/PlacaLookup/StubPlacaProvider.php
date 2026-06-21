<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Services\PlacaLookup;

/**
 * StubPlacaProvider — driver padrão (dev/CI · sem custo · sem rede).
 *
 * Devolve dados técnicos DETERMINÍSTICOS derivados da própria placa (hash),
 * pra que tela e testes funcionem antes de plugar um fornecedor real.
 * Não há proprietário no retorno — sem PII de terceiro.
 *
 * Convenção de teste: placas começando com `NF` (ex.: `NF0000`) simulam
 * "não encontrada" (retorno null), pra exercitar o caminho de empty state.
 *
 * Trocar pra fornecedor real: `OFICINA_PLACA_DRIVER=http` no .env + config http.
 */
final class StubPlacaProvider implements PlacaProvider
{
    private const BRANDS = [
        ['Fiat', 'Uno'],
        ['Volkswagen', 'Gol'],
        ['Chevrolet', 'Onix'],
        ['Ford', 'Ka'],
        ['Toyota', 'Corolla'],
        ['Hyundai', 'HB20'],
        ['Renault', 'Kwid'],
        ['Honda', 'Civic'],
    ];

    private const COLORS = ['Branco', 'Prata', 'Preto', 'Vermelho', 'Cinza', 'Azul'];

    private const FUELS = ['Flex', 'Gasolina', 'Diesel', 'Etanol'];

    public function lookup(string $plate): ?PlacaLookupResult
    {
        // Caminho "não encontrada" determinístico pra testes/empty state.
        if (str_starts_with($plate, 'NF')) {
            return null;
        }

        // Seed estável a partir da placa — mesmo input gera mesmo output.
        $seed = crc32($plate);

        [$brand, $model] = self::BRANDS[$seed % count(self::BRANDS)];
        $manufactureYear = 2010 + ($seed % 15);             // 2010..2024
        $modelYear       = min($manufactureYear + 1, 2025); // ano modelo = fab + 1
        $color           = self::COLORS[$seed % count(self::COLORS)];
        $fuel            = self::FUELS[$seed % count(self::FUELS)];

        return new PlacaLookupResult(
            plate: $plate,
            brand: $brand,
            model: $model,
            manufactureYear: $manufactureYear,
            modelYear: $modelYear,
            color: $color,
            fuelType: $fuel,
            // Chassi/renavam sintéticos só pra demonstrar o auto-fill — claramente fake.
            chassis: '9BW' . str_pad((string) ($seed % 1_000_000_000), 14, '0', STR_PAD_LEFT),
            engine: null,
            renavam: str_pad((string) ($seed % 100_000_000_000), 11, '0', STR_PAD_LEFT),
        );
    }
}
