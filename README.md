# Mini CRM de Contatos

> **Este projeto foi construído usando Spec Driven Development com agentes de IA.**
> Arquiteto e orquestrador: **[silviorodrigues98](https://github.com/silviorodrigues98)**.

Uma API REST para gerenciamento de contatos com cálculo de score assíncrono e atualizações em tempo real via WebSocket. Os contatos passam pelos status `pending → processing → active|failed` enquanto o score é calculado com base em regras de domínio (domínio de email, tamanho do nome, DDD do telefone).

Construído como desafio técnico demonstrando DDD, SOLID e TDD no ecossistema Laravel.

## Tech Stack

- **Framework:** Laravel 13 (REST API)
- **Banco de dados:** SQLite (desenvolvimento/teste) / MySQL (produção)
- **Fila assíncrona:** Redis (produção) / `sync` (teste)
- **WebSocket:** Laravel Reverb
- **Arquitetura:** DDD com camadas Domain, Application e Infrastructure

## Setup

```bash
# Instalar dependências
composer install

# Configurar ambiente
cp .env.example .env
# Edite .env com suas configurações de banco de dados

# Gerar chave da aplicação
php artisan key:generate

# Executar migrations
php artisan migrate

# (Opcional) Popular banco com dados de exemplo
php artisan db:seed
```

## Executando a Aplicação

Para rodar a aplicação completa, você precisa de três processos:

```bash
# Terminal 1: Servidor HTTP da API
php artisan serve

# Terminal 2: Processador de fila (para jobs assíncronos)
php artisan queue:work

# Terminal 3: Servidor WebSocket (Reverb) para transmissão em tempo real
php artisan reverb:start
```

Ou utilize o comando integrado:

```bash
composer run dev
```

> **Nota:** O servidor Reverb deve estar rodando para que as transmissões WebSocket funcionem. Sem ele, os eventos são enfileirados mas não entregues aos clientes.

## Endpoints da API

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET` | `/api/contacts` | Listar contatos (paginado, 15 por página) |
| `POST` | `/api/contacts` | Criar novo contato |
| `GET` | `/api/contacts/{id}` | Exibir detalhes de um contato |
| `PUT` | `/api/contacts/{id}` | Atualizar um contato |
| `DELETE` | `/api/contacts/{id}` | Excluir um contato (soft delete) |
| `POST` | `/api/contacts/{id}/process-score` | Processar score do contato (assíncrono) |

### Exemplo: Criar Contato

```bash
curl -X POST http://localhost:8000/api/contacts \
  -H "Content-Type: application/json" \
  -d '{"name": "João Silva", "email": "joao@empresa.com", "phone": "(11) 99999-8888"}'
```

### Exemplo: Processar Score

```bash
curl -X POST http://localhost:8000/api/contacts/1/process-score
# Resposta: 202 Accepted
# { "message": "Score processing queued.", "contact_id": 1 }
```

## Acompanhamento em Tempo Real

O sistema transmite atualizações de score em tempo real via WebSocket (Laravel Reverb) no canal `contacts.{id}` sempre que um score é processado.

Para acompanhar as atualizações de um contato específico, utilize o seguinte HTML/JS:

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

## Testes

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

## Arquitetura

O projeto segue os princípios de DDD (Domain-Driven Design) com três camadas principais:

```
src/
├── Domain/          # Entidades, Value Objects, Enums, Interfaces de Repositório
│   ├── Entities/    # Contact (entidade principal)
│   ├── Enums/       # ContactStatus
│   ├── ValueObjects/# Email, Phone, Score
│   ├── Services/    # ScoreCalculator (Strategy pattern)
│   └── Repositories/# ContactRepositoryInterface
├── Application/     # Casos de uso
│   └── UseCases/    # CreateContactUseCase, ProcessScoreUseCase

app/
├── Events/          # Eventos ShouldBroadcast (ContactScoreProcessed)
├── Listeners/       # LogContactScoreProcessed
├── Jobs/            # ProcessContactScoreJob
├── Infrastructure/  # Models, Repositories, Observers
│   ├── Models/      # Contact (Eloquent)
│   ├── Repositories/# EloquentContactRepository
│   └── Observers/   # ContactObserver
├── Http/
│   ├── Controllers/ # ContactController
│   ├── Requests/    # StoreContactRequest, UpdateContactRequest
│   └── Resources/   # ContactResource
└── Providers/       # AppServiceProvider (bindings + observers)
```

### Fluxo de Processamento de Score

1. Cliente envia `POST /api/contacts/{id}/process-score`
2. Controller despacha `ProcessContactScoreJob` para a fila
3. Job executa `ProcessScoreUseCase` que calcula o score (Strategy pattern)
4. Após o cálculo, o job dispara o evento `ContactScoreProcessed`
5. O listener `LogContactScoreProcessed` registra no log (`storage/logs/contact.log`)
6. O evento (`ShouldBroadcast`) transmite via Reverb no canal `contacts.{id}`
