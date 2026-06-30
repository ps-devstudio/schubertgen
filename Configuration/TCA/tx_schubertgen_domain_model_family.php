<?php

declare(strict_types=1);

defined('TYPO3') or die();

return [
    'ctrl' => [
        'title' => 'LLL:EXT:schubertgen/Resources/Private/Language/locallang_db.xlf:tx_schubertgen_domain_model_family',
        'label' => 'external_id',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => ['disabled' => 'hidden'],
        'searchFields' => 'external_id,husband_external_id,wife_external_id,marriage_place',
        'iconfile' => 'EXT:schubertgen/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'hidden, external_id, husband_external_id, wife_external_id, husband, wife, children, marriage_date_text, marriage_place, events'],
    ],
    'columns' => [
        'hidden' => ['label' => 'Hidden', 'config' => ['type' => 'check', 'default' => 0]],
        'external_id' => ['label' => 'GEDCOM ID', 'config' => ['type' => 'input', 'readOnly' => true]],
        'husband_external_id' => ['label' => 'Husband GEDCOM ID', 'config' => ['type' => 'input', 'readOnly' => true]],
        'wife_external_id' => ['label' => 'Wife GEDCOM ID', 'config' => ['type' => 'input', 'readOnly' => true]],
        'husband' => [
            'label' => 'Husband',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_schubertgen_domain_model_person',
                'items' => [['', 0]],
                'default' => 0,
            ],
        ],
        'wife' => [
            'label' => 'Wife',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_schubertgen_domain_model_person',
                'items' => [['', 0]],
                'default' => 0,
            ],
        ],
        'children' => [
            'label' => 'Children',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_schubertgen_domain_model_person',
                'MM' => 'tx_schubertgen_family_child_mm',
            ],
        ],
        'marriage_date_text' => ['label' => 'Marriage date', 'config' => ['type' => 'input', 'size' => 20]],
        'marriage_place' => ['label' => 'Marriage place', 'config' => ['type' => 'input', 'size' => 60]],
        'events' => [
            'label' => 'Events',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_schubertgen_domain_model_event',
                'foreign_field' => 'family',
                'appearance' => [
                    'collapseAll' => true,
                    'expandSingle' => true,
                ],
            ],
        ],
    ],
];
