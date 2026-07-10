<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\ProjectContext;

use Throwable;

final readonly class GraphifyLocalFileContextProvider implements ProjectContextProvider
{
    private const DEFAULT_DENY_TERMS = [
        '.env', 'password', 'passwd', 'secret', 'token', 'private key', 'private_key',
        'database credentials', 'db_password', 'access_key', 'refresh_token', 'api_key',
    ];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private array $config = [],
    ) {}

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false)
            && ($this->config['mode'] ?? 'local_file') === 'local_file';
    }

    public function health(): ProjectContextHealth
    {
        $source = $this->graphPath();
        $warnings = [];

        if (! (bool) ($this->config['enabled'] ?? false)) {
            $warnings[] = 'Graphify project context is disabled.';
        }

        if (($this->config['mode'] ?? 'local_file') !== 'local_file') {
            $warnings[] = 'Graphify provider is configured with an unsupported core mode. Only local_file is implemented in the core package.';
        }

        if (! is_file($source)) {
            $warnings[] = 'Graphify graph.json was not found.';
        } elseif (! is_readable($source)) {
            $warnings[] = 'Graphify graph.json is not readable.';
        }

        return new ProjectContextHealth(
            available: $this->enabled() && is_file($source) && is_readable($source),
            provider: 'graphify',
            source: $source,
            warnings: $warnings,
            metadata: [
                'mode' => $this->config['mode'] ?? 'local_file',
                'max_nodes' => $this->intConfig('max_nodes', 40),
                'max_edges' => $this->intConfig('max_edges', 80),
                'max_chars' => $this->intConfig('max_chars', 12000),
            ],
        );
    }

    public function query(ProjectContextQuery $query): ProjectContextResult
    {
        $query = $query->withConfigLimits($this->config);
        $health = $this->health();

        if (! $health->available) {
            return new ProjectContextResult(
                provider: 'graphify',
                source: $health->source ?? $this->graphPath(),
                warnings: $health->warnings,
                metadata: ['query' => $query],
            );
        }

        $source = $health->source ?? $this->graphPath();
        $warnings = [];

        try {
            $contents = file_get_contents($source);
        } catch (Throwable $exception) {
            return new ProjectContextResult(
                provider: 'graphify',
                source: $source,
                warnings: ['Could not read graphify graph: '.$exception->getMessage()],
                metadata: ['query' => $query],
            );
        }

        if (! is_string($contents) || trim($contents) === '') {
            return new ProjectContextResult(
                provider: 'graphify',
                source: $source,
                warnings: ['Graphify graph.json is empty.'],
                metadata: ['query' => $query],
            );
        }

        $maxChars = $query->maxChars;
        $data = json_decode($contents, true);
        if (! is_array($data)) {
            return new ProjectContextResult(
                provider: 'graphify',
                source: $source,
                warnings: ['Graphify graph.json is not valid JSON.'],
                metadata: ['query' => $query, 'json_error' => json_last_error_msg()],
            );
        }

        $denyTerms = $this->denyTerms();
        $tokens = $this->tokens($query);
        $nodes = $this->rankedMatches($this->nodeCandidates($data), $tokens, $denyTerms, $query->maxNodes, $maxChars, $warnings);
        $edges = $this->rankedMatches($this->edgeCandidates($data), $tokens, $denyTerms, $query->maxEdges, $maxChars, $warnings);

        return new ProjectContextResult(
            provider: 'graphify',
            source: $source,
            nodes: $nodes,
            edges: $edges,
            summaries: $this->summaries($nodes, $edges),
            trusted: false,
            warnings: array_values(array_unique($warnings)),
            metadata: [
                'query' => $query,
                'matched_nodes' => count($nodes),
                'matched_edges' => count($edges),
                'untrusted' => true,
            ],
        );
    }

    private function graphPath(): string
    {
        $basePath = (string) ($this->config['path'] ?? getcwd().DIRECTORY_SEPARATOR.'graphify-out');
        $graphJson = (string) ($this->config['graph_json'] ?? 'graph.json');

        return rtrim($basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$graphJson;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    private function nodeCandidates(array $data): array
    {
        return $this->candidatesFromKeys($data, ['nodes', 'vertices', 'entities', 'files', 'classes']);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    private function edgeCandidates(array $data): array
    {
        return $this->candidatesFromKeys($data, ['edges', 'relations', 'links', 'dependencies']);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $keys
     * @return array<int, array<string, mixed>>
     */
    private function candidatesFromKeys(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? $data['graph'][$key] ?? null;
            if (is_array($value)) {
                return $this->normalizeList($value);
            }
        }

        return $this->recursiveCandidateList($data);
    }

    /**
     * @param array<int|string, mixed> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeList(array $items): array
    {
        $normalized = [];
        foreach ($items as $key => $item) {
            if (is_array($item)) {
                $normalized[] = $this->normalizeCandidate($item, is_string($key) ? $key : null);
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    private function recursiveCandidateList(array $data, int $depth = 0): array
    {
        if ($depth > 4) {
            return [];
        }

        $candidates = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($this->looksLikeGraphItem($value)) {
                    $candidates[] = $this->normalizeCandidate($value, is_string($key) ? $key : null);
                }

                $candidates = [...$candidates, ...$this->recursiveCandidateList($value, $depth + 1)];
            }
        }

        return $candidates;
    }

    /**
     * @param array<string|int, mixed> $item
     */
    private function looksLikeGraphItem(array $item): bool
    {
        foreach (['id', 'name', 'label', 'type', 'path', 'file', 'source', 'target', 'from', 'to'] as $key) {
            if (array_key_exists($key, $item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string|int, mixed> $item
     * @return array<string, mixed>
     */
    private function normalizeCandidate(array $item, ?string $fallbackId = null): array
    {
        $allowed = ['id', 'name', 'label', 'type', 'kind', 'path', 'file', 'source', 'target', 'from', 'to', 'summary', 'description'];
        $candidate = [];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $item) && is_scalar($item[$key])) {
                $candidate[$key] = $this->truncate((string) $item[$key], 500);
            }
        }

        if (! isset($candidate['id']) && $fallbackId !== null) {
            $candidate['id'] = $fallbackId;
        }

        $candidate['excerpt'] = $this->truncate($this->stringify($item), 1000);

        return $candidate;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, string> $tokens
     * @param array<int, string> $denyTerms
     * @param array<int, string> $warnings
     * @return array<int, array<string, mixed>>
     */
    private function rankedMatches(array $items, array $tokens, array $denyTerms, int $limit, int $maxChars, array &$warnings): array
    {
        $ranked = [];
        $sensitiveSkipped = 0;

        foreach ($items as $item) {
            $text = mb_strtolower($this->stringify($item));
            if ($this->containsAny($text, $denyTerms)) {
                $sensitiveSkipped++;
                continue;
            }

            $score = $this->score($text, $tokens);
            if ($score <= 0 && $tokens !== []) {
                continue;
            }

            $item['_score'] = $score;
            $ranked[] = $item;
        }

        if ($sensitiveSkipped > 0) {
            $warnings[] = sprintf('Skipped %d graph item(s) because they matched sensitive deny terms.', $sensitiveSkipped);
        }

        usort($ranked, static fn (array $a, array $b): int => ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0));

        $result = [];
        $chars = 0;
        foreach ($ranked as $item) {
            unset($item['_score']);
            $encoded = json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $length = is_string($encoded) ? mb_strlen($encoded) : 0;
            if (count($result) >= $limit || ($chars + $length) > $maxChars) {
                break;
            }

            $chars += $length;
            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param array<int, array<string, mixed>> $edges
     * @return array<int, string>
     */
    private function summaries(array $nodes, array $edges): array
    {
        $summaries = [];
        foreach (array_slice($nodes, 0, 5) as $node) {
            $name = $node['name'] ?? $node['label'] ?? $node['id'] ?? null;
            if (is_string($name)) {
                $summaries[] = 'Matched node: '.$name;
            }
        }

        foreach (array_slice($edges, 0, 5) as $edge) {
            $source = $edge['source'] ?? $edge['from'] ?? null;
            $target = $edge['target'] ?? $edge['to'] ?? null;
            if (is_string($source) && is_string($target)) {
                $summaries[] = 'Matched edge: '.$source.' -> '.$target;
            }
        }

        return $summaries;
    }

    /**
     * @return array<int, string>
     */
    private function tokens(ProjectContextQuery $query): array
    {
        $text = implode(' ', array_filter([
            $query->question,
            $query->resource,
            $query->operation,
            implode(' ', $query->keywords),
        ], is_string(...)));

        $words = preg_split('/[^\pL\pN_\\.]+/u', mb_strtolower($text)) ?: [];
        $stopWords = ['the', 'and', 'for', 'con', 'que', 'como', 'para', 'del', 'los', 'las', 'una', 'uno', 'por', 'what', 'how'];

        return array_values(array_unique(array_filter(
            array_map('trim', $words),
            static fn (string $word): bool => mb_strlen($word) > 2 && ! in_array($word, $stopWords, true),
        )));
    }

    /**
     * @param array<int, string> $tokens
     */
    private function score(string $text, array $tokens): int
    {
        if ($tokens === []) {
            return 1;
        }

        $score = 0;
        foreach ($tokens as $token) {
            if (str_contains($text, $token)) {
                $score++;
            }
        }

        return $score;
    }

    /**
     * @param array<int, string> $needles
     */
    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function denyTerms(): array
    {
        $configured = $this->config['deny_sensitive_terms'] ?? self::DEFAULT_DENY_TERMS;

        return is_array($configured)
            ? array_values(array_filter(array_map(
                static fn (mixed $term): string => is_scalar($term) ? mb_strtolower((string) $term) : '',
                $configured,
            )))
            : self::DEFAULT_DENY_TERMS;
    }

    private function intConfig(string $key, int $default): int
    {
        $value = $this->config[$key] ?? $default;

        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }

    private function stringify(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : '';
    }

    private function truncate(string $value, int $limit): string
    {
        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit).'…' : $value;
    }
}
