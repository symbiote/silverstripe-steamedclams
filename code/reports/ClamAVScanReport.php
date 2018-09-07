<?php

use Symbiote\SteamedClams\ClamAVScan;

if (class_exists('SS_Report')) {
/**
 * Class ClamAVScanReport
 *
 * Class can not be namespaced for SilverStripe 3
 *
 * This report gives the option to select certain date ranges and view who uploaded what kind of file
 */
class ClamAVScanReport extends SS_Report
{

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
            array(
                $startDate = DateField::create('Created:LessThan', _t('ClamAV.FROM_DATE', 'From date')),
                $endDate = DateField::create('Created:GreaterThan', _t('ClamAV.TO_DATE', 'To date')),
                $scanned = DropdownField::create('IsScanned', _t('ClamAV.IS_SCANNED', 'Is scanned'), array(
                    true  => 'Yes',
                    false => 'No'
                )),
                $action = DropdownField::create('Action', _t('ClamAV.ACTION_TAKEN', 'Action taken'),
                    array(
                        ClamAVScan::ACTION_NONE    => _t('ClamAV.ACTION_TAKEN.NONE', 'No action taken'),
                        ClamAVScan::ACTION_DELETED => _t('ClamAV.ACTION_TAKEN.DELETED', 'File deleted'),
                        ClamAVScan::ACTION_IGNORED => _t('ClamAV.ACTION_TAKEN.IGNORED', 'File ignored'),
                    )),
                $memberField = DropdownField::create('MemberID', _t('ClamAV.UPLOADED_BY', 'Uploaded by'),
                    Member::get()->map('ID', 'getName')->toArray())
            )
        );
        $startDate->setConfig('showcalendar', true);
        $endDate->setConfig('showcalendar', true);
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
        $report->getConfig()->getComponentByType('GridFieldExportButton')->setExportColumns($this->columns());

        return $fields;
    }

    /**
     * @param array $params
     * @return DataList
     */
    public function sourceRecords($params = array())
    {
        $filter = $this->createFilter($params);

        return ClamAVScan::get()
            ->filter($filter)
            ->exclude($params);
    }

    /**
     * @inheritdoc
     * @return array
     */
    public function columns()
    {
        return array(
            'UserIdentifier'   => _t('ClamAV.USER_IDENTIFIER', 'User Identifier'),
            'Created'          => _t('ClamAV.DATE_SCANNED', 'Date Scanned'),
            'File.Title'       => _t('ClamAV.FILE_NAME', 'File Name'),
            'LocationUploaded' => _t('ClamAV.LOCATION_UPLOADED', 'Location Uploaded'),
            'StateMessage'     => _t('ClamAV.STATE', 'State'),
            'RawDataSummary'   => _t('ClamAV.INFO', 'Virus Scan Info.'),
        );

    }

    /**
     * @param array $params
     * @return array
     */
    private function createFilter(&$params)
    {
        $filter = array();
        if (isset($params['Action'])) {
            $filter['Action'] = $params['Action'];
        }
        if (isset($params['MemberID'])) {
            $filter['MemberID'] = $params['MemberID'];
        }
        if (isset($params['Scanned'])) {
            $filter['Scanned'] = $params['Scanned'];
        }
        unset($params['Scanned'], $params['MemberID'], $params['Action']);

        return $filter;
    }
}

}