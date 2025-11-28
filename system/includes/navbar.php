<?php
// NUNCA deixe espaços acima deste <?php

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/system/config/autenticacao.php';

// sistema de menus
require_once ROOT_PATH . '/system/includes/mozart_menu.php';
require_once ROOT_PATH . '/system/includes/mozart_menu_render.php';

// Carrega menus dinâmicos do backend (sidebar)
$menusBack = mozart_get_menu('back', 'sidebar');
?>
</head>
<body>
<div id="wrapper">

    <!-- TOP NAV -->
    <nav class="navbar navbar-inverse navbar-fixed-top" role="navigation">

        <div class="navbar-header">
            <img src="<?= BASE_URL ?>/data/media/media_sytem/images/logo_mozart.png"
                 alt="<?= APP_NAME ?>" width="160">
        </div>

        <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
        </button>

        <!-- LINK HOME -->
        <ul class="nav navbar-nav navbar-left navbar-top-links">
            <li>
                <a href="<?= BASE_URL ?>/pages/dashboard.php">
                    <i class="fa fa-home fa-fw"></i> Home
                </a>
            </li>
        </ul>

        <!-- USER / NOTIFICAÇÕES -->
        <ul class="nav navbar-right navbar-top-links">

            <!-- NOTIFICAÇÕES (como no seu original) -->
            <li class="dropdown navbar-inverse">
                <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                    <i class="fa fa-bell fa-fw"></i> <b class="caret"></b>
                </a>
                <ul class="dropdown-menu dropdown-alerts">
                    <li>
                        <a href="<?= BASE_URL ?>/pages/listagem_chamados.php?status=novos">
                            <div>
                                <i class="fa fa-comment fa-fw"></i> Novos
                                <span id="count-novos" class="pull-right text-muted small"></span>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/pages/listagem_chamados.php?status=atribuidos">
                            <div>
                                <i class="fa fa-commenting fa-fw"></i> Atribuídos
                                <span id="count-atribuidos" class="pull-right text-muted small"></span>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/pages/listagem_chamados.php?status=solucionados">
                            <div>
                                <i class="fa fa-check fa-fw"></i> Solucionados
                                <span id="count-solucionados" class="pull-right text-muted small"></span>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/pages/listagem_chamados.php?status=fechados">
                            <div>
                                <i class="fa fa-smile-o fa-fw"></i> Fechados
                                <span id="count-fechados" class="pull-right text-muted small"></span>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/pages/listagem_chamados.php?status=atrasados">
                            <div>
                                <i class="fa fa-clock-o fa-fw"></i> Atrasados (+7 dias)
                                <span id="count-atrasados" class="pull-right text-muted small"></span>
                            </div>
                        </a>
                    </li>
                    <li class="divider"></li>
                    <li>
                        <a class="text-center" href="<?= BASE_URL ?>/pages/listagem_chamados.php">
                            <strong>Mais alertas</strong>
                            <i class="fa fa-angle-right"></i>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ÁREA DO USUÁRIO -->
            <li class="dropdown">
                <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                    <i class="fa fa-user fa-fw"></i> Área de Usuário <b class="caret"></b>
                </a>
                <ul class="dropdown-menu dropdown-user">
                    <li>
                        <a href="<?= BASE_URL ?>/pages/user_page.php">
                            <i class="fa fa-feed"></i> Feed
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/pages/meu_perfil.php">
                            <i class="fa fa-user"></i> Alterar Perfil
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/pages/view_calendar.php">
                            <i class="fa fa-calendar"></i> Calendário
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/pages/meu_perfil_senha.php">
                            <i class="fa fa-expeditedssl"></i> Segurança
                        </a>
                    </li>
                    <li class="divider"></li>
                    <li>
                        <a href="<?= BASE_URL ?>/system/actions/logout.php">
                            <i class="fa fa-sign-out"></i> Sair
                        </a>
                    </li>
                </ul>
            </li>

        </ul>
    </nav>

    <!-- SIDEBAR -->
    <aside class="sidebar navbar-default" role="navigation">
        <div class="text-left" style="padding:5px">
            <button id="sidebarToggle" class="btn btn-default btn-xs">
                <i class="fa fa-arrows-alt"></i>
            </button>
        </div>

        <div class="sidebar-nav navbar-collapse">
            <ul class="nav" id="side-menu">

                <!-- Busca -->
                <li class="sidebar-search">
                    <div class="input-group custom-search-form">
                        <input type="text" class="form-control" placeholder="Buscar...">
                        <span class="input-group-btn">
                            <button class="btn btn-primary" type="button">
                                <i class="fa fa-search"></i>
                            </button>
                        </span>
                    </div>
                </li>

                <!-- Painel (fixo) -->
                <li>
                    <a href="<?= BASE_URL ?>/pages/dashboard.php"
                       class="<?= mozart_menu_is_active(BASE_URL . '/pages/dashboard.php') ? 'active' : '' ?>">
                        <i class="fa fa-dashboard"></i> Painel
                    </a>
                </li>

                <!-- MENUS DINÂMICOS (de todos os módulos via manifest) -->
                <?php renderSidebarMenu($menusBack); ?>

            </ul>
        </div>
    </aside>
    <!-- /.sidebar -->

    <!-- A partir daqui, em cada página, vem o conteúdo: 
         <div id="page-wrapper">...</div> -->
