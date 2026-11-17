<?php

/*
|--------------------------------------------------------------------------
| Exportação
|--------------------------------------------------------------------------
|
| `copia_fixa` é a lista (separada por ponto e vírgula) que entra em cópia
| em TODO e-mail de programação de estufagem — a equipe interna que
| acompanha os embarques. Mora no `.env`, e não no código, pelos mesmos
| motivos da cópia fixa das instruções de AA: o repositório é público e a
| lista muda com a equipe, sem deploy.
|
*/

return [
    'copia_fixa' => env('EXPORTACAO_COPIA_FIXA', ''),
];
