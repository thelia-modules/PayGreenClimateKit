<?php

namespace PayGreenClimateKit\ClimateKitExt\Request;

use Psr\Http\Message\RequestInterface;

class EmissionFactorsRequest extends \Paygreen\Sdk\Core\Request\Request
{
    /**
     * @return RequestInterface
     */
    public function getGetRequest(): RequestInterface
    {
        return $this->requestFactory->create(
            "/carbon/emissionFactors",
            null,
            'GET'
        )->withAuthorization()->getRequest();
    }
}
