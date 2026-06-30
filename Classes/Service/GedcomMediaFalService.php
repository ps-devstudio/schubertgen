<?php

declare(strict_types=1);

namespace SchubertliederPlugin\Schubertgen\Service;

use RuntimeException;
use Throwable;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ZipArchive;

final class GedcomMediaFalService
{
    public function __construct(
        private readonly ResourceFactory $resourceFactory,
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    /**
     * @param array<string, array<string, mixed>> $mediaRecords
     * @return array<string, int>
     */
    public function importFromZip(string $zipPath, array $mediaRecords, string $targetFolderIdentifier = '/schubertgen/'): array
    {
        $zipPath = GeneralUtility::getFileAbsFileName($zipPath) ?: $zipPath;
        if (!is_file($zipPath)) {
            return [];
        }
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZipArchive is not available.');
        }

        $storage = $this->resourceFactory->getDefaultStorage();
        $folder = $storage->hasFolder($targetFolderIdentifier)
            ? $storage->getFolder($targetFolderIdentifier)
            : $storage->createFolder(trim($targetFolderIdentifier, '/'), $storage->getRootLevelFolder());

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException(sprintf('Could not open media zip "%s".', $zipPath));
        }

        $temporaryDirectory = Environment::getVarPath() . '/transient/schubertgen-media-' . uniqid('', true);
        GeneralUtility::mkdir_deep($temporaryDirectory);

        $fileUidsByMediaId = [];
        foreach ($mediaRecords as $mediaId => $media) {
            $fileName = (string)($media['file_name'] ?? '');
            if ($fileName === '' || $zip->locateName($fileName) === false) {
                continue;
            }

            $zip->extractTo($temporaryDirectory, $fileName);
            $temporaryFile = $temporaryDirectory . '/' . $fileName;
            if (!is_file($temporaryFile)) {
                continue;
            }

            try {
                $file = $folder->getFile($fileName);
            } catch (Throwable) {
                $file = null;
            }
            if (!is_object($file)) {
                $file = $folder->addFile($temporaryFile, $fileName, DuplicationBehavior::REPLACE);
            }

            if (is_object($file) && method_exists($file, 'getUid')) {
                $fileUidsByMediaId[(string)$mediaId] = (int)$file->getUid();
            }
        }

        $zip->close();
        GeneralUtility::rmdir($temporaryDirectory, true);

        return $fileUidsByMediaId;
    }

    public function createFileReference(int $fileUid, int $recordUid, string $tableName, string $fieldName, int $pid, string $title = ''): void
    {
        if ($fileUid <= 0 || $recordUid <= 0) {
            return;
        }

        $connection = $this->connectionPool->getConnectionForTable('sys_file_reference');
        $connection->insert('sys_file_reference', [
            'pid' => max(0, $pid),
            'uid_local' => $fileUid,
            'uid_foreign' => $recordUid,
            'tablenames' => $tableName,
            'fieldname' => $fieldName,
            'sorting_foreign' => 1,
            'title' => $title,
            'crdate' => time(),
            'tstamp' => time(),
        ]);
    }

    /**
     * @param list<string> $tableNames
     */
    public function deleteReferencesForTables(array $tableNames): void
    {
        if ($tableNames === []) {
            return;
        }

        $connection = $this->connectionPool->getConnectionForTable('sys_file_reference');
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder
            ->delete('sys_file_reference')
            ->where(
                $queryBuilder->expr()->in(
                    'tablenames',
                    $queryBuilder->createNamedParameter($tableNames, \TYPO3\CMS\Core\Database\Connection::PARAM_STR_ARRAY)
                )
            )
            ->executeStatement();
    }
}
