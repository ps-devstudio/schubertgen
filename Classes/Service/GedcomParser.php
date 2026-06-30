<?php

declare(strict_types=1);

namespace SchubertliederPlugin\Schubertgen\Service;

final class GedcomParser
{
    /**
     * @return array{persons: array<string, array<string, mixed>>, families: array<string, array<string, mixed>>, sources: array<string, array<string, mixed>>, media: array<string, array<string, mixed>>}
     */
    public function parse(string $filePath): array
    {
        $content = (string)file_get_contents($filePath);
        $blocks = preg_split('/\R(?=0 @)/u', trim($content)) ?: [];
        $result = [
            'persons' => [],
            'families' => [],
            'sources' => [],
            'media' => [],
        ];

        foreach ($blocks as $block) {
            $lines = preg_split('/\R/u', trim($block)) ?: [];
            if ($lines === [] || !preg_match('/^0 @([^@]+)@ ([A-Z_]+)/', $lines[0], $match)) {
                continue;
            }

            $id = $match[1];
            $type = $match[2];
            if ($type === 'INDI') {
                $result['persons'][$id] = $this->parsePerson($id, $lines);
            } elseif ($type === 'FAM') {
                $result['families'][$id] = $this->parseFamily($id, $lines);
            } elseif ($type === 'SOUR') {
                $result['sources'][$id] = $this->parseSource($id, $lines);
            } elseif ($type === 'OBJE') {
                $result['media'][$id] = $this->parseMedia($id, $lines);
            }
        }

        return $result;
    }

    /**
     * @param list<string> $lines
     * @return array<string, mixed>
     */
    private function parsePerson(string $id, array $lines): array
    {
        $name = $this->firstLevelValue($lines, 'NAME');
        $birth = $this->subRecord($lines, 'BIRT');
        $death = $this->subRecord($lines, 'DEAT');

        return [
            'external_id' => $id,
            'full_name' => $this->normalizeName($name),
            'given_name' => $this->firstLevelValue($lines, 'GIVN') ?: $this->extractGivenName($name),
            'surname' => $this->firstLevelValue($lines, 'SURN') ?: $this->extractSurname($name),
            'gender' => $this->firstLevelValue($lines, 'SEX'),
            'birth_date_text' => (string)($birth['DATE'] ?? ''),
            'birth_sort_date' => $this->sortDate((string)($birth['DATE'] ?? '')),
            'birth_place' => $this->normalizePlace((string)($birth['PLAC'] ?? '')),
            'death_date_text' => (string)($death['DATE'] ?? ''),
            'death_sort_date' => $this->sortDate((string)($death['DATE'] ?? '')),
            'death_place' => $this->normalizePlace((string)($death['PLAC'] ?? '')),
            'primary_media' => $this->firstLevelPointer($lines, 'OBJE'),
            'events' => $this->eventsForBlock($id, 'person', $lines),
            'raw_gedcom' => implode("\n", $lines),
        ];
    }

    /**
     * @param list<string> $lines
     * @return array<string, mixed>
     */
    private function parseFamily(string $id, array $lines): array
    {
        $marriage = $this->subRecord($lines, 'MARR');

        return [
            'external_id' => $id,
            'husband_external_id' => $this->firstLevelPointer($lines, 'HUSB'),
            'wife_external_id' => $this->firstLevelPointer($lines, 'WIFE'),
            'children' => $this->firstLevelPointers($lines, 'CHIL'),
            'marriage_date_text' => (string)($marriage['DATE'] ?? ''),
            'marriage_sort_date' => $this->sortDate((string)($marriage['DATE'] ?? '')),
            'marriage_place' => $this->normalizePlace((string)($marriage['PLAC'] ?? '')),
            'events' => $this->eventsForBlock($id, 'family', $lines),
            'raw_gedcom' => implode("\n", $lines),
        ];
    }

