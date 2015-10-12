<?php

/**
 * Description of UniadsObject (ddvertisement object)
 *
 * @author Elvinas Liutkevičius <elvinas@unisolutions.eu>
 * @author Hans de Ruiter <hans@hdrlab.org.nz>
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD http://silverstripe.org/BSD-license
 */
class UniadsObject extends DataObject {

	private static $use_js_tracking = true;
	private static $record_impressions = true;
	private static $record_impressions_stats = false;
	private static $record_clicks = true;
	private static $record_clicks_stats = true;

	private static $files_dir = 'UploadedAds';
	private static $max_file_size = 2097152;
	private static $singular_name = "Advert";
	private static $plural_name = "Adverts";

	private static $db = array(
		'Title' => 'Varchar',
		'Starts' => 'Datetime',
		'Expires' => 'Datetime',
		'Active' => 'Boolean',
		'TargetURL' => 'Varchar(255)',
		'NewWindow' => 'Boolean',
		'AdContent' => 'HTMLText',
		'ImpressionLimit' => 'Int',
		'Weight' => 'Double',
		'Impressions' => 'Int',
		'Clicks' => 'Int',
		'LocationType' => 'Enum("global,selectable","global")',
		'LinkType' => 'Enum("internal,external","external")',
		'DisplayType' => 'Enum("file,code","file")',
	);

	private static $has_one = array(
		'File' => 'File',
		'Zone' => 'UniadsZone',
		'Campaign' => 'UniadsCampaign',
		'InternalPage' => 'Page',
	);

	private static $has_many = array(
		'ImpressionDetails' => 'UniadsImpression'
	);

	private static $belongs_many_many = array(
		'AdInPages' => 'Page',
	);


	private static $defaults = array(
		'Active' => 0,
		'NewWindow' => 1,
		'ImpressionLimit' => 0,
		'Weight' => 1.0,
	);
	private static $searchable_fields = array(
		'Title',
	);
	private static $summary_fields = array(
		'showActive' => 'Active',
		'Title' => 'Title',
		'showCampaignAndCampaignStatus' => 'Campaign',
		'Zone.Title' => 'Zone',
		'Impressions' => 'Impressions',
		'Clicks' => 'Clicks',
		'showAdInPages' => 'Location',
		'Weight' => 'Weight',
	);


	public function fieldLabels($includerelations = true) {
		$labels = parent::fieldLabels($includerelations);

		$labels['Campaign.Title'] = _t('UniadsObject.has_one_Campaign', 'Campaign');
		$labels['Zone.Title'] = _t('UniadsObject.has_one_Zone', 'Zone');
		$labels['Impressions'] = _t('UniadsObject.db_Impressions', 'Impressions');
		$labels['Clicks'] = _t('UniadsObject.db_Clicks', 'Clicks');

		return $labels;
	}


