<?php

declare(strict_types=1);

namespace App\Contracts\Enderecos;

/**
 * Contrato do núcleo para buscar endereço por CEP (botão "Buscar" do app das lojas, tela 09).
 *
 * O núcleo depende deste contrato; o módulo Crm o implementa sobre o proxy que já existe
 * (BrLookupService: ViaCEP com cache) e o registra no próprio ServiceProvider. Sem o módulo,
 * vale App\Contracts\Enderecos\Nulo\SemBuscaCep (nunca acha). A seta fica módulo → núcleo
 * (DependencyDirectionTest).
 *
 * CEP é dado público federal: não há business_id envolvido.
 *
 * @phpstan-type Endereco array{logradouro: string, complemento: string, bairro: string, cidade: string, uf: string, codigo_ibge: ?string}
 */
interface BuscaCep
{
    /**
     * Endereço do CEP (8 dígitos, com ou sem máscara), ou null se não achou ou o serviço falhou.
     *
     * @return Endereco|null
     */
    public function buscar(string $cep): ?array;
}
