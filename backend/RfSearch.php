<?php

declare(encoding='utf8');

class RfSearch {
    const REST_URL = 'http://api.123rf.com/rest/';

    private $config = null;
    private $categories = null;
    private $categoryIndex = null;

    public function loadCategories($file) {
        if (!is_readable($file)) {
            throw new Exception('Categories file not found or not readable.');
        }

        $contents = @file_get_contents($file);
        if ($contents === false) {
            throw new Exception('Could not get contents of categories file.');
        }

        $categories = json_decode($contents);
        if ($categories === null) {
            $errorCode = json_last_error();
            throw new Exception("Could not parse categories file (code {$errorCode}).");
        }

        $this->categories = $categories;
        $this->createCategoryIndex();
    }

    private function addCategoryToIndex(array $mediaTypes, $category) {
        if (array_key_exists($category->id, $this->categoryIndex)) {
            throw new Exception("Category id {$category->id} is not unique.");
        }
        $this->categoryIndex[(string) $category->id] = (Object) array(
            'keywords' => $category->keywords,
            'mediatypes' => $mediaTypes
        );
    }

    private function createCategoryIndex() {
        $this->categoryIndex = array();

        foreach ($this->categories as $category) {
            foreach ($category->categories as $subcategory) {
                if (property_exists($subcategory, 'categories')) {
                    foreach ($subcategory->categories as $subsubcategory) {
                        $this->addCategoryToIndex($category->mediatypes, $subsubcategory);
                    }
                } else {
                    $this->addCategoryToIndex($category->mediatypes, $subcategory);
                }
            }
        }
    }

    public function loadConfig($file) {
        if (!is_readable($file)) {
            throw new Exception('Config file not found or not readable.');
        }

        $contents = @file_get_contents($file);
        if ($contents === false) {
            throw new Exception('Could not get contents of config file.');
        }

        $config = json_decode($contents);
        if ($config === null) {
            $errorCode = json_last_error();
            throw new Exception("Could not parse config file (code {$errorCode}).");
        }

        $this->config = $config;
    }

    public function getCategoryStructure() {
        $result = json_decode(json_encode($this->categories));

        foreach ($result as &$category) {
            unset($category->mediatypes);
            usort($category->categories, function ($a, $b) {
                return strcmp($a->name, $b->name);
            });

            foreach ($category->categories as $subcategory) {
                unset($subcategory->mediatypes);
                unset($subcategory->keywords);

                if (property_exists($subcategory, 'categories')) {
                    usort($subcategory->categories, function ($a, $b) {
                        return strcmp($a->name, $b->name);
                    });
                    foreach ($subcategory->categories as $subsubcategory) {
                        unset($subsubcategory->mediatypes);
                        unset($subsubcategory->keywords);
                    }
                }
            }
        }

        return $result;
    }

    public function categoryExists($categoryId) {
        return array_key_exists((string) $categoryId, $this->categoryIndex);
    }

    public function search($categoryId, $keywords, $page = 1) {
        if ($this->config === null) {
            throw new Exception('Config was not loaded.');
        }
        if ($this->categories === null) {
            throw new Exception('Categories were not loaded.');
        }
        if ($categoryId !== null && !$this->categoryExists($categoryId)) {
            throw new Exception("Unknown category id: {$categoryId}");
        }

        $mediaTypes = $categoryId === null ? 'all' : join(',', $this->categoryIndex[(string) $categoryId]->mediatypes);
        $categoryKeywords = $categoryId === null ? array() : $this->categoryIndex[(string) $categoryId]->keywords;
        $searchKeywords = $keywords === null ? array() : $keywords;
        $keywords = array_merge($categoryKeywords, $searchKeywords);

        $handle = curl_init();
        $parameters = array(
            'apikey' => $this->config->noncommercial->apiKey,
            'method' => '123rf.images.search',
            'keyword' => join(',', $keywords),
            'language' => 'de',
            'page' => intval($page),
            'media_type' => $mediaTypes,
            'perpage' => 40,
            'orderby' => 'latest',
            'nudity' => 1
        );
        
        // Enable this for debugging but never in production since it will reveal your api key!
        // header('X-123RF-Query: ' . json_encode($parameters));

        curl_setopt_array($handle, array(
            CURLOPT_HTTPGET => true,
            CURLOPT_URL => self::REST_URL . '?' . http_build_query($parameters),
            CURLOPT_RETURNTRANSFER => true
        ));
        $result = curl_exec($handle);

        try {
            if ($result === false) {
                throw new Exception('Request to 123RF backend failed: ' . curl_error($handle));
            }

            $statusCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
            if ($statusCode !== 200) {
                throw new Exception("Request to 123RF backend failed with unexpected status (code {$statusCode}).");
            }

            $dom = new DOMDocument();
            if ($dom->loadXML($result) === false) {
                throw new Exception('Error parsing XML response from 123RF backend.');
            }

            $xpath = new DOMXPath($dom);
            $status = $xpath->evaluate('string(/rsp/@stat)');
            if ($status !== 'ok') {
                $errorCode = $xpath->evaluate('string(/rsp/err/@code)');
                throw new Exception("Response from 123RF backend failed with error code {$errorCode}.");
            }

            $result = (Object) array(
                'meta' => (Object) array(
                    'page' => $xpath->evaluate('number(/rsp/images/@page)'),
                    'pages' => $xpath->evaluate('number(/rsp/images/@pages)'),
                    'total' => $xpath->evaluate('number(/rsp/images/@total)'),
                    'keywords' => array_values($keywords)
                ),
                'images' => array()
            );

            foreach ($xpath->query("/rsp/images/image") as $node) {
                $thumbnail_url = sprintf(
                    'http://images.assetsdelivery.com/thumbnails/%s/%s/%s.jpg',
                    $node->getAttribute('contributorid'),
                    $node->getAttribute('folder'),
                    $node->getAttribute('filename')
                );
                $preview_url = sprintf(
                    'http://images.assetsdelivery.com/compings/%s/%s/%s.jpg',
                    $node->getAttribute('contributorid'),
                    $node->getAttribute('folder'),
                    $node->getAttribute('filename')
                );
                $result->images[] = (Object) array(
                    'id' => $node->getAttribute('id'),
                    'description' => $node->getAttribute('description'),
                    'thumbnail' => $thumbnail_url,
                    'preview' => $preview_url
                );
            }

            curl_close($handle);
            return $result;
        } catch (Exception $e) {
            @curl_close($handle);
            throw $e;
        }
    }
}
