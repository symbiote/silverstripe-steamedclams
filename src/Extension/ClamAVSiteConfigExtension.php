<?php

namespace Symbiote\SteamedClams\Extension;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;

/**
 * This extension adds contact information such as 'Phone' and 'Email' as well
 * as 'SocialMediaLinks'
 */
class ClamAVSiteConfigExtension extends DataExtension
{

    /**
     * @var array
     */
    private static $db = [
        'ValidationMessage' => 'Varchar(255)',
    ];

    /**
     * @var array
     */
    private static $defaults = [
        'validationMessage' => 'A virus was detected.',
    ];

    /**
     * @param  Fieldlist $fields
     *
     * @return void
     */
    public function updateCMSFields(Fieldlist $fields)
    {
        $fields->addFieldsToTab(
            'Root.ClamAV',
            [
                TextField::create('ValidationMessage', 'Validation Message')
                    ->setDescription('This will display as a validation message when virus detected.'),
            ]
        );
    }
}
