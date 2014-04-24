<?php

declare(encoding='utf-8');

require_once('../backend/RfSearch.php');

try {
    $configFile = join(DIRECTORY_SEPARATOR, array(__DIR__, '..', 'config.json'));
    $categoriesFile = join(DIRECTORY_SEPARATOR, array(__DIR__, '..', 'categories.json'));

    $rfSearch = new RfSearch();
    $rfSearch->loadConfig($configFile);
    $rfSearch->loadCategories($categoriesFile);

    $parameters = processAndValidateRequest($rfSearch);
    $result = $rfSearch->search($parameters->category, $parameters->keywords, $parameters->page);

    respond($result);

} catch (Exception $e) {
    respond((Object) array(
        'error' => $e->getMessage()
    ), '500 Internal Server Error');
}

function respond($response, $status = '200 OK') {
    header("HTTP/1.1 {$status}");
    header('Content-Type: application/json');
    die(json_encode($response));
}

function processAndValidateRequest(RfSearch $rfSearch) {
    $parameters = (Object) array(
        'page' => 1,
        'keywords' => null,
        'category' => null
    );

    if (array_key_exists('category', $_GET)) {
        if (!ctype_digit($_GET['category'])) {
            respond((Object) array(
                'error' => 'Parameter "category" is malformed.'
            ), '400 Bad Request');
        }
        $parameters->category = intval($_GET['category']);

        if (!$rfSearch->categoryExists($parameters->category)) {
            respond((Object) array(
                'error' => 'Parameter "category" is invalid: no such category.'
            ), '400 Bad Request');
        }
    }

    if (array_key_exists('keywords', $_GET)) {
        $keywords = is_array($_GET['keywords']) ? $_GET['keywords'] : explode(',', $_GET['keywords']);
        $keywords = array_filter($keywords, function ($item) {
            return mb_strlen($item) > 0;
        });
        if (count($keywords) === 0) {
            respond((Object) array(
                'error' => 'Parameter "keyword" must contain at least one keyword.'
            ), '400 Bad Request');

        }
        $parameters->keywords = $keywords;
    }

    if (array_key_exists('page', $_GET)) {
        if (!ctype_digit($_GET['page']) || intval($_GET['page']) === 0) {
            respond((Object) array(
                'error' => 'Parameter "page" must be an integer greater than zero.'
            ), '400 Bad Request');
        } else {
            $parameters->page = intval($_GET['page']);
        }
    }

    if ($parameters->keywords !== null && $parameters->category !== null) {
        respond((Object) array(
            'error' => 'Parameters "category" and "keywords" are mutually exclusive.'
        ), '400 Bad Request');
    }

    return $parameters;
}
