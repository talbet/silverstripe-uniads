<?php

/**
 * Description of UniadsController
 *
 * @author Elvinas Liutkevičius <elvinas@unisolutions.eu>
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD http://silverstripe.org/BSD-license
 */
class UniadsController extends Controller {

	private static $allowed_actions = array(
		'clk',
		'go',
	);

	public function clk() {
		$this->GetAdAndLogClick($this->request->requestVar('id'));
	}

	public function go() {
		$ad = $this->GetAdAndLogClick($this->request->param('ID'));
		if ($ad) {
			$target = $ad->getTarget();
			$this->redirect($target ? $target : Director::baseURL());
		}
	}

	private function GetAdAndLogClick($id) {
		$id = (int) $id;
		if ($id) {
			$ad = UniadsObject::get()->byID($id);
			if ($ad && $ad->exists()) {
				$ad->increaseClicks();
				return $ad;
			}
		}
		return null;
	}

}
