<?php
declare(strict_types=1);

namespace Kkkonrad\FreeGift\Plugin;

use Kkkonrad\FreeGift\Model\Gifts;
use Magento\SalesRule\Model\Rule\Action\SimpleActionOptionsProvider;

/** Adds "Add free gift" to the cart price rule "Apply" select. */
class ActionOption
{
    public function afterToOptionArray(SimpleActionOptionsProvider $subject, array $result): array
    {
        $result[] = ['value' => Gifts::ACTION, 'label' => __('Add free gift (SKUs below)')];
        return $result;
    }
}
