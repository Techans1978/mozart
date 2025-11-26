</head>
<body>

        <div id="wrapper">

            <!-- Navigation -->
            <nav class="navbar navbar-inverse navbar-fixed-top" role="navigation">
                <div class="navbar-header">
                    <img src="<?= BASE_URL ?>/data/media/media_sytem/images/logo_mozart.png" alt="<?= APP_NAME ?>" width="160px">
                </div>

                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>

                <ul class="nav navbar-nav navbar-left navbar-top-links">
                    <li><a href="<?= BASE_URL ?>/pages/dashboard.php"><i class="fa fa-home fa-fw"></i> Home</a></li>
                </ul>

                <ul class="nav navbar-right navbar-top-links">
                    <li class="dropdown navbar-inverse">
                        <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                            <i class="fa fa-bell fa-fw"></i> <b class="caret"></b>
                        </a>
                        <ul class="dropdown-menu dropdown-alerts">
                            <li>
                                <a href="<?= BASE_URL ?>/pages/listagem_chamados.php?status=novos">
                                    <div>
                                        <i class="fa fa-comment fa-fw"></i> Novos
                                        <span  id="count-novos" class="pull-right text-muted small"></span>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a href="<?= BASE_URL ?>/pages/listagem_chamados.php?status=atribuidos">
                                    <div>
                                        <i class="fa fa-commenting fa-fw"></i> Atribuidos
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
                                        <i class="fa fa-clock-o fa-fw"></i> Atrasados (+7dias)
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
                    <li class="dropdown">
                        <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                            <i class="fa fa-user fa-fw"></i> Área de Usuário <b class="caret"></b>
                        </a>
                        <ul class="dropdown-menu dropdown-user">
                            <li>
                                <a href="<?= BASE_URL ?>/pages/user_page.php"><i class="fa fa-feed"></i> Feed</a>
                            </li>
                            <li>
                                <a href="<?= BASE_URL ?>/pages/meu_perfil.php"><i class="fa fa-user fa-fw"></i> Alterar Perfil</a>
                            </li>
                            <li>
                                <a href="<?= BASE_URL ?>/pages/meu_perfil.php"><i class="fa fa-calendar fa-fw"></i> Calendário</a>
                            </li>
                            <li>
                                <a href="<?= BASE_URL ?>/pages/meu_perfil_senha.php"><i class="fa fa-expeditedssl fa-fw"></i> Segurança</a>
                            </li>
                            <li class="divider"></li>
                            <li>
                                <a href="<?= BASE_URL ?>/system/actions/logout.php"><i class="fa fa-sign-out fa-fw"></i> Sair</a>
                            </li>
                        </ul>
                    </li>
                </ul>
                <!-- /.navbar-top-links -->
            </nav>

            <aside class="sidebar navbar-default" role="navigation">
                <div class="text-left" style="padding:5px">
                <button id="sidebarToggle" class="btn btn-default btn-xs">
                    <i class="fa fa-arrows-alt"></i>
                </button>
                </div>
                <div class="sidebar-nav navbar-collapse">
                    <ul class="nav" id="side-menu">
                        <li class="sidebar-search">
                            <div class="input-group custom-search-form">
                                <input type="text" class="form-control" placeholder="Search...">
                                <span class="input-group-btn">
                                    <button class="btn btn-primary" type="button">
                                        <i class="fa fa-search"></i>
                                    </button>
                                </span>
                            </div>
                            <!-- /input-group -->
                        </li>
                        <li>
                            <a href="<?= BASE_URL ?>/pages/dashboard.php" class="active"><i class="fa fa-dashboard fa-fw"></i> Painel</a>
                        </li>
                        <li>
                            <a href="#"><i class="fa fa-wechat fa-fw"></i> IA Collab</a>
                            <ul class="nav nav-second-level">
                                <li>
                                    <a href="<?= BASE_URL ?>/#">Criar Consulta</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/#">Ver Consultas Públicas</a>
                                </li>
                                
                            </ul>
                            <!-- /.nav-second-level -->
                        </li>
                        <li>
                        <a href="#"><i class="fa fa-phone"></i> Help Desk</a>
                        <ul class="nav nav-second-level">
                            <li><a href="<?= BASE_URL ?>/modules/helpdesk/pages/dashboard.php">Dashboard</a></li>
                            <li><a href="<?= BASE_URL ?>/modules/helpdesk/pages/tickets_listar.php">Listar Chamados</a></li>
                            <li><a href="<?= BASE_URL ?>/modules/helpdesk/pages/ticket_novo.php">Abrir Chamado</a></li>
                            <li><a href="<?= BASE_URL ?>/modules/helpdesk/pages/inbox.php">Minha Caixa (Agente)</a></li>

                            <li class="nav-divider"></li>
                            <li><a href="<?= BASE_URL ?>/modules/helpdesk/pages/reports_one.php">Relatórios & Agendamentos</a></li>
                            <li><a href="<?= BASE_URL ?>/modules/helpdesk/pages/reports_pro.php">Relatórios & Agendamentos</a></li>

                            <li class="nav-divider"></li>
                            <li>
                            <a href="#">Cadastros</a>
                            <ul class="nav nav-third-level">
                                <li><a href="<?= BASE_URL ?>/modules/helpdesk/pages/admin/categorias.php">Categorias</a></li>
                                <li><a href="<?= BASE_URL ?>/modules/helpdesk/pages/admin/servicos.php">Serviços</a></li>
                                <li><a href="<?= BASE_URL ?>/modules/helpdesk/pages/admin/tipos_status.php">Tipos / Status</a></li>
                                <li><a href="<?= BASE_URL ?>/modules/helpdesk/pages/admin/entidades.php">Entidades / Lojas</a></li>
                                <li><a href="<?= BASE_URL ?>/modules/helpdesk/pages/admin/origens.php">Origens</a></li>
                                <li><a href="<?= BASE_URL ?>/modules/helpdesk/pages/admin/tecnicos_filas.php">Técnicos & Filas</a></li>
                                <li><a href="<?= BASE_URL ?>/modules/helpdesk/pages/admin/formularios.php">Formulários</a></li>
                                <li><a href="<?= BASE_URL ?>/modules/helpdesk/pages/admin/templates_email.php">Templates de E-mail</a></li>
                                <li><a href="<?= BASE_URL ?>/modules/helpdesk/pages/admin/macros.php">Macros</a></li>
                            </ul>
                            </li>

                            <li>
                            <a href="#">SLA & Regras</a>
                            <ul class="nav nav-third-level">
                                <li><a href="<?= BASE_URL ?>/modules/helpdesk/pages/admin/sla.php">SLA</a></li>
                                <li><a href="<?= BASE_URL ?>/modules/helpdesk/pages/admin/regras.php">Regras de Automação</a></li>
                                <li><a href="<?= BASE_URL ?>/modules/helpdesk/pages/admin/oncall.php">On-call / Plantões</a></li>
                            </ul>
                            </li>

                            <li>
                            <a href="#">Segurança</a>
                            <ul class="nav nav-third-level">
                                <li><a href="<?= BASE_URL ?>/modules/helpdesk/pages/admin/rbac.php">RBAC</a></li>
                                <li><a href="<?= BASE_URL ?>/modules/helpdesk/pages/admin/auditoria.php">Auditoria</a></li>
                            </ul>
                            </li>
                        </ul>
                        </li>

                        <li>
                            <a href="#"><i class="fa fa-wrench"></i> Ativos</a>
                            <ul class="nav nav-second-level">
                                <li>
                                    <a href="<?= BASE_URL ?>#">Dashboard</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/gestao_ativos/#">Desenho de Infra</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/gestao_ativos/os-listar.php">Ordem de serviço</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/gestao_ativos/oc-listar.php">Ordem de Compra</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/gestao_ativos/etiquetas-qr-listar.php">Etiquetas</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/gestao_ativos/contratos-listar.php">Contratos</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/gestao_ativos/reservas-listar.php">Reservas</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/gestao_ativos/transferencias-listar.php">Transferências</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/gestao_ativos/ativos-listar.php">Cadastro de Ativos</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/gestao_ativos/licencas-listar.php">Cadastro de Licenças</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/gestao_ativos/categorias-listar.php">Categorias de Ativos</a>
                                </li>
                                
                                <li>
                                    <a href="<?= BASE_URL ?>/modules/gestao_ativos/fabricantes-distribuidores-listar.php">Fabricantes / Distribuidores</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/gestao_ativos/modelos-listar.php">Modelos</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/gestao_ativos/listas-dropdowns-form.php">Listas</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/gestao_ativos/depositos-listar.php">Depósitos</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/gestao_ativos/tempos-servico-listar.php">Tempo de Serviço</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>#">Margens de Valor</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/gestao_ativos/automacoes-listar.php">Automação & Regras</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/gestao_ativos/tecnicos-filas-listar.php">Técnico / Filas / Tempo</a>
                                </li>
                                
                            </ul>
                            <!-- /.nav-second-level -->
                        </li>
                        <li>
                            <a href="#"><i class="fa fa-hourglass-3 fa-fw"></i> Projetos</a>
                            <ul class="nav nav-second-level">
                                <li>
                                    <a href="<?= BASE_URL ?>/#">Dashboard</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/#">Criar/Acompanhar</a>
                                </li>
                                
                            </ul>
                            <!-- /.nav-second-level -->
                        </li>
                        <li>
                            <a href="#"><i class="fa fa-code"></i> APP Code Creator</a>
                            <ul class="nav nav-second-level">
                                <li>
                                    <a href="<?= BASE_URL ?>/#">Dashboard</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/#">Criar/Acompanhar</a>
                                </li>
                                
                            </ul>
                            <!-- /.nav-second-level -->
                        <li>
                            <a href="#">
                                <i class="fa fa-whatsapp"></i> WPP Chat <span class="fa arrow"></span>
                            </a>
                            <ul class="nav nav-second-level">
                                <li>
                                    <a href="<?= BASE_URL ?>/modules/wpp_chat/dashboard.php">Dashboard</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/wpp_chat/pages/conversas.php">Conversas</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/wpp_chat/pages/clientes-list.php">Clientes</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/wpp_chat/pages/campanhas-list.php">Campanhas</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/wpp_chat/pages/scripts-list.php">Scripts</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/wpp_chat/instancias.php">Instâncias</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/wpp_chat/configuracoes.php">Configurações</a>
                                </li>
                            </ul>
                            <!-- /.nav-second-level -->
                        </li>

                        <li>
                            <a href="#"><i class="fa fa-tasks fa-fw"></i> BPM</a>
                            <ul class="nav nav-second-level">
                                <li>
                                    <a href="<?= BASE_URL ?>/modules/bpm/wizard_bpm.php">Wizard BPM</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/bpm/bpm_designer.php">Diagramas</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/bpm/forms/forms_designer.php">Formulários</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/bpm/categorias_bpm_listar.php">Categorias</a>
                                </li>
                                
                                <li>
                                    <a href="<?= BASE_URL ?>/system/dmn/dmn_playground.html">Decision</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/bpm/listar_datasets_bpm.php">Data Sets</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>#">Substitutos</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>#">Transferir pendências</a>
                                </li>                                
                                
                                <li>
                                    <a href="<?= BASE_URL ?>#">Códigos Adicionais</a>
                                </li>
                                
                                <li>
                                    <a href="<?= BASE_URL ?>#">Data Sets</a>
                                </li>
                                
                            </ul>
                            <!-- /.nav-second-level -->
                        </li>

                        <li>
                            <a href="#"><i class="fa fa-comments"></i> Intranet</a>
                            <ul class="nav nav-second-level">
                                <li>
                                    <a href="<?= BASE_URL ?>/pages/conteudo_listar.php">Artigos</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/pages/conteudo_categorias.php">Categorias Artigos</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/pages/dicas_listar.php">Dicas</a>
                                </li>
                                
                                <li>
                                    <a href="<?= BASE_URL ?>/pages/media_listar.php">Midia Feed</a>
                                
                                <li>
                                    <a href="<?= BASE_URL ?>/pages/docs_listar.php">Documentos</a>
                                </li>
                                
                                <li>
                                    <a href="<?= BASE_URL ?>/pages/event_listar.php">Calendário de Eventos</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/pages/event_listar.php">Área de Conhecimento</a>
                                </li>

                            </ul>
                            <!-- /.nav-second-level -->
                        </li>

                        <li>
                            <a href="#"><i class="fa fa-arrows fa-fw"></i> Orquestrador de API</a>
                            <ul class="nav nav-second-level">
                                <li>
                                    <a href="<?= BASE_URL ?>/modules/orquestrador_api/conectores.php">Criação Guiada</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/orquestrador_api/flows-listar.php">Flows</a>
                                </li>

                                <li>
                                    <a href="<?= BASE_URL ?>/modules/orquestrador_api/connectors-builder.php">Conectores</a>
                                </li>
                                
                            </ul>
                            <!-- /.nav-second-level -->
                        </li>

                        <li>
                            <a href="#"><i class="fa fa-phone"></i> Gestão de Terceiros</a>
                            <ul class="nav nav-second-level">
                                <li>
                                    <a href="<?= BASE_URL ?>/#">Dashbboard</a>
                                </li>
                                <li>
                                    <a href="<?= BASE_URL ?>/#">Listar Fornecedor</a>
                                </li>
                                <li>
                                    <a href="<?= BASE_URL ?>/#">Cadastrar Fornecedor</a>
                                </li>
                                <li>
                                    <a href="<?= BASE_URL ?>/#">Listar Promotor</a>
                                </li>
                                <li>
                                    <a href="<?= BASE_URL ?>/#">Exportar Promotor</a>
                                </li>
                                
                            </ul>
                            <!-- /.nav-second-level -->
                        </li>

                        <li>
                            <a href="#"><i class="fa fa-cogs fa-fw"></i> Gerenciamento</a>
                            <ul class="nav nav-second-level">
                                <li>
                                    <a href="<?= BASE_URL ?>/pages/listar_usuarios.php">Usuários</a>
                                </li>
                                <li>
                                    <a href="<?= BASE_URL ?>/pages/empresas_listar.php">Empresas / Coligadas</a>
                                </li>
                                <li>
                                    <a href="<?= BASE_URL ?>/pages/grupos_listar.php">Grupos</a>
                                </li>
                                <li>
                                    <a href="<?= BASE_URL ?>/pages/perfis_listar.php">Perfis</a>
                                </li>
                                <li>
                                    <a href="<?= BASE_URL ?>/pages/papeis_listar.php">Papéis</a>
                                </li>
                                <li>
                                    <a href="<?= BASE_URL ?>/pages/niveis_listar.php">Níveis de Acesso</a>
                                </li>
                                <li>
                                    <a href="<?= BASE_URL ?>#">Campos extras</a>
                                </li>
                                <li>
                                    <a href="<?= BASE_URL ?>#">System Media Admin</a>
                                </li>
                                <li>
                                    <a href="<?= BASE_URL ?>#">Ativar / Modulos</a>
                                </li>
                                <li>
                                    <a href="<?= BASE_URL ?>#">Ativar Inativar Plugins</a>
                                </li>                                
                                <li>
                                    <a href="<?= BASE_URL ?><?= BASE_URL ?>/pages/pagina_teste.php">Teste Page</a>
                                </li>
                            </ul>
                            <!-- /.nav-second-level -->
                        </li>
                    </ul>
                </div>
            </aside>
            <!-- /.sidebar -->            