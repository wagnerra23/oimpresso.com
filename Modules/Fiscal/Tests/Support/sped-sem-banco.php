<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Fiscal\Services\SpedIcmsIpiGeneratorService;

/*
 * Apoio dos testes do SPED (US-FISCAL-028): o gerador inteiro sem banco — só a leitura de
 * business, NF-e e das fontes do emitente é trocada por um tenant fictício 98 em memória.
 */

/**
 * Emitente fictício completo do tenant 98. CNPJ 11.222.333/0001-81 é o exemplo de manual (dígitos
 * válidos, não pertence a ninguém); o mesmo que `DfeCnpjEAlarmeTest` usa.
 *
 * @param  array<string,mixed>  $sobrescrever  campos de `business` a trocar
 * @param  array{cnpj?: ?string, uf?: ?string, cod_mun?: ?string, inventario?: array}  $fontes
 */
if (! function_exists('spedSemBanco')) {
function spedSemBanco(array $emissoes = [], array $sobrescrever = [], array $fontes = []): SpedIcmsIpiGeneratorService
{
    $business = array_merge([
        'name' => 'CI TENANT 98', 'razao_social' => 'CI TENANT 98 (FICTICIO) LTDA', 'ie' => '123456789',
        'cep' => '88700-000', 'rua' => 'RUA DO TESTE', 'numero' => '98', 'bairro' => 'CENTRO',
        'telefone' => '48 99999-0098', 'cidade_id' => 1,
    ], $sobrescrever);
    $fontes = array_merge(['cnpj' => '11222333000181', 'uf' => 'SC', 'cod_mun' => '4207304',
        'inventario' => ['itens' => [], 'soma_bruta' => 0.0, 'negativos' => 0, 'total_canonico' => 0.0]], $fontes);

    return new class($emissoes, $business, $fontes) extends SpedIcmsIpiGeneratorService {
        public function __construct(private array $fake, private array $business, private array $fontes)
        {
            parent::__construct(null);
        }

        protected function carregarBusiness(int $businessId): ?object
        {
            return (object) (['id' => $businessId] + $this->business);
        }

        protected function resolverCnpj(int $businessId): ?string
        {
            return $this->fontes['cnpj'];
        }

        protected function carregarUfDoEstabelecimento(int $businessId): ?string
        {
            return $this->fontes['uf'];
        }

        protected function carregarCodigoMunicipio(object $business): ?string
        {
            return $this->fontes['cod_mun'];
        }

        protected function carregarInventario(int $businessId, string $data): array
        {
            return $this->fontes['inventario'];
        }

        protected function carregarEmissoes(int $businessId, CarbonImmutable $ini, CarbonImmutable $fim): Collection
        {
            return collect($this->fake);
        }
    };
}

}

/** @return list<list<string>> campos de cada linha, sem os pipes das pontas */
if (! function_exists('camposSped')) {
function camposSped(string $txt): array
{
    $linhas = array_values(array_filter(explode("\r\n", $txt), fn ($l) => $l !== ''));

    return array_map(fn ($l) => explode('|', substr($l, 1, -1)), $linhas);
}
}
