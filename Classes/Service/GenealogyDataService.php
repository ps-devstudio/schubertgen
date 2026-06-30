<?php

declare(strict_types=1);

namespace SchubertliederPlugin\Schubertgen\Service;

use Throwable;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\ResourceFactory;

final class GenealogyDataService
{
    private const TABLE_PERSON = 'tx_schubertgen_domain_model_person';
    private const TABLE_FAMILY = 'tx_schubertgen_domain_model_family';
    private const TABLE_FAMILY_CHILD = 'tx_schubertgen_family_child_mm';
    private const TABLE_EVENT = 'tx_schubertgen_domain_model_event';
    private const TABLE_SOURCE = 'tx_schubertgen_domain_model_source';
    private const TABLE_MEDIA = 'tx_schubertgen_domain_model_media';
    private const TABLE_PLACE = 'tx_schubertgen_domain_model_place';
    private const LOCALIZED_PLACE_PARTS = [
        'Austria' => 'Österreich',
        'Australia' => 'Australien',
        'Bohemia' => 'Böhmen',
        'Czechia' => 'Tschechien',
        'Czechoslovakia' => 'Tschechoslowakei',
        'German Empire' => 'Deutsches Reich',
        'Germany' => 'Deutschland',
        'Hungary' => 'Ungarn',
        'Lower Austria' => 'Niederösterreich',
        'Maehren' => 'Mähren',
        'Moravia' => 'Mähren',
        'Prussia' => 'Preußen',
        'Silesia' => 'Schlesien',
        'South Australia' => 'Südaustralien',
        'Vienna' => 'Wien',
    ];
    private const LOCALIZED_PLACE_NAMES = [
        'Alsergrund, Vienna, Austria' => 'Alsergrund, Wien, Österreich',
        'Gumpendorf, Vienna, Austria' => 'Gumpendorf, Wien, Österreich',
        'Lichtental, Vienna, Austria' => 'Lichtental, Wien, Österreich',
        'Rossau, Vienna, Austria' => 'Rossau, Wien, Österreich',
        'Vienna, Austria' => 'Wien, Österreich',
        'Wien, Kath. Pf. St. Josef Margarethen, p. 9' => 'Wien, Kath. Pf. St. Josef Margarethen, S. 9',
        'Ndr. Oesterreich, Austria' => 'Niederösterreich, Österreich',
        'Neisse, Silesia, Prussia, German Empire' => 'Neisse, Schlesien, Preußen, Deutsches Reich',
        'Neudorf, Temes, Hungary' => 'Neudorf, Temes, Ungarn',
        'Zuckmantel, Freiwaldau, Silesia, Austria' => 'Zuckmantel, Freiwaldau, Schlesien, Österreich',
    ];
    private const LOCALIZED_DATE_PREFIXES = [
        'about' => 'um',
        'abt' => 'um',
        'bef' => 'vor',
        'before' => 'vor',
        'aft' => 'nach',
        'after' => 'nach',
        'cal' => 'errechnet',
        'est' => 'geschätzt',
    ];
    private const LOCALIZED_DATE_WORDS = [
        'about' => 'um',
        'before' => 'vor',
        'after' => 'nach',
    ];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ResourceFactory $resourceFactory,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllPersons(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_PERSON);
        $rows = $queryBuilder
            ->select('*')
            ->from(self::TABLE_PERSON)
            ->where($queryBuilder->expr()->eq('deleted', 0))
            ->orderBy('surname')
            ->addOrderBy('given_name')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn (array $person): array => $this->enrichPerson($person), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPersonByExternalId(string $externalId): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_PERSON);
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE_PERSON)
            ->where(
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('external_id', $queryBuilder->createNamedParameter($externalId))
            )
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $this->enrichPerson($row) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findEventsForPerson(string $externalId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_EVENT);
        return $queryBuilder
            ->select('*')
            ->from(self::TABLE_EVENT)
            ->where(
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('parent_type', $queryBuilder->createNamedParameter('person')),
                $queryBuilder->expr()->eq('parent_external_id', $queryBuilder->createNamedParameter($externalId))
            )
            ->orderBy('sort_date')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findFamiliesForPerson(string $externalId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_FAMILY);
        $families = $queryBuilder
            ->select('*')
            ->from(self::TABLE_FAMILY)
            ->where(
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('husband_external_id', $queryBuilder->createNamedParameter($externalId)),
                    $queryBuilder->expr()->eq('wife_external_id', $queryBuilder->createNamedParameter($externalId))
                )
            )
            ->orderBy('marriage_sort_date')
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($families as &$family) {
            $family['partner'] = $this->findPersonByExternalId(
                $family['husband_external_id'] === $externalId ? (string)$family['wife_external_id'] : (string)$family['husband_external_id']
            );
            $family['children'] = $this->findChildrenForFamily((int)$family['uid']);
        }
        unset($family);

        return $families;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findParentFamiliesForPerson(string $externalId): array
    {
        $person = $this->findPersonByExternalId($externalId);
        if ($person === null) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_FAMILY_CHILD);
        $rows = $queryBuilder
            ->select('f.*')
            ->from(self::TABLE_FAMILY_CHILD, 'mm')
            ->join('mm', self::TABLE_FAMILY, 'f', 'f.uid = mm.uid_local')
            ->where(
                $queryBuilder->expr()->eq('mm.uid_foreign', $queryBuilder->createNamedParameter((int)$person['uid'])),
                $queryBuilder->expr()->eq('f.deleted', 0)
            )
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($rows as &$family) {
            $family['husband'] = $this->findPersonByExternalId((string)$family['husband_external_id']);
            $family['wife'] = $this->findPersonByExternalId((string)$family['wife_external_id']);
        }
        unset($family);

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findChildrenForFamily(int $familyUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_FAMILY_CHILD);
        $rows = $queryBuilder
            ->select('p.*')
            ->from(self::TABLE_FAMILY_CHILD, 'mm')
            ->join('mm', self::TABLE_PERSON, 'p', 'p.uid = mm.uid_foreign')
            ->where(
                $queryBuilder->expr()->eq('mm.uid_local', $queryBuilder->createNamedParameter($familyUid)),
                $queryBuilder->expr()->eq('p.deleted', 0)
            )
            ->orderBy('p.birth_sort_date')
            ->addOrderBy('mm.sorting')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn (array $person): array => $this->enrichPerson($person), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findMediaByExternalId(string $externalId): ?array
    {
        if ($externalId === '') {
            return null;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_MEDIA);
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE_MEDIA)
            ->where(
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('external_id', $queryBuilder->createNamedParameter($externalId))
            )
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildMapEvents(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_EVENT);
        $rows = $queryBuilder
            ->selectLiteral(
                'e.uid',
                'e.event_type',
                'e.date_text',
                'e.sort_date',
                'e.place',
                'e.latitude AS event_latitude',
                'e.longitude AS event_longitude',
                'pl.name AS place_name',
                'pl.latitude AS place_latitude',
                'pl.longitude AS place_longitude',
                'p.full_name AS person_name',
                'p.external_id AS person_external_id',
                'hp.full_name AS husband_name',
                'wp.full_name AS wife_name'
            )
            ->from(self::TABLE_EVENT, 'e')
            ->leftJoin('e', self::TABLE_PLACE, 'pl', 'pl.uid = e.place_record')
            ->leftJoin('e', self::TABLE_PERSON, 'p', 'p.uid = e.person')
            ->leftJoin('e', self::TABLE_FAMILY, 'f', 'f.uid = e.family')
            ->leftJoin('f', self::TABLE_PERSON, 'hp', 'hp.uid = f.husband')
            ->leftJoin('f', self::TABLE_PERSON, 'wp', 'wp.uid = f.wife')
            ->where(
                $queryBuilder->expr()->eq('e.deleted', 0),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->neq('e.latitude', $queryBuilder->createNamedParameter(0)),
                    $queryBuilder->expr()->neq('e.longitude', $queryBuilder->createNamedParameter(0)),
                    $queryBuilder->expr()->neq('pl.latitude', $queryBuilder->createNamedParameter(0)),
                    $queryBuilder->expr()->neq('pl.longitude', $queryBuilder->createNamedParameter(0))
                )
            )
            ->orderBy('e.sort_date')
            ->addOrderBy('e.uid')
            ->executeQuery()
            ->fetchAllAssociative();

        $events = [];
        foreach ($rows as $row) {
            $latitude = (float)($row['event_latitude'] ?: $row['place_latitude'] ?: 0);
            $longitude = (float)($row['event_longitude'] ?: $row['place_longitude'] ?: 0);
            if ($latitude === 0.0 && $longitude === 0.0) {
                continue;
            }

            $personName = trim((string)($row['person_name'] ?? ''));
            $familyName = trim(implode(' und ', array_filter([
                (string)($row['husband_name'] ?? ''),
                (string)($row['wife_name'] ?? ''),
            ])));
            $title = $personName !== '' ? $personName : $familyName;
            $place = $this->localizePlace((string)($row['place_name'] ?? $row['place'] ?? ''));

            $events[] = [
                'uid' => (int)$row['uid'],
                'type' => (string)$row['event_type'],
                'typeLabel' => $this->eventLabel((string)$row['event_type']) ?: (string)$row['event_type'],
                'date' => $this->localizeDateText((string)($row['date_text'] ?? '')),
                'sortDate' => (int)($row['sort_date'] ?? 0),
                'place' => $place,
                'title' => $title,
                'personExternalId' => (string)($row['person_external_id'] ?? ''),
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];
        }

        return $events;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buildAncestorTree(string $externalId, int $maxDepth = 4): ?array
    {
        $person = $this->findPersonByExternalId($externalId);
        if ($person === null) {
            return null;
        }

        $node = [
            'person' => $person,
            'parents' => [],
        ];
        if ($maxDepth <= 0) {
            return $node;
        }

        $parentFamilies = $this->findParentFamiliesForPerson($externalId);
        $parentFamily = $parentFamilies[0] ?? null;
        if ($parentFamily === null) {
            return $node;
        }

        foreach (['husband_external_id', 'wife_external_id'] as $fieldName) {
            $parentExternalId = (string)$parentFamily[$fieldName];
            if ($parentExternalId !== '') {
                $parentNode = $this->buildAncestorTree($parentExternalId, $maxDepth - 1);
                if ($parentNode !== null) {
                    $node['parents'][] = $parentNode;
                }
            }
        }

        return $node;
    }

    /**
     * Builds a generation-based chart for D3:
     * focus person and siblings first, then parent couples, then grandparents.
     *
     * @return array<string, mixed>|null
     */
    public function buildAncestorChart(string $externalId, int $maxDepth = 4): ?array
    {
        $focusPerson = $this->findPersonByExternalId($externalId);
        if ($focusPerson === null) {
            return null;
        }

        $parentFamily = $this->findFirstParentFamilyForPerson((int)$focusPerson['uid']);
        $parentUids = $this->familyParentUids($parentFamily);
        $parentFamilies = $parentUids !== [] ? $this->findFamiliesForParentUids($parentUids) : ($parentFamily !== null ? [$parentFamily] : []);
        foreach ($parentFamilies as &$family) {
            $family['children_records'] = $this->findChildrenForFamily((int)$family['uid']);
            $family['child_uids'] = array_map(static fn (array $person): int => (int)$person['uid'], $family['children_records']);
        }
        unset($family);
        $parentFamilies = $this->preferSingleParentFamiliesForDuplicateChildren($parentFamilies);
        $parentFamilies = $this->sortFamiliesByFirstChild($parentFamilies);

        $currentFamilies = array_map(fn (array $family): array => $this->enrichFamilyWithParents($family), $parentFamilies);
        $generations = [[
            'type' => 'childGroups',
            'label' => 'Kinder je Elternfamilie',
            'nodes' => array_map(
                fn (array $family): array => $this->chartChildGroup($family, $externalId),
                $currentFamilies
            ),
        ]];

        if ($parentFamilies === []) {
            $generations[0]['nodes'][] = [
                'type' => 'childGroup',
                'uid' => 0,
                'externalId' => '',
                'label' => 'Startperson',
                'children' => [$this->chartPerson($focusPerson, true)],
            ];
        }

        $seenFamilyUids = [];

        for ($depth = 1; $depth <= $maxDepth && $currentFamilies !== []; ++$depth) {
            $generationNodes = [];
            $nextFamilies = [];

            foreach ($currentFamilies as $family) {
                $familyUid = (int)$family['uid'];
                if (isset($seenFamilyUids[$familyUid])) {
                    continue;
                }
                $seenFamilyUids[$familyUid] = true;

                $generationNodes[] = $this->chartCouple($family, $depth);
                foreach (['husband', 'wife'] as $parentField) {
                    if (!is_array($family[$parentField] ?? null)) {
                        continue;
                    }
                    $parentsParentFamily = $this->findFirstParentFamilyForPerson((int)$family[$parentField]['uid']);
                    if ($parentsParentFamily !== null) {
                        $parentsParentFamilyUid = (int)$parentsParentFamily['uid'];
                        if (!isset($nextFamilies[$parentsParentFamilyUid])) {
                            $parentsParentFamily['child_links'] = [];
                            $nextFamilies[$parentsParentFamilyUid] = $parentsParentFamily;
                        }
                        $nextFamilies[$parentsParentFamilyUid]['child_links'][] = [
                            'childUid' => (int)$family[$parentField]['uid'],
                            'contextFamilyUid' => $familyUid,
                        ];
                    }
                }
            }

            if ($generationNodes !== []) {
                $generations[] = [
                    'type' => 'couples',
                    'label' => $this->generationLabel($depth),
                    'nodes' => $generationNodes,
                ];
            }

            $currentFamilies = array_map(fn (array $family): array => $this->enrichFamilyWithParents($family), array_values($nextFamilies));
        }

        return [
            'focusPerson' => $this->chartPerson($focusPerson, true),
            'generations' => $generations,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildFrontendCharts(string $externalId, int $maxDepth = 4): array
    {
        $focusPerson = $this->findPersonByExternalId($externalId);
        if ($focusPerson === null) {
            return [];
        }

        $parentFamily = $this->findFirstParentFamilyForPerson((int)$focusPerson['uid']);
        if ($parentFamily === null) {
            return array_values(array_filter([
                $this->buildAncestorChart($externalId, $maxDepth),
            ]));
        }

        $parentFamily = $this->withChildren($parentFamily);
        $fatherUid = (int)($parentFamily['husband'] ?? 0);
        $motherUid = (int)($parentFamily['wife'] ?? 0);
        $relatedFamilies = $this->findFamiliesForParentUids(array_values(array_filter([$fatherUid, $motherUid])));
        $fatherFamilies = $fatherUid > 0 ? array_values(array_filter(
            $relatedFamilies,
            static fn (array $family): bool => (int)($family['husband'] ?? 0) === $fatherUid || (int)($family['wife'] ?? 0) === $fatherUid
        )) : [$parentFamily];
        foreach ($relatedFamilies as &$family) {
            $family = $this->withChildren($family);
        }
        unset($family);
        $relatedFamilies = $this->preferSingleParentFamiliesForDuplicateChildren($relatedFamilies);
        $fatherFamilies = array_values(array_filter(
            $relatedFamilies,
            static fn (array $family): bool => (int)($family['husband'] ?? 0) === $fatherUid || (int)($family['wife'] ?? 0) === $fatherUid
        ));
        $fatherFamilies = $this->sortFamiliesByFirstChild($fatherFamilies);

        $charts = [];
        $charts[] = $this->buildChartFromFamilies(
            [$this->withChildren($parentFamily, [$focusPerson])],
            'Franz Peter Schubert mit Vorfahren',
            $externalId,
            $maxDepth
        );

        foreach ($fatherFamilies as $family) {
            $chart = $this->buildChartFromFamilies(
                [$family],
                $this->familyChartTitle($family),
                $externalId,
                $maxDepth
            );
            if ($chart !== null) {
                $charts[] = $chart;
            }
        }

        return array_values(array_filter($charts));
    }

    /**
     * @param list<array<string, mixed>> $families
     * @return array<string, mixed>|null
     */
    private function buildChartFromFamilies(array $families, string $title, string $focusExternalId, int $maxDepth): ?array
    {
        if ($families === []) {
            return null;
        }

        $currentFamilies = array_map(fn (array $family): array => $this->enrichFamilyWithParents($family), $families);
        $generations = [[
            'type' => 'childGroups',
            'label' => 'Kinder',
            'nodes' => array_map(
                fn (array $family): array => $this->chartChildGroup($family, $focusExternalId),
                $currentFamilies
            ),
        ]];
        $seenFamilyUids = [];
        $focusPerson = $this->findPersonByExternalId($focusExternalId);

        for ($depth = 1; $depth <= $maxDepth && $currentFamilies !== []; ++$depth) {
            $generationNodes = [];
            $nextFamilies = [];

            foreach ($currentFamilies as $family) {
                $familyUid = (int)$family['uid'];
                if (!isset($seenFamilyUids[$familyUid])) {
                    $seenFamilyUids[$familyUid] = true;
                    $generationNodes[] = $this->chartCouple($family, $depth);
                }

                foreach (['husband', 'wife'] as $parentField) {
                    if (!is_array($family[$parentField] ?? null)) {
                        continue;
                    }
                    $parentsParentFamily = $this->findFirstParentFamilyForPerson((int)$family[$parentField]['uid']);
                    if ($parentsParentFamily !== null) {
                        $parentsParentFamilyUid = (int)$parentsParentFamily['uid'];
                        if (!isset($nextFamilies[$parentsParentFamilyUid])) {
                            $parentsParentFamily['child_links'] = [];
                            $nextFamilies[$parentsParentFamilyUid] = $parentsParentFamily;
                        }
                        $nextFamilies[$parentsParentFamilyUid]['child_links'][] = [
                            'childUid' => (int)$family[$parentField]['uid'],
                            'contextFamilyUid' => $familyUid,
                        ];
                    }
                }
            }

            if ($generationNodes !== []) {
                $generations[] = [
                    'type' => 'couples',
                    'label' => $this->generationLabel($depth),
                    'nodes' => $generationNodes,
                ];
            }

            $currentFamilies = array_map(fn (array $family): array => $this->enrichFamilyWithParents($family), array_values($nextFamilies));
        }

        return [
            'title' => $title,
            'focusPerson' => is_array($focusPerson) ? $this->chartPerson($focusPerson, true) : null,
            'generations' => $generations,
        ];
    }

    /**
     * @param array<string, mixed> $person
     * @return array<string, mixed>
     */
    private function enrichPerson(array $person): array
    {
        $person['primaryImage'] = $this->findFileReference(
            self::TABLE_PERSON,
            'primary_image',
            (int)($person['uid'] ?? 0)
        );

        return $person;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findFirstParentFamilyForPerson(int $personUid): ?array
    {
        if ($personUid <= 0) {
            return null;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_FAMILY_CHILD);
        $family = $queryBuilder
            ->select('f.*')
            ->from(self::TABLE_FAMILY_CHILD, 'mm')
            ->join('mm', self::TABLE_FAMILY, 'f', 'f.uid = mm.uid_local')
            ->where(
                $queryBuilder->expr()->eq('mm.uid_foreign', $queryBuilder->createNamedParameter($personUid)),
                $queryBuilder->expr()->eq('f.deleted', 0)
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($family) ? $family : null;
    }

    /**
     * @param array<string, mixed>|null $family
     * @return list<int>
     */
    private function familyParentUids(?array $family): array
    {
        if ($family === null) {
            return [];
        }

        return array_values(array_filter([
            (int)($family['husband'] ?? 0),
            (int)($family['wife'] ?? 0),
        ], static fn (int $uid): bool => $uid > 0));
    }

    /**
     * @param list<int> $parentUids
     * @return list<array<string, mixed>>
     */
    private function findFamiliesForParentUids(array $parentUids): array
    {
        $parentUids = array_values(array_unique(array_filter($parentUids, static fn (int $uid): bool => $uid > 0)));
        if ($parentUids === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_FAMILY);
        $orConditions = [];
        foreach ($parentUids as $parentUid) {
            $orConditions[] = $queryBuilder->expr()->eq('husband', $queryBuilder->createNamedParameter($parentUid));
            $orConditions[] = $queryBuilder->expr()->eq('wife', $queryBuilder->createNamedParameter($parentUid));
        }

        return $queryBuilder
            ->select('*')
            ->from(self::TABLE_FAMILY)
            ->where(
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->or(...$orConditions)
            )
            ->orderBy('marriage_sort_date')
            ->addOrderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * If a child exists in a single-parent family and in a two-parent family,
     * the single-parent relation is visually more precise for uncertain parentage.
     *
     * @param list<array<string, mixed>> $families
     * @return list<array<string, mixed>>
     */
    private function preferSingleParentFamiliesForDuplicateChildren(array $families): array
    {
        $preferredFamilyByChildUid = [];
        foreach ($families as $family) {
            $isSingleParent = (int)($family['husband'] ?? 0) === 0 || (int)($family['wife'] ?? 0) === 0;
            foreach ((array)($family['children_records'] ?? []) as $child) {
                $childUid = (int)$child['uid'];
                if ($childUid <= 0) {
                    continue;
                }
                if (!isset($preferredFamilyByChildUid[$childUid]) || $isSingleParent) {
                    $preferredFamilyByChildUid[$childUid] = (int)$family['uid'];
                }
            }
        }

        foreach ($families as &$family) {
            $familyUid = (int)$family['uid'];
            $family['children_records'] = array_values(array_filter(
                (array)($family['children_records'] ?? []),
                static fn (array $child): bool => ($preferredFamilyByChildUid[(int)$child['uid']] ?? $familyUid) === $familyUid
            ));
            $family['child_uids'] = array_map(static fn (array $person): int => (int)$person['uid'], $family['children_records']);
        }
        unset($family);

        return array_values(array_filter(
            $families,
            static fn (array $family): bool => ((array)($family['children_records'] ?? [])) !== []
        ));
    }

    /**
     * @param list<array<string, mixed>> $families
     * @return list<array<string, mixed>>
     */
    private function sortFamiliesByFirstChild(array $families): array
    {
        usort($families, static function (array $first, array $second): int {
            $firstChildren = (array)($first['children_records'] ?? []);
            $secondChildren = (array)($second['children_records'] ?? []);
            $firstSort = (int)($firstChildren[0]['birth_sort_date'] ?? 0);
            $secondSort = (int)($secondChildren[0]['birth_sort_date'] ?? 0);
            if ($firstSort !== $secondSort) {
                return ($firstSort ?: PHP_INT_MAX) <=> ($secondSort ?: PHP_INT_MAX);
            }

            return (int)$first['uid'] <=> (int)$second['uid'];
        });

        return $families;
    }

    /**
     * @param array<string, mixed> $family
     * @param list<array<string, mixed>>|null $children
     * @return array<string, mixed>
     */
    private function withChildren(array $family, ?array $children = null): array
    {
        $family['children_records'] = $children ?? $this->findChildrenForFamily((int)$family['uid']);
        $family['child_uids'] = array_map(static fn (array $person): int => (int)$person['uid'], $family['children_records']);

        return $family;
    }

    /**
     * @param array<string, mixed> $family
     */
    private function familyChartTitle(array $family): string
    {
        $parents = [];
        if ((int)($family['husband'] ?? 0) > 0) {
            $husband = $this->findPersonByExternalId((string)($family['husband_external_id'] ?? ''));
            if (is_array($husband)) {
                $parents[] = (string)$husband['full_name'];
            }
        }
        if ((int)($family['wife'] ?? 0) > 0) {
            $wife = $this->findPersonByExternalId((string)($family['wife_external_id'] ?? ''));
            if (is_array($wife)) {
                $parents[] = (string)$wife['full_name'];
            }
        }

        return 'Kinder von ' . implode(' und ', $parents) . ' mit Vorfahren';
    }

    /**
     * @param array<string, mixed> $family
     * @return array<string, mixed>
     */
    private function enrichFamilyWithParents(array $family): array
    {
        $family['husband'] = $this->findPersonByExternalId((string)$family['husband_external_id']);
        $family['wife'] = $this->findPersonByExternalId((string)$family['wife_external_id']);
        $family['events'] = $this->findEventsForFamily((int)$family['uid']);

        return $family;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findEventsForFamily(int $familyUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_EVENT);
        return $queryBuilder
            ->select('e.*', 'pl.name AS place_name')
            ->from(self::TABLE_EVENT, 'e')
            ->leftJoin('e', self::TABLE_PLACE, 'pl', 'pl.uid = e.place_record')
            ->where(
                $queryBuilder->expr()->eq('e.deleted', 0),
                $queryBuilder->expr()->eq('e.family', $queryBuilder->createNamedParameter($familyUid))
            )
            ->orderBy('e.sort_date')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findEventsForPersonWithPlaces(string $externalId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_EVENT);
        return $queryBuilder
            ->select('e.*', 'pl.name AS place_name')
            ->from(self::TABLE_EVENT, 'e')
            ->leftJoin('e', self::TABLE_PLACE, 'pl', 'pl.uid = e.place_record')
            ->where(
                $queryBuilder->expr()->eq('e.deleted', 0),
                $queryBuilder->expr()->eq('e.parent_type', $queryBuilder->createNamedParameter('person')),
                $queryBuilder->expr()->eq('e.parent_external_id', $queryBuilder->createNamedParameter($externalId))
            )
            ->orderBy('e.sort_date')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param list<array<string, mixed>> $events
     * @return list<array{label:string,value:string}>
     */
    private function chartEventDetails(array $events): array
    {
        $details = [];
        foreach ($events as $event) {
            $label = $this->eventLabel((string)($event['event_type'] ?? ''));
            if ($label === '') {
                continue;
            }

            $value = $this->localizeDateText((string)($event['date_text'] ?? ''));
            $place = $this->localizePlace((string)($event['place_name'] ?? $event['place'] ?? ''));
            if ($place !== '') {
                $value = trim($value . "\n" . $place);
            }
            if ($value === '') {
                continue;
            }

            $details[] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        return $details;
    }

    /**
     * @param array<string, mixed> $person
     * @return list<array{label:string,value:string}>
     */
    private function chartMarriageDetails(array $person): array
    {
        $details = [];
        foreach ($this->findFamiliesForPerson((string)$person['external_id']) as $family) {
            $partner = is_array($family['partner'] ?? null) ? (string)$family['partner']['full_name'] : '';
            if ($partner === '') {
                continue;
            }
            $value = $this->localizeDateText((string)($family['marriage_date_text'] ?? ''));
            if ($partner !== '') {
                $value = trim($value . "\n" . $partner);
            }
            $place = $this->localizePlace((string)($family['marriage_place'] ?? ''));
            if ($place !== '') {
                $value = trim($value . "\n" . $place);
            }
            if ($value !== '') {
                $details[] = [
                    'label' => 'Heirat',
                    'value' => $value,
                ];
            }
        }

        return $details;
    }

    private function eventLabel(string $eventType): string
    {
        return match ($eventType) {
            'birth' => 'Geburt',
            'christening' => 'Taufe',
            'marriage' => 'Heirat',
            'death' => 'Tod',
            'burial' => 'Begräbnis',
            default => '',
        };
    }

    private function localizePlace(string $place): string
    {
        $place = trim($place);
        if ($place === '') {
            return '';
        }

        if (isset(self::LOCALIZED_PLACE_NAMES[$place])) {
            return self::LOCALIZED_PLACE_NAMES[$place];
        }

        $parts = array_map('trim', explode(',', $place));
        $parts = array_map(static function (string $part): string {
            return self::LOCALIZED_PLACE_PARTS[$part] ?? $part;
        }, $parts);

        return implode(', ', array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    private function localizeDateText(string $dateText): string
    {
        $dateText = trim($dateText);
        if ($dateText === '') {
            return '';
        }

        if (preg_match('/^([A-Za-z]+)\s+(.+)$/', $dateText, $match)) {
            $prefix = strtolower($match[1]);
            if (isset(self::LOCALIZED_DATE_PREFIXES[$prefix])) {
                return self::LOCALIZED_DATE_PREFIXES[$prefix] . ' ' . trim($match[2]);
            }
        }

        return preg_replace_callback('/\b(about|before|after)\b/i', static function (array $match): string {
            return self::LOCALIZED_DATE_WORDS[strtolower($match[1])] ?? $match[1];
        }, $dateText) ?? $dateText;
    }

    /**
     * @param array<string, mixed> $person
     * @return array<string, mixed>
     */
    private function chartPerson(array $person, bool $focus = false): array
    {
        $events = $this->findEventsForPersonWithPlaces((string)$person['external_id']);
        $details = array_merge(
            $this->chartEventDetails($events),
            $this->chartMarriageDetails($person)
        );

        return [
            'type' => 'person',
            'uid' => (int)$person['uid'],
            'externalId' => (string)$person['external_id'],
            'name' => (string)$person['full_name'],
            'gender' => (string)$person['gender'],
            'birthDate' => $this->localizeDateText((string)$person['birth_date_text']),
            'birthPlace' => $this->localizePlace((string)$person['birth_place']),
            'deathDate' => $this->localizeDateText((string)$person['death_date_text']),
            'deathPlace' => $this->localizePlace((string)$person['death_place']),
            'image' => is_array($person['primaryImage'] ?? null) ? (string)$person['primaryImage']['publicUrl'] : '',
            'details' => array_slice($details, 0, 5),
            'focus' => $focus,
        ];
    }

    /**
     * @param array<string, mixed> $family
     * @return array<string, mixed>
     */
    private function chartCouple(array $family, int $depth): array
    {
        $marriage = '';
        foreach ($family['events'] ?? [] as $event) {
            if (($event['event_type'] ?? '') === 'marriage') {
                $marriage = trim($this->localizeDateText((string)($event['date_text'] ?? '')) . ' ' . $this->localizePlace((string)($event['place_name'] ?? '')));
                break;
            }
        }

        return [
            'type' => 'couple',
            'uid' => (int)$family['uid'],
            'externalId' => (string)$family['external_id'],
            'depth' => $depth,
            'label' => $marriage,
            'childUids' => array_values(array_map('intval', (array)($family['child_uids'] ?? []))),
            'childLinks' => array_values(array_map(
                static fn (array $link): array => [
                    'childUid' => (int)($link['childUid'] ?? 0),
                    'contextFamilyUid' => (int)($link['contextFamilyUid'] ?? 0),
                ],
                (array)($family['child_links'] ?? [])
            )),
            'husband' => is_array($family['husband'] ?? null) ? $this->chartPerson($family['husband']) : null,
            'wife' => is_array($family['wife'] ?? null) ? $this->chartPerson($family['wife']) : null,
        ];
    }

    /**
     * @param array<string, mixed> $family
     * @return array<string, mixed>
     */
    private function chartChildGroup(array $family, string $focusExternalId): array
    {
        $children = (array)($family['children_records'] ?? []);
        $parents = array_filter([
            is_array($family['husband'] ?? null) ? (string)$family['husband']['full_name'] : '',
            is_array($family['wife'] ?? null) ? (string)$family['wife']['full_name'] : '',
        ]);

        return [
            'type' => 'childGroup',
            'uid' => (int)$family['uid'],
            'externalId' => (string)$family['external_id'],
            'label' => implode(' + ', $parents),
            'children' => array_map(
                fn (array $person): array => $this->chartPerson($person, (string)$person['external_id'] === $focusExternalId),
                $children
            ),
        ];
    }

    private function generationLabel(int $depth): string
    {
        return match ($depth) {
            1 => 'Eltern',
            2 => 'Grosseltern',
            3 => 'Urgrosseltern',
            4 => 'Ururgrosseltern',
            default => 'Generation ' . $depth,
        };
    }

    /**
     * @return array{uid:int, publicUrl:string, title:string, alternative:string}|null
     */
    private function findFileReference(string $tableName, string $fieldName, int $recordUid): ?array
    {
        if ($recordUid <= 0) {
            return null;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $reference = $queryBuilder
            ->select('uid', 'uid_local', 'title', 'alternative')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('hidden', 0),
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($recordUid)),
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter($tableName)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter($fieldName))
            )
            ->orderBy('sorting_foreign')
            ->executeQuery()
            ->fetchAssociative();

        if (!is_array($reference)) {
            return null;
        }

        try {
            $file = $this->resourceFactory->getFileObject((int)$reference['uid_local']);
            $publicUrl = (string)$file->getPublicUrl();
        } catch (Throwable) {
            return null;
        }

        if ($publicUrl === '') {
            return null;
        }

        return [
            'uid' => (int)$reference['uid'],
            'publicUrl' => $publicUrl,
            'title' => (string)($reference['title'] ?? ''),
            'alternative' => (string)($reference['alternative'] ?? ''),
        ];
    }
}
