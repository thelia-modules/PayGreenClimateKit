<?php
/*************************************************************************************/
/*      Copyright (c) OpenStudio                                                     */
/*      web : https://www.openstudio.fr                                              */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE      */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

/**
 * Created by Franck Allimant, OpenStudio <fallimant@openstudio.fr>
 * Projet: thelia25
 * Date: 26/08/2022
 */
namespace PayGreenClimateKit\Controller\Front;

use PayGreenClimateKit\PayGreenClimateKit;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\ProductQuery;
use Thelia\Tools\URL;

class CarbonBotController extends BaseFrontController
{
    /**
     * @param Request $request
     * @param Session $session
     * @param EventDispatcherInterface $eventDispatcher
     * @return Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function addCarbonCompensationToCartAction(Request $request, Session $session, EventDispatcherInterface $eventDispatcher): Response
    {
        $response = $this->generateRedirect(URL::getInstance()->absoluteUrl('/order/invoice'));

        if (0 >= $price = (float) $request->get('price', 0)) {
            return $response;
        }

        if (null === $product = ProductQuery::create()->findOneByRef(PayGreenClimateKit::COMPENSATION_PRODUCT_REF)) {
            return $response;
        }

        // Update product price in cart if it is already present
        if (null === $cartItem = PayGreenClimateKit::findCompensationItemInCart($session, $eventDispatcher)) {
            // Create
            $cartEvent = (new CartEvent($session->getSessionCart($eventDispatcher)))
                ->setQuantity(1)
                ->setProduct($product->getId())
                ->setProductSaleElementsId($product->getDefaultSaleElements()->getId());

            $eventDispatcher->dispatch($cartEvent, TheliaEvents::CART_ADDITEM);

            $cartItem = $cartEvent->getCartItem();
        }

        // Update cartItem price
        $cartItem
            ->setPrice($price)
            ->setPromo($price)
            ->setPromoPrice($price)
            ->save();

        return $response;
    }

    /**
     * @param Session $session
     * @param EventDispatcherInterface $dispatcher
     * @return Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function removeCarbonCompensationFromCartAction(Session $session, EventDispatcherInterface $eventDispatcher): Response
    {
        if (null !== $cartItem = PayGreenClimateKit::findCompensationItemInCart($session, $eventDispatcher)) {
            $cartEvent = (new CartEvent($session->getSessionCart($eventDispatcher)))
                ->setCartItemId($cartItem->getId());

            $eventDispatcher->dispatch($cartEvent, TheliaEvents::CART_DELETEITEM);
        }

        return $this->generateRedirect(URL::getInstance()->absoluteUrl('/order/invoice'));
    }
}
