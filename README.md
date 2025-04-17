# 🚀 AWS S3 Bucket Migrator (PHP)

Este projeto realiza a **migração automatizada de buckets e objetos entre duas contas AWS S3**, utilizando PHP e a AWS SDK. Ele consulta uma base de dados com a lista de buckets, faz a transferência direta entre as contas (sem salvar localmente) e marca cada bucket como migrado após a conclusão.

---

## ✨ Funcionalidades

- ✅ Conexão com múltiplas contas AWS via SDK
- ✅ Leitura de buckets a partir do banco de dados
- ✅ Transferência direta entre contas (stream de memória)
- ✅ Marcação no banco após migração
- ✅ Log detalhado de progresso no terminal
- ✅ Código limpo, extensível e fácil de adaptar

---

## 📦 Requisitos

- PHP 7.4+
- Composer
- Extensões `pdo`, `pdo_mysql`
- AWS SDK para PHP

---

## 🧩 Estrutura esperada da tabela

```sql
CREATE TABLE buckets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    migrado TINYINT(1) NOT NULL DEFAULT 0,
    data_migracao DATETIME NULL
    observacao TEXT NULL
);
```
---

## 🛠️ Instalação

```bash
git clone https://github.com/paulophdev/aws-s3-migrator.git
cd aws-s3-migrator
composer install
```

---

## 🚀 Executando

```bash
php index.php
```

Ou para acessar via navegador (em ambiente local):

```bash
php -S localhost:8080
```

Acesse: http://localhost:8080

## 🤖 Automação

Se por acaso sua migração tiver poucos buckets, altere a quantidade de migrações diretamente no `limite` da query.

Caso você tenha muitos buckets com muitos arquivos, considere utilizar um sistema de **cron** para executar a migração programaticamente, garantindo que o processo seja executado em intervalos regulares e sem sobrecarregar o sistema.