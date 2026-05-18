<!-- generated-by: gsd-doc-writer -->
## CONFIGURATION.md

### Variáveis de Ambiente

| Variável | Obrigatório | Padrão | Descrição |
|----------|-------------|--------|-----------|
| APP_NAME | Não | Laravel | Nome da aplicação |
| APP_ENV | Não | local | Ambiente da aplicação (local, production, etc.) |
| APP_KEY | Sim | (gerado) | Chave de criptografia da aplicação |
| APP_DEBUG | Não | true | Ativa o modo de depuração |
| APP_URL | Não | http://localhost | URL base da aplicação |
| APP_LOCALE | Não | en | Local padrão da aplicação |
| APP_FALLBACK_LOCALE | Não | en | Local de fallback |
| APP_FAKER_LOCALE | Não | en_US | Local para geração de dados falsos |
| BCRYPT_ROUNDS | Não | 12 | Número de rounds para hash bcrypt |
| LOG_CHANNEL | Não | stack | Canal de log padrão |
| LOG_STACK | Não | single | Canais incluídos no stack de log |
| LOG_LEVEL | Não | debug | Nível de log |
| DB_CONNECTION | Não | sqlite | Conexão de banco de dados padrão |
| DB_HOST | Não | 127.0.0.1 | Host do banco de dados MySQL/MariaDB |
| DB_PORT | Não | 3306 | Porta do banco de dados |
| DB_DATABASE | Não | laravel | Nome do banco de dados |
| DB_USERNAME | Não | root | Usuário do banco de dados |
| DB_PASSWORD | Não | (vazio) | Senha do banco de dados |
| SESSION_DRIVER | Não | database | Driver de sessão |
| SESSION_LIFETIME | Não | 120 | Tempo de vida da sessão em minutos |
| BROADCAST_CONNECTION | Não | reverb | Conexão de broadcast padrão |
| REVERB_APP_ID | Não | contact-app | ID da aplicação Reverb |
| REVERB_APP_KEY | Sim | (vazio) | Chave da aplicação Reverb |
| REVERB_APP_SECRET | Sim | (vazio) | Segredo da aplicação Reverb |
| REVERB_HOST | Não | localhost | Host do servidor Reverb |
| REVERB_PORT | Não | 8080 | Porta do servidor Reverb |
| REVERB_SCHEME | Não | http | Esquema do servidor Reverb |
| FILESYSTEM_DISK | Não | local | Disco de armazenamento padrão |
| QUEUE_CONNECTION | Não | database | Conexão de fila padrão |
| CACHE_STORE | Não | database | Armazenamento de cache padrão |
| REDIS_CLIENT | Não | phpredis | Cliente Redis a ser usado |
| REDIS_HOST | Não | 127.0.0.1 | Host do servidor Redis |
| REDIS_PASSWORD | Não | null | Senha do servidor Redis |
| REDIS_PORT | Não | 6379 | Porta do servidor Redis |
| MAIL_MAILER | Não | log | Driver de correio |
| MAIL_HOST | Não | 127.0.0.1 | Host do servidor de correio |
| MAIL_PORT | Não | 2525 | Porta do servidor de correio |
| MAIL_USERNAME | Não | null | Usuário do servidor de correio |
| MAIL_PASSWORD | Não | null | Senha do servidor de correio |
| MAIL_FROM_ADDRESS | Não | hello@example.com | Endereço de remetente padrão |
| MAIL_FROM_NAME | Não | ${APP_NAME} | Nome do remetente padrão |
| AWS_ACCESS_KEY_ID | Não | (vazio) | ID da chave de acesso AWS |
| AWS_SECRET_ACCESS_KEY | Não | (vazio) | Chave de acesso secreta AWS |
| AWS_DEFAULT_REGION | Não | us-east-1 | Região padrão AWS |
| AWS_BUCKET | Não | (vazio) | Bucket S3 padrão |
| VITE_APP_NAME | Não | ${APP_NAME} | Nome da aplicação para Vite |

### Formato de Arquivos de Configuração

O projeto utiliza arquivos de configuração PHP no diretório `config/`. Cada arquivo retorna um array associativo com as configurações específicas para seu componente.

