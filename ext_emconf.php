<?php

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title' => 'Schubert Genealogy',
    'description' => 'TYPO3 frontend output and import for the Franz Schubert family tree.',
    'category' => 'plugin',
    'author' => 'Peter Schöne',
    'author_email' => 'mail@ps-devstudio.de',
    'author_company' => 'ps-devstudio',
    'state' => 'beta',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.9.99',
        ],
    ],
];
