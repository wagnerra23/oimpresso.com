<?php

declare(strict_types=1);

namespace Modules\ConsultaOs\Repositories;

use Modules\ConsultaOs\Contracts\ConsultaOsRepositoryInterface;

/**
 * MockConsultaOsRepository — implementacao mock-only do contrato.
 *
 * Wave 18 D4 — Repository pattern. Dados estaticos para validar UX antes da
 * integracao real (US-CONSULTA-001). Quando substituir por RepairRepository
 * (transactions + invoice_no + ultimos 4 telefone), trocar bind() no Provider.
 *
 * Dados sao mock — NAO incluem PII real (CPF/CNPJ), apenas razao social
 * publica + numero OS + estagio. Cliente externo so ve oque publico do estado.
 */
class MockConsultaOsRepository implements ConsultaOsRepositoryInterface
{
    public function buscarPorNumero(string $numero): ?array
    {
        return $this->dataset()[$numero] ?? null;
    }

    /**
     * Dataset estatico — mesmas 4 OS do controller legacy mockData().
     *
     * @return array<string, array<string, mixed>>
     */
    private function dataset(): array
    {
        return [
            '4821' => [
                'id'       => '4821',
                'client'   => 'Acme Comercio Ltda',
                'contact'  => 'Camila Diniz',
                'vendedor' => 'Bruna Vendas',
                'designer' => 'Joana Lima',
                'created'  => '21/04/2025',
                'updated'  => 'hoje, 13:55',
                'stage'    => 'aprovacao',
                'items'    => [
                    ['desc' => 'Banner Lona 440g — 3×2m', 'qty' => 1, 'unit' => 'un', 'stage' => 'aprovacao'],
                ],
            ],
            '4819' => [
                'id'       => '4819',
                'client'   => 'Padaria Estrela',
                'contact'  => 'Renato Lopes',
                'vendedor' => 'Bruna Vendas',
                'designer' => 'Joana Lima',
                'created'  => '18/04/2025',
                'updated'  => 'ontem, 16:20',
                'stage'    => 'expedicao',
                'items'    => [
                    ['desc' => 'Cardapios A4 frente e verso', 'qty' => 50, 'unit' => 'un', 'stage' => 'expedicao'],
                ],
            ],
            '4817' => [
                'id'       => '4817',
                'client'   => 'Clinica Vida',
                'contact'  => 'Marcos Saraiva',
                'vendedor' => 'Bruna Vendas',
                'designer' => 'Carla Souza',
                'created'  => '16/04/2025',
                'updated'  => 'hoje, 08:42',
                'stage'    => 'producao',
                'items'    => [
                    ['desc' => 'Placa de sinalizacao PVC — 60×40cm', 'qty' => 8,  'unit' => 'un', 'stage' => 'producao'],
                    ['desc' => 'Placa de sinalizacao PVC — 30×20cm', 'qty' => 4,  'unit' => 'un', 'stage' => 'acabamento'],
                    ['desc' => 'Suporte metalico fixacao parede',     'qty' => 12, 'unit' => 'un', 'stage' => 'producao'],
                ],
            ],
            '4815' => [
                'id'       => '4815',
                'client'   => 'Escola Aurora',
                'contact'  => 'Pedagogico',
                'vendedor' => 'Mateus PCP',
                'designer' => '—',
                'created'  => '10/04/2025',
                'updated'  => 'ontem, 17:50',
                'stage'    => 'entregue',
                'items'    => [
                    ['desc' => 'Banners faixas 1×3m', 'qty' => 6, 'unit' => 'un', 'stage' => 'entregue'],
                ],
            ],
        ];
    }
}
