<?php

/**
 * Description of UniadsExtension
 *
 * @author Elvinas Liutkevičius <elvinas@unisolutions.eu>
 * @author Hans de Ruiter <hans@hdrlab.org.nz>
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD http://silverstripe.org/BSD-license
 */
class UniadsExtension extends DataExtension {

	private static $many_many = array(
		'Ads' => 'UniadsObject',
	);

	private static $filter_double_ads = true;

	/**
	 * @var array cache for ads already shown on this page
	 */
	protected static $shown_ad_ids = array();

	public function updateCMSFields(FieldList $fields) {
		parent::updateCMSFields($fields);

		$fields->findOrMakeTab('Root.Advertisements', _t('UniadsObject.PLURALNAME', 'Advertisements'));

		$conf = GridFieldConfig_RelationEditor::create();
		$conf->getComponentByType('GridFieldAddExistingAutocompleter')->setSearchFields(array('Title'));
		$grid = new GridField("Advertisements", _t('UniadsObject.PLURALNAME', 'Advertisements'), $this->owner->Ads(), $conf);
		$fields->addFieldToTab("Root.Advertisements", $grid);
	}

	/** Displays a randomly chosen advertisement of the specified dimensions.
	 *
	 * @param zone of the advertisement
	 */
	public function DisplayAd($zone) {
		$output = '';
		if ($zone) {
			if (!is_object($zone)) {
				$zone = UniadsZone::getActiveZoneByTitle($zone);
			}
			if ($zone) {
				$adList = $this->getAdListForDisplaying($zone);
				foreach ($adList as $ad) {
					$output .= $ad->forTemplate();
					self::$shown_ad_ids[] = $ad->ID;
				}
			}
		}
		return $output;
	}

	/**
	 * Gets the ad for the current zone and all subzones
	 * @param UniadsZone $zone
	 * @retunr ArrayList with all ads
	 */
	public function getAdListForDisplaying(UniadsZone $zone){
		$adList = ArrayList::create();

		$ad = $this->getRandomAdByZone($zone);

		if($ad) {
			$ad = $ad->increaseImpressions();
		}

		if (!$ad) {
			// Show an empty advert
			$ad = new UniadsObject();
		}

		$adList->add($ad);

		foreach ($zone->ChildZones()->sort('Order') as $child) {
			if ($child->Active) {
				$adList->merge($this->getAdListForDisplaying($child));
			}
		}

		return $adList;

	}

    /**
	 * Scans over the owning page and all parent pages until it finds the one with the settings for displaying ads
	 * @return null|Page
	 */
	public function getPageWithSettingsForAds()
	{
		$settingsPage = $this->owner;
		if ($settingsPage->InheritSettings) {
			while ($settingsPage->ParentID) {
				if (!$settingsPage->InheritSettings) {
					break;
				}
				$settingsPage = $settingsPage->Parent();
			}
			if (!$settingsPage->ParentID && $settingsPage->InheritSettings) {
				$settingsPage = null;
				return $settingsPage;
			}
			return $settingsPage;
		}
		return $settingsPage;
	}

    /**
     * Scans over the owning page and all parent pages until it finds the one with ads assigned
     * @return null|Page
     */
    public function getPageWithAdsInZone(UniadsZone $zone)
    {
        $page = $this->owner;
        if ($page->Ads()->exists()) {
            return $page;
        }

        while ($page->ParentID) {
            $page = $page->Parent();
            if ($page->Ads()->exists()) {
                return $page;
            }
        }

        return null;
    }

	/**
	 * @param $zone
	 * @return DataList
	 */
	public function getBasicFilteredAdListByZone(UniadsZone $zone)
	{

        $UniadsObject = UniadsObject::get()->filter(
            array(
                'ZoneID' => $zone->ID,
                'Active' => 1,
                'AdInPages.ID' => $this->getPageIDsWithAncestors(),
            )
        );

		$UniadsObject = $UniadsObject->leftJoin('UniadsCampaign', 'c.ID = UniadsObject.CampaignID', 'c');

		//current ads and campaigns
		$campaignFilter = "(c.ID is null or (
						c.Active = '1'
						and (c.Starts <= '" . SS_Datetime::now()->getValue() . "' or c.Starts = '' or c.Starts is null)
						and (c.Expires >= '" . SS_Datetime::now()->getValue() . "' or c.Expires = '' or c.Expires is null)
					))
					and (UniadsObject.Starts <= '" . SS_Datetime::now()->getValue() . "' or UniadsObject.Starts = '' or UniadsObject.Starts is null)
					and (UniadsObject.Expires >= '" . SS_Datetime::now()->getValue() . "' or UniadsObject.Expires = '' or UniadsObject.Expires is null)
				";

		$UniadsObject = $UniadsObject->where($campaignFilter);
		$sql = $UniadsObject->sql();

		// Fall back on global ads if there is nothing in the hierarchy
		if (!$UniadsObject->exists()) {
			$UniadsObject = UniadsObject::get()->filter(
				array(
					'ZoneID' => $zone->ID,
					'Active' => 1,
				)
			);
			// Remove all ads that are assigned to a page exclusively
			$UniadsObject = $UniadsObject->subtract(UniadsObject::get()->filter('AdInPages.ID:GreaterThan', 0));

			// Filter out embargoed and expired ads
			$UniadsObject = $UniadsObject->leftJoin('UniadsCampaign', 'c.ID = UniadsObject.CampaignID', 'c');
			$UniadsObject = $UniadsObject->where($campaignFilter);
		}

		return $UniadsObject;
	}

	/**
	 * returns a DataList with all possible Ads in this zone.
	 * respects ImpressionLimit
	 *
	 * @param UniadsZone $zone
	 * @return DataList
	 */
	public function getAdsByZone(UniadsZone $zone){
		$adList = $this->getBasicFilteredAdListByZone($zone)
			->where('(UniadsObject.ImpressionLimit = 0 or UniadsObject.ImpressionLimit > UniadsObject.Impressions)');

		return $adList;
	}

	/**
	 * @param UniadsZone $zone
	 * @todo: filter out already displayed ads or campaigns
	 * @return UniadsObject
	 */
	public function getRandomAdByZone(UniadsZone $zone)
	{
		$weight = rand(0, $this->getMaxWeightByZone($zone));

		$randomString = DB::getConn()->random(); //e.g. rand() for mysql, random() for Sqlite3

		$ad =$this->getAdsByZone($zone)
			->filter(array('Weight:GreaterThanOrEqual' => $weight))
			->sort($randomString);

		if (Config::inst()->get('UniadsExtension', 'filter_double_ads')) {
			$ad = $ad->exclude(
				array('ID' => self::$shown_ad_ids)
			);
		}

		$this->owner->extend('UpdateRandomAdByZone', $ad);

		return $ad->First();
	}


	/**
	 * @param UniadsZone $zone
	 * @return string
	 */
	public function getMaxWeightByZone(UniadsZone $zone){
		$UniadsObject = $this->getBasicFilteredAdListByZone($zone);
		$weight = $UniadsObject->max('Weight');

		return $weight;
	}

    /**
     * Returns an array of page ids for the current page and all parents
     * @return array
     */
    private function getPageIDsWithAncestors()
    {
        $page = $this->owner;
        return array_merge(array($page->ID), $page->getAncestors()->column('ID'));
    }


}
