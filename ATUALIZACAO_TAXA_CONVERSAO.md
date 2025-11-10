# Atualização - Taxa de Conversão de Pontos

**Data:** 10 de novembro de 2025  
**Versão:** 2.0

---

## 📋 Resumo

Atualizada a taxa de conversão de pontos para reais em todo o sistema.

### Antes
- **100 pontos = R$ 1,00**

### Depois
- **10.000 pontos = R$ 1,00**

---

## 📝 Arquivos Modificados

### 1. `/api/v1/withdrawals.php`
**Alterações:**
- ✅ Adicionada constante `POINTS_PER_REAL = 10000`
- ✅ Implementado débito de pontos ao solicitar saque
- ✅ Adicionado registro no histórico de pontos
- ✅ Implementada transação SQL (rollback em caso de erro)
- ✅ Validação de saldo mínimo (R$ 1,00)
- ✅ Validação de pontos suficientes

**Antes:**
```php
// Não debitava pontos
// Apenas criava registro de saque
```

**Depois:**
```php
define('POINTS_PER_REAL', 10000);
$pointsRequired = intval($amountBrl * POINTS_PER_REAL);

// Debita pontos
UPDATE users SET points = points - ? WHERE id = ?

// Registra no histórico
INSERT INTO points_history (user_id, points, description, type) 
VALUES (?, ?, ?, 'debit')

// Cria registro de saque
INSERT INTO withdrawals (user_id, pix_key, pix_key_type, amount, status) 
VALUES (?, ?, ?, ?, 'pending')
```

### 2. `/withdraw/request.php`
**Alterações:**
- ✅ Adicionada constante `POINTS_PER_REAL = 10000`
- ✅ Implementado débito de pontos (antes só retornava sucesso)
- ✅ Adicionado registro no histórico de pontos
- ✅ Implementada transação SQL
- ✅ Validação de valor mínimo (R$ 1,00)
- ✅ Validação de pontos suficientes
- ✅ Criação de registro na tabela `withdrawals`

**Antes:**
```php
// Por enquanto, apenas retornar sucesso (criar tabela withdrawals depois)
echo json_encode([
    'success' => true,
    'data' => [
        'withdrawal_id' => time(),
        'amount' => $amount,
        'status' => 'pending',
        'message' => 'Solicitação de saque enviada com sucesso'
    ]
]);
```

**Depois:**
```php
define('POINTS_PER_REAL', 10000);

// Valida saldo
if ($currentPoints < $pointsRequired) {
    return error('Saldo insuficiente');
}

// Transação completa:
// 1. Debita pontos
// 2. Registra histórico
// 3. Cria saque
$conn->begin_transaction();
// ... operações ...
$conn->commit();
```

---

## 🔄 Fluxo Completo de Saque

### Antes (Incompleto)
1. Usuário solicita saque
2. ~~API não verifica saldo~~
3. ~~API não debita pontos~~
4. ~~API retorna sucesso falso~~

### Depois (Completo)
1. Usuário solicita saque de R$ 10,00
2. API calcula: 10 × 10.000 = 100.000 pontos
3. API verifica se usuário tem ≥ 100.000 pontos
4. API inicia transação SQL:
   - Debita 100.000 pontos do usuário
   - Registra "-100.000 pts" no histórico
   - Cria registro de saque (status: pending)
5. API retorna sucesso com dados:
   ```json
   {
     "success": true,
     "data": {
       "withdrawal_id": 123,
       "amount": 10.0,
       "points_debited": 100000,
       "remaining_points": 1400000,
       "status": "pending"
     }
   }
   ```

---

## ✅ Validações Implementadas

### 1. Valor Mínimo
```php
if ($amountBrl < 1) {
    return error('Valor mínimo: R$ 1,00');
}
```

### 2. Saldo Suficiente
```php
if ($currentPoints < $pointsRequired) {
    return error('Saldo insuficiente');
}
```

### 3. Token Válido
```php
if (!$token) {
    return error('Token não fornecido');
}
```

### 4. Dados Completos
```php
if (!isset($input['amount']) || !isset($input['pixKeyType']) || !isset($input['pixKey'])) {
    return error('Dados incompletos');
}
```

---

## 🔒 Segurança

### Transações SQL
Todas as operações de saque usam transações para garantir integridade:

```php
$conn->begin_transaction();
try {
    // 1. Debitar pontos
    // 2. Registrar histórico
    // 3. Criar saque
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback(); // Desfaz tudo em caso de erro
    throw $e;
}
```

### Validação XReq
Mantida a validação de token XReq para prevenir ataques:

```php
validateXReq();
```

---

## 📊 Exemplos de Conversão

| Valor em Reais | Pontos Necessários | Antes (100 pts/R$) |
|----------------|--------------------|--------------------|
| R$ 1,00        | 10.000 pts         | 100 pts            |
| R$ 10,00       | 100.000 pts        | 1.000 pts          |
| R$ 20,00       | 200.000 pts        | 2.000 pts          |
| R$ 50,00       | 500.000 pts        | 5.000 pts          |
| R$ 100,00      | 1.000.000 pts      | 10.000 pts         |

---

## ⚠️ Impacto

### Usuários Existentes
- Usuários com pontos acumulados **NÃO** foram afetados
- O saldo de pontos permanece o mesmo
- Apenas a **taxa de conversão** mudou

### Exemplo Real
**Usuário com 500.000 pontos:**

**Antes:**
- Podia sacar: R$ 5.000,00 (500.000 ÷ 100)

**Depois:**
- Pode sacar: R$ 50,00 (500.000 ÷ 10.000)

---

## 🧪 Como Testar

### Teste 1: Saque com Saldo Suficiente
```bash
curl -X POST https://youngmoney-api-production.up.railway.app/withdraw/request.php \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 10,
    "pixKeyType": "CPF",
    "pixKey": "12345678900"
  }'
```

**Resultado esperado:**
- Status: 200
- Pontos debitados: 100.000
- Registro criado na tabela `withdrawals`
- Registro criado na tabela `points_history`

### Teste 2: Saque com Saldo Insuficiente
```bash
curl -X POST https://youngmoney-api-production.up.railway.app/withdraw/request.php \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 100,
    "pixKeyType": "CPF",
    "pixKey": "12345678900"
  }'
```

**Resultado esperado:**
- Status: 400
- Erro: "Saldo insuficiente"

### Teste 3: Valor Abaixo do Mínimo
```bash
curl -X POST https://youngmoney-api-production.up.railway.app/withdraw/request.php \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 0.50,
    "pixKeyType": "CPF",
    "pixKey": "12345678900"
  }'
```

**Resultado esperado:**
- Status: 400
- Erro: "Valor mínimo: R$ 1,00"

---

## 📌 Próximos Passos

### Backend
- [x] Atualizar taxa de conversão
- [x] Implementar débito de pontos
- [x] Adicionar validações
- [x] Implementar transações SQL
- [ ] Testar em produção
- [ ] Monitorar logs de erro

### Frontend (App Android)
- [x] Atualizar taxa de conversão (10.000 pts = R$1)
- [x] Atualizar validação de saldo mínimo
- [x] Atualizar botão "TUDO"
- [ ] Testar integração com backend atualizado

### Painel Admin
- [x] Atualizar taxa ao devolver pontos (rejeitar saque)
- [ ] Atualizar relatórios e estatísticas

---

**Desenvolvido por:** Manus AI  
**Versão da API:** 2.0  
**Compatibilidade:** Requer app Android v4+
