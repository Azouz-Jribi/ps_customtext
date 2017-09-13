<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class CustomText extends ObjectModel
{
	public $id_info;

	public $id_shop;

	public $text;

	/**
	 * @see ObjectModel::$definition
	 */
	public static $definition = array(
		'table' => 'info',
		'primary' => 'id_info',
		'multilang' => true,
		'multilang_shop' => true,
		'fields' => array(
			'id_info' =>         	array('type' => self::TYPE_NOTHING, 'validate' => 'isUnsignedId'),
			'id_shop' =>			array('type' => self::TYPE_NOTHING, 'validate' => 'isUnsignedId'),
			// Lang fields
			'text' =>				array('type' => self::TYPE_HTML, 'lang' => true, 'validate' => 'isCleanHtml', 'required' => true),
		)
	);

	/**
	 * Adding new custom text.
	 * @param bool $autoDate
	 * @param bool $nullValues
	 * @return bool
	 * @throws PrestaShopDatabaseException
	 * @throws PrestaShopException
	 */
	public function add($autoDate = true, $nullValues = false)
	{
		$this->id = $this->id_info;

		if (!$result = Db::getInstance()->insert($this->def['table'], $this->getFields(), $nullValues)) {
			return false;
		}

		// Database insertion for multilingual fields related to the object
		if (!empty($this->def['multilang'])) {
			$fields = $this->getFieldsLang();
			if ($fields && is_array($fields)) {
				$asso = Shop::getAssoTable($this->def['table'].'_lang');
				foreach ($fields as $field) {
					foreach (array_keys($field) as $key) {
						if (!Validate::isTableOrIdentifier($key)) {
							throw new PrestaShopException('key '.$key.' is not table or identifier');
						}
					}
					$field[$this->def['primary']] = (int)$this->id_info;

					if ($asso !== false && $asso['type'] == 'fk_shop') {
						$field['id_shop'] = (int)$this->id_shop;
						$result &= Db::getInstance()->insert($this->def['table'] . '_lang', $field);
					} else {
						$result &= Db::getInstance()->insert($this->def['table'].'_lang', $field);
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Save the custom text changes.
	 * @param bool $null_values
	 * @param bool $auto_date
	 * @return bool
	 */
	public function save($null_values = false, $auto_date = true)
	{
		$this->id = $this->id_info;

		if (is_array($this->id_shop)) {
			$this->id_shop = array_unique($this->id_shop);
			$this->id_shop = reset($this->id_shop);
		}

		return parent::save($null_values, $auto_date);
	}

	/**
	 * Update the custom text.
	 * @param bool $null_values
	 * @return bool
	 * @throws PrestaShopDatabaseException
	 * @throws PrestaShopException
	 */
	public function update($null_values = false)
	{
		$this->clearCache();

		// Database update
		$where = '`' . pSQL($this->def['primary']) . '` = ' . (int)$this->id . ' AND `id_shop` = ' . (int)$this->id_shop;
		if (!$result = Db::getInstance()->update($this->def['table'], $this->getFields(), $where, 0, $null_values)) {
			return false;
		}

		// Database update for multilingual fields related to the object
		if (isset($this->def['multilang']) && $this->def['multilang']) {
			$fields = $this->getFieldsLang();
			if (is_array($fields)) {
				foreach ($fields as $field) {
					foreach (array_keys($field) as $key) {
						if (!Validate::isTableOrIdentifier($key)) {
							throw new PrestaShopException('key '.$key.' is not a valid table or identifier');
						}
					}

					// If this table is linked to multishop system, update / insert for all shops from context
					if ($this->isLangMultishop()) {
						$field['id_shop'] = (int)$this->id_shop;
						$where = pSQL($this->def['primary']) . ' = ' . (int)$this->id
							. ' AND id_lang = ' . (int)$field['id_lang']
							. ' AND id_shop = ' . (int)$this->id_shop;

						if (Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . pSQL(_DB_PREFIX_ . $this->def['table']) . '_lang WHERE ' . $where)) {
							$result &= Db::getInstance()->update($this->def['table'] . '_lang', $field, $where);
						} else {
							$result &= Db::getInstance()->insert($this->def['table'] . '_lang', $field);
						}
					}
				}
			}
		}

		return $result;
	}
}
