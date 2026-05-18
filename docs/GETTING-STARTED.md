<!-- generated-by: gsd-doc-writer -->
# Guia de Início Rápido

Este guia fornece os passos necessários para configurar e executar o projeto Mini CRM de Contatos em seu ambiente de desenvolvimento local.

## Pré-requisitos

Antes de começar, certifique-se de que você tem os seguintes softwares instalados:

- **PHP** >= 8.3 (verifique com `php -v`)
- **Composer** (verifique com `composer --version`)
- **Node.js** >= 18.0 (verifique com `node -v`)
- **npm** (verifique com `npm -v`)
- **Redis** (para filas e broadcasting em produção; opcional para testes, pois o ambiente de teste usa drivers síncronos)

> **Nota:** O projeto pode ser executado sem Redis em ambiente de teste usando os drivers `sync` (para filas) e `null` (para broadcasting), mas para o funcionamento completo do recurso de score assíncrono e atualizações em tempo real, o Redis é necessário.

## Passos de Instalação

Siga estes passos para configurar o projeto:

1. **Instalar dependências PHP**
   ```bash
   composer install
   ```

2. **Instalar dependências de frontend**
   ```bash
   npm install
   ```

3. **Configurar variáveis de ambiente**
   ```bash
   cp .env.example .env
   ```
   Edite o arquivo `.env` para configurar:
   - Conexão com o banco de dados (SQLite por padrão para desenvolvimento)
   - Configurações do Redis (se desejar usar filas e broadcasting reais)
   - Chaves do Reverb (`REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`)

4. **Gerar chave da aplicação**
   ```bash
   php artisan key:generate
   ```

5. **Executar migrations**
   ```bash
   php artisan migrate
   ```

6. **(Opcional) Popular o banco de dados com dados de exemplo**
   ```bash
   php artisan db:seed
   ```

## Primeira Execução

Para iniciar a aplicação completa, você precisa de três processos executando simultaneamente:

### Terminal 1: Servidor HTTP da API
```bash
php artisan serve
```

### Terminal 2: Processador de fila (para jobs assíncronos)
```bash
php artisan queue:work
```

### Terminal 3: Servidor WebSocket (Reverb) para transmissão em tempo real
```bash
php artisan reverb:start
```

### Comando Integrado (Recomendado)

Você pode usar o comando integrado que inicia todos os processos acima em painéis separados:
```bash
composer run dev
```

Este comando utiliza `concurrently` para gerenciar os processos e será interrompido se qualquer um deles falhar.

> **Importante:** O servidor Reverb deve estar rodando para que as transmissões WebSocket funcionem. Sem ele, os eventos são enfileirados mas não entregues aos clientes.

## Executando os Testes

O projeto segue TDD com testes unitários (Domain/Application) e de feature (endpoints + banco de dados).

```bash
# Executar suite completa
php artisan test

# Alternativa via Composer
composer run test

# Filtrar por classe de teste específica
php artisan test --filter=ContactApiTest
php artisan test --filter=ScoreProcessingTest
php artisan test --filter=ScoreCalculatorTest
```

A configuração de teste (`phpunit.xml`) já define `QUEUE_CONNECTION=sync` para execução síncrona de jobs e `BROADCAST_CONNECTION=null` para desabilitar broadcasting durante os testes.

## Teste Manual da API (REST Client)

Além dos testes automatizados, você pode testar a API manualmente usando a extensão **REST Client** para VS Code.

### Extensão Necessária

Instale a extensão [REST Client](https://marketplace.visualstudio.com/items?itemName=humao.rest-client) no VS Code.

### Pré-requisitos

1. Execute `php artisan migrate:fresh --seed` para criar o banco de dados com dados de exemplo
2. Execute `php artisan serve` para iniciar o servidor (disponível em `http://localhost:8000`)
3. (Opcional) Execute `php artisan queue:work` em outro terminal para processar jobs de score

### Arquivo de Testes

O projeto inclui o arquivo `api-tests.http` na raiz do projeto, que contém todos os requests organizados por cenário:

- **CONTACTS — CRUD**: Listar, criar, atualizar e excluir contatos
- **SCORE PROCESSING**: Trigger de processamento de score e verificação
- **SCORING RULES REFERENCE**: Tabela de referência das regras de pontuação

### Como Usar

1. Abra o arquivo `api-tests.http` no VS Code
2. Clique em **"Send Request"** acima de qualquer bloco de request
3. O arquivo já define a variável `@baseUrl = http://localhost:8000/api`
4. Os requests incluem comentários explicando cada cenário e o resultado esperado

### Cobertura dos Testes

O `api-tests.http` cobre:
- Criação de contatos (válidos, inválidos, email duplicado)
- Listagem com paginação
- Exibição de contato individual
- Atualização de contatos
- Exclusão (soft delete)
- Processamento de score assíncrono
- Validação de status machine (transições inválidas)
- Verificação de pontuação calculada

## Exemplo de Listener WebSocket

Para acompanhar as atualizações de score de um contato em tempo real via WebSocket, utilize o seguinte exemplo HTML/JavaScript:

```html
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Score em Tempo Real</title>
</head>
<body>
    <h1>Acompanhamento de Score</h1>
    <div id="contact-info">
        <p>Aguardando atualizações...</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16/dist/echo.iife.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8/dist/pusher.min.js"></script>
    <script>
        window.Pusher = Pusher;

        const echo = new Echo({
            broadcaster: 'reverb',
            key: 'YOUR_REVERB_APP_KEY', // Substitua pelo valor real de REVERB_APP_KEY no .env
            wsHost: window.location.hostname,
            wsPort: 8080,
            wssPort: 8080,
            forceTLS: false,
            enabledTransports: ['ws', 'wss'],
        });

        const contactId = 1; // Altere para o ID do contato desejado

        echo.channel(`contacts.${contactId}`)
            .listen('ContactScoreProcessed', (e) => {
                console.log('Score atualizado:', e);
                document.getElementById('contact-info').innerHTML = `
                    <p><strong>ID:</strong> ${e.contact_id}</p>
                    <p><strong>Email:</strong> ${e.email}</p>
                    <p><strong>Score:</strong> ${e.score}</p>
                    <p><strong>Status:</strong> ${e.status}</p>
                `;
            });
    </script>
</body>
</html>
```

> **Importante:** Substitua `YOUR_REVERB_APP_KEY` pelo valor da variável `REVERB_APP_KEY` no seu arquivo `.env`. Mantenha o servidor Reverb rodando (`php artisan reverb:start`) para que as transmissões funcionem.

## Próximos Passos

Após ter a aplicação running, consulte o [README.md](./README.md) para:
- Detalhes sobre a arquitetura DDD
- Lista completa de endpoints da API
- Informações avançadas de configuração
- Diretrizes para contribuição e desenvolvimento

<!-- VERIFY: Redis é necessário para filas e broadcasting em produção -->