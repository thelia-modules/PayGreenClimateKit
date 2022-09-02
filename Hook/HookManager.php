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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Assets\AssetResolverInterface;
use Thelia\Log\Tlog;
use TheliaSmarty\Template\SmartyParser;

class HookManager extends BaseHook
{
    protected const BASE_THEME_COLOR_CARBON_BOT = '#556B2F';
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
            "main.body-bottom" => [
                [
                    "type" => "front",
                    "method" => "onBodyBottom"
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
            'showCarbonBotOnAllPages' => (bool) PayGreenClimateKit::getConfigValue('showCarbonBotOnAllPages', 1),
            'colorThemeCarbonBot' => PayGreenClimateKit::getConfigValue('colorThemeCarbonBot', self::BASE_THEME_COLOR_CARBON_BOT),
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
     * Display carbon bot
     *
     * @param HookRenderEvent $event
     * @return void
     */
    public function onBodyBottom(HookRenderEvent $event): void
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
                'paygreenCarbonBotOnAllPages' => (bool) PayGreenClimateKit::getConfigValue('showCarbonBotOnAllPages', 1) ? 'true' : 'false',
                'colorThemeCarbonBot' => PayGreenClimateKit::getConfigValue('colorThemeCarbonBot', self::BASE_THEME_COLOR_CARBON_BOT),
                'paygreenContributionInCart' =>
                    null !== PayGreenClimateKit::findCompensationItemInCart(
                        $this->getSession(),
                        $this->dispatcher
                    )
            ];

            $event->add(
                $this->render('paygreen-climatekit/main.body-bottom.html', $vars)
            );
        } catch (\Exception $ex) {
            Tlog::getInstance()->error("Failed to get Climate data from PayGreen API: " . $ex->getMessage());
        }
    }
}
