# Backend PHP para o Projeto Young Money

**Versão:** 1.0.0
**Autor:** Manus AI

Este é o backend em PHP desenvolvido para o aplicativo Young Money. Ele fornece uma API RESTful para gerenciar usuários, pontos, saques e outras funcionalidades do aplicativo.

## 1. Estrutura de Arquivos

```
/young_money_backend
├── api/
│   └── v1/
│       ├── users.php       # Endpoint para gerenciar usuários
│       ├── points.php      # Endpoint para gerenciar pontos
│       └── withdrawals.php # Endpoint para gerenciar saques
├── config.php              # Arquivo de configuração do banco de dados
├── database.php            # Script de conexão com o banco de dados
└── README.md               # Este arquivo
```

## 2. Configuração

### Passo 1: Banco de Dados

1.  **Crie o banco de dados:** Utilize o serviço recomendado (Aiven.io) ou qualquer outro provedor de sua escolha.
2.  **Importe o Schema:** O arquivo `schema.sql` (localizado em `project_files/`) contém a estrutura de todas as tabelas necessárias. Importe este arquivo para o seu banco de dados recém-criado. Você pode fazer isso através do phpMyAdmin ou de qualquer outro cliente MySQL.

### Passo 2: Credenciais de Conexão

Abra o arquivo `config.php` e substitua os valores de placeholder pelas credenciais do seu banco de dados Aiven (ou do provedor que você escolheu).

```php
// config.php

define('DB_HOST', 'SEU_HOST_AIVEN');
define('DB_PORT', 'SUA_PORTA_AIVEN');
define('DB_USER', 'avnadmin');
define('DB_PASSWORD', 'SUA_SENHA_AIVEN');
define('DB_NAME', 'defaultdb');
```

## 3. Documentação da API (v1)

A API foi projetada para ser simples e RESTful, utilizando JSON como formato de comunicação.

### Usuários (`/api/v1/users.php`)

*   **`GET /api/v1/users.php?id={user_id}`**
    *   **Descrição:** Retorna os detalhes de um usuário específico.
    *   **Resposta:** Objeto JSON com os dados do usuário (sem a senha).

*   **`POST /api/v1/users.php`**
    *   **Descrição:** Cria um novo usuário.
    *   **Corpo da Requisição (JSON):**
        ```json
        {
            "username": "novo_usuario",
            "password": "senha_forte_123",
            "email": "email@exemplo.com"
        }
        ```
    *   **Resposta:** Mensagem de sucesso com o ID do novo usuário.

### Pontos (`/api/v1/points.php`)

*   **`GET /api/v1/points.php?user_id={user_id}`**
    *   **Descrição:** Retorna o histórico de pontos de um usuário.
    *   **Resposta:** Array de objetos JSON, cada um representando uma transação de pontos.

*   **`POST /api/v1/points.php`**
    *   **Descrição:** Adiciona uma nova entrada de pontos para um usuário e atualiza seu saldo total.
    *   **Corpo da Requisição (JSON):**
        ```json
        {
            "user_id": 1,
            "points_earned": 100,
            "activity_type": "game"
        }
        ```
    *   **Resposta:** Mensagem de sucesso.

### Saques (`/api/v1/withdrawals.php`)

*   **`GET /api/v1/withdrawals.php?user_id={user_id}`**
    *   **Descrição:** Retorna o histórico de saques de um usuário.
    *   **Resposta:** Array de objetos JSON com os detalhes dos saques.

*   **`POST /api/v1/withdrawals.php`**
    *   **Descrição:** Cria uma nova solicitação de saque.
    *   **Corpo da Requisição (JSON):**
        ```json
        {
            "user_id": 1,
            "pix_key": "chave_pix_aqui",
            "pix_key_type": "email",
            "amount": 50.50
        }
        ```
    *   **Resposta:** Mensagem de sucesso.

## 4. Como Fazer o Deploy

1.  **Hospedagem:** Você precisará de um serviço de hospedagem que suporte PHP (a maioria dos provedores de hospedagem compartilhada oferece isso).
2.  **Upload:** Faça o upload de todos os arquivos da pasta `young_money_backend` para o diretório raiz do seu servidor (geralmente `public_html` ou `www`).
3.  **Teste:** Acesse os endpoints da sua API através do seu domínio para garantir que tudo está funcionando corretamente. Ex: `https://seudominio.com/api/v1/users.php?id=1`.

## 5. Próximos Passos

*   **Segurança:** Considere adicionar um sistema de autenticação por token (como JWT) para proteger os endpoints da sua API.
*   **Validação:** Implemente uma validação mais robusta dos dados de entrada.
*   **Admin:** Crie uma área administrativa para gerenciar saques e usuários.