Exemplo de configuração de banco de dados (`config/database.php`):
```php
'connections' => [
    'sqlite' => [
        'driver' => 'sqlite',
        'url' => env('DB_URL'),
        'database' => env('DB_DATABASE', database_path('database.sqlite')),
        // ... outras configurações
    ],
    'mysql' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        // ... outras configurações
    ],
],
```

Exemplo de configuração de filas (`config/queue.php`):
```php
'connections' => [
    'sync' => [
        'driver' => 'sync',
    ],
    'database' => [
        'driver' => 'database',
        'connection' => env('DB_QUEUE_CONNECTION'),
        'table' => env('DB_QUEUE_TABLE', 'jobs'),
        'queue' => env('DB_QUEUE', 'default'),
        'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
    ],
    'redis' => [
        'driver' => 'redis',
        'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
    ],
],
```

Exemplo de configuração de broadcast (`config/broadcasting.php`):
```php
'connections' => [
    'reverb' => [
        'driver' => 'reverb',
        'key' => env('REVERB_APP_KEY'),
        'secret' => env('REVERB_APP_SECRET'),
        'app_id' => env('REVERB_APP_ID'),
        'options' => [
            'host' => env('REVERB_HOST'),
            'port' => env('REVERB_PORT', 443),
            'scheme' => env('REVERB_SCHEME', 'https'),
            'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
        ],
    ],
],
```

### Configurações Obrigatórias vs Opcionais

**Configurações Obrigatórias** (causam falha na inicialização se ausentes):
- `APP_KEY` - Chave de criptografia da aplicação (gerada durante a instalação)
- `REVERB_APP_KEY` - Chave da aplicação Reverb para WebSockets
- `REVERB_APP_SECRET` - Segredo da aplicação Reverb para WebSockets

**Configurações Opcionais** (possui valores padrão):
- Todas as outras variáveis de ambiente listadas acima têm valores padrão definidos nos arquivos de configuração.

### Padrões

Os valores padrão para configurações opcionais são definidos nos arquivos de configuração do Laravel:

- Cache: `database` (tabela `cache`)
- Filas: `database` (tabela `jobs`)
- Sessões: `database` (tabela `sessions`)
- Broadcast: `reverb` (com fallback para `null`)
- Log: `stack` (canal único com nível `debug`)

### Sobrescritas por Ambiente

O Laravel suporta arquivos de ambiente específicos para diferentes estágios:
- `.env` - Ambiente padrão
- `.env.local` - Sobrescreve .env em desenvolvimento local
- `.env.[environment]` - Ambiente específico (ex: `.env.production`)
- `.env.testing` - Ambiente de testes

Para configuração específica do Reverb em diferentes ambientes, variáveis como `REVERB_HOST`, `REVERB_PORT` e `REVERB_SCHEME` podem ser sobrescritas nos arquivos de ambiente específicos.

### Detalhes de Infraestrutura

<!-- VERIFY: REVERB_APP_ID -->
O identificador da aplicação Reverb (`REVERB_APP_ID`) é definido como `contact-app` no arquivo `.env.example`.

<!-- VERIFY: QUEUE_CONNECTION padrão -->
A conexão de fila padrão é configurada como `database` no arquivo `.env.example`, usando a tabela `jobs` para armazenamento de filas.

<!-- VERIFY: CACHE_STORE padrão -->
O armazenamento de cache padrão é configurado como `database` no arquivo `.env.example`, usando a tabela `cache`.

<!-- VERIFY: REDIS_HOST e REDIS_PORT padrão -->
O servidor Redis é configurado para `127.0.0.1:6379` por padrão no arquivo `.env.example`.

<!-- VERIFY: MAIL_MAILER padrão -->
O driver de correio padrão é configurado como `log` no arquivo `.env.example`, que registra emails nos logs ao invés de enviá-los.

### Service Providers e Bindings do Laravel

O Laravel utiliza Service Providers para vincular interfaces a implementações. No contexto do DDD deste projeto:

- Os repositórios do domínio são vinculados às suas implementações Eloquent em providers de serviço.
- Os eventos de domínio (como `ContactScoreProcessed`) têm listeners que lidam com logging e broadcast via Reverb.
- Os Form Requests são automaticamente resolvidos pelo container de serviço para validação de entrada.
- Os API Resources são usados para transformar modelos Eloquent em respostas JSON consistentes.

Os service providers podem ser encontrados em `app/Providers/` e são carregados automaticamente pelo Laravel durante a inicialização.