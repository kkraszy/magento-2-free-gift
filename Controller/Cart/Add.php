<?php
declare(strict_types=1);

namespace Kkkonrad\FreeGift\Controller\Cart;

use Kkkonrad\FreeGift\Model\Gifts;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;

/** The cart's "Choose your gift" button. Form key is checked by the core CSRF validator (POST). */
class Add implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Session $session,
        private readonly Gifts $gifts,
        private readonly CartRepositoryInterface $quotes,
        private readonly ManagerInterface $messages,
        private readonly RedirectFactory $redirects
    ) {
    }

    public function execute()
    {
        $quote = $this->session->getQuote();
        $ruleId = (int)$this->request->getParam('rule');
        $sku = (string)$this->request->getParam('sku');

        $quote->setTotalsCollectedFlag(false)->collectTotals();
        $gift = $this->gifts->available($quote)[$ruleId] ?? null;

        if ($gift && in_array($sku, $gift['skus'], true) && $this->gifts->add($quote, $ruleId, $sku, 1)) {
            $quote->setTotalsCollectedFlag(false)->collectTotals();
            $this->quotes->save($quote);
            $this->messages->addSuccessMessage(__('Your free gift was added to the cart.'));
        } else {
            $this->messages->addErrorMessage(__('This gift is not available for your cart.'));
        }
        return $this->redirects->create()->setPath('checkout/cart');
    }
}
