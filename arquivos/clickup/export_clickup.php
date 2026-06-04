<?php

$configFile = __DIR__ . '/config.txt';

if (!file_exists($configFile)) {
    echo "Arquivo config.txt não encontrado em " . __DIR__ . "\n";
    exit(1);
}

$config = parse_ini_file($configFile, true);
$token  = $config['token'] ?? null;
$lists  = $config['lists'] ?? [];

if (!$token) {
    echo "Token não configurado em config.txt\n";
    exit(1);
}

if (empty($lists)) {
    echo "Nenhuma lista configurada em config.txt\n";
    exit(1);
}

$allTasks = [];

foreach ($lists as $listName => $listId) {
    $page      = 0;
    $listTasks = [];

    echo "Exportando lista: {$listName} (ID: {$listId})\n";

    do {
        $url = "https://api.clickup.com/api/v2/list/{$listId}/task?archived=false&include_closed=true&page={$page}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: {$token}",
            "Content-Type: application/json",
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            echo "  Erro HTTP {$httpCode} na lista {$listName} (página {$page})\n";
            break;
        }

        $response = json_decode($raw, true);
        $tasks    = $response['tasks'] ?? [];

        foreach ($tasks as $task) {
            $listTasks[] = $task;
        }

        $page++;

    } while (count($tasks) === 100);

    echo "  -> {$listName}: " . count($listTasks) . " tasks\n";

    foreach ($listTasks as $task) {
        $allTasks[] = $task;
    }
}

$outputFile = __DIR__ . '/all_tasks.json';
file_put_contents($outputFile, json_encode($allTasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\nTotal exportado: " . count($allTasks) . " tasks\n";
echo "Arquivo salvo em: {$outputFile}\n";
