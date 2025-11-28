<?php
return [
    'slug'        => 'wpp_chat',
    'name'        => 'WPP Chat',
    'area'        => ['back' => true, 'front' => false],
    'version'     => '1.0.0',
    'description' => 'Integração com WhatsApp, instâncias, campanhas e chat.',

    'capabilities' => [
        'whatsapp:instances:read'    => 'Ver instâncias',
        'whatsapp:instances:manage'  => 'Criar/editar instâncias',
        'whatsapp:messages:send'     => 'Enviar mensagens manuais',
        'whatsapp:messages:read'     => 'Ver histórico de mensagens',
        'whatsapp:campaigns:read'    => 'Ver campanhas',
        'whatsapp:campaigns:manage'  => 'Criar/editar campanhas',
        'whatsapp:webhook:configure' => 'Configurar webhooks',
        'whatsapp:templates:manage'  => 'Gerenciar templates',
    ],

    /**
     * Novo padrão de menus Mozart
     */
    'menus' => [

        // ROOT - Backend sidebar
        [
            'id'           => 'wppchat-root',
            'area'         => 'back',
            'position'     => 'sidebar',
            'label'        => 'WPP Chat',
            'icon'         => 'ti ti-brand-whatsapp',  // Tabler Icons / Font icon
            'url'          => '#',
            'order'        => 300,
            'capabilities' => ['whatsapp:messages:read', 'whatsapp:instances:read'],
            'children'     => [

                [
                    'id'           => 'wppchat-dashboard',
                    'label'        => 'Dashboard',
                    'icon'         => 'ti ti-gauge',
                    'url'          => BASE_URL . '/modules/wpp_chat/dashboard.php',
                    'order'        => 10,
                    'capabilities' => ['whatsapp:messages:read'],
                    'children'     => [],
                ],

                [
                    'id'           => 'wppchat-instancias',
                    'label'        => 'Instâncias',
                    'icon'         => 'ti ti-hexagon',
                    'url'          => BASE_URL . '/modules/wpp_chat/instancias_listar.php',
                    'order'        => 20,
                    'capabilities' => ['whatsapp:instances:read'],
                    'children'     => [],
                ],

                [
                    'id'           => 'wppchat-nova-instancia',
                    'label'        => 'Nova Instância',
                    'icon'         => 'ti ti-plus',
                    'url'          => BASE_URL . '/modules/wpp_chat/instancia-form.php',
                    'order'        => 30,
                    'capabilities' => ['whatsapp:instances:manage'],
                    'children'     => [],
                ],

                [
                    'id'           => 'wppchat-qr',
                    'label'        => 'QR Code / Sessão',
                    'icon'         => 'ti ti-qrcode',
                    'url'          => BASE_URL . '/modules/wpp_chat/instancia-qr.php',
                    'order'        => 40,
                    'capabilities' => ['whatsapp:instances:manage'],
                    'children'     => [],
                ],

                // Divider
                [
                    'id'       => 'wppchat-divider-campanhas',
                    'divider'  => true,
                    'order'    => 90,
                    'children' => [],
                ],

                [
                    'id'           => 'wppchat-campanhas-root',
                    'label'        => 'Campanhas',
                    'icon'         => 'ti ti-megaphone',
                    'url'          => '#',
                    'order'        => 100,
                    'capabilities' => ['whatsapp:campaigns:read'],
                    'children'     => [

                        [
                            'id'           => 'wppchat-campanhas-listar',
                            'label'        => 'Listar Campanhas',
                            'icon'         => 'ti ti-list',
                            'url'          => BASE_URL . '/modules/wpp_chat/campanhas_listar.php',
                            'order'        => 10,
                            'capabilities' => ['whatsapp:campaigns:read'],
                        ],

                        [
                            'id'           => 'wppchat-campanhas-nova',
                            'label'        => 'Nova Campanha',
                            'icon'         => 'ti ti-plus',
                            'url'          => BASE_URL . '/modules/wpp_chat/campanha-form.php',
                            'order'        => 20,
                            'capabilities' => ['whatsapp:campaigns:manage'],
                        ],

                    ],
                ],
            ],
        ],
    ],

    'routes' => [
        [ 'path' => '/modules/wpp_chat/dashboard.php',         'requires' => ['whatsapp:messages:read'] ],
        [ 'path' => '/modules/wpp_chat/instancias_listar.php', 'requires' => ['whatsapp:instances:read'] ],
        [ 'path' => '/modules/wpp_chat/instancia-form.php',    'requires' => ['whatsapp:instances:manage'] ],
        [ 'path' => '/modules/wpp_chat/instancia-qr.php',      'requires' => ['whatsapp:instances:manage'] ],
        [ 'path' => '/modules/wpp_chat/campanhas_listar.php',  'requires' => ['whatsapp:campaigns:read'] ],
        [ 'path' => '/modules/wpp_chat/campanha-form.php',     'requires' => ['whatsapp:campaigns:manage'] ],
    ],

    'role_defaults' => [
        'superadmin'   => ['*'],
        'admin_wpp'    => ['whatsapp:*'],
        'operador_wpp' => [
            'whatsapp:messages:send',
            'whatsapp:messages:read',
            'whatsapp:instances:read',
            'whatsapp:campaigns:read',
        ],
    ],
];
