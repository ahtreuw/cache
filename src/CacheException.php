<?php declare(strict_types=1);

namespace Cache;

use Exception;

class CacheException extends Exception implements \Psr\SimpleCache\CacheException
{

}
