<?php

return [
    // disabled, graph (Microsoft 365), smtp ou mail.
    'transport' => 'graph',

    'from_email' => 'sinistro@vaidemotoca.com',
    'from_name' => 'MOTOCA',
    'reply_to' => 'sinistro@vaidemotoca.com',

    'graph' => [
        // Tenant ID do Microsoft Entra / Azure AD.
        'tenant_id' => 'COLOQUE_O_TENANT_ID_AQUI',
        // Application (client) ID do app registrado no Azure.
        'client_id' => 'COLOQUE_O_CLIENT_ID_AQUI',
        // Client secret gerado no app registration.
        'client_secret' => 'COLOQUE_O_CLIENT_SECRET_AQUI',
        // Opcional: mailbox remetente. Se vazio, usa from_email.
        'mailbox_user_id' => 'sinistro@vaidemotoca.com',
        'scope' => 'https://graph.microsoft.com/.default',
        'base_url' => 'https://graph.microsoft.com/v1.0',
        'timeout' => 20,
    ],

    'smtp' => [
        'host' => 'smtp.office365.com',
        'port' => 587,
        'secure' => 'tls',
        'username' => 'sinistro@vaidemotoca.com',
        'password' => 'COLOQUE_A_SENHA_SMTP_AQUI',
        'auth' => true,
        'timeout' => 20,
        'helo' => 'vaidemotoca.com',
    ],
];
