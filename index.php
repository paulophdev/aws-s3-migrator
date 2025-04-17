<?php

require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Dotenv\Dotenv;

$break = (php_sapi_name() === 'cli') ? "\n" : "<br>";

ini_set('max_execution_time', 0); // 0 = ilimitado

// Carrega .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Conexão com o banco
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
$stmt = $pdo->query("SELECT id, nome FROM buckets WHERE migrado = 0 AND observacao IS NULL LIMIT 1");
$bucket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bucket) {
    echo "✅ Nenhum bucket pendente para migrar.{$break}";
    exit;
}

$bucketName = $bucket['nome'];
$bucketId   = $bucket['id'];
$destBucket = 'lista-clientes-all'; // Bucket fixo na conta de destino

echo "📦 Iniciando migração do bucket: $bucketName para $destBucket/$bucketName/{$break}";

// Verifica acesso ao bucket de destino
try {
    $destS3->listObjectsV2(['Bucket' => $destBucket]);
    echo "✅ Acesso ao bucket $destBucket confirmado.{$break}";
} catch (\Throwable $th) {
    $errorMessage = "Erro ao acessar o bucket $destBucket: " . $th->getMessage();
    echo $errorMessage . $break;
    $pdo->prepare("UPDATE buckets SET observacao = ?, data_migracao = NOW() WHERE id = ?")
        ->execute([$errorMessage, $bucketId]);
    exit;
}

// Lista objetos no bucket de origem
$result = $sourceS3->listObjectsV2([
    'Bucket' => $bucketName,
    'Prefix' => '', // opcional: ex. 'imagens/' para filtrar por pasta
]);

foreach ($result['Contents'] as $object) {
    $key = $object['Key'];
    $destKey = $bucketName . '/' . $key; // Adiciona o nome do bucket como pasta
    echo "🔁 Migrando: {$key} para {$destBucket}/{$destKey}{$break}";

    try {
        // Baixa objeto da origem
        $data = $sourceS3->getObject([
            'Bucket' => $bucketName,
            'Key'    => $key
        ]);

        // Envia para bucket de destino com pasta
        $retorno = $destS3->putObject([
            'Bucket' => $destBucket, // Usa bucket fixo
            'Key'    => $destKey,    // Inclui o nome do bucket como prefixo
            'Body'   => $data['Body'],
            'ContentType' => $data['ContentType'] ?? 'application/octet-stream'
        ]);

        echo "✅ Objeto {$key} migrado para {$destBucket}/{$destKey} com sucesso.{$break}";
    } catch (\Throwable $th) {
        $errorMessage = "Erro na migração do objeto {$key} para {$destBucket}/{$destKey}: " . $th->getMessage();
        echo $errorMessage . $break;
        $pdo->prepare("UPDATE buckets SET observacao = ?, data_migracao = NOW() WHERE id = ?")
            ->execute([$errorMessage, $bucketId]);
        break;
    }
}

// Marca como migrado
$pdo->prepare("UPDATE buckets SET migrado = 1, data_migracao = NOW() WHERE id = ?")
    ->execute([$bucketId]);

echo "✅ Migração do bucket '{$bucketName}' para '{$destBucket}/{$bucketName}/' finalizada com sucesso.{$break}";
?>