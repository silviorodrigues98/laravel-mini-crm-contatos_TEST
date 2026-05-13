# Mini CRM de Contatos

API REST para gerenciamento de contatos com processamento assíncrono de score e atualizações em tempo real via WebSocket.

Construído como desafio técnico demonstrando **DDD**, **SOLID**, **TDD** e fluência no ecossistema Laravel.

> **Documento original do desafio:** [`docs/original/README.md`](docs/original/README.md)

---

## Stack

| Camada     | Tecnologia                        |
|------------|-----------------------------------|
| Backend    | Laravel 11+                       |
| Banco      | MySQL / SQLite                    |
| Fila       | Redis                             |
| WebSocket  | Laravel Reverb                    |
| Testes     | PHPUnit (Feature + Unit)          |

## Setup

```bash
# 1. Clonar
git clone <repo-url>
cd laravel-mini-crm-contatos

# 2. Instalar dependências
composer install

# 3. Configurar ambiente
cp .env.example .env
# Ajuste DB_CONNECTION, REDIS_HOST, etc. conforme necessário

# 4. Gerar chave
php artisan key:generate

# 5. Rodar migrations
php artisan migrate

# 6. (Opcional) Seed
php artisan db:seed
```

## Arquitetura

O projeto segue os princípios de **Domain-Driven Design** com separação em três camadas:

```
src/
├── Domain/          # Entidades, Value Objects, interfaces de repositório
│   ├── Entities/
│   ├── ValueObjects/
│   └── Repositories/
├── Application/     # Use Cases / Actions
│   └── UseCases/
app/
└── Infrastructure/  # Laravel: Controllers, Eloquent, Jobs, Events, Listeners
    ├── Http/
    │   ├── Controllers/
    │   ├── Requests/
    │   └── Resources/
    ├── Repositories/
    ├── Jobs/
    ├── Events/
    ├── Listeners/
    └── Observers/
```

### Princípios

- **Domain Layer** é 100% agnóstica ao framework — sem facades, sem ORM
- **Use Cases** recebem interfaces de repositório por injeção de dependência
- **Laravel Service Container** faz o binding das implementações concretas (Eloquent)
- **Value Objects** para Email, Phone e Status (nunca strings cruas na entidade)
- **Strategy Pattern** no cálculo do score para facilitar extensão
- **Form Requests** para validação, **API Resources** para saída JSON
- **Observer** para normalização do telefone no evento `saving`

## Endpoints

### Contatos (CRUD)

| Método | Rota                  | Descrição                |
|--------|-----------------------|--------------------------|
| POST   | `/api/contacts`       | Criar contato            |
| GET    | `/api/contacts`       | Listar contatos (paginado) |
| GET    | `/api/contacts/{id}`  | Exibir contato           |
| PUT    | `/api/contacts/{id}`  | Atualizar contato        |
| DELETE | `/api/contacts/{id}`  | Excluir contato (soft)   |

### Processamento de Score

| Método | Rota                             | Descrição                              |
|--------|----------------------------------|----------------------------------------|
| POST   | `/api/contacts/{id}/process-score` | Dispara processamento assíncrono do score |

### Regras de Cálculo do Score

- **E-mail**: domínios corporativos (exceto gmail, hotmail, yahoo) → +20 pontos
- **E-mail**: terminação `.br` → +10 pontos
- **Nome**: mais de uma palavra → +10 pontos
- **Telefone**: DDD de São Paulo (11–19) → +20 pontos
- **Telefone**: DDD de outros estados → +10 pontos

### Fluxo de Status

```
pending → processing → active (sucesso)
                      → failed (falha)
```

## Testes

```bash
# Suite completa (Unit + Feature)
php artisan test

# Classe específica
php artisan test --filter=NomeDaClasse

# Método específico
php artisan test --filter="nome_do_metodo"
```

## Fila (Redis)

```bash
# Processar a fila
php artisan queue:work

# Em ambiente de desenvolvimento com múltiplos jobs
php artisan queue:work --tries=3
```

## WebSocket (Reverb)

```bash
# Iniciar servidor Reverb
php artisan reverb:start
```

### Exemplo de escuta no frontend

```html
<!DOCTYPE html>
<html>
<head>
    <title>Mini CRM — Listener</title>
</head>
<body>
    <div id="status"></div>

    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1/dist/echo.iife.js"></script>
    <script>
        const echo = new Echo({
            broadcaster: 'reverb',
            key: 'sua-chave',
            wsHost: window.location.hostname,
            wsPort: 8080,
            forceTLS: false,
            enabledTransports: ['ws', 'wss'],
        });

        const contactId = 1; // substituir pelo ID desejado
        echo.channel(`contacts.${contactId}`)
            .listen('ContactScoreProcessed', (e) => {
                document.getElementById('status').innerHTML = `
                    <p>Score atualizado: ${e.contact.score}</p>
                    <p>Status: ${e.contact.status}</p>
                `;
            });
    </script>
</body>
</html>
```

## Comandos Úteis

| Ação                          | Comando                          |
|-------------------------------|----------------------------------|
| Migrar                        | `php artisan migrate`            |
| Resetar banco + seed          | `php artisan migrate:fresh --seed` |
| Processar fila                | `php artisan queue:work`         |
| Iniciar Reverb                | `php artisan reverb:start`       |
| Rodar testes                  | `php artisan test`               |
| Cache de rotas                | `php artisan route:cache`        |

---

*Projeto baseado no desafio técnico descrito em [`docs/original/README.md`](docs/original/README.md).*
