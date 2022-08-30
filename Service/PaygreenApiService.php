<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Created by Franck Allimant, OpenStudio <fallimant@openstudio.fr>
 * Projet: thelia25
 * Date: 26/08/2022.
 */

namespace PayGreenClimateKit\Service;

use Http\Client\Curl\Client;
use Paygreen\Sdk\Climate\V2\Environment;
use Paygreen\Sdk\Climate\V2\Model\WebBrowsingData;
use PayGreenClimateKit\ClimateKitExt\ClientExt;
use PayGreenClimateKit\PayGreenClimateKit;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Exception\TheliaProcessException;
use Thelia\Log\Tlog;

class PaygreenApiService
{
    protected const FOOTPRINT_ID_SESSION_VAR = 'paygreen.user.footprintid';

    protected ?\Paygreen\Sdk\Climate\V2\Client $climateClient = null;
    protected ?string $accessToken = null;

    protected RequestStack $requestStack;

    /**
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @throws \Exception
     */
    public function getCurrentUserId(): int
    {
        // Récupérer les infos utilisateur (l'ID notamment)
        $response = $this->getClimateKitClient()->getCurrentUserInfos();
        $responseData = json_decode($response->getBody()->getContents());

        if (false === $responseData || !isset($responseData->idUser)) {
            throw new TheliaProcessException('Failed to get PayGreen climate kit user info:'.print_r($responseData, 1));
        }

        return $responseData->idUser;
    }

    public function isTestMode(): bool
    {
        return 'TEST' === PayGreenClimateKit::getConfigValue('mode', 'TEST');
    }

    public function getAccessToken(): string
    {
        // To be sure that we have the token
        $this->getClimateKitClient();

        return $this->accessToken;
    }

    /**
     * Return a footprint ID. A new one is created if none was defined.
     */
    public function getFootPrintId(): string
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();

        // Generate a footprint ID in session if it is not defined
        if (null === $fpId = $session->get(self::FOOTPRINT_ID_SESSION_VAR)) {
            $fpId = md5(uniqid('paygreen', true));

            $response = $this->climateClient->createEmptyFootprint($fpId);

            // We store the ID in the user session only if it was successfully created on Paygreen, side.
            if ($this->checkApiResponse('createEmptyFootprint', $response)) {
                $session->set('paygreen.user.footprintid', $fpId);
            }
        }

        return $fpId;
    }

    /**
     * @throws \Exception
     */
    public function closeFootprint(string $footprintId, string $status): void
    {
        $response = $this->getClimateKitClient()->closeFootprint($footprintId, $status);

        $this->checkApiResponse('closeFootprint', $response);

        // Remove Footprint ID from session, we will create a new one as needed.
        $this->clearFootPrintId();
    }


    /**
     * Remove Footprint ID from user session
     */
    public function clearFootPrintId(): void
    {
        $this->requestStack->getCurrentRequest()->getSession()->remove(self::FOOTPRINT_ID_SESSION_VAR);
    }

    /**
     * @param WebBrowsingData $webBrowsingData
     * @return void
     * @throws \Exception
     */
    public function addWebBrowsingData(WebBrowsingData $webBrowsingData): void
    {
        $response = $this->getClimateKitClient()->addWebBrowsingData($this->getFootPrintId(), $webBrowsingData);

        $this->checkApiResponse('addWebBrowsingData', $response);

    }

    public function getCarbonEmissionFactor()
    {
        $response = $this->getClimateKitClient()->getEmissionFactors();

        if (! $this->checkApiResponse('getEmissionFactors', $response)) {
            return [];
        }

        $responseData = json_decode($response->getBody()->getContents());
    }

    /**
     * @throws \Exception
     */
    protected function createClimateClient(): array
    {
        $accountName = PayGreenClimateKit::getConfigValue('accountName');
        $userName = PayGreenClimateKit::getConfigValue('userName');
        $password = PayGreenClimateKit::getConfigValue('password');
        $testMode = $this->isTestMode();

        $curl = new Client();

        $environment = new Environment(
            $accountName,
            Environment::ENVIRONMENT_PRODUCTION,
            Environment::API_VERSION_2
        );

        if ($testMode) {
            $environment->setTestMode(true);
        }

        $climateKitClient = new ClientExt($curl, $environment, Tlog::getInstance());

        // Se connecter à PayGreen
        $response = $climateKitClient->login($accountName, $userName, $password);
        $responseData = json_decode($response->getBody()->getContents());

        if (false === $responseData || !isset($responseData->access_token)) {
            throw new TheliaProcessException('Failed to log to PayGreen climate kit, please check module configuration: '.print_r($responseData, 1));
        }

        $climateKitClient->setBearer($responseData->access_token);

        return [$climateKitClient,  $responseData->access_token];
    }

    /**
     * @param string $filePath
     * @return void
     */
    public function sendShopCatalog(string $filePath)
    {
        $response = $this->getClimateKitClient()->exportProductCatalog($filePath);

        return $this->checkApiResponse('exportProductCatalog', $response);
    }

    /**
     * @throws \Exception
     */
    protected function getClimateKitClient(): \Paygreen\Sdk\Climate\V2\Client
    {
        if (null === $this->climateClient) {
            [ $this->climateClient, $this->accessToken ] = $this->createClimateClient();
        }

        return $this->climateClient;
    }

    /**
     * Check an API call response, and log a message on failure.
     *
     * @param $response
     * @return bool
     */
    protected function checkApiResponse(string $serviceName, $response): bool
    {
        if ($response->getStatusCode()< 200 && $response->getStatusCode()> 299) {
            $responseData = json_decode($response->getBody()->getContents());

            Tlog::getInstance()->error(
                "Call to Paygreen service $serviceName failed : status:" . $responseData->status
                . ", reason:" . $responseData->title
                . ", detail:" . $responseData->detail
            );

            return false;
        }

        return true;
    }
}
