<!-- generated-by: gsd-doc-writer -->
## ARCHITECTURE.md

**Visão geral do sistema**  
Uma API REST para gerenciamento de contatos com acompanhamento em tempo real da evolução do score (pontuação). Os usuários criam contatos, disparam o processamento assíncrono do score (baseado em regras de domínio: domínio do e-mail, comprimento do nome e DDD do telefone) e recebem atualizações ao vivo via WebSocket enquanto o contato transita pelos estados `pendente → processando → ativo|falho`. O sistema segue uma arquitetura baseada em Domain-Driven Design (DDD) com separação estrita de camadas: Domínio (regras de negócio puras), Aplicação (casos de uso) e Infraestrutura (integração com Laravel, como controladores, jobs, eventos e modelos Eloquent).

**Diagrama de componentes**  
```mermaid
graph TD
    A[Camada de Apresentação] --> B[Controladores HTTP]
    B --> C[Casos de Use (Application)]
    C --> D[Serviços de Domínio]
    C --> E[Repositórios (Interfaces)]
    E --> F[Implementações Eloquent (Infraestrutura)]
    D --> G[Objetos de Valor]
    D --> H[Entidades]
    D --> I[Enums de Domínio]
    C --> J[Events de Domínio]
    J --> K[Listeners (Infraestrutura)]
    K --> L[Log de Arquivo]
    K --> M[Broadcast via Reverb]
    B --> N[Jobs de Fila (Infraestrutura)]
    N --> O[Processamento Assíncrono do Score]
    O --> C
```

**Fluxo de dados**  
1. **Criação de contato**: Requisição HTTP POST para `/api/contacts` é recebida por um `ContactController` (Infraestrutura), que valida os dados usando um `StoreContactFormRequest` (Infraestrutura) e delega a criação ao `CreateContactUseCase` (Aplicação). O caso de uso instancia uma entidade `Contact` (Domínio) usando os objetos de valor `Email`, `Phone` e inicializa o score como zero e status como `pending`. O contato é persistido via repositório Eloquent (Infraestrutura) e retornado como recurso JSON usando `ContactResource` (Infraestrutura).

2. **Processamento de score**: Requisição HTTP POST para `/api/contacts/{id}/process-score` é tratada pelo `ContactController`, que valida a existência do contato e dispõe o job `ProcessContactScoreJob` (Infraestrutura) na fila Redis. O job, ao ser executado, altera o status do contato para `processing` via repositório, calcula o novo score usando o `ScoreCalculator` (Serviço de Domínio que aplica o padrão Strategy com estratégias para e-mail, nome e telefone), atualiza o contato com o novo score e status (`active` ou `failed`), e dispara o evento de domínio `ContactScoreProcessed`.

3. **Reação ao evento**: O evento `ContactScoreProcessed` é ouvido por dois listeners (Infraestrutura):  
   - `LogContactScoreProcessedListener`: grava no arquivo `storage/logs/contact.log` o ID do contato, e-mail, novo score e status.  
   - `BroadcastContactScoreProcessedListener`: transmite a atualização do contato via Laravel Reverb no canal `contacts.{id}` para clientes WebSocket conectados.

**Abstrações-chave**  
- `Domain\Entities\Contact`: Entidade rica que encapsula o estado e as transições de status do contato, garantindo consistência através de métodos como `markAsProcessing()`, `markAsActive()` e `markAsFailed()`.  
- `Domain\ValueObjects\Email`, `Phone`, `Score`: Objetos de valor que validam e encapsulam seus respectivos dados, impedindo o uso de tipos primitivos brutos nas entidades.  
- `Domain\Enums\ContactStatus`: Enum que define os estados possíveis do contato (`pending`, `processing`, `active`, `failed`) e regras de transição entre eles.  
- `Domain\Repositories\ContactRepositoryInterface`: Interface que define o contrato para persistência de contatos, permitindo que a camada de aplicação trabalha com abstrações e não com implementações específicas de ORM.  
- `Domain\Services\ScoreCalculator`: Serviço de domínio que orquestra o cálculo do score usando o padrão Strategy, delegando para estratégias específicas (`EmailDomainScoringStrategy`, `NameLengthScoringStrategy`, `PhoneDddScoringStrategy`).  
- `Application\UseCases\*`: Casos de uso (ex: `CreateContactUseCase`, `ProcessScoreUseCase`) que orquestram as operações da aplicação, dependendo apenas de interfaces de domínio (repositórios, serviços).  
- `App\Infrastructure\Observers\ContactObserver`: Observer do Laravel que normaliza o formato do telefone antes de salvar (método `saving`), mantendo a lógica de formatação fora da entidade.  
- `App\Infrastructure\Jobs\ProcessContactScoreJob`: Job enfileirado que processa o score assincronamente, incluindo um `sleep(1-2)` para simular carga de processamento.  
- `App\Infrastructure\Http\Resources\ContactResource`: API Resource que padroniza a saída JSON dos contatos, ocultando detalhes internos da entidade.  
- `App\Infrastructure\Http\Requests\StoreContactFormRequest` e `UpdateContactFormRequest`: Form Requests que centralizam a validação de entrada HTTP, reutilizando regras entre criação e atualização.

**Racionalização da estrutura de diretórios**  
- `src/`: Contém todo o código puro de domínio e aplicação, agnosticado do Laravel.  
  - `Domain/`: Regras de negócio puras (entidades, value objects, enums, serviços, interfaces de repositório).  
  - `Application/`: Casos de uso que orquestram fluxos de negócio, dependendo apenas de interfaces definidas em `Domain/`.  
- `app/`: Infraestrutura específica do Laravel, adaptando a aplicação ao framework.  
  - `Http/`: Controladores, middleware, form requests e API resources (camada de apresentação).  
  - `Infrastructure/`: Implementações de repositórios (Eloquent), jobs, events, listeners, observers e outras adaptações ao Laravel.  
  - `Models/`: Modelos Eloquent que implementam as interfaces de repositório de `Domain/`, mapeando entidades para tabelas do banco de dados.  
- `config/`: Arquivos de configuração do Laravel (broadcasting, queue, services, etc.).  
- `routes/`: Definição das rotas API (`api.php`).  
- `tests/`: Testes automatizados, separados por `Feature/` (testes de integração) e `Unit/` (testes de unidade com mocks).  
- `database/`: Migrations e seeders para o banco de dados.  
- `resources/js/`: Frontend básico para demonstração de WebSocket (conforme especificação).  
- `storage/logs/`: Arquivo de log onde os listeners gravam o processamento de score.  

Essa separação garante que as regras de negócio (em `src/`) possam ser desenvolvidas e testadas independentemente do Laravel, facilitando a manutenção, a testabilidade e a adaptação a outros frameworks ou plataformas no futuro.