    /**
     * @param list<string> $lines
     * @return array<string, mixed>
     */
    private function parseSource(string $id, array $lines): array
    {
        return [
            'external_id' => $id,
            'title' => $this->firstLevelValue($lines, 'TITL'),
            'reference_title' => $this->firstLevelValue($lines, 'REFT'),
            'church' => $this->firstLevelValue($lines, 'CHUR'),
            'place' => $this->normalizePlace($this->firstLevelValue($lines, 'PLAC')),
            'url' => $this->firstLevelValue($lines, 'TEXT'),
            'media_external_ids' => implode(',', $this->firstLevelPointers($lines, 'OBJE')),
            'raw_gedcom' => implode("\n", $lines),
        ];
    }

    /**
     * @param list<string> $lines
     * @return array<string, mixed>
     */
    private function parseMedia(string $id, array $lines): array
    {
        $fileName = $this->firstLevelValue($lines, 'FILE');

        return [
            'external_id' => $id,
            'title' => $this->firstLevelValue($lines, 'TITL'),
            'file_name' => $fileName,
            'file_extension' => strtolower((string)pathinfo($fileName, PATHINFO_EXTENSION)),
            'raw_gedcom' => implode("\n", $lines),
        ];
    }

    /**
     * @param list<string> $lines
     * @return array<int, array<string, mixed>>
     */
    private function eventsForBlock(string $parentId, string $parentType, array $lines): array
    {
        $events = [];
        foreach (['BIRT', 'CHR', 'DEAT', 'BURI', 'MARR'] as $tag) {
            $record = $this->subRecord($lines, $tag);
            if ($record === []) {
                continue;
            }

            $dateParts = $this->dateParts((string)($record['DATE'] ?? ''));

            $events[] = [
                'parent_type' => $parentType,
                'parent_external_id' => $parentId,
                'event_type' => $this->normalizeEventType($tag),
                'date_text' => (string)($record['DATE'] ?? ''),
                'date_year' => $dateParts['year'],
                'date_month' => $dateParts['month'],
                'date_day' => $dateParts['day'],
                'date_modifier' => $dateParts['modifier'],
                'sort_date' => $this->sortDate((string)($record['DATE'] ?? '')),
                'place' => $this->normalizePlace((string)($record['PLAC'] ?? '')),
                'place_original' => (string)($record['PLAC'] ?? ''),
                'latitude' => $this->coordinate((string)($record['LATI'] ?? '')),
                'longitude' => $this->coordinate((string)($record['LONG'] ?? '')),
                'description' => (string)($record['NOTE'] ?? ''),
                'source_external_id' => (string)($record['SOUR'] ?? ''),
            ];
        }

        return $events;
    }

    /**
     * @param list<string> $lines
     * @return array<string, string>
     */
    private function subRecord(array $lines, string $tag): array
    {
        $record = [];
        $inside = false;
        $mapContext = false;

        foreach ($lines as $line) {
            if (preg_match('/^1 ' . preg_quote($tag, '/') . '(?:\s+(.*))?$/', $line)) {
                $inside = true;
                $mapContext = false;
                continue;
            }
            if ($inside && str_starts_with($line, '1 ')) {
                break;
            }
            if (!$inside) {
                continue;
            }

            if (preg_match('/^2 ([A-Z_]+)\s*(.*)$/', $line, $match)) {
                $value = trim($match[2]);
                $record[$match[1]] = preg_match('/^@([^@]+)@$/', $value, $pointer) ? $pointer[1] : $value;
                $mapContext = false;
                continue;
            }

            if (preg_match('/^3 MAP\b/', $line)) {
                $mapContext = true;
                continue;
            }

            if ($mapContext && preg_match('/^4 (LATI|LONG)\s+(.+)$/', $line, $match)) {
                $record[$match[1]] = trim($match[2]);
            }
        }

        return $record;
    }

