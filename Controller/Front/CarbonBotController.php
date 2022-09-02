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
use PayGreenClimateKit\Service\PaygreenApiService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\JsonResponse;
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
        $this->updateItemAddedToCart($request, $session, $eventDispatcher);

        return $this->generateRedirect(URL::getInstance()->absoluteUrl('/order/invoice'));
    }

    /**
     * @param Request $request
     * @param Session $session
     * @param EventDispatcherInterface $eventDispatcher
     * @return JsonResponse|Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function addModernCarbonCompensationToCartAction(Request $request, Session $session, EventDispatcherInterface $eventDispatcher): JsonResponse|Response
    {
        $this->updateItemAddedToCart($request, $session, $eventDispatcher);

        return new JsonResponse([]);
    }

    /**
     * @param Session $session
     * @param EventDispatcherInterface $eventDispatcher
     * @return JsonResponse|Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function removeModernCarbonCompensationToCartAction(Session $session, EventDispatcherInterface $eventDispatcher): JsonResponse|Response
    {
        $this->updateItemRemovedToCart($session, $eventDispatcher);
        return new JsonResponse([]);
    }

    /**
     * @param Session $session
     * @param EventDispatcherInterface $dispatcher
     * @return Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function removeCarbonCompensationFromCartAction(Session $session, EventDispatcherInterface $eventDispatcher): Response
    {
        $this->updateItemRemovedToCart($session, $eventDispatcher);
        return $this->generateRedirect(URL::getInstance()->absoluteUrl('/order/invoice'));
    }

    public function clearFootprint(PaygreenApiService $apiService)
    {
        $apiService->clearFootPrintId();

        return new Response("OK");
    }

    protected function updateItemAddedToCart(Request $request, Session $session, EventDispatcherInterface $eventDispatcher): void
    {
        // Price is in cents.
        $price = round((float) $request->get('price', -1) / 100, 2);

        if ($price <= 0) {
            return;
        }

        if (null === $product = ProductQuery::create()->findOneByRef(PayGreenClimateKit::COMPENSATION_PRODUCT_REF)) {
            return;
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
    }

    protected function updateItemRemovedToCart(Session $session, EventDispatcherInterface $eventDispatcher): void
    {
        if (null !== $cartItem = PayGreenClimateKit::findCompensationItemInCart($session, $eventDispatcher)) {
            $cartEvent = (new CartEvent($session->getSessionCart($eventDispatcher)))
                ->setCartItemId($cartItem->getId());

            $eventDispatcher->dispatch($cartEvent, TheliaEvents::CART_DELETEITEM);
        }
    }
}
