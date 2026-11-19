<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class EmployeeRoster
{
    public function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('Roster file is missing or unreadable. Supply a private CSV file.');
        }

        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw new InvalidArgumentException('Cannot read the roster file.');
        }
        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if (! $header) {
                throw new InvalidArgumentException('Roster CSV is empty.');
            }
            $header = array_map(fn ($value) => mb_strtolower(trim((string) $value)), $header);
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            $required = ['name', 'email', 'phone', 'primary_team', 'additional_teams'];
            if (count($header) !== count(array_unique($header)) || array_diff($required, $header)) {
                throw new InvalidArgumentException('CSV must contain unique name,email,phone,primary_team,additional_teams columns.');
            }

            $rows = [];
            $seen = [];
            $line = 1;
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null]) {
                    continue;
                }
                if (count($values) !== count($header)) {
                    throw new InvalidArgumentException("Row {$line}: column count does not match the header.");
                }
                $row = array_map(fn ($value) => trim((string) $value), array_combine($header, $values));
                $row['email'] = mb_strtolower($row['email']);
                $validator = Validator::make($row, ['name' => 'required|string|max:160', 'email' => 'required|email|max:255', 'phone' => 'required|string', 'primary_team' => 'required|string']);
                if ($validator->fails()) {
                    throw new InvalidArgumentException("Row {$line}: invalid ".implode(', ', $validator->errors()->keys()).'.');
                }
                if (isset($seen[$row['email']])) {
                    throw new InvalidArgumentException("Row {$line}: duplicate normalized email in roster.");
                }
                $seen[$row['email']] = true;
                try {
                    $row['phone'] = BangladeshPhone::normalize($row['phone']);
                } catch (InvalidArgumentException) {
                    throw new InvalidArgumentException("Row {$line}: invalid phone format.");
                }
                $row['primary_slug'] = $this->teamSlug($row['primary_team'], $line);
                $row['team_slugs'] = [$row['primary_slug']];
                foreach (array_filter(explode('|', $row['additional_teams'])) as $team) {
                    $row['team_slugs'][] = $this->teamSlug($team, $line);
                }
                $row['team_slugs'] = array_values(array_unique($row['team_slugs']));
                $rows[] = $row;
            }
            if (! $rows) {
                throw new InvalidArgumentException('Roster CSV contains no employees.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function teamSlug(string $name, int $line): string
    {
        return match (mb_strtolower(trim($name))) {
            'admin' => 'admin',
            'operations' => 'operations',
            'marketing', 'digital marketing' => 'marketing',
            'graphics' => 'graphics',
            'development', 'developer' => 'developer',
            default => throw new InvalidArgumentException("Row {$line}: unknown team name."),
        };
    }
}
