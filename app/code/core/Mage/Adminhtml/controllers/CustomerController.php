<?php

/**
 * @copyright  For copyright and license information, read the COPYING.txt file.
 * @link       /COPYING.txt
 * @license    Open Software License (OSL 3.0)
 * @package    Mage_Adminhtml
 */

/**
 * Customer admin controller
 *
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_CustomerController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'customer/manage';

    /**
     * Controller pre-dispatch method
     *
     * @return Mage_Adminhtml_Controller_Action
     */
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions(['delete', 'massDelete']);
        return parent::preDispatch();
    }

    /**
     * @param string $idFieldName
     * @return $this
     * @throws Mage_Core_Exception
     */
    protected function _initCustomer($idFieldName = 'id')
    {
        $this->_title($this->__('Customers'))->_title($this->__('Manage Customers'));

        $customerId = (int) $this->getRequest()->getParam($idFieldName);
        $customer = Mage::getModel('customer/customer');

        if ($customerId) {
            $customer->load($customerId);
        }

        Mage::register('current_customer', $customer);
        return $this;
    }

    /**
     * Customers list action
     */
    public function indexAction()
    {
        $this->_title($this->__('Customers'))->_title($this->__('Manage Customers'));

        if ($this->getRequest()->getQuery('ajax')) {
            $this->_forward('grid');
            return;
        }
        $this->loadLayout();

        /**
         * Set active menu item
         */
        $this->_setActiveMenu('customer/manage');

        /**
         * Append customers block to content
         */
        $this->_addContent(
            $this->getLayout()->createBlock('adminhtml/customer', 'customer'),
        );

        /**
         * Add breadcrumb item
         */
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Customers'), Mage::helper('adminhtml')->__('Customers'));
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Manage Customers'), Mage::helper('adminhtml')->__('Manage Customers'));

        $this->renderLayout();
    }

    public function gridAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Customer edit action
     */
    public function editAction()
    {
        $this->_initCustomer();
        $this->loadLayout();

        /** @var Mage_Customer_Model_Customer $customer */
        $customer = Mage::registry('current_customer');

        // set entered data if was error when we do save
        $data = Mage::getSingleton('adminhtml/session')->getCustomerData(true);

        // restore data from SESSION
        if ($data) {
            $request = clone $this->getRequest();
            $request->setParams($data);

            if (isset($data['account'])) {
                /** @var Mage_Customer_Model_Form $customerForm */
                $customerForm = Mage::getModel('customer/form');
                $customerForm->setEntity($customer)
                    ->setFormCode('adminhtml_customer')
                    ->setIsAjaxRequest(true);
                $formData = $customerForm->extractData($request, 'account');
                $customerForm->restoreData($formData);
            }

            if (isset($data['address']) && is_array($data['address'])) {
                /** @var Mage_Customer_Model_Form $addressForm */
                $addressForm = Mage::getModel('customer/form');
                $addressForm->setFormCode('adminhtml_customer_address');

                foreach (array_keys($data['address']) as $addressId) {
                    if ($addressId === '_template_') {
                        continue;
                    }

                    $address = $customer->getAddressItemById($addressId);
                    if (!$address) {
                        $address = Mage::getModel('customer/address');
                        $customer->addAddress($address);
                    }

                    $formData = $addressForm->setEntity($address)
                        ->extractData($request);
                    $addressForm->restoreData($formData);
                }
            }
        }

        $this->_title($customer->getId() ? $customer->getName() : $this->__('New Customer'));

        /**
         * Set active menu item
         */
        $this->_setActiveMenu('customer/manage');

        $this->renderLayout();
    }

    /**
     * Create new customer action
     */
    public function newAction()
    {
        $this->_forward('edit');
    }

    /**
     * Delete customer action
     */
    public function deleteAction()
    {
        $this->_initCustomer();
        $customer = Mage::registry('current_customer');
        if ($customer->getId()) {
            try {
                $customer->load($customer->getId());
                $customer->delete();
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('The customer has been deleted.'));
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/customer');
    }

    /**
     * Save customer action
     */
    public function saveAction()
    {
        $data = $this->getRequest()->getPost();
        if ($data) {
            $redirectBack = $this->getRequest()->getParam('back', false);
            $this->_initCustomer('customer_id');

            /** @var Mage_Customer_Model_Customer $customer */
            $customer = Mage::registry('current_customer');

            /** @var Mage_Customer_Model_Form $customerForm */
            $customerForm = Mage::getModel('customer/form');
            $customerForm->setEntity($customer)
                ->setFormCode('adminhtml_customer')
                ->ignoreInvisible(false)
            ;

            $formData = $customerForm->extractData($this->getRequest(), 'account');

            // Handle 'disable auto_group_change' attribute
            if (isset($formData['disable_auto_group_change'])) {
                $formData['disable_auto_group_change'] = empty($formData['disable_auto_group_change']) ? '0' : '1';
            }

            $errors = null;
            if ($customer->getId() && !empty($data['account']['new_password'])
                && Mage::helper('customer')->getIsRequireAdminUserToChangeUserPassword()
            ) {
                //Validate current admin password
                $currentPassword = $data['account']['current_password'] ?? null;
                unset($data['account']['current_password']);
                $errors = $this->_validateCurrentPassword($currentPassword);
            }

            if (!is_array($errors)) {
                $errors = $customerForm->validateData($formData);
            }

            if ($errors !== true) {
                foreach ($errors as $error) {
                    $this->_getSession()->addError($error);
                }
                $this->_getSession()->setCustomerData($data);
                $this->getResponse()->setRedirect($this->getUrl('*/customer/edit', ['id' => $customer->getId()]));
                return;
            }

            $customerForm->compactData($formData);

            // Unset template data
            if (isset($data['address']['_template_'])) {
                unset($data['address']['_template_']);
            }

            $modifiedAddresses = [];
            if (!empty($data['address'])) {
                /** @var Mage_Customer_Model_Form $addressForm */
                $addressForm = Mage::getModel('customer/form');
                $addressForm->setFormCode('adminhtml_customer_address')->ignoreInvisible(false);

                foreach (array_keys($data['address']) as $index) {
                    $address = $customer->getAddressItemById($index);
                    if (!$address) {
                        $address = Mage::getModel('customer/address');
                    }

                    $requestScope = sprintf('address/%s', $index);
                    $formData = $addressForm->setEntity($address)
                        ->extractData($this->getRequest(), $requestScope);

                    // Set default billing and shipping flags to address
                    $isDefaultBilling = isset($data['account']['default_billing'])
                        && $data['account']['default_billing'] == $index;
                    $address->setIsDefaultBilling($isDefaultBilling);
                    $isDefaultShipping = isset($data['account']['default_shipping'])
                        && $data['account']['default_shipping'] == $index;
                    $address->setIsDefaultShipping($isDefaultShipping);

                    $errors = $addressForm->validateData($formData);
                    if ($errors !== true) {
                        foreach ($errors as $error) {
                            $this->_getSession()->addError($error);
                        }
                        $this->_getSession()->setCustomerData($data);
                        $this->getResponse()->setRedirect($this->getUrl(
                            '*/customer/edit',
                            [
                                'id' => $customer->getId()],
                        ));
                        return;
                    }

                    $addressForm->compactData($formData);

                    // Set post_index for detect default billing and shipping addresses
                    $address->setPostIndex($index);

                    if ($address->getId()) {
                        $modifiedAddresses[] = $address->getId();
                    } else {
                        $customer->addAddress($address);
                    }
                }
            }

            // Default billing and shipping
            if (isset($data['account']['default_billing'])) {
                $customer->setData('default_billing', $data['account']['default_billing']);
            }
            if (isset($data['account']['default_shipping'])) {
                $customer->setData('default_shipping', $data['account']['default_shipping']);
            }
            if (isset($data['account']['confirmation'])) {
                $customer->setData('confirmation', $data['account']['confirmation']);
            }

            // Mark not modified customer addresses for delete
            foreach ($customer->getAddressesCollection() as $customerAddress) {
                if ($customerAddress->getId() && !in_array($customerAddress->getId(), $modifiedAddresses)) {
                    $customerAddress->setData('_deleted', true);
                }
            }

            if (Mage::getSingleton('admin/session')->isAllowed('newsletter/subscriber')
                && !$customer->getConfirmation()
            ) {
                $customer->setIsSubscribed(isset($data['subscription']));
            }

            if (isset($data['account']['sendemail_store_id'])) {
                $customer->setSendemailStoreId($data['account']['sendemail_store_id']);
            }

            $isNewCustomer = $customer->isObjectNew();
            try {
                $sendPassToEmail = false;
                // Force new customer confirmation
                if ($isNewCustomer) {
                    $customer->setPassword($data['account']['password']);
                    $customer->setPasswordCreatedAt(time());
                    $customer->setForceConfirmed(true);
                    if ($customer->getPassword() === 'auto') {
                        $sendPassToEmail = true;
                        $customer->setPassword($customer->generatePassword());
                    }
                }

                Mage::dispatchEvent('adminhtml_customer_prepare_save', [
                    'customer'  => $customer,
                    'request'   => $this->getRequest(),
                ]);

                $customer->save();

                // Send welcome email
                if ($customer->getWebsiteId() && (isset($data['account']['sendemail']) && !$sendPassToEmail)) {
                    $storeId = $customer->getSendemailStoreId();
                    if ($isNewCustomer) {
                        $customer->sendNewAccountEmail('registered', '', $storeId);
                    } elseif ((!$customer->getConfirmation())) {
                        // Confirm not confirmed customer
                        $customer->sendNewAccountEmail('confirmed', '', $storeId);
                    }
                }

                if (!empty($data['account']['new_password'])) {
                    $newPassword = trim($data['account']['new_password']);
                    if ($newPassword === 'auto') {
                        $newPassword = $customer->generatePassword();
                        $customer->sendPasswordLinkEmail($isNewCustomer);
                    } else {
                        $minPasswordLength = Mage::getModel('customer/customer')->getMinPasswordLength();
                        if (Mage::helper('core/string')->strlen($newPassword) < $minPasswordLength) {
                            Mage::throwException(Mage::helper('customer')
                                ->__('The minimum password length is %s', $minPasswordLength));
                        }
                        $customer->changePassword($newPassword);
                        $customer->sendPasswordReminderEmail();
                    }
                } elseif ($sendPassToEmail) {
                    $customer->sendPasswordLinkEmail($isNewCustomer);
                }

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('The customer has been saved.'),
                );
                Mage::dispatchEvent('adminhtml_customer_save_after', [
                    'customer'  => $customer,
                    'request'   => $this->getRequest(),
                ]);

                if ($redirectBack) {
                    $this->_redirect('*/*/edit', [
                        'id' => $customer->getId(),
                        '_current' => true,
                    ]);
                    return;
                }
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
                $this->_getSession()->setCustomerData($data);
                $this->getResponse()->setRedirect($this->getUrl('*/customer/edit', ['id' => $customer->getId()]));
                return;
            } catch (Exception $e) {
                $this->_getSession()->addException(
                    $e,
                    Mage::helper('adminhtml')->__('An error occurred while saving the customer.'),
                );
                $this->_getSession()->setCustomerData($data);
                $this->getResponse()->setRedirect($this->getUrl('*/customer/edit', ['id' => $customer->getId()]));
                return;
            }
        }
        $this->getResponse()->setRedirect($this->getUrl('*/customer'));
    }

    /**
     * Export customer grid to CSV format
     */
    public function exportCsvAction()
    {
        $fileName   = 'customers.csv';
        $content    = $this->getLayout()->createBlock('adminhtml/customer_grid')
            ->getCsvFile();

        $this->_prepareDownloadResponse($fileName, $content);
    }

    /**
     * Export customer grid to XML format
     */
    public function exportXmlAction()
    {
        $fileName   = 'customers.xml';
        $content    = $this->getLayout()->createBlock('adminhtml/customer_grid')
            ->getExcelFile();

        $this->_prepareDownloadResponse($fileName, $content);
    }

    /**
     * Prepare file download response
     *
     * @todo remove in 1.3
     * @deprecated please use $this->_prepareDownloadResponse()
     * @see Mage_Adminhtml_Controller_Action::_prepareDownloadResponse()
     * @param string $fileName
     * @param string $content
     * @param string $contentType
     */
    protected function _sendUploadResponse($fileName, $content, $contentType = 'application/octet-stream')
    {
        $this->_prepareDownloadResponse($fileName, $content, $contentType);
    }

    /**
     * Customer orders grid
     */
    public function ordersAction()
    {
        $this->_initCustomer();
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Customer last orders grid for ajax
     */
    public function lastOrdersAction()
    {
        $this->_initCustomer();
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Customer newsletter grid
     */
    public function newsletterAction()
    {
        $this->_initCustomer();
        $subscriber = Mage::getModel('newsletter/subscriber')
            ->loadByCustomer(Mage::registry('current_customer'));

        Mage::register('subscriber', $subscriber);
        $this->loadLayout();
        $this->renderLayout();
    }

    public function wishlistAction()
    {
        $this->_initCustomer();
        $customer = Mage::registry('current_customer');
        if ($customer->getId()) {
            if ($itemId = (int) $this->getRequest()->getParam('delete')) {
                try {
                    Mage::getModel('wishlist/item')->load($itemId)
                        ->delete();
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }
        }

        $this->getLayout()->getUpdate()
            ->addHandle(strtolower($this->getFullActionName()));
        $this->loadLayoutUpdates()->generateLayoutXml()->generateLayoutBlocks();

        $this->renderLayout();
    }

    /**
     * Customer last view wishlist for ajax
     */
    public function viewWishlistAction()
    {
        $this->_initCustomer();
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * [Handle and then] get a cart grid contents
     */
    public function cartAction()
    {
        $this->_initCustomer();
        $websiteId = $this->getRequest()->getParam('website_id');

        // delete an item from cart
        $deleteItemId = $this->getRequest()->getPost('delete');
        if ($deleteItemId) {
            $quote = Mage::getModel('sales/quote')
                ->setWebsite(Mage::app()->getWebsite($websiteId))
                ->loadByCustomer(Mage::registry('current_customer'));
            $item = $quote->getItemById($deleteItemId);
            if ($item && $item->getId()) {
                $quote->removeItem($deleteItemId);
                $quote->collectTotals()->save();
            }
        }

        $this->loadLayout();
        $this->getLayout()->getBlock('admin.customer.view.edit.cart')->setWebsiteId($websiteId);
        $this->renderLayout();
    }

    /**
     * Get shopping cart to view only
     */
    public function viewCartAction()
    {
        $this->_initCustomer();
        $this->loadLayout()
            ->getLayout()
            ->getBlock('admin.customer.view.cart')
            ->setWebsiteId($this->getRequest()->getParam('website_id'));
        $this->renderLayout();
    }

    /**
     * Get shopping carts from all websites for specified client
     */
    public function cartsAction()
    {
        $this->_initCustomer();
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Get customer's product reviews list
     */
    public function productReviewsAction()
    {
        $this->_initCustomer();
        $this->loadLayout()
            ->getLayout()
            ->getBlock('admin.customer.reviews')
            ->setCustomerId(Mage::registry('current_customer')->getId())
            ->setUseAjax(true);
        $this->renderLayout();
    }

    /**
     * Get customer's tags list
     */
    public function productTagsAction()
    {
        $this->_initCustomer();
        $this->loadLayout()
            ->getLayout()
            ->getBlock('admin.customer.tags')
            ->setCustomerId(Mage::registry('current_customer')->getId())
            ->setUseAjax(true);
        $this->renderLayout();
    }

    public function tagGridAction()
    {
        $this->_initCustomer();
        $this->loadLayout();
        $this->getLayout()->getBlock('admin.customer.tags')->setCustomerId(
            Mage::registry('current_customer'),
        );
        $this->renderLayout();
    }

    public function validateAction()
    {
        $response       = new Varien_Object();
        $response->setError(0);
        $websiteId      = Mage::app()->getStore()->getWebsiteId();
        $accountData    = $this->getRequest()->getPost('account');

        $customer = Mage::getModel('customer/customer');
        $customerId = $this->getRequest()->getParam('id');
        if ($customerId) {
            $customer->load($customerId);
            $websiteId = $customer->getWebsiteId();
        } elseif (isset($accountData['website_id'])) {
            $websiteId = $accountData['website_id'];
        }

        /** @var Mage_Customer_Model_Form $customerForm */
        $customerForm = Mage::getModel('customer/form');
        $customerForm->setEntity($customer)
            ->setFormCode('adminhtml_customer')
            ->setIsAjaxRequest(true)
            ->ignoreInvisible(false)
        ;

        $data   = $customerForm->extractData($this->getRequest(), 'account');
        $errors = $customerForm->validateData($data);
        if ($errors !== true) {
            foreach ($errors as $error) {
                $this->_getSession()->addError($error);
            }
            $response->setError(1);
        }

        # additional validate email
        if (!$response->getError()) {
            # Trying to load customer with the same email and return error message
            # if customer with the same email address exists
            $checkCustomer = Mage::getModel('customer/customer')
                ->setWebsiteId($websiteId);
            $checkCustomer->loadByEmail($accountData['email']);
            if ($checkCustomer->getId() && ($checkCustomer->getId() != $customer->getId())) {
                $response->setError(1);
                $this->_getSession()->addError(
                    Mage::helper('adminhtml')->__('Customer with the same email already exists.'),
                );
            }
        }

        $addressesData = $this->getRequest()->getParam('address');
        if (is_array($addressesData)) {
            /** @var Mage_Customer_Model_Form $addressForm */
            $addressForm = Mage::getModel('customer/form');
            $addressForm->setFormCode('adminhtml_customer_address')->ignoreInvisible(false);
            foreach (array_keys($addressesData) as $index) {
                if ($index === '_template_') {
                    continue;
                }
                $address = $customer->getAddressItemById($index);
                if (!$address) {
                    $address   = Mage::getModel('customer/address');
                }

                $requestScope = sprintf('address/%s', $index);
                $formData = $addressForm->setEntity($address)
                    ->extractData($this->getRequest(), $requestScope);

                $errors = $addressForm->validateData($formData);
                if ($errors !== true) {
                    foreach ($errors as $error) {
                        $this->_getSession()->addError($error);
                    }
                    $response->setError(1);
                }
            }
        }

        if ($response->getError()) {
            $this->_initLayoutMessages('adminhtml/session');
            $response->setMessage($this->getLayout()->getMessagesBlock()->getGroupedHtml());
        }

        $this->getResponse()->setBody($response->toJson());
    }

    public function massSubscribeAction()
    {
        $customersIds = $this->getRequest()->getParam('customer');
        if (!is_array($customersIds)) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Please select customer(s).'));
        } else {
            try {
                foreach ($customersIds as $customerId) {
                    // phpcs:ignore Ecg.Performance.Loop.ModelLSD
                    $customer = Mage::getModel('customer/customer')->load($customerId);
                    $customer->setIsSubscribed(true);
                    // phpcs:ignore Ecg.Performance.Loop.ModelLSD
                    $customer->save();
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('Total of %d record(s) were updated.', count($customersIds)),
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }

    public function massUnsubscribeAction()
    {
        $customersIds = $this->getRequest()->getParam('customer');
        if (!is_array($customersIds)) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Please select customer(s).'));
        } else {
            try {
                foreach ($customersIds as $customerId) {
                    // phpcs:ignore Ecg.Performance.Loop.ModelLSD
                    $customer = Mage::getModel('customer/customer')->load($customerId);
                    $customer->setIsSubscribed(false);
                    // phpcs:ignore Ecg.Performance.Loop.ModelLSD
                    $customer->save();
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('Total of %d record(s) were updated.', count($customersIds)),
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/index');
    }

    public function massDeleteAction()
    {
        $customersIds = $this->getRequest()->getParam('customer');
        if (!is_array($customersIds)) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Please select customer(s).'));
        } else {
            try {
                $customer = Mage::getModel('customer/customer');
                foreach ($customersIds as $customerId) {
                    $customer->reset()
                        // phpcs:ignore Ecg.Performance.Loop.ModelLSD
                        ->load($customerId)
                        // phpcs:ignore Ecg.Performance.Loop.ModelLSD
                        ->delete();
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('Total of %d record(s) were deleted.', count($customersIds)),
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/index');
    }

    public function massAssignGroupAction()
    {
        $customersIds = $this->getRequest()->getParam('customer');
        if (!is_array($customersIds)) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Please select customer(s).'));
        } else {
            try {
                foreach ($customersIds as $customerId) {
                    // phpcs:ignore Ecg.Performance.Loop.ModelLSD
                    $customer = Mage::getModel('customer/customer')->load($customerId);
                    $customer->setGroupId($this->getRequest()->getParam('group'));
                    // phpcs:ignore Ecg.Performance.Loop.ModelLSD
                    $customer->save();
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('Total of %d record(s) were updated.', count($customersIds)),
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/index');
    }

    /**
     * @SuppressWarnings("PHPMD.ExitExpression")
     */
    public function viewfileAction()
    {
        $plain  = false;
        if ($this->getRequest()->getParam('file')) {
            // download file
            $file   = Mage::helper('core')->urlDecode($this->getRequest()->getParam('file'));
        } elseif ($this->getRequest()->getParam('image')) {
            // show plain image
            $file   = Mage::helper('core')->urlDecode($this->getRequest()->getParam('image'));
            $plain  = true;
        } else {
            $this->norouteAction();
            return;
        }

        $path = Mage::getBaseDir('media') . DS . 'customer';

        $ioFile = new Varien_Io_File();
        $ioFile->open(['path' => $path]);
        $fileName   = $ioFile->getCleanPath($path . $file);
        $path       = $ioFile->getCleanPath($path);

        if ((!$ioFile->fileExists($fileName) || !str_starts_with($fileName, $path))
            && !Mage::helper('core/file_storage')->processStorageFile(str_replace('/', DS, $fileName))
        ) {
            $this->norouteAction();
            return;
        }

        if ($plain) {
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $contentType = match (strtolower($extension)) {
                'gif' => 'image/gif',
                'jpg' => 'image/jpeg',
                'webp' => 'image/webp',
                'png' => 'image/png',
                default => 'application/octet-stream',
            };

            $ioFile->streamOpen($fileName, 'r');
            $contentLength = $ioFile->streamStat('size');
            $contentModify = $ioFile->streamStat('mtime');

            $this->getResponse()
                ->setHttpResponseCode(200)
                ->setHeader('Pragma', 'public', true)
                ->setHeader('Content-type', $contentType, true)
                ->setHeader('Content-Length', $contentLength)
                ->setHeader('Last-Modified', date('r', $contentModify))
                ->clearBody();
            $this->getResponse()->sendHeaders();

            while (($buffer = $ioFile->streamRead()) !== false) {
                echo $buffer;
            }
        } else {
            $name = pathinfo($fileName, PATHINFO_BASENAME);
            $this->_prepareDownloadResponse($name, [
                'type'  => 'filename',
                'value' => $fileName,
            ]);
        }

        exit();
    }

    /**
     * Filtering posted data. Converting localized data if needed
     *
     * @param array $data
     * @return array
     */
    protected function _filterPostData($data)
    {
        $data['account'] = $this->_filterDates($data['account'], ['dob']);
        return $data;
    }
}
