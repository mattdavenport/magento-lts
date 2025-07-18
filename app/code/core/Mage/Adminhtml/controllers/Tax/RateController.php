<?php

/**
 * @copyright  For copyright and license information, read the COPYING.txt file.
 * @link       /COPYING.txt
 * @license    Open Software License (OSL 3.0)
 * @package    Mage_Adminhtml
 */

/**
 * Adminhtml tax rate controller
 *
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Tax_RateController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Show Main Grid
     */
    public function indexAction()
    {
        $this->_title($this->__('Sales'))
             ->_title($this->__('Tax'))
             ->_title($this->__('Manage Tax Zones and Rates'));

        /** @var Mage_Adminhtml_Block_Tax_Rate_Toolbar_Add $block */
        $block = $this->getLayout()->createBlock('adminhtml/tax_rate_toolbar_add', 'tax_rate_toolbar');
        $this->_initAction()
            ->_addBreadcrumb(Mage::helper('tax')->__('Manage Tax Rates'), Mage::helper('tax')->__('Manage Tax Rates'))
            ->_addContent(
                $block
                    ->assign('createUrl', $this->getUrl('*/tax_rate/add'))
                    ->assign('header', Mage::helper('tax')->__('Manage Tax Rates')),
            )
            ->_addContent($this->getLayout()->createBlock('adminhtml/tax_rate_grid', 'tax_rate_grid'))
            ->renderLayout();
    }

    /**
     * Show Add Form
     */
    public function addAction()
    {
        $rateModel = Mage::getSingleton('tax/calculation_rate')->load(null);

        $this->_title($this->__('Sales'))
             ->_title($this->__('Tax'))
             ->_title($this->__('Manage Tax Zones and Rates'));

        $this->_title($this->__('New Rate'));

        $rateModel->setData(Mage::getSingleton('adminhtml/session')->getFormData(true));

        if ($rateModel->getZipIsRange() && !$rateModel->hasTaxPostcode()) {
            $rateModel->setTaxPostcode($rateModel->getZipFrom() . '-' . $rateModel->getZipTo());
        }

        /** @var Mage_Adminhtml_Block_Tax_Rate_Toolbar_Save $block */
        $block = $this->getLayout()->createBlock('adminhtml/tax_rate_toolbar_save');
        $this->_initAction()
            ->_addBreadcrumb(Mage::helper('tax')->__('Manage Tax Rates'), Mage::helper('tax')->__('Manage Tax Rates'), $this->getUrl('*/tax_rate'))
            ->_addBreadcrumb(Mage::helper('tax')->__('New Tax Rate'), Mage::helper('tax')->__('New Tax Rate'))
            ->_addContent(
                $block
                ->assign('header', Mage::helper('tax')->__('Add New Tax Rate'))
                ->assign('form', $this->getLayout()->createBlock('adminhtml/tax_rate_form')),
            )
            ->renderLayout();
    }

    /**
     * Save Rate and Data
     *
     * @return true|void
     * @throws Throwable
     */
    public function saveAction()
    {
        $ratePost = $this->getRequest()->getPost();
        if ($ratePost) {
            $rateId = $this->getRequest()->getParam('tax_calculation_rate_id');
            if ($rateId) {
                $rateModel = Mage::getSingleton('tax/calculation_rate')->load($rateId);
                if (!$rateModel->getId()) {
                    unset($ratePost['tax_calculation_rate_id']);
                }
            }

            $rateModel = Mage::getModel('tax/calculation_rate')->setData($ratePost);

            try {
                $rateModel->save();

                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('tax')->__('The tax rate has been saved.'));
                $this->getResponse()->setRedirect($this->getUrl('*/*/'));
                return true;
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('adminhtml/session')->setFormData($ratePost);
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }

            $this->_redirectReferer();
            return;
        }
        $this->getResponse()->setRedirect($this->getUrl('*/tax_rate'));
    }

    /**
     * Show Edit Form
     */
    public function editAction()
    {
        $this->_title($this->__('Sales'))
             ->_title($this->__('Tax'))
             ->_title($this->__('Manage Tax Zones and Rates'));

        $rateId = (int) $this->getRequest()->getParam('rate');
        $rateModel = Mage::getSingleton('tax/calculation_rate')->load(null);
        $rateModel->setData(Mage::getSingleton('adminhtml/session')->getFormData(true));
        if ($rateModel->getId() != $rateId) {
            $rateModel->load($rateId);
        }

        if (!$rateModel->getId()) {
            $this->getResponse()->setRedirect($this->getUrl('*/*/'));
            return;
        }

        if ($rateModel->getZipIsRange() && !$rateModel->hasTaxPostcode()) {
            $rateModel->setTaxPostcode($rateModel->getZipFrom() . '-' . $rateModel->getZipTo());
        }

        $this->_title(sprintf('%s', $rateModel->getCode()));

        /** @var Mage_Adminhtml_Block_Tax_Rate_Toolbar_Save $block */
        $block = $this->getLayout()->createBlock('adminhtml/tax_rate_toolbar_save');
        $this->_initAction()
            ->_addBreadcrumb(Mage::helper('tax')->__('Manage Tax Rates'), Mage::helper('tax')->__('Manage Tax Rates'), $this->getUrl('*/tax_rate'))
            ->_addBreadcrumb(Mage::helper('tax')->__('Edit Tax Rate'), Mage::helper('tax')->__('Edit Tax Rate'))
            ->_addContent(
                $block
                ->assign('header', Mage::helper('tax')->__('Edit Tax Rate'))
                ->assign('form', $this->getLayout()->createBlock('adminhtml/tax_rate_form')),
            )
            ->renderLayout();
    }

    /**
     * Delete Rate and Data
     *
     * @return true|void
     * @throws Throwable
     */
    public function deleteAction()
    {
        if ($rateId = $this->getRequest()->getParam('rate')) {
            $rateModel = Mage::getModel('tax/calculation_rate')->load($rateId);
            if ($rateModel->getId()) {
                try {
                    $rateModel->delete();

                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('tax')->__('The tax rate has been deleted.'));
                    $this->getResponse()->setRedirect($this->getUrl('*/*/'));
                    return true;
                } catch (Mage_Core_Exception $e) {
                    Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                } catch (Exception $e) {
                    Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tax')->__('An error occurred while deleting this rate.'));
                }
                if ($referer = $this->getRequest()->getServer('HTTP_REFERER')) {
                    $this->getResponse()->setRedirect($referer);
                } else {
                    $this->getResponse()->setRedirect($this->getUrl('*/*/'));
                }
            } else {
                Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tax')->__('An error occurred while deleting this rate. Incorrect rate ID.'));
                $this->getResponse()->setRedirect($this->getUrl('*/*/'));
            }
        }
    }

    /**
     * Export rates grid to CSV format
     */
    public function exportCsvAction()
    {
        $fileName   = 'rates.csv';
        $content    = $this->getLayout()->createBlock('adminhtml/tax_rate_grid')
            ->getCsvFile();

        $this->_prepareDownloadResponse($fileName, $content);
    }

    /**
     * Export rates grid to XML format
     */
    public function exportXmlAction()
    {
        $fileName   = 'rates.xml';
        $content    = $this->getLayout()->createBlock('adminhtml/tax_rate_grid')
            ->getExcelFile();

        $this->_prepareDownloadResponse($fileName, $content);
    }

    /**
     * Initialize action
     *
     * @return Mage_Adminhtml_Controller_Action
     */
    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('sales/tax/rates')
            ->_addBreadcrumb(Mage::helper('tax')->__('Sales'), Mage::helper('tax')->__('Sales'))
            ->_addBreadcrumb(Mage::helper('tax')->__('Tax'), Mage::helper('tax')->__('Tax'));
        return $this;
    }

    /**
     * Import and export Page
     *
     */
    public function importExportAction()
    {
        $this->_title($this->__('Sales'))
             ->_title($this->__('Tax'))
             ->_title($this->__('Manage Tax Zones and Rates'));

        $this->_title($this->__('Import and Export Tax Rates'));

        $this->loadLayout()
            ->_setActiveMenu('sales/tax/import_export')
            ->_addContent($this->getLayout()->createBlock('adminhtml/tax_rate_importExport'))
            ->renderLayout();
    }

    /**
     * import action from import/export tax
     *
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    public function importPostAction()
    {
        if ($this->getRequest()->isPost() && !empty($_FILES['import_rates_file']['tmp_name'])) {
            try {
                $this->_importRates();

                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('tax')->__('The tax rate has been imported.'));
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tax')->__('Invalid file upload attempt'));
            }
        } else {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tax')->__('Invalid file upload attempt'));
        }
        $this->_redirect('*/*/importExport');
    }

    /**
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    protected function _importRates()
    {
        $fileName   = $_FILES['import_rates_file']['tmp_name'];
        $csvObject  = new Varien_File_Csv();
        $csvData = $csvObject->getData($fileName);

        /** checks columns */
        $csvFields  = [
            0   => Mage::helper('tax')->__('Code'),
            1   => Mage::helper('tax')->__('Country'),
            2   => Mage::helper('tax')->__('State'),
            3   => Mage::helper('tax')->__('Zip/Post Code'),
            4   => Mage::helper('tax')->__('Rate'),
            5   => Mage::helper('tax')->__('Zip/Post is Range'),
            6   => Mage::helper('tax')->__('Range From'),
            7   => Mage::helper('tax')->__('Range To'),
        ];

        $stores = [];
        $unset = [];
        $storeCollection = Mage::getModel('core/store')->getCollection()->setLoadDefault(false);
        $cvsFieldsNum = count($csvFields);
        $cvsDataNum   = count($csvData[0]);
        for ($i = $cvsFieldsNum; $i < $cvsDataNum; $i++) {
            $header = $csvData[0][$i];
            $found = false;
            foreach ($storeCollection as $store) {
                if ($header == $store->getCode()) {
                    $csvFields[$i] = $store->getCode();
                    $stores[$i] = $store->getId();
                    $found = true;
                }
            }
            if (!$found) {
                $unset[] = $i;
            }
        }

        $regions = [];

        if ($unset) {
            foreach ($unset as $u) {
                unset($csvData[0][$u]);
            }
        }
        if ($csvData[0] == $csvFields) {
            /** @var Mage_Adminhtml_Helper_Data $helper */
            $helper = Mage::helper('adminhtml');

            foreach ($csvData as $k => $v) {
                if ($k == 0) {
                    continue;
                }

                //end of file has more then one empty lines
                // phpcs:ignore Ecg.Performance.Loop.ArraySize
                if (count($v) <= 1 && !strlen($v[0])) {
                    continue;
                }
                if ($unset) {
                    foreach ($unset as $u) {
                        unset($v[$u]);
                    }
                }

                // phpcs:ignore Ecg.Performance.Loop.ArraySize
                if (count($csvFields) != count($v)) {
                    Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tax')->__('Invalid file upload attempt'));
                }

                $country = Mage::getModel('directory/country')->loadByCode($v[1], 'iso2_code');
                if (!$country->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tax')->__('One of the country has invalid code.'));
                    continue;
                }

                if (!isset($regions[$v[1]])) {
                    $regions[$v[1]]['*'] = '*';
                    $regionCollection = Mage::getModel('directory/region')->getCollection()
                        ->addCountryFilter($v[1]);
                    if ($regionCollection->getSize()) {
                        foreach ($regionCollection as $region) {
                            $regions[$v[1]][$region->getCode()] = $region->getRegionId();
                        }
                    }
                }

                if (!empty($regions[$v[1]][$v[2]])) {
                    $rateData  = [
                        'code'           => $v[0],
                        'tax_country_id' => $v[1],
                        'tax_region_id'  => ($regions[$v[1]][$v[2]] == '*') ? 0 : $regions[$v[1]][$v[2]],
                        'tax_postcode'   => empty($v[3]) ? null : $v[3],
                        'rate'           => $v[4],
                        'zip_is_range'   => $v[5],
                        'zip_from'       => $v[6],
                        'zip_to'         => $v[7],
                    ];

                    $rateModel = Mage::getModel('tax/calculation_rate')->loadByCode($rateData['code']);
                    foreach ($rateData as $dataName => $dataValue) {
                        $rateModel->setData($dataName, $dataValue);
                    }

                    $titles = [];
                    foreach ($stores as $field => $id) {
                        $titles[$id] = $v[$field];
                    }

                    $rateModel->setTitle($titles);
                    // phpcs:ignore Ecg.Performance.Loop.ModelLSD
                    $rateModel->save();
                }
            }
        } else {
            Mage::throwException(Mage::helper('tax')->__('Invalid file format upload attempt'));
        }
    }

    /**
     * export action from import/export tax
     *
     */
    public function exportPostAction()
    {
        /** start csv content and set template */
        $headers = new Varien_Object([
            'code'         => Mage::helper('tax')->__('Code'),
            'country_name' => Mage::helper('tax')->__('Country'),
            'region_name'  => Mage::helper('tax')->__('State'),
            'tax_postcode' => Mage::helper('tax')->__('Zip/Post Code'),
            'rate'         => Mage::helper('tax')->__('Rate'),
            'zip_is_range' => Mage::helper('tax')->__('Zip/Post is Range'),
            'zip_from'     => Mage::helper('tax')->__('Range From'),
            'zip_to'       => Mage::helper('tax')->__('Range To'),
        ]);
        $template = '"{{code}}","{{country_name}}","{{region_name}}","{{tax_postcode}}","{{rate}}"'
                . ',"{{zip_is_range}}","{{zip_from}}","{{zip_to}}"';
        $content = $headers->toString($template);

        $storeTaxTitleTemplate       = [];
        $taxCalculationRateTitleDict = [];

        foreach (Mage::getModel('core/store')->getCollection()->setLoadDefault(false) as $store) {
            $storeTitle = 'title_' . $store->getId();
            $content   .= ',"' . $store->getCode() . '"';
            $template  .= ',"{{' . $storeTitle . '}}"';
            $storeTaxTitleTemplate[$storeTitle] = null;
        }
        unset($store);

        $content .= "\n";

        foreach (Mage::getModel('tax/calculation_rate_title')->getCollection() as $title) {
            $rateId = $title->getTaxCalculationRateId();

            if (!array_key_exists($rateId, $taxCalculationRateTitleDict)) {
                $taxCalculationRateTitleDict[$rateId] = $storeTaxTitleTemplate;
            }

            $taxCalculationRateTitleDict[$rateId]['title_' . $title->getStoreId()] = $title->getValue();
        }
        unset($title);

        $collection = Mage::getResourceModel('tax/calculation_rate_collection')
            ->joinCountryTable()
            ->joinRegionTable();

        while ($rate = $collection->fetchItem()) {
            if ($rate->getTaxRegionId() == 0) {
                $rate->setRegionName('*');
            }

            if (array_key_exists($rate->getId(), $taxCalculationRateTitleDict)) {
                $rate->addData($taxCalculationRateTitleDict[$rate->getId()]);
            } else {
                $rate->addData($storeTaxTitleTemplate);
            }

            $content .= $rate->toString($template) . "\n";
        }

        $this->_prepareDownloadResponse('tax_rates.csv', $content);
    }

    /**
     * @inheritDoc
     */
    protected function _isAllowed()
    {
        $action = strtolower($this->getRequest()->getActionName());
        $aclPath = match ($action) {
            'importexport' => 'sales/tax/import_export',
            default => 'sales/tax/rates',
        };

        return Mage::getSingleton('admin/session')->isAllowed($aclPath);
    }
}
