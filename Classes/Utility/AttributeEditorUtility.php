<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2005 - 2011 Thomas Hempel <thomas@work.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * A metaclass for creating inputfield fields in the backend.
 */
class Tx_Commerce_Utility_AttributeEditorUtility {
	/**
	 * @var Tx_Commerce_Utility_BackendUtility
	 */
	protected $belib;

	/**
	 * Constructor
	 *
	 * @return self
	 */
	public function __construct() {
		$this->belib = t3lib_div::makeInstance('Tx_Commerce_Utility_BackendUtility');
	}

	/**
	 * This method creates a dynaflex field configuration from the submitted database entry.
	 * The variable "configData" contains the complete dynaflex configuration of the field and
	 * the data that where maybe fetched from the database.
	 *
	 * We have to fill the fields
	 *
	 * $config['name']
	 * $config['label']
	 * $config['config']
	 *
	 * @param array $aData: The data array contains in element "row" the dataset of the table we're creating
	 * @param array $config: The config array is the fynaflex fieldconfiguration.
	 * @param boolean $fetchFromDB: If true the attribute data is fetched from DB
	 * @param boolean $onlyDisplay: If true the field is not an input field but is displayed
	 * @return array The modified dynaflex configuration
	 */
	public function getAttributeEditField($aData, &$config, $fetchFromDB = TRUE, $onlyDisplay = FALSE) {
			// first of all, fetch data from attribute table
		if ($fetchFromDB) {
			$aData = $this->belib->getAttributeData($aData['row']['uid_foreign'], 'uid,title,has_valuelist,multiple,unit,deleted');
		}

		if ($aData['deleted'] == 1) {
			return array();
		}

		/**
		 * Try to detect article UID since there is currently no way to get the data from the method
		 * and get language_uid from article
		 * @author ingo schmitt <is@marketing-factory.de>
		 */

		$sys_language_uid = 0;
		$getPostedit = t3lib_div::_GPmerged('edit');
		if (is_array($getPostedit['tx_commerce_articles'])) {
			$articleUid = array_keys($getPostedit['tx_commerce_articles']);
			if ($articleUid[0] > 0) {
				$lok_data = t3lib_BEfunc::getRecord('tx_commerce_articles', $articleUid[0], 'sys_language_uid');
				$sys_language_uid = $lok_data['sys_language_uid'];
			}
			if (empty($sys_language_uid)) {
				$sys_language_uid = 0;
			}
		} elseif (is_array($getPostedit['tx_commerce_products'])) {
			$articleUid = array_keys($getPostedit['tx_commerce_products']);
			if ($articleUid[0] > 0) {
				$lok_data = t3lib_BEfunc::getRecord('tx_commerce_products', $articleUid[0], 'sys_language_uid');
				$sys_language_uid = $lok_data['sys_language_uid'];
			}
			if (empty($sys_language_uid)) {
				$sys_language_uid = 0;
			}
		}

			// set label and name
		$config['label'] = $aData['title'];
		$config['name'] = 'attribute_' . $aData['uid'];

		/**
		 * Try to get language label
		 */
		if ($sys_language_uid > 0) {
			$lok_data = t3lib_BEfunc::getRecordRaw(
				'tx_commerce_attributes',
				'sys_language_uid=' . $sys_language_uid . ' AND l18n_parent=' . $aData['uid'],
				'*'
			);
		}

			// get the value
		if ($onlyDisplay) {
			$config['config']['type'] = 'user';
			$config['config']['userFunc'] = 'tx_commerce_attributeEditor->displayAttributeValue';
			$config['config']['aUid'] = $aData['uid'];
			return $config;
		}

		/**
		 * Get PID to select only the Attribute Values in the correct PID
		 * @since 3rd January 2006
		 * @author Ingo Schmitt <is@marketing-factory.de>
		 */
		Tx_Commerce_Utility_FolderUtility::init_folders();
		list($modPid) = Tx_Commerce_Domain_Repository_FolderRepository::initFolders('Commerce', 'commerce');
		Tx_Commerce_Domain_Repository_FolderRepository::initFolders('Products', 'commerce', $modPid);
		list($attrPid) = Tx_Commerce_Domain_Repository_FolderRepository::initFolders('Attributes', 'commerce', $modPid);

		if ($aData['has_valuelist'] == 1) {
			$config['config'] = array (
				'type' => 'select',
				'foreign_table' => 'tx_commerce_attribute_values',
				'foreign_table_where' => 'AND attributes_uid=' . (int) $aData['uid'] . ' and tx_commerce_attribute_values.pid=' .
					(int) $attrPid . ' ORDER BY value',
				'size' => 1,
				'minitems' => 0,
				'maxitems' => 1,
				'items' => array (
					array('', 0)
				),
			);

			if (intval($aData['multiple']) == 1) {
					// create a selectbox for multiple selection
				$config['config']['multiple'] = 1;
				$config['config']['size'] = 5;
				$config['config']['maxitems'] = 100;
				unset($config['config']['items']);
			}
		} else {
				// the field should be a simple input field
			if ($aData['unit'] != '') {
				$config['label'] .= ' (' . $aData['unit'] . ')';
			}
			$config['config'] = array('type' => 'input');
		}

			// Dont display in lokalised version Attributes with valuelist
		if (($aData['has_valuelist'] == 1) && ($sys_language_uid <> 0)) {
			$config['config']['type'] = '';
			return FALSE;
		}

		return $config;
	}

