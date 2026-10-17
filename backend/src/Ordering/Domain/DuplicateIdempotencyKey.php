<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Domain;

use DomainException;

final class DuplicateIdempotencyKey extends DomainException {}
