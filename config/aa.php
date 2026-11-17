<?php

/*
|--------------------------------------------------------------------------
| Instruções de AA
|--------------------------------------------------------------------------
|
| Ajustes do e-mail de instrução de AA que NÃO são de um cadastro — valem
| para toda operação, independente de corretora ou cliente.
|
| `copia_fixa` é a lista (separada por ponto e vírgula) que entra em cópia
| em TODA instrução. Mora no `.env`, e não no código, por dois motivos: o
| repositório é público, e a lista muda com a equipe sem precisar de deploy.
| Quem já estiver entre os destinatários "Para" não é repetido na cópia.
|
*/

return [
    'copia_fixa' => env('AA_COPIA_FIXA', ''),
];
