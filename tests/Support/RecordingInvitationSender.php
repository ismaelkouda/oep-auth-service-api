<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\InvitationSender;
use App\Models\User;
use RuntimeException;

final class RecordingInvitationSender implements InvitationSender
{
    /** @var array<int, array{email: string, link: string}> */
    public array $sent = [];

    public function send(User $user, string $link): void
    {
        $this->sent[] = ['email' => (string) $user->email, 'link' => $link];
    }

    public function lastLink(): string
    {
        $last = $this->sent[array_key_last($this->sent)] ?? throw new RuntimeException('Aucune invitation envoyée.');

        return $last['link'];
    }

    public function lastToken(): string
    {
        parse_str((string) parse_url($this->lastLink(), PHP_URL_QUERY), $query);

        return (string) $query['token'];
    }
}
