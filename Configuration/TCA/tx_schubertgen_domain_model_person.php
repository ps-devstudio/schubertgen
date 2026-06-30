<?php

declare(strict_types=1);

defined('TYPO3') or die();

return [
    'ctrl' => [
        'title' => 'LLL:EXT:schubertgen/Resources/Private/Language/locallang_db.xlf:tx_schubertgen_domain_model_person',
        'label' => 'full_name',
        'label_alt' => 'birth_date_text,death_date_text',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => ['disabled' => 'hidden'],
        'searchFields' => 'full_name,given_name,surname,birth_place,death_place,external_id',
        'iconfile' => 'EXT:schubertgen/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'hidden, external_id, slug, full_name, given_name, surname, gender, birth_date_text, birth_place, death_date_text, death_place, primary_media, primary_image, events, notes'],
    ],
    'columns' => [
        'hidden' => ['label' => 'Hidden', 'config' => ['type' => 'check', 'default' => 0]],
        'external_id' => ['label' => 'GEDCOM ID', 'config' => ['type' => 'input', 'readOnly' => true]],
        'slug' => ['label' => 'Slug', 'config' => ['type' => 'input', 'size' => 50]],
        'full_name' => ['label' => 'Name', 'config' => ['type' => 'input', 'size' => 50]],
        'given_name' => ['label' => 'Given name', 'config' => ['type' => 'input', 'size' => 40]],
        'surname' => ['label' => 'Surname', 'config' => ['type' => 'input', 'size' => 30]],
        'gender' => ['label' => 'Gender', 'config' => ['type' => 'select', 'renderType' => 'selectSingle', 'items' => [['Unknown', ''], ['Male', 'M'], ['Female', 'F']]]],
        'birth_date_text' => ['label' => 'Birth date', 'config' => ['type' => 'input', 'size' => 20]],
        'birth_place' => ['label' => 'Birth place', 'config' => ['type' => 'input', 'size' => 60]],
        'death_date_text' => ['label' => 'Death date', 'config' => ['type' => 'input', 'size' => 20]],
        'death_place' => ['label' => 'Death place', 'config' => ['type' => 'input', 'size' => 60]],
        'primary_media' => ['label' => 'Primary media ID', 'config' => ['type' => 'input', 'size' => 20]],
        'primary_image' => [
            'label' => 'Primary image',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'sys_file_reference',
                'foreign_field' => 'uid_foreign',
                'foreign_sortby' => 'sorting_foreign',
                'foreign_match_fields' => [
                    'fieldname' => 'primary_image',
                    'tablenames' => 'tx_schubertgen_domain_model_person',
                ],
                'minitems' => 0,
                'maxitems' => 1,
                'appearance' => [
                    'collapseAll' => true,
                    'expandSingle' => true,
                ],
            ],
        ],
        'events' => [
            'label' => 'Events',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_schubertgen_domain_model_event',
                'foreign_field' => 'person',
                'appearance' => [
                    'collapseAll' => true,
                    'expandSingle' => true,
                ],
            ],
        ],
        'notes' => ['label' => 'Notes', 'config' => ['type' => 'text', 'rows' => 5]],
    ],
];
