<?php

use Diglin\Ricardo\Managers\SellerAccount\Parameter\OpenArticlesParameter;

ini_set('display_errors', 1);

require_once '../../app/Mage.php';

Mage::app('admin')->setUseSessionInUrl(false);

set_include_path(get_include_path() . PATH_SEPARATOR . realpath(dirname(__DIR__) . '/tests/src'));

require_once '../../app/code/community/Diglin/Ricento/Model/SplAutoloader.php';
$autoload = new Diglin_Ricento_Model_SplAutoloader(null, realpath(dirname(__DIR__) . '/lib/Diglin'));
$autoload->register();

/**
 * Retrieve the open articles and add missing information into the product listing item
 * To use in case of sync problem
 */
$sellerAccount = new Diglin_Ricento_Model_Api_Services_Selleraccount();
$sellerAccount->setCanUseCache(false);

$openParameter = new OpenArticlesParameter();
$openParameter->setPageSize(400); // Make it configurable otherwise we may miss articles
$openArticles = $sellerAccount->getOpenArticles($openParameter);

if (!isset($openArticles['TotalLines']) || $openArticles['TotalLines'] <= 0) {
    echo 'No opened articles found!';
    exit;
}

echo 'Total Lines found ' . $openArticles['TotalLines'] . PHP_EOL;

$i = 0;
foreach ($openArticles['OpenArticles'] as $openArticle) {

    if (!isset($openArticle['ArticleInternalReferences'][0]['InternalReferenceValue'])) {
        echo 'Open Article: Internal Reference not found. Skipped!' . PHP_EOL;
        continue;
    }

    $internalReference = Mage::helper('diglin_ricento')->extractInternalReference($openArticle['ArticleInternalReferences'][0]['InternalReferenceValue']);
    $item = Mage::getModel('diglin_ricento/products_listing_item')->load($internalReference->getItemId());

    if ($item->getId()) {

        if ($item->getRicardoArticleId() != $openArticle['ArticleId']) {
            $item
                ->setQtyInventory($openArticle['AvailableQuantity'])
                ->setRicardoArticleId($openArticle['ArticleId'])
                ->setIsPlanned(0)
                ->setStatus(Diglin_Ricento_Helper_Data::STATUS_LISTED);

            if (Mage::getIsDeveloperMode()) {
                print_r($item->getData());
            } else {
                $item->save();
                echo Mage::helper('diglin_ricento')->__('Ricardo Article ID %s saved in Item ID %s', $item->getRicardoArticleId(), $item->getId()) . PHP_EOL;
            }
        } else {
            echo Mage::helper('diglin_ricento')->__('ricardo Article ID %s skipped because ID is same as previously.', $item->getRicardoArticleId()) . PHP_EOL;
        }

        $i++;
    }
}
echo Mage::helper('diglin_ricento')->__('Total tested %d items', $i);
