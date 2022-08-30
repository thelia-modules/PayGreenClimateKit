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
 * Date: 30/08/2022
 */

namespace PayGreenClimateKit\ClimateKitExt;

use Paygreen\Sdk\Climate\V2\Request\FootprintRequest;
use PayGreenClimateKit\ClimateKitExt\Request\EmissionFactorsRequest;

class ClientExt extends \Paygreen\Sdk\Climate\V2\Client
{
    /**
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    public function getEmissionFactors()
    {
        $this->logger->info("Get emission factors.");

        $request = (new EmissionFactorsRequest($this->requestFactory, $this->environment))->getGetRequest();

        $this->setLastRequest($request);

        $response = $this->sendRequest($request);
        $this->setLastResponse($response);

        if (200 === $response->getStatusCode()) {
            $this->logger->info('Emission factors successfully retrieved.');
        }

        return $response;
    }

}
