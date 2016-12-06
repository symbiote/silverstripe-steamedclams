<?php

namespace SilbinaryWolf\SteamedClams;
use ReadonlyField;
use LiteralField;
use Debug;
use Session;
use Controller;
use Requirements;
use Injector;
use Permission;

class ClamAVAdmin extends \ModelAdmin {
	private static $url_segment = 'clamav';
	private static $menu_title = 'ClamAV';
	private static $managed_models = array(
        'SilbinaryWolf\SteamedClams\ClamAVScan',
    );
	private static $menu_icon = 'steamedclams/images/clamav_icon.png';

    private static $allowed_actions = array(
        'Assets',
    );

    /**
     * Unable to directly link to a File in the AssetAdmin, so set
     * the required FileID via `setCurrentPageID` and redirect.
     *
     * @return \SS_HTTPResponse
     */
    public function Assets() {
        $request = $this->getRequest();
        $id = $request->shift();
        if (!$id) {
            return $this->redirect($this->Link());
        }
        $assetAdmin = singleton('AssetAdmin');
        if (!$assetAdmin->canView()) {
            return $this->redirect($this->Link());
        }
        $assetAdmin->setCurrentPageID($id);
        //Session::set($assetAdmin->class.".currentPage", (int)$id);
        return $this->redirect(Controller::join_links($assetAdmin->Link('EditForm'), 'field', 'File', 'item', $id, 'edit'));
    }

	public function getEditForm($id = null, $fields = null) {
        $self = &$this;
        $this->beforeExtending('updateEditForm', function($form) use ($self) {
            Requirements::css(ClamAv::MODULE_DIR.'/css/ClamAVCMS.css');
        
            $fields = $form->Fields();
            $insertBeforeFieldName = str_replace('\\', '-', $self->config()->managed_models[0]);
            $gridField = $fields->dataFieldByName($insertBeforeFieldName);
            if ($gridField) {
                $gridConfig = $gridField->getConfig();
                $gridConfig->removeComponentsByType('GridFieldAddNewButton');
                // NOTE(Jake): These buttons shouldn't be necessary, but incase you want to bring
                //             them back, add '?fullview'
                if ((Permission::check('ADMIN') && isset($_GET['fullview'])) === false) {
                    $gridConfig->removeComponentsByType('GridFieldEditButton');
                    $gridConfig->removeComponentsByType('GridFieldDeleteAction');
                }
                $gridConfig->addComponent(Injector::inst()->create('SilbinaryWolf\SteamedClams\GridFieldClamAVAction'));
            }

            $clamAV = singleton('SilbinaryWolf\SteamedClams\ClamAV');

            $version = $clamAV->version();
            $reason = '';
            if ($version === ClamAV::OFFLINE) {
                $version = '<strong style="color: #C00;">OFFLINE</strong>';

                $exception = $clamAV->getLastException();
                if ($exception) {
                    $reason = 'Reason: '.$exception->getMessage();
                }
            } else {
                $version = '<strong style="color: #18BA18;">ONLINE</strong> ('.$version.')';
            }

            $versionField = ReadonlyField::create('ClamAV_Version', 'ClamAV Version', $version);
            $versionField->setRightTitle($reason);
            $versionField->dontEscape = true;
            $fields->insertBefore($versionField, $insertBeforeFieldName);

            // Files to scan with install task
            $listCount = 0;
            $list = $clamAV->getInitialFileToScanList();
            if ($list) {
                $listCount = $list->count();
            }
            if ($listCount > 0) {
                $fields->insertBefore(ReadonlyField::create('ClamAV_InitialScan', 'Files to scan with install task', $listCount.' '), $insertBeforeFieldName);
            }

            // Files that failed to scan 
            $listCount = 0;
            $list = $clamAV->getFailedToScanFileList();
            if ($list) {
                $listCount = $list->count();
            }
            $fields->insertBefore(ReadonlyField::create('ClamAV_NeedScan', 'Files that failed to scan', $listCount.' ')->setRightTitle('Due to ClamAV daemon being inaccessible/offline.'), $insertBeforeFieldName);
        });

        $form = parent::getEditForm($id, $fields);
        return $form;
    }
}
