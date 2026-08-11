<?php

/*
| Mensagens do fluxo "Esqueci minha senha". Sem este arquivo, a tela
| mostraria a chave crua ("passwords.sent") em vez do aviso.
| Obs.: o PasswordResetLinkController responde sempre a mesma frase
| genérica de propósito (proteção contra enumeração de usuários), então
| 'sent' e 'user' aqui só aparecem em fluxos internos do broker.
*/

return [
    'reset' => 'Sua senha foi redefinida com sucesso.',
    'sent' => 'Enviamos o link de redefinição de senha para o seu e-mail.',
    'throttled' => 'Aguarde alguns instantes antes de tentar novamente.',
    'token' => 'Este link de redefinição de senha é inválido ou já foi usado. Solicite um novo.',
    'user' => 'Se este e-mail estiver cadastrado, enviamos um link de redefinição de senha.',
];
