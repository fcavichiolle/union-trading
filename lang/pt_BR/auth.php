<?php

/*
| Mensagens de autenticação. O AuthenticatedSessionController já lança
| textos próprios em português (para não vazar se o e-mail existe ou
| não), mas estas chaves são usadas pelo próprio framework — sem o
| arquivo, apareceria "auth.throttle" na tela.
*/

return [
    'failed' => 'E-mail ou senha incorretos.',
    'password' => 'A senha informada está incorreta.',
    'throttle' => 'Muitas tentativas de login. Tente novamente em :seconds segundos.',
];
