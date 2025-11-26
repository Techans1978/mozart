# 🎼 Mozart — Plataforma Operacional Integrada Super ABC
**Sistema modular com Help Desk, Ativos, BPM, WhatsApp, Intranet e Orquestrador de APIs.**

O **Mozart** é uma plataforma centralizada, projetada para integrar operações de atendimento, gestão de ativos, orquestração de processos, comunicação via WhatsApp, gestão documental e intranet corporativa — tudo em um único ecossistema.

A arquitetura é modular, expansível e organizada para facilitar evolução contínua, novos módulos e governança por RBAC (Role-Based Access Control).

---

# 📦 Recursos Principais

### 🔧 **Gerenciamento (Core System)**
- Usuários, níveis de acesso, permissões (RBAC completo)  
- Auditoria detalhada de ações  
- Administração de empresas e unidades  
- Dashboard central  

### 📞 **Help Desk**
- Abertura e gestão de chamados  
- Painel do agente (Inbox)  
- SLAs, categorias, formulários e templates  
- Relatórios e agendamentos  

### 💼 **Gestão de Ativos**
- Cadastro e controle de ativos  
- Marcas, modelos, fornecedores  
- Importações em massa  
- Portal do Ativo (via QR Code)

### 💬 **WPP Chat**
- Conexão com WhatsApp (WPPConnect)  
- Instâncias, sessões e QR Code  
- Campanhas, templates, histórico de mensagens  

### 🔄 **BPM – Modelador e Engine**
- Designer BPMN  
- Deploy de processos  
- Execução e monitoramento de instâncias  
- Conectores e formulários BPM  

### 📰 **Intranet**
- Notícias, documentos e comunicados  
- Agenda interna e eventos  

### 🔌 **Orquestrador de API**
- Conectores externos  
- Criação de fluxos  
- Credenciais/Segredos  
- Execuções manuais e automáticas  

---

# 🏗️ Arquitetura Geral

```
mozart/
│
├── public/                → arquivos públicos
├── system/                → núcleo da plataforma
│   ├── config/            → configs (config.php / config.example.php)
│   ├── core/              → classes centrais
│   ├── middleware/        → RBAC, autenticação
│   └── manifests/         → module_system.php, module_helpdesk.php, ...
│
├── modules/               → módulos funcionais
│   ├── helpdesk/
│   ├── gestao_ativos/
│   ├── wpp_chat/
│   ├── bpm/
│   ├── intranet/
│   └── orquestrador/
│
└── uploads/               → anexos / arquivos do usuário (gitignore)
```

---

# 🧩 Manifestos dos Módulos

Cada módulo possui um arquivo `module_*.php` em:

```
system/includes/manifest/
```

Ou dentro do módulo:

```
modules/<nome>/module.php
```

O manifesto define:
- nome do módulo
- menus do front/back
- capabilities (RBAC)
- rotas → permissões
- defaults de papéis

Exemplo resumido:

```php
return [
  'slug' => 'helpdesk',
  'name' => 'Help Desk',
  'capabilities' => [
    'helpdesk:tickets:read' => 'Ver chamados',
    'helpdesk:tickets:create' => 'Criar chamados'
  ],
  'menu' => [
    'back' => [
      [
        'label' => 'Listar Chamados',
        'route' => BASE_URL.'/modules/helpdesk/pages/tickets_listar.php',
        'requires' => ['helpdesk:tickets:read'],
      ]
    ]
  ],
  'routes' => [
    [ 'path' => '/modules/helpdesk/pages/tickets_listar.php', 'requires' => ['helpdesk:tickets:read'] ]
  ]
];
```

---

# 🔐 RBAC — Controle de Acesso

Mozart usa RBAC granular baseado em:

```
scope:resource:action
```

Exemplos:
- `helpdesk:tickets:read`
- `ativos:marcas:create`
- `bpm:processos:deploy`
- `whatsapp:instances:manage`

O middleware valida:
- Menus
- Acesso a páginas
- Acesso a ações por rota
- Acesso no front e no back

Caso negado, o usuário vê tela amigável de **Acesso Negado**.

---

# ⚙️ Instalação

## 1. Clonar repositório
```bash
git clone https://github.com/superabc/mozart.git
cd mozart
```

## 2. Criar arquivo de configuração
Nunca commitar `config.php`.

Use o exemplo:
```bash
cp system/config/config.example.php system/config/config.php
```

Edite com seus dados:
- host do banco
- usuário/senha
- nome do banco
- domínio
- opções do WPPConnect

## 3. Ajustar permissões de pastas
```
chmod -R 755 .
chmod -R 775 uploads/ cache/ logs/
```

---

# 🚀 Deploy (cPanel + Git)

## No servidor (uma vez):
```bash
cd ~/public_html
git init
git remote add origin https://github.com/superabc/mozart.git
git fetch
git checkout -t origin/main
```

## Para atualizar código:
```bash
cd ~/public_html
git pull
```

O `config.php` e uploads **não são sobrescritos**.

---

# 🧪 Ambiente de Desenvolvimento

Desenvolvimento local usando:
- VSCode
- Git
- Servidor local (XAMPP, Laragon, WAMP ou Docker)
- Banco MySQL local
- `config.php` local ignorado pelo Git

---

# 📁 .gitignore incluído

Ignora:
- `config.php`
- `uploads/`
- `logs/`
- `cache/`
- `.env`
- `vendor/`
- `node_modules/`
- backups e dumps (`*.sql`, `*.zip`)

---

# 🛠️ Ferramentas usuais
- PHP 8+
- MySQL/MariaDB
- cPanel/WHM
- Docker (WPPConnect, n8n)
- Git

---

# 📘 Próximos Módulos (Evolução Semanal)

Mozart evolui continuamente.
Novos módulos podem ser criados adicionando um manifesto em:
```
system/includes/manifest/module_novomodulo.php
```

---

# 🤝 Contribuição
Pull Requests são bem-vindos!

Siga boas práticas:
- Commits claros
- Branches semânticas (`feat/...`, `fix/...`)
- Código limpo e organizado

---

# 🧩 Contato
**Super ABC / Marcelo Teixeira**

Consultoria técnica via ChatGPT 🚀
