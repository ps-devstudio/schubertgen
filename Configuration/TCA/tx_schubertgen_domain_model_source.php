<?php

declare(strict_types=1);

defined('TYPO3') or die();

return [
    'ctrl' => [
        'title' => 'LLL:EXT:schubertgen/Resources/Private/Language/locallang_db.xlf:tx_schubertgen_domain_model_source',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => ['disabled' => 'hidden'],
        'searchFields' => 'title,reference_title,church,place,url,external_id',
        'iconfile' => 'EXT:schubertgen/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'hidden, external_id, title, reference_title, church, place, url, media_external_ids'],
    ],
    'columns' => [
        'hidden' => ['label' => 'Hidden', 'config' => ['type' => 'check', 'default' => 0]],
        'external_id' => ['label' => 'GEDCOM ID', 'config' => ['type' => 'input', 'readOnly' => true]],
        'title' => ['label' => 'Title', 'config' => ['type' => 'input', 'size' => 60]],
        'reference_title' => ['label' => 'Reference title', 'config' => ['type' => 'input', 'size' => 60]],
        'church' => ['label' => 'Church', 'config' => ['type' => 'input', 'size' => 40]],
        'place' => ['label' => 'Place', 'config' => ['type' => 'input', 'size' => 40]],
        'url' => ['label' => 'URL', 'config' => ['type' => 'link', 'allowedTypes' => ['url']]],
        'media_external_ids' => ['label' => 'Media IDs', 'config' => ['type' => 'text', 'rows' => 3]],
    ],
];
