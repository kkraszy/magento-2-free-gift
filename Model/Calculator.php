<?php
declare(strict_types=1);

namespace Kkkonrad\FreeGift\Model;

use Magento\SalesRule\Model\Rule\Action\Discount\DataFactory;
use Magento\SalesRule\Model\Rule\Action\Discount\DiscountInterface;

/**
 * Called by the rule engine for every cart item the rule applies to. Gifts
 * themselves never get here (they carry no_discount), so a gift cannot keep
 * its own rule alive through the "Apply to" conditions.
 */
class Calculator implements DiscountInterface
{
    public function __construct(private readonly DataFactory $discountData)
    {
    }

    public function calculate($rule, $item, $qty)
    {
        $quote = $item->getQuote();
        $fired = $quote->getData(Gifts::FIRED) ?: [];
        $fired[(int)$rule->getId()] = [
            'skus' => Gifts::parseSkus((string)$rule->getData(Gifts::SKUS)),
            'qty' => max(1, (int)$rule->getDiscountAmount()),
        ];
        $quote->setData(Gifts::FIRED, $fired);

        return $this->discountData->create();
    }

    public function fixQuantity($qty, $rule)
    {
        return $qty;
    }
}
