<?php

namespace Symbiote\SteamedClams\Admin;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Security\Permission;
use SilverStripe\View\Requirements;
use Symbiote\SteamedClams\ClamAV;
use Symbiote\SteamedClams\Forms\GridFieldClamAVAction;
use Symbiote\SteamedClams\Model\ClamAVScan;
use SilverStripe\AssetAdmin\Controller\AssetAdmin;

/**
 * Class Symbiote\SteamedClams\ClamAVAdmin
 *
 */
class ClamAVAdmin extends ModelAdmin
{
    /**
     * @var string
     */
    private static $url_segment = 'clamav';

    /**
     * @var string
     */
    private static $menu_title = 'ClamAV';

    /**
     * @var array
     */
    private static $managed_models = [
        ClamAVScan::class,
    ];

    /**
     * @var string
     */
    private static $menu_icon = 'symbiote/silverstripe-steamedclams:client/images/clamav_icon.png';

    /**
     * @var array
     */
    private static $allowed_actions = [
        'Assets',
    ];

    /**
     * Unable to directly link to a File in the AssetAdmin, so set
     * the required FileID via `setCurrentPageID` and redirect.
     *
     * @return HTTPResponse|string
     */
    public function Assets()
    {
        $request = $this->getRequest();
        $id = $request->shift();
        if (!$id) {
            return $this->redirect($this->Link());
        }
        $assetAdmin = singleton(AssetAdmin::class);
        if (!$assetAdmin->canView()) {
            return $this->redirect($this->Link());
        }
        $assetAdmin->setCurrentPageID($id);

        //Session::set($assetAdmin->class.".currentPage", (int)$id);
        return $this->redirect(
            Controller::join_links($assetAdmin->Link('EditForm'), 'field', 'File', 'item', $id, 'edit')
        );
    }

    public function getEditForm($id = null, $fields = null)
    {
        $self = &$this;
        $this->beforeExtending('updateEditForm', function ($form) use ($self) {
            Requirements::css('symbiote/silverstripe-steamedclams:client/css/ClamAVCMS.css');

            $fields = $form->Fields();
            $insertBeforeFieldName = str_replace('\\', '-', $self->config()->managed_models[0]);
            $gridField = $fields->dataFieldByName($insertBeforeFieldName);
            if ($gridField) {
                $gridConfig = $gridField->getConfig();
                $gridConfig->removeComponentsByType(GridFieldAddNewButton::class);
                // NOTE(Jake): These buttons shouldn't be necessary, but incase you want to bring
                //             them back, add '?fullview'
                if ((Permission::check('ADMIN') && isset($_GET['fullview'])) === false) {
                    $gridConfig->removeComponentsByType(GridFieldEditButton::class);
                    $gridConfig->removeComponentsByType(GridFieldDeleteAction::class);
                }
                $gridConfig->addComponent(Injector::inst()->create(GridFieldClamAVAction::class));
            }

            $clamAV = Injector::inst()->get(ClamAV::class);

            $version = $clamAV->version();
            $reason = '';
            if ($version === ClamAV::OFFLINE) {
                $version = '<p style="margin-bottom: 20px"><strong style="color: #C00;">OFFLINE</strong></p>';

                $exception = $clamAV->getLastException();
                if ($exception) {
                    $reason = 'Reason: ' . $exception->getMessage();
                }
            } else {
                $version = '<p style="margin-bottom: 20px"><strong style="color: #18BA18;">ONLINE</strong> (' . $version . ')</p>';
            }

            $versionField = LiteralField::create('ClamAV_Version', $version);
            $versionField->setRightTitle($reason);
            $versionField->dontEscape = true;
            $fields->insertBefore($insertBeforeFieldName, $versionField);

            // Files to scan with install task
            $listCount = 0;
            $list = $clamAV->getInitialFileToScanList();

            if ($list) {
                $listCount = $list->count();
            }

            if ($listCount > 0) {
                $fields->insertBefore(
                    $insertBeforeFieldName,
                    ReadonlyField::create(
                        'ClamAV_InitialScan',
                        'Files to scan with install task',
                        $listCount . ' '
                    )
                );
            }

            //Files that failed to scan
            $listCount = 0;
            $list = $clamAV->getFailedToScanFileList();
            if ($list) {
                $listCount = $list->count();
            }
            $fields->insertBefore(
                $insertBeforeFieldName,
                ReadonlyField::create('ClamAV_NeedScan', 'Files that failed to scan', $listCount . ' ')
                    ->setRightTitle('Due to ClamAV daemon being inaccessible/offline.')
            );
        });

        $form = parent::getEditForm($id, $fields);

        return $form;
    }
}
