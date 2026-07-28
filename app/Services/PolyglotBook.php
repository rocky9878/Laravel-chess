<?php

namespace App\Services;

final class PolyglotBook
{
    private const ENTRY_SIZE = 16;

    private $handle;

    private int $entryCount;

    public function __construct(string $path)
    {
        $this->handle = fopen($path, 'rb');
        $this->entryCount = intdiv(filesize($path), self::ENTRY_SIZE);
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    public function findMoves(int $polyglotHash): array
    {
        // binary search for the first matching key, then collect the contiguous run
        $lo = 0;
        $hi = $this->entryCount - 1;
        $start = null;

        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            $cmp = $this->compareUnsigned($this->readEntry($mid)['key'], $polyglotHash);

            if ($cmp === 0) {
                $start = $mid;
                $hi = $mid - 1; // keep searching left for the first match
            } elseif ($cmp < 0) {
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }

        if ($start === null) {
            return [];
        }

        $matches = [];
        for ($i = $start; $i < $this->entryCount; $i++) {
            $entry = $this->readEntry($i);
            if ($entry['key'] !== $polyglotHash) {
                break;
            }
            $matches[] = $entry;
        }

        return $matches;
    }

    private function readEntry(int $index): array
    {
        fseek($this->handle, $index * self::ENTRY_SIZE);
        $chunk = fread($this->handle, self::ENTRY_SIZE);

        return unpack('Jkey/nmove/nweight/Nlearn', $chunk);
    }

    /**
     * Polyglot keys are unsigned 64-bit; PHP ints are signed 64-bit, so any
     * key with the high bit set becomes negative. Flipping the sign bit on
     * both operands before a normal signed comparison maps unsigned ordering
     * onto PHP's native comparison correctly.
     */
    private function compareUnsigned(int $a, int $b): int
    {
        return ($a ^ PHP_INT_MIN) <=> ($b ^ PHP_INT_MIN);
    }
}
