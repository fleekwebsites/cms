<?php

namespace App\Support;

use Illuminate\Support\Collection;

class RemoteFetchResult
{
    /**
     * @param  Collection<int, RemoteRecord>  $items
     */
    public function __construct(
        public bool $reachable,
        public Collection $items,
        public ?string $message = null,
    ) {}

    public static function unreachable(?string $message = null): self
    {
        return new self(
            reachable: false,
            items: collect(),
            message: $message ?? 'The remote site is unreachable. Check the connection and try again.',
        );
    }

    /**
     * @param  Collection<int, RemoteRecord>  $items
     */
    public static function success(Collection $items): self
    {
        return new self(
            reachable: true,
            items: $items,
        );
    }
}
