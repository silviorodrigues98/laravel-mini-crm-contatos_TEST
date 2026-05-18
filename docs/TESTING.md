<!-- generated-by: gsd-doc-writer -->

# Testes

Guia completo para execução e escrita de testes no Mini CRM de Contatos.

## Framework de Teste e Configuração

O projeto utiliza **PHPUnit** (`phpunit/phpunit: ^12.5`) como framework de teste, com duas suites configuradas em `phpunit.xml`:

- **Unit**: Testes de unidade para a camada de Domain e Application (mocks de infraestrutura)
- **Feature**: Testes de integração para endpoints, banco de dados e filas

### Configurações de Ambiente para Testes

O arquivo `phpunit.xml` configura automaticamente o ambiente de teste:

```xml
<env name="APP_ENV" value="testing"/>
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="BROADCAST_CONNECTION" value="null"/>
```

- **Banco de dados**: SQLite em memória para testes rápidos
- **Fila**: Conexão `sync` para execução síncrona de jobs durante testes
- **Broadcast**: Desabilitado durante testes (não há WebSocket ativo)

## Executando Testes

### Executar Suite Completa

```bash
php artisan test
```

Ou via Composer:

```bash
composer run test
```

### Filtrar por Classe de Teste

```bash
# Executar uma classe específica
php artisan test --filter=ContactApiTest

# Executar múltiplas classes
php artisan test --filter=ScoreProcessingTest
php artisan test --filter=ScoreCalculatorTest
```

### Filtrar por Método de Teste

```bash
# Executar um método específico
php artisan test --filter="test_can_create_a_contact"
```

### Executar Apenas Testes de Unidade ou Feature

```bash
# Apenas testes de unidade
php artisan test --testsuite=Unit

# Apenas testes de feature
php artisan test --testsuite=Feature
```

## Estrutura dos Testes

Os arquivos de teste estão localizados no diretório `tests/` com a seguinte organização:

```
tests/
├── Feature/                    # Testes de integração (endpoints + DB + filas)
│   ├── ContactApiTest.php     # CRUD de contatos via API
│   ├── ScoreProcessingTest.php # Processamento de score assíncrono
│   └── ObserverTest.php       # Testes de observer do modelo Contact
├── Unit/                       # Testes de unidade (Domain/Application)
│   ├── Application/
│   │   └── UseCases/
│   │       └── ProcessScoreUseCaseTest.php
│   ├── Domain/
│   │   ├── Services/
│   │   │   ├── ScoreCalculatorTest.php
│   │   │   └── Scoring/
│   │   │       ├── EmailDomainScoringStrategyTest.php
│   │   │       ├── NameLengthScoringStrategyTest.php
│   │   │       └── PhoneDddScoringStrategyTest.php
│   │   └── ValueObjects/
│   └── Infrastructure/
│       ├── Events/
│       │   └── ContactScoreProcessedTest.php
│       └── Listeners/
│           └── LogContactScoreProcessedTest.php
└── TestCase.php               # Classe base para todos os testes
```

### Testes de Unidade (Unit)

Testam a camada de **Domain** e **Application** de forma isolada, mockando dependências externas como repositórios e serviços externos. Seguem o padrão PHPUnit puro (`PHPUnit\Framework\TestCase`).

Exemplo: `tests/Unit/Domain/Services/Scoring/EmailDomainScoringStrategyTest.php`

```php
<?php

namespace Tests\Unit\Domain\Services\Scoring;

use Domain\Entities\Contact;
use Domain\Services\Scoring\EmailDomainScoringStrategy;
use Domain\ValueObjects\Email;
use Domain\ValueObjects\Phone;
use PHPUnit\Framework\TestCase;

class EmailDomainScoringStrategyTest extends TestCase
{
    private EmailDomainScoringStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new EmailDomainScoringStrategy();
    }

    public function test_corporate_email_adds_20_points(): void
    {
        $contact = Contact::create(
            'John Doe',
            new Email('john@company.com'),
            new Phone('11999999999')
        );

        $this->assertSame(20, $this->strategy->score($contact));
    }
}
```

### Testes de Feature (Integração)

Testam endpoints da API, interações com banco de dados e processamento de filas. Estendem `Tests\TestCase` e utilizam traits como `RefreshDatabase` e `WithoutMiddleware`.

Exemplo: `tests/Feature/ContactApiTest.php`

```php
<?php

namespace Tests\Feature;

use App\Infrastructure\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactApiTest extends TestCase
{
    use RefreshDatabase;

    const BASE_URL = '/api/contacts';

    public function test_can_create_a_contact(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '11999999999',
        ];

        $response = $this->postJson(self::BASE_URL, $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'phone', 'score', 'status'],
            ]);

        $this->assertDatabaseHas('contacts', [
            'email' => 'john@example.com',
            'phone' => '11999999999',
            'score' => 0,
            'status' => 'pending',
        ]);
    }
}
```

## Padrões de Teste

### Setup e Teardown

Use `setUp()` para inicializar dependências comuns antes de cada teste:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->strategy = new EmailDomainScoringStrategy();
}
```

Para testes de feature, use traits para gerenciar o estado do banco:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class ContactApiTest extends TestCase
{
    use RefreshDatabase;
}
```

### Assertions Comuns

**Verificar status HTTP:**
```php
$response->assertStatus(201);
$response->assertCreated(); // atalho
$response->assertNotFound(); // 404
```

