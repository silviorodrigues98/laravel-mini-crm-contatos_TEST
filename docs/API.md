<!-- generated-by: gsd-doc-writer -->

## API.md

### Autenticação

Nenhum requisito de autenticação foi definido para esta API. Todos os endpoints são públicos.

### Endpoints

| Método | URL | Descrição | Corpo da Requisição | Código de Resposta | Exemplo de Resposta |
|--------|-----|-----------|----------------------|---------------------|----------------------|
| **GET** | `/api/contacts` | Lista contatos (paginação). | - | `200` | ```json
{
  "data": [
    {"id":1,"name":"João","email":"joao@example.com","phone":"11999999999","status":"active"}
  ],
  "links": {"first":"...","last":"...","prev":null,"next":null},
  "meta": {"current_page":1,"from":1,"last_page":1,"path":"/api/contacts","per_page":15,"to":1,"total":1}
}
```
| **POST** | `/api/contacts` | Cria novo contato. | ```json
{
  "name": "Maria",
  "email": "maria@example.com",
  "phone": "+55 11 98765-4321"
}
``` | `201` | ```json
{"data":{"id":2,"name":"Maria","email":"maria@example.com","phone":"11987654321","status":"pending"}}
```
| **GET** | `/api/contacts/{id}` | Exibe detalhes do contato. | - | `200` `404` | `200` -> ```json
{"data":{"id":1,"name":"João","email":"joao@example.com","phone":"11999999999","status":"active"}}
```
`404` -> ```json
{"message":"Not Found"}
```
| **PUT** | `/api/contacts/{id}` | Atualiza contato existente. | ```json
{"name":"João Silva","email":"joao.silva@example.com"}
``` | `200` `404` | `200` -> ```json
{"data":{"id":1,"name":"João Silva","email":"joao.silva@example.com","phone":"11999999999","status":"active"}}
```
`404` -> ```json
{"message":"Not Found"}
```
| **DELETE** | `/api/contacts/{id}` | Remove contato (soft‑delete). | - | `204` `404` | `204` sem corpo. `404` -> ```json
{"message":"Not Found"}
```
| **POST** | `/api/contacts/{id}/process-score` | Enfileira cálculo assíncrono de pontuação. | - | `202` `404` | `202` -> ```json
{"message":"Score processing queued.","contact_id":1}
```
`404` -> ```json
{"message":"Not Found"}
```

### Paginação

A lista de contatos (`GET /api/contacts`) aceita os parâmetros de query `page` (página) e `per_page` (itens por página, padrão 15). A resposta inclui os campos padrão de paginação do Laravel (`links` e `meta`).

### Soft Delete

O endpoint `DELETE /api/contacts/{id}` realiza um *soft delete* (marca `deleted_at`). O registro permanece no banco de dados, permitindo restauração futura.

### Processamento Assíncrono de Pontuação

Ao chamar `POST /api/contacts/{id}/process-score` o contato é colocado na fila `ProcessContactScoreJob`. O job aguarda de 1 a 2 segundos (simulação) e calcula a pontuação baseada em domínio de e‑mail, tamanho do nome e DDD do telefone. Quando concluído, o status do contato muda de `pending` → `processing` → `active` ou `failed`.

### Atualizações em Tempo Real via Reverb/WebSocket

Após a pontuação ser calculada, o evento `ContactScoreProcessed` dispara um broadcast pelo canal `contacts.{id}`. Clientes podem escutar esse canal usando Reverb:

```html
<script src="https://cdn.jsdelivr.net/npm/laravel-echo/dist/echo.iife.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pusher-js/dist/web/pusher.min.js"></script>
<script>
    const echo = new Echo({
        broadcaster: 'reverb',
        key: 'reverb-key', // <-- substituir pela chave configurada
        wsHost: 'reverb.myapp.test',
        wsPort: 8080,
        forceTLS: false,
    });
    echo.channel('contacts.1')
        .listen('.ContactScoreProcessed', (e) => {
            console.log('Pontuação atualizada:', e);
        });
</script>
```

> **Nota:** Substitua `reverb-key` e `wsHost` pelos valores configurados no seu ambiente (`.env`).

### Validação e Formatação de Resposta

- **Validação:** As requisições `POST` e `PUT` utilizam *Form Requests* (`StoreContactRequest` e `UpdateContactRequest`). As regras de validação garantem campos obrigatórios, formato de e‑mail e regex para telefone.
- **Formatação:** As respostas são formatadas via *API Resources* (`ContactResource` e `ContactCollection`), retornando JSON padrão da API Laravel.

### Referência ao Spec Original

Consulte `docs/original/README.md` para detalhes sobre a lógica de negócio de cálculo de pontuação, enum de status (`pending`, `processing`, `active`, `failed`) e demais regras de domínio.

### Teste Manual com REST Client

Para testar a API manualmente com a extensão **REST Client** do VS Code, utilize o arquivo `api-tests.http` na raiz do projeto. Ele contém todos os requests organizados por cenário (CRUD de contatos, processamento de score, validações) e pode ser executado diretamente no editor. Cada request inclui comentários explicando o cenário e o resultado esperado.
