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

namespace PayGreenClimateKit\Loop;

use PayGreenClimateKit\Model\PaygreenClimateOrderFootprint;
use PayGreenClimateKit\Model\PaygreenClimateOrderFootprintQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;

/**
 * @method int getOrderId()
 */
class PaygreenOrderFootprint extends BaseLoop implements PropelSearchLoopInterface
{
    /**
     * {@inheritdoc}
     */
    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('order_id')
        );
    }

    /**
     * @param LoopResult $loopResult
     * @return LoopResult
     */
    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var PaygreenClimateOrderFootprint $item */
        foreach ($loopResult->getResultDataCollection() as $item) {

            $loopResult->addRow(
                (new LoopResultRow())
                    ->set('ID', $item->getId())
                    ->set('ORDER_ID', $item->getOrderId())
                    ->set('FOOTPRINT_ID', $item->getFootprintId())
            );
        }

        return $loopResult;
    }

    /**
     * this method returns a Propel ModelCriteria.
     *
     * @return ModelCriteria
     */
    public function buildModelCriteria(): ModelCriteria
    {
        $search = PaygreenClimateOrderFootprintQuery::create();

        if (null !== $orderId = $this->getOrderId()) {
            $search->filterByOrderId($orderId, Criteria::IN);
        }

        return $search;
    }
}
