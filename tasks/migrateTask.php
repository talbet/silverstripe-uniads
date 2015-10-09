<?php

class UniadsMigrateTask extends BuildTask
{
    protected $title = 'Uniads Field Migration';
    protected $description = 'Sets fields that have been added to existing adverts';
    protected $enabled = true;

    public function run($request)
    {
        $ads = DataList::create('UniadsObject');

        echo "<ul>";
        foreach ($ads as $ad) {

            $ad->LocationType = $this->checkLocationType($ad);
            $ad->LinkType = $this->checkLinkType($ad);
            $ad->DisplayType = $this->checkDisplayType($ad);

            echo "<li>$ad->Title - $ad->LocationType - $ad->LinkType - $ad->DisplayType </li>";

            $ad->write();

        }

        echo "</ul>";
    }

    private function checkLocationType($ad)
    {
        if ($ad->AdInPages()->exists()) {
            return 'selectable';
        }

        return 'global';
    }

    private function checkLinkType($ad)
    {
        if ($ad->InternalPage()->exists()) {
            return 'internal';
        }

        return 'external';
    }

    private function checkDisplayType($ad)
    {
        if ($ad->AdContent) {
            return 'code';
        }

        return 'file';
    }
}
