<!-- generated-by: gsd-doc-writer -->

# Guia de Desenvolvimento

Este documento descreve o fluxo de desenvolvimento, a arquitetura DDD e as práticas recomendadas para contribuir com o Mini CRM de Contatos.

## Fluxo de Desenvolvimento (TDD)

O projeto segue o ciclo **red-green-refactor** do TDD:

### 1. Red (Vermelho)

Comece escrevendo um teste que falha:

```bash
# Crie um teste unitário
php artisan test --filter=NovoRecursoTest
```

O teste deve falhar porque a implementação ainda não existe.

### 2. Green (Verde)

Implemente o código mínimo necessário para passar no teste:

```bash
# Execute o teste novamente
php artisan test --filter=NovoRecursoTest
# Deve passar
```

### 3. Refactor (Refatorar)

Melhore o código mantendo os testes passando:

```bash
# Verifique se todos os testes ainda passam
php artisan test
```

## Arquitetura DDD (Domain-Driven Design)

O projeto está estruturado em três camadas principais:

```
src/
├── Domain/          # Regras de negócio puras, sem dependência do framework
│   ├── Entities/    # Contact (entidade principal)
│   ├── ValueObjects/# Email, Phone, Score
│   ├── Enums/       # ContactStatus
│   ├── Services/    # ScoreCalculator (aplica Strategy pattern)
│   └── Repositories/# ContactRepositoryInterface
└── Application/     # Casos de uso
    └── UseCases/    # CreateContactUseCase, ProcessScoreUseCase, etc.
```

### Camada Domain (Domínio)

- **Entities**: Entidades ricas com regras de negócio encapsuladas
- **Value Objects**: Email, Phone, Score - sem identidade, imutáveis
- **Domain Services**: ScoreCalculator com Strategy pattern
- **Repositories**: Interfaces que definem contratos de persistência

### Camada Application (Aplicação)

- **UseCases**: Orquestram operações de negócio
- Injeção de dependência via construtor
- Sem dependência de Laravel (exceto types/interfaces)

### Camada Infrastructure (Infraestrutura)

```
app/
├── Http/
│   ├── Controllers/ # ContactController
│   ├── Requests/    # StoreContactRequest, UpdateContactRequest
│   └── Resources/   # ContactResource
├── Jobs/            # ProcessContactScoreJob
├── Events/          # ContactScoreProcessed
├── Listeners/       # LogContactScoreProcessed
├── Infrastructure/
│   ├── Models/      # Contact (Eloquent)
│   ├── Repositories/# EloquentContactRepository
│   └── Observers/   # ContactObserver
└── Providers/       # AppServiceProvider (bindings)
```

## Como Criar Novos Use Cases

1. **Crie a classe do Use Case em `src/Application/UseCases/`**

```php
<?php

namespace Application\UseCases;

use Domain\Repositories\ContactRepositoryInterface;

class MeuNovoUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $repository,
    ) {}

    public function execute(int $contactId): void
    {
        $contact = $this->repository->findById($contactId);
        
        // Lógica de negócio aqui
    }
}
```

2. **Adicione o binding no `AppServiceProvider`**:

```php
// app/Providers/AppServiceProvider.php
$this->app->bind(
    \Application\UseCases\MeuNovoUseCase::class,
    fn($app) => new \Application\UseCases\MeuNovoUseCase(
        $app->make(ContactRepositoryInterface::class)
    )
);
```

3. **Escreva o teste unitário**:

```php
// tests/Unit/Application/UseCases/MeuNovoUseCaseTest.php
public function test_executa_com_sucesso(): void
{
    $repository = $this->createMock(ContactRepositoryInterface::class);
    // Configure mocks e asserções
}
```

## Como Adicionar Novas Regras de Score (Strategy Pattern)

As regras de score são implementadas usando o padrão Strategy. Cada estratégia deve implementar `ScoreScoringStrategy`:

1. **Crie uma nova estratégia em `src/Domain/Services/Scoring/`**:

```php
<?php

namespace Domain\Services\Scoring;

use Domain\Entities\Contact;

final class MinhaRegraScoringStrategy implements ScoreScoringStrategy
{
    public function score(Contact $contact): int
    {
        $points = 0;
        
        // Sua lógica de pontuação aqui
        // Exemplo: +15 pontos se nome tem mais de 10 caracteres
        if (strlen($contact->name()) > 10) {
            $points += 15;
        }
        
        return $points;
    }
}
```

