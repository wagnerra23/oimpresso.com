<?php

return [
    'name' => 'ComunicacaoVisual',

    /*
     * CNAE principal deste vertical: 1813-0/01 — indústria gráfica rápida e comunicação visual.
     * Concorrentes: Mubisys, Zênite, Calcgraf.
     * Diferencial: cálculo m² + PCP gráfico + apontamento + NFe-de-boleto-pago + IA conversacional.
     */
    'cnae' => '1813-0/01',

    /*
     * Unidade padrão de medida para orçamento (m²).
     * Configurável por business futuramente.
     */
    'unidade_medida_default' => 'm2',

    /*
     * Dias de validade do link do protocolo da instalação que vai ao cliente (UC-PCV-25 · 1d).
     * Passado o prazo o link expira; o protocolo continua na tela da instalação para quem tem login.
     * Valor fixo, sem env(): a regra do Larastan (ratchet do PHPStan) só aceita env() dentro de config/
     * na raiz. Para mudar, edite aqui ou publique o config do módulo.
     */
    'protocolo_link_dias' => 7,
];