	public function getCMSFields() {
		$fields = new FieldList();
		$fields->push(TabSet::create('Root', Tab::create('Main', _t('SiteTree.TABMAIN', 'Main'))));
		$previewLink = Director::absoluteBaseURL() . 'admin/' . UniadsAdmin::config()->url_segment . '/UniadsObject/preview/' . $this->ID;

		$fields->addFieldsToTab('Root.Main', array(
			TextField::create('Title', _t('UniadsObject.db_Title', 'Title'))
				->setDescription(_t('UniadsObject.Title_after_save', 'This name is used to identify the ad in the list view')),
			DropdownField::create('CampaignID', _t('UniadsObject.has_one_Campaign', 'Campaign'), DataList::create('UniadsCampaign')->map())->setEmptyString(_t('UniadsObject.Campaign_none', 'none'))
				->setDescription(_t('UniadsObject.CampaignID_Description', 'Setting a campaign helps to group ads together')),
			DropdownField::create('ZoneID', _t('UniadsObject.has_one_Zone', 'Zone'), DataList::create('UniadsZone')->map())->setEmptyString(_t('UniadsObject.Zone_select', 'select one'))
				->setDescription(_t('UniadsObject.ZoneID_Description', 'An ads zone controls where on a page it can be displayed')),
			CheckboxField::create('Active', _t('UniadsObject.db_Active', 'Active')),

			HeaderField::create('LocationHeader', 'Location'),
			OptionSetField::create("LocationType", "Location Type", array('global' => 'Display ad globally', 'selectable' => 'Choose locations to display ad'), 'global'),
			$locations = DisplayLogicWrapper::create(TreeMultiselectField::create('AdInPages', _t('UniadsObject.belongs_many_many_AdInPages', 'Locations'), 'SiteTree')
				->setDescription(_t('UniadsObject.AdInPages_Description', 'Select the locations this ad can be viewed, The ad will also show up on sub-pages'))),

			HeaderField::create('LinkHeader', _t('UniadsObject.Link_header', 'Link')),
			OptionSetField::create("LinkType", "Link Type", array('external' => 'Link to an external website', 'internal' => 'Link to an internal page'), 'external'),
			$external = TextField::create('TargetURL', _t('UniadsObject.db_TargetURL', 'Target URL')),
			$internalWrapper = DisplayLogicWrapper::create($internal = OptionalTreedropdownfield::create('InternalPageID', _t('UniadsObject.has_one_InternalPage', 'Internal Page Link'), 'SiteTree')->setEmptyString('No page')),
			$newWindow = CheckboxField::create('NewWindow', _t('UniadsObject.db_NewWindow', 'Open link in a new Window')),


			HeaderField::create('ContentHeader', _t('UniadsObject.Content_header', 'Content')),
			OptionSetField::create("DisplayType", "Display Type", array('file' => 'Display an image', 'code' => 'Use an embed code'), 'file'),
			$fileWrapper = DisplayLogicWrapper::create($file = UploadField::create('File', _t('UniadsObject.has_one_File', 'Advertisement File'))),
			$AdContent = TextareaField::create('AdContent', _t('UniadsObject.db_AdContent', 'Advertisement Content'))
				->setDescription(_t('UniadsObject.AdContent_Description', 'This field can be used with embed code from AdWords or another ad network instead of a file')),

			HeaderField::create('ExpiryHeader', 'Expiry', 3),
			$Starts = DatetimeField::create('Starts', _t('UniadsObject.db_Starts', 'Starts')),
			$Expires = DatetimeField::create('Expires', _t('UniadsObject.db_Expires', 'Expires')),
			NumericField::create('ImpressionLimit', _t('UniadsObject.db_ImpressionLimit', 'Impression Limit')),
			NumericField::create('Weight', _t('UniadsObject.db_Weight', 'Weight'))
				->setDescription(_t('UniadsObject.weight_description', 'Controls how often the ad will be shown relative to others, a value 2 will show twice as often as 1')),

			HeaderField::create('statsHeader', _t('UniadsObject.stats_header', 'Stats')),
			ReadonlyField::create('Impressions', _t('UniadsObject.db_Impressions', 'Impressions'))
				->addExtraClass('small'),
			ReadonlyField::create('Clicks', _t('UniadsObject.db_Clicks', 'Clicks'))
				->addExtraClass('small'),
			LiteralField::create('Preview', '<a href="' . $previewLink . '" target="_blank">' . _t('UniadsObject.Preview', 'Preview this advertisement') . "</a>"),
		));

		$app_categories = File::config()->app_categories;
		$file->setFolderName($this->config()->files_dir);
		$file->getValidator()->setAllowedMaxFileSize(array('*' => $this->config()->max_file_size));
		$file->getValidator()->setAllowedExtensions(array_merge($app_categories['image'], $app_categories['flash']));

		/* Display logic */
		$locations->displayIf("LocationType")->isEqualTo("selectable");
		$external->displayIf("LinkType")->isEqualTo("external");
		$internalWrapper->displayIf("LinkType")->isEqualTo("internal");
		$newWindow->displayIf("LinkType")->isEqualTo("internal")->orIf("LinkType")->isEqualTo("external");
		$fileWrapper->displayIf("DisplayType")->isEqualTo("file");
		$AdContent->displayIf("DisplayType")->isEqualTo("code");

		$AdContent->setRows(5);
		$AdContent->setColumns(20);

		$Starts->getDateField()->setConfig('showcalendar', true);
		$Starts->getDateField()->setConfig('dateformat', i18n::get_date_format());
		$Starts->getDateField()->setConfig('datavalueformat', 'yyyy-MM-dd');
		$Starts->setTimeField(TimePickerField::create('Starts[time]', '')->addExtraClass('fieldgroup-field'));
		$Starts->getTimeField()->setConfig('timeformat', 'HH:mm');

		$Expires->getDateField()->setConfig('showcalendar', true);
		$Expires->getDateField()->setConfig('dateformat', i18n::get_date_format());
		$Expires->getDateField()->setConfig('datavalueformat', 'yyyy-MM-dd');
		$Expires->getDateField()->setConfig('min', date('Y-m-d', strtotime($this->Starts ? $this->Starts : '+1 days')));
		$Expires->setTimeField(TimePickerField::create('Expires[time]', '')->addExtraClass('fieldgroup-field'));
		$Expires->getTimeField()->setConfig('timeformat', 'HH:mm');

		$this->extend('updateCMSFields', $fields);
		return $fields;
	}

