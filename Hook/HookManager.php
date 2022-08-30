<?php
/*************************************************************************************/
/*      Copyright (c) Open Studio                                                    */
/*      web : https://open.studio                                                    */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE      */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

/**
 * Created by Théo Robillard, OpenStudio
 * Date: 26/08/2022 22:43
 */

namespace PayGreenClimateKit\Hook;

use PayGreenClimateKit\PayGreenClimateKit;
use PayGreenClimateKit\Service\PaygreenApiService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Assets\AssetResolverInterface;
use Thelia\Log\Tlog;
use TheliaSmarty\Template\SmartyParser;

class HookManager extends BaseHook
{
    protected PaygreenApiService $climateClient;

    public function __construct(
        SmartyParser $parser,
        AssetResolverInterface $resolver,
        EventDispatcherInterface $eventDispatcher,
        PaygreenApiService $climateClient)
    {
        parent::__construct($parser, $resolver, $eventDispatcher);

        $this->climateClient = $climateClient;
    }

    /**
     * On déclare ici les hooks traités par cette classe. De cette manière, on peut déclarer le hook
     * comme un service normal dans le config.xml, et lui passer des données
     *
     * @return \string[][][]
     */
    public static function getSubscribedHooks()
    {
        return [
            "module.configuration" => [
                [
                    "type" => "back",
                    "method" => "onModuleConfigure"
                ]
            ],
            "order-edit.cart-bottom" => [
                [
                    "type" => "back",
                    "method" => "onOrderEditCartBottom"
                ]
            ],
            "order-invoice.javascript-initialization" => [
                [
                    "type" => "front",
                    "method" => "onOrderInvoiceJavascript"
                ]
            ]
        ];
    }


    public function onModuleConfigure(HookRenderEvent $event): void
    {
        $vars = [
            'accountName' => PayGreenClimateKit::getConfigValue('accountName'),
            'userName' => PayGreenClimateKit::getConfigValue('userName'),
            'password' => PayGreenClimateKit::getConfigValue('password'),
            'mode' => PayGreenClimateKit::getConfigValue('mode'),
            'transportationExternalId' => PayGreenClimateKit::getConfigValue('transportationExternalId', PayGreenClimateKit::DEFAULT_TRANSPORTATION_EXTERNAL_ID),
        ];

        $event->add(
            $this->render('paygreen-climatekit/module-configuration.html', $vars)
        );
    }

    public function onOrderEditCartBottom(HookRenderEvent $event): void
    {
        $event->add(
            $this->render('paygreen-climatekit/order-edit.cart-bottom.html')
        );
    }

    /**
     * @param HookRenderEvent $event
     * @return void
     */
    public function onOrderInvoiceJavascript(HookRenderEvent $event): void
    {
        try {
            $userId = $this->climateClient->getCurrentUserId();

            $vars = [
                'paygreenUser' => $userId,
                'paygreenToken' => $this->climateClient->getAccessToken(),
                'paygreenFootprintId' => $this->climateClient->getFootPrintId(),
                'paygreenTestMode' => $this->climateClient->isTestMode(),
                'paygreenTransportationExternalId' => PayGreenClimateKit::getConfigValue('transportationExternalId'),
                'paygreenCompensationProductRef' => PayGreenClimateKit::COMPENSATION_PRODUCT_REF,
                'paygreenContributionInCart' =>
                    null !== PayGreenClimateKit::findCompensationItemInCart(
                        $this->getSession(),
                        $this->dispatcher
                    )
            ];

            $event->add(
                $this->render('paygreen-climatekit/order-invoice.javascript-initialization.html', $vars)
            );
        } catch (\Exception $ex) {
            Tlog::getInstance()->error("Failed to get Climate data from PayGreen API: " . $ex->getMessage());
        }
    }
}
