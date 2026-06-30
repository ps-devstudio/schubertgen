<?php

declare(strict_types=1);

defined('TYPO3') or die();

return [
    'ctrl' => [
        'title' => 'LLL:EXT:schubertgen/Resources/Private/Language/locallang_db.xlf:tx_schubertgen_domain_model_event',
        'label' => 'event_type',
        'label_alt' => 'date_text,place',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => ['disabled' => 'hidden'],
        'searchFields' => 'external_id,event_type,date_text,place,parent_external_id',
        'iconfile' => 'EXT:schubertgen/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'hidden, external_id, parent_type, parent_external_id, person, family, event_type, date_text, date_year, date_month, date_day, date_modifier, sort_date, place, place_record, latitude, longitude, description, source_external_id, source'],
    ],
    'columns' => [
        'hidden' => ['label' => 'Hidden', 'config' => ['type' => 'check', 'default' => 0]],
        'external_id' => ['label' => 'Import ID', 'config' => ['type' => 'input', 'readOnly' => true]],
        'parent_type' => ['label' => 'Parent type', 'config' => ['type' => 'input', 'readOnly' => true]],
        'parent_external_id' => ['label' => 'Parent GEDCOM ID', 'config' => ['type' => 'input', 'readOnly' => true]],
        'person' => [
            'label' => 'Person',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_schubertgen_domain_model_person',
                'items' => [['', 0]],
                'default' => 0,
            ],
        ],
        'family' => [
            'label' => 'Family',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_schubertgen_domain_model_family',
                'items' => [['', 0]],
                'default' => 0,
            ],
        ],
        'event_type' => ['label' => 'Event type', 'config' => ['type' => 'input', 'readOnly' => true]],
        'date_text' => ['label' => 'Date', 'config' => ['type' => 'input', 'size' => 20]],
        'date_year' => ['label' => 'Year', 'config' => ['type' => 'number', 'default' => 0]],
        'date_month' => ['label' => 'Month', 'config' => ['type' => 'number', 'default' => 0, 'range' => ['lower' => 0, 'upper' => 12]]],
        'date_day' => ['label' => 'Day', 'config' => ['type' => 'number', 'default' => 0, 'range' => ['lower' => 0, 'upper' => 31]]],
        'date_modifier' => ['label' => 'Date modifier', 'config' => ['type' => 'input', 'size' => 20]],
        'sort_date' => ['label' => 'Sort date', 'config' => ['type' => 'number', 'default' => 0]],
        'place' => ['label' => 'Place', 'config' => ['type' => 'input', 'size' => 60]],
        'place_record' => [
            'label' => 'Place record',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_schubertgen_domain_model_place',
                'items' => [['', 0]],
                'default' => 0,
            ],
        ],
        'latitude' => ['label' => 'Latitude', 'config' => ['type' => 'number', 'format' => 'decimal', 'default' => 0]],
        'longitude' => ['label' => 'Longitude', 'config' => ['type' => 'number', 'format' => 'decimal', 'default' => 0]],
        'description' => ['label' => 'Description', 'config' => ['type' => 'text', 'rows' => 4]],
        'source_external_id' => ['label' => 'Source GEDCOM ID', 'config' => ['type' => 'input', 'readOnly' => true]],
        'source' => [
            'label' => 'Source',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_schubertgen_domain_model_source',
                'items' => [['', 0]],
                'default' => 0,
            ],
        ],
    ],
];
