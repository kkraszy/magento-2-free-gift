<?php
declare(strict_types=1);

namespace Kkkonrad\FreeGift\Plugin;

use Kkkonrad\FreeGift\Model\Gifts;
use Magento\Quote\Model\Quote;

/**
 * After totals: add/trim/remove gift lines to match the rules that fired,
 * then collect once more so the totals include the change.
 */
class SyncGifts
{
    private bool $running = false;

    public function __construct(private readonly Gifts $gifts)
    {
    }

    public function aroundCollectTotals(Quote $quote, callable $proceed)
    {
        if ($this->running || $quote->getTotalsCollectedFlag()) {
            return $proceed();
        }
        $quote->setData(Gifts::FIRED, []);
        $result = $proceed();

        $this->running = true;
        try {
            if ($this->gifts->sync($quote)) {
                $quote->setData(Gifts::FIRED, []);
                $quote->setTotalsCollectedFlag(false);
                $proceed();
            }
        } finally {
            $this->running = false;
        }
        return $result;
    }
}
