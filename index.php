<?php

require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Dotenv\Dotenv;

// Carrega o .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Cria conexão com o banco
$pdo = new PDO(
    "mysql:host={$_ENV['DB_HOST']};port={$_ENV['DB_PORT']};dbname={$_ENV['DB_DATABASE']}",
    $_ENV['DB_USERNAME'],
    $_ENV['DB_PASSWORD']
);

// Inicializa S3 origem
$sourceS3 = new S3Client([
    'version' => 'latest',
    'region'  => $_ENV['AWS_SOURCE_REGION'],
    'credentials' => [
        'key'    => $_ENV['AWS_SOURCE_KEY'],
        'secret' => $_ENV['AWS_SOURCE_SECRET']
    ]
]);

// Inicializa S3 destino
$destS3 = new S3Client([
    'version' => 'latest',
    'region'  => $_ENV['AWS_DEST_REGION'],
    'credentials' => [
        'key'    => $_ENV['AWS_DEST_KEY'],
        'secret' => $_ENV['AWS_DEST_SECRET']
    ]
]);

// Busca um bucket para migrar
$stmt = $pdo->query("SELECT id, nome FROM buckets WHERE migrado = 0 LIMIT 1");
$bucket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bucket) {
    echo "✅ Nenhum bucket pendente para migrar.\n";
    exit;
}

$bucketName = $bucket['nome'];
$bucketId   = $bucket['id'];

echo "📦 Iniciando migração do bucket: $bucketName\n";

// Retornar bucket
$result = $sourceS3->listObjectsV2([
    'Bucket' => $bucketName,
    'Prefix' => '', // opcional: exemplo 'imagens/' se quiser filtrar por "pasta"
]);

foreach ($result['Contents'] as $object) {
    $key = $object['Key'];
    echo "🔁 Migrando: $key\n";

    // Baixar objeto da origem
    $data = $sourceS3->getObject([
        'Bucket' => $bucketName,
        'Key'    => $key
    ]);

    // Enviar para bucket de destino
    $destS3->putObject([
        'Bucket' => $bucketName,
        'Key'    => $key,
        'Body'   => $data['Body'],
        'ContentType' => $data['ContentType'] ?? 'application/octet-stream'
    ]);
}

// Marcar como migrado
$pdo->prepare("UPDATE buckets SET migrado = 1, data_migracao = NOW() WHERE id = ?")
    ->execute([$bucketId]);

echo "✅ Migração do bucket '$bucketName' finalizada com sucesso.";
