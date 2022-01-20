<?php
require "vendor/autoload.php";
use PHPHtmlParser\Dom;

ini_set('max_execution_time', '3000');
$time_start = microtime(true);

function getItemInfo ($productLink) {
    try {
        $itemData = [];

        $dom = new Dom;
        $dom->loadFromUrl($productLink);

        $name = $dom->find('h1')->text; //Название
        $description = ($dom->find('.description-product-content')->count() && $dom->find('.description-product-content')) ? $dom->find('.description-product-content')->text : null; //Описание
        $SKU = ($dom->find('div.column.shrink.sku .style') && $dom->find('div.column.shrink.sku .style')) ? $dom->find('div.column.shrink.sku .style')->text : null; //SKU (Item #);

        $categoryDomNode = $dom->find('nav.breadcrumbs-container');
        $categoriesBreadJSON = json_decode($categoryDomNode->getAttribute('data-breadcrumbs'));
        $category =  $categoriesBreadJSON[count($categoriesBreadJSON)-2]->{'name'}; // получаем ближайшею категорию

        $options = ($dom->find('.tabs-panel .option')->count() && $dom->find('.tabs-panel .option')) ? $dom->find('.tabs-panel .option') : []; //Остальные данные: размер, вес и характеристики.
    // ну и брэнд

        foreach ($options as $option)
        {
            $nameLocal = $option->find('.option-title')->text;

            $value = ($option->find('.value a')->count() && $option->find('.value a')) ? $option->find('.value a')->text : null;
            if (!$value) {
                $value = ($option->find('.value span')->count() && $option->find('.value span')) ? $option->find('.value span')->text : null;
            }

            $itemData[$nameLocal] = $value;
        }


        $salePrice =$dom->find('.value.product-quantity-old-price span.price') ? $dom->find('.value.product-quantity-old-price span.price')->text : null; // Цена со скидкой
        $price =$dom->find('.value.product-quantity-one-price.product-quantity-one-price__discount span.price') ?  $dom->find('.value.product-quantity-one-price.product-quantity-one-price__discount span.price')->text : null; //Обычная цена товара;
        $quantityNode = $dom->find('.quantity-group-input');
        $stock = ($quantityNode->count() && (int)$quantityNode->getAttribute('max')) ? 'In stock' : 'Out of stock'; // Доступность товара, если есть на складе хотя бы одна единица товара то выводим что есть



        $photoNodes = $dom->find('datalist option') ? $dom->find('datalist option') : [];
        $photos = [];

        foreach ($photoNodes as $content)
        {
            $photos[] = $content->getAttribute('value');
        }

        return array_merge($itemData, [
            'SKU' => $SKU,
            'name' => $name,
            'description' => $description,
            'category' => $category,
            'photos' => $photos,
            'price' => $price,
            'salePrice' => $salePrice,
            'stock' => $stock
        ]);
    } catch (Exception $exception) {
        return null;
    }
}

$fetchXml = simplexml_load_file('https://www.electronictoolbox.com/sitemap.xml');
$sitemapXml = $fetchXml->{'sitemap'};
$additionalDataXML = [];

$index = 0;
foreach ($sitemapXml as $element) {
    $addressAdditionalSitemap = $element->{'loc'};

    if ($addressAdditionalSitemap && !$index) {
        $fetchAdditionalSitemap = simplexml_load_file($addressAdditionalSitemap);
//        var_dump($fetchAdditionalSitemap);
        $additionalDataXML[] = $fetchAdditionalSitemap;
    }
    $index += 1;
}


$dataLintToProduct = [];
foreach ($additionalDataXML as $element) {
    $arrayElements = $element->{'url'};

    foreach ($arrayElements as $item) {
        $location = $item->{'loc'};

        if ($location && strpos($location, '/product/')) {
            $dataLintToProduct[] = $location;
        }
    }
}

$productsData = [];
$testArray = array_slice($dataLintToProduct, 0, 3);
foreach ($testArray as $productLink) { // $testArray $dataLintToProduct
    $data = getItemInfo($productLink);
    if($data) {
        $productsData[] = $data;
    }
}
$fileName = 'products.json';
$jsonFile = fopen($fileName, "w");
fwrite($jsonFile, json_encode($productsData));
fclose($jsonFile);

$time_end = microtime(true);
$execution_time = ($time_end - $time_start) / 60;
echo '<b>Время выполнения скрипта:</b> ' . $execution_time . ' Минут </br>';
echo '<b>Ссылка на скачивание:</b> <a target="_blank" download href="./' . $fileName . '">Файл</a></br>';