**Verificar estrutura JSON:**
```php
$response->assertJsonStructure([
    'data' => ['id', 'name', 'email', 'phone', 'score', 'status'],
]);
```

**Verificar conteúdo JSON:**
```php
$response->assertJsonPath('data.name', 'John Doe');
$response->assertJson([
    'data' => ['name' => 'John Doe']
]);
```

**Verificar dados no banco:**
```php
$this->assertDatabaseHas('contacts', [
    'email' => 'john@example.com',
]);

$this->assertSoftDeleted('contacts', ['id' => $contact->id]);
```

**Verificar erros de validação:**
```php
$response->assertStatus(422);
$response->assertJsonValidationErrors(['name', 'email', 'phone']);
```

## Mocking em Testes de Domain

Os testes de unidade da camada de Domain e Application utilizam mocks para isolar completamente do framework Laravel.

### Mockando Repositório

```php
$repository = $this->createMock(ContactRepositoryInterface::class);
$repository->method('findById')->with(1)->willReturn($contact);
```

### Mockando Calculador de Score

```php
$calculator = $this->createMock(ScoreCalculator::class);
$calculator->method('calculate')->with($contact)->willReturn(new Score(60));
```

### Mockando Exceções

```php
$calculator = $this->createMock(ScoreCalculator::class);
$calculator->method('calculate')
    ->willThrowException(new \RuntimeException('Calculation failed'));
```

### Exemplo Completo com Injeção de Dependências

```php
public function test_processes_score_successfully(): void
{
    $contact = Contact::create(
        'John Doe',
        new Email('john@company.com'),
        new Phone('11999999999')
    );

    $repository = $this->createMock(ContactRepositoryInterface::class);
    $repository->method('findById')->with(1)->willReturn($contact);

    $calculator = $this->createMock(ScoreCalculator::class);
    $calculator->method('calculate')->with($contact)->willReturn(new Score(60));

    $useCase = new ProcessScoreUseCase($repository, $calculator);
    $useCase->execute(1);

    $this->assertSame(ContactStatus::Active, $contact->status());
    $this->assertSame(60, $contact->score()->value);
}
```

## Testando Filas e Broadcasting

### Fakes para Fila

```php
use Illuminate\Support\Facades\Queue;

Queue::fake();

// Executar ação que despacha job
$this->postJson('/api/contacts/1/process-score');

Queue::assertPushed(ProcessContactScoreJob::class, function ($job) use ($contact) {
    return $job->contactId === $contact->id;
});
```

### Fakes para Eventos

```php
use Illuminate\Support\Facades\Event;

Event::fake([ContactScoreProcessed::class]);

// Executar processamento
$this->postJson('/api/contacts/1/process-score');

Event::assertDispatched(ContactScoreProcessed::class, function ($event) use ($contact) {
    return $event->contactId === $contact->id
        && $event->score === 50
        && $event->status === 'active';
});
```

## Relação com Criteria de UAT (User Acceptance Testing)

Para documentação, **UAT criteria** (Critérios de Aceitação do Usuário) são os requisitos que definem se uma funcionalidade foi implementada corretamente do ponto de vista do usuário final. No contexto deste projeto:

- **Critérios de UAT para Score**: Um contato deve transitar de `pending → processing → active|failed` após processamento
- **Critérios de UAT para API**: Endpoints CRUD devem retornar códigos de status HTTP corretos e formatar resposta conforme especificado
- **Critérios de UAT para DDD**: Camada de Domain não deve importar facades ou classes do Laravel

Os testes escritos devem validar estes critérios, servindo como documentação executável da funcionalidade esperada. Quando um teste passa, está provando que aquele critério de UAT foi atendido.

## Exemplos de Classes de Teste

### Teste de Estratégia de Scoring por DDD

```php
// tests/Unit/Domain/Services/Scoring/PhoneDddScoringStrategyTest.php
public function test_sp_ddd_adds_20_points(): void
{
    $contact = Contact::create(
        'John Doe',
        new Email('john@company.com'),
        new Phone('11999999999') // DDD 11 = São Paulo
    );

    $this->assertSame(20, $this->strategy->score($contact));
}
```

### Teste de Use Case com Repositório Mockado

```php
// tests/Unit/Application/UseCases/ProcessScoreUseCaseTest.php
public function test_fails_on_exception(): void
{
    $calculator = $this->createMock(ScoreCalculator::class);
    $calculator->method('calculate')
        ->willThrowException(new \RuntimeException('Calculation failed'));

    $useCase = new ProcessScoreUseCase($repository, $calculator);
    $useCase->execute(1);

    $this->assertSame(ContactStatus::Failed, $contact->status());
    $this->assertSame(0, $contact->score()->value);
}
```

### Teste de Integração Completa

```php
// tests/Feature/ScoreProcessingTest.php
public function test_process_score_full_integration_with_sync_queue(): void
{
    $contact = Contact::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@company.com',
        'phone' => '11999999999',
        'status' => 'pending',
        'score' => 0,
    ]);

    $response = $this->postJson('/api/contacts/' . $contact->id . '/process-score');
    
    $response->assertStatus(202);

    // Verifica mudança de status e score calculado
    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'status' => 'active',
        'score' => 50, // 20 (email corp.) + 10 (nome completo) + 20 (DDD SP)
    ]);
}
```