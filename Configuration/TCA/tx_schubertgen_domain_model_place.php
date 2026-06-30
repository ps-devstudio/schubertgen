<?php

declare(strict_types=1);

defined('TYPO3') or die();

return [
    'ctrl' => [
        'title' => 'LLL:EXT:schubertgen/Resources/Private/Language/locallang_db.xlf:tx_schubertgen_domain_model_place',
        'label' => 'name',
        'label_alt' => 'country,latitude,longitude',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => ['disabled' => 'hidden'],
        'searchFields' => 'name,original_name,city,county,state,country,external_id',
        'iconfile' => 'EXT:schubertgen/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'hidden, external_id, name, original_name, city, county, state, country, latitude, longitude'],
    ],
    'columns' => [
        'hidden' => ['label' => 'Hidden', 'config' => ['type' => 'check', 'default' => 0]],
        'external_id' => ['label' => 'Import ID', 'config' => ['type' => 'input', 'readOnly' => true]],
        'name' => ['label' => 'Name', 'config' => ['type' => 'input', 'size' => 60]],
        'original_name' => ['label' => 'Original GEDCOM place', 'config' => ['type' => 'input', 'size' => 80]],
        'city' => ['label' => 'City', 'config' => ['type' => 'input', 'size' => 40]],
        'county' => ['label' => 'County', 'config' => ['type' => 'input', 'size' => 40]],
        'state' => ['label' => 'State/region', 'config' => ['type' => 'input', 'size' => 40]],
        'country' => ['label' => 'Country', 'config' => ['type' => 'input', 'size' => 40]],
        'latitude' => ['label' => 'Latitude', 'config' => ['type' => 'number', 'format' => 'decimal', 'default' => 0]],
        'longitude' => ['label' => 'Longitude', 'config' => ['type' => 'number', 'format' => 'decimal', 'default' => 0]],
    ],
];
