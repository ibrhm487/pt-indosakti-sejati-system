<?php

namespace App\Services;

class LogReader
{
    public function path(): string
    {
        return storage_path('logs/laravel.log');
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    public function size(): int
    {
        return $this->exists() ? (int) filesize($this->path()) : 0;
    }

    public function lastModified(): ?string
    {
        return $this->exists() ? date(DATE_ATOM, (int) filemtime($this->path())) : null;
    }

    /**
     * Read the most recent chunk of the log and parse it into entries.
     *
     * @return array<int, array{timestamp: string, environment: string, level: string, message: string}>
     */
    public function entries(int $maxBytes = 2097152): array
    {
        if (! $this->exists()) {
            return [];
        }

        $size = $this->size();
        $handle = fopen($this->path(), 'rb');

        if ($handle === false) {
            return [];
        }

        if ($size > $maxBytes) {
            fseek($handle, $size - $maxBytes);
        }

        $content = (string) stream_get_contents($handle);
        fclose($handle);

        if ($size > $maxBytes) {
            $firstBreak = strpos($content, "\n");
            $content = $firstBreak !== false ? substr($content, $firstBreak + 1) : '';
        }

        $content = ltrim($content, "\r\n");
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

        $entries = [];
        $pattern = '/^\[(?<timestamp>[^\]]+)\]\s+(?<environment>[^\.]+)\.(?<level>[A-Z]+):\s?(?<message>.*)$/';

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            if (preg_match($pattern, $line, $matches)) {
                $entries[] = [
                    'timestamp' => $matches['timestamp'],
                    'environment' => $matches['environment'],
                    'level' => strtolower($matches['level']),
                    'message' => $matches['message'],
                ];

                continue;
            }

            if ($entries !== []) {
                $entries[count($entries) - 1]['message'] .= "\n".$line;
            }
        }

        return $entries;
    }

    /**
     * @return array{timestamp: string, environment: string, level: string, message: string}|null
     */
    public function lastError(): ?array
    {
        foreach (array_reverse($this->entries()) as $entry) {
            if (in_array($entry['level'], ['error', 'critical', 'alert', 'emergency'], true)) {
                return $entry;
            }
        }

        return null;
    }
}