	/**
	 * Returns the editfield dynaflex config for all attributes of a product
	 *
	 * @param array $funcDataArray: ...
	 * @param array $baseConfig: ...
	 * @return array An array with fieldconfigs
	 */
	public function getAttributeEditFields($funcDataArray, $baseConfig) {
		$result = array();

		$sortedAttributes = array();
		foreach ($funcDataArray as $funcData) {
			if ($funcData['row']['uid_foreign'] == 0) {
				continue;
			}

			$aData = $this->belib->getAttributeData($funcData['row']['uid_foreign'], 'uid,title,has_valuelist,multiple,unit,deleted');

				// get correlationtype for this attribute and the product of this article
				// first get the product for this aticle
			$productUid = $this->belib->getProductOfArticle($funcData['row']['uid_local'], FALSE);

			$uidCT = $this->belib->getCtForAttributeOfProduct($funcData['row']['uid_foreign'], $productUid);
			$sortedAttributes[$uidCT][] = $aData;
		}
		ksort($sortedAttributes);
		reset($sortedAttributes);

		foreach ($sortedAttributes as $ctUid => $attributes) {
				// add a userfunction as header
			foreach ($attributes as $attribute) {
				$onlyDisplay = (($ctUid == 1 && ($attribute['has_valuelist'])) || $ctUid == 4);
				$fieldConfig = $this->getAttributeEditField($attribute, $baseConfig, FALSE, $onlyDisplay);

				if (is_array($fieldConfig) && (count($fieldConfig) > 0)) {
					$result[] = $fieldConfig;
				}
			}
		}

		return $result;
	}

	/**
	 * Simply returns the value of an attribute of an article.
	 *
	 * @param array $PA:
	 * @param t3lib_TCEforms $fObj: The form object
	 * @return string
	 */
	public function displayAttributeValue($PA, $fObj) {
		/** @var t3lib_db $database */
		$database = $GLOBALS['TYPO3_DB'];

			// attribute value uid
		$aUid = $PA['fieldConf']['config']['aUid'];

		$relRes = $database->exec_SELECTquery(
			'uid_valuelist,default_value,value_char',
			'tx_commerce_articles_article_attributes_mm',
			'uid_local=' . (int) $PA['row']['uid'] . ' AND uid_foreign=' . (int) $aUid
		);

		$attributeData = $this->belib->getAttributeData($aUid, 'has_valuelist,multiple,unit');
		$relationData = NULL;
		if ($attributeData['multiple'] == 1) {
			while ($relData = $database->sql_fetch_assoc($relRes)) {
				$relationData[] = $relData;
			}
		} else {
			$relationData = $database->sql_fetch_assoc($relRes);
		}

		return htmlspecialchars(strip_tags($this->belib->getAttributeValue(
			$PA['row']['uid'],
			$aUid,
			'tx_commerce_articles_article_attributes_mm',
			$relationData,
			$attributeData
		)));
	}
}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/commerce/class.tx_commerce_attributeeditor.php']) {
	/** @noinspection PhpIncludeInspection */
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/commerce/class.tx_commerce_attributeeditor.php']);
}

?>