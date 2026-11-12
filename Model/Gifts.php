<?php
declare(strict_types=1);

namespace Kkkonrad\FreeGift\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\Checkout\Model\Session;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;

class Gifts implements ArgumentInterface
{
    public const ACTION = 'kk_free_gift';
    /** salesrule column */
    public const SKUS = 'kk_gift_skus';
    /** quote item option holding the rule id; also keeps gifts from merging with paid lines */
    public const OPTION = 'kk_free_gift';
    /** runtime quote data: rule id => ['skus' => string[], 'qty' => int], filled by Calculator */
    public const FIRED = 'kk_free_gift_fired';

    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly Session $checkoutSession,
        private readonly CartRepositoryInterface $quotes
    ) {
    }

    public static function parseSkus(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)), 'strlen')));
    }

    /** @return bool whether the cart changed */
    public function sync(Quote $quote): bool
    {
        $fired = $quote->getData(self::FIRED) ?: [];
        $used = [];
        $changed = false;

        foreach ($quote->getAllVisibleItems() as $item) {
            $ruleId = $this->ruleOf($item);
            if ($ruleId === null) {
                continue;
            }
            $gift = $fired[$ruleId] ?? null;
            $left = $gift ? $gift['qty'] - ($used[$ruleId] ?? 0) : 0;
            if ($left <= 0 || !in_array($item->getSku(), $gift['skus'], true)) {
                $quote->deleteItem($item);
                $changed = true;
                continue;
            }
            if ($item->getQty() > $left) {
                $item->setQty($left);
                $changed = true;
            }
            $this->makeFree($item);
            $used[$ruleId] = ($used[$ruleId] ?? 0) + $item->getQty();
        }

        // One SKU: nothing to choose, add it. Several: the customer picks in the cart.
        foreach ($fired as $ruleId => $gift) {
            $left = $gift['qty'] - ($used[$ruleId] ?? 0);
            if ($left > 0 && count($gift['skus']) === 1) {
                $changed = $this->add($quote, $ruleId, $gift['skus'][0], $left) || $changed;
            }
        }
        return $changed;
    }

    /** Remaining gift qty per fired rule. */
    public function available(Quote $quote): array
    {
        $left = [];
        foreach ($quote->getData(self::FIRED) ?: [] as $ruleId => $gift) {
            $left[$ruleId] = $gift + ['left' => $gift['qty']];
        }
        foreach ($quote->getAllVisibleItems() as $item) {
            $ruleId = $this->ruleOf($item);
            if (isset($left[$ruleId])) {
                $left[$ruleId]['left'] -= $item->getQty();
            }
        }
        return array_filter($left, fn ($gift) => $gift['left'] > 0);
    }

    public function add(Quote $quote, int $ruleId, string $sku, float $qty): bool
    {
        try {
            // forceReload: the option below must not leak onto the cached instance
            $product = $this->products->get($sku, false, $quote->getStoreId(), true);
        } catch (NoSuchEntityException) {
            return false;
        }
        if (!in_array($product->getTypeId(), [Type::TYPE_SIMPLE, Type::TYPE_VIRTUAL], true)) {
            return false;
        }
        $product->addCustomOption(self::OPTION, (string)$ruleId);
        $item = $quote->addProduct($product, new DataObject(['qty' => $qty]));
        if (!$item instanceof Item) {
            return false; // out of stock etc. - message string from the type instance
        }
        $this->makeFree($item);
        return true;
    }

    /**
     * Cart page: gifts the customer still has to pick, as
     * [['rule' => id, 'left' => n, 'products' => Product[]]].
     */
    public function choices(): array
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$quote->getId()) {
            return [];
        }
        if ($quote->getData(self::FIRED) === null) {
            // the cart page itself does not collect totals
            $count = count($quote->getAllVisibleItems());
            $quote->collectTotals();
            if (count($quote->getAllVisibleItems()) !== $count) {
                $this->quotes->save($quote);
            }
        }
        $result = [];
        foreach ($this->available($quote) as $ruleId => $gift) {
            if (count($gift['skus']) < 2) {
                continue;
            }
            $products = [];
            foreach ($gift['skus'] as $sku) {
                try {
                    $product = $this->products->get($sku, false, $quote->getStoreId());
                } catch (NoSuchEntityException) {
                    continue;
                }
                if ($product->isSalable()) {
                    $products[] = $product;
                }
            }
            if ($products) {
                $result[] = ['rule' => $ruleId, 'left' => $gift['left'], 'products' => $products];
            }
        }
        return $result;
    }

    public function isGift(Item $item): bool
    {
        return $this->ruleOf($item) !== null;
    }

    private function ruleOf(Item $item): ?int
    {
        $option = $item->getOptionByCode(self::OPTION);
        return $option ? (int)$option->getValue() : null;
    }

    private function makeFree(Item $item): void
    {
        $item->setCustomPrice(0)->setOriginalCustomPrice(0)->setNoDiscount(1);
        $item->getProduct()->setIsSuperMode(true);
    }
}
