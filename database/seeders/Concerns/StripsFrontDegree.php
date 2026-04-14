<?php

namespace Database\Seeders\Concerns;

trait StripsFrontDegree
{
    /**
     * Strip known academic front-degree prefixes from a name.
     *
     * @return array{0: ?string, 1: string} [degreeFront, cleanName]
     */
    private function extractFrontDegree(string $name): array
    {
        // Longest prefixes first to avoid partial matches (e.g. "Prof. Dr." before "Prof.")
        $prefixes = ['Prof. Dr.', 'Prof.', 'Dr.', 'Drs.', 'Dra.', 'Ir.'];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($name, $prefix.' ')) {
                return [$prefix, trim(substr($name, strlen($prefix)))];
            }
        }

        return [null, $name];
    }

    private function stripFrontDegree(string $name): string
    {
        [, $clean] = $this->extractFrontDegree($name);

        return $clean;
    }
}