    /**
     * @param list<string> $lines
     */
    private function firstLevelValue(array $lines, string $tag): string
    {
        foreach ($lines as $line) {
            if (preg_match('/^1 ' . preg_quote($tag, '/') . '\s+(.+)$/', $line, $match)) {
                return trim($match[1]);
            }
        }

        return '';
    }

    /**
     * @param list<string> $lines
     */
    private function firstLevelPointer(array $lines, string $tag): string
    {
        $pointers = $this->firstLevelPointers($lines, $tag);
        return $pointers[0] ?? '';
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function firstLevelPointers(array $lines, string $tag): array
    {
        $pointers = [];
        foreach ($lines as $line) {
            if (preg_match('/^1 ' . preg_quote($tag, '/') . '\s+@([^@]+)@$/', $line, $match)) {
                $pointers[] = $match[1];
            }
        }

        return $pointers;
    }

    private function normalizeName(string $name): string
    {
        return trim(str_replace('/', '', $name));
    }

    private function extractGivenName(string $name): string
    {
        return trim((string)preg_replace('/\/.*$/', '', $name));
    }

    private function extractSurname(string $name): string
    {
        if (preg_match('/\/([^\/]+)\//', $name, $match)) {
            return trim($match[1]);
        }

        return '';
    }

    private function normalizePlace(string $place): string
    {
        $parts = array_filter(array_map('trim', explode(',', $place)), static fn (string $part): bool => $part !== '');
        return implode(', ', $parts);
    }

    /**
     * @return array{year: int, month: int, day: int, modifier: string}
     */
    private function dateParts(string $date): array
    {
        $date = trim($date);
        $modifier = '';
        if (preg_match('/^(ABT|ABOUT|BEF|AFT|BET|FROM|TO|CAL|EST)\b\s*(.*)$/i', $date, $match)) {
            $modifier = strtolower($match[1]);
            $date = trim($match[2]);
        }

        $year = 0;
        $month = 0;
        $day = 0;
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $date, $match)) {
            $day = (int)$match[1];
            $month = (int)$match[2];
            $year = (int)$match[3];
        } elseif (preg_match('/^(\d{1,2}) ([A-Z]{3}) (\d{4})$/i', $date, $match)) {
            $months = ['JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6, 'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12];
            $day = (int)$match[1];
            $month = $months[strtoupper($match[2])] ?? 0;
            $year = (int)$match[3];
        } elseif (preg_match('/(\d{4})/', $date, $match)) {
            $year = (int)$match[1];
        }

        return [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'modifier' => $modifier,
        ];
    }

    private function coordinate(string $coordinate): float
    {
        $coordinate = trim($coordinate);
        if ($coordinate === '') {
            return 0.0;
        }

        $sign = 1;
        if (preg_match('/^[SW]/i', $coordinate)) {
            $sign = -1;
        }

        return $sign * (float)preg_replace('/^[NSEW]\s*/i', '', $coordinate);
    }

    private function normalizeEventType(string $tag): string
    {
        return match (strtoupper($tag)) {
            'BIRT' => 'birth',
            'CHR' => 'christening',
            'DEAT' => 'death',
            'BURI' => 'burial',
            'MARR' => 'marriage',
            default => strtolower($tag),
        };
    }

    private function sortDate(string $date): int
    {
        if ($date === '') {
            return 0;
        }

        if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', $date, $match)) {
            return (int)sprintf('%04d%02d%02d', (int)$match[3], (int)$match[2], (int)$match[1]);
        }
        if (preg_match('/(\d{1,2}) ([A-Z]{3}) (\d{4})/i', $date, $match)) {
            $months = ['JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6, 'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12];
            return (int)sprintf('%04d%02d%02d', (int)$match[3], $months[strtoupper($match[2])] ?? 1, (int)$match[1]);
        }
        if (preg_match('/(\d{4})/', $date, $match)) {
            return (int)($match[1] . '0000');
        }

        return 0;
    }
}
