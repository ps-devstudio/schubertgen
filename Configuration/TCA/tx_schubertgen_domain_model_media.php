<?php

declare(strict_types=1);

defined('TYPO3') or die();

return [
    'ctrl' => [
        'title' => 'LLL:EXT:schubertgen/Resources/Private/Language/locallang_db.xlf:tx_schubertgen_domain_model_media',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => ['disabled' => 'hidden'],
        'searchFields' => 'title,file_name,external_id',
        'iconfile' => 'EXT:schubertgen/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'hidden, external_id, title, file_name, file_extension, file'],
    ],
    'columns' => [
        'hidden' => ['label' => 'Hidden', 'config' => ['type' => 'check', 'default' => 0]],
        'external_id' => ['label' => 'GEDCOM ID', 'config' => ['type' => 'input', 'readOnly' => true]],
        'title' => ['label' => 'Title', 'config' => ['type' => 'input', 'size' => 60]],
        'file_name' => ['label' => 'File name', 'config' => ['type' => 'input', 'size' => 60]],
        'file_extension' => ['label' => 'File extension', 'config' => ['type' => 'input', 'size' => 10]],
        'file' => [
            'label' => 'File',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'sys_file_reference',
                'foreign_field' => 'uid_foreign',
                'foreign_sortby' => 'sorting_foreign',
                'foreign_match_fields' => [
                    'fieldname' => 'file',
                    'tablenames' => 'tx_schubertgen_domain_model_media',
                ],
                'minitems' => 0,
                'maxitems' => 1,
                'appearance' => [
                    'collapseAll' => true,
                    'expandSingle' => true,
                ],
            ],
        ],
    ],
];
