````markdown
# Desafio Técnico – Mini CRM de Contatos

Construa uma pequena API REST em Laravel para gerenciar contatos e acompanhar, em tempo real, a evolução do score desses contatos quando um processamento assíncrono for executado.

A solução deve demonstrar o uso de:

- CRUD completo (HTTP JSON)  
- Jobs (dispatch + queue worker)  
- Observers
- Events & Listeners
- Laravel Reverb (broadcasting em tempo real)  


## 1. Escopo Funcional

### Modelo `Contact`

| Campo        | Tipo                 | Regras / Default                           |
|--------------|----------------------|--------------------------------------------|
| `id`         | bigint / PK          | auto-increment                             |
| `name`       | string               | obrigatório                                |
| `email`      | string único         | obrigatório \| formato e-mail              |
| `phone`      | string               | obrigatório                                |
| `score`      | integer              | default **0**                              |
| `processed_at` | timestamp nullable | preenchido após processamento do score     |
| Timestamps   | `created_at`, `updated_at`, `deleted_at` (soft delete)            |

### Endpoints CRUD

| Método | Rota                      | Ação                     |
|--------|---------------------------|--------------------------|
| POST   | `/api/contacts`           | Criar contato            |
| GET    | `/api/contacts`           | Listar contatos          |
| GET    | `/api/contacts/{id}`      | Mostrar contato          |
| PUT    | `/api/contacts/{id}`      | Atualizar contato        |
| DELETE | `/api/contacts/{id}`      | Excluir contato (soft)   |

### Fluxo Processar Score

1. Endpoint
   ```http
   POST /api/contacts/{id}/process-score
````

2. A rota dispatcha o job `ProcessContactScore` na fila contacts.

3. O job (simule carga pesada com `sleep(2)` ou cálculo aleatório) deve:

   * Atribuir um score aleatório entre 0 – 100.
   * Atualizar `processed_at`.
   * Disparar o evento `ContactScoreProcessed`.

4. **Listener**

   * Gravar em `storage/logs/contact.log` ➜ **ID, email, novo score, timestamp**.

5. **Broadcast** via **Reverb**

   * Canal público: `contacts.{id}`.
   * Front-end conectado recebe o objeto atualizado em tempo real.

---

## 2. Requisitos Técnicos

| Área             | Detalhes                                                                                                                                       |
| ---------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| **Queues**       | Use **Redis**. <br/>Comando sugerido: <br/>`bash<br/>php artisan queue:work --queue=contacts<br/>`   |
| **Reverb**       | Inicie com:<br/>`bash<br/>php artisan reverb:start<br/>`<br/>Disponibilize exemplo JS no README para ouvir o evento.                           |
| **Observer**     | `ContactObserver`<br/>• `saving` → normalizar telefone (somente dígitos).<br/>• `created` → logar criação.                                     |
| **Validação**    | Utilize **Form Requests** em **store** e **update**.                                                                                           |
| **Autenticação** | Opcional – bônus se usar **Laravel Passport**.                                                                                                 |
| **Documentação** | Este README deve explicar:<br/>• Setup (Laravel Sail ou Docker).<br/>• Como rodar worker e Reverb.<br/>• Exemplo de assinatura do canal em JS. |

---

## 3. Critérios de Avaliação

| ✅                                                               | Critério |
| --------------------------------------------------------------- | -------- |
| Uso correto de **Jobs, Events, Listeners e Observers**          |          |
| **Broadcast** funcionando via Reverb                            |          |
| Estrutura e organização do código (DDD, Service layer opcional) |          |
| Qualidade dos **testes** e cobertura do fluxo principal         |          |
| Clareza da documentação e **facilidade de setup**               |          |

---

## 4. Entrega

1. **Clone** este repositório e implemente sua solução.
2. Faça *commit* em uma branch (`main` ou `develop`) e **publique**.
3. Tempo sugerido: **1 SEMANA** (máx).
   Concentre-se em qualidade, não em escopo extra.

---



Boa sorte 🚀

```
```
