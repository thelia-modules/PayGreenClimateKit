<?php
/*************************************************************************************/
/*      Copyright (c) Open Studio                                                    */
/*      web : https://open.studio                                                    */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE      */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

/**
 * Created by Franck Allimant, OpenStudio <fallimant@openstudio.fr>
 * Date: 29/08/2022 10:29
 */
namespace PayGreenClimateKit\EventListener;

use Paygreen\Sdk\Climate\V2\Enum\FootprintStatusEnum;
use PayGreenClimateKit\Model\PaygreenClimateOrderFootprint;
use PayGreenClimateKit\Model\PaygreenClimateOrderFootprintQuery;
use PayGreenClimateKit\PayGreenClimateKit;
use PayGreenClimateKit\Service\PaygreenApiService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;

class OrderListener implements EventSubscriberInterface
{
    protected PaygreenApiService $climateClient;

    /**
     * @param PaygreenApiService $climateClient
     */
    public function __construct(PaygreenApiService $climateClient)
    {
        $this->climateClient = $climateClient;
    }

    /**
     * @return array[]
     */
    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::ORDER_BEFORE_PAYMENT => ['saveOrderCarbonFootprint', 10],
            TheliaEvents::ORDER_UPDATE_STATUS  => ['orderStatusUpdate', 10],
            TheliaEvents::ORDER_CART_CLEAR     => ['clearFootprintId', 10],
        ];
    }

    /**
     * Close footprint depending on the order status
     *
     * @param OrderEvent $event
     * @return void
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function orderStatusUpdate(OrderEvent $event): void
    {
        $order = $event->getOrder();

        if (! ($order->isPaid(true) || $order->isCancelled())) {
            return;
        }

        // Do we have a footprint ?
        if (null === $carbonFootprint = PaygreenClimateOrderFootprintQuery::create()->findOneByOrderId($order->getId())) {
            return;
        }

        try {
            if ($event->getOrder()->isPaid(true)) {
                // Do we have the carbon compensation in the cart
                foreach ($order->getOrderProducts() as $orderProduct) {
                    if (PayGreenClimateKit::COMPENSATION_PRODUCT_REF === $orderProduct->getProductRef()) {
                        // Le client a contribué, bravo.
                        $this->climateClient->closeFootprint($carbonFootprint->getFootprintId(), FootprintStatusEnum::PURCHASED);

                        return;
                    }
                }

                // On n'a pas eu de contribution, sniff sniff
                $this->climateClient->closeFootprint($carbonFootprint->getFootprintId(), FootprintStatusEnum::CLOSED);
            }

            // If order is canceled,
            if ($event->getOrder()->isCancelled(false)) {
                $this->climateClient->closeFootprint($carbonFootprint->getFootprintId(), FootprintStatusEnum::CLOSED);
            }
        } catch (\Exception $ex) {
            Tlog::getInstance()->error("Failed to close Climate footprint: " . $ex->getMessage());
        }
    }

    /**
     * Save carbon footprint ID for a given order, so that we can find it for processing order status changes
     *
     * @param OrderEvent $event
     * @return void
     */
    public function saveOrderCarbonFootprint(OrderEvent $event): void
    {
        $orderId = $event->getOrder()->getId();

        try {
            (new PaygreenClimateOrderFootprint())
                ->setOrderId($orderId)
                ->setFootprintId($this->climateClient->getFootPrintId())
                ->save();
        } catch (\Exception $ex) {
            Tlog::getInstance()->error("Failed to save climate footprint for order $orderId: " . $ex->getMessage());
        }
    }

    /**
     * Remove footprint ID from user session after a successful order processing
     *
     * @param OrderEvent $event
     * @return void
     */
    public function clearFootprintId(OrderEvent $event): void
    {
        $this->climateClient->clearFootPrintId();
    }
}
