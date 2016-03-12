<?php
namespace CommerceTeam\Commerce\Domain\Repository;

/*
 * This file is part of the TYPO3 Commerce project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

/**
 * Database class for tx_commerce_attributes. All database calls should
 * be made by this class. In most cases you should use the methodes
 * provided by tx_commerce_attribute to get informations for articles.
 *
 * Class \CommerceTeam\Commerce\Domain\Repository\AttributeRepository
 */
class AttributeRepository extends Repository
{
    /**
     * Database table.
     *
     * @var string
     */
    public $databaseTable = 'tx_commerce_attributes';

    /**
     * Database value table.
     *
     * @var string Child database table
     */
    protected $childDatabaseTable = 'tx_commerce_attribute_values';

    /**
     * @param int $productUid
     *
     * @return array
     */
    public function findByProductUid($productUid)
    {
        $attributes = (array)$this->getDatabaseConnection()->exec_SELECTgetRows(
            'at.*',
            $this->databaseTable . ' AS at
            INNER JOIN tx_commerce_products_attributes_mm AS mm ON at.uid = mm.uid_foreign',
            'mm.uid_local = ' . $productUid . ' AND mm.uid_correlationtype = 4'
            . $this->enableFields($this->databaseTable, -1, 'at')
        );

        return $attributes;
    }

    /**
     * @param int $articleUid
     *
     * @return array
     */
    public function findByArticleUid($articleUid)
    {
        // @todo fix this query to realy get attributes of article
        $attributes = (array)$this->getDatabaseConnection()->exec_SELECTgetRows(
            'at.*, pmm.uid_correlationtype',
            $this->databaseTable . ' AS at ON mm.uid_foreign = at.uid
            INNER JOIN tx_commerce_articles_attributes_mm AS amm ON at.uid = amm.uid_foreign
            INNER JOIN tx_commerce_articles AS a ON amm.uid_local = a.uid
            INNER JOIN tx_commerce_products AS p ON a.uid_product = p.uid
            INNER JOIN tx_commerce_products_attributes_mm AS pmm 
                ON (p.uid = pmm.uid_local AND at.uid = pmm.uid_foreign)',
            'a.uid = ' . (int)$articleUid
            . $this->enableFields($this->databaseTable, -1, 'at')
            . $this->enableFields('tx_commerce_articles', -1, 'a')
            . $this->enableFields('tx_commerce_products', -1, 'p')
        );

        return $attributes;
    }

    /**
     * Gets a list of attribute_value_uids.
     *
     * @param int $uid Uid
     *
     * @return array
     */
    public function getAttributeValueUids($uid)
    {
        $rows = $this->getDatabaseConnection()->exec_SELECTgetRows(
            'uid',
            $this->childDatabaseTable,
            'attributes_uid = ' . (int) $uid . $this->enableFields($this->childDatabaseTable),
            '',
            'sorting'
        );

        $attributeValueList = [];
        if (!empty($rows)) {
            foreach ($rows as $data) {
                $attributeValueList[] = $data['uid'];
            }
        }

        return $attributeValueList;
    }

    /**
     * Get child attribute uids.
     *
     * @param int $uid Uid
     *
     * @return array
     */
    public function getChildAttributeUids($uid)
    {
        $childAttributeList = [];
        if ((int) $uid) {
            $rows = $this->getDatabaseConnection()->exec_SELECTgetRows(
                'uid',
                $this->databaseTable,
                'parent = ' . (int) $uid . $this->enableFields($this->databaseTable),
                '',
                'sorting'
            );

            if (!empty($rows)) {
                foreach ($rows as $data) {
                    $childAttributeList[] = $data['uid'];
                }
            }
        }

        return $childAttributeList;
    }
}
