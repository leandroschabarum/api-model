<?php

namespace Leandro\ApiModel;

use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\JsonEncodingException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Concerns\HasEvents;
use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use Illuminate\Database\Eloquent\Concerns\HidesAttributes;
use Illuminate\Database\Eloquent\Concerns\GuardsAttributes;
use Illuminate\Database\Eloquent\Concerns\HasGlobalScopes;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Support\Traits\ForwardsCalls;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Broadcasting\HasBroadcastChannel;
use Illuminate\Contracts\Routing\UrlRoutable;
use ArrayAccess;
use JsonSerializable;
use Exception;
use LogicException;
use InvalidArgumentException;
use ReturnTypeWillChange;

use Illuminate\Support\Str;
use Illuminate\Http\Client\Response as ApiResponse;
use ReflectionClass;

abstract class ApiModel implements Arrayable, ArrayAccess, HasBroadcastChannel, Jsonable, JsonSerializable, UrlRoutable
{}
