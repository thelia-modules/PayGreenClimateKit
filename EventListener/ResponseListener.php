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
 * Date: 27/08/2022 10:28.
 */

namespace PayGreenClimateKit\EventListener;

use Detection\MobileDetect;
use Paygreen\Sdk\Climate\V2\Model\WebBrowsingData;
use PayGreenClimateKit\Service\PaygreenApiService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Log\Tlog;

class ResponseListener implements EventSubscriberInterface
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
     * {@inheritdoc}
     * api.
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => ['beforeResponse', 10],
        ];
    }

    /**
     * Add Paygreen navigation data for each returned response.
     *
     * @throws \Exception
     */
    public function beforeResponse(ResponseEvent $event): void
    {
        if (TheliaRequest::$isAdminEnv || str_contains($event->getRequest()->getRequestUri(), '/_wdt')) {
            return;
        }

        try {
            $md = new MobileDetect();

            $device = 'Desktop';

            if ($md->isMobile()) {
                $device = 'Mobile';
            }

            if ($md->isTablet()) {
                $device = 'Tablet';
            }
            if ($md->isTV()) {
                $device = 'TV';
            }

            $webBrowsingData = new WebBrowsingData();
            $webBrowsingData->setUserAgent($event->getRequest()->headers->get('User-Agent'));
            $webBrowsingData->setPageCount(1);
            $webBrowsingData->setImageCount(substr_count($event->getResponse()->getContent(), '<img')); // sort of...
            $webBrowsingData->setDevice($device);

            $this->climateClient->addWebBrowsingData($webBrowsingData);
        } catch (\Exception $ex) {
            Tlog::getInstance()->error("Failed to store PayGreen navigation data, please check module configuration:" . $ex->getMessage());
        }
    }
}
