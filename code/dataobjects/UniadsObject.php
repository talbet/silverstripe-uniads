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

	private static $db = array(
		'Title' => 'Varchar',
		'Starts' => 'Date',
		'Expires' => 'Date',
		'Active' => 'Boolean',
		'TargetURL' => 'Varchar(255)',
		'NewWindow' => 'Boolean',
		'AdContent' => 'HTMLText',
		'ImpressionLimit' => 'Int',
		'Weight' => 'Double',
		'Impressions' => 'Int',
		'Clicks' => 'Int',
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
		$fields->push(new TabSet('Root', new Tab('Main', _t('SiteTree.TABMAIN', 'Main')
			, new TextField('Title', _t('UniadsObject.db_Title', 'Title'))
		)));

		if ($this->ID) {
			$previewLink = Director::absoluteBaseURL() . 'admin/' . UniadsAdmin::config()->url_segment . '/UniadsObject/preview/' . $this->ID;

			$fields->addFieldToTab('Root.Main', new ReadonlyField('Impressions', _t('UniadsObject.db_Impressions', 'Impressions')), 'Title');
			$fields->addFieldToTab('Root.Main', new ReadonlyField('Clicks', _t('UniadsObject.db_Clicks', 'Clicks')), 'Title');

			$fields->addFieldsToTab('Root.Main', array(
				DropdownField::create('CampaignID', _t('UniadsObject.has_one_Campaign', 'Campaign'), DataList::create('UniadsCampaign')->map())->setEmptyString(_t('UniadsObject.Campaign_none', 'none')),
				DropdownField::create('ZoneID', _t('UniadsObject.has_one_Zone', 'Zone'), DataList::create('UniadsZone')->map())->setEmptyString(_t('UniadsObject.Zone_select', 'select one')),
				NumericField::create('Weight', _t('UniadsObject.db_Weight', 'Weight'))
					->setDescription(_t('UniadsObject.weight_description', 'Controls how often the ad will be shown relative to others, a value 2 will show twice as often as 1')),
				TextField::create('TargetURL', _t('UniadsObject.db_TargetURL', 'Target URL'))
					->setDescription(_t('UniadsObject.TargetURL_Description', 'Optional: An external link that will be loaded when the ad is clicked')),
				Treedropdownfield::create('InternalPageID', _t('UniadsObject.has_one_InternalPage', 'Internal Page Link'), 'Page')
					->setDescription(_t('UniadsObject.InternalPageID_Description', 'Optional: An internal link that will be loaded when the ad is clicked')),
				CheckboxField::create('NewWindow', _t('UniadsObject.db_NewWindow', 'Open in a new Window')),
				$file = UploadField::create('File', _t('UniadsObject.has_one_File', 'Advertisement File')),
				$AdContent = TextareaField::create('AdContent', _t('UniadsObject.db_AdContent', 'Advertisement Content'))
					->setDescription(_t('UniadsObject.AdContent_Description', 'Optional: Use an embed code from AdWords or another ad network instead of a file')),
				$Starts = DateField::create('Starts', _t('UniadsObject.db_Starts', 'Starts')),
				$Expires = DateField::create('Expires', _t('UniadsObject.db_Expires', 'Expires')),
				NumericField::create('ImpressionLimit', _t('UniadsObject.db_ImpressionLimit', 'Impression Limit')),
				CheckboxField::create('Active', _t('UniadsObject.db_Active', 'Active')),
				LiteralField::create('Preview', '<a href="'.$previewLink.'" target="_blank">' . _t('UniadsObject.Preview', 'Preview this advertisement') . "</a>"),
			));

			$app_categories = File::config()->app_categories;
			$file->setFolderName($this->config()->files_dir);
			$file->getValidator()->setAllowedMaxFileSize(array('*' => $this->config()->max_file_size));
			$file->getValidator()->setAllowedExtensions(array_merge($app_categories['image'], $app_categories['flash']));

			$AdContent->setRows(5);
			$AdContent->setColumns(20);

			$Starts->setConfig('showcalendar', true);
			$Starts->setConfig('dateformat', i18n::get_date_format());
			$Starts->setConfig('datavalueformat', 'yyyy-MM-dd');

			$Expires->setConfig('showcalendar', true);
			$Expires->setConfig('dateformat', i18n::get_date_format());
			$Expires->setConfig('datavalueformat', 'yyyy-MM-dd');
			$Expires->setConfig('min', date('Y-m-d', strtotime($this->Starts ? $this->Starts : '+1 days')));
		}

		if ($this->owner->isInDB() == false) {
			// Description for title before save
			$fields->dataFieldByName('Title')->setDescription(_t('UniadsObject.Title_before_save', 'Add a title and save the ad before adding an image'));
		} else {
			// Description for title after save
			$fields->dataFieldByName('Title')->setDescription(_t('UniadsObject.Title_after_save', 'This name is used to identify the ad in the list view'));
		}

		$this->extend('updateCMSFields', $fields);
		return $fields;
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
		if ($this->memberShouldIncreaseImpressions() && $this->stat('record_impressions')) {
			$ad->Impressions++;
			$ad->write();
		}
		if ($this->memberShouldIncreaseImpressions() && $this->stat('record_impressions_stats')) {
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
		if ($this->memberShouldIncreaseImpressions() && $this->stat('record_clicks')) {
			$ad->Clicks++;
			$ad->write();
		}
		if ($this->memberShouldIncreaseImpressions() && $this->stat('record_clicks_stats')) {
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
	private function memberShouldIncreaseImpressions()
	{
		if (Permission::check("VIEW_DRAFT_CONTENT")) {
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
