<?php

declare(strict_types=1);

namespace SchubertliederPlugin\Schubertgen\Command;

use SchubertliederPlugin\Schubertgen\Service\GedcomParser;
use SchubertliederPlugin\Schubertgen\Service\GedcomMediaFalService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class ImportGedcomCommand extends Command
{
    private const TABLE_PERSON = 'tx_schubertgen_domain_model_person';
    private const TABLE_FAMILY = 'tx_schubertgen_domain_model_family';
    private const TABLE_FAMILY_CHILD = 'tx_schubertgen_family_child_mm';
    private const TABLE_EVENT = 'tx_schubertgen_domain_model_event';
    private const TABLE_PLACE = 'tx_schubertgen_domain_model_place';
    private const TABLE_SOURCE = 'tx_schubertgen_domain_model_source';
    private const TABLE_MEDIA = 'tx_schubertgen_domain_model_media';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly GedcomParser $gedcomParser,
        private readonly GedcomMediaFalService $gedcomMediaFalService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'GEDCOM file path', 'EXT:schubertgen/Resources/Private/Import/franz-schubert.ged')
            ->addOption('media-zip', null, InputOption::VALUE_REQUIRED, 'GedZip file containing exported media', 'EXT:schubertgen/Franz Schubert.zip')
            ->addOption('media-folder', null, InputOption::VALUE_REQUIRED, 'Target folder in the default FAL storage', '/schubertgen/')
            ->addOption('skip-media', null, InputOption::VALUE_NONE, 'Skip FAL media import')
            ->addOption('pid', null, InputOption::VALUE_REQUIRED, 'Storage PID for imported records', '0')
            ->addOption('replace', null, InputOption::VALUE_NONE, 'Delete existing genealogy records before import');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = GeneralUtility::getFileAbsFileName((string)$input->getOption('file'));
        if ($filePath === '' || !is_file($filePath)) {
            $output->writeln('<error>GEDCOM file not found.</error>');
            return Command::FAILURE;
        }

        $pid = (int)$input->getOption('pid');
        if ((bool)$input->getOption('replace')) {
            $this->clearTables();
        }

        $data = $this->gedcomParser->parse($filePath);
        foreach ($data['persons'] as $personKey => $person) {
            $data['persons'][$personKey] = $this->applyImportCorrections($person);
        }

        $now = time();
        $fileUidsByMediaId = [];
        if (!(bool)$input->getOption('skip-media')) {
            $output->writeln(sprintf(
                '<comment>Importing %d media files from %s</comment>',
                count($data['media']),
                (string)$input->getOption('media-zip')
            ));
            $fileUidsByMediaId = $this->gedcomMediaFalService->importFromZip(
                (string)$input->getOption('media-zip'),
                $data['media'],
                (string)$input->getOption('media-folder')
            );
            $output->writeln(sprintf('<comment>Imported %d files into FAL.</comment>', count($fileUidsByMediaId)));
        }

        foreach ($data['media'] as $media) {
            $mediaUid = $this->insert(self::TABLE_MEDIA, $this->baseRow($pid, $now) + [
                'external_id' => $media['external_id'],
                'title' => $media['title'],
                'file_name' => $media['file_name'],
                'file_extension' => $media['file_extension'],
                'file' => isset($fileUidsByMediaId[(string)$media['external_id']]) ? 1 : 0,
                'raw_gedcom' => $media['raw_gedcom'],
            ]);
            $fileUid = $fileUidsByMediaId[(string)$media['external_id']] ?? 0;
            if ($fileUid > 0) {
                $this->gedcomMediaFalService->createFileReference(
                    $fileUid,
                    $mediaUid,
                    self::TABLE_MEDIA,
                    'file',
                    $pid,
                    (string)$media['title']
                );
            }
        }

        $sourceUidsByExternalId = [];
        foreach ($data['sources'] as $source) {
            $sourceUidsByExternalId[(string)$source['external_id']] = $this->insert(self::TABLE_SOURCE, $this->baseRow($pid, $now) + [
                'external_id' => $source['external_id'],
                'title' => $source['title'],
                'reference_title' => $source['reference_title'],
                'church' => $source['church'],
                'place' => $source['place'],
                'url' => $source['url'],
                'media_external_ids' => $source['media_external_ids'],
                'raw_gedcom' => $source['raw_gedcom'],
            ]);
        }

        $personUidsByExternalId = [];
        foreach ($data['persons'] as $person) {
            $primaryMediaId = (string)$person['primary_media'];
            $primaryImageFileUid = $fileUidsByMediaId[$primaryMediaId] ?? 0;
            $personUid = $this->insert(self::TABLE_PERSON, $this->baseRow($pid, $now) + [
                'external_id' => $person['external_id'],
                'slug' => $this->slug((string)$person['full_name'], (string)$person['external_id']),
                'full_name' => $person['full_name'],
                'given_name' => $person['given_name'],
                'surname' => $person['surname'],
                'gender' => $person['gender'],
                'birth_date_text' => $person['birth_date_text'],
                'birth_sort_date' => $person['birth_sort_date'],
                'birth_place' => $person['birth_place'],
                'death_date_text' => $person['death_date_text'],
                'death_sort_date' => $person['death_sort_date'],
                'death_place' => $person['death_place'],
                'primary_media' => $person['primary_media'],
                'primary_image' => $primaryImageFileUid > 0 ? 1 : 0,
                'notes' => '',
                'raw_gedcom' => $person['raw_gedcom'],
            ]);
            $personUidsByExternalId[(string)$person['external_id']] = $personUid;
            if ($primaryImageFileUid > 0) {
                $this->gedcomMediaFalService->createFileReference(
                    $primaryImageFileUid,
                    $personUid,
                    self::TABLE_PERSON,
                    'primary_image',
                    $pid,
                    (string)$person['full_name']
                );
            }
        }

        $familyUidsByExternalId = [];
        foreach ($data['families'] as $family) {
            $familyUid = $this->insert(self::TABLE_FAMILY, $this->baseRow($pid, $now) + [
                'external_id' => $family['external_id'],
                'husband_external_id' => $family['husband_external_id'],
                'wife_external_id' => $family['wife_external_id'],
                'husband' => $personUidsByExternalId[(string)$family['husband_external_id']] ?? 0,
                'wife' => $personUidsByExternalId[(string)$family['wife_external_id']] ?? 0,
                'marriage_date_text' => $family['marriage_date_text'],
                'marriage_sort_date' => $family['marriage_sort_date'],
                'marriage_place' => $family['marriage_place'],
                'raw_gedcom' => $family['raw_gedcom'],
            ]);
            $familyUidsByExternalId[(string)$family['external_id']] = $familyUid;

            $sorting = 0;
            foreach ($family['children'] as $childExternalId) {
                $childUid = $personUidsByExternalId[(string)$childExternalId] ?? 0;
                if ($childUid > 0) {
                    $this->insert(self::TABLE_FAMILY_CHILD, [
                        'uid_local' => $familyUid,
                        'uid_foreign' => $childUid,
                        'sorting' => ++$sorting,
                    ]);
                }
            }
        }

        $placeUidsByExternalId = [];
        $personEventCounts = array_fill_keys(array_values($personUidsByExternalId), 0);
        $familyEventCounts = array_fill_keys(array_values($familyUidsByExternalId), 0);

        foreach ($data['persons'] as $person) {
            $personUid = $personUidsByExternalId[(string)$person['external_id']] ?? 0;
            if ($personUid === 0) {
                continue;
            }

            foreach ($person['events'] as $event) {
                $this->insertEvent(
                    $event,
                    $pid,
                    $now,
                    $personUid,
                    0,
                    $sourceUidsByExternalId,
                    $placeUidsByExternalId
                );
                ++$personEventCounts[$personUid];
            }
        }

        foreach ($data['families'] as $family) {
            $familyUid = $familyUidsByExternalId[(string)$family['external_id']] ?? 0;
            if ($familyUid === 0) {
                continue;
            }

            foreach ($family['events'] as $event) {
                $this->insertEvent(
                    $event,
                    $pid,
                    $now,
                    0,
                    $familyUid,
                    $sourceUidsByExternalId,
                    $placeUidsByExternalId
                );
                ++$familyEventCounts[$familyUid];
            }
        }

        $this->updateRelationCounts(self::TABLE_PERSON, 'events', $personEventCounts);
        $this->updateRelationCounts(self::TABLE_FAMILY, 'events', $familyEventCounts);
        $this->updateRelationCounts(self::TABLE_FAMILY, 'children', $this->familyChildCounts($familyUidsByExternalId, $data['families'], $personUidsByExternalId));

        $output->writeln(sprintf(
            '<info>Imported %d persons, %d families, %d sources, %d media records and %d FAL files.</info>',
            count($data['persons']),
            count($data['families']),
            count($data['sources']),
            count($data['media']),
            count($fileUidsByMediaId)
        ));

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $person
     * @return array<string, mixed>
     */
    private function applyImportCorrections(array $person): array
    {
        if (!$this->isFranzPeterSchubert($person)) {
            return $person;
        }

        $person['birth_place'] = 'Lichtental, Vienna, Austria';
        foreach ($person['events'] as &$event) {
            if (!in_array((string)($event['event_type'] ?? ''), ['birth', 'christening'], true)) {
                continue;
            }

            $event['place'] = 'Lichtental, Vienna, Austria';
            $event['place_original'] = 'Lichtental, , Vienna, Austria';
            $event['latitude'] = 48.228056;
            $event['longitude'] = 16.357222;
        }
        unset($event);

        return $person;
    }

    /**
     * @param array<string, mixed> $person
     */
    private function isFranzPeterSchubert(array $person): bool
    {
        if ((string)($person['external_id'] ?? '') === '96255408') {
            return true;
        }

        return (string)($person['full_name'] ?? '') === 'Franz Peter Schubert'
            && (string)($person['birth_date_text'] ?? '') === '31.01.1797';
    }

    private function clearTables(): void
    {
        $this->gedcomMediaFalService->deleteReferencesForTables([self::TABLE_PERSON, self::TABLE_MEDIA]);
        foreach ([self::TABLE_FAMILY_CHILD, self::TABLE_EVENT, self::TABLE_PLACE, self::TABLE_FAMILY, self::TABLE_PERSON, self::TABLE_SOURCE, self::TABLE_MEDIA] as $table) {
            $connection = $this->connectionPool->getConnectionForTable($table);
            $connection->truncate($table);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insert(string $table, array $row): int
    {
        $connection = $this->connectionPool->getConnectionForTable($table);
        $connection->insert($table, $row);
        return (int)$connection->lastInsertId();
    }

    /**
     * @return array<string, int>
     */
    private function baseRow(int $pid, int $now): array
    {
        return [
            'pid' => $pid,
            'tstamp' => $now,
            'crdate' => $now,
        ];
    }

    /**
     * @param array<string, mixed> $event
     * @param array<string, int> $sourceUidsByExternalId
     * @param array<string, int> $placeUidsByExternalId
     */
    private function insertEvent(
        array $event,
        int $pid,
        int $now,
        int $personUid,
        int $familyUid,
        array $sourceUidsByExternalId,
        array &$placeUidsByExternalId
    ): int {
        $placeUid = $this->placeUidForEvent($event, $pid, $now, $placeUidsByExternalId);
        $externalId = sprintf(
            '%s-%s-%s',
            (string)$event['parent_type'],
            (string)$event['parent_external_id'],
            (string)$event['event_type']
        );

        unset($event['place_original']);

        return $this->insert(self::TABLE_EVENT, $this->baseRow($pid, $now) + $event + [
            'external_id' => $externalId,
            'person' => $personUid,
            'family' => $familyUid,
            'place_record' => $placeUid,
            'source' => $sourceUidsByExternalId[(string)$event['source_external_id']] ?? 0,
        ]);
    }

    /**
     * @param array<string, mixed> $event
     * @param array<string, int> $placeUidsByExternalId
     */
    private function placeUidForEvent(array $event, int $pid, int $now, array &$placeUidsByExternalId): int
    {
        $name = trim((string)($event['place'] ?? ''));
        if ($name === '') {
            return 0;
        }

        $externalId = 'place-' . substr(sha1(strtolower($name) . '|' . (string)($event['latitude'] ?? 0) . '|' . (string)($event['longitude'] ?? 0)), 0, 32);
        if (isset($placeUidsByExternalId[$externalId])) {
            return $placeUidsByExternalId[$externalId];
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', (string)($event['place_original'] ?? $name))), static fn (string $part): bool => $part !== ''));
        $placeUidsByExternalId[$externalId] = $this->insert(self::TABLE_PLACE, $this->baseRow($pid, $now) + [
            'external_id' => $externalId,
            'name' => $name,
            'original_name' => (string)($event['place_original'] ?? $name),
            'city' => $parts[0] ?? '',
            'county' => $parts[1] ?? '',
            'state' => $parts[2] ?? '',
            'country' => $parts[count($parts) - 1] ?? '',
            'latitude' => (float)($event['latitude'] ?? 0),
            'longitude' => (float)($event['longitude'] ?? 0),
        ]);

        return $placeUidsByExternalId[$externalId];
    }

    /**
     * @param array<int, int> $counts
     */
    private function updateRelationCounts(string $table, string $field, array $counts): void
    {
        $connection = $this->connectionPool->getConnectionForTable($table);
        foreach ($counts as $uid => $count) {
            $connection->update($table, [$field => $count], ['uid' => $uid]);
        }
    }

    /**
     * @param array<string, int> $familyUidsByExternalId
     * @param array<string, array<string, mixed>> $families
     * @param array<string, int> $personUidsByExternalId
     * @return array<int, int>
     */
    private function familyChildCounts(array $familyUidsByExternalId, array $families, array $personUidsByExternalId): array
    {
        $counts = [];
        foreach ($families as $family) {
            $familyUid = $familyUidsByExternalId[(string)$family['external_id']] ?? 0;
            if ($familyUid === 0) {
                continue;
            }

            $counts[$familyUid] = 0;
            foreach ($family['children'] as $childExternalId) {
                if (($personUidsByExternalId[(string)$childExternalId] ?? 0) > 0) {
                    ++$counts[$familyUid];
                }
            }
        }

        return $counts;
    }

    private function slug(string $name, string $externalId): string
    {
        $slug = strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name), '-'));
        return ($slug !== '' ? $slug : 'person') . '-' . $externalId;
    }
}
