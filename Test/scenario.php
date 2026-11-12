<?php
// php gift-step.php <quoteId|new> <op> [arg]  - one "request" per process
require '/var/www/html/app/bootstrap.php';
$om = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER)->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('frontend');
$repo = $om->get(\Magento\Quote\Api\CartRepositoryInterface::class);
$products = $om->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
[, $id, $op, $arg] = $argv + [3 => null];
if ($id === 'new') { $quote = $om->create(\Magento\Quote\Model\Quote::class)->setStore($om->get(\Magento\Store\Model\StoreManagerInterface::class)->getStore())->setCustomerIsGuest(1); $repo->save($quote); }
else $quote = $repo->get($id);
switch ($op) {
  case 'add': $quote->addProduct($products->get($arg), 1); break;
  case 'qty': [$sku, $q] = explode('=', $arg); foreach ($quote->getAllVisibleItems() as $i) if ($i->getSku() === $sku && !$i->getOptionByCode('kk_free_gift')) $i->setQty($q); break;
  case 'giftqty': foreach ($quote->getAllVisibleItems() as $i) if ($i->getOptionByCode('kk_free_gift')) $i->setQty($arg); break;
  case 'coupon': $quote->setCouponCode($arg); break;
  case 'pick': [$r, $sku] = explode('=', $arg); $g = $om->get(\Kkkonrad\FreeGift\Model\Gifts::class); $quote->collectTotals();
    $ok = isset($g->available($quote)[$r]) && $g->add($quote, (int)$r, $sku, 1); echo $ok ? "picked\n" : "pick refused\n"; break;
  case 'delete': $repo->delete($quote); exit;
}
$quote->setTotalsCollectedFlag(false)->collectTotals(); $repo->save($quote);
$rows = array_map(fn($i) => $i->getSku().'x'.(float)$i->getQty().'@'.(float)$i->getCalculationPrice().($i->getOptionByCode('kk_free_gift') ? '[gift]' : ''), $quote->getAllVisibleItems());
$avail = array_map(fn($g) => implode('|', $g['skus']).' left '.$g['left'], $om->get(\Kkkonrad\FreeGift\Model\Gifts::class)->available($quote));
printf("%s %-22s %s | subtotal %.2f coupon %s | choose: %s\n", $quote->getId(), "$op $arg", implode(', ', $rows), $quote->getSubtotal(), $quote->getCouponCode() ?: '-', json_encode($avail));
