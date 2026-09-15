import * as React from 'react';

export interface PlacaVeiculoProps {
  /** Texto da placa (ex. "RFB1D23"). Não-alfanuméricos são removidos; máx. 7 chars. */
  placa?: string;
  /** Padrão visual. Default 'mercosul'. */
  padrao?: 'mercosul' | 'antiga';
  /** Tamanho. Default 'md'. */
  size?: 'sm' | 'md' | 'lg';
  /** Texto da faixa-país. Default 'BRASIL'. */
  pais?: string;
  /** Sigla na faixa (ex. 'SP'). Mercosul mostra 'BR' se omitido. */
  uf?: string;
  /** Categoria → cor do caractere (placa Mercosul). Default 'particular'. */
  categoria?: 'particular' | 'comercial' | 'oficial' | 'especial';
}

/** Placa veicular brasileira (Mercosul ou antiga) como componente reutilizável. */
export declare function PlacaVeiculo(props: PlacaVeiculoProps): JSX.Element;
