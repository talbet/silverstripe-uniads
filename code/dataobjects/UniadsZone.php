<?php

/**
 * Description of UniadsZone
 *
 * @author Elvinas Liutkevičius <elvinas@unisolutions.eu>
 * @license BSD http://silverstripe.org/BSD-license
 */

class UniadsZone extends DataObject {

	private static $db = array(
		'Title' => 'Varchar',
		'ZoneWidth' => 'Varchar(6)',
		'ZoneHeight' => 'Varchar(6)',
		'Order' => 'Int',
		'Active' => 'Boolean',
	);

	private static $summary_fields = array(
		'Title',
		'ParentZone.Title',
		'Order',
		'Active',
	);

	private static $has_one = array(
		'ParentZone' => 'UniadsZone',
	);

	private static $has_many = array(
		'Ads' => 'UniadsObject',
		'ChildZones' => 'UniadsZone',
	);

	private static $indexes = array(
		'Title' => true,
	);

	private static $defaults = array(
		'Active' => 1,
	);

    private static $singular_name = "Zone";
    private static $plural_name = "Zones";

	private static $default_records = array(
		array('Title' => 'Top', 'ZoneWidth' => '500', 'ZoneHeight' => '90'),
		array('Title' => 'Right', 'ZoneWidth' => '160', 'ZoneHeight' => '600'),
	);

	private static $default_sort = 'ParentZoneID asc, Order asc, ID asc';

	public function getWidth(){
		return $this->ZoneWidth . (ctype_digit($this->ZoneWidth) ? 'px' : '');
	}

	public function getHeight(){
		return $this->ZoneHeight . (ctype_digit($this->ZoneHeight) ? 'px' : '');
	}

	public function fieldLabels($includerelations = true) {
		$labels = parent::fieldLabels($includerelations);

		$labels['ParentZone.Title'] = _t('UniadsZone.has_one_ParentZone', 'Parent Zone');

		return $labels;
	}

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		if (!$this->ParentZoneID) {
			$fields->removeByName('Order');
		}

		if ($this->ChildZones()->Count() > 0) {
			$fields->removeByName('ParentZoneID');
		}

		if (($field = $fields->dataFieldByName('ParentZoneID'))) {
			$field->setSource(
				DataList::create('UniadsZone')
				->where("ID != " . $this->ID . " and (ParentZoneID is null or ParentZoneID = 0)")
				->map()
			);
		}

		return $fields;
	}


	/**
	 * @param string $title
	 * @return UniadsZone|null
	 */
	public static function getActiveZoneByTitle($title)
	{
		$zone = UniadsZone::get()
			->filter(
				array(
					'Title' => $title,
					'Active' => 1
				)
			)
			->first();
		return $zone;
	}

}