	public function onBeforeWrite()
	{
		// Remove this ad from all pages if global has been selected
		if ($this->LocationType == 'global') {
			$this->AdInPages()->removeAll();
		}

		parent::onBeforeWrite();
	}


	/** Returns true if this is an "external" advertisment (e.g., one from Google AdSense).
	 * "External" advertisements have no target URL or page.
	 */
	public function ExternalAd() {
		if (!$this->InternalPageID && empty($this->TargetURL)) {
			return true;
		}

		$file = $this->getComponent('File');
		if ($file && $file->appCategory() == 'flash') {
			return true;
		}

		return false;
	}

	public function forTemplate() {
		$template = new SSViewer('UniadsObject');
		return $template->process($this);
	}

	public function UseJsTracking() {
		return $this->config()->use_js_tracking;
	}

	public function TrackingLink($absolute = false) {
		return Controller::join_links($absolute ? Director::absoluteBaseURL() : Director::baseURL(), 'uniads-click/go/'.$this->ID);
	}

	public function Link() {
		if ($this->UseJsTracking()) {
			Requirements::javascript(THIRDPARTY_DIR.'/jquery/jquery.js'); // TODO: How about jquery.min.js?
			Requirements::javascript(ADS_MODULE_DIR.'/javascript/uniads.js');

			$link = Convert::raw2att($this->getTarget());
		} else {
			$link = $this->TrackingLink();
		}
		return $link;
	}

	public function getTarget() {
		return $this->InternalPageID
			? $this->InternalPage()->AbsoluteLink()
			: ($this->TargetURL ? (strpos($this->TargetURL, 'http') !== 0 ? 'http://' : '') . $this->TargetURL : false)
		;
	}

	public function getContent() {
		$file = $this->getComponent('File');
		$zone = $this->getComponent('Zone');
		if ($file) {
			if ($file->appCategory() == 'flash') {
				$src = $this->getTarget() ? HTTP::setGetVar('clickTAG', $this->TrackingLink(true), $file->Filename) : $file->Filename;
				return '
					<object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" width="'.$zone->Width.'" height="'.$zone->Height.'" style="display:block;">
						<param name="movie" value="'.$src.'" />
						<param name="quality" value="high" />
						<param name="wmode" value="transparent" />
						<embed
							src="'.$src.'"
							quality="high"
							wmode="transparent"
							width="'.$zone->Width.'"
							height="'.$zone->Height.'"
							type="application/x-shockwave-flash"
							pluginspage="http://www.macromedia.com/go/getflashplayer">
						</embed>
					</object>
				';
			} else if ($file->appCategory() == 'image') {
				return '<img src="'.$file->URL.'" style="width:100%;display:block;" alt="'.$file->Title.'" />';
			}
		}
		return $this->AdContent;
	}

