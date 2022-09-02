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
 * Date: 26/08/2022 22:38
 */
namespace PayGreenClimateKit\Controller\Admin;

use PayGreenClimateKit\Form\ConfigForm;
use PayGreenClimateKit\PayGreenClimateKit;
use PayGreenClimateKit\Service\PaygreenApiService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Currency;
use Thelia\Model\Lang;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Tools\URL;

class ConfigureController extends BaseAdminController
{
    public function configure(Request $request, Translator $translator): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'paygreenClimatekit', AccessManager::UPDATE)) {
            return $response;
        }

        $configurationForm = $this->createForm(ConfigForm::getName());

        try {
            $form = $this->validateForm($configurationForm);

            // Get the form field values
            $data = $form->getData();

            foreach ($data as $name => $value) {
                if (\is_array($value)) {
                    $value = implode(';', $value);
                }

                PayGreenClimateKit::setConfigValue($name, $value);
            }

            // Log configuration modification
            $this->adminLogAppend(
                'paygreenClimatekit.configuration.message',
                AccessManager::UPDATE,
                'PayGreenClimateKit configuration updated'
            );

            // Redirect to the success URL,
            if ($request->get('save_mode') === 'stay') {
                // If we have to stay on the same page, redisplay the configuration page/
                $url = '/admin/module/PayGreenClimateKit';
            } else {
                // If we have to close the page, go back to the module back-office page.
                $url = '/admin/modules';
            }

            return $this->generateRedirect(URL::getInstance()->absoluteUrl($url));
        } catch (FormValidationException $ex) {
            $message = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            $message = $ex->getMessage();
        }
        $this->setupFormErrorContext(
            $translator->trans('PayGreenClimateKit configuration', [], PayGreenClimateKit::DOMAIN_NAME),
            $message,
            $configurationForm,
            $ex
        );

        return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/module/PayGreenClimateKit'));
    }

    /**
     * Download product catalog
     * @return Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function downloadCatalog(): Response
    {
        $filePath = $this->generateProductCatalog();

        $response = new StreamedResponse();
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->setCallback(function () use ($filePath): void {

            $fh = fopen($filePath, 'rb');

            while (!feof($fh)) {
                echo (fread($fh, 1024));

                ob_flush();
                flush();
            }

            fclose($fh);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="catalog.csv"');
        $response->send();

        @unlink($filePath);

        return$response;
    }

    /**
     * Send catalog to PayGreen
     * @param PaygreenApiService $apiService
     * @return Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function sendCatalog(PaygreenApiService $apiService): Response
    {
        $filePath = $this->generateProductCatalog();

        $success = $apiService->sendShopCatalog($filePath) ? 'y' : 'n';

        @unlink($filePath);

        return $this->generateRedirect(
            URL::getInstance()->absoluteUrl(
                '/admin/module/PayGreenClimateKit',
                ['uploadCatalogSuccess' => $success ]
            )
        );
    }

    /**
     * @return string
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function generateProductCatalog(): string
    {
        $fileName = tempnam(sys_get_temp_dir(), "paygreen_catalog");

        if ($fh = fopen($fileName, 'wb')) {
            $locale = Lang::getDefaultLanguage()->getLocale();
            $currency = Currency::getDefaultCurrency();
            $pseList = ProductSaleElementsQuery::create()
                ->orderByRef()
                ->find();

            $ligne = ['nom', 'ID-tech', 'code article', 'poids', 'prix hors taxe', 'categorie_1', 'categorie_2', 'categorie_3'];

            fputcsv($fh, $ligne);

            foreach ($pseList as $pse) {
                if ($pse->getProduct()->getRef() === PayGreenClimateKit::COMPENSATION_PRODUCT_REF) {
                    continue;
                }

                /** @var Category[] $pathCategory */
                $pathCategory = CategoryQuery::getPathToCategory($pse->getProduct()->getDefaultCategoryId());
                $ligne = [
                    $pse->getProduct()->setLocale($locale)->getTitle(),
                    $pse->getProduct()->getId(),
                    $pse->getRef() => preg_replace('/[^a-zA-Z\d_-]+/', '_', $pse->getRef()),
                    $pse->getWeight(),
                    $pse->getPricesByCurrency($currency)->getPrice(),
                    isset($pathCategory[0]) ? $pathCategory[0]->setLocale($locale)->getTitle() : '',
                    isset($pathCategory[1]) ? $pathCategory[0]->setLocale($locale)->getTitle() : '',
                    isset($pathCategory[2]) ? $pathCategory[0]->setLocale($locale)->getTitle() : '',
                ];

                fputcsv($fh, $ligne);
                flush();
            }

            fclose($fh);
        }

        return $fileName;
    }
}
