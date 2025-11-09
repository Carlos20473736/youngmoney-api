# 🔄 Migração: Sistema de Ranking com Reset Automático

## 📅 Data: 09 de Novembro de 2025

---

## 🎯 Objetivo

Implementar sistema de ranking com **reset automático** por período (diário/semanal/mensal).

---

## 📋 Problemas Corrigidos

### 1️⃣ **Pontos não salvavam corretamente**
- ✅ App enviava `activity` mas backend esperava `description`
- ✅ Corrigido em `add_points.php` para aceitar ambos

### 2️⃣ **Ranking não resetava**
- ✅ Ranking acumulava pontos infinitamente
- ✅ Implementado sistema de períodos com reset automático

---

## 🗄️ Estrutura do Banco de Dados

### Nova Tabela: `ranking_periods`

Armazena os períodos de ranking (diário/semanal/mensal):

```sql
CREATE TABLE ranking_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_type ENUM('daily', 'weekly', 'monthly'),
    start_date DATETIME,
    end_date DATETIME,
    status ENUM('active', 'finished'),
    created_at TIMESTAMP
);
```

### Nova Tabela: `ranking_points`

Armazena pontos dos usuários por período:

```sql
CREATE TABLE ranking_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    period_id INT,
    points INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY (user_id, period_id)
);
```

---

## 🔧 Stored Procedures

### 1. `create_ranking_period(period_type)`
Cria um novo período de ranking.

### 2. `finish_expired_periods()`
Finaliza períodos que já expiraram.

### 3. `get_active_period(period_type)`
Retorna o período ativo ou cria um novo se não existir.

---

## 📝 Passo a Passo da Migração

### Passo 1: Executar SQL no Banco

```bash
# Conectar ao banco MySQL no Railway
mysql -h <host> -u <user> -p<password> <database> < ranking_system.sql
```

Ou via Railway CLI:
```bash
railway run mysql < ranking_system.sql
```

### Passo 2: Substituir Arquivos da API

#### Opção A: Substituir arquivos existentes (RECOMENDADO)

```bash
# Backup dos arquivos antigos
cp ranking/add_points.php ranking/add_points_old.php
cp ranking/list.php ranking/list_old.php

# Substituir pelos novos
cp ranking/add_points_v2.php ranking/add_points.php
cp ranking/list_v2.php ranking/list.php
```

#### Opção B: Usar novos endpoints (sem quebrar compatibilidade)

Manter arquivos antigos e usar os novos:
- `/ranking/add_points_v2.php` - Nova versão com períodos
- `/ranking/list_v2.php` - Nova versão com períodos

### Passo 3: Fazer Deploy

```bash
git add .
git commit -m "🎯 Implement ranking system with automatic reset"
git push origin main
```

O Railway vai fazer deploy automático.

---

## 🔄 Como Funciona

### Fluxo de Adição de Pontos

```
1. Usuário ganha pontos (Candy Crush, Check-in, etc)
   ↓
2. App chama /ranking/add_points.php
   ↓
3. Backend:
   a) Atualiza pontos totais do usuário (tabela users)
   b) Salva no histórico (tabela points_history)
   c) Obtém período ativo (CALL get_active_period('daily'))
   d) Atualiza pontos do período (tabela ranking_points)
   ↓
4. Retorna:
   - points_added: pontos adicionados
   - daily_points: pontos do período atual
   - total_points: pontos totais acumulados
```

### Fluxo de Listagem do Ranking

```
1. App chama /ranking/list.php?period=daily
   ↓
2. Backend:
   a) Obtém período ativo (CALL get_active_period('daily'))
   b) Busca top 100 do período (ranking_points)
   c) Retorna ranking com informações do período
   ↓
3. App exibe ranking do período atual
```

### Reset Automático

```
Quando um período expira:

1. Próxima chamada a get_active_period()
   ↓
2. finish_expired_periods() marca períodos expirados como 'finished'
   ↓
3. create_ranking_period() cria novo período
   ↓
4. Ranking começa zerado no novo período
```

---

## 📊 Tipos de Período

### Daily (Diário)
- Duração: 24 horas
- Reset: Todo dia à meia-noite
- Uso: Ranking diário

