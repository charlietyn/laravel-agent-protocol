<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ronu\LaravelAgentProtocol\DTO\FieldDescriptor;
use Ronu\LaravelAgentProtocol\DTO\OperationDescriptor;
use Ronu\LaravelAgentProtocol\DTO\ResourceDescriptor;
use Ronu\LaravelAgentProtocol\Metadata\Readiness\ReadinessScorer;
use Ronu\LaravelAgentProtocol\Security\OperationRiskClassifier;

final class SecurityAndReadinessTest extends TestCase
{
    public function test_risk_classifier_marks_destructive_operations(): void
    {
        $classifier = new OperationRiskClassifier;

        self::assertSame('low', $classifier->classify('query', 'GET'));
        self::assertSame('high', $classifier->classify('delete', 'DELETE'));
        self::assertSame('critical', $classifier->classify('force_delete', 'DELETE'));
    }

    public function test_readiness_scorer_marks_incomplete_resources_as_documentation_only(): void
    {
        $resource = new ResourceDescriptor(
            key: 'security.user',
            module: 'security',
            name: 'user',
            endpoint: '/api/security/users',
            fields: [new FieldDescriptor('email')],
            operations: [new OperationDescriptor('query', 'GET', '/api/security/users', 'Query users.', risk: 'low')],
        );

        $readiness = (new ReadinessScorer)->score($resource);

        self::assertSame('documentation_only', $readiness['status']);
    }
}
