<?php

declare(strict_types=1);

namespace SchubertliederPlugin\Schubertgen\Upgrades;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[UpgradeWizard('schubertgen_migrate_genealogy_content_elements')]
final class MigrateGenealogyContentElements implements UpgradeWizardInterface
{
    private const LEGACY_LIST_TYPE = 'schubertgen_genealogy';
    private const CONTENT_ELEMENT_TYPE = 'schubertgen_genealogy';

    public function getTitle(): string
    {
        return 'Migrate Schubert Genealogy content elements';
    }

    public function getDescription(): string
    {
        return 'Migrates legacy Schubert Genealogy list plugins to the TYPO3 content element type.';
    }

    public function updateNecessary(): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');

        return (int)$queryBuilder
            ->count('uid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq(
                    'list_type',
                    $queryBuilder->createNamedParameter(self::LEGACY_LIST_TYPE)
                )
            )
            ->executeQuery()
            ->fetchOne() > 0;
    }

    public function executeUpdate(): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');

        $queryBuilder
            ->update('tt_content')
            ->set('CType', self::CONTENT_ELEMENT_TYPE)
            ->set('list_type', '')
            ->where(
                $queryBuilder->expr()->eq(
                    'list_type',
                    $queryBuilder->createNamedParameter(self::LEGACY_LIST_TYPE)
                )
            )
            ->executeStatement();

        return true;
    }

    public function getPrerequisites(): array
    {
        return [];
    }
}
