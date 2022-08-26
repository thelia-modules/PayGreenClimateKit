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

use Http\Client\Curl\Client;
use Paygreen\Sdk\Climate\V2\Environment;
use PayGreenClimateKit\PayGreenClimateKit;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Log\Tlog;

class HookManager extends BaseHook
{
    public function onModuleConfigure(HookRenderEvent $event): void
    {
        $vars = [
            'accountName' => PayGreenClimateKit::getConfigValue('accountName'),
            'userName' => PayGreenClimateKit::getConfigValue('userName'),
            'password' => PayGreenClimateKit::getConfigValue('password'),
            'clientId' => PayGreenClimateKit::getConfigValue('clientId'),
            'mode'     => PayGreenClimateKit::getConfigValue('mode'),
        ];

        $event->add(
            $this->render('paygreen-climatekit/module-configuration.html', $vars)
        );
    }

    public function onOrderInvoiceJavascript(HookRenderEvent $event): void
    {
        $accountName = PayGreenClimateKit::getConfigValue('accountName');
        $userName = PayGreenClimateKit::getConfigValue('userName');
        $password = PayGreenClimateKit::getConfigValue('password');
        $clientId = PayGreenClimateKit::getConfigValue('clientId');

        $curl = new Client();

        $environment = new Environment(
            $clientId,
            Environment::ENVIRONMENT_PRODUCTION,
            Environment::API_VERSION_2
        );

        // @todo configurable
        $testMode = true;

        if ($testMode) {
            $environment->setTestMode(true);
        }

        $climateKitClient = new \Paygreen\Sdk\Climate\V2\Client($curl, $environment);

        // Se connecter à PayGreen
        $response = $climateKitClient->login($accountName, $userName, $password);
        $responseData = json_decode($response->getBody()->getContents());

        if (false === $responseData || !isset($responseData->access_token)) {
            Tlog::getInstance()->error('Failed to log to PayGreen climate kit, please check module configuration: '.print_r($responseData, 1));

            return;
        }

        $accessToken = $responseData->access_token;
        $climateKitClient->setBearer($accessToken);

        // Récupérer les infos utiulisateur (l'ID notamment)
        $response = $climateKitClient->getCurrentUserInfos();
        $responseData = json_decode($response->getBody()->getContents());

        if (false === $responseData || !isset($responseData->idUser)) {
            Tlog::getInstance()->error('Failed to get PayGreen climate kit user info:'.print_r($responseData, 1));

            return;
        }

        $userId = $responseData->idUser;

        // Créer le footprint carbone
        $footPrintId = $this->getRequest()->getSession()->getId();

        $climateKitClient->createEmptyFootprint($footPrintId);

        $vars = [
            'paygreenUser' => $userId,
            'paygreenToken' => $accessToken,
            'paygreenFootprintId' => $footPrintId,
            'paygreenTestMode' => $testMode,
            'paygreenContributionInCart' => null !== PayGreenClimateKit::findCompensationItemInCart(
                $this->getSession(),
                $this->dispatcher
            ),
        ];

        $event->add(
            $this->render('paygreen-climatekit/order-invoice.javascript-initialization.html', $vars)
        );
    }
}
