# End-To-End Example

This example shows the intended flow from backend metadata to an agent query.

## Backend Source

```php
final class User extends Model
{
    protected $fillable = ['name', 'email', 'status', 'department_id'];
}
```

```php
final class UserRequest
{
    public function getAvailableScenarios(): array
    {
        return ['query', 'create', 'update'];
    }

    public function getRulesForScenario(string $scenario): array
    {
        return [
            'status' => ['nullable', 'in:active,inactive,suspended'],
            'department_id' => ['nullable', 'integer'],
        ];
    }
}
```

## ADP Configuration

```php
'resources' => [
    'security.user' => [
        'module' => 'security',
        'model' => App\Models\User::class,
        'request' => App\Http\Requests\UserRequest::class,
        'endpoint' => '/api/security/users',
        'fields' => [
            'status' => [
                'label' => 'User status',
                'description' => 'Lifecycle state of the account.',
                'type' => 'enum',
                'enum_values' => [
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'inactive', 'label' => 'Inactive'],
                    ['value' => 'suspended', 'label' => 'Suspended'],
                ],
            ],
        ],
    ],
],

'reference_tables' => [
    'departments' => [
        'model' => App\Models\Department::class,
        'resource' => 'hr.department',
        'fields' => ['id', 'name'],
        'lookup_field' => 'name',
        'foreign_keys' => ['department_id'],
        'max_records' => 100,
    ],
],
```

## Compiled Field Metadata

```json
{
  "name": "department_id",
  "type": "foreign_key",
  "label": "Department Id",
  "reference": {
    "resource": "hr.department",
    "lookup_field": "name",
    "display_fields": ["id", "name"],
    "inline_values": [{ "id": 7, "name": "Sales" }],
    "complete": true
  }
}
```

## Agent Interaction

User prompt:

```text
Show active users from Sales.
```

The agent reads `security.user`, sees `status=active`, resolves `Sales` to
`department_id=7` from inline reference values, and calls the backend with the
published filter syntax:

```text
GET /api/security/users?oper=status|=|active;department_id|=|7
```

The API still owns authorization, tenant scopes, validation and execution.
ADP only supplied the metadata needed to choose the correct operation.
