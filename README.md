# Desafio Técnico – Mini CRM de Contatos

Construa uma pequena API REST em Laravel para gerenciar contatos e acompanhar, em tempo real, a evolução do score desses contatos quando um processamento assíncrono for executado.

A solução **deve** demonstrar o uso de:

- CRUD completo (HTTP JSON)  
- **Form Requests** para validação de entrada  
- **API Resources** para serialização de saída  
- **Jobs** (dispatch + queue worker)  
- **Observers**  
- **Events & Listeners**  
- **Laravel Reverb** (broadcasting em tempo real)  


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
   POST /api/contacts/{id}/process-score

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

| Área               | Detalhes                                                                                                                                                                   |
| ------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Queues**         | Use **Redis**. <br/>Comando sugerido:<br/>`php artisan queue:work --queue=contacts`                                                  |
| **Reverb**         | Inicie com:<br/>`php artisan reverb:start`<br/>Inclua no README um exemplo JavaScript de assinatura do canal.                      |
| **Form Requests**  | Crie classes específicas para **store** e **update** garantindo validação centralizada.                                             |
| **API Resources**  | Serialize todas as respostas JSON (inclusive erros) usando **Laravel Resource** / **Resource Collection**.                          |
| **Observer**       | `ContactObserver`<br/>• `saving` → normalizar telefone (somente dígitos).<br/>• `created` → logar criação.                          |
| **Autenticação**   | Opcional — bônus se utilizar **Laravel Passport**.                                                                                   |
| **Documentação**   | Este README deve explicar:<br/>• Setup (Laravel Sail ou Docker).<br/>• Como rodar o worker e o Reverb.<br/>• Exemplo de escuta de canal em JS. |

---

## 3. Critérios de Avaliação

| ✅ | Critério                                                                                         |
|----|--------------------------------------------------------------------------------------------------|
|    | Uso correto de **Form Requests** e **API Resources**                                             |
|    | Emprego adequado de **Jobs, Events, Listeners e Observers**                                      |
|    | **Broadcast** funcionando via Reverb                                                             |
|    | Estrutura e organização do código                                                                |
|    | Qualidade dos **testes** e cobertura do fluxo principal                                          |
|    | Clareza da documentação e **facilidade de setup**      
     

---

## 4. Entrega

1. **Clone** este repositório e implemente sua solução.
2. Faça *commit* em uma branch (`main` ou `develop`) e **publique**.
3. Prazo de entrega: **1 SEMANA** (máx).
   Concentre-se em qualidade, não em escopo extra.

---

Boa sorte 🚀
