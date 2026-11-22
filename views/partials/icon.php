<?php

declare(strict_types=1);

function ui_icon(string $name): string
{
    $path = match ($name) {
        'questions' => 'M4 5h16v11H9l-5 4zM8 9h8m-8 3h5',
        'search' => 'M16 16l4 4M18 11a7 7 0 1 1-14 0 7 7 0 0 1 14 0z',
        'ask' => 'M12 5v14M5 12h14',
        'topics' => 'm12 3 8 4.5v9L12 21l-8-4.5v-9zM4 7.5l8 4.5 8-4.5M12 12v9M8 5.25l8 4.5',
        'members' => 'M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2m18 0v-2a4 4 0 0 0-3-4M13 8a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0zm4-3a3.5 3.5 0 0 1 0 6',
        'notifications' => 'M6 9a6 6 0 0 1 12 0v4l2 3H4l2-3zm4 11h4',
        default => '',
    };

    return '<svg class="ui-icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $path . '"/></svg>';
}
