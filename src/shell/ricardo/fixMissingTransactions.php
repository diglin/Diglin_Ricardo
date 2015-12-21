<?php

use Diglin\Ricardo\Managers\SellerAccount\Parameter\SoldArticlesParameter;

ini_set('display_errors', 1);

require_once '../../app/Mage.php';

Mage::app('admin')->setUseSessionInUrl(false);

set_include_path(get_include_path() . PATH_SEPARATOR . realpath(dirname(__DIR__) . '/tests/src'));

require_once '../../app/code/community/Diglin/Ricento/Model/SplAutoloader.php';
$autoload = new Diglin_Ricento_Model_SplAutoloader(null, realpath(dirname(__DIR__) . '/lib/Diglin'));
$autoload->register();

$websiteId = 1;
$articleIds = array('780416698', '766926356'); // @todo set empty array if you don't want specific article to import
$minimumEndDate = 3600*24*30; // 1 month old

$sellerAccountService = Mage::getSingleton('diglin_ricento/api_services_selleraccount')->setCanUseCache(false);
$sellerAccountService->setCurrentWebsite($websiteId);

$transaction = Mage::getModel('diglin_ricento/dispatcher_transaction');
$transaction->setLimit(2000);

$soldArticles = $transaction->getSoldArticlesList($articleIds, $minimumEndDate);

if (count($soldArticles) <= 0) {
    echo 'No sold articles found!';
    exit;
}

echo 'Total Lines found ' . count($soldArticles) . PHP_EOL;

$i = 0;
$result = [];
foreach ($soldArticles as $soldArticle) {

    if (!isset($soldArticle['ArticleInternalReferences'][0]['InternalReferenceValue'])) {
        echo 'Open Article: Internal Reference not found. Skipped!' . PHP_EOL;
        continue;
    }

    $internalReference = Mage::helper('diglin_ricento')->extractInternalReference($soldArticle['ArticleInternalReferences'][0]['InternalReferenceValue']);
    $item = Mage::getModel('diglin_ricento/products_listing_item')->load($internalReference->getItemId());

    if ($item->getId()) {

        if (!in_array($item->getStatus(), array(Diglin_Ricento_Helper_Data::STATUS_LISTED, Diglin_Ricento_Helper_Data::STATUS_SOLD))) {
            $item->setStatus(Diglin_Ricento_Helper_Data::STATUS_LISTED); // Used temporary to allow to save the transaction but status not saved
        }
        $result[] = $transaction->getSoldArticles(array($soldArticle['ArticleId']), $item, $minimumEndDate);
        $i++;

    }
}

print_r($result);

echo Mage::helper('diglin_ricento')->__('Total tested %d items', $i);
