<?php

/**
 * @copyright  For copyright and license information, read the COPYING.txt file.
 * @link       /COPYING.txt
 * @license    Open Software License (OSL 3.0)
 * @package    Mage_Reports
 */

/**
 * Reports Mysql resource helper model
 *
 * @package    Mage_Reports
 */
class Mage_Reports_Model_Resource_Helper_Mysql4 extends Mage_Core_Model_Resource_Helper_Mysql4 implements Mage_Reports_Model_Resource_Helper_Interface
{
    /**
     * Merge Index data
     *
     * @param string $mainTable
     * @param array $data
     * @param mixed $matchFields
     * @return int
     */
    public function mergeVisitorProductIndex($mainTable, $data, $matchFields)
    {
        return $this->_getWriteAdapter()->insertOnDuplicate($mainTable, $data, array_keys($data));
    }

    /**
     * Update rating position
     *
     * @param string $type day|month|year
     * @param string $column
     * @param string $mainTable
     * @param string $aggregationTable
     * @return Mage_Core_Model_Resource_Helper_Mysql4
     */
    public function updateReportRatingPos($type, $column, $mainTable, $aggregationTable)
    {
        $adapter         = $this->_getWriteAdapter();
        $periodSubSelect = $adapter->select();
        $ratingSubSelect = $adapter->select();
        $ratingSelect    = $adapter->select();

        $periodCol = match ($type) {
            'year' => $adapter->getDateFormatSql('t.period', '%Y-01-01'),
            'month' => $adapter->getDateFormatSql('t.period', '%Y-%m-01'),
            default => 't.period',
        };

        $columns = [
            'period'          => 't.period',
            'store_id'        => 't.store_id',
            'product_id'      => 't.product_id',
            'product_name'    => 't.product_name',
            'product_price'   => 't.product_price',
        ];

        if ($type == 'day') {
            $columns['id'] = 't.id';  // to speed-up insert on duplicate key update
        }

        if ($column == 'qty_ordered') {
            $columns['product_type_id'] = 't.product_type_id';
        }

        $cols = array_keys($columns);
        $cols['total_qty'] = new Zend_Db_Expr('SUM(t.' . $column . ')');

        $periodSubSelect->from(['t' => $mainTable], $cols)
            ->group(['t.store_id', $periodCol, 't.product_id']);

        if ($column == 'qty_ordered') {
            $productTypesInExpr = $adapter->quoteInto(
                't.product_type_id IN (?)',
                Mage_Catalog_Model_Product_Type::getCompositeTypes(),
            );
            $periodSubSelect->order(
                [
                    't.store_id',
                    $periodCol,
                    $adapter->getCheckSql($productTypesInExpr, '1', '0'),
                    'total_qty DESC',
                ],
            );
        } else {
            $periodSubSelect->order(['t.store_id', $periodCol, 'total_qty DESC']);
        }

        $cols = $columns;
        $cols[$column] = 't.total_qty';
        $cols['rating_pos']  = new Zend_Db_Expr(
            "(@pos := IF(t.`store_id` <> @prevStoreId OR {$periodCol} <> @prevPeriod, 1, @pos+1))",
        );
        $cols['prevStoreId'] = new Zend_Db_Expr('(@prevStoreId := t.`store_id`)');
        $cols['prevPeriod']  = new Zend_Db_Expr("(@prevPeriod := {$periodCol})");
        $ratingSubSelect->from($periodSubSelect, $cols);

        $cols               = $columns;
        $cols['period']     = $periodCol;
        $cols[$column]      = 't.' . $column;
        $cols['rating_pos'] = 't.rating_pos';
        $ratingSelect->from($ratingSubSelect, $cols);

        $sql = $ratingSelect->insertFromSelect($aggregationTable, array_keys($cols));
        $adapter->query("SET @pos = 0, @prevStoreId = -1, @prevPeriod = '0000-00-00'");

        $adapter->query($sql);

        return $this;
    }
}
