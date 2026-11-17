<?php

/**
 * Parâmetros da posição Long & Short. São os mesmos números que a mesa
 * mantinha em células da planilha de posição (aba GERAL ESTOQUE, rodapé, e
 * célula Q1 da aba COMPRA) — mudam raramente, por isso ficam aqui.
 */
return [

    /*
    | Rendimento do que está em estoque ainda sem preparo. A bica e o
    | reaproveitamento não são padrão de venda: viram 17/18, 14/16, grinders
    | e mercado interno depois do rebenefício. Percentuais por balde de
    | destino; cada linha soma 100.
    */
    'rendimento_estoque' => [
        'BICA_FC' => ['1718_FC' => 35, '1416_FC' => 52, 'GRINDERS_12UP' => 10, 'MERCADO_INTERNO' => 3],
        'BICA_GC' => ['1718_GC' => 30, '1416_GC' => 50, 'GRINDERS_12UP' => 15, 'MERCADO_INTERNO' => 5],
        'REAPROV' => ['1718_GC' => 10, '1416_GC' => 60, 'GRINDERS_12UP' => 30, 'MERCADO_INTERNO' => 0],
    ],

    /*
    | Da cata de uma compra, quanto vira grinders; o resto é baixo (mercado
    | interno). Na planilha: "% Grinders s/ baixo".
    */
    'grinders_sobre_cata' => 85,

    /*
    | Rendimento de uma compra que não tem % de 17/18 e de cata informados
    | nem classificação: [% 17/18, % cata] pela família do padrão.
    */
    'rendimento_padrao_da_compra' => [
        'FC' => [35, 13],
        'GC' => [30, 20],
    ],
];
