<?php

/**
 * Class UniadsDisplayAdsTest
 *
 * Checks if $DisplayAd($zone) is working right
 */
class UniadsDisplayAdsTest extends SapphireTest {

	protected static $fixture_file = 'Uniads.yml';

	public function testActiveZone(){
		$page = $this->objFromFixture('Page', 'using-zone');

		$active = UniadsZone::getActiveZoneByTitle('ActiveZone');
		$inactive = UniadsZone::getActiveZoneByTitle('InactiveZone');

		$this->assertInstanceOf('UniadsZone', $active, 'Active zone should be an UniadsZone object');
		$this->assertNull($inactive, 'Inactive zone must not be returned');
	}

	public function testGetSettingsPage(){
		$page = $this->objFromFixture('Page', 'using-zone');
		$settingPage = $page->getPageWithSettingsForAds();
		$this->assertEquals(1, $settingPage->ID);

		$childPage = $this->objFromFixture('Page', 'subpage');

		$childSettingPage = $childPage->getPageWithSettingsForAds(); //should return Page#1
		$this->assertEquals(1, $childSettingPage->ID);
	}

	/**
	 * Checks if getAdsByZone retrieves the right number of ads and if the returning ads are in the right zone
	 */
	public function testIfZoneIsRight()
	{
		//check correct number of Ads per Zone
		$page = $this->objFromFixture('Page', 'using-zone');
		$zone = $this->objFromFixture('UniadsZone', 'active-zone');
		$adList = $page->getAdsByZone($zone);
		$this->assertEquals(5, $adList->count());

		$pageWithExclusiveAds = $this->objFromFixture('Page', 'exclusive');
		$adList2 = $pageWithExclusiveAds->getAdsByZone($zone);
		$this->assertEquals(6, $adList2->count());

		//check if returned Ad is having the right Zone
		foreach($adList as $ad) {
			$this->assertEquals($zone->ID, $ad->ZoneID);
		}
		foreach($adList2 as $ad) {
			$this->assertEquals($zone->ID, $ad->ZoneID);
		}
	}

	public function testSubzones(){
		$page = $this->objFromFixture('Page', 'using-zone');
		$zone = $this->objFromFixture('UniadsZone', 'active-zone');
		$adList = $page->getAdListForDisplaying($zone);
		$this->assertEquals(2, $adList->count(), 'AdListForDisplay should return an ArrayList with two items');
	}

	public function testMaxWeight()
	{
		$page = $this->objFromFixture('Page', 'using-zone');
		$zone = $this->objFromFixture('UniadsZone', 'active-zone');
		$weight1 = $page->getMaxWeightByZone($zone);
		$this->assertEquals(4, $weight1);

		$pageWithExclusiveAds = $this->objFromFixture('Page', 'exclusive');
		$adCount = $pageWithExclusiveAds->Ads()->count();
		$this->assertEquals(1, $adCount, 'There should be one Ad in the many_many Ads on exclusive Page');

		$weight2 = $pageWithExclusiveAds->getMaxWeightByZone($zone);

		$this->assertEquals(6, $weight2);
	}


	public function testImpressionCounter(){
		Config::inst()->update('UniadsObject', 'record_impressions', true);
		$ad = $this->objFromFixture('UniadsObject', 'default');
		$impressionsBefore = $ad->Impressions;

		$ad = $ad->increaseImpressions();
		$this->assertEquals($impressionsBefore + 1, $ad->Impressions, 'Impressions should not increase if record_impressions is set to true');

		Config::inst()->update('UniadsObject', 'record_impressions', false);
		$ad = $this->objFromFixture('UniadsObject', 'default');
		$impressionsBefore = $ad->Impressions;

		$ad = $ad->increaseImpressions();
		$this->assertEquals($impressionsBefore, $ad->Impressions, 'Impressions should not increase if record_impressions is set to false');

		/**
		 * check if an UniadsImpression is generated
		 */
		$impressionsCountBefore = $ad->ImpressionDetails()->count();

		Config::inst()->update('UniadsObject', 'record_impressions_stats', false,
			'The number of UniadsImpressions should not increase the counter if record_impressions_stats is set to false');
		$ad = $ad->increaseImpressions();
		$impressionsCountAfter = $ad->ImpressionDetails()->count();
		$this->assertEquals($impressionsCountBefore, $impressionsCountAfter);

		Config::inst()->update('UniadsObject', 'record_impressions_stats', true);
		$ad = $ad->increaseImpressions();
		$impressionsCountAfter = $ad->ImpressionDetails()->count();
		$this->assertEquals($impressionsCountBefore + 1, $impressionsCountAfter,
			'The number of UniadsImpressions should increase by one the counter if record_impressions_stats is set to true');
	}

	public function testAdFilterForDuplicateAds() {
		Config::inst()->update('UniadsObject', 'filter_double_ads', true);
		$page = $this->objFromFixture('Page', 'using-zone');
		$zone = $this->objFromFixture('UniadsZone', 'active-zone');

		$cnt = $page->getAdsByZone($zone)->count();
		$this->assertEquals(5, $cnt, 'we should have fife ads in this zone');

		//get all Ads by zone
		$ads[1] = $page->DisplayAd($zone);
		$ads[2] = $page->DisplayAd($zone);
		$ads[3] = $page->DisplayAd($zone);
		$ads[4] = $page->DisplayAd($zone);
		$ads[5] = $page->DisplayAd($zone);

		$emptyAd = $page->DisplayAd($zone);

		foreach ($ads as $k => $ad) {
			foreach ($ads as $k2 => $comparedAd) {
				if ($k2 !== $k) {
					$this->assertNotSame($ad, $comparedAd, 'ads must not be equal');
				}
			}
		}

		$this->assertEmpty($emptyAd, 'there should be no sixth add shown as there are just two ads...');
	}
}