	/**
	 * Increases the impression counter if 'record_impressions' setting is true
	 * Creates a new UniadsImpression  entry in DB if 'record_impressions_stats' is true
	 * @return UniadsObject
	 */
	public function increaseImpressions(){
		$ad = clone($this);
		if ($this->memberShouldIncreaseImpressions(Member::currentUser()) && $this->stat('record_impressions')) {
			$ad->Impressions++;
			$ad->write();
		}
		if ($this->memberShouldIncreaseImpressions(Member::currentUser()) && $this->stat('record_impressions_stats')) {
			$imp = new UniadsImpression;
			$imp->AdID = $ad->ID;
			$imp->write();
		}
		return $ad;
	}

	/**
	 * Increases the clicks counter if 'record_clicks' setting is true
	 * Creates a new UniadsClickentry in DB if 'record_click_stats' is true
	 * @return UniadsObject
	 */
	public function increaseClicks(){
		$ad = clone($this);
		if ($this->memberShouldIncreaseImpressions(Member::currentUser()) && $this->stat('record_clicks')) {
			$ad->Clicks++;
			$ad->write();
		}
		if ($this->memberShouldIncreaseImpressions(Member::currentUser()) && $this->stat('record_clicks_stats')) {
			$clk = new UniadsClick;
			$clk->AdID = $ad->ID;
			$clk->write();
		}
		return $ad;
	}

	/**
	 * Check to see if member should increase impressions so that admins cannot effect
	 * advertising statistics.
	 * todo: add the ability to set the permission level to check from admin
	 * @return bool
     */
	private function memberShouldIncreaseImpressions($member)
	{
		if (Permission::checkMember($member, "VIEW_DRAFT_CONTENT")) {
		    return false;
		}
		return true;
	}

	// Permissions
	// -----------
	public function canView($member = null) {
		return Permission::check('CMS_ACCESS_UniadsAdmin', 'any', $member);
	}

	public function canEdit($member = null) {
		return Permission::check('CMS_ACCESS_UniadsAdmin', 'any', $member);
	}

	public function canDelete($member = null) {
		return Permission::check('CMS_ACCESS_UniadsAdmin', 'any', $member);
	}

	public function canCreate($member = null) {
		return Permission::check('CMS_ACCESS_UniadsAdmin', 'any', $member);
	}

	// Summary Field Functions
	// -----------------------

	/**
	 * Summary field function that returns an HTML check mark if this ad is active
	 * @return literalField
	 */
	public function showActive()
	{
		if ($this->Active == 1) {
			return literalField::create('check', '<span style="display:block; text-align:center; color:#1F9433">&check;</span>');
		}
	}

	/**
	 * Summary field function that returns the name of the linked campaign and extra text
	 * to warn if the campaign is inactive
	 * @return literalField
	 */
	public function showCampaignAndCampaignStatus()
	{
		$campaignName =  $this->Campaign()->Title;
		if ($this->Campaign()->exists() && $this->Campaign()->Active == 0) {
			return literalField::create('campaign', $campaignName . ' <span style="color:red">Inactive</span>');
		}

		return literalField::create('campaign', $campaignName);
	}


	/**
	 * Summary field function that returns a string listing all the locations the ad is attached to
	 * or 'global' if the ad will show everywhere
	 * @return string
	 */
	public function showAdInPages()
	{
		if (0 == $this->AdInPages()->count()) {
			return 'Global';
		}

		$ret = array();
		foreach ($this->AdInPages() as $page) {
			$ret[$page->ID] = $page->Title;
		}
		return implode(", ", $ret);
	}
}