2. **Registre a estratégia no ScoreCalculator**:

```php
// src/Domain/Services/ScoreCalculator.php
public function __construct()
{
    $this->strategies = [
        new EmailDomainScoringStrategy(),
        new NameLengthScoringStrategy(),
        new PhoneDddScoringStrategy(),
        new MinhaRegraScoringStrategy(), // Nova estratégia
    ];
}
```

3. **Escreva o teste da estratégia**:

```bash
php artisan test --filter=MinhaRegraScoringStrategyTest
```

## Executando o Queue Worker Localmente

O processamento de score é assíncrono via fila:

```bash
# Inicia o worker de fila
php artisan queue:work

# Com opções úteis
php artisan queue:work --tries=3 --timeout=60

# Para desenvolvimento, use queue:listen para auto-reload
php artisan queue:listen --tries=1 --timeout=0
```

Para desenvolvimento rápido, você pode usar:

```bash
# Executa com logs em tempo real (via Laravel Pail)
php artisan pail
```

## Executando o Servidor Reverb Localmente

O Reverb fornece WebSocket para atualizações em tempo real:

```bash
# Inicia o servidor Reverb
php artisan reverb:start

# Com configurações específicas
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Certifique-se de que `REVERB_APP_KEY` está configurado no `.env`.

## Banco de Dados para Desenvolvimento

O projeto usa SQLite para desenvolvimento e testes:

```bash
# Cria o arquivo do banco (se não existir)
touch database/database.sqlite

# Executa as migrations
php artisan migrate

# Reverte e recria tudo
php artisan migrate:fresh

# Com seeds
php artisan migrate:fresh --seed
```

Configuração no `.env`:

```
DB_CONNECTION=sqlite
DB_DATABASE=/caminho/absoluto/para/database/database.sqlite
```

## Debugging e Logs

### Laravel Pail (Logs em Tempo Real)

```bash
# Monitora logs em tempo real
php artisan pail

# Com filtros
php artisan pail --filter="ContactScoreProcessed"

# Verifica o log específico de contatos
tail -f storage/logs/contact.log
```

### Debug com Xdebug ou Ray

```php
// Em código PHP
ray($contact);
// ou
dd($contact);
```

## Padronização de Código (Laravel Pint)

O projeto usa Laravel Pint para formatação:

```bash
# Verifica formatação (dry-run)
vendor/bin/pint --test

# Corrige formatação automaticamente
vendor/bin/pint

# Verifica arquivos específicos
vendor/bin/pint app/Http/Controllers/ContactController.php
```

## Testes Unitários vs Feature Tests

### Testes Unitários

Localizados em `tests/Unit/`, testam a camada Domain e Application:

- Mockam dependências externas (repositórios, serviços)
- Executam rapidamente (sem banco de dados)
- Exemplo: `tests/Unit/Domain/Services/ScoreCalculatorTest.php`

```php
// Exemplo de teste unitário
$repository = $this->createMock(ContactRepositoryInterface::class);
$calculator = new ScoreCalculator();
```

### Feature Tests

Localizados em `tests/Feature/`, testam endpoints e integração:

- Usam SQLite em memória
- Executam o framework completo
- Simulam requisições HTTP reais
- Exemplo: `tests/Feature/ContactApiTest.php`

```php
// Exemplo de teste feature
$response = $this->postJson('/api/contacts', [
    'name' => 'João Silva',
    'email' => 'joao@empresa.com',
    'phone' => '(11) 99999-9999'
]);
```

### Executando Testes

```bash
# Suite completa
php artisan test

# Apenas testes unitários
php artisan test --testsuite=Unit

# Apenas testes de feature
php artisan test --testsuite=Feature

# Teste específico
php artisan test --filter=EmailDomainScoringStrategyTest
```

## Comandos Úteis Resumo

| Comando | Descrição |
|---------|-----------|
| `php artisan serve` | Inicia o servidor HTTP |
| `php artisan queue:work` | Worker de processamento de filas |
| `php artisan queue:listen` | Worker com auto-reload para desenvolvimento |
| `php artisan reverb:start` | Inicia o servidor WebSocket |
| `php artisan pail` | Monitor de logs em tempo real |
| `vendor/bin/pint` | Formata código com Laravel Pint |
| `php artisan test` | Executa a suite de testes |
| `composer run dev` | Inicia todos os serviços com concurrently |