### Weekly (Semanal)
- Duração: 7 dias
- Reset: Toda semana
- Uso: Ranking semanal

### Monthly (Mensal)
- Duração: 1 mês
- Reset: Todo mês
- Uso: Ranking mensal

---

## 🧪 Testes

### Teste 1: Adicionar Pontos

```bash
curl -X POST https://youngmoney-api-production.up.railway.app/ranking/add_points.php \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -H "X-Req: SEU_XREQ" \
  -d '{"points": 100, "activity": "Teste"}'
```

**Resposta esperada:**
```json
{
  "success": true,
  "data": {
    "points_added": 100,
    "daily_points": 100,
    "total_points": 100
  }
}
```

### Teste 2: Listar Ranking

```bash
curl -X GET "https://youngmoney-api-production.up.railway.app/ranking/list.php?period=daily" \
  -H "X-Req: SEU_XREQ"
```

**Resposta esperada:**
```json
{
  "success": true,
  "data": {
    "rankings": [
      {
        "position": 1,
        "userName": "Usuário 1",
        "dailyPoints": 1000,
        "totalPoints": 5000
      }
    ],
    "period": {
      "type": "daily",
      "start_date": "2025-11-09 00:00:00",
      "end_date": "2025-11-10 00:00:00",
      "period_id": 1
    }
  }
}
```

### Teste 3: Verificar Reset

1. Aguardar até o período expirar (ou mudar manualmente no banco)
2. Fazer nova requisição ao ranking
3. Verificar que um novo período foi criado
4. Verificar que os pontos do período estão zerados

---

## ⚠️ Importante

### Dados Antigos

Os pontos totais dos usuários (coluna `users.points`) **NÃO serão zerados**.

Apenas os pontos do **período atual** (tabela `ranking_points`) serão zerados a cada reset.

Isso permite:
- ✅ Ranking periódico com reset
- ✅ Manter histórico total de pontos
- ✅ Múltiplos rankings simultâneos (diário, semanal, mensal)

### Migração de Dados Existentes

Se quiser migrar os pontos atuais para o primeiro período:

```sql
-- Inserir pontos atuais no período ativo
INSERT INTO ranking_points (user_id, period_id, points)
SELECT 
    id as user_id,
    (SELECT id FROM ranking_periods WHERE period_type = 'daily' AND status = 'active' LIMIT 1) as period_id,
    points
FROM users
WHERE points > 0;
```

---

## 🔄 Compatibilidade

### Versão Antiga (sem períodos)

Se não quiser migrar agora, os arquivos antigos continuam funcionando:
- `add_points.php` - Adiciona pontos totais
- `list.php` - Lista ranking total

### Versão Nova (com períodos)

Para usar o novo sistema:
- `add_points_v2.php` - Adiciona pontos + atualiza período
- `list_v2.php` - Lista ranking do período

### Migração Gradual

1. **Fase 1:** Executar SQL para criar tabelas
2. **Fase 2:** Testar novos endpoints (_v2.php)
3. **Fase 3:** Substituir endpoints antigos
4. **Fase 4:** Atualizar app para usar novos endpoints

---

## 📈 Benefícios

✅ **Rankings justos** - Reset periódico dá chance para todos  
✅ **Múltiplos rankings** - Diário, semanal, mensal simultâneos  
✅ **Histórico preservado** - Pontos totais nunca são perdidos  
✅ **Automático** - Reset acontece automaticamente  
✅ **Escalável** - Suporta milhões de usuários  

---

## 🆘 Troubleshooting

### Erro: "Table ranking_periods doesn't exist"

**Solução:** Execute o SQL `ranking_system.sql` no banco.

### Erro: "PROCEDURE get_active_period does not exist"

**Solução:** Execute as stored procedures do SQL.

### Ranking não reseta

**Solução:** Verifique se o período está expirado:
```sql
SELECT * FROM ranking_periods WHERE status = 'active';
```

Se estiver expirado, chame manualmente:
```sql
CALL finish_expired_periods();
```

---

**Status:** ✅ PRONTO PARA MIGRAÇÃO  
**Versão:** 2.0  
**Data:** 09 de Novembro de 2025
