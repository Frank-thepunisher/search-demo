<?php

declare(encoding='utf-8');

require_once('../backend/RfSearch.php');

try {
    $configFile = join(DIRECTORY_SEPARATOR, array(__DIR__, '..', 'config.json'));
    $categoriesFile = join(DIRECTORY_SEPARATOR, array(__DIR__, '..', 'categories.json'));

    $rfSearch = new RfSearch();
    $rfSearch->loadConfig($configFile);
    $rfSearch->loadCategories($categoriesFile);

    $categories = $rfSearch->getCategoryStructure();
    respond($categories);

} catch (Exception $e) {
    respond((Object) array(
        'error' => $e->getMessage()
    ), '500 Internal Server Error');
}

function respond($response, $status = '200 OK') {
    header("HTTP/1.1 {$status}");
    header('Content-Type: application/json');
    die(json_encode($response, JSON_PRETTY_PRINT));
}
