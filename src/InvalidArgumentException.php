<?php declare(strict_types=1);

namespace Cache;

class InvalidArgumentException extends CacheException implements \Psr\SimpleCache\InvalidArgumentException
{

}
