<?php

namespace Symbiote\SteamedClams\Reports;

use SilverStripe\Control\Controller;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\ORM\DataList;
use SilverStripe\Reports\Report;
use SilverStripe\Security\Member;
use Symbiote\SteamedClams\Model\ClamAVScan;

/**
 * Class ClamAVScanReport
 *
 * This report gives the option to select certain date ranges and view who uploaded what kind of file
 */
class ClamAVScanReport extends Report
{

    /**
     * @return string
     */
    public function title()
    {
        return 'Clam AV Scan results';
    }

    /**
     * @return FieldList
     */
    public function parameterFields()
    {
        $fieldList = FieldList::create(
            [
                $startDate = DateField::create('Created:LessThan', _t('ClamAV.FROM_DATE', 'From date')),
                $endDate = DateField::create('Created:GreaterThan', _t('ClamAV.TO_DATE', 'To date')),
                $scanned = DropdownField::create('IsScanned', _t('ClamAV.IS_SCANNED', 'Is scanned'), [
                    true  => 'Yes',
                    false => 'No',
                ]),
                $action = DropdownField::create(
                    'Action',
                    _t('ClamAV.ACTION_TAKEN', 'Action taken'),
                    [
                        ClamAVScan::ACTION_NONE    => _t('ClamAV.ACTION_TAKEN.NONE', 'No action taken'),
                        ClamAVScan::ACTION_DELETED => _t('ClamAV.ACTION_TAKEN.DELETED', 'File deleted'),
                        ClamAVScan::ACTION_IGNORED => _t('ClamAV.ACTION_TAKEN.IGNORED', 'File ignored'),
                    ]
                ),
                $memberField = DropdownField::create(
                    'MemberID',
                    _t('ClamAV.UPLOADED_BY', 'Uploaded by'),
                    Member::get()->map('ID', 'getName')->toArray()
                ),
            ]
        );
        $startDate->setHTML5(false)
            ->setDateFormat('dd/MM/yyyy')
            ->setAttribute('placeholder', sprintf('Example: %s', date('d/m/Y')))
            ->setDescription('Date format (dd/mm/yyyy)');

        $endDate->setHTML5(false)
            ->setDateFormat('dd/MM/yyyy')
            ->setAttribute('placeholder', sprintf('Example: %s', date('d/m/Y')))
            ->setDescription('Date format (dd/mm/yyyy)');
        $scanned->setEmptyString('All');
        $action->setEmptyString('All actions');
        $memberField->setEmptyString('All members');

        // Workaround for 0 being treated as empty by {@link DropdownField}
        $filters = Controller::curr()->getRequest()->getVar('filters');
        if (isset($filters['Action'])) {
            $action->setValue($filters['Action']);
        }

        return $fieldList;
    }

    /**
     * @inheritdoc
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // Fix the export button so that it uses the columns defined below,
        // instead of those from {@link Symbiote\\SteamedClams\\ClamAVScan}
        /** @var GridField $report */
        $report = $fields->dataFieldByName('Report');
        $report->getConfig()->getComponentByType(GridFieldExportButton::class)->setExportColumns($this->columns());

        return $fields;
    }

    /**
     * @inheritdoc
     * @return array
     */
    public function columns()
    {
        return [
            'UserIdentifier'   => _t('ClamAV.USER_IDENTIFIER', 'User Identifier'),
            'Created'          => _t('ClamAV.DATE_SCANNED', 'Date Scanned'),
            'File.Title'       => _t('ClamAV.FILE_NAME', 'File Name'),
            'LocationUploaded' => _t('ClamAV.LOCATION_UPLOADED', 'Location Uploaded'),
            'StateMessage'     => _t('ClamAV.STATE', 'State'),
            'RawDataSummary'   => _t('ClamAV.INFO', 'Virus Scan Info.'),
        ];
    }

    /**
     * @param array $params
     *
     * @return DataList
     */
    public function sourceRecords($params = [])
    {
        $filter = $this->createFilter($params);

        return ClamAVScan::get()
            ->filter($filter)
            ->exclude($params);
    }

    /**
     * @param array $params
     *
     * @return array
     */
    private function createFilter(&$params)
    {
        $filter = [];
        if (isset($params['Action'])) {
            $filter['Action'] = $params['Action'];
        }
        if (isset($params['MemberID'])) {
            $filter['MemberID'] = $params['MemberID'];
        }
        if (isset($params['IsScanned'])) {
            $filter['IsScanned'] = $params['IsScanned'];
        }
        unset($params['IsScanned'], $params['MemberID'], $params['Action']);

        return $filter;
    }
}
