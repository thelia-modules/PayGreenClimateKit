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

namespace PayGreenClimateKit;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\Event\Category\CategoryCreateEvent;
use Thelia\Core\Event\Category\CategoryDeleteEvent;
use Thelia\Core\Event\Category\CategoryUpdateEvent;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\Product\ProductCreateEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Files\FileConfiguration;
use Thelia\Files\FileManager;
use Thelia\Install\Database;
use Thelia\Model\CartItem;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Currency;
use Thelia\Model\Lang;
use Thelia\Model\ProductQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\Module\BaseModule;

class PayGreenClimateKit extends BaseModule
{
    /** @var string */
    public const DOMAIN_NAME = 'paygreenclimatekit';

    public const COMPENSATION_PRODUCT_REF = 'CLIMATEKIT-COMPENSATION';

    /**
     * Create the product and the category that will be added to the cart to compensate carbon cost.
     */
    public function postActivation(ConnectionInterface $con = null): void
    {
        // Do not create the product if it's already there
        if (ProductQuery::create()->filterByRef(self::COMPENSATION_PRODUCT_REF)->count() > 0) {
            return;
        }

        // Create a root category
        $categoryCreateEvent = (new CategoryCreateEvent())
            ->setParent(0)
            ->setVisible(false)
            ->setLocale('en_US')
            ->setTitle('Paygreen ClimateKit carbon compensation')
        ;

        $this->getDispatcher()->dispatch($categoryCreateEvent, TheliaEvents::CATEGORY_CREATE);

        // Create the French version
        $categoryUpdateEvent = (new CategoryUpdateEvent($categoryCreateEvent->getCategory()->getId()))
            ->setParent(0)
            ->setVisible(false)
            ->setLocale('fr_FR')
            ->setTitle('Compensation carbone avec Paygreen ClimateKit')
            ;

        $this->getDispatcher()->dispatch($categoryUpdateEvent, TheliaEvents::CATEGORY_UPDATE);

        // Use the default tax rule to create the compensation product
        $taxRuleId = TaxRuleQuery::create()->findOneByIsDefault(true)->getId();

        // We create the product in the default currency.
        $currencyId = Currency::getDefaultCurrency()->getId();

        $createProductEvent = (new ProductCreateEvent())
            ->setRef(self::COMPENSATION_PRODUCT_REF)
            ->setLocale('en_US')
            ->setTitle('Carbon compensation for this order')
            ->setVisible(false)
            ->setVirtual(true)
            ->setTaxRuleId($taxRuleId)
            ->setDefaultCategory($categoryCreateEvent->getCategory()->getId())
            ->setBasePrice(0)
            ->setCurrencyId($currencyId)
            ->setBaseWeight(0);

        $this->getDispatcher()->dispatch($createProductEvent, TheliaEvents::PRODUCT_CREATE);

        // Update product Info
        $updateProductEvent = (new ProductUpdateEvent($createProductEvent->getProduct()->getId()))
            ->setRef(self::COMPENSATION_PRODUCT_REF)
            ->setLocale('fr_FR')
            ->setTitle('Compensation carbone pour votre commande')
            ->setVisible(false)
            ->setVirtual(true)
            ->setTaxRuleId($taxRuleId)
            ->setDefaultCategory($categoryCreateEvent->getCategory()->getId())
            ->setBasePrice(0)
            ->setCurrencyId($currencyId)
            ->setBaseWeight(0)
            ->setQuantity(5000000) // Large amount toi provide infinite stock (kind of)
            ;

        // Add product image
        $imagePath = __DIR__.DS.'Config'.DS.'images'.DS.'climate-kit-image.png';

        @copy($imagePath, $imagePath.'.tmp');

        $imagePath .= '.tmp';

        /** @var FileManager $fileManager */
        $fileManager = $this->container->get('thelia.file_manager');

        $config = FileConfiguration::getImageConfig();

        $fileModel = $fileManager->getModelInstance($config['objectType'], 'product');

        $parentModel = $fileModel->getParentFileModel();

        $defaultTitle = 'ClimateKit logo';

        $fileModel
            ->setParentId($createProductEvent->getProduct()->getId())
            ->setLocale(Lang::getDefaultLanguage()->getLocale())
            ->setTitle($defaultTitle)
        ;

        $fileCreateOrUpdateEvent = new FileCreateOrUpdateEvent($createProductEvent->getProduct()->getId());
        $fileCreateOrUpdateEvent
            ->setModel($fileModel)
            ->setUploadedFile(new UploadedFile($imagePath, 'climate-kit-image.png'))
            ->setParentName($parentModel->getTitle())
        ;

        $this->getDispatcher()->dispatch($fileCreateOrUpdateEvent, TheliaEvents::IMAGE_SAVE);

        // Init database.
        $database = new Database($con);

        $database->insertSql(null, [__DIR__.'/Config/TheliaMain.sql']);
    }

    /**
     *  Remove compensation product and category.
     */
    public function postDeactivation(ConnectionInterface $con = null): void
    {
        if (null !== $product = ProductQuery::create()->findOneByRef(self::COMPENSATION_PRODUCT_REF)) {
            $categoryId = $product->getDefaultCategoryId();

            $this->getDispatcher()->dispatch(
                new ProductDeleteEvent($product->getId()),
                TheliaEvents::PRODUCT_DELETE
            );

            // Delete category if it has zero children
            if ((null !== $category = CategoryQuery::create()->findPk($categoryId)) && $category->countChild() === 0) {
                $this->getDispatcher()->dispatch(
                    new CategoryDeleteEvent($categoryId),
                    TheliaEvents::CATEGORY_DELETE
                );
            }
        }
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
        ->exclude([THELIA_MODULE_DIR.ucfirst(self::getModuleCode()).'/I18n/*'])
        ->autowire(true)
        ->autoconfigure(true);
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public static function findCompensationItemInCart(Session $session, EventDispatcherInterface $dispatcher): ?CartItem
    {
        foreach ($session->getSessionCart($dispatcher)->getCartItems() as $cartItem) {
            if ($cartItem->getProduct()->getRef() === self::COMPENSATION_PRODUCT_REF) {
                return $cartItem;
            }
        }

        return null;
    }
}
