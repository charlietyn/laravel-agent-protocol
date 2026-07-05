<?php

declare(strict_types=1);

namespace Ronu\LaravelAgentProtocol\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class FakeModel extends Model
{
    public const MODEL = 'fake-user';

    public const RELATIONS = [];

    public const HIERARCHY_FIELD_ID = 'parent_id';

    public const columns = ['id', 'name', 'email', 'status', 'parent_id'];

    protected $table = 'fake_users';

    protected $fillable = ['name', 'email', 'status', 'parent_id'];

    protected $casts = [
        'id' => 'integer',
        'parent_id' => 'integer',
    ];
}
