<?php
/**
 * OpenMage
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available at https://opensource.org/license/osl-3-0-php
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://www.magento.com)
 * @copyright  Copyright (c) 2022 The OpenMage Contributors (https://www.openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml block for showing product options fieldsets
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class Mage_Adminhtml_Block_Catalog_Product_Composite_Error extends Mage_Core_Block_Template
{
    /**
     * Returns error message to show what kind of error happened during retrieving of product
     * configuration controls
     *
     * @return string
     */
    public function _toHtml()
    {
        $message = Mage::registry('composite_configure_result_error_message');
        return Mage::helper('core')->jsonEncode(['error' => true, 'message' => $message]);
    